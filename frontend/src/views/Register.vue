<script setup>
import { ref } from 'vue';
import { useAuth } from '../composables/useAuth'
import { useRouter } from 'vue-router'

const router = useRouter()
const { register } = useAuth()

const name = ref('')
const email = ref('')
const password = ref('')
const formError = ref({})
const submitting = ref(false)
const error = ref('')

async function submit() {
  submitting.value = true
  formError.value = {}
  try {
    await register(name.value, email.value, password.value)
    router.push('/')
  } catch (e) {
    if (e.response) {
        formError.value = e.response?.data?.errors ?? {}
    } else {
        error.value = 'Could not reach the server'
    }
  } finally {
    submitting.value = false
  }
}

</script>

<template>
    <h1>Register.vue</h1>
    <form @submit.prevent="submit">
            <input v-model="name" placeholder="name" required></input>
            <input v-model="email" type="email" placeholder="email" required>
            <p v-if="formError.email">{{ formError.email[0] }}</p>
            <input v-model="password" type="password" placeholder="password" required>
            <button :disabled="submitting">Register</button>
        </form>
</template>
