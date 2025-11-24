<script setup>
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, onUnmounted } from 'vue'

const user = usePage().props.auth.user

const form = useForm({
  nik: user.nik || '',
  signature: null,
})

const previewUrl = ref(null)
const fileName = ref('')
const fileSizeMb = ref(null)
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
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900">Aktivasi TTE</h2>
      <p class="mt-1 text-sm text-gray-600">Isi NIK dan unggah spesimen tanda tangan untuk mengaktifkan e-sign.</p>
    </header>

    <form @submit.prevent="form.patch(route('profile.esign.update'))" class="mt-6 space-y-6">
      <div>
        <InputLabel for="nik" value="NIK" />
        <TextInput id="nik" type="text" class="block mt-1 w-full" v-model="form.nik" required autocomplete="off" />
        <InputError class="mt-2" :message="form.errors.nik" />
      </div>
      <div>
        <InputLabel for="signature" value="Tanda Tangan (jpg/png)" />
        <input id="signature" type="file" accept="image/jpeg,image/png" class="block mt-1 w-full" @change="onPick" />
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
        <PrimaryButton :disabled="form.processing">Simpan</PrimaryButton>
        <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
          <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Saved.</p>
        </Transition>
      </div>
    </form>
  </section>
</template>