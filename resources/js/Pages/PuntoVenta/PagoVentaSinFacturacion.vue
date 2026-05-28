<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
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

// Estado para cliente (identificador)
const nitCliente = ref('')
const clienteSeleccionado = ref(null)
const clientesLista = ref([])
const mostrandoListaClientes = ref(false)
const buscandoCliente = ref(false)
const cargandoNitPredefinido = ref(false)

let searchTimeout = null

// Totales
const totalRegistrado = computed(() => {
    return Object.values(montosPorConcepto.value).reduce((acc, monto) => acc + (Number(monto) || 0), 0)
})

const pagoCorrecto = computed(() => {
    return totalRegistrado.value === Number(props.deuda)
})

// Cargar conceptos de pago
const cargarConceptos = async () => {
    loadingConceptos.value = true
    try {
        const response = await axios.get('/api/pago/conceptos-sin-facturacion')
        if (response.data.success) {
            conceptos.value = response.data.conceptos
            conceptos.value.forEach(concepto => {
                montosPorConcepto.value[concepto.id] = 0
            })
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

// Buscar clientes
const buscarClientes = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    
    searchTimeout = setTimeout(async () => {
        const termino = nitCliente.value.trim()
        
        if (termino.length < 2) {
            clientesLista.value = []
            mostrandoListaClientes.value = false
            return
        }
        
        buscandoCliente.value = true
        try {
            const response = await axios.get(`/api/pago/buscar-identificador?q=${encodeURIComponent(termino)}`)
            if (response.data.success) {
                clientesLista.value = response.data.clientes
                mostrandoListaClientes.value = clientesLista.value.length > 0
            } else {
                clientesLista.value = []
            }
        } catch (error) {
            console.error('Error buscando cliente:', error)
            clientesLista.value = []
        } finally {
            buscandoCliente.value = false
        }
    }, 300)
}

// Seleccionar cliente
const seleccionarCliente = (cliente) => {
    clienteSeleccionado.value = {
        IdIdentificador: cliente.IdIdentificador,
        CI_NIT: cliente.CI_NIT,
        Nombre: cliente.Nombre
    }
    nitCliente.value = `${cliente.CI_NIT} - ${cliente.Nombre}`
    mostrandoListaClientes.value = false
    clientesLista.value = []
}

const limpiarCliente = () => {
    clienteSeleccionado.value = null
    nitCliente.value = ''
    clientesLista.value = []
    mostrandoListaClientes.value = false
}

const handleClickOutside = (event) => {
    const container = document.querySelector('.cliente-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrandoListaClientes.value = false
    }
}

// Cargar NIT predefinido desde la venta
const cargarNitPredefinido = async () => {
    if (!props.ventaId) return
    
    cargandoNitPredefinido.value = true
    try {
        const nitEmpresa = props.clienteNit ? props.clienteNit.toString() : null
        const response = await axios.get(`/api/venta/${props.ventaId}/nit-predefinido`)
        
        console.log('=== NIT PREDEFINIDO ===', response.data)
        console.log('=== NIT DE LA EMPRESA ===', nitEmpresa)
        
        if (response.data.success && response.data.nit !== undefined && response.data.nit !== null) {
            const nitValue = response.data.nit.toString()
            
            // DETECTAR SI EL NIT DEL COMISIONISTA ES IGUAL AL NIT DE LA EMPRESA
            const esNitEmpresa = nitEmpresa && nitValue === nitEmpresa.toString()
            
            if (esNitEmpresa) {
                // Es el comisionista con NIT de empresa, mostrar "0 - SIN NIT"
                clienteSeleccionado.value = {
                    IdIdentificador: null,
                    CI_NIT: 0,
                    Nombre: 'SIN NIT'
                }
                nitCliente.value = '0 - SIN NIT'
                console.log('✅ NIT igual al de la empresa, mostrando SIN NIT')
            }
            // Si el NIT es diferente de la empresa y tiene nombre
            else if (response.data.nombre && response.data.nombre !== 'SIN NIT' && response.data.nombre !== '') {
                clienteSeleccionado.value = {
                    IdIdentificador: response.data.id_identificador,
                    CI_NIT: response.data.nit,
                    Nombre: response.data.nombre
                }
                nitCliente.value = `${response.data.nit} - ${response.data.nombre}`
                console.log('✅ Cliente cargado:', clienteSeleccionado.value)
            }
            // Si no hay nombre o es SIN NIT
            else {
                clienteSeleccionado.value = {
                    IdIdentificador: response.data.id_identificador || null,
                    CI_NIT: response.data.nit,
                    Nombre: 'SIN NOMBRE'
                }
                nitCliente.value = `${response.data.nit} - SIN NOMBRE`
                console.log('⚠️ Sin nombre:', nitCliente.value)
            }
        }
    } catch (error) {
        console.error('Error cargando NIT predefinido:', error)
    } finally {
        cargandoNitPredefinido.value = false
    }
}

// Procesar pago
const procesarPago = async () => {
    if (!pagoCorrecto) {
        toast?.error('Error', 'El monto total debe ser igual a la deuda')
        return
    }
    
    procesando.value = true
    
    const idCliente = clienteSeleccionado.value?.IdIdentificador || null
    console.log('=== ENVIANDO AL BACKEND ===')
    console.log('venta_id:', props.ventaId)
    console.log('id_identificador_cliente:', idCliente)
    
    try {
        const response = await axios.post('/api/pago/procesar-sin-facturacion', {
            venta_id: props.ventaId,
            montos: montosPorConcepto.value,
            tipo_venta: props.tipoVenta,
            id_identificador_cliente: idCliente
        })
        
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
        console.error('Error:', error)
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

onMounted(() => {
    cargarConceptos()
    cargarNitPredefinido()
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    if (searchTimeout) clearTimeout(searchTimeout)
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
                <div class="bg-gradient-to-r from-guindo-700 to-guindo-800 rounded-lg shadow-md p-3 mb-4 text-white">
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
                        
                        <!-- Sección: Cliente -->
                        <div class="border-b pb-2">
                            <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center text-[9px] text-blue-600">1</span>
                                NIT / CI del Cliente
                            </h3>
                            <div class="relative cliente-autocomplete">
                                <div class="flex gap-2">
                                    <div class="flex-1 relative">
                                        <div class="relative">
                                            <i class="fas fa-search absolute left-3 top-2 text-gray-400 text-[11px]"></i>
                                            <input 
                                                type="text"
                                                v-model="nitCliente"
                                                @input="buscarClientes"
                                                @focus="mostrandoListaClientes = true"
                                                placeholder="Buscar por CI/NIT o nombre..."
                                                class="w-full border border-gray-200 rounded-md pl-8 pr-3 py-1.5 text-xs focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                                :disabled="cargandoNitPredefinido"
                                            />
                                            <div v-if="cargandoNitPredefinido" class="absolute right-2 top-1.5">
                                                <i class="fas fa-spinner fa-spin text-gray-400 text-[10px]"></i>
                                            </div>
                                        </div>
                                        
                                        <div v-if="mostrandoListaClientes && clientesLista.length > 0" 
                                            class="absolute z-50 w-full max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg mt-0.5">
                                            <div 
                                                v-for="cliente in clientesLista" 
                                                :key="cliente.IdIdentificador"
                                                @click="seleccionarCliente(cliente)"
                                                class="px-2 py-1.5 hover:bg-primary-50 cursor-pointer border-b last:border-b-0 text-xs"
                                            >
                                                <span class="font-mono text-[10px] bg-gray-100 px-1.5 py-0.5 rounded-full">{{ cliente.CI_NIT }}</span>
                                                <span class="ml-2 text-gray-700">{{ cliente.Nombre }}</span>
                                            </div>
                                        </div>
                                        
                                        <div v-if="buscandoCliente" class="absolute right-2 top-1.5">
                                            <i class="fas fa-spinner fa-spin text-gray-400 text-[10px]"></i>
                                        </div>
                                    </div>
                                    <button 
                                        v-if="clienteSeleccionado"
                                        @click="limpiarCliente"
                                        class="px-2 py-1 bg-red-50 text-red-500 rounded-md text-[10px] hover:bg-red-100"
                                        type="button"
                                    >
                                        <i class="fas fa-times text-[9px]"></i>
                                    </button>
                                </div>
                                
                                <div v-if="clienteSeleccionado" class="mt-1.5 text-[11px] text-primary-600 bg-primary-50 rounded-md px-2 py-1">
                                    <i class="fas fa-check-circle text-[10px] mr-1"></i> 
                                    {{ clienteSeleccionado.Nombre }} ({{ clienteSeleccionado.CI_NIT }})
                                </div>
                                <div v-if="!clienteSeleccionado && nitCliente === '0 - SIN NIT'" class="mt-1.5 text-[11px] text-amber-600 bg-amber-50 rounded-md px-2 py-1">
                                    <i class="fas fa-info-circle text-[10px] mr-1"></i> 
                                    Cliente con NIT 0 (SIN NIT)
                                </div>
                            </div>
                        </div>

                        <!-- Sección: Métodos de Pago -->
                        <div>
                            <h3 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-amber-100 flex items-center justify-center text-[9px] text-amber-600">2</span>
                                Métodos de Pago
                            </h3>
                            
                            <div v-if="loadingConceptos" class="text-center py-6">
                                <i class="fas fa-spinner fa-spin text-xl text-primary-500"></i>
                                <p class="mt-1 text-[10px] text-gray-400">Cargando métodos de pago...</p>
                            </div>

                            <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                <div v-for="concepto in conceptos" :key="concepto.id" class="bg-gray-50 rounded-md p-2 border border-gray-100">
                                    <label class="block text-[10px] font-medium text-gray-600 mb-0.5">
                                        {{ concepto.nombre }}
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
                            </div>
                        </div>

                        <!-- Resumen de pagos -->
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
                            <div v-if="totalRegistrado > deuda" class="text-[10px] text-amber-500 mt-1">
                                <i class="fas fa-exchange-alt mr-0.5"></i>
                                Cambio: {{ (totalRegistrado - deuda).toFixed(2) }} Bs
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="px-4 py-3 bg-gray-50 border-t flex justify-end gap-2">
                        <button 
                            @click="volverALaVenta" 
                            class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-600 hover:bg-gray-100 transition"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="procesarPago" 
                            :disabled="!pagoCorrecto || procesando || loadingConceptos" 
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