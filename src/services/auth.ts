/**
 * UpMizik - Auth Service
 */

import { apiClient, ApiResponse } from './api';
import { ArtistUser } from '../types';

export interface AdminUser {
  id: string;
  username: string;
  email: string;
  name: string;
  role: string;
}

export interface SessionData {
  authenticated: boolean;
  user?: AdminUser | ArtistUser;
  role?: 'admin' | 'artist';
  csrf_token?: string;
}

export class AuthService {
  static async checkSession(): Promise<ApiResponse<SessionData>> {
    const res = await apiClient.get<SessionData>('/auth.php?action=check_session');
    if (res.data?.csrf_token) {
      apiClient.setCsrfToken(res.data.csrf_token);
    }
    return res;
  }

  static async loginAdmin(username: string, password: string): Promise<ApiResponse<{ user: AdminUser; role: string; csrf_token: string }>> {
    const res = await apiClient.post('/auth.php', {
      action: 'admin_login',
      username,
      password
    });
    if (res.data?.csrf_token) {
      apiClient.setCsrfToken(res.data.csrf_token);
    }
    return res;
  }

  static async loginArtist(identifier: string, pin: string): Promise<ApiResponse<{ artist: ArtistUser; user: ArtistUser; role: string; csrf_token: string }>> {
    const res = await apiClient.post('/auth.php', {
      action: 'artist_login',
      identifier,
      pin
    });
    if (res.data?.csrf_token) {
      apiClient.setCsrfToken(res.data.csrf_token);
    }
    return res;
  }

  static async logout(): Promise<ApiResponse<null>> {
    return apiClient.post('/auth.php', { action: 'logout' });
  }

  static async changePin(artistId: string, oldPin: string, newPin: string): Promise<ApiResponse<null>> {
    return apiClient.post('/auth.php', {
      action: 'change_pin',
      artistId,
      oldPin,
      newPin
    });
  }
}
