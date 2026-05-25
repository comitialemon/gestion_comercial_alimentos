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
  router.post('/venta-tactil/nueva', {
    lugar_venta_id: form.value.lugar_venta_id,
    comisionista_id: form.value.comisionista_id
  }, {
    onFinish: () => { loading.value = false }
  })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="flex items-center justify-center min-h-screen p-4">
      <div class="w-full max-w-md">
        
        <!-- Card principal -->
        <div class="relative">
          <!-- Decoración de fondo -->
          <div class="absolute -top-10 -right-10 w-32 h-32 bg-guindo-100 rounded-full opacity-50"></div>
          <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-amber-100 rounded-full opacity-40"></div>
          
          <!-- Contenido -->
          <div class="relative bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Header con degradado -->
            <div class="bg-gradient-to-r from-guindo-700 to-guindo-800 px-6 py-6 text-center">
              <div class="inline-flex items-center justify-center w-16 h-16 bg-white/10 rounded-2xl mb-3 backdrop-blur-sm">
                <i class="fas fa-hand-peace text-3xl text-white"></i>
              </div>
              <h1 class="text-2xl font-bold text-white">Venta Táctil</h1>
              <p class="text-amber-200 text-sm mt-1">Selecciona los datos para comenzar</p>
            </div>

            <!-- Formulario -->
            <div class="p-6 space-y-5">
              <!-- Lugar de Venta -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fas fa-store text-guindo-500 mr-2"></i>
                  Lugar de Venta
                  <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                  <select
                    v-model="form.lugar_venta_id"
                    class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 text-sm appearance-none focus:border-guindo-400 focus:ring-2 focus:ring-guindo-200 transition-all duration-200 bg-white"
                    :class="{ 'border-rose-400 bg-rose-50': errors.lugar_venta_id }"
                  >
                    <option value="" disabled class="text-gray-400">📌 Selecciona un lugar</option>
                    <option 
                      v-for="lugar in lugaresVenta" 
                      :key="lugar.id" 
                      :value="lugar.id"
                    >
                      🏪 {{ lugar.nombre }}
                    </option>
                  </select>
                  <div class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                    <i class="fas fa-chevron-down text-xs"></i>
                  </div>
                </div>
                <p v-if="errors.lugar_venta_id" class="text-xs text-rose-500 mt-1 flex items-center gap-1">
                  <i class="fas fa-exclamation-circle"></i> {{ errors.lugar_venta_id }}
                </p>
              </div>

              <!-- Comisionista -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fas fa-user-tie text-guindo-500 mr-2"></i>
                  Comisionista
                  <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                  <select
                    v-model="form.comisionista_id"
                    class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 text-sm appearance-none focus:border-guindo-400 focus:ring-2 focus:ring-guindo-200 transition-all duration-200 bg-white"
                    :class="{ 'border-rose-400 bg-rose-50': errors.comisionista_id }"
                  >
                    <option value="" disabled class="text-gray-400">👤 Selecciona un comisionista</option>
                    <option 
                      v-for="comisionista in comisionistas" 
                      :key="comisionista.id" 
                      :value="comisionista.id"
                    >
                      👨‍💼 {{ comisionista.nombre }}
                    </option>
                  </select>
                  <div class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                    <i class="fas fa-chevron-down text-xs"></i>
                  </div>
                </div>
                <p v-if="errors.comisionista_id" class="text-xs text-rose-500 mt-1 flex items-center gap-1">
                  <i class="fas fa-exclamation-circle"></i> {{ errors.comisionista_id }}
                </p>
              </div>

              <!-- Resumen visual -->
              <div class="mt-4 p-3 bg-gradient-to-r from-guindo-50 to-amber-50 rounded-xl">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600">Completa los campos</span>
                  <span class="text-guindo-600 font-semibold">
                    <i class="fas fa-check-circle text-xs mr-1"></i> Requeridos
                  </span>
                </div>
              </div>
            </div>

            <!-- Botón Iniciar Venta -->
            <div class="px-6 pb-6">
              <button
                type="button"
                @click="submitForm"
                :disabled="loading"
                class="w-full py-3 rounded-xl bg-gradient-to-r from-guindo-600 to-guindo-700 hover:from-guindo-700 hover:to-guindo-800 text-white font-semibold shadow-lg hover:shadow-xl transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 group"
              >
                <i v-if="loading" class="fas fa-spinner fa-spin text-white"></i>
                <i v-else class="fas fa-play text-white group-hover:scale-110 transition-transform"></i>
                <span>{{ loading ? 'Iniciando...' : 'Iniciar Venta Táctil' }}</span>
                <i v-if="!loading" class="fas fa-arrow-right text-white opacity-70 group-hover:translate-x-1 transition-transform"></i>
              </button>
            </div>

            <!-- Separador -->
            <div class="relative px-6">
              <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200"></div>
              </div>
              <div class="relative flex justify-center text-xs">
                <span class="px-2 bg-white text-gray-400">o</span>
              </div>
            </div>

            <!-- Enlace a venta normal -->
            <div class="px-6 pb-6 pt-3">
              <router-link 
                :to="{ name: 'ventas.crear' }" 
                class="w-full flex items-center justify-center gap-2 py-2 rounded-lg text-sm text-gray-500 hover:text-guindo-600 transition-colors group"
              >
                <i class="fas fa-arrow-left text-xs group-hover:-translate-x-0.5 transition-transform"></i>
                Usar venta normal con selectores
              </router-link>
            </div>
          </div>
        </div>

        <!-- Footer decorativo -->
        <div class="text-center mt-6">
          <p class="text-[10px] text-gray-400 flex items-center justify-center gap-1">
            <i class="fas fa-shield-alt"></i>
            Datos seguros
            <span class="mx-1">•</span>
            <i class="fas fa-clock"></i>
            Venta rápida
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Animaciones sutiles */
.group-hover\:translate-x-1 {
  transform: translateX(0.25rem);
}

.group-hover\:-translate-x-0\.5 {
  transform: translateX(-0.125rem);
}

/* Mejora para selects */
select {
  cursor: pointer;
}

select:focus {
  outline: none;
}

/* Transiciones */
.transition-all {
  transition-property: all;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 200ms;
}
</style>