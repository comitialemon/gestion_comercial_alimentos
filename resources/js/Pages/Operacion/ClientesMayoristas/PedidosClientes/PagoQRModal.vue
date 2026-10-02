<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import axios from 'axios'

const props = defineProps({
    pedidoId: { type: Number, required: true },
    qr: { type: Object, required: true },
})

const emit = defineEmits(['pago-exitoso', 'cancelar', 'expirado'])

const toast = inject('toast')

// ============================================================
// ESTADO
// ============================================================
const estado = ref('PENDIENTE') // PENDIENTE, PAGADO, EXPIRADO, ERROR
const segundosRestantes = ref(props.qr.ExpiraEnSegundos || 900)
const procesando = ref(false)
const error = ref(null)

// ✅ NUEVO: Modal de confirmación personalizado
const mostrarConfirmacion = ref(false)

let pollingInterval = null
let contadorInterval = null

// ============================================================
// COMPUTED
// ============================================================
const minutosRestantes = computed(() => {
    const min = Math.floor(segundosRestantes.value / 60)
    const seg = segundosRestantes.value % 60
    return `${String(min).padStart(2, '0')}:${String(seg).padStart(2, '0')}`
})

const porcentajeRestante = computed(() => {
    const total = props.qr.ExpiraEnSegundos || 900
    return Math.max(0, Math.min(100, (segundosRestantes.value / total) * 100))
})

const colorContador = computed(() => {
    const pct = porcentajeRestante.value
    if (pct > 50) return 'text-emerald-600'
    if (pct > 20) return 'text-amber-600'
    return 'text-red-600'
})

const colorBarra = computed(() => {
    const pct = porcentajeRestante.value
    if (pct > 50) return 'bg-emerald-500'
    if (pct > 20) return 'bg-amber-500'
    return 'bg-red-500'
})

// ============================================================
// MÉTODOS
// ============================================================
const iniciarPolling = () => {
    pollingInterval = setInterval(async () => {
        await consultarEstado()
    }, 3000)
}

const iniciarContador = () => {
    contadorInterval = setInterval(() => {
        if (segundosRestantes.value > 0) {
            segundosRestantes.value--
        } else {
            detenerTodo()
            estado.value = 'EXPIRADO'
            emit('expirado')
        }
    }, 1000)
}

const consultarEstado = async () => {
    try {
        const response = await axios.get(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${props.pedidoId}/estado-pago`)

        if (response.data.success) {
            const nuevoEstado = response.data.estado

            if (nuevoEstado === 'PAGADO') {
                estado.value = 'PAGADO'
                detenerTodo()
                toast?.success('¡Pago recibido!', 'El pedido se ha registrado correctamente.')
                setTimeout(() => emit('pago-exitoso', response.data), 2000)
            } else if (nuevoEstado === 'EXPIRADO' || nuevoEstado === 'ANULADO') {
                estado.value = 'EXPIRADO'
                detenerTodo()
                emit('expirado')
            } else {
                if (response.data.segundos_restantes !== undefined) {
                    segundosRestantes.value = response.data.segundos_restantes
                }
            }
        }
    } catch (e) {
        console.error('Error consultando estado:', e)
    }
}

// ✅ NUEVO: Abrir modal de confirmación
const abrirConfirmacion = () => {
    if (procesando.value) return
    mostrarConfirmacion.value = true
}

// ✅ NUEVO: Cerrar modal
const cerrarConfirmacion = () => {
    mostrarConfirmacion.value = false
}

// ✅ NUEVO: Confirmar cancelación desde el modal
const confirmarCancelar = async () => {
    mostrarConfirmacion.value = false
    procesando.value = true

    try {
        const response = await axios.post(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${props.pedidoId}/cancelar-qr`)

        if (response.data.success) {
            detenerTodo()
            toast?.success('QR cancelado', 'Puedes modificar el pedido y volver a intentar.')
            emit('cancelar')
        } else {
            toast?.error('Error', response.data.message || 'No se pudo cancelar')
        }
    } catch (e) {
        toast?.error('Error', e.response?.data?.message || 'Error al cancelar')
    } finally {
        procesando.value = false
    }
}

const detenerTodo = () => {
    if (pollingInterval) {
        clearInterval(pollingInterval)
        pollingInterval = null
    }
    if (contadorInterval) {
        clearInterval(contadorInterval)
        contadorInterval = null
    }
}

// ============================================================
// CICLO DE VIDA
// ============================================================
onMounted(() => {
    iniciarPolling()
    iniciarContador()
})

onUnmounted(() => {
    detenerTodo()
})
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-3">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col max-h-[95vh]">

            <!-- ============================================ -->
            <!-- HEADER -->
            <!-- ============================================ -->
            <div
                class="px-4 py-3 text-white flex-shrink-0 transition-colors"
                :class="{
                    'bg-primary-600': estado === 'PENDIENTE',
                    'bg-emerald-600': estado === 'PAGADO',
                    'bg-red-600': estado === 'EXPIRADO',
                    'bg-gray-600': estado === 'ERROR',
                }"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                        <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i
                                class="fas text-base"
                                :class="{
                                    'fa-qrcode': estado === 'PENDIENTE',
                                    'fa-check-circle': estado === 'PAGADO',
                                    'fa-clock': estado === 'EXPIRADO',
                                    'fa-exclamation-triangle': estado === 'ERROR',
                                }"
                            ></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold text-sm truncate">
                                <template v-if="estado === 'PENDIENTE'">Pago por QR</template>
                                <template v-else-if="estado === 'PAGADO'">¡Pago Exitoso!</template>
                                <template v-else-if="estado === 'EXPIRADO'">QR Expirado</template>
                                <template v-else>Error</template>
                            </h3>
                            <p class="text-[10px] opacity-90 truncate">
                                <template v-if="estado === 'PENDIENTE'">Escanea el código con la app del banco</template>
                                <template v-else-if="estado === 'PAGADO'">El pedido fue registrado correctamente</template>
                                <template v-else-if="estado === 'EXPIRADO'">El código ya no es válido</template>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- CONTENIDO -->
            <!-- ============================================ -->

            <!-- PENDIENTE -->
            <div v-if="estado === 'PENDIENTE'" class="p-4 overflow-y-auto">

                <!-- Monto -->
                <div class="text-center mb-3">
                    <p class="text-[9px] text-gray-400 uppercase font-semibold tracking-wide">Monto a pagar</p>
                    <p class="text-3xl font-bold text-primary-700 mt-0.5">
                        Bs. {{ Number(qr.Monto).toFixed(2) }}
                    </p>
                </div>

                <!-- QR -->
                <div class="bg-gray-50 rounded-xl p-3 flex justify-center mb-3">
                    <img
                        :src="'data:image/png;base64,' + qr.QrImage"
                        alt="Código QR"
                        class="w-64 h-64"
                    />
                </div>

                <!-- Contador -->
                <div class="bg-white rounded-lg border-2 p-2.5 mb-3"
                    :class="{
                        'border-emerald-200': porcentajeRestante > 50,
                        'border-amber-200': porcentajeRestante > 20 && porcentajeRestante <= 50,
                        'border-red-200': porcentajeRestante <= 20,
                    }"
                >
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-medium text-gray-600">
                            <i class="fas fa-hourglass-half mr-1 text-[9px]"></i>
                            Tiempo restante
                        </span>
                        <span class="text-base font-bold font-mono" :class="colorContador">
                            {{ minutosRestantes }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                        <div
                            class="h-full transition-all duration-1000 ease-linear"
                            :class="colorBarra"
                            :style="{ width: porcentajeRestante + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- Instrucciones -->
                <div class="bg-primary-50 rounded-lg p-2.5 mb-3 border border-primary-200">
                    <p class="text-[11px] text-primary-800 flex items-start gap-1.5">
                        <i class="fas fa-info-circle text-primary-500 mt-0.5 text-[10px] flex-shrink-0"></i>
                        <span>
                            Pide al cliente que <strong>escanee el código QR</strong> con la app del banco.
                            El pedido se confirmará automáticamente al recibir el pago.
                        </span>
                    </p>
                </div>

                <!-- Indicador de polling -->
                <div class="flex items-center justify-center gap-2 text-[10px] text-gray-500">
                    <i class="fas fa-circle-notch fa-spin text-primary-500"></i>
                    <span>Esperando pago... (actualizando cada 3 seg)</span>
                </div>
            </div>

            <!-- PAGADO -->
            <div v-else-if="estado === 'PAGADO'" class="p-6 text-center">
                <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-3 animate-bounce">
                    <i class="fas fa-check-circle text-5xl text-emerald-600"></i>
                </div>
                <h3 class="text-lg font-bold text-emerald-700 mb-1">¡Pago Exitoso!</h3>
                <p class="text-xs text-gray-600 mb-0.5">
                    Se recibió el pago de <strong>Bs. {{ Number(qr.Monto).toFixed(2) }}</strong>
                </p>
                <p class="text-[10px] text-gray-500">El pedido ha sido registrado correctamente.</p>
                <div class="mt-3 flex items-center justify-center gap-2 text-[10px] text-gray-400">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    <span>Redirigiendo...</span>
                </div>
            </div>

            <!-- EXPIRADO -->
            <div v-else-if="estado === 'EXPIRADO'" class="p-6 text-center">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-clock text-4xl text-red-600"></i>
                </div>
                <h3 class="text-base font-bold text-red-700 mb-1">El QR ha expirado</h3>
                <p class="text-xs text-gray-600">
                    Ya no es posible pagar este código. Puedes generar uno nuevo.
                </p>
            </div>

            <!-- ERROR -->
            <div v-else class="p-6 text-center">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-600"></i>
                </div>
                <h3 class="text-base font-bold text-red-700 mb-1">Error</h3>
                <p class="text-xs text-gray-600">{{ error || 'Ocurrió un error inesperado.' }}</p>
            </div>

            <!-- ============================================ -->
            <!-- FOOTER -->
            <!-- ============================================ -->
            <div v-if="estado === 'PENDIENTE'" class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2 flex-shrink-0">
                <button
                    @click="abrirConfirmacion"
                    :disabled="procesando"
                    class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-600 hover:bg-gray-100 transition font-medium disabled:opacity-50 flex items-center gap-1.5"
                >
                    <i v-if="procesando" class="fas fa-spinner fa-spin text-[10px]"></i>
                    <i v-else class="fas fa-times text-[10px]"></i>
                    {{ procesando ? 'Cancelando...' : 'Cancelar QR' }}
                </button>
            </div>

            <div v-else-if="estado === 'EXPIRADO'" class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2 flex-shrink-0">
                <button
                    @click="emit('cancelar')"
                    class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5"
                >
                    <i class="fas fa-arrow-left text-[10px]"></i>
                    Volver al pedido
                </button>
            </div>

        </div>

        <!-- ============================================ -->
        <!-- MODAL DE CONFIRMACIÓN PERSONALIZADO -->
        <!-- ============================================ -->
        <div
            v-if="mostrarConfirmacion"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-3"
            @click.self="cerrarConfirmacion"
        >
            <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full overflow-hidden animate-fade-in-up">

                <!-- Header -->
                <div class="bg-red-600 px-4 py-3 flex items-center gap-2.5">
                    <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-white text-base"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-sm text-white">Cancelar QR</h3>
                        <p class="text-[10px] text-white/80">Esta acción no se puede deshacer</p>
                    </div>
                </div>

                <!-- Body -->
                <div class="p-4">
                    <p class="text-xs text-gray-600 mb-1">
                        ¿Estás seguro de que deseas <strong class="text-gray-800">cancelar este QR</strong>?
                    </p>
                    <p class="text-[11px] text-gray-500">
                        El cliente ya no podrá pagarlo. Podrás modificar el pedido y generar uno nuevo.
                    </p>

                    <div class="mt-3 bg-red-50 border border-red-200 rounded-lg p-2.5 flex items-start gap-2">
                        <i class="fas fa-info-circle text-red-500 text-[10px] mt-0.5 flex-shrink-0"></i>
                        <p class="text-[10px] text-red-700">
                            El pedido seguirá guardado como <strong>borrador</strong>, no se perderá nada.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button
                        @click="cerrarConfirmacion"
                        :disabled="procesando"
                        class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition font-medium disabled:opacity-50"
                    >
                        No, mantener
                    </button>
                    <button
                        @click="confirmarCancelar"
                        :disabled="procesando"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 flex items-center gap-1.5"
                    >
                        <i v-if="procesando" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-trash-alt text-[10px]"></i>
                        {{ procesando ? 'Cancelando...' : 'Sí, cancelar QR' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
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

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.2s ease-out;
}

@media (min-width: 1024px) {
    input, button {
        font-size: 13px !important;
    }
}
</style>