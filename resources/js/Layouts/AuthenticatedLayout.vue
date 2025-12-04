<script setup>
import { ref, computed } from 'vue';
import { usePage, Link, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

const showingNavigationDropdown = ref(false);
const page = usePage();

const user = page.props.auth.user;
const role = user.role;
//const currentRoute = page.component;

const menuItems = {
  admin: [
    { label: 'Dashboard', route: 'dashboard', icon: ['fas', 'home'] },
    { label: 'Users', route: 'users.index', icon: ['fas', 'user'] },
    { label: 'SKPD', route: 'skpds.index', icon: ['fas', 'sitemap'] },
    { label: 'Nota Dinas', route: 'nota-dinas.index', icon: ['fas', 'file-lines'] },
    { label: 'Approval', route: 'approval-histories.index', icon: ['fas', 'circle-check'] },
    { label: 'API Logs', route: 'api-logs.index', icon: ['fas', 'server'] },
  ],
  skpd: [
    { label: 'Dashboard', route: 'dashboard', icon: ['fas', 'home'] },
    { label: 'Nota Dinas', route: 'nota-dinas.index', icon: ['fas', 'file-lines'] },
    { label: 'Approval', route: 'approval-histories.index', icon: ['fas', 'circle-check'] },
  ],
  asisten: [
    { label: 'Dashboard', route: 'dashboard', icon: ['fas', 'home'] },
    { label: 'Nota Dinas', route: 'nota-dinas.index', icon: ['fas', 'file-lines'] },
    { label: 'Approval', route: 'approval-histories.index', icon: ['fas', 'circle-check'] },
  ],
  sekda: [
    { label: 'Dashboard', route: 'dashboard', icon: ['fas', 'home'] },
    { label: 'Nota Dinas', route: 'nota-dinas.index', icon: ['fas', 'file-lines'] },
    { label: 'Approval', route: 'approval-histories.index', icon: ['fas', 'circle-check'] },
  ],
  bupati: [
    { label: 'Dashboard', route: 'dashboard', icon: ['fas', 'home'] },
    { label: 'Nota Dinas', route: 'nota-dinas.index', icon: ['fas', 'file-lines'] },
    { label: 'Approval', route: 'approval-histories.index', icon: ['fas', 'circle-check'] },
  ],
};

const menus = computed(() => menuItems[role] || []);

const isLoading = ref(false);
document.addEventListener('inertia:start', () => {
  isLoading.value = true;
});

document.addEventListener('inertia:finish', () => {
  isLoading.value = false;
});

const currentYear = computed(() => new Date().getFullYear());
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
</script>

<template>
  <div class="min-h-screen bg-pattern flex flex-col">
    <!-- Top Navigation -->
    <nav class="border-b border-gray-700 bg-blue-700 fixed top-0 left-0 right-0 z-50 h-16">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
          <div class="flex">
            <div class="flex shrink-0 items-center">
              <Link :href="route('dashboard')">
              <ApplicationLogo class="block h-9 w-auto fill-current text-gray-100" />
              </Link>
            </div>
            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
              <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                Dashboard
              </NavLink>
            </div>
          </div>

          <div class="hidden sm:ms-6 sm:flex sm:items-center">
            <div class="relative ms-3">
              <Dropdown align="right" width="48">
                <template #trigger>
                  <span class="inline-flex rounded-md">
                    <button type="button"
                      class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-200 bg-blue-700 hover:text-gray-400 focus:outline-none transition ease-in-out duration-150">
                      {{ user.name }}
                      <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                          d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                          clip-rule="evenodd" />
                      </svg>
                    </button>
                  </span>
                </template>

                <template #content>
                  <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                  <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                </template>
              </Dropdown>
            </div>
          </div>

          <div class="-me-2 flex items-center sm:hidden">
            <button @click="showingNavigationDropdown = !showingNavigationDropdown"
              class="inline-flex items-center justify-center p-2 rounded-md text-gray-300 hover:text-gray-200 hover:bg-blue-500 focus:outline-none focus:bg-blue-500 focus:text-gray-200 transition duration-150 ease-in-out">
              <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path v-if="!showingNavigationDropdown" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <div v-show="showingNavigationDropdown" class="sm:hidden bg-blue-700">
        <div class="space-y-1 pb-3 pt-2">
          <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
            Dashboard
          </ResponsiveNavLink>
        </div>
        <div class="border-t border-gray-200 pb-1 pt-4">
          <div class="px-4">
            <div class="text-base font-medium text-gray-100">{{ user.name }}</div>
            <div class="text-sm font-medium text-gray-300">{{ user.email }}</div>
          </div>
          <div class="mt-3 space-y-1">
            <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
            <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
          </div>
        </div>
      </div>
      <!-- Bottom Navigation -->
      <header class="border bg-white shadow fixed bottom-0 left-0 right-0 z-20 h-12 sm:sticky sm:top-16 sm:h-auto">
        <div class="max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8 flex flex-col items-center justify-center">
          <nav class="flex overflow-x-auto whitespace-nowrap scrollbar-hide w-full gap-6 justify-center">
            <template v-for="menu in menus" :key="menu.route">
              <Link :href="route(menu.route)" class="flex flex-col items-center text-sm sm:text-base"
                :class="{ 'border-b-2 border-green-500': route().current(menu.route) }">
              <font-awesome-icon :icon="menu.icon" :class="[
                'text-lg sm:text-xl',
                route().current(menu.route) ? 'text-green-500' : 'text-gray-800'
              ]" />
              <span class="mt-2 font-medium text-gray-800 hover:text-blue-700">{{ menu.label }}</span>
              </Link>
            </template>
          </nav>
        </div>
      </header>
    </nav>

    <div class="flex-1 pt-16 pb-12 sm:pt-16 sm:pb-0 relative">
      <div v-if="isLoading" class="fixed inset-0 flex items-center justify-center bg-white bg-opacity-70 z-10" :style="{
        top: '4rem',
        bottom: '0rem',
        '@media (min-width: 640px)': {
          top: '8rem',
          bottom: '0'
        }
      }">
        <svg class="animate-spin h-20 w-20 text-green-800" xmlns="http://www.w3.org/2000/svg" fill="none"
          viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-95" fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
          </path>
        </svg>
      </div>

      <main class="h-full overflow-auto">
        <slot />
      </main>
    </div>
    <footer class="text-gray-800 py-4 text-center w-full">
      <div class="max-w-7xl mx-auto px-4">
        <p class="text-sm">&copy; {{ currentYear }} {{ appName }} Kabupaten Tanah Bumbu</p>
      </div>
    </footer>
  </div>
</template>
