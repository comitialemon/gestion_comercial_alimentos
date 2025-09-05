<script setup>
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'     // 👈 export nombrado
import { Ziggy } from '@/ziggy'      // 👈 archivo generado por artisan ziggy:generate

const form = useForm({
  usuario: '',
  clave: '',
})

const submit = () => {
  // name, params ({}), absolute (false), Ziggy config
  form.post(route('login.do', {}, false, Ziggy))
}
</script>

<template>
  <div class="min-h-screen grid place-items-center bg-neutral-100">
    <div class="w-full max-w-md rounded-2xl shadow-lg border bg-white overflow-hidden">
      <div class="p-6 bg-[var(--primary,#1f2937)] text-white">
        <h1 class="text-2xl font-semibold">Acceso Operador</h1>
      </div>

      <form @submit.prevent="submit" class="p-6 space-y-4" novalidate>
        <div>
          <label class="text-sm font-medium">Usuario</label>
          <input
            v-model="form.usuario"
            autocomplete="username"
            class="mt-1 w-full rounded-xl border px-3 py-2 focus:ring-2 focus:ring-[color:var(--primary,#1f2937)]"
          />
          <p v-if="form.errors.usuario" class="mt-1 text-sm text-red-600">{{ form.errors.usuario }}</p>
        </div>

        <div>
          <label class="text-sm font-medium">Clave</label>
          <input
            type="password"
            v-model="form.clave"
            autocomplete="current-password"
            class="mt-1 w-full rounded-xl border px-3 py-2 focus:ring-2 focus:ring-[color:var(--primary,#1f2937)]"
          />
          <p v-if="form.errors.clave" class="mt-1 text-sm text-red-600">{{ form.errors.clave }}</p>
        </div>

        <p v-if="form.errors.login" class="text-red-600 text-sm">{{ form.errors.login }}</p>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-2 rounded-xl bg-[var(--primary,#1f2937)] text-white disabled:opacity-50"
        >
          {{ form.processing ? 'Validando...' : 'Ingresar' }}
        </button>
      </form>
    </div>
  </div>
</template>
