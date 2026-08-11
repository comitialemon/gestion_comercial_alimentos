<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted, computed, inject } from 'vue'
import ModalDetalleFactura from './ModalDetalleFactura.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    ventas: Object,
    sucursales: Array,
    operadores: Array,
    sucursalActual: Number,
    filtroEstado: String,
    filtroOperador: String,
    buscar: String,
    sucursalSeleccionada: String
})

// =============================================
// ESTADO DE FILTROS
// =============================================

const sucursalId = ref(props.sucursalSeleccionada || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const operadorId = ref(props.filtroOperador || '')
const operadorBusqueda = ref('')
const mostrarOperadores = ref(false)

const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')

// =============================================
// ESTADO PARA MODAL DE DETALLE
// =============================================
const modalDetalleVisible = ref(false)
const ventaIdSeleccionada = ref(null)

// =============================================
// COMPUTADOS - Autocomplete Sucursal
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
// COMPUTADOS - Autocomplete Operador
// =============================================

const operadoresDisponibles = computed(() => {
    if (!props.operadores) return []
    if (!operadorBusqueda.value) return props.operadores
    
    const termino = operadorBusqueda.value.toLowerCase()
    return props.operadores.filter(op => 
        op.nombre_completo?.toLowerCase().includes(termino)
    )
})

const operadorNombre = computed(() => {
    if (!operadorId.value) return ''
    const op = props.operadores?.find(o => o.id == operadorId.value)
    return op?.nombre_completo || ''
})

// =============================================
// ESTADO PARA CONTRAER/EXPANDIR
// =============================================
const sucursalesExpandidas = ref({})

const inicializarExpandidas = () => {
    const grupos = ventasAgrupadas.value
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
const ventasAgrupadas = computed(() => {
    if (!props.ventas?.data) return {}
    
    const grupos = {}
    
    props.ventas.data.forEach(venta => {
        const sucursalNombre = venta.sucursal_nombre || 'Sin sucursal'
        const sucursalId = venta.IdClienteSucursal || 0
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                ventas: [],
                total: 0
            }
        }
        
        grupos[sucursalId].ventas.push(venta)
        grupos[sucursalId].total += venta.ImporteVenta || 0
    })
    
    return grupos
})

const sucursalesConVentas = computed(() => {
    return Object.values(ventasAgrupadas.value)
})

const actualizarExpandidas = () => {
    const grupos = ventasAgrupadas.value
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
// ACCIONES - CON FILTROS
// =============================================
const aplicarFiltros = () => {
    const params = {}
    
    if (sucursalId.value && sucursalId.value !== '') {
        params.sucursal_id = sucursalId.value
    }
    if (operadorId.value && operadorId.value !== '') {
        params.operador_id = operadorId.value
    }
    if (estadoFiltro.value && estadoFiltro.value !== '') {
        params.estado = estadoFiltro.value
    }
    if (buscador.value && buscador.value !== '') {
        params.buscar = buscador.value
    }
    
    router.get('/gestion/reportes/control-interno/ventas/gestion-estado', params, {
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

const seleccionarOperador = (operador) => {
    operadorId.value = operador.id
    operadorBusqueda.value = operador.nombre_completo
    mostrarOperadores.value = false
    aplicarFiltros()
}

const limpiarOperador = () => {
    operadorId.value = ''
    operadorBusqueda.value = ''
    mostrarOperadores.value = false
    aplicarFiltros()
}

let timeoutBuscador
const buscarVentas = () => {
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
    const containerOp = document.querySelector('.operador-autocomplete')
    if (containerOp && !containerOp.contains(event.target)) {
        mostrarOperadores.value = false
    }
}

// =============================================
// 🔥 Construir URL con filtros para paginación
// =============================================
const construirUrlConFiltros = (url) => {
    if (!url) return '#'
    
    try {
        const urlObj = new URL(url, window.location.origin)
        const params = new URLSearchParams(urlObj.search)
        
        if (sucursalId.value && sucursalId.value !== '') {
            params.set('sucursal_id', sucursalId.value)
        }
        if (operadorId.value && operadorId.value !== '') {
            params.set('operador_id', operadorId.value)
        }
        if (estadoFiltro.value && estadoFiltro.value !== '') {
            params.set('estado', estadoFiltro.value)
        }
        if (buscador.value && buscador.value !== '') {
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
// 🔥 ESTADO PARA SWITCH
// =============================================
const cambiando = ref({})
const loading = ref(false)

const modalVisible = ref(false)
const modalData = ref({
    id: null,
    numero: null,
    nuevoEstado: null
})

// =============================================
// 🔥 TOAST
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
// 🔥 ABRIR MODAL DE DETALLE
// =============================================
const abrirModalDetalle = (ventaId) => {
    ventaIdSeleccionada.value = ventaId
    modalDetalleVisible.value = true
}

// =============================================
// 🔥 CUANDO SE ACTIVA UNA FACTURA DESDE EL MODAL
// =============================================
const onFacturaActivada = () => {
    setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

// =============================================
// 🔥 SWITCH - SOLO PARA FACTURAS INACTIVAS (ActivoInactivo = 1)
// =============================================
const toggleSwitch = (venta) => {
    if (venta.ActivoInactivo !== 1) {
        mostrarToast('Esta factura ya está activa', 'info')
        return
    }
    
    if (cambiando.value[venta.IdVentas]) return
    abrirModalConfirmacion(venta)
}

const abrirModalConfirmacion = (venta) => {
    modalData.value = {
        id: venta.IdVentas,
        numero: venta.NumeroFactura,
        nuevoEstado: 0
    }
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    modalData.value = { id: null, numero: null, nuevoEstado: null }
}

const ejecutarCambioEstado = async () => {
    if (!modalData.value.id) return
    
    cambiando.value[modalData.value.id] = true
    loading.value = true
    
    try {
        const response = await fetch(`/gestion/reportes/control-interno/ventas/${modalData.value.id}/cambiar-estado`, {
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
            
            // 🔥 CERRAR EL MODAL INMEDIATAMENTE
            cerrarModal()
            
            // 🔥 RECARGAR LA LISTA DESPUÉS DE CERRAR EL MODAL
            setTimeout(() => {
                aplicarFiltros()
            }, 500)
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

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    const d = new Date(fecha)
    return d.toLocaleDateString('es-BO', { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Inactivo' : 'Activo'
}

const puedeEditar = (venta) => {
    return venta.ActivoInactivo === 0 && venta.LiquidadoVendedor === 0
}

const puedeActivar = (venta) => {
    return venta.ActivoInactivo === 1 && venta.LiquidadoVendedor === 0
}

// =============================================
// CICLO DE VIDA
// =============================================
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    
    if (sucursalId.value) {
        const sucursal = props.sucursales?.find(s => s.id == sucursalId.value)
        if (sucursal) {
            sucursalBusqueda.value = sucursal.nombre
        }
    }
    
    if (operadorId.value) {
        const operador = props.operadores?.find(o => o.id == operadorId.value)
        if (operador) {
            operadorBusqueda.value = operador.nombre_completo
        }
    }
    
    setTimeout(() => {
        inicializarExpandidas()
    }, 100)
})

onUnmounted(() => {
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
                            <i class="fas fa-file-invoice text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Gestión de Facturas</h1>
                            <p class="text-[10px] text-gray-500">Control Interno - Edición de facturas</p>
                        </div>
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
                        
                        <!-- 🔥 Operador - Autocomplete -->
                        <div class="operador-autocomplete flex items-center gap-1">
                            <label class="text-xs font-medium text-gray-700">Vendedor:</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="operadorBusqueda"
                                    @focus="mostrarOperadores = true"
                                    @input="mostrarOperadores = true"
                                    class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-36 sm:w-44 pr-6 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="Buscar vendedor..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="operadorBusqueda"
                                    @click="limpiarOperador"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                
                                <div v-if="mostrarOperadores && operadoresDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto min-w-[180px]">
                                    <div 
                                        v-for="op in operadoresDisponibles" 
                                        :key="op.id"
                                        @click="seleccionarOperador(op)"
                                        class="px-3 py-1.5 cursor-pointer hover:bg-primary-50 text-xs flex justify-between items-center border-b border-gray-100 last:border-0"
                                        :class="operadorId == op.id ? 'bg-primary-50' : ''"
                                    >
                                        <span class="truncate">{{ op.nombre_completo }}</span>
                                        <span v-if="operadorId == op.id" class="text-primary-600">
                                            <i class="fas fa-check-circle text-[10px]"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div v-else-if="mostrarOperadores && operadoresDisponibles.length === 0 && operadorBusqueda" 
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-2 text-center text-gray-500 text-xs">
                                    <i class="fas fa-search mr-1"></i> No se encontraron vendedores
                                </div>
                            </div>
                            <span v-if="operadorId && operadorNombre" class="text-[10px] text-primary-600 font-medium ml-1">
                                <i class="fas fa-check-circle"></i> {{ operadorNombre }}
                            </span>
                            <span v-else class="text-[10px] text-gray-400 ml-1">
                                <i class="fas fa-user"></i> Todos
                            </span>
                        </div>
                        
                        <!-- Estado -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" @change="aplicarFiltros" class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-32 sm:w-36 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Todos</option>
                                <option value="activos">Activos (Borrador)</option>
                                <option value="inactivos">Inactivos (Cerrado)</option>
                            </select>
                        </div>
                        
                        <!-- Buscador por N° Factura -->
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarVentas"
                                placeholder="N° Factura..."
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
                        <span class="ml-2">({{ ventas?.total || 0 }} resultados)</span>
                    </div>
                    
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> 
                        <span class="text-green-600">● Activo</span> = Borrador (editable) | 
                        <span class="text-red-600">● Inactivo</span> = Cerrado (no editable)
                        <span v-if="ventas?.total" class="ml-2">| Total: <strong>{{ ventas.total }}</strong> facturas</span>
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
                        Use el campo de búsqueda de sucursales para visualizar las facturas de una sucursal específica.
                    </p>
                </div>

                <!-- 🔥 GRID AGRUPADA POR SUCURSAL -->
                <div v-else-if="sucursalesConVentas.length > 0">
                    <div v-for="grupo in sucursalesConVentas" :key="grupo.id" class="mb-3">
                        
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
                                    {{ grupo.ventas.length }}
                                </span>
                            </div>
                            <div class="text-xs sm:text-sm font-bold text-primary-700 flex-shrink-0">
                                {{ Number(grupo.total).toFixed(2) }} Bs
                            </div>
                        </div>

                        <!-- Tabla de facturas -->
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
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Factura</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="venta in grupo.ventas" :key="venta.IdVentas" class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">
                                                    {{ venta.NumeroFactura }}
                                                </td>
                                                <td class="px-3 py-2 text-xs text-gray-500">{{ formatearFecha(venta.FechaVenta) }}</td>
                                                <td class="px-3 py-2 text-xs text-gray-500 max-w-[100px] truncate" :title="venta.vendedor_nombre">
                                                    {{ venta.vendedor_nombre || '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-xs text-right font-semibold text-primary-600">
                                                    {{ formatearMonto(venta.ImporteVenta) }} Bs
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(venta.ActivoInactivo)">
                                                        {{ getEstadoTexto(venta.ActivoInactivo) }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <div class="flex items-center justify-center gap-2">
                                                        
                                                        <!-- Switch activar (solo si está inactiva) -->
                                                        <div v-if="puedeActivar(venta)" class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(venta)">
                                                            <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out bg-red-500">
                                                                <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out translate-x-[18px]">
                                                                </div>
                                                            </div>
                                                            <span class="ml-2 text-[10px]" :class="cambiando[venta.IdVentas] ? 'text-gray-400' : 'text-red-600'">
                                                                <i v-if="cambiando[venta.IdVentas]" class="fas fa-spinner fa-spin"></i>
                                                                <span v-else>Inactivo</span>
                                                            </span>
                                                        </div>
                                                        <span v-else-if="venta.LiquidadoVendedor > 0" class="text-[10px] text-gray-400" title="Factura liquidada - no editable">
                                                            <i class="fas fa-lock text-xs mr-1"></i> Liquidada
                                                        </span>
                                                        <span v-else class="text-[10px] text-green-600">
                                                            <i class="fas fa-check-circle mr-1"></i> Activo
                                                        </span>
                                                        
                                                        <!-- 🔥 Botón Ver - abre modal -->
                                                        <button 
                                                            @click="abrirModalDetalle(venta.IdVentas)"
                                                            class="text-blue-600 hover:text-blue-800 text-xs"
                                                            title="Ver detalle de la factura"
                                                        >
                                                            <i class="fas fa-eye text-sm"></i>
                                                        </button>

                                                        <!-- 🖨️ Reimprimir -->
                                                        <a 
                                                            :href="`/gestion/reportes/control-interno/ventas/${venta.IdVentas}/reimprimir`"
                                                            target="_blank"
                                                            class="text-red-600 hover:text-red-800 text-xs"
                                                            title="Reimprimir factura"
                                                        >
                                                            <i class="fas fa-file-pdf text-sm"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- MÓVIL: Tarjetas -->
                                <div class="md:hidden divide-y divide-gray-100">
                                    <div v-for="venta in grupo.ventas" :key="venta.IdVentas" class="p-3 hover:bg-gray-50 transition">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-bold text-primary-700 text-sm">N° {{ venta.NumeroFactura }}</span>
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(venta.ActivoInactivo)">
                                                        {{ getEstadoTexto(venta.ActivoInactivo) }}
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    {{ formatearFecha(venta.FechaVenta) }}
                                                </div>
                                                <div class="text-xs text-gray-600">
                                                    <span class="text-gray-400">Vendedor:</span> {{ venta.vendedor_nombre || '-' }}
                                                </div>
                                                <div class="flex items-center gap-3 mt-1">
                                                    <span class="text-sm font-bold text-primary-700">{{ formatearMonto(venta.ImporteVenta) }} Bs</span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                                
                                                <!-- Switch activar -->
                                                <div v-if="puedeActivar(venta)" class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(venta)">
                                                    <div class="w-8 h-4 rounded-full transition-colors duration-200 ease-in-out bg-red-500">
                                                        <div class="absolute w-3.5 h-3.5 bg-white rounded-full top-[1px] transition-transform duration-200 ease-in-out translate-x-[16px]">
                                                        </div>
                                                    </div>
                                                    <span class="ml-1 text-[10px]" :class="cambiando[venta.IdVentas] ? 'text-gray-400' : 'text-red-600'">
                                                        <i v-if="cambiando[venta.IdVentas]" class="fas fa-spinner fa-spin"></i>
                                                        <span v-else>Inactivo</span>
                                                    </span>
                                                </div>

                                                <!-- 🔥 Botón Ver - Móvil -->
                                                <button 
                                                    @click="abrirModalDetalle(venta.IdVentas)"
                                                    class="text-blue-600 hover:text-blue-800 text-xs"
                                                    title="Ver detalle"
                                                >
                                                    <i class="fas fa-eye text-base"></i>
                                                </button>
                                            
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </transition>
                    </div>
                    
                    <!-- Paginación -->
                    <div v-if="props.ventas?.data?.length" class="bg-white rounded-xl shadow-sm mt-4 px-3 sm:px-4 py-2 border border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <p class="text-[10px] sm:text-xs text-gray-500">
                            Mostrando {{ props.ventas.from || 0 }} - {{ props.ventas.to || 0 }} de {{ props.ventas.total || 0 }}
                        </p>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in props.ventas.links" 
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

                <!-- 🔥 MENSAJE: SIN DATOS -->
                <div v-else class="bg-white rounded-xl shadow-sm p-6 sm:p-8 text-center text-gray-500">
                    <i class="fas fa-file-invoice text-3xl sm:text-4xl block mb-2 text-gray-300"></i>
                    <p class="text-sm sm:text-base">
                        <span v-if="buscador">No hay facturas que coincidan con "{{ buscador }}"</span>
                        <span v-else>No hay facturas en esta sucursal</span>
                    </p>
                </div>

            </div>
        </div>

        <!-- Modal de confirmación para activar -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarModal">
            <div class="bg-white rounded-xl w-full max-w-[90%] sm:max-w-sm overflow-hidden shadow-xl">
                <div class="p-4 border-b bg-green-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 text-sm sm:text-base">Activar Factura</h3>
                            <p class="text-[10px] sm:text-xs text-gray-500">Factura N° {{ modalData.numero }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-700 text-center">
                        ¿Estás seguro de cambiar esta factura a <span class="font-bold text-green-600">ACTIVO (Borrador)</span>?
                    </p>
                    <p class="text-[10px] sm:text-xs text-gray-400 text-center mt-2">
                        Al activarla, la factura podrá ser editada y modificada.
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

        <!-- 🔥 MODAL DE DETALLE DE FACTURA -->
        <ModalDetalleFactura
            v-model:visible="modalDetalleVisible"
            :venta-id="ventaIdSeleccionada"
            @activado="onFacturaActivada"
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

.sucursal-autocomplete,
.operador-autocomplete {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}
</style>