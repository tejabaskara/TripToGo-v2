<script setup>
import { ref } from 'vue';
import { useAuth } from '../composables/useAuth'
import { useRouter } from 'vue-router'

const router = useRouter()
const { login } = useAuth()

const email = ref('')
const password = ref('')
const formError = ref({})
const submitting = ref(false)

async function submit() {
  submitting.value = true
  formError.value = {}
  try {
    await login(email.value, password.value)
    router.push('/')
  } catch (e) {
    formError.value = e.response?.data?.errors ?? {}
  } finally {
    submitting.value = false
  }
}

</script>

<template>
    <h1>Login.vue</h1>
    <form @submit.prevent="submit">
        <input v-model="email" type="email" placeholder="email" required>
        <p v-if="formError.email">{{ formError.email[0] }}</p>
        <input v-model="password" type="password" placeholder="password" required>
        <button :disabled="submitting">Login</button>
    </form>
</template>
