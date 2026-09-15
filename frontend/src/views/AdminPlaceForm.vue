<script setup>
import { onMounted, watch, ref, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

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
const error = ref('')
const submmiting = ref(false)
const loading = ref(true)

const mapEl = ref(null)
let map
let marker

async function fetchPlace() {
  try {
    const res = await api.get(`/places/${id}`)
    name.value = res.data.name
    description.value = res.data.description
    address.value = res.data.address
    category.value = res.data.category
    latitude.value = res.data.latitude
    longitude.value = res.data.longitude
    image.value = res.data.image
  } catch (e) {
    error.value = 'Could not reach the server'
  } finally {
    loading.value = false
  }
}

async function submit() {
  submmiting.value = true
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
    router.push('/admin/places')
  } catch (e) {
    if (e.response) {
      formError.value = e.response.data.errors ?? {}
    } else {
      error.value = 'Could not reach the server'
    }
  } finally {
    submmiting.value = false
  }

}


onMounted(async () => {
  if (isEdit) {
    await fetchPlace()
  } else {
    loading.value = false
  }

  await nextTick()

  map = L.map(mapEl.value).setView(
    isEdit && latitude.value !== ''  ? [latitude.value, longitude.value] : [-6.2, 106.8],
    isEdit && latitude.value !== '' ? 14 : 10
  )
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map)

  if (isEdit && latitude.value !== '') {
    marker = L.marker([latitude.value, longitude.value]).addTo(map)
  }

  map.on('click', e => {
    latitude.value = e.latlng.lat
    longitude.value = e.latlng.lng
  })

  watch([latitude, longitude], ([lat, lng]) => {
    if (lat === '' || lng === '') return
    if (marker) marker.setLatLng([lat, lng])
    else marker = L.marker([lat, lng]).addTo(map)
  })

})


</script>
<template>
    <div v-if="loading">Loading...</div>
    <div v-else>
        <h1>{{ isEdit? 'Edit Place' : 'Create Place'}}</h1>
        <p v-if="error">{{ error }}</p>

        <form @submit.prevent="submit">
            <input v-model="name" placeholder="Name" required>
            <p v-if="formError.name">{{ formError.name[0] }}</p>

            <input v-model="description" placeholder="Description" required>
            <p v-if="formError.description">{{ formError.description[0] }}</p>

            <input v-model="address" placeholder="Address">
            <select v-model="category">
                <option value="">All Category</option>
                <option value="beach">Beach</option>
                <option value="mountain">Mountain</option>
                <option value="museum">Museum</option>
            </select>
            <input v-model="image" placeholder="Image URL">

            <input v-model.number="latitude" type="number" step="any" placeholder="Latitude" required>
            <input v-model.number="longitude" type="number" step="any" placeholder="Longitude" required>
            <p v-if="formError.latitude
            ">{{ formError.latitude[0] }}</p>
            <p v-if="formError.longitude">{{ formError.longitude[0] }}</p>

            <div ref="mapEl" class="h-96"></div>

            <button :disabled="submmiting">{{ isEdit? 'Save' : 'Create' }}</button>
        </form>
    </div>
</template>
