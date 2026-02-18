import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../components/features/authentication/LoginForm.vue'),
    meta: {
      requiresAuth: false,
      title: 'Login - BSU Research Portal'
    }
  },
  {
    path: '/',
    component: () => import('../components/layouts/DashboardLayout.vue'),
    children: [
      {
        path: '',
        name: 'Home',
        component: () => import('../components/features/home/Homeview.vue'),
        meta: {
          requiresAuth: false,
          title: 'BSU Research Portal'
        }
      },
      {
        path: 'library',
        name: 'ResearchLibrary',
        component: () => import('../components/features/research/ResearchLibrary.vue'),
        meta: {
          requiresAuth: false,
          title: 'Research Library - BSU Research Portal'
        }
      },
      {
        path: 'workspace',
        name: 'MyWorkspace',
        component: () => import('../components/features/research/MyWorkspace.vue'),
        meta: {
          requiresAuth: true,
          title: 'My Workspace - BSU Research Portal'
        }
      },
      {
        path: 'approval',
        name: 'Approval',
        component: () => import('../components/features/research/Approval.vue'),
        meta: {
          requiresAuth: true,
          requiresRole: ['admin'],
          title: 'Approval - BSU Research Portal'
        }
      },
      {
        path: 'settings',
        name: 'Settings',
        component: () => import('../components/features/admin/Settings.vue'),
        meta: {
          requiresAuth: true,
          title: 'Settings - BSU Research Portal'
        }
      },
      {
        path: 'import',
        name: 'ImportCsv',
        component: () => import('../components/features/import/ImportCsv.vue'),
        meta: {
          requiresAuth: true,
          requiresRole: ['admin'],
          title: 'Import Data - BSU Research Portal'
        }
      },
      {
        path: 'users',
        name: 'UserManagement',
        component: () => import('../components/features/admin/UserManagement.vue'),
        meta: {
          requiresAuth: true,
          requiresRole: ['admin'],
          title: 'User Management - BSU Research Portal'
        }
      },
      {
        path: 'masterlist',
        name: 'Masterlist',
        component: () => import('../components/features/research/Masterlist.vue'),
        meta: {
          requiresAuth: true,
          requiresRole: ['admin'],
          title: 'Masterlist - BSU Research Portal'
        }
      },
      {
        path: 'logs',
        name: 'AdminLogs',
        component: () => import('../components/features/admin/AdminLogs.vue'),
        meta: {
          requiresAuth: true,
          requiresRole: ['admin'],
          title: 'System Logs - BSU Research Portal'
        }
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// Navigation Guard: Authentication & Authorization
router.beforeEach(async (to, _from, next) => {
  const { isAuthenticated, setUser, isInitialized, setInitialized } = useAuth()
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth)

  // 1. Initialize Auth State if not yet done
  if (!isInitialized.value) {
    try {
      // Import dynamically to avoid potential circular dependency if api imports router
      // But we verified api.ts doesn't.
      // However, we need 'api' here. 
      // Better to use authService if available, or raw api.
      // Let's use api directly for now as implemented in App.vue, or authService if cleaner.
      // Given I haven't seen authService content yet, I'll use a dynamic import or direct api if I import it at top.
      // I'll assume I can import api at top.
      const { default: api } = await import('../services/api')

      const response = await api.get('/auth/verify')
      if (response.data.status === 'success') {
        setUser(response.data.user)
        // Handle CSRF if needed (App.vue does it)
        if (response.data.csrf_token) {
          document.cookie = `csrf_cookie_name=${response.data.csrf_token}; path=/; domain=${window.location.hostname}; secure; samesite=None`;
          localStorage.setItem('csrf_token_backup', response.data.csrf_token);
        }
      }
    } catch (e) {
      console.warn("Auth check failed", e)
    } finally {
      setInitialized(true)
    }
  }

  // 2. Check Permissions
  if (requiresAuth && !isAuthenticated.value) {
    // User is not authenticated, redirect to login
    next({
      path: '/login',
      query: { redirect: to.fullPath }
    })
  } else if (to.path === '/login' && isAuthenticated.value) {
    // User is already logged in, redirect to home
    next('/')
  } else {
    // Check role requirements
    const requiredRoles = to.meta.requiresRole as string[] | undefined
    if (requiredRoles && requiredRoles.length > 0) {
      const userRole = useAuth().userRole.value
      if (!userRole || !requiredRoles.includes(userRole)) {
        // Unauthorized
        next('/') // or 403 page
        return
      }
    }
    next()
  }
})

// Navigation Guard: Update Page Title
router.afterEach((to) => {
  // Set page title from route meta or use default
  const title = (to.meta.title as string) || 'BSU Research Portal'
  document.title = title
})

export default router