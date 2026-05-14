<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  empresas: { type: Array, default: () => [] },
  selected: { type: Object, default: () => ({}) },
  isSuper: { type: Boolean, default: false },
})

const empresaId  = ref(props.selected?.empresa_id  ?? '')
const sucursalId = ref(props.selected?.sucursal_id ?? '')
const sucursales = ref([])
const guardando = ref(false)

const cargarSucursales = async (idEmpresa) => {
  sucursales.value = []
  if (!idEmpresa) return
  try {
    const { data } = await axios.get(`/contexto/sucursales/${idEmpresa}`)
    sucursales.value = Array.isArray(data) ? data : []
  } catch (error) {
    console.error('Error cargando sucursales:', error)
  }
}

watch(empresaId, async (val) => {
  sucursalId.value = ''
  if (val) {
    await cargarSucursales(val)
  }
})

// 🔥 Forzar recarga después del login
onMounted(async () => {
  // Si venimos del login con el parámetro reload, forzar recarga real
  if (window.location.search.includes('reload=1')) {
    window.location.href = '/contexto'
    return
  }
  
  if (empresaId.value) {
    await cargarSucursales(empresaId.value)
  }
})

const guardar = async () => {
  if (!empresaId.value || !sucursalId.value) {
    alert('Selecciona empresa y sucursal')
    return
  }
  
  if (guardando.value) return
  guardando.value = true
  
  try {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    
    const response = await axios.post('/contexto', {
      _token: token,
      empresa_id: empresaId.value,
      sucursal_id: sucursalId.value
    })
    
    if (response.data.redirect) {
      window.location.href = response.data.redirect
    } else {
      alert(response.data.message || 'Error al guardar')
      guardando.value = false
    }
  } catch (error) {
    console.error('Error:', error)
    
    if (error.response?.status === 419) {
      // 🔥 FORZAR RECARGA COMPLETA Y LIMPIAR CACHÉ
      window.location.reload(true)
    } else {
      alert(error.response?.data?.message || 'Error al guardar el contexto')
      guardando.value = false
    }
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-2xl mx-auto overflow-hidden">
      <div class="bg-guindo-900 text-white px-6 py-5">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-guindo-800 rounded-xl flex items-center justify-center">
            <i class="fas fa-building text-amber-400 text-xl"></i>
          </div>
          <div>
            <h1 class="text-xl font-bold">Seleccionar Contexto</h1>
            <p class="text-xs opacity-80">Elige la empresa y sucursal para trabajar</p>
          </div>
        </div>
      </div>

      <div class="px-6 py-6 bg-gray-50">
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-building mr-2 text-guindo-600"></i> Empresa
          </label>
          <select v-model="empresaId"
            class="w-full rounded-lg border-gray-300 focus:ring-guindo-500 focus:border-guindo-500 py-2.5 px-3">
            <option value="" disabled>Selecciona una empresa</option>
            <option v-for="e in props.empresas" :key="e.id" :value="e.id">
              {{ e.nombre }} — NIT {{ e.nit }}
              <span v-if="e.facturacion_habilitada" class="text-emerald-600 ml-2 text-xs">✓ Facturación</span>
              <span v-else class="text-gray-400 ml-2 text-xs">✗ Sin facturación</span>
            </option>
          </select>
        </div>

        <div class="mb-8">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-store mr-2 text-guindo-600"></i> Sucursal
          </label>
          <select v-model="sucursalId" :disabled="!empresaId || guardando"
            class="w-full rounded-lg border-gray-300 focus:ring-guindo-500 focus:border-guindo-500 disabled:bg-gray-100 py-2.5 px-3">
            <option value="" disabled>Selecciona una sucursal</option>
            <option v-for="s in sucursales" :key="s.id" :value="s.id">
              {{ s.nombre }} (N° {{ s.numero }})
              <span v-if="s.facturacion_habilitada" class="text-emerald-600 ml-2 text-xs">✓ Facturación</span>
              <span v-else class="text-gray-400 ml-2 text-xs">✗ Sin facturación</span>
            </option>
          </select>
          <p v-if="empresaId && sucursales.length === 0" class="text-xs text-amber-600 mt-1">
            <i class="fas fa-info-circle mr-1"></i> Esta empresa no tiene sucursales asignadas
          </p>
        </div>

        <div class="flex justify-end">
          <button @click="guardar" :disabled="!empresaId || !sucursalId || guardando"
            class="px-6 py-2.5 rounded-lg bg-guindo-700 hover:bg-guindo-800 text-white font-medium shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
            <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-save"></i>
            {{ guardando ? 'Guardando...' : 'Guardar contexto' }}
          </button>
        </div>
      </div>

      <div class="px-6 py-3 bg-gray-100 border-t border-gray-200 text-xs text-gray-500 flex justify-between items-center">
        <span><i class="fas fa-info-circle mr-1"></i> Solo verás las empresas y sucursales asignadas a tu usuario</span>
        <span v-if="isSuper" class="text-guindo-600 font-medium">
          <i class="fas fa-crown mr-1"></i> Super Usuario
        </span>
      </div>
    </div>
  </div>
</template>