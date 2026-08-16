<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted, computed, inject } from 'vue'
import axios from 'axios'
import ShowModal from './ShowModal.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    contenedores: Object,
    sucursales: Array,
    sucursalActual: Number,
    filtroEstado: String,
    buscar: String,
    sucursalSeleccionada: String
})

// =============================================
// ESTADO DE FILTROS
// =============================================

// 🔥 Inicializar desde props para mantener filtros
const sucursalId = ref(props.sucursalSeleccionada || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')

// =============================================
// MODAL
// =============================================
const modalVisible = ref(false)
const contenedorSeleccionado = ref(null)

const abrirModal = (contenedor) => {
    contenedorSeleccionado.value = contenedor
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    contenedorSeleccionado.value = null
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
    const grupos = contenedoresAgrupados.value
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
const contenedoresAgrupados = computed(() => {
    if (!props.contenedores?.data) return {}
    
    const grupos = {}
    
    props.contenedores.data.forEach(contenedor => {
        const sucursalNombre = contenedor.sucursal?.Nombre || 'Sin sucursal'
        const sucursalId = contenedor.IdSucursal || 0
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                contenedores: [],
                total_capacidad: 0,
                total_unidades: 0,
                total_productos: 0
            }
        }
        
        grupos[sucursalId].contenedores.push(contenedor)
        grupos[sucursalId].total_capacidad += contenedor.CapacidadTotal || 0
        grupos[sucursalId].total_unidades += contenedor.TotalUnidades || 0
        grupos[sucursalId].total_productos += contenedor.cantidad_productos || 0
    })
    
    return grupos
})

const sucursalesConContenedores = computed(() => {
    return Object.values(contenedoresAgrupados.value)
})

const actualizarExpandidas = () => {
    const grupos = contenedoresAgrupados.value
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
    
    router.get('/operacion/pedidos/clientes-mayoristas/contenedores', params, {
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
const buscarContenedores = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

// Cerrar autocompletes
const handleClickOutside = (event) => {
    const container = document.querySelector('.sucursal-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarSucursales.value = false
    }
}

// =============================================
// 🔥 NUEVA FUNCIÓN: Construir URL con filtros para paginación
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
// ESTADO PARA SWITCH
// =============================================
const cambiando = ref({})
const loading = ref(false)
const isMobile = ref(window.innerWidth < 768)

// Modal de confirmación
const modalConfirmacionVisible = ref(false)
const modalConfirmacionData = ref({
    id: null,
    nombre: null,
    accion: 'desactivar',
    nuevoEstado: null
})

// =============================================
// TOAST
// =============================================
const mostrarToast = (mensaje, tipo = 'success') => {
    const toastAnterior = document.querySelector('.custom-toast')
    if (toastAnterior) toastAnterior.remove()
    
    const toast = document.createElement('div')
    const colores = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    }
    const iconos = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    }
    toast.className = `custom-toast fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white flex items-center gap-2 ${colores[tipo] || 'bg-blue-500'}`
    toast.innerHTML = `<i class="fas ${iconos[tipo] || 'fa-info-circle'}"></i> ${mensaje}`
    document.body.appendChild(toast)
    setTimeout(() => {
        if (toast && toast.remove) toast.remove()
    }, 4000)
}

// =============================================
// SWITCH / MODAL DE CONFIRMACIÓN
// =============================================
const toggleSwitch = (contenedor) => {
    if (contenedor.ActivoInactivo === 0) {
        mostrarToast('Este contenedor ya está en estado BORRADOR. Puede editarlo.', 'info')
        return
    }
    
    if (cambiando.value[contenedor.IdContenedor]) return
    
    // Solo permite desactivar (Activo → Inactivo/Borrador)
    abrirModalConfirmacion(contenedor, 0)
}

const abrirModalConfirmacion = (contenedor, nuevoEstado) => {
    modalConfirmacionData.value = {
        id: contenedor.IdContenedor,
        nombre: contenedor.Nombre,
        accion: 'desactivar',
        nuevoEstado: nuevoEstado
    }
    modalConfirmacionVisible.value = true
}

const cerrarModalConfirmacion = () => {
    modalConfirmacionVisible.value = false
    modalConfirmacionData.value = { id: null, nombre: null, accion: 'desactivar', nuevoEstado: null }
}

const ejecutarCambioEstado = async () => {
    if (!modalConfirmacionData.value.id) return
    
    cambiando.value[modalConfirmacionData.value.id] = true
    loading.value = true
    
    try {
        const response = await axios.post(`/operacion/pedidos/clientes-mayoristas/contenedores/${modalConfirmacionData.value.id}/toggle-estado`)
        
        if (response.data.success) {
            mostrarToast(response.data.message, 'success')
            aplicarFiltros()
            cerrarModalConfirmacion()
        } else {
            mostrarToast(response.data.message, 'error')
            cerrarModalConfirmacion()
        }
    } catch (error) {
        console.error('Error:', error)
        mostrarToast(error.response?.data?.message || 'Error al cambiar el estado', 'error')
        cerrarModalConfirmacion()
    } finally {
        cambiando.value[modalConfirmacionData.value.id] = false
        loading.value = false
    }
}

// =============================================
// ELIMINAR CONTENEDOR
// =============================================
const eliminarContenedor = async (id, nombre) => {
    if (!confirm(`¿Estás seguro de eliminar el contenedor "${nombre}"?`)) {
        return
    }
    
    try {
        const response = await axios.delete(`/operacion/pedidos/clientes-mayoristas/contenedores/${id}`)
        
        if (response.data.success) {
            mostrarToast(response.data.message, 'success')
            aplicarFiltros()
        } else {
            mostrarToast(response.data.message, 'error')
        }
    } catch (error) {
        console.error('Error:', error)
        mostrarToast(error.response?.data?.message || 'Error al eliminar', 'error')
    }
}

// =============================================
// UTILIDADES
// =============================================
const formatearNumero = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(2)
}

const formatearEntero = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(0)
}

const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Inactivo' : 'Activo'
}

const getEstadoBadge = (activo) => {
    return activo === 1 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
}

const getEstadoIcono = (activo) => {
    return activo === 1 ? 'fas fa-toggle-on' : 'fas fa-pencil-alt'
}

const puedeDesactivar = (contenedor) => {
    return contenedor.ActivoInactivo === 1
}

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
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-boxes text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Gestión de Contenedores</h1>
                            <p class="text-[10px] text-gray-500">Administra los contenedores por sucursal</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/operacion/pedidos/clientes-mayoristas/contenedores/create" class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-plus text-[10px]"></i>
                            <span>Nuevo Contenedor</span>
                        </Link>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        
                        <!-- 🔥 Sucursal - Autocomplete -->
                        <div class="sucursal-autocomplete flex items-center gap-1">
                            <label class="text-xs font-medium text-gray-700">Sucursal:</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="sucursalBusqueda"
                                    @focus="mostrarSucursales = true"
                                    @input="mostrarSucursales = true"
                                    class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-36 sm:w-44 pr-6 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
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
                            <select v-model="estadoFiltro" @change="aplicarFiltros" class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-32 sm:w-36 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Todos</option>
                                <option value="activos">Activos</option>
                                <option value="borradores">Borradores</option>
                            </select>
                        </div>
                        
                        <!-- Buscador -->
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarContenedores"
                                placeholder="Buscar..."
                                class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-28 sm:w-32 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
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
                        <span class="ml-2">({{ contenedores?.total || 0 }} resultados)</span>
                    </div>
                    
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> 
                        <span class="text-green-600">● Activo</span> = Contenedor listo para usar | 
                        <span class="text-yellow-600">● Borrador</span> = En edición (puede agregar productos)
                    </div>
                </div>

                <!-- ============================================= -->
                <!-- CONTENIDO PRINCIPAL -->
                <!-- ============================================= -->

                <!-- 🔥 MENSAJE: SIN SUCURSAL SELECCIONADA -->
                <div v-if="!sucursalId" class="bg-white rounded-xl shadow-sm p-8 sm:p-12 text-center">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-store text-primary-400 text-3xl sm:text-4xl"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-700">Seleccione una Sucursal</h3>
                    <p class="text-xs sm:text-sm text-gray-400 mt-2 max-w-sm mx-auto">
                        Use el campo de búsqueda de sucursales para visualizar los contenedores de una sucursal específica.
                    </p>
                </div>

                <!-- 🔥 GRID AGRUPADA POR SUCURSAL -->
                <div v-else-if="sucursalesConContenedores.length > 0">
                    <div v-for="grupo in sucursalesConContenedores" :key="grupo.id" class="mb-3">
                        
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
                                    {{ grupo.contenedores.length }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-xs sm:text-sm text-primary-700 flex-shrink-0">
                                <span class="hidden sm:inline">
                                    {{ grupo.contenedores.length }} contenedor(es)
                                </span>
                                <span class="font-bold">
                                    {{ formatearNumero(grupo.total_capacidad) }} und
                                </span>
                            </div>
                        </div>

                        <!-- Tabla de contenedores -->
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
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Capacidad</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="item in grupo.contenedores" :key="item.IdContenedor" class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">{{ item.Codigo }}</td>
                                                <td class="px-3 py-2 text-center text-xs">{{ formatearNumero(item.CapacidadTotal) }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoBadge(item.ActivoInactivo)">
                                                        <i :class="getEstadoIcono(item.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                                        {{ getEstadoTexto(item.ActivoInactivo) }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <div v-if="puedeDesactivar(item)" class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(item)">
                                                        <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out" 
                                                             :class="item.ActivoInactivo === 1 ? 'bg-red-500' : 'bg-green-500'">
                                                            <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out"
                                                                 :class="item.ActivoInactivo === 1 ? 'translate-x-[18px]' : 'translate-x-[2px]'">
                                                            </div>
                                                        </div>
                                                        <span class="ml-2 text-[10px]" :class="cambiando[item.IdContenedor] ? 'text-gray-400' : (item.ActivoInactivo === 1 ? 'text-red-600' : 'text-green-600')">
                                                            <i v-if="cambiando[item.IdContenedor]" class="fas fa-spinner fa-spin"></i>
                                                            <span v-else>{{ item.ActivoInactivo === 1 ? 'Inactivo' : 'Activo' }}</span>
                                                        </span>
                                                    </div>
                                                    <span v-else class="text-[10px] text-gray-400">
                                                        <i class="fas fa-lock mr-1"></i> Borrador
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <div class="flex justify-end gap-2">
                                                        <!-- BOTÓN EDITAR (solo borrador) -->
                                                        <Link 
                                                            v-if="item.ActivoInactivo === 0" 
                                                            :href="`/operacion/pedidos/clientes-mayoristas/contenedores/${item.IdContenedor}/edit`" 
                                                            class="text-amber-500 hover:text-amber-700 transition p-1 hover:bg-amber-50 rounded" 
                                                            title="Editar"
                                                        >
                                                            <i class="fas fa-edit text-sm"></i>
                                                        </Link>
                                                        <!-- BOTÓN ELIMINAR (solo borrador) -->
                                                        <button 
                                                            v-if="item.ActivoInactivo === 0" 
                                                            @click="eliminarContenedor(item.IdContenedor, item.Nombre)" 
                                                            class="text-red-500 hover:text-red-700 transition p-1 hover:bg-red-50 rounded" 
                                                            title="Eliminar"
                                                        >
                                                            <i class="fas fa-trash-alt text-sm"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- MÓVIL: Tarjetas -->
                                <div class="md:hidden divide-y divide-gray-100">
                                    <div v-for="item in grupo.contenedores" :key="item.IdContenedor" class="p-3 hover:bg-gray-50 transition">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-bold text-primary-700 text-sm font-mono">{{ item.Codigo }}</span>
                                                    <span class="text-xs text-gray-500">{{ item.Nombre }}</span>
                                                </div>
                                                <div class="flex items-center gap-3 mt-1">
                                                    <span class="text-xs text-gray-500">Cap: {{ formatearNumero(item.CapacidadTotal) }}</span>
                                                    <span class="text-xs font-bold" :class="item.TotalUnidades == item.CapacidadTotal ? 'text-green-600' : 'text-red-600'">
                                                        {{ formatearNumero(item.TotalUnidades) }} und
                                                    </span>
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoBadge(item.ActivoInactivo)">
                                                        {{ getEstadoTexto(item.ActivoInactivo) }}
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-400 mt-1">
                                                    {{ item.cantidad_productos || 0 }} producto(s)
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                                <div class="flex gap-2">
                                                    <!-- 🔥 BOTÓN VER DETALLE (OJO) -->
                                                    <button 
                                                        @click="abrirModal(item)" 
                                                        class="text-blue-500 hover:text-blue-700" 
                                                        title="Ver detalle"
                                                    >
                                                        <i class="fas fa-eye text-sm"></i>
                                                    </button>
                                                    <Link 
                                                        v-if="item.ActivoInactivo === 0" 
                                                        :href="`/operacion/pedidos/clientes-mayoristas/contenedores/${item.IdContenedor}/edit`" 
                                                        class="text-amber-500 hover:text-amber-700" 
                                                        title="Editar"
                                                    >
                                                        <i class="fas fa-edit text-sm"></i>
                                                    </Link>
                                                    <button 
                                                        v-if="item.ActivoInactivo === 0" 
                                                        @click="eliminarContenedor(item.IdContenedor, item.Nombre)" 
                                                        class="text-red-500 hover:text-red-700" 
                                                        title="Eliminar"
                                                    >
                                                        <i class="fas fa-trash-alt text-sm"></i>
                                                    </button>
                                                </div>
                                                <div v-if="puedeDesactivar(item)" class="relative inline-flex items-center cursor-pointer mt-1" @click="toggleSwitch(item)">
                                                    <div class="w-8 h-4 rounded-full transition-colors duration-200 ease-in-out"
                                                         :class="item.ActivoInactivo === 1 ? 'bg-red-500' : 'bg-green-500'">
                                                        <div class="absolute w-3.5 h-3.5 bg-white rounded-full top-[1px] transition-transform duration-200 ease-in-out"
                                                             :class="item.ActivoInactivo === 1 ? 'translate-x-[16px]' : 'translate-x-[2px]'">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </transition>
                    </div>
                    
                    <!-- 🔥 PAGINACIÓN CON FILTROS -->
                    <div v-if="props.contenedores?.data?.length" class="bg-white rounded-xl shadow-sm mt-4 px-3 sm:px-4 py-2 border border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <p class="text-[10px] sm:text-xs text-gray-500">
                            Mostrando {{ props.contenedores.from || 0 }} - {{ props.contenedores.to || 0 }} de {{ props.contenedores.total || 0 }}
                        </p>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in props.contenedores.links" 
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

                <!-- 🔥 MENSAJE: SIN DATOS EN LA SUCURSAL SELECCIONADA -->
                <div v-else class="bg-white rounded-xl shadow-sm p-6 sm:p-8 text-center text-gray-500">
                    <i class="fas fa-box-open text-3xl sm:text-4xl block mb-2 text-gray-300"></i>
                    <p class="text-sm sm:text-base">
                        <span v-if="buscador">No hay contenedores que coincidan con "{{ buscador }}"</span>
                        <span v-else>No hay contenedores en esta sucursal</span>
                    </p>
                    <div class="mt-4">
                        <Link href="/operacion/pedidos/clientes-mayoristas/contenedores/create" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-800">
                            <i class="fas fa-plus"></i> Crear nuevo contenedor
                        </Link>
                    </div>
                </div>

            </div>
        </div>

        <!-- ============================================= -->
        <!-- MODAL DE CONFIRMACIÓN (Switch) -->
        <!-- ============================================= -->
        <div v-if="modalConfirmacionVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarModalConfirmacion">
            <div class="bg-white rounded-xl w-full max-w-[90%] sm:max-w-sm overflow-hidden shadow-xl">
                <div class="p-4 border-b bg-amber-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-amber-100">
                            <i class="fas fa-sync-alt text-amber-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 text-sm sm:text-base">Cambiar Estado</h3>
                            <p class="text-[10px] sm:text-xs text-gray-500">{{ modalConfirmacionData.nombre }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-700 text-center">
                        ¿Estás seguro de cambiar este contenedor a <span class="font-bold text-green-600">BORRADOR</span>?
                    </p>
                    <p class="text-[10px] sm:text-xs text-gray-400 text-center mt-2">
                        Al desactivarlo, el contenedor pasará a estado <span class="font-bold text-yellow-600">BORRADOR</span> y podrá editarse.
                    </p>
                </div>
                <div class="p-3 sm:p-4 bg-gray-50 flex justify-end gap-2 sm:gap-3">
                    <button @click="cerrarModalConfirmacion" class="px-3 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button @click="ejecutarCambioEstado" :disabled="loading" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition flex items-center gap-2 bg-green-600 hover:bg-green-700">
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check"></i>
                        Desactivar
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================= -->
        <!-- SHOW MODAL (Detalle del Contenedor) -->
        <!-- ============================================= -->
        <ShowModal 
            :visible="modalVisible" 
            :contenedor="contenedorSeleccionado"
            @close="cerrarModal"
        />
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:inline { display: inline; }
    .xs\:block { display: block; }
}

.custom-toast {
    max-width: 90%;
    z-index: 9999;
}

/* Transición para expandir/contraer */
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

/* Autocomplete */
.sucursal-autocomplete {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}
</style>