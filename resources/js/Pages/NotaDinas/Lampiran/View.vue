<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SuccessFlash from '@/Components/SuccessFlash.vue'
import ErrorFlash from '@/Components/ErrorFlash.vue'
import VuePdfEmbed from 'vue-pdf-embed'
import 'vue-pdf-embed/dist/styles/annotationLayer.css'
import 'vue-pdf-embed/dist/styles/textLayer.css'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  doc: { type: Object, required: true },
  signers: { type: Array, default: () => [] },
  hasSigned: { type: Boolean, default: false },
  currentUserId: { type: String, default: '' },
})

const flash = ref({ success: null, error: null })
const clearFlash = () => { flash.value = { success: null, error: null } }

const pdfUrl = ref(props.doc?.url || '')
const currentPage = ref(1)
const totalPages = ref(0)
const scale = ref(1)
const wrapRef = ref(null)
const pdfDoc = ref(null)


function onLoaded(doc) { totalPages.value = doc?.numPages || 0; pdfDoc.value = doc; updateScaleToFit() }
function onLoadingFailed() { flash.value.error = 'Gagal memuat dokumen.' }
function onRenderingFailed() { flash.value.error = 'Gagal merender halaman PDF.' }

// zoom disabled: scale fixed to 1
function prevPage() { currentPage.value = Math.max(currentPage.value - 1, 1) }
function nextPage() { currentPage.value = Math.min(currentPage.value + 1, totalPages.value || currentPage.value + 1) }

function navigateToSign() {
  router.visit(route('nota.lampiran.sign.page', props.doc.id))
}

async function getPageDims() {
  const doc = pdfDoc.value
  if (!doc) return { width: 0, height: 0 }
  const page = await doc.getPage(currentPage.value)
  const vp = page.getViewport({ scale: 1 })
  const w = Number(vp?.width) || 0
  const h = Number(vp?.height) || 0
  return { width: w, height: h }
}

async function updateScaleToFit() {
  const el = wrapRef.value
  const rect = el?.getBoundingClientRect()
  const { width: pageW, height: pageH } = await getPageDims()
  const vw = rect?.width || 0
  const vh = rect?.height || Math.floor(window.innerHeight * 0.8) || 0
  if (!pageW || !pageH || !vw || !vh) return
  const s = Math.min(vw / pageW, vh / pageH)
  scale.value = s > 0 ? s : 1
}

function onWindowResize() { updateScaleToFit() }

onMounted(() => {
  window.addEventListener('resize', onWindowResize)
  watch(currentPage, () => { updateScaleToFit() })
})

onUnmounted(() => {
  window.removeEventListener('resize', onWindowResize)
})
</script>

<template>

  <Head :title="doc?.name ? `Lampiran: ${doc.name}` : 'Lampiran'" />
  <AuthenticatedLayout>
    <SuccessFlash :flash="flash" @clearFlash="clearFlash" />
    <ErrorFlash :flash="flash" @clearFlash="clearFlash" />

    <div class="pt-6 mx-2 sm:pt-24 sm:px-2">
      <div class="mx-auto space-y-6 max-w-6xl sm:px-6 lg:px-6">
        <div class="p-6 bg-white shadow-sm sm:rounded-lg">
          <div class="flex flex-wrap gap-2 justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800 sm:text-xl">Preview Lampiran</h2>
            <div class="flex gap-2 items-center">
              <a :href="doc?.url" download
                class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded hover:bg-green-700">Download</a>
              <button v-if="!hasSigned" @click="navigateToSign"
                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700">
                Buka Halaman TTE
              </button>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-6 md:flex-row">
          <div class="p-0 bg-white shadow-sm sm:rounded-lg md:p-6 md:flex-1">
            <div class="flex flex-col gap-4 px-6 pt-6 md:flex-row md:items-center md:justify-between md:p-0">
              <h2 class="text-lg font-semibold text-gray-800 sm:text-xl">Dokumen</h2>
              <div class="flex flex-wrap gap-2 items-center">
                <button @click="prevPage" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">Prev</button>
                <span class="text-sm text-gray-700">Halaman {{ currentPage }} / {{ totalPages || '-' }}</span>
                <button @click="nextPage" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">Next</button>
              </div>
            </div>

            <div class="mt-4 md:mt-6">
              <div v-if="!pdfUrl" class="p-6 text-sm text-gray-600">Dokumen tidak ditemukan.</div>
              <div v-else class="w-full">
                <div class="flex flex-col gap-6 md:flex-row">
                  <div class="md:flex-1">
                    <div class="overflow-auto w-full">
                      <div ref="wrapRef" class="relative w-full bg-gray-50">
                        <VuePdfEmbed annotation-layer text-layer :source="pdfUrl" :page="currentPage" :scale="scale"
                          @loaded="onLoaded" @loading-failed="onLoadingFailed" @rendering-failed="onRenderingFailed"
                          class="min-h-[80vh] w-full h-full" />
                      </div>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>
          <div class="md:w-96 shrink-0">
            <div class="p-4 bg-white rounded-md border">
              <h3 class="text-base font-semibold text-gray-900">Penanda Tangan</h3>
              <ul class="mt-2 space-y-2 text-sm">
                <li v-for="s in signers" :key="s.id" class="flex justify-between items-center">
                  <span>{{ s.name }}</span>
                  <span class="text-xs text-green-600">Sudah TTE</span>
                </li>
                <li v-if="signers.length === 0" class="text-gray-500">Belum ada penanda tangan.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
