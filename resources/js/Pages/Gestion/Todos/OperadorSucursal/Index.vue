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
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-6xl mx-auto">
                <!-- Header compacto -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-tag text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">Asignación Operador - Sucursal</h1>
                            <p class="text-[10px] text-gray-500">Gestión de accesos de operadores a sucursales</p>
                        </div>
                    </div>
                    <button @click="nuevaAsignacion" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center gap-1 transition">
                        <i class="fas fa-plus text-[10px]"></i> Nueva Asignación
                    </button>
                </div>

                <!-- Grupos por Sucursal -->
                <div v-if="asignacionesPorSucursal.length === 0" class="bg-white rounded-lg shadow-sm p-6 text-center">
                    <i class="fas fa-store text-3xl text-gray-300 mb-2 block"></i>
                    <p class="text-sm text-gray-500">No hay asignaciones registradas</p>
                    <button @click="nuevaAsignacion" class="mt-2 text-primary-600 hover:text-primary-700 text-xs">
                        <i class="fas fa-plus mr-1 text-[10px]"></i> Crear primera asignación
                    </button>
                </div>

                <div v-else class="space-y-2">
                    <div v-for="sucursal in asignacionesPorSucursal" :key="sucursal.id" class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <!-- Header de sucursal -->
                        <div 
                            @click="toggleSucursal(sucursal.id)" 
                            class="flex items-center justify-between px-3 py-2 bg-primary-50 cursor-pointer hover:bg-primary-100 transition-colors group"
                        >
                            <div class="flex items-center gap-2">
                                <i class="fas fa-chevron-right text-primary-600 text-[10px] transition-transform duration-200" :class="{ 'rotate-90': isExpanded(sucursal.id) }"></i>
                                <i class="fas fa-store text-primary-600 text-xs"></i>
                                <h2 class="text-sm font-semibold text-primary-800">{{ sucursal.nombre }}</h2>
                                <span v-if="sucursal.numero" class="text-[10px] text-primary-500">(N° {{ sucursal.numero }})</span>
                                <span class="text-[10px] bg-primary-100 text-primary-600 px-1.5 py-0.5 rounded-full">
                                    {{ sucursal.asignaciones.length }}
                                </span>
                            </div>
                        </div>

                        <!-- Tabla compacta -->
                        <div v-show="isExpanded(sucursal.id)" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-1.5 text-left text-[10px] font-medium text-gray-500 uppercase">ID</th>
                                        <th class="px-3 py-1.5 text-left text-[10px] font-medium text-gray-500 uppercase">Operador</th>
                                        <th class="px-3 py-1.5 text-left text-[10px] font-medium text-gray-500 uppercase">CI/NIT</th>
                                        <th class="px-3 py-1.5 text-left text-[10px] font-medium text-gray-500 uppercase">Iniciales</th>
                                        <th class="px-3 py-1.5 text-right text-[10px] font-medium text-gray-500 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="asignacion in sucursal.asignaciones" :key="asignacion.IdSucursalDB" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-xs text-gray-500">{{ asignacion.IdSucursalDB }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-700">
                                            <i class="fas fa-user text-gray-400 mr-1 text-[10px]"></i>
                                            {{ asignacion.operador?.identificador?.Nombre || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs font-mono text-gray-600">
                                            {{ asignacion.operador?.identificador?.CI_NIT || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-500">
                                            {{ asignacion.operador?.Iniciales || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-right space-x-1">
                                            <button @click="editarAsignacion(asignacion)" class="text-primary-600 hover:text-primary-800 text-xs" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button @click="eliminarAsignacion(asignacion)" class="text-red-600 hover:text-red-800 text-xs" title="Eliminar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="sucursal.asignaciones.length === 0">
                                        <td colspan="5" class="px-3 py-4 text-center text-gray-400 text-xs">
                                            <i class="fas fa-user-slash text-sm mb-1 block"></i>
                                            No hay operadores asignados
                                        </td>
                                    </tr>
                                </tbody>
                             </table>
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
</style>