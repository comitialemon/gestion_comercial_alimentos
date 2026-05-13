<!-- resources/js/Pages/Facturacion/SiatCUFD/Vigente.vue -->
<script setup>
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  contexto: Object,
  cufd: Object,
  historial: Array,
  flash: Object
})

const solicitando = ref(false)
const solicitandoCuis = ref(false)
const mensajeError = ref(null)
const mensajeExito = ref(null)
const infoCuis = ref(null)
const cargandoInfo = ref(false)

// Computed para saber si falta CUIS
const faltaCuis = computed(() => {
  return props.cufd?.requiere_cuis === true || 
         (props.cufd?.message && props.cufd.message.includes('CUIS'))
})

// Computed para saber si ya hay CUFD activo
const tieneCufdActivo = computed(() => {
  return props.cufd?.success && props.cufd?.data
})

// Cargar información del CUIS para este PDV
const cargarInfoCuis = async () => {
  cargandoInfo.value = true
  try {
    const nitEmisor = props.contexto?.cliente_nit
    const puntoVentaId = props.contexto?.punto_venta_id
    
    if (!nitEmisor || !puntoVentaId) {
      console.warn('Faltan datos para cargar info CUIS')
      return
    }
    
    const response = await axios.get('http://localhost:8081/api/v1/facturacion/cufd/cuis-info', {
      params: {
        nit_emisor: nitEmisor,
        punto_venta_id: puntoVentaId
      }
    })
    
    if (response.data.success) {
      infoCuis.value = response.data.data
    }
  } catch (error) {
    console.error('Error cargando info CUIS:', error)
  } finally {
    cargandoInfo.value = false
  }
}

// Solicitar CUIS primero
const solicitarCuisPrimero = () => {
  if (solicitandoCuis.value) return
  
  solicitandoCuis.value = true
  mensajeError.value = null
  mensajeExito.value = null
  
  router.post('/facturacion/siat/cuis/solicitar', {}, {
    preserveScroll: true,
    onSuccess: () => {
      mensajeExito.value = '✅ CUIS solicitado correctamente. Ahora puedes solicitar el CUFD.'
      setTimeout(() => {
        mensajeExito.value = null
        router.reload()
      }, 2000)
    },
    onError: (errors) => {
      mensajeError.value = errors.message || 'Error al solicitar CUIS'
      setTimeout(() => { mensajeError.value = null }, 5000)
    },
    onFinish: () => {
      solicitandoCuis.value = false
    }
  })
}

// Solicitar CUFD
const solicitarCufd = () => {
  if (solicitando.value) return
  
  solicitando.value = true
  mensajeError.value = null
  mensajeExito.value = null
  
  router.post('/facturacion/siat/cufd/solicitar', {}, {
    preserveScroll: true,
    onSuccess: () => {
      mensajeExito.value = '✅ CUFD solicitado correctamente.'
      setTimeout(() => {
        mensajeExito.value = null
        router.reload()
      }, 1500)
    },
    onError: (errors) => {
      mensajeError.value = errors.message || 'Error al solicitar CUFD'
      setTimeout(() => { mensajeError.value = null }, 5000)
    },
    onFinish: () => {
      solicitando.value = false
    }
  })
}

const formatearFecha = (fecha) => {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleString('es-BO')
}

onMounted(() => {
  cargarInfoCuis()
})
</script>

<template>
  <div class="max-w-7xl mx-auto p-4 sm:p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">CUFD vigente</h1>
      <p class="text-gray-500 text-sm mt-1">Código Único de Facturación Digital</p>
    </div>

    <!-- Panel de contexto -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="bg-gradient-to-br from-blue-50 to-white rounded-xl border border-blue-200 p-4 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <div>
            <div class="text-xs font-medium text-blue-600 uppercase tracking-wider">Empresa</div>
            <div class="font-bold text-gray-900 text-base">{{ contexto?.cliente_nombre || '—' }}</div>
            <div class="text-xs text-gray-400">NIT: {{ contexto?.cliente_nit || '—' }}</div>
          </div>
        </div>
      </div>
      
      <div class="bg-gradient-to-br from-green-50 to-white rounded-xl border border-green-200 p-4 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <div>
            <div class="text-xs font-medium text-green-600 uppercase tracking-wider">Sucursal</div>
            <div class="font-bold text-gray-900 text-base">{{ contexto?.sucursal_nombre || '—' }}</div>
            <div class="text-xs text-gray-400">N°: {{ contexto?.sucursal_numero || '—' }}</div>
          </div>
        </div>
      </div>
      
      <div class="bg-gradient-to-br from-purple-50 to-white rounded-xl border border-purple-200 p-4 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9a6 6 0 10-12 0 6 6 0 0012 0zm0 0a2 2 0 014 0m-4 0h4" />
            </svg>
          </div>
          <div>
            <div class="text-xs font-medium text-purple-600 uppercase tracking-wider">Punto de Venta</div>
            <div class="font-bold text-gray-900 text-base">{{ contexto?.punto_venta_nombre || '—' }}</div>
            <div class="text-xs text-gray-400">Código: {{ contexto?.punto_venta_codigo || '—' }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Información del CUIS que se está usando -->
    <div class="mb-6 p-4 bg-indigo-50 rounded-xl border border-indigo-200">
      <div class="flex items-center gap-2 mb-3">
        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="text-sm font-semibold text-indigo-800">Información del CUIS para este PDV</h3>
      </div>
      
      <div v-if="cargandoInfo" class="text-sm text-indigo-600">
        Cargando información del CUIS...
      </div>
      
      <div v-else-if="infoCuis" class="space-y-3">
        <!-- CUIS del PDV actual -->
        <div class="bg-white rounded-lg p-3">
          <div class="flex justify-between items-center">
            <div>
              <span class="text-xs text-gray-500">CUIS del PDV Actual</span>
              <div class="font-mono text-sm font-bold" :class="infoCuis.cuis_del_pdv_actual ? 'text-green-600' : 'text-red-600'">
                {{ infoCuis.cuis_del_pdv_actual?.codigo || '❌ No tiene CUIS' }}
              </div>
              <div v-if="infoCuis.cuis_del_pdv_actual?.fecha_vigencia" class="text-xs text-gray-400">
                Vigente hasta: {{ formatearFecha(infoCuis.cuis_del_pdv_actual.fecha_vigencia) }}
              </div>
            </div>
            <div v-if="infoCuis.cuis_del_pdv_actual?.activo == 1" class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">
              Activo
            </div>
            <div v-else class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">
              Inactivo
            </div>
          </div>
        </div>
        
        <!-- CUIS del PDV 0 (alternativa) -->
        <div v-if="infoCuis.cuis_del_pdv_cero" class="bg-white rounded-lg p-3 border-l-4 border-amber-400">
          <div class="flex justify-between items-center">
            <div>
              <span class="text-xs text-gray-500">CUIS del PDV 0 (Base)</span>
              <div class="font-mono text-sm font-bold text-amber-600">
                {{ infoCuis.cuis_del_pdv_cero?.codigo }}
              </div>
              <div class="text-xs text-gray-400">
                PDV: {{ infoCuis.pdv_cero?.nombre }} (Código: {{ infoCuis.pdv_cero?.codigo }})
              </div>
            </div>
          </div>
        </div>
        
        <!-- Alerta si no hay CUIS -->
        <div v-if="!infoCuis.cuis_del_pdv_actual && !infoCuis.cuis_del_pdv_cero" class="bg-red-50 rounded-lg p-3 text-red-700 text-sm">
          ⚠️ No hay CUIS activo para este punto de venta ni para el PDV 0. Debes solicitar un CUIS primero.
        </div>
        
        <!-- Indicador de qué CUIS se usará -->
        <div class="bg-indigo-100 rounded-lg p-2 text-xs text-indigo-700 text-center">
          <strong>📌 Nota:</strong> 
          <span v-if="infoCuis.cuis_del_pdv_actual">Se usará el CUIS del PDV actual para solicitar el CUFD.</span>
          <span v-else-if="infoCuis.cuis_del_pdv_cero">Se usará el CUIS del PDV 0 para solicitar el CUFD (recomendado).</span>
          <span v-else>No hay CUIS disponible.</span>
        </div>
      </div>
    </div>

    <!-- Mensajes flash -->
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg">
      ✅ {{ flash.success }}
    </div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg">
      ❌ {{ flash.error }}
    </div>
    <div v-if="mensajeExito" class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg">
      {{ mensajeExito }}
    </div>
    <div v-if="mensajeError" class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg">
      ❌ {{ mensajeError }}
    </div>

    <!-- ADVERTENCIA: Falta CUIS -->
    <div v-if="faltaCuis" class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
      <div class="flex items-start gap-3">
        <div class="flex-shrink-0">
          <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <div class="flex-1">
          <h3 class="font-semibold text-amber-800">⚠️ No hay CUIS activo</h3>
          <p class="text-sm text-amber-700 mt-1">
            Para solicitar un CUFD, primero debes tener un CUIS activo para este punto de venta.
          </p>
          <button 
            @click="solicitarCuisPrimero"
            :disabled="solicitandoCuis"
            class="mt-3 px-4 py-2 bg-amber-600 text-white rounded-lg text-sm hover:bg-amber-700 disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="solicitandoCuis" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ solicitandoCuis ? 'Solicitando CUIS...' : 'Solicitar CUIS primero' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Panel de acciones -->
    <div class="flex justify-end mb-6">
      <div v-if="tieneCufdActivo" class="text-sm text-green-600 bg-green-50 px-4 py-2 rounded-lg">
        ✅ CUFD activo y vigente
      </div>
      
      <button 
        v-else-if="!faltaCuis"
        @click="solicitarCufd"
        :disabled="solicitando"
        class="inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium gap-2"
      >
        <svg v-if="solicitando" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <span>{{ solicitando ? 'Solicitando...' : 'Solicitar CUFD' }}</span>
      </button>
    </div>

    <!-- Tarjeta CUFD Activo -->
    <div class="rounded-xl border overflow-hidden shadow-sm bg-white mb-6">
      <div class="px-6 py-4 border-b bg-gradient-to-r from-green-50 to-white">
        <h2 class="font-semibold text-gray-900">CUFD Activo</h2>
      </div>
      <div class="p-6">
        <div v-if="!tieneCufdActivo" class="text-center text-gray-500">
          <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <p>{{ cufd?.message || 'No hay CUFD activo para este punto de venta' }}</p>
        </div>
        <template v-else>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <span class="text-xs text-gray-500">Código CUFD</span>
              <p class="text-xl font-mono font-bold text-blue-600 break-all">{{ cufd.data.codigo }}</p>
            </div>
            <div>
              <span class="text-xs text-gray-500">Código Control</span>
              <p class="text-sm font-mono break-all">{{ cufd.data.codigo_control || '—' }}</p>
            </div>
            <div>
              <span class="text-xs text-gray-500">Fecha Vigencia</span>
              <p class="text-sm">{{ formatearFecha(cufd.data.fecha_vigencia) }}</p>
            </div>
            <div>
              <span class="text-xs text-gray-500">Generado el</span>
              <p class="text-sm">{{ formatearFecha(cufd.data.generado_en) }}</p>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Historial de CUFD -->
    <div v-if="historial && historial.length > 0" class="rounded-xl border overflow-hidden shadow-sm bg-white">
      <div class="px-6 py-4 border-b bg-gradient-to-r from-gray-50 to-white">
        <h2 class="font-semibold text-gray-900">Historial de CUFD</h2>
        <p class="text-xs text-gray-500">Todos los CUFD solicitados para este punto de venta</p>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código Control</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Vigencia</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in historial" :key="item.codigo" class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <span class="font-mono text-sm">{{ item.codigo }}</span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ item.codigo_control || '—' }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ formatearFecha(item.fecha_vigencia) }}</td>
              <td class="px-6 py-4">
                <span class="px-2 py-1 text-xs rounded-full" :class="item.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'">
                  {{ item.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>