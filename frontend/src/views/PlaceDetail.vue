<script setup>
  import { ref, onMounted, nextTick } from 'vue'
  import api from '../api'
  import L from 'leaflet'
  import 'leaflet/dist/leaflet.css'
  import icon from 'leaflet/dist/images/marker-icon.png'
  import iconShadow from 'leaflet/dist/images/marker-shadow.png'
  import { useRoute } from 'vue-router'
  import { useAuth } from '../composables/useAuth'


const { isLoggedIn } = useAuth()
  const route = useRoute()

  const mapEl = ref(null)
  let map

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
  const rating = ref(5)
  const comment = ref('')
  const formError = ref({})
  const submmiting = ref(false)


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
      comment.value = ''
    } catch (e) {
      formError.value = e.response?.data?.errors ?? {}
    } finally {
      submmiting.value=false
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
        <p>{{ place.address}}</p>
        <p>{{ place.category}}</p>
        <hr>
        <p>{{ place.description }}</p>
        <p>
          {{ place.reviews_avg_rating ? Number(place.reviews_avg_rating).toFixed(1) : 'No ratings' }}
          ({{ place.reviews_count }} reviews)
        </p>
        <img v-if="place.image" :src="place.image" :alt="place.name" class="w-50 h-64 object-cover">
        <div ref="mapEl" class="h-96"></div>
        <br>
        <ul>
          <li v-for="r in place.reviews" :key="r.id">
            <strong>{{ r.user.name }}</strong> — {{ r.rating }}/5
            <p>{{ r.comment }}</p>
            <small>{{ r.created_at }}</small>
          </li>
        </ul>
        <hr>
        <br>
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
    </div>

</template>
