// src/apiConfig.ts

// 1. Get the IP address or hostname from the browser's current URL
const currentHost = window.location.hostname;

// 2. Define your Backend path
const BACKEND_PATH = '/OJT2/backend/public';

// 3. Construct the dynamic URL
// 🔒 UPDATED: Changed 'http' to 'https' to match your secure backend
export const API_BASE_URL = `https://${currentHost}${BACKEND_PATH}`;