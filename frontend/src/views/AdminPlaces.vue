<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import { styleFor, ratingText } from '../placeStyle'

const places = ref([])
const category = ref('')
const search = ref('')
const loading = ref(true)
const error = ref(null)
let timer

// Paging: the API sends 15 places per page
const page = ref(1)
const lastPage = ref(1)
const from = ref(0)
const to = ref(0)
const total = ref(0)

async function fetchPlaces() {
  loading.value = true
  error.value = null

  try {
    const res = await api.get('/places', {
      params: {
        search: search.value,
        category: category.value,
        page: page.value,
      }
    })
    places.value = res.data.data
    lastPage.value = res.data.last_page
    from.value = res.data.from ?? 0
    to.value = res.data.to ?? 0
    total.value = res.data.total
  } catch (e) {
    error.value = 'Could not reach the server.'
  } finally {
    loading.value = false
  }
}

function goToPage(n) {
  if (n < 1 || n > lastPage.value) return
  page.value = n
  fetchPlaces()
}

// Delete popup: toDelete is the place the popup asks about (null = popup closed)
const toDelete = ref(null)
const deleting = ref(false)
const deleteError = ref('')

function askDelete(p) {
  toDelete.value = p
  deleteError.value = ''
}

function closeDelete() {
  if (deleting.value) return
  toDelete.value = null
}

async function confirmDelete() {
  deleting.value = true
  deleteError.value = ''
  try {
    await api.delete(`/places/${toDelete.value.id}`)
    toDelete.value = null
    // The last place on this page is gone: go back one page
    if (places.value.length === 1 && page.value > 1) page.value--
    // Load again so the count and paging are right
    await fetchPlaces()
  } catch (e) {
    deleteError.value = 'Could not delete the place.'
  } finally {
    deleting.value = false
  }
}

// "Place saved" message: the form sends us to /admin/places?saved=1
const route = useRoute()
const router = useRouter()
const saved = ref(false)

onMounted(() => {
  fetchPlaces()

  if (route.query.saved) {
    saved.value = true
    router.replace({ query: {} }) // remove ?saved so a page refresh doesn't show it again
    setTimeout(() => (saved.value = false), 3000)
  }
})

// New search or filter: wait until typing stops, then start again from page 1
watch([search, category], () => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    page.value = 1
    fetchPlaces()
  }, 300)
})
</script>

<template>
  <div class="flex-1 bg-[#F3F7F0]">
    <div class="mx-auto w-full max-w-6xl px-4 md:px-8 pt-6 md:pt-10 pb-16 flex flex-col gap-5 md:gap-6">

      <!-- Header -->
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Manage places</h1>
          <p class="text-sm md:text-base text-gray-500 mt-1">
            {{ total }} place{{ total === 1 ? '' : 's' }}<span class="hidden md:inline"> in the catalogue · add, edit or remove them here</span>
          </p>
        </div>
        <RouterLink
          to="/admin/places/create"
          class="shrink-0 bg-green-800 text-white font-semibold rounded-full px-4 md:px-6 py-2.5 md:py-3 shadow-lg shadow-green-800/25 hover:bg-green-900"
        >
          + New<span class="hidden md:inline"> place</span>
        </RouterLink>
      </div>

      <!-- Search + category -->
      <div class="flex items-center gap-3 bg-white rounded-full shadow-sm pl-5 pr-2 py-2">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="#6B7280" stroke-width="2" class="shrink-0">
          <circle cx="8.5" cy="8.5" r="7" /><path d="M14 14l5 5" />
        </svg>
        <input
          v-model="search"
          placeholder="Search places by name..."
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

      <!-- Loading / error / empty -->
      <div v-if="loading" class="flex justify-center py-10">
        <div class="flex items-center gap-2 bg-white rounded-full shadow-sm px-5 py-3 text-gray-700">
          <span class="w-4 h-4 border-[3px] border-green-700 border-t-transparent rounded-full animate-spin"></span>
          Loading places...
        </div>
      </div>
      <div v-else-if="error" class="flex justify-center py-10">
        <div class="flex items-center gap-3 bg-red-50 border border-red-300 rounded-full px-5 py-3">
          <span class="font-semibold text-red-700">{{ error }}</span>
          <button @click="fetchPlaces" class="font-bold text-blue-700 cursor-pointer">Retry</button>
        </div>
      </div>
      <div v-else-if="places.length === 0" class="flex justify-center py-10">
        <div class="bg-white rounded-full shadow-sm px-5 py-3 font-semibold text-gray-700">
          No places match your search.
        </div>
      </div>

      <template v-else>
        <!-- Desktop: table -->
        <div class="hidden md:block bg-white rounded-2xl shadow-sm overflow-hidden">
          <table class="w-full text-left">
            <thead class="bg-gray-50 text-xs font-semibold tracking-wide text-gray-500 uppercase">
              <tr>
                <th class="pl-6 pr-4 py-3 w-24">Photo</th>
                <th class="px-4 py-3">Place</th>
                <th class="px-4 py-3 w-36">Category</th>
                <th class="px-4 py-3 w-48">Rating</th>
                <th class="pl-4 pr-6 py-3 w-48 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in places" :key="p.id" class="border-t border-slate-100">
                <td class="pl-6 pr-4 py-3.5">
                  <img v-if="p.image" :src="p.image" :alt="p.name" class="w-16 h-12 rounded-lg object-cover" />
                  <div v-else class="w-16 h-12 rounded-lg bg-gradient-to-br" :class="styleFor(p.category).photo"></div>
                </td>
                <td class="px-4 py-3.5">
                  <RouterLink :to="`/places/${p.id}`" class="font-bold text-gray-900 hover:underline">{{ p.name }}</RouterLink>
                  <div class="text-sm" :class="p.address ? 'text-gray-500' : 'text-gray-400'">
                    {{ p.address || 'No address yet' }}
                  </div>
                </td>
                <td class="px-4 py-3.5">
                  <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 capitalize" :class="styleFor(p.category).chip">
                    {{ p.category || 'No category' }}
                  </span>
                </td>
                <td class="px-4 py-3.5 text-sm" :class="p.reviews_count ? 'text-amber-700' : 'text-gray-400'">
                  {{ p.reviews_count ? ratingText(p) : 'No reviews yet' }}
                </td>
                <td class="pl-4 pr-6 py-3.5">
                  <div class="flex justify-end gap-2">
                    <RouterLink
                      :to="`/admin/places/${p.id}/edit`"
                      class="text-sm font-semibold text-green-800 border-[1.5px] border-green-800 rounded-full px-4 py-1.5 hover:bg-green-50"
                    >
                      Edit
                    </RouterLink>
                    <button
                      @click="askDelete(p)"
                      class="text-sm font-semibold text-red-600 border-[1.5px] border-red-300 rounded-full px-4 py-1.5 cursor-pointer hover:bg-red-50"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Phone: cards -->
        <div class="md:hidden flex flex-col gap-3">
          <div v-for="p in places" :key="p.id" class="bg-white rounded-2xl shadow-sm p-3 flex flex-col gap-3">
            <div class="flex items-center gap-3">
              <img v-if="p.image" :src="p.image" :alt="p.name" class="w-16 h-16 rounded-xl object-cover shrink-0" />
              <div v-else class="w-16 h-16 rounded-xl bg-gradient-to-br shrink-0" :class="styleFor(p.category).photo"></div>
              <div class="min-w-0">
                <RouterLink :to="`/places/${p.id}`" class="block font-bold text-gray-900 truncate">{{ p.name }}</RouterLink>
                <div class="flex items-center gap-2 mt-1.5">
                  <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 capitalize" :class="styleFor(p.category).chip">
                    {{ p.category || 'No category' }}
                  </span>
                  <span class="text-sm truncate" :class="p.reviews_count ? 'text-amber-700' : 'text-gray-400'">
                    {{ p.reviews_count ? ratingText(p) : 'No reviews yet' }}
                  </span>
                </div>
              </div>
            </div>
            <div class="flex gap-2">
              <RouterLink
                :to="`/admin/places/${p.id}/edit`"
                class="flex-1 text-center text-sm font-semibold text-green-800 border-[1.5px] border-green-800 rounded-full py-2"
              >
                Edit
              </RouterLink>
              <button
                @click="askDelete(p)"
                class="flex-1 text-sm font-semibold text-red-600 border-[1.5px] border-red-300 rounded-full py-2 cursor-pointer"
              >
                Delete
              </button>
            </div>
          </div>
        </div>

        <!-- Paging -->
        <div class="flex items-center justify-between gap-3 md:bg-white md:rounded-2xl md:shadow-sm md:px-6 md:py-4">
          <span class="hidden md:inline text-sm text-gray-500">Showing {{ from }}–{{ to }} of {{ total }} places</span>
          <div class="flex items-center justify-between md:justify-end gap-3 w-full md:w-auto">
            <button
              @click="goToPage(page - 1)"
              :disabled="page === 1"
              class="text-sm font-semibold rounded-full px-4 py-1.5 border-[1.5px] cursor-pointer disabled:cursor-default disabled:bg-gray-50 disabled:border-gray-200 disabled:text-gray-400 border-green-800 text-green-800 bg-white"
            >
              ← Previous
            </button>
            <span class="text-sm font-semibold text-gray-700">Page {{ page }} of {{ lastPage }}</span>
            <button
              @click="goToPage(page + 1)"
              :disabled="page === lastPage"
              class="text-sm font-semibold rounded-full px-4 py-1.5 border-[1.5px] cursor-pointer disabled:cursor-default disabled:bg-gray-50 disabled:border-gray-200 disabled:text-gray-400 border-green-800 text-green-800 bg-white"
            >
              Next →
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- Delete popup -->
    <div
      v-if="toDelete"
      @click.self="closeDelete"
      class="fixed inset-0 z-[2000] bg-gray-900/45 flex items-center justify-center px-4"
    >
      <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-7 flex flex-col gap-3">
        <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-2xl font-bold">!</div>
        <h2 class="text-xl font-bold text-gray-900">Delete {{ toDelete.name }}?</h2>
        <p class="text-gray-600 leading-relaxed">
          <template v-if="toDelete.reviews_count">
            This also removes its {{ toDelete.reviews_count }} review{{ toDelete.reviews_count === 1 ? '' : 's' }}.
          </template>
          This cannot be undone.
        </p>
        <p v-if="deleteError" class="text-sm font-semibold text-red-600">{{ deleteError }}</p>
        <div class="flex justify-end gap-2.5 pt-2">
          <button
            @click="closeDelete"
            :disabled="deleting"
            class="font-semibold text-gray-700 border-[1.5px] border-gray-300 rounded-full px-5 py-2.5 cursor-pointer disabled:opacity-60"
          >
            Cancel
          </button>
          <button
            @click="confirmDelete"
            :disabled="deleting"
            class="font-semibold text-white bg-red-600 rounded-full px-5 py-2.5 cursor-pointer hover:bg-red-700 disabled:opacity-60"
          >
            {{ deleting ? 'Deleting...' : 'Yes, delete' }}
          </button>
        </div>
      </div>
    </div>

    <!-- "Place saved" message -->
    <Transition
      enter-from-class="opacity-0 translate-y-3"
      leave-to-class="opacity-0 translate-y-3"
      enter-active-class="transition duration-300"
      leave-active-class="transition duration-300"
    >
      <div
        v-if="saved"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[2000] bg-green-50 border-[1.5px] border-green-300 text-green-800 font-semibold rounded-full shadow-lg px-5 py-3"
      >
        ✓ Place saved
      </div>
    </Transition>
  </div>
</template>
