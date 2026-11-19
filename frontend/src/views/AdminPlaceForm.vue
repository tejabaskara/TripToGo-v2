<script setup>
import { onMounted, onBeforeUnmount, watch, ref, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { pinIcon } from '../placeStyle'

const router = useRouter()
const route = useRoute()
const id = route.params.id
const isEdit = Boolean(id)

const name = ref('')
const description = ref('')
const address = ref('')
const category = ref('')
const latitude = ref('')
const longitude = ref('')
const image = ref('')

const formError = ref({})
const error = ref('')       // problem while saving
const loadError = ref('')   // problem while loading the place (edit only)
const submitting = ref(false)
const loading = ref(true)
const imageBroken = ref(false) // the image link could not be shown

const mapEl = ref(null)
let map
let marker

// Input style: red border when the server sent an error for this field
function inputClass(field) {
  return formError.value[field]
    ? 'border-red-500 bg-red-50 focus:ring-red-200'
    : 'border-gray-300 bg-gray-50 focus:border-green-700 focus:ring-green-100'
}

async function fetchPlace() {
  try {
    const res = await api.get(`/places/${id}`)
    name.value = res.data.name
    description.value = res.data.description
    address.value = res.data.address ?? ''
    category.value = res.data.category ?? ''
    latitude.value = res.data.latitude
    longitude.value = res.data.longitude
    image.value = res.data.image ?? ''
  } catch (e) {
    loadError.value = e.response?.status === 404 ? 'This place does not exist.' : 'Could not reach the server.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  submitting.value = true
  formError.value = {}
  error.value = ''

  const body = {
    name: name.value,
    description: description.value,
    latitude: latitude.value,
    longitude: longitude.value,
    address: address.value,
    category: category.value,
    image: image.value,
  }
  try {
    if (isEdit) {
      await api.put(`/places/${id}`, body)
    } else {
      await api.post(`/places`, body)
    }
    router.push('/admin/places?saved=1')
  } catch (e) {
    if (e.response?.status === 422) {
      formError.value = e.response.data.errors ?? {}
      error.value = 'Please fix the fields marked in red.'
    } else if (e.response) {
      error.value = 'Could not save the place.'
    } else {
      error.value = 'Could not reach the server.'
    }
  } finally {
    submitting.value = false
  }
}

function createMap() {
  const hasPoint = latitude.value !== '' && longitude.value !== ''

  map = L.map(mapEl.value, { scrollWheelZoom: false }).setView(
    hasPoint ? [latitude.value, longitude.value] : [-6.2, 106.8],
    hasPoint ? 14 : 10
  )
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map)

  if (hasPoint) {
    marker = L.marker([latitude.value, longitude.value], { icon: pinIcon(true) }).addTo(map)
  }

  // Click on the map = put the pin there (6 decimals is about 10 cm, plenty)
  map.on('click', e => {
    latitude.value = Number(e.latlng.lat.toFixed(6))
    longitude.value = Number(e.latlng.lng.toFixed(6))
  })

  // Typing numbers (or clicking) moves the pin
  watch([latitude, longitude], ([lat, lng]) => {
    if (lat === '' || lng === '' || isNaN(lat) || isNaN(lng)) return
    if (marker) marker.setLatLng([lat, lng])
    else marker = L.marker([lat, lng], { icon: pinIcon(true) }).addTo(map)
  })
}

// A new image link: try showing it again
watch(image, () => (imageBroken.value = false))

onMounted(async () => {
  if (isEdit) {
    await fetchPlace()
  } else {
    loading.value = false
  }

  // No form on the page when loading failed, so no map either
  if (loadError.value) return

  await nextTick()
  createMap()
})

onBeforeUnmount(() => {
  if (map) map.remove()
})
</script>

<template>
  <div class="flex-1 bg-[#F3F7F0]">
    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-24">
      <div class="flex items-center gap-2 bg-white rounded-full shadow-sm px-5 py-3 text-gray-700">
        <span class="w-4 h-4 border-[3px] border-green-700 border-t-transparent rounded-full animate-spin"></span>
        Loading place...
      </div>
    </div>

    <!-- Could not load the place -->
    <div v-else-if="loadError" class="flex flex-col items-center gap-4 py-24">
      <div class="bg-red-50 border border-red-300 rounded-full px-5 py-3 font-semibold text-red-700">{{ loadError }}</div>
      <RouterLink to="/admin/places" class="font-semibold text-green-800 hover:underline">← Back to places</RouterLink>
    </div>

    <form
      v-else
      @submit.prevent="submit"
      novalidate
      class="mx-auto w-full max-w-6xl px-4 md:px-8 pt-5 md:pt-7 pb-16 flex flex-col gap-4 md:gap-5"
    >
      <RouterLink
        to="/admin/places"
        class="self-start bg-white rounded-full shadow px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50"
      >
        ← Back to places
      </RouterLink>

      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">{{ isEdit ? 'Edit place' : 'New place' }}</h1>
        <p class="text-sm md:text-base text-gray-500 mt-1">
          {{ isEdit ? 'Changes are visible to visitors right after you save.' : 'Fill in the details, then pick the spot on the map.' }}
        </p>
      </div>

      <div class="flex flex-col lg:flex-row lg:items-start gap-4 md:gap-6">
        <!-- Details card -->
        <div class="flex-1 min-w-0 bg-white rounded-2xl shadow-sm p-5 md:p-7 flex flex-col gap-4.5">
          <h2 class="text-xl font-bold text-gray-900">Details</h2>

          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-semibold text-gray-700">Name *</span>
            <input
              v-model="name"
              placeholder="e.g. Ancol Beach"
              class="rounded-xl border px-3.5 py-3 outline-none focus:ring-4 placeholder:text-gray-400"
              :class="inputClass('name')"
            />
            <span v-if="formError.name" class="text-sm text-red-600">{{ formError.name[0] }}</span>
          </label>

          <div class="flex flex-col md:flex-row gap-4">
            <label class="md:w-56 flex flex-col gap-1.5">
              <span class="text-sm font-semibold text-gray-700">Category</span>
              <select
                v-model="category"
                class="rounded-xl border px-3.5 py-3 outline-none focus:ring-4 cursor-pointer"
                :class="[inputClass('category'), category ? 'text-gray-900' : 'text-gray-400']"
              >
                <option value="">Choose a category</option>
                <option value="beach">Beach</option>
                <option value="mountain">Mountain</option>
                <option value="museum">Museum</option>
              </select>
              <span v-if="formError.category" class="text-sm text-red-600">{{ formError.category[0] }}</span>
            </label>

            <label class="flex-1 flex flex-col gap-1.5">
              <span class="text-sm font-semibold text-gray-700">Address</span>
              <input
                v-model="address"
                placeholder="Street, area, city"
                class="rounded-xl border px-3.5 py-3 outline-none focus:ring-4 placeholder:text-gray-400"
                :class="inputClass('address')"
              />
              <span v-if="formError.address" class="text-sm text-red-600">{{ formError.address[0] }}</span>
            </label>
          </div>

          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-semibold text-gray-700">Description *</span>
            <textarea
              v-model="description"
              rows="4"
              placeholder="Tell visitors what makes this place special..."
              class="rounded-xl border px-3.5 py-3 outline-none focus:ring-4 resize-y leading-relaxed placeholder:text-gray-400"
              :class="inputClass('description')"
            ></textarea>
            <span v-if="formError.description" class="text-sm text-red-600">{{ formError.description[0] }}</span>
          </label>

          <div class="flex flex-col gap-1.5">
            <label class="flex flex-col gap-1.5">
              <span class="text-sm font-semibold text-gray-700">Image URL</span>
              <input
                v-model="image"
                placeholder="https://..."
                class="rounded-xl border px-3.5 py-3 outline-none focus:ring-4 placeholder:text-gray-400"
                :class="inputClass('image')"
              />
            </label>
            <span v-if="formError.image" class="text-sm text-red-600">{{ formError.image[0] }}</span>

            <!-- Preview -->
            <div class="relative mt-1 h-40 rounded-xl overflow-hidden">
              <img
                v-if="image && !imageBroken"
                :src="image"
                alt="Preview"
                @error="imageBroken = true"
                class="w-full h-full object-cover"
              />
              <div v-else class="w-full h-full rounded-xl bg-gray-100 border-[1.5px] border-dashed border-gray-300 flex items-center justify-center">
                <span class="text-sm text-gray-500">{{ image ? 'Could not load this image' : 'No image yet' }}</span>
              </div>
              <span v-if="image && !imageBroken" class="absolute left-2.5 bottom-2.5 bg-white/90 rounded-full px-2.5 py-0.5 text-xs font-semibold text-gray-700">
                Preview
              </span>
            </div>
          </div>
        </div>

        <!-- Location card -->
        <div class="lg:w-96 shrink-0 bg-white rounded-2xl shadow-sm p-5 md:p-6 flex flex-col gap-3.5">
          <h2 class="text-xl font-bold text-gray-900">Location *</h2>
          <p class="text-sm text-gray-500 leading-relaxed">Click on the map to place the pin, or type the numbers below.</p>

          <div
            ref="mapEl"
            class="h-64 rounded-xl overflow-hidden z-0 border"
            :class="formError.latitude || formError.longitude ? 'border-red-500' : 'border-transparent'"
          ></div>

          <div class="flex gap-3">
            <label class="flex-1 min-w-0 flex flex-col gap-1.5">
              <span class="text-sm font-semibold text-gray-700">Latitude</span>
              <input
                v-model.number="latitude"
                type="number"
                step="any"
                placeholder="Click the map"
                class="w-full rounded-xl border px-3.5 py-3 outline-none focus:ring-4 placeholder:text-gray-400"
                :class="inputClass('latitude')"
              />
            </label>
            <label class="flex-1 min-w-0 flex flex-col gap-1.5">
              <span class="text-sm font-semibold text-gray-700">Longitude</span>
              <input
                v-model.number="longitude"
                type="number"
                step="any"
                placeholder="Click the map"
                class="w-full rounded-xl border px-3.5 py-3 outline-none focus:ring-4 placeholder:text-gray-400"
                :class="inputClass('longitude')"
              />
            </label>
          </div>
          <span v-if="formError.latitude" class="text-sm text-red-600">{{ formError.latitude[0] }}</span>
          <span v-if="formError.longitude" class="text-sm text-red-600">{{ formError.longitude[0] }}</span>
        </div>
      </div>

      <!-- Save bar -->
      <div class="bg-white rounded-2xl shadow-sm px-4 md:px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <span class="text-sm" :class="error ? 'font-semibold text-red-600' : 'text-gray-500'">
          {{ error || 'Fields marked * are required.' }}
        </span>
        <div class="flex gap-2.5">
          <RouterLink
            to="/admin/places"
            class="flex-1 md:flex-none text-center font-semibold text-gray-700 border-[1.5px] border-gray-300 rounded-full px-5 py-2.5 hover:bg-gray-50"
          >
            Cancel
          </RouterLink>
          <button
            type="submit"
            :disabled="submitting"
            class="flex-1 md:flex-none font-semibold text-white bg-green-800 rounded-full px-6 py-2.5 cursor-pointer hover:bg-green-900 disabled:opacity-60 disabled:cursor-default"
          >
            {{ submitting ? 'Saving...' : isEdit ? 'Save changes' : 'Create place' }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
