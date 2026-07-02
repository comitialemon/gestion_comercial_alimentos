<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, inject } from 'vue'
import axios from 'axios'
import ModalOperadorSucursal from './ModalOperadorSucursal.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    asignaciones: Array,
    sucursales: Array,
    operadores: Array,
})

// Estado para el modal
const modalOpen = ref(false)
const editando = ref(false)
const asignacionSeleccionada = ref(null)

// Agrupar asignaciones por sucursal
const asignacionesPorSucursal = computed(() => {
    const grupos = {}
    
    props.asignaciones.forEach(asignacion => {
        const sucursalId = asignacion.IdSucursal
        const sucursalNombre = asignacion.sucursal?.Nombre || 'Sin sucursal'
        const sucursalNumero = asignacion.sucursal?.NumeroSucursal
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                numero: sucursalNumero,
                asignaciones: []
            }
        }
        grupos[sucursalId].asignaciones.push(asignacion)
    })
    
    return Object.values(grupos).sort((a, b) => a.nombre.localeCompare(b.nombre))
})

// Estado para acordeón
const sucursalesExpandidas = ref({})

// Inicializar: expandir primera sucursal
if (asignacionesPorSucursal.value.length > 0) {
    sucursalesExpandidas.value[asignacionesPorSucursal.value[0].id] = true
}

const toggleSucursal = (sucursalId) => {
    sucursalesExpandidas.value[sucursalId] = !sucursalesExpandidas.value[sucursalId]
}

const isExpanded = (sucursalId) => {
    return sucursalesExpandidas.value[sucursalId] === true
}

// Abrir modal para nueva asignación (general)
const nuevaAsignacion = () => {
    editando.value = false
    asignacionSeleccionada.value = null
    modalOpen.value = true
}

// Abrir modal para editar
const editarAsignacion = (asignacion) => {
    editando.value = true
    asignacionSeleccionada.value = asignacion
    modalOpen.value = true
}

const recargarDatos = () => {
    window.location.reload()
}

const eliminarAsignacion = async (asignacion) => {
    if (!confirm(`¿Eliminar la asignación del operador "${asignacion.operador?.identificador?.Nombre}"?`)) {
        return
    }
    
    try {
        const response = await axios.delete(`/gestion/operador-sucursal/${asignacion.IdSucursalDB}`)
        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            recargarDatos()
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al eliminar')
    }
}
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-4 lg:py-4 lg:px-6">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-3 mb-3 sm:mb-4">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-tag text-primary-600 text-[10px] sm:text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-sm sm:text-lg font-bold text-gray-800 truncate">Asignación Operador - Sucursal</h1>
                            <p class="text-[8px] sm:text-[10px] text-gray-500 truncate">Gestión de accesos de operadores a sucursales</p>
                        </div>
                    </div>
                    <button @click="nuevaAsignacion" class="w-full sm:w-auto bg-primary-600 hover:bg-primary-700 text-white px-2.5 sm:px-3 py-1.5 sm:py-1.5 rounded-lg text-[10px] sm:text-xs flex items-center justify-center gap-1 transition">
                        <i class="fas fa-plus text-[8px] sm:text-[10px]"></i> 
                        <span>Nueva Asignación</span>
                    </button>
                </div>

                <!-- Grupos por Sucursal -->
                <div v-if="asignacionesPorSucursal.length === 0" class="bg-white rounded-lg shadow-sm p-4 sm:p-6 text-center">
                    <i class="fas fa-store text-2xl sm:text-3xl text-gray-300 mb-1 sm:mb-2 block"></i>
                    <p class="text-xs sm:text-sm text-gray-500">No hay asignaciones registradas</p>
                    <button @click="nuevaAsignacion" class="mt-1 sm:mt-2 text-primary-600 hover:text-primary-700 text-[10px] sm:text-xs">
                        <i class="fas fa-plus mr-1 text-[8px] sm:text-[10px]"></i> Crear primera asignación
                    </button>
                </div>

                <div v-else class="space-y-2 sm:space-y-3">
                    <div v-for="sucursal in asignacionesPorSucursal" :key="sucursal.id" class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <!-- Header de sucursal - Mobile y Desktop -->
                        <div 
                            @click="toggleSucursal(sucursal.id)" 
                            class="flex items-center justify-between px-2 sm:px-3 py-1.5 sm:py-2 bg-primary-50 cursor-pointer hover:bg-primary-100 transition-colors group"
                        >
                            <div class="flex items-center gap-1.5 sm:gap-2 min-w-0 flex-1">
                                <i class="fas fa-chevron-right text-primary-600 text-[8px] sm:text-[10px] transition-transform duration-200 flex-shrink-0" :class="{ 'rotate-90': isExpanded(sucursal.id) }"></i>
                                <i class="fas fa-store text-primary-600 text-[10px] sm:text-xs flex-shrink-0"></i>
                                <h2 class="text-xs sm:text-sm font-semibold text-primary-800 truncate">{{ sucursal.nombre }}</h2>
                                <span v-if="sucursal.numero" class="text-[8px] sm:text-[10px] text-primary-500 flex-shrink-0">(N° {{ sucursal.numero }})</span>
                                <span class="text-[8px] sm:text-[10px] bg-primary-100 text-primary-600 px-1 sm:px-1.5 py-0.5 rounded-full flex-shrink-0">
                                    {{ sucursal.asignaciones.length }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                                <span class="text-[8px] sm:text-[10px] text-primary-400 group-hover:text-primary-600 transition">
                                    {{ isExpanded(sucursal.id) ? '▼' : '▶' }}
                                </span>
                            </div>
                        </div>

                        <!-- Tabla Desktop -->
                        <div v-show="isExpanded(sucursal.id)" class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 lg:px-3 py-1 sm:py-1.5 text-left text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase">ID</th>
                                        <th class="px-2 lg:px-3 py-1 sm:py-1.5 text-left text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase">Operador</th>
                                        <th class="px-2 lg:px-3 py-1 sm:py-1.5 text-left text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase">CI/NIT</th>
                                        <th class="px-2 lg:px-3 py-1 sm:py-1.5 text-left text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase">Iniciales</th>
                                        <th class="px-2 lg:px-3 py-1 sm:py-1.5 text-right text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="asignacion in sucursal.asignaciones" :key="asignacion.IdSucursalDB" class="hover:bg-gray-50 transition">
                                        <td class="px-2 lg:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs text-gray-500">{{ asignacion.IdSucursalDB }}</td>
                                        <td class="px-2 lg:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs text-gray-700">
                                            <i class="fas fa-user text-gray-400 mr-1 text-[8px] sm:text-[10px]"></i>
                                            {{ asignacion.operador?.identificador?.Nombre || '-' }}
                                        </td>
                                        <td class="px-2 lg:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs font-mono text-gray-600">
                                            {{ asignacion.operador?.identificador?.CI_NIT || '-' }}
                                        </td>
                                        <td class="px-2 lg:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs text-gray-500">
                                            {{ asignacion.operador?.Iniciales || '-' }}
                                        </td>
                                        <td class="px-2 lg:px-3 py-1.5 sm:py-2 text-right space-x-0.5 sm:space-x-1">
                                            <button @click="editarAsignacion(asignacion)" class="text-primary-600 hover:text-primary-800 text-[10px] sm:text-xs p-0.5 sm:p-1 hover:bg-primary-50 rounded transition" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button @click="eliminarAsignacion(asignacion)" class="text-red-600 hover:text-red-800 text-[10px] sm:text-xs p-0.5 sm:p-1 hover:bg-red-50 rounded transition" title="Eliminar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="sucursal.asignaciones.length === 0">
                                        <td colspan="5" class="px-2 lg:px-3 py-3 sm:py-4 text-center text-gray-400 text-[10px] sm:text-xs">
                                            <i class="fas fa-user-slash text-xs sm:text-sm mb-0.5 sm:mb-1 block"></i>
                                            No hay operadores asignados
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Vista Mobile (tarjetas) -->
                        <div v-show="isExpanded(sucursal.id)" class="md:hidden divide-y divide-gray-100">
                            <div v-for="asignacion in sucursal.asignaciones" :key="asignacion.IdSucursalDB" class="p-2 sm:p-3 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 sm:gap-2 mb-0.5 sm:mb-1">
                                            <i class="fas fa-user text-primary-500 text-[10px] sm:text-xs"></i>
                                            <span class="text-[10px] sm:text-xs font-medium text-gray-900 truncate">
                                                {{ asignacion.operador?.identificador?.Nombre || '-' }}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-0.5 sm:gap-1 text-[9px] sm:text-[10px]">
                                            <div class="text-gray-500">
                                                <span class="font-medium text-gray-600">CI/NIT:</span>
                                                <span class="font-mono ml-0.5">{{ asignacion.operador?.identificador?.CI_NIT || '-' }}</span>
                                            </div>
                                            <div class="text-gray-500">
                                                <span class="font-medium text-gray-600">ID:</span>
                                                <span class="ml-0.5">{{ asignacion.IdSucursalDB }}</span>
                                            </div>
                                            <div class="text-gray-500 col-span-2">
                                                <span class="font-medium text-gray-600">Iniciales:</span>
                                                <span class="ml-0.5">{{ asignacion.operador?.Iniciales || '-' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex gap-0.5 sm:gap-1 flex-shrink-0 ml-1 sm:ml-2">
                                        <button @click="editarAsignacion(asignacion)" class="text-primary-600 hover:text-primary-800 p-1 sm:p-1.5 hover:bg-primary-50 rounded transition" title="Editar">
                                            <i class="fas fa-edit text-[10px] sm:text-xs"></i>
                                        </button>
                                        <button @click="eliminarAsignacion(asignacion)" class="text-red-600 hover:text-red-800 p-1 sm:p-1.5 hover:bg-red-50 rounded transition" title="Eliminar">
                                            <i class="fas fa-trash-alt text-[10px] sm:text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="sucursal.asignaciones.length === 0" class="p-3 sm:p-4 text-center text-gray-400 text-[10px] sm:text-xs">
                                <i class="fas fa-user-slash text-sm mb-0.5 block"></i>
                                No hay operadores asignados
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <ModalOperadorSucursal
            v-model="modalOpen"
            :asignacion="asignacionSeleccionada"
            :sucursales="sucursales"
            :operadores="operadores"
            :editando="editando"
            @saved="recargarDatos"
        />
    </div>
</template>

<style scoped>
.rotate-90 {
    transform: rotate(90deg);
}

.transition-transform {
    transition: transform 0.2s ease;
}

.transition {
    transition: all 0.15s ease-in-out;
}

/* Mejoras para dispositivos muy pequeños */
@media (max-width: 360px) {
    .grid-cols-2 {
        grid-template-columns: 1fr;
    }
}

/* Hover effects mejorados */
.bg-primary-50:hover {
    background-color: var(--color-primary-100);
}

/* Sombra y bordes suaves */
.shadow-sm {
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

/* Transición suave para el acordeón */
[v-show] {
    transition: opacity 0.2s ease;
}
</style>