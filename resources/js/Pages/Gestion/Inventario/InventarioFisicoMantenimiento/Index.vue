<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue'
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

// Estado para el switch
const cambiando = ref({})
const loading = ref(false)

// Modal de confirmación para desactivar
const modalVisible = ref(false)
const modalData = ref({ id: null, numero: null })

// Procesar filtros iniciales
onMounted(() => {
    if (props.filtros?.sucursales) {
        sucursalesSeleccionadas.value = props.filtros.sucursales.split(',').map(Number)
    }
    if (props.filtros?.realizados_por) {
        realizadosPorSeleccionados.value = props.filtros.realizados_por.split(',').map(Number)
    }
})

// Debounce para búsqueda
let timeoutBuscador
const buscarInventarios = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => aplicarFiltros(), 500)
}

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

// ==================== SWITCH / TOGGLE ====================
const toggleSwitch = (inventario) => {
    if (inventario.ActivoInactivo === 0) {
        mostrarToast('Este inventario ya está inactivo. Para activarlo, edite el registro.', 'info')
        return
    }
    
    if (cambiando.value[inventario.IdFisico]) return
    abrirModalConfirmacion(inventario)
}

const abrirModalConfirmacion = (inventario) => {
    modalData.value = {
        id: inventario.IdFisico,
        numero: inventario.NumeroCorrelativo
    }
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    modalData.value = { id: null, numero: null }
}

// ==================== EJECUTAR CAMBIO DE ESTADO ====================
const ejecutarCambioEstado = async () => {
    if (!modalData.value.id) return
    
    cambiando.value[modalData.value.id] = true
    loading.value = true
    
    try {
        const response = await axios.put(`/gestion/inventario-fisico-mantenimiento/${modalData.value.id}/estado`, {
            ActivoInactivo: 0
        })
        
        if (response.data.success) {
            mostrarToast(response.data.message || 'Inventario desactivado correctamente', 'success')
            const params = new URLSearchParams()
            if (estado.value) params.append('estado', estado.value)
            if (search.value) params.append('search', search.value)
            if (sucursalesSeleccionadas.value.length > 0) params.append('sucursales', sucursalesSeleccionadas.value.join(','))
            if (realizadosPorSeleccionados.value.length > 0) params.append('realizados_por', realizadosPorSeleccionados.value.join(','))
            window.location.href = `/gestion/inventario-fisico-mantenimiento?${params.toString()}`
        } else {
            mostrarToast(response.data.message || 'Error al desactivar', 'error')
            cerrarModal()
        }
    } catch (error) {
        console.error('Error:', error)
        mostrarToast('Error al cambiar el estado', 'error')
        cerrarModal()
    } finally {
        cambiando.value[modalData.value.id] = false
        loading.value = false
    }
}

// ==================== TOAST ====================
const mostrarToast = (mensaje, tipo = 'success') => {
    const toastAnterior = document.querySelector('.custom-toast')
    if (toastAnterior) toastAnterior.remove()
    
    let bgColor = 'bg-green-500'
    let icon = 'fa-check-circle'
    
    if (tipo === 'error') {
        bgColor = 'bg-red-500'
        icon = 'fa-exclamation-circle'
    } else if (tipo === 'info') {
        bgColor = 'bg-blue-500'
        icon = 'fa-info-circle'
    }
    
    const toast = document.createElement('div')
    toast.className = `custom-toast fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white flex items-center gap-2 ${bgColor}`
    toast.innerHTML = `<i class="fas ${icon}"></i> ${mensaje}`
    document.body.appendChild(toast)
    setTimeout(() => toast.remove(), 4000)
}

// ==================== UTILIDADES ====================
const estadoTexto = (activo) => {
    return activo == 1 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo == 1 ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

// ==================== CONDICIONES PARA EL SWITCH ====================
const isSwitchDisabled = (inventario) => {
    return inventario.ActivoInactivo === 0
}

const getSwitchTitle = (inventario) => {
    if (inventario.ActivoInactivo === 0) {
        return 'No se puede activar desde aquí. Edite el inventario para volver a activarlo.'
    }
    return 'Desactivar inventario físico'
}

// ==================== WATCH ====================
watch(estado, () => aplicarFiltros())
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

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estado" class="border border-gray-200 rounded-lg px-2 py-1 text-xs w-36 sm:w-40 focus:border-primary-400 focus:ring-1 focus:ring-primary-200">
                                <option value="">Todos</option>
                                <option value="1">Activos ({{ totalActivos }})</option>
                                <option value="0">Inactivos ({{ totalInactivos }})</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="search" 
                                @input="buscarInventarios"
                                placeholder="N° Correlativo..."
                                class="border border-gray-200 rounded-lg px-2 py-1 text-xs w-28 sm:w-32 focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                            >
                            <button v-if="search" @click="search = ''; aplicarFiltros()" class="text-gray-400 hover:text-gray-600 text-xs">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div v-if="search" class="mt-2 text-[10px] text-gray-500">
                        <span class="font-semibold">{{ search }}</span>
                        <span class="ml-2">({{ inventarios.total || 0 }} resultados)</span>
                    </div>
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> Solo se pueden desactivar inventarios. Para activar, edite el registro.
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
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold text-primary-700 uppercase">Cambiar</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold text-primary-700 uppercase">PDF</th>
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
                                                <!-- Switch -->
                                                <div 
                                                    class="relative inline-flex items-center cursor-pointer" 
                                                    :class="{ 'cursor-not-allowed opacity-50': isSwitchDisabled(item) }"
                                                    :title="getSwitchTitle(item)"
                                                    @click="!isSwitchDisabled(item) && toggleSwitch(item)"
                                                >
                                                    <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out"
                                                        :class="item.ActivoInactivo === 1 ? 'bg-primary-600' : 'bg-gray-300'">
                                                        <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out"
                                                            :class="item.ActivoInactivo === 1 ? 'translate-x-[18px]' : 'translate-x-[2px]'">
                                                        </div>
                                                    </div>
                                                    <span class="ml-2 text-[10px]" :class="cambiando[item.IdFisico] ? 'text-gray-400' : (item.ActivoInactivo === 1 ? 'text-green-600' : 'text-gray-500')">
                                                        <i v-if="cambiando[item.IdFisico]" class="fas fa-spinner fa-spin"></i>
                                                        <span v-else>{{ item.ActivoInactivo === 1 ? 'Activo' : 'Inactivo' }}</span>
                                                    </span>
                                                </div>
                                            </td>
                                            <!-- 🔥 COLUMNA PDF -->
                                            <td class="px-3 py-2 text-right">
                                                <a :href="`/gestion/inventario-fisico/${item.IdFisico}/pdf`" target="_blank" class="text-red-600 hover:text-red-700 transition" title="Ver PDF">
                                                    <i class="fas fa-file-pdf text-sm"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <tr v-if="inventarios.data?.some(c => c.ActivoInactivo === 0)" class="bg-blue-50">
                                            <td colspan="8" class="px-3 py-2 text-center text-[10px] text-blue-600">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Los inventarios inactivos no se pueden activar desde aquí. Para activarlos, debe editarlos y guardar nuevamente.
                                            </td>
                                        </tr>
                                        <tr v-if="!inventarios.data || inventarios.data.length === 0">
                                            <td colspan="8" class="px-3 py-8 text-center text-gray-400 text-[11px]">
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

        <!-- Modal de confirmación (solo para desactivar) -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarModal">
            <div class="bg-white rounded-xl w-full max-w-[90%] sm:max-w-sm overflow-hidden shadow-xl">
                <div class="p-4 border-b bg-amber-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-amber-100">
                            <i class="fas fa-ban text-amber-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 text-sm sm:text-base">Desactivar Inventario</h3>
                            <p class="text-[10px] sm:text-xs text-gray-500">N° {{ modalData.numero }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-700 text-center">
                        ¿Estás seguro de <span class="font-bold text-red-600">DESACTIVAR</span> este inventario físico?
                    </p>
                    <p class="text-[10px] sm:text-xs text-gray-400 text-center mt-2">
                        Al desactivarlo, el inventario volverá a estado borrador y podrá editarse.
                        Para volver a activarlo, deberá editarlo y guardar nuevamente.
                    </p>
                </div>
                <div class="p-3 sm:p-4 bg-gray-50 flex justify-end gap-2 sm:gap-3">
                    <button @click="cerrarModal" class="px-3 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition">Cancelar</button>
                    <button @click="ejecutarCambioEstado" :disabled="loading" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition flex items-center gap-2 bg-amber-600 hover:bg-amber-700">
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-ban"></i>
                        Desactivar
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