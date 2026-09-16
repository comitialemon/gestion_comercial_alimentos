<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    bitacora: {
        type: Array,
        default: () => []
    },
    paginacion: {
        type: Object,
        default: () => ({
            current_page: 1,
            last_page: 1,
            per_page: 5,
            total: 0,
            from: 0,
            to: 0,
            links: [],
        })
    },
    clientesConPrecios: {
        type: Array,
        default: () => []
    },
    productosHabilitados: {
        type: Array,
        default: () => []
    },
    filtros: {
        type: Object,
        default: () => ({})
    },
    clientesPorPagina: {
        type: Number,
        default: 5
    }
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const handleResize = () => { isMobile.value = window.innerWidth < 640 }

// ==================== FILTROS ====================
const filtros = ref({
    identificador_id: props.filtros?.identificador_id || '',
    producto_id: props.filtros?.producto_id || '',
    fecha_desde: props.filtros?.fecha_desde || '',
    fecha_hasta: props.filtros?.fecha_hasta || '',
})

const aplicandoFiltros = ref(false)

// ==================== AUTOCOMPLETE - CLIENTE ====================
const busquedaCliente = ref('')
const mostrarClientes = ref(false)
const clientesSugeridos = ref([])
const buscandoClientes = ref(false)
let timeoutCliente = null

if (props.filtros?.identificador_id && props.clientesConPrecios?.length) {
    const cli = props.clientesConPrecios.find(c => c.IdIdentificador == props.filtros.identificador_id)
    if (cli) busquedaCliente.value = `${cli.Nombre} - ${cli.CI_NIT}`
}

const buscarClientes = async () => {
    buscandoClientes.value = true
    try {
        const params = new URLSearchParams({ q: busquedaCliente.value || '' })
        const res = await fetch(`/operacion/pedidos/clientes-mayoristas/precios/bitacora/buscar-clientes?${params}`)
        const data = await res.json()
        clientesSugeridos.value = data.clientes || []
    } catch (e) {
        clientesSugeridos.value = []
    } finally {
        buscandoClientes.value = false
    }
}

watch(busquedaCliente, (nuevo) => {
    if (!nuevo) {
        filtros.value.identificador_id = ''
    }
    clearTimeout(timeoutCliente)
    timeoutCliente = setTimeout(buscarClientes, 300)
})

const seleccionarCliente = (cliente) => {
    filtros.value.identificador_id = cliente.IdIdentificador
    busquedaCliente.value = `${cliente.Nombre} - ${cliente.CI_NIT}`
    mostrarClientes.value = false
}

const limpiarCliente = () => {
    filtros.value.identificador_id = ''
    busquedaCliente.value = ''
    clientesSugeridos.value = []
}

// ==================== AUTOCOMPLETE - PRODUCTO ====================
const busquedaProducto = ref('')
const mostrarProductos = ref(false)
const productosSugeridos = ref([])
const buscandoProductos = ref(false)
let timeoutProducto = null

if (props.filtros?.producto_id && props.productosHabilitados?.length) {
    const prod = props.productosHabilitados.find(p => p.IdProducto == props.filtros.producto_id)
    if (prod) busquedaProducto.value = `${prod.Descripcion} - ${prod.Codigo}`
}

const buscarProductos = async () => {
    buscandoProductos.value = true
    try {
        const params = new URLSearchParams({ q: busquedaProducto.value || '' })
        const res = await fetch(`/operacion/pedidos/clientes-mayoristas/precios/bitacora/buscar-productos?${params}`)
        const data = await res.json()
        productosSugeridos.value = data.productos || []
    } catch (e) {
        productosSugeridos.value = []
    } finally {
        buscandoProductos.value = false
    }
}

watch(busquedaProducto, (nuevo) => {
    if (!nuevo) {
        filtros.value.producto_id = ''
    }
    clearTimeout(timeoutProducto)
    timeoutProducto = setTimeout(buscarProductos, 300)
})

const seleccionarProducto = (producto) => {
    filtros.value.producto_id = producto.IdProducto
    busquedaProducto.value = `${producto.Descripcion} - ${producto.Codigo}`
    mostrarProductos.value = false
}

const limpiarProducto = () => {
    filtros.value.producto_id = ''
    busquedaProducto.value = ''
    productosSugeridos.value = []
}

// ==================== WATCHERS ====================
watch(() => filtros.value.identificador_id, (nuevo, anterior) => {
    if (nuevo !== anterior) aplicarFiltrosAutomatico()
})

watch(() => filtros.value.producto_id, (nuevo, anterior) => {
    if (nuevo !== anterior) aplicarFiltrosAutomatico()
})

let timeoutFechaDesde = null
watch(() => filtros.value.fecha_desde, (nuevo, anterior) => {
    if (nuevo === anterior) return
    clearTimeout(timeoutFechaDesde)
    timeoutFechaDesde = setTimeout(() => { aplicarFiltrosAutomatico() }, 500)
})

let timeoutFechaHasta = null
watch(() => filtros.value.fecha_hasta, (nuevo, anterior) => {
    if (nuevo === anterior) return
    clearTimeout(timeoutFechaHasta)
    timeoutFechaHasta = setTimeout(() => { aplicarFiltrosAutomatico() }, 500)
})

// ==================== EXPANSIÓN - ACORDEONES ====================
const clientesExpandidos = ref({})
const productosExpandidos = ref({})

const toggleCliente = (clienteId) => {
    clientesExpandidos.value = {
        ...clientesExpandidos.value,
        [clienteId]: !clientesExpandidos.value[clienteId]
    }
}

const estaClienteExpandido = (clienteId) => {
    return !!clientesExpandidos.value[clienteId]
}

const toggleProducto = (clienteId, productoId) => {
    const key = `${clienteId}_${productoId}`
    productosExpandidos.value = {
        ...productosExpandidos.value,
        [key]: !productosExpandidos.value[key]
    }
}

const estaExpandido = (clienteId, productoId) => {
    return !!productosExpandidos.value[`${clienteId}_${productoId}`]
}

const expandirTodo = () => {
    const clientes = {}
    const productos = {}
    bitacoraAgrupada.value.forEach(cliente => {
        clientes[cliente.id] = true
        cliente.productos.forEach(producto => {
            productos[`${cliente.id}_${producto.id}`] = true
        })
    })
    clientesExpandidos.value = clientes
    productosExpandidos.value = productos
}

const contraerTodo = () => {
    clientesExpandidos.value = {}
    productosExpandidos.value = {}
}

// ==================== APLICAR FILTROS ====================
const aplicarFiltrosAutomatico = () => {
    if (aplicandoFiltros.value) return
    aplicandoFiltros.value = true

    const params = new URLSearchParams()
    if (filtros.value.identificador_id) params.append('identificador_id', filtros.value.identificador_id)
    if (filtros.value.producto_id) params.append('producto_id', filtros.value.producto_id)
    if (filtros.value.fecha_desde) params.append('fecha_desde', filtros.value.fecha_desde)
    if (filtros.value.fecha_hasta) params.append('fecha_hasta', filtros.value.fecha_hasta)
    params.append('page', '1')

    router.visit(`/operacion/pedidos/clientes-mayoristas/precios/bitacora?${params.toString()}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            aplicandoFiltros.value = false
            contraerTodo()
        },
        onError: () => {
            aplicandoFiltros.value = false
        }
    })
}

const limpiarFiltros = () => {
    filtros.value = {
        identificador_id: '',
        producto_id: '',
        fecha_desde: '',
        fecha_hasta: '',
    }
    busquedaCliente.value = ''
    busquedaProducto.value = ''
    clientesSugeridos.value = []
    productosSugeridos.value = []
}

// ==================== EXPORTAR PDF ====================
const exportarPdf = () => {
    const params = new URLSearchParams()
    if (filtros.value.identificador_id) params.append('identificador_id', filtros.value.identificador_id)
    if (filtros.value.producto_id) params.append('producto_id', filtros.value.producto_id)
    if (filtros.value.fecha_desde) params.append('fecha_desde', filtros.value.fecha_desde)
    if (filtros.value.fecha_hasta) params.append('fecha_hasta', filtros.value.fecha_hasta)

    const url = `/operacion/pedidos/clientes-mayoristas/precios/bitacora/exportar-pdf?${params.toString()}`
    window.open(url, '_blank')
}

// ==================== AGRUPACIÓN ====================
const bitacoraAgrupada = computed(() => {
    const grupos = {}

    props.bitacora.forEach(registro => {
        const clienteId = registro.IdIdentificador
        const clienteNombre = registro.IdentificadorNombre
        const ciNit = registro.CI_NIT

        if (!grupos[clienteId]) {
            grupos[clienteId] = {
                id: clienteId,
                nombre: clienteNombre,
                ci_nit: ciNit,
                productos: {},
                totalCambios: 0,
            }
        }

        const productoId = registro.IdProducto
        if (!grupos[clienteId].productos[productoId]) {
            grupos[clienteId].productos[productoId] = {
                id: productoId,
                nombre: registro.ProductoNombre,
                codigo: registro.ProductoCodigo,
                registros: [],
            }
        }

        grupos[clienteId].productos[productoId].registros.push(registro)
        grupos[clienteId].totalCambios++
    })

    return Object.values(grupos)
        .map(cliente => ({
            ...cliente,
            productos: Object.values(cliente.productos)
                .map(producto => ({
                    ...producto,
                    registros: producto.registros.sort((a, b) =>
                        new Date(b.FechaCambio) - new Date(a.FechaCambio)
                    ),
                    ultimaFecha: producto.registros.reduce((max, r) => {
                        const fecha = new Date(r.FechaCambio)
                        return fecha > max ? fecha : max
                    }, new Date(0)),
                }))
                .sort((a, b) => b.ultimaFecha - a.ultimaFecha),
        }))
        .sort((a, b) => (a.nombre || '').localeCompare(b.nombre || '', 'es', { sensitivity: 'base' }))
})

// ==================== PAGINACIÓN ====================
const irAPagina = (url) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                contraerTodo()
                window.scrollTo({ top: 0, behavior: 'smooth' })
            }
        })
    }
}

// ==================== HELPERS ====================
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    const date = new Date(fecha)
    return date.toLocaleDateString('es-BO', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    })
}

const formatearFechaCorta = (fecha) => {
    if (!fecha) return '-'
    const date = new Date(fecha)
    return date.toLocaleDateString('es-BO', {
        day: '2-digit', month: '2-digit', year: '2-digit'
    })
}

const obtenerColorPrecio = (precioAnterior, precioNuevo) => {
    if (precioAnterior === 0) return 'text-emerald-600'
    if (precioNuevo === 0) return 'text-red-600'
    if (precioNuevo > precioAnterior) return 'text-orange-500'
    if (precioNuevo < precioAnterior) return 'text-blue-600'
    return 'text-gray-600'
}

const obtenerIconoPrecio = (precioAnterior, precioNuevo) => {
    if (precioAnterior === 0) return 'fa-plus-circle text-emerald-500'
    if (precioNuevo === 0) return 'fa-trash-alt text-red-500'
    if (precioNuevo > precioAnterior) return 'fa-arrow-up text-orange-500'
    if (precioNuevo < precioAnterior) return 'fa-arrow-down text-blue-500'
    return 'fa-minus text-gray-400'
}

const obtenerBadgeEstado = (precioAnterior, precioNuevo) => {
    if (precioAnterior === 0) return 'bg-emerald-100 text-emerald-700 border-emerald-200'
    if (precioNuevo === 0) return 'bg-red-100 text-red-700 border-red-200'
    if (precioNuevo > precioAnterior) return 'bg-orange-100 text-orange-700 border-orange-200'
    if (precioNuevo < precioAnterior) return 'bg-blue-100 text-blue-700 border-blue-200'
    return 'bg-gray-100 text-gray-700 border-gray-200'
}

const obtenerTextoEstado = (precioAnterior, precioNuevo) => {
    if (precioAnterior === 0) return 'Creación'
    if (precioNuevo === 0) return 'Eliminación'
    if (precioNuevo > precioAnterior) return 'Aumento'
    if (precioNuevo < precioAnterior) return 'Disminución'
    return 'Sin cambio'
}

const getResumenProducto = (producto) => {
    const ultimo = producto.registros[0]
    if (!ultimo) return null
    return {
        precioActual: ultimo.PrecioNuevo,
        fecha: ultimo.FechaCambio,
        totalCambios: producto.registros.length,
    }
}

const irAPrecios = () => {
    router.visit('/operacion/pedidos/clientes-mayoristas/precios')
}

const cerrarAutocompletar = () => {
    setTimeout(() => {
        mostrarClientes.value = false
        mostrarProductos.value = false
    }, 200)
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    if (timeoutFechaDesde) clearTimeout(timeoutFechaDesde)
    if (timeoutFechaHasta) clearTimeout(timeoutFechaHasta)
    if (timeoutCliente) clearTimeout(timeoutCliente)
    if (timeoutProducto) clearTimeout(timeoutProducto)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-7xl mx-auto">

                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-history text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Bitácora de Precios</h1>
                            <p class="text-xs text-gray-500">
                                Historial por cliente y producto •
                                <span class="font-medium text-primary-600">{{ clientesPorPagina }} clientes por página</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-1.5">
                        <button
                            @click="exportarPdf"
                            :disabled="bitacora.length === 0"
                            class="px-3 py-1.5 bg-emerald-600 text-white rounded-md text-xs font-medium hover:bg-emerald-700 transition flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <i class="fas fa-file-pdf text-[10px]"></i>
                            Exportar PDF
                        </button>
                        <button
                            @click="irAPrecios"
                            class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1.5"
                        >
                            <i class="fas fa-arrow-left text-[10px]"></i>
                            Volver a Precios
                        </button>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Cliente -->
                        <div class="flex-1 min-w-[180px] max-w-[240px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Cliente</label>
                            <div class="relative">
                                <div class="flex">
                                    <input
                                        type="text"
                                        v-model="busquedaCliente"
                                        @focus="mostrarClientes = true; buscarClientes()"
                                        @blur="cerrarAutocompletar"
                                        placeholder="Buscar cliente..."
                                        class="flex-1 border border-gray-300 rounded-l-md px-2.5 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    />
                                    <button
                                        v-if="filtros.identificador_id"
                                        @click="limpiarCliente"
                                        class="px-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r-md hover:bg-gray-200 text-gray-500 text-[10px]"
                                        type="button"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div
                                    v-if="mostrarClientes && clientesSugeridos.length > 0"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div
                                        v-for="cliente in clientesSugeridos"
                                        :key="cliente.IdIdentificador"
                                        @mousedown.prevent="seleccionarCliente(cliente)"
                                        class="px-2.5 py-1.5 hover:bg-primary-50 cursor-pointer border-b border-gray-100 last:border-b-0 text-xs text-gray-700"
                                    >
                                        {{ cliente.Nombre }} - {{ cliente.CI_NIT }}
                                    </div>
                                </div>
                                <div
                                    v-else-if="mostrarClientes && busquedaCliente && !buscandoClientes"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center"
                                >
                                    <p class="text-[10px] text-gray-400">Sin resultados</p>
                                </div>
                            </div>
                        </div>

                        <!-- Producto -->
                        <div class="flex-1 min-w-[180px] max-w-[240px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Producto</label>
                            <div class="relative">
                                <div class="flex">
                                    <input
                                        type="text"
                                        v-model="busquedaProducto"
                                        @focus="mostrarProductos = true; buscarProductos()"
                                        @blur="cerrarAutocompletar"
                                        placeholder="Buscar producto..."
                                        class="flex-1 border border-gray-300 rounded-l-md px-2.5 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    />
                                    <button
                                        v-if="filtros.producto_id"
                                        @click="limpiarProducto"
                                        class="px-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r-md hover:bg-gray-200 text-gray-500 text-[10px]"
                                        type="button"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div
                                    v-if="mostrarProductos && productosSugeridos.length > 0"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div
                                        v-for="producto in productosSugeridos"
                                        :key="producto.IdProducto"
                                        @mousedown.prevent="seleccionarProducto(producto)"
                                        class="px-2.5 py-1.5 hover:bg-primary-50 cursor-pointer border-b border-gray-100 last:border-b-0 text-xs text-gray-700"
                                    >
                                        {{ producto.Descripcion }} - {{ producto.Codigo }}
                                    </div>
                                </div>
                                <div
                                    v-else-if="mostrarProductos && busquedaProducto && !buscandoProductos"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center"
                                >
                                    <p class="text-[10px] text-gray-400">Sin resultados</p>
                                </div>
                            </div>
                        </div>

                        <!-- Fecha Desde -->
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Desde:</label>
                            <input
                                type="date"
                                v-model="filtros.fecha_desde"
                                class="w-32 border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                        </div>

                        <!-- Fecha Hasta -->
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Hasta:</label>
                            <input
                                type="date"
                                v-model="filtros.fecha_hasta"
                                class="w-32 border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                        </div>

                        <!-- Botones -->
                        <div class="flex items-end gap-1.5 ml-auto">
                            <button
                                @click="limpiarFiltros"
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1.5"
                            >
                                <i class="fas fa-eraser text-[10px]"></i>
                                Limpiar
                            </button>
                            <span v-if="aplicandoFiltros" class="text-[10px] text-gray-400 flex items-center gap-1">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ==================== SIN DATOS ==================== -->
                <div v-if="bitacora.length === 0" class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                    <p class="text-sm">No hay registros en la bitácora</p>
                </div>

                <!-- ==================== BITÁCORA ==================== -->
                <div v-else class="space-y-3">
                    <!-- Barra de acciones -->
                    <div class="bg-white rounded-xl shadow-sm px-3 py-2 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-3 text-[11px] text-gray-600">
                            <span class="flex items-center gap-1">
                                <i class="fas fa-users text-primary-500"></i>
                                <strong>{{ paginacion.total }}</strong> cliente(s)
                            </span>
                            <span class="text-gray-300">|</span>
                            <span class="flex items-center gap-1">
                                <i class="fas fa-file-alt text-primary-500"></i>
                                Página <strong>{{ paginacion.current_page }}</strong> de <strong>{{ paginacion.last_page }}</strong>
                            </span>
                        </div>
                        <div class="flex gap-1.5">
                            <button
                                @click="expandirTodo"
                                class="text-[10px] bg-primary-100 hover:bg-primary-200 text-primary-700 px-2.5 py-1 rounded-md transition flex items-center gap-1"
                            >
                                <i class="fas fa-expand-alt text-[8px]"></i>
                                Expandir todo
                            </button>
                            <button
                                @click="contraerTodo"
                                class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-700 px-2.5 py-1 rounded-md transition flex items-center gap-1"
                            >
                                <i class="fas fa-compress-alt text-[8px]"></i>
                                Contraer todo
                            </button>
                        </div>
                    </div>

                    <!-- Por cada CLIENTE -->
                    <div
                        v-for="cliente in bitacoraAgrupada"
                        :key="cliente.id"
                        class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200"
                    >
                        <!-- Header del CLIENTE -->
                        <button
                            @click="toggleCliente(cliente.id)"
                            class="w-full bg-gradient-to-r from-primary-50 to-primary-100/40 px-3 py-2.5 border-b border-primary-100 flex items-center justify-between gap-2 hover:from-primary-100/70 transition-all text-left"
                        >
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <div class="w-6 h-6 rounded-md bg-primary-600 text-white flex items-center justify-center flex-shrink-0 transition-transform duration-200"
                                    :class="estaClienteExpandido(cliente.id) ? 'rotate-90' : ''">
                                    <i class="fas fa-chevron-right text-[9px]"></i>
                                </div>
                                <div class="w-9 h-9 bg-white rounded-full flex items-center justify-center shadow-sm flex-shrink-0 border border-primary-200">
                                    <span class="text-primary-600 font-bold text-xs">
                                        {{ cliente.nombre?.charAt(0)?.toUpperCase() || '?' }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2 class="font-bold text-gray-800 text-sm truncate">
                                        {{ cliente.nombre }}
                                    </h2>
                                    <div class="flex items-center gap-2 text-[9px] text-gray-500 flex-wrap">
                                        <span v-if="cliente.ci_nit" class="font-mono">
                                            <i class="fas fa-id-card text-[8px] mr-0.5"></i>
                                            CI: {{ cliente.ci_nit }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <span class="text-[9px] bg-white text-primary-700 px-2 py-0.5 rounded-full font-medium border border-primary-200">
                                    <i class="fas fa-box text-[8px] mr-0.5"></i>
                                    {{ Object.keys(cliente.productos).length }}
                                </span>
                                <span class="text-[9px] bg-white text-primary-700 px-2 py-0.5 rounded-full font-medium border border-primary-200">
                                    <i class="fas fa-history text-[8px] mr-0.5"></i>
                                    {{ cliente.totalCambios }}
                                </span>
                            </div>
                        </button>

                        <!-- Productos -->
                        <div v-show="estaClienteExpandido(cliente.id)" class="divide-y divide-gray-100">
                            <div
                                v-for="producto in cliente.productos"
                                :key="producto.id"
                                class="transition-colors"
                            >
                                <button
                                    @click="toggleProducto(cliente.id, producto.id)"
                                    class="w-full px-3 py-2 flex items-center justify-between gap-2 hover:bg-gray-50 transition text-left"
                                    :class="{ 'bg-primary-50/40': estaExpandido(cliente.id, producto.id) }"
                                >
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <div class="w-5 h-5 rounded-md flex items-center justify-center flex-shrink-0 transition-transform duration-200"
                                            :class="estaExpandido(cliente.id, producto.id)
                                                ? 'bg-primary-100 text-primary-600 rotate-90'
                                                : 'bg-gray-100 text-gray-500'">
                                            <i class="fas fa-chevron-right text-[8px]"></i>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-mono text-[9px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                                                    {{ producto.codigo }}
                                                </span>
                                                <span class="font-medium text-gray-800 text-xs truncate">
                                                    {{ producto.nombre }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <div class="text-right hidden sm:block">
                                            <div class="text-[8px] text-gray-400 uppercase">Actual</div>
                                            <div class="text-xs font-bold text-emerald-600">
                                                Bs. {{ Number(getResumenProducto(producto)?.precioActual || 0).toFixed(2) }}
                                            </div>
                                        </div>
                                        <div class="text-right hidden md:block">
                                            <div class="text-[8px] text-gray-400 uppercase">Último</div>
                                            <div class="text-[10px] text-gray-600 font-medium">
                                                {{ formatearFechaCorta(getResumenProducto(producto)?.fecha) }}
                                            </div>
                                        </div>
                                        <span class="text-[9px] bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded-full font-medium whitespace-nowrap">
                                            {{ producto.registros.length }}
                                        </span>
                                    </div>
                                </button>

                                <!-- Historial expandido -->
                                <div
                                    v-if="estaExpandido(cliente.id, producto.id)"
                                    class="bg-gray-50/50 border-t border-gray-100 px-3 py-2"
                                >
                                    <div class="ml-6 space-y-1.5">
                                        <div
                                            v-for="(registro, index) in producto.registros"
                                            :key="registro.IdBitacora"
                                            class="flex items-start gap-2 text-xs border-l-2 pl-2.5 pb-1.5 relative"
                                            :class="{
                                                'border-emerald-400': registro.PrecioAnterior === 0,
                                                'border-red-400': registro.PrecioNuevo === 0,
                                                'border-orange-400': registro.PrecioNuevo > registro.PrecioAnterior && registro.PrecioAnterior > 0,
                                                'border-blue-400': registro.PrecioNuevo < registro.PrecioAnterior && registro.PrecioNuevo > 0,
                                                'border-gray-300': registro.PrecioNuevo === registro.PrecioAnterior
                                            }"
                                        >
                                            <div class="flex-shrink-0 w-4 text-center mt-0.5">
                                                <i :class="obtenerIconoPrecio(registro.PrecioAnterior, registro.PrecioNuevo)" class="text-[10px]"></i>
                                            </div>

                                            <div class="flex-1 min-w-0">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="font-medium text-gray-700 text-xs">
                                                        Bs. {{ Number(registro.PrecioAnterior).toFixed(2) }}
                                                    </span>
                                                    <i class="fas fa-arrow-right text-gray-400 text-[8px]"></i>
                                                    <span class="font-bold text-xs" :class="obtenerColorPrecio(registro.PrecioAnterior, registro.PrecioNuevo)">
                                                        Bs. {{ Number(registro.PrecioNuevo).toFixed(2) }}
                                                    </span>
                                                    <span
                                                        class="text-[8px] px-1.5 py-0.5 rounded-full border"
                                                        :class="obtenerBadgeEstado(registro.PrecioAnterior, registro.PrecioNuevo)"
                                                    >
                                                        {{ obtenerTextoEstado(registro.PrecioAnterior, registro.PrecioNuevo) }}
                                                    </span>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-1.5 mt-0.5 text-[9px] text-gray-400">
                                                    <span>
                                                        <i class="fas fa-clock text-[8px] mr-0.5"></i>
                                                        {{ formatearFecha(registro.FechaCambio) }}
                                                    </span>
                                                    <span class="flex items-center gap-0.5 text-primary-600 font-medium">
                                                        <i class="fas fa-user-edit text-[8px]"></i>
                                                        {{ registro.OperadorNombre || 'Sin operador' }}
                                                    </span>
                                                    <span v-if="registro.Motivo" class="flex items-center gap-0.5">
                                                        <i class="fas fa-comment text-[8px]"></i>
                                                        {{ registro.Motivo }}
                                                    </span>
                                                </div>
                                            </div>

                                            <span v-if="index === 0" class="flex-shrink-0 text-[8px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-medium">
                                                RECIENTE
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== PAGINACIÓN ==================== -->
                <div v-if="paginacion.last_page > 1" class="bg-white rounded-xl shadow-sm px-3 py-2 flex flex-col sm:flex-row items-center justify-between gap-2 mt-3">
                    <div class="text-[10px] text-gray-500">
                        Página <strong>{{ paginacion.current_page }}</strong> de <strong>{{ paginacion.last_page }}</strong>
                        • {{ paginacion.total }} cliente(s) en total
                    </div>
                    <div class="flex gap-1 flex-wrap justify-center">
                        <button
                            v-for="link in paginacion.links"
                            :key="link.label"
                            @click="irAPagina(link.url)"
                            class="px-2.5 py-1 border rounded-md text-[10px] hover:bg-gray-100 disabled:opacity-50 transition-colors"
                            :class="{
                                'bg-primary-600 text-white border-primary-600 hover:bg-primary-700': link.active,
                                'bg-white text-gray-700 border-gray-300': !link.active && link.url,
                                'opacity-50 cursor-not-allowed bg-gray-100': !link.url
                            }"
                            v-html="link.label"
                            :disabled="!link.url"
                        />
                    </div>
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

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

.rotate-90 {
    transform: rotate(90deg);
}
</style>