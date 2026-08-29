/**
 * UpMizik - Centralized Frontend API Client
 * Kominikasyon ak backend PHP / MySQL sou Hostinger VPS
 */

export interface ApiResponse<T = any> {
  success: boolean;
  message?: string;
  data?: T;
  errors?: string[];
  [key: string]: any;
}

const getBaseUrl = (): string => {
  if (typeof window !== 'undefined') {
    const metaEnv = (import.meta as any).env;
    if (metaEnv?.VITE_API_BASE_URL) {
      return metaEnv.VITE_API_BASE_URL;
    }
    if (metaEnv?.VITE_PHP_API_URL) {
      return metaEnv.VITE_PHP_API_URL;
    }
    return '/backend/api';
  }
  return '/backend/api';
};

class ApiClient {
  private baseUrl: string = getBaseUrl();
  private csrfToken: string | null = null;

  public setBaseUrl(url: string) {
    this.baseUrl = url;
  }

  public getBaseUrl(): string {
    return this.baseUrl;
  }

  public setCsrfToken(token: string) {
    this.csrfToken = token;
  }

  public async fetchCsrfToken(): Promise<string | null> {
    try {
      const res = await this.get<{ csrf_token: string }>('/auth.php?action=csrf');
      if (res.success && res.data?.csrf_token) {
        this.csrfToken = res.data.csrf_token;
        return this.csrfToken;
      }
    } catch {
      // Ignorer erè CSRF nan dev lokal
    }
    return null;
  }

  public async request<T = any>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<ApiResponse<T>> {
    const url = endpoint.startsWith('http')
      ? endpoint
      : `${this.baseUrl}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      ...(options.headers as Record<string, string> || {})
    };

    if (!(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }

    if (this.csrfToken) {
      headers['X-CSRF-Token'] = this.csrfToken;
    }

    try {
      const res = await fetch(url, {
        ...options,
        headers,
        credentials: 'include' // Pou sesyon PHP HttpOnly cookies
      });

      const json = await res.json().catch(() => ({
        success: res.ok,
        message: res.statusText,
        data: null,
        errors: [`HTTP Error ${res.status}`]
      }));

      return json;
    } catch (error) {
      return {
        success: false,
        message: error instanceof Error ? error.message : 'Erè koneksyon ak sèvè a',
        data: undefined,
        errors: [error instanceof Error ? error.message : 'Network error']
      };
    }
  }

  public get<T = any>(endpoint: string, params?: Record<string, any>): Promise<ApiResponse<T>> {
    let url = endpoint;
    if (params) {
      const searchParams = new URLSearchParams();
      Object.entries(params).forEach(([key, val]) => {
        if (val !== undefined && val !== null) {
          searchParams.append(key, String(val));
        }
      });
      const qs = searchParams.toString();
      if (qs) {
        url += (url.includes('?') ? '&' : '?') + qs;
      }
    }
    return this.request<T>(url, { method: 'GET' });
  }

  public post<T = any>(endpoint: string, body?: any): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, {
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body || {})
    });
  }

  public put<T = any>(endpoint: string, body?: any): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: body instanceof FormData ? body : JSON.stringify(body || {})
    });
  }

  public patch<T = any>(endpoint: string, body?: any): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, {
      method: 'PATCH',
      body: body instanceof FormData ? body : JSON.stringify(body || {})
    });
  }

  public delete<T = any>(endpoint: string, body?: any): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, {
      method: 'DELETE',
      body: body ? JSON.stringify(body) : undefined
    });
  }
}

export const apiClient = new ApiClient();
