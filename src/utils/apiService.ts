/**
 * UpMizik - Hostinger PHP / MySQL API Service Client
 * 
 * Modil sa a jere tout kominikasyon ant koòdone React la ak backend PHP / MySQL sou Hostinger.
 * Li sipòte telechajman fichye odyo (MP3) ak prèv dirèkteman nan dosye sèvè Hostinger a.
 */

import { ArtistUser, MusicItem, DonationItem, ArtistInboxMessage, SocialPost, PubItem, RpaItem, PaymentSettingsConfig } from '../types';

// API Base URL:
// Sèvi ak chemen relatif /backend/api lè n ap kouri sou upmizik.com, sou IP VPS la, oswa localhost.
const getInitialApiBaseUrl = (): string => {
  const envUrl = ((import.meta as unknown as { env?: Record<string, string> }).env?.VITE_PHP_API_URL as string);
  if (envUrl) return envUrl;
  if (typeof window !== 'undefined') {
    const host = window.location.hostname;
    if (host.includes('upmizik.com') || host.includes('2.25.132.44') || host === 'localhost' || host === '127.0.0.1') {
      return `${window.location.origin}/backend/api`;
    }
  }
  return 'https://upmizik.com/backend/api';
};

const API_BASE_URL = getInitialApiBaseUrl();

class ApiService {
  private baseUrl: string = API_BASE_URL;

  public setBaseUrl(url: string) {
    this.baseUrl = url;
  }

  public getBaseUrl(): string {
    return this.baseUrl;
  }

  /**
   * Telechaje yon fichye (Mizik MP3, Kouvèti, Prèv MonCash) dirèkteman sou sèvè Hostinger a
   */
  public async uploadFile(
    file: File | Blob,
    type: 'music' | 'covers' | 'proofs' | 'avatars' | 'banners' | 'media' | 'general' = 'general',
    customFileName?: string
  ): Promise<{ success: boolean; url: string; relativePath?: string; message?: string }> {
    try {
      const formData = new FormData();
      formData.append('file', file, customFileName || (file instanceof File ? file.name : 'upload.bin'));
      formData.append('type', type);

      const response = await fetch(`${this.baseUrl}/upload.php`, {
        method: 'POST',
        body: formData,
      });

      if (!response.ok) {
        throw new Error(`Erè HTTP: ${response.status}`);
      }

      const result = await response.json();
      return result;
    } catch (error) {
      console.warn('[ApiService] Upload dirèk pa disponib nan preview lokal, fallback aktif.', error);
      // Fallback pou anviwònman dev lokal si sèvè PHP a poko deplwaye
      return {
        success: false,
        url: '',
        message: error instanceof Error ? error.message : 'Erè telechajman'
      };
    }
  }

  /**
   * Telechaje yon imaj oswa dokiman Base64 (Data URI) sou sèvè Hostinger a
   */
  public async uploadBase64(
    base64Data: string,
    type: 'music' | 'covers' | 'proofs' | 'avatars' | 'banners' | 'media' = 'covers'
  ): Promise<{ success: boolean; url: string }> {
    try {
      const response = await fetch(`${this.baseUrl}/upload.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ base64Data, type }),
      });

      const result = await response.json();
      return result;
    } catch (error) {
      console.warn('[ApiService] Upload Base64 fallback:', error);
      return { success: false, url: base64Data };
    }
  }

  // ----------------------------------------------------------
  // ATIS (ARTISTS)
  // ----------------------------------------------------------

  public async getArtists(status?: string): Promise<ArtistUser[]> {
    try {
      const url = status ? `${this.baseUrl}/artists.php?status=${status}` : `${this.baseUrl}/artists.php`;
      const res = await fetch(url);
      const data = await res.json();
      const list = data.artists || data.data?.artists;
      return Array.isArray(list) ? list : [];
    } catch {
      return [];
    }
  }

  public async registerArtist(artistData: Partial<ArtistUser>): Promise<{ success: boolean; artistId?: string; message?: string }> {
    try {
      const res = await fetch(`${this.baseUrl}/artists.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(artistData),
      });
      return await res.json();
    } catch (err) {
      return { success: false, message: 'Erè enskripsyon' };
    }
  }

  public async validateArtist(artistId: string, accept: boolean, reason?: string): Promise<boolean> {
    try {
      const res = await fetch(`${this.baseUrl}/artists.php`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: artistId,
          status: accept ? 'active' : 'rejected',
          registrationRejectionReason: !accept ? reason : undefined,
        }),
      });
      const data = await res.json();
      return data.success;
    } catch {
      return false;
    }
  }

  public async updateArtist(artistId: string, data: Partial<ArtistUser>): Promise<boolean> {
    try {
      const res = await fetch(`${this.baseUrl}/artists.php`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: artistId, ...data }),
      });
      const resData = await res.json();
      return !!resData.success;
    } catch {
      return false;
    }
  }

  // ----------------------------------------------------------
  // MIZIK (MUSICS)
  // ----------------------------------------------------------

  public async getMusics(params?: { category?: string; artistId?: string; status?: string }): Promise<MusicItem[]> {
    try {
      const query = new URLSearchParams();
      if (params?.category && params.category !== 'Tout') query.append('category', params.category);
      if (params?.artistId) query.append('artistId', params.artistId);
      if (params?.status) query.append('status', params.status);

      const res = await fetch(`${this.baseUrl}/musics.php?${query.toString()}`);
      const data = await res.json();
      return data.success ? data.musics : [];
    } catch {
      return [];
    }
  }

  public async addMusic(musicData: Partial<MusicItem>): Promise<{ success: boolean; musicId?: string; message?: string }> {
    try {
      const res = await fetch(`${this.baseUrl}/musics.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(musicData),
      });
      return await res.json();
    } catch {
      return { success: false, message: 'Erè piblikasyon mizik' };
    }
  }

  public async deleteMusic(musicId: string): Promise<boolean> {
    try {
      const res = await fetch(`${this.baseUrl}/musics.php?id=${encodeURIComponent(musicId)}`, {
        method: 'DELETE',
      });
      const data = await res.json();
      return !!data.success;
    } catch {
      return false;
    }
  }

  // ----------------------------------------------------------
  // RIBRIK POUSE ATIS (RPA)
  // ----------------------------------------------------------

  public async getRpa(): Promise<RpaItem[]> {
    try {
      const res = await fetch(`${this.baseUrl}/rpa.php`);
      const data = await res.json();
      return data.success ? data.rpa : [];
    } catch {
      return [];
    }
  }

  public async addRpa(item: Partial<RpaItem>): Promise<{ success: boolean; rpaId?: string; message?: string }> {
    try {
      const res = await fetch(`${this.baseUrl}/rpa.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(item),
      });
      return await res.json();
    } catch {
      return { success: false, message: 'Erè piblikasyon RPA' };
    }
  }

  public async deleteRpa(id: string): Promise<boolean> {
    try {
      const res = await fetch(`${this.baseUrl}/rpa.php?id=${encodeURIComponent(id)}`, {
        method: 'DELETE',
      });
      const data = await res.json();
      return !!data.success;
    } catch {
      return false;
    }
  }

  // ----------------------------------------------------------
  // PIBLISITE (PUBS)
  // ----------------------------------------------------------

  public async getPubs(activeOnly: boolean = true): Promise<PubItem[]> {
    try {
      const url = activeOnly ? `${this.baseUrl}/pubs.php?active=1` : `${this.baseUrl}/pubs.php`;
      const res = await fetch(url);
      const data = await res.json();
      return data.success ? data.pubs : [];
    } catch {
      return [];
    }
  }

  public async addPub(item: Partial<PubItem>): Promise<{ success: boolean; pubId?: string; message?: string }> {
    try {
      const res = await fetch(`${this.baseUrl}/pubs.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(item),
      });
      return await res.json();
    } catch {
      return { success: false, message: 'Erè anrejistreman piblisite' };
    }
  }

  public async incrementStream(musicId: string): Promise<void> {
    try {
      await fetch(`${this.baseUrl}/musics.php`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: musicId, action: 'listen' }),
      });
    } catch (e) {
      // Non-blocking
    }
  }

  // ----------------------------------------------------------
  // DONASYON (DONATIONS)
  // ----------------------------------------------------------

  public async getDonations(params?: { artistId?: string; musicId?: string; status?: string }): Promise<DonationItem[]> {
    try {
      let url = `${this.baseUrl}/donations.php`;
      const queryParams: string[] = [];
      if (params?.artistId) queryParams.push(`artistId=${encodeURIComponent(params.artistId)}`);
      if (params?.musicId) queryParams.push(`musicId=${encodeURIComponent(params.musicId)}`);
      if (params?.status) queryParams.push(`status=${encodeURIComponent(params.status)}`);
      if (queryParams.length > 0) {
        url += `?${queryParams.join('&')}`;
      }
      const res = await fetch(url);
      const data = await res.json();
      const list = data.donations || data.data?.donations;
      return Array.isArray(list) ? list : [];
    } catch {
      return [];
    }
  }

  public async submitDonation(donation: Partial<DonationItem>): Promise<{ success: boolean; donationId?: string }> {
    try {
      const res = await fetch(`${this.baseUrl}/donations.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(donation),
      });
      return await res.json();
    } catch {
      return { success: false };
    }
  }

  public async validateDonation(donationId: string, accept: boolean): Promise<boolean> {
    try {
      const res = await fetch(`${this.baseUrl}/donations.php`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: donationId, accept }),
      });
      const data = await res.json();
      return data.success;
    } catch {
      return false;
    }
  }

  // ----------------------------------------------------------
  // PARAMÈT PEMAN & NIMEWO (PAYMENT SETTINGS & MONCASH/NATCASH)
  // ----------------------------------------------------------

  public async getPaymentSettings(): Promise<PaymentSettingsConfig | null> {
    try {
      const res = await fetch(`${this.baseUrl}/settings.php`);
      if (!res.ok) return null;
      const data = await res.json();
      if (data && data.success) {
        const cfg = data.settings || data.data?.settings || data.data;
        if (cfg && typeof cfg === 'object' && Array.isArray(cfg.methods)) {
          return cfg as PaymentSettingsConfig;
        }
      }
      return null;
    } catch {
      return null;
    }
  }

  public async savePaymentSettings(config: PaymentSettingsConfig): Promise<boolean> {
    try {
      const res = await fetch(`${this.baseUrl}/settings.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ settings: config }),
      });
      if (!res.ok) return false;
      const data = await res.json();
      return !!data.success;
    } catch {
      return false;
    }
  }

  // ----------------------------------------------------------
  // SENKRONIZASYON TOTAL (BULK SYNC)
  // ----------------------------------------------------------

  public async syncAllData(allData: {
    artists?: ArtistUser[];
    musics?: MusicItem[];
    pubs?: PubItem[];
    rpa?: RpaItem[];
  }): Promise<boolean> {
    try {
      const res = await fetch(`${this.baseUrl}/sync.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(allData),
      });
      const data = await res.json();
      return data.success;
    } catch {
      return false;
    }
  }
}

export const UpMizikAPI = new ApiService();
