<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: Object,
    sucursales: Array,
    sucursalId: Number,
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
const sucursalId = ref('')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const productoSeleccionado = ref('')
const productoBusqueda = ref('')
const mostrarProductos = ref(false)
const productosDisponibles = ref([])
const cargandoProductos = ref(false)

const tipoFiltro = ref('fecha_unica')
const fechaUnica = ref('')
const fechaInicio = ref('')
const fechaFin = ref('')

const cargando = ref(false)
const reporte = ref([])
const totales = ref({ unidades: 0, ventas: 0, fechas: 0, productos: 0, detalles: 0 })
const expandidosFechas = ref({})
const expandidosProductos = ref({})
const errorFiltro = ref('')
const advertencia = ref('')

// ==================== COMPUTED ====================
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales

    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s =>
        s.nombre.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id === sucursalId.value)
    return suc?.nombre || ''
})

const haySucursalSeleccionada = computed(() => {
    return sucursalId.value && sucursalId.value !== '' && Number(sucursalId.value) > 0
})

const hayProductoSeleccionado = computed(() => {
    return productoSeleccionado.value && productoSeleccionado.value !== ''
})

const productosFiltrados = computed(() => {
    if (!productosDisponibles.value.length) return []
    if (!productoBusqueda.value) return productosDisponibles.value

    const termino = productoBusqueda.value.toLowerCase()
    return productosDisponibles.value.filter(p =>
        p.toLowerCase().includes(termino)
    )
})

const detallesPlanos = computed(() => {
    const lista = []
    reporte.value.forEach(fechaData => {
        fechaData.productos?.forEach(productoData => {
            productoData.detalles?.forEach(detalle => {
                lista.push({
                    ...detalle,
                    fecha: fechaData.fecha,
                })
            })
        })
    })
    return lista
})

// ==================== ACCIONES ====================
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    cargarProductos()
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    productoSeleccionado.value = ''
    productoBusqueda.value = ''
    productosDisponibles.value = []
    reporte.value = []
    totales.value = { unidades: 0, ventas: 0, fechas: 0, productos: 0, detalles: 0 }
    errorFiltro.value = ''
}

const seleccionarProducto = (producto) => {
    productoSeleccionado.value = producto
    productoBusqueda.value = producto
    mostrarProductos.value = false
}

const limpiarProducto = () => {
    productoSeleccionado.value = ''
    productoBusqueda.value = ''
    mostrarProductos.value = false
}

const cargarProductos = async () => {
    if (!sucursalId.value) return

    cargandoProductos.value = true
    try {
        const params = new URLSearchParams()
        params.append('sucursal_id', sucursalId.value)

        if (tipoFiltro.value === 'fecha_unica' && fechaUnica.value) {
            params.append('fecha_inicio', fechaUnica.value)
            params.append('fecha_fin', fechaUnica.value)
        } else if (tipoFiltro.value === 'rango') {
            if (fechaInicio.value) params.append('fecha_inicio', fechaInicio.value)
            if (fechaFin.value) params.append('fecha_fin', fechaFin.value)
        }

        const response = await axios.get('/gestion/reportes/unidades-ventas/productos', { params })
        if (response.data.success) {
            productosDisponibles.value = response.data.productos || []
        }
    } catch (error) {
        console.error('Error cargando productos:', error)
        productosDisponibles.value = []
    } finally {
        cargandoProductos.value = false
    }
}

const limpiarTodosLosFiltros = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    productoSeleccionado.value = ''
    productoBusqueda.value = ''
    productosDisponibles.value = []
    tipoFiltro.value = 'fecha_unica'
    fechaUnica.value = ''
    fechaInicio.value = ''
    fechaFin.value = ''
    errorFiltro.value = ''
    advertencia.value = ''
    reporte.value = []
    totales.value = { unidades: 0, ventas: 0, fechas: 0, productos: 0, detalles: 0 }
    expandidosFechas.value = {}
    expandidosProductos.value = {}
}

const toggleFecha = (index) => {
    expandidosFechas.value = {
        ...expandidosFechas.value,
        [index]: !expandidosFechas.value[index]
    }
}

const toggleProducto = (fechaIndex, productoIndex) => {
    const key = `${fechaIndex}_${productoIndex}`
    expandidosProductos.value = {
        ...expandidosProductos.value,
        [key]: !expandidosProductos.value[key]
    }
}

const expandirTodo = () => {
    const nuevosExpandidosFechas = {}
    reporte.value.forEach((_, fIdx) => {
        nuevosExpandidosFechas[fIdx] = true
    })
    expandidosFechas.value = nuevosExpandidosFechas
    expandidosProductos.value = {}
}

const contraerTodo = () => {
    expandidosFechas.value = {}
    expandidosProductos.value = {}
}

const limpiarFechas = () => {
    fechaUnica.value = ''
    fechaInicio.value = ''
    fechaFin.value = ''
    errorFiltro.value = ''
}

const cargarReporte = async () => {
    if (!sucursalId.value) {
        errorFiltro.value = 'Seleccione una sucursal'
        return
    }

    if (tipoFiltro.value === 'fecha_unica') {
        if (!fechaUnica.value) {
            errorFiltro.value = 'Seleccione una fecha'
            return
        }
    } else {
        if (!fechaInicio.value || !fechaFin.value) {
            errorFiltro.value = 'Seleccione fecha de inicio y fin'
            return
        }
        if (fechaInicio.value > fechaFin.value) {
            errorFiltro.value = 'La fecha de inicio no puede ser mayor a la fecha de fin'
            return
        }
    }

    errorFiltro.value = ''
    advertencia.value = ''
    cargando.value = true

    try {
        const params = new URLSearchParams()
        params.append('sucursal_id', sucursalId.value)

        if (tipoFiltro.value === 'fecha_unica') {
            params.append('fecha_inicio', fechaUnica.value)
            params.append('fecha_fin', fechaUnica.value)
        } else {
            params.append('fecha_inicio', fechaInicio.value)
            params.append('fecha_fin', fechaFin.value)
        }

        if (productoSeleccionado.value && productoSeleccionado.value !== '') {
            params.append('producto', productoSeleccionado.value)
        }

        const response = await axios.get('/gestion/reportes/unidades-ventas/data', { params })

        if (response.data.success) {
            reporte.value = response.data.reporte || []
            totales.value = response.data.totales || { unidades: 0, ventas: 0, fechas: 0, productos: 0, detalles: 0 }
            expandidosFechas.value = {}
            expandidosProductos.value = {}

            if (response.data.advertencia) {
                advertencia.value = response.data.advertencia.mensaje
                setTimeout(() => { advertencia.value = '' }, 5000)
            }
        } else {
            errorFiltro.value = response.data.error || 'Error al cargar el reporte'
            reporte.value = []
        }
    } catch (error) {
        console.error('Error cargando reporte:', error)
        errorFiltro.value = error.response?.data?.error || 'Error al cargar el reporte'
        reporte.value = []
    } finally {
        cargando.value = false
    }
}

const volver = () => {
    router.get('/oficial')
}

const handleClickOutside = (event) => {
    const container = document.querySelector('.sucursal-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarSucursales.value = false
    }

    const prodContainer = document.querySelector('.producto-autocomplete')
    if (prodContainer && !prodContainer.contains(event.target)) {
        mostrarProductos.value = false
    }
}

const formatearNumero = (numero) => {
    if (numero === undefined || numero === null) return '0'
    return parseFloat(numero).toLocaleString('es-BO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3
    })
}

const formatearMoneda = (numero) => {
    if (numero === undefined || numero === null) return '0.00'
    return parseFloat(numero).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

const getNombreProducto = (detalle) => {
    return detalle.descripcion_producto
        || detalle.detalle_producto
        || detalle.producto
        || 'Sin producto'
}

watch([fechaUnica, fechaInicio, fechaFin, tipoFiltro], () => {
    if (sucursalId.value) {
        cargarProductos()
    }
})

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-chart-line text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Ventas por Producto</h1>
                            <p class="text-xs text-gray-500">Detalle de unidades vendidas por día</p>
                        </div>
                    </div>
                    <div class="flex gap-1.5">
                        <button v-if="!hayProductoSeleccionado" @click="expandirTodo" class="px-2.5 py-1.5 text-[10px] bg-primary-100 hover:bg-primary-200 text-primary-700 rounded-md transition flex items-center gap-1">
                            <i class="fas fa-expand-alt text-[9px]"></i>
                            Expandir
                        </button>
                        <button v-if="!hayProductoSeleccionado" @click="contraerTodo" class="px-2.5 py-1.5 text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md transition flex items-center gap-1">
                            <i class="fas fa-compress-alt text-[9px]"></i>
                            Contraer
                        </button>
                        <button @click="volver" class="px-2.5 py-1.5 text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md transition flex items-center gap-1">
                            <i class="fas fa-arrow-left text-[9px]"></i>
                            Volver
                        </button>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">

                        <!-- Sucursal -->
                        <div class="sucursal-autocomplete flex-1 min-w-[160px] max-w-[200px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Sucursal</label>
                            <div class="relative">
                                <input
                                    type="text"
                                    v-model="sucursalBusqueda"
                                    @focus="mostrarSucursales = true"
                                    @input="mostrarSucursales = true"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-6 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="Seleccione..."
                                    autocomplete="off"
                                />
                                <button v-if="sucursalBusqueda" @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                    <div v-for="suc in sucursalesDisponibles" :key="suc.id"
                                        @mousedown.prevent="seleccionarSucursal(suc)"
                                        class="px-2.5 py-1.5 cursor-pointer hover:bg-primary-50 text-xs flex justify-between items-center border-b border-gray-100 last:border-0"
                                        :class="sucursalId === suc.id ? 'bg-primary-50' : ''">
                                        <span class="truncate">{{ suc.nombre }}</span>
                                        <i v-if="sucursalId === suc.id" class="fas fa-check-circle text-[10px] text-primary-600"></i>
                                    </div>
                                </div>
                            </div>
                            <div v-if="sucursalId && sucursalNombre" class="mt-0.5">
                                <span class="text-[10px] text-primary-600 font-medium">
                                    <i class="fas fa-check-circle text-[8px]"></i> {{ sucursalNombre }}
                                </span>
                            </div>
                        </div>

                        <!-- Producto -->
                        <div class="producto-autocomplete flex-1 min-w-[180px] max-w-[240px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                                Producto <span class="text-gray-400 font-normal">(opcional)</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="text"
                                    v-model="productoBusqueda"
                                    @focus="mostrarProductos = true; !productosDisponibles.length && sucursalId && cargarProductos()"
                                    @input="mostrarProductos = true"
                                    :disabled="!sucursalId"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-6 focus:ring-primary-500 focus:border-primary-500 outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    placeholder="Todos los productos..."
                                    autocomplete="off"
                                />
                                <button v-if="productoBusqueda" @click="limpiarProducto"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                <div v-if="mostrarProductos && sucursalId"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                    <div v-if="cargandoProductos" class="px-3 py-2 text-center text-gray-400 text-xs">
                                        <i class="fas fa-spinner fa-spin"></i> Cargando...
                                    </div>
                                    <template v-else-if="productosFiltrados.length > 0">
                                        <div @mousedown.prevent="limpiarProducto()"
                                            class="px-2.5 py-1.5 cursor-pointer hover:bg-primary-50 text-xs flex items-center gap-1.5 border-b border-gray-100 font-medium"
                                            :class="!productoSeleccionado ? 'bg-primary-50' : ''">
                                            <i class="fas fa-list text-[10px] text-primary-600"></i>
                                            <span>Todos los productos</span>
                                            <i v-if="!productoSeleccionado" class="fas fa-check-circle text-[10px] ml-auto text-primary-600"></i>
                                        </div>
                                        <div v-for="(prod, idx) in productosFiltrados" :key="idx"
                                            @mousedown.prevent="seleccionarProducto(prod)"
                                            class="px-2.5 py-1.5 cursor-pointer hover:bg-primary-50 text-xs flex justify-between items-center border-b border-gray-100 last:border-0"
                                            :class="productoSeleccionado === prod ? 'bg-primary-50' : ''">
                                            <span class="truncate">{{ prod }}</span>
                                            <i v-if="productoSeleccionado === prod" class="fas fa-check-circle text-[10px] ml-2 flex-shrink-0 text-primary-600"></i>
                                        </div>
                                    </template>
                                    <div v-else class="px-3 py-2 text-center text-gray-400 text-[10px]">
                                        <i class="fas fa-info-circle"></i> Sin productos
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tipo Filtro -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Tipo</label>
                            <div class="flex items-center bg-gray-100 p-0.5 rounded-md border border-gray-200">
                                <button @click="tipoFiltro = 'fecha_unica'; limpiarFechas()"
                                    class="px-2.5 py-1 text-[10px] rounded transition font-medium"
                                    :class="tipoFiltro === 'fecha_unica' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'">
                                    Única
                                </button>
                                <button @click="tipoFiltro = 'rango'; limpiarFechas()"
                                    class="px-2.5 py-1 text-[10px] rounded transition font-medium"
                                    :class="tipoFiltro === 'rango' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'">
                                    Rango
                                </button>
                            </div>
                        </div>

                        <!-- Fechas -->
                        <div v-if="tipoFiltro === 'fecha_unica'">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha</label>
                            <input type="date" v-model="fechaUnica"
                                class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                        </div>
                        <div v-else class="flex gap-1.5">
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Desde</label>
                                <input type="date" v-model="fechaInicio"
                                    class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Hasta</label>
                                <input type="date" v-model="fechaFin"
                                    class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-1.5 ml-auto">
                            <button @click="cargarReporte" :disabled="cargando || !sucursalId"
                                class="px-3 py-1.5 text-xs font-medium text-white rounded-md transition disabled:opacity-50 flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700">
                                <i v-if="cargando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-search text-[10px]"></i>
                                <span>Buscar</span>
                            </button>
                            <button @click="limpiarTodosLosFiltros"
                                class="px-3 py-1.5 text-xs font-medium rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300 transition flex items-center gap-1.5">
                                <i class="fas fa-eraser text-[10px]"></i>
                                <span>Limpiar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Chip producto activo -->
                    <div v-if="productoSeleccionado" class="mt-2 flex items-center gap-1.5 text-[10px]">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-primary-50 text-primary-700 border border-primary-200">
                            <i class="fas fa-box text-[9px]"></i>
                            <span class="truncate max-w-[300px]" :title="productoSeleccionado">{{ productoSeleccionado }}</span>
                            <button @click="limpiarProducto" class="hover:text-red-600 ml-1">
                                <i class="fas fa-times text-[9px]"></i>
                            </button>
                        </span>
                    </div>

                    <!-- Mensajes -->
                    <div v-if="errorFiltro" class="mt-2 text-[10px] text-red-600 font-medium flex items-center gap-1">
                        <i class="fas fa-exclamation-circle"></i> {{ errorFiltro }}
                    </div>
                    <div v-if="advertencia" class="mt-2 text-[10px] text-amber-600 font-medium flex items-center gap-1">
                        <i class="fas fa-clock"></i> {{ advertencia }}
                    </div>
                </div>

                <!-- ==================== SIN SUCURSAL ==================== -->
                <div v-if="!haySucursalSeleccionada" class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-calendar-alt text-3xl mb-2 block"></i>
                    <p class="text-sm font-medium text-gray-600">Seleccione una Sucursal y una Fecha</p>
                    <p class="text-xs text-gray-400 mt-1">Use los campos de búsqueda arriba para filtrar</p>
                </div>

                <!-- ==================== CARGANDO ==================== -->
                <div v-if="cargando && haySucursalSeleccionada" class="bg-white rounded-xl shadow-sm py-12 text-center">
                    <i class="fas fa-spinner fa-spin text-3xl text-primary-500 mb-3 block"></i>
                    <p class="text-gray-500 text-sm">Cargando reporte...</p>
                </div>

                <!-- ==================== TOTALES ==================== -->
                <div v-else-if="reporte.length > 0 && haySucursalSeleccionada" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border-l-2 border-primary-500">
                        <p class="text-[9px] text-gray-400 uppercase tracking-wide">Unidades</p>
                        <p class="text-base font-bold text-primary-700">{{ formatearNumero(totales.unidades) }}</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border-l-2 border-emerald-500">
                        <p class="text-[9px] text-gray-400 uppercase tracking-wide">Total Bs</p>
                        <p class="text-base font-bold text-emerald-600">Bs {{ formatearMoneda(totales.ventas) }}</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border-l-2 border-blue-500">
                        <p class="text-[9px] text-gray-400 uppercase tracking-wide">Días</p>
                        <p class="text-base font-bold text-blue-600">{{ totales.fechas }}</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border-l-2 border-purple-500">
                        <p class="text-[9px] text-gray-400 uppercase tracking-wide">Productos</p>
                        <p class="text-base font-bold text-purple-600">{{ totales.productos }}</p>
                    </div>
                </div>

                <!-- ==================================================== -->
                <!-- MODO PRODUCTO SELECCIONADO: FECHA COMO COLUMNA      -->
                <!-- ==================================================== -->
                <div v-if="reporte.length > 0 && !cargando && haySucursalSeleccionada && hayProductoSeleccionado" 
                    class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                    
                    <div class="px-3 py-2 bg-primary-50 border-b border-primary-100 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-box text-primary-600 text-sm"></i>
                            <span class="font-semibold text-gray-800 text-sm truncate">{{ productoSeleccionado }}</span>
                            <span class="text-[9px] text-gray-500 bg-white px-2 py-0.5 rounded-full border border-primary-200">
                                {{ detallesPlanos.length }} venta(s)
                            </span>
                        </div>
                    </div>

                    <!-- DESKTOP -->
                    <div class="hidden sm:block overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase text-[9px]">Fecha</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase text-[9px]">Factura</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase text-[9px] w-20">Cant.</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase text-[9px] w-24">Precio</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase text-[9px] w-28">Subtotal</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase text-[9px]">Vendedor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(detalle, idx) in detallesPlanos" :key="idx" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 text-gray-700 whitespace-nowrap">
                                        <i class="far fa-calendar-alt text-primary-500 text-[10px] mr-1"></i>
                                        {{ detalle.fecha }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-gray-800 font-medium">
                                        #{{ detalle.numero_factura }}
                                    </td>
                                    <td class="px-3 py-2 text-center text-gray-700 tabular-nums">
                                        {{ formatearNumero(detalle.unidades) }}
                                    </td>
                                    <td class="px-3 py-2 text-center text-gray-600 tabular-nums">
                                        {{ formatearMoneda(detalle.precio_unitario) }}
                                    </td>
                                    <td class="px-3 py-2 text-center font-bold text-primary-700 tabular-nums">
                                        {{ formatearMoneda(detalle.total_bolivianos) }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-600 truncate max-w-[180px]" :title="detalle.operador">
                                        {{ detalle.operador || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50 sticky bottom-0 border-t-2 border-primary-200">
                                <tr>
                                    <td colspan="2" class="px-3 py-2 font-bold text-gray-800 text-sm">
                                        TOTAL
                                    </td>
                                    <td class="px-3 py-2 text-center font-bold text-gray-800">
                                        {{ formatearNumero(totales.unidades) }}
                                    </td>
                                    <td class="px-3 py-2"></td>
                                    <td class="px-3 py-2 text-center font-bold text-primary-700 text-sm">
                                        Bs {{ formatearMoneda(totales.ventas) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- MOBILE -->
                    <div class="sm:hidden p-2 space-y-2">
                        <div v-for="(detalle, idx) in detallesPlanos" :key="idx" 
                            class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                            <div class="flex justify-between items-start mb-1.5">
                                <div>
                                    <span class="font-mono font-bold text-primary-700 text-xs">#{{ detalle.numero_factura }}</span>
                                    <div class="text-[9px] text-gray-500 mt-0.5">
                                        <i class="far fa-calendar-alt text-primary-500 text-[8px] mr-1"></i>
                                        {{ detalle.fecha }}
                                    </div>
                                </div>
                                <span class="font-bold text-primary-700 text-sm">Bs {{ formatearMoneda(detalle.total_bolivianos) }}</span>
                            </div>
                            <div class="space-y-1 text-[10px]">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Cant:</span>
                                    <span class="text-gray-700 font-medium">{{ formatearNumero(detalle.unidades) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Precio:</span>
                                    <span class="text-gray-700 font-medium">Bs {{ formatearMoneda(detalle.precio_unitario) }}</span>
                                </div>
                                <div class="flex justify-between pt-1 border-t border-gray-100">
                                    <span class="text-gray-400">Vendedor:</span>
                                    <span class="text-gray-700 font-medium text-right truncate max-w-[140px]">{{ detalle.operador || '—' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================================================== -->
                <!-- MODO NORMAL: AGRUPADO POR FECHA                     -->
                <!-- ==================================================== -->
                <div v-else-if="reporte.length > 0 && !cargando && haySucursalSeleccionada && !hayProductoSeleccionado" 
                    class="space-y-2">
                    <div v-for="(fechaData, fechaIndex) in reporte" :key="fechaIndex" 
                        class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                        
                        <!-- CABECERA FECHA -->
                        <div @click="toggleFecha(fechaIndex)"
                            class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 bg-primary-50 border-b border-primary-100 cursor-pointer hover:bg-primary-100 transition">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <i :class="expandidosFechas[fechaIndex] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-primary-600 text-[10px] flex-shrink-0"></i>
                                <div class="w-7 h-7 rounded-lg bg-primary-600 text-white flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-calendar-alt text-xs"></i>
                                </div>
                                <div class="min-w-0">
                                    <span class="font-bold text-gray-800 text-sm truncate block">{{ fechaData.fecha }}</span>
                                    <span class="text-[9px] text-gray-500">{{ fechaData.productos.length }} producto(s)</span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span class="text-sm font-bold text-primary-700 block">Bs {{ formatearMoneda(fechaData.total_ventas_fecha) }}</span>
                                <span class="text-[9px] text-gray-500">{{ formatearNumero(fechaData.total_unidades_fecha) }} und</span>
                            </div>
                        </div>

                        <!-- PRODUCTOS -->
                        <div v-if="expandidosFechas[fechaIndex]">
                            <div v-for="(productoData, productoIndex) in fechaData.productos" :key="productoIndex"
                                class="border-b border-gray-100 last:border-b-0">

                                <!-- CABECERA PRODUCTO -->
                                <div @click="toggleProducto(fechaIndex, productoIndex)"
                                    class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 pl-8 hover:bg-gray-50 cursor-pointer transition">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <i :class="expandidosProductos[`${fechaIndex}_${productoIndex}`] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-gray-400 text-[9px] flex-shrink-0"></i>
                                        <div class="w-6 h-6 rounded-md bg-primary-100 text-primary-600 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-box text-[10px]"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <span class="font-medium text-gray-800 text-xs truncate block" :title="productoData.producto">{{ productoData.producto }}</span>
                                            <span class="text-[9px] text-gray-500">{{ productoData.detalles.length }} venta(s)</span>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <span class="text-xs font-bold text-primary-700 block">Bs {{ formatearMoneda(productoData.total_ventas_producto) }}</span>
                                        <span class="text-[9px] text-gray-500">{{ formatearNumero(productoData.total_unidades_producto) }} und</span>
                                    </div>
                                </div>

                                <!-- DETALLE -->
                                <div v-if="expandidosProductos[`${fechaIndex}_${productoIndex}`]" class="bg-gray-50 px-3 py-2">
                                    <!-- DESKTOP -->
                                    <div class="hidden sm:block overflow-x-auto">
                                        <table class="min-w-full text-xs">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase text-[9px]">Factura</th>
                                                    <th class="px-2 py-1.5 text-center font-medium text-gray-500 uppercase text-[9px] w-16">Cant.</th>
                                                    <th class="px-2 py-1.5 text-center font-medium text-gray-500 uppercase text-[9px] w-20">Precio</th>
                                                    <th class="px-2 py-1.5 text-center font-medium text-gray-500 uppercase text-[9px] w-24">Subtotal</th>
                                                    <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase text-[9px]">Vendedor</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-for="detalle in productoData.detalles" :key="detalle.numero_factura" class="hover:bg-gray-100 transition">
                                                    <td class="px-2 py-1.5 font-mono text-gray-800 font-medium">
                                                        #{{ detalle.numero_factura }}
                                                    </td>
                                                    <td class="px-2 py-1.5 text-center text-gray-700 tabular-nums">
                                                        {{ formatearNumero(detalle.unidades) }}
                                                    </td>
                                                    <td class="px-2 py-1.5 text-center text-gray-600 tabular-nums">
                                                        {{ formatearMoneda(detalle.precio_unitario) }}
                                                    </td>
                                                    <td class="px-2 py-1.5 text-center font-bold text-primary-700 tabular-nums">
                                                        {{ formatearMoneda(detalle.total_bolivianos) }}
                                                    </td>
                                                    <td class="px-2 py-1.5 text-gray-600 truncate max-w-[150px]" :title="detalle.operador">
                                                        {{ detalle.operador || '—' }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- MOBILE -->
                                    <div class="sm:hidden space-y-2">
                                        <div v-for="detalle in productoData.detalles" :key="detalle.numero_factura"
                                            class="bg-white rounded-lg p-2.5 shadow-sm border border-gray-200">
                                            <div class="flex justify-between items-start mb-1.5">
                                                <span class="font-mono font-bold text-primary-700 text-xs">#{{ detalle.numero_factura }}</span>
                                                <span class="font-bold text-primary-700 text-sm">Bs {{ formatearMoneda(detalle.total_bolivianos) }}</span>
                                            </div>
                                            <div class="space-y-1 text-[10px]">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-400">Cant:</span>
                                                    <span class="text-gray-700 font-medium">{{ formatearNumero(detalle.unidades) }}</span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-400">Precio:</span>
                                                    <span class="text-gray-700 font-medium">Bs {{ formatearMoneda(detalle.precio_unitario) }}</span>
                                                </div>
                                                <div class="flex justify-between pt-1 border-t border-gray-100">
                                                    <span class="text-gray-400">Vendedor:</span>
                                                    <span class="text-gray-700 font-medium text-right truncate max-w-[140px]">{{ detalle.operador || '—' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== SIN RESULTADOS ==================== -->
                <div v-else-if="!cargando && haySucursalSeleccionada && !errorFiltro && reporte.length === 0" 
                    class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-chart-line text-3xl mb-2 block"></i>
                    <p class="text-sm">No hay ventas con estos filtros</p>
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

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tabular-nums {
    font-variant-numeric: tabular-nums;
}

.overflow-y-auto::-webkit-scrollbar,
.overflow-x-auto::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track,
.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb,
.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover,
.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>