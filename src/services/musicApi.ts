/**
 * UpMizik - Music & Albums API Service
 */

import { apiClient, ApiResponse } from './api';
import { MusicItem } from '../types';

export interface AlbumItem {
  id: string;
  title: string;
  artistId: string;
  artistName: string;
  coverUrl: string;
  description?: string;
  genre: string;
  releaseDate: string;
  status: 'active' | 'pending' | 'archived';
  tracks?: MusicItem[];
  tracksCount?: number;
}

export class MusicApiService {
  static async getMusics(params?: { category?: string; artistId?: string; albumId?: string; status?: string; limit?: number; offset?: number }): Promise<ApiResponse<{ musics: MusicItem[]; count: number }>> {
    return apiClient.get('/musics.php', params);
  }

  static async getMusicById(id: string): Promise<ApiResponse<{ music: MusicItem }>> {
    return apiClient.get('/musics.php', { id });
  }

  static async createMusic(musicData: Partial<MusicItem>): Promise<ApiResponse<{ musicId: string; title: string }>> {
    return apiClient.post('/musics.php', musicData);
  }

  static async updateMusic(id: string, musicData: Partial<MusicItem>): Promise<ApiResponse<{ musicId: string }>> {
    return apiClient.put('/musics.php', { id, ...musicData });
  }

  static async deleteMusic(id: string): Promise<ApiResponse<{ musicId: string }>> {
    return apiClient.delete('/musics.php', { id });
  }

  static async incrementPlayCount(musicId: string): Promise<ApiResponse<{ musicId: string }>> {
    return apiClient.post('/musics.php', { action: 'play', musicId });
  }

  // ALBUMS
  static async getAlbums(params?: { artistId?: string; status?: string }): Promise<ApiResponse<{ albums: AlbumItem[]; count: number }>> {
    return apiClient.get('/albums.php', params);
  }

  static async getAlbumById(id: string): Promise<ApiResponse<{ album: AlbumItem }>> {
    return apiClient.get('/albums.php', { id });
  }

  static async createAlbum(albumData: Partial<AlbumItem>): Promise<ApiResponse<{ albumId: string; title: string }>> {
    return apiClient.post('/albums.php', albumData);
  }

  static async updateAlbum(id: string, albumData: Partial<AlbumItem>): Promise<ApiResponse<{ albumId: string }>> {
    return apiClient.put('/albums.php', { id, ...albumData });
  }

  static async deleteAlbum(id: string): Promise<ApiResponse<{ albumId: string }>> {
    return apiClient.delete('/albums.php', { id });
  }
}
