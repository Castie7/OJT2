// src/services/api.ts

import axios, { type InternalAxiosRequestConfig } from 'axios';

// 1. Get the URL from the .env file
const BASE_URL = import.meta.env.VITE_BACKEND_URL;

const api = axios.create({
  // Append '/index.php' for API calls
  baseURL: `${BASE_URL}/index.php`, 
  withCredentials: true, 
  headers: {
    'Content-Type': 'application/json',
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