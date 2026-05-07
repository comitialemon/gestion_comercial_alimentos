<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  lugaresVenta: {
    type: Array,
    required: true,
    default: () => []
  },
  comisionistas: {
    type: Array,
    required: true,
    default: () => []
  }
})

const form = ref({
  lugar_venta_id: '',
  comisionista_id: ''
})

const errors = ref({})
const loading = ref(false)

const submitForm = () => {
  const newErrors = {}
  if (!form.value.lugar_venta_id) newErrors.lugar_venta_id = 'Selecciona un lugar de venta'
  if (!form.value.comisionista_id) newErrors.comisionista_id = 'Selecciona un comisionista'
  
  if (Object.keys(newErrors).length > 0) {
    errors.value = newErrors
    return
  }
  
  loading.value = true
  router.post('/venta-factura/store', {
    lugar_venta_id: form.value.lugar_venta_id,
    comisionista_id: form.value.comisionista_id
  })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
    <div class="py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-2xl mb-4">
            <i class="fas fa-cash-register text-2xl text-emerald-600"></i>
          </div>
          <h1 class="text-2xl font-bold text-gray-900">Nueva Venta</h1>
          <p class="mt-2 text-sm text-gray-500">
            Selecciona el lugar de venta y el comisionista
          </p>
        </div>

        <!-- Formulario -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
          <div class="p-6 space-y-6">
            <!-- Lugar de Venta -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                📍 Lugar de Venta
                <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="form.lugar_venta_id"
                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-200 transition-all"
                :class="{ 'border-rose-400': errors.lugar_venta_id }"
              >
                <option value="">Selecciona un lugar de venta</option>
                <option 
                  v-for="lugar in lugaresVenta" 
                  :key="lugar.id" 
                  :value="lugar.id"
                >
                  {{ lugar.nombre }}
                </option>
              </select>
              <p v-if="errors.lugar_venta_id" class="mt-1 text-sm text-rose-500">
                {{ errors.lugar_venta_id }}
              </p>
            </div>

            <!-- Comisionista -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                🤝 Comisionista
                <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="form.comisionista_id"
                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-200 transition-all"
                :class="{ 'border-rose-400': errors.comisionista_id }"
              >
                <option value="">Selecciona un comisionista</option>
                <option 
                  v-for="comisionista in comisionistas" 
                  :key="comisionista.id" 
                  :value="comisionista.id"
                >
                  {{ comisionista.nombre }}
                </option>
              </select>
              <p v-if="errors.comisionista_id" class="mt-1 text-sm text-rose-500">
                {{ errors.comisionista_id }}
              </p>
            </div>
          </div>

          <!-- Botón Aceptar -->
          <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button
              type="button"
              @click="submitForm"
              :disabled="loading"
              class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-medium shadow-sm hover:shadow-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              <i v-if="loading" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-arrow-right"></i>
              {{ loading ? 'Cargando...' : 'Aceptar' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>