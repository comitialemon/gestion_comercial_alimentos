<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    bitacora: {
        type: Object,
        required: true
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
    }
})

// ==========================================
// FILTROS
// ==========================================
const filtros = ref({
    identificador_id: props.filtros?.identificador_id || '',
    producto_id: props.filtros?.producto_id || '',
    fecha_desde: props.filtros?.fecha_desde || '',
    fecha_hasta: props.filtros?.fecha_hasta || '',
})

const aplicandoFiltros = ref(false)

// ==========================================
// ✅ WATCH: APLICAR FILTROS AUTOMÁTICAMENTE
// ==========================================

// Cuando cambia el cliente
watch(() => filtros.value.identificador_id, () => {
    aplicarFiltrosAutomatico()
})

// Cuando cambia el producto
watch(() => filtros.value.producto_id, () => {
    aplicarFiltrosAutomatico()
})

// Cuando cambia fecha desde (con debounce)
let timeoutFechaDesde = null
watch(() => filtros.value.fecha_desde, () => {
    clearTimeout(timeoutFechaDesde)
    timeoutFechaDesde = setTimeout(() => {
        aplicarFiltrosAutomatico()
    }, 500)
})

// Cuando cambia fecha hasta (con debounce)
let timeoutFechaHasta = null
watch(() => filtros.value.fecha_hasta, () => {
    clearTimeout(timeoutFechaHasta)
    timeoutFechaHasta = setTimeout(() => {
        aplicarFiltrosAutomatico()
    }, 500)
})

// ==========================================
// AUTOCOMPLETE - CLIENTE
// ==========================================
const busquedaCliente = ref('')
const mostrarClientes = ref(false)

const clientesFiltrados = computed(() => {
    if (!busquedaCliente.value) return props.clientesConPrecios.slice(0, 10)
    const termino = busquedaCliente.value.toLowerCase()
    return props.clientesConPrecios.filter(c =>
        c.Nombre?.toLowerCase().includes(termino) ||
        c.CI_NIT?.toString().includes(termino)
    )
})

const clienteSeleccionado = computed(() => {
    if (filtros.value.identificador_id) {
        const cliente = props.clientesConPrecios.find(c => c.IdIdentificador == filtros.value.identificador_id)
        if (cliente) {
            return `${cliente.CI_NIT} - ${cliente.Nombre}`
        }
    }
    return ''
})

const seleccionarCliente = (cliente) => {
    filtros.value.identificador_id = cliente.IdIdentificador
    busquedaCliente.value = `${cliente.CI_NIT} - ${cliente.Nombre}`
    mostrarClientes.value = false
}

const limpiarCliente = () => {
    filtros.value.identificador_id = ''
    busquedaCliente.value = ''
}

// ==========================================
// AUTOCOMPLETE - PRODUCTO
// ==========================================
const busquedaProducto = ref('')
const mostrarProductos = ref(false)

const productosFiltrados = computed(() => {
    if (!busquedaProducto.value) return props.productosHabilitados.slice(0, 10)
    const termino = busquedaProducto.value.toLowerCase()
    return props.productosHabilitados.filter(p =>
        p.Descripcion?.toLowerCase().includes(termino) ||
        p.Codigo?.toLowerCase().includes(termino)
    )
})

const productoseleccionado = computed(() => {
    if (filtros.value.producto_id) {
        const producto = props.productosHabilitados.find(p => p.IdProducto == filtros.value.producto_id)
        if (producto) {
            return `${producto.Codigo} - ${producto.Descripcion}`
        }
    }
    return ''
})

const seleccionarProducto = (producto) => {
    filtros.value.producto_id = producto.IdProducto
    busquedaProducto.value = `${producto.Codigo} - ${producto.Descripcion}`
    mostrarProductos.value = false
}

const limpiarProducto = () => {
    filtros.value.producto_id = ''
    busquedaProducto.value = ''
}

// ==========================================
// ✅ APLICAR FILTROS AUTOMÁTICAMENTE
// ==========================================
const aplicarFiltrosAutomatico = () => {
    // No aplicar si ya está cargando
    if (aplicandoFiltros.value) return
    
    aplicandoFiltros.value = true
    
    const params = new URLSearchParams()
    if (filtros.value.identificador_id) params.append('identificador_id', filtros.value.identificador_id)
    if (filtros.value.producto_id) params.append('producto_id', filtros.value.producto_id)
    if (filtros.value.fecha_desde) params.append('fecha_desde', filtros.value.fecha_desde)
    if (filtros.value.fecha_hasta) params.append('fecha_hasta', filtros.value.fecha_hasta)
    
    router.visit(`/operacion/pedidos/clientes-mayoristas/precios/bitacora?${params.toString()}`, {
        preserveState: true,
        onSuccess: () => {
            aplicandoFiltros.value = false
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
    // No es necesario llamar a aplicarFiltros, los watches lo harán
}

// ==========================================
// AGRUPAR BITÁCORA POR CLIENTE Y PRODUCTO
// ==========================================
const bitacoraAgrupada = computed(() => {
    const grupos = {}
    
    props.bitacora.data.forEach(registro => {
        const clienteId = registro.IdIdentificador
        const clienteNombre = registro.IdentificadorNombre
        const ciNit = registro.CI_NIT
        
        if (!grupos[clienteId]) {
            grupos[clienteId] = {
                id: clienteId,
                nombre: clienteNombre,
                ci_nit: ciNit,
                productos: {}
            }
        }
        
        const productoId = registro.IdProducto
        const productoNombre = registro.ProductoNombre
        const productoCodigo = registro.ProductoCodigo
        
        if (!grupos[clienteId].productos[productoId]) {
            grupos[clienteId].productos[productoId] = {
                id: productoId,
                nombre: productoNombre,
                codigo: productoCodigo,
                registros: []
            }
        }
        
        grupos[clienteId].productos[productoId].registros.push(registro)
    })
    
    return Object.values(grupos).map(cliente => ({
        ...cliente,
        productos: Object.values(cliente.productos).map(producto => ({
            ...producto,
            registros: producto.registros.sort((a, b) => 
                new Date(b.FechaCambio) - new Date(a.FechaCambio)
            )
        }))
    }))
})

// ==========================================
// MÉTODOS
// ==========================================

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    const date = new Date(fecha)
    return date.toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const obtenerColorPrecio = (precioAnterior, precioNuevo) => {
    if (precioAnterior === 0) return 'text-green-600'
    if (precioNuevo === 0) return 'text-red-600'
    if (precioNuevo > precioAnterior) return 'text-orange-500'
    if (precioNuevo < precioAnterior) return 'text-blue-600'
    return 'text-gray-600'
}

const obtenerIconoPrecio = (precioAnterior, precioNuevo) => {
    if (precioAnterior === 0) return 'fa-plus-circle text-green-500'
    if (precioNuevo === 0) return 'fa-trash-alt text-red-500'
    if (precioNuevo > precioAnterior) return 'fa-arrow-up text-orange-500'
    if (precioNuevo < precioAnterior) return 'fa-arrow-down text-blue-500'
    return 'fa-minus text-gray-400'
}

const obtenerBadgeEstado = (precioAnterior, precioNuevo) => {
    if (precioAnterior === 0) return 'bg-green-100 text-green-800 border-green-200'
    if (precioNuevo === 0) return 'bg-red-100 text-red-800 border-red-200'
    if (precioNuevo > precioAnterior) return 'bg-orange-100 text-orange-800 border-orange-200'
    if (precioNuevo < precioAnterior) return 'bg-blue-100 text-blue-800 border-blue-200'
    return 'bg-gray-100 text-gray-800 border-gray-200'
}

const obtenerTextoEstado = (precioAnterior, precioNuevo) => {
    if (precioAnterior === 0) return 'Creación'
    if (precioNuevo === 0) return 'Eliminación'
    if (precioNuevo > precioAnterior) return 'Aumento'
    if (precioNuevo < precioAnterior) return 'Disminución'
    return 'Sin cambio'
}

const irAPagina = (url) => {
    if (url) {
        router.visit(url, { preserveState: true })
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
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-7xl mx-auto">
                <!-- HEADER -->
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-history text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Bitácora de Precios</h1>
                            <p class="text-[10px] text-gray-500 hidden sm:block">Historial de cambios de precios por cliente y producto</p>
                        </div>
                    </div>
                    <button
                        @click="irAPrecios"
                        class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs hover:bg-primary-700 flex items-center gap-1 whitespace-nowrap transition-colors"
                    >
                        <i class="fas fa-arrow-left text-[10px]"></i>
                        Volver a Precios
                    </button>
                </div>

                <!-- FILTROS CON AUTOCOMPLETE - SIN BOTÓN BUSCAR -->
                <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <!-- Cliente (Autocomplete) -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Cliente</label>
                            <div class="relative">
                                <div class="flex">
                                    <input
                                        type="text"
                                        v-model="busquedaCliente"
                                        @focus="mostrarClientes = true"
                                        @blur="cerrarAutocompletar"
                                        :placeholder="!filtros.identificador_id ? 'Buscar cliente...' : ''"
                                        :value="filtros.identificador_id ? clienteSeleccionado : busquedaCliente"
                                        @input="(e) => { if (!filtros.identificador_id) busquedaCliente = e.target.value }"
                                        class="flex-1 border rounded-l-md px-2 py-1.5 text-xs"
                                    />
                                    <button
                                        v-if="filtros.identificador_id"
                                        @click="limpiarCliente"
                                        class="px-2 bg-gray-100 border border-l-0 rounded-r-md hover:bg-gray-200 text-gray-500 text-[10px]"
                                        type="button"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div
                                    v-if="mostrarClientes && clientesFiltrados.length > 0"
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-36 overflow-y-auto"
                                >
                                    <div
                                        v-for="cliente in clientesFiltrados"
                                        :key="cliente.IdIdentificador"
                                        @click="seleccionarCliente(cliente)"
                                        class="px-2 py-1.5 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-xs"
                                    >
                                        <span class="font-mono">{{ cliente.CI_NIT }}</span> - {{ cliente.Nombre }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Producto (Autocomplete) -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Producto</label>
                            <div class="relative">
                                <div class="flex">
                                    <input
                                        type="text"
                                        v-model="busquedaProducto"
                                        @focus="mostrarProductos = true"
                                        @blur="cerrarAutocompletar"
                                        :placeholder="!filtros.producto_id ? 'Buscar producto...' : ''"
                                        :value="filtros.producto_id ? productoseleccionado : busquedaProducto"
                                        @input="(e) => { if (!filtros.producto_id) busquedaProducto = e.target.value }"
                                        class="flex-1 border rounded-l-md px-2 py-1.5 text-xs"
                                    />
                                    <button
                                        v-if="filtros.producto_id"
                                        @click="limpiarProducto"
                                        class="px-2 bg-gray-100 border border-l-0 rounded-r-md hover:bg-gray-200 text-gray-500 text-[10px]"
                                        type="button"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div
                                    v-if="mostrarProductos && productosFiltrados.length > 0"
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-36 overflow-y-auto"
                                >
                                    <div
                                        v-for="producto in productosFiltrados"
                                        :key="producto.IdProducto"
                                        @click="seleccionarProducto(producto)"
                                        class="px-2 py-1.5 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-xs"
                                    >
                                        <span class="font-mono">{{ producto.Codigo }}</span> - {{ producto.Descripcion }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fecha Desde -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Fecha Desde</label>
                            <input
                                type="date"
                                v-model="filtros.fecha_desde"
                                class="w-full border rounded-md px-2 py-1.5 text-xs"
                            />
                        </div>

                        <!-- Fecha Hasta -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Fecha Hasta</label>
                            <input
                                type="date"
                                v-model="filtros.fecha_hasta"
                                class="w-full border rounded-md px-2 py-1.5 text-xs"
                            />
                        </div>

                        <!-- Solo botón Limpiar -->
                        <div class="flex items-end gap-2">
                            <button
                                @click="limpiarFiltros"
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs hover:bg-gray-300 flex items-center gap-1 transition-colors"
                            >
                                <i class="fas fa-times text-[10px]"></i>
                                Limpiar Filtros
                            </button>
                            <span v-if="aplicandoFiltros" class="text-[10px] text-gray-400">
                                <i class="fas fa-spinner fa-spin"></i> Cargando...
                            </span>
                        </div>
                    </div>
                </div>

                <!-- VISTA DE BITÁCORA AGRUPADA -->
                <div v-if="bitacora.data.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                    <i class="fas fa-inbox text-4xl text-gray-300 mb-3 block"></i>
                    <p class="text-gray-500 text-sm">No hay registros en la bitácora</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Por cada Cliente -->
                    <div
                        v-for="cliente in bitacoraAgrupada"
                        :key="cliente.id"
                        class="bg-white rounded-lg shadow-sm overflow-hidden"
                    >
                        <!-- Header del Cliente -->
                        <div class="bg-primary-50 px-4 py-3 border-b border-primary-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-primary-200 rounded-full flex items-center justify-center text-primary-700 font-bold text-sm">
                                    {{ cliente.nombre.charAt(0) }}
                                </div>
                                <div>
                                    <h2 class="font-bold text-gray-800 text-sm">{{ cliente.nombre }}</h2>
                                    <span class="text-[10px] text-gray-500">CI/NIT: {{ cliente.ci_nit }}</span>
                                </div>
                            </div>
                            <span class="text-[10px] bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full">
                                {{ Object.keys(cliente.productos).length }} productos
                            </span>
                        </div>

                        <!-- Productos del Cliente -->
                        <div class="divide-y divide-gray-100">
                            <div
                                v-for="producto in cliente.productos"
                                :key="producto.id"
                                class="p-4 hover:bg-gray-50 transition-colors"
                            >
                                <!-- Header del Producto -->
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-box text-primary-400 text-xs"></i>
                                        <span class="font-medium text-gray-700 text-xs">{{ producto.nombre }}</span>
                                        <span class="text-[10px] text-gray-400 font-mono">({{ producto.codigo }})</span>
                                    </div>
                                    <span class="text-[10px] text-gray-400">
                                        {{ producto.registros.length }} cambios
                                    </span>
                                </div>

                                <!-- Timeline de cambios del producto -->
                                <div class="space-y-2 ml-6">
                                    <div
                                        v-for="registro in producto.registros"
                                        :key="registro.IdBitacora"
                                        class="flex items-start gap-3 text-xs border-l-2 pl-3 pb-2 relative"
                                        :class="{
                                            'border-green-400': registro.PrecioAnterior === 0,
                                            'border-red-400': registro.PrecioNuevo === 0,
                                            'border-orange-400': registro.PrecioNuevo > registro.PrecioAnterior && registro.PrecioAnterior > 0,
                                            'border-blue-400': registro.PrecioNuevo < registro.PrecioAnterior && registro.PrecioNuevo > 0,
                                            'border-gray-300': registro.PrecioNuevo === registro.PrecioAnterior
                                        }"
                                    >
                                        <div class="flex-shrink-0 w-5 text-center mt-0.5">
                                            <i :class="obtenerIconoPrecio(registro.PrecioAnterior, registro.PrecioNuevo)"></i>
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-medium text-gray-700">
                                                    Bs. {{ Number(registro.PrecioAnterior).toFixed(2) }}
                                                </span>
                                                <i class="fas fa-arrow-right text-gray-400 text-[8px]"></i>
                                                <span class="font-bold" :class="obtenerColorPrecio(registro.PrecioAnterior, registro.PrecioNuevo)">
                                                    Bs. {{ Number(registro.PrecioNuevo).toFixed(2) }}
                                                </span>
                                                <span
                                                    class="text-[8px] px-2 py-0.5 rounded-full border"
                                                    :class="obtenerBadgeEstado(registro.PrecioAnterior, registro.PrecioNuevo)"
                                                >
                                                    {{ obtenerTextoEstado(registro.PrecioAnterior, registro.PrecioNuevo) }}
                                                </span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 mt-0.5 text-[10px] text-gray-400">
                                                <span>{{ formatearFecha(registro.FechaCambio) }}</span>
                                                <span class="text-gray-300">|</span>
                                                <span>
                                                    <i class="fas fa-user text-[8px] mr-1"></i>
                                                    {{ registro.OperadorNombre }}
                                                </span>
                                                <span v-if="registro.Motivo" class="text-gray-400">
                                                    <i class="fas fa-comment text-[8px] mr-1"></i>
                                                    {{ registro.Motivo }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="bitacora.total > 20" class="bg-white rounded-lg shadow-sm px-3 sm:px-4 py-2 flex flex-col sm:flex-row items-center justify-between gap-2 mt-4">
                    <div class="text-[10px] text-gray-500">
                        Mostrando {{ bitacora.data.length }} de {{ bitacora.total }} registros
                    </div>
                    <div class="flex gap-1">
                        <button
                            v-for="link in bitacora.links"
                            :key="link.label"
                            @click="irAPagina(link.url)"
                            class="px-2 py-1 border rounded text-[10px] hover:bg-gray-100 disabled:opacity-50 transition-colors"
                            :class="{
                                'bg-primary-600 text-white border-primary-600 hover:bg-primary-700': link.active,
                                'opacity-50 cursor-not-allowed': !link.url
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
.max-h-36 {
    max-height: 9rem;
}
</style>