<script setup>
import { ref, computed, inject, onMounted, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import ShowPedido from './ShowPedido.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    pedidos: {
        type: Object,
        default: () => ({ data: [] })
    },
    // ✅ NUEVAS PROPS para validar si puede hacer pedidos
    puedeHacerPedidos: {
        type: Boolean,
        default: true
    },
    razonNoPuedePedir: {
        type: String,
        default: null
    },
})

// ==================== ESTADO ====================
const buscador = ref('')
const estadoFiltro = ref('')
const mostrarModal = ref(false)
const pedidoSeleccionado = ref(null)
const detallesAgrupados = ref([])
const cargandoDetalle = ref(false)

// ✅ Modal de "no puede pedir"
const modalNoPuedePedirVisible = ref(false)

// ✅ Alerta flash (opcional, para errores genéricos)
const alertaFlash = ref({ visible: false, tipo: 'error', mensaje: '' })

// ==================== COMPUTED ====================
const pedidosFiltrados = computed(() => {
    if (!props.pedidos?.data) return []

    let filtrados = props.pedidos.data

    if (buscador.value) {
        const termino = buscador.value.toLowerCase()
        filtrados = filtrados.filter(p => {
            const numero = p.NumeroPedido?.toString() || ''
            const cliente = p.cliente?.Nombre || ''
            return numero.toLowerCase().includes(termino) ||
                   cliente.toLowerCase().includes(termino)
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

    // Auto-cerrar después de 8 segundos
    setTimeout(() => {
        alertaFlash.value.visible = false
    }, 8000)
}

watch(() => page.props.flash, (flash) => {
    if (flash?.error) {
        mostrarFlash('error', flash.error)
    }
    if (flash?.success) {
        mostrarFlash('success', flash.success)
    }
}, { deep: true, immediate: true })

// ==================== FUNCIONES ====================
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    })
}

const formatearFechaHora = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const getEstadoBadge = (estado) => {
    const badges = {
        'Borrador': 'bg-yellow-100 text-yellow-800',
        'Pendiente': 'bg-blue-100 text-blue-800',
        'En Proceso': 'bg-orange-100 text-orange-800',
        'Entregado': 'bg-green-100 text-green-800',
        'Cancelado': 'bg-red-100 text-red-800'
    }
    return badges[estado] || 'bg-gray-100 text-gray-800'
}

const getEstadoIcono = (estado) => {
    const iconos = {
        'Borrador': 'fa-pencil-alt',
        'Pendiente': 'fa-clock',
        'En Proceso': 'fa-cog',
        'Entregado': 'fa-check-circle',
        'Cancelado': 'fa-times-circle'
    }
    return iconos[estado] || 'fa-circle'
}

const formatearNumero = (valor) => {
    if (valor === undefined || valor === null) return '0'
    return Number(valor).toFixed(0)
}

const abrirPdf = (id) => {
    const url = `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${id}/pdf`
    window.open(url, '_blank')
}

// ✅ VALIDAR ANTES DE IR A NUEVO PEDIDO
const intentarNuevoPedido = () => {
    if (!props.puedeHacerPedidos) {
        modalNoPuedePedirVisible.value = true
        return
    }
    router.visit('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create')
}

const cerrarModalNoPuedePedir = () => {
    modalNoPuedePedirVisible.value = false
}

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
        console.error('❌ Error cargando detalles:', error)
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
    }, {
        preserveState: true,
        replace: true
    })
}

const limpiarFiltros = () => {
    buscador.value = ''
    estadoFiltro.value = ''
    aplicarFiltros()
}

const cerrarAlerta = () => {
    alertaFlash.value.visible = false
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search)
    if (urlParams.get('buscar')) {
        buscador.value = urlParams.get('buscar')
    }
    if (urlParams.get('estado')) {
        estadoFiltro.value = urlParams.get('estado')
    }
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-10">
        <div class="max-w-7xl mx-auto px-3 py-4">

            <!-- ALERTAS FLASH (error/success genéricos) -->
            <div v-if="alertaFlash.visible"
                 class="mb-4 p-3 rounded-xl border-l-4 shadow-md flex items-start gap-3 animate-fade-in"
                 :class="alertaFlash.tipo === 'error'
                     ? 'bg-red-50 border-red-500 text-red-800'
                     : 'bg-green-50 border-green-500 text-green-800'">

                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                     :class="alertaFlash.tipo === 'error' ? 'bg-red-100' : 'bg-green-100'">
                    <i :class="alertaFlash.tipo === 'error'
                            ? 'fas fa-exclamation-circle text-red-600'
                            : 'fas fa-check-circle text-green-600'"
                       class="text-base"></i>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold mb-0.5">
                        {{ alertaFlash.tipo === 'error' ? 'No se puede continuar' : 'Éxito' }}
                    </p>
                    <p class="text-xs leading-relaxed break-words whitespace-pre-line">{{ alertaFlash.mensaje }}</p>
                </div>

                <button @click="cerrarAlerta"
                        class="text-gray-400 hover:text-gray-600 p-1 rounded-lg flex-shrink-0 transition">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <!-- HEADER -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Mis Pedidos</h1>
                    <p class="text-[10px] text-gray-400">
                        {{ totalPedidos }} pedido(s) realizados
                    </p>
                </div>
                <button
                    @click="intentarNuevoPedido"
                    class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition flex items-center gap-2 shadow-sm"
                >
                    <i class="fas fa-plus"></i>
                    Nuevo Pedido
                </button>
            </div>

            <!-- FILTROS -->
            <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 flex-1 min-w-[150px]">
                        <div class="relative flex-1">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            <input
                                type="text"
                                v-model="buscador"
                                @input="aplicarFiltros"
                                placeholder="Buscar por número o cliente..."
                                class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none"
                            />
                            <button
                                v-if="buscador"
                                @click="buscador = ''; aplicarFiltros()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500">Estado:</label>
                        <select
                            v-model="estadoFiltro"
                            @change="aplicarFiltros"
                            class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none"
                        >
                            <option value="">Todos</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="En Proceso">En Proceso</option>
                            <option value="Entregado">Entregado</option>
                            <option value="Cancelado">Cancelado</option>
                        </select>
                    </div>

                    <button
                        v-if="buscador || estadoFiltro"
                        @click="limpiarFiltros"
                        class="text-xs text-gray-400 hover:text-gray-600 transition"
                    >
                        <i class="fas fa-times mr-1"></i>
                        Limpiar filtros
                    </button>
                </div>
            </div>

            <!-- LISTA DE PEDIDOS -->
            <div v-if="pedidosFiltrados.length > 0" class="space-y-3">
                <div
                    v-for="pedido in pedidosFiltrados"
                    :key="pedido.IdPedidoCliente"
                    class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow overflow-hidden border border-gray-100"
                >
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="font-bold text-primary-700 text-sm font-mono">
                                    #{{ pedido.NumeroPedido || 'Nuevo' }}
                                </span>
                                <span
                                    class="px-2 py-0.5 text-[10px] rounded-full font-medium"
                                    :class="getEstadoBadge(pedido.EstadoPedido)"
                                >
                                    <i :class="getEstadoIcono(pedido.EstadoPedido)" class="mr-1 text-[8px]"></i>
                                    {{ pedido.EstadoPedido || 'Borrador' }}
                                </span>
                                <span class="text-xs text-gray-400">
                                    <i class="fas fa-boxes mr-1"></i>
                                    {{ pedido.TotalContenedores || 0 }} contenedor(es)
                                </span>
                                <span class="text-xs font-semibold text-primary-600">
                                    {{ formatearNumero(pedido.TotalUnidades) }} und
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-gray-500">
                                <span>
                                    <i class="fas fa-calendar mr-1"></i>
                                    {{ formatearFechaHora(pedido.FechaPedido) }}
                                </span>
                                <span v-if="pedido.FechaEntrega">
                                    <i class="fas fa-calendar-check mr-1 text-green-500"></i>
                                    Entrega: {{ formatearFecha(pedido.FechaEntrega) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button
                                @click="verDetalle(pedido)"
                                class="px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition flex items-center gap-1"
                            >
                                <i class="fas fa-eye text-[10px]"></i>
                                Ver
                            </button>

                            <button
                                v-if="pedido.ActivoInactivo === 1"
                                @click="abrirPdf(pedido.IdPedidoCliente)"
                                class="px-3 py-1.5 text-xs bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition flex items-center gap-1"
                            >
                                <i class="fas fa-file-pdf text-[10px]"></i>
                                PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="props.pedidos?.links" class="flex justify-center mt-4">
                    <div class="flex gap-1 flex-wrap justify-center">
                        <Link
                            v-for="link in props.pedidos.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="px-3 py-1.5 rounded-lg text-sm transition"
                            :class="{
                                'bg-primary-600 text-white': link.active,
                                'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200': !link.active && link.url,
                                'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url
                            }"
                            v-html="link.label"
                            preserve-state
                        />
                    </div>
                </div>
            </div>

            <!-- SIN PEDIDOS -->
            <div v-else class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="w-20 h-20 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-inbox text-primary-400 text-3xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-700">No hay pedidos</h3>
                <p class="text-sm text-gray-400 mt-1">
                    {{ buscador || estadoFiltro ? 'No hay pedidos que coincidan con los filtros' : 'Aún no has realizado ningún pedido' }}
                </p>
                <button
                    v-if="!buscador && !estadoFiltro"
                    @click="intentarNuevoPedido"
                    class="inline-block mt-4 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm transition"
                >
                    <i class="fas fa-plus mr-1"></i>
                    Crear primer pedido
                </button>
            </div>
        </div>

        <!-- MODAL CON ShowPedido -->
        <div
            v-if="mostrarModal && pedidoSeleccionado"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-40 p-4"
            @click.self="cerrarModal"
        >
            <div class="bg-white rounded-xl w-full max-w-4xl max-h-[95vh] overflow-hidden shadow-2xl animate-fade-in-up">
                <div v-if="cargandoDetalle" class="flex items-center justify-center h-64">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-primary-500"></i>
                        <p class="text-sm text-gray-400 mt-2">Cargando detalles del pedido...</p>
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

        <!-- ============================================================ -->
        <!-- MODAL: NO PUEDE HACER PEDIDOS -->
        <!-- ============================================================ -->
        <div
            v-if="modalNoPuedePedirVisible"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4 animate-fade-in"
            @click.self="cerrarModalNoPuedePedir"
        >
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-scale-in">

                <!-- Ícono grande -->
                <div class="pt-6 pb-4 flex flex-col items-center">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4 bg-red-100">
                        <i class="fas fa-box-open text-red-500 text-4xl"></i>
                    </div>

                    <h2 class="text-xl font-bold text-gray-800 text-center px-4">
                        No puedes hacer pedidos
                    </h2>
                </div>

                <!-- Mensaje -->
                <div class="px-6 pb-6">
                    <p class="text-sm text-gray-600 text-center leading-relaxed whitespace-pre-line break-words">
                        {{ razonNoPuedePedir || 'Contacta al administrador para más información.' }}
                    </p>
                </div>

                <!-- Footer -->
                <div class="border-t border-gray-200 p-4 bg-gray-50">
                    <button
                        @click="cerrarModalNoPuedePedir"
                        class="w-full py-3 rounded-xl font-bold text-white bg-red-500 hover:bg-red-600 transition flex items-center justify-center gap-2"
                    >
                        <i class="fas fa-check"></i>
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.25s ease-out;
}

.animate-fade-in {
    animation: fadeIn 0.2s ease-out;
}

.animate-scale-in {
    animation: scaleIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>