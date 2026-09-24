<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, computed, inject } from 'vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    gestion: Object,
    facturacion: Object,
    diaActual: Object,
    fechasPendientes: Array,
    flash: Object,
})

// ==================== DETECTAR DISPOSITIVO ====================
const windowWidth = ref(window.innerWidth)
const isMobile = computed(() => windowWidth.value < 640)
const handleResize = () => (windowWidth.value = window.innerWidth)

onMounted(() => window.addEventListener('resize', handleResize))
onUnmounted(() => window.removeEventListener('resize', handleResize))

// ==================== COMPUTED ====================
const facturacionCompleta = computed(() => props.facturacion?.completo === true)
const hayDatosHoy = computed(() => props.diaActual?.tieneDatos === true)
const hayFechasPendientes = computed(() => (props.fechasPendientes?.length || 0) > 0)

// ==================== FORMATEO ====================
const formatearNumero = (value) => {
    return Number(value || 0).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

// ==================== ICONOS DINÁMICOS ====================
const getIconoConcepto = (nombre, index) => {
    const n = (nombre || '').toLowerCase()
    if (n.includes('efectivo')) return 'fa-money-bill-wave'
    if (n.includes('qr')) return 'fa-qrcode'
    if (n.includes('tarjeta')) return 'fa-credit-card'
    if (n.includes('cliente')) return 'fa-user'
    if (n.includes('transferencia')) return 'fa-exchange-alt'
    const fallback = ['fa-money-bill-wave', 'fa-qrcode', 'fa-credit-card', 'fa-user']
    return fallback[index % 4]
}

const getColorConcepto = (nombre, index) => {
    const n = (nombre || '').toLowerCase()
    if (n.includes('efectivo')) return { bg: 'bg-primary-50', text: 'text-primary-600' }
    if (n.includes('qr')) return { bg: 'bg-primary-50', text: 'text-primary-600' }
    if (n.includes('tarjeta')) return { bg: 'bg-primary-50', text: 'text-primary-600' }
    if (n.includes('cliente')) return { bg: 'bg-primary-50', text: 'text-primary-600' }
    return { bg: 'bg-primary-50', text: 'text-primary-600' }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    if (props.flash?.ok) toast?.success('Éxito', props.flash.ok)
    if (props.flash?.warn) toast?.warning('Advertencia', props.flash.warn)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-3xl mx-auto">

                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-home text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Inicio Oficial</h1>
                            <p class="text-xs text-gray-500">
                                {{ gestion?.empresa_nombre || 'Sin empresa' }}
                                <span v-if="gestion?.sucursal_nombre"> · {{ gestion.sucursal_nombre }}</span>
                            </p>
                        </div>
                    </div>
                    <span
                        class="px-2.5 py-1 rounded-full text-[10px] font-medium flex items-center gap-1.5 border"
                        :class="facturacionCompleta
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                            : 'bg-yellow-50 text-yellow-700 border-yellow-200'"
                    >
                        <i :class="facturacionCompleta ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'" class="text-[9px]"></i>
                        <span class="hidden sm:inline">{{ facturacionCompleta ? 'Facturación activa' : 'Facturación pendiente' }}</span>
                    </span>
                </div>

                <!-- ==================== ALERTA FECHAS PENDIENTES ==================== -->
                <div
                    v-if="hayFechasPendientes"
                    class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4"
                >
                    <div class="flex items-start gap-2.5">
                        <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-amber-600 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-amber-800">
                                Tenés {{ fechasPendientes.length }}
                                {{ fechasPendientes.length === 1 ? 'fecha pendiente' : 'fechas pendientes' }} por liquidar
                            </p>
                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                <span
                                    v-for="fecha in fechasPendientes"
                                    :key="fecha.id"
                                    class="text-[10px] bg-white border border-amber-200 text-amber-700 px-2 py-0.5 rounded-full font-medium flex items-center gap-1"
                                >
                                    <i class="fas fa-calendar-day text-[8px]"></i>
                                    {{ fecha.fecha }}
                                    <span class="text-amber-400">·</span>
                                    <span class="font-bold">Bs {{ formatearNumero(fecha.total_ventas) }}</span>
                                </span>
                            </div>
                            <Link
                                href="/gestion/impuestos/liquidacion-vendedor"
                                class="inline-flex items-center gap-1.5 mt-2 text-[10px] font-semibold text-amber-700 hover:text-amber-900 transition"
                            >
                                Ir a liquidar
                                <i class="fas fa-arrow-right text-[9px]"></i>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- ==================== CARD PRINCIPAL ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">

                    <!-- Header con color primario -->
                    <div class="bg-primary-600 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] text-white/80 uppercase tracking-wide font-medium flex items-center gap-1.5">
                                    <i class="fas fa-calendar-day text-[9px]"></i>
                                    {{ diaActual?.fecha ? 'Fecha: ' + diaActual.fecha : 'Sin fecha pendiente' }}
                                </p>
                                <p class="text-2xl font-bold text-white mt-0.5 tabular-nums">
                                    Bs {{ formatearNumero(diaActual?.totalVentas) }}
                                </p>
                                <p class="text-[10px] text-white/80 mt-0.5">
                                    Total Ventas del día
                                </p>
                            </div>
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-chart-line text-white text-lg"></i>
                            </div>
                        </div>
                    </div>

                    <!-- LISTA DE CONCEPTOS -->
                    <template v-if="hayDatosHoy">
                        <div class="divide-y divide-gray-100">
                            <div
                                v-for="(concepto, index) in diaActual.conceptos"
                                :key="concepto.id"
                                class="flex items-center justify-between px-4 py-2.5 hover:bg-gray-50 transition"
                            >
                                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                    <div
                                        class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                        :class="[getColorConcepto(concepto.nombre, index).bg, getColorConcepto(concepto.nombre, index).text]"
                                    >
                                        <i class="fas text-xs" :class="getIconoConcepto(concepto.nombre, index)"></i>
                                    </div>
                                    <span class="text-xs font-medium text-gray-700 truncate">{{ concepto.nombre }}</span>
                                </div>
                                <span class="text-sm font-semibold text-gray-800 tabular-nums flex-shrink-0 ml-2">
                                    {{ formatearNumero(concepto.monto) }}
                                </span>
                            </div>
                        </div>

                        <!-- Diferencia -->
                        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-balance-scale text-gray-400 text-sm"></i>
                                    <span class="text-xs font-semibold text-gray-700">Diferencia</span>
                                </div>
                                <span
                                    class="text-base font-bold tabular-nums"
                                    :class="diaActual.diferencia >= 0 ? 'text-emerald-600' : 'text-red-600'"
                                >
                                    Bs {{ formatearNumero(diaActual.diferencia) }}
                                </span>
                            </div>
                        </div>
                    </template>

                    <!-- Sin datos -->
                    <div v-else class="px-4 py-10 text-center">
                        <div class="w-14 h-14 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-inbox text-gray-300 text-xl"></i>
                        </div>
                        <p class="text-sm font-semibold text-gray-700">Sin ventas pendientes</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">
                            No hay movimientos por liquidar
                        </p>
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

.tabular-nums {
    font-variant-numeric: tabular-nums;
}

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>