<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, watch } from 'vue'
import VuePdfEmbed from 'vue-pdf-embed'
import 'vue-pdf-embed/dist/styles/annotationLayer.css'
import 'vue-pdf-embed/dist/styles/textLayer.css'

const props = defineProps({
  doc: { type: Object, required: true },
  hasSigned: { type: Boolean, default: false },
})

const pdfUrl = ref(props.doc?.url || '')
const currentPage = ref(1)
const totalPages = ref(0)
const scale = ref(1)
const wrapRef = ref(null)
const pdfDoc = ref(null)

function onLoaded(doc) { totalPages.value = doc?.numPages || 0; pdfDoc.value = doc; updateScaleToFit() }
function onLoadingFailed() {}
function onRenderingFailed() {}

function prevPage() { currentPage.value = Math.max(currentPage.value - 1, 1) }
function nextPage() { currentPage.value = Math.min(currentPage.value + 1, totalPages.value || currentPage.value + 1) }

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
  <Head :title="doc?.name ? `Dokumen Publik: ${doc.name}` : 'Dokumen Publik'" />
  <div class="min-h-screen bg-gray-50">
    <div class="sticky top-0 z-10 w-full bg-indigo-600 text-white text-center py-2 text-sm">Tampilan Publik</div>
    <div class="px-2 sm:px-4 lg:px-6">
      <div class="mx-auto max-w-5xl py-4 sm:py-8">
        <div class="bg-white shadow-sm rounded-md">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between p-4">
            <div>
              <h1 class="text-base sm:text-lg font-semibold text-gray-800">{{ doc?.name || 'Dokumen' }}</h1>
              <p class="text-xs sm:text-sm text-gray-500">Status: {{ hasSigned ? 'Sudah TTE' : 'Original' }}</p>
            </div>
            <div class="flex items-center gap-2">
              <button @click="prevPage" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200 text-sm">Prev</button>
              <span class="text-xs sm:text-sm text-gray-700">Halaman {{ currentPage }} / {{ totalPages || '-' }}</span>
              <button @click="nextPage" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200 text-sm">Next</button>
            </div>
          </div>

          <div class="p-2 sm:p-4">
            <div v-if="!pdfUrl" class="p-6 text-sm text-gray-600">Dokumen tidak ditemukan.</div>
            <div v-else class="w-full">
              <div class="relative w-full">
                <div ref="wrapRef" class="relative w-full bg-gray-50">
                  <VuePdfEmbed annotation-layer text-layer :source="pdfUrl" :page="currentPage" :scale="scale"
                    @loaded="onLoaded" @loading-failed="onLoadingFailed" @rendering-failed="onRenderingFailed"
                    class="min-h-[70vh] sm:min-h-[80vh] w-full h-full" />
                  <div class="pointer-events-none absolute top-2 right-2 bg-indigo-600/80 text-white text-xs px-2 py-1 rounded">Publik</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

