import { createRouter, createWebHistory } from 'vue-router'

const history = createWebHistory()

const routes = [
  { path: '/', name: 'home', component: () => import('../views/Home.vue') },
  { path: '/login', name: 'login', component: () => import('../views/Login.vue') },
  { path: '/register', name: 'register', component: () => import('../views/Register.vue') },
  { path: '/places/:id', name: 'placeDetail', component: () => import('../views/PlaceDetail.vue') },
  { path: '/admin/places', name: 'adminPlaces', component: () => import('../views/AdminPlaces.vue') },
  { path: '/admin/places/create', name: 'adminPlaceCreate', component: ()=> import ('../views/AdminPlaceForm.vue') },
  { path: '/admin/places/:id/edit', name: 'adminPlaceEdit', component: ()=> import ('../views/AdminPlaceForm.vue') }



]

const router = createRouter({
  history: history,
  routes: routes,
})

export default router
