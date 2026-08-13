<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted, computed, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    inventarios: Object,
    sucursales: Array,
    sucursalActual: Number,
    filtroEstado: String,
    buscar: String,
    sucursalSeleccionada: String
})

// =============================================
// ESTADO DE FILTROS
// =============================================

const sucursalId = ref(props.sucursalSeleccionada || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')

// =============================================
// CONSTRUIR URL CON FILTROS PARA PAGINACIÓN
// =============================================
const construirUrlConFiltros = (url) => {
    if (!url) return '#'
    
    try {
        const urlObj = new URL(url, window.location.origin)
        const params = new URLSearchParams(urlObj.search)
        
        if (sucursalId.value) {
            params.set('sucursal_id', sucursalId.value)
        }
        
        if (estadoFiltro.value) {
            params.set('estado', estadoFiltro.value)
        }
        
        if (buscador.value) {
            params.set('buscar', buscador.value)
        }
        
        urlObj.search = params.toString()
        return urlObj.toString()
    } catch (error) {
        console.error('Error construyendo URL:', error)
        return url
    }
}

// =============================================
// COMPUTADOS - Autocomplete
// =============================================

const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre?.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id == sucursalId.value)
    return suc?.nombre || ''
})

// =============================================
// ESTADO PARA CONTRAER/EXPANDIR
// =============================================
const sucursalesExpandidas = ref({})

const inicializarExpandidas = () => {
    const grupos = inventariosAgrupados.value
    Object.keys(grupos).forEach(id => {
        sucursalesExpandidas.value[id] = true
    })
}

const toggleSucursal = (id) => {
    if (sucursalesExpandidas.value[id] !== undefined) {
        sucursalesExpandidas.value[id] = !sucursalesExpandidas.value[id]
    }
}

const expandirTodas = () => {
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        sucursalesExpandidas.value[id] = true
    })
}

const contraerTodas = () => {
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        sucursalesExpandidas.value[id] = false
    })
}

// =============================================
// AGRUPACIÓN POR SUCURSAL
// =============================================
const inventariosAgrupados = computed(() => {
    if (!props.inventarios?.data) return {}
    
    const grupos = {}
    
    props.inventarios.data.forEach(item => {
        const sucursalNombre = item.sucursal_nombre || 'Sin sucursal'
        const sucursalId = item.IdSucursal || 0
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                items: [],
                total_productos: 0,
                total_contados: 0
            }
        }
        
        grupos[sucursalId].items.push(item)
        grupos[sucursalId].total_productos += item.CantidadTotalProductos || 0
        grupos[sucursalId].total_contados += item.CantidadContados || 0
    })
    
    return grupos
})

const sucursalesConInventarios = computed(() => {
    return Object.values(inventariosAgrupados.value)
})

const actualizarExpandidas = () => {
    const grupos = inventariosAgrupados.value
    const idsActuales = Object.keys(grupos)
    
    idsActuales.forEach(id => {
        if (sucursalesExpandidas.value[id] === undefined) {
            sucursalesExpandidas.value[id] = true
        }
    })
    
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        if (!idsActuales.includes(id)) {
            delete sucursalesExpandidas.value[id]
        }
    })
}

// =============================================
// ACCIONES
// =============================================
const aplicarFiltros = () => {
    const params = {
        sucursal_id: sucursalId.value || undefined,
        estado: estadoFiltro.value || undefined,
        buscar: buscador.value || undefined
    }
    
    router.get('/gestion/inventario/inventario-fisico-diario/admin', params, {
        preserveState: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                actualizarExpandidas()
            }, 100)
        }
    })
}

const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    aplicarFiltros()
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    aplicarFiltros()
}

let timeoutBuscador
const buscarInventarios = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

const handleClickOutside = (event) => {
    const container = document.querySelector('.sucursal-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarSucursales.value = false
    }
}

// =============================================
// MODAL DE DETALLE
// =============================================
const mostrarModal = ref(false)
const inventarioSeleccionado = ref(null)
const loadingDetalle = ref(false)

const verDetalle = async (id) => {
    loadingDetalle.value = true
    mostrarModal.value = true
    
    try {
        const response = await axios.get(`/gestion/inventario/inventario-fisico-diario/obtener-por-id/${id}`)
        
        if (response.data.success) {
            inventarioSeleccionado.value = response.data.data
        } else {
            inventarioSeleccionado.value = null
        }
    } catch (error) {
        console.error('Error al obtener detalle:', error)
        inventarioSeleccionado.value = null
    } finally {
        loadingDetalle.value = false
    }
}

const cerrarModal = () => {
    mostrarModal.value = false
    inventarioSeleccionado.value = null
}

// =============================================
// UTILIDADES
// =============================================
const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

const getEstadoColor = (activo) => {
    if (activo === 1) return 'bg-green-100 text-green-800'
    if (activo === 2) return 'bg-red-100 text-red-800'
    return 'bg-yellow-100 text-yellow-800'
}

const getEstadoIcono = (activo) => {
    if (activo === 1) return 'fas fa-check-circle'
    if (activo === 2) return 'fas fa-times-circle'
    return 'fas fa-pencil-alt'
}

const getEstadoTexto = (activo) => {
    if (activo === 1) return 'Completado'
    if (activo === 2) return 'Anulado'
    return 'Borrador'
}

const reimprimirPDF = (id) => {
    window.open(`/gestion/inventario/inventario-fisico-diario/pdf/${id}`, '_blank')
}

// =============================================
// ESTADO
// =============================================
const loading = ref(false)
const isMobile = ref(window.innerWidth < 768)

// =============================================
// CICLO DE VIDA
// =============================================
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
    
    if (sucursalId.value) {
        const sucursal = props.sucursales?.find(s => s.id == sucursalId.value)
        if (sucursal) {
            sucursalBusqueda.value = sucursal.nombre
        }
        aplicarFiltros()
    }
    
    setTimeout(() => {
        inicializarExpandidas()
    }, 100)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Inventario Físico Diario</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">Historial de inventarios físicos realizados</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/gestion/inventario/inventario-fisico-diario/config" class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-cog text-[10px]"></i>
                            <span>Configuración</span>
                        </Link>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        
                        <!-- Sucursal - Autocomplete -->
                        <div class="sucursal-autocomplete flex items-center gap-1">
                            <label class="text-xs font-medium text-gray-700">Sucursal:</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="sucursalBusqueda"
                                    @focus="mostrarSucursales = true"
                                    @input="mostrarSucursales = true"
                                    class="border border-gray-300 rounded-md px-2 py-1 text-xs w-36 sm:w-44 pr-6 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="Seleccione Sucursal..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="sucursalBusqueda"
                                    @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                
                                <!-- Lista de sucursales -->
                                <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto min-w-[180px]">
                                    <div 
                                        v-for="suc in sucursalesDisponibles" 
                                        :key="suc.id"
                                        @click="seleccionarSucursal(suc)"
                                        class="px-3 py-1.5 cursor-pointer hover:bg-primary-50 text-xs flex justify-between items-center border-b border-gray-100 last:border-0"
                                        :class="sucursalId == suc.id ? 'bg-primary-50' : ''"
                                    >
                                        <span class="truncate">{{ suc.nombre }}</span>
                                        <span v-if="sucursalId == suc.id" class="text-primary-600">
                                            <i class="fas fa-check-circle text-[10px]"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div v-else-if="mostrarSucursales && sucursalesDisponibles.length === 0 && sucursalBusqueda" 
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-2 text-center text-gray-500 text-xs">
                                    <i class="fas fa-search mr-1"></i> No se encontraron sucursales
                                </div>
                            </div>
                            <span v-if="sucursalId && sucursalNombre" class="text-[10px] text-primary-600 font-medium ml-1">
                                <i class="fas fa-check-circle"></i> {{ sucursalNombre }}
                            </span>
                            <span v-else class="text-[10px] text-gray-400 ml-1">
                                <i class="fas fa-store"></i> Ninguna
                            </span>
                        </div>
                        
                        <!-- Estado -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" @change="aplicarFiltros" class="border border-gray-300 rounded-md px-2 py-1 text-xs w-32 sm:w-36 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Todos</option>
                                <option value="completados">Completados</option>
                                <option value="borradores">Borradores</option>
                                <option value="anulados">Anulados</option>
                            </select>
                        </div>
                        
                        <!-- Buscador -->
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarInventarios"
                                placeholder="N° Correlativo..."
                                class="border border-gray-300 rounded-md px-2 py-1 text-xs w-28 sm:w-32 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                            <button 
                                v-if="buscador" 
                                @click="limpiarBusqueda" 
                                class="text-gray-400 hover:text-gray-600 text-xs"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <!-- Botones Expandir/Contraer -->
                        <div class="flex gap-1 ml-auto">
                            <button @click="expandirTodas" class="text-[10px] bg-primary-100 hover:bg-primary-200 text-primary-700 px-2 py-1 rounded transition">
                                <i class="fas fa-plus-circle"></i> Expandir
                            </button>
                            <button @click="contraerTodas" class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-1 rounded transition">
                                <i class="fas fa-minus-circle"></i> Contraer
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="buscador" class="mt-2 text-[10px] text-gray-500">
                        <span class="font-semibold">{{ buscador }}</span>
                        <span class="ml-2">({{ inventarios?.total || 0 }} resultados)</span>
                    </div>
                    
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> 
                        <span class="text-green-600">● Completado</span> | 
                        <span class="text-yellow-600">● Borrador</span> | 
                        <span class="text-red-600">● Anulado</span>
                    </div>
                </div>

                <!-- MENSAJE: SIN SUCURSAL SELECCIONADA -->
                <div v-if="!sucursalId" class="bg-white rounded-lg shadow-sm p-8 sm:p-12 text-center">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-store text-primary-400 text-3xl sm:text-4xl"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-700">Seleccione una Sucursal</h3>
                    <p class="text-xs sm:text-sm text-gray-400 mt-2 max-w-sm mx-auto">
                        Use el campo de búsqueda de sucursales para visualizar los inventarios de una sucursal específica.
                    </p>
                </div>

                <!-- GRID AGRUPADA POR SUCURSAL -->
                <div v-else-if="sucursalesConInventarios.length > 0">
                    <div v-for="grupo in sucursalesConInventarios" :key="grupo.id" class="mb-3">
                        
                        <!-- Encabezado de Sucursal -->
                        <div 
                            @click="toggleSucursal(grupo.id)"
                            class="flex flex-wrap items-center justify-between gap-2 bg-primary-50 rounded-t-lg px-3 sm:px-4 py-2 border border-primary-200 cursor-pointer hover:bg-primary-100 transition"
                        >
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <i 
                                    class="text-primary-600 text-[10px] sm:text-sm transition-transform duration-200 flex-shrink-0"
                                    :class="sucursalesExpandidas[grupo.id] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                ></i>
                                <i class="fas fa-store text-primary-600 text-xs sm:text-sm flex-shrink-0"></i>
                                <h2 class="font-bold text-primary-800 text-xs sm:text-sm truncate">{{ grupo.nombre }}</h2>
                                <span class="text-[9px] sm:text-xs text-primary-600 bg-primary-100 px-1.5 sm:px-2 py-0.5 rounded-full flex-shrink-0">
                                    {{ grupo.items.length }}
                                </span>
                            </div>
                            <div class="text-xs sm:text-sm font-bold text-primary-700 flex-shrink-0">
                                {{ grupo.total_contados }} / {{ grupo.total_productos }} contados
                            </div>
                        </div>

                        <!-- Tabla de inventarios -->
                        <transition 
                            enter-active-class="transition-all duration-300 ease-in-out"
                            enter-from-class="max-h-0 opacity-0 overflow-hidden"
                            enter-to-class="max-h-[5000px] opacity-100 overflow-hidden"
                            leave-active-class="transition-all duration-300 ease-in-out"
                            leave-from-class="max-h-[5000px] opacity-100 overflow-hidden"
                            leave-to-class="max-h-0 opacity-0 overflow-hidden"
                        >
                            <div v-if="sucursalesExpandidas[grupo.id]" class="bg-white rounded-b-lg shadow-sm overflow-hidden border border-t-0 border-primary-200">
                                
                                <!-- DESKTOP: Tabla -->
                                <div class="hidden md:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">N°</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Operador</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Productos</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Contados</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="item in grupo.items" :key="item.IdFisicoDiario" class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-xs font-mono font-bold text-primary-600">
                                                    {{ item.NumeroCorrelativo || '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-xs text-gray-500">{{ item.fecha_formateada || '-' }}</td>
                                                <td class="px-3 py-2 text-xs text-gray-700 max-w-[150px] truncate" :title="item.nombre_operador">
                                                    {{ item.nombre_operador || 'N/A' }}
                                                </td>
                                                <td class="px-3 py-2 text-xs text-center">{{ item.CantidadTotalProductos || 0 }}</td>
                                                <td class="px-3 py-2 text-xs text-center font-medium text-primary-600">
                                                    {{ item.CantidadContados || 0 }}
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(item.ActivoInactivo)">
                                                        <i :class="getEstadoIcono(item.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                                        {{ getEstadoTexto(item.ActivoInactivo) }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-right space-x-1.5 whitespace-nowrap">
                                                    <button @click="verDetalle(item.IdFisicoDiario)" class="text-blue-600 hover:text-blue-800 text-xs" title="Ver detalle">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button v-if="item.ActivoInactivo === 1" @click="reimprimirPDF(item.IdFisicoDiario)" class="text-red-600 hover:text-red-800 text-xs" title="PDF">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- MÓVIL: Tarjetas -->
                                <div class="md:hidden divide-y divide-gray-100">
                                    <div v-for="item in grupo.items" :key="item.IdFisicoDiario" class="p-3 hover:bg-gray-50 transition">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-bold text-primary-700 text-sm">#{{ item.NumeroCorrelativo || 'Sin número' }}</span>
                                                    <span class="text-[10px] text-gray-500">{{ item.fecha_formateada || '-' }}</span>
                                                </div>
                                                <div class="text-xs text-gray-600 mt-0.5">
                                                    <span class="text-gray-400">Operador:</span> {{ item.nombre_operador || 'N/A' }}
                                                </div>
                                                <div class="flex items-center gap-3 mt-1">
                                                    <span class="text-xs text-gray-500">Productos: {{ item.CantidadTotalProductos || 0 }}</span>
                                                    <span class="text-xs font-medium text-primary-600">Contados: {{ item.CantidadContados || 0 }}</span>
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(item.ActivoInactivo)">
                                                        <i :class="getEstadoIcono(item.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                                        {{ getEstadoTexto(item.ActivoInactivo) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                                <button @click="verDetalle(item.IdFisicoDiario)" class="text-blue-600" title="Ver detalle">
                                                    <i class="fas fa-eye text-lg"></i>
                                                </button>
                                                <button v-if="item.ActivoInactivo === 1" @click="reimprimirPDF(item.IdFisicoDiario)" class="text-red-600" title="PDF">
                                                    <i class="fas fa-file-pdf text-lg"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </transition>
                    </div>
                    
                    <!-- PAGINACIÓN CON FILTROS -->
                    <div v-if="props.inventarios?.data?.length" class="bg-white rounded-lg shadow-sm mt-4 px-3 sm:px-4 py-2 border border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <p class="text-[10px] sm:text-xs text-gray-500">
                            Mostrando {{ props.inventarios.from || 0 }} - {{ props.inventarios.to || 0 }} de {{ props.inventarios.total || 0 }}
                        </p>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in props.inventarios.links" 
                                :key="link.label" 
                                :href="construirUrlConFiltros(link.url)"
                                class="px-2 sm:px-2.5 py-1 rounded text-[10px] sm:text-xs transition" 
                                :class="{ 
                                    'bg-primary-600 text-white': link.active, 
                                    'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200': !link.active && link.url, 
                                    'opacity-50 cursor-not-allowed': !link.url 
                                }" 
                                v-html="link.label"
                                preserve-state
                            />
                        </div>
                    </div>
                </div>

                <!-- MENSAJE: SIN DATOS -->
                <div v-else class="bg-white rounded-lg shadow-sm p-6 sm:p-8 text-center text-gray-500">
                    <i class="fas fa-clipboard-list text-3xl sm:text-4xl block mb-2 text-gray-300"></i>
                    <p class="text-sm sm:text-base">
                        <span v-if="buscador">No hay inventarios que coincidan con "{{ buscador }}"</span>
                        <span v-else>No hay inventarios físicos registrados en esta sucursal</span>
                    </p>
                </div>

            </div>
        </div>

        <!-- MODAL DE DETALLE -->
        <div v-if="mostrarModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="cerrarModal"></div>

                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                    <!-- Header -->
                    <div class="px-4 pt-4 pb-3 border-b bg-primary-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clipboard-list text-primary-600 text-lg"></i>
                                <h3 class="text-base font-medium text-gray-900">Detalle del Inventario Físico</h3>
                                <span v-if="inventarioSeleccionado" class="text-xs text-gray-500 ml-2">
                                    #{{ inventarioSeleccionado.numero_correlativo || 'Sin número' }}
                                </span>
                            </div>
                            <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Cuerpo -->
                    <div class="px-4 py-4 sm:px-6">
                        <div v-if="loadingDetalle" class="flex justify-center py-8">
                            <i class="fas fa-spinner fa-spin text-primary-600 text-2xl"></i>
                        </div>

                        <div v-else-if="!inventarioSeleccionado" class="text-center py-8 text-gray-500">
                            <i class="fas fa-info-circle text-3xl mb-2 block"></i>
                            <p class="text-sm">No se encontró información</p>
                        </div>

                        <div v-else>
                            <!-- Resumen -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                                <div class="bg-gray-50 rounded-lg p-2 text-center">
                                    <p class="text-[10px] text-gray-500">Fecha</p>
                                    <p class="text-sm font-semibold">{{ inventarioSeleccionado.fecha || '-' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-2 text-center">
                                    <p class="text-[10px] text-gray-500">Productos</p>
                                    <p class="text-sm font-semibold text-primary-600">{{ inventarioSeleccionado.total_productos || 0 }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-2 text-center">
                                    <p class="text-[10px] text-gray-500">Con diferencia</p>
                                    <p class="text-sm font-semibold text-yellow-600">{{ inventarioSeleccionado.con_diferencia || 0 }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-2 text-center">
                                    <p class="text-[10px] text-gray-500">Operador</p>
                                    <p class="text-sm font-semibold truncate">{{ inventarioSeleccionado.operador || 'N/A' }}</p>
                                </div>
                            </div>

                            <!-- Tabla -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-2 py-1.5 text-left font-medium text-gray-500">#</th>
                                            <th class="px-2 py-1.5 text-left font-medium text-gray-500">Código</th>
                                            <th class="px-2 py-1.5 text-left font-medium text-gray-500">Producto</th>
                                            <th class="px-2 py-1.5 text-right font-medium text-gray-500">Sistema</th>
                                            <th class="px-2 py-1.5 text-right font-medium text-gray-500">Contado</th>
                                            <th class="px-2 py-1.5 text-right font-medium text-gray-500">Diferencia</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="(item, index) in inventarioSeleccionado.detalles" :key="index" class="hover:bg-gray-50">
                                            <td class="px-2 py-1.5 text-gray-500">{{ index + 1 }}</td>
                                            <td class="px-2 py-1.5 font-mono text-gray-600">{{ item.codigo || '-' }}</td>
                                            <td class="px-2 py-1.5 text-gray-700 max-w-[200px] truncate" :title="item.producto">{{ item.producto }}</td>
                                            <td class="px-2 py-1.5 text-right font-mono">{{ formatearNumero(item.sistema) }}</td>
                                            <td class="px-2 py-1.5 text-right font-mono font-semibold" :class="item.contado > 0 ? 'text-green-600' : 'text-gray-500'">
                                                {{ formatearNumero(item.contado) }}
                                            </td>
                                            <td class="px-2 py-1.5 text-right font-mono font-bold" :class="item.diferencia > 0 ? 'text-green-600' : item.diferencia < 0 ? 'text-red-600' : 'text-gray-400'">
                                                {{ formatearNumero(item.diferencia) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6 flex justify-end">
                        <button @click="cerrarModal" class="px-4 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:inline {
        display: inline;
    }
    .xs\:block {
        display: block;
    }
}

.max-h-0 {
    max-height: 0;
}
.max-h-\[5000px\] {
    max-height: 5000px;
}
.transition-all {
    transition-property: all;
}
.duration-300 {
    transition-duration: 300ms;
}
.ease-in-out {
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
.overflow-hidden {
    overflow: hidden;
}

.sucursal-autocomplete {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}
</style>