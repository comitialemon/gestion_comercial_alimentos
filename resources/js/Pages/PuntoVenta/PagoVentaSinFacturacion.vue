<script setup>
import { ref, computed, inject, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    venta: Object,
    deuda: Number,
    productos: Array,
    ventaId: Number,
    tipoVenta: {
        type: String,
        default: 'normal'
    },
    volverRuta: {
        type: String,
        default: '/venta-factura/nueva'
    },
    clienteNit: {
        type: [String, Number],
        default: null
    }
})

// Estado para conceptos de pago
const conceptos = ref([])
const montosPorConcepto = ref({})
const procesando = ref(false)
const loadingConceptos = ref(true)

// 🔥 Estado para el NIT del cliente COMPRADOR (quien recibe la factura)
const nitClienteComprador = ref('')
const clienteCompradorSeleccionado = ref(null)
const clientesLista = ref([])
const mostrandoListaComprador = ref(false)
const buscandoComprador = ref(false)

// 🔥 Estado para identificadores por método de pago
const identificadoresPorConcepto = ref({})
const busquedaIdentificadorPorConcepto = ref({})
const mostrandoListaIdentificador = ref({})
const buscandoIdentificador = ref({})
const resultadosIdentificador = ref({})

let searchTimeout = null
let searchTimeoutIdentificador = {}

// Totales
const totalRegistrado = computed(() => {
    return Object.values(montosPorConcepto.value).reduce((acc, monto) => acc + (Number(monto) || 0), 0)
})

const pagoCorrecto = computed(() => {
    return Math.abs(totalRegistrado.value - Number(props.deuda)) < 0.01
})

// 🔥 Verificar si un concepto requiere identificador Y tiene monto > 0
const conceptoRequeridoActivo = (concepto) => {
    const monto = montosPorConcepto.value[concepto.id] || 0
    const requiereId = Number(concepto.requiere_identificador) === 1
    return monto > 0 && requiereId
}

// 🔥 Verificar si ya tiene identificador seleccionado
const tieneIdentificadorSeleccionado = (conceptoId) => {
    return identificadoresPorConcepto.value[conceptoId] && 
           identificadoresPorConcepto.value[conceptoId].id
}

// 🔥 Obtener texto del identificador seleccionado
const getTextoIdentificador = (conceptoId) => {
    const id = identificadoresPorConcepto.value[conceptoId]
    if (!id) return ''
    return `${id.ci} - ${id.nombre}`
}

// 🔥 Obtener resultados para un concepto
const getResultadosIdentificador = (conceptoId) => {
    return resultadosIdentificador.value[conceptoId] || []
}

// Cargar conceptos de pago
const cargarConceptos = async () => {
    loadingConceptos.value = true
    try {
        const response = await axios.get('/api/pago/conceptos-sin-facturacion')
        console.log('=== CONCEPTOS RECIBIDOS ===', response.data)
        
        if (response.data.success) {
            conceptos.value = response.data.conceptos
            // Inicializar estructuras
            montosPorConcepto.value = {}
            identificadoresPorConcepto.value = {}
            busquedaIdentificadorPorConcepto.value = {}
            mostrandoListaIdentificador.value = {}
            buscandoIdentificador.value = {}
            resultadosIdentificador.value = {}
            
            conceptos.value.forEach(concepto => {
                montosPorConcepto.value[concepto.id] = 0
                identificadoresPorConcepto.value[concepto.id] = null
                busquedaIdentificadorPorConcepto.value[concepto.id] = ''
                mostrandoListaIdentificador.value[concepto.id] = false
                buscandoIdentificador.value[concepto.id] = false
                resultadosIdentificador.value[concepto.id] = []
            })
            console.log('Conceptos cargados:', conceptos.value)
        } else {
            toast?.error('Error', 'No se pudieron cargar los conceptos')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', 'Error cargando conceptos')
    } finally {
        loadingConceptos.value = false
    }
}

// 🔥 Buscar cliente comprador
const buscarComprador = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    
    searchTimeout = setTimeout(async () => {
        const termino = nitClienteComprador.value.trim()
        
        if (termino.length < 2) {
            clientesLista.value = []
            mostrandoListaComprador.value = false
            return
        }
        
        buscandoComprador.value = true
        try {
            const response = await axios.get(`/api/pago/buscar-identificador?q=${encodeURIComponent(termino)}`)
            if (response.data.success) {
                clientesLista.value = response.data.clientes
                mostrandoListaComprador.value = clientesLista.value.length > 0
            }
        } catch (error) {
            console.error('Error:', error)
        } finally {
            buscandoComprador.value = false
        }
    }, 300)
}

// 🔥 Buscar identificador para un concepto específico
const buscarIdentificadorParaConcepto = (conceptoId, termino) => {
    if (searchTimeoutIdentificador[conceptoId]) clearTimeout(searchTimeoutIdentificador[conceptoId])
    
    searchTimeoutIdentificador[conceptoId] = setTimeout(async () => {
        if (termino.length < 2) {
            mostrandoListaIdentificador.value[conceptoId] = false
            return
        }
        
        buscandoIdentificador.value[conceptoId] = true
        try {
            const response = await axios.get(`/api/pago/buscar-identificador?q=${encodeURIComponent(termino)}`)
            if (response.data.success) {
                resultadosIdentificador.value[conceptoId] = response.data.clientes
                mostrandoListaIdentificador.value[conceptoId] = response.data.clientes.length > 0
            }
        } catch (error) {
            console.error('Error:', error)
        } finally {
            buscandoIdentificador.value[conceptoId] = false
        }
    }, 300)
}

// 🔥 Seleccionar identificador para un concepto
const seleccionarIdentificadorParaConcepto = (conceptoId, cliente) => {
    identificadoresPorConcepto.value[conceptoId] = {
        id: cliente.IdIdentificador,
        ci: cliente.CI_NIT,
        nombre: cliente.Nombre
    }
    busquedaIdentificadorPorConcepto.value[conceptoId] = `${cliente.CI_NIT} - ${cliente.Nombre}`
    mostrandoListaIdentificador.value[conceptoId] = false
    resultadosIdentificador.value[conceptoId] = []
}

// 🔥 Limpiar identificador
const limpiarIdentificadorParaConcepto = (conceptoId) => {
    identificadoresPorConcepto.value[conceptoId] = null
    busquedaIdentificadorPorConcepto.value[conceptoId] = ''
    mostrandoListaIdentificador.value[conceptoId] = false
    resultadosIdentificador.value[conceptoId] = []
}

// Seleccionar comprador
const seleccionarComprador = (cliente) => {
    clienteCompradorSeleccionado.value = {
        id: cliente.IdIdentificador,
        ci: cliente.CI_NIT,
        nombre: cliente.Nombre
    }
    nitClienteComprador.value = `${cliente.CI_NIT} - ${cliente.Nombre}`
    mostrandoListaComprador.value = false
    clientesLista.value = []
}

const limpiarComprador = () => {
    clienteCompradorSeleccionado.value = null
    nitClienteComprador.value = ''
    clientesLista.value = []
    mostrandoListaComprador.value = false
}

const handleClickOutside = (event) => {
    const containerComprador = document.querySelector('.comprador-autocomplete')
    if (containerComprador && !containerComprador.contains(event.target)) {
        mostrandoListaComprador.value = false
    }
    
    conceptos.value.forEach(concepto => {
        const container = document.querySelector(`.identificador-autocomplete-${concepto.id}`)
        if (container && !container.contains(event.target)) {
            mostrandoListaIdentificador.value[concepto.id] = false
        }
    })
}

// Procesar pago
const procesarPago = async () => {
    if (!pagoCorrecto) {
        toast?.error('Error', 'El monto total debe ser igual a la deuda')
        return
    }
    
    // Validar cliente comprador
    if (!clienteCompradorSeleccionado.value) {
        toast?.error('Error', 'Debe seleccionar el NIT/CI del cliente que realiza la compra')
        return
    }
    
    // Validar que haya al menos un monto > 0
    const tieneAlgunMonto = Object.values(montosPorConcepto.value).some(m => parseFloat(m) > 0)
    if (!tieneAlgunMonto) {
        toast?.error('Error', 'Debe ingresar al menos un monto de pago')
        return
    }
    
    // 🔥 Validar conceptos que requieren identificador
    for (const concepto of conceptos.value) {
        const monto = parseFloat(montosPorConcepto.value[concepto.id] || 0)
        const requiereId = Number(concepto.requiere_identificador) === 1
        
        if (monto > 0 && requiereId && !tieneIdentificadorSeleccionado(concepto.id)) {
            toast?.error('Error', `El método de pago "${concepto.nombre}" requiere seleccionar un cliente`)
            return
        }
    }
    
    procesando.value = true
    
    // 🔥 Construir datos a enviar
    const montosFiltrados = {}
    const identificadoresPorConceptoEnvio = {}
    
    for (const concepto of conceptos.value) {
        const monto = parseFloat(montosPorConcepto.value[concepto.id] || 0)
        if (monto > 0) {
            montosFiltrados[concepto.id] = monto
            
            // Si el concepto tiene identificador seleccionado, enviarlo
            if (tieneIdentificadorSeleccionado(concepto.id)) {
                identificadoresPorConceptoEnvio[concepto.id] = identificadoresPorConcepto.value[concepto.id].id
            }
        }
    }
    
    const datosEnvio = {
        venta_id: props.ventaId,
        montos: montosFiltrados,
        tipo_venta: props.tipoVenta,
        id_identificador_cliente: clienteCompradorSeleccionado.value.id,
        identificadores_por_concepto: identificadoresPorConceptoEnvio
    }
    
    console.log('=== ENVIANDO AL BACKEND ===')
    console.log('Datos:', datosEnvio)
    
    try {
        const response = await axios.post('/api/pago/procesar-sin-facturacion', datosEnvio)
        
        if (response.data.success) {
            if (response.data.pdf_url) {
                window.open(response.data.pdf_url, '_blank')
            }
            toast?.success('Venta completada', 'Pago registrado correctamente')
            setTimeout(() => {
                router.get(props.tipoVenta === 'tactil' ? '/venta-tactil/nueva' : '/venta-factura/crear')
            }, 1500)
        } else {
            toast?.error('Error', response.data.message || 'Error al procesar pago')
        }
    } catch (error) {
        console.error('Error detallado:', error.response?.data)
        toast?.error('Error', error.response?.data?.message || 'No se pudo procesar el pago')
    } finally {
        procesando.value = false
    }
}

const volverALaVenta = () => {
    if (confirm('¿Volver a la venta? Los datos de pago no se guardarán.')) {
        router.get(props.volverRuta)
    }
}

// Cargar NIT predefinido
const cargarNitPredefinido = async () => {
    if (!props.ventaId) return
    
    try {
        const response = await axios.get(`/api/venta/${props.ventaId}/nit-predefinido`)
        if (response.data.success && response.data.nit && response.data.nit != 0) {
            clienteCompradorSeleccionado.value = {
                id: response.data.id_identificador,
                ci: response.data.nit,
                nombre: response.data.nombre || 'SIN NOMBRE'
            }
            nitClienteComprador.value = `${response.data.nit} - ${clienteCompradorSeleccionado.value.nombre}`
        }
    } catch (error) {
        console.error('Error cargando NIT predefinido:', error)
    }
}

onMounted(() => {
    cargarConceptos()
    cargarNitPredefinido()
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    if (searchTimeout) clearTimeout(searchTimeout)
    Object.values(searchTimeoutIdentificador).forEach(t => clearTimeout(t))
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-4">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-4">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-primary-100 rounded-xl mb-2">
                        <i class="fas fa-cash-register text-xl text-primary-600"></i>
                    </div>
                    <h1 class="text-lg font-bold text-gray-900">Registrar Pago</h1>
                    <p class="text-[11px] text-gray-500">Venta sin facturación electrónica</p>
                </div>

                <!-- Botón Volver -->
                <div class="mb-3">
                    <button @click="volverALaVenta" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 rounded-md text-gray-600 text-xs hover:bg-gray-200">
                        <i class="fas fa-arrow-left text-[10px]"></i> {{ tipoVenta === 'tactil' ? 'Volver al Carrito' : 'Volver a la Venta' }}
                    </button>
                </div>

                <!-- Resumen de venta -->
                <div class="bg-gradient-to-r from-primary-700 to-primary-800 rounded-lg shadow-md p-3 mb-4 text-white">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-[10px] opacity-80">Total a pagar</p>
                            <p class="text-2xl font-bold">{{ Number(deuda).toFixed(2) }} Bs</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] opacity-80">Productos</p>
                            <p class="text-base font-semibold">{{ productos.length }} items</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario de pago -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 space-y-4">
                        
                        <!-- SECCIÓN 1: Cliente que COMPRA -->
                        <div class="border-b pb-3">
                            <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center text-[9px] text-blue-600">1</span>
                                NIT / CI del Cliente (Comprador)
                                <span class="text-red-500 text-[10px]">*</span>
                            </h3>
                            <div class="relative comprador-autocomplete">
                                <div class="flex gap-2">
                                    <div class="flex-1 relative">
                                        <div class="relative">
                                            <i class="fas fa-search absolute left-3 top-2 text-gray-400 text-[11px]"></i>
                                            <input 
                                                type="text"
                                                v-model="nitClienteComprador"
                                                @input="buscarComprador"
                                                @focus="mostrandoListaComprador = true"
                                                placeholder="Buscar por CI/NIT o nombre..."
                                                class="w-full border border-gray-200 rounded-md pl-8 pr-3 py-1.5 text-xs"
                                                :class="{ 'border-red-300 bg-red-50': !clienteCompradorSeleccionado && nitClienteComprador }"
                                            />
                                        </div>
                                        
                                        <div v-if="mostrandoListaComprador && clientesLista.length > 0" 
                                            class="absolute z-50 w-full max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg mt-0.5">
                                            <div 
                                                v-for="cliente in clientesLista" 
                                                :key="cliente.IdIdentificador"
                                                @click="seleccionarComprador(cliente)"
                                                class="px-2 py-1.5 hover:bg-primary-50 cursor-pointer border-b last:border-b-0 text-xs"
                                            >
                                                <span class="font-mono text-[10px] bg-gray-100 px-1.5 py-0.5 rounded-full">{{ cliente.CI_NIT }}</span>
                                                <span class="ml-2 text-gray-700">{{ cliente.Nombre }}</span>
                                            </div>
                                        </div>
                                        
                                        <div v-if="buscandoComprador" class="absolute right-2 top-1.5">
                                            <i class="fas fa-spinner fa-spin text-gray-400 text-[10px]"></i>
                                        </div>
                                    </div>
                                    <button 
                                        v-if="clienteCompradorSeleccionado"
                                        @click="limpiarComprador"
                                        class="px-2 py-1 bg-red-50 text-red-500 rounded-md text-[10px] hover:bg-red-100"
                                        type="button"
                                    >
                                        <i class="fas fa-times text-[9px]"></i>
                                    </button>
                                </div>
                                
                                <div v-if="clienteCompradorSeleccionado" class="mt-1.5 text-[11px] text-green-600 bg-green-50 rounded-md px-2 py-1">
                                    <i class="fas fa-check-circle text-[10px] mr-1"></i> 
                                    {{ clienteCompradorSeleccionado.nombre }} ({{ clienteCompradorSeleccionado.ci }})
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 2: Métodos de Pago -->
                        <div>
                            <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-secondary-100 flex items-center justify-center text-[9px] text-secondary-600">2</span>
                                Métodos de Pago
                            </h3>
                            
                            <div v-if="loadingConceptos" class="text-center py-6">
                                <i class="fas fa-spinner fa-spin text-xl text-primary-500"></i>
                                <p class="mt-1 text-[10px] text-gray-400">Cargando métodos de pago...</p>
                            </div>

                            <div v-else class="space-y-3">
                                <div v-for="concepto in conceptos" :key="concepto.id" 
                                    class="bg-gray-50 rounded-md p-2 border"
                                    :class="{ 'border-amber-300 bg-amber-50': conceptoRequeridoActivo(concepto) }">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 items-start md:items-center">
                                        <!-- Columna izquierda -->
                                        <div>
                                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">
                                                {{ concepto.nombre }}
                                                <span v-if="Number(concepto.requiere_identificador) === 1" class="text-[8px] text-amber-600 ml-1">(requiere ID)</span>
                                            </label>
                                            <div class="relative">
                                                <span class="absolute left-1.5 top-0.5 text-gray-400 text-[9px]">Bs</span>
                                                <input 
                                                    v-model.number="montosPorConcepto[concepto.id]" 
                                                    type="number" 
                                                    step="0.01" 
                                                    min="0" 
                                                    class="no-spinner w-full border border-gray-200 rounded-md pl-6 pr-2 py-1 text-xs font-mono focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                                    placeholder="0.00"
                                                />
                                            </div>
                                        </div>
                                        
                                        <!-- Columna derecha: Identificador -->
                                        <div v-if="conceptoRequeridoActivo(concepto)" 
                                            :class="`identificador-autocomplete-${concepto.id}`">
                                            <label class="block text-[10px] font-medium text-amber-600 mb-0.5">
                                                <i class="fas fa-id-card mr-1"></i>
                                                Cliente (Cuentas por Cobrar)
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <div class="flex gap-1">
                                                <div class="flex-1 relative">
                                                    <input 
                                                        type="text"
                                                        v-model="busquedaIdentificadorPorConcepto[concepto.id]"
                                                        @input="buscarIdentificadorParaConcepto(concepto.id, busquedaIdentificadorPorConcepto[concepto.id])"
                                                        @focus="mostrandoListaIdentificador[concepto.id] = true"
                                                        placeholder="Buscar cliente..."
                                                        class="w-full border border-amber-200 rounded-md px-2 py-1 text-[10px]"
                                                        :class="{ 'border-red-300 bg-red-50': !tieneIdentificadorSeleccionado(concepto.id) }"
                                                    />
                                                    <div v-if="mostrandoListaIdentificador[concepto.id] && getResultadosIdentificador(concepto.id).length > 0" 
                                                        class="absolute z-50 w-full max-h-32 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg mt-0.5">
                                                        <div 
                                                            v-for="cliente in getResultadosIdentificador(concepto.id)" 
                                                            :key="cliente.IdIdentificador"
                                                            @click="seleccionarIdentificadorParaConcepto(concepto.id, cliente)"
                                                            class="px-2 py-1 hover:bg-amber-50 cursor-pointer border-b last:border-b-0 text-[10px]"
                                                        >
                                                            <span class="font-mono bg-gray-100 px-1 py-0.5 rounded-full">{{ cliente.CI_NIT }}</span>
                                                            <span class="ml-2">{{ cliente.Nombre }}</span>
                                                        </div>
                                                    </div>
                                                    <div v-if="buscandoIdentificador[concepto.id]" class="absolute right-2 top-1">
                                                        <i class="fas fa-spinner fa-spin text-gray-400 text-[9px]"></i>
                                                    </div>
                                                </div>
                                                <button 
                                                    v-if="tieneIdentificadorSeleccionado(concepto.id)"
                                                    @click="limpiarIdentificadorParaConcepto(concepto.id)"
                                                    class="px-2 py-1 bg-red-50 text-red-500 rounded-md text-[9px] hover:bg-red-100"
                                                    type="button"
                                                >
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            
                                            <div v-if="tieneIdentificadorSeleccionado(concepto.id)" class="mt-0.5 text-[9px] text-green-600">
                                                <i class="fas fa-check-circle"></i> {{ identificadoresPorConcepto[concepto.id]?.nombre }}
                                            </div>
                                            <div v-else class="mt-0.5 text-[9px] text-red-500">
                                                <i class="fas fa-exclamation-triangle"></i> Requiere seleccionar cliente
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen -->
                        <div class="bg-gray-50 rounded-md p-3">
                            <div class="flex justify-between items-center">
                                <span class="text-[11px] text-gray-600">Total registrado:</span>
                                <span class="text-base font-bold" :class="pagoCorrecto ? 'text-primary-700' : 'text-red-600'">
                                    {{ totalRegistrado.toFixed(2) }} Bs
                                </span>
                            </div>
                            <div v-if="!pagoCorrecto && totalRegistrado > 0" class="text-[10px] text-red-500 mt-1">
                                <i class="fas fa-exclamation-circle mr-0.5"></i>
                                Faltan {{ (deuda - totalRegistrado).toFixed(2) }} Bs
                            </div>
                            <div v-if="totalRegistrado > deuda" class="text-[10px] text-secondary-500 mt-1">
                                <i class="fas fa-exchange-alt mr-0.5"></i>
                                Cambio: {{ (totalRegistrado - deuda).toFixed(2) }} Bs
                            </div>
                        </div>

                        <!-- Alerta comprador -->
                        <div v-if="!clienteCompradorSeleccionado" class="bg-red-50 rounded-md p-2 text-[11px] text-red-600 border border-red-200">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            ⚠️ Debes seleccionar el NIT/CI del cliente que realiza la compra.
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="px-4 py-3 bg-gray-50 border-t flex justify-end gap-2">
                        <button 
                            @click="volverALaVenta" 
                            class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-600 hover:bg-gray-100 transition"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="procesarPago" 
                            :disabled="!pagoCorrecto || procesando || loadingConceptos || !clienteCompradorSeleccionado" 
                            class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition disabled:opacity-50 flex items-center gap-1"
                        >
                            <i v-if="procesando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-check text-[10px]"></i>
                            {{ procesando ? 'Procesando...' : 'Completar Venta' }}
                        </button>
                    </div>
                </div>

                <div class="mt-3 text-center text-[9px] text-gray-400">
                    <i class="fas fa-lock"></i> Datos seguros
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-spinner {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>