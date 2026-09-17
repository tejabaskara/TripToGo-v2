<script setup>
import { onMounted, ref, watch } from 'vue';
import api from '../api';

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

onMounted(() => {
  fetchPlaces()
})

watch([search, category], () => {
  clearTimeout(timer)
  timer= setTimeout(fetchPlaces, 300)
})
watch(places)

</script>
<template>
    <div class="mx-auto p-4 max-w-6xl w-full">
        <h1 class="font-bold text-center mb-5 text-4xl">Admin Page</h1>
        <div class="flex flex-col md:flex-row gap-2 mb-5">
            <input v-model="search" placeholder="Search.." class="border rounded px-3 py-2 flex-1 bg-white"/>
            <select v-model="category" class="border rounded px-3 py-2 bg-white">
                <option value="">All categories</option>
                <option value="beach">Beach</option>
                <option value="mountain">Mountain</option>
                <option value="museum">Museum</option>
            </select>
        </div>

        <RouterLink to="/admin/places/create" class="inline-block bg-green-600 text-white rounded px-4 py-2 mb-5 hover:bg-green-700">
            New Place
        </RouterLink>

        <div v-if="loading">Loading...</div>
        <div v-else-if="error" class="text-red-600">{{ error }}</div>
        <div v-else-if="places.length === 0 " class="font-bold">No places found.</div>

        <ul class="flex flex-col gap-2">
            <li v-for="p in places" :key="p.id" class="border rounded p-3 bg-white flex items-center justify-between">
                <div>
                    <div class="font-semibold text-blue-700">
                        {{p.name}}
                    </div>
                    <div class="text-sm text-gray-800">
                        {{p.category}}
                    </div>
                </div>

                <div class="flex items-center gap-3 text-white">
                    <RouterLink :to="`/admin/places/${p.id}/edit`" class="inline-block bg-blue-400 px-3 py-2 rounded hover:bg-blue-600">Edit</RouterLink>

                    <button @click="remove(p.id)" class="inline-block bg-red-500 px-3 py-2 rounded cursor-pointer hover:bg-red-600">Delete</button>
                </div>

            </li>
        </ul>

</div>


</template>
