<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    gestion: Object,
    facturacion: Object,
    esVentaMostrador: Boolean,
    diaActual: Object,
    fechasPendientes: Array,
    flash: Object,
    notificacionesInicio: { type: Array, default: () => [] } 
})

// 🔥 RESTO DEL SCRIPT
const windowWidth = ref(window.innerWidth)
const isMobile = computed(() => windowWidth.value < 640)
const handleResize = () => (windowWidth.value = window.innerWidth)
onMounted(() => window.addEventListener('resize', handleResize))
onUnmounted(() => window.removeEventListener('resize', handleResize))

const facturacionCompleta = computed(() => props.facturacion?.completo === true)

const mostrarToast = (mensaje, tipo = 'success') => {
    const colores = { success: 'bg-emerald-500', warning: 'bg-yellow-500', error: 'bg-red-500', info: 'bg-blue-500' }
    const iconos = { success: 'fas fa-check-circle', warning: 'fas fa-exclamation-triangle', error: 'fas fa-times-circle', info: 'fas fa-info-circle' }
    const toast = document.createElement('div')
    toast.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white ${colores[tipo]} flex items-center gap-2`
    toast.innerHTML = `<i class="${iconos[tipo]}"></i> ${mensaje}`
    document.body.appendChild(toast)
    setTimeout(() => toast.remove(), 3000)
}

onMounted(() => {
    if (props.flash?.ok) mostrarToast(props.flash.ok, 'success')
    if (props.flash?.warn) mostrarToast(props.flash.warn, 'warning')
})

const formatearNumero = (value) => {
    return Number(value || 0).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const hayDatosHoy = computed(() => props.diaActual?.tieneDatos === true)
const hayFechasPendientes = computed(() => (props.fechasPendientes?.length || 0) > 0)

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
    if (n.includes('efectivo')) return { bg: 'bg-blue-50', text: 'text-blue-600' }
    if (n.includes('qr')) return { bg: 'bg-purple-50', text: 'text-purple-600' }
    if (n.includes('tarjeta')) return { bg: 'bg-orange-50', text: 'text-orange-600' }
    if (n.includes('cliente')) return { bg: 'bg-pink-50', text: 'text-pink-600' }
    const fallback = [
        { bg: 'bg-blue-50', text: 'text-blue-600' },
        { bg: 'bg-purple-50', text: 'text-purple-600' },
        { bg: 'bg-orange-50', text: 'text-orange-600' },
        { bg: 'bg-pink-50', text: 'text-pink-600' },
    ]
    return fallback[index % 4]
}

const irANotificacion = (url) => {
    router.visit(url)
}
</script>

<template>
    <!-- 🔥 SI NO ES VENTAMOSTRADOR → DASHBOARD DE NOTIFICACIONES -->
    <div v-if="!esVentaMostrador" class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 p-4 sm:p-6">
        <div class="max-w-5xl mx-auto pt-6">
            
            <!-- HEADER -->
            <div class="mb-6 text-center sm:text-left">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center justify-center sm:justify-start gap-2">
                    <i class="fas fa-satellite-dish text-primary-600"></i> Centro de Tareas
                </h1>
                <p class="text-sm text-gray-500 mt-1">Panel de control rápido para tu jornada</p>
            </div>

            <!-- 🔥 TARJETAS DE NOTIFICACIÓN (DASHBOARD LLAMATIVO) -->
            <div v-if="notificacionesInicio.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <div 
                    v-for="notif in notificacionesInicio" 
                    :key="notif.tipo"
                    @click="irANotificacion(notif.url)"
                    class="relative overflow-hidden rounded-2xl border-2 cursor-pointer transition-all duration-300 hover:-translate-y-1 hover:shadow-xl group"
                    :class="[notif.color_bg, notif.color_border]"
                >
                    <!-- Icono de fondo gigante (decorativo) -->
                    <i :class="['fas', notif.icono, 'absolute -right-4 -bottom-4 text-8xl opacity-10 group-hover:scale-110 transition-transform duration-500']"></i>

                    <div class="p-5 relative z-10">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow-sm" :class="notif.color_icon">
                                <i :class="['fas', notif.icono, 'text-xl']"></i>
                            </div>
                            <span class="flex h-3 w-3 relative">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                            </span>
                        </div>
                        
                        <h3 class="text-sm font-semibold text-gray-600 mb-1">{{ notif.titulo }}</h3>
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl font-extrabold" :class="notif.color_text">{{ notif.cantidad }}</span>
                            <span class="text-sm font-medium text-gray-500">pendientes</span>
                        </div>
                        
                        <div class="mt-4 flex items-center text-xs font-bold gap-1 group-hover:gap-2 transition-all" :class="notif.color_text">
                            Revisar ahora <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ESTADO VACÍO -->
            <div v-else class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
                <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check-double text-emerald-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">¡Todo al día!</h3>
                <p class="text-sm text-gray-500 mt-1">No tienes tareas pendientes en este momento.</p>
            </div>

        </div>
    </div>

    <!-- 🔥 SI ES VENTAMOSTRADOR → DASHBOARD COMPLETO -->
    <div v-else class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-6 sm:px-6">
            <div class="max-w-3xl mx-auto">

                <!-- HEADER -->
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl flex items-center justify-center shadow-md shadow-primary-200">
                            <i class="fas fa-home text-white text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-gray-800">Inicio Oficial</h1>
                            <p class="text-[11px] sm:text-xs text-gray-500">
                                {{ gestion?.empresa_nombre || 'Sin empresa' }}
                                <span v-if="gestion?.sucursal_nombre"> · {{ gestion.sucursal_nombre }}</span>
                            </p>
                        </div>
                    </div>
                    <span
                        class="px-2.5 py-1 rounded-full text-[10px] sm:text-xs font-medium flex items-center gap-1.5"
                        :class="facturacionCompleta
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'bg-yellow-50 text-yellow-700'"
                    >
                        <i :class="facturacionCompleta ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'" class="text-[9px]"></i>
                        <span class="hidden sm:inline">{{ facturacionCompleta ? 'Facturación activa' : 'Facturación pendiente' }}</span>
                    </span>
                </div>

                <!-- 🔥 NOTIFICACIONES DE SISTEMA (TAMBIÉN PARA VENTAMOSTRADOR) -->
                <div v-if="notificacionesInicio.length > 0" class="mb-4 space-y-2">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider px-1">
                        <i class="fas fa-bell mr-1"></i> Tienes tareas pendientes
                    </p>
                    <div 
                        v-for="notif in notificacionesInicio" 
                        :key="notif.tipo"
                        @click="irANotificacion(notif.url)"
                        class="bg-white rounded-xl border border-gray-200 p-3 flex items-center justify-between cursor-pointer hover:shadow-md transition group"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" :class="notif.color_icon">
                                <i :class="['fas', notif.icono, 'text-sm']"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">{{ notif.titulo }}</p>
                                <p class="text-[11px] text-gray-500">{{ notif.cantidad }} pendiente(s) de revisar</p>
                            </div>
                        </div>
                        <div class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center group-hover:bg-primary-50 transition">
                            <i class="fas fa-chevron-right text-gray-400 group-hover:text-primary-500 text-xs transition"></i>
                        </div>
                    </div>
                </div>

                <!-- ALERTA: FECHAS PENDIENTES -->
                <div
                    v-if="hayFechasPendientes"
                    class="bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-200 rounded-2xl p-4 mb-4 shadow-sm"
                >
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-orange-600 text-base"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-orange-800">
                                Tenés {{ fechasPendientes.length }}
                                {{ fechasPendientes.length === 1 ? 'fecha pendiente' : 'fechas pendientes' }} por liquidar
                            </p>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <span
                                    v-for="fecha in fechasPendientes"
                                    :key="fecha.id"
                                    class="text-[10px] bg-white border border-orange-200 text-orange-700 px-2 py-1 rounded-lg font-medium flex items-center gap-1"
                                >
                                    <i class="fas fa-calendar-day text-[8px]"></i>
                                    {{ fecha.fecha }}
                                    <span class="text-orange-400">·</span>
                                    <span class="font-bold">Bs {{ formatearNumero(fecha.total_ventas) }}</span>
                                </span>
                            </div>
                            <Link
                                href="/gestion/impuestos/liquidacion-vendedor"
                                class="inline-flex items-center gap-1.5 mt-3 text-[11px] font-semibold text-orange-700 hover:text-orange-900"
                            >
                                Ir a liquidar
                                <i class="fas fa-arrow-right text-[9px]"></i>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- CARD PRINCIPAL: CUENTAS DEL DÍA -->
                <div class="bg-white rounded-2xl shadow-lg shadow-gray-200/50 overflow-hidden">

                    <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 px-5 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] sm:text-xs text-emerald-100 uppercase tracking-wide font-medium flex items-center gap-1.5">
                                    <i class="fas fa-calendar-day text-[9px]"></i>
                                    {{ diaActual?.fecha ? 'Fecha: ' + diaActual.fecha : 'Sin fecha pendiente' }}
                                </p>
                                <p class="text-2xl sm:text-3xl font-bold text-white mt-0.5">
                                    Bs {{ formatearNumero(diaActual?.totalVentas) }}
                                </p>
                                <p class="text-[10px] sm:text-xs text-emerald-100 mt-0.5">
                                    Total Ventas
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                                <i class="fas fa-chart-line text-white text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <template v-if="hayDatosHoy">
                        <div class="divide-y divide-gray-100">
                            <div
                                v-for="(concepto, index) in diaActual.conceptos"
                                :key="concepto.id"
                                class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                                        :class="[
                                            getColorConcepto(concepto.nombre, index).bg,
                                            getColorConcepto(concepto.nombre, index).text,
                                        ]"
                                    >
                                        <i class="fas text-xs" :class="getIconoConcepto(concepto.nombre, index)"></i>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700">{{ concepto.nombre }}</span>
                                </div>
                                <span class="text-sm font-semibold text-gray-800 tabular-nums">
                                    {{ formatearNumero(concepto.monto) }}
                                </span>
                            </div>
                        </div>

                        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-balance-scale text-gray-400 text-sm"></i>
                                    <span class="text-sm font-semibold text-gray-700">Diferencia</span>
                                </div>
                                <span
                                    class="text-base font-bold tabular-nums"
                                    :class="diaActual.diferencia >= 0 ? 'text-emerald-600' : 'text-red-600'"
                                >
                                    Bs {{ formatearNumero(diaActual.diferencia) }}
                                </span>
                            </div>
                        </div>

                        <div class="px-5 py-3 bg-white border-t border-gray-100">
                            <Link
                                href="/gestion/impuestos/liquidacion-vendedor"
                                class="w-full py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl text-sm font-semibold hover:from-emerald-600 hover:to-emerald-700 transition flex items-center justify-center gap-2 shadow-md shadow-emerald-200"
                            >
                                <i class="fas fa-file-invoice-dollar text-xs"></i>
                                Liquidar esta fecha
                            </Link>
                        </div>
                    </template>

                    <div v-else class="px-5 py-12 text-center">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-inbox text-gray-300 text-2xl"></i>
                        </div>
                        <p class="text-sm font-semibold text-gray-700">Sin ventas pendientes</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">No hay movimientos por liquidar</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.tabular-nums {
    font-variant-numeric: tabular-nums;
}
</style>