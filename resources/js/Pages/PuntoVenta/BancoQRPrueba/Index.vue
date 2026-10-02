<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    credenciales: { type: Array, default: () => [] },
    ultimosQRs: { type: Array, default: () => [] },
})

const toast = inject('toast')

// ============================================================
// ESTADO
// ============================================================
const IdCredencial = ref(props.credenciales[0]?.IdCredencial || null)
const monto = ref(1.00)
const descripcion = ref('')
const generando = ref(false)
const qrActual = ref(null)
const estadoActual = ref(null)
const pollingInterval = ref(null)

// Lista de QRs
const ultimosQRs = ref(props.ultimosQRs || [])

// Lista de pagados
const fechaConsulta = ref(new Date().toISOString().split('T')[0])
const listaPagados = ref([])
const consultandoPagados = ref(false)

// Modal QR ampliado
const mostrarQRGrande = ref(false)

// ============================================================
// COMPUTED
// ============================================================
const credencialSeleccionada = computed(() => {
    return props.credenciales.find(c => c.IdCredencial === IdCredencial.value)
})

// ============================================================
// ACCIONES
// ============================================================

// Generar QR
const generarQR = async () => {
    if (!IdCredencial.value) {
        toast?.error('Error', 'Selecciona una credencial')
        return
    }
    if (!monto.value || monto.value <= 0) {
        toast?.error('Error', 'Ingresa un monto mayor a 0')
        return
    }

    generando.value = true
    qrActual.value = null
    estadoActual.value = null

    try {
        const response = await axios.post('/banco-qr-prueba/generar', {
            IdCredencial: IdCredencial.value,
            Monto: monto.value,
            Descripcion: descripcion.value,
        })

        if (response.data.success) {
            qrActual.value = response.data.qr
            estadoActual.value = 'ACTIVO'
            toast?.success('QR generado', 'Escanea el código para pagar')
            
            // Iniciar polling
            iniciarPolling(response.data.qr.QrId)
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al generar QR')
    } finally {
        generando.value = false
    }
}

// Consultar estado
const consultarEstado = async () => {
    if (!qrActual.value) return

    try {
        const response = await axios.get(`/banco-qr-prueba/estado/${qrActual.value.QrId}`)
        if (response.data.success) {
            estadoActual.value = response.data.estado

            if (response.data.estado === 'PAGADO') {
                toast?.success('¡PAGADO!', 'El QR ha sido pagado')
                detenerPolling()
                router.reload()
            }
        }
    } catch (error) {
        console.error('Error consultando estado:', error)
    }
}

// Iniciar polling cada 3 segundos
const iniciarPolling = (qrId) => {
    detenerPolling()
    pollingInterval.value = setInterval(() => {
        consultarEstado()
    }, 3000)
}

// Detener polling
const detenerPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
    }
}

// Anular QR
const anularQR = async (qrId) => {
    if (!confirm('¿Estás seguro de anular este QR?')) return

    try {
        const response = await axios.post(`/banco-qr-prueba/anular/${qrId}`)
        if (response.data.success) {
            toast?.success('QR anulado', response.data.message)
            estadoActual.value = 'ANULADO'
            detenerPolling()
            router.reload()
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al anular')
    }
}

// Listar pagados
const listarPagados = async () => {
    if (!IdCredencial.value) {
        toast?.error('Error', 'Selecciona una credencial')
        return
    }

    consultandoPagados.value = true
    try {
        const response = await axios.post('/banco-qr-prueba/listar-pagados', {
            fecha: fechaConsulta.value,
            IdCredencial: IdCredencial.value,
        })
        if (response.data.success) {
            listaPagados.value = response.data.pagos
            toast?.success('Consulta exitosa', `${response.data.total} pagos encontrados`)
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al listar')
    } finally {
        consultandoPagados.value = false
    }
}

// Limpiar QR actual
const limpiarQR = () => {
    detenerPolling()
    qrActual.value = null
    estadoActual.value = null
    descripcion.value = ''
}

// Colores de estado
const colorEstado = (estado) => {
    return {
        'ACTIVO': 'bg-blue-100 text-blue-700',
        'PAGADO': 'bg-green-100 text-green-700',
        'ANULADO': 'bg-gray-200 text-gray-600',
        'EXPIRADO': 'bg-red-100 text-red-700',
        'ERROR': 'bg-red-100 text-red-700',
    }[estado] || 'bg-gray-100 text-gray-600'
}

// Ciclo de vida
onMounted(() => {
    if (props.credenciales.length === 0) {
        toast?.warning('Sin credenciales', 'Configura una credencial primero')
    }
})

onUnmounted(() => {
    detenerPolling()
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4">
            <div class="max-w-6xl mx-auto">

                <!-- HEADER -->
                <div class="bg-gradient-to-r from-purple-700 to-purple-800 rounded-lg shadow-md p-4 mb-6 text-white">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-flask text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold">Pruebas API Banco QR</h1>
                            <p class="text-xs opacity-75">Prueba las funciones de generación, consulta y anulación de QR</p>
                        </div>
                    </div>
                </div>

                <!-- ALERTA SIN CREDENCIALES -->
                <div v-if="credenciales.length === 0" class="bg-amber-50 border border-amber-300 rounded-lg p-4 mb-6">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-exclamation-triangle text-amber-600 text-2xl"></i>
                        <div>
                            <h3 class="font-semibold text-amber-800">No hay credenciales activas</h3>
                            <p class="text-sm text-amber-700">
                                Primero configura una credencial en
                                <a href="/banco-credenciales" class="underline font-medium">Credenciales del Banco</a>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- ============================================ -->
                    <!-- COLUMNA IZQUIERDA: GENERAR QR -->
                    <!-- ============================================ -->
                    <div>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                                <i class="fas fa-qrcode text-purple-600"></i>
                                Generar QR
                            </h2>

                            <!-- Credencial -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Credencial</label>
                                <select
                                    v-model="IdCredencial"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-200"
                                    :disabled="generando"
                                >
                                    <option v-for="cred in credenciales" :key="cred.IdCredencial" :value="cred.IdCredencial">
                                        {{ cred.Alias }} ({{ cred.Ambiente }})
                                    </option>
                                </select>
                            </div>

                            <!-- Monto -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Monto (BOB)</label>
                                <input
                                    v-model.number="monto"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-lg font-bold focus:border-purple-500 focus:ring-1 focus:ring-purple-200"
                                    :disabled="generando"
                                />
                            </div>

                            <!-- Descripción -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción (opcional)</label>
                                <input
                                    v-model="descripcion"
                                    type="text"
                                    maxlength="200"
                                    placeholder="Ej: Venta de prueba"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-200"
                                    :disabled="generando"
                                />
                            </div>

                            <!-- Botones -->
                            <div class="flex gap-2">
                                <button
                                    @click="generarQR"
                                    :disabled="generando || credenciales.length === 0"
                                    class="flex-1 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md text-sm font-medium transition disabled:opacity-50 flex items-center justify-center gap-2"
                                >
                                    <i v-if="generando" class="fas fa-spinner fa-spin"></i>
                                    <i v-else class="fas fa-qrcode"></i>
                                    {{ generando ? 'Generando...' : 'Generar QR' }}
                                </button>
                                <button
                                    v-if="qrActual"
                                    @click="limpiarQR"
                                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-sm font-medium transition"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- QR ACTUAL -->
                        <div v-if="qrActual" class="bg-white rounded-lg shadow-md p-6 mt-4">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-sm font-bold text-gray-700">QR Generado</h3>
                                <span
                                    class="px-3 py-1 rounded-full text-xs font-bold"
                                    :class="colorEstado(estadoActual)"
                                >
                                    {{ estadoActual }}
                                </span>
                            </div>

                            <!-- Imagen QR -->
                            <div class="text-center bg-gray-50 rounded-lg p-4">
                                <img
                                    :src="'data:image/png;base64,' + qrActual.QrImage"
                                    alt="Código QR"
                                    class="w-64 h-64 mx-auto cursor-pointer hover:scale-105 transition"
                                    @click="mostrarQRGrande = true"
                                />
                                <p class="mt-2 text-xs text-gray-500">Clic en el QR para ampliar</p>
                            </div>

                            <!-- Datos -->
                            <div class="mt-4 space-y-2 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Monto:</span>
                                    <span class="font-bold text-lg text-purple-700">{{ qrActual.Monto.toFixed(2) }} Bs</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">QR ID:</span>
                                    <span class="font-mono text-[10px]">{{ qrActual.QrId }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Transaction ID:</span>
                                    <span class="font-mono text-[10px]">{{ qrActual.TransactionId }}</span>
                                </div>
                            </div>

                            <!-- Acciones -->
                            <div class="mt-4 flex gap-2">
                                <button
                                    @click="consultarEstado"
                                    class="flex-1 px-3 py-2 bg-blue-50 text-blue-600 rounded-md text-xs font-medium hover:bg-blue-100 transition"
                                >
                                    <i class="fas fa-sync mr-1"></i>
                                    Consultar estado
                                </button>
                                <button
                                    v-if="estadoActual === 'ACTIVO'"
                                    @click="anularQR(qrActual.QrId)"
                                    class="flex-1 px-3 py-2 bg-red-50 text-red-600 rounded-md text-xs font-medium hover:bg-red-100 transition"
                                >
                                    <i class="fas fa-times-circle mr-1"></i>
                                    Anular
                                </button>
                            </div>

                            <div v-if="estadoActual === 'ACTIVO'" class="mt-3 text-center text-xs text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-1"></i>
                                Esperando pago... (actualizando cada 3 segundos)
                            </div>

                            <div v-if="estadoActual === 'PAGADO'" class="mt-3 bg-green-50 rounded-md p-3 text-center">
                                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                                <p class="text-sm font-bold text-green-700 mt-1">¡Pago Exitoso!</p>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================ -->
                    <!-- COLUMNA DERECHA -->
                    <!-- ============================================ -->
                    <div>
                        <!-- LISTA DE PAGADOS -->
                        <div class="bg-white rounded-lg shadow-md p-6 mb-4">
                            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                                <i class="fas fa-list text-green-600"></i>
                                QRs Pagados por Fecha
                            </h2>

                            <div class="flex gap-2 mb-4">
                                <input
                                    v-model="fechaConsulta"
                                    type="date"
                                    class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm"
                                />
                                <button
                                    @click="listarPagados"
                                    :disabled="consultandoPagados"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-medium transition disabled:opacity-50"
                                >
                                    <i v-if="consultandoPagados" class="fas fa-spinner fa-spin mr-1"></i>
                                    <i v-else class="fas fa-search mr-1"></i>
                                    Consultar
                                </button>
                            </div>

                            <div v-if="listaPagados.length === 0" class="text-center py-8 text-gray-400 text-sm">
                                <i class="fas fa-inbox text-3xl mb-2"></i>
                                <p>No hay pagos para esta fecha</p>
                            </div>

                            <div v-else class="space-y-2 max-h-96 overflow-y-auto">
                                <div
                                    v-for="(pago, index) in listaPagados"
                                    :key="index"
                                    class="bg-gray-50 rounded-md p-3 text-xs"
                                >
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-mono text-[10px] text-gray-500">{{ pago.qrId }}</p>
                                            <p class="font-semibold">{{ pago.senderName }}</p>
                                        </div>
                                        <span class="font-bold text-green-600">{{ pago.amount }} Bs</span>
                                    </div>
                                    <p class="text-gray-500 text-[10px] mt-1">
                                        {{ pago.paymentDate }} {{ pago.paymentTime }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- ÚLTIMOS QRs -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                                <i class="fas fa-history text-gray-600"></i>
                                Últimos QRs Generados
                            </h2>

                            <div v-if="ultimosQRs.length === 0" class="text-center py-8 text-gray-400 text-sm">
                                <p>No hay QRs generados</p>
                            </div>

                            <div v-else class="space-y-2 max-h-96 overflow-y-auto">
                                <div
                                    v-for="qr in ultimosQRs"
                                    :key="qr.IdPagosQr"
                                    class="bg-gray-50 rounded-md p-3 text-xs border-l-4"
                                    :class="{
                                        'border-blue-400': qr.Estado === 'ACTIVO',
                                        'border-green-400': qr.Estado === 'PAGADO',
                                        'border-gray-400': qr.Estado === 'ANULADO',
                                    }"
                                >
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold">{{ qr.Monto.toFixed(2) }} Bs</p>
                                            <p class="text-gray-500 text-[10px]">{{ qr.Descripcion || 'Sin descripción' }}</p>
                                        </div>
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[9px] font-bold"
                                            :class="colorEstado(qr.Estado)"
                                        >
                                            {{ qr.Estado }}
                                        </span>
                                    </div>
                                    <p class="text-gray-400 text-[9px] mt-1">{{ qr.FechaCreacion }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL QR GRANDE -->
        <div
            v-if="mostrarQRGrande && qrActual"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
            @click="mostrarQRGrande = false"
        >
            <div class="bg-white rounded-2xl p-6 max-w-lg">
                <img
                    :src="'data:image/png;base64,' + qrActual.QrImage"
                    alt="QR Grande"
                    class="w-96 h-96"
                />
                <div class="text-center mt-4">
                    <p class="text-2xl font-bold text-purple-700">{{ qrActual.Monto.toFixed(2) }} Bs</p>
                    <p class="text-xs text-gray-500 mt-1">{{ qrActual.Descripcion }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Nada extra por ahora */
</style>