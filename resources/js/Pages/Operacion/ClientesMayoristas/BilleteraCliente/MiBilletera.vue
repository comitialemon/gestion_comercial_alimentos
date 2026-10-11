<script setup>
import { computed, inject } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useBilletera } from '@/Composables/useBilletera'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()
const { consultarSaldo } = useBilletera()

const props = defineProps({
    billetera: {
        type: Object,
        default: null
    },
    movimientos: {
        type: Object,
        default: null
    },
})

const tieneBilletera = computed(() => props.billetera !== null)
const estaActiva = computed(() => props.billetera?.ActivoInactivo === 0)
const saldoFormateado = computed(() => {
    const s = parseFloat(props.billetera?.SaldoActual || 0)
    return s.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
})

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

const getTipoBadge = (tipo) => {
    const badges = {
        'ABONO': 'bg-green-100 text-green-800',
        'CARGO': 'bg-red-100 text-red-800',
        'AJUSTE_POSITIVO': 'bg-blue-100 text-blue-800',
        'AJUSTE_NEGATIVO': 'bg-orange-100 text-orange-800',
        'ANULACION': 'bg-purple-100 text-purple-800',
        'DEVOLUCION': 'bg-yellow-100 text-yellow-800',
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
        'DEVOLUCION': 'Devolución',
    }
    return labels[tipo] || tipo
}

const getTipoIcono = (tipo) => {
    const iconos = {
        'ABONO': 'fa-arrow-down',
        'CARGO': 'fa-shopping-cart',
        'AJUSTE_POSITIVO': 'fa-plus',
        'AJUSTE_NEGATIVO': 'fa-minus',
        'ANULACION': 'fa-undo',
        'DEVOLUCION': 'fa-undo-alt',
    }
    return iconos[tipo] || 'fa-circle'
}

const formatearMonto = (monto) => {
    const n = parseFloat(monto)
    const signo = n >= 0 ? '+' : ''
    return signo + n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const esPositivo = (monto) => parseFloat(monto) >= 0
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-10">
        <div class="max-w-4xl mx-auto px-3 py-4">

            <!-- ============================================================ -->
            <!-- SIN BILLETERA -->
            <!-- ============================================================ -->
            <div v-if="!tieneBilletera" class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-wallet text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-700">No tienes billetera</h3>
                <p class="text-sm text-gray-400 mt-1">
                    Contacta al encargado para que te cree una billetera.
                </p>
                <Link
                    href="/operacion/pedidos/clientes-mayoristas/pedidos-clientes"
                    class="inline-block mt-4 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm transition"
                >
                    <i class="fas fa-arrow-left mr-1"></i>
                    Volver a pedidos
                </Link>
            </div>

            <!-- ============================================================ -->
            <!-- CON BILLETERA -->
            <!-- ============================================================ -->
            <div v-else>
                <!-- HEADER TIPO AMAZON -->
                <div
                    class="rounded-2xl shadow-lg p-6 mb-4 text-white relative overflow-hidden"
                    :class="estaActiva
                        ? 'bg-gradient-to-br from-primary-600 to-primary-800'
                        : 'bg-gradient-to-br from-gray-600 to-gray-800'"
                >
                    <div class="relative z-10">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wider opacity-80 flex items-center gap-2">
                                    <i :class="estaActiva ? 'fas fa-wallet' : 'fas fa-lock'"></i>
                                    {{ estaActiva ? 'Saldo disponible' : 'Billetera inactiva (saldo congelado)' }}
                                </p>
                                <p class="text-4xl sm:text-5xl font-bold mt-2">
                                    Bs. {{ saldoFormateado }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] opacity-70 uppercase">Estado</p>
                                <p class="text-sm font-bold">
                                    <i :class="estaActiva ? 'fas fa-check-circle' : 'fas fa-lock'"></i>
                                    {{ estaActiva ? 'ACTIVA' : 'INACTIVA' }}
                                </p>
                            </div>
                        </div>

                        <div v-if="!estaActiva" class="mt-4 p-3 bg-white/10 rounded-lg text-xs">
                            <i class="fas fa-info-circle mr-1"></i>
                            Tu billetera está inactiva. Contacta al encargado para reactivarla.
                        </div>

                        <!-- Stats -->
                        <div class="grid grid-cols-2 gap-3 mt-4 pt-4 border-t border-white/20">
                            <div>
                                <p class="text-[10px] opacity-70 uppercase">Total cargado</p>
                                <p class="text-sm font-semibold">
                                    Bs. {{ parseFloat(billetera.TotalCargado || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] opacity-70 uppercase">Total consumido</p>
                                <p class="text-sm font-semibold">
                                    Bs. {{ parseFloat(billetera.TotalConsumido || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HISTORIAL -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-800 text-sm">
                                <i class="fas fa-history text-primary-500 mr-2"></i>
                                Historial de movimientos
                            </h3>
                            <p class="text-[10px] text-gray-400">
                                Todos los movimientos de tu billetera
                            </p>
                        </div>
                        <button
                            @click="consultarSaldo"
                            class="text-xs text-gray-500 hover:text-gray-700 transition"
                            title="Actualizar"
                        >
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>

                    <div v-if="movimientos?.data?.length > 0" class="divide-y divide-gray-100">
                        <div
                            v-for="mov in movimientos.data"
                            :key="mov.IdMovimiento"
                            class="px-4 py-3 hover:bg-gray-50 transition"
                        >
                            <div class="flex items-center gap-3">
                                <!-- Icono -->
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                    :class="esPositivo(mov.Monto) ? 'bg-green-100' : 'bg-red-100'"
                                >
                                    <i
                                        :class="getTipoIcono(mov.Tipo)"
                                        :class-text="esPositivo(mov.Monto) ? 'text-green-600' : 'text-red-600'"
                                        class="text-sm"
                                        :style="{ color: esPositivo(mov.Monto) ? '#16a34a' : '#dc2626' }"
                                    ></i>
                                </div>

                                <!-- Info -->
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
                                    <p class="text-xs text-gray-600 mt-0.5 truncate">
                                        {{ mov.Glosa }}
                                    </p>
                                    <p v-if="mov.pedido" class="text-[10px] text-gray-400 mt-0.5">
                                        <i class="fas fa-receipt mr-1"></i>
                                        Pedido #{{ mov.pedido.NumeroPedido || 'Nuevo' }}
                                    </p>
                                </div>

                                <!-- Monto -->
                                <div class="text-right flex-shrink-0">
                                    <p
                                        class="text-sm font-bold"
                                        :class="esPositivo(mov.Monto) ? 'text-green-600' : 'text-red-600'"
                                    >
                                        {{ formatearMonto(mov.Monto) }}
                                    </p>
                                    <p class="text-[10px] text-gray-400">
                                        Saldo: Bs. {{ parseFloat(mov.SaldoDespues || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sin movimientos -->
                    <div v-else class="p-12 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-inbox text-gray-400 text-xl"></i>
                        </div>
                        <p class="text-sm text-gray-500">No hay movimientos todavía</p>
                        <p class="text-xs text-gray-400 mt-1">
                            Cuando el encargado te cargue saldo o hagas un pedido, aparecerán aquí.
                        </p>
                    </div>

                    <!-- Paginación -->
                    <div v-if="movimientos?.links?.length > 3" class="px-4 py-3 border-t border-gray-100 flex justify-center">
                        <div class="flex gap-1 flex-wrap">
                            <Link
                                v-for="link in movimientos.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                class="px-2.5 py-1 rounded text-xs transition"
                                :class="{
                                    'bg-primary-600 text-white': link.active,
                                    'bg-gray-100 text-gray-700 hover:bg-gray-200': !link.active && link.url,
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
    </div>
</template>