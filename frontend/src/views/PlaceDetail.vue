<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
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

// Average rating as a number (or null when there are no reviews)
const average = computed(() => {
  const avg = place.value?.reviews_avg_rating
  return avg ? Number(avg) : null
})

// How many reviews gave 5, 4, 3, 2 and 1 stars
const breakdown = computed(() => {
  const reviews = place.value?.reviews ?? []
  return [5, 4, 3, 2, 1].map(stars => {
    const count = reviews.filter(r => r.rating === stars).length
    const percent = reviews.length ? (count / reviews.length) * 100 : 0
    return { stars, count, percent }
  })
})

// Reviews list: newest first, show 3 until "Show all" is clicked
const SHOW_FIRST = 3
const showAll = ref(false)
const sortedReviews = computed(() =>
  [...(place.value?.reviews ?? [])].sort(
    (a, b) => new Date(b.created_at) - new Date(a.created_at) || b.id - a.id
  )
)
const visibleReviews = computed(() =>
  showAll.value ? sortedReviews.value : sortedReviews.value.slice(0, SHOW_FIRST)
)

// "2026-11-02T10:00:00Z" -> "2 Nov 2026"
function formatDate(value) {
  return new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

// Avatar colour picked from the name, so the same person always gets the same colour
const avatarColors = ['bg-green-100 text-green-800', 'bg-sky-100 text-sky-700', 'bg-amber-100 text-amber-800']
function avatarColor(name = '') {
  const sum = [...name].reduce((total, ch) => total + ch.charCodeAt(0), 0)
  return avatarColors[sum % avatarColors.length]
}

// Orange map pin, same shape as on the home page
function pinIcon() {
  return L.divIcon({
    className: '',
    iconSize: [40, 54],
    iconAnchor: [20, 54],
    html: `<svg width="40" height="54" viewBox="-2 -2 32 42" style="filter: drop-shadow(0 2px 3px rgba(0,0,0,.3))">
      <path d="M14 0C6.3 0 0 6.3 0 14c0 10.5 14 24 14 24s14-13.5 14-24C28 6.3 21.7 0 14 0z"
        fill="#EA580C" stroke="#fff" stroke-width="2.5"/>
      <circle cx="14" cy="14" r="5" fill="#fff"/>
    </svg>`,
  })
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
  // scrollWheelZoom off: scrolling the page should not zoom the small map
  map = L.map(mapEl.value, { scrollWheelZoom: false }).setView([p.latitude, p.longitude], 15)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map)
  L.marker([p.latitude, p.longitude], { icon: pinIcon() }).addTo(map)
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

          <!-- Reviews list -->
          <section class="bg-white rounded-2xl shadow-sm p-5 md:p-7">
            <h2 class="text-lg md:text-xl font-bold text-gray-900 mb-5">Reviews ({{ place.reviews_count }})</h2>

            <p v-if="sortedReviews.length === 0" class="text-gray-500">
              No reviews yet. Be the first to share your experience!
            </p>

            <ul v-else class="divide-y divide-gray-100">
              <li v-for="r in visibleReviews" :key="r.id" class="flex gap-3.5 py-5 first:pt-0">
                <div
                  class="w-10 h-10 md:w-11 md:h-11 shrink-0 rounded-full flex items-center justify-center font-bold"
                  :class="avatarColor(r.user?.name)"
                >
                  {{ (r.user?.name ?? '?').charAt(0).toUpperCase() }}
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-bold text-gray-900 truncate">{{ r.user?.name ?? 'Unknown' }}</span>
                    <span class="shrink-0 text-xs md:text-sm text-gray-400">{{ formatDate(r.created_at) }}</span>
                  </div>
                  <div class="tracking-wider text-sm" :aria-label="`${r.rating} out of 5 stars`">
                    <span v-for="n in 5" :key="n" :class="n <= r.rating ? 'text-amber-500' : 'text-gray-300'">★</span>
                  </div>
                  <p class="mt-1 text-gray-600 leading-relaxed whitespace-pre-line break-words">{{ r.comment }}</p>
                </div>
              </li>
            </ul>

            <button
              v-if="sortedReviews.length > SHOW_FIRST"
              @click="showAll = !showAll"
              class="w-full mt-2 bg-green-50 hover:bg-green-100 text-green-800 font-semibold text-sm rounded-xl py-3 cursor-pointer"
            >
              {{ showAll ? 'Show less' : `Show all ${sortedReviews.length} reviews` }}
            </button>
          </section>
        </div>

        <!-- Right column -->
        <div class="flex flex-col gap-6">
          <!-- Rating summary -->
          <section class="bg-white rounded-2xl shadow-sm p-5 md:p-6">
            <div class="flex items-center gap-4 mb-4">
              <span class="text-5xl font-bold text-gray-900">{{ average ? average.toFixed(1) : '–' }}</span>
              <div>
                <div class="text-xl tracking-wider">
                  <span
                    v-for="n in 5"
                    :key="n"
                    :class="average && n <= Math.round(average) ? 'text-amber-500' : 'text-gray-300'"
                  >★</span>
                </div>
                <p class="text-sm text-gray-500">
                  {{ place.reviews_count ? `Based on ${place.reviews_count} review${place.reviews_count === 1 ? '' : 's'}` : 'No reviews yet' }}
                </p>
              </div>
            </div>

            <div class="flex flex-col gap-2">
              <div v-for="row in breakdown" :key="row.stars" class="flex items-center gap-3 text-sm">
                <span class="w-7 shrink-0 text-gray-700">{{ row.stars }} ★</span>
                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full bg-amber-500 rounded-full" :style="{ width: row.percent + '%' }"></div>
                </div>
                <span class="w-6 shrink-0 text-right text-gray-500">{{ row.count }}</span>
              </div>
            </div>
          </section>

          <!-- Location -->
          <section class="bg-white rounded-2xl shadow-sm p-5 md:p-6 flex flex-col gap-4">
            <h2 class="text-lg md:text-xl font-bold text-gray-900">Location</h2>
            <div ref="mapEl" class="h-52 rounded-xl overflow-hidden z-0"></div>
            <p v-if="place.address" class="text-sm text-gray-600 leading-relaxed">{{ place.address }}</p>
            <RouterLink
              :to="{ path: '/', query: { place: place.id } }"
              class="text-center border-[1.5px] border-green-800 text-green-800 font-semibold text-sm rounded-full py-3 hover:bg-green-50"
            >
              Show on big map
            </RouterLink>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>
