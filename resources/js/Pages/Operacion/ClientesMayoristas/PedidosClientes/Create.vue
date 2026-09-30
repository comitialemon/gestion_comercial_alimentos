<script setup>
import { ref, computed, onMounted, inject, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import CreateModalProductos from './CreateModalProductos.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    contenedores: { type: Array, default: () => [] },
    clientes: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    pedidoBorrador: { type: Object, default: null },
    carrito: { type: Array, default: () => [] },
    sucursalDefault: { type: [Number, String], default: null },
    idIdentificador: { type: [Number, String], default: null },
    nombreOperador: { type: String, default: '' },
    minimosGrupos: { type: Array, default: () => [] },           // ✅ Ahora array
    minimosProductos: { type: [Array, Object], default: () => ({}) },
    infoProductos: { type: [Array, Object], default: () => ({}) }, // ✅ NUEVO
    progresoInicial: { type: Array, default: () => [] },
    tipoPrecio: { type: String, default: 'sin_factura' },
    subclientes: { type: Array, default: () => [] },
    idSubClienteDefault: { type: [Number, String], default: null },
})

// ==================== ESTADO ====================
const loading = ref(false)
const modalVisible = ref(false)
const contenedorSeleccionado = ref(null)
const carritoItems = ref([])
const pedidoId = ref(null)
const busquedaContenedor = ref('')

const tipoPrecioActual = ref(props.tipoPrecio || 'sin_factura')
const modalCambiarTipoVisible = ref(false)
const tipoPrecioSeleccionado = ref('sin_factura')
const cambiandoTipo = ref(false)

const minimos = ref([...props.minimosGrupos])
const minimosProd = ref({ ...props.minimosProductos })
const productosInfo = ref({ ...props.infoProductos })

// ==================== COMPUTADOS ====================
const totalCarrito = computed(() => {
    let total = 0
    carritoItems.value.forEach(item => {
        item.productos.forEach(p => {
            total += Number(p.Cantidad) || 0
        })
    })
    return total
})

const totalContenedoresCarrito = computed(() => carritoItems.value.length)
const hayProductosEnCarrito = computed(() => carritoItems.value.length > 0)

const contenedoresFiltrados = computed(() => {
    if (!busquedaContenedor.value) return props.contenedores
    const termino = busquedaContenedor.value.toLowerCase()
    return props.contenedores.filter(c =>
        c.Codigo?.toLowerCase().includes(termino) ||
        c.TipoContenedor?.toLowerCase().includes(termino)
    )
})

const acumuladoPorGrupo = computed(() => {
    const acumulado = {}
    carritoItems.value.forEach(item => {
        item.productos.forEach(p => {
            const grupoId = p.IdGrupoAnalisis
            if (grupoId) {
                acumulado[grupoId] = (acumulado[grupoId] || 0) + (Number(p.Cantidad) || 0)
            }
        })
    })
    return acumulado
})

const acumuladoPorProducto = computed(() => {
    const acumulado = {}
    carritoItems.value.forEach(item => {
        item.productos.forEach(p => {
            const prodId = p.IdProducto
            if (prodId) {
                acumulado[prodId] = (acumulado[prodId] || 0) + (Number(p.Cantidad) || 0)
            }
        })
    })
    return acumulado
})

/**
 * ✅ PROGRESO JERÁRQUICO: grupos → productos anidados
 */
const progresoJerarquico = computed(() => {
    const acumG = acumuladoPorGrupo.value
    const acumP = acumuladoPorProducto.value
    const resultado = {}

    // 1. Inicializar grupos con mínimo
    minimos.value.forEach(minimo => {
        const idGrupo = minimo.IdGrupoAnalisis
        if (acumG[idGrupo] === undefined) return

        const pedida = acumG[idGrupo] || 0
        const minima = Number(minimo.CantidadMinimaGrupo) || 0

        resultado[idGrupo] = {
            IdGrupoAnalisis: idGrupo,
            NombreGrupo: minimo.NombreGrupo || `Grupo ${idGrupo}`,
            CantidadMinima: minima,
            CantidadPedida: pedida,
            Cumple: pedida >= minima,
            Falta: Math.max(0, minima - pedida),
            Porcentaje: minima > 0 ? Math.min((pedida / minima) * 100, 100) : 100,
            productos: [],
        }
    })

    // 2. Agregar productos dentro de su grupo
    Object.keys(minimosProd.value).forEach(idProd => {
        const idProducto = Number(idProd)
        if (acumP[idProducto] === undefined) return

        const pedida = acumP[idProducto] || 0
        const minima = Number(minimosProd.value[idProd]) || 0
        const info = productosInfo.value[idProducto]

        if (!info) return

        const idGrupo = info.IdGrupoAnalisis
        const grupo = resultado[idGrupo]

        if (!grupo) return

        grupo.productos.push({
            IdProducto: idProducto,
            Codigo: info.Codigo,
            Descripcion: info.Descripcion,
            CantidadMinima: minima,
            CantidadPedida: pedida,
            Cumple: pedida >= minima,
            Falta: Math.max(0, minima - pedida),
            Porcentaje: minima > 0 ? Math.min((pedida / minima) * 100, 100) : 100,
        })
    })

    // 3. Convertir a array
    return Object.values(resultado).filter(g => g.productos.length > 0 || g.CantidadPedida > 0)
})

const totalPendientes = computed(() => {
    let count = 0
    progresoJerarquico.value.forEach(g => {
        if (!g.Cumple) count++
        g.productos.forEach(p => {
            if (!p.Cumple) count++
        })
    })
    return count
})

const cumpleTodosMinimos = computed(() =>
    progresoJerarquico.value.length === 0 || totalPendientes.value === 0
)

const tipoPrecioLabel = computed(() =>
    tipoPrecioActual.value === 'con_factura' ? 'Con Factura' : 'Sin Factura'
)

const tipoPrecioColor = computed(() =>
    tipoPrecioActual.value === 'con_factura'
        ? 'bg-blue-100 text-blue-700 border-blue-300'
        : 'bg-gray-100 text-gray-700 border-gray-300'
)

// ==================== FUNCIONES ====================
const inicializarCarrito = () => {
    if (props.carrito && props.carrito.length > 0) {
        carritoItems.value = props.carrito.map(item => ({
            ...item,
            productos: item.productos.map(p => ({
                ...p,
                Cantidad: Number(p.Cantidad) || 0
            }))
        }))
    }
    if (props.pedidoBorrador) {
        pedidoId.value = props.pedidoBorrador.IdPedidoCliente
    }
}

const abrirModal = (contenedor) => {
    contenedorSeleccionado.value = contenedor
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    contenedorSeleccionado.value = null
}

const abrirModalCambiarTipo = (nuevoTipo) => {
    if (nuevoTipo === tipoPrecioActual.value) return
    tipoPrecioSeleccionado.value = nuevoTipo

    if (!hayProductosEnCarrito.value) {
        aplicarCambioTipo()
        return
    }
    modalCambiarTipoVisible.value = true
}

const aplicarCambioTipo = async () => {
    cambiandoTipo.value = true

    try {
        if (pedidoId.value && hayProductosEnCarrito.value) {
            const response = await axios.post(
                '/operacion/pedidos/clientes-mayoristas/pedidos-clientes/carrito/cambiar-tipo-precio',
                {
                    IdPedidoCliente: pedidoId.value,
                    TipoPrecio: tipoPrecioSeleccionado.value
                }
            )

            if (response.data.success) {
                toast?.success('Éxito', 'Tipo de precio actualizado y precios recalculados')
                router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create', {
                    tipo_precio: tipoPrecioSeleccionado.value
                }, {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        tipoPrecioActual.value = tipoPrecioSeleccionado.value
                    }
                })
            } else {
                toast?.error('Error', response.data.message || 'Error al cambiar tipo')
            }
        } else {
            router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create', {
                tipo_precio: tipoPrecioSeleccionado.value
            }, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    tipoPrecioActual.value = tipoPrecioSeleccionado.value
                }
            })
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al cambiar tipo')
    } finally {
        cambiandoTipo.value = false
        modalCambiarTipoVisible.value = false
    }
}

const cerrarModalCambiarTipo = () => {
    modalCambiarTipoVisible.value = false
    tipoPrecioSeleccionado.value = tipoPrecioActual.value
}

const agregarContenedorAlCarrito = async (data) => {
    loading.value = true
    try {
        const payload = { ...data, TipoPrecio: tipoPrecioActual.value }
        const response = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/pedidos-clientes/carrito/agregar',
            payload
        )
        if (response.data.success) {
            toast?.success('Éxito', 'Productos agregados al carrito')
            if (response.data.pedido) {
                pedidoId.value = response.data.pedido.IdPedidoCliente
            }
            router.reload({ only: ['carrito', 'pedidoBorrador'] })
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al agregar al carrito')
    } finally {
        loading.value = false
    }
}

const irARevisarPedido = () => {
    if (!pedidoId.value) {
        toast?.warning('Carrito vacío', 'Agregue productos antes de revisar')
        return
    }
    router.get(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${pedidoId.value}/review`)
}

// ==================== WATCHERS ====================
watch(() => props.carrito, (newVal) => {
    if (newVal && newVal.length > 0) {
        carritoItems.value = newVal.map(item => ({
            ...item,
            productos: item.productos.map(p => ({
                ...p,
                Cantidad: Number(p.Cantidad) || 0
            }))
        }))
    } else {
        carritoItems.value = []
    }
}, { immediate: true, deep: true })

watch(() => props.minimosGrupos, (newVal) => {
    minimos.value = [...newVal]
}, { deep: true })

watch(() => props.minimosProductos, (newVal) => {
    minimosProd.value = { ...newVal }
}, { deep: true })

watch(() => props.infoProductos, (newVal) => {
    productosInfo.value = { ...newVal }
}, { deep: true })

watch(() => props.tipoPrecio, (newVal) => {
    tipoPrecioActual.value = newVal
})

onMounted(() => {
    inicializarCarrito()
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-32">
        <div class="max-w-7xl mx-auto px-3 py-3">

            <!-- HEADER -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-3">
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Nuevo Pedido</h1>
                    <p class="text-[10px] text-gray-400">
                        Seleccione contenedores y agregue productos
                        <span v-if="nombreOperador" class="text-primary-600 font-medium">• {{ nombreOperador }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div v-if="hayProductosEnCarrito" class="hidden sm:flex items-center gap-3 text-xs">
                        <div class="flex items-center gap-1.5 text-gray-500">
                            <i class="fas fa-shopping-cart text-primary-500"></i>
                            <span><strong class="text-gray-700">{{ totalContenedoresCarrito }}</strong> cont.</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-gray-500">
                            <i class="fas fa-cubes text-primary-500"></i>
                            <span><strong class="text-primary-600">{{ totalCarrito }}</strong> und</span>
                        </div>
                    </div>
                    <button
                        @click="irARevisarPedido"
                        :disabled="!hayProductosEnCarrito"
                        class="px-5 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2 shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="hayProductosEnCarrito
                            ? (cumpleTodosMinimos ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-orange-500 hover:bg-orange-600 text-white')
                            : 'bg-gray-300 text-gray-500'"
                    >
                        <i :class="cumpleTodosMinimos && hayProductosEnCarrito ? 'fas fa-check-circle' : 'fas fa-clipboard-list'" class="text-sm"></i>
                        Revisar Pedido
                        <span v-if="hayProductosEnCarrito" class="bg-white/20 rounded-full px-2 py-0.5 text-xs">
                            {{ totalContenedoresCarrito }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- SELECTOR TIPO DE PRECIO -->
            <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-file-invoice-dollar text-primary-600"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-gray-800">Tipo de Precio</h2>
                            <p class="text-[10px] text-gray-500">Selecciona cómo se cotizará el pedido</p>
                        </div>
                    </div>
                    <div class="flex bg-gray-100 rounded-lg p-1 w-full sm:w-auto">
                        <button
                            @click="abrirModalCambiarTipo('sin_factura')"
                            :disabled="cambiandoTipo"
                            class="flex-1 sm:flex-none px-4 py-2 rounded-md text-xs font-medium transition flex items-center justify-center gap-2"
                            :class="tipoPrecioActual === 'sin_factura' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        >
                            <i class="fas fa-receipt text-[10px]"></i>
                            Sin Factura
                            <i v-if="tipoPrecioActual === 'sin_factura'" class="fas fa-check-circle text-green-500 text-[10px]"></i>
                        </button>
                        <button
                            @click="abrirModalCambiarTipo('con_factura')"
                            :disabled="cambiandoTipo"
                            class="flex-1 sm:flex-none px-4 py-2 rounded-md text-xs font-medium transition flex items-center justify-center gap-2"
                            :class="tipoPrecioActual === 'con_factura' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        >
                            <i class="fas fa-file-invoice text-[10px]"></i>
                            Con Factura
                            <i v-if="tipoPrecioActual === 'con_factura'" class="fas fa-check-circle text-green-500 text-[10px]"></i>
                        </button>
                    </div>
                </div>
                <div class="mt-2 flex items-center gap-2 text-[10px]" :class="tipoPrecioColor">
                    <i :class="tipoPrecioActual === 'con_factura' ? 'fas fa-file-invoice' : 'fas fa-receipt'" class="text-[9px]"></i>
                    <span>Los productos se cotizarán con <strong>{{ tipoPrecioLabel }}</strong></span>
                </div>
            </div>

            <!-- ✅ PROGRESO JERÁRQUICO -->
            <div v-if="progresoJerarquico.length > 0 && hayProductosEnCarrito"
                 class="bg-white rounded-xl shadow-sm mb-4 border-l-4 overflow-hidden"
                 :class="cumpleTodosMinimos ? 'border-green-500' : 'border-orange-500'">

                <!-- Header -->
                <div class="p-4 pb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-chart-line" :class="cumpleTodosMinimos ? 'text-green-500' : 'text-orange-500'"></i>
                        Progreso del Pedido
                    </h2>
                    <span class="text-[10px] px-2 py-1 rounded-full font-medium"
                          :class="cumpleTodosMinimos ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'">
                        <i :class="cumpleTodosMinimos ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'" class="mr-1"></i>
                        {{ cumpleTodosMinimos ? 'Todo listo' : `${totalPendientes} pendiente(s)` }}
                    </span>
                </div>

                <!-- Lista de grupos -->
                <div class="px-4 pb-4 space-y-3">
                    <div v-for="grupo in progresoJerarquico"
                         :key="'g-' + grupo.IdGrupoAnalisis"
                         class="rounded-lg border-2 overflow-hidden"
                         :class="grupo.Cumple ? 'border-green-200 bg-green-50/30' : 'border-orange-200 bg-orange-50/30'">

                        <!-- GRUPO header -->
                        <div class="p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-white text-[10px]"
                                          :class="grupo.Cumple ? 'bg-green-500' : 'bg-orange-500'">
                                        <i :class="grupo.Cumple ? 'fas fa-check' : 'fas fa-exclamation'"></i>
                                    </span>
                                    <span class="text-[8px] uppercase tracking-wide font-bold px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 flex-shrink-0">
                                        Grupo
                                    </span>
                                    <span class="font-bold text-gray-800 text-sm truncate">{{ grupo.NombreGrupo }}</span>
                                </div>
                                <span class="text-sm font-bold whitespace-nowrap ml-2"
                                      :class="grupo.Cumple ? 'text-green-600' : 'text-orange-600'">
                                    {{ grupo.CantidadPedida }} / {{ grupo.CantidadMinima }}
                                </span>
                            </div>

                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full transition-all duration-300 rounded-full"
                                     :class="grupo.Cumple ? 'bg-green-500' : 'bg-orange-500'"
                                     :style="{ width: grupo.Porcentaje + '%' }"></div>
                            </div>

                            <p v-if="!grupo.Cumple" class="text-[10px] text-orange-600 mt-1.5 flex items-center gap-1">
                                <i class="fas fa-arrow-right"></i>
                                Faltan <strong>{{ grupo.Falta }}</strong> und para el grupo
                            </p>
                            <p v-else class="text-[10px] text-green-600 mt-1.5 flex items-center gap-1">
                                <i class="fas fa-check"></i> Mínimo del grupo alcanzado
                            </p>
                        </div>

                        <!-- PRODUCTOS dentro del grupo -->
                        <div v-if="grupo.productos.length > 0"
                             class="border-t border-gray-200 bg-white/50 p-2 space-y-1.5">
                            <p class="text-[9px] uppercase tracking-wide font-bold text-gray-500 px-1 mb-1">
                                <i class="fas fa-box mr-1"></i>
                                Productos con mínimo ({{ grupo.productos.length }})
                            </p>

                            <div v-for="prod in grupo.productos"
                                 :key="'p-' + prod.IdProducto"
                                 class="rounded-md border px-2 py-1.5"
                                 :class="prod.Cumple ? 'border-green-200 bg-green-50/50' : 'border-orange-200 bg-orange-50/50'">

                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                        <i class="fas text-[10px] flex-shrink-0"
                                           :class="prod.Cumple ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-orange-500'"></i>
                                        <span class="font-mono text-[9px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded flex-shrink-0">
                                            {{ prod.Codigo }}
                                        </span>
                                        <span class="text-xs text-gray-700 truncate">{{ prod.Descripcion }}</span>
                                    </div>
                                    <span class="text-[11px] font-bold whitespace-nowrap ml-2"
                                          :class="prod.Cumple ? 'text-green-600' : 'text-orange-600'">
                                        {{ prod.CantidadPedida }} / {{ prod.CantidadMinima }}
                                    </span>
                                </div>

                                <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full transition-all duration-300 rounded-full"
                                         :class="prod.Cumple ? 'bg-green-500' : 'bg-orange-500'"
                                         :style="{ width: prod.Porcentaje + '%' }"></div>
                                </div>

                                <p v-if="!prod.Cumple" class="text-[9px] text-orange-600 mt-1">
                                    Faltan <strong>{{ prod.Falta }}</strong> und
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerta resumen -->
                <div v-if="!cumpleTodosMinimos" class="mx-4 mb-4 p-2 bg-orange-50 border border-orange-200 rounded-lg">
                    <p class="text-[10px] font-semibold text-orange-800 mb-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Para finalizar el pedido, debes cumplir TODOS los mínimos marcados en naranja.
                    </p>
                </div>
            </div>

            <!-- CONTENEDORES -->
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-3">
                    <h2 class="text-sm font-semibold text-gray-700">
                        <i class="fas fa-boxes mr-2 text-primary-500"></i>
                        Contenedores
                        <span class="text-xs text-gray-400 font-normal ml-1">({{ contenedores.length }})</span>
                    </h2>
                    <div class="relative w-full sm:w-64">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" v-model="busquedaContenedor" placeholder="Buscar contenedor..."
                               class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-primary-400 outline-none" />
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    <div v-for="contenedor in contenedoresFiltrados"
                         :key="contenedor.IdContenedor"
                         @click="abrirModal(contenedor)"
                         class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all cursor-pointer overflow-hidden border-2 border-transparent hover:border-primary-300">
                        <div class="h-24 bg-gradient-to-br from-primary-50 to-indigo-50 flex items-center justify-center">
                            <div class="w-14 h-14 bg-primary-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-box text-primary-500 text-2xl"></i>
                            </div>
                        </div>
                        <div class="p-3 text-center">
                            <h3 class="font-bold text-sm text-gray-800 truncate">{{ contenedor.Codigo }}</h3>
                            <p class="text-[10px] font-mono text-gray-400">{{ contenedor.TipoContenedor }}</p>
                            <div class="flex justify-center items-center gap-2 mt-1">
                                <span class="text-[10px] text-gray-500">
                                    <i class="fas fa-weight-hanging mr-0.5"></i>
                                    {{ contenedor.CapacidadTotalFormateada }} und
                                </span>
                            </div>
                            <button class="mt-2 w-full py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-xs transition flex items-center justify-center gap-1">
                                <i class="fas fa-plus text-[10px]"></i>
                                Seleccionar
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="contenedores.length === 0" class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">
                    <i class="fas fa-box-open text-3xl mb-2 block"></i>
                    <p class="text-sm">No hay contenedores disponibles</p>
                    <p class="text-xs mt-1">Contacte al administrador</p>
                </div>
            </div>

        </div>

        <!-- MODAL DE PRODUCTOS -->
        <CreateModalProductos
            :visible="modalVisible"
            :contenedor="contenedorSeleccionado"
            :idIdentificador="idIdentificador"
            :modoEdicion="false"
            :datosEdicion="null"
            :tipoPrecio="tipoPrecioActual"
            :subclientes="subclientes"
            :idSubClienteOperadorDefault="idSubClienteDefault"
            @close="cerrarModal"
            @agregar="agregarContenedorAlCarrito"
            @actualizar="agregarContenedorAlCarrito"
        />

        <!-- MODAL CAMBIO TIPO -->
        <div v-if="modalCambiarTipoVisible"
             class="fixed inset-0 bg-black/50 flex items-center justify-center z-[70] p-4"
             @click.self="cerrarModalCambiarTipo">
            <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-2xl">
                <div class="p-4 border-b bg-orange-50 flex items-center gap-3">
                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-orange-600 text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-800">Cambiar Tipo de Precio</h3>
                        <p class="text-[10px] text-gray-500">Esta acción afectará a todo el carrito</p>
                    </div>
                </div>
                <div class="p-4">
                    <p class="text-sm text-gray-700 text-center">
                        Tienes <strong class="text-primary-600">{{ totalContenedoresCarrito }} contenedor(es)</strong> en el carrito.
                    </p>
                    <p class="text-sm text-gray-700 text-center mt-2">
                        Al cambiar a <strong class="text-blue-600">{{ tipoPrecioSeleccionado === 'con_factura' ? 'Con Factura' : 'Sin Factura' }}</strong> se recalcularán los precios.
                    </p>
                    <p class="text-xs text-center mt-3 text-gray-500">¿Deseas continuar?</p>
                </div>
                <div class="p-3 bg-gray-50 flex justify-end gap-2">
                    <button @click="cerrarModalCambiarTipo" :disabled="cambiandoTipo"
                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-medium">
                        Cancelar
                    </button>
                    <button @click="aplicarCambioTipo" :disabled="cambiandoTipo"
                            class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-xs font-medium flex items-center gap-1.5 disabled:opacity-50">
                        <i v-if="cambiandoTipo" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-check text-[10px]"></i>
                        {{ cambiandoTipo ? 'Procesando...' : 'Sí, cambiar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-fade-in-up { animation: fadeInUp 0.2s ease-out; }
</style>