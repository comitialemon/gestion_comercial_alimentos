<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')  // 👈 INYECTAR TOAST

const props = defineProps({
    configuraciones: Array,
    operadoresDisponibles: Array,
})

const modalOpen = ref(false)
const searchTerm = ref('')
const operadorSeleccionado = ref('')
const loading = ref(false)
const error = ref('')
const showDropdown = ref(false)

// Operadores filtrados por búsqueda
const operadoresFiltrados = computed(() => {
    if (!searchTerm.value) return props.operadoresDisponibles || []
    const termino = searchTerm.value.toLowerCase()
    return (props.operadoresDisponibles || []).filter(op => 
        op.ci?.toString().includes(termino) || 
        op.nombre?.toLowerCase().includes(termino)
    )
})

// Abrir modal
const abrirModal = () => {
    operadorSeleccionado.value = ''
    searchTerm.value = ''
    error.value = ''
    showDropdown.value = false
    modalOpen.value = true
}

// Cerrar dropdown con delay
const cerrarDropdown = () => {
    setTimeout(() => {
        showDropdown.value = false
    }, 200)
}

// Seleccionar operador
const seleccionarOperador = (operador) => {
    operadorSeleccionado.value = operador.id
    searchTerm.value = `${operador.ci} - ${operador.nombre}`
    showDropdown.value = false
    error.value = ''
}

// Limpiar selección
const limpiarSeleccion = () => {
    operadorSeleccionado.value = ''
    searchTerm.value = ''
    error.value = ''
}

// Guardar aprobador
const guardarAprobador = async () => {
    if (!operadorSeleccionado.value) {
        error.value = 'Seleccione un operador'
        return
    }
    
    loading.value = true
    try {
        await axios.post('/gestion/productos-aprobacion/config', {
            IdOperador: operadorSeleccionado.value
        })
        toast?.success('Éxito', 'Aprobador agregado correctamente')
        modalOpen.value = false
        setTimeout(() => window.location.reload(), 500)
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al guardar'
        toast?.error('Error', error.value)
    } finally {
        loading.value = false
    }
}

// Eliminar aprobador
const eliminarAprobador = async (id, nombre) => {
    if (!confirm(`¿Eliminar a "${nombre}" de los aprobadores?`)) return
    
    try {
        const response = await axios.delete(`/gestion/productos-aprobacion/config/${id}`)
        
        if (response.data.success) {
            toast?.success('Éxito', `Aprobador "${nombre}" eliminado correctamente`)
            setTimeout(() => window.location.reload(), 500)
        } else {
            toast?.error('Error', response.data.message || 'Error al eliminar')
        }
    } catch (err) {
        console.error('Error:', err)
        toast?.error('Error', err.response?.data?.message || 'Error al eliminar')
    }
}

// Activar/Desactivar aprobador
const toggleEstado = async (id, nombre, estadoActual) => {
    const accion = estadoActual === 0 ? 'desactivar' : 'activar'
    const mensaje = estadoActual === 0 ? 'desactivar' : 'activar'
    
    if (!confirm(`¿${mensaje === 'activar' ? 'Activar' : 'Desactivar'} a "${nombre}" como aprobador?`)) return
    
    try {
        const response = await axios.post(`/gestion/productos-aprobacion/config/${id}/toggle`)
        
        if (response.data.success) {
            toast?.success('Éxito', `Aprobador ${accion === 'activar' ? 'activado' : 'desactivado'} correctamente`)
            setTimeout(() => window.location.reload(), 500)
        } else {
            toast?.error('Error', response.data.message || 'Error al cambiar estado')
        }
    } catch (err) {
        toast?.error('Error', err.response?.data?.message || 'Error al cambiar estado')
    }
}

// Verificar si hay operadores disponibles
const hayOperadoresDisponibles = computed(() => {
    return props.operadoresDisponibles && props.operadoresDisponibles.length > 0
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user-check text-primary-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Aprobadores de Productos</h1>
                            <p class="text-xs text-gray-500">Configura qué operadores deben aprobar los nuevos productos</p>
                        </div>
                    </div>
                    <button @click="abrirModal" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                        <i class="fas fa-plus"></i> Agregar Aprobador
                    </button>
                </div>

                <!-- Lista de aprobadores -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-primary-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-primary-700 uppercase">CI / NIT</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-primary-700 uppercase">Nombre</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-primary-700 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="conf in configuraciones" :key="conf.IdProductoAprobacionConfig" class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ conf.operador?.identificador?.CI_NIT || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ conf.operador?.identificador?.Nombre || '-' }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-1 text-xs rounded-full" :class="conf.ActivoInactivo === 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        {{ conf.ActivoInactivo === 0 ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button @click="toggleEstado(conf.IdProductoAprobacionConfig, conf.operador?.identificador?.Nombre, conf.ActivoInactivo)" class="text-secondary-600 hover:text-secondary-800" :title="conf.ActivoInactivo === 0 ? 'Desactivar' : 'Activar'">
                                        <i :class="conf.ActivoInactivo === 0 ? 'fas fa-ban' : 'fas fa-check-circle'"></i>
                                    </button>
                                    <button 
                                        @click="eliminarAprobador(conf.IdProductoAprobacionConfig, conf.operador?.identificador?.Nombre)" 
                                        class="text-red-600 hover:text-red-800"
                                    >
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="configuraciones.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-user-check text-3xl mb-2 block"></i>
                                    No hay aprobadores configurados
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Los productos nuevos deberán ser aprobados por TODOS los aprobadores activos antes de estar disponibles para la venta.
                </div>
            </div>
        </div>

        <!-- Modal para agregar aprobador con búsqueda -->
        <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="modalOpen = false">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="modalOpen = false"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
                    <div class="flex items-center justify-between px-5 py-3 border-b bg-primary-600 rounded-t-lg">
                        <h3 class="text-sm font-semibold text-white">Agregar Aprobador</h3>
                        <button @click="modalOpen = false" class="text-white/80 hover:text-white">✕</button>
                    </div>
                    <div class="p-5">
                        <div v-if="error" class="mb-4 p-2 bg-red-50 text-red-600 text-xs rounded">{{ error }}</div>
                        
                        <!-- Selector de operador con búsqueda -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Seleccione un operador *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="searchTerm"
                                    @focus="showDropdown = true"
                                    @blur="cerrarDropdown"
                                    placeholder="Buscar por CI o nombre..."
                                    class="w-full border rounded-lg px-3 py-2 pr-8 text-sm focus:ring-primary-500 focus:border-primary-500"
                                >
                                <button 
                                    v-if="searchTerm"
                                    @click="limpiarSeleccion"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                                
                                <!-- Dropdown de resultados -->
                                <div 
                                    v-if="showDropdown && operadoresFiltrados.length > 0"
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div
                                        v-for="op in operadoresFiltrados"
                                        :key="op.id"
                                        @click="seleccionarOperador(op)"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-sm"
                                        :class="{ 'bg-primary-50': operadorSeleccionado == op.id }"
                                    >
                                        <span class="font-mono text-gray-600">{{ op.ci }}</span>
                                        <span class="mx-2 text-gray-300">-</span>
                                        <span class="text-gray-800">{{ op.nombre }}</span>
                                    </div>
                                </div>
                                
                                <div 
                                    v-if="showDropdown && searchTerm && operadoresFiltrados.length === 0 && hayOperadoresDisponibles"
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-2 text-center text-gray-400 text-xs"
                                >
                                    No se encontraron operadores
                                </div>
                            </div>
                            <p v-if="!hayOperadoresDisponibles" class="text-xs text-secondary-600 mt-1">
                                No hay operadores disponibles para agregar. Todos los operadores de la empresa ya están configurados.
                            </p>
                        </div>
                        
                        <!-- Información del seleccionado -->
                        <div v-if="operadorSeleccionado" class="mb-4 p-2 bg-green-50 rounded text-xs text-green-700">
                            <i class="fas fa-check-circle mr-1"></i>
                            Operador seleccionado: <span class="font-semibold">{{ searchTerm }}</span>
                        </div>
                        
                        <div class="flex justify-end gap-2">
                            <button @click="modalOpen = false" class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-100 transition">
                                Cancelar
                            </button>
                            <button @click="guardarAprobador" :disabled="loading" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition flex items-center gap-2">
                                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-save"></i>
                                {{ loading ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>