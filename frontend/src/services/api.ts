// src/services/api.ts

import axios, { type InternalAxiosRequestConfig, type AxiosResponse, type AxiosError } from 'axios';
import type { ApiResponse } from '../types';

// 1. Dynamic Base URL (Auto-detects IP)
// This overrides the .env file so you don't need to change it when your IP changes.
export const getBaseUrl = () => {
  // Dynamically construct URL from browser location
  // Assumption: Backend is at the same hostname, but on standard port 80/443 (via XAMPP)
  const hostname = window.location.hostname;
  const protocol = window.location.protocol;
  return `${protocol}//${hostname}/OJT2/backend/public/index.php`;
};

export const getAssetUrl = () => {
  const hostname = window.location.hostname;
  const protocol = window.location.protocol;
  return `${protocol}//${hostname}/OJT2/backend/public`;
};

const api = axios.create({
  baseURL: getBaseUrl(),
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
  },
});

// ============================================================================
// REQUEST INTERCEPTOR - CSRF Token Handling
// ============================================================================
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const match = document.cookie.match(new RegExp('(^| )csrf_cookie_name=([^;]+)'));
  let token = match ? match[2] : null;

  if (!token) {
    token = localStorage.getItem('csrf_token_backup');
  }

  if (token && config.headers) {
    config.headers['X-CSRF-TOKEN'] = token;
  }

  return config;
}, (error) => {
  return Promise.reject(error);
});

// ============================================================================
// RESPONSE INTERCEPTOR - Error Handling
// ============================================================================
api.interceptors.response.use(
  (response: AxiosResponse) => {
    // Successful response - pass through
    return response;
  },
  (error: AxiosError<ApiResponse>) => {
    // Centralized error handling
    if (error.response) {
      // Server responded with error status
      const status = error.response.status;
      const data = error.response.data;

      // Log specific error cases for debugging
      if (status === 401) {
        console.warn('Unauthorized request - user may need to log in');
      } else if (status === 403) {
        console.warn('Forbidden - CSRF token may be invalid or permissions insufficient');
      } else if (status === 429) {
        console.warn('Too many requests - rate limit exceeded');
      } else if (status >= 500) {
        console.error('Server error:', data?.message || 'Internal server error');
      }
    } else if (error.request) {
      // Request made but no response received
      console.error('Network error - no response from server');
    } else {
      // Something else happened
      console.error('Request error:', error.message);
    }

    return Promise.reject(error);
  }
);

export default api;