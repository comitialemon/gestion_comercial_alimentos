<!-- resources/js/Pages/Operacion/ClientesMayoristas/PedidosClientes/PrecioPedidosClientesMayoristas.vue -->
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import ModalPrecioClientes from './ModalPrecioClientes.vue'

const props = defineProps({
    identificadores: {
        type: Array,
        default: () => []
    },
    productos: {
        type: Array,
        default: () => []
    },
    sucursalId: {
        type: Number,
        default: 0
    }
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
const busqueda = ref('')
const cargando = ref(false)
const productosCargados = ref(false)

// ✅ CAMBIO: usar `ref` normal en lugar de `shallowRef` para mejor reactividad
const productosData = ref([...props.productos || []])

// Paginación
const paginaActual = ref(1)
const itemsPorPagina = ref(10)

// Lista identificadores
const listaIdentificadores = ref(props.identificadores || [])

// Modal
const modalPrecioOpen = ref(false)
const productoSeleccionado = ref(null)

// Debounce
let timeoutId = null

// ==================== COMPUTED ====================
const productosFiltrados = computed(() => {
    let resultados = productosData.value
    if (busqueda.value) {
        const busquedaLower = busqueda.value.toLowerCase()
        resultados = resultados.filter(p =>
            p.Codigo?.toLowerCase().includes(busquedaLower) ||
            p.Descripcion?.toLowerCase().includes(busquedaLower)
        )
    }
    return resultados
})

const totalPaginas = computed(() => {
    return Math.ceil(productosFiltrados.value.length / itemsPorPagina.value)
})

const productosPaginados = computed(() => {
    const inicio = (paginaActual.value - 1) * itemsPorPagina.value
    const fin = inicio + itemsPorPagina.value
    return productosFiltrados.value.slice(inicio, fin)
})

// ==================== MÉTODOS ====================
const cargarProductos = () => {
    cargando.value = true
    const url = `/operacion/pedidos/clientes-mayoristas/precios`
    router.visit(url, {
        method: 'get',
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            productosData.value = page.props.productos || []
            if (page.props.identificadores) {
                listaIdentificadores.value = page.props.identificadores
            }
            productosCargados.value = true
            cargando.value = false
        },
        onError: () => {
            cargando.value = false
            alert('Error al cargar los productos')
        }
    })
}

const buscarProductos = () => {
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => {
        paginaActual.value = 1
    }, 300)
}

const contarClientes = (producto) => {
    if (!producto.precios) return 0
    return Object.keys(producto.precios).length
}

const abrirModal = (producto) => {
    productoSeleccionado.value = producto
    modalPrecioOpen.value = true
}

const irABitacora = () => {
    router.visit('/operacion/pedidos/clientes-mayoristas/precios/bitacora')
}

// ==================== HANDLERS OPTIMISTIC UPDATE ====================

/**
 * ✅ Cuando el modal AGREGA un cliente con precio
 * Actualiza el producto local sin recargar nada
 */
const onPrecioAgregado = ({ IdProducto, IdIdentificador, Precio }) => {
    console.log('➕ Precio agregado localmente:', { IdProducto, IdIdentificador, Precio })
    
    const productos = productosData.value
    const index = productos.findIndex(p => p.IdProducto === IdProducto)
    
    if (index !== -1) {
        const producto = productos[index]
        
        // ✅ Crear nuevo objeto precios (inmutabilidad para reactividad)
        const nuevosPrecios = {
            ...(producto.precios || {}),
            [IdIdentificador]: Precio,
        }
        
        // ✅ Reemplazar el producto en el array
        productosData.value = [
            ...productos.slice(0, index),
            { ...producto, precios: nuevosPrecios },
            ...productos.slice(index + 1),
        ]
    }
    
    // ✅ Actualizar el producto seleccionado (el que está en el modal)
    if (productoSeleccionado.value?.IdProducto === IdProducto) {
        productoSeleccionado.value = {
            ...productoSeleccionado.value,
            precios: {
                ...(productoSeleccionado.value.precios || {}),
                [IdIdentificador]: Precio,
            },
        }
    }
}

/**
 * ✅ Cuando el modal ACTUALIZA el precio de un cliente
 */
const onPrecioActualizado = ({ IdProducto, IdIdentificador, Precio }) => {
    console.log('✏️ Precio actualizado localmente:', { IdProducto, IdIdentificador, Precio })
    
    const productos = productosData.value
    const index = productos.findIndex(p => p.IdProducto === IdProducto)
    
    if (index !== -1) {
        const producto = productos[index]
        
        const nuevosPrecios = {
            ...(producto.precios || {}),
            [IdIdentificador]: Precio,
        }
        
        productosData.value = [
            ...productos.slice(0, index),
            { ...producto, precios: nuevosPrecios },
            ...productos.slice(index + 1),
        ]
    }
    
    if (productoSeleccionado.value?.IdProducto === IdProducto) {
        productoSeleccionado.value = {
            ...productoSeleccionado.value,
            precios: {
                ...(productoSeleccionado.value.precios || {}),
                [IdIdentificador]: Precio,
            },
        }
    }
}

/**
 * ✅ Cuando el modal ELIMINA un precio
 */
const onPrecioEliminado = ({ IdProducto, IdIdentificador }) => {
    console.log('🗑️ Precio eliminado localmente:', { IdProducto, IdIdentificador })
    
    const productos = productosData.value
    const index = productos.findIndex(p => p.IdProducto === IdProducto)
    
    if (index !== -1) {
        const producto = productos[index]
        
        const nuevosPrecios = { ...(producto.precios || {}) }
        delete nuevosPrecios[IdIdentificador]
        
        productosData.value = [
            ...productos.slice(0, index),
            { ...producto, precios: nuevosPrecios },
            ...productos.slice(index + 1),
        ]
    }
    
    if (productoSeleccionado.value?.IdProducto === IdProducto) {
        const nuevosPrecios = { ...(productoSeleccionado.value.precios || {}) }
        delete nuevosPrecios[IdIdentificador]
        
        productoSeleccionado.value = {
            ...productoSeleccionado.value,
            precios: nuevosPrecios,
        }
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    productosCargados.value = true
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    if (timeoutId) clearTimeout(timeoutId)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-tag text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Asignación de Precios</h1>
                            <p class="text-xs text-gray-500">
                                Asigna precios a los operadores de tipo <span class="font-medium text-primary-600">PedidoClientes</span>
                            </p>
                        </div>
                    </div>
                    <button
                        @click="irABitacora"
                        class="px-3 py-1.5 bg-purple-600 text-white rounded-md text-xs font-medium hover:bg-purple-700 transition flex items-center gap-1.5"
                    >
                        <i class="fas fa-history text-[10px]"></i>
                        Ver Bitácora
                    </button>
                </div>

                <!-- ==================== FILTROS COMPACTOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[180px] max-w-[300px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar Producto</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                <input
                                    type="text"
                                    v-model="busqueda"
                                    @input="buscarProductos"
                                    placeholder="Código o descripción..."
                                    class="w-full border border-gray-300 rounded-md pl-7 pr-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                />
                            </div>
                        </div>

                        <div class="flex gap-1.5 ml-auto">
                            <button
                                @click="cargarProductos"
                                :disabled="cargando"
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1.5 disabled:opacity-50"
                            >
                                <i class="fas fa-sync-alt text-[10px]" :class="{'animate-spin': cargando}"></i>
                                Actualizar
                            </button>
                        </div>
                    </div>

                    <!-- Contador -->
                    <div v-if="!cargando && productosCargados" class="mt-2 flex items-center gap-2 text-[10px]">
                        <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">
                            <i class="fas fa-check-circle mr-1"></i>
                            {{ productosFiltrados.length }} productos
                        </span>
                    </div>
                </div>

                <!-- ==================== CARGANDO ==================== -->
                <div v-if="cargando" class="flex justify-center items-center py-12">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-primary-500 mb-3 block"></i>
                        <p class="text-gray-600 text-sm">Cargando productos...</p>
                    </div>
                </div>

                <!-- ==================== TABLA ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">

                        <!-- VISTA MÓVIL -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div
                                v-for="producto in productosPaginados"
                                :key="producto.IdProducto"
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100"
                            >
                                <div class="mb-1.5">
                                    <p class="text-[10px] font-mono text-gray-800">{{ producto.Codigo }}</p>
                                    <p class="text-xs font-medium text-gray-800">{{ producto.Descripcion }}</p>
                                </div>
                                <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-gray-200">
                                    <span
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-medium"
                                        :class="contarClientes(producto) > 0
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-gray-100 text-gray-500'"
                                    >
                                        <i class="fas fa-users text-[8px]"></i>
                                        {{ contarClientes(producto) }} cliente{{ contarClientes(producto) === 1 ? '' : 's' }}
                                    </span>
                                    <button
                                        @click="abrirModal(producto)"
                                        class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-[10px] font-medium inline-flex items-center gap-1 transition"
                                    >
                                        <i class="fas fa-plus-circle text-[9px]"></i>
                                        Agregar
                                    </button>
                                </div>
                            </div>
                            <div v-if="productosPaginados.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-box-open text-2xl mb-1 block"></i>
                                <span class="text-xs">No se encontraron productos</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase w-12">#</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase w-56">Código</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-40">Clientes</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-44">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="(producto, index) in productosPaginados"
                                    :key="producto.IdProducto"
                                    class="hover:bg-gray-50 transition"
                                >
                                    <td class="px-3 py-2 text-center text-[10px] text-gray-400">
                                        {{ (paginaActual - 1) * itemsPorPagina + index + 1 }}
                                    </td>
                                    <td class="px-3 py-2 text-xs font-mono text-gray-800 whitespace-nowrap">
                                        {{ producto.Codigo }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-800 truncate max-w-[250px]">
                                        {{ producto.Descripcion }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span
                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-medium whitespace-nowrap"
                                            :class="contarClientes(producto) > 0
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-gray-100 text-gray-500'"
                                        >
                                            <i class="fas fa-users text-[8px]"></i>
                                            Existen {{ contarClientes(producto) }} cliente{{ contarClientes(producto) === 1 ? '' : 's' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button
                                            @click="abrirModal(producto)"
                                            class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-[10px] font-medium inline-flex items-center gap-1.5 transition w-full max-w-[160px] justify-center"
                                        >
                                            <i class="fas fa-plus-circle text-[9px]"></i>
                                            Agregar cliente
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="productosPaginados.length === 0">
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-box-open text-2xl mb-1 block"></i>
                                        No se encontraron productos
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ==================== PAGINACIÓN ==================== -->
                    <div class="px-3 py-2 border-t border-gray-200 bg-gray-50 flex flex-col sm:flex-row items-center justify-between gap-2">
                        <div class="text-[10px] text-gray-500">
                            Mostrando {{ productosPaginados.length }} de {{ productosFiltrados.length }} productos
                        </div>
                        <div class="flex gap-1">
                            <button
                                @click="paginaActual > 1 && paginaActual--"
                                :disabled="paginaActual <= 1"
                                class="px-2.5 py-0.5 border border-gray-300 rounded text-[10px] hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            >
                                <i class="fas fa-chevron-left text-[8px]"></i> Anterior
                            </button>
                            <span class="px-2.5 py-0.5 text-[10px] text-gray-600">
                                Pág. {{ paginaActual }} de {{ totalPaginas }}
                            </span>
                            <button
                                @click="paginaActual < totalPaginas && paginaActual++"
                                :disabled="paginaActual >= totalPaginas"
                                class="px-2.5 py-0.5 border border-gray-300 rounded text-[10px] hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            >
                                Siguiente <i class="fas fa-chevron-right text-[8px]"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL ==================== -->
        <ModalPrecioClientes
            v-model="modalPrecioOpen"
            :producto="productoSeleccionado"
            :identificadores="listaIdentificadores"
            :sucursal-id="sucursalId"
            :precios-actuales="productoSeleccionado?.precios || {}"
            @precio-agregado="onPrecioAgregado"
            @precio-actualizado="onPrecioActualizado"
            @precio-eliminado="onPrecioEliminado"
        />
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
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
</style>