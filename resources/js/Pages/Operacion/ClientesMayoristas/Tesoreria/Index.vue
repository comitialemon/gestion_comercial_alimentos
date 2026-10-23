<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    reclamos: { type: Array, default: () => [] },
})

const toast = inject('toast', null) || usePage().props.toast
const modalConfirmacionVisible = ref(false)
const reclamoSeleccionado = ref(null)

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const handleResize = () => {
    isMobile.value = window.innerWidth < 640
}

// ==================== TABS ====================
const tabActiva = ref('pendientes')

const reclamosPendientes = computed(() => props.reclamos.filter(r => r.Estado === 'Aprobado por Calidad'))
const reclamosReembolsados = computed(() => props.reclamos.filter(r => r.Estado === 'Reembolsado'))

const reclamosFiltrados = computed(() => {
    return tabActiva.value === 'pendientes' ? reclamosPendientes.value : reclamosReembolsados.value
})

// ==================== ACCIONES ====================
const abrirModal = (reclamo) => {
    reclamoSeleccionado.value = reclamo
    modalConfirmacionVisible.value = true
}

const cerrarModal = () => {
    modalConfirmacionVisible.value = false
    reclamoSeleccionado.value = null
}

const confirmarDevolucion = async () => {
    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/tesoreria/${reclamoSeleccionado.value.IdReclamo}/devolver`
        )
        if (response.data.success) {
            toast?.success('Devolución realizada', response.data.message)
            modalConfirmacionVisible.value = false
            router.visit(window.location.pathname, { preserveScroll: true, preserveState: false })
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al devolver')
    }
}

// ==================== FORMATEO ====================
const formatearPrecio = (valor) => {
    return Number(valor || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 })
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

// ==================== CICLO DE VIDA ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
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
                <!-- HEADER -->
                <!-- ============================================ -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Panel de Tesorería</h1>
                            <p class="text-xs text-gray-500">Gestiona las devoluciones a la billetera del cliente</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TABS -->
                <!-- ============================================ -->
                <div class="bg-white rounded-xl shadow-sm p-1 mb-4 flex gap-1 border border-gray-200">
                    <button
                        @click="tabActiva = 'pendientes'"
                        class="flex-1 px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 flex items-center justify-center gap-1.5"
                        :class="tabActiva === 'pendientes'
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <i class="fas fa-clock text-[11px]"></i>
                        <span>Pendientes</span>
                        <span
                            v-if="reclamosPendientes.length"
                            class="text-[9px] px-1.5 py-0.5 rounded-full font-bold"
                            :class="tabActiva === 'pendientes' ? 'bg-white/25 text-white' : 'bg-primary-100 text-primary-700'"
                        >
                            {{ reclamosPendientes.length }}
                        </span>
                    </button>

                    <button
                        @click="tabActiva = 'reembolsados'"
                        class="flex-1 px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 flex items-center justify-center gap-1.5"
                        :class="tabActiva === 'reembolsados'
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <i class="fas fa-check-double text-[11px]"></i>
                        <span>Reembolsados</span>
                        <span
                            v-if="reclamosReembolsados.length"
                            class="text-[9px] px-1.5 py-0.5 rounded-full font-bold"
                            :class="tabActiva === 'reembolsados' ? 'bg-white/25 text-white' : 'bg-primary-100 text-primary-700'"
                        >
                            {{ reclamosReembolsados.length }}
                        </span>
                    </button>
                </div>

                <!-- ============================================ -->
                <!-- SIN DATOS -->
                <!-- ============================================ -->
                <div v-if="reclamosFiltrados.length === 0" class="bg-white rounded-xl shadow-sm p-10 text-center border border-gray-200">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check-circle text-emerald-400 text-3xl"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-700">
                        {{ tabActiva === 'pendientes' ? 'No hay devoluciones pendientes' : 'No hay devoluciones realizadas' }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ tabActiva === 'pendientes' ? 'Los reclamos aprobados por Calidad aparecerán aquí' : 'El historial de reembolsos aparecerá aquí' }}
                    </p>
                </div>

                <!-- ============================================ -->
                <!-- LISTA DE TICKETS -->
                <!-- ============================================ -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="reclamo in reclamosFiltrados"
                        :key="reclamo.IdReclamo"
                        class="bg-white rounded-xl shadow-sm border overflow-hidden flex flex-col hover:shadow-md transition-shadow"
                        :class="tabActiva === 'pendientes' ? 'border-primary-200' : 'border-gray-200'"
                    >
                        <!-- Header -->
                        <div
                            class="px-3 py-2 border-b flex justify-between items-center"
                            :class="tabActiva === 'pendientes' ? 'bg-primary-50 border-primary-100' : 'bg-gray-50 border-gray-200'"
                        >
                            <div>
                                <span
                                    class="text-[9px] font-bold uppercase tracking-wide"
                                    :class="tabActiva === 'pendientes' ? 'text-primary-600' : 'text-gray-500'"
                                >
                                    Ticket #{{ reclamo.IdReclamo }}
                                </span>
                                <h3
                                    class="text-xs font-bold"
                                    :class="tabActiva === 'pendientes' ? 'text-primary-800' : 'text-gray-700'"
                                >
                                    Pedido #{{ reclamo.pedido?.NumeroPedido }}
                                </h3>
                            </div>
                            <span
                                class="px-1.5 py-0.5 text-[8px] font-bold rounded-full border"
                                :class="tabActiva === 'pendientes'
                                    ? 'bg-primary-100 text-primary-700 border-primary-300'
                                    : 'bg-gray-200 text-gray-700 border-gray-300'"
                            >
                                {{ reclamo.Estado }}
                            </span>
                        </div>

                        <!-- Body -->
                        <div class="p-2.5 flex-1 space-y-2">
                            <!-- Info Cliente y Operador -->
                            <div class="text-[10px] text-gray-600 space-y-0.5 bg-gray-50 p-2 rounded-lg border border-gray-100">
                                <p class="truncate">
                                    <i class="fas fa-user mr-1 text-primary-500 text-[9px]"></i>
                                    <span class="font-semibold">Cliente:</span> {{ reclamo.pedido?.cliente?.Nombre }}
                                </p>
                                <p class="truncate">
                                    <i class="fas fa-user-tie mr-1 text-primary-500 text-[9px]"></i>
                                    <span class="font-semibold">Operador:</span> {{ reclamo.nombre_operador }}
                                </p>
                                <p class="text-[9px] text-gray-500">
                                    <i class="fas fa-calendar mr-1 text-primary-500 text-[9px]"></i>
                                    {{ formatearFecha(reclamo.FechaActualizacion) }}
                                </p>
                            </div>

                            <!-- Productos -->
                            <div class="bg-white rounded-lg p-2 border border-gray-100">
                                <p class="text-[9px] font-bold text-gray-500 uppercase mb-1">
                                    <i class="fas fa-box mr-1 text-primary-400"></i>
                                    Cantidades aprobadas
                                </p>
                                <ul class="space-y-0.5">
                                    <li
                                        v-for="det in reclamo.detalles.filter(d => d.CantidadAprobada > 0)"
                                        :key="det.IdReclamoDetalle"
                                        class="text-[10px] text-gray-700 flex justify-between gap-2"
                                    >
                                        <span class="truncate">{{ det.producto?.Descripcion }}</span>
                                        <span class="font-bold text-emerald-600 whitespace-nowrap">
                                            {{ det.CantidadAprobada }} und
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Monto -->
                            <div
                                class="rounded-lg p-2 text-center"
                                :class="tabActiva === 'pendientes'
                                    ? 'bg-primary-50 border border-primary-200'
                                    : 'bg-gray-50 border border-gray-200'"
                            >
                                <p
                                    class="text-[9px] font-bold uppercase mb-0.5"
                                    :class="tabActiva === 'pendientes' ? 'text-primary-700' : 'text-gray-500'"
                                >
                                    Monto devuelto
                                </p>
                                <p
                                    class="text-lg font-extrabold"
                                    :class="tabActiva === 'pendientes' ? 'text-primary-800' : 'text-gray-700'"
                                >
                                    Bs. {{ formatearPrecio(reclamo.monto_a_devolver) }}
                                </p>
                            </div>
                        </div>

                        <!-- Botón Solo en Pendientes -->
                        <div v-if="tabActiva === 'pendientes'" class="border-t border-gray-200 p-2 bg-gray-50">
                            <button
                                @click="abrirModal(reclamo)"
                                class="w-full py-1.5 text-xs font-bold text-white bg-primary-600 hover:bg-primary-700 rounded-md shadow-sm transition flex items-center justify-center gap-1.5"
                            >
                                <i class="fas fa-wallet text-[10px]"></i>
                                Devolver a Billetera
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- MODAL DE CONFIRMACIÓN -->
        <!-- ============================================ -->
        <Teleport to="body">
            <div
                v-if="modalConfirmacionVisible"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-3"
                @click.self="cerrarModal"
            >
                <div class="bg-white rounded-xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-in-up">

                    <!-- Header -->
                    <div class="px-4 py-3 bg-primary-600 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-wallet text-white text-base"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-white">Confirmar Devolución</h3>
                                <p class="text-[10px] text-white/80">Ticket #{{ reclamoSeleccionado?.IdReclamo }}</p>
                            </div>
                        </div>
                        <button @click="cerrarModal" class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-4 space-y-3">
                        <div class="bg-primary-50 border border-primary-200 rounded-lg p-3 text-center">
                            <p class="text-[10px] text-primary-700 uppercase font-semibold tracking-wide mb-1">
                                Monto a devolver
                            </p>
                            <p class="text-2xl font-extrabold text-primary-700">
                                Bs. {{ formatearPrecio(reclamoSeleccionado?.monto_a_devolver) }}
                            </p>
                        </div>

                        <p class="text-xs text-gray-600 text-center">
                            Se devolverá este monto a la billetera del operador.
                        </p>

                        <div class="bg-gray-50 rounded-lg p-2.5 text-[10px] text-gray-700 space-y-1 border border-gray-200">
                            <p class="truncate">
                                <i class="fas fa-user mr-1 text-primary-500 text-[9px] w-3"></i>
                                <strong>Cliente:</strong> {{ reclamoSeleccionado?.pedido?.cliente?.Nombre }}
                            </p>
                            <p class="truncate">
                                <i class="fas fa-user-tie mr-1 text-primary-500 text-[9px] w-3"></i>
                                <strong>Operador:</strong> {{ reclamoSeleccionado?.nombre_operador }}
                            </p>
                            <p>
                                <i class="fas fa-receipt mr-1 text-primary-500 text-[9px] w-3"></i>
                                <strong>Pedido:</strong> #{{ reclamoSeleccionado?.pedido?.NumeroPedido }}
                            </p>
                        </div>

                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-2 flex items-start gap-2">
                            <i class="fas fa-exclamation-triangle text-amber-500 text-[10px] mt-0.5 flex-shrink-0"></i>
                            <p class="text-[10px] text-amber-700">
                                Esta acción <strong>no se puede deshacer</strong>. El monto se acreditará inmediatamente.
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                        <button
                            @click="cerrarModal"
                            class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100 transition font-medium"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="confirmarDevolucion"
                            class="px-4 py-1.5 text-xs text-white bg-primary-600 rounded-md hover:bg-primary-700 transition font-medium shadow-sm flex items-center gap-1.5"
                        >
                            <i class="fas fa-check text-[10px]"></i>
                            Sí, devolver Bs. {{ formatearPrecio(reclamoSeleccionado?.monto_a_devolver) }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.animate-fade-in-up {
    animation: fadeInUp 0.2s ease-out;
}

@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}
</style>