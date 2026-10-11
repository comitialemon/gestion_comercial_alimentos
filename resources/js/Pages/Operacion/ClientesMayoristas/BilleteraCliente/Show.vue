<script setup>
import { ref, computed, inject } from 'vue'
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

// Modales
const mostrarModalCargar = ref(false)
const mostrarModalAjuste = ref(false)
const mostrarModalInactivar = ref(false)

// Formulario cargar
const formCargar = ref({
    Monto: '',
    OrigenPago: 'EFECTIVO',
    ReferenciaPago: '',
    Observaciones: '',
})

// Formulario ajuste
const formAjuste = ref({
    Monto: '',
    Motivo: '',
})

// Formulario inactivar
const formInactivar = ref({
    MotivoInactivacion: '',
})

const procesando = ref(false)

// Filtros historial
const filtroTipo = ref(props.filtros.tipo || '')
const filtroDesde = ref(props.filtros.desde || '')
const filtroHasta = ref(props.filtros.hasta || '')

const estaActiva = computed(() => props.billetera.ActivoInactivo === 0)
const saldoFormateado = computed(() => {
    return parseFloat(props.billetera.SaldoActual || 0).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
})

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
        'ABONO': 'bg-green-100 text-green-800',
        'CARGO': 'bg-red-100 text-red-800',
        'AJUSTE_POSITIVO': 'bg-blue-100 text-blue-800',
        'AJUSTE_NEGATIVO': 'bg-orange-100 text-orange-800',
        'ANULACION': 'bg-purple-100 text-purple-800',
    }
    return badges[tipo] || 'bg-gray-100 text-gray-800'
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
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-10">
        <div class="max-w-5xl mx-auto px-3 py-4">

            <!-- BREADCRUMB -->
            <div class="mb-3 flex items-center gap-2 text-xs">
                <Link
                    href="/operacion/pedidos/clientes-mayoristas/billeteras"
                    class="text-gray-500 hover:text-primary-600 transition"
                >
                    <i class="fas fa-arrow-left mr-1"></i>
                    Billeteras
                </Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-800 font-medium">
                    {{ billetera.operador?.identificador?.Nombre || 'Sin nombre' }}
                </span>
            </div>

            <!-- HEADER BILLETERA -->
            <div
                class="rounded-2xl shadow-lg p-6 mb-4 text-white"
                :class="estaActiva
                    ? 'bg-gradient-to-br from-primary-600 to-primary-800'
                    : 'bg-gradient-to-br from-gray-600 to-gray-800'"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <p class="text-xs uppercase tracking-wider opacity-80">
                            <i :class="estaActiva ? 'fas fa-wallet' : 'fas fa-lock'" class="mr-1"></i>
                            {{ estaActiva ? 'Saldo disponible' : 'Saldo congelado' }}
                        </p>
                        <p class="text-4xl sm:text-5xl font-bold mt-2">
                            Bs. {{ saldoFormateado }}
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs">
                            <span class="flex items-center gap-1">
                                <i class="fas fa-user"></i>
                                {{ billetera.operador?.identificador?.Nombre }}
                            </span>
                            <span class="flex items-center gap-1 opacity-70">
                                CI: {{ billetera.operador?.identificador?.CI_NIT }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                :class="estaActiva ? 'bg-green-400/30 text-green-50' : 'bg-gray-400/30 text-gray-100'">
                                {{ estaActiva ? 'ACTIVA' : 'INACTIVA' }}
                            </span>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-3 text-right">
                        <div>
                            <p class="text-[10px] opacity-70 uppercase">Total cargado</p>
                            <p class="text-sm font-bold">
                                Bs. {{ parseFloat(billetera.TotalCargado || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] opacity-70 uppercase">Total consumido</p>
                            <p class="text-sm font-bold">
                                Bs. {{ parseFloat(billetera.TotalConsumido || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Motivo inactivación -->
                <div v-if="!estaActiva && billetera.MotivoInactivacion" class="mt-4 p-3 bg-white/10 rounded-lg text-xs">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Motivo:</strong> {{ billetera.MotivoInactivacion }}
                </div>
            </div>

            <!-- ACCIONES PRINCIPALES -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                <button
                    @click="mostrarModalCargar = true"
                    :disabled="!estaActiva"
                    class="p-3 bg-white hover:bg-green-50 border border-gray-200 hover:border-green-300 rounded-xl transition flex flex-col items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-plus-circle text-green-500 text-xl"></i>
                    <span class="text-xs font-medium text-gray-700">Cargar Saldo</span>
                </button>

                <button
                    @click="mostrarModalAjuste = true"
                    class="p-3 bg-white hover:bg-blue-50 border border-gray-200 hover:border-blue-300 rounded-xl transition flex flex-col items-center gap-1"
                >
                    <i class="fas fa-balance-scale text-blue-500 text-xl"></i>
                    <span class="text-xs font-medium text-gray-700">Ajustar</span>
                </button>

                <button
                    v-if="estaActiva"
                    @click="abrirModalInactivar"
                    class="p-3 bg-white hover:bg-orange-50 border border-gray-200 hover:border-orange-300 rounded-xl transition flex flex-col items-center gap-1"
                >
                    <i class="fas fa-lock text-orange-500 text-xl"></i>
                    <span class="text-xs font-medium text-gray-700">Inactivar</span>
                </button>

                <button
                    v-else
                    @click="activar"
                    class="p-3 bg-white hover:bg-green-50 border border-gray-200 hover:border-green-300 rounded-xl transition flex flex-col items-center gap-1"
                >
                    <i class="fas fa-unlock text-green-500 text-xl"></i>
                    <span class="text-xs font-medium text-gray-700">Activar</span>
                </button>
            </div>

            <!-- HISTORIAL -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800 text-sm">
                        <i class="fas fa-history text-primary-500 mr-2"></i>
                        Historial de movimientos
                    </h3>
                </div>

                <!-- Filtros -->
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex flex-wrap gap-2 items-center">
                    <select
                        v-model="filtroTipo"
                        @change="aplicarFiltros"
                        class="border border-gray-200 rounded-lg px-2 py-1 text-xs outline-none"
                    >
                        <option value="">Todos los tipos</option>
                        <option value="ABONO">Abonos</option>
                        <option value="CARGO">Cargos (pedidos)</option>
                        <option value="AJUSTE_POSITIVO">Ajustes (+)</option>
                        <option value="AJUSTE_NEGATIVO">Ajustes (−)</option>
                        <option value="ANULACION">Anulaciones</option>
                    </select>

                    <input type="date" v-model="filtroDesde" @change="aplicarFiltros"
                        class="border border-gray-200 rounded-lg px-2 py-1 text-xs outline-none" />
                    <input type="date" v-model="filtroHasta" @change="aplicarFiltros"
                        class="border border-gray-200 rounded-lg px-2 py-1 text-xs outline-none" />

                    <button
                        v-if="filtroTipo || filtroDesde || filtroHasta"
                        @click="limpiarFiltros"
                        class="text-xs text-gray-400 hover:text-gray-600"
                    >
                        <i class="fas fa-times mr-1"></i>Limpiar
                    </button>
                </div>

                <!-- Lista -->
                <div v-if="movimientos.data.length > 0" class="divide-y divide-gray-100">
                    <div
                        v-for="mov in movimientos.data"
                        :key="mov.IdMovimiento"
                        class="px-4 py-3 hover:bg-gray-50 transition"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
                                :class="esPositivo(mov.Monto) ? 'bg-green-100' : 'bg-red-100'"
                            >
                                <i
                                    :class="esPositivo(mov.Monto) ? 'fas fa-arrow-down text-green-600' : 'fas fa-arrow-up text-red-600'"
                                    class="text-xs"
                                ></i>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        class="px-2 py-0.5 text-[10px] rounded-full font-medium"
                                        :class="getTipoBadge(mov.Tipo)"
                                    >
                                        {{ getTipoLabel(mov.Tipo) }}
                                    </span>
                                    <span class="text-[10px] text-gray-400">
                                        {{ formatearFechaHora(mov.FechaMovimiento) }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 mt-0.5 truncate">{{ mov.Glosa }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">
                                    <i class="fas fa-user-edit mr-1"></i>
                                    {{ mov.operador_inserta?.identificador?.Nombre || 'Sistema' }}
                                    <span v-if="mov.Observaciones"> · {{ mov.Observaciones }}</span>
                                </p>
                            </div>

                            <div class="text-right flex-shrink-0">
                                <p
                                    class="text-sm font-bold"
                                    :class="esPositivo(mov.Monto) ? 'text-green-600' : 'text-red-600'"
                                >
                                    {{ formatearMonto(mov.Monto) }}
                                </p>
                                <p class="text-[10px] text-gray-400">
                                    Bs. {{ parseFloat(mov.SaldoDespues || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="p-12 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-inbox text-gray-400 text-xl"></i>
                    </div>
                    <p class="text-sm text-gray-500">No hay movimientos</p>
                </div>

                <!-- Paginación -->
                <div v-if="movimientos.links?.length > 3" class="px-4 py-3 border-t border-gray-100 flex justify-center">
                    <div class="flex gap-1 flex-wrap">
                        <Link
                            v-for="link in movimientos.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="px-2.5 py-1 rounded text-xs transition"
                            :class="{
                                'bg-primary-600 text-white': link.active,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200': !link.active && link.url,
                                'opacity-50 cursor-not-allowed': !link.url
                            }"
                            v-html="link.label"
                            preserve-state
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL CARGAR SALDO -->
        <!-- ============================================================ -->
        <div v-if="mostrarModalCargar" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="mostrarModalCargar = false">
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-800">
                        <i class="fas fa-plus-circle text-green-500 mr-2"></i>
                        Cargar Saldo
                    </h2>
                    <button @click="mostrarModalCargar = false" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Monto *</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold">Bs.</span>
                            <input
                                type="number"
                                v-model="formCargar.Monto"
                                placeholder="0.00"
                                step="0.01"
                                min="0.01"
                                class="w-full border border-gray-200 rounded-lg pl-10 pr-3 py-2 text-lg font-bold outline-none focus:ring-2 focus:ring-primary-400"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Origen *</label>
                        <select v-model="formCargar.OrigenPago" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-400">
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TRANSFERENCIA">Transferencia</option>
                        </select>
                    </div>

                    <div v-if="formCargar.OrigenPago === 'TRANSFERENCIA'">
                        <label class="text-xs font-medium text-gray-600 block mb-1">Nro. Referencia</label>
                        <input type="text" v-model="formCargar.ReferenciaPago" placeholder="Ej: 123456789"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-400" />
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Observaciones (opcional)</label>
                        <textarea v-model="formCargar.Observaciones" rows="2" placeholder="Notas adicionales..."
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-400 resize-none"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex gap-2 justify-end">
                    <button @click="mostrarModalCargar = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-200 rounded-lg transition">
                        Cancelar
                    </button>
                    <button @click="cargarSaldo" :disabled="procesando"
                        class="px-4 py-2 text-sm bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-lg transition flex items-center gap-2">
                        <i v-if="procesando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check"></i>
                        Cargar Saldo
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL AJUSTAR SALDO -->
        <!-- ============================================================ -->
        <div v-if="mostrarModalAjuste" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="mostrarModalAjuste = false">
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-800">
                        <i class="fas fa-balance-scale text-blue-500 mr-2"></i>
                        Ajustar Saldo
                    </h2>
                    <button @click="mostrarModalAjuste = false" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-xs text-yellow-800">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Usa el signo (+) para sumar, (−) para restar. El motivo es obligatorio.
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Monto *</label>
                        <input type="number" v-model="formAjuste.Monto" placeholder="Ej: 100.00 o -50.00" step="0.01"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-lg font-bold outline-none focus:ring-2 focus:ring-blue-400" />
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Motivo *</label>
                        <textarea v-model="formAjuste.Motivo" rows="3" placeholder="Explica por qué se hace este ajuste (mínimo 5 caracteres)"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400 resize-none"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex gap-2 justify-end">
                    <button @click="mostrarModalAjuste = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-200 rounded-lg transition">
                        Cancelar
                    </button>
                    <button @click="ajustarSaldo" :disabled="procesando"
                        class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg transition flex items-center gap-2">
                        <i v-if="procesando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check"></i>
                        Aplicar Ajuste
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL INACTIVAR -->
        <!-- ============================================================ -->
        <div v-if="mostrarModalInactivar" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="mostrarModalInactivar = false">
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-800">
                        <i class="fas fa-lock text-orange-500 mr-2"></i>
                        Inactivar Billetera
                    </h2>
                    <button @click="mostrarModalInactivar = false" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-xs text-orange-800">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Importante:</strong> Si esta billetera tiene saldo
                        (Bs. {{ saldoFormateado }}), quedará <strong>congelado</strong> hasta que la reactives.
                        El operador dejará de poder usarla y tendrá que pagar con QR.
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">
                            Motivo (opcional)
                        </label>
                        <textarea v-model="formInactivar.MotivoInactivacion" rows="3"
                            placeholder="Ej: El operador cambiará a pago con QR"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex gap-2 justify-end">
                    <button @click="mostrarModalInactivar = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-200 rounded-lg transition">
                        Cancelar
                    </button>
                    <button @click="inactivar" :disabled="procesando"
                        class="px-4 py-2 text-sm bg-orange-600 hover:bg-orange-700 disabled:opacity-50 text-white rounded-lg transition flex items-center gap-2">
                        <i v-if="procesando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-lock"></i>
                        Sí, inactivar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>