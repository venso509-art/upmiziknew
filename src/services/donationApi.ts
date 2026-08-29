/**
 * UpMizik - Donations & MonCash API Service
 */

import { apiClient, ApiResponse } from './api';
import { DonationItem } from '../types';

export class DonationApiService {
  static async getDonations(params?: { artistId?: string; musicId?: string; status?: string }): Promise<ApiResponse<{ donations: DonationItem[]; count: number }>> {
    return apiClient.get('/donations.php', params);
  }

  static async initiateMonCash(data: {
    amount: number;
    musicId: string;
    musicTitle: string;
    artistId: string;
    artistName: string;
    donorName: string;
    donorPhone: string;
  }): Promise<ApiResponse<{ redirect_url?: string; payment_token?: string; orderId?: string; mode?: string }>> {
    return apiClient.post('/donations.php', {
      action: 'initiate_moncash',
      ...data
    });
  }

  static async createDonationManual(donationData: Partial<DonationItem>): Promise<ApiResponse<{ donationId: string }>> {
    return apiClient.post('/donations.php', donationData);
  }

  static async verifyPayment(transactionId: string, orderId?: string): Promise<ApiResponse<{ is_paid: boolean; status: string }>> {
    return apiClient.get('/donations.php', {
      action: 'verify',
      transactionId,
      orderId
    });
  }

  static async updateDonationStatus(id: string, status: 'validated' | 'rejected'): Promise<ApiResponse<{ donationId: string; status: string }>> {
    return apiClient.put('/donations.php', { id, status });
  }
}
