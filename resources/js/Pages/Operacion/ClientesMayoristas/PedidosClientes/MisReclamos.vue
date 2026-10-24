<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    reclamos: { type: Array, default: () => [] },
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const handleResize = () => {
    isMobile.value = window.innerWidth < 640
}

// ==================== TABS ====================
const tabActiva = ref('activos')

const reclamosActivos = computed(() => props.reclamos.filter(r =>
    ['Pendiente de Revisión', 'En Revisión', 'Aprobado por Calidad'].includes(r.Estado)
))

const reclamosFinalizados = computed(() => props.reclamos.filter(r =>
    ['Reembolsado', 'Rechazado'].includes(r.Estado)
))

const reclamosFiltrados = computed(() => {
    return tabActiva.value === 'activos' ? reclamosActivos.value : reclamosFinalizados.value
})

// ==================== HELPERS ====================
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

const formatearPrecio = (valor) => {
    return Number(valor || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 })
}

const getEstadoBadge = (estado) => {
    const badges = {
        'Pendiente de Revisión': 'bg-yellow-100 text-yellow-700 border-yellow-300',
        'En Revisión': 'bg-blue-100 text-blue-700 border-blue-300',
        'Aprobado por Calidad': 'bg-primary-100 text-primary-700 border-primary-300',
        'Rechazado': 'bg-red-100 text-red-700 border-red-300',
        'Reembolsado': 'bg-gray-200 text-gray-700 border-gray-300',
    }
    return badges[estado] || 'bg-gray-100 text-gray-700 border-gray-300'
}

const volverAPedidos = () => {
    router.visit('/operacion/pedidos/clientes-mayoristas/pedidos-clientes')
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
                <div class="flex items-center gap-3 mb-4">
                    <button
                        @click="volverAPedidos"
                        class="w-9 h-9 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary-600 hover:border-primary-300 transition flex-shrink-0 shadow-sm"
                    >
                        <i class="fas fa-arrow-left text-xs"></i>
                    </button>
                    <div class="flex items-center gap-3 flex-1">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-exclamation-circle text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Mis Reclamos</h1>
                            <p class="text-xs text-gray-500">Sigue el estado de tus productos fallados</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TABS -->
                <!-- ============================================ -->
                <div class="bg-white rounded-xl shadow-sm p-1 mb-4 flex gap-1 border border-gray-200">
                    <button
                        @click="tabActiva = 'activos'"
                        class="flex-1 px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 flex items-center justify-center gap-1.5"
                        :class="tabActiva === 'activos'
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <i class="fas fa-spinner text-[11px]"></i>
                        <span>En Proceso</span>
                        <span
                            v-if="reclamosActivos.length"
                            class="text-[9px] px-1.5 py-0.5 rounded-full font-bold"
                            :class="tabActiva === 'activos' ? 'bg-white/25 text-white' : 'bg-primary-100 text-primary-700'"
                        >
                            {{ reclamosActivos.length }}
                        </span>
                    </button>

                    <button
                        @click="tabActiva = 'finalizados'"
                        class="flex-1 px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 flex items-center justify-center gap-1.5"
                        :class="tabActiva === 'finalizados'
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <i class="fas fa-check-double text-[11px]"></i>
                        <span>Historial</span>
                        <span
                            v-if="reclamosFinalizados.length"
                            class="text-[9px] px-1.5 py-0.5 rounded-full font-bold"
                            :class="tabActiva === 'finalizados' ? 'bg-white/25 text-white' : 'bg-primary-100 text-primary-700'"
                        >
                            {{ reclamosFinalizados.length }}
                        </span>
                    </button>
                </div>

                <!-- ============================================ -->
                <!-- LISTA VACÍA -->
                <!-- ============================================ -->
                <div v-if="reclamosFiltrados.length === 0" class="bg-white rounded-xl shadow-sm p-10 text-center border border-gray-200">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check-circle text-primary-400 text-3xl"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-700">No tienes reclamos aquí</h3>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ tabActiva === 'activos' ? 'No tienes reclamos en proceso' : 'No tienes reclamos finalizados' }}
                    </p>
                </div>

                <!-- ============================================ -->
                <!-- LISTA DE TARJETAS -->
                <!-- ============================================ -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="reclamo in reclamosFiltrados"
                        :key="reclamo.IdReclamo"
                        class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col hover:shadow-md transition-shadow"
                    >
                        <!-- ========== HEADER ========== -->
                        <div class="px-3 py-2 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                            <div>
                                <span class="text-[9px] font-bold text-gray-500 uppercase tracking-wide">
                                    Ticket #{{ reclamo.IdReclamo }}
                                </span>
                                <h3 class="text-xs font-bold text-primary-700">
                                    Pedido #{{ reclamo.pedido?.NumeroPedido }}
                                </h3>
                            </div>
                            <span
                                class="px-1.5 py-0.5 text-[8px] font-bold rounded-full border"
                                :class="getEstadoBadge(reclamo.Estado)"
                            >
                                {{ reclamo.Estado }}
                            </span>
                        </div>

                        <!-- ========== BODY ========== -->
                        <div class="p-2.5 flex-1 space-y-2">
                            <!-- Fecha -->
                            <div class="text-[10px] text-gray-500 flex items-center gap-1">
                                <i class="fas fa-calendar mr-1 text-primary-500 text-[9px]"></i>
                                {{ formatearFecha(reclamo.FechaCreacion) }}
                            </div>

                            <!-- Motivo cliente -->
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-2">
                                <p class="text-[9px] font-bold text-amber-800 uppercase mb-0.5 flex items-center gap-1">
                                    <i class="fas fa-user text-[8px]"></i>
                                    Tu motivo
                                </p>
                                <p class="text-[10px] text-gray-700 italic line-clamp-2">"{{ reclamo.Descripcion }}"</p>
                            </div>

                            <!-- Respuesta calidad -->
                            <div
                                v-if="reclamo.RespuestaCalidad"
                                class="rounded-lg p-2 border"
                                :class="reclamo.Estado === 'Rechazado' ? 'bg-red-50 border-red-200' : 'bg-primary-50 border-primary-200'"
                            >
                                <p
                                    class="text-[9px] font-bold uppercase mb-0.5 flex items-center gap-1"
                                    :class="reclamo.Estado === 'Rechazado' ? 'text-red-800' : 'text-primary-800'"
                                >
                                    <i class="fas fa-clipboard-check text-[8px]"></i>
                                    Respuesta de Calidad
                                </p>
                                <p class="text-[10px] text-gray-700 italic line-clamp-2">"{{ reclamo.RespuestaCalidad }}"</p>
                            </div>

                            <!-- Envío (si existe) -->
                            <div v-if="reclamo.pedido?.envio" class="bg-blue-50 border border-blue-200 rounded-lg p-2">
                                <p class="text-[9px] font-bold text-blue-800 uppercase mb-0.5 flex items-center gap-1">
                                    <i class="fas fa-shipping-fast text-[8px]"></i>
                                    Envío
                                </p>
                                <div class="text-[10px] text-gray-700">
                                    <p class="truncate">
                                        {{ reclamo.pedido.envio.transportadora?.identificador?.Nombre || 'Transportadora' }}
                                    </p>
                                    <p v-if="reclamo.pedido.envio.NumeroGuia" class="font-mono text-[9px] text-blue-700 mt-0.5">
                                        Guía: {{ reclamo.pedido.envio.NumeroGuia }}
                                    </p>
                                    <p v-if="reclamo.pedido.envio.CostoEnvio > 0" class="text-[9px] text-gray-500 mt-0.5">
                                        Costo: Bs. {{ formatearPrecio(reclamo.pedido.envio.CostoEnvio) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Productos -->
                            <div>
                                <p class="text-[9px] font-bold text-gray-500 uppercase mb-1">
                                    <i class="fas fa-box mr-0.5 text-primary-400"></i>
                                    Productos
                                </p>
                                <ul class="space-y-1">
                                    <li
                                        v-for="det in reclamo.detalles"
                                        :key="det.IdReclamoDetalle"
                                        class="text-[10px] text-gray-700 bg-gray-50 px-2 py-1.5 rounded border border-gray-100"
                                    >
                                        <span class="font-semibold truncate text-gray-800 block mb-0.5">{{ det.producto?.Descripcion }}</span>

                                        <div
                                            v-if="['Aprobado por Calidad', 'Reembolsado'].includes(reclamo.Estado)"
                                            class="flex justify-between mt-0.5 text-[9px] font-bold bg-white px-1.5 py-0.5 rounded"
                                        >
                                            <span class="text-gray-500">Reclamó: <span class="text-gray-700">{{ det.CantidadReclamada }}</span></span>
                                            <span class="text-primary-600">Aprobó: <span class="text-primary-700">{{ det.CantidadAprobada || 0 }}</span></span>
                                        </div>

                                        <div v-else class="flex justify-end mt-0.5 text-[9px] font-bold">
                                            <span class="text-red-600">{{ det.CantidadReclamada }} unid.</span>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <!-- Monto reembolsado -->
                            <div
                                v-if="reclamo.Estado === 'Reembolsado'"
                                class="bg-primary-50 border border-primary-200 rounded-lg p-2 text-center"
                            >
                                <p class="text-[9px] font-bold text-primary-700 uppercase mb-0.5">
                                    <i class="fas fa-wallet mr-0.5"></i>
                                    Monto devuelto
                                </p>
                                <p class="text-base font-extrabold text-primary-800">
                                    Bs. {{ formatearPrecio(reclamo.monto_a_devolver) }}
                                </p>
                            </div>
                        </div>
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

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>