<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    configuraciones: Array,
    operadoresDisponibles: Array,
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const modalOpen = ref(false)
const searchTerm = ref('')
const operadorSeleccionado = ref('')
const loading = ref(false)
const error = ref('')
const showDropdown = ref(false)

// ==================== COMPUTED ====================
const operadoresFiltrados = computed(() => {
    if (!searchTerm.value) return props.operadoresDisponibles || []
    const termino = searchTerm.value.toLowerCase()
    return (props.operadoresDisponibles || []).filter(op => 
        op.ci?.toString().includes(termino) || 
        op.nombre?.toLowerCase().includes(termino)
    )
})

const hayOperadoresDisponibles = computed(() => {
    return props.operadoresDisponibles && props.operadoresDisponibles.length > 0
})

// ==================== FUNCIONES ====================
const abrirModal = () => {
    operadorSeleccionado.value = ''
    searchTerm.value = ''
    error.value = ''
    showDropdown.value = false
    modalOpen.value = true
}

const cerrarDropdown = () => {
    setTimeout(() => {
        showDropdown.value = false
    }, 200)
}

const seleccionarOperador = (operador) => {
    operadorSeleccionado.value = operador.id
    searchTerm.value = `${operador.ci} - ${operador.nombre}`
    showDropdown.value = false
    error.value = ''
}

const limpiarSeleccion = () => {
    operadorSeleccionado.value = ''
    searchTerm.value = ''
    error.value = ''
}

const guardarAprobador = async () => {
    if (!operadorSeleccionado.value) {
        error.value = 'Seleccione un operador'
        return
    }
    
    loading.value = true
    try {
        await axios.post('/gestion/inventario/productos-aprobacion/config', {
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

const eliminarAprobador = async (id, nombre) => {
    if (!confirm(`¿Eliminar a "${nombre}" de los aprobadores?`)) return
    
    try {
        const response = await axios.delete(`/gestion/inventario/productos-aprobacion/config/${id}`)
        
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

const toggleEstado = async (id, nombre, estadoActual) => {
    const accion = estadoActual === 0 ? 'desactivar' : 'activar'
    const mensaje = estadoActual === 0 ? 'desactivar' : 'activar'
    
    if (!confirm(`¿${mensaje === 'activar' ? 'Activar' : 'Desactivar'} a "${nombre}" como aprobador?`)) return
    
    try {
        const response = await axios.post(`/gestion/inventario/productos-aprobacion/config/${id}/toggle`)
        
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

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user-check text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Aprobadores de Productos</h1>
                            <p class="text-[10px] text-gray-500">Configura qué operadores deben aprobar los nuevos productos</p>
                        </div>
                    </div>
                    <button @click="abrirModal" 
                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition">
                        <i class="fas fa-plus text-[10px]"></i> Agregar Aprobador
                    </button>
                </div>

                <!-- ==================== TABLA DE APROBADORES ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="conf in configuraciones" :key="conf.IdProductoAprobacionConfig" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-xs font-medium text-gray-800">{{ conf.operador?.identificador?.Nombre || '-' }}</p>
                                        <p class="text-[10px] font-mono text-gray-500">{{ conf.operador?.identificador?.CI_NIT || '-' }}</p>
                                    </div>
                                    <span class="px-1.5 py-0.5 text-[8px] rounded-full whitespace-nowrap" 
                                        :class="conf.ActivoInactivo === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                        {{ conf.ActivoInactivo === 0 ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </div>
                                <div class="flex justify-end gap-2 mt-2 pt-2 border-t border-gray-200">
                                    <button @click="toggleEstado(conf.IdProductoAprobacionConfig, conf.operador?.identificador?.Nombre, conf.ActivoInactivo)" 
                                        class="px-2 py-1 text-[9px] rounded bg-secondary-50 text-secondary-600 hover:bg-secondary-100 transition flex items-center gap-1">
                                        <i :class="conf.ActivoInactivo === 0 ? 'fas fa-ban' : 'fas fa-check-circle'"></i>
                                        {{ conf.ActivoInactivo === 0 ? 'Desactivar' : 'Activar' }}
                                    </button>
                                    <button @click="eliminarAprobador(conf.IdProductoAprobacionConfig, conf.operador?.identificador?.Nombre)" 
                                        class="px-2 py-1 text-[9px] rounded bg-red-50 text-red-600 hover:bg-red-100 transition flex items-center gap-1">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                            <div v-if="configuraciones.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-user-check text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay aprobadores configurados</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET (tabla compacta) -->
                        <div v-else-if="isTablet" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase">CI / NIT</th>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase">Nombre</th>
                                        <th class="px-3 py-2 text-center text-[9px] font-medium text-primary-700 uppercase">Estado</th>
                                        <th class="px-3 py-2 text-right text-[9px] font-medium text-primary-700 uppercase w-28">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="conf in configuraciones" :key="conf.IdProductoAprobacionConfig" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-[10px] font-mono text-gray-600">{{ conf.operador?.identificador?.CI_NIT || '-' }}</td>
                                        <td class="px-3 py-2 text-[10px] text-gray-700">{{ conf.operador?.identificador?.Nombre || '-' }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="conf.ActivoInactivo === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                                {{ conf.ActivoInactivo === 0 ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right space-x-1.5">
                                            <button @click="toggleEstado(conf.IdProductoAprobacionConfig, conf.operador?.identificador?.Nombre, conf.ActivoInactivo)" 
                                                class="text-secondary-600 hover:text-secondary-800 text-[10px]" :title="conf.ActivoInactivo === 0 ? 'Desactivar' : 'Activar'">
                                                <i :class="conf.ActivoInactivo === 0 ? 'fas fa-ban' : 'fas fa-check-circle'"></i>
                                            </button>
                                            <button @click="eliminarAprobador(conf.IdProductoAprobacionConfig, conf.operador?.identificador?.Nombre)" 
                                                class="text-red-600 hover:text-red-800 text-[10px]">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="configuraciones.length === 0">
                                        <td colspan="4" class="px-4 py-10 text-center text-gray-400 text-sm">
                                            <i class="fas fa-user-check text-2xl mb-1 block"></i>
                                            No hay aprobadores configurados
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- VISTA ESCRITORIO (tabla completa) -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left text-[10px] font-medium text-primary-700 uppercase">CI / NIT</th>
                                        <th class="px-4 py-2.5 text-left text-[10px] font-medium text-primary-700 uppercase">Nombre</th>
                                        <th class="px-4 py-2.5 text-center text-[10px] font-medium text-primary-700 uppercase w-24">Estado</th>
                                        <th class="px-4 py-2.5 text-right text-[10px] font-medium text-primary-700 uppercase w-32">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="conf in configuraciones" :key="conf.IdProductoAprobacionConfig" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-2.5 text-xs font-mono text-gray-600">{{ conf.operador?.identificador?.CI_NIT || '-' }}</td>
                                        <td class="px-4 py-2.5 text-xs text-gray-700">{{ conf.operador?.identificador?.Nombre || '-' }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            <span class="px-2 py-0.5 text-[9px] rounded-full" :class="conf.ActivoInactivo === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                                {{ conf.ActivoInactivo === 0 ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right space-x-2">
                                            <button @click="toggleEstado(conf.IdProductoAprobacionConfig, conf.operador?.identificador?.Nombre, conf.ActivoInactivo)" 
                                                class="text-secondary-600 hover:text-secondary-800 transition text-xs" :title="conf.ActivoInactivo === 0 ? 'Desactivar' : 'Activar'">
                                                <i :class="conf.ActivoInactivo === 0 ? 'fas fa-ban' : 'fas fa-check-circle'"></i>
                                            </button>
                                            <button @click="eliminarAprobador(conf.IdProductoAprobacionConfig, conf.operador?.identificador?.Nombre)" 
                                                class="text-red-600 hover:text-red-800 transition text-xs">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="configuraciones.length === 0">
                                        <td colspan="4" class="px-4 py-12 text-center text-gray-400 text-sm">
                                            <i class="fas fa-user-check text-2xl mb-1 block"></i>
                                            No hay aprobadores configurados
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ==================== FOOTER INFORMATIVO ==================== -->
                <div class="mt-3 p-2.5 bg-primary-50 rounded-xl border border-primary-100 text-[10px] text-primary-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Los productos nuevos deberán ser aprobados por TODOS los aprobadores activos antes de estar disponibles para la venta.
                </div>
            </div>
        </div>

        <!-- ==================== MODAL PARA AGREGAR APROBADOR ==================== -->
        <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="modalOpen = false">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="modalOpen = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 py-3 bg-primary-600 rounded-t-xl">
                        <h3 class="text-sm font-semibold text-white">Agregar Aprobador</h3>
                        <button @click="modalOpen = false" class="text-white/80 hover:text-white transition text-sm">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Body -->
                    <div class="p-4">
                        <div v-if="error" class="mb-3 p-2 bg-red-50 text-red-600 text-[10px] rounded-lg border border-red-200">
                            <i class="fas fa-exclamation-circle mr-1"></i> {{ error }}
                        </div>
                        
                        <div class="mb-3">
                            <label class="block text-[10px] font-medium text-gray-700 mb-1">Seleccione un operador *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="searchTerm"
                                    @focus="showDropdown = true"
                                    @blur="cerrarDropdown"
                                    placeholder="Buscar por CI o nombre..."
                                    class="w-full border border-gray-300 rounded-md px-3 py-1.5 pr-8 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                >
                                <button 
                                    v-if="searchTerm"
                                    @click="limpiarSeleccion"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                
                                <!-- Dropdown -->
                                <div 
                                    v-if="showDropdown && operadoresFiltrados.length > 0"
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div
                                        v-for="op in operadoresFiltrados"
                                        :key="op.id"
                                        @click="seleccionarOperador(op)"
                                        class="px-3 py-1.5 hover:bg-primary-50 cursor-pointer border-b last:border-b-0 text-sm flex items-center gap-2"
                                        :class="{ 'bg-primary-50': operadorSeleccionado == op.id }"
                                    >
                                        <span class="font-mono text-gray-600 text-[10px]">{{ op.ci }}</span>
                                        <span class="text-gray-300 text-[10px]">-</span>
                                        <span class="text-gray-800 text-xs">{{ op.nombre }}</span>
                                    </div>
                                </div>
                                
                                <div 
                                    v-if="showDropdown && searchTerm && operadoresFiltrados.length === 0 && hayOperadoresDisponibles"
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-2 text-center text-gray-400 text-[10px]"
                                >
                                    <i class="fas fa-search mr-1"></i> No se encontraron operadores
                                </div>
                            </div>
                            <p v-if="!hayOperadoresDisponibles" class="text-[9px] text-secondary-600 mt-1">
                                <i class="fas fa-info-circle mr-0.5"></i>
                                No hay operadores disponibles. Todos ya están configurados.
                            </p>
                        </div>
                        
                        <!-- Seleccionado -->
                        <div v-if="operadorSeleccionado" class="mb-3 p-2 bg-emerald-50 rounded-lg border border-emerald-200 text-[10px] text-emerald-700 flex items-center gap-1.5">
                            <i class="fas fa-check-circle"></i>
                            Operador seleccionado: <span class="font-semibold">{{ searchTerm }}</span>
                        </div>
                        
                        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                            <button @click="modalOpen = false" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                                Cancelar
                            </button>
                            <button @click="guardarAprobador" :disabled="loading" 
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1.5 disabled:opacity-50">
                                <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-save text-[10px]"></i>
                                {{ loading ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

/* Scrollbar personalizada */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>