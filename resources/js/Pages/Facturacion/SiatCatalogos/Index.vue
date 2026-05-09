<!-- resources/js/Pages/Facturacion/SiatCatalogos/Index.vue -->
<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const page = usePage()
const contexto = computed(() => page.props.contexto || {})
const status = computed(() => page.props.status || {})
const flashOk = computed(() => page.props.flash?.ok || null)
const flashSuccess = computed(() => page.props.flash?.success || null)
const flashError = computed(() => page.props.flash?.error || null)
const flashPing = computed(() => page.props.flash?.ping || null)

const syncing = ref(false)
const syncingKey = ref(null)

const items = [
  { key: 'actividad_economica', label: 'Actividades Económicas', desc: 'Códigos CAEB del rubro.' },
  { key: 'documento_sector', label: 'Documento Sector', desc: 'Tipos de documento por sector.' },
  { key: 'leyenda_fiscal', label: 'Leyendas Fiscales', desc: 'Textos legales por actividad.' },
  { key: 'tipo_documento_identidad', label: 'Tipos de Doc. Identidad', desc: 'CI, CEX, PAS, NIT…' },
  { key: 'unidad_medida', label: 'Unidades de Medida', desc: 'Pza, Kg, Lt…' },
  { key: 'moneda', label: 'Monedas', desc: 'BOB, USD…' },
  { key: 'pais', label: 'Países', desc: 'Catálogo de países.' },
  { key: 'producto_sin', label: 'Productos/Servicios SIN', desc: 'Diccionario oficial (puede tardar).' },
  { key: 'actividad_documento_sector', label: 'Actividad ↔ Doc/Sector', desc: 'Compatibilidades válidas.' },
  { key: 'mensaje_servicio', label: 'Mensajes de Servicio', desc: 'Códigos y leyendas de respuesta.' },
  { key: 'evento_significativo', label: 'Eventos Significativos', desc: 'Motivos de contingencia.' },
  { key: 'motivo_anulacion', label: 'Motivos de Anulación', desc: 'Códigos de anulación.' },
  { key: 'tipo_emision', label: 'Tipo de Emisión', desc: 'En línea, contingencia…' },
  { key: 'tipo_habitacion', label: 'Tipo Habitación', desc: 'Sector hotelería.' },
  { key: 'metodo_pago', label: 'Método de Pago', desc: 'Efectivo, tarjeta…' },
  { key: 'tipo_punto_venta', label: 'Tipo Punto de Venta', desc: 'Físico, móvil…' },
  { key: 'tipo_factura', label: 'Tipos de Factura', desc: 'Compra-venta, etc.' },
]

const fmt = (v) => v ?? '—'

const syncAll = async () => {
  if (syncing.value) return
  syncing.value = true
  try {
    await router.post('/facturacion/siat/catalogos/sync', {}, { preserveScroll: true })
  } finally {
    syncing.value = false
  }
}

const syncOne = async (key) => {
  if (syncing.value || syncingKey.value) return
  syncingKey.value = key
  try {
    await router.post(`/facturacion/siat/catalogos/sync/${key}`, {}, { preserveScroll: true })
  } finally {
    syncingKey.value = null
  }
}

const pingFechaHora = async () => {
  if (syncing.value) return
  syncing.value = true
  try {
    await router.post('/facturacion/siat/catalogos/ping', {}, { preserveScroll: true })
  } finally {
    syncing.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
    <div class="py-6 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-100 rounded-2xl mb-3">
              <i class="fas fa-database text-xl text-indigo-600"></i>
            </div>
            <h1 class="text-xl font-bold text-gray-900">Catálogos SIAT</h1>
            <p class="text-xs text-gray-500 mt-1">Sincronización de catálogos con el SIN</p>
          </div>

          <div class="flex items-center gap-2">
            <button 
              :disabled="syncing" 
              @click="pingFechaHora"
              class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 text-sm"
            >
              <i class="fas fa-clock"></i>
              {{ syncing ? 'Probando…' : 'Probar fecha/hora' }}
            </button>
            <button 
              :disabled="syncing" 
              @click="syncAll"
              class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 text-sm"
            >
              <i class="fas fa-sync-alt" :class="{ 'fa-spin': syncing }"></i>
              {{ syncing ? 'Sincronizando…' : 'Sincronizar todo' }}
            </button>
          </div>
        </div>

        <!-- Contexto -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
          <div class="bg-white rounded-lg p-3 shadow-sm border">
            <div class="text-[10px] text-gray-500">NIT</div>
            <div class="font-semibold text-sm">{{ fmt(contexto.nit) }}</div>
          </div>
          <div class="bg-white rounded-lg p-3 shadow-sm border">
            <div class="text-[10px] text-gray-500">Sucursal</div>
            <div class="font-semibold text-sm">{{ fmt(contexto.sucursal) }}</div>
          </div>
          <div class="bg-white rounded-lg p-3 shadow-sm border">
            <div class="text-[10px] text-gray-500">Punto Venta</div>
            <div class="font-semibold text-sm">{{ fmt(contexto.punto_venta) }}</div>
          </div>
          <div class="bg-white rounded-lg p-3 shadow-sm border">
            <div class="text-[10px] text-gray-500">CUIS</div>
            <div class="font-semibold text-sm font-mono">{{ fmt(contexto.cuis) }}</div>
          </div>
          <div class="bg-white rounded-lg p-3 shadow-sm border">
            <div class="text-[10px] text-gray-500">Amb/Mod</div>
            <div class="font-semibold text-sm">{{ contexto.ambiente }}/{{ contexto.modalidad }}</div>
          </div>
        </div>

        <!-- Flash Messages -->
        <div v-if="flashSuccess" class="p-3 bg-green-100 text-green-800 rounded-lg text-sm">
          ✅ {{ flashSuccess }}
        </div>
        <div v-if="flashError" class="p-3 bg-red-100 text-red-800 rounded-lg text-sm">
          ❌ {{ flashError }}
        </div>
        <div v-if="flashOk" class="p-3 bg-blue-100 text-blue-800 rounded-lg text-sm">
          ℹ️ {{ flashOk }}
        </div>

        <!-- Ping Result -->
        <div v-if="flashPing && flashPing.fechaHora" class="bg-blue-50 border border-blue-200 rounded-lg p-3">
          <div class="flex items-center gap-2">
            <i class="fas fa-check-circle text-blue-600"></i>
            <span class="text-sm text-blue-800">Fecha/Hora SIN: <b>{{ flashPing.fechaHora }}</b></span>
          </div>
        </div>

        <!-- Catálogos Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="it in items" :key="it.key" class="bg-white rounded-xl border shadow-sm overflow-hidden hover:shadow-md transition">
            <div class="p-4">
              <div class="flex items-start justify-between">
                <div>
                  <div class="font-semibold text-gray-900">{{ it.label }}</div>
                  <p class="text-xs text-gray-500 mt-0.5">{{ it.desc }}</p>
                </div>
                <button 
                  :disabled="syncing || syncingKey === it.key" 
                  @click="syncOne(it.key)"
                  class="px-3 py-1.5 bg-gray-800 text-white text-xs rounded-lg hover:bg-gray-900 disabled:opacity-50"
                >
                  <i v-if="syncingKey === it.key" class="fas fa-spinner fa-spin mr-1"></i>
                  {{ syncingKey === it.key ? '...' : 'Sincronizar' }}
                </button>
              </div>
              <div class="mt-3 pt-3 border-t text-xs text-gray-500 flex justify-between">
                <span>Filas: <b class="text-gray-700">{{ fmt(status[it.key]?.rows) }}</b></span>
                <span>Última: <b class="text-gray-700">{{ fmt(status[it.key]?.last) }}</b></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>