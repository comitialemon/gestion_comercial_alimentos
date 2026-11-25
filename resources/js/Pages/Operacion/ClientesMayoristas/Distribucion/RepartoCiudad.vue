<script setup>
import { ref, reactive, computed, inject, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    pedidosPendientes:  { type: Array, default: () => [] },
    pedidosEnTransito:  { type: Array, default: () => [] },
    pedidosFinalizados: { type: Array, default: () => [] },
})

const toast = inject('toast')

// ==================== DISPOSITIVO ====================
const isMobile = ref(false)
const handleResize = () => { isMobile.value = window.innerWidth < 640 }

// ==================== TABS ====================
const tabActiva = ref('pendientes') // 'pendientes' | 'transito' | 'finalizados'

// ==================== FILTROS ====================
const filtros = reactive({ q: '', fechaEntrega: '' })

// ==================== ACTUALIZAR ====================
const actualizando = ref(false)
const actualizar = () => {
    if (actualizando.value) return
    router.reload({
        only: ['pedidosPendientes', 'pedidosEnTransito', 'pedidosFinalizados'],
        onStart: () => { actualizando.value = true },
        onFinish: () => { actualizando.value = false },
        onSuccess: () => toast?.success('Actualizado', 'Datos refrescados correctamente'),
        onError: () => toast?.error('Error', 'No se pudieron actualizar los datos'),
    })
}

// ==================== HELPERS FECHA ====================
const toYMD = (fecha) => {
    if (!fecha) return null
    if (typeof fecha === 'string') return fecha.slice(0, 10)
    const d = new Date(fecha)
    if (isNaN(d.getTime())) return null
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}

const diasHasta = (fecha) => {
    const ymd = toYMD(fecha)
    if (!ymd) return null
    const [y, m, d] = ymd.split('-').map(Number)
    const target = new Date(y, m - 1, d)
    const now = new Date(); now.setHours(0,0,0,0)
    return Math.round((target - now) / (1000 * 60 * 60 * 24))
}

// ==================== LISTA BASE ====================
const pedidosBase = computed(() => {
    if (tabActiva.value === 'pendientes')  return props.pedidosPendientes
    if (tabActiva.value === 'transito')    return props.pedidosEnTransito
    if (tabActiva.value === 'finalizados') return props.pedidosFinalizados
    return []
})

const pedidosFiltrados = computed(() => {
    let lista = [...pedidosBase.value]

    const q = filtros.q.trim().toLowerCase()
    if (q) {
        lista = lista.filter(p => {
            const operador = (p.OperadorNombre || '').toLowerCase()
            const numero   = String(p.NumeroPedido || '').toLowerCase()
            return operador.includes(q) || numero.includes(q)
        })
    }

    if (filtros.fechaEntrega) {
        lista = lista.filter(p => toYMD(p.FechaEntrega) === filtros.fechaEntrega)
    }

    const cmpPedido = (a, b) => {
        const da = new Date(a.FechaPedido || 0).getTime() || 0
        const db = new Date(b.FechaPedido || 0).getTime() || 0
        return da - db
    }

    lista.sort((a, b) => {
        const ya = toYMD(a.FechaEntrega)
        const yb = toYMD(b.FechaEntrega)
        if (!ya && !yb) return cmpPedido(a, b)
        if (!ya) return 1
        if (!yb) return -1
        if (ya !== yb) return ya < yb ? -1 : 1
        return cmpPedido(a, b)
    })

    if (tabActiva.value === 'transito' || tabActiva.value === 'finalizados') lista.reverse()

    return lista
})

const pedidosActuales = pedidosFiltrados
const totalFiltrados = computed(() => pedidosFiltrados.value.length)
const totalBase = computed(() => pedidosBase.value.length)
const hayFiltrosActivos = computed(() => !!filtros.q || !!filtros.fechaEntrega)
const limpiarFiltros = () => { filtros.q = ''; filtros.fechaEntrega = '' }

// ==================== ACCIÓN (solo Pendientes) ====================
const modalEstadoVisible = ref(false)
const pedidoSeleccionado = ref(null)

const abrirModal = (pedido) => {
    pedidoSeleccionado.value = pedido
    modalEstadoVisible.value = true
}

const confirmar = async () => {
    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/distribucion/${pedidoSeleccionado.value.IdPedidoCliente}/cambiar-estado`,
            { modo: 'reparto', nuevo_estado: 'En Distribución' }
        )
        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            modalEstadoVisible.value = false
            router.reload()
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al cambiar estado')
    }
}

// ==================== UTILIDADES ====================
const formatoFecha = (fecha) => {
    if (!fecha) return '-'
    const ymd = toYMD(fecha); if (!ymd) return '-'
    const [y, m, d] = ymd.split('-')
    return `${d}/${m}/${y}`
}

const formatoFechaHora = (fecha) => {
    if (!fecha) return '-'
    const d = new Date(fecha)
    if (isNaN(d.getTime())) return '-'
    return d.toLocaleString('es-BO', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const badgeEntrega = (fecha) => {
    const dias = diasHasta(fecha)
    if (dias === null) return null
    if (dias < 0)  return { texto: `Vencido ${Math.abs(dias)}d`, clase: 'bg-red-100 text-red-700 border-red-300' }
    if (dias === 0) return { texto: 'Hoy',       clase: 'bg-amber-100 text-amber-800 border-amber-300' }
    if (dias === 1) return { texto: 'Mañana',    clase: 'bg-blue-100 text-blue-700 border-blue-300' }
    if (dias <= 3)  return { texto: `En ${dias}d`, clase: 'bg-sky-100 text-sky-700 border-sky-300' }
    return null
}

const badgeEstado = (estado) => {
    const e = (estado || '').trim()
    if (e === 'En Distribución')    return 'bg-blue-100 text-blue-700 border-blue-200'
    if (e === 'En Transportadora')  return 'bg-cyan-100 text-cyan-700 border-cyan-200'
    return 'bg-gray-100 text-gray-700 border-gray-200'
}

const verPdf = (idPedido) => {
    window.open(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${idPedido}/pdf`, '_blank')
}

// ==================== LIFECYCLE ====================
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
            <div class="max-w-full mx-auto">

                <!-- HEADER -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-city text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Cámara · Reparto Ciudad</h1>
                            <p class="text-xs text-gray-500">Supervisa tus pedidos: pendientes, en tránsito y finalizados</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="actualizar" :disabled="actualizando"
                            class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition shadow-sm disabled:opacity-60 disabled:cursor-not-allowed">
                            <i class="fas fa-sync-alt text-[10px]" :class="{ 'fa-spin': actualizando }"></i>
                            {{ actualizando ? 'Actualizando...' : 'Actualizar' }}
                        </button>
                        <span class="text-xs text-gray-500 bg-white border border-gray-200 px-2.5 py-1 rounded-md shadow-sm">
                            <i class="fas fa-clock text-primary-500 mr-1"></i>
                            <b>{{ pedidosPendientes.length }}</b> pendiente(s)
                        </span>
                    </div>
                </div>

                <!-- TABS -->
                <div class="bg-white rounded-xl shadow-sm p-1.5 mb-3 overflow-x-auto">
                    <div class="flex gap-1 min-w-max">
                        <button @click="tabActiva = 'pendientes'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'pendientes' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'">
                            <i class="fas fa-clock text-[10px]"></i> Pendientes
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="tabActiva === 'pendientes' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ pedidosPendientes.length }}
                            </span>
                        </button>
                        <button @click="tabActiva = 'transito'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'transito' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'">
                            <i class="fas fa-truck-moving text-[10px]"></i> En Tránsito
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="tabActiva === 'transito' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ pedidosEnTransito.length }}
                            </span>
                        </button>
                        <button @click="tabActiva = 'finalizados'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'finalizados' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'">
                            <i class="fas fa-check-double text-[10px]"></i> Finalizados
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="tabActiva === 'finalizados' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ pedidosFinalizados.length }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- FILTROS -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-3 p-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative flex-1 min-w-[160px]">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[11px]"></i>
                            <input v-model="filtros.q" type="text" placeholder="Buscar operador o #pedido..."
                                class="w-full border border-gray-300 rounded-md pl-7 pr-2.5 py-1.5 text-sm sm:text-xs outline-none focus:border-primary-500" />
                        </div>
                        <div class="flex items-center gap-1.5">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">
                                <i class="fas fa-calendar-check text-primary-500 mr-0.5"></i> Entrega:
                            </label>
                            <input v-model="filtros.fechaEntrega" type="date"
                                class="border border-gray-300 rounded-md px-2 py-1.5 text-sm sm:text-xs outline-none focus:border-primary-500" />
                        </div>
                        <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
                            class="px-2.5 py-1.5 text-[11px] bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-md transition flex items-center gap-1 whitespace-nowrap">
                            <i class="fas fa-times text-[10px]"></i> Limpiar
                        </button>
                    </div>
                    <div v-if="hayFiltrosActivos" class="text-[11px] text-gray-500 mt-1.5 px-0.5">
                        Mostrando <b>{{ totalFiltrados }}</b> de <b>{{ totalBase }}</b> pedidos
                    </div>
                </div>

                <!-- TABLA -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 75vh; overflow-y: auto;">

                        <!-- MÓVIL -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="p in pedidosActuales" :key="p.IdPedidoCliente" class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start mb-1.5 gap-2">
                                    <span class="text-xs font-bold text-primary-700 font-mono">#{{ p.NumeroPedido }}</span>
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="text-[9px] text-gray-400">{{ formatoFechaHora(p.FechaPedido) }}</span>
                                        <span v-if="p.FechaEntrega" class="text-[9px] text-gray-600 font-medium">
                                            <i class="fas fa-calendar-check mr-0.5 text-primary-500"></i>
                                            {{ formatoFecha(p.FechaEntrega) }}
                                        </span>
                                        <span v-if="badgeEntrega(p.FechaEntrega)" class="text-[8px] px-1.5 py-0.5 rounded font-bold border" :class="badgeEntrega(p.FechaEntrega).clase">
                                            {{ badgeEntrega(p.FechaEntrega).texto }}
                                        </span>
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-500 uppercase font-bold tracking-wider">Operador:</p>
                                <p class="text-xs font-bold text-gray-800 truncate mb-1">{{ p.OperadorNombre || 'Sin operador' }}</p>
                                <div class="flex items-center gap-1 mb-2">
                                    <span class="text-[8px] bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded font-bold border border-primary-200">CIUDAD</span>
                                    <span v-if="tabActiva !== 'pendientes'" class="text-[8px] px-1.5 py-0.5 rounded font-bold border" :class="badgeEstado(p.EstadoPedido)">
                                        {{ p.EstadoPedido }}
                                    </span>
                                </div>

                                <!-- Guías (tránsito y finalizados) -->
                                <div v-if="tabActiva !== 'pendientes' && p.envios?.length" class="mt-1.5 pt-1.5 border-t border-gray-200 space-y-1">
                                    <div v-for="e in p.envios" :key="e.IdEnvio" class="border-l-2 border-primary-300 pl-2">
                                        <p class="text-[10px] font-bold text-primary-700">{{ e.transportadora?.identificador?.Nombre || 'Sin transportadora' }}</p>
                                        <p class="text-[9px] text-gray-500">Guía: {{ e.NumeroGuia || '-' }} | Bs. {{ e.CostoEnvio || '0.00' }}</p>
                                        <p v-if="e.Observaciones" class="text-[9px] text-gray-400 italic">Obs: {{ e.Observaciones }}</p>
                                    </div>
                                </div>

                                <!-- Fecha entrega real (finalizados) -->
                                <div v-if="tabActiva === 'finalizados'" class="mt-1.5 text-[9px] text-emerald-700 font-bold">
                                    <i class="fas fa-check-circle mr-0.5"></i>
                                    Entregado: {{ p.envios?.[0]?.FechaEntregaReal ? formatoFechaHora(p.envios[0].FechaEntregaReal) : formatoFechaHora(p.FechaActualiza) }}
                                </div>

                                <div class="flex justify-end items-center mt-2 pt-2 border-t border-gray-200 gap-2">
                                    <button @click="verPdf(p.IdPedidoCliente)"
                                        class="flex items-center gap-1 bg-rose-100 text-rose-700 hover:bg-rose-200 px-2.5 py-1.5 rounded-md text-[10px] font-bold transition border border-rose-200">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </button>
                                    <button v-if="tabActiva === 'pendientes'" @click="abrirModal(p)"
                                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-[10px] font-bold transition flex items-center gap-1 shadow-sm">
                                        <i class="fas fa-play text-[8px]"></i> Comenzar
                                    </button>
                                </div>
                            </div>
                            <div v-if="pedidosActuales.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay pedidos en esta tab</span>
                            </div>
                        </div>

                        <!-- DESKTOP -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase w-24">Pedido</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase">Operador</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase w-32">Fecha Pedido</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase w-32">Fecha Entrega</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase w-16">Cont.</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase">Detalle</th>
                                    <th class="px-4 py-3 text-right text-[10px] font-bold text-gray-500 uppercase w-48">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="p in pedidosActuales" :key="p.IdPedidoCliente" class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 text-xs font-mono font-bold text-primary-700">#{{ p.NumeroPedido }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs text-gray-800 mb-1"><b>{{ p.OperadorNombre || 'Sin operador' }}</b></div>
                                        <div class="flex items-center gap-1">
                                            <span class="text-[8px] bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded font-bold border border-primary-200">CIUDAD</span>
                                            <span v-if="tabActiva !== 'pendientes'" class="text-[8px] px-1.5 py-0.5 rounded font-bold border" :class="badgeEstado(p.EstadoPedido)">
                                                {{ p.EstadoPedido }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-[11px] text-gray-600 whitespace-nowrap">{{ formatoFechaHora(p.FechaPedido) }}</td>
                                    <td class="px-4 py-3 text-[11px] whitespace-nowrap">
                                        <div v-if="p.FechaEntrega" class="flex flex-col gap-0.5">
                                            <span class="text-gray-700 font-medium">{{ formatoFecha(p.FechaEntrega) }}</span>
                                            <span v-if="badgeEntrega(p.FechaEntrega)" class="text-[8px] px-1.5 py-0.5 rounded font-bold border w-fit" :class="badgeEntrega(p.FechaEntrega).clase">
                                                {{ badgeEntrega(p.FechaEntrega).texto }}
                                            </span>
                                        </div>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 font-medium">{{ p.TotalContenedores }} cont.</td>
                                    <td class="px-4 py-3 text-xs">
                                        <template v-if="tabActiva === 'pendientes'">
                                            <span class="text-gray-500">{{ p.TotalContenedores }} contenedores</span>
                                        </template>
                                        <template v-else>
                                            <div v-if="p.envios && p.envios.length > 0" class="space-y-1">
                                                <div v-for="e in p.envios" :key="e.IdEnvio" class="border-l-2 border-primary-300 pl-2">
                                                    <div class="font-bold text-primary-700 text-[11px]">{{ e.transportadora?.identificador?.Nombre || 'Sin transportadora' }}</div>
                                                    <div class="text-[9px] text-gray-500">Guía: {{ e.NumeroGuia || '-' }} | Bs. {{ e.CostoEnvio || '0.00' }}</div>
                                                    <div v-if="e.Observaciones" class="text-[9px] text-gray-400 italic">Obs: {{ e.Observaciones }}</div>
                                                </div>
                                            </div>
                                            <span v-else class="text-gray-400 italic text-[10px]">Sin guías</span>
                                            <div v-if="tabActiva === 'finalizados'" class="mt-1 text-[10px] text-emerald-700 font-bold">
                                                <i class="fas fa-check-circle mr-0.5"></i>
                                                Entregado: {{ p.envios?.[0]?.FechaEntregaReal ? formatoFechaHora(p.envios[0].FechaEntregaReal) : formatoFechaHora(p.FechaActualiza) }}
                                            </div>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="verPdf(p.IdPedidoCliente)"
                                                class="flex items-center gap-1 bg-rose-100 text-rose-700 hover:bg-rose-200 border border-rose-200 px-2.5 py-1.5 rounded-md text-[10px] font-bold transition shadow-sm">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </button>
                                            <button v-if="tabActiva === 'pendientes'" @click="abrirModal(p)"
                                                class="inline-flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-[10px] font-bold transition shadow-sm">
                                                <i class="fas fa-play text-[9px]"></i> Comenzar Distribución
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="pedidosActuales.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                        No hay pedidos en esta tab
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL CONFIRMAR (solo Pendientes) -->
        <div v-if="modalEstadoVisible" class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-0 sm:p-3"
            @click.self="modalEstadoVisible = false">
            <div class="bg-white w-full sm:max-w-md sm:rounded-xl rounded-t-2xl shadow-xl overflow-hidden max-h-[92vh] flex flex-col">
                <div class="px-4 py-2.5 bg-primary-600 flex items-center justify-between flex-shrink-0">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <i class="fas fa-play text-xs"></i> Comenzar Distribución · Ciudad
                        </h3>
                        <p class="text-[9px] text-white/80 truncate">Pedido #{{ pedidoSeleccionado?.NumeroPedido }}</p>
                    </div>
                    <button @click="modalEstadoVisible = false" class="text-white/80 hover:text-white p-1">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <div class="p-4">
                    <p class="text-xs text-gray-600">
                        El pedido pasará a <strong class="text-primary-600">En Distribución</strong>. ¿Confirmas?
                    </p>
                </div>
                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2 flex-shrink-0">
                    <button @click="modalEstadoVisible = false" class="px-3 py-2 sm:py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition min-h-[40px] sm:min-h-0">
                        Cancelar
                    </button>
                    <button @click="confirmar" class="px-3 py-2 sm:py-1.5 text-xs bg-primary-600 hover:bg-primary-700 text-white rounded-md transition flex items-center gap-1.5 font-medium min-h-[40px] sm:min-h-0">
                        <i class="fas fa-check text-[10px]"></i> Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) { input, select, textarea { font-size: 16px !important; } }
@media (min-width: 1024px) { input, select, button { font-size: 13px !important; } }
.overflow-x-auto { -webkit-overflow-scrolling: touch; scrollbar-width: thin; }
.overflow-x-auto::-webkit-scrollbar { height: 4px; }
.overflow-x-auto::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.overflow-x-auto::-webkit-scrollbar-track { background: #f1f5f9; }
.overflow-y-auto::-webkit-scrollbar { width: 4px; }
.overflow-y-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
</style>