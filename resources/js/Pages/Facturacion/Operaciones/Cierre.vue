<script setup>
import { router } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  contexto: Object,
  flash: Object
})

const enviando = ref(false)
const mensajeError = ref(null)
const mensajeExito = ref(null)
const cargandoCuis = ref(false)
const cuisActual = ref(null)

const cargarCuisActual = async () => {
  cargandoCuis.value = true
  try {
    const nitEmisor = props.contexto?.cliente_nit
    const puntoVentaId = props.contexto?.punto_venta_id
    
    if (!nitEmisor || !puntoVentaId) {
      return
    }
    
    const response = await axios.get('http://localhost:8081/api/v1/siat/operaciones/cuis-actual', {
      params: {
        nit_emisor: nitEmisor,
        punto_venta_id: puntoVentaId,
        ambiente: props.contexto?.ambiente
      }
    })
    
    if (response.data.success) {
      cuisActual.value = response.data.data
    }
  } catch (error) {
    console.error('Error cargando CUIS actual:', error)
  } finally {
    cargandoCuis.value = false
  }
}

const cerrarOperaciones = () => {
  if (enviando.value) return
  
  let mensajeConfirmacion = '⚠️ ¿Estás seguro de realizar el CIERRE DE OPERACIONES?\n\n'
  
  if (cuisActual.value?.cuis_actual) {
    mensajeConfirmacion += `Se invalidará el siguiente CUIS:\n📌 ${cuisActual.value.cuis_actual.codigo}\n`
    mensajeConfirmacion += `📅 Vigente hasta: ${new Date(cuisActual.value.cuis_actual.fecha_vigencia).toLocaleString('es-BO')}\n\n`
  }
  
  mensajeConfirmacion += 'Esto afectará a TODOS los puntos de venta de esta sucursal.\n'
  mensajeConfirmacion += 'Después deberás solicitar un NUEVO CUIS.\n\n'
  mensajeConfirmacion += 'Esta acción NO se puede deshacer.'
  
  if (!confirm(mensajeConfirmacion)) {
    return
  }
  
  enviando.value = true
  mensajeError.value = null
  mensajeExito.value = null
  
  router.post('/facturacion/siat/operaciones/cierre', {}, {
    preserveScroll: true,
    onSuccess: (page) => {
      mensajeExito.value = page.props.flash?.success || '✅ Cierre de operaciones completado'
      // Recargar la información del CUIS después del cierre
      cargarCuisActual()
      setTimeout(() => {
        mensajeExito.value = null
      }, 5000)
    },
    onError: (errors) => {
      mensajeError.value = errors.message || 'Error al cerrar operaciones'
      setTimeout(() => {
        mensajeError.value = null
      }, 5000)
    },
    onFinish: () => {
      enviando.value = false
    }
  })
}

const formatearFecha = (fecha) => {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleString('es-BO')
}

onMounted(() => {
  cargarCuisActual()
})
</script>

<template>
  <div class="max-w-4xl mx-auto p-4 sm:p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Cierre de Operaciones</h1>
      <p class="text-gray-500 text-sm mt-1">Invalida el CUIS actual para solicitar uno nuevo</p>
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

    <!-- CUIS que se va a invalidar -->
    <div class="mb-6 p-4 bg-amber-50 rounded-xl border border-amber-200">
      <div class="flex items-center gap-2 mb-3">
        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <h3 class="text-sm font-semibold text-amber-800">CUIS que será invalidado</h3>
      </div>
      
      <div v-if="cargandoCuis" class="text-sm text-amber-600">
        Cargando información del CUIS...
      </div>
      
      <div v-else-if="cuisActual?.cuis_actual" class="bg-white rounded-lg p-3">
        <div class="flex justify-between items-center">
          <div>
            <span class="text-xs text-gray-500">Código CUIS</span>
            <div class="font-mono text-lg font-bold text-amber-600">
              {{ cuisActual.cuis_actual.codigo }}
            </div>
            <div class="text-xs text-gray-400 mt-1">
              Vigente hasta: {{ formatearFecha(cuisActual.cuis_actual.fecha_vigencia) }}
            </div>
            <div class="text-xs text-gray-400">
              Asociado al PDV: {{ cuisActual.pdv_cero?.nombre }} (Código: {{ cuisActual.pdv_cero?.codigo }})
            </div>
          </div>
          <div class="px-3 py-1 bg-amber-100 text-amber-700 text-xs rounded-full">
            Activo
          </div>
        </div>
      </div>
      
      <div v-else-if="!cargandoCuis" class="bg-white rounded-lg p-3 text-center text-gray-500">
        <p>No hay CUIS activo para esta sucursal</p>
        <p class="text-xs mt-1">No es necesario hacer cierre de operaciones</p>
      </div>
    </div>

    <!-- Mensajes -->
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

    <!-- Tarjeta de advertencia -->
    <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-6">
      <div class="flex items-start gap-4">
        <div class="flex-shrink-0">
          <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <div>
          <h3 class="font-semibold text-red-800">⚠️ ¿Qué hace el Cierre de Operaciones?</h3>
          <ul class="mt-2 text-sm text-red-700 space-y-1">
            <li>• Invalida el CUIS mostrado arriba en el SIAT</li>
            <li>• Desactiva el CUIS en tu base de datos</li>
            <li>• Afecta a TODOS los puntos de venta de esta sucursal</li>
            <li>• Te permite solicitar un NUEVO CUIS válido</li>
            <li>• Es OBLIGATORIO antes de cambiar de ambiente (Piloto → Producción)</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Botón de cierre -->
    <div class="flex justify-center">
      <button
        @click="cerrarOperaciones"
        :disabled="enviando || !cuisActual?.cuis_actual"
        class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium disabled:opacity-50 flex items-center gap-2"
      >
        <svg v-if="enviando" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        <span>{{ enviando ? 'Procesando...' : 'Cerrar Operaciones' }}</span>
      </button>
    </div>

    <!-- Pasos a seguir después -->
    <div class="mt-8 p-4 bg-blue-50 rounded-lg">
      <h4 class="text-sm font-semibold text-blue-800 mb-2">📋 Pasos a seguir después del cierre:</h4>
      <ol class="text-sm text-blue-700 space-y-1 list-decimal list-inside">
        <li>Espera la confirmación del SIAT</li>
        <li>Verifica que el CUIS ya no esté activo</li>
        <li>Ve a <strong>SIAT &gt; CUIS</strong> y solicita un NUEVO CUIS</li>
        <li>Confirma que el nuevo CUIS esté activo</li>
        <li>Luego solicita un NUEVO CUFD</li>
        <li>Finalmente sincroniza los catálogos</li>
      </ol>
    </div>
  </div>
</template>