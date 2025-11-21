<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SuccessFlash from '@/Components/SuccessFlash.vue';
import ErrorFlash from '@/Components/ErrorFlash.vue';
import { ref } from 'vue';

const page = usePage();
const flash = page.props.flash || {};

const clearFlash = () => {
  router.reload({ only: [] });
};

const form = useForm({
  signer_id: '',
  signer_email: '',
  method: 'passphrase',
  passphrase: '',
  totp: '',
  file_base64: '',
  tampilan: 'VIS',
  page: 1,
  originX: 0,
  originY: 0,
  width: 200,
  height: 100,
  location: '',
  reason: ''
});

const loading = ref(false);
const fileInput = ref(null);

const onFileChange = (e) => {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = () => {
    const res = reader.result;
    const base64 = (typeof res === 'string' && res.includes(',')) ? res.split(',')[1] : '';
    form.file_base64 = base64;
  };
  reader.readAsDataURL(file);
};

const submit = () => {
  loading.value = true;
  form.post(route('esign.sign.submit'), {
    onFinish: () => {
      loading.value = false;
    }
  });
};

const requestOtp = () => {
  loading.value = true;
  router.post(route('esign.totp.request'), {
    signer_id: form.signer_id,
    signer_email: form.signer_email
  }, {
    onFinish: () => {
      loading.value = false;
    }
  });
};
</script>

<template>
  <Head title="TTE Sign" />
  <AuthenticatedLayout>
    <SuccessFlash :flash="flash" @clearFlash="clearFlash" />
    <ErrorFlash :flash="flash" @clearFlash="clearFlash" />
    <div class="pt-6 sm:pt-24 mx-2 sm:px-2">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-6">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Tanda Tangan Elektronik</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <InputLabel value="NIK Penandatangan" />
              <TextInput v-model="form.signer_id" class="mt-1 block w-full" />
            </div>
            <div>
              <InputLabel value="Email Penandatangan" />
              <TextInput v-model="form.signer_email" class="mt-1 block w-full" />
            </div>
            <div>
              <InputLabel value="Metode" />
              <select v-model="form.method" class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="passphrase">Passphrase</option>
                <option value="totp">OTP</option>
              </select>
            </div>
            <div v-if="form.method === 'passphrase'">
              <InputLabel value="Passphrase" />
              <input type="password" v-model="form.passphrase" autocomplete="off" class="mt-1 block w-full border-gray-300 rounded-md" />
            </div>
            <div v-if="form.method === 'totp'">
              <InputLabel value="OTP" />
              <TextInput v-model="form.totp" class="mt-1 block w-full" />
              <PrimaryButton class="mt-2" @click="requestOtp" :disabled="loading">Minta OTP</PrimaryButton>
            </div>
            <div>
              <InputLabel value="PDF" />
              <input ref="fileInput" type="file" accept="application/pdf" @change="onFileChange" class="mt-1 block w-full" />
            </div>
            <div>
              <InputLabel value="Halaman" />
              <TextInput v-model="form.page" type="number" class="mt-1 block w-full" />
            </div>
            <div>
              <InputLabel value="Posisi X" />
              <TextInput v-model="form.originX" type="number" class="mt-1 block w-full" />
            </div>
            <div>
              <InputLabel value="Posisi Y" />
              <TextInput v-model="form.originY" type="number" class="mt-1 block w-full" />
            </div>
            <div>
              <InputLabel value="Lebar" />
              <TextInput v-model="form.width" type="number" class="mt-1 block w-full" />
            </div>
            <div>
              <InputLabel value="Tinggi" />
              <TextInput v-model="form.height" type="number" class="mt-1 block w-full" />
            </div>
            <div class="md:col-span-2">
              <InputLabel value="Alasan" />
              <TextInput v-model="form.reason" class="mt-1 block w-full" />
            </div>
          </div>
          <div class="mt-4">
            <PrimaryButton @click="submit" :disabled="loading">Tandatangani</PrimaryButton>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
  </template>