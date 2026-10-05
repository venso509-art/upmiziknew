import sharp from 'sharp';
import fs from 'fs';
import path from 'path';

const publicDir = path.resolve('public');
const logoSvgPath = path.join(publicDir, 'upmizik-logo.svg');
const faviconSvgPath = path.join(publicDir, 'favicon.svg');

// Create an optimized square favicon SVG with bold presence for small icon display
const faviconSvgContent = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="100%" height="100%">
  <defs>
    <!-- Background Gradient -->
    <radialGradient id="bgGrad" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="#0f172a" />
      <stop offset="100%" stop-color="#020617" />
    </radialGradient>

    <!-- Outer Ring Gradient (Red on Left, Blue on Right) -->
    <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#ff3b4b" />
      <stop offset="45%" stop-color="#dc2626" />
      <stop offset="55%" stop-color="#2563eb" />
      <stop offset="100%" stop-color="#1d4ed8" />
    </linearGradient>

    <!-- Up Red Gradient -->
    <linearGradient id="upRed" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#ff4d5e" />
      <stop offset="60%" stop-color="#dc2626" />
      <stop offset="100%" stop-color="#991b1b" />
    </linearGradient>

    <!-- Mizik Blue Gradient -->
    <linearGradient id="mizikBlue" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#60a5fa" />
      <stop offset="50%" stop-color="#2563eb" />
      <stop offset="100%" stop-color="#1e40af" />
    </linearGradient>

    <!-- Arrow Highlight -->
    <linearGradient id="arrowLight" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#fca5a5" />
      <stop offset="100%" stop-color="#ef4444" />
    </linearGradient>

    <!-- Subtle Drop Shadow -->
    <filter id="iconShadow" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="6" stdDeviation="8" flood-color="#000000" flood-opacity="0.6" />
    </filter>
  </defs>

  <!-- Circular Base Badge -->
  <circle cx="256" cy="256" r="248" fill="url(#bgGrad)" />
  <circle cx="256" cy="256" r="236" fill="none" stroke="url(#ringGrad)" stroke-width="20" />

  <!-- Inner Soundwave Accents -->
  <g fill="#ef4444" opacity="0.8">
    <rect x="52" y="244" width="6" height="24" rx="3" />
    <rect x="64" y="230" width="6" height="52" rx="3" />
    <rect x="76" y="218" width="6" height="76" rx="3" />
  </g>

  <g fill="#3b82f6" opacity="0.8">
    <rect x="430" y="218" width="6" height="76" rx="3" />
    <rect x="442" y="230" width="6" height="52" rx="3" />
    <rect x="454" y="244" width="6" height="24" rx="3" />
  </g>

  <!-- Main Center Icon Group -->
  <g filter="url(#iconShadow)">
    <!-- Stylized 'U' in Red -->
    <path d="M 112 165 L 112 265 C 112 345 160 375 220 375 C 280 375 328 345 328 265 L 328 165 L 282 165 L 282 265 C 282 312 258 335 220 335 C 182 335 158 312 158 265 L 158 165 Z"
          fill="url(#upRed)" />

    <!-- 3D Ascending Red Arrow (The "UP" Motif) -->
    <g>
      <!-- Arrow Base -->
      <path d="M 200 230 L 200 310 L 240 310 L 240 230 Z" fill="#991b1b" />
      <!-- Left Facet -->
      <path d="M 220 120 L 155 225 L 220 225 Z" fill="url(#arrowLight)" />
      <!-- Right Facet -->
      <path d="M 220 120 L 285 225 L 220 225 Z" fill="#b91c1c" />
    </g>

    <!-- White Crisp Play Button -->
    <g transform="translate(195, 238)">
      <polygon points="10,6 46,26 10,46" fill="#ffffff" stroke="#991b1b" stroke-width="4" stroke-linejoin="round" />
    </g>

    <!-- Stylized 'M' in Royal Blue overlaying right side -->
    <g transform="translate(290, 205)">
      <path d="M 12 165 L 12 30 L 48 30 L 72 105 L 96 30 L 132 30 L 132 165 L 102 165 L 102 85 L 82 145 L 62 145 L 42 85 L 42 165 Z"
            fill="url(#mizikBlue)" />
    </g>

    <!-- Dual Accent Underlines -->
    <rect x="130" y="405" width="110" height="8" rx="4" fill="#ef4444" />
    <rect x="250" y="405" width="132" height="8" rx="4" fill="#3b82f6" />
  </g>
</svg>`;

fs.writeFileSync(faviconSvgPath, faviconSvgContent, 'utf-8');
console.log('Created favicon.svg');

// Generate PNG sizes
async function generatePngs() {
  const svgBuffer = Buffer.from(faviconSvgContent);

  // 48x48 (Google Search official recommendation)
  await sharp(svgBuffer)
    .resize(48, 48)
    .png()
    .toFile(path.join(publicDir, 'favicon-48x48.png'));
  console.log('Created favicon-48x48.png');

  // 96x96 (Google Search high-density recommendation)
  await sharp(svgBuffer)
    .resize(96, 96)
    .png()
    .toFile(path.join(publicDir, 'favicon-96x96.png'));
  console.log('Created favicon-96x96.png');

  // 144x144 (Google Search multiple of 48)
  await sharp(svgBuffer)
    .resize(144, 144)
    .png()
    .toFile(path.join(publicDir, 'favicon-144x144.png'));
  console.log('Created favicon-144x144.png');

  // 192x192 (Standard PWA & Search)
  await sharp(svgBuffer)
    .resize(192, 192)
    .png()
    .toFile(path.join(publicDir, 'favicon-192x192.png'));
  console.log('Created favicon-192x192.png');

  // Also refresh pwa-192x192.png and apple-touch-icon.png with crisp icon
  await sharp(svgBuffer)
    .resize(180, 180)
    .png()
    .toFile(path.join(publicDir, 'apple-touch-icon.png'));
  console.log('Updated apple-touch-icon.png');

  // Generate valid multi-size ICO file containing 48x48, 32x32, 16x16
  const png48 = await sharp(svgBuffer).resize(48, 48).png().toBuffer();
  const png32 = await sharp(svgBuffer).resize(32, 32).png().toBuffer();
  const png16 = await sharp(svgBuffer).resize(16, 16).png().toBuffer();

  const icoBuffer = createIcoFile([
    { width: 16, height: 16, buffer: png16 },
    { width: 32, height: 32, buffer: png32 },
    { width: 48, height: 48, buffer: png48 }
  ]);

  fs.writeFileSync(path.join(publicDir, 'favicon.ico'), icoBuffer);
  console.log('Created valid multi-resolution favicon.ico');
}

/**
 * Packs PNG buffers into standard Windows ICO file format
 */
function createIcoFile(images) {
  const numImages = images.length;
  const headerSize = 6;
  const dirEntrySize = 16;
  const totalHeaderSize = headerSize + (dirEntrySize * numImages);

  let currentOffset = totalHeaderSize;
  const dirEntries = [];

  for (const img of images) {
    const size = img.buffer.length;
    const entry = Buffer.alloc(dirEntrySize);
    entry.writeUInt8(img.width >= 256 ? 0 : img.width, 0);   // width
    entry.writeUInt8(img.height >= 256 ? 0 : img.height, 1); // height
    entry.writeUInt8(0, 2);                                  // color count
    entry.writeUInt8(0, 3);                                  // reserved
    entry.writeUInt16LE(1, 4);                               // color planes
    entry.writeUInt16LE(32, 6);                              // bits per pixel
    entry.writeUInt32LE(size, 8);                            // size in bytes
    entry.writeUInt32LE(currentOffset, 12);                  // offset
    dirEntries.push(entry);
    currentOffset += size;
  }

  const header = Buffer.alloc(headerSize);
  header.writeUInt16LE(0, 0);         // reserved
  header.writeUInt16LE(1, 2);         // 1 = ICO
  header.writeUInt16LE(numImages, 4); // count

  return Buffer.concat([
    header,
    ...dirEntries,
    ...images.map(img => img.buffer)
  ]);
}

generatePngs().catch(console.error);
