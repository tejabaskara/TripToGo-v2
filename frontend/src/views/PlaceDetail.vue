<script setup>
  import { ref, onMounted, nextTick } from 'vue'
  import api from '../api'
  import L from 'leaflet'
  import 'leaflet/dist/leaflet.css'
  import icon from 'leaflet/dist/images/marker-icon.png'
  import iconShadow from 'leaflet/dist/images/marker-shadow.png'
  import { useRoute } from 'vue-router'

  const route = useRoute()

  const mapEl = ref(null)
  let map
  let marker

  L.Marker.prototype.options.icon = L.icon({
    iconUrl: icon,
    shadowUrl: iconShadow,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
  })
  const place = ref()
  const loading = ref(true)
  const error = ref(null)
  const id = route.params.id

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

  onMounted(async () => {
    await fetchDetailPlace()
    await nextTick()
    if (!place.value) return
    const p = place.value
    map = L.map(mapEl.value).setView([p.latitude, p.longitude], 14)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map)
    L.marker([p.latitude, p.longitude]).addTo(map).bindPopup(`<b>${p.name}</b>`)
  })


</script>
<template>
    <div v-if="loading">Loading...</div>
    <div v-else-if="error">
        {{ error }}
    </div>
    <div v-else-if="place">
        <h1>{{ place.name }}</h1>
        <br>
        <h2>{{ place.address}}</h2>
        <p>{{ place.description }}</p>
        <div ref="mapEl" class="h-96"></div>
        <br>

    </div>
</template>
