<script setup>
import { onMounted, ref, watch } from 'vue';
import api from '../api';
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import icon from 'leaflet/dist/images/marker-icon.png'
import iconShadow from 'leaflet/dist/images/marker-shadow.png'


const mapEl = ref(null)
let map
let markers = []

L.Marker.prototype.options.icon = L.icon({
  iconUrl: icon,
  shadowUrl: iconShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
})

const places = ref([])
const category = ref('')
const search = ref('')
const loading = ref(true)
const error = ref(null)
let timer

async function fetchPlaces() {
  loading.value = true
  error.value = null

  try {
    const res = await api.get('/places', {
      params: {
        search: search.value,
        category: category.value
      }
    })
    places.value = res.data.data
  } catch (e) {
    error.value = 'Could not reach the server'
  } finally {
    loading.value = false
  }
}

async function remove(id) {
  if (!window.confirm('Delete this place ?')) return
  try {
    await api.delete(`/places/${id}`)
    places.value = places.value.filter(p => p.id !== id)
  } catch (e) {
    error.value = 'Could not delete place'
  }
}

function updateMarkers() {
  markers.forEach(m => map.removeLayer(m))
  markers = places.value.map(p =>
    L.marker([p.latitude, p.longitude])
      .addTo(map)
      .bindPopup(`<b>${p.name}</b><br>${p.reviews_avg_rating ?? 'No rating'}<br><a href= "/places/${p.id}">View</a>`)
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
  timer= setTimeout(fetchPlaces, 300)
})
watch(places, updateMarkers)

</script>
<template>
    <h1>AdminPlaces.vue</h1>
    <input v-model="search" placeholder="Search.."/>
    <select v-model="category">
        <option value="">All categories</option>
        <option value="beach">Beach</option>
    </select>
    <div v-if="loading">Loading...</div>
    <div v-else-if="error">{{ error }}</div>
    <div v-else-if="places.length === 0 ">No places found.</div>
    <div v-else>
        <RouterLink to="/admin/places/create">New Place</RouterLink>
        <ul>
            <li v-for="p in places" :key="p.id">
                {{p.name}} - {{p.category}}
                <RouterLink :to="`/admin/places/${p.id}/edit`">Edit</RouterLink>
                <button @click="remove(p.id)">Delete</button>
            </li>
        </ul>
    </div>
    <div ref="mapEl" class="h-96"></div>

</template>
