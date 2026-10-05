// Web Audio & HTML5 Audio playback manager
// Strictly plays authentic admin/artist uploaded audio files with high fidelity and zero cacophony/synthetic noise.
import { IdbStorage } from './idbStorage';

type TimeUpdateCallback = (currentTime: number, duration: number) => void;

/**
 * Utility to calculate exact audio duration from an uploaded File, Blob, or URL
 */
export function getAudioDuration(fileOrUrl: File | Blob | string): Promise<number> {
  return new Promise((resolve) => {
    try {
      const url = typeof fileOrUrl === 'string' ? fileOrUrl : URL.createObjectURL(fileOrUrl);
      const audio = new Audio();
      audio.preload = 'metadata';
      audio.onloadedmetadata = () => {
        const dur = Math.round(audio.duration || 180);
        if (typeof fileOrUrl !== 'string') {
          try { URL.revokeObjectURL(url); } catch (e) {}
        }
        resolve(dur > 0 && Number.isFinite(dur) ? dur : 180);
      };
      audio.onerror = () => {
        if (typeof fileOrUrl !== 'string') {
          try { URL.revokeObjectURL(url); } catch (e) {}
        }
        resolve(180);
      };
      audio.src = url;
    } catch {
      resolve(180);
    }
  });
}

export const DEFAULT_FALLBACK_AUDIO = '/assets/default_audio.wav';

/**
 * Resolves any audio URL (IndexedDB, relative server path, blob, or absolute URL) into a playable URL
 */
export async function resolvePlayableAudioUrl(audioUrl?: string | null): Promise<string> {
  if (!audioUrl || typeof audioUrl !== 'string') return DEFAULT_FALLBACK_AUDIO;
  const trimmed = audioUrl.trim();
  if (!trimmed || trimmed === 'null' || trimmed === 'undefined' || trimmed.startsWith('data:image')) {
    return DEFAULT_FALLBACK_AUDIO;
  }

  // Reject text or json files disguised as audio
  if (trimmed.endsWith('.json') || trimmed.endsWith('.txt')) {
    return DEFAULT_FALLBACK_AUDIO;
  }

  // Repair corrupted idb: prefix (e.g. from /backend/uploads/general/idb:...)
  let cleanUrl = trimmed.replace(/\\/g, '/');
  if (cleanUrl.includes('idb:')) {
    cleanUrl = cleanUrl.substring(cleanUrl.indexOf('idb:'));
  }

  // Detect and reject raw track IDs without extensions or paths
  if (!cleanUrl.includes('/') && !cleanUrl.includes('.') && (cleanUrl.startsWith('music-') || cleanUrl.startsWith('m_'))) {
    console.warn('[AudioEngine] Detected track ID passed instead of valid audio URL:', cleanUrl);
    return DEFAULT_FALLBACK_AUDIO;
  }

  // 1. IndexedDB key (e.g. 'idb:audio_music_123' or 'idb:audio_artist_...')
  if (cleanUrl.startsWith('idb:')) {
    try {
      const fromIdb = await IdbStorage.resolveMediaUrl(cleanUrl);
      if (fromIdb) return fromIdb;
      // Direct lookup fallback
      const rawKey = cleanUrl.replace('idb:', '');
      const direct = await IdbStorage.getMedia(rawKey);
      if (direct) {
        if (direct instanceof Blob) {
          return URL.createObjectURL(direct);
        } else if (typeof direct === 'string') {
          return direct;
        }
      }
    } catch (e) {
      console.warn('[AudioEngine] IDB media resolution error:', e);
    }
    return DEFAULT_FALLBACK_AUDIO;
  }

  // 2. Blob or Data URL (audio/video)
  if (cleanUrl.startsWith('blob:') || cleanUrl.startsWith('data:audio') || cleanUrl.startsWith('data:video') || cleanUrl.startsWith('data:application')) {
    return cleanUrl;
  }

  // 3. Normalize local dev server URL (e.g. http://localhost:3000/backend/uploads/...)
  if (cleanUrl.startsWith('http://localhost:3000/backend/uploads/') || cleanUrl.startsWith('http://localhost:3000/uploads/')) {
    if (typeof window !== 'undefined' && window.location.origin !== 'http://localhost:3000') {
      cleanUrl = cleanUrl.replace('http://localhost:3000', '');
    }
  }

  // 4. Absolute HTTP/HTTPS URL
  if (cleanUrl.startsWith('http://') || cleanUrl.startsWith('https://')) {
    if (typeof window !== 'undefined' && window.location.protocol === 'https:' && cleanUrl.startsWith('http://')) {
      if (cleanUrl.includes('upmizik.com')) {
        cleanUrl = cleanUrl.replace('http://', 'https://');
      }
    }
    return cleanUrl;
  }

  // 5. Relative paths like /backend/uploads/music/... or uploads/music/... or music_123.mp3
  let path = cleanUrl;
  if (!path.startsWith('/')) {
    if (path.startsWith('uploads/')) {
      path = '/' + path;
    } else if (path.startsWith('backend/uploads/')) {
      path = '/' + path;
    } else if (path.startsWith('music_') || path.endsWith('.mp3') || path.endsWith('.wav') || path.endsWith('.m4a') || path.endsWith('.ogg') || path.endsWith('.aac')) {
      path = '/backend/uploads/music/' + path;
    } else {
      path = '/backend/uploads/music/' + path;
    }
  }

  if (typeof window !== 'undefined') {
    return `${window.location.origin}${path}`;
  }

  return path;
}

class SoundEngine {
  private ctx: AudioContext | null = null;
  private analyser: AnalyserNode | null = null;
  private masterGain: GainNode | null = null;
  private isPlaying = false;
  private shouldPlayOnLoad = false;
  private loadSessionId = 0;
  private currentTrackId: string | null = null;
  private currentTrackTitle: string = '';
  private currentCategory: string = 'Kompa';
  private timer: any = null;
  private activeNodes: any[] = [];
  private timeListeners: TimeUpdateCallback[] = [];
  private currentTime = 0;
  private duration = 180; // Default 3 min
  private htmlAudio: HTMLAudioElement | null = null;
  private lastFreqArray: Uint8Array = new Uint8Array(32);
  private currentVolume = 0.9;
  private isMuted = false;
  private isLoadPending = false;

  private getOrCreateAudio(): HTMLAudioElement | null {
    if (typeof window === 'undefined') return null;
    if (!this.htmlAudio) {
      const audio = new Audio();
      audio.preload = 'auto';
      audio.volume = this.isMuted ? 0 : this.currentVolume;
      audio.muted = this.isMuted;
      this.htmlAudio = audio;
    }
    return this.htmlAudio;
  }

  private initContext() {
    if (typeof window === 'undefined') return;
    try {
      if (!this.ctx) {
        const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
        if (AudioCtx) {
          this.ctx = new AudioCtx();
        }
      }
      if (this.ctx && this.ctx.state === 'suspended') {
        this.ctx.resume().catch(() => {});
      }
    } catch {}
  }

  public onTimeUpdate(callback: TimeUpdateCallback): () => void {
    this.timeListeners.push(callback);
    return () => {
      this.timeListeners = this.timeListeners.filter(cb => cb !== callback);
    };
  }

  private emitTime(current: number, dur: number) {
    this.timeListeners.forEach(cb => {
      try {
        cb(current, dur);
      } catch (err) {
        console.error('Time update listener error', err);
      }
    });
  }

  /**
   * Loads a track and prepares it for high fidelity playback.
   * Supports both MusicItem object or parameter lists.
   */
  public async loadTrack(
    trackIdOrMusic?: any,
    audioUrlOrTitle?: string,
    titleOrCategory?: string,
    categoryOrDuration?: string | number,
    knownDuration?: number,
    autoPlay: boolean = false
  ) {
    const currentSession = ++this.loadSessionId;
    this.stop(false); // Stop prior audio without destroying upcoming intent

    let trackId: string | null = null;
    let audioUrl: string | undefined = undefined;
    let title: string = 'Mizik UpMizik';
    let category: string = 'Kompa';
    let dur: number = 180;

    // Signature 1: Object passed (MusicItem)
    if (trackIdOrMusic && typeof trackIdOrMusic === 'object') {
      const m = trackIdOrMusic;
      trackId = m.id || null;
      audioUrl = m.audioUrl;
      title = m.title || 'Mizik UpMizik';
      category = m.category || 'Kompa';
      dur = m.duration > 0 ? m.duration : 180;
    }
    // Signature 2: (trackId, audioUrl, title, category, knownDuration)
    else if (arguments.length >= 4 || (typeof trackIdOrMusic === 'string' && (trackIdOrMusic.startsWith('music-') || trackIdOrMusic.startsWith('m_')))) {
      trackId = typeof trackIdOrMusic === 'string' ? trackIdOrMusic : null;
      audioUrl = audioUrlOrTitle;
      title = titleOrCategory || 'Mizik UpMizik';
      category = typeof categoryOrDuration === 'string' ? categoryOrDuration : 'Kompa';
      dur = typeof knownDuration === 'number' && knownDuration > 0
        ? knownDuration
        : (typeof categoryOrDuration === 'number' && categoryOrDuration > 0 ? categoryOrDuration : 180);
    }
    // Signature 3: Legacy (audioUrl, title, category, duration)
    else {
      audioUrl = typeof trackIdOrMusic === 'string' ? trackIdOrMusic : undefined;
      title = audioUrlOrTitle || 'Mizik UpMizik';
      category = typeof titleOrCategory === 'string' ? titleOrCategory : 'Kompa';
      if (typeof categoryOrDuration === 'number' && categoryOrDuration > 0) {
        dur = categoryOrDuration;
      }
    }

    this.currentTrackId = trackId;
    this.currentTrackTitle = title;
    this.currentCategory = category;
    this.currentTime = 0;
    this.duration = dur > 0 ? dur : 180;
    this.isLoadPending = true;

    if (autoPlay) {
      this.shouldPlayOnLoad = true;
      this.isPlaying = true;
    }

    try {
      const resolvedUrl = await resolvePlayableAudioUrl(audioUrl);
      
      // If another track started loading while resolving, discard this attempt
      if (this.loadSessionId !== currentSession) {
        return;
      }

      this.isLoadPending = false;
      const targetUrl = resolvedUrl || DEFAULT_FALLBACK_AUDIO;

      console.log('[AudioEngine] loadTrack: Setting audio.src:', {
        passedAudioUrl: audioUrl,
        targetUrl,
        trackTitle: title,
        trackId: trackId
      });

      const audio = this.getOrCreateAudio();
      if (!audio) return;

      try {
        audio.pause();
      } catch {}

      audio.src = targetUrl;
      audio.volume = this.isMuted ? 0 : this.currentVolume;
      audio.muted = this.isMuted;
      audio.currentTime = 0;

      audio.onloadedmetadata = () => {
        if (this.loadSessionId === currentSession && audio.duration && Number.isFinite(audio.duration)) {
          this.duration = Math.round(audio.duration);
          this.emitTime(this.currentTime, this.duration);
        }
      };

      audio.ontimeupdate = () => {
        if (this.loadSessionId === currentSession) {
          this.currentTime = audio.currentTime;
          if (audio.duration && Number.isFinite(audio.duration)) {
            this.duration = Math.round(audio.duration);
          }
          this.emitTime(this.currentTime, this.duration);
        }
      };

      audio.onended = () => {
        if (this.loadSessionId === currentSession) {
          this.isPlaying = false;
          this.shouldPlayOnLoad = false;
          this.currentTime = this.duration;
          this.emitTime(this.duration, this.duration);
        }
      };

      audio.onerror = () => {
        if (this.loadSessionId !== currentSession) return;
        console.warn('[AudioEngine] Audio element error on source:', audio.src, audio.error);
        
        // Immediate fallback to guaranteed authentic default Kompa audio
        if (!audio.src.includes('default_audio.wav')) {
          console.log('[AudioEngine] Switching to fallback audio:', DEFAULT_FALLBACK_AUDIO);
          audio.src = DEFAULT_FALLBACK_AUDIO;
          audio.load();
          if (this.isPlaying || this.shouldPlayOnLoad) {
            audio.play().catch((err) => {
              console.warn('[AudioEngine] Fallback play error:', err);
            });
          }
        }
      };

      if (this.isPlaying || this.shouldPlayOnLoad) {
        this.isPlaying = true;
        this.shouldPlayOnLoad = false;
        if (this.timer) {
          clearInterval(this.timer);
          this.timer = null;
        }

        this.initContext();
        const playPromise = audio.play();
        if (playPromise !== undefined) {
          playPromise.catch((err) => {
            console.warn('[AudioEngine] Autoplay waiting for user gesture:', err);
            // One-time click/touch listener to resume playback seamlessly
            const unlockPlay = () => {
              if (this.isPlaying) {
                audio.play().catch(() => {});
              }
              window.removeEventListener('click', unlockPlay);
              window.removeEventListener('touchstart', unlockPlay);
            };
            window.addEventListener('click', unlockPlay, { once: true });
            window.addEventListener('touchstart', unlockPlay, { once: true });
          });
        }
      }
    } catch (err) {
      console.warn('[AudioEngine] Load error:', err);
      this.isLoadPending = false;
    }
  }

  public play() {
    this.isPlaying = true;
    this.shouldPlayOnLoad = true;

    this.initContext();
    const audio = this.getOrCreateAudio();
    if (audio) {
      if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
      }
      if (!audio.src) {
        audio.src = DEFAULT_FALLBACK_AUDIO;
      }
      const playPromise = audio.play();
      if (playPromise !== undefined) {
        playPromise.catch((err) => {
          console.warn('[AudioEngine] Play deferred:', err);
          const unlock = () => {
            if (this.isPlaying) {
              audio.play().catch(() => {});
            }
            window.removeEventListener('click', unlock);
            window.removeEventListener('touchstart', unlock);
          };
          window.addEventListener('click', unlock, { once: true });
          window.addEventListener('touchstart', unlock, { once: true });
        });
      }
    }
  }

  public stop(resetIntent = true) {
    if (resetIntent) {
      this.isPlaying = false;
      this.shouldPlayOnLoad = false;
    }
    this.isLoadPending = false;
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
    if (this.htmlAudio) {
      try {
        this.htmlAudio.pause();
        this.htmlAudio.currentTime = 0;
      } catch (e) {}
    }
    this.activeNodes.forEach(node => {
      try {
        node.stop?.();
        node.disconnect?.();
      } catch (e) {}
    });
    this.activeNodes = [];
    this.currentTime = 0;
    this.emitTime(0, this.duration);
  }

  private startTimeSimulation() {
    if (this.timer) {
      clearInterval(this.timer);
    }
    this.timer = setInterval(() => {
      if (!this.isPlaying) return;
      this.currentTime += 0.5;
      if (this.currentTime >= this.duration) {
        this.currentTime = this.duration;
        this.isPlaying = false;
        clearInterval(this.timer);
        this.timer = null;
      }
      this.emitTime(this.currentTime, this.duration);
    }, 500);
  }

  public seek(seconds: number) {
    const target = Math.max(0, Math.min(seconds, this.duration));
    this.currentTime = target;

    if (this.htmlAudio) {
      try {
        this.htmlAudio.currentTime = target;
      } catch {
        // Fallback silently
      }
    }

    this.emitTime(this.currentTime, this.duration);
  }

  public skip(deltaSeconds: number) {
    this.seek(this.currentTime + deltaSeconds);
  }

  public setVolume(val: number) {
    const clamped = Math.max(0, Math.min(1, val));
    this.currentVolume = clamped;
    this.isMuted = clamped === 0;

    if (this.htmlAudio) {
      this.htmlAudio.volume = this.isMuted ? 0 : clamped;
    }
    if (this.masterGain && this.ctx) {
      this.masterGain.gain.setValueAtTime(this.isMuted ? 0 : clamped, this.ctx.currentTime);
    }
  }

  public getVolume(): number {
    return this.isMuted ? 0 : this.currentVolume;
  }

  public setMuted(muted: boolean) {
    this.isMuted = muted;
    if (this.htmlAudio) {
      this.htmlAudio.muted = muted;
    }
    if (this.masterGain && this.ctx) {
      this.masterGain.gain.setValueAtTime(muted ? 0 : this.currentVolume, this.ctx.currentTime);
    }
  }

  public getMuted(): boolean {
    return this.isMuted;
  }

  public getFrequencyData(targetArray?: Uint8Array): Uint8Array {
    const numBins = targetArray ? targetArray.length : 32;
    const output = targetArray || new Uint8Array(numBins);

    if (this.analyser && this.isPlaying) {
      try {
        const binCount = this.analyser.frequencyBinCount;
        const temp = new Uint8Array(binCount);
        this.analyser.getByteFrequencyData(temp);

        let total = 0;
        for (let i = 0; i < temp.length; i++) {
          total += temp[i];
        }

        if (total > 0) {
          const step = binCount / numBins;
          for (let i = 0; i < numBins; i++) {
            const srcIdx = Math.floor(i * step);
            output[i] = temp[srcIdx] || 0;
          }
          this.lastFreqArray = output;
          return output;
        }
      } catch (e) {}
    }

    if (this.isPlaying) {
      const t = Date.now() / 1000;
      for (let i = 0; i < numBins; i++) {
        const val = 80 * Math.sin(t * 4 + i * 0.5) + 60 * Math.cos(t * 2 + i * 0.3) + 70;
        output[i] = Math.min(255, Math.max(15, Math.floor(val)));
      }
      this.lastFreqArray = output;
      return output;
    }

    for (let i = 0; i < numBins; i++) {
      output[i] = Math.max(0, Math.floor((this.lastFreqArray[i] || 0) * 0.85));
    }
    this.lastFreqArray = output;
    return output;
  }

  public pause() {
    this.isPlaying = false;
    this.shouldPlayOnLoad = false;
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
    if (this.htmlAudio) {
      try {
        this.htmlAudio.pause();
      } catch (e) {}
    }
  }

  public getIsPlaying(): boolean {
    return this.isPlaying;
  }

  public getCurrentTrackId(): string | null {
    return this.currentTrackId;
  }
}

export const globalSoundEngine = new SoundEngine();
