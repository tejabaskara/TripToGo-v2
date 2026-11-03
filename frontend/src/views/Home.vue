<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const router = useRouter()

const mapEl = ref(null)
const cardRow = ref(null)
let map
// place id -> Leaflet marker, so a card can find its pin
const markers = new Map()

const places = ref([])
const loading = ref(true)
const error = ref(null)
const search = ref('')
const category = ref('')
const selectedId = ref(null)
let timer

// Colours for each category (chip + photo placeholder)
const categoryStyle = {
  beach: { chip: 'bg-sky-100 text-sky-700', photo: 'from-sky-300 to-sky-600' },
  mountain: { chip: 'bg-green-100 text-green-800', photo: 'from-green-200 to-green-700' },
  museum: { chip: 'bg-amber-100 text-amber-800', photo: 'from-amber-200 to-amber-600' },
}
const defaultStyle = { chip: 'bg-gray-100 text-gray-700', photo: 'from-gray-200 to-gray-500' }
function styleFor(cat) {
  return categoryStyle[cat] ?? defaultStyle
}

function ratingText(p) {
  const avg = p.reviews_avg_rating ? Number(p.reviews_avg_rating).toFixed(1) : null
  const count = `${p.reviews_count} review${p.reviews_count === 1 ? '' : 's'}`
  return avg ? `★ ${avg} · ${count}` : `No ratings · ${count}`
}

// Pin drawn as SVG: green normally, bigger and orange when selected
function pinIcon(selected) {
  const w = selected ? 40 : 28
  const h = selected ? 54 : 38
  const color = selected ? '#EA580C' : '#15803D'
  return L.divIcon({
    className: '',
    iconSize: [w, h],
    iconAnchor: [w / 2, h],
    popupAnchor: [0, -h],
    html: `<svg width="${w}" height="${h}" viewBox="-2 -2 32 42" style="filter: drop-shadow(0 2px 3px rgba(0,0,0,.3))">
      <path d="M14 0C6.3 0 0 6.3 0 14c0 10.5 14 24 14 24s14-13.5 14-24C28 6.3 21.7 0 14 0z"
        fill="${color}" stroke="#fff" stroke-width="2.5"/>
      <circle cx="14" cy="14" r="5" fill="#fff"/>
    </svg>`,
  })
}

// Popup content built with DOM (textContent keeps place names safe from HTML injection)
function popupContent(p) {
  const box = document.createElement('div')
  box.className = 'min-w-40'
  const name = document.createElement('div')
  name.className = 'font-bold text-base text-gray-900'
  name.textContent = p.name
  const rating = document.createElement('div')
  rating.className = 'text-sm text-amber-700 my-1'
  rating.textContent = ratingText(p)
  const link = document.createElement('button')
  link.className = 'text-sm font-semibold text-green-800 cursor-pointer hover:underline'
  link.textContent = 'View details →'
  link.onclick = () => router.push(`/places/${p.id}`)
  box.append(name, rating, link)
  return box
}

async function fetchPlaces() {
  loading.value = true
  error.value = null
  try {
    const res = await api.get('/places', {
      params: { search: search.value, category: category.value },
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
  markers.clear()
  if (!places.value.some(p => p.id === selectedId.value)) selectedId.value = null

  places.value.forEach(p => {
    const marker = L.marker([p.latitude, p.longitude], { icon: pinIcon(p.id === selectedId.value) })
      .addTo(map)
      .bindPopup(popupContent(p), { closeButton: false })
      .on('click', () => select(p, { fromPin: true }))
    markers.set(p.id, marker)
  })

  // Zoom the map so every pin is visible
  if (places.value.length > 0) {
    const bounds = L.latLngBounds(places.value.map(p => [p.latitude, p.longitude]))
    map.fitBounds(bounds, { padding: [80, 80], maxZoom: 14 })
  }
}

// Called when a pin or a card is clicked
async function select(p, { fromPin = false } = {}) {
  const old = markers.get(selectedId.value)
  if (old) old.setIcon(pinIcon(false))

  selectedId.value = p.id
  const marker = markers.get(p.id)
  if (!marker) return
  marker.setIcon(pinIcon(true))

  if (fromPin) {
    // Pin clicked: scroll its card into view
    await nextTick()
    document.getElementById(`place-card-${p.id}`)
      ?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })
  } else {
    // Card clicked: move the map to the pin and open its popup
    map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 13), { duration: 0.6 })
    marker.openPopup()
  }
}

function scrollCards() {
  cardRow.value?.scrollBy({ left: 300, behavior: 'smooth' })
}

onMounted(() => {
  map = L.map(mapEl.value, { zoomControl: false }).setView([-6.2, 106.8], 10)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map)
  L.control.zoom({ position: 'topright' }).addTo(map)
  fetchPlaces()
})

onBeforeUnmount(() => {
  clearTimeout(timer)
  map?.remove()
})

watch([search, category], () => {
  clearTimeout(timer)
  timer = setTimeout(fetchPlaces, 300)
})
watch(places, updateMarkers)
</script>

<template>
  <div class="relative flex-1">
    <!-- Full-screen map -->
    <div ref="mapEl" class="absolute inset-0 z-0"></div>

    <!-- Floating search bar -->
    <div class="absolute top-4 inset-x-4 z-[1000] flex justify-center pointer-events-none">
      <div class="pointer-events-auto w-full max-w-xl flex items-center gap-3 bg-white rounded-full shadow-lg pl-5 pr-2 py-2">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="#6B7280" stroke-width="2" class="shrink-0">
          <circle cx="8.5" cy="8.5" r="7" /><path d="M14 14l5 5" />
        </svg>
        <input
          v-model="search"
          placeholder="Search places..."
          class="flex-1 min-w-0 outline-none text-base bg-transparent placeholder:text-gray-400"
        />
        <div class="w-px h-7 bg-gray-200"></div>
        <select
          v-model="category"
          class="bg-green-50 text-green-800 font-semibold text-sm rounded-full px-4 py-2 outline-none cursor-pointer"
        >
          <option value="">All categories</option>
          <option value="beach">Beach</option>
          <option value="mountain">Mountain</option>
          <option value="museum">Museum</option>
        </select>
      </div>
    </div>

    <!-- Loading / error / empty messages -->
    <div class="absolute inset-x-4 bottom-60 z-[1000] flex justify-center pointer-events-none">
      <div v-if="loading" class="pointer-events-auto flex items-center gap-2 bg-white rounded-full shadow-lg px-5 py-3 text-gray-700">
        <span class="w-4 h-4 border-[3px] border-green-700 border-t-transparent rounded-full animate-spin"></span>
        Loading places...
      </div>
      <div v-else-if="error" class="pointer-events-auto flex items-center gap-3 bg-red-50 border border-red-300 rounded-full shadow-lg px-5 py-3">
        <span class="font-semibold text-red-700">{{ error }}</span>
        <button @click="fetchPlaces" class="font-bold text-blue-700 cursor-pointer">Retry</button>
      </div>
      <div v-else-if="places.length === 0" class="pointer-events-auto bg-white rounded-full shadow-lg px-5 py-3 font-semibold text-gray-700">
        No places found. Try another search.
      </div>
    </div>

    <!-- Sliding row of place cards -->
    <div v-if="!loading && places.length > 0" class="absolute bottom-0 inset-x-0 z-[1000]">
      <div ref="cardRow" class="flex gap-4 overflow-x-auto snap-x px-4 md:px-8 pb-5 pt-2 [scrollbar-width:none]">
        <div
          v-for="p in places"
          :key="p.id"
          :id="`place-card-${p.id}`"
          @click="select(p)"
          class="snap-start shrink-0 w-72 bg-white rounded-2xl shadow-lg overflow-hidden cursor-pointer transition"
          :class="p.id === selectedId ? 'ring-[3px] ring-orange-600' : 'hover:-translate-y-1'"
        >
          <img v-if="p.image" :src="p.image" :alt="p.name" class="w-full h-28 object-cover" />
          <div v-else class="w-full h-28 bg-gradient-to-br" :class="styleFor(p.category).photo"></div>

          <div class="px-4 py-3">
            <div class="flex items-center justify-between gap-2">
              <span class="font-bold text-gray-900 truncate">{{ p.name }}</span>
              <RouterLink
                :to="`/places/${p.id}`"
                @click.stop
                class="shrink-0 text-xs font-semibold text-white bg-green-800 rounded-full px-3 py-1"
              >
                View
              </RouterLink>
            </div>
            <div class="flex items-center gap-2 mt-1.5">
              <span v-if="p.category" class="text-xs font-semibold rounded-full px-2.5 py-0.5 capitalize" :class="styleFor(p.category).chip">
                {{ p.category }}
              </span>
              <span class="text-sm text-amber-700">{{ ratingText(p) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- "Next" arrow (desktop only) -->
      <button
        @click="scrollCards"
        class="hidden md:flex absolute right-5 top-1/2 -translate-y-1/2 w-12 h-12 items-center justify-center bg-white rounded-full shadow-lg cursor-pointer"
        aria-label="Show more places"
      >
        <svg width="10" height="16" viewBox="0 0 10 16" fill="none" stroke="#166534" stroke-width="2.5"><path d="M2 2l6 6-6 6" /></svg>
      </button>
    </div>
  </div>
</template>
