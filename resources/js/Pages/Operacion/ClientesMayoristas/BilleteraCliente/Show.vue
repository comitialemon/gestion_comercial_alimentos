<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    billetera: { type: Object, required: true },
    movimientos: { type: Object, default: () => ({ data: [] }) },
    filtros: { type: Object, default: () => ({}) },
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
const mostrarModalCargar = ref(false)
const mostrarModalAjuste = ref(false)
const mostrarModalInactivar = ref(false)

const formCargar = ref({
    Monto: '',
    OrigenPago: 'EFECTIVO',
    ReferenciaPago: '',
    Observaciones: '',
})

const formAjuste = ref({
    Monto: '',
    Motivo: '',
})

const formInactivar = ref({
    MotivoInactivacion: '',
})

const procesando = ref(false)

// Filtros historial
const filtroTipo = ref(props.filtros.tipo || '')
const filtroDesde = ref(props.filtros.desde || '')
const filtroHasta = ref(props.filtros.hasta || '')

// ==================== COMPUTED ====================
const estaActiva = computed(() => props.billetera.ActivoInactivo === 0)

const saldoFormateado = computed(() => {
    return parseFloat(props.billetera.SaldoActual || 0).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
})

const totalCargadoFormateado = computed(() => {
    return parseFloat(props.billetera.TotalCargado || 0).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
})

const totalConsumidoFormateado = computed(() => {
    return parseFloat(props.billetera.TotalConsumido || 0).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
})

// ==================== FUNCIONES ====================
const aplicarFiltros = () => {
    router.get(`/operacion/pedidos/clientes-mayoristas/billeteras/${props.billetera.IdBilletera}`, {
        tipo: filtroTipo.value || undefined,
        desde: filtroDesde.value || undefined,
        hasta: filtroHasta.value || undefined,
    }, { preserveState: true, replace: true })
}

const limpiarFiltros = () => {
    filtroTipo.value = ''
    filtroDesde.value = ''
    filtroHasta.value = ''
    aplicarFiltros()
}

// 🔥 NUEVA FUNCIÓN PARA EXPORTAR PDF
const exportarPdf = () => {
    const params = new URLSearchParams({
        tipo: filtroTipo.value || '',
        desde: filtroDesde.value || '',
        hasta: filtroHasta.value || ''
    }).toString();
    window.open(`/operacion/pedidos/clientes-mayoristas/billeteras/${props.billetera.IdBilletera}/pdf-movimientos?${params}`, '_blank');
}

const cargarSaldo = async () => {
    if (!formCargar.value.Monto || parseFloat(formCargar.value.Monto) <= 0) {
        toast?.warning('Ingresa un monto válido')
        return
    }

    procesando.value = true

    try {
        const res = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/billeteras/${props.billetera.IdBilletera}/cargar-saldo`,
            formCargar.value
        )

        if (res.data.success) {
            toast?.success(res.data.message)
            mostrarModalCargar.value = false
            formCargar.value = { Monto: '', OrigenPago: 'EFECTIVO', ReferenciaPago: '', Observaciones: '' }
            router.reload({ only: ['billetera', 'movimientos'] })
        }
    } catch (e) {
        toast?.error('Error', e.response?.data?.message || 'Error al cargar saldo')
    } finally {
        procesando.value = false
    }
}

const ajustarSaldo = async () => {
    if (!formAjuste.value.Monto || parseFloat(formAjuste.value.Monto) === 0) {
        toast?.warning('Ingresa un monto distinto de cero')
        return
    }
    if (!formAjuste.value.Motivo || formAjuste.value.Motivo.length < 5) {
        toast?.warning('El motivo es obligatorio (mínimo 5 caracteres)')
        return
    }

    procesando.value = true

    try {
        const res = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/billeteras/${props.billetera.IdBilletera}/ajustar-saldo`,
            formAjuste.value
        )

        if (res.data.success) {
            toast?.success(res.data.message)
            mostrarModalAjuste.value = false
            formAjuste.value = { Monto: '', Motivo: '' }
            router.reload({ only: ['billetera', 'movimientos'] })
        }
    } catch (e) {
        toast?.error('Error', e.response?.data?.message || 'Error al ajustar')
    } finally {
        procesando.value = false
    }
}

const activar = async () => {
    if (!confirm('¿Activar esta billetera?')) return
    procesando.value = true
    try {
        const res = await axios.post(`/operacion/pedidos/clientes-mayoristas/billeteras/${props.billetera.IdBilletera}/activar`)
        toast?.success(res.data.message)
        router.reload({ only: ['billetera'] })
    } catch (e) {
        toast?.error('Error', e.response?.data?.message || 'Error')
    } finally {
        procesando.value = false
    }
}

const inactivar = async () => {
    procesando.value = true
    try {
        const res = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/billeteras/${props.billetera.IdBilletera}/inactivar`,
            {
                MotivoInactivacion: formInactivar.value.MotivoInactivacion,
                ConfirmarSaldoCongelado: true,
            }
        )
        toast?.success(res.data.message)
        mostrarModalInactivar.value = false
        formInactivar.value = { MotivoInactivacion: '' }
        router.reload({ only: ['billetera'] })
    } catch (e) {
        toast?.error('Error', e.response?.data?.message || 'Error al inactivar')
    } finally {
        procesando.value = false
    }
}

const abrirModalInactivar = () => {
    formInactivar.value.MotivoInactivacion = ''
    mostrarModalInactivar.value = true
}

const formatearFechaHora = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleString('es-BO', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    })
}

const formatearMonto = (monto) => {
    const n = parseFloat(monto)
    const signo = n >= 0 ? '+' : ''
    return signo + n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const esPositivo = (monto) => parseFloat(monto) >= 0

const getTipoBadge = (tipo) => {
    const badges = {
        'ABONO': 'bg-emerald-100 text-emerald-700',
        'CARGO': 'bg-red-100 text-red-700',
        'AJUSTE_POSITIVO': 'bg-blue-100 text-blue-700',
        'AJUSTE_NEGATIVO': 'bg-orange-100 text-orange-700',
        'ANULACION': 'bg-purple-100 text-purple-700',
    }
    return badges[tipo] || 'bg-gray-100 text-gray-600'
}

const getTipoLabel = (tipo) => {
    const labels = {
        'ABONO': 'Abono',
        'CARGO': 'Pedido',
        'AJUSTE_POSITIVO': 'Ajuste (+)',
        'AJUSTE_NEGATIVO': 'Ajuste (−)',
        'ANULACION': 'Anulación',
    }
    return labels[tipo] || tipo
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
            <div class="max-w-5xl mx-auto">

                <!-- ==================== BREADCRUMB ==================== -->
                <div class="mb-3 flex items-center gap-1.5 text-xs">
                    <Link
                        href="/operacion/pedidos/clientes-mayoristas/billeteras"
                        class="text-gray-500 hover:text-primary-600 transition flex items-center gap-1"
                    >
                        <i class="fas fa-arrow-left text-[10px]"></i>
                        Billeteras
                    </Link>
                    <span class="text-gray-300">/</span>
                    <span class="text-gray-800 font-medium truncate">
                        {{ billetera.operador?.identificador?.Nombre || 'Sin nombre' }}
                    </span>
                </div>

                <!-- ==================== HEADER BILLETERA ==================== -->
                <div
                    class="rounded-xl shadow-sm p-4 mb-4 text-white overflow-hidden relative"
                    :class="estaActiva
                        ? 'bg-gradient-to-br from-primary-600 to-primary-800'
                        : 'bg-gradient-to-br from-gray-600 to-gray-800'"
                >
                    <!-- Fondo decorativo -->
                    <div class="absolute top-0 right-0 w-40 h-40 rounded-full bg-white/10 blur-2xl -mr-20 -mt-20 pointer-events-none"></div>

                    <div class="flex flex-wrap items-start justify-between gap-3 relative z-10">
                        <div class="flex-1 min-w-[200px]">
                            <p class="text-[10px] uppercase tracking-wider opacity-80 flex items-center gap-1">
                                <i :class="estaActiva ? 'fas fa-wallet' : 'fas fa-lock'" class="text-[10px]"></i>
                                {{ estaActiva ? 'Saldo disponible' : 'Saldo congelado' }}
                            </p>
                            <p class="text-3xl sm:text-4xl font-bold mt-1">
                                Bs. {{ saldoFormateado }}
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px]">
                                <span class="flex items-center gap-1">
                                    <i class="fas fa-user text-[9px]"></i>
                                    {{ billetera.operador?.identificador?.Nombre }}
                                </span>
                                <span class="flex items-center gap-1 opacity-70">
                                    <i class="fas fa-id-card text-[9px]"></i>
                                    CI: {{ billetera.operador?.identificador?.CI_NIT }}
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold"
                                    :class="estaActiva ? 'bg-emerald-400/30 text-emerald-50' : 'bg-gray-400/30 text-gray-100'">
                                    <i :class="estaActiva ? 'fas fa-check-circle' : 'fas fa-lock'" class="text-[7px] mr-0.5"></i>
                                    {{ estaActiva ? 'ACTIVA' : 'INACTIVA' }}
                                </span>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="grid grid-cols-2 gap-3 text-right">
                            <div>
                                <p class="text-[9px] opacity-70 uppercase">Total cargado</p>
                                <p class="text-sm font-bold">Bs. {{ totalCargadoFormateado }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] opacity-70 uppercase">Total consumido</p>
                                <p class="text-sm font-bold">Bs. {{ totalConsumidoFormateado }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Motivo inactivación -->
                    <div v-if="!estaActiva && billetera.MotivoInactivacion" class="mt-3 p-2 bg-white/10 rounded-md text-[10px] relative z-10">
                        <i class="fas fa-info-circle mr-1 text-[10px]"></i>
                        <strong>Motivo:</strong> {{ billetera.MotivoInactivacion }}
                    </div>
                </div>

                <!-- ==================== ACCIONES ==================== -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                    <button
                        @click="mostrarModalCargar = true"
                        :disabled="!estaActiva"
                        class="p-2.5 bg-white hover:bg-emerald-50 border border-gray-200 hover:border-emerald-300 rounded-xl transition flex flex-col items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i class="fas fa-plus-circle text-emerald-500 text-base"></i>
                        <span class="text-[10px] font-medium text-gray-700">Cargar Saldo</span>
                    </button>

                    <button
                        @click="mostrarModalAjuste = true"
                        class="p-2.5 bg-white hover:bg-blue-50 border border-gray-200 hover:border-blue-300 rounded-xl transition flex flex-col items-center gap-1"
                    >
                        <i class="fas fa-balance-scale text-blue-500 text-base"></i>
                        <span class="text-[10px] font-medium text-gray-700">Ajustar</span>
                    </button>

                    <button
                        v-if="estaActiva"
                        @click="abrirModalInactivar"
                        class="p-2.5 bg-white hover:bg-orange-50 border border-gray-200 hover:border-orange-300 rounded-xl transition flex flex-col items-center gap-1"
                    >
                        <i class="fas fa-lock text-orange-500 text-base"></i>
                        <span class="text-[10px] font-medium text-gray-700">Inactivar</span>
                    </button>

                    <button
                        v-else
                        @click="activar"
                        class="p-2.5 bg-white hover:bg-emerald-50 border border-gray-200 hover:border-emerald-300 rounded-xl transition flex flex-col items-center gap-1"
                    >
                        <i class="fas fa-unlock text-emerald-500 text-base"></i>
                        <span class="text-[10px] font-medium text-gray-700">Activar</span>
                    </button>
                </div>

                <!-- ==================== HISTORIAL ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                            <i class="fas fa-history text-primary-500 text-[10px]"></i>
                            Historial de movimientos
                        </h3>
                        <span class="text-[10px] text-gray-500">{{ movimientos.total || 0 }} mov.</span>
                    </div>

                    <!-- Filtros -->
                    <div class="px-3 py-2 border-b border-gray-200 bg-gray-50 flex flex-wrap gap-2 items-center justify-between">
                        <div class="flex flex-wrap gap-2 items-center">
                            <select
                                v-model="filtroTipo"
                                @change="aplicarFiltros"
                                class="border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                                <option value="">Todos los tipos</option>
                                <option value="ABONO">Abonos</option>
                                <option value="CARGO">Cargos</option>
                                <option value="AJUSTE_POSITIVO">Ajustes (+)</option>
                                <option value="AJUSTE_NEGATIVO">Ajustes (−)</option>
                                <option value="ANULACION">Anulaciones</option>
                            </select>

                            <input type="date" v-model="filtroDesde" @change="aplicarFiltros"
                                class="border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none" />
                            <input type="date" v-model="filtroHasta" @change="aplicarFiltros"
                                class="border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none" />

                            <button
                                v-if="filtroTipo || filtroDesde || filtroHasta"
                                @click="limpiarFiltros"
                                class="px-2.5 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-[10px] font-medium transition flex items-center gap-1"
                            >
                                <i class="fas fa-times text-[9px]"></i> Limpiar
                            </button>
                        </div>

                        <!-- 🔥 BOTÓN DE EXPORTAR PDF -->
                        <button 
                            @click="exportarPdf" 
                            class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-md text-[10px] font-medium transition flex items-center gap-1 shadow-sm" 
                            title="Exportar Estado de Cuenta a PDF"
                        >
                            <i class="fas fa-file-pdf text-[10px]"></i>
                            Exportar PDF
                        </button>
                    </div>

                    <!-- Lista de movimientos -->
                    <div v-if="movimientos.data.length > 0" class="divide-y divide-gray-100">
                        <div
                            v-for="mov in movimientos.data"
                            :key="mov.IdMovimiento"
                            class="px-3 py-2 hover:bg-gray-50 transition"
                        >
                            <div class="flex items-center gap-2">
                                <!-- Icono -->
                                <div
                                    class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                    :class="esPositivo(mov.Monto) ? 'bg-emerald-100' : 'bg-red-100'"
                                >
                                    <i
                                        :class="esPositivo(mov.Monto) ? 'fas fa-arrow-down text-emerald-600' : 'fas fa-arrow-up text-red-600'"
                                        class="text-xs"
                                    ></i>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span
                                            class="px-1.5 py-0.5 text-[9px] rounded-full font-medium"
                                            :class="getTipoBadge(mov.Tipo)"
                                        >
                                            {{ getTipoLabel(mov.Tipo) }}
                                        </span>
                                        <span class="text-[9px] text-gray-400">
                                            {{ formatearFechaHora(mov.FechaMovimiento) }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-gray-600 mt-0.5 truncate">{{ mov.Glosa }}</p>
                                    <p class="text-[9px] text-gray-400 mt-0.5">
                                        <i class="fas fa-user-edit mr-1 text-[8px]"></i>
                                        {{ mov.operador_inserta?.identificador?.Nombre || 'Sistema' }}
                                        <span v-if="mov.Observaciones"> · {{ mov.Observaciones }}</span>
                                    </p>
                                </div>

                                <div class="text-right flex-shrink-0">
                                    <p
                                        class="text-sm font-bold"
                                        :class="esPositivo(mov.Monto) ? 'text-emerald-600' : 'text-red-600'"
                                    >
                                        {{ formatearMonto(mov.Monto) }}
                                    </p>
                                    <p class="text-[9px] text-gray-400">
                                        Bs. {{ parseFloat(mov.SaldoDespues || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-10 text-center">
                        <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-inbox text-gray-400 text-lg"></i>
                        </div>
                        <p class="text-xs text-gray-500">No hay movimientos registrados</p>
                    </div>

                    <!-- Paginación -->
                    <div v-if="movimientos.links?.length > 3" class="px-3 py-2 border-t border-gray-200 bg-gray-50 flex justify-center">
                        <div class="flex gap-1 flex-wrap">
                            <Link
                                v-for="link in movimientos.links"
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
        </div>

        <!-- ==================== MODAL CARGAR SALDO ==================== -->
        <div v-if="mostrarModalCargar" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3" @click.self="mostrarModalCargar = false">
            <div class="bg-white rounded-xl w-full max-w-md shadow-xl overflow-hidden">
                <div class="px-4 py-2.5 bg-emerald-600 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <i class="fas fa-plus-circle text-xs"></i>
                            Cargar Saldo
                        </h2>
                        <p class="text-[9px] text-white/80">Aumenta el saldo disponible del operador</p>
                    </div>
                    <button @click="mostrarModalCargar = false" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="p-4 space-y-3">
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Monto *</label>
                        <div class="relative">
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs font-semibold">Bs.</span>
                            <input
                                type="number"
                                v-model="formCargar.Monto"
                                placeholder="0.00"
                                step="0.01"
                                min="0.01"
                                class="w-full border border-gray-300 rounded-md pl-8 pr-2.5 py-1.5 text-base font-bold focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Origen *</label>
                        <select v-model="formCargar.OrigenPago" class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TRANSFERENCIA">Transferencia</option>
                        </select>
                    </div>

                    <div v-if="formCargar.OrigenPago === 'TRANSFERENCIA'">
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Nro. Referencia</label>
                        <input type="text" v-model="formCargar.ReferenciaPago" placeholder="Ej: 123456789"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                    </div>

                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Observaciones (opcional)</label>
                        <textarea v-model="formCargar.Observaciones" rows="2" placeholder="Notas adicionales..."
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none resize-none"></textarea>
                    </div>
                </div>

                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button @click="mostrarModalCargar = false" class="px-3 py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition">
                        Cancelar
                    </button>
                    <button @click="cargarSaldo" :disabled="procesando"
                        class="px-3 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-md transition flex items-center gap-1.5 font-medium">
                        <i v-if="procesando" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-check text-[10px]"></i>
                        Cargar Saldo
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL AJUSTAR SALDO ==================== -->
        <div v-if="mostrarModalAjuste" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3" @click.self="mostrarModalAjuste = false">
            <div class="bg-white rounded-xl w-full max-w-md shadow-xl overflow-hidden">
                <div class="px-4 py-2.5 bg-blue-600 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <i class="fas fa-balance-scale text-xs"></i>
                            Ajustar Saldo
                        </h2>
                        <p class="text-[9px] text-white/80">Usa (+) para sumar, (−) para restar</p>
                    </div>
                    <button @click="mostrarModalAjuste = false" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="p-4 space-y-3">
                    <div class="p-2 bg-amber-50 border border-amber-200 rounded-md text-[10px] text-amber-800 flex items-start gap-1.5">
                        <i class="fas fa-exclamation-triangle mt-0.5 text-[10px]"></i>
                        <span>El motivo es obligatorio (mínimo 5 caracteres)</span>
                    </div>

                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Monto *</label>
                        <input type="number" v-model="formAjuste.Monto" placeholder="Ej: 100.00 o -50.00" step="0.01"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-base font-bold focus:ring-primary-500 focus:border-primary-500 outline-none" />
                    </div>

                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Motivo *</label>
                        <textarea v-model="formAjuste.Motivo" rows="3" placeholder="Explica por qué se hace este ajuste"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none resize-none"></textarea>
                    </div>
                </div>

                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button @click="mostrarModalAjuste = false" class="px-3 py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition">
                        Cancelar
                    </button>
                    <button @click="ajustarSaldo" :disabled="procesando"
                        class="px-3 py-1.5 text-xs bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-md transition flex items-center gap-1.5 font-medium">
                        <i v-if="procesando" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-check text-[10px]"></i>
                        Aplicar Ajuste
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL INACTIVAR ==================== -->
        <div v-if="mostrarModalInactivar" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3" @click.self="mostrarModalInactivar = false">
            <div class="bg-white rounded-xl w-full max-w-md shadow-xl overflow-hidden">
                <div class="px-4 py-2.5 bg-orange-600 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <i class="fas fa-lock text-xs"></i>
                            Inactivar Billetera
                        </h2>
                        <p class="text-[9px] text-white/80">Esta acción congelará el saldo</p>
                    </div>
                    <button @click="mostrarModalInactivar = false" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="p-4 space-y-3">
                    <div class="p-2 bg-orange-50 border border-orange-200 rounded-md text-[10px] text-orange-800 flex items-start gap-1.5">
                        <i class="fas fa-exclamation-triangle mt-0.5 text-[10px]"></i>
                        <span>
                            Si esta billetera tiene saldo <strong>Bs. {{ saldoFormateado }}</strong>, quedará <strong>congelado</strong> hasta que la reactives.
                        </span>
                    </div>

                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Motivo (opcional)</label>
                        <textarea v-model="formInactivar.MotivoInactivacion" rows="3"
                            placeholder="Ej: El operador cambiará a pago con QR"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none resize-none"></textarea>
                    </div>
                </div>

                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button @click="mostrarModalInactivar = false" class="px-3 py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition">
                        Cancelar
                    </button>
                    <button @click="inactivar" :disabled="procesando"
                        class="px-3 py-1.5 text-xs bg-orange-600 hover:bg-orange-700 disabled:opacity-50 text-white rounded-md transition flex items-center gap-1.5 font-medium">
                        <i v-if="procesando" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-lock text-[10px]"></i>
                        Sí, inactivar
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

.overflow-x-auto {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

.overflow-x-auto::-webkit-scrollbar {
    height: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
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