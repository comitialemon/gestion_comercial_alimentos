<template>
  <div class="min-h-screen grid place-items-center bg-gray-900 p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
      <!-- Header rojo -->
      <div class="p-6 text-white text-center" style="background-color: #dc2626;">
        <div class="flex justify-center mb-3">
          <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center">
            <i class="fas fa-exclamation-triangle text-4xl text-white"></i>
          </div>
        </div>
        <h1 class="text-2xl font-bold">Fecha No Habilitada</h1>
        <p class="text-white/80 text-sm mt-1">El sistema no puede procesar operaciones</p>
      </div>

      <!-- Contenido -->
      <div class="p-6 space-y-4">
        <div class="bg-gray-50 rounded-xl p-4 text-center border border-gray-200">
          <p class="text-sm text-gray-600">Fecha actual:</p>
          <p class="text-xl font-bold text-red-600">{{ fecha }}</p>
        </div>

        <p class="text-sm text-gray-600 text-center">
          La fecha actual no está habilitada en el sistema.<br>
          Por favor, contacta al administrador.
        </p>

        <button
          @click="logout"
          class="w-full py-3 rounded-xl text-white font-semibold transition hover:bg-red-700"
          style="background-color: #dc2626;"
        >
          <i class="fas fa-sign-out-alt mr-2"></i>
          Cerrar Sesión
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '@/ziggy'

defineProps({
  fecha: {
    type: String,
    required: true
  }
})

const logout = () => {
  router.post(route('logout', {}, false, Ziggy))
}
</script>