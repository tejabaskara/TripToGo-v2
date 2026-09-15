<script setup>
import { ref, onMounted, watch } from 'vue'
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
    <input v-model="search" placeholder="Search..." />
    <select v-model="category">
        <option value="">All categories</option>
        <option value="beach">Beach</option>
        <option value="mountain">Mountain</option>
        <option value="museum">Museum</option>
    </select>

    <div v-if="loading">Loading...</div>
    <div v-else-if="error">{{ error }}</div>
    <div v-else-if="places.length === 0">No places found.</div>
    <ul v-else>
        <li v-for="p in places" :key="p.id">
            <RouterLink :to="`/places/${p.id}`">{{ p.name }}</RouterLink>
            — {{ p.category }} — {{ p.reviews_count }} reviews
            — {{ p.reviews_avg_rating ? Number(p.reviews_avg_rating).toFixed(1) : 'No ratings' }}
        </li>
    </ul>
    <div ref="mapEl" class="h-96"></div>
</template>
