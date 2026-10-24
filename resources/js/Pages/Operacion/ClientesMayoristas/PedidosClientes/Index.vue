<script setup>
import { ref, computed, inject, onMounted, onUnmounted, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import ShowPedido from './ShowPedido.vue'
import ModalReclamo from './ModalReclamo.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    pedidos: { type: Object, default: () => ({ data: [] }) },
    puedeHacerPedidos: { type: Boolean, default: true },
    razonNoPuedePedir: { type: String, default: null },
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const handleResize = () => {
    isMobile.value = window.innerWidth < 640
}

// ==================== ESTADO ====================
const buscador = ref('')
const estadoFiltro = ref('')
const mostrarModal = ref(false)
const pedidoSeleccionado = ref(null)
const detallesAgrupados = ref([])
const cargandoDetalle = ref(false)

const modalNoPuedePedirVisible = ref(false)
const alertaFlash = ref({ visible: false, tipo: 'error', mensaje: '' })

const modalReclamoVisible = ref(false)
const pedidoParaReclamo = ref(null)

// ==================== COMPUTED ====================
const pedidosFiltrados = computed(() => {
    if (!props.pedidos?.data) return []
    let filtrados = props.pedidos.data
    if (buscador.value) {
        const termino = buscador.value.toLowerCase()
        filtrados = filtrados.filter(p => {
            const numero = p.NumeroPedido?.toString() || ''
            const cliente = p.cliente?.Nombre || ''
            return numero.toLowerCase().includes(termino) || cliente.toLowerCase().includes(termino)
        })
    }
    if (estadoFiltro.value) {
        filtrados = filtrados.filter(p => p.EstadoPedido === estadoFiltro.value)
    }
    return filtrados
})

const totalPedidos = computed(() => props.pedidos?.total || 0)

// ==================== FLASH ====================
const mostrarFlash = (tipo, mensaje) => {
    alertaFlash.value = { visible: true, tipo, mensaje }
    setTimeout(() => { alertaFlash.value.visible = false }, 8000)
}

watch(() => page.props.flash, (flash) => {
    if (flash?.error) mostrarFlash('error', flash.error)
    if (flash?.success) mostrarFlash('success', flash.success)
}, { deep: true, immediate: true })

// ==================== FORMATEO ====================
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const formatearFechaHora = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const formatearNumero = (valor) => {
    if (valor === undefined || valor === null) return '0'
    return Number(valor).toFixed(0)
}

// ==================== ESTADOS ====================
const getEstadoBadge = (estado) => {
    const badges = {
        'Borrador': 'bg-yellow-100 text-yellow-700 border-yellow-300',
        'Esperando Pago': 'bg-purple-100 text-purple-700 border-purple-300',
        'Pendiente': 'bg-blue-100 text-blue-700 border-blue-300',
        'En Cámara': 'bg-orange-100 text-orange-700 border-orange-300',
        'En Distribución': 'bg-indigo-100 text-indigo-700 border-indigo-300',
        'En Transportadora': 'bg-cyan-100 text-cyan-700 border-cyan-300',
        'Recibido': 'bg-emerald-100 text-emerald-700 border-emerald-300',
        'Con Reclamo': 'bg-red-100 text-red-700 border-red-300',
        'Cancelado': 'bg-gray-200 text-gray-700 border-gray-300'
    }
    return badges[estado] || 'bg-gray-100 text-gray-700 border-gray-300'
}

const getEstadoIcono = (estado) => {
    const iconos = {
        'Borrador': 'fa-pencil-alt',
        'Esperando Pago': 'fa-qrcode',
        'Pendiente': 'fa-clock',
        'En Cámara': 'fa-box-open',
        'En Distribución': 'fa-truck',
        'En Transportadora': 'fa-shipping-fast',
        'Recibido': 'fa-check-double',
        'Con Reclamo': 'fa-exclamation-circle',
        'Cancelado': 'fa-times-circle'
    }
    return iconos[estado] || 'fa-circle'
}

// ==================== PROGRESO COMPACTO ====================
const pasosProgreso = [
    { key: 'Pendiente', label: 'Pagado' },
    { key: 'En Cámara', label: 'Armado' },
    { key: 'En Distribución', label: 'Despachado' },
    { key: 'En Transportadora', label: 'En Camino' },
    { key: 'Recibido', label: 'Recibido' },
]

const getPasoActualIndex = (estado) => {
    if (estado === 'Con Reclamo') return pasosProgreso.length - 1
    return pasosProgreso.findIndex(p => p.key === estado)
}

const getEstadoPaso = (estadoActual, pasoIndex) => {
    const actualIndex = getPasoActualIndex(estadoActual)

    if (estadoActual === 'Con Reclamo') {
        return pasoIndex <= actualIndex ? 'completado-reclamo' : 'pendiente'
    }

    if (actualIndex === -1) return 'pendiente'
    if (pasoIndex < actualIndex) return 'completado'
    if (pasoIndex === actualIndex) return 'actual'
    return 'pendiente'
}

// ==================== ACCIONES ====================
const abrirPdf = (id) => {
    window.open(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${id}/pdf`, '_blank')
}

const intentarNuevoPedido = () => {
    if (!props.puedeHacerPedidos) {
        modalNoPuedePedirVisible.value = true
        return
    }
    router.visit('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create', { replace: true })
}

const cerrarModalNoPuedePedir = () => { modalNoPuedePedirVisible.value = false }

const verDetalle = async (pedido) => {
    pedidoSeleccionado.value = pedido
    mostrarModal.value = true
    cargandoDetalle.value = true
    detallesAgrupados.value = []
    try {
        const response = await axios.get(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${pedido.IdPedidoCliente}/detalles`)
        if (response.data.success) {
            detallesAgrupados.value = response.data.detalles || []
        } else {
            toast?.error('Error', response.data.message || 'No se pudieron cargar los detalles')
        }
    } catch (error) {
        console.error('Error cargando detalles:', error)
        toast?.error('Error', 'No se pudieron cargar los detalles del pedido')
    } finally {
        cargandoDetalle.value = false
    }
}

const cerrarModal = () => {
    mostrarModal.value = false
    pedidoSeleccionado.value = null
    detallesAgrupados.value = []
    cargandoDetalle.value = false
}

const aplicarFiltros = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes', {
        buscar: buscador.value || undefined,
        estado: estadoFiltro.value || undefined
    }, { preserveState: true, replace: true })
}

const limpiarFiltros = () => {
    buscador.value = ''
    estadoFiltro.value = ''
    aplicarFiltros()
}

const cerrarAlerta = () => { alertaFlash.value.visible = false }

const marcarRecibido = async (pedido) => {
    if (!confirm('¿Confirmas que recibiste el pedido en conformidad?')) return
    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/distribucion/${pedido.IdPedidoCliente}/cambiar-estado`,
            { nuevo_estado: 'Recibido' }
        )
        if (response.data.success) {
            toast?.success('¡Pedido Recibido!', 'Gracias por confirmar la recepción.')
            router.reload({ only: ['pedidos'] })
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al confirmar recepción')
    }
}

const abrirModalReclamo = (pedido) => {
    pedidoParaReclamo.value = pedido
    modalReclamoVisible.value = true
}

const cerrarModalReclamo = () => {
    modalReclamoVisible.value = false
    pedidoParaReclamo.value = null
}

const onReclamoSuccess = (resultado) => {
    if (resultado.tipo === 'success') {
        toast?.success('¡Reclamo enviado!', resultado.message)
        router.reload({ only: ['pedidos'] })
    } else if (resultado.tipo === 'error') {
        toast?.error('Error', resultado.message)
    } else {
        toast?.warning('Atención', resultado.message)
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    const urlParams = new URLSearchParams(window.location.search)
    if (urlParams.get('buscar')) buscador.value = urlParams.get('buscar')
    if (urlParams.get('estado')) estadoFiltro.value = urlParams.get('estado')
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-7xl mx-auto">

                <!-- ============================================ -->
                <!-- ALERTAS FLASH -->
                <!-- ============================================ -->
                <div
                    v-if="alertaFlash.visible"
                    class="mb-4 p-2.5 rounded-xl border-l-4 shadow-sm flex items-start gap-2 animate-fade-in"
                    :class="alertaFlash.tipo === 'error' ? 'bg-red-50 border-red-500 text-red-800' : 'bg-emerald-50 border-emerald-500 text-emerald-800'"
                >
                    <div
                        class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0"
                        :class="alertaFlash.tipo === 'error' ? 'bg-red-100' : 'bg-emerald-100'"
                    >
                        <i
                            :class="alertaFlash.tipo === 'error' ? 'fas fa-exclamation-circle text-red-600' : 'fas fa-check-circle text-emerald-600'"
                            class="text-xs"
                        ></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold mb-0.5">{{ alertaFlash.tipo === 'error' ? 'No se puede continuar' : 'Éxito' }}</p>
                        <p class="text-[10px] leading-relaxed break-words whitespace-pre-line">{{ alertaFlash.mensaje }}</p>
                    </div>
                    <button @click="cerrarAlerta" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg flex-shrink-0 transition">
                        <i class="fas fa-times text-[10px]"></i>
                    </button>
                </div>

                <!-- ============================================ -->
                <!-- HEADER -->
                <!-- ============================================ -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Mis Pedidos</h1>
                            <p class="text-xs text-gray-500">{{ totalPedidos }} pedido(s) realizados</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button
                            @click="router.visit('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/mis-reclamos')"
                            class="bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5"
                        >
                            <i class="fas fa-exclamation-circle text-[10px]"></i>
                            Mis Reclamos
                        </button>

                        <button
                            @click="intentarNuevoPedido"
                            class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 shadow-sm"
                        >
                            <i class="fas fa-plus text-[10px]"></i>
                            Nuevo Pedido
                        </button>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- FILTROS -->
                <!-- ============================================ -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4 border border-gray-200">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[180px] max-w-[320px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                <input
                                    type="text"
                                    v-model="buscador"
                                    @input="aplicarFiltros"
                                    placeholder="Número o cliente..."
                                    class="w-full border border-gray-300 rounded-md pl-7 pr-7 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                />
                                <button
                                    v-if="buscador"
                                    @click="buscador = ''; aplicarFiltros()"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Estado</label>
                            <select
                                v-model="estadoFiltro"
                                @change="aplicarFiltros"
                                class="w-40 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                                <option value="">Todos</option>
                                <option value="Pendiente">Pendiente</option>
                                <option value="En Cámara">En Cámara</option>
                                <option value="En Distribución">En Distribución</option>
                                <option value="En Transportadora">En Transportadora</option>
                                <option value="Recibido">Recibido</option>
                                <option value="Con Reclamo">Con Reclamo</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>
                        </div>

                        <button
                            v-if="buscador || estadoFiltro"
                            @click="limpiarFiltros"
                            class="px-2.5 py-1 text-[10px] text-gray-600 hover:text-gray-800 bg-gray-100 rounded-md transition flex items-center gap-1"
                        >
                            <i class="fas fa-eraser text-[9px]"></i>
                            Limpiar
                        </button>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- LISTA DE PEDIDOS -->
                <!-- ============================================ -->
                <div v-if="pedidosFiltrados.length > 0" class="space-y-3">
                    <div
                        v-for="pedido in pedidosFiltrados"
                        :key="pedido.IdPedidoCliente"
                        class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow overflow-hidden border border-gray-200"
                    >
                        <!-- ========== HEADER DEL PEDIDO ========== -->
                        <div class="p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-primary-700 text-sm font-mono">
                                        #{{ pedido.NumeroPedido || 'Nuevo' }}
                                    </span>
                                    <span
                                        class="px-1.5 py-0.5 text-[9px] rounded-full font-bold border"
                                        :class="getEstadoBadge(pedido.EstadoPedido)"
                                    >
                                        <i :class="getEstadoIcono(pedido.EstadoPedido)" class="mr-0.5 text-[8px]"></i>
                                        {{ pedido.EstadoPedido || 'Borrador' }}
                                    </span>
                                    <span class="text-[10px] text-gray-500">
                                        <i class="fas fa-boxes mr-1"></i>
                                        {{ pedido.TotalContenedores || 0 }} cont.
                                    </span>
                                    <span class="text-[10px] font-semibold text-primary-600">
                                        {{ formatearNumero(pedido.TotalUnidades) }} und
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-3 mt-0.5 text-[10px] text-gray-500">
                                    <span><i class="fas fa-calendar mr-1"></i>{{ formatearFechaHora(pedido.FechaPedido) }}</span>
                                    <span v-if="pedido.FechaEntrega">
                                        <i class="fas fa-calendar-check mr-1 text-primary-500"></i>
                                        Entrega: {{ formatearFecha(pedido.FechaEntrega) }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 flex-shrink-0 flex-wrap">
                                <button
                                    v-if="pedido.EstadoPedido === 'En Transportadora'"
                                    @click="marcarRecibido(pedido)"
                                    class="px-2.5 py-1 text-[10px] bg-emerald-500 hover:bg-emerald-600 text-white rounded-md transition flex items-center gap-1 shadow-sm font-medium"
                                >
                                    <i class="fas fa-check-double text-[9px]"></i>
                                    Recibido
                                </button>

                                <button
                                    v-if="pedido.EstadoPedido === 'Recibido'"
                                    @click="abrirModalReclamo(pedido)"
                                    class="px-2.5 py-1 text-[10px] bg-red-500 hover:bg-red-600 text-white rounded-md transition flex items-center gap-1 shadow-sm font-medium"
                                >
                                    <i class="fas fa-exclamation-triangle text-[9px]"></i>
                                    Reclamo
                                </button>

                                <button
                                    @click="verDetalle(pedido)"
                                    class="px-2.5 py-1 text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md transition flex items-center gap-1 font-medium"
                                >
                                    <i class="fas fa-eye text-[9px]"></i>
                                    Ver
                                </button>

                                <button
                                    v-if="pedido.ActivoInactivo === 1"
                                    @click="abrirPdf(pedido.IdPedidoCliente)"
                                    class="px-2.5 py-1 text-[10px] bg-red-50 hover:bg-red-100 text-red-600 rounded-md transition flex items-center gap-1 font-medium"
                                >
                                    <i class="fas fa-file-pdf text-[9px]"></i>
                                    PDF
                                </button>
                            </div>
                        </div>

                        <!-- ========== BARRA DE PROGRESO COMPACTA ========== -->
                        <div
                            v-if="getPasoActualIndex(pedido.EstadoPedido) !== -1"
                            class="px-3 pb-2.5 pt-1 border-t border-gray-100 bg-gray-50/50"
                        >
                            <div class="flex items-center gap-1">
                                <template v-for="(paso, index) in pasosProgreso" :key="paso.key">
                                    <!-- Punto del paso -->
                                    <div
                                        class="w-2 h-2 rounded-full transition-all duration-300 flex-shrink-0"
                                        :class="{
                                            'bg-primary-500': getEstadoPaso(pedido.EstadoPedido, index) === 'completado',
                                            'bg-primary-500 ring-2 ring-primary-200': getEstadoPaso(pedido.EstadoPedido, index) === 'actual',
                                            'bg-red-500': getEstadoPaso(pedido.EstadoPedido, index) === 'completado-reclamo',
                                            'bg-gray-300': getEstadoPaso(pedido.EstadoPedido, index) === 'pendiente',
                                        }"
                                        :title="paso.label"
                                    ></div>

                                    <!-- Línea conectora -->
                                    <div
                                        v-if="index < pasosProgreso.length - 1"
                                        class="flex-1 h-0.5 transition-colors duration-300"
                                        :class="{
                                            'bg-primary-500': getEstadoPaso(pedido.EstadoPedido, index + 1) === 'completado' || getEstadoPaso(pedido.EstadoPedido, index + 1) === 'actual',
                                            'bg-red-500': getEstadoPaso(pedido.EstadoPedido, index + 1) === 'completado-reclamo',
                                            'bg-gray-200': getEstadoPaso(pedido.EstadoPedido, index + 1) === 'pendiente',
                                        }"
                                    ></div>
                                </template>

                                <!-- Label del paso actual -->
                                <span
                                    class="ml-2 text-[9px] font-medium whitespace-nowrap"
                                    :class="pedido.EstadoPedido === 'Con Reclamo' ? 'text-red-600' : 'text-gray-500'"
                                >
                                    {{ pasosProgreso[getPasoActualIndex(pedido.EstadoPedido)]?.label || '' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================ -->
                    <!-- PAGINACIÓN -->
                    <!-- ============================================ -->
                    <div v-if="props.pedidos?.links && props.pedidos.links.length > 3" class="bg-white rounded-xl shadow-sm px-3 py-2 border border-gray-200 mt-3">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <div class="text-[10px] text-gray-500">
                                Mostrando {{ props.pedidos.from || 0 }} a {{ props.pedidos.to || 0 }} de {{ props.pedidos.total || 0 }}
                            </div>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link
                                    v-for="link in props.pedidos.links"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    class="px-2.5 py-1 rounded-lg border text-[10px] transition"
                                    :class="{
                                        'bg-primary-600 text-white border-primary-600': link.active,
                                        'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url,
                                        'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url
                                    }"
                                    v-html="link.label"
                                    preserve-state
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SIN PEDIDOS -->
                <!-- ============================================ -->
                <div v-else class="bg-white rounded-xl shadow-sm p-10 text-center border border-gray-200">
                    <div class="w-16 h-16 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-inbox text-primary-400 text-3xl"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-700">No hay pedidos</h3>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ buscador || estadoFiltro ? 'No hay pedidos que coincidan con los filtros' : 'Aún no has realizado ningún pedido' }}
                    </p>
                    <button
                        v-if="!buscador && !estadoFiltro"
                        @click="intentarNuevoPedido"
                        class="inline-block mt-3 px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition"
                    >
                        <i class="fas fa-plus mr-1 text-[10px]"></i>
                        Crear primer pedido
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- MODAL SHOW PEDIDO -->
        <!-- ============================================ -->
        <Teleport to="body">
            <div
                v-if="mostrarModal && pedidoSeleccionado"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-40 p-3"
                @click.self="cerrarModal"
            >
                <div class="bg-white rounded-xl w-full max-w-4xl max-h-[95vh] overflow-hidden shadow-2xl animate-fade-in-up">
                    <div v-if="cargandoDetalle" class="flex items-center justify-center h-64">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin text-3xl text-primary-500"></i>
                            <p class="text-xs text-gray-400 mt-2">Cargando detalles...</p>
                        </div>
                    </div>
                    <ShowPedido
                        v-else
                        :pedido="pedidoSeleccionado"
                        :detalles-agrupados="detallesAgrupados"
                        :cliente-nombre="pedidoSeleccionado.cliente?.Nombre || 'Sin cliente'"
                        :sucursal-nombre="pedidoSeleccionado.sucursal?.Nombre || 'Sin sucursal'"
                        :operador-nombre="pedidoSeleccionado.operador?.Nombre || 'Sin operador'"
                        @cerrar="cerrarModal"
                    />
                </div>
            </div>
        </Teleport>

        <!-- ============================================ -->
        <!-- MODAL RECLAMO -->
        <!-- ============================================ -->
        <ModalReclamo
            :visible="modalReclamoVisible"
            :pedido="pedidoParaReclamo"
            @close="cerrarModalReclamo"
            @success="onReclamoSuccess"
        />

        <!-- ============================================ -->
        <!-- MODAL NO PUEDE PEDIR -->
        <!-- ============================================ -->
        <Teleport to="body">
            <div
                v-if="modalNoPuedePedirVisible"
                class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-3 animate-fade-in"
                @click.self="cerrarModalNoPuedePedir"
            >
                <div class="bg-white rounded-xl w-full max-w-md shadow-2xl overflow-hidden animate-scale-in">
                    <div class="bg-primary-600 px-4 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-box-open text-white text-base"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-white">No puedes hacer pedidos</h3>
                            </div>
                        </div>
                        <button @click="cerrarModalNoPuedePedir" class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>

                    <div class="p-4">
                        <p class="text-sm text-gray-700 text-center leading-relaxed whitespace-pre-line break-words">
                            {{ razonNoPuedePedir || 'Contacta al administrador para más información.' }}
                        </p>
                    </div>

                    <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end">
                        <button
                            @click="cerrarModalNoPuedePedir"
                            class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5"
                        >
                            <i class="fas fa-check text-[10px]"></i>
                            Entendido
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}
.animate-fade-in-up { animation: fadeInUp 0.25s ease-out; }
.animate-fade-in { animation: fadeIn 0.2s ease-out; }
.animate-scale-in { animation: scaleIn 0.25s cubic-bezier(0.16, 1, 0.3, 1); }

@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}
</style>