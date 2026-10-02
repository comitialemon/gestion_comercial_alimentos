<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    qrs: { type: Object, default: () => ({ data: [], links: [] }) },
    stats: { type: Object, default: () => ({}) },
    credenciales: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
    estadosDisponibles: { type: Array, default: () => [] },
    origenesDisponibles: { type: Array, default: () => [] },
    tabActivo: { type: String, default: 'historial' },
})

const toast = inject('toast')

// ============================================================
// DETECTAR DISPOSITIVO
// ============================================================
const isMobile = ref(false)
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

// ============================================================
// TABS
// ============================================================
const tabActual = ref(props.tabActivo || 'historial')

const cambiarTab = (tab) => {
    tabActual.value = tab
    router.get('/historial-pagos-qr', {
        ...props.filtros,
        tab: tab,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const tabs = [
    { id: 'historial', nombre: 'Historial', icono: 'fa-list' },
    { id: 'banco', nombre: 'Banco', icono: 'fa-university' },
    { id: 'conciliacion', nombre: 'Conciliación', icono: 'fa-sync' },
]

// ============================================================
// FILTROS
// ============================================================
const fechaDesde = ref(props.filtros.fecha_desde || '')
const fechaHasta = ref(props.filtros.fecha_hasta || '')
const estado = ref(props.filtros.estado || '')
const buscar = ref(props.filtros.buscar || '')
const idCredencial = ref(props.filtros.id_credencial || '')
const origen = ref(props.filtros.origen || 'TODOS')

// ============================================================
// DETALLE
// ============================================================
const mostrarDetalle = ref(false)
const qrSeleccionado = ref(null)

// ============================================================
// TAB BANCO
// ============================================================
const fechaBanco = ref(new Date().toISOString().split('T')[0])
const credencialBanco = ref(props.credenciales[0]?.IdCredencial || null)
const pagosBanco = ref([])
const consultandoBanco = ref(false)

// ============================================================
// TAB CONCILIACIÓN
// ============================================================
const conciliando = ref(false)
const resultadoConciliacion = ref(null)

// ============================================================
// FILTROS
// ============================================================
const aplicarFiltros = () => {
    router.get('/historial-pagos-qr', {
        fecha_desde: fechaDesde.value || undefined,
        fecha_hasta: fechaHasta.value || undefined,
        estado: estado.value || undefined,
        buscar: buscar.value || undefined,
        id_credencial: idCredencial.value || undefined,
        origen: origen.value || undefined,
        tab: tabActual.value,
    }, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltros = () => {
    fechaDesde.value = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
    fechaHasta.value = new Date().toISOString().split('T')[0]
    estado.value = ''
    buscar.value = ''
    idCredencial.value = ''
    origen.value = 'TODOS'
    aplicarFiltros()
}

const filtrarHuerfanos = () => {
    origen.value = 'BANCO'
    aplicarFiltros()
}

// ============================================================
// DETALLE
// ============================================================
const verDetalle = (qr) => {
    qrSeleccionado.value = qr
    mostrarDetalle.value = true
}

const cerrarDetalle = () => {
    mostrarDetalle.value = false
    qrSeleccionado.value = null
}

// ============================================================
// CONSULTAR BANCO
// ============================================================
const consultarBanco = async () => {
    if (!fechaBanco.value || !credencialBanco.value) {
        toast?.warning('Faltan datos', 'Selecciona fecha y credencial')
        return
    }

    consultandoBanco.value = true
    try {
        const response = await axios.get('/historial-pagos-qr/banco', {
            params: {
                fecha: fechaBanco.value,
                id_credencial: credencialBanco.value,
            },
        })

        if (response.data.success) {
            pagosBanco.value = response.data.pagos.map(p => ({
                ...p,
                esHuerfano: true,
            }))
            toast?.success('Consulta exitosa', `${response.data.total} pagos encontrados en el banco`)
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al consultar')
    } finally {
        consultandoBanco.value = false
    }
}

// ============================================================
// CONCILIAR
// ============================================================
const conciliar = async (fecha, idCredencial) => {
    const fechaUsar = fecha || fechaBanco.value
    const credencialUsar = idCredencial || credencialBanco.value

    if (!fechaUsar || !credencialUsar) {
        toast?.warning('Faltan datos', 'Selecciona fecha y credencial')
        return
    }

    conciliando.value = true
    try {
        const response = await axios.post('/historial-pagos-qr/conciliar', {
            fecha: fechaUsar,
            id_credencial: credencialUsar,
        })

        if (response.data.success) {
            resultadoConciliacion.value = response.data
            toast?.success('Conciliación exitosa', response.data.message)
            router.reload()
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al conciliar')
    } finally {
        conciliando.value = false
    }
}

// ============================================================
// FORMATEO
// ============================================================
const formatearMonto = (valor) => {
    if (valor === null || valor === undefined) return '—'
    return Number(valor).toFixed(2)
}

const formatearFecha = (fecha) => {
    if (!fecha) return '—'
    return new Date(fecha).toLocaleString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

// ============================================================
// COLORES
// ============================================================
const colorEstado = (estado) => {
    return {
        'ACTIVO': 'bg-blue-100 text-blue-700 border-blue-300',
        'PAGADO': 'bg-emerald-100 text-emerald-700 border-emerald-300',
        'ANULADO': 'bg-gray-100 text-gray-600 border-gray-300',
        'EXPIRADO': 'bg-amber-100 text-amber-700 border-amber-300',
        'ERROR': 'bg-red-100 text-red-700 border-red-300',
    }[estado] || 'bg-gray-100 text-gray-600 border-gray-300'
}

const iconoEstado = (estado) => {
    return {
        'ACTIVO': 'fa-clock',
        'PAGADO': 'fa-check-circle',
        'ANULADO': 'fa-times-circle',
        'EXPIRADO': 'fa-hourglass-end',
        'ERROR': 'fa-exclamation-triangle',
    }[estado] || 'fa-circle'
}

const colorOrigen = (origen) => {
    return {
        'VENTA': 'bg-primary-100 text-primary-700',
        'PEDIDO': 'bg-purple-100 text-purple-700',
        'BANCO': 'bg-amber-100 text-amber-700 border border-amber-300',
    }[origen] || 'bg-gray-100 text-gray-600'
}

const iconoOrigen = (origen) => {
    return {
        'VENTA': 'fa-shopping-cart',
        'PEDIDO': 'fa-box',
        'BANCO': 'fa-university',
    }[origen] || 'fa-circle'
}

const colorAmbiente = (ambiente) => {
    return ambiente === 'PRODUCCION'
        ? 'bg-red-100 text-red-700'
        : 'bg-amber-100 text-amber-700'
}

// ============================================================
// CICLO DE VIDA
// ============================================================
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
                            <i class="fas fa-history text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Historial de QRs</h1>
                            <p class="text-xs text-gray-500">Gestión completa de pagos QR</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ESTADÍSTICAS -->
                <!-- ============================================ -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-4">
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-gray-200 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-qrcode text-xs"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[8px] text-gray-400 uppercase font-semibold tracking-wide">Total</p>
                            <p class="text-sm font-bold text-gray-800">{{ stats.total || 0 }}</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-gray-200 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-clock text-xs"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[8px] text-gray-400 uppercase font-semibold tracking-wide">Activos</p>
                            <p class="text-sm font-bold text-gray-800">{{ stats.activos || 0 }}</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-gray-200 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-check-circle text-xs"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[8px] text-gray-400 uppercase font-semibold tracking-wide">Pagados</p>
                            <p class="text-sm font-bold text-gray-800">{{ stats.pagados || 0 }}</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-gray-200 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-times-circle text-xs"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[8px] text-gray-400 uppercase font-semibold tracking-wide">Anulados</p>
                            <p class="text-sm font-bold text-gray-800">{{ stats.anulados || 0 }}</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-amber-200 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-xs"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[8px] text-gray-400 uppercase font-semibold tracking-wide">Huérfanos</p>
                            <p class="text-sm font-bold text-amber-600">{{ stats.huerfanos || 0 }}</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-emerald-200 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-money-bill-wave text-xs"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[8px] text-gray-400 uppercase font-semibold tracking-wide">Monto</p>
                            <p class="text-xs font-bold text-emerald-600">Bs. {{ formatearMonto(stats.monto_total) }}</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ALERTA HUÉRFANOS -->
                <!-- ============================================ -->
                <div v-if="stats.huerfanos > 0" class="bg-amber-50 border-l-4 border-amber-500 rounded-xl p-2.5 mb-4">
                    <div class="flex items-start gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-amber-600 text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-amber-800 text-xs">
                                Tienes {{ stats.huerfanos }} pago(s) en el banco sin registro local
                            </h3>
                            <p class="text-[10px] text-amber-700 mt-0.5">
                                Estos pagos entraron al banco pero no están en tu sistema.
                            </p>
                            <button
                                @click="filtrarHuerfanos"
                                class="mt-1.5 px-2.5 py-1 bg-amber-600 text-white rounded-md text-[10px] font-medium hover:bg-amber-700 transition flex items-center gap-1"
                            >
                                <i class="fas fa-filter text-[9px]"></i>
                                Ver solo huérfanos
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TABS -->
                <!-- ============================================ -->
                <div class="bg-white rounded-xl shadow-sm p-1 mb-4 flex gap-1 border border-gray-200">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="cambiarTab(tab.id)"
                        class="flex-1 px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 flex items-center justify-center gap-1.5"
                        :class="tabActual === tab.id
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-100'"
                    >
                        <i :class="['fas', tab.icono, 'text-[11px]']"></i>
                        <span>{{ tab.nombre }}</span>
                        <span
                            v-if="tab.id === 'historial' && stats.total > 0"
                            class="text-[9px] px-1.5 py-0.5 rounded-full font-bold"
                            :class="tabActual === tab.id ? 'bg-white/25 text-white' : 'bg-primary-100 text-primary-700'"
                        >
                            {{ stats.total }}
                        </span>
                        <span
                            v-if="tab.id === 'conciliacion' && stats.huerfanos > 0"
                            class="text-[9px] px-1.5 py-0.5 rounded-full font-bold"
                            :class="tabActual === tab.id ? 'bg-white/25 text-white' : 'bg-primary-100 text-primary-700'"
                        >
                            {{ stats.huerfanos }}
                        </span>
                    </button>
                </div>

                <!-- ============================================ -->
                <!-- TAB: HISTORIAL -->
                <!-- ============================================ -->
                <div v-show="tabActual === 'historial'">

                    <!-- Filtros -->
                    <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                        <div class="flex flex-wrap items-end gap-2">
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Desde</label>
                                <input v-model="fechaDesde" type="date" class="w-36 border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Hasta</label>
                                <input v-model="fechaHasta" type="date" class="w-36 border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Estado</label>
                                <select v-model="estado" class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                    <option value="">Todos</option>
                                    <option v-for="e in estadosDisponibles" :key="e.valor" :value="e.valor">{{ e.nombre }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Origen</label>
                                <select v-model="origen" class="w-36 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                    <option v-for="o in origenesDisponibles" :key="o.valor" :value="o.valor">{{ o.nombre }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Credencial</label>
                                <select v-model="idCredencial" class="w-40 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                    <option value="">Todas</option>
                                    <option v-for="c in credenciales" :key="c.IdCredencial" :value="c.IdCredencial">{{ c.Alias }} ({{ c.Ambiente }})</option>
                                </select>
                            </div>
                            <div class="flex-1 min-w-[140px] max-w-[220px]">
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar</label>
                                <input v-model="buscar" type="text" placeholder="QR ID, Transaction ID..." class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" @keyup.enter="aplicarFiltros" />
                            </div>
                            <div class="flex gap-1.5 ml-auto">
                                <button @click="limpiarFiltros" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1.5">
                                    <i class="fas fa-eraser text-[10px]"></i> Limpiar
                                </button>
                                <button @click="aplicarFiltros" class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5">
                                    <i class="fas fa-search text-[10px]"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Lista -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                        <div v-if="qrs.data.length === 0" class="text-center py-12">
                            <i class="fas fa-inbox text-3xl text-gray-300 mb-2 block"></i>
                            <p class="text-gray-500 text-sm">No hay QRs con los filtros seleccionados</p>
                        </div>

                        <!-- Vista móvil -->
                        <div v-else-if="isMobile" class="p-2 space-y-2">
                            <div
                                v-for="qr in qrs.data"
                                :key="qr.IdPagosQr"
                                @click="verDetalle(qr)"
                                class="rounded-lg p-2.5 border cursor-pointer transition"
                                :class="qr.EsHuerfano ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-100 hover:bg-gray-100'"
                            >
                                <div class="flex justify-between items-start mb-1.5">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1 mb-1">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[8px] font-bold" :class="colorOrigen(qr.Origen)">
                                                <i :class="['fas', iconoOrigen(qr.Origen)]"></i> {{ qr.Origen }}
                                            </span>
                                        </div>
                                        <p class="text-[10px] font-mono text-gray-500 truncate">{{ qr.QrId }}</p>
                                        <p class="text-xs font-medium text-gray-700 truncate mt-0.5">{{ qr.Descripcion || 'Sin descripción' }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[8px] font-bold border flex-shrink-0 ml-2" :class="colorEstado(qr.Estado)">
                                        <i :class="['fas', iconoEstado(qr.Estado)]"></i> {{ qr.Estado }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 pt-1.5 border-t border-gray-200">
                                    <div>
                                        <p class="text-[8px] text-gray-400 uppercase">Monto</p>
                                        <p class="text-xs font-bold text-gray-800">Bs. {{ formatearMonto(qr.Monto) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[8px] text-gray-400 uppercase">Pagado</p>
                                        <p class="text-xs font-bold text-emerald-600">
                                            <template v-if="qr.MontoPagado">Bs. {{ formatearMonto(qr.MontoPagado) }}</template>
                                            <template v-else>—</template>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vista desktop -->
                        <div v-else class="overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase w-20">Origen</th>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase">QR ID</th>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase w-28">Estado</th>
                                        <th class="px-3 py-2 text-right text-[9px] font-medium text-primary-700 uppercase w-24">Monto</th>
                                        <th class="px-3 py-2 text-right text-[9px] font-medium text-primary-700 uppercase w-24">Pagado</th>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase">Descripción</th>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase w-40">Credencial</th>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase w-32">Creado</th>
                                        <th class="px-3 py-2 text-center text-[9px] font-medium text-primary-700 uppercase w-14">Ver</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr
                                        v-for="qr in qrs.data"
                                        :key="qr.IdPagosQr"
                                        class="hover:bg-gray-50 transition cursor-pointer"
                                        :class="{ 'bg-amber-50/50 border-l-4 border-amber-400': qr.EsHuerfano }"
                                        @click="verDetalle(qr)"
                                    >
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[8px] font-bold" :class="colorOrigen(qr.Origen)">
                                                <i :class="['fas', iconoOrigen(qr.Origen), 'text-[7px]']"></i> {{ qr.Origen }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 font-mono text-[10px] text-gray-600 truncate max-w-[140px]">{{ qr.QrId }}</td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[8px] font-bold border" :class="colorEstado(qr.Estado)">
                                                <i :class="['fas', iconoEstado(qr.Estado), 'text-[7px]']"></i> {{ qr.Estado }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-xs font-bold text-gray-800 tabular-nums">Bs. {{ formatearMonto(qr.Monto) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-bold text-emerald-600 tabular-nums">
                                            <template v-if="qr.MontoPagado">Bs. {{ formatearMonto(qr.MontoPagado) }}</template>
                                            <template v-else>—</template>
                                        </td>
                                        <td class="px-3 py-2 text-[10px] text-gray-600 truncate max-w-[200px]">{{ qr.Descripcion || '—' }}</td>
                                        <td class="px-3 py-2">
                                            <span v-if="qr.Credencial" class="inline-flex items-center gap-1">
                                                <span class="text-[10px] font-medium text-gray-700 truncate max-w-[100px]">{{ qr.Credencial.Alias }}</span>
                                                <span class="px-1.5 py-0.5 rounded-full text-[7px] font-bold flex-shrink-0" :class="colorAmbiente(qr.Credencial.Ambiente)">
                                                    {{ qr.Credencial.Ambiente === 'PRODUCCION' ? 'PROD' : 'CERT' }}
                                                </span>
                                            </span>
                                            <span v-else class="text-gray-400 text-[10px]">—</span>
                                        </td>
                                        <td class="px-3 py-2 text-[10px] text-gray-500 whitespace-nowrap">{{ formatearFecha(qr.FechaCreacion) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <button class="p-1 rounded-md hover:bg-primary-100 text-primary-600 transition" @click.stop="verDetalle(qr)" title="Ver detalle">
                                                <i class="fas fa-eye text-[10px]"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div v-if="qrs.links && qrs.links.length > 3" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                            <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                                <div class="text-[10px] text-gray-500">
                                    Mostrando {{ qrs.from || 0 }} a {{ qrs.to || 0 }} de {{ qrs.total || 0 }}
                                </div>
                                <div class="flex gap-1 flex-wrap justify-center">
                                    <button
                                        v-for="link in qrs.links"
                                        :key="link.label"
                                        @click="link.url ? router.get(link.url) : null"
                                        class="px-2.5 py-1 rounded-lg border text-[10px] transition"
                                        :class="{
                                            'bg-primary-600 text-white border-primary-600': link.active,
                                            'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url,
                                            'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url
                                        }"
                                        v-html="link.label"
                                        :disabled="!link.url"
                                    ></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TAB: BANCO -->
                <!-- ============================================ -->
                <div v-show="tabActual === 'banco'">
                    <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                        <div class="flex items-center gap-2.5 mb-3">
                            <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-university text-primary-600 text-xs"></i>
                            </div>
                            <div>
                                <h2 class="font-bold text-gray-800 text-sm">Consultar Pagos del Banco</h2>
                                <p class="text-[10px] text-gray-500">Obtén el listado de pagos reportados por el banco</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-end gap-2">
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha</label>
                                <input v-model="fechaBanco" type="date" class="w-36 border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Credencial</label>
                                <select v-model="credencialBanco" class="w-44 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                    <option v-for="c in credenciales" :key="c.IdCredencial" :value="c.IdCredencial">{{ c.Alias }} ({{ c.CodigoBanco }})</option>
                                </select>
                            </div>
                            <div class="flex gap-1.5">
                                <button @click="consultarBanco" :disabled="consultandoBanco" class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 flex items-center gap-1.5">
                                    <i v-if="consultandoBanco" class="fas fa-spinner fa-spin text-[10px]"></i>
                                    <i v-else class="fas fa-search text-[10px]"></i>
                                    Consultar
                                </button>
                                <button @click="conciliar()" :disabled="conciliando" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 flex items-center gap-1.5">
                                    <i v-if="conciliando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                    <i v-else class="fas fa-sync text-[10px]"></i>
                                    Conciliar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Resultado -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                        <div v-if="pagosBanco.length === 0" class="text-center py-10">
                            <i class="fas fa-inbox text-3xl text-gray-300 mb-2 block"></i>
                            <p class="text-gray-500 text-sm">Selecciona una fecha y credencial, luego consulta</p>
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase">QR ID</th>
                                        <th class="px-3 py-2 text-right text-[9px] font-medium text-primary-700 uppercase">Monto</th>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase">Fecha Pago</th>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase">Enviado por</th>
                                        <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase">Descripción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(pago, idx) in pagosBanco" :key="idx" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 font-mono text-[10px] text-gray-600">{{ pago.qrId }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-bold text-emerald-600">Bs. {{ pago.amount }}</td>
                                        <td class="px-3 py-2 text-[10px] text-gray-600">{{ pago.paymentDate }} {{ pago.paymentTime }}</td>
                                        <td class="px-3 py-2 text-[10px] text-gray-600">{{ pago.senderName }}</td>
                                        <td class="px-3 py-2 text-[10px] text-gray-600 truncate max-w-[200px]">{{ pago.description || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- TAB: CONCILIACIÓN -->
                <!-- ============================================ -->
                <div v-show="tabActual === 'conciliacion'">
                    
                    <!-- Configurar -->
                    <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                        <div class="flex items-center gap-2.5 mb-3">
                            <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-sync text-emerald-600 text-xs"></i>
                            </div>
                            <div>
                                <h2 class="font-bold text-gray-800 text-sm">Conciliación de Pagos</h2>
                                <p class="text-[10px] text-gray-500">Compara los pagos del banco con tu sistema</p>
                            </div>
                        </div>

                        <!-- Paso 1: Configurar -->
                        <div class="bg-primary-50 border border-primary-200 rounded-lg p-2.5 mb-3">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-5 h-5 rounded-full bg-primary-600 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">1</span>
                                <p class="text-[10px] font-bold text-primary-800">Configurar</p>
                            </div>
                            <div class="flex flex-wrap items-end gap-2">
                                <div>
                                    <label class="text-[10px] text-gray-500 font-medium block mb-0.5">📅 Fecha</label>
                                    <input v-model="fechaBanco" type="date" class="w-36 border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                                </div>
                                <div>
                                    <label class="text-[10px] text-gray-500 font-medium block mb-0.5">🔑 Credencial</label>
                                    <select v-model="credencialBanco" class="w-44 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                        <option v-for="c in credenciales" :key="c.IdCredencial" :value="c.IdCredencial">{{ c.Alias }} ({{ c.CodigoBanco }})</option>
                                    </select>
                                </div>
                                <div>
                                    <button
                                        @click="conciliar()"
                                        :disabled="conciliando"
                                        class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-bold transition disabled:opacity-50 flex items-center gap-1.5"
                                    >
                                        <i v-if="conciliando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                        <i v-else class="fas fa-rocket text-[10px]"></i>
                                        {{ conciliando ? 'Conciliando...' : 'Iniciar Conciliación' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 2: Resultado - REDISEÑADO COMPACTO -->
                        <div v-if="resultadoConciliacion" class="bg-emerald-50 border border-emerald-200 rounded-lg p-2.5 mb-3">
                            <div class="flex items-center gap-2 mb-2.5">
                                <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">2</span>
                                <p class="text-[10px] font-bold text-emerald-800">Resultado</p>
                                <i class="fas fa-check-circle text-emerald-600 ml-auto text-xs"></i>
                            </div>

                            <!-- Resumen en formato fila compacta -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 mb-2.5">
                                <!-- Banco -->
                                <div class="bg-white rounded-lg p-2 border border-gray-200 flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-university text-[10px]"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[8px] text-gray-500 uppercase font-semibold">Banco</p>
                                        <p class="text-sm font-bold text-gray-800">{{ resultadoConciliacion.resumen.pagos_banco }}</p>
                                    </div>
                                </div>

                                <!-- Coinciden -->
                                <div class="bg-white rounded-lg p-2 border border-emerald-300 flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-check-circle text-[10px]"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[8px] text-emerald-700 uppercase font-semibold">Coinciden</p>
                                        <p class="text-sm font-bold text-emerald-600">{{ resultadoConciliacion.resumen.conciliados }}</p>
                                    </div>
                                </div>

                                <!-- Marcados -->
                                <div class="bg-white rounded-lg p-2 border border-blue-300 flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-sync text-[10px]"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[8px] text-blue-700 uppercase font-semibold">Marcados</p>
                                        <p class="text-sm font-bold text-blue-600">{{ resultadoConciliacion.resumen.marcados_pagados }}</p>
                                    </div>
                                </div>

                                <!-- Huérfanos -->
                                <div class="bg-white rounded-lg p-2 border border-amber-300 flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-[10px]"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[8px] text-amber-700 uppercase font-semibold">Huérfanos</p>
                                        <p class="text-sm font-bold text-amber-600">{{ resultadoConciliacion.resumen.no_encontrados_local }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg p-2 border border-emerald-200">
                                <p class="text-[10px] text-gray-700 leading-relaxed">
                                    <i class="fas fa-info-circle text-emerald-600 mr-1"></i>
                                    <strong>¿Qué significa?</strong>
                                    Se encontraron {{ resultadoConciliacion.resumen.pagos_banco }} pagos en el banco.
                                    <span class="text-emerald-700 font-semibold">{{ resultadoConciliacion.resumen.conciliados }} ya coincidían</span>,
                                    <span class="text-blue-700 font-semibold">{{ resultadoConciliacion.resumen.marcados_pagados }} fueron corregidos</span>
                                    y
                                    <span class="text-amber-700 font-semibold">{{ resultadoConciliacion.resumen.no_encontrados_local }} no están en tu sistema</span>.
                                </p>
                            </div>
                        </div>

                        <!-- Estado inicial -->
                        <div v-else class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-info-circle text-gray-400 text-lg"></i>
                            </div>
                            <p class="text-xs font-medium text-gray-600 mb-0.5">Sin conciliación activa</p>
                            <p class="text-[10px] text-gray-500">Selecciona fecha y credencial, luego inicia la conciliación</p>
                        </div>
                    </div>

                    <!-- Paso 3: Detalle -->
                    <div v-if="resultadoConciliacion && resultadoConciliacion.diferencias.length > 0" class="bg-white rounded-xl shadow-sm p-3">
                        <div class="flex items-center gap-2 mb-2.5">
                            <span class="w-5 h-5 rounded-full bg-amber-600 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">3</span>
                            <p class="text-[10px] font-bold text-gray-800">Detalle de Diferencias</p>
                            <span class="text-[9px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-bold">
                                {{ resultadoConciliacion.diferencias.length }}
                            </span>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="(dif, idx) in resultadoConciliacion.diferencias"
                                :key="idx"
                                class="bg-amber-50 rounded-lg p-2.5 border border-amber-200"
                            >
                                <div class="flex items-start gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-question text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-bold text-amber-800">⚠️ Pago no encontrado en tu sistema</p>
                                        <p class="text-[9px] font-mono text-amber-700 mt-0.5 truncate">QR ID: {{ dif.qrId }}</p>
                                        <div class="grid grid-cols-2 gap-2 mt-1.5">
                                            <div>
                                                <p class="text-[8px] text-gray-500 uppercase">Monto</p>
                                                <p class="text-[11px] font-bold text-emerald-600">Bs. {{ dif.monto }}</p>
                                            </div>
                                            <div>
                                                <p class="text-[8px] text-gray-500 uppercase">Fecha</p>
                                                <p class="text-[11px] font-medium text-gray-700">{{ dif.fecha || '—' }}</p>
                                            </div>
                                        </div>
                                        <p class="text-[9px] text-amber-600 mt-1.5 italic">
                                            Este pago entró al banco pero no está en tu BD.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="resultadoConciliacion && resultadoConciliacion.diferencias.length === 0" class="bg-white rounded-xl shadow-sm p-6 text-center">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                        </div>
                        <p class="text-sm font-bold text-emerald-700">¡Todo cuadra!</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">Todos los pagos del banco están registrados en tu sistema</p>
                    </div>

                </div>

            </div>
        </div>

        <!-- MODAL DE DETALLE -->
        <Teleport to="body">
            <div
                v-if="mostrarDetalle && qrSeleccionado"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-3"
                @click.self="cerrarDetalle"
            >
                <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full flex flex-col max-h-[95vh] overflow-hidden">
                    <div class="bg-primary-600 px-4 py-3 flex items-center justify-between flex-shrink-0">
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-qrcode text-white text-base"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-sm text-white truncate">Detalle del QR</h3>
                                <p class="text-[10px] text-white/80 font-mono truncate">{{ qrSeleccionado.QrId }}</p>
                            </div>
                        </div>
                        <button @click="cerrarDetalle" class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition flex-shrink-0">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-3 space-y-3">
                        <div v-if="qrSeleccionado.EsHuerfano" class="bg-amber-50 rounded-lg p-2.5 border border-amber-300">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5 text-xs"></i>
                                <div>
                                    <p class="text-[11px] font-bold text-amber-800">⚠️ Pago huérfano</p>
                                    <p class="text-[10px] text-amber-700 mt-0.5">Este pago entró al banco pero no está asociado a ningún pedido o venta en tu sistema.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                                <p class="text-[9px] text-gray-400 uppercase font-semibold mb-1">Estado</p>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="colorEstado(qrSeleccionado.Estado)">
                                    <i :class="['fas', iconoEstado(qrSeleccionado.Estado), 'text-[8px]']"></i> {{ qrSeleccionado.Estado }}
                                </span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                                <p class="text-[9px] text-gray-400 uppercase font-semibold mb-1">Monto</p>
                                <p class="text-base font-bold text-gray-800">Bs. {{ formatearMonto(qrSeleccionado.Monto) }}</p>
                            </div>
                        </div>

                        <div v-if="qrSeleccionado.MontoPagado" class="bg-emerald-50 rounded-lg p-2.5 border border-emerald-200 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-money-bill-wave text-xs"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[9px] text-emerald-700 uppercase font-semibold">Monto Pagado</p>
                                <p class="text-base font-bold text-emerald-600">Bs. {{ formatearMonto(qrSeleccionado.MontoPagado) }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                                <p class="text-[9px] text-gray-400 uppercase font-semibold mb-0.5">Origen</p>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold" :class="colorOrigen(qrSeleccionado.Origen)">
                                    <i :class="['fas', iconoOrigen(qrSeleccionado.Origen)]"></i> {{ qrSeleccionado.Origen }}
                                </span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                                <p class="text-[9px] text-gray-400 uppercase font-semibold mb-0.5">Transaction ID</p>
                                <p class="font-mono text-[10px] text-gray-800 break-all">{{ qrSeleccionado.TransactionId || '—' }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                                <p class="text-[9px] text-gray-400 uppercase font-semibold mb-0.5">Descripción</p>
                                <p class="text-[11px] text-gray-800">{{ qrSeleccionado.Descripcion || '—' }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                                <p class="text-[9px] text-gray-400 uppercase font-semibold mb-0.5">Fecha Creación</p>
                                <p class="text-[11px] text-gray-800">{{ formatearFecha(qrSeleccionado.FechaCreacion) }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                                <p class="text-[9px] text-gray-400 uppercase font-semibold mb-0.5">Fecha Pago</p>
                                <p class="text-[11px] text-gray-800">{{ formatearFecha(qrSeleccionado.FechaPago) }}</p>
                            </div>
                        </div>

                        <div v-if="qrSeleccionado.Credencial" class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                            <p class="text-[9px] text-gray-400 uppercase font-semibold mb-1">Credencial</p>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-xs text-gray-800">{{ qrSeleccionado.Credencial.Alias }}</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[8px] font-bold" :class="colorAmbiente(qrSeleccionado.Credencial.Ambiente)">
                                    {{ qrSeleccionado.Credencial.Ambiente }}
                                </span>
                            </div>
                        </div>

                        <div v-if="qrSeleccionado.DatosPago" class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                            <p class="text-[9px] text-gray-400 uppercase font-semibold mb-1.5">Datos del Pago</p>
                            <pre class="text-[10px] text-gray-700 overflow-x-auto whitespace-pre-wrap bg-white p-2 rounded border border-gray-200">{{ JSON.stringify(qrSeleccionado.DatosPago, null, 2) }}</pre>
                        </div>
                    </div>

                    <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end flex-shrink-0">
                        <button @click="cerrarDetalle" class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

.tabular-nums {
    font-variant-numeric: tabular-nums;
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