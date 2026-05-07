<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })

import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import MenuTree from './MenuTree.vue'

const props = defineProps({
  operadores: Array,
  menuCompleto: Array,
  clienteId: Number
})

const selectedOperador = ref('')
const menusAsignados = ref([])

const cargarMenusAsignados = async () => {
  if (!selectedOperador.value) return
  
  try {
    const response = await fetch(`/gestion/menu/asignar-menu/${selectedOperador.value}`)
    const data = await response.json()
    menusAsignados.value = data
  } catch (error) {
    console.error('Error al cargar menús asignados:', error)
  }
}

const actualizarAsignados = (nuevosAsignados) => {
  menusAsignados.value = nuevosAsignados
}

const guardarAsignacion = () => {
  router.post('/gestion/menu/asignar-menu', {
    operador_id: selectedOperador.value,
    menus: menusAsignados.value
  })
}

const expandirTodo = () => {
  document.querySelectorAll('.nested').forEach(el => {
    el.classList.add('active')
  })
  document.querySelectorAll('.folder-toggle').forEach(el => {
    el.textContent = '📂'
  })
}

const contraerTodo = () => {
  document.querySelectorAll('.nested').forEach(el => {
    el.classList.remove('active')
  })
  document.querySelectorAll('.folder-toggle').forEach(el => {
    el.textContent = '📁'
  })
}
</script>

<template>
  <div class="min-h-screen bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Asignar Menús por Operador</h1>
        <p class="text-gray-600 mt-1">Selecciona un operador y asigna los menús correspondientes</p>
      </div>

      <!-- Selector de operador -->
      <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          👤 Selecciona un operador
        </label>
        <select 
          v-model="selectedOperador"
          class="w-full md:w-96 rounded-lg border-gray-300 focus:border-rose-500 focus:ring-rose-500"
          @change="cargarMenusAsignados"
        >
          <option value="">-- Elegir operador --</option>
          <option 
            v-for="op in operadores" 
            :key="op.id" 
            :value="op.id"
          >
            {{ op.ci }} - {{ op.nombre }}
          </option>
        </select>
      </div>

      <!-- Árbol de menús -->
      <div v-if="selectedOperador" class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-semibold">Menús disponibles</h2>
          <div class="space-x-2">
            <button 
              @click="expandirTodo"
              class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded"
            >
              Expandir todo
            </button>
            <button 
              @click="contraerTodo"
              class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded"
            >
              Contraer todo
            </button>
          </div>
        </div>

        <div class="border rounded-lg p-4 max-h-96 overflow-y-auto">
          <MenuTree 
            :items="menuCompleto"
            :asignados="menusAsignados"
            @update:asignados="actualizarAsignados"
          />
        </div>

        <div class="mt-6 flex justify-end">
          <button 
            @click="guardarAsignacion"
            class="px-6 py-2 bg-rose-700 hover:bg-rose-800 text-white font-medium rounded-lg shadow disabled:opacity-50"
            :disabled="!selectedOperador"
          >
            💾 Guardar cambios
          </button>
        </div>
      </div>
    </div>
  </div>
</template>