import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api'; 

export function useLogout() {
  const router = useRouter();
  const isLoading = ref(false);

  const logout = async () => {
    isLoading.value = true;
    
    try {
      // 1. Inform the server to destroy the session/cookie
      await api.post('/auth/logout');
      console.log('Backend session destroyed');
    } catch (error) {
      // Logic: If the server is down, we still clear the frontend
      console.warn('Server logout failed, proceeding with local cleanup.', error);
    } finally {
      // ✅ 2. CLEAR LOCAL DATA (The "Bridge")
      // Remove the CSRF backup so the interceptor doesn't send an old token
      localStorage.removeItem('csrf_token_backup');

      // ✅ 3. CLEAR AXIOS HEADERS
      // Ensure the 'X-CSRF-TOKEN' header is removed from future requests
      delete api.defaults.headers.common['X-CSRF-TOKEN'];

      isLoading.value = false;

      // ✅ 4. REDIRECT & REFRESH (Optional but recommended)
      // Using router.push is good, but window.location.href = '/login' 
      // is sometimes better for logout because it completely resets the 
      // entire Vue application state (Vuex/Pinia) instantly.
      router.push('/login');
    }
  };

  return {
    logout,
    isLoading
  };
}