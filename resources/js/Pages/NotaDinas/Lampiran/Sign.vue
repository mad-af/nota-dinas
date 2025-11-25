<template>

  <Head :title="doc?.name ? `TTE: ${doc.name}` : 'TTE Lampiran'" />
  <AuthenticatedLayout>
    <SuccessFlash :flash="flash" @clearFlash="clearFlash" />
    <ErrorFlash :flash="flash" @clearFlash="clearFlash" />

    <div class="pt-6 mx-2 sm:pt-24 sm:px-2">
      <div class="mx-auto space-y-6 max-w-6xl sm:px-6 lg:px-6">
        <div class="p-6 bg-white shadow-sm sm:rounded-lg">
          <div class="flex flex-wrap gap-2 justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800 sm:text-xl">TTE Lampiran</h2>
          </div>
        </div>

        <div class="flex flex-col gap-6 md:flex-row">
          <div class="p-0 bg-white shadow-sm sm:rounded-lg md:p-6 md:flex-1">
            <div class="flex flex-col gap-4 px-6 pt-6 md:flex-row md:items-center md:justify-between md:p-0">
              <h2 class="text-lg font-semibold text-gray-800 sm:text-xl">Dokumen</h2>
              <div class="flex flex-wrap gap-2 items-center">
                <button @click="zoomOut" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">-</button>
                <span class="text-sm text-gray-700">Zoom {{ Math.round(scale * 100) }}%</span>
                <button @click="zoomIn" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">+</button>
                <button @click="resetZoom" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">Reset</button>
                <div class="mx-2 w-px h-6 bg-gray-300"></div>
                <button @click="prevPage" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">Prev</button>
                <span class="text-sm text-gray-700">Halaman {{ currentPage }} / {{ totalPages || '-' }}</span>
                <button @click="nextPage" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">Next</button>
              </div>
            </div>

            <div class="mt-4 md:mt-6">
              <div v-if="!pdfUrl" class="p-6 text-sm text-gray-600">Dokumen tidak ditemukan.</div>
              <div v-else class="w-full">
                <div class="overflow-auto w-full">
                  <div ref="wrapRef" class="relative w-full bg-gray-50">
                    <VuePdfEmbed annotation-layer text-layer :source="pdfUrl" :page="currentPage" :scale="scale"
                      @loaded="onLoaded" @loading-failed="onLoadingFailed" @rendering-failed="onRenderingFailed"
                      @rendered="onRendered" class="min-h-[80vh] w-full h-full" />
                    <Vue3DraggableResizable v-if="viewerReady" :x="absCoord.x" :y="absCoord.y" :w="absCoord.width"
                      :h="absCoord.height" :parent="true" :draggable="!!pdfUrl" :resizable="!!pdfUrl" :active="true"
                      class="absolute" @dragging="onDrag" @resizing="onResize">
                      <div class="w-full h-full border-2 border-indigo-500 bg-indigo-200/40"></div>
                    </Vue3DraggableResizable>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="md:w-96 shrink-0">
            <div class="p-4 bg-white rounded-md border">
              <h3 class="text-base font-semibold text-gray-900">Form TTE</h3>
              <p class="mt-1 text-sm text-gray-600">Masukkan data yang diminta oleh layanan eSign.</p>
              <div class="mt-4 space-y-3">
                <label class="flex gap-2 items-center text-sm">
                  <input type="checkbox" v-model="form.consent" />
                  <span>Saya menyetujui untuk menandatangani dokumen ini.</span>
                </label>
                <div>
                  <label class="text-sm text-gray-700">Kode OTP</label>
                  <input type="text" v-model="form.otp" class="px-2 py-1 mt-1 w-full text-sm rounded border"
                    placeholder="6 digit" maxlength="6" />
                </div>
                <div class="flex gap-2 items-center">
                  <button @click="startSigning"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700"
                    :disabled="signing">
                    <span v-if="!signing">Mulai TTE</span>
                    <span v-else>Memproses…</span>
                  </button>
                  <span class="text-xs text-gray-600">Status: {{ statusText }}</span>
                </div>
                <div class="mt-2 text-xs text-gray-500">
                  Posisi tanda tangan (%):
                  {{ {
                    x: +(percentCoord.x.toFixed(4)), y: +(percentCoord.y.toFixed(4)), width:
                      +(percentCoord.width.toFixed(4)), height: +(percentCoord.height.toFixed(4)) } }}
                </div>
              </div>
              <div class="mt-6">
                <h4 class="text-sm font-semibold text-gray-900">Penanda Tangan</h4>
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
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, nextTick, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SuccessFlash from '@/Components/SuccessFlash.vue'
import ErrorFlash from '@/Components/ErrorFlash.vue'
import VuePdfEmbed from 'vue-pdf-embed'
import Vue3DraggableResizable from 'vue3-draggable-resizable'
import 'vue3-draggable-resizable/dist/Vue3DraggableResizable.css'
import 'vue-pdf-embed/dist/styles/annotationLayer.css'
import 'vue-pdf-embed/dist/styles/textLayer.css'

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
const scale = ref(1.25)

const wrapRef = ref(null)
const viewerReady = ref(false)
const absCoord = ref({ x: 50, y: 50, width: 200, height: 80 })
const percentCoord = ref({ x: 0, y: 0, width: 0, height: 0 })

function onLoaded(doc) { totalPages.value = doc?.numPages || 0 }
function onLoadingFailed() { flash.value.error = 'Gagal memuat dokumen.' }
function onRenderingFailed() { flash.value.error = 'Gagal merender halaman PDF.' }
function onRendered() { viewerReady.value = true; nextTick(updatePercentFromAbs) }

function zoomIn() { scale.value = Math.min(scale.value + 0.25, 3) }
function zoomOut() { scale.value = Math.max(scale.value - 0.25, 0.5) }
function resetZoom() { scale.value = 1 }
function prevPage() { currentPage.value = Math.max(currentPage.value - 1, 1) }
function nextPage() { currentPage.value = Math.min(currentPage.value + 1, totalPages.value || currentPage.value + 1) }

function updatePercentFromAbs() {
  const el = wrapRef.value?.querySelector('canvas') || wrapRef.value
  const rect = el?.getBoundingClientRect()
  if (!rect) return
  percentCoord.value = {
    x: absCoord.value.x / rect.width,
    y: absCoord.value.y / rect.height,
    width: absCoord.value.width / rect.width,
    height: absCoord.value.height / rect.height,
  }
}

function updateAbsFromPercent() {
  const el = wrapRef.value?.querySelector('canvas') || wrapRef.value
  const rect = el?.getBoundingClientRect()
  if (!rect) return
  absCoord.value = {
    x: Math.round(percentCoord.value.x * rect.width),
    y: Math.round(percentCoord.value.y * rect.height),
    width: Math.round(percentCoord.value.width * rect.width),
    height: Math.round(percentCoord.value.height * rect.height),
  }
}

function onDrag(...args) {
  let x, y
  if (typeof args[0] === 'object' && args[0] !== null) {
    x = args[0].x ?? args[0].left
    y = args[0].y ?? args[0].top
  } else {
    ;[x, y] = args
  }
  absCoord.value.x = Math.round(x ?? absCoord.value.x)
  absCoord.value.y = Math.round(y ?? absCoord.value.y)
  updatePercentFromAbs()
}

function onResize(...args) {
  let x, y, width, height
  if (typeof args[0] === 'object' && args[0] !== null) {
    x = args[0].x ?? args[0].left
    y = args[0].y ?? args[0].top
    width = args[0].width ?? args[0].w
    height = args[0].height ?? args[0].h
  } else {
    ;[x, y, width, height] = args
  }
  absCoord.value = {
    x: Math.round(x ?? absCoord.value.x),
    y: Math.round(y ?? absCoord.value.y),
    width: Math.round(width ?? absCoord.value.width),
    height: Math.round(height ?? absCoord.value.height),
  }
  updatePercentFromAbs()
}

function resetBox() {
  const el = wrapRef.value?.querySelector('canvas') || wrapRef.value
  const rect = el?.getBoundingClientRect()
  if (!rect) return
  const w = Math.round(rect.width * 0.3)
  const h = Math.round(rect.height * 0.12)
  absCoord.value = {
    x: Math.round((rect.width - w) / 2),
    y: Math.round((rect.height - h) / 2),
    width: w,
    height: h,
  }
  updatePercentFromAbs()
}

const form = ref({ consent: false, otp: '' })
const signing = ref(false)
const statusText = ref(props.hasSigned ? 'Sudah ditandatangani' : 'Belum ditandatangani')
let poller = null

function validate() {
  if (!form.value.consent) {
    flash.value.error = 'Anda harus menyetujui untuk melanjutkan TTE.'
    return false
  }
  if (!/^\d{6}$/.test(form.value.otp)) {
    flash.value.error = 'Kode OTP harus 6 digit angka.'
    return false
  }
  return true
}

async function startSigning() {
  if (!validate()) return
  signing.value = true
  flash.value = { success: null, error: null }
  statusText.value = 'Memproses penandatanganan…'
  try {
    await router.post(route('nota.lampiran.sign', props.doc.id), {
      placement: {
        percent: percentCoord.value,
        absolute: absCoord.value,
        page: currentPage.value,
        scale: scale.value,
      },
      otp: form.value.otp,
    }, {
      onFinish: () => { },
    })
    statusText.value = 'Berhasil ditandatangani'
    flash.value.success = 'Dokumen berhasil ditandatangani.'
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || 'Gagal proses TTE.'
    flash.value.error = msg
    statusText.value = 'Gagal, coba lagi.'
  } finally {
    signing.value = false
  }
}

async function pollStatus() {
  try {
    const { data } = await axios.get(route('nota.lampiran.status', props.doc.id))
    if (data?.hasSigned) {
      statusText.value = 'Berhasil ditandatangani'
      if (poller) clearInterval(poller)
    }
  } catch { }
}

function onWindowResize() { updateAbsFromPercent() }
onMounted(() => { poller = setInterval(pollStatus, 2000); window.addEventListener('resize', onWindowResize) })
onUnmounted(() => { if (poller) clearInterval(poller); window.removeEventListener('resize', onWindowResize) })
</script>