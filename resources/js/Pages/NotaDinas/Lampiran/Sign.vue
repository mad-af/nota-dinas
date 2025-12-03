<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, nextTick, onMounted, onUnmounted, computed, watch, markRaw } from 'vue'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
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
  currentUserSignaturePath: { type: String, default: '' },
  currentUserNik: { type: String, default: '' },
})

const flash = ref({ success: null, error: null, info: null })
const clearFlash = () => { flash.value = { success: null, error: null, info: null } }

const pdfUrl = ref(props.doc?.url || '')
const currentPage = ref(1)
const totalPages = ref(0)
const scale = ref(1)

const wrapRef = ref(null)
const viewerReady = ref(false)
const absCoord = ref({ x: 50, y: 50, width: 80, height: 80 })
const percentCoord = ref({ x: 0, y: 0, width: 0, height: 0 })
const pdfDoc = ref(null)
const originalDims = ref({ width: 0, height: 0 })
const normalizedCoord = computed(() => {
  const ow = originalDims.value.width || 0
  const oh = originalDims.value.height || 0
  return {
    x: Math.round((percentCoord.value.x || 0) * ow),
    y: Math.round((percentCoord.value.y || 0) * oh),
    width: Math.round((percentCoord.value.width || 0) * ow),
    height: Math.round((percentCoord.value.height || 0) * oh),
  }
})

const isEsignReady = computed(() => !!props.currentUserNik)
function goToEsignSetup() { router.visit(route('profile.edit') + '#esign-setup') }
const safeSignatureUrl = computed(() => {
  const p = props.currentUserSignaturePath || ''
  const ok = /^[A-Za-z0-9_\-\/\.]+$/.test(p) && !p.includes('..')
  return ok && p ? '/storage/' + p : ''
})

const imgRef = ref(null)
const imgDims = ref({ width: 0, height: 0 })
function onImageLoad(e) {
  try {
    const t = e?.target
    const w = Number(t?.naturalWidth) || 0
    const h = Number(t?.naturalHeight) || 0
    imgDims.value = { width: w, height: h }
    applyImageDimsToRect()
  } catch {
  }
}
function onImageError() {
  imgDims.value = { width: 0, height: 0 }
  flash.value.error = 'Gagal memuat gambar tanda tangan.'
}
function applyImageDimsToRect() {
  const nw = imgDims.value.width
  const nh = imgDims.value.height
  if (!nw || !nh) return
  const el = wrapRef.value?.querySelector('canvas') || wrapRef.value
  const rect = el?.getBoundingClientRect()
  if (!rect) return
  let w = nw
  let h = nh
  const maxW = Math.max(60, rect.width * 0.3)
  const maxH = Math.max(40, rect.height * 0.3)
  const scaleW = maxW / w
  const scaleH = maxH / h
  const scale = Math.min(scaleW, scaleH, 1)
  w = Math.round(w * scale)
  h = Math.round(h * scale)
  absCoord.value.width = w
  absCoord.value.height = h
  updatePercentFromAbs()
}

function onLoaded(doc) { totalPages.value = doc?.numPages || 0; pdfDoc.value = markRaw(doc); updateScaleToFit(); getPageDims().then(d => { originalDims.value = d; console.log('PDF original dims', d) }) }
function onLoadingFailed() { flash.value.error = 'Gagal memuat dokumen.' }
function onRenderingFailed() { flash.value.error = 'Gagal merender halaman PDF.' }
function onRendered() { viewerReady.value = true; nextTick(() => { updatePercentFromAbs(); const el = wrapRef.value?.querySelector('canvas') || wrapRef.value; const rect = el?.getBoundingClientRect(); const w = rect?.width || 0; const h = rect?.height || 0; renderedDims.value = { width: w, height: h }; console.log('PDF rendered dims', renderedDims.value, 'scale', scale.value) }) }

// zoom disabled: scale fixed to 1
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

const form = ref({ signer_id: '', method: 'passphrase', tampilan: 'VIS', location: '', reason: '' })
const signing = ref(false)
const statusText = ref(props.hasSigned ? 'Sudah ditandatangani' : 'Belum ditandatangani')
const pollingActive = ref(false)
const pollDelayMs = ref(3000)
let pollTimeout = null

const showNotifModal = ref(false)
const notifModal = ref(null)
function openNotifModal(m) { notifModal.value = m; showNotifModal.value = true }
function closeNotifModal() { showNotifModal.value = false; notifModal.value = null }

const redirectTimer = ref(null)
const redirectCountdown = ref(5)
function scheduleRedirect() {
  if (redirectTimer.value) { clearTimeout(redirectTimer.value); redirectTimer.value = null }
  redirectCountdown.value = 5
  const tick = () => {
    if (redirectCountdown.value <= 1) {
      router.visit(route('nota.lampiran.view', props.doc.id))
      return
    }
    redirectCountdown.value -= 1
    redirectTimer.value = setTimeout(tick, 1000)
  }
  redirectTimer.value = setTimeout(tick, 1000)
}

function validate() {
  if (!form.value.signer_id) { flash.value.error = 'NIK penanda tangan wajib diisi.'; return false }
  if (form.value.tampilan === 'VIS' && !props.currentUserSignaturePath) {
    flash.value.error = 'Spesimen tanda tangan belum tersedia. Gunakan tampilan INV.'
    form.value.tampilan = 'INV'
    return false
  }
  return true
}

const showPassphraseModal = ref(false)
const passphraseInput = ref('')
const passphraseError = ref('')
const passInputRef = ref(null)
const showPass = ref(false)

function openPassphraseModal() {
  passphraseInput.value = ''
  passphraseError.value = ''
  showPassphraseModal.value = true
}

function closePassphraseModal() {
  showPassphraseModal.value = false
  passphraseInput.value = ''
  passphraseError.value = ''
  showPass.value = false
  if (passInputRef.value) { try { passInputRef.value.value = '' } catch { } }
}


function validatePassphrase(p) {
  passphraseError.value = ''
  if (!p || typeof p !== 'string') { passphraseError.value = 'Passphrase wajib diisi.'; return false }
  return true
}

async function confirmPassphrase() {
  const p = passphraseInput.value
  if (!validate()) return
  if (!validatePassphrase(p)) return
  showPassphraseModal.value = false
  try {
    await startSigning(p)
  } finally {
    passphraseInput.value = ''
    passphraseError.value = ''
    if (passInputRef.value) { try { passInputRef.value.value = '' } catch { } }
  }
}

async function startSigning(passphrase) {
  if (!validate()) return
  signing.value = true
  clearFlash()
  statusText.value = 'Memproses penandatanganan…'
  try {
    const lampiranPayload = {
      method: 'passphrase',
      passphrase,
      tampilan: form.value.tampilan || 'VIS',
      signature_path: props.currentUserSignaturePath,
      page: currentPage.value,
      originX: normalizedCoord.value.x,
      originY: normalizedCoord.value.y,
      width: normalizedCoord.value.width,
      height: normalizedCoord.value.height,
      location: form.value.location,
      reason: form.value.reason,
    }
    console.log("INI BRO", lampiranPayload)
    const filteredLampiranPayload = Object.fromEntries(Object.entries(lampiranPayload).filter(([_, v]) => v !== null && v !== undefined))
    flash.value.info = 'Dokumen sedang diproses.'
    const { data } = await axios.post(route('api.nota.lampiran.sign', props.doc.id), filteredLampiranPayload)
    flash.value.info = null
    if (data?.success) {
      const modalPayload = data?.modal || { type: 'success', title: 'Mantap!', message: data?.message || 'Dokumen berhasil ditandatangani.' }
      modalPayload.actions = [{ route: route('nota.lampiran.view', props.doc.id) }]
      openNotifModal(modalPayload)
      scheduleRedirect()
    } else {
      const msg = data?.message || 'Gagal proses TTE.'
      statusText.value = 'Gagal, coba lagi.'
      if (data?.modal) openNotifModal(data.modal)
    }
  } catch (e) {
    // const msg = e?.response?.data?.error || e?.response?.data?.message || e?.message || 'Gagal proses TTE.'
    // flash.value.error = msg
    statusText.value = 'Gagal, coba lagi.'
    const m = e?.response?.data?.modal
    if (m) openNotifModal(m)
  } finally {
    signing.value = false
  }
}


async function runPoll() {
  if (!pollingActive.value) return
  if (document.hidden || !navigator.onLine) { schedulePoll(); return }
  try {
    const { data } = await axios.get(route('nota.lampiran.status', props.doc.id))
    if (data?.hasSigned) { statusText.value = 'Berhasil ditandatangani'; stopPolling(); return }
    pollDelayMs.value = Math.min(pollDelayMs.value + 2000, 15000)
  } catch { }
  schedulePoll()
}

function schedulePoll() {
  if (!pollingActive.value) return
  pollTimeout = setTimeout(runPoll, pollDelayMs.value)
}

function startPolling() {
  if (pollingActive.value) return
  pollingActive.value = true
  pollDelayMs.value = 3000
  schedulePoll()
}

function stopPolling() {
  pollingActive.value = false
  if (pollTimeout) { clearTimeout(pollTimeout); pollTimeout = null }
}

function onWindowResize() { updateScaleToFit(); updateAbsFromPercent() }
function onVisibilityChange() { if (!document.hidden) { pollDelayMs.value = 3000 } }
function onOnline() { pollDelayMs.value = 3000 }
function onFocus() { pollDelayMs.value = 3000 }
onMounted(() => {
  startPolling()
  window.addEventListener('resize', onWindowResize)
  window.addEventListener('visibilitychange', onVisibilityChange)
  window.addEventListener('online', onOnline)
  window.addEventListener('focus', onFocus)
  if (!props.currentUserSignaturePath) { form.value.tampilan = 'INV' }
  if (props.currentUserNik) { form.value.signer_id = props.currentUserNik }
  watch(currentPage, () => { updateScaleToFit() })
})
onUnmounted(() => {
  stopPolling()
  if (redirectTimer.value) { clearTimeout(redirectTimer.value); redirectTimer.value = null }
  window.removeEventListener('resize', onWindowResize)
  window.removeEventListener('visibilitychange', onVisibilityChange)
  window.removeEventListener('online', onOnline)
  window.removeEventListener('focus', onFocus)
})
</script>

<template>

  <Head :title="doc?.name ? `TTE: ${doc.name}` : 'TTE Lampiran'" />
  <AuthenticatedLayout>
    <SuccessFlash :flash="flash" @clearFlash="clearFlash" />
    <ErrorFlash :flash="flash" @clearFlash="clearFlash" />

    <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0"
      enter-to-class="opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100"
      leave-to-class="opacity-0">
      <div v-if="signing" class="flex fixed inset-0 z-50 justify-center items-center cursor-wait bg-black/40">
        <div class="flex flex-col items-center">
          <div class="w-12 h-12 rounded-full border-4 border-indigo-500 animate-spin border-t-transparent"></div>
          <p class="mt-3 text-sm text-white">Memproses penandatanganan…</p>
        </div>
      </div>
    </Transition>
    <Modal :show="showNotifModal" :closeable="notifModal?.type !== 'success'" @close="closeNotifModal">
      <div class="p-6 w-full max-w-md bg-white rounded-lg shadow-lg">
        <div class="flex gap-3 items-center mb-3">
          <div v-if="notifModal?.type === 'success'"
            class="flex justify-center items-center w-8 h-8 bg-green-100 rounded-full">
            <svg class="w-5 h-5 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4M20 12a8 8 0 11-16 0 8 8 0 0116 0z" />
            </svg>
          </div>
          <div v-else class="flex justify-center items-center w-8 h-8 bg-red-100 rounded-full">
            <svg class="w-5 h-5 text-red-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M4.93 4.93a10 10 0 1114.14 14.14A10 10 0 014.93 4.93z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold" :class="notifModal?.type === 'success' ? 'text-green-700' : 'text-red-700'">
            {{ notifModal?.title }}</h3>
        </div>
        <p class="mb-2 text-sm text-gray-700">{{ notifModal?.message }}</p>
        <p v-if="notifModal?.type === 'success'" class="mb-3 text-xs text-gray-500">Anda akan dialihkan dalam {{
          redirectCountdown }} detik</p>
        <p v-if="notifModal?.elapsed_ms" class="mb-3 text-xs text-gray-500">Waktu proses TTE: {{ notifModal?.elapsed_ms
        }} ms</p>
        <div v-if="notifModal?.type === 'error' && (notifModal?.status || notifModal?.detail)"
          class="p-3 mb-3 text-xs text-gray-100 bg-gray-900 rounded">
          <div v-if="notifModal?.status">Status: {{ notifModal?.status }}</div>
          <div v-if="notifModal?.detail">Error: {{ notifModal?.detail }}</div>
        </div>
        <div class="flex gap-2 justify-end">
          <button v-if="notifModal?.type !== 'success'" @click="closeNotifModal"
            class="px-3 py-2 text-xs bg-gray-100 rounded sm:text-sm hover:bg-gray-200">Tutup</button>
          <button v-else @click="router.visit(route('nota.lampiran.view', doc.id))"
            class="px-3 py-2 text-xs text-white bg-indigo-600 rounded sm:text-sm hover:bg-indigo-700">Buka
            Preview</button>
        </div>
      </div>
    </Modal>
    <Transition appear name="fade">
      <div v-if="flash.info" class="mx-2 sm:mx-0">
        <div class="p-4 bg-blue-50 rounded-md">
          <div class="flex items-start">
            <svg class="flex-shrink-0 w-5 h-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
            </svg>
            <div class="flex-1 ml-3 text-sm text-blue-700">{{ flash.info }}</div>
            <button @click="clearFlash"
              class="p-1 ml-4 rounded-full hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-blue-50"
              aria-label="Close notification">
              <svg class="w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <div class="pt-6 mx-2 sm:pt-24 sm:px-2">
      <div class="mx-auto space-y-6 max-w-6xl sm:px-6 lg:px-6">
        <div class="p-6 bg-white shadow-sm sm:rounded-lg">
          <div class="flex flex-wrap gap-2 justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800 sm:text-xl">TTE Lampiran</h2>
          </div>
        </div>

        <div class="flex flex-col gap-6 md:flex-row">
          <div class="p-0 bg-amber-100 shadow-sm sm:rounded-lg md:p-6 md:flex-1">
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
                <div class="overflow-auto w-full">
                  <div ref="wrapRef" class="relative w-full bg-gray-50">
                    <VuePdfEmbed annotation-layer text-layer :source="pdfUrl" :page="currentPage" :scale="scale"
                      @loaded="onLoaded" @loading-failed="onLoadingFailed" @rendering-failed="onRenderingFailed"
                      @rendered="onRendered" class="w-full h-full" />
                    <Vue3DraggableResizable v-if="viewerReady && form.tampilan === 'VIS'" :x="absCoord.x"
                      :y="absCoord.y" :w="absCoord.width" :h="absCoord.height" :parent="true" :draggable="!!pdfUrl"
                      :resizable="!!pdfUrl" :active="true" class="absolute" @dragging="onDrag" @resizing="onResize">
                      <img v-if="safeSignatureUrl" :src="safeSignatureUrl" class="object-contain w-full h-full"
                        ref="imgRef" @load="onImageLoad" @error="onImageError" />
                    </Vue3DraggableResizable>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="md:w-96 shrink-0" v-if="isEsignReady">
            <div class="p-4 bg-white rounded-md border">
              <h3 class="text-base font-semibold text-gray-900">Form TTE</h3>
              <p class="mt-1 text-sm text-gray-600">Masukkan data yang diminta oleh layanan eSign.</p>
              <div class="mt-4 space-y-3">
                <div class="grid gap-3">
                  <div>
                    <label class="text-sm text-gray-700">NIK Penanda Tangan</label>
                    <input type="text" v-model="form.signer_id" readonly
                      class="px-2 py-1 mt-1 w-full text-sm text-gray-600 bg-gray-50 rounded border border-gray-300 cursor-not-allowed"
                      placeholder="Diambil dari profil" />
                  </div>
                  <div>
                    <label class="text-sm text-gray-700">Tampilan</label>
                    <select v-model="form.tampilan" class="px-2 py-1 mt-1 w-full text-sm rounded border">
                      <option value="VIS" v-if="!!props.currentUserSignaturePath">Tampilkan Tanda Tangan</option>
                      <option value="INV">Sembunyikan Tanda Tangan</option>
                    </select>
                  </div>
                  <div>
                    <label class="text-sm text-gray-700">Lokasi</label>
                    <input type="text" v-model="form.location" class="px-2 py-1 mt-1 w-full text-sm rounded border"
                      placeholder="Opsional" />
                  </div>
                  <div>
                    <label class="text-sm text-gray-700">Alasan</label>
                    <input type="text" v-model="form.reason" class="px-2 py-1 mt-1 w-full text-sm rounded border"
                      placeholder="Opsional" />
                  </div>
                </div>
                <div class="flex gap-2 items-center">
                  <button @click="openPassphraseModal"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700"
                    :disabled="signing">
                    <span v-if="!signing">Tanda Tangan Elektronik</span>
                    <span v-else>Memproses…</span>
                  </button>
                  <span class="text-xs text-gray-600">Status: {{ statusText }}</span>
                </div>
                <div class="mt-2 text-xs text-gray-500">

                  Posisi tanda tangan (%):
                  {{ {
                    x: +(percentCoord.x.toFixed(4)), y: +(percentCoord.y.toFixed(4)), width:
                      +(percentCoord.width.toFixed(4)), height: +(percentCoord.height.toFixed(4))
                  } }}
                </div>
              </div>

            </div>
          </div>
          <div v-else class="md:w-96 shrink-0">
            <div class="p-4 bg-white rounded-md border">
              <h3 class="text-base font-semibold text-gray-900">Aktivasi eSign Diperlukan</h3>
              <p class="mt-2 text-sm text-gray-600">Silakan lengkapi NIK dan unggah spesimen tanda tangan pada halaman
                profil sebelum melakukan TTE.</p>
              <button type="button" @click="goToEsignSetup"
                class="text-sm bg-gray-50 rounded border inlin-t-3 hover:bg-gray-100">Buka Pengaturan
                eSign</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Passphrase Modal -->
    <Modal :show="showPassphraseModal" @close="closePassphraseModal">
      <form autocomplete="off" class="p-6 space-y-4">
        <h3 class="text-base font-semibold text-gray-900">Masukkan Passphrase</h3>
        <p class="text-xs text-gray-600">Passphrase tidak disimpan. Hindari penggunaan yang lemah.</p>
        <div class="relative">
          <input ref="passInputRef" type="text" :value="passphraseInput"
            @input="(e) => { passphraseInput = e.target.value }" @focus="(e) => { e.target.readOnly = false }"
            :class="['px-2 py-2 pr-16 w-full text-sm rounded border', showPass ? '' : 'masked']"
            placeholder="Passphrase" autocomplete="new-password" autocapitalize="off" autocorrect="off"
            spellcheck="false" data-lpignore="true" data-1p-ignore="true" />
          <button type="button" @click="showPass = !showPass"
            class="absolute right-2 top-1/2 px-2 py-1 text-xs bg-white rounded border -translate-y-1/2 hover:bg-gray-50">
            {{ showPass ? 'Hide' : 'Show' }}
          </button>
          <div v-if="passphraseError" class="mt-2 text-xs text-red-600">{{ passphraseError }}</div>
        </div>
        <div class="flex gap-2 justify-end">
          <button type="button" @click="closePassphraseModal"
            class="px-3 py-2 text-sm bg-gray-50 rounded border hover:bg-gray-100">Cancel</button>
          <button type="button" @click="confirmPassphrase"
            class="px-3 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">Kirim</button>
        </div>
      </form>
    </Modal>
  </AuthenticatedLayout>
</template>

<style>
.masked {
  -webkit-text-security: disc;
  /* atau circle, square */
}
</style>
