import { computed, ref } from "vue"
import api from "../api"

const user = ref(null)
const token = ref(localStorage.getItem('token'))
const isLoggedIn = computed(() => !!token.value)
const isAdmin = computed(()=> user.value?.is_admin)


async function login(email, password) {
  const res = await api.post('/login', { email, password })
  token.value = res.data.token
  user.value = res.data.user
  localStorage.setItem('token', token.value)
}

async function register(name, email, password) {
  const res = await api.post('/register', {
    name, email, password
  })
  token.value = res.data.token
  user.value = res.data.user
  localStorage.setItem('token', token.value)
}

async function logout() {
  try {
    await api.post('/logout')
  } catch (e) {

  }

  token.value = null
  user.value = null
  localStorage.removeItem('token')
}

async function fetchUser() {
  const res = await api.get('/user')
  user.value = res.data
}


export function useAuth() {
  return {user, token, isLoggedIn, isAdmin, login, register, logout, fetchUser}
}
