const API_BASE_URL = 'http://localhost:8000/api';

interface RegisterData {
  first_name: string;
  last_name: string;
  email: string;
  password: string;
}

interface LoginData {
  email: string;
  password: string;
  remember?: boolean;
}

interface ApiResponse<T = any> {
  success: boolean;
  message?: string;
  user?: T;
  token?: string;
  errors?: Record<string, string[]>;
  posts?: any[];
  post?: any;
  comments?: any[];
  comment?: any;
  reply?: any;
  is_liked?: boolean;
  likes_count?: number;
  likers?: any[];
}

class ApiService {
  private getToken(): string | null {
    return localStorage.getItem('auth_token');
  }

  private setToken(token: string): void {
    localStorage.setItem('auth_token', token);
  }

  private removeToken(): void {
    localStorage.removeItem('auth_token');
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<ApiResponse<T>> {
    const url = `${API_BASE_URL}${endpoint}`;
    const token = this.getToken();

    const config: RequestInit = {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(token && { 'Authorization': `Bearer ${token}` }),
        ...options.headers,
      },
    };

    try {
      const response = await fetch(url, config);
      const data = await response.json();

      if (response.status === 401) {
        // Token expired or revoked: discard it so it is not reused.
        this.removeToken();
      }

      if (!response.ok) {
        throw new Error(data.message || 'Request failed');
      }

      return data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  async register(data: RegisterData): Promise<ApiResponse> {
    const response = await this.request('/register', {
      method: 'POST',
      body: JSON.stringify(data),
    });

    if (response.success && response.token) {
      this.setToken(response.token);
    }

    return response;
  }

  async login(data: LoginData): Promise<ApiResponse> {
    const response = await this.request('/login', {
      method: 'POST',
      body: JSON.stringify(data),
    });

    if (response.success && response.token) {
      this.setToken(response.token);
    }

    return response;
  }

  async logout(): Promise<ApiResponse> {
    try {
      return await this.request('/logout', {
        method: 'POST',
      });
    } finally {
      // Always drop the local token, even if the server call fails
      // (e.g. token already expired), so a stale credential never lingers.
      this.removeToken();
    }
  }

  async getUser(): Promise<ApiResponse> {
    return this.request('/me', {
      method: 'GET',
    });
  }

  async getPosts(): Promise<ApiResponse> {
    return this.request('/posts', {
      method: 'GET',
    });
  }

  async createPost(content: string, image?: File, isPrivate?: boolean): Promise<ApiResponse> {
    const formData = new FormData();
    formData.append('content', content);
    if (image) {
      formData.append('image', image);
    }
    if (isPrivate !== undefined) {
      formData.append('is_private', isPrivate ? '1' : '0');
    }

    const url = `${API_BASE_URL}/posts`;
    const token = this.getToken();

    const config: RequestInit = {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        ...(token && { 'Authorization': `Bearer ${token}` }),
      },
      body: formData,
    };

    try {
      const response = await fetch(url, config);
      const data = await response.json();

      if (response.status === 401) {
        // Token expired or revoked: discard it so it is not reused.
        this.removeToken();
      }

      if (!response.ok) {
        throw new Error(data.message || 'Request failed');
      }

      return data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  async likePost(postId: number): Promise<ApiResponse> {
    return this.request(`/posts/${postId}/like`, {
      method: 'POST',
    });
  }

  async getComments(postId: number): Promise<ApiResponse> {
    return this.request(`/posts/${postId}/comments`, {
      method: 'GET',
    });
  }

  async addComment(postId: number, content: string): Promise<ApiResponse> {
    return this.request(`/posts/${postId}/comments`, {
      method: 'POST',
      body: JSON.stringify({ content }),
    });
  }

  async likeComment(commentId: number): Promise<ApiResponse> {
    return this.request(`/comments/${commentId}/like`, {
      method: 'POST',
    });
  }

  async addReply(commentId: number, content: string): Promise<ApiResponse> {
    return this.request(`/comments/${commentId}/replies`, {
      method: 'POST',
      body: JSON.stringify({ content }),
    });
  }

  async likeReply(replyId: number): Promise<ApiResponse> {
    return this.request(`/replies/${replyId}/like`, {
      method: 'POST',
    });
  }

  isAuthenticated(): boolean {
    return !!this.getToken();
  }
}

export const api = new ApiService();
export type { RegisterData, LoginData, ApiResponse };
