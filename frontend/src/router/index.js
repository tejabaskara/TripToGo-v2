import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const history = createWebHistory()

const routes = [
  { path: '/', name: 'home', component: () => import('../views/Home.vue') },
  { path: '/login', name: 'login', component: () => import('../views/Login.vue') },
  { path: '/register', name: 'register', component: () => import('../views/Register.vue') },
  { path: '/places/:id', name: 'placeDetail', component: () => import('../views/PlaceDetail.vue') },
  { path: '/admin/places', name: 'adminPlaces', meta: {admin: true} , component: () => import('../views/AdminPlaces.vue') },
  { path: '/admin/places/create', name: 'adminPlaceCreate', meta:{admin: true},component: ()=> import ('../views/AdminPlaceForm.vue') },
  { path: '/admin/places/:id/edit', name: 'adminPlaceEdit', meta: { admin: true }, component: () => import('../views/AdminPlaceForm.vue') },
  { path: '/:pathMatch(.*)*', redirect:'/'}
]

const router = createRouter({
  history: history,
  routes: routes,
})

router.beforeEach(async (to) => {
  if (!to.meta.admin) return

  const { isLoggedIn, isAdmin, user, fetchUser } = useAuth()

  if (!isLoggedIn.value) return '/login'

  if (user.value === null) {
    try {
      await fetchUser()
    } catch {
      return '/login'
    }
  }

  if (!isAdmin.value) return '/'
})

export default router
