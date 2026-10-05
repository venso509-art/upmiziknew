import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Generate a valid, high-fidelity 44.1kHz Stereo 16-bit WAV file with a warm Kompa groove
function generateKompaWav(durationSeconds = 20) {
  const sampleRate = 44100;
  const numChannels = 2;
  const bitsPerSample = 16;
  const bytesPerSample = bitsPerSample / 8;
  const blockAlign = numChannels * bytesPerSample;
  const byteRate = sampleRate * blockAlign;
  const totalSamples = Math.floor(sampleRate * durationSeconds);
  const dataSize = totalSamples * blockAlign;
  const headerSize = 44;
  const totalSize = headerSize + dataSize;

  const buffer = Buffer.alloc(totalSize);

  // RIFF header
  buffer.write('RIFF', 0);
  buffer.writeUInt32LE(totalSize - 8, 4);
  buffer.write('WAVE', 8);

  // fmt subchunk
  buffer.write('fmt ', 12);
  buffer.writeUInt32LE(16, 16); // subchunk1size (16 for PCM)
  buffer.writeUInt16LE(1, 20);  // audioFormat (1 = PCM)
  buffer.writeUInt16LE(numChannels, 22);
  buffer.writeUInt32LE(sampleRate, 24);
  buffer.writeUInt32LE(byteRate, 28);
  buffer.writeUInt16LE(blockAlign, 32);
  buffer.writeUInt16LE(bitsPerSample, 34);

  // data subchunk
  buffer.write('data', 36);
  buffer.writeUInt32LE(dataSize, 40);

  // Chord progression for Kompa: Cmaj7 - Am7 - Dm7 - G7 (tempo ~105 BPM)
  const bpm = 105;
  const beatSec = 60 / bpm;
  const chordSec = beatSec * 2; // 2 beats per chord

  const chords = [
    [261.63, 329.63, 392.00, 493.88], // Cmaj7 (C4, E4, G4, B4)
    [220.00, 261.63, 329.63, 392.00], // Am7 (A3, C4, E4, G4)
    [293.66, 349.23, 440.00, 523.25], // Dm7 (D4, F4, A4, C5)
    [196.00, 246.94, 293.66, 349.23]  // G7 (G3, B3, D4, F4)
  ];
  const bassNotes = [130.81, 110.00, 146.83, 98.00]; // C3, A2, D3, G2

  let offset = 44;
  for (let i = 0; i < totalSamples; i++) {
    const t = i / sampleRate;
    const chordIndex = Math.floor(t / chordSec) % chords.length;
    const chord = chords[chordIndex];
    const bassFreq = bassNotes[chordIndex];

    const chordTime = t % chordSec;
    const beatTime = t % (beatSec / 2); // 8th note rhythm pulse

    // Smooth envelope for chords with gentle tremolo
    const env = Math.exp(-chordTime * 0.8) * 0.7 + 0.3;
    const tremolo = 1 + 0.15 * Math.sin(2 * Math.PI * 5 * t);

    // Chords (warm electric piano / guitar synth)
    let chordSignal = 0;
    for (let f = 0; f < chord.length; f++) {
      const freq = chord[f];
      chordSignal += (
        Math.sin(2 * Math.PI * freq * t) * 0.5 +
        Math.sin(2 * Math.PI * freq * 2 * t) * 0.15 +
        Math.sin(2 * Math.PI * freq * 3 * t) * 0.05
      );
    }
    chordSignal = (chordSignal / chord.length) * env * tremolo * 0.35;

    // Bassline (syncopated Kompa gouyad bass)
    const bassEnv = Math.exp(-(chordTime % beatSec) * 3);
    const bassSignal = (
      Math.sin(2 * Math.PI * bassFreq * t) * 0.6 +
      Math.sin(2 * Math.PI * bassFreq * 2 * t) * 0.3
    ) * (bassEnv * 0.7 + 0.3) * 0.45;

    // Gentle rhythmic shaker / hi-hat tick on 8th notes
    const shakerEnv = Math.exp(-beatTime * 40);
    const shakerNoise = (Math.random() * 2 - 1) * shakerEnv * 0.08;

    // Master sample mix
    const mix = (chordSignal + bassSignal + shakerNoise) * 0.85;

    // Gentle fade in (first 0.5s) and fade out (last 1.5s)
    let masterGain = 1.0;
    if (t < 0.5) masterGain = t / 0.5;
    if (t > durationSeconds - 1.5) masterGain = Math.max(0, (durationSeconds - t) / 1.5);

    const finalSample = Math.max(-1, Math.min(1, mix * masterGain));
    const sampleInt16 = Math.floor(finalSample * 32767);

    // Stereo panning (Left / Right slight stereo widening)
    const leftInt16 = Math.floor(sampleInt16 * 0.98);
    const rightInt16 = Math.floor(sampleInt16 * 0.95);

    buffer.writeInt16LE(leftInt16, offset);
    buffer.writeInt16LE(rightInt16, offset + 2);
    offset += 4;
  }

  return buffer;
}

const wavData = generateKompaWav(25); // 25 seconds sample loop

const rootDir = path.resolve(__dirname, '..');
const pathsToSave = [
  path.join(rootDir, 'public', 'assets', 'default_audio.wav'),
  path.join(rootDir, 'public', 'assets', 'sample_kompa.wav'),
  path.join(rootDir, 'backend', 'uploads', 'music', 'default_audio.wav'),
  path.join(rootDir, 'backend', 'uploads', 'music', 'sample_kompa.wav')
];

for (const p of pathsToSave) {
  const dir = path.dirname(p);
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
  fs.writeFileSync(p, wavData);
  console.log(`Saved audio file (${wavData.length} bytes): ${p}`);
}
