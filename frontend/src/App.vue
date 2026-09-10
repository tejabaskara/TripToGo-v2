<script setup>
import { onMounted } from 'vue'
import { useAuth } from './composables/useAuth'
import { useRouter } from 'vue-router'

const router = useRouter()
const { isLoggedIn, fetchUser, user, isAdmin, logout } = useAuth()


async function handleLogout() {
  await logout()
  router.push('/')
}

onMounted(() => {
  if (isLoggedIn.value) {
    fetchUser().catch(()=>logout)
  }
})

</script>

<template>
 <nav>
    <RouterLink to="/">
        Triptogo
    </RouterLink>
    <template v-if="isLoggedIn">
        <span>{{ user?.name }}</span>
        <RouterLink v-if="isAdmin">Admin</RouterLink>
        <button @click="handleLogout">Logout</button>
    </template>
    <template v-else>
        <RouterLink to="/login">
            Login
        </RouterLink>
        <RouterLink to="/register">
            Register
        </RouterLink>
    </template>
 </nav>
 <RouterView />
</template>
