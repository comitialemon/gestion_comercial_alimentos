<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    inventarios: Object,
    sucursales: Array,
    realizadosPor: Array,
    totalActivos: Number,
    totalInactivos: Number,
    filtros: Object,
})

// ==================== FILTROS ====================
const search = ref(props.filtros?.search || '')
const estado = ref(props.filtros?.estado || '')
const sucursalesSeleccionadas = ref([])
const realizadosPorSeleccionados = ref([])

// Modal
const modalAbierto = ref(false)
const inventarioEditando = ref(null)
const editandoId = ref(null)
const editandoActivoInactivo = ref(0)
const modalLoading = ref(false)

// Procesar filtros iniciales
onMounted(() => {
    if (props.filtros?.sucursales) {
        sucursalesSeleccionadas.value = props.filtros.sucursales.split(',').map(Number)
    }
    if (props.filtros?.realizados_por) {
        realizadosPorSeleccionados.value = props.filtros.realizados_por.split(',').map(Number)
    }
})

// Alternar selección de sucursal
const toggleSucursal = (sucursal) => {
    const id = sucursal.id
    const index = sucursalesSeleccionadas.value.indexOf(id)
    if (index === -1) {
        sucursalesSeleccionadas.value.push(id)
    } else {
        sucursalesSeleccionadas.value.splice(index, 1)
    }
}

const isSucursalSelected = (sucursal) => {
    return sucursalesSeleccionadas.value.includes(sucursal.id)
}

// Alternar selección de realizado por
const toggleRealizadoPor = (item) => {
    const id = item.id
    const index = realizadosPorSeleccionados.value.indexOf(id)
    if (index === -1) {
        realizadosPorSeleccionados.value.push(id)
    } else {
        realizadosPorSeleccionados.value.splice(index, 1)
    }
}

const isRealizadoPorSelected = (item) => {
    return realizadosPorSeleccionados.value.includes(item.id)
}

// Aplicar filtros
const aplicarFiltros = () => {
    const params = {}
    if (search.value && search.value.trim() !== '') params.search = search.value
    if (estado.value !== undefined && estado.value !== null && estado.value !== '') params.estado = estado.value
    if (sucursalesSeleccionadas.value.length > 0) params.sucursales = sucursalesSeleccionadas.value.join(',')
    if (realizadosPorSeleccionados.value.length > 0) params.realizados_por = realizadosPorSeleccionados.value.join(',')
    
    router.get('/gestion/inventario-fisico-mantenimiento', params, {
        preserveState: true,
        replace: true,
    })
}

// Limpiar filtros
const limpiarFiltros = () => {
    search.value = ''
    estado.value = ''
    sucursalesSeleccionadas.value = []
    realizadosPorSeleccionados.value = []
    
    router.get('/gestion/inventario-fisico-mantenimiento', {}, {
        preserveState: true,
        replace: true,
    })
}

// ==================== MODAL ====================
const abrirModal = (inventario) => {
    inventarioEditando.value = inventario
    editandoId.value = inventario.IdFisico
    editandoActivoInactivo.value = inventario.ActivoInactivo
    modalAbierto.value = true
}

const cerrarModal = () => {
    modalAbierto.value = false
    inventarioEditando.value = null
    editandoId.value = null
    editandoActivoInactivo.value = 0
}

const guardarEstado = async () => {
    modalLoading.value = true
    try {
        const response = await axios.put(`/gestion/inventario-fisico-mantenimiento/${editandoId.value}/estado`, {
            ActivoInactivo: editandoActivoInactivo.value
        })
        
        if (response.data.success) {
            // Actualizar la lista
            aplicarFiltros()
            cerrarModal()
        } else {
            alert(response.data.message || 'Error al actualizar')
        }
    } catch (error) {
        console.error('Error:', error)
        alert('Error al actualizar el estado')
    } finally {
        modalLoading.value = false
    }
}

// ==================== UTILIDADES ====================
const estadoTexto = (activo) => {
    return activo == 1 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-4 sm:py-6 px-3 sm:px-4 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-boxes text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">Mantenimiento de Inventarios Físicos</h1>
                            <p class="text-[10px] text-gray-500">Gestión de inventarios físicos realizados</p>
                        </div>
                    </div>
                </div>

                <!-- Layout Principal -->
                <div class="flex flex-row gap-4">
                    <!-- FILTROS LATERAL -->
                    <div class="w-64 flex-shrink-0">
                        <div class="bg-white rounded-lg shadow-sm p-3 sticky top-24">
                            <h3 class="text-xs font-semibold text-gray-800 mb-3 flex items-center gap-1">
                                <i class="fas fa-filter text-primary-600 text-[10px]"></i> Filtros
                            </h3>

                            <!-- Buscar por N° Correlativo -->
                            <div class="mb-3">
                                <label class="block text-[10px] font-medium text-gray-700 mb-1">N° Correlativo</label>
                                <div class="relative">
                                    <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[9px]"></i>
                                    <input 
                                        type="text" 
                                        v-model="search" 
                                        placeholder="Buscar por número..." 
                                        class="w-full border rounded-md pl-7 pr-2 py-1.5 text-[11px]"
                                        @keyup.enter="aplicarFiltros"
                                    >
                                </div>
                            </div>

                            <!-- Estado -->
                            <div class="mb-3">
                                <label class="block text-[10px] font-medium text-gray-700 mb-1">Estado</label>
                                <div class="flex flex-col gap-1">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="" v-model="estado" class="w-3 h-3 text-primary-600"> 
                                        <span class="text-[11px] text-gray-700">Todos</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="1" v-model="estado" class="w-3 h-3 text-primary-600"> 
                                        <span class="text-[11px] text-gray-700">Activos ({{ totalActivos }})</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="0" v-model="estado" class="w-3 h-3 text-primary-600"> 
                                        <span class="text-[11px] text-gray-700">Inactivos ({{ totalInactivos }})</span>
                                    </label>
                                </div>
                            </div>

                            <!-- LISTA DE SUCURSALES (Checkboxes) -->
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[10px] font-medium text-gray-700">Sucursales</label>
                                    <span v-if="sucursalesSeleccionadas.length > 0" class="text-[9px] text-primary-600 font-bold">
                                        {{ sucursalesSeleccionadas.length }} sel.
                                    </span>
                                </div>
                                <div class="max-h-48 overflow-y-auto border rounded-md bg-white">
                                    <div 
                                        v-for="suc in sucursales" 
                                        :key="suc.id" 
                                        class="flex items-center justify-between px-2 py-1.5 hover:bg-gray-50 border-b border-gray-100 last:border-b-0"
                                    >
                                        <label class="flex items-center gap-1.5 flex-1 min-w-0 cursor-pointer select-none py-0.5">
                                            <input 
                                                type="checkbox" 
                                                :checked="isSucursalSelected(suc)" 
                                                @change="toggleSucursal(suc)"
                                                class="w-3 h-3 rounded border-gray-300 text-primary-600 focus:ring-0 cursor-pointer"
                                            >
                                            <span class="text-[11px] text-gray-700 truncate">
                                                {{ suc.numero ? `${suc.numero} - ${suc.nombre}` : suc.nombre }}
                                            </span>
                                        </label>
                                        <span class="text-[9px] text-gray-400 pl-1 pr-1">
                                            ({{ suc.inventarios_count || 0 }})
                                        </span>
                                    </div>
                                    <div v-if="!sucursales || sucursales.length === 0" class="px-2 py-3 text-center text-gray-400 text-[10px]">
                                        No hay sucursales disponibles
                                    </div>
                                </div>
                            </div>

                            <!-- LISTA DE REALIZADO POR (Checkboxes) -->
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[10px] font-medium text-gray-700">Realizado por</label>
                                    <span v-if="realizadosPorSeleccionados.length > 0" class="text-[9px] text-primary-600 font-bold">
                                        {{ realizadosPorSeleccionados.length }} sel.
                                    </span>
                                </div>
                                <div class="max-h-48 overflow-y-auto border rounded-md bg-white">
                                    <div 
                                        v-for="item in realizadosPor" 
                                        :key="item.id" 
                                        class="flex items-center justify-between px-2 py-1.5 hover:bg-gray-50 border-b border-gray-100 last:border-b-0"
                                    >
                                        <label class="flex items-center gap-1.5 flex-1 min-w-0 cursor-pointer select-none py-0.5">
                                            <input 
                                                type="checkbox" 
                                                :checked="isRealizadoPorSelected(item)" 
                                                @change="toggleRealizadoPor(item)"
                                                class="w-3 h-3 rounded border-gray-300 text-primary-600 focus:ring-0 cursor-pointer"
                                            >
                                            <span class="text-[11px] text-gray-700 truncate">
                                                {{ item.display || item.Nombre }}
                                            </span>
                                        </label>
                                        <span class="text-[9px] text-gray-400 pl-1 pr-1">
                                            ({{ item.inventarios_count || 0 }})
                                        </span>
                                    </div>
                                    <div v-if="!realizadosPor || realizadosPor.length === 0" class="px-2 py-3 text-center text-gray-400 text-[10px]">
                                        No hay registros
                                    </div>
                                </div>
                            </div>

                            <!-- Botonera -->
                            <div class="flex gap-2 pt-2 border-t">
                                <button @click="aplicarFiltros" class="flex-1 px-2 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-[10px] transition flex items-center justify-center gap-1">
                                    <i class="fas fa-search text-[8px]"></i> Filtrar
                                </button>
                                <button @click="limpiarFiltros" class="px-2 py-1.5 border border-gray-300 rounded-md text-[10px] text-gray-700 hover:bg-gray-50 transition" title="Limpiar Filtros">
                                    <i class="fas fa-eraser text-[8px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TABLA DE INVENTARIOS -->
                    <div class="flex-1 min-w-0">
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-primary-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">N° Correlativo</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Fecha</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Sucursal</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Realizado por</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Encargado Sucursal</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold text-primary-700 uppercase">Estado</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold text-primary-700 uppercase">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="item in inventarios.data" :key="item.IdFisico" class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-[11px] text-gray-800 font-bold">{{ item.NumeroCorrelativo }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-600">{{ formatearFecha(item.fecha?.Fecha) || '-' }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-700">
                                                {{ item.sucursal?.numero ? `${item.sucursal.numero} - ` : '' }}{{ item.sucursal?.nombre || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-gray-700">
                                                {{ item.realizado_por?.CI_NIT }} - {{ item.realizado_por?.Nombre || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-gray-700">
                                                {{ item.encargado_sucursal?.CI_NIT }} - {{ item.encargado_sucursal?.Nombre || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="estadoClase(item.ActivoInactivo)">
                                                    {{ estadoTexto(item.ActivoInactivo) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button 
                                                    @click="abrirModal(item)" 
                                                    class="text-primary-600 hover:text-primary-800"
                                                    title="Editar estado"
                                                >
                                                    <i class="fas fa-edit text-sm"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="!inventarios.data || inventarios.data.length === 0">
                                            <td colspan="7" class="px-3 py-8 text-center text-gray-400 text-[11px]">
                                                <i class="fas fa-box-open text-xl mb-1 block"></i>
                                                No se encontraron inventarios físicos con los filtros seleccionados.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginación -->
                            <div v-if="inventarios.links && inventarios.links.length > 1" class="px-3 py-2 border-t border-gray-200">
                                <div class="flex justify-between items-center flex-wrap gap-2">
                                    <div class="text-[9px] text-gray-500">
                                        Mostrando {{ inventarios.from || 0 }} a {{ inventarios.to || 0 }} de {{ inventarios.total || 0 }}
                                    </div>
                                    <div class="flex gap-1 flex-wrap">
                                        <Link 
                                            v-for="link in inventarios.links" 
                                            :key="link.label" 
                                            :href="link.url || '#'" 
                                            class="px-2 py-0.5 rounded border text-[9px] transition"
                                            :class="{ 
                                                'bg-primary-600 text-white border-primary-600': link.active, 
                                                'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 
                                                'opacity-50 cursor-not-allowed': !link.url 
                                            }" 
                                            v-html="link.label" 
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL PARA EDITAR ESTADO -->
        <div v-if="modalAbierto" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Editar Estado</h3>
                    <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            N° Correlativo: <span class="font-bold">{{ inventarioEditando?.NumeroCorrelativo }}</span>
                        </label>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="editandoActivoInactivo" :value="1" class="w-4 h-4 text-primary-600">
                                <span class="text-sm text-gray-700">Activo</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="editandoActivoInactivo" :value="0" class="w-4 h-4 text-primary-600">
                                <span class="text-sm text-gray-700">Inactivo</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <button @click="cerrarModal" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">
                        Cancelar
                    </button>
                    <button @click="guardarEstado" :disabled="modalLoading" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                        <i v-if="modalLoading" class="fas fa-spinner fa-spin mr-1"></i>
                        {{ modalLoading ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:focus {
    --tw-ring-color: var(--color-primary-500);
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

.transition {
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>