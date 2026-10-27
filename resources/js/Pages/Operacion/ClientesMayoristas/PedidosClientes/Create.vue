<script setup>
import { ref, computed, onMounted, inject, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import CreateModalProductos from './CreateModalProductos.vue'
import CreateModalContenedorProductoUnico from './CreateModalContenedorProductoUnico.vue'

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
    minimosGrupos: { type: Array, default: () => [] },
    minimosProductos: { type: [Array, Object], default: () => ({}) },
    infoProductos: { type: [Array, Object], default: () => ({}) },
    progresoInicial: { type: Array, default: () => [] },
    tipoPrecio: { type: String, default: 'sin_factura' },
    subclientes: { type: Array, default: () => [] },
    idSubClienteDefault: { type: [Number, String], default: null },
})

// ==================== ESTADO ====================
const loading = ref(false)
const modalVisible = ref(false)
const modalProductoUnicoVisible = ref(false)
const contenedorSeleccionado = ref(null)
const busquedaContenedor = ref('')

const tipoPrecioActual = ref(props.tipoPrecio || 'sin_factura')
const modalCambiarTipoVisible = ref(false)
const tipoPrecioSeleccionado = ref('sin_factura')
const cambiandoTipo = ref(false)

const carritoItems = ref([])
const minimos = ref([])
const minimosProd = ref({})
const productosInfo = ref({})

// 🔥 EL FIX: Calculamos el ID del pedido dinámicamente
const pedidoId = computed(() => {
    if (carritoItems.value.length > 0 && carritoItems.value[0].IdPedidoCliente) {
        return carritoItems.value[0].IdPedidoCliente
    }
    if (props.pedidoBorrador) {
        return props.pedidoBorrador.IdPedidoCliente
    }
    return null
})

// ==================== COMPUTADOS ====================
const totalCarrito = computed(() => {
    let total = 0
    carritoItems.value.forEach(item => {
        if (item.productos) {
            item.productos.forEach(p => {
                total += Number(p.Cantidad) || 0
            })
        }
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

// 🔥 Acumulados mejorados para evitar errores de tipo
const acumuladoPorGrupo = computed(() => {
    const acumulado = {}
    carritoItems.value.forEach(item => {
        if (item.productos) {
            item.productos.forEach(p => {
                const grupoId = Number(p.IdGrupoAnalisis)
                if (grupoId) {
                    acumulado[grupoId] = (acumulado[grupoId] || 0) + (Number(p.Cantidad) || 0)
                }
            })
        }
    })
    return acumulado
})

const acumuladoPorProducto = computed(() => {
    const acumulado = {}
    carritoItems.value.forEach(item => {
        if (item.productos) {
            item.productos.forEach(p => {
                const prodId = Number(p.IdProducto)
                if (prodId) {
                    acumulado[prodId] = (acumulado[prodId] || 0) + (Number(p.Cantidad) || 0)
                }
            })
        }
    })
    return acumulado
})

// 🔥 Cálculo del progreso reactivo
const progresoJerarquico = computed(() => {
    const acumG = acumuladoPorGrupo.value
    const acumP = acumuladoPorProducto.value
    const resultado = {}

    minimos.value.forEach(minimo => {
        const idGrupo = Number(minimo.IdGrupoAnalisis)
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

    Object.keys(minimosProd.value).forEach(idProd => {
        const idProducto = Number(idProd)
        if (acumP[idProducto] === undefined) return

        const pedida = acumP[idProducto] || 0
        const minima = Number(minimosProd.value[idProd]) || 0
        const info = productosInfo.value[idProducto]

        if (!info) return

        const idGrupo = Number(info.IdGrupoAnalisis)
        const grupo = resultado[idGrupo]

        if (!grupo) {
            const minG = minimos.value.find(m => Number(m.IdGrupoAnalisis) === idGrupo)
            resultado[idGrupo] = {
                IdGrupoAnalisis: idGrupo,
                NombreGrupo: minG ? minG.NombreGrupo : `Grupo ${idGrupo}`,
                CantidadMinima: minG ? Number(minG.CantidadMinimaGrupo) : 0,
                CantidadPedida: acumG[idGrupo] || 0,
                Cumple: (acumG[idGrupo] || 0) >= (minG ? Number(minG.CantidadMinimaGrupo) : 0),
                Falta: Math.max(0, (minG ? Number(minG.CantidadMinimaGrupo) : 0) - (acumG[idGrupo] || 0)),
                Porcentaje: (minG && minG.CantidadMinimaGrupo > 0) ? Math.min(((acumG[idGrupo] || 0) / minG.CantidadMinimaGrupo) * 100, 100) : 100,
                productos: []
            }
        }

        resultado[idGrupo].productos.push({
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
        ? 'bg-blue-50 text-blue-700 border-blue-200'
        : 'bg-gray-50 text-gray-700 border-gray-200'
)

// ==================== FUNCIONES ====================
const sincronizarCarrito = (nuevoCarrito) => {
    if (nuevoCarrito && nuevoCarrito.length > 0) {
        carritoItems.value = nuevoCarrito.map(item => ({
            ...item,
            productos: (item.productos || []).map(p => ({
                ...p,
                Cantidad: Number(p.Cantidad) || 0,
                IdGrupoAnalisis: Number(p.IdGrupoAnalisis) || null
            }))
        }))
    } else {
        carritoItems.value = []
    }
}

const abrirModal = (contenedor) => {
    contenedorSeleccionado.value = contenedor

    if (contenedor.EsProductoUnico) {
        modalProductoUnicoVisible.value = true
    } else {
        modalVisible.value = true
    }
}

const cerrarModal = () => {
    modalVisible.value = false
    contenedorSeleccionado.value = null
}

const cerrarModalProductoUnico = () => {
    modalProductoUnicoVisible.value = false
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
            router.reload({ only: ['carrito', 'pedidoBorrador'] })
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al agregar al carrito')
    } finally {
        loading.value = false
    }
}

const agregarRejillasAlCarrito = async (data) => {
    loading.value = true
    try {
        const payload = { ...data, TipoPrecio: tipoPrecioActual.value }
        const response = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/pedidos-clientes/carrito/agregar-rejillas',
            payload
        )
        if (response.data.success) {
            toast?.success('Éxito', response.data.message || 'Rejillas agregadas al carrito')
            router.reload({ only: ['carrito', 'pedidoBorrador'] })
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al agregar rejillas')
    } finally {
        loading.value = false
    }
}

const irARevisarPedido = () => {
    if (!pedidoId.value) {
        toast?.warning('Carrito vacío', 'Agregue productos antes de revisar')
        return
    }
    router.visit(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${pedidoId.value}/review`, {
        replace: true,
    })
}

// ==================== WATCHERS ====================
watch(() => props.carrito, (newVal) => {
    sincronizarCarrito(newVal)
}, { immediate: true, deep: true })

watch(() => props.minimosGrupos, (newVal) => {
    minimos.value = [...newVal]
}, { immediate: true, deep: true })

watch(() => props.minimosProductos, (newVal) => {
    minimosProd.value = { ...newVal }
}, { immediate: true, deep: true })

watch(() => props.infoProductos, (newVal) => {
    productosInfo.value = { ...newVal }
}, { immediate: true, deep: true })

watch(() => props.tipoPrecio, (newVal) => {
    tipoPrecioActual.value = newVal
}, { immediate: true })

onMounted(() => {
    sincronizarCarrito(props.carrito)
    minimos.value = [...props.minimosGrupos]
    minimosProd.value = { ...props.minimosProductos }
    productosInfo.value = { ...props.infoProductos }
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
                            <i class="fas fa-shopping-cart text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Nuevo Pedido</h1>
                            <p class="text-xs text-gray-500">
                                Seleccione contenedores y agregue productos
                                <span v-if="nombreOperador" class="text-primary-600 font-medium">• {{ nombreOperador }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div v-if="hayProductosEnCarrito" class="hidden sm:flex items-center gap-2 text-[10px] bg-white px-2.5 py-1 rounded-lg border border-gray-200">
                            <span class="text-gray-500"><i class="fas fa-shopping-cart text-primary-500 mr-1"></i><strong class="text-gray-700">{{ totalContenedoresCarrito }}</strong></span>
                            <span class="text-gray-300">|</span>
                            <span class="text-gray-500"><i class="fas fa-cubes text-primary-500 mr-1"></i><strong class="text-primary-600">{{ totalCarrito }}</strong></span>
                        </div>
                        <button
                            @click="irARevisarPedido"
                            :disabled="!hayProductosEnCarrito"
                            class="px-4 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="hayProductosEnCarrito
                                ? (cumpleTodosMinimos ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white')
                                : 'bg-gray-200 text-gray-500'"
                        >
                            <i :class="cumpleTodosMinimos && hayProductosEnCarrito ? 'fas fa-check-circle' : 'fas fa-clipboard-list'" class="text-[10px]"></i>
                            Revisar Pedido
                            <span v-if="hayProductosEnCarrito" class="bg-white/20 rounded-full px-1.5 py-0.5 text-[9px]">
                                {{ totalContenedoresCarrito }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- ==================== TIPO DE PRECIO ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-file-invoice-dollar text-primary-600 text-[10px]"></i>
                            </div>
                            <div>
                                <h2 class="text-xs font-bold text-gray-800">Tipo de Precio</h2>
                                <p class="text-[9px] text-gray-500">Selecciona cómo se cotizará el pedido</p>
                            </div>
                        </div>
                        <div class="flex bg-gray-100 rounded-md p-0.5">
                            <button
                                @click="abrirModalCambiarTipo('sin_factura')"
                                :disabled="cambiandoTipo"
                                class="px-3 py-1.5 rounded text-[10px] font-medium transition flex items-center justify-center gap-1"
                                :class="tipoPrecioActual === 'sin_factura' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            >
                                <i class="fas fa-receipt text-[8px]"></i>
                                Sin Factura
                                <i v-if="tipoPrecioActual === 'sin_factura'" class="fas fa-check-circle text-emerald-500 text-[8px]"></i>
                            </button>
                            <button
                                @click="abrirModalCambiarTipo('con_factura')"
                                :disabled="cambiandoTipo"
                                class="px-3 py-1.5 rounded text-[10px] font-medium transition flex items-center justify-center gap-1"
                                :class="tipoPrecioActual === 'con_factura' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            >
                                <i class="fas fa-file-invoice text-[8px]"></i>
                                Con Factura
                                <i v-if="tipoPrecioActual === 'con_factura'" class="fas fa-check-circle text-emerald-500 text-[8px]"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mt-1.5 flex items-center gap-1.5 text-[9px] px-2 py-1 rounded border" :class="tipoPrecioColor">
                        <i :class="tipoPrecioActual === 'con_factura' ? 'fas fa-file-invoice' : 'fas fa-receipt'" class="text-[8px]"></i>
                        <span>Los productos se cotizarán con <strong>{{ tipoPrecioLabel }}</strong></span>
                    </div>
                </div>

                <!-- ==================== CONTENEDORES ==================== -->
                <div class="mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <h2 class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                            <i class="fas fa-boxes text-primary-500 text-[10px]"></i>
                            Contenedores
                            <span class="text-[9px] text-gray-400 font-normal">({{ contenedores.length }})</span>
                        </h2>
                        <div class="relative w-full sm:w-56">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                            <input type="text" v-model="busquedaContenedor" placeholder="Buscar contenedor..."
                                   class="w-full border border-gray-300 rounded-md pl-7 pr-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                        <div v-for="contenedor in contenedoresFiltrados"
                             :key="contenedor.IdContenedor"
                             @click="abrirModal(contenedor)"
                             class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all cursor-pointer overflow-hidden border"
                             :class="contenedor.EsProductoUnico
                                 ? 'border-purple-200 hover:border-purple-400'
                                 : 'border-gray-200 hover:border-primary-300'">
                            <div class="h-16 flex items-center justify-center"
                                 :class="contenedor.EsProductoUnico
                                     ? 'bg-gradient-to-br from-purple-50 to-indigo-50'
                                     : 'bg-gradient-to-br from-primary-50 to-indigo-50'">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                     :class="contenedor.EsProductoUnico ? 'bg-purple-100' : 'bg-primary-100'">
                                    <i class="fas text-base"
                                       :class="contenedor.EsProductoUnico ? 'fa-cube text-purple-500' : 'fa-box text-primary-500'"></i>
                                </div>
                            </div>
                            <div class="p-2 text-center">
                                <h3 class="font-bold text-xs text-gray-800 truncate">{{ contenedor.Codigo }}</h3>
                                <p class="text-[8px] font-mono text-gray-400">{{ contenedor.TipoContenedor }}</p>

                                <div class="flex justify-center mt-1">
                                    <span v-if="contenedor.EsProductoUnico"
                                          class="text-[8px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full font-bold">
                                        <i class="fas fa-cube text-[7px] mr-0.5"></i>
                                        Único
                                    </span>
                                    <span v-else class="text-[9px] text-gray-500">
                                        <i class="fas fa-weight-hanging mr-0.5 text-[8px]"></i>
                                        {{ contenedor.CapacidadTotalFormateada }} und
                                    </span>
                                </div>

                                <button class="mt-1.5 w-full py-1 rounded-md text-[10px] font-medium transition flex items-center justify-center gap-1 text-white"
                                        :class="contenedor.EsProductoUnico
                                            ? 'bg-purple-600 hover:bg-purple-700'
                                            : 'bg-primary-600 hover:bg-primary-700'">
                                    <i class="fas text-[8px]"
                                       :class="contenedor.EsProductoUnico ? 'fa-cube' : 'fa-plus'"></i>
                                    {{ contenedor.EsProductoUnico ? 'Elegir' : 'Seleccionar' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="contenedores.length === 0" class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">
                        <i class="fas fa-box-open text-2xl mb-2 block"></i>
                        <p class="text-sm">No hay contenedores disponibles</p>
                    </div>
                </div>

                <!-- ==================== PROGRESO COMPACTO ==================== -->
                <div v-if="progresoJerarquico.length > 0 && hayProductosEnCarrito"
                     class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

                    <!-- HEADER DEL PROGRESO -->
                    <div class="px-3 py-2 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2"
                         :class="cumpleTodosMinimos ? 'bg-emerald-50' : 'bg-amber-50'">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center"
                                 :class="cumpleTodosMinimos ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'">
                                <i class="fas fa-chart-pie text-xs"></i>
                            </div>
                            <div>
                                <h2 class="text-xs font-bold text-gray-800">Progreso del Pedido</h2>
                                <p class="text-[9px] text-gray-500">Revisa si cumples con los mínimos</p>
                            </div>
                        </div>
                        <span class="text-[9px] px-2 py-0.5 rounded-full font-bold border flex items-center gap-1"
                              :class="cumpleTodosMinimos
                                  ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                  : 'bg-amber-100 text-amber-700 border-amber-200'">
                            <i :class="cumpleTodosMinimos ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'" class="text-[8px]"></i>
                            {{ cumpleTodosMinimos ? 'Todo listo' : `${totalPendientes} pendiente(s)` }}
                        </span>
                    </div>

                    <!-- CUERPO DEL PROGRESO -->
                    <div class="p-2 space-y-2">
                        <div v-for="grupo in progresoJerarquico"
                             :key="'g-' + grupo.IdGrupoAnalisis"
                             class="rounded-lg border bg-white overflow-hidden"
                             :class="grupo.Cumple ? 'border-emerald-200' : 'border-amber-200'">

                            <!-- Cabecera del Grupo -->
                            <div class="px-2.5 py-1.5 flex items-center justify-between gap-2 border-b"
                                 :class="grupo.Cumple ? 'bg-emerald-50/50 border-emerald-100' : 'bg-amber-50/50 border-amber-100'">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 text-white text-[8px]"
                                         :class="grupo.Cumple ? 'bg-emerald-500' : 'bg-amber-500'">
                                        <i :class="grupo.Cumple ? 'fas fa-check' : 'fas fa-layer-group'"></i>
                                    </div>
                                    <span class="text-[8px] uppercase tracking-wider font-bold px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 flex-shrink-0">
                                        G
                                    </span>
                                    <span class="font-bold text-gray-800 text-[11px] truncate">{{ grupo.NombreGrupo }}</span>
                                </div>
                                <span class="text-[11px] font-bold whitespace-nowrap"
                                      :class="grupo.Cumple ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ grupo.CantidadPedida }}<span class="text-[9px] text-gray-400 font-normal">/{{ grupo.CantidadMinima }}</span>
                                </span>
                            </div>

                            <!-- Barra de progreso del grupo -->
                            <div class="px-2.5 py-1">
                                <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden flex">
                                    <div class="h-full transition-all duration-500 ease-out rounded-full"
                                         :class="grupo.Cumple ? 'bg-emerald-500' : 'bg-amber-500'"
                                         :style="{ width: grupo.Porcentaje + '%' }">
                                    </div>
                                </div>
                                <p v-if="!grupo.Cumple" class="text-[8px] text-amber-600 mt-0.5 font-medium">
                                    <i class="fas fa-arrow-trend-up mr-0.5 text-[7px]"></i> Faltan <strong>{{ grupo.Falta }}</strong>
                                </p>
                                <p v-else class="text-[8px] text-emerald-600 mt-0.5 font-medium">
                                    <i class="fas fa-check mr-0.5 text-[7px]"></i> Mínimo alcanzado
                                </p>
                            </div>

                            <!-- Productos del grupo -->
                            <div v-if="grupo.productos.length > 0" class="px-2 pb-2 bg-gray-50/30 border-t border-gray-100">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5 mt-1.5">
                                    <div v-for="prod in grupo.productos"
                                         :key="'p-' + prod.IdProducto"
                                         class="rounded-md border bg-white p-1.5"
                                         :class="prod.Cumple ? 'border-emerald-200' : 'border-amber-200'">
                                        <div class="flex items-center justify-between gap-1 mb-1">
                                            <div class="flex items-center gap-1 min-w-0 flex-1">
                                                <i class="fas text-[8px] flex-shrink-0"
                                                   :class="prod.Cumple ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-circle text-amber-500'"></i>
                                                <span class="font-mono text-[8px] text-gray-500 bg-gray-100 px-1 py-0.5 rounded flex-shrink-0">
                                                    {{ prod.Codigo }}
                                                </span>
                                            </div>
                                            <span class="text-[9px] font-bold whitespace-nowrap"
                                                  :class="prod.Cumple ? 'text-emerald-600' : 'text-amber-600'">
                                                {{ prod.CantidadPedida }}<span class="text-[7px] text-gray-400 font-normal">/{{ prod.CantidadMinima }}</span>
                                            </span>
                                        </div>
                                        <p class="text-[9px] text-gray-600 truncate mb-1" :title="prod.Descripcion">{{ prod.Descripcion }}</p>
                                        <div class="w-full h-1 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full transition-all duration-500 rounded-full"
                                                 :class="prod.Cumple ? 'bg-emerald-400' : 'bg-amber-400'"
                                                 :style="{ width: prod.Porcentaje + '%' }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer del Progreso -->
                    <div v-if="!cumpleTodosMinimos" class="px-3 py-2 bg-amber-50 border-t border-amber-100">
                        <p class="text-[9px] text-amber-800 flex items-start gap-1.5">
                            <i class="fas fa-info-circle text-amber-500 mt-0.5 text-[9px]"></i>
                            <span>Debes cumplir todos los mínimos para finalizar el pedido.</span>
                        </p>
                    </div>
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

        <!-- MODAL PRODUCTO ÚNICO -->
        <CreateModalContenedorProductoUnico
            :visible="modalProductoUnicoVisible"
            :contenedor="contenedorSeleccionado"
            :idIdentificador="idIdentificador"
            :tipoPrecio="tipoPrecioActual"
            :subclientes="subclientes"
            :idSubClienteOperadorDefault="idSubClienteDefault"
            :nombreOperador="nombreOperador"
            @close="cerrarModalProductoUnico"
            @agregar="agregarRejillasAlCarrito"
        />

        <!-- MODAL CAMBIO TIPO -->
        <div v-if="modalCambiarTipoVisible"
             class="fixed inset-0 bg-black/50 flex items-center justify-center z-[70] p-4"
             @click.self="cerrarModalCambiarTipo">
            <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-2xl">
                <div class="px-4 py-2.5 bg-amber-600 flex items-center gap-2">
                    <div class="w-7 h-7 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-white text-xs"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-white text-sm">Cambiar Tipo de Precio</h3>
                        <p class="text-[9px] text-white/80">Esta acción afectará a todo el carrito</p>
                    </div>
                </div>
                <div class="p-4">
                    <p class="text-sm text-gray-700 text-center">
                        Tienes <strong class="text-primary-600">{{ totalContenedoresCarrito }} contenedor(es)</strong> en el carrito.
                    </p>
                    <p class="text-sm text-gray-700 text-center mt-1.5">
                        Al cambiar a <strong class="text-blue-600">{{ tipoPrecioSeleccionado === 'con_factura' ? 'Con Factura' : 'Sin Factura' }}</strong> se recalcularán los precios.
                    </p>
                    <p class="text-[10px] text-center mt-2 text-gray-500">¿Deseas continuar?</p>
                </div>
                <div class="px-4 py-2.5 bg-gray-50 flex justify-end gap-2">
                    <button @click="cerrarModalCambiarTipo" :disabled="cambiandoTipo"
                            class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-xs font-medium">
                        Cancelar
                    </button>
                    <button @click="aplicarCambioTipo" :disabled="cambiandoTipo"
                            class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-md text-xs font-medium flex items-center gap-1.5 disabled:opacity-50">
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
</style>