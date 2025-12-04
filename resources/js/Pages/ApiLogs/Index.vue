<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchInput from '@/Components/SearchInput.vue'

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

watch(search, (val) => {
  router.get(route('api-logs.index'), { search: val, method: method.value, status: status.value }, { preserveState: true, replace: true })
})
</script>

<template>

  <Head title="API Logs" />
  <AuthenticatedLayout>
    <div class="pt-6 sm:pt-24 mx-2 sm:px-2">
      <div class="max-w-8xl mx-auto sm:px-6 lg:px-6">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800">API Logs</h2>
          </div>

          <div class="overflow-x-auto">
            <div class="mb-3 flex flex-wrap gap-2 items-center">
              <SearchInput v-model:search="search" />
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
            </div>
            <table class="table-auto w-full">
              <thead>
                <tr class="bg-gray-300 text-left">
                  <th class="px-4 py-2">Waktu</th>
                  <th class="px-4 py-2">File Lampiran</th>
                  <th class="px-4 py-2">User</th>
                  <th class="px-4 py-2">Endpoint</th>
                  <th class="px-4 py-2">Method</th>
                  <th class="px-4 py-2">Status</th>
                  <th class="px-4 py-2">Durasi</th>
                  <th class="px-4 py-2">Error</th>
                  <th class="px-4 py-2"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in (props.logs?.data || [])" :key="item.id"
                  class="hover:bg-red-50 transition even:bg-gray-100">
                  <td class="px-4 py-2">{{ new Date(item.created_at).toLocaleString() }}</td>
                  <td class="px-4 py-2">{{ item.correlation_name || '-' }}</td>
                  <td class="px-4 py-2">{{ item.user_name || '-' }}</td>
                  <td class="px-4 py-2">{{ item.endpoint }}</td>
                  <td class="px-4 py-2">{{ item.method }}</td>
                  <td class="px-4 py-2" :class="item.status_code >= 400 ? 'text-red-600' : 'text-green-600'">{{
                    item.status_code }}</td>
                  <td class="px-4 py-2">{{ item.duration_ms }} ms</td>
                  <td class="px-4 py-2">{{ item.error_message || '-' }}</td>
                  <td class="px-4 py-2">
                    <button @click="openDetail(item)"
                      class="px-2 py-1 text-md sm:text-xl font-semibold rounded transition text-blue-600 hover:bg-blue-100">
                      <font-awesome-icon icon="eye" />
                    </button>
                  </td>
                </tr>
                <tr v-if="(props.logs?.data || []).length === 0">
                  <td colspan="9" class="px-4 py-2 text-center">Tidak ada data</td>
                </tr>
              </tbody>
            </table>
          </div>

          <Pagination :links="props.logs?.links || []"
            :meta="{ from: props.logs?.from, to: props.logs?.to, total: props.logs?.total }" />

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
                <pre
                  class="p-3 bg-gray-900 text-gray-100 rounded overflow-auto text-xs">{{ detail?.response_body }}</pre>
              </div>
              <div class="flex justify-end mt-4">
                <button @click="closeDetail"
                  class="px-3 py-2 text-xs bg-gray-100 rounded sm:text-sm hover:bg-gray-200">Tutup</button>
              </div>
            </div>
          </Modal>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
