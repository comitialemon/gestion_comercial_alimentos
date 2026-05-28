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
  <div class="min-h-screen grid place-items-center bg-gray-100">
    <div class="w-full max-w-md rounded-2xl shadow-xl border border-gray-200 bg-white overflow-hidden">
      <!-- Header con color guindo -->
      <div class="p-6 bg-primary-900 text-white text-center">
        <div class="flex justify-center mb-3">
          <div class="w-16 h-16 bg-primary-800 rounded-2xl flex items-center justify-center">
            <i class="fas fa-store text-3xl text-amber-400"></i>
          </div>
        </div>
        <h1 class="text-2xl font-bold">Acceso al Sistema</h1>
        <p class="text-xs opacity-80 mt-1">Ingresa tus credenciales para continuar</p>
      </div>

      <!-- Formulario -->
      <form @submit.prevent="submit" class="p-6 space-y-5" novalidate>
        <div>
          <label class="text-sm font-medium text-gray-700 block mb-1">
            <i class="fas fa-user mr-1 text-primary-600"></i> Usuario
          </label>
          <input
            v-model="form.usuario"
            type="text"
            autocomplete="username"
            placeholder="Nombre de acceso"
            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition"
          />
          <p v-if="form.errors.usuario" class="mt-1 text-sm text-red-600">{{ form.errors.usuario }}</p>
        </div>

        <div>
          <label class="text-sm font-medium text-gray-700 block mb-1">
            <i class="fas fa-lock mr-1 text-primary-600"></i> Contraseña
          </label>
          <input
            type="password"
            v-model="form.clave"
            autocomplete="current-password"
            placeholder="••••••••"
            class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition"
          />
          <p v-if="form.errors.clave" class="mt-1 text-sm text-red-600">{{ form.errors.clave }}</p>
        </div>

        <p v-if="form.errors.login" class="text-red-600 text-sm text-center bg-red-50 p-2 rounded-lg">
          <i class="fas fa-exclamation-triangle mr-1"></i> {{ form.errors.login }}
        </p>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-2.5 rounded-xl bg-primary-700 hover:bg-primary-800 text-white font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          <i v-if="form.processing" class="fas fa-spinner fa-spin"></i>
          <i v-else class="fas fa-sign-in-alt"></i>
          {{ form.processing ? 'Validando...' : 'Ingresar' }}
        </button>
      </form>

      <!-- Footer -->
      <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 text-center text-xs text-gray-400">
        <i class="fas fa-shield-alt mr-1"></i> Sistema de Gestión Comercial
      </div>
    </div>
  </div>
</template>