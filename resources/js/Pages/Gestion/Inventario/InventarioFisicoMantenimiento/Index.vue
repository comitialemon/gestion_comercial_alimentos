<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, onMounted, watch, onUnmounted } from 'vue'
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

// ==================== RESPONSIVE ====================
const isMobile = ref(false)
const isTablet = ref(false)
const filtrosAbiertos = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 640
    isTablet.value = window.innerWidth >= 640 && window.innerWidth < 1024
    if (!isMobile.value && !isTablet.value) {
        filtrosAbiertos.value = true
    }
}

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
    handleResize()
    window.addEventListener('resize', handleResize)
    
    if (props.filtros?.sucursales) {
        sucursalesSeleccionadas.value = props.filtros.sucursales.split(',').map(Number)
    }
    if (props.filtros?.realizados_por) {
        realizadosPorSeleccionados.value = props.filtros.realizados_por.split(',').map(Number)
    }
    
    if (!isMobile.value && !isTablet.value) {
        filtrosAbiertos.value = true
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
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
    
    router.get('/gestion/inventario/inventario-fisico-mantenimiento', params, {
        preserveState: true,
        replace: true,
    })
    
    if (isMobile.value || isTablet.value) {
        filtrosAbiertos.value = false
    }
}

// Limpiar filtros
const limpiarFiltros = () => {
    search.value = ''
    estado.value = ''
    sucursalesSeleccionadas.value = []
    realizadosPorSeleccionados.value = []
    filtrosAbiertos.value = false
    
    router.get('/gestion/inventario/inventario-fisico-mantenimiento', {}, {
        preserveState: true,
        replace: true,
    })
}

// Toggle filtros en móvil
const toggleFiltros = () => {
    filtrosAbiertos.value = !filtrosAbiertos.value
}

// ==================== SWITCH / TOGGLE ====================
const toggleSwitch = (inventario) => {
    if (inventario.ActivoInactivo === 0) {
        mostrarToast('Este inventario ya está inactivo. Puede editarlo en el formulario de ingreso.', 'info')
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
        const response = await axios.put(`/gestion/inventario/inventario-fisico-mantenimiento/${modalData.value.id}/estado`, {
            ActivoInactivo: 0
        })
        
        if (response.data.success) {
            mostrarToast(response.data.message || 'Inventario desactivado correctamente. Redirigiendo a edición...', 'success')
            cerrarModal()
            
            // 🔥 REDIRIGIR AL FORMULARIO DE INGRESO PARA CONTINUAR EDITANDO
            setTimeout(() => {
                window.location.href = `/gestion/inventario/inventario-fisico`
            }, 1500)
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
        return 'Este inventario ya está inactivo. Edítelo en el formulario de ingreso.'
    }
    return 'Desactivar inventario físico para continuar editándolo'
}

// ==================== WATCH ====================
watch(estado, () => aplicarFiltros())
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3 sm:mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-boxes text-primary-600 text-[11px] sm:text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-sm sm:text-base font-bold text-gray-800 truncate">Mantenimiento de Inventarios Físicos</h1>
                            <p class="text-[8px] sm:text-[10px] text-gray-500 truncate">Gestión de inventarios físicos realizados</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button 
                            @click="toggleFiltros"
                            class="lg:hidden flex-1 sm:flex-none px-3 py-1.5 bg-white border rounded-lg text-xs flex items-center justify-center gap-1.5 transition"
                            :style="{ borderColor: `var(--color-primary-300)` }"
                        >
                            <i class="fas fa-sliders-h text-[10px]" :style="{ color: `var(--color-primary-600)` }"></i>
                            <span class="text-gray-700 text-[10px] sm:text-xs">{{ filtrosAbiertos ? 'Ocultar' : 'Filtros' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Filtros rápidos -->
                <div class="bg-white rounded-lg sm:rounded-xl shadow-sm p-2.5 sm:p-3 mb-3 sm:mb-4">
                    <div class="flex flex-col xs:flex-row flex-wrap items-center gap-2 sm:gap-3">
                        <div class="flex items-center gap-2 w-full xs:w-auto">
                            <label class="text-[9px] sm:text-xs font-medium text-gray-700 whitespace-nowrap">Estado:</label>
                            <select v-model="estado" class="border border-gray-200 rounded-lg px-2 py-1 text-[10px] sm:text-xs w-full xs:w-36 sm:w-40 focus:border-primary-400 focus:ring-1 focus:ring-primary-200">
                                <option value="">Todos</option>
                                <option value="1">Activos ({{ totalActivos }})</option>
                                <option value="0">Inactivos ({{ totalInactivos }})</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center gap-1 w-full xs:w-auto">
                            <input 
                                type="text" 
                                v-model="search" 
                                @input="buscarInventarios"
                                placeholder="N° Correlativo..."
                                class="flex-1 xs:flex-none border border-gray-200 rounded-lg px-2 py-1 text-[10px] sm:text-xs w-full xs:w-28 sm:w-32 focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                            >
                            <button v-if="search" @click="search = ''; aplicarFiltros()" class="text-gray-400 hover:text-gray-600 text-[10px] sm:text-xs">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="text-[8px] sm:text-[10px] text-gray-400 xs:ml-auto">
                            <i class="fas fa-info-circle"></i> Desactivar para continuar editando
                        </div>
                    </div>
                    <div v-if="search" class="mt-1.5 text-[8px] sm:text-[10px] text-gray-500">
                        <span class="font-semibold">{{ search }}</span>
                        <span class="ml-2">({{ inventarios.total || 0 }} resultados)</span>
                    </div>
                </div>

                <!-- Layout Principal -->
                <div class="flex flex-col lg:flex-row gap-3 sm:gap-4">
                    
                    <!-- FILTROS LATERAL - Colapsable -->
                    <div 
                        class="lg:w-64 flex-shrink-0 transition-all duration-300 overflow-hidden"
                        :class="{
                            'max-h-[600px] opacity-100': filtrosAbiertos || !isMobile && !isTablet,
                            'max-h-0 opacity-0 lg:max-h-full lg:opacity-100': !filtrosAbiertos && (isMobile || isTablet)
                        }"
                    >
                        <div class="bg-white rounded-lg shadow-sm p-3 sticky top-2 lg:top-24">
                            <h3 class="text-xs font-semibold text-gray-800 mb-3 flex items-center gap-1">
                                <i class="fas fa-filter text-[10px]" :style="{ color: `var(--color-primary-600)` }"></i> Filtros
                            </h3>

                            <!-- LISTA DE SUCURSALES -->
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[10px] font-medium text-gray-700">Sucursales</label>
                                    <span v-if="sucursalesSeleccionadas.length > 0" class="text-[9px] text-primary-600 font-bold">
                                        {{ sucursalesSeleccionadas.length }} sel.
                                    </span>
                                </div>
                                <div class="max-h-48 overflow-y-auto border rounded-md bg-white"
                                     :style="{ borderColor: `var(--color-primary-200)` }">
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
                                                class="w-3 h-3 rounded border-gray-300 cursor-pointer flex-shrink-0"
                                                :style="{ accentColor: `var(--color-primary-600)` }"
                                            >
                                            <span class="text-[10px] sm:text-[11px] text-gray-700 truncate">
                                                {{ suc.numero ? `${suc.numero} - ${suc.nombre}` : suc.nombre }}
                                            </span>
                                        </label>
                                        <span class="text-[8px] sm:text-[9px] text-gray-400 pl-1 pr-1 flex-shrink-0">
                                            ({{ suc.inventarios_count || 0 }})
                                        </span>
                                    </div>
                                    <div v-if="!sucursales || sucursales.length === 0" class="px-2 py-3 text-center text-gray-400 text-[10px]">
                                        No hay sucursales disponibles
                                    </div>
                                </div>
                            </div>

                            <!-- LISTA DE REALIZADO POR -->
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[10px] font-medium text-gray-700">Realizado por</label>
                                    <span v-if="realizadosPorSeleccionados.length > 0" class="text-[9px] text-primary-600 font-bold">
                                        {{ realizadosPorSeleccionados.length }} sel.
                                    </span>
                                </div>
                                <div class="max-h-48 overflow-y-auto border rounded-md bg-white"
                                     :style="{ borderColor: `var(--color-primary-200)` }">
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
                                                class="w-3 h-3 rounded border-gray-300 cursor-pointer flex-shrink-0"
                                                :style="{ accentColor: `var(--color-primary-600)` }"
                                            >
                                            <span class="text-[10px] sm:text-[11px] text-gray-700 truncate">
                                                {{ item.display || item.Nombre }}
                                            </span>
                                        </label>
                                        <span class="text-[8px] sm:text-[9px] text-gray-400 pl-1 pr-1 flex-shrink-0">
                                            ({{ item.inventarios_count || 0 }})
                                        </span>
                                    </div>
                                    <div v-if="!realizadosPor || realizadosPor.length === 0" class="px-2 py-3 text-center text-gray-400 text-[10px]">
                                        No hay registros
                                    </div>
                                </div>
                            </div>

                            <!-- Botonera -->
                            <div class="flex gap-2 pt-2 border-t" :style="{ borderColor: `var(--color-primary-200)` }">
                                <button @click="aplicarFiltros" class="flex-1 px-2 py-1.5 text-white rounded-md text-[10px] transition flex items-center justify-center gap-1"
                                    :style="{ backgroundColor: `var(--color-primary-600)` }">
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
                            
                            <!-- Indicador de filtros activos (móvil/tablet) -->
                            <div class="p-2 border-b flex flex-wrap gap-1 lg:hidden"
                                :style="{ borderColor: `var(--color-primary-200)` }">
                                <span v-if="search" class="px-1.5 py-0.5 bg-primary-50 rounded text-[8px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-search text-[7px]"></i> {{ search }}
                                </span>
                                <span v-if="estado !== ''" class="px-1.5 py-0.5 bg-primary-50 rounded text-[8px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-circle text-[5px]" :class="estado == '1' ? 'text-green-500' : 'text-amber-500'"></i>
                                    {{ estado == '1' ? 'Activos' : 'Inactivos' }}
                                </span>
                                <span v-if="sucursalesSeleccionadas.length > 0" class="px-1.5 py-0.5 bg-primary-50 rounded text-[8px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-store text-[7px]"></i> {{ sucursalesSeleccionadas.length }} suc.
                                </span>
                                <span v-if="realizadosPorSeleccionados.length > 0" class="px-1.5 py-0.5 bg-primary-50 rounded text-[8px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-user text-[7px]"></i> {{ realizadosPorSeleccionados.length }} per.
                                </span>
                            </div>

                            <!-- Desktop: Tabla -->
                            <div class="hidden md:block overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-primary-50" :style="{ backgroundColor: `var(--color-primary-50)` }">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">N° Correlativo</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Fecha</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Sucursal</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Realizado por</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Encargado</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Estado</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Cambiar</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">PDF</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="item in inventarios.data" :key="item.IdFisico" class="hover:bg-gray-50 transition">
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
                                            <td class="px-3 py-2 text-right">
                                                <a :href="`/gestion/inventario/inventario-fisico/${item.IdFisico}/pdf`" target="_blank" class="text-red-600 hover:text-red-700 transition" title="Ver PDF">
                                                    <i class="fas fa-file-pdf text-sm"></i>
                                                </a>
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

                            <!-- Tablet: Tabla simplificada -->
                            <div class="hidden sm:block md:hidden overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-primary-50" :style="{ backgroundColor: `var(--color-primary-50)` }">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">N°</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Sucursal</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Realizado por</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Estado</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Cambiar</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">PDF</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="item in inventarios.data" :key="item.IdFisico" class="hover:bg-gray-50 transition">
                                            <td class="px-3 py-2 text-[11px] text-gray-800 font-bold">{{ item.NumeroCorrelativo }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-700">
                                                {{ item.sucursal?.numero ? `${item.sucursal.numero} - ` : '' }}{{ item.sucursal?.nombre || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-gray-700">
                                                {{ item.realizado_por?.CI_NIT }} - {{ item.realizado_por?.Nombre || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="estadoClase(item.ActivoInactivo)">
                                                    {{ estadoTexto(item.ActivoInactivo) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <div 
                                                    class="relative inline-flex items-center cursor-pointer" 
                                                    :class="{ 'cursor-not-allowed opacity-50': isSwitchDisabled(item) }"
                                                    :title="getSwitchTitle(item)"
                                                    @click="!isSwitchDisabled(item) && toggleSwitch(item)"
                                                >
                                                    <div class="w-8 h-4 rounded-full transition-colors duration-200 ease-in-out"
                                                        :class="item.ActivoInactivo === 1 ? 'bg-primary-600' : 'bg-gray-300'">
                                                        <div class="absolute w-3 h-3 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out"
                                                            :class="item.ActivoInactivo === 1 ? 'translate-x-[17px]' : 'translate-x-[2px]'">
                                                        </div>
                                                    </div>
                                                    <span class="ml-1.5 text-[9px]" :class="cambiando[item.IdFisico] ? 'text-gray-400' : (item.ActivoInactivo === 1 ? 'text-green-600' : 'text-gray-500')">
                                                        <i v-if="cambiando[item.IdFisico]" class="fas fa-spinner fa-spin"></i>
                                                        <span v-else>{{ item.ActivoInactivo === 1 ? 'Activo' : 'Inactivo' }}</span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <a :href="`/gestion/inventario/inventario-fisico/${item.IdFisico}/pdf`" target="_blank" class="text-red-600 hover:text-red-700 transition" title="Ver PDF">
                                                    <i class="fas fa-file-pdf text-sm"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <tr v-if="!inventarios.data || inventarios.data.length === 0">
                                            <td colspan="6" class="px-3 py-8 text-center text-gray-400 text-[11px]">
                                                <i class="fas fa-box-open text-xl mb-1 block"></i>
                                                No se encontraron inventarios
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile: Tarjetas -->
                            <div class="sm:hidden divide-y divide-gray-100">
                                <div v-for="item in inventarios.data" :key="item.IdFisico" class="p-3 hover:bg-gray-50 transition">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-xs font-bold text-gray-800">#{{ item.NumeroCorrelativo }}</span>
                                                <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(item.ActivoInactivo)">
                                                    {{ estadoTexto(item.ActivoInactivo) }}
                                                </span>
                                            </div>
                                            <div class="text-[10px] text-gray-500 mt-0.5">
                                                {{ formatearFecha(item.fecha?.Fecha) || '-' }}
                                            </div>
                                            <div class="text-[10px] text-gray-700 truncate mt-0.5">
                                                <i class="fas fa-store text-primary-400 text-[8px] mr-1"></i>
                                                {{ item.sucursal?.nombre || '-' }}
                                            </div>
                                            <div class="text-[10px] text-gray-600 truncate">
                                                <i class="fas fa-user text-primary-400 text-[8px] mr-1"></i>
                                                {{ item.realizado_por?.Nombre || '-' }}
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-end gap-2 flex-shrink-0">
                                            <a :href="`/gestion/inventario/inventario-fisico/${item.IdFisico}/pdf`" target="_blank" class="text-red-600 hover:text-red-700 transition" title="Ver PDF">
                                                <i class="fas fa-file-pdf text-base"></i>
                                            </a>
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
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="!inventarios.data || inventarios.data.length === 0" class="p-6 text-center text-gray-400 text-sm">
                                    <i class="fas fa-box-open text-2xl mb-2 block"></i>
                                    No se encontraron inventarios
                                </div>
                            </div>

                            <!-- Paginación -->
                            <div v-if="inventarios.links && inventarios.links.length > 1" class="px-2 sm:px-3 py-2 border-t border-gray-200 bg-gray-50">
                                <div class="flex flex-col xs:flex-row justify-between items-center gap-2 text-[8px] sm:text-[10px]">
                                    <div class="text-gray-500 text-[8px] sm:text-[10px]">
                                        Mostrando {{ inventarios.from || 0 }} - {{ inventarios.to || 0 }} de {{ inventarios.total || 0 }}
                                    </div>
                                    <div class="flex gap-0.5 flex-wrap justify-center">
                                        <Link 
                                            v-for="link in inventarios.links" 
                                            :key="link.label" 
                                            :href="link.url || '#'" 
                                            class="px-1.5 sm:px-2 py-0.5 rounded border text-[8px] sm:text-[10px] transition min-w-[22px] text-center"
                                            :style="{
                                                borderColor: link.active ? `var(--color-primary-600)` : '#e5e7eb',
                                                backgroundColor: link.active ? `var(--color-primary-600)` : 'white',
                                                color: link.active ? 'white' : '#374151'
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

        <!-- Modal de confirmación -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3 sm:p-4" @click.self="cerrarModal">
            <div class="bg-white rounded-xl w-full max-w-[95%] sm:max-w-sm overflow-hidden shadow-xl mx-2 sm:mx-0">
                <div class="p-3 sm:p-4 border-b bg-amber-50">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-amber-100">
                            <i class="fas fa-ban text-amber-600 text-base sm:text-xl"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-gray-800 text-[11px] sm:text-sm">Desactivar Inventario</h3>
                            <p class="text-[9px] sm:text-xs text-gray-500 truncate">N° {{ modalData.numero }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-3 sm:p-4">
                    <p class="text-[10px] sm:text-sm text-gray-700 text-center">
                        ¿Estás seguro de <span class="font-bold text-red-600">DESACTIVAR</span> este inventario físico?
                    </p>
                    <p class="text-[8px] sm:text-[10px] text-gray-400 text-center mt-1.5 sm:mt-2">
                        Al desactivarlo, podrás continuar editándolo en el formulario de ingreso.
                        Luego podrás contabilizarlo nuevamente.
                    </p>
                </div>
                <div class="p-2.5 sm:p-4 bg-gray-50 flex flex-col xs:flex-row justify-end gap-2">
                    <button @click="cerrarModal" class="flex-1 xs:flex-none px-3 sm:px-4 py-1.5 border border-gray-300 rounded-lg text-[10px] sm:text-xs text-gray-700 hover:bg-gray-100 transition">Cancelar</button>
                    <button @click="ejecutarCambioEstado" :disabled="loading" class="flex-1 xs:flex-none px-3 sm:px-4 py-1.5 rounded-lg text-[10px] sm:text-xs text-white transition flex items-center justify-center gap-2 bg-amber-600 hover:bg-amber-700">
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
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}

input:focus {
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

.max-h-48::-webkit-scrollbar {
    width: 4px;
}

.max-h-48::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.max-h-48::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.max-h-48::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

@media (max-width: 1023px) {
    .max-h-0 {
        max-height: 0;
    }
    .max-h-\[600px\] {
        max-height: 600px;
    }
}

@media (max-width: 380px) {
    .xs\:flex-row {
        flex-direction: column !important;
    }
}
</style>