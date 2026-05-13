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

// Temporizador para búsqueda
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

// Buscar clientes (con debounce)
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
    clienteSeleccionado.value = cliente
    nitCliente.value = `${cliente.CI_NIT} - ${cliente.Nombre}`
    mostrandoListaClientes.value = false
    clientesLista.value = []
}

// Limpiar selección de cliente
const limpiarCliente = () => {
    clienteSeleccionado.value = null
    nitCliente.value = ''
    clientesLista.value = []
    mostrandoListaClientes.value = false
}

// Cerrar lista al hacer clic fuera
const handleClickOutside = (event) => {
    const container = document.querySelector('.cliente-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrandoListaClientes.value = false
    }
}

// Procesar pago
const procesarPago = async () => {
    if (!pagoCorrecto) {
        toast?.error('Error', 'El monto total debe ser igual a la deuda')
        return
    }
    
    procesando.value = true
    
    try {
        const response = await axios.post('/api/pago/procesar-sin-facturacion', {
            venta_id: props.ventaId,
            montos: montosPorConcepto.value,
            tipo_venta: props.tipoVenta,
            id_identificador_cliente: clienteSeleccionado.value?.IdIdentificador || null
        })
        
        if (response.data.success) {
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
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    if (searchTimeout) clearTimeout(searchTimeout)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-4 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-5">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-100 rounded-2xl mb-2">
                        <i class="fas fa-cash-register text-2xl text-emerald-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Registrar Pago</h1>
                    <p class="text-xs text-gray-500">Venta sin facturación</p>
                </div>

                <!-- Botón Volver -->
                <div class="mb-3">
                    <button @click="volverALaVenta" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                        <i class="fas fa-arrow-left text-xs"></i> {{ tipoVenta === 'tactil' ? 'Volver al Carrito' : 'Volver a la Venta' }}
                    </button>
                </div>

                <!-- Resumen de venta -->
                <div class="bg-gradient-to-r from-emerald-500 to-teal-500 rounded-xl shadow-lg p-4 mb-5 text-white">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-xs opacity-90">Total a pagar</p>
                            <p class="text-3xl font-bold">{{ Number(deuda).toFixed(2) }} Bs</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs opacity-90">Productos</p>
                            <p class="text-xl font-semibold">{{ productos.length }} items</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario de pago -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="p-5 space-y-5">
                        
                        <!-- Sección: Cliente -->
                        <div class="border-b pb-3">
                            <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs text-blue-600">1</span>
                                Cliente (Opcional)
                            </h3>
                            <div class="relative cliente-autocomplete">
                                <div class="flex gap-2">
                                    <div class="flex-1 relative">
                                        <div class="relative">
                                            <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                                            <input 
                                                type="text"
                                                v-model="nitCliente"
                                                @input="buscarClientes"
                                                @focus="mostrandoListaClientes = true"
                                                placeholder="Buscar por CI/NIT o nombre del cliente..."
                                                class="w-full border border-gray-300 rounded-lg pl-10 pr-3 py-2 text-sm focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200"
                                            />
                                        </div>
                                        
                                        <!-- Lista de resultados -->
                                        <div v-if="mostrandoListaClientes && clientesLista.length > 0" 
                                            class="absolute z-50 w-full max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg mt-1">
                                            <div 
                                                v-for="cliente in clientesLista" 
                                                :key="cliente.IdIdentificador"
                                                @click="seleccionarCliente(cliente)"
                                                class="px-3 py-2 hover:bg-emerald-50 cursor-pointer border-b last:border-b-0 transition"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded-full">{{ cliente.CI_NIT }}</span>
                                                    <span class="text-sm text-gray-700">{{ cliente.Nombre }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Indicador de búsqueda -->
                                        <div v-if="buscandoCliente" class="absolute right-3 top-2">
                                            <i class="fas fa-spinner fa-spin text-gray-400"></i>
                                        </div>
                                    </div>
                                    <button 
                                        v-if="clienteSeleccionado"
                                        @click="limpiarCliente"
                                        class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition"
                                        type="button"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <!-- Mensaje de cliente seleccionado -->
                                <div v-if="clienteSeleccionado" class="mt-2 text-sm text-emerald-600 bg-emerald-50 rounded-lg px-3 py-2">
                                    <i class="fas fa-check-circle mr-1"></i> 
                                    Cliente: <span class="font-medium">{{ clienteSeleccionado.Nombre }}</span> (CI/NIT: {{ clienteSeleccionado.CI_NIT }})
                                </div>
                            </div>
                        </div>

                        <!-- Sección: Métodos de Pago en 4 columnas -->
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-xs text-emerald-600">2</span>
                                Métodos de Pago
                            </h3>
                            
                            <div v-if="loadingConceptos" class="text-center py-8">
                                <i class="fas fa-spinner fa-spin text-2xl text-emerald-600"></i>
                                <p class="mt-2 text-gray-500">Cargando métodos de pago...</p>
                            </div>

                            <!-- 🔥 Grid de 4 columnas para los métodos de pago -->
                            <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                <div v-for="concepto in conceptos" :key="concepto.id" class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ concepto.nombre }}
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-2 top-1.5 text-gray-500 text-xs">Bs</span>
                                        <input 
                                            v-model.number="montosPorConcepto[concepto.id]" 
                                            type="number" 
                                            step="0.01" 
                                            min="0" 
                                            class="w-full border border-gray-200 rounded-lg pl-6 pr-2 py-1.5 text-sm font-mono focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200"
                                            placeholder="0.00"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen de pagos -->
                        <div class="bg-gray-100 rounded-lg p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Total registrado:</span>
                                <span class="text-xl font-bold" :class="pagoCorrecto ? 'text-emerald-600' : 'text-red-600'">
                                    {{ totalRegistrado.toFixed(2) }} Bs
                                </span>
                            </div>
                            <div v-if="!pagoCorrecto && totalRegistrado > 0" class="text-xs text-red-500 mt-2">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                Faltan {{ (deuda - totalRegistrado).toFixed(2) }} Bs
                            </div>
                            <div v-if="totalRegistrado > deuda" class="text-xs text-amber-500 mt-2">
                                <i class="fas fa-exchange-alt mr-1"></i>
                                Cambio: {{ (totalRegistrado - deuda).toFixed(2) }} Bs
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="px-5 py-4 bg-gray-50 border-t flex justify-end gap-3">
                        <button 
                            @click="volverALaVenta" 
                            class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm hover:bg-gray-100 transition"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="procesarPago" 
                            :disabled="!pagoCorrecto || procesando || loadingConceptos" 
                            class="px-6 py-2 bg-gradient-to-r from-emerald-500 to-teal-500 text-white rounded-lg text-sm font-medium hover:shadow-lg transition disabled:opacity-50 flex items-center gap-2"
                        >
                            <i v-if="procesando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-check"></i>
                            {{ procesando ? 'Procesando...' : 'Completar Venta' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>