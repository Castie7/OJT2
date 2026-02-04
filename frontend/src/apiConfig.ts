// src/apiConfig.ts

// 1. Get the IP address or hostname from the browser's current URL
const currentHost = window.location.hostname;

// 2. Define your Backend path (The part after the IP)
// If using XAMPP, it is usually this:
<<<<<<< HEAD
const BACKEND_PATH = '/OJT2/backend/public';
=======
const BACKEND_PATH = '/OJT/backend/public';
>>>>>>> dd203bd (joebama code)

// 3. Construct the dynamic URL
// This automatically becomes "http://192.168.1.5/OJT2..." or "http://localhost/OJT2..."
export const API_BASE_URL = `http://${currentHost}${BACKEND_PATH}`;

// ----------------------------------------------------------------------
// OPTIONAL: If you use "php spark serve" instead of XAMPP
// Uncomment the line below (Spark uses port 8080)
// export const API_BASE_URL = `http://${currentHost}:8080`;
// ----------------------------------------------------------------------