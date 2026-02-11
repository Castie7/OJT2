// src/services/api.ts

import axios, { type InternalAxiosRequestConfig } from 'axios';

// 1. Dynamic Base URL (Auto-detects IP)
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

export default api;