<script setup>
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, onUnmounted, computed } from 'vue'

const user = usePage().props.auth.user

const form = useForm({
  nik: user.nik || '',
  signature: null,
})

const previewUrl = ref(null)
const fileName = ref('')
const fileSizeMb = ref(null)
const notif = ref({ success: null, error: null })
const editMode = ref(true)
const hasEsign = computed(() => !!user?.nik && !!user?.signature_path)
function onPick(e) {
  const file = e.target.files?.[0]
  if (!file) return
  const okType = ['image/jpeg', 'image/png'].includes(file.type)
  if (!okType) {
    alert('Format tidak valid. Hanya jpg atau png.')
    e.target.value = ''
    return
  }
  const sizeMb = file.size / (1024 * 1024)
  if (sizeMb > 2) {
    alert(`Ukuran terlalu besar (${sizeMb.toFixed(2)} MB). Maks 2 MB.`)
    e.target.value = ''
    return
  }
  form.signature = file
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = URL.createObjectURL(file)
  fileName.value = file.name
  fileSizeMb.value = sizeMb
}

onUnmounted(() => { if (previewUrl.value) URL.revokeObjectURL(previewUrl.value) })

function clearPick() {
  form.signature = null
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = null
  fileName.value = ''
  fileSizeMb.value = null
}

function submit() {
  notif.value = { success: null, error: null }
  // client-side validation for nik
  const nik = String(form.nik || '').trim()
  if (!/^\d{16}$/.test(nik)) {
    notif.value.error = 'NIK harus 16 digit angka.'
    return
  }
  form.transform((data) => ({
    ...data,
    nik,
    _method: 'patch',
  }))

  form.post(route('profile.esign.update'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { notif.value.success = 'Data eSign tersimpan.' },
    onError: () => { notif.value.error = form.errors.nik || 'Gagal menyimpan data eSign.' },
    onFinish: () => {},
  })
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900">Aktivasi TTE</h2>
      <p class="mt-1 text-sm text-gray-600">Isi NIK dan unggah spesimen tanda tangan untuk mengaktifkan e-sign.</p>
      <p class="mt-2 text-xs" :class="hasEsign ? 'text-green-600' : 'text-gray-500'">
        Status e-sign: {{ hasEsign ? 'Sudah diaktifkan' : 'Belum aktif' }}
      </p>
    </header>

    <form @submit.prevent="submit" class="mt-6 space-y-6">
      <div v-if="notif.success" class="px-3 py-2 text-sm text-green-700 bg-green-50 rounded border border-green-200">{{ notif.success }}</div>
      <div v-if="notif.error" class="px-3 py-2 text-sm text-red-700 bg-red-50 rounded border border-red-200">{{ notif.error }}</div>
      <div>
        <InputLabel for="nik" value="NIK" />
        <TextInput id="nik" type="text" class="block mt-1 w-full" v-model="form.nik" required autocomplete="off" :disabled="hasEsign && !editMode" />
        <InputError class="mt-2" :message="form.errors.nik" />
      </div>
      <div>
        <InputLabel for="signature" value="Tanda Tangan (jpg/png)" />
        <input id="signature" type="file" accept="image/jpeg,image/png" class="block mt-1 w-full" @change="onPick" :disabled="hasEsign && !editMode" />
        <InputError class="mt-2" :message="form.errors.signature" />
        <div class="mt-4 space-y-2">
          <p class="text-sm text-gray-600">Preview:</p>
          <div class="flex justify-center items-center p-2 rounded border" style="background-image: linear-gradient(45deg, #eee 25%, transparent 25%), linear-gradient(-45deg, #eee 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #eee 75%), linear-gradient(-45deg, transparent 75%, #eee 75%); background-size: 16px 16px; background-position: 0 0, 0 8px, 8px -8px, -8px 0px;">
            <img v-if="previewUrl || user.signature_path" :src="previewUrl || ('/storage/' + user.signature_path)" alt="Signature" class="object-contain max-h-40" />
            <span v-else class="text-xs text-gray-400">Belum ada gambar</span>
          </div>
          <div v-if="previewUrl" class="text-xs text-gray-600">{{ fileName }} • {{ fileSizeMb?.toFixed(2) }} MB</div>
          <div class="flex hidden gap-2">
            <button v-if="previewUrl" type="button" @click="clearPick" class="px-2 py-1 text-xs text-red-500 rounded border hover:bg-gray-50">Hapus pilihan</button>
            <a v-if="user.signature_path && !previewUrl" :href="'/storage/' + user.signature_path" target="_blank" class="px-2 py-1 text-xs rounded border hover:bg-gray-50">Lihat tersimpan</a>
          </div>
        </div>
      </div>

      <div class="flex gap-4 items-center">
        <PrimaryButton :disabled="form.processing || (hasEsign && !editMode)">{{ form.processing ? 'Menyimpan…' : 'Simpan' }}</PrimaryButton>
        <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
          <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Saved.</p>
        </Transition>
      </div>
    </form>
  </section>
</template>