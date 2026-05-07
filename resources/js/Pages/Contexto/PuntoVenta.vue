<!-- resources/js/Pages/Contexto/PuntoVenta.vue -->
<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  empresa: Object,
  sucursal_id: Number,
  selected: Object,
})

const pdvs = ref([])
const puntoVentaId = ref(props?.selected?.punto_venta_id || 0)
const cargando = ref(false)
const error = ref(null)

const cargarPdvs = async () => {
  try {
    cargando.value = true
    const res = await fetch('/contexto/pdv/lista')
    const data = await res.json()
    pdvs.value = Array.isArray(data) ? data : []
  } catch (e) {
    error.value = 'No se pudo cargar la lista de puntos de venta.'
    console.error(e)
  } finally {
    cargando.value = false
  }
}

const guardar = () => {
  router.post('/contexto/pdv', { punto_venta_id: Number(puntoVentaId.value) }, { preserveScroll: true })
}

onMounted(cargarPdvs)
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-4">
    <div class="bg-white rounded-2xl shadow max-w-4xl mx-auto overflow-hidden">
      <!-- Header -->
      <div class="bg-rose-900 text-white px-6 py-5 flex items-center gap-4">
        <div class="flex-1">
          <h1 class="text-2xl font-semibold">Elegir Punto de Venta</h1>
          <p class="text-xs opacity-90">Completa el contexto para continuar</p>
        </div>
        <div class="text-sm font-semibold">
          {{ new Date().toLocaleDateString('es-BO',{weekday:'long',day:'2-digit',month:'long',year:'numeric'}) }}
        </div>
      </div>

      <!-- Contenido -->
      <div class="px-6 py-6 bg-gray-50">
        <!-- Info empresa/sucursal -->
        <div class="rounded-2xl border p-4 mb-5 text-sm text-gray-700 bg-white">
          <div class="mb-1">
            <b>Empresa:</b> {{ empresa?.nombre }} (NIT {{ empresa?.nit }})
          </div>
          <div>
            <b>Sucursal (facturación):</b> {{ sucursal_id }}
          </div>
        </div>

        <!-- Selector PDV -->
        <div class="rounded-2xl border p-4 bg-white">
          <label class="block text-sm font-medium text-gray-700 mb-1">Punto de Venta</label>

          <div v-if="cargando" class="text-sm text-gray-500 py-2">
            Cargando puntos de venta…
          </div>

          <div v-else>
            <select
              v-model="puntoVentaId"
              class="w-full rounded-lg border-gray-300 focus:ring-rose-600 focus:border-rose-600"
            >
              <option :value="0">— Selecciona un punto de venta —</option>
              <option v-for="p in pdvs" :key="p.id" :value="p.id">
                {{ p.codigo ? `#${p.codigo} — ` : '' }}{{ p.nombre || 'Sin nombre' }}
              </option>
            </select>

            <p v-if="error" class="text-sm text-red-600 mt-2">{{ error }}</p>

            <div class="mt-5">
              <button
                @click="guardar"
                :disabled="!puntoVentaId"
                class="px-5 py-2.5 rounded-lg bg-rose-700 hover:bg-rose-800 text-white font-medium shadow disabled:opacity-50"
              >
                Guardar contexto
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>