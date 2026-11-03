<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import api from '../api'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useRoute } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const { isLoggedIn } = useAuth()
const route = useRoute()

const mapEl = ref(null)
let map

const place = ref()
const loading = ref(true)
const error = ref(null)
const id = route.params.id
const rating = ref(5)
const comment = ref('')
const formError = ref({})
const submmiting = ref(false)

// Colours for each category (chip + photo placeholder), same as the home page
const categoryStyle = {
  beach: { chip: 'bg-sky-100 text-sky-700', photo: 'from-sky-300 to-sky-600' },
  mountain: { chip: 'bg-green-100 text-green-800', photo: 'from-green-200 to-green-700' },
  museum: { chip: 'bg-amber-100 text-amber-800', photo: 'from-amber-200 to-amber-600' },
}
const defaultStyle = { chip: 'bg-gray-100 text-gray-700', photo: 'from-gray-200 to-gray-500' }
function styleFor(cat) {
  return categoryStyle[cat] ?? defaultStyle
}

async function fetchDetailPlace() {
  loading.value = true
  error.value = null
  try {
    const res = await api.get(`/places/${id}`)
    place.value = res.data
  } catch (e) {
    error.value = 'Could not reach the server.'
  } finally {
    loading.value = false
  }
}

function initMap() {
  if (!place.value || !mapEl.value) return
  const p = place.value
  map?.remove()
  map = L.map(mapEl.value).setView([p.latitude, p.longitude], 14)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map)
  L.marker([p.latitude, p.longitude]).addTo(map).bindPopup(`<b>${p.name}</b>`)
}

async function load() {
  await fetchDetailPlace()
  await nextTick()
  initMap()
}

async function submitReview() {
  submmiting.value = true
  formError.value = {}
  try {
    const res = await api.post(`/places/${id}/reviews`, {
      rating: rating.value,
      comment: comment.value,
    })
    place.value.reviews.unshift(res.data)
    place.value.reviews_count++
    const total = place.value.reviews.reduce((sum, r) => sum + r.rating, 0)
    place.value.reviews_avg_rating = total / place.value.reviews_count
    comment.value = ''
  } catch (e) {
    formError.value = e.response?.data?.errors ?? {}
  } finally {
    submmiting.value = false
  }
}

onMounted(load)
onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div class="flex-1 bg-[#F3F7F0]">
    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-24">
      <div class="flex items-center gap-2 bg-white rounded-full shadow px-5 py-3 text-gray-700">
        <span class="w-4 h-4 border-[3px] border-green-700 border-t-transparent rounded-full animate-spin"></span>
        Loading place...
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex justify-center py-24">
      <div class="flex items-center gap-3 bg-red-50 border border-red-300 rounded-full shadow px-5 py-3">
        <span class="font-semibold text-red-700">{{ error }}</span>
        <button @click="load" class="font-bold text-blue-700 cursor-pointer">Retry</button>
      </div>
    </div>

    <div v-else-if="place">
      <!-- Hero photo -->
      <div class="relative h-64 md:h-[400px] overflow-hidden">
        <img v-if="place.image" :src="place.image" :alt="place.name" class="absolute inset-0 w-full h-full object-cover" />
        <div v-else class="absolute inset-0 bg-gradient-to-br" :class="styleFor(place.category).photo"></div>
        <!-- dark fade so the white text is readable -->
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-black/70"></div>

        <div class="relative h-full max-w-6xl mx-auto px-4 md:px-8 py-4 md:py-7 flex flex-col justify-between">
          <RouterLink
            to="/"
            class="self-start bg-white rounded-full shadow px-4 py-2 text-sm font-semibold text-green-800 hover:bg-green-50"
          >
            ← Back to map
          </RouterLink>

          <div class="flex flex-col gap-2 md:gap-3">
            <span
              v-if="place.category"
              class="self-start text-xs md:text-sm font-semibold rounded-full px-3 py-1 capitalize"
              :class="styleFor(place.category).chip"
            >
              {{ place.category }}
            </span>
            <h1 class="text-3xl md:text-5xl font-bold text-white">{{ place.name }}</h1>
            <p v-if="place.address" class="text-sm md:text-base text-gray-100">◉ {{ place.address }}</p>
          </div>
        </div>
      </div>

      <!-- Page body: 2 columns on desktop, 1 column on phone -->
      <div class="max-w-6xl mx-auto px-4 md:px-8 py-6 md:py-8 grid grid-cols-1 md:grid-cols-[1fr_376px] gap-6 items-start">
        <!-- Left column -->
        <div class="flex flex-col gap-6 min-w-0">
          <section class="bg-white rounded-2xl shadow-sm p-5 md:p-7">
            <h2 class="text-lg md:text-xl font-bold text-gray-900 mb-3">About this place</h2>
            <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ place.description }}</p>
          </section>

          <!-- Review form (styled in a later step) -->
          <section class="bg-white rounded-2xl shadow-sm p-5 md:p-7">
            <form v-if="isLoggedIn" @submit.prevent="submitReview">
              <select v-model="rating">
                <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
              </select>
              <textarea v-model="comment" placeholder="Your review"></textarea>
              <p v-if="formError.comment">{{ formError.comment[0] }}</p>
              <p v-if="formError.rating">{{ formError.rating[0] }}</p>
              <button :disabled="submmiting">Post Review</button>
            </form>
            <p v-else>
              <RouterLink to="/login">Login</RouterLink> to leave review
            </p>
          </section>

          <!-- Reviews list (styled in a later step) -->
          <section class="bg-white rounded-2xl shadow-sm p-5 md:p-7">
            <p>
              {{ place.reviews_avg_rating ? Number(place.reviews_avg_rating).toFixed(1) : 'No ratings' }}
              ({{ place.reviews_count }} reviews)
            </p>
            <ul>
              <li v-for="r in place.reviews" :key="r.id">
                <strong>{{ r.user.name }}</strong> — {{ r.rating }}/5
                <p>{{ r.comment }}</p>
                <small>{{ r.created_at }}</small>
              </li>
            </ul>
          </section>
        </div>

        <!-- Right column -->
        <div class="flex flex-col gap-6">
          <!-- Map (turned into the location card in a later step) -->
          <section class="bg-white rounded-2xl shadow-sm p-5 md:p-6">
            <div ref="mapEl" class="h-64 rounded-xl overflow-hidden z-0"></div>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>
