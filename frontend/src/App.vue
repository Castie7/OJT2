<script setup>
import { ref, onMounted } from 'vue'
import LoginForm from './components/LoginForm.vue'
import Dashboard from './components/Dashboard.vue'

const currentPage = ref('dashboard') 
const currentUser = ref(null) // This will now store { id, name, role }

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
      // Ask backend to verify token and return User Role & ID
      const response = await fetch('http://localhost:8080/auth/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token })
      });
      const data = await response.json();
      
      if (data.status === 'success') {
        // Restore the full user object (id, name, role)
        currentUser.value = data.user;
        console.log("Session restored. Role:", currentUser.value.role);
      } else {
        // Token expired, clear it
        deleteCookie('auth_token');
      }
    } catch (e) {
      console.error("Session check failed", e);
    }
  }
});

// --- 2. LOGIN ACTION ---
const onLoginSuccess = (data) => {
  // 'data.user' is now an object: { id: 1, name: "Admin", role: "admin" }
  currentUser.value = data.user; 
  currentPage.value = 'dashboard';
  
  console.log("Login Success. Role:", currentUser.value.role);

  // Save Token to Cookie (Valid for 7 days)
  if (data.token) {
    setCookie('auth_token', data.token, 7);
  }
}

// --- 3. LOGOUT ACTION ---
const onLogout = async () => {
  const token = getCookie('auth_token');
  
  if (token) {
    await fetch('http://localhost:8080/auth/logout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: token })
    });
  }

  // Clear Frontend State
  currentUser.value = null;
  currentPage.value = 'dashboard';
  deleteCookie('auth_token'); 
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
    @logout-click="onLogout" 
  />
</template>