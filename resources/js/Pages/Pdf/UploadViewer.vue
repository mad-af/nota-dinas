<script setup>
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SuccessFlash from '@/Components/SuccessFlash.vue'
import ErrorFlash from '@/Components/ErrorFlash.vue'
import VuePdfEmbed from 'vue-pdf-embed'
import 'vue-pdf-embed/dist/styles/annotationLayer.css'
import 'vue-pdf-embed/dist/styles/textLayer.css'

const flash = ref({ success: null, error: null })
const clearFlash = () => { flash.value = { success: null, error: null } }

const fileInput = ref(null)
const uploading = ref(false)
const uploadProgress = ref(0)
const pdfUrl = ref('')
const currentPage = ref(1)
const totalPages = ref(0)
const scale = ref(1)

const MAX_SIZE_MB = 20

function onFilePicked(e) {
  const file = e.target.files?.[0]
  if (!file) return

  const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')
  if (!isPdf) {
    flash.value.error = 'Format tidak valid. Hanya file .pdf yang diperbolehkan.'
    e.target.value = ''
    return
  }

  const sizeMb = file.size / (1024 * 1024)
  if (sizeMb > MAX_SIZE_MB) {
    flash.value.error = `Ukuran file terlalu besar (${sizeMb.toFixed(2)} MB). Maksimal ${MAX_SIZE_MB} MB.`
    e.target.value = ''
    return
  }

  uploadFile(file)
}

async function uploadFile(file) {
  uploading.value = true
  uploadProgress.value = 0
  flash.value = { success: null, error: null }

  try {
    const formData = new FormData()
    formData.append('file', file)

    const resp = await axios.post(route('pdf.upload'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total) {
          uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total)
        }
      }
    })

    if (resp.data?.success && resp.data?.data?.url) {
      pdfUrl.value = resp.data.data.url
      currentPage.value = 1
      scale.value = 1
      flash.value.success = 'Upload berhasil. Preview ditampilkan.'
    } else {
      throw new Error('Respons upload tidak valid')
    }
  } catch (err) {
    const msg = err?.response?.data?.message || err?.message || 'Gagal mengunggah dokumen.'
    flash.value.error = msg
  } finally {
    uploading.value = false
    uploadProgress.value = 0
    if (fileInput.value) fileInput.value.value = ''
  }
}

function zoomIn() { scale.value = Math.min(scale.value + 0.25, 3) }
function zoomOut() { scale.value = Math.max(scale.value - 0.25, 0.5) }
function resetZoom() { scale.value = 1 }
function prevPage() { currentPage.value = Math.max(currentPage.value - 1, 1) }
function nextPage() { currentPage.value = Math.min(currentPage.value + 1, totalPages.value || currentPage.value + 1) }

function onLoaded(doc) { totalPages.value = doc?.numPages || 0 }
function onLoadingFailed(e) { flash.value.error = 'Gagal memuat dokumen. Periksa file Anda.' }
function onRenderingFailed(e) { flash.value.error = 'Gagal merender halaman PDF.' }
</script>

<template>
  <Head title="Upload & View PDF" />
  <AuthenticatedLayout>
    <SuccessFlash :flash="flash" @clearFlash="clearFlash" />
    <ErrorFlash :flash="flash" @clearFlash="clearFlash" />

    <div class="pt-6 sm:pt-24 mx-2 sm:px-2">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-6 space-y-6">
        <!-- Upload Card -->
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Unggah Dokumen PDF</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="md:col-span-2">
              <input
                ref="fileInput"
                type="file"
                accept="application/pdf"
                @change="onFilePicked"
                class="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
              />
              <p class="text-xs text-gray-500 mt-2">Hanya menerima file .pdf, maksimal {{ MAX_SIZE_MB }} MB.</p>
            </div>

            <div class="w-full">
              <button
                class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700 disabled:opacity-50"
                :disabled="uploading"
                @click="fileInput?.click()"
              >
                Pilih File
              </button>
            </div>
          </div>

          <div v-if="uploading" class="mt-4">
            <div class="w-full bg-gray-200 rounded-full h-2.5">
              <div class="bg-indigo-600 h-2.5 rounded-full" :style="{ width: uploadProgress + '%' }"></div>
            </div>
            <p class="text-xs text-gray-600 mt-1">Mengunggah… {{ uploadProgress }}%</p>
          </div>
        </div>

        <!-- Viewer Card -->
        <div class="bg-white shadow-sm sm:rounded-lg p-0 md:p-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 px-6 pt-6 md:p-0">
            <h2 class="text-lg sm:text-xl font-semibold text-gray-800">Preview PDF</h2>
            <div class="flex flex-wrap items-center gap-2">
              <button @click="zoomOut" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">-</button>
              <span class="text-sm text-gray-700">Zoom {{ Math.round(scale * 100) }}%</span>
              <button @click="zoomIn" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">+</button>
              <button @click="resetZoom" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">Reset</button>

              <div class="w-px h-6 bg-gray-300 mx-2"></div>
              <button @click="prevPage" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">Prev</button>
              <span class="text-sm text-gray-700">Halaman {{ currentPage }} / {{ totalPages || '-' }}</span>
              <button @click="nextPage" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">Next</button>
            </div>
          </div>

          <div class="mt-4 md:mt-6">
            <div v-if="!pdfUrl" class="p-6 text-sm text-gray-600">Belum ada dokumen. Silakan unggah PDF untuk melihat preview.</div>
            <div v-else class="w-full overflow-auto">
              <VuePdfEmbed
                annotation-layer
                text-layer
                :source="pdfUrl"
                :page="currentPage"
                :scale="scale"
                @loaded="onLoaded"
                @loading-failed="onLoadingFailed"
                @rendering-failed="onRenderingFailed"
                class="min-h-[60vh]"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
  </template>