<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  empresas: { type: Array, default: () => [] },
  selected: { type: Object, default: () => ({}) },
  isSuper: { type: Boolean, default: false },
})

const empresaId = ref(props.selected?.empresa_id ?? '')
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

onMounted(async () => {
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
      window.location.reload(true)
    } else {
      alert(error.response?.data?.message || 'Error al guardar el contexto')
      guardando.value = false
    }
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-sky-50 to-blue-100 p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-2xl mx-auto overflow-hidden">
      <!-- Header con color #25b8f5 -->
      <div class="px-4 sm:px-6 py-5" style="background-color: #25b8f5;">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
          <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-building text-white text-xl"></i>
          </div>
          <div>
            <h1 class="text-xl sm:text-2xl font-bold text-white">Seleccionar Contexto</h1>
            <p class="text-xs text-white/80 mt-0.5">Elige la empresa y sucursal para trabajar</p>
          </div>
        </div>
      </div>

      <div class="px-4 sm:px-6 py-6 bg-gray-50">
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-building mr-2" style="color: #25b8f5;"></i> Empresa
          </label>
          <select 
            v-model="empresaId"
            class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-[#25b8f5] focus:border-[#25b8f5] py-2.5 px-3 text-sm sm:text-base"
          >
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
            <i class="fas fa-store mr-2" style="color: #25b8f5;"></i> Sucursal
          </label>
          <select 
            v-model="sucursalId" 
            :disabled="!empresaId || guardando"
            class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-[#25b8f5] focus:border-[#25b8f5] disabled:bg-gray-100 py-2.5 px-3 text-sm sm:text-base"
          >
            <option value="" disabled>Selecciona una sucursal</option>
            <option v-for="s in sucursales" :key="s.id" :value="s.id">
              {{ s.nombre }} (N° {{ s.numero }})
              <span v-if="s.facturacion_habilitada" class="text-emerald-600 ml-2 text-xs">✓ Facturación</span>
              <span v-else class="text-gray-400 ml-2 text-xs">✗ Sin facturación</span>
            </option>
          </select>
          <p v-if="empresaId && sucursales.length === 0" class="text-xs mt-1 text-amber-600">
            <i class="fas fa-info-circle mr-1"></i> Esta empresa no tiene sucursales asignadas
          </p>
        </div>

        <div class="flex justify-end">
          <button 
            @click="guardar" 
            :disabled="!empresaId || !sucursalId || guardando"
            class="px-4 sm:px-6 py-2.5 rounded-lg text-white font-medium shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm sm:text-base"
            style="background-color: #25b8f5;"
          >
            <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-save"></i>
            {{ guardando ? 'Guardando...' : 'Guardar contexto' }}
          </button>
        </div>
      </div>

      <div class="px-4 sm:px-6 py-3 bg-gray-100 border-t border-gray-200 text-xs text-gray-500 flex flex-col sm:flex-row justify-between items-center gap-2">
        <span><i class="fas fa-info-circle mr-1"></i> Solo verás las empresas y sucursales asignadas a tu usuario</span>
        <span v-if="isSuper" class="font-medium flex items-center gap-1 text-amber-600">
          <i class="fas fa-crown mr-1"></i> Super Usuario
        </span>
      </div>
    </div>
  </div>
</template>