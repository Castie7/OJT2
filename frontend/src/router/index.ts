import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import LoginForm from '../components/features/authentication/LoginForm.vue'
import Dashboard from '../components/features/dashboard/Dashboard.vue'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    name: 'Dashboard',
    component: Dashboard,
    // This ensures that when you open the site, the Dashboard is the "Home"
  },
  {
    path: '/login',
    name: 'Login',
    component: LoginForm
  },
  {
    // Redirect /dashboard to / to keep your URLs clean 
    // and prevent users from getting lost
    path: '/dashboard',
    redirect: '/'
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

export default router