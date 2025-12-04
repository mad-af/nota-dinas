<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  logs: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search || '')
const method = ref(props.filters.method || '')
const status = ref(props.filters.status || '')

const detail = ref(null)
const showDetail = ref(false)
function openDetail(item) { detail.value = item; showDetail.value = true }
function closeDetail() { showDetail.value = false; detail.value = null }

function applyFilters() {
  router.get(route('api-logs.index'), { search: search.value, method: method.value, status: status.value }, { preserveState: true, replace: true })
}

watch([search, method, status], () => { /* debounce could be added */ })
</script>

<template>

  <Head title="API Logs" />
  <AuthenticatedLayout>
    <div class="pt-6 mx-2 sm:pt-24 sm:px-2">
      <div class="mx-auto space-y-6 max-w-7xl sm:px-6 lg:px-6">
        <div class="p-6 bg-white shadow-sm sm:rounded-lg">
          <div class="flex flex-wrap gap-3 justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800 sm:text-xl">API Logs</h2>
            <div class="flex flex-wrap gap-2 items-center">
              <input v-model="search" @keyup.enter="applyFilters" type="text"
                placeholder="Cari endpoint/correlation/error" class="px-2 py-1 w-64 text-sm rounded border" />
              <select v-model="method" @change="applyFilters" class="px-2 py-1 text-sm rounded border">
                <option value="">Metode: Semua</option>
                <option value="GET">GET</option>
                <option value="POST">POST</option>
              </select>
              <select v-model="status" @change="applyFilters" class="px-2 py-1 text-sm rounded border">
                <option value="">Status: Semua</option>
                <option value="200">200</option>
                <option value="400">400</option>
                <option value="401">401</option>
                <option value="403">403</option>
                <option value="500">500</option>
              </select>
              <button @click="applyFilters" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded">Terapkan</button>
            </div>
          </div>
        </div>

        <div class="p-0 bg-white shadow-sm sm:rounded-lg">
          <div class="overflow-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-gray-50 text-gray-700">
                  <th class="px-4 py-2 text-left">Waktu</th>
                  <th class="px-4 py-2 text-left">File Lampiran</th>
                  <th class="px-4 py-2 text-left">User</th>
                  <th class="px-4 py-2 text-left">Endpoint</th>
                  <th class="px-4 py-2 text-left">Method</th>
                  <th class="px-4 py-2 text-left">Status</th>
                  <th class="px-4 py-2 text-left">Durasi</th>
                  <th class="px-4 py-2 text-left">Error</th>
                  <th class="px-4 py-2 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in (props.logs?.data || [])" :key="item.id" class="border-t">
                  <td class="px-4 py-2 text-gray-700">{{ new Date(item.created_at).toLocaleString() }}</td>
                  <td class="px-4 py-2 text-gray-700">{{ item.correlation_name || '-' }}</td>
                  <td class="px-4 py-2 text-gray-700">{{ item.user_name || '-' }}</td>
                  <td class="px-4 py-2 text-gray-700">{{ item.endpoint }}</td>
                  <td class="px-4 py-2 text-gray-700">{{ item.method }}</td>
                  <td class="px-4 py-2" :class="item.status_code >= 400 ? 'text-red-600' : 'text-green-600'">{{
                    item.status_code }}</td>
                  <td class="px-4 py-2 text-gray-700">{{ item.duration_ms }} ms</td>
                  <td class="px-4 py-2 text-gray-700">{{ item.error_message || '-' }}</td>
                  <td class="px-4 py-2">
                    <button @click="openDetail(item)"
                      class="px-3 py-1 text-xs bg-gray-100 rounded hover:bg-gray-200">Detail</button>
                  </td>
                </tr>
                <tr v-if="(props.logs?.data || []).length === 0">
                  <td colspan="9" class="px-4 py-6 text-center text-gray-500">Tidak ada data.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="flex justify-between items-center px-4 py-3">
            <div class="text-xs text-gray-500">Total: {{ props.logs?.total || 0 }} • Halaman {{ props.logs?.current_page
              || 1 }}/{{ props.logs?.last_page || 1 }}</div>
            <div class="flex gap-2">
              <button :disabled="!(props.logs?.prev_page_url)" @click="router.visit(props.logs?.prev_page_url)"
                class="px-3 py-1 text-xs rounded border disabled:opacity-50">Prev</button>
              <button :disabled="!(props.logs?.next_page_url)" @click="router.visit(props.logs?.next_page_url)"
                class="px-3 py-1 text-xs rounded border disabled:opacity-50">Next</button>
            </div>
          </div>
        </div>

        <Modal :show="showDetail" @close="closeDetail">
          <div class="p-6 w-full max-w-2xl bg-white rounded-lg shadow-lg">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Detail Log</h3>
            <div class="grid grid-cols-2 gap-3 text-sm mb-4">
              <div><span class="text-gray-500">File Lampiran:</span> {{ detail?.correlation_name || '-' }}</div>
              <div><span class="text-gray-500">User:</span> {{ detail?.user_name || '-' }}</div>
              <div><span class="text-gray-500">Endpoint:</span> {{ detail?.endpoint }}</div>
              <div><span class="text-gray-500">Method:</span> {{ detail?.method }}</div>
              <div><span class="text-gray-500">Status:</span> {{ detail?.status_code }}</div>
              <div><span class="text-gray-500">Durasi:</span> {{ detail?.duration_ms }} ms</div>
            </div>
            <div class="mb-3">
              <h4 class="text-sm font-semibold text-gray-700">Request Payload</h4>
              <pre
                class="p-3 bg-gray-900 text-gray-100 rounded overflow-auto text-xs">{{ JSON.stringify(detail?.request_payload, null, 2) }}</pre>
            </div>
            <div>
              <h4 class="text-sm font-semibold text-gray-700">Response Body</h4>
              <pre class="p-3 bg-gray-900 text-gray-100 rounded overflow-auto text-xs">{{ detail?.response_body }}</pre>
            </div>
            <div class="flex justify-end mt-4">
              <button @click="closeDetail"
                class="px-3 py-2 text-xs bg-gray-100 rounded sm:text-sm hover:bg-gray-200">Tutup</button>
            </div>
          </div>
        </Modal>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
