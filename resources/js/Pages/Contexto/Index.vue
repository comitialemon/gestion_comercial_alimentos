<script setup>
import { ref, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  empresas: { type: Array, default: () => [] },
  selected: { type: Object, default: () => ({}) },
  isSuper: { type: Boolean, default: false },
})

const empresaId  = ref(props.selected?.empresa_id  ?? '')
const sucursalId = ref(props.selected?.sucursal_id ?? '')
const sucursales = ref([])

const cargarSucursales = async (idEmpresa) => {
  sucursales.value = []
  if (!idEmpresa) return
  const { data } = await axios.get(`/contexto/sucursales/${idEmpresa}`)
  sucursales.value = Array.isArray(data) ? data : []
}

watch(empresaId, async (val) => {
  sucursalId.value = ''
  await cargarSucursales(val)
})

onMounted(async () => {
  if (empresaId.value) await cargarSucursales(empresaId.value)
})

const guardar = () => {
  router.post('/contexto', {
    empresa_id:  empresaId.value,
    sucursal_id: sucursalId.value,
  })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-4">
    <div class="bg-white rounded-2xl shadow max-w-4xl mx-auto overflow-hidden">
      <div class="bg-rose-900 text-white px-6 py-5 flex items-center gap-4">
        <div class="flex-1">
          <h1 class="text-2xl font-semibold">Define Empresa y Sucursal</h1>
          <p class="text-xs opacity-90">Solo verás lo asignado a tu usuario</p>
        </div>
        <div class="text-sm font-semibold">
          {{ new Date().toLocaleDateString('es-BO',{weekday:'long',day:'2-digit',month:'long',year:'numeric'}) }}
        </div>
      </div>

      <div class="px-6 py-6 bg-gray-50">
        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
            <select v-model="empresaId"
              class="w-full rounded-lg border-gray-300 focus:ring-rose-600 focus:border-rose-600">
              <option value="" disabled>Selecciona empresa</option>
              <option v-for="e in props.empresas" :key="e.id" :value="e.id">
                {{ e.nombre }} — NIT {{ e.nit }}
                <span v-if="e.facturacion_habilitada" class="text-green-600 ml-2">(con facturación)</span>
                <span v-else class="text-gray-400 ml-2">(sin facturación)</span>
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal</label>
            <select v-model="sucursalId" :disabled="!empresaId"
              class="w-full rounded-lg border-gray-300 focus:ring-rose-600 focus:border-rose-600 disabled:bg-gray-100">
              <option value="" disabled>Selecciona sucursal</option>
              <option v-for="s in sucursales" :key="s.id" :value="s.id">
                {{ s.nombre }} ({{ s.numero }})
                <span v-if="s.facturacion_habilitada" class="text-green-600 ml-2">(con facturación)</span>
                <span v-else class="text-gray-400 ml-2">(sin facturación)</span>
              </option>
            </select>
          </div>
        </div>

        <div class="mt-6">
          <button @click="guardar" :disabled="!empresaId || !sucursalId"
            class="px-5 py-2.5 rounded-lg bg-rose-700 hover:bg-rose-800 text-white font-medium shadow disabled:opacity-50">
            Guardar contexto
          </button>
        </div>
      </div>
    </div>
  </div>
</template>