<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

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
  },
  fechaFormateada: {
    type: String,
    default: null
  },
  tieneAlmacen: {
    type: Boolean,
    default: false
  }
})

const form = ref({
  lugar_venta_id: '',
  comisionista_id: ''
})

const errors = ref({})
const loading = ref(false)

// ✅ Computed para saber si se puede enviar
const puedeEnviar = computed(() => {
  return props.tieneAlmacen && 
         form.value.lugar_venta_id && 
         form.value.comisionista_id &&
         !loading.value
})

const submitForm = () => {
  const newErrors = {}
  if (!form.value.lugar_venta_id) newErrors.lugar_venta_id = 'Selecciona un lugar de venta'
  if (!form.value.comisionista_id) newErrors.comisionista_id = 'Selecciona un comisionista'
  
  if (Object.keys(newErrors).length > 0) {
    errors.value = newErrors
    return
  }
  
  loading.value = true
  router.post('/venta-tactil/nueva', {
    lugar_venta_id: form.value.lugar_venta_id,
    comisionista_id: form.value.comisionista_id
  }, {
    onFinish: () => { loading.value = false }
  })
}
</script>

<template>
  <div class="min-h-screen bg-gray-100 py-4 px-3">
    <div class="max-w-md mx-auto">
      
      <!-- Card -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden">
        
        <!-- Header -->
        <div class="bg-primary-700 px-4 py-4 text-center">
          <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mx-auto mb-2">
            <i class="fas fa-hand-peace text-white text-xl"></i>
          </div>
          <h1 class="text-lg font-bold text-white">Venta Táctil</h1>
          <p class="text-primary-200 text-xs mt-0.5">Selecciona los datos</p>
        </div>

        <!-- Info Fecha -->
        <div class="px-4 pt-4">
          <div class="bg-blue-50 rounded-lg p-2.5 border border-blue-100">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center">
                  <i class="fas fa-calendar-alt text-blue-500 text-xs"></i>
                </div>
                <div>
                  <p class="text-[10px] text-gray-500">Fecha venta</p>
                  <p class="text-sm font-semibold text-gray-800">{{ fechaFormateada || '---' }}</p>
                </div>
              </div>
              <div class="flex items-center gap-1 bg-green-100 px-2 py-0.5 rounded-full">
                <i class="fas fa-check-circle text-green-600 text-[9px]"></i>
                <span class="text-[9px] text-green-700">Válida</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ✅ MENSAJE DE ALMACÉN (DEBAJO DE FECHA) -->
        <div v-if="!tieneAlmacen" class="px-4 pt-3">
          <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
            <div class="flex items-start gap-2">
              <i class="fas fa-exclamation-triangle text-amber-500 text-sm mt-0.5"></i>
              <div>
                <p class="text-sm font-medium text-amber-800">⚠️ Sin almacenes configurados</p>
                <p class="text-xs text-amber-700 mt-0.5">
                  Esta sucursal no tiene almacenes activos. 
                  <br><span class="font-medium">No se puede iniciar una venta hasta que se configure un almacén.</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Formulario -->
        <div class="p-4 space-y-4">
          
          <!-- Lugar de Venta -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
              <i class="fas fa-store text-primary-500 mr-1 text-[10px]"></i>
              Lugar de Venta <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.lugar_venta_id"
              :disabled="!tieneAlmacen"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-400 focus:ring-1 focus:ring-primary-200 bg-white disabled:bg-gray-100 disabled:cursor-not-allowed"
              :class="{ 'border-rose-400 bg-rose-50': errors.lugar_venta_id }"
            >
              <option value="" disabled>📌 Selecciona</option>
              <option v-for="lugar in lugaresVenta" :key="lugar.id" :value="lugar.id">
                🏪 {{ lugar.nombre }}
              </option>
            </select>
            <p v-if="errors.lugar_venta_id" class="text-[10px] text-rose-500 mt-1">
              {{ errors.lugar_venta_id }}
            </p>
          </div>

          <!-- Comisionista -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
              <i class="fas fa-user-tie text-primary-500 mr-1 text-[10px]"></i>
              Comisionista <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.comisionista_id"
              :disabled="!tieneAlmacen"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-400 focus:ring-1 focus:ring-primary-200 bg-white disabled:bg-gray-100 disabled:cursor-not-allowed"
              :class="{ 'border-rose-400 bg-rose-50': errors.comisionista_id }"
            >
              <option value="" disabled>👤 Selecciona</option>
              <option v-for="comisionista in comisionistas" :key="comisionista.id" :value="comisionista.id">
                👨‍💼 {{ comisionista.nombre }}
              </option>
            </select>
            <p v-if="errors.comisionista_id" class="text-[10px] text-rose-500 mt-1">
              {{ errors.comisionista_id }}
            </p>
          </div>

          <!-- Mensaje info -->
          <div class="bg-primary-50 rounded-lg p-2 text-center">
            <p class="text-[10px] text-primary-600 flex items-center justify-center gap-1">
              <i class="fas fa-check-circle text-[9px]"></i>
              {{ tieneAlmacen ? 'Completa todos los campos para iniciar la venta' : '⚠️ Configura un almacén primero' }}
            </p>
          </div>
        </div>

        <!-- Botón -->
        <div class="px-4 pb-4">
          <button
            type="button"
            @click="submitForm"
            :disabled="!puedeEnviar"
            class="w-full py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-medium text-sm shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <i v-if="loading" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-play text-xs"></i>
            <span>{{ loading ? 'Iniciando...' : 'Iniciar Venta' }}</span>
            <i v-if="!loading" class="fas fa-arrow-right text-[10px]"></i>
          </button>
          <p v-if="!tieneAlmacen" class="text-[9px] text-amber-500 text-center mt-1">
            <i class="fas fa-info-circle"></i>
            No se puede iniciar venta sin almacenes
          </p>
        </div>

        <!-- Separador -->
        <div class="relative px-4">
          <div class="border-t border-gray-100"></div>
        </div>

        <!-- Enlace -->
        <div class="px-4 pb-4 pt-2 text-center">
          <router-link 
            :to="{ name: 'ventas.crear' }" 
            class="text-xs text-gray-400 hover:text-primary-500 transition-colors inline-flex items-center gap-1"
          >
            <i class="fas fa-arrow-left text-[9px]"></i>
            Usar venta normal
          </router-link>
        </div>
      </div>

      <!-- Footer -->
      <div class="text-center mt-3">
        <p class="text-[9px] text-gray-400 flex items-center justify-center gap-2">
          <i class="fas fa-shield-alt"></i>
          <span>Seguro</span>
          <i class="fas fa-clock"></i>
          <span>Rápido</span>
          <i class="fas fa-mobile-alt"></i>
          <span>Táctil</span>
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Transiciones */
button:active {
  transform: scale(0.97);
}

.transition-all {
  transition: all 0.2s ease;
}

button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>