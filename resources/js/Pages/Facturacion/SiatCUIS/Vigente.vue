<!-- resources/js/Pages/Facturacion/SiatCUIS/Vigente.vue -->
<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  contexto: Object,
  cuis: Object,
  historial: Array,
  flash: Object
})

const solicitar = () => {
  if (props.cuis?.success && props.cuis?.data) {
    alert('Ya existe un CUIS activo para este punto de venta. No se puede solicitar uno nuevo.')
    return
  }
  
  if (confirm('¿Solicitar nuevo CUIS al SIAT? Esta operación puede tomar unos segundos.')) {
    router.post('/facturacion/siat/cuis/solicitar', {}, {
      preserveScroll: true,
      onSuccess: () => {
        setTimeout(() => router.reload(), 1000)
      }
    })
  }
}
</script>

<template>
  <div class="max-w-7xl mx-auto p-4 sm:p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">CUIS vigente</h1>
      <p class="text-gray-500 text-sm mt-1">Código Único de Identificación del Sistema</p>
    </div>

    <!-- Panel de contexto con 4 columnas -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <!-- Empresa -->
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
      
      <!-- Sucursal -->
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
      
      <!-- Punto de Venta -->
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
      
      <!-- Configuración -->
      <div class="bg-gradient-to-br from-amber-50 to-white rounded-xl border border-amber-200 p-4 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            </svg>
          </div>
          <div>
            <div class="text-xs font-medium text-amber-600 uppercase tracking-wider">Configuración</div>
            <div class="font-bold text-gray-900 text-sm">
              <span>{{ contexto?.ambiente === 1 ? 'Producción' : contexto?.ambiente === 2 ? 'Piloto' : '—' }}</span>
              <span class="text-gray-400 mx-1">/</span>
              <span>{{ contexto?.modalidad === 1 ? 'Electrónica' : contexto?.modalidad === 2 ? 'Computarizada' : '—' }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Flash messages -->
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg">
      ✅ {{ flash.success }}
    </div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg">
      ❌ {{ flash.error }}
    </div>

    <!-- Botón Solicitar (solo si NO hay CUIS activo) -->
    <div class="flex justify-end mb-6">
      <button 
        v-if="!cuis?.success || !cuis?.data"
        @click="solicitar"
        class="inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 text-sm font-medium gap-2"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <span>Solicitar CUIS</span>
      </button>
      <div v-else class="text-sm text-green-600 bg-green-50 px-4 py-2 rounded-lg">
        ✅ Hay un CUIS activo para este punto de venta
      </div>
    </div>

    <!-- Tarjeta CUIS Activo -->
    <div class="rounded-xl border overflow-hidden shadow-sm bg-white mb-6">
      <div class="px-6 py-4 border-b bg-gradient-to-r from-green-50 to-white">
        <h2 class="font-semibold text-gray-900">CUIS Activo</h2>
      </div>
      <div class="p-6 text-center">
        <div v-if="!cuis?.success || !cuis?.data" class="text-center text-gray-500">
          <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <p>{{ cuis?.message || 'No hay CUIS activo para este punto de venta' }}</p>
          <p class="text-xs mt-2">Presiona el botón "Solicitar CUIS" para obtener uno nuevo del SIAT</p>
        </div>
        <template v-else>
          <div class="mb-4">
            <span class="text-xs text-gray-500">Código CUIS</span>
            <p class="text-2xl font-mono font-bold text-blue-600 break-all">{{ cuis.data.codigo }}</p>
          </div>
          <div class="mb-4">
            <span class="text-xs text-gray-500">Fecha Vigencia</span>
            <p class="text-sm">{{ cuis.data.fecha_vigencia }}</p>
          </div>
          <div class="mb-4">
            <span class="text-xs text-gray-500">Generado el</span>
            <p class="text-sm">{{ cuis.data.generado_en }}</p>
          </div>
        </template>
      </div>
    </div>

    <!-- Historial de CUIS -->
    <div v-if="historial && historial.length > 0" class="rounded-xl border overflow-hidden shadow-sm bg-white">
      <div class="px-6 py-4 border-b bg-gradient-to-r from-gray-50 to-white">
        <h2 class="font-semibold text-gray-900">Historial de CUIS</h2>
        <p class="text-xs text-gray-500">Todos los CUIS solicitados para este punto de venta</p>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Vigencia</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Generado el</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in historial" :key="item.codigo" class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <span class="font-mono text-sm">{{ item.codigo }}</span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ item.fecha_vigencia }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ item.generado_en }}</td>
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