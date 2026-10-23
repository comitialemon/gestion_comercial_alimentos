<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    reclamos: { type: Array, default: () => [] },
})

const toast = inject('toast', null) || usePage().props.toast

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const handleResize = () => {
    isMobile.value = window.innerWidth < 640
}

// ==================== TABS ====================
const tabActiva = ref('pendiente_recepcion')

const reclamosPendientes = computed(() => props.reclamos.filter(r => r.Estado === 'Pendiente de Revisión'))
const reclamosEnRevision = computed(() => props.reclamos.filter(r => r.Estado === 'En Revisión'))
const reclamosAprobados = computed(() => props.reclamos.filter(r => r.Estado === 'Aprobado por Calidad'))
const reclamosRechazados = computed(() => props.reclamos.filter(r => r.Estado === 'Rechazado'))
const reclamosReembolsados = computed(() => props.reclamos.filter(r => r.Estado === 'Reembolsado'))

const tabs = computed(() => [
    { id: 'pendiente_recepcion', nombre: 'Pendientes', icono: 'fa-clock', count: reclamosPendientes.value.length },
    { id: 'en_revision', nombre: 'En Revisión', icono: 'fa-search', count: reclamosEnRevision.value.length },
    { id: 'aprobados', nombre: 'Aprobados', icono: 'fa-check-circle', count: reclamosAprobados.value.length },
    { id: 'rechazados', nombre: 'Rechazados', icono: 'fa-times-circle', count: reclamosRechazados.value.length },
    { id: 'reembolsados', nombre: 'Reembolsados', icono: 'fa-check-double', count: reclamosReembolsados.value.length },
])

const reclamosFiltrados = computed(() => {
    switch (tabActiva.value) {
        case 'pendiente_recepcion': return reclamosPendientes.value
        case 'en_revision': return reclamosEnRevision.value
        case 'aprobados': return reclamosAprobados.value
        case 'rechazados': return reclamosRechazados.value
        case 'reembolsados': return reclamosReembolsados.value
        default: return []
    }
})

// ==================== MODAL DE ACCIÓN ====================
const modalResolucionVisible = ref(false)
const reclamoSeleccionado = ref(null)
const accion = ref('')
const observacion = ref('')
const productosAprobados = ref([])

const abrirModal = (reclamo, accionSeleccionada) => {
    reclamoSeleccionado.value = reclamo
    accion.value = accionSeleccionada
    observacion.value = ''

    if (accionSeleccionada === 'Aprobar') {
        productosAprobados.value = reclamo.detalles.map(d => ({
            IdReclamoDetalle: d.IdReclamoDetalle,
            IdProducto: d.IdProducto,
            Descripcion: d.producto?.Descripcion,
            CantidadReclamada: d.CantidadReclamada,
            CantidadAprobada: d.CantidadAprobada !== null ? d.CantidadAprobada : d.CantidadReclamada
        }))
    } else {
        productosAprobados.value = []
    }

    modalResolucionVisible.value = true
}

const cerrarModal = () => {
    modalResolucionVisible.value = false
    reclamoSeleccionado.value = null
    observacion.value = ''
    productosAprobados.value = []
}

const validarCantidadInput = (prod) => {
    let val = Number(prod.CantidadAprobada)

    if (isNaN(val) || val < 0) {
        prod.CantidadAprobada = 0
    } else if (val > prod.CantidadReclamada) {
        prod.CantidadAprobada = prod.CantidadReclamada
    } else {
        prod.CantidadAprobada = Math.floor(val)
    }
}

const confirmarResolucion = async () => {
    if (accion.value === 'Rechazar' && !observacion.value.trim()) {
        toast?.warning('Atención', 'Debes escribir el motivo del rechazo.')
        return
    }

    if (accion.value === 'Aprobar') {
        for (let p of productosAprobados.value) {
            if (p.CantidadAprobada > p.CantidadReclamada || p.CantidadAprobada < 0) {
                toast?.error('Error', 'Las cantidades aprobadas no son válidas.')
                return
            }
        }
    }

    try {
        let url = ''
        let payload = {}

        if (accion.value === 'Recibir') {
            url = `/operacion/pedidos/clientes-mayoristas/calidad/${reclamoSeleccionado.value.IdReclamo}/recibir`
            payload = {}
        } else if (accion.value === 'Aprobar') {
            url = `/operacion/pedidos/clientes-mayoristas/calidad/${reclamoSeleccionado.value.IdReclamo}/aprobar`
            payload = {
                observacion: observacion.value,
                productos_aprobados: productosAprobados.value
            }
        } else {
            url = `/operacion/pedidos/clientes-mayoristas/calidad/${reclamoSeleccionado.value.IdReclamo}/rechazar`
            payload = { motivo_rechazo: observacion.value }
        }

        const response = await axios.post(url, payload)

        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            cerrarModal()
            router.visit(window.location.pathname, { preserveScroll: true, preserveState: false })
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al procesar')
    }
}

// ==================== FORMATEO ====================
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

// ==================== COLORES BADGE ====================
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

// ==================== COLORES BOTÓN MODAL ====================
const colorBotonConfirmar = computed(() => {
    if (accion.value === 'Aprobar') return 'bg-primary-600 hover:bg-primary-700'
    if (accion.value === 'Rechazar') return 'bg-red-600 hover:bg-red-700'
    return 'bg-blue-600 hover:bg-blue-700'
})

const colorHeaderModal = computed(() => {
    if (accion.value === 'Aprobar') return 'bg-primary-600'
    if (accion.value === 'Rechazar') return 'bg-red-600'
    return 'bg-blue-600'
})

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
                            <i class="fas fa-clipboard-check text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Panel de Calidad</h1>
                            <p class="text-xs text-gray-500">Gestiona el ciclo de vida de los reclamos</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TABS -->
                <!-- ============================================ -->
                <div class="bg-white rounded-xl shadow-sm p-1 mb-4 flex gap-1 border border-gray-200 overflow-x-auto">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="tabActiva = tab.id"
                        class="flex-1 min-w-[90px] px-2.5 py-2 rounded-lg text-xs font-medium transition-all duration-200 flex items-center justify-center gap-1.5 whitespace-nowrap"
                        :class="tabActiva === tab.id
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <i :class="['fas', tab.icono, 'text-[10px]']"></i>
                        <span>{{ tab.nombre }}</span>
                        <span
                            v-if="tab.count > 0"
                            class="text-[9px] px-1.5 py-0.5 rounded-full font-bold"
                            :class="tabActiva === tab.id ? 'bg-white/25 text-white' : 'bg-primary-100 text-primary-700'"
                        >
                            {{ tab.count }}
                        </span>
                    </button>
                </div>

                <!-- ============================================ -->
                <!-- SIN DATOS -->
                <!-- ============================================ -->
                <div v-if="reclamosFiltrados.length === 0" class="bg-white rounded-xl shadow-sm p-10 text-center border border-gray-200">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check-circle text-primary-400 text-3xl"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-700">No hay tickets aquí</h3>
                    <p class="text-xs text-gray-400 mt-1">No tienes reclamos en este estado.</p>
                </div>

                <!-- ============================================ -->
                <!-- LISTA DE TICKETS -->
                <!-- ============================================ -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="reclamo in reclamosFiltrados"
                        :key="reclamo.IdReclamo"
                        class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col hover:shadow-md transition-shadow"
                    >
                        <!-- Header del ticket -->
                        <div class="px-3 py-2 border-b flex justify-between items-center bg-gray-50">
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

                        <!-- Body -->
                        <div class="p-2.5 flex-1 space-y-2">
                            <!-- Info Cliente / Operador / Fecha -->
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
                                    {{ formatearFecha(reclamo.FechaCreacion) }}
                                </p>
                            </div>

                            <!-- Motivo del cliente -->
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-2">
                                <p class="text-[9px] font-bold text-amber-800 uppercase mb-0.5 flex items-center gap-1">
                                    <i class="fas fa-user text-[8px]"></i>
                                    Motivo del Cliente
                                </p>
                                <p class="text-[10px] text-gray-700 italic line-clamp-2">"{{ reclamo.Descripcion }}"</p>
                            </div>

                            <!-- Respuesta de Calidad (si existe) -->
                            <div
                                v-if="reclamo.RespuestaCalidad"
                                class="rounded-lg p-2 border"
                                :class="
                                    (reclamo.Estado === 'Aprobado por Calidad') ? 'bg-primary-50 border-primary-200' :
                                    (reclamo.Estado === 'Reembolsado') ? 'bg-blue-50 border-blue-200' :
                                    'bg-red-50 border-red-200'
                                "
                            >
                                <p
                                    class="text-[9px] font-bold uppercase mb-0.5 flex items-center gap-1"
                                    :class="
                                        (reclamo.Estado === 'Aprobado por Calidad') ? 'text-primary-800' :
                                        (reclamo.Estado === 'Reembolsado') ? 'text-blue-800' :
                                        'text-red-800'
                                    "
                                >
                                    <i class="fas fa-clipboard-check text-[8px]"></i>
                                    Respuesta de Calidad
                                </p>
                                <p class="text-[10px] text-gray-700 italic line-clamp-2">"{{ reclamo.RespuestaCalidad }}"</p>
                            </div>

                            <!-- Productos -->
                            <div>
                                <p class="text-[9px] font-bold text-gray-500 uppercase mb-1">
                                    <i class="fas fa-box mr-1 text-primary-400"></i>
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
                                            v-if="reclamo.Estado === 'Aprobado por Calidad' || reclamo.Estado === 'Reembolsado'"
                                            class="flex justify-between mt-0.5 text-[9px] font-bold bg-white px-1.5 py-0.5 rounded"
                                        >
                                            <span class="text-gray-500">Reclamó: <span class="text-gray-700">{{ det.CantidadReclamada }}</span></span>
                                            <span class="text-primary-600">Aprobó: <span class="text-primary-700">{{ det.CantidadAprobada !== null ? det.CantidadAprobada : 0 }}</span></span>
                                        </div>

                                        <div v-else class="flex justify-end mt-0.5 text-[9px] font-bold">
                                            <span class="text-red-600">{{ det.CantidadReclamada }} unid.</span>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="border-t border-gray-200 p-2 bg-gray-50 flex gap-2">
                            <button
                                v-if="reclamo.Estado === 'Pendiente de Revisión'"
                                @click="abrirModal(reclamo, 'Recibir')"
                                class="w-full py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-sm transition flex items-center justify-center gap-1.5"
                            >
                                <i class="fas fa-box-open text-[10px]"></i>
                                Recibir en Fábrica
                            </button>

                            <template v-if="reclamo.Estado === 'En Revisión'">
                                <button
                                    @click="abrirModal(reclamo, 'Rechazar')"
                                    class="flex-1 py-1.5 text-[10px] font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-md transition flex items-center justify-center gap-1"
                                >
                                    <i class="fas fa-times text-[9px]"></i> Rechazar
                                </button>
                                <button
                                    @click="abrirModal(reclamo, 'Aprobar')"
                                    class="flex-1 py-1.5 text-[10px] font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-md transition shadow-sm flex items-center justify-center gap-1"
                                >
                                    <i class="fas fa-check text-[9px]"></i> Aprobar
                                </button>
                            </template>

                            <!-- Reembolsado -->
                            <div
                                v-if="reclamo.Estado === 'Reembolsado'"
                                class="w-full text-center text-[10px] text-gray-500 py-1"
                            >
                                <i class="fas fa-check-double text-primary-500 mr-1"></i>
                                Proceso completado y reembolsado
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- MODAL DE RESOLUCIÓN -->
        <!-- ============================================ -->
        <Teleport to="body">
            <div
                v-if="modalResolucionVisible"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm p-0 sm:p-3"
                @click.self="cerrarModal"
            >
                <div class="bg-white rounded-t-2xl sm:rounded-xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col max-h-[90vh] animate-fade-in-up">

                    <!-- Header -->
                    <div class="px-4 py-3 flex items-center justify-between flex-shrink-0" :class="colorHeaderModal">
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i
                                    class="fas text-white text-base"
                                    :class="accion === 'Aprobar' ? 'fa-check-circle' : (accion === 'Rechazar' ? 'fa-times-circle' : 'fa-box-open')"
                                ></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-sm text-white truncate">
                                    {{ accion === 'Recibir' ? 'Recibir en Fábrica' : (accion === 'Aprobar' ? 'Aprobar Reclamo' : 'Rechazar Reclamo') }}
                                </h3>
                                <p class="text-[10px] text-white/80 truncate">
                                    Ticket #{{ reclamoSeleccionado?.IdReclamo }} - Pedido #{{ reclamoSeleccionado?.pedido?.NumeroPedido }}
                                </p>
                            </div>
                        </div>
                        <button @click="cerrarModal" class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition flex-shrink-0">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-3 overflow-y-auto space-y-3 flex-1">
                        <p class="text-xs text-gray-600">
                            {{ accion === 'Recibir' ? 'Confirma que los productos físicos llegaron a la fábrica y que vas a comenzar a revisarlos.' :
                               (accion === 'Aprobar' ? 'Indica cuántas unidades se aprueban realmente. No puedes aprobar más de lo reclamado.' :
                               'Al rechazar, el ticket se cierra y el cliente no recibirá reembolso. Explica el motivo.') }}
                        </p>

                        <!-- Productos a aprobar -->
                        <div v-if="accion === 'Aprobar'" class="space-y-2">
                            <label class="text-[10px] font-bold text-gray-700 uppercase tracking-wide block">
                                <i class="fas fa-box text-primary-500 mr-1"></i>
                                Cantidades a aprobar
                            </label>
                            <div class="space-y-1.5 border border-gray-200 rounded-lg p-2 bg-gray-50">
                                <div
                                    v-for="(prod, idx) in productosAprobados"
                                    :key="idx"
                                    class="bg-white p-2 rounded-md border border-gray-200"
                                >
                                    <p class="text-xs font-semibold text-gray-800 leading-tight truncate">{{ prod.Descripcion }}</p>
                                    <div class="flex items-center justify-between mt-1.5 gap-2">
                                        <p class="text-[10px] text-gray-500">
                                            Reclamado: <strong class="text-gray-700">{{ prod.CantidadReclamada }}</strong>
                                        </p>
                                        <div class="flex items-center gap-1.5">
                                            <label class="text-[10px] text-gray-500 font-bold">Aprobar:</label>
                                            <input
                                                type="number"
                                                min="0"
                                                :max="prod.CantidadReclamada"
                                                v-model.number="prod.CantidadAprobada"
                                                @input="validarCantidadInput(prod)"
                                                @blur="validarCantidadInput(prod)"
                                                class="w-16 border border-gray-300 rounded-md px-1.5 py-1 text-center text-xs font-bold focus:ring-primary-500 focus:border-primary-500 outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div v-if="accion !== 'Recibir'">
                            <label class="text-[10px] font-bold text-gray-700 uppercase tracking-wide block mb-1">
                                {{ accion === 'Aprobar' ? 'Observaciones de Calidad (Opcional)' : 'Motivo del Rechazo *' }}
                            </label>
                            <textarea
                                v-model="observacion"
                                rows="2"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none resize-none"
                                :placeholder="accion === 'Aprobar' ? 'Ej: Se verificó que algunas venían rotas...' : 'Ej: El daño fue por mal manejo...'"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2 flex-shrink-0">
                        <button
                            @click="cerrarModal"
                            class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100 transition font-medium"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="confirmarResolucion"
                            class="px-4 py-1.5 text-xs text-white rounded-md font-medium shadow-sm transition flex items-center gap-1.5"
                            :class="colorBotonConfirmar"
                        >
                            <i class="fas fa-check text-[10px]"></i>
                            Confirmar
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

/* Limitar líneas de texto */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@media (min-width: 1024px) {
    input, select, button, textarea {
        font-size: 13px !important;
    }
}
</style>