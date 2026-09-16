/**
 * UpMizik - Hostinger VPS (PHP + MySQL) Database & Storage Synchronization Service
 * 
 * Sèvis sa a konekte dirèkteman ak sèvè Hostinger VPS ou a atravè backend PHP & baz done MySQL,
 * epi li itilize senkronizasyon an tan reyèl ak evènman CustomEvent pou yon eksperyans ultra-rapid.
 */

import {
  ArtistUser,
  MusicItem,
  DonationItem,
  SocialPost,
  SocialPostComment,
  ArtistInboxMessage,
  ArchiveRecord,
  PubItem,
  RpaItem,
  PaymentSettingsConfig
} from '../types';
import { UpMizikAPI } from './apiService';
import { StorageService } from './storage';

export type Unsubscribe = () => void;

class HostingerSyncService {
  private listeners: Map<string, Set<(data: any) => void>> = new Map();
  private broadcastChannel: BroadcastChannel | null = null;
  private eventSource: EventSource | null = null;
  private autoRefetchTimer: any = null;
  private isRefetchingMusic = false;
  private isRefetchingArtists = false;
  private isRefetchingDonations = false;
  private tabId = 'tab_' + Math.random().toString(36).substring(2, 9);

  constructor() {
    if (typeof window !== 'undefined') {
      // 1. Koute evènman chanjman lokal
      window.addEventListener('upmizik_data_sync', (e: Event) => {
        const customEvent = e as CustomEvent<{ type: string; data: any }>;
        if (customEvent.detail) {
          this.notifySubscribers(customEvent.detail.type, customEvent.detail.data);
        }
      });

      // 2. BroadcastChannel pou senkronizasyon an dirèk ant tout onglet ouvè sou menm aparèy la
      if (typeof BroadcastChannel !== 'undefined') {
        try {
          this.broadcastChannel = new BroadcastChannel('upmizik_realtime_sync');
          this.broadcastChannel.onmessage = (event) => {
            if (event.data && event.data.sender !== this.tabId) {
              const { type, data } = event.data;
              if (type) {
                this.notifySubscribers(type, data);
                if (type === 'music' || type === 'music_update') {
                  this.fetchMusicAndNotify(false);
                }
              }
            }
          };
        } catch (e) {
          console.warn('[HostingerService] BroadcastChannel unavailable:', e);
        }
      }

      // 3. Koute Storage Event (fallback pou tout navigatè)
      window.addEventListener('storage', (e: StorageEvent) => {
        if (e.key === 'upmizik_music_broadcast') {
          this.fetchMusicAndNotify(false);
        }
      });

      // 4. Kòmanse koute stream SSE si disponib
      this.initRealtimeStream();
    }
  }

  /**
   * Kominikasyon an tan reyèl ak Server-Sent Events (SSE) sou sèvè a
   */
  public initRealtimeStream() {
    if (typeof window === 'undefined' || typeof EventSource === 'undefined') return;
    if (this.eventSource) return;

    try {
      const baseUrl = UpMizikAPI.getBaseUrl();
      const streamUrl = `${baseUrl}/stream.php`;
      const es = new EventSource(streamUrl);

      es.addEventListener('music_update', (event: MessageEvent) => {
        try {
          const parsed = JSON.parse(event.data);
          if (parsed && (parsed.count !== undefined || parsed.latest)) {
            this.fetchMusicAndNotify(true);
          }
        } catch {}
      });

      es.addEventListener('artists_update', () => {
        try {
          this.fetchArtistsAndNotify(true);
        } catch {}
      });

      es.addEventListener('donations_update', () => {
        try {
          this.fetchDonationsAndNotify(true);
        } catch {}
      });

      es.addEventListener('payment_settings_update', () => {
        try {
          this.fetchPaymentSettingsAndNotify(true);
        } catch {}
      });

      es.addEventListener('connected', () => {
        // SSE konekte avèk siksè
      });

      es.onerror = () => {
        // SSE ap eseye rekonekte otomatikman
      };

      this.eventSource = es;
    } catch (e) {
      console.warn('[HostingerService] SSE stream fallback:', e);
    }
  }

  private notifySubscribers(type: string, data: any) {
    const subs = this.listeners.get(type);
    if (subs) {
      subs.forEach((cb) => {
        try {
          cb(data);
        } catch (err) {
          console.warn(`[HostingerService] Listener error for ${type}:`, err);
        }
      });
    }
  }

  private emitChange(type: string, data: any) {
    this.notifySubscribers(type, data);
    if (typeof window !== 'undefined') {
      window.dispatchEvent(
        new CustomEvent('upmizik_data_sync', {
          detail: { type, data }
        })
      );
      if (this.broadcastChannel) {
        try {
          this.broadcastChannel.postMessage({
            type,
            sender: this.tabId,
            data
          });
        } catch {}
      }
    }
  }

  // ==========================================
  // ARTISTS (ATIS YO)
  // ==========================================
  async fetchArtists(): Promise<ArtistUser[] | null> {
    try {
      const apiArtists = await UpMizikAPI.getArtists();
      if (apiArtists && apiArtists.length > 0) {
        return apiArtists;
      }
      return StorageService.getArtists();
    } catch {
      return StorageService.getArtists();
    }
  }

  /**
   * Refetch atis yo sou sèvè Hostinger MySQL epi difize bay tout abòne yo si gen chanjman
   */
  async fetchArtistsAndNotify(forceNotify = false): Promise<ArtistUser[]> {
    if (this.isRefetchingArtists) {
      return StorageService.getArtists();
    }
    this.isRefetchingArtists = true;
    try {
      const serverArtists = await UpMizikAPI.getArtists();
      if (serverArtists && serverArtists.length > 0) {
        const localArtists = StorageService.getArtists();
        const serverMap = new Map(serverArtists.map((a) => [a.id, a]));
        const localMap = new Map(localArtists.map((a) => [a.id, a]));

        let hasDifferences = forceNotify || serverArtists.length !== localArtists.length;

        if (!hasDifferences) {
          for (const sa of serverArtists) {
            const la = localMap.get(sa.id);
            if (!la || la.status !== sa.status || la.stageName !== sa.stageName || la.registrationProofUrl !== sa.registrationProofUrl) {
              hasDifferences = true;
              break;
            }
          }
        }

        if (hasDifferences) {
          const merged: ArtistUser[] = [];
          const processedIds = new Set<string>();

          for (const sa of serverArtists) {
            const la = localMap.get(sa.id);
            if (la) {
              if (la.status && la.status !== 'pending' && sa.status === 'pending') {
                merged.push({ ...sa, ...la, status: la.status });
              } else {
                merged.push({ ...sa, ...la });
              }
            } else {
              merged.push(sa);
            }
            processedIds.add(sa.id);
          }

          for (const la of localArtists) {
            if (!processedIds.has(la.id)) {
              merged.push(la);
            }
          }

          StorageService.saveArtists(merged);
          this.emitChange('artists', merged);
          return merged;
        }
      }
      return StorageService.getArtists();
    } catch (e) {
      console.warn('[HostingerService] fetchArtistsAndNotify warn:', e);
      return StorageService.getArtists();
    } finally {
      this.isRefetchingArtists = false;
    }
  }

  async syncArtists(list: ArtistUser[]) {
    try {
      StorageService.saveArtists(list);
      this.emitChange('artists', list);
      // Senkronize an background ak Hostinger MySQL
      await UpMizikAPI.syncAllData({ artists: list });
    } catch (e) {
      console.warn('[HostingerService] Sync artists warn:', e);
    }
  }

  subscribeToArtists(callback: (artists: ArtistUser[]) => void): Unsubscribe {
    if (!this.listeners.has('artists')) {
      this.listeners.set('artists', new Set());
    }
    const set = this.listeners.get('artists')!;
    set.add(callback);

    // Voye eta aktyèl la imedyatman
    const current = StorageService.getArtists();
    callback(current);

    // Tcheke sèvè a an tan reyèl imedyatman an background
    this.fetchArtistsAndNotify().catch(() => {});

    return () => {
      set.delete(callback);
    };
  }

  async saveSingleArtist(artist: ArtistUser) {
    try {
      const all = StorageService.getArtists();
      const idx = all.findIndex((a) => a.id === artist.id);
      let updated: ArtistUser[];
      if (idx >= 0) {
        updated = [...all];
        updated[idx] = { ...updated[idx], ...artist };
      } else {
        updated = [artist, ...all];
      }
      StorageService.saveArtists(updated);
      this.emitChange('artists', updated);

      // Voye nan backend Hostinger MySQL (POST upsert avèk ON DUPLICATE KEY UPDATE)
      const res = await UpMizikAPI.registerArtist(artist);
      if (!res.success) {
        await UpMizikAPI.updateArtist(artist.id, artist);
      }

      setTimeout(() => {
        this.fetchArtistsAndNotify(true);
      }, 400);
    } catch (e) {
      console.warn('[HostingerService] saveSingleArtist warn:', e);
    }
  }

  async deleteArtist(artistId: string) {
    try {
      const result = StorageService.deleteArtist(artistId, true);
      this.emitChange('artists', StorageService.getArtists());
      return result;
    } catch (e) {
      console.warn('[HostingerService] deleteArtist warn:', e);
    }
  }

  async purgePendingArtists() {
    try {
      const purgedCount = StorageService.purgeAllPendingArtists();
      this.emitChange('artists', StorageService.getArtists());
      return purgedCount;
    } catch (e) {
      console.warn('[HostingerService] purgePendingArtists warn:', e);
    }
  }

  // ==========================================
  // MUSIC (MIZIK YO)
  // ==========================================
  async fetchMusic(): Promise<MusicItem[] | null> {
    try {
      const apiMusic = await UpMizikAPI.getMusics();
      if (apiMusic && apiMusic.length > 0) {
        return apiMusic;
      }
      return StorageService.getMusic();
    } catch {
      return StorageService.getMusic();
    }
  }

  /**
   * Refetch mizik yo sou sèvè Hostinger MySQL epi difize bay tout abòne yo si gen chanjman
   */
  async fetchMusicAndNotify(forceNotify = false): Promise<MusicItem[]> {
    if (this.isRefetchingMusic) {
      return StorageService.getMusic();
    }
    this.isRefetchingMusic = true;
    try {
      const serverMusic = await UpMizikAPI.getMusics();
      if (serverMusic && serverMusic.length > 0) {
        const localMusic = StorageService.getMusic();
        const serverMap = new Map(serverMusic.map(m => [m.id, m]));
        const localMap = new Map(localMusic.map(m => [m.id, m]));

        let hasDifferences = forceNotify || serverMusic.length !== localMusic.length;

        if (!hasDifferences) {
          for (const sm of serverMusic) {
            const lm = localMap.get(sm.id);
            if (!lm || lm.listens !== sm.listens || lm.title !== sm.title || lm.status !== sm.status) {
              hasDifferences = true;
              break;
            }
          }
        }

        if (hasDifferences) {
          const merged: MusicItem[] = [...serverMusic];
          for (const lm of localMusic) {
            if (!serverMap.has(lm.id)) {
              merged.push(lm);
              serverMap.set(lm.id, lm);
            }
          }
          StorageService.saveMusic(merged);
          this.emitChange('music', merged);
          return merged;
        }
      }
      return StorageService.getMusic();
    } catch (e) {
      console.warn('[HostingerService] fetchMusicAndNotify warn:', e);
      return StorageService.getMusic();
    } finally {
      this.isRefetchingMusic = false;
    }
  }

  /**
   * Mekanis Refetch Otomatik & Polling pou mizik ki fèk ajoute
   */
  startAutoRefetch(intervalMs = 8000): Unsubscribe {
    if (typeof window === 'undefined') return () => {};

    // 1. Kouri refetch inisyal pou mizik, atis, ak donasyon
    this.fetchMusicAndNotify();
    this.fetchArtistsAndNotify();
    this.fetchDonationsAndNotify();

    // 2. Enteval regilye
    if (this.autoRefetchTimer) {
      clearInterval(this.autoRefetchTimer);
    }
    this.autoRefetchTimer = setInterval(() => {
      this.fetchMusicAndNotify();
      this.fetchArtistsAndNotify();
      this.fetchDonationsAndNotify();
    }, intervalMs);

    // 3. Lè itilizatè a retounen sou paj la (Visibility / Focus / Online)
    const handleVisibility = () => {
      if (document.visibilityState === 'visible') {
        this.fetchMusicAndNotify();
        this.fetchArtistsAndNotify();
        this.fetchDonationsAndNotify();
      }
    };
    const handleFocus = () => {
      this.fetchMusicAndNotify();
      this.fetchArtistsAndNotify();
      this.fetchDonationsAndNotify();
    };
    const handleOnline = () => {
      this.fetchMusicAndNotify();
      this.fetchArtistsAndNotify();
      this.fetchDonationsAndNotify();
    };

    document.addEventListener('visibilitychange', handleVisibility);
    window.addEventListener('focus', handleFocus);
    window.addEventListener('online', handleOnline);

    return () => {
      if (this.autoRefetchTimer) {
        clearInterval(this.autoRefetchTimer);
        this.autoRefetchTimer = null;
      }
      document.removeEventListener('visibilitychange', handleVisibility);
      window.removeEventListener('focus', handleFocus);
      window.removeEventListener('online', handleOnline);
    };
  }

  async syncMusic(list: MusicItem[]) {
    try {
      StorageService.saveMusic(list);
      this.emitChange('music', list);
      await UpMizikAPI.syncAllData({ musics: list });
    } catch (e) {
      console.warn('[HostingerService] syncMusic warn:', e);
    }
  }

  subscribeToMusic(callback: (music: MusicItem[]) => void): Unsubscribe {
    if (!this.listeners.has('music')) {
      this.listeners.set('music', new Set());
    }
    const set = this.listeners.get('music')!;
    set.add(callback);

    const current = StorageService.getMusic();
    callback(current);

    return () => {
      set.delete(callback);
    };
  }

  async saveSingleMusic(item: MusicItem) {
    try {
      const all = StorageService.getMusic();
      const idx = all.findIndex((m) => m.id === item.id);
      let updated: MusicItem[];
      if (idx >= 0) {
        updated = [...all];
        updated[idx] = { ...updated[idx], ...item };
      } else {
        updated = [item, ...all];
      }
      StorageService.saveMusic(updated);
      this.emitChange('music', updated);

      // Notifikasyon Broadcast pou tout onglet ak fenèt
      if (typeof window !== 'undefined') {
        try {
          localStorage.setItem('upmizik_music_broadcast', Date.now().toString());
        } catch {}
      }

      // Asire atis la egziste sou sèvè a dabò pou evite foreign key constraint error
      if (item.artistId) {
        const localArtist = StorageService.getArtists().find((a) => a.id === item.artistId);
        if (localArtist) {
          await UpMizikAPI.registerArtist(localArtist).catch(() => {});
        }
      }

      // Voye nan Hostinger MySQL
      const res = await UpMizikAPI.addMusic(item);

      // Si gen siksè, re-senkronize pou asire tout IDs ak metadata yo aliyen ak baz done a
      if (res && res.success) {
        setTimeout(() => {
          this.fetchMusicAndNotify(true);
        }, 300);
      }
      return res;
    } catch (e) {
      console.warn('[HostingerService] saveSingleMusic warn:', e);
      return { success: false, message: 'Erè anrejistreman mizik' };
    }
  }

  async deleteMusic(musicId: string) {
    try {
      StorageService.deleteMusic(musicId);
      this.emitChange('music', StorageService.getMusic());
    } catch (e) {
      console.warn('[HostingerService] deleteMusic warn:', e);
    }
  }

  // ==========================================
  // DONATIONS (SIPÒ & DONASYON)
  // ==========================================
  async fetchDonations(): Promise<DonationItem[] | null> {
    try {
      const apiDonations = await UpMizikAPI.getDonations();
      if (apiDonations && apiDonations.length > 0) {
        return apiDonations;
      }
      return StorageService.getDonations();
    } catch {
      return StorageService.getDonations();
    }
  }

  /**
   * Refetch donasyon yo sou sèvè Hostinger MySQL epi difize bay tout abòne yo si gen chanjman
   */
  async fetchDonationsAndNotify(forceNotify = false): Promise<DonationItem[]> {
    if (this.isRefetchingDonations) {
      return StorageService.getDonations();
    }
    this.isRefetchingDonations = true;
    try {
      const serverDonations = await UpMizikAPI.getDonations();
      if (serverDonations && serverDonations.length > 0) {
        const localDonations = StorageService.getDonations();
        const serverMap = new Map(serverDonations.map((d) => [d.id, d]));
        const localMap = new Map(localDonations.map((d) => [d.id, d]));

        let hasDifferences = forceNotify || serverDonations.length !== localDonations.length;

        if (!hasDifferences) {
          for (const sd of serverDonations) {
            const ld = localMap.get(sd.id);
            if (!ld || ld.status !== sd.status || ld.amount !== sd.amount || ld.proofUrl !== sd.proofUrl) {
              hasDifferences = true;
              break;
            }
          }
        }

        if (hasDifferences) {
          const merged: DonationItem[] = [];
          const processedIds = new Set<string>();

          for (const sd of serverDonations) {
            const ld = localMap.get(sd.id);
            if (ld) {
              if (ld.status && ld.status !== 'pending' && sd.status === 'pending') {
                merged.push({ ...sd, ...ld, status: ld.status });
              } else {
                merged.push({ ...sd, ...ld });
              }
            } else {
              merged.push(sd);
            }
            processedIds.add(sd.id);
          }

          for (const ld of localDonations) {
            if (!processedIds.has(ld.id)) {
              merged.push(ld);
            }
          }

          StorageService.saveDonations(merged);
          this.emitChange('donations', merged);
          return merged;
        }
      }
      return StorageService.getDonations();
    } catch (e) {
      console.warn('[HostingerService] fetchDonationsAndNotify warn:', e);
      return StorageService.getDonations();
    } finally {
      this.isRefetchingDonations = false;
    }
  }

  subscribeToDonations(callback: (donations: DonationItem[]) => void): Unsubscribe {
    if (!this.listeners.has('donations')) {
      this.listeners.set('donations', new Set());
    }
    const set = this.listeners.get('donations')!;
    set.add(callback);

    const current = StorageService.getDonations();
    callback(current);

    // Tcheke sèvè a an tan reyèl imedyatman an background
    this.fetchDonationsAndNotify().catch(() => {});

    return () => {
      set.delete(callback);
    };
  }

  async saveSingleDonation(don: DonationItem) {
    try {
      const all = StorageService.getDonations();
      const idx = all.findIndex((d) => d.id === don.id);
      let updated: DonationItem[];
      if (idx >= 0) {
        updated = [...all];
        updated[idx] = { ...updated[idx], ...don };
      } else {
        updated = [don, ...all];
      }
      StorageService.saveDonations(updated);
      this.emitChange('donations', updated);

      // Asire atis la egziste nan baz done sèvè a anvan pou evite vyolasyon foreign key (fk_dons_artiste)
      if (don.artistId) {
        const localArtist = StorageService.getArtists().find((a) => a.id === don.artistId);
        if (localArtist) {
          await UpMizikAPI.registerArtist(localArtist).catch(() => {});
        }
      }

      // Voye nan Hostinger MySQL
      await UpMizikAPI.submitDonation(don);

      setTimeout(() => {
        this.fetchDonationsAndNotify(true);
      }, 400);
    } catch (e) {
      console.warn('[HostingerService] saveSingleDonation warn:', e);
    }
  }

  async deleteDonation(id: string) {
    try {
      const all = StorageService.getDonations().filter((d) => d.id !== id);
      StorageService.saveDonations(all);
      this.emitChange('donations', all);
    } catch (e) {
      console.warn('[HostingerService] deleteDonation warn:', e);
    }
  }

  async purgePendingDonations() {
    try {
      const purgedCount = StorageService.purgeAllPendingDonations();
      this.emitChange('donations', StorageService.getDonations());
      return purgedCount;
    } catch (e) {
      console.warn('[HostingerService] purgePendingDonations warn:', e);
    }
  }

  // ==========================================
  // SOCIAL POSTS & COMMENTS (UPMIZIK SOCIAL)
  // ==========================================
  async fetchSocialPosts(): Promise<SocialPost[] | null> {
    return StorageService.getSocialPosts();
  }

  async syncSocialPosts(list: SocialPost[]) {
    try {
      StorageService.saveSocialPosts(list);
      this.emitChange('social_posts', list);
    } catch (e) {
      console.warn('[HostingerService] syncSocialPosts warn:', e);
    }
  }

  subscribeToSocialPosts(callback: (posts: SocialPost[]) => void): Unsubscribe {
    if (!this.listeners.has('social_posts')) {
      this.listeners.set('social_posts', new Set());
    }
    const set = this.listeners.get('social_posts')!;
    set.add(callback);

    const current = StorageService.getSocialPosts();
    callback(current);

    return () => {
      set.delete(callback);
    };
  }

  async saveSinglePost(post: SocialPost) {
    try {
      const all = StorageService.getSocialPosts();
      const idx = all.findIndex((p) => p.id === post.id);
      let updated: SocialPost[];
      if (idx >= 0) {
        updated = [...all];
        updated[idx] = { ...updated[idx], ...post };
      } else {
        updated = [post, ...all];
      }
      StorageService.saveSocialPosts(updated);
      this.emitChange('social_posts', updated);
    } catch (e) {
      console.warn('[HostingerService] saveSinglePost warn:', e);
    }
  }

  async deleteSinglePost(postId: string) {
    try {
      const all = StorageService.getSocialPosts().filter((p) => p.id !== postId);
      StorageService.saveSocialPosts(all);
      this.emitChange('social_posts', all);
    } catch (e) {
      console.warn('[HostingerService] deleteSinglePost warn:', e);
    }
  }

  async saveSingleComment(comment: SocialPostComment) {
    try {
      StorageService.addSocialPostComment(comment.postId, {
        authorName: comment.authorName,
        content: comment.content,
        authorAvatar: comment.authorAvatar
      });
      this.emitChange('social_posts', StorageService.getSocialPosts());
    } catch (e) {
      console.warn('[HostingerService] saveSingleComment warn:', e);
    }
  }

  async fetchCommentsForPost(postId: string): Promise<SocialPostComment[] | null> {
    try {
      return StorageService.getSocialPostComments(postId);
    } catch {
      return [];
    }
  }

  // ==========================================
  // INBOX NOTIFICATIONS (NOTIFIKASYON ATIS)
  // ==========================================
  async saveInboxMessage(msg: ArtistInboxMessage) {
    try {
      if (!msg || !msg.id) return;
      const allMessages = StorageService.getArtistInboxMessages();
      const alreadyExists = allMessages.some(m => m.id === msg.id);
      if (!alreadyExists) {
        StorageService.saveArtistInboxMessages([msg, ...allMessages]);
      }
      this.emitChange('inbox', msg);
    } catch (e) {
      console.warn('[HostingerService] saveInboxMessage warn:', e);
    }
  }

  // ==========================================
  // ARCHIVES (ACHIV CHAK MWA)
  // ==========================================
  async syncArchives(list: ArchiveRecord[]) {
    try {
      StorageService.saveArchives(list);
      this.emitChange('archives', list);
    } catch (e) {
      console.warn('[HostingerService] syncArchives warn:', e);
    }
  }

  // ==========================================
  // RPA (RIBRIK POUSE ATIS)
  // ==========================================
  async fetchRpa(): Promise<RpaItem[] | null> {
    try {
      const apiRpa = await UpMizikAPI.getRpa();
      if (apiRpa && apiRpa.length > 0) {
        return apiRpa;
      }
      return StorageService.getRpa();
    } catch {
      return StorageService.getRpa();
    }
  }

  async syncRpa(list: RpaItem[]) {
    try {
      StorageService.saveRpa(list);
      this.emitChange('rpa', list);
      await UpMizikAPI.syncAllData({ rpa: list });
    } catch (e) {
      console.warn('[HostingerService] syncRpa warn:', e);
    }
  }

  async saveSingleRpa(item: RpaItem) {
    try {
      const all = StorageService.getRpa();
      const idx = all.findIndex((r) => r.id === item.id);
      let updated: RpaItem[];
      if (idx >= 0) {
        updated = [...all];
        updated[idx] = { ...updated[idx], ...item };
      } else {
        updated = [item, ...all];
      }
      StorageService.saveRpa(updated);
      this.emitChange('rpa', updated);
      await UpMizikAPI.addRpa(item);
    } catch (e) {
      console.warn('[HostingerService] saveSingleRpa warn:', e);
    }
  }

  async deleteRpa(id: string) {
    try {
      const all = StorageService.getRpa().filter((r) => r.id !== id);
      StorageService.saveRpa(all);
      this.emitChange('rpa', all);
      await UpMizikAPI.deleteRpa(id);
    } catch (e) {
      console.warn('[HostingerService] deleteRpa warn:', e);
    }
  }

  subscribeToRpa(callback: (rpa: RpaItem[]) => void): Unsubscribe {
    if (!this.listeners.has('rpa')) {
      this.listeners.set('rpa', new Set());
    }
    const set = this.listeners.get('rpa')!;
    set.add(callback);
    callback(StorageService.getRpa());
    return () => {
      set.delete(callback);
    };
  }

  // ==========================================
  // PIBLISITE (PUBS)
  // ==========================================
  async fetchPubs(): Promise<PubItem[] | null> {
    try {
      const apiPubs = await UpMizikAPI.getPubs(false);
      if (apiPubs && apiPubs.length > 0) {
        return apiPubs;
      }
      return StorageService.getPubs();
    } catch {
      return StorageService.getPubs();
    }
  }

  async syncPubs(list: PubItem[]) {
    try {
      StorageService.savePubs(list);
      this.emitChange('pubs', list);
      await UpMizikAPI.syncAllData({ pubs: list });
    } catch (e) {
      console.warn('[HostingerService] syncPubs warn:', e);
    }
  }

  async saveSinglePub(item: PubItem) {
    try {
      const all = StorageService.getPubs();
      const idx = all.findIndex((p) => p.id === item.id);
      let updated: PubItem[];
      if (idx >= 0) {
        updated = [...all];
        updated[idx] = { ...updated[idx], ...item };
      } else {
        updated = [item, ...all];
      }
      StorageService.savePubs(updated);
      this.emitChange('pubs', updated);
      await UpMizikAPI.addPub(item);
    } catch (e) {
      console.warn('[HostingerService] saveSinglePub warn:', e);
    }
  }

  subscribeToPubs(callback: (pubs: PubItem[]) => void): Unsubscribe {
    if (!this.listeners.has('pubs')) {
      this.listeners.set('pubs', new Set());
    }
    const set = this.listeners.get('pubs')!;
    set.add(callback);
    callback(StorageService.getPubs());
    return () => {
      set.delete(callback);
    };
  }

  // ==========================================
  // PAYMENT SETTINGS & METHODS (PARAMÈT PEMAN & FRÈ ENSKRIPSYON)
  // ==========================================
  async fetchPaymentSettings(): Promise<PaymentSettingsConfig | null> {
    try {
      return await UpMizikAPI.getPaymentSettings();
    } catch (e) {
      console.warn('[HostingerService] fetchPaymentSettings warn:', e);
      return null;
    }
  }

  async savePaymentSettings(config: PaymentSettingsConfig): Promise<boolean> {
    try {
      const ok = await UpMizikAPI.savePaymentSettings(config);
      this.emitChange('payment_settings', config);
      return ok;
    } catch (e) {
      console.warn('[HostingerService] savePaymentSettings warn:', e);
      return false;
    }
  }

  async fetchPaymentSettingsAndNotify(forceNotify = false): Promise<void> {
    try {
      const cloudSettings = await this.fetchPaymentSettings();
      if (cloudSettings && typeof cloudSettings === 'object' && Array.isArray(cloudSettings.methods)) {
        const local = StorageService.getPaymentSettings();
        const isDifferent =
          cloudSettings.artistRegistrationFeeUsd !== local.artistRegistrationFeeUsd ||
          cloudSettings.htgExchangeRate !== local.htgExchangeRate ||
          cloudSettings.artistRegistrationFeeHtg !== local.artistRegistrationFeeHtg ||
          cloudSettings.methods?.length !== local.methods?.length ||
          JSON.stringify(cloudSettings.methods) !== JSON.stringify(local.methods);

        if (isDifferent || forceNotify) {
          StorageService.savePaymentSettings(cloudSettings, false);
          this.emitChange('payment_settings', cloudSettings);
        }
      }
    } catch (e) {
      console.warn('[HostingerService] fetchPaymentSettingsAndNotify warn:', e);
    }
  }

  subscribeToPaymentSettings(callback: (settings: PaymentSettingsConfig) => void): Unsubscribe {
    if (!this.listeners.has('payment_settings')) {
      this.listeners.set('payment_settings', new Set());
    }
    const set = this.listeners.get('payment_settings')!;
    set.add(callback);
    callback(StorageService.getPaymentSettings());
    return () => {
      set.delete(callback);
    };
  }

  // ==========================================
  // RESET TOTAL POU ADMIN
  // ==========================================
  async clearAllCollections() {
    try {
      StorageService.resetContentData();
      this.emitChange('artists', []);
      this.emitChange('music', []);
      this.emitChange('donations', []);
      this.emitChange('social_posts', []);
      this.emitChange('archives', []);
    } catch (e) {
      console.warn('[HostingerService] clearAllCollections warn:', e);
    }
  }
}

export const HostingerService = new HostingerSyncService();
