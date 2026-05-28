<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
  empresa: Object,
  sucursal: Object,
  sucursalGestion: Object,
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

// 🔥 Usar fetch + window.location.replace
const guardar = async () => {
  if (!puntoVentaId.value) return
  
  try {
    const response = await fetch('/contexto/pdv', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        punto_venta_id: puntoVentaId.value
      })
    })
    
    const data = await response.json()
    
    if (data.redirect) {
      // 🔥 Reemplazar la URL actual, no guardar en historial
      window.location.replace(data.redirect)
    } else if (data.error) {
      alert(data.error)
    }
  } catch (error) {
    console.error('Error:', error)
    alert('Error al guardar')
  }
}

onMounted(cargarPdvs)
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-2xl mx-auto overflow-hidden">
      <div class="bg-primary-900 text-white px-6 py-5">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-primary-800 rounded-xl flex items-center justify-center">
            <i class="fas fa-cash-register text-amber-400 text-xl"></i>
          </div>
          <div>
            <h1 class="text-xl font-bold">Seleccionar Punto de Venta</h1>
            <p class="text-xs opacity-80">Elige el punto de venta para continuar</p>
          </div>
        </div>
      </div>

      <div class="px-6 py-6 bg-gray-50">
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
          <div class="flex items-center gap-3 mb-3">
            <i class="fas fa-building text-primary-600"></i>
            <span class="text-sm font-medium text-gray-700">Información del Contexto</span>
          </div>
          <div class="grid gap-2 text-sm">
            <div class="flex flex-wrap gap-2">
              <span class="font-semibold text-gray-600 w-24">Empresa:</span>
              <span>{{ empresa?.nombre || '-' }}</span>
              <span class="text-gray-400">(NIT: {{ empresa?.nit || '-' }})</span>
            </div>
            <div class="flex flex-wrap gap-2">
              <span class="font-semibold text-gray-600 w-24">Sucursal:</span>
              <span>{{ sucursalGestion?.Nombre || sucursal?.nombre || '-' }}</span>
              <span v-if="sucursalGestion?.NumeroSucursal" class="text-gray-400">(N° {{ sucursalGestion.NumeroSucursal }})</span>
            </div>
            <div class="flex flex-wrap gap-2">
              <span class="font-semibold text-gray-600 w-24">Ambiente:</span>
              <span>{{ empresa?.ambiente == 2 ? 'Pruebas' : 'Producción' }}</span>
              <span class="text-gray-400">(Modalidad: {{ empresa?.modalidad == 1 ? 'Electrónica' : 'Computarizada' }})</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-qrcode mr-2 text-primary-600"></i> Punto de Venta
          </label>

          <div v-if="cargando" class="text-sm text-gray-500 py-4 text-center">
            <i class="fas fa-spinner fa-spin mr-2"></i> Cargando puntos de venta...
          </div>

          <div v-else>
            <select
              v-model="puntoVentaId"
              class="w-full rounded-lg border-gray-300 focus:ring-primary-500 focus:border-primary-500 py-2.5 px-3"
            >
              <option :value="0">— Selecciona un punto de venta —</option>
              <option v-for="p in pdvs" :key="p.id" :value="p.id">
                {{ p.codigo ? `#${p.codigo} — ` : '' }}{{ p.nombre || 'Sin nombre' }}
              </option>
            </select>

            <p v-if="error" class="text-sm text-red-600 mt-2 flex items-center gap-1">
              <i class="fas fa-exclamation-triangle"></i> {{ error }}
            </p>

            <div class="mt-5 flex justify-end">
              <button
                @click="guardar"
                :disabled="!puntoVentaId"
                class="px-6 py-2.5 rounded-lg bg-primary-700 hover:bg-primary-800 text-white font-medium shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              >
                <i class="fas fa-save"></i>
                Guardar contexto
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="px-6 py-3 bg-gray-100 border-t border-gray-200 text-xs text-gray-500 flex justify-between items-center">
        <span><i class="fas fa-info-circle mr-1"></i> Selecciona el punto de venta para continuar</span>
        <span class="text-primary-600">
          <i class="fas fa-store"></i> PDV
        </span>
      </div>
    </div>
  </div>
</template>