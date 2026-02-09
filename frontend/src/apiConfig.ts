// src/services/api.ts

import axios, { type InternalAxiosRequestConfig } from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_BACKEND_URL + '/index.php',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

api.interceptors.request.use((config: InternalAxiosRequestConfig) => {

  // 1. Try to get token from Cookie
  const match = document.cookie.match(new RegExp('(^| )csrf_cookie_name=([^;]+)'));
  let token = match ? match[2] : null;

  // 2. Fallback: Check LocalStorage if cookie is blocked
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

export default api;