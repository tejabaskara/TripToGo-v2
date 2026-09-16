<script setup>
import { onMounted } from 'vue'
import { useAuth } from './composables/useAuth'
import {useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const { isLoggedIn, fetchUser, user, isAdmin, logout } = useAuth()


async function handleLogout() {
  await logout()
  router.push('/')
}

onMounted(() => {
  if (isLoggedIn.value) {
    fetchUser().catch(()=>logout())
  }
})

</script>

<template>
    <div class="min-h-screen bg-green-200 flex flex-col">
        <nav v-if="!route.meta.hideNav" class="flex items-center justify-between bg-white shadow px-6 py-3">
            <RouterLink to="/" class="font-bold text-lg text-green-800">
                Triptogo
            </RouterLink>

            <div class="flex items-center gap-4 font-bold">
                <template v-if="isLoggedIn">
                    <span class="text-sm text-gray-700" >{{ user?.name }}</span>
                    <RouterLink v-if="isAdmin" to="/admin/places" class="text-blue-500">Admin</RouterLink>
                    <button @click="handleLogout" class="text-red-500 cursor-pointer">Logout</button>
                </template>

                <template v-else>
                    <RouterLink to="/login" class="text-blue-700">
                        Login
                    </RouterLink>
                    <RouterLink to="/register" class="text-blue-700">
                        Register
                    </RouterLink>
                </template>
            </div>

         </nav>
         <RouterView />
    </div>

</template>
