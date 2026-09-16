<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import api from '../api'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import router from '../router'

const mapEl = ref(null)
let map
let markers = []

const places = ref([])
const loading = ref(true)
const error = ref(null)
const search = ref('')
const category = ref('')
let timer


async function fetchPlaces() {
  loading.value = true
  error.value = null
  try {
    const res = await api.get('/places', {
      params: { search: search.value, category: category.value }
    })
    places.value = res.data.data
  } catch (e) {
    error.value = 'Could not reach the server.'
  } finally {
    loading.value = false
  }
}

function updateMarkers() {
  markers.forEach(m => map.removeLayer(m))
  markers = places.value.map(p =>
    L.marker([p.latitude, p.longitude])
      .addTo(map)
      .bindPopup(`<b>${p.name}</b><br>${p.reviews_avg_rating ?? 'No ratings'}`)
      .on('click', () => router.push(`/places/${p.id}`))
    )
}

onMounted(() => {
  fetchPlaces()
  map = L.map(mapEl.value).setView([-6.2, 106.8], 10)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map)
})



watch([search, category], () => {
  clearTimeout(timer)
  timer = setTimeout(fetchPlaces, 300)
})
watch(places, updateMarkers)

</script>

<template>
    <div class="p-4 max-w-6xl mx-auto w-full">
        <div class="flex flex-col md:flex-row gap-2 mb-5">
            <input v-model="search" placeholder="Search..." class="border rounded px-3 py-2 flex-1 bg-white"/>
            <select v-model="category" class=" border rounded px-3 py-2 bg-white">
                <option value="">All categories</option>
                <option value="beach">Beach</option>
                <option value="mountain">Mountain</option>
                <option value="museum">Museum</option>
            </select>
        </div>

        <div class="p-4 max-w-fit mx-auto w-full">
            <div v-if="loading">Loading...</div>
            <div v-else-if="error" class="text-red-600">{{ error }}</div>
            <div v-else-if="places.length === 0" class="font-bold">No places found.</div>
            <div v-else class="flex flex-col mb-5 md:flex-row gap-4">
                <ul class="md: h-1/2 grid grid-cols-1 sm:grid-cols-2 gap-3 self-start">
                    <li v-for="p in places" :key="p.id" class="border rounded p-3 bg-white">
                        <RouterLink :to="`/places/${p.id}`" class="font-semibold text-blue-600">{{ p.name }}</RouterLink>
                        <div class="text-sm text-gray-600">
                            {{ p.category }} — {{ p.reviews_count }} reviews
                            — {{ p.reviews_avg_rating ? Number(p.reviews_avg_rating).toFixed(1) : 'No ratings' }}
                        </div>

                    </li>
                </ul>
            </div>
        </div>


        <div ref="mapEl" class="h-96 rounded-3xl"></div>
    </div>

</template>
