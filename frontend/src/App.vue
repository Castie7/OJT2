<script setup>
import { ref, onMounted } from 'vue'
import LoginForm from './components/LoginForm.vue'
import Dashboard from './components/Dashboard.vue'

const currentPage = ref('dashboard') 
const currentUser = ref(null) 

// --- HELPER: MANAGE COOKIES ---
const setCookie = (name, value, days) => {
  const date = new Date();
  date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
  document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/`;
}

const getCookie = (name) => {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(';').shift();
  return null;
}

const deleteCookie = (name) => {
  document.cookie = `${name}=; max-age=0; path=/`;
}

// --- 1. CHECK SESSION ON REFRESH ---
onMounted(async () => {
  const token = getCookie('auth_token');
  if (token) {
    try {
      const response = await fetch('http://localhost:8080/auth/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token })
      });
      const data = await response.json();
      if (data.status === 'success') {
        currentUser.value = data.user;
      } else {
        deleteCookie('auth_token');
      }
    } catch (e) { console.error("Session check failed", e); }
  }
});

// --- 2. LOGIN ACTION ---
const onLoginSuccess = (data) => {
  currentUser.value = data.user; 
  currentPage.value = 'dashboard';
  if (data.token) setCookie('auth_token', data.token, 7);
}

// --- 3. LOGOUT LOGIC ---
const handleLogout = async () => {
  // 1. Get current token
  const token = getCookie('auth_token'); // Reused helper for cleaner code

  // 2. Notify Backend
  if (token) {
    try {
      await fetch('http://localhost:8080/auth/logout', { 
        method: 'POST',
        headers: { 'Authorization': token }
      });
    } catch (e) { console.error("Logout API failed", e); }
  }

  // 3. DELETE COOKIE
  deleteCookie('auth_token');

  // 4. RESET STATE
  currentUser.value = null;
  
  // 5. FORCE REDIRECT
  window.location.href = '/'; 
}

const goToLogin = () => { currentPage.value = 'login' }
</script>

<template>
  <LoginForm 
    v-if="currentPage === 'login'" 
    @login-success="onLoginSuccess" 
    @back="currentPage = 'dashboard'" 
  />

  <Dashboard 
    v-else 
    :currentUser="currentUser" 
    @login-click="goToLogin"
    @logout-click="handleLogout" 
  />
</template>