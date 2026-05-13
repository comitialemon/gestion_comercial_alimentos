<script setup>
import { ref, computed, onMounted, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    venta: {
        type: Object,
        required: true
    },
    deuda: {
        type: Number,
        required: true
    },
    productos: {
        type: Array,
        required: true,
        default: () => []
    },
    ventaId: {
        type: Number,
        required: true
    },
    tipoVenta: {
        type: String,
        default: 'normal'
    },
    volverRuta: {
        type: String,
        default: '/venta-factura/nueva'
    }
})

// Catálogos
const tiposDocumento = ref([])
const monedas = ref([])
const metodosPago = ref([])
const loadingCatalogos = ref(true)

// Estado del formulario
const nitCliente = ref('')
const nombreCliente = ref('')
const emailCliente = ref('')
const tipoDocumentoId = ref('')
const monedaId = ref('')
const observaciones = ref('')
const descuento = ref(0)
const metodoSeleccionado = ref(null)
const montosPorCuenta = ref({})
const procesando = ref(false)

// Verificación de NIT
const nitVerificando = ref(false)
const errorNit = ref('')
const mensajeNit = ref('')
const tipoMensaje = ref('')

// Cálculos
const subtotal = computed(() => {
    return props.productos.reduce((acc, p) => acc + Number(p.total || 0), 0)
})

const totalConDescuento = computed(() => {
    const subtotalValor = subtotal.value
    const descuentoValor = Number(descuento.value) || 0
    if (descuentoValor > subtotalValor) return 0
    if (descuentoValor < 0) return subtotalValor
    return subtotalValor - descuentoValor
})

const totalRegistrado = computed(() => {
    return Object.values(montosPorCuenta.value).reduce((acc, monto) => acc + (Number(monto) || 0), 0)
})

const pagoCorrecto = computed(() => {
    return totalConDescuento.value === Number(props.deuda)
})

// Cargar catálogos
const cargarCatalogos = async () => {
    loadingCatalogos.value = true
    try {
        // Tipos de documento
        const tiposRes = await axios.get('/api/catalogos/tipos-documento')
        tiposDocumento.value = tiposRes.data?.data || tiposRes.data || []
        
        // Monedas
        const monedasRes = await axios.get('/api/catalogos/monedas')
        monedas.value = monedasRes.data || []
        
        // 🔥 Métodos de pago desde API compartida
        const metodosRes = await axios.get('/api/pago/metodos-con-facturacion')
        metodosPago.value = metodosRes.data || []
        
        if (tiposDocumento.value.length > 0 && !tipoDocumentoId.value) {
            tipoDocumentoId.value = tiposDocumento.value[0].id
        }
        if (monedas.value.length > 0 && !monedaId.value) {
            monedaId.value = monedas.value[0].id
        }
        
    } catch (error) {
        console.error('Error cargando catálogos:', error)
        monedas.value = [{ id: 1, sigla: 'BOB', descripcion: 'Boliviano' }]
        monedaId.value = 1
    } finally {
        loadingCatalogos.value = false
    }
}

const onMetodoChange = () => {
    montosPorCuenta.value = {}
    if (metodoSeleccionado.value?.cuentas) {
        metodoSeleccionado.value.cuentas.forEach(cuenta => {
            montosPorCuenta.value[cuenta.id] = 0
        })
    }
}

const verificarNit = async () => {
    if (!nitCliente.value || nitCliente.value.length < 6) {
        errorNit.value = 'Ingrese un NIT válido (mínimo 6 dígitos)'
        return
    }
    
    nitVerificando.value = true
    errorNit.value = ''
    mensajeNit.value = ''
    
    try {
        // 🔥 API compartida de verificación NIT
        const response = await axios.post('/api/pago/verificar-nit', { nit: nitCliente.value })
        
        if (response.data.success) {
            if (response.data.existe) {
                mensajeNit.value = '✅ NIT VÁLIDO - Existe en el padrón'
                tipoMensaje.value = 'success'
                if (response.data.nombre && !nombreCliente.value) {
                    nombreCliente.value = response.data.nombre
                }
            } else {
                mensajeNit.value = '⚠️ NIT NO ENCONTRADO - Se usará código de excepción'
                tipoMensaje.value = 'warning'
            }
        } else {
            mensajeNit.value = '❌ Error: ' + (response.data.error || 'No se pudo verificar')
            tipoMensaje.value = 'error'
        }
    } catch (error) {
        console.error('Error verificando NIT:', error)
        mensajeNit.value = '❌ Error de conexión'
        tipoMensaje.value = 'error'
    } finally {
        nitVerificando.value = false
        setTimeout(() => { mensajeNit.value = '' }, 5000)
    }
}

const validarDescuento = () => {
    const d = Number(descuento.value) || 0
    const s = subtotal.value
    if (d < 0) descuento.value = 0
    else if (d > s) descuento.value = s
}

const volverALaVenta = () => {
    if (confirm('¿Volver a la venta? Los datos de pago no se guardarán.')) {
        router.get(props.volverRuta)
    }
}

const procesarPago = async () => {
    if (!pagoCorrecto) {
        toast?.error('Error', 'El monto total debe ser igual a la deuda')
        return
    }
    
    if (totalRegistrado.value !== totalConDescuento.value) {
        toast?.error('Error', `Total registrado (${totalRegistrado.value.toFixed(2)} Bs) no coincide con total a pagar (${totalConDescuento.value.toFixed(2)} Bs)`)
        return
    }
    
    if (!nitCliente.value || !nombreCliente.value || !tipoDocumentoId.value || !monedaId.value || !metodoSeleccionado.value) {
        toast?.error('Error', 'Complete todos los campos obligatorios')
        return
    }
    
    procesando.value = true
    
    // 🔥 API compartida para procesar pago con facturación
    router.post('/api/pago/procesar-con-facturacion', {
        venta_id: props.ventaId,
        nit: nitCliente.value,
        nombre: nombreCliente.value,
        email: emailCliente.value,
        tipo_documento_id: tipoDocumentoId.value,
        moneda_id: monedaId.value,
        observacion: observaciones.value,
        descuento: descuento.value,
        codigo_metodo_pago: metodoSeleccionado.value.codigo,
        montos: montosPorCuenta.value,
        monto_total: totalConDescuento.value,
        tipo_venta: props.tipoVenta
    }, {
        onSuccess: () => {
            toast?.success('Venta completada', 'Redirigiendo...')
            setTimeout(() => {
                router.get(props.tipoVenta === 'tactil' ? '/venta-tactil/nueva' : '/venta-factura/crear')
            }, 1500)
        },
        onError: (errors) => {
            console.error('Error:', errors)
            toast?.error('Error', errors?.message || 'Error al procesar pago')
        },
        onFinish: () => { procesando.value = false }
    })
}

onMounted(() => {
    cargarCatalogos()
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-100 rounded-2xl mb-3">
                        <i class="fas fa-credit-card text-xl text-blue-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Procesar Pago</h1>
                    <p class="text-xs text-gray-500">Complete los datos para finalizar la venta</p>
                </div>

                <div class="mb-3">
                    <button @click="volverALaVenta" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                        <i class="fas fa-arrow-left text-xs"></i> {{ tipoVenta === 'tactil' ? 'Volver al Carrito' : 'Volver a la Venta' }}
                    </button>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-xs text-gray-500">Total a pagar</p>
                            <p class="text-2xl font-bold text-blue-600">{{ Number(deuda).toFixed(2) }} Bs</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500">Productos</p>
                            <p class="text-base font-semibold text-gray-700">{{ productos.length }} items</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-5 space-y-5">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center text-[10px] text-blue-600">1</span>
                                Datos del Cliente
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Tipo Documento *</label>
                                    <select v-model="tipoDocumentoId" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                        <option value="">Seleccione</option>
                                        <option v-for="t in tiposDocumento" :key="t.id" :value="t.id">{{ t.descripcion }}</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">NIT / CI *</label>
                                    <div class="flex gap-2">
                                        <input v-model="nitCliente" type="text" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="12345678" @blur="verificarNit" />
                                        <button @click="verificarNit" :disabled="nitVerificando" class="px-3 py-2 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">
                                            <i v-if="nitVerificando" class="fas fa-spinner fa-spin"></i>
                                            <i v-else class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    <p v-if="mensajeNit" class="text-[11px] mt-1" :class="{
                                        'text-green-600': tipoMensaje === 'success',
                                        'text-amber-600': tipoMensaje === 'warning',
                                        'text-red-600': tipoMensaje === 'error'
                                    }">{{ mensajeNit }}</p>
                                </div>
                                
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Nombre / Razón Social *</label>
                                    <input v-model="nombreCliente" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="Nombre completo" />
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Correo Electrónico</label>
                                    <input v-model="emailCliente" type="email" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="cliente@ejemplo.com" />
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Moneda *</label>
                                    <select v-model="monedaId" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                        <option v-for="m in monedas" :key="m.id" :value="m.id">
                                            {{ m.sigla }} - {{ m.descripcion }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center text-[10px] text-blue-600">2</span>
                                Método de Pago
                            </h3>
                            <select v-model="metodoSeleccionado" @change="onMetodoChange" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                <option :value="null">Seleccione un método</option>
                                <option v-for="m in metodosPago" :key="m.id" :value="m">
                                    {{ m.codigo }} - {{ m.descripcion }}
                                </option>
                            </select>
                            <p v-if="loadingCatalogos" class="text-xs text-gray-400 mt-1">Cargando...</p>
                            <p v-else-if="metodosPago.length === 0" class="text-xs text-amber-600 mt-1">⚠️ No hay métodos configurados</p>
                        </div>

                        <div v-if="metodoSeleccionado?.cuentas?.length">
                            <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-green-100 flex items-center justify-center text-[10px] text-green-600">3</span>
                                Registrar Montos
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="cuenta in metodoSeleccionado.cuentas" :key="cuenta.id" class="bg-gray-50 rounded-lg p-3 border">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ cuenta.nombre }}
                                        <span v-if="cuenta.descripcion" class="text-gray-400 text-[10px] block">{{ cuenta.descripcion }}</span>
                                    </label>
                                    <input v-model.number="montosPorCuenta[cuenta.id]" type="number" step="0.01" min="0" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mt-1" placeholder="0.00" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Descuento (Bs)</label>
                            <input v-model.number="descuento" type="number" step="0.01" min="0" class="w-32 border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="0.00" @input="validarDescuento" />
                        </div>

                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                                <div><p class="text-[10px] text-gray-500">Subtotal</p><p class="text-sm font-semibold">{{ subtotal.toFixed(2) }} Bs</p></div>
                                <div><p class="text-[10px] text-gray-500">Descuento</p><p class="text-sm font-semibold text-red-600">{{ Number(descuento).toFixed(2) }} Bs</p></div>
                                <div><p class="text-[10px] text-gray-500">Total a pagar</p><p class="text-base font-bold text-blue-600">{{ totalConDescuento.toFixed(2) }} Bs</p></div>
                                <div><p class="text-[10px] text-gray-500">Total registrado</p><p class="text-sm font-semibold" :class="totalRegistrado === totalConDescuento ? 'text-green-600' : 'text-red-600'">{{ totalRegistrado.toFixed(2) }} Bs</p></div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Observaciones</label>
                            <textarea v-model="observaciones" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="Notas adicionales..."></textarea>
                        </div>
                    </div>

                    <div class="px-5 py-3 bg-gray-50 border-t flex justify-end gap-2">
                        <button @click="volverALaVenta" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm hover:bg-gray-100">Cancelar</button>
                        <button @click="procesarPago" :disabled="!pagoCorrecto || !nitCliente || !nombreCliente || !tipoDocumentoId || !monedaId || !metodoSeleccionado || totalRegistrado !== totalConDescuento || procesando" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                            <i v-if="procesando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-check"></i>
                            {{ procesando ? 'Procesando...' : 'Finalizar Venta' }}
                        </button>
                    </div>
                </div>

                <div class="mt-4 text-center text-[10px] text-gray-400">
                    <i class="fas fa-lock"></i> Datos seguros · Facturación electrónica
                </div>
            </div>
        </div>
    </div>
</template>