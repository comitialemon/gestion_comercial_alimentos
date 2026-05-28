<script setup>
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '@/ziggy'

const form = useForm({
  usuario: '',
  clave: '',
})

const submit = () => {
  form.post(route('login.do', {}, false, Ziggy))
}
</script>

<template>
  <div class="min-h-screen grid place-items-center bg-gradient-to-br from-sky-50 to-blue-100 p-4">
    <div class="w-full max-w-md rounded-2xl shadow-xl border border-white/20 bg-white overflow-hidden">
      <!-- Header con color #25b8f5 -->
      <div class="p-4 sm:p-6 text-white text-center" style="background-color: #25b8f5;">
        <div class="flex justify-center mb-3">
          <div class="w-14 h-14 sm:w-16 sm:h-16 bg-white/20 rounded-2xl flex items-center justify-center">
            <i class="fas fa-store text-2xl sm:text-3xl text-white"></i>
          </div>
        </div>
        <h1 class="text-xl sm:text-2xl font-bold">Acceso al Sistema</h1>
        <p class="text-xs sm:text-sm text-white/80 mt-1">Ingresa tus credenciales para continuar</p>
      </div>

      <!-- Formulario -->
      <form @submit.prevent="submit" class="p-4 sm:p-6 space-y-4 sm:space-y-5" novalidate>
        <div>
          <label class="text-sm font-medium text-gray-700 block mb-1">
            <i class="fas fa-user mr-1" style="color: #25b8f5;"></i> Usuario
          </label>
          <input
            v-model="form.usuario"
            type="text"
            autocomplete="username"
            placeholder="Nombre de acceso"
            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm sm:text-base focus:ring-2 focus:ring-[#25b8f5] focus:border-[#25b8f5] transition"
          />
          <p v-if="form.errors.usuario" class="mt-1 text-xs sm:text-sm text-red-600">{{ form.errors.usuario }}</p>
        </div>

        <div>
          <label class="text-sm font-medium text-gray-700 block mb-1">
            <i class="fas fa-lock mr-1" style="color: #25b8f5;"></i> Contraseña
          </label>
          <input
            type="password"
            v-model="form.clave"
            autocomplete="current-password"
            placeholder="••••••••"
            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm sm:text-base focus:ring-2 focus:ring-[#25b8f5] focus:border-[#25b8f5] transition"
          />
          <p v-if="form.errors.clave" class="mt-1 text-xs sm:text-sm text-red-600">{{ form.errors.clave }}</p>
        </div>

        <p v-if="form.errors.login" class="text-red-600 text-xs sm:text-sm text-center bg-red-50 p-2 rounded-lg">
          <i class="fas fa-exclamation-triangle mr-1"></i> {{ form.errors.login }}
        </p>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-2.5 rounded-xl text-white font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 text-sm sm:text-base"
          style="background-color: #25b8f5;"
        >
          <i v-if="form.processing" class="fas fa-spinner fa-spin"></i>
          <i v-else class="fas fa-sign-in-alt"></i>
          {{ form.processing ? 'Validando...' : 'Ingresar' }}
        </button>
      </form>

      <!-- Footer -->
      <div class="px-4 sm:px-6 py-3 bg-gray-50 border-t border-gray-200 text-center text-xs text-gray-400">
        <i class="fas fa-shield-alt mr-1"></i> Sistema de Gestión Comercial
      </div>
    </div>
  </div>
</template>