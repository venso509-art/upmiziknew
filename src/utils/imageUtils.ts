import type { SyntheticEvent } from 'react';

/**
 * Utility to process uploaded proof photos / album covers / avatars into persistent Base64 Data URLs
 * Compresses images aggressively so they fit comfortably in local storage while retaining crisp quality.
 */
export async function compressAndReadFile(file: File, maxWidth = 600, maxHeight = 800, quality = 0.65): Promise<string> {
  return new Promise((resolve) => {
    // If it's not an image, read directly as data URL
    if (!file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = () => resolve((reader.result as string) || '');
      reader.onerror = () => resolve('');
      reader.readAsDataURL(file);
      return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
      const result = event.target?.result as string;
      if (!result) {
        resolve('');
        return;
      }

      const img = new Image();
      img.onload = () => {
        try {
          let { width, height } = img;

          if (width > maxWidth || height > maxHeight) {
            const ratio = Math.min(maxWidth / width, maxHeight / height);
            width = Math.max(1, Math.round(width * ratio));
            height = Math.max(1, Math.round(height * ratio));
          }

          const canvas = document.createElement('canvas');
          canvas.width = width;
          canvas.height = height;
          const ctx = canvas.getContext('2d');
          if (!ctx) {
            resolve(result);
            return;
          }

          // Clear with white background to keep text on light screenshots crystal clear
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, width, height);
          ctx.drawImage(img, 0, 0, width, height);

          // Convert to compressed base64 jpeg
          const dataUrl = canvas.toDataURL('image/jpeg', quality);
          resolve(dataUrl);
        } catch (e) {
          resolve(result);
        }
      };
      img.onerror = () => {
        resolve(result);
      };
      img.src = result;
    };
    reader.onerror = () => resolve('');
    reader.readAsDataURL(file);
  });
}

/**
 * Local offline SVG data URIs that work 100% offline with NO network requests.
 * Guaranteed to never trigger net::ERR_FAILED even without internet or missing backend uploads.
 */
export const DEFAULT_ARTIST_AVATAR = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3E%3Crect width='120' height='120' fill='%230f172a'/%3E%3Ccircle cx='60' cy='45' r='24' fill='%23334155'/%3E%3Cpath d='M24 105 C24 82 40 75 60 75 C80 75 96 82 96 105 Z' fill='%23334155'/%3E%3Cpath d='M76 34 L82 30 L82 44 M82 36 L90 34' stroke='%23f59e0b' stroke-width='3' stroke-linecap='round' stroke-linejoin='round' fill='none'/%3E%3C/svg%3E";

export const DEFAULT_SONG_COVER = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3E%3Crect width='120' height='120' fill='%23090d16'/%3E%3Ccircle cx='60' cy='60' r='42' fill='%231e293b' stroke='%23334155' stroke-width='4'/%3E%3Ccircle cx='60' cy='60' r='22' fill='%230f172a' stroke='%23f59e0b' stroke-width='2'/%3E%3Ccircle cx='60' cy='60' r='6' fill='%23f59e0b'/%3E%3C/svg%3E";

export const DEFAULT_HEADER_BANNER = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 300'%3E%3Cdefs%3E%3ClinearGradient id='bg' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' stop-color='%230f172a'/%3E%3Cstop offset='50%25' stop-color='%23060a14'/%3E%3Cstop offset='100%25' stop-color='%231e1b4b'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='800' height='300' fill='url(%23bg)'/%3E%3Ccircle cx='400' cy='150' r='180' fill='%23f59e0b' fill-opacity='0.05'/%3E%3C/svg%3E";

/**
 * Handle image load error on <img> elements by switching gracefully to an offline fallback.
 * Prevents broken image icons and browser console spam.
 */
export function handleImageError(
  event: SyntheticEvent<HTMLImageElement, Event>,
  fallback: string = DEFAULT_ARTIST_AVATAR
): void {
  const target = event.currentTarget;
  if (target && target.src !== fallback) {
    target.onerror = null; // Prevent infinite error loops
    target.src = fallback;
  }
}

/**
 * Utility to safely normalize image/avatar/cover/proof URLs and prevent
 * `Failed to load resource: net::ERR_FAILED` caused by Mixed Content (HTTP vs HTTPS),
 * missing path segments, or raw filenames.
 *
 * Always returns a non-null, valid URL or an offline SVG fallback image path.
 */
export function resolveMediaUrl(
  url?: string | null,
  fallback = DEFAULT_SONG_COVER
): string {
  const safeFallback = fallback && typeof fallback === 'string' && fallback.trim().length > 0
    ? fallback.trim()
    : DEFAULT_SONG_COVER;

  if (
    !url ||
    typeof url !== 'string' ||
    !url.trim() ||
    url.trim() === 'null' ||
    url.trim() === 'undefined' ||
    url.trim() === 'NaN' ||
    url.trim() === '[object Object]'
  ) {
    return safeFallback;
  }

  let clean = url.trim();

  // If corrupted by previous general/idb: bug, restore idb: prefix
  if (clean.includes('idb:')) {
    clean = clean.substring(clean.indexOf('idb:'));
  }

  // Reject malicious / dangerous schemes
  const lower = clean.toLowerCase();
  if (lower.startsWith('javascript:') || lower.startsWith('vbscript:') || lower.startsWith('file:')) {
    return safeFallback;
  }

  // 1. Data URLs, Blob URLs & IndexedDB keys are already self-contained
  if (clean.startsWith('data:') || clean.startsWith('blob:') || clean.startsWith('idb:')) {
    // Basic check for truncated or malformed data: URI
    if (clean.startsWith('data:') && clean.length < 15) {
      return safeFallback;
    }
    return clean;
  }

  // 2. Prevent Mixed Content & Normalize local dev server URLs
  let normalized = clean.replace(/\\/g, '/');

  // Check for malformed protocol like http:/ or https:/ without double slashes
  if (/^https?:[^\/]/i.test(normalized) || /^https?:\/[^\/]/i.test(normalized)) {
    return safeFallback;
  }

  if (normalized.startsWith('http://localhost:3000/backend/uploads/') || normalized.startsWith('http://localhost:3000/uploads/')) {
    if (typeof window !== 'undefined' && window.location.origin !== 'http://localhost:3000') {
      normalized = normalized.replace('http://localhost:3000', '');
    }
  }

  if (normalized.startsWith('http://upmizik.com') || normalized.startsWith('http://www.upmizik.com')) {
    normalized = normalized.replace('http://', 'https://');
  } else if (typeof window !== 'undefined' && window.location.protocol === 'https:' && normalized.startsWith('http://')) {
    if (normalized.includes('images.unsplash.com') || normalized.includes('dicebear.com')) {
      normalized = normalized.replace('http://', 'https://');
    }
  }

  // 3. Raw filenames without folder paths (e.g. "avatar_1789679229_a4d8db2b.jpeg" or "cover_123.jpg")
  if (!normalized.startsWith('http://') && !normalized.startsWith('https://') && !normalized.startsWith('/')) {
    // If it's a raw string without extension or recognized folder prefix, treat as malformed
    if (!normalized.includes('.') && !normalized.includes('/')) {
      if (!normalized.startsWith('avatar') && !normalized.startsWith('cover') && !normalized.startsWith('proof') && !normalized.startsWith('music')) {
        return safeFallback;
      }
    }

    if (normalized.startsWith('avatar_') || normalized.startsWith('avatars_')) {
      return `/backend/uploads/avatars/${normalized}`;
    }
    if (normalized.startsWith('cover_') || normalized.startsWith('covers_')) {
      return `/backend/uploads/covers/${normalized}`;
    }
    if (normalized.startsWith('proof_') || normalized.startsWith('proofs_')) {
      return `/backend/uploads/proofs/${normalized}`;
    }
    if (normalized.startsWith('music_') || normalized.endsWith('.mp3') || normalized.endsWith('.wav') || normalized.endsWith('.m4a') || normalized.endsWith('.ogg')) {
      return `/backend/uploads/music/${normalized}`;
    }
    return `/backend/uploads/general/${normalized}`;
  }

  // 4. Relative paths like "uploads/avatars/..." missing leading /backend/
  if (normalized.startsWith('uploads/')) {
    return `/backend/${normalized}`;
  }
  if (normalized.startsWith('/uploads/')) {
    return `/backend${normalized}`;
  }

  return (normalized && normalized.trim().length > 0) ? normalized : safeFallback;
}

