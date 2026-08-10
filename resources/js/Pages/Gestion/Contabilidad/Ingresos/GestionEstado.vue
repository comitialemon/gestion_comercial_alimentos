<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted, computed, inject } from 'vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    ingresos: Object,
    sucursales: Array,
    sucursalActual: Number,
    filtroEstado: String,
    buscar: String,
    sucursalSeleccionada: String
})

// =============================================
// ESTADO DE FILTROS
// =============================================

// 🔥 Inicializar vacío - SIN auto-selección
const sucursalId = ref(props.sucursalSeleccionada || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')

// =============================================
// 🔥 NUEVA FUNCIÓN: Construir URL con filtros para paginación
// =============================================
const construirUrlConFiltros = (url) => {
    if (!url) return '#'
    
    try {
        // Crear objeto URL
        const urlObj = new URL(url, window.location.origin)
        const params = new URLSearchParams(urlObj.search)
        
        // Mantener los filtros actuales
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
    const grupos = ingresosAgrupados.value
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
const ingresosAgrupados = computed(() => {
    if (!props.ingresos?.data) return {}
    
    const grupos = {}
    
    props.ingresos.data.forEach(ingreso => {
        const sucursalNombre = ingreso.sucursal_nombre || 'Sin sucursal'
        const sucursalId = ingreso.IdSucursal || 0
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                ingresos: [],
                total: 0
            }
        }
        
        grupos[sucursalId].ingresos.push(ingreso)
        grupos[sucursalId].total += ingreso.TotalBolivianos || 0
    })
    
    return grupos
})

const sucursalesConIngresos = computed(() => {
    return Object.values(ingresosAgrupados.value)
})

const actualizarExpandidas = () => {
    const grupos = ingresosAgrupados.value
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
    
    router.get('/gestion/ingresos/gestion-estado', params, {
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
const buscarIngresos = () => {
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
// ESTADO
// =============================================
const cambiando = ref({})
const loading = ref(false)
const isMobile = ref(window.innerWidth < 768)

// Modal
const modalVisible = ref(false)
const modalData = ref({
    id: null,
    numero: null,
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
        warning: 'bg-yellow-500'
    }
    const iconos = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle'
    }
    toast.className = `custom-toast fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white flex items-center gap-2 ${colores[tipo] || 'bg-blue-500'}`
    toast.innerHTML = `<i class="fas ${iconos[tipo] || 'fa-info-circle'}"></i> ${mensaje}`
    document.body.appendChild(toast)
    setTimeout(() => {
        if (toast && toast.remove) toast.remove()
    }, 4000)
}

// =============================================
// SWITCH / MODAL
// =============================================
const toggleSwitch = (ingreso) => {
    if (ingreso.ActivoInactivo === 0) {
        mostrarToast('Este ingreso ya está en estado ACTIVO (Borrador). Puede editarlo.', 'info')
        return
    }
    
    if (cambiando.value[ingreso.IdIngreso]) return
    abrirModalConfirmacion(ingreso, 0)
}

const abrirModalConfirmacion = (ingreso, nuevoEstado) => {
    modalData.value = {
        id: ingreso.IdIngreso,
        numero: ingreso.NumeroIngreso,
        accion: 'desactivar',
        nuevoEstado: nuevoEstado
    }
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    modalData.value = { id: null, numero: null, accion: 'desactivar', nuevoEstado: null }
}

const ejecutarCambioEstado = async () => {
    if (!modalData.value.id) return
    
    cambiando.value[modalData.value.id] = true
    loading.value = true
    
    try {
        const response = await fetch(`/gestion/ingresos/${modalData.value.id}/cambiar-estado`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                estado: 0
            })
        })
        
        const data = await response.json()
        
        if (data.success) {
            mostrarToast(data.message, 'success')
            aplicarFiltros()
        } else {
            mostrarToast(data.message, 'error')
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

// =============================================
// UTILIDADES
// =============================================
const formatearMonto = (monto) => {
    return Number(monto).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

// 🔥 CORREGIDO: 1 = Inactivo (Contabilizado), 0 = Activo (Borrador)
const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
}

const getEstadoIcono = (activo) => {
    return activo === 1 ? 'fas fa-lock' : 'fas fa-pencil-alt'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Inactivo' : 'Activo'
}

const puedeDesactivar = (ingreso) => {
    return ingreso.ActivoInactivo === 1
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
        // Si hay sucursal seleccionada, cargar datos
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
                            <i class="fas fa-toggle-on text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Gestión de Estados - Ingresos</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">Cambiar estado de comprobantes de ingreso</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/gestion/ingresos" class="flex-1 sm:flex-initial bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-list text-[10px]"></i>
                            <span>Listado</span>
                        </Link>
                        <Link href="/gestion/ingresos/create" class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-plus text-[10px]"></i>
                            <span>Nuevo Ingreso</span>
                        </Link>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
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
                                
                                <!-- Mensaje sin sucursales -->
                                <div v-else-if="mostrarSucursales && sucursalesDisponibles.length === 0 && sucursalBusqueda" 
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-2 text-center text-gray-500 text-xs">
                                    <i class="fas fa-search mr-1"></i> No se encontraron sucursales
                                </div>
                            </div>
                            <!-- Badge de sucursal seleccionada -->
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
                                <option value="activos">Activos (Borrador)</option>
                                <option value="inactivos">Inactivos (Contabilizado)</option>
                            </select>
                        </div>
                        
                        <!-- Buscador -->
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarIngresos"
                                placeholder="N° Ingreso..."
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
                        <span class="ml-2">({{ ingresos?.total || 0 }} resultados)</span>
                    </div>
                    
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> 
                        <span class="text-green-600">● Activo</span> = Borrador (editable) | 
                        <span class="text-red-600">● Inactivo</span> = Contabilizado (no editable)
                    </div>
                </div>

                <!-- ============================================= -->
                <!-- CONTENIDO PRINCIPAL -->
                <!-- ============================================= -->

                <!-- 🔥 MENSAJE: SIN SUCURSAL SELECCIONADA -->
                <div v-if="!sucursalId" class="bg-white rounded-lg shadow-sm p-8 sm:p-12 text-center">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-store text-primary-400 text-3xl sm:text-4xl"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-700">Seleccione una Sucursal</h3>
                    <p class="text-xs sm:text-sm text-gray-400 mt-2 max-w-sm mx-auto">
                        Use el campo de búsqueda de sucursales para visualizar los ingresos de una sucursal específica.
                    </p>
                </div>

                <!-- 🔥 GRID AGRUPADA POR SUCURSAL (solo si hay sucursal seleccionada) -->
                <div v-else-if="sucursalesConIngresos.length > 0">
                    <div v-for="grupo in sucursalesConIngresos" :key="grupo.id" class="mb-3">
                        
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
                                    {{ grupo.ingresos.length }}
                                </span>
                            </div>
                            <div class="text-xs sm:text-sm font-bold text-primary-700 flex-shrink-0">
                                {{ Number(grupo.total).toFixed(2) }} Bs
                            </div>
                        </div>

                        <!-- Tabla de ingresos -->
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
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Ingreso</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Recibido de</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Glosa</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acción</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">PDF</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="ingreso in grupo.ingresos" :key="ingreso.IdIngreso" class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">{{ ingreso.NumeroIngreso }}</td>
                                                <td class="px-3 py-2 text-xs text-gray-500">{{ ingreso.fecha_formateada || '-' }}</td>
                                                <td class="px-3 py-2 text-xs text-gray-700 max-w-[150px] truncate" :title="ingreso.identificador?.Nombre">
                                                    {{ ingreso.identificador?.Nombre || '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-xs text-gray-600 max-w-[200px] truncate" :title="ingreso.Glosa">
                                                    {{ ingreso.Glosa || '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-xs text-right font-semibold text-primary-600">{{ formatearMonto(ingreso.TotalBolivianos) }} Bs</td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(ingreso.ActivoInactivo)">
                                                        <i :class="getEstadoIcono(ingreso.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                                        {{ getEstadoTexto(ingreso.ActivoInactivo) }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <div v-if="puedeDesactivar(ingreso)" class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(ingreso)">
                                                        <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out" 
                                                             :class="ingreso.ActivoInactivo === 1 ? 'bg-red-500' : 'bg-green-500'">
                                                            <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out"
                                                                 :class="ingreso.ActivoInactivo === 1 ? 'translate-x-[18px]' : 'translate-x-[2px]'">
                                                            </div>
                                                        </div>
                                                        <span class="ml-2 text-[10px]" :class="cambiando[ingreso.IdIngreso] ? 'text-gray-400' : (ingreso.ActivoInactivo === 1 ? 'text-red-600' : 'text-green-600')">
                                                            <i v-if="cambiando[ingreso.IdIngreso]" class="fas fa-spinner fa-spin"></i>
                                                            <span v-else>{{ ingreso.ActivoInactivo === 1 ? 'Inactivo' : 'Activo' }}</span>
                                                        </span>
                                                    </div>
                                                    <span v-else class="text-[10px] text-gray-400">
                                                        <i class="fas fa-lock mr-1"></i> Activo
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <a :href="`/gestion/ingresos/${ingreso.IdIngreso}/pdf`" target="_blank" class="text-red-600 hover:text-red-800" title="PDF">
                                                        <i class="fas fa-file-pdf text-sm"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- MÓVIL: Tarjetas -->
                                <div class="md:hidden divide-y divide-gray-100">
                                    <div v-for="ingreso in grupo.ingresos" :key="ingreso.IdIngreso" class="p-3 hover:bg-gray-50 transition">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-bold text-primary-700 text-sm">N° {{ ingreso.NumeroIngreso }}</span>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    {{ ingreso.fecha_formateada || '-' }}
                                                </div>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    <span class="text-gray-400">Recibido de:</span> {{ ingreso.identificador?.Nombre || '-' }}
                                                </div>
                                                <div class="text-xs text-gray-600 mt-0.5">
                                                    <span class="text-gray-400">Glosa:</span> {{ ingreso.Glosa || '-' }}
                                                </div>
                                                <div class="flex items-center gap-3 mt-1">
                                                    <span class="text-sm font-bold text-primary-700">{{ formatearMonto(ingreso.TotalBolivianos) }} Bs</span>
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(ingreso.ActivoInactivo)">
                                                        <i :class="getEstadoIcono(ingreso.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                                        {{ getEstadoTexto(ingreso.ActivoInactivo) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                                <a :href="`/gestion/ingresos/${ingreso.IdIngreso}/pdf`" target="_blank" class="text-red-600" title="PDF">
                                                    <i class="fas fa-file-pdf text-lg"></i>
                                                </a>
                                                <div v-if="puedeDesactivar(ingreso)" class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(ingreso)">
                                                    <div class="w-8 h-4 rounded-full transition-colors duration-200 ease-in-out"
                                                         :class="ingreso.ActivoInactivo === 1 ? 'bg-red-500' : 'bg-green-500'">
                                                        <div class="absolute w-3.5 h-3.5 bg-white rounded-full top-[1px] transition-transform duration-200 ease-in-out"
                                                             :class="ingreso.ActivoInactivo === 1 ? 'translate-x-[16px]' : 'translate-x-[2px]'">
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
                    
                    <!-- 🔥 PAGINACIÓN CON FILTROS CORREGIDA -->
                    <div v-if="props.ingresos?.data?.length" class="bg-white rounded-lg shadow-sm mt-4 px-3 sm:px-4 py-2 border border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <p class="text-[10px] sm:text-xs text-gray-500">
                            Mostrando {{ props.ingresos.from || 0 }} - {{ props.ingresos.to || 0 }} de {{ props.ingresos.total || 0 }}
                        </p>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in props.ingresos.links" 
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
                <div v-else class="bg-white rounded-lg shadow-sm p-6 sm:p-8 text-center text-gray-500">
                    <i class="fas fa-receipt text-3xl sm:text-4xl block mb-2 text-gray-300"></i>
                    <p class="text-sm sm:text-base">
                        <span v-if="buscador">No hay ingresos que coincidan con "{{ buscador }}"</span>
                        <span v-else>No hay comprobantes de ingreso en esta sucursal</span>
                    </p>
                </div>

            </div>
        </div>

        <!-- MODAL DE CONFIRMACIÓN -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarModal">
            <div class="bg-white rounded-xl w-full max-w-[90%] sm:max-w-sm overflow-hidden shadow-xl">
                <div class="p-4 border-b bg-yellow-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-yellow-100">
                            <i class="fas fa-sync-alt text-yellow-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 text-sm sm:text-base">Cambiar Estado</h3>
                            <p class="text-[10px] sm:text-xs text-gray-500">Ingreso N° {{ modalData.numero }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-700 text-center">
                        ¿Estás seguro de cambiar este ingreso a <span class="font-bold text-green-600">ACTIVO</span>?
                    </p>
                    <p class="text-[10px] sm:text-xs text-gray-400 text-center mt-2">
                        Al activarlo, el ingreso pasará a estado <span class="font-bold text-green-600">BORRADOR</span> y podrá editarse.
                    </p>
                </div>
                <div class="p-3 sm:p-4 bg-gray-50 flex justify-end gap-2 sm:gap-3">
                    <button @click="cerrarModal" class="px-3 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button @click="ejecutarCambioEstado" :disabled="loading" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition flex items-center gap-2 bg-green-600 hover:bg-green-700">
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check"></i>
                        Activar
                    </button>
                </div>
            </div>
        </div>
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