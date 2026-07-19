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

// 🔥 Estado para el NIT del cliente COMPRADOR
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

// 🔥 Estado para observación
const observacion = ref('')

// 🔥 Modal de confirmación para volver
const mostrarModalVolver = ref(false)

let searchTimeout = null
let searchTimeoutIdentificador = {}

// Computed: conceptos que NO requieren identificador
const conceptosSinIdentificador = computed(() => {
    return conceptos.value.filter(c => Number(c.requiere_identificador) !== 1)
})

// Computed: conceptos que SÍ requieren identificador
const conceptosConIdentificador = computed(() => {
    return conceptos.value.filter(c => Number(c.requiere_identificador) === 1)
})

// Totales
const totalRegistrado = computed(() => {
    return Object.values(montosPorConcepto.value).reduce((acc, monto) => acc + (Number(monto) || 0), 0)
})

const pagoCorrecto = computed(() => {
    return Math.abs(totalRegistrado.value - Number(props.deuda)) < 0.01
})

const conceptoRequeridoActivo = (concepto) => {
    const monto = montosPorConcepto.value[concepto.id] || 0
    const requiereId = Number(concepto.requiere_identificador) === 1
    return monto > 0 && requiereId
}

const tieneIdentificadorSeleccionado = (conceptoId) => {
    return identificadoresPorConcepto.value[conceptoId] && 
           identificadoresPorConcepto.value[conceptoId].id
}

const getResultadosIdentificador = (conceptoId) => {
    return resultadosIdentificador.value[conceptoId] || []
}

const todosIdentificadoresCompletos = computed(() => {
    for (const concepto of conceptosConIdentificador.value) {
        const monto = parseFloat(montosPorConcepto.value[concepto.id] || 0)
        if (monto > 0 && !tieneIdentificadorSeleccionado(concepto.id)) {
            return false
        }
    }
    return true
})

const botonDeshabilitado = computed(() => {
    return !pagoCorrecto.value || 
           procesando.value || 
           loadingConceptos.value || 
           !clienteCompradorSeleccionado.value ||
           !todosIdentificadoresCompletos.value
})

// Cargar conceptos de pago
const cargarConceptos = async () => {
    loadingConceptos.value = true
    try {
        const response = await axios.get('/api/pago/conceptos-sin-facturacion')
        
        if (response.data.success) {
            conceptos.value = response.data.conceptos
            montosPorConcepto.value = {}
            identificadoresPorConcepto.value = {}
            busquedaIdentificadorPorConcepto.value = {}
            mostrandoListaIdentificador.value = {}
            buscandoIdentificador.value = {}
            resultadosIdentificador.value = {}
            
            conceptos.value.forEach(concepto => {
                montosPorConcepto.value[concepto.id] = 0
                if (Number(concepto.requiere_identificador) === 1) {
                    identificadoresPorConcepto.value[concepto.id] = null
                    busquedaIdentificadorPorConcepto.value[concepto.id] = ''
                    mostrandoListaIdentificador.value[concepto.id] = false
                    buscandoIdentificador.value[concepto.id] = false
                    resultadosIdentificador.value[concepto.id] = []
                }
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

const limpiarIdentificadorParaConcepto = (conceptoId) => {
    identificadoresPorConcepto.value[conceptoId] = null
    busquedaIdentificadorPorConcepto.value[conceptoId] = ''
    mostrandoListaIdentificador.value[conceptoId] = false
    resultadosIdentificador.value[conceptoId] = []
}

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
    
    conceptosConIdentificador.value.forEach(concepto => {
        const container = document.querySelector(`.identificador-autocomplete-${concepto.id}`)
        if (container && !container.contains(event.target)) {
            mostrandoListaIdentificador.value[concepto.id] = false
        }
    })
}

// 🔥 Procesar pago
const procesarPago = async () => {
    if (!pagoCorrecto.value) {
        toast?.error('Error', 'El monto total debe ser igual a la deuda')
        return
    }
    
    if (!clienteCompradorSeleccionado.value) {
        toast?.error('Error', 'Debe seleccionar el NIT/CI del cliente que realiza la compra')
        return
    }
    
    const tieneAlgunMonto = Object.values(montosPorConcepto.value).some(m => parseFloat(m) > 0)
    if (!tieneAlgunMonto) {
        toast?.error('Error', 'Debe ingresar al menos un monto de pago')
        return
    }
    
    for (const concepto of conceptosConIdentificador.value) {
        const monto = parseFloat(montosPorConcepto.value[concepto.id] || 0)
        if (monto > 0 && !tieneIdentificadorSeleccionado(concepto.id)) {
            toast?.error('Error', `El método de pago "${concepto.nombre}" requiere seleccionar un cliente`)
            return
        }
    }
    
    procesando.value = true
    
    const montosFiltrados = {}
    const identificadoresPorConceptoEnvio = {}
    
    for (const concepto of conceptos.value) {
        const monto = parseFloat(montosPorConcepto.value[concepto.id] || 0)
        if (monto > 0) {
            montosFiltrados[concepto.id] = monto
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
        identificadores_por_concepto: identificadoresPorConceptoEnvio,
        observacion: observacion.value.trim() // 🔥 Enviar observación
    }
    
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
        console.error('Error:', error.response?.data)
        toast?.error('Error', error.response?.data?.message || 'No se pudo procesar el pago')
    } finally {
        procesando.value = false
    }
}

// 🔥 Modal de confirmación para volver
const abrirModalVolver = () => {
    mostrarModalVolver.value = true
}

const cerrarModalVolver = () => {
    mostrarModalVolver.value = false
}

const confirmarVolver = () => {
    mostrarModalVolver.value = false
    router.get(props.volverRuta)
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

const tieneMonto = (conceptoId) => {
    return parseFloat(montosPorConcepto.value[conceptoId] || 0) > 0
}

const faltanIdentificadores = computed(() => {
    let count = 0
    for (const concepto of conceptosConIdentificador.value) {
        const monto = parseFloat(montosPorConcepto.value[concepto.id] || 0)
        if (monto > 0 && !tieneIdentificadorSeleccionado(concepto.id)) {
            count++
        }
    }
    return count
})

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
                <!-- 🔥 HEADER MEJORADO -->
                <div class="bg-gradient-to-r from-primary-700 to-primary-800 rounded-lg shadow-md p-3 mb-4 text-white">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                        <!-- Izquierda: Título -->
                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-cash-register text-sm"></i>
                            </div>
                            <div>
                                <h1 class="text-sm font-bold">Registrar Pago</h1>
                                <p class="text-[9px] opacity-75">Venta sin facturación electrónica</p>
                            </div>
                        </div>

                        <!-- Centro: Total -->
                        <div class="flex items-center gap-2 bg-white/10 rounded-lg px-3 py-1 flex-shrink-0">
                            <span class="text-[10px] opacity-75 hidden xs:inline">Total:</span>
                            <span class="text-lg font-bold tabular-nums">{{ Number(deuda).toFixed(2) }}</span>
                            <span class="text-[10px] font-light">Bs</span>
                        </div>

                        <!-- Derecha: Botón Volver MEJORADO -->
                        <button 
                            @click="abrirModalVolver" 
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-md text-xs transition flex-shrink-0"
                        >
                            <i class="fas fa-arrow-left text-[10px]"></i>
                            <span class="hidden xs:inline">Volver a la venta</span>
                            <span class="xs:hidden">Volver</span>
                        </button>
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
                                        class="px-2 py-1 bg-red-50 text-red-500 rounded-md text-[10px] hover:bg-red-100 flex-shrink-0"
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
                                <span v-if="faltanIdentificadores > 0" class="text-[9px] text-red-500 ml-1">
                                    (faltan {{ faltanIdentificadores }} cliente{{ faltanIdentificadores > 1 ? 's' : '' }})
                                </span>
                            </h3>
                            
                            <div v-if="loadingConceptos" class="text-center py-6">
                                <i class="fas fa-spinner fa-spin text-xl text-primary-500"></i>
                                <p class="mt-1 text-[10px] text-gray-400">Cargando métodos de pago...</p>
                            </div>

                            <div v-else>
                                <!-- GRID: Conceptos SIN identificador -->
                                <div v-if="conceptosSinIdentificador.length > 0" class="mb-3">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                        <div 
                                            v-for="concepto in conceptosSinIdentificador" 
                                            :key="concepto.id"
                                            class="bg-gray-50 rounded-md p-2 border transition-all duration-200"
                                            :class="{ 'border-primary-300 bg-primary-50 shadow-sm': tieneMonto(concepto.id) }"
                                        >
                                            <label class="block text-[9px] font-medium text-gray-600 truncate" :title="concepto.nombre">
                                                {{ concepto.nombre }}
                                            </label>
                                            <div class="relative mt-0.5">
                                                <span class="absolute left-1.5 top-0.5 text-gray-400 text-[8px]">Bs</span>
                                                <input 
                                                    v-model.number="montosPorConcepto[concepto.id]" 
                                                    type="number" 
                                                    step="0.01" 
                                                    min="0" 
                                                    class="no-spinner w-full border border-gray-200 rounded-md pl-5 pr-1 py-1 text-[10px] font-mono focus:border-primary-400 focus:ring-1 focus:ring-primary-200 transition-all"
                                                    :class="{ 'border-primary-300 bg-white': tieneMonto(concepto.id) }"
                                                    placeholder="0.00"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- FILA APARTE: Conceptos CON identificador -->
                                <div v-if="conceptosConIdentificador.length > 0" class="space-y-3 mt-3 border-t pt-3 border-gray-200">
                                    <div class="flex items-center gap-2">
                                        <p class="text-[10px] font-medium text-amber-600 flex items-center gap-1">
                                            <i class="fas fa-id-card"></i>
                                            Métodos que requieren seleccionar cliente
                                        </p>
                                        <span v-if="faltanIdentificadores > 0" class="text-[9px] text-red-500 font-medium">
                                            ⚠️ {{ faltanIdentificadores }} pendiente{{ faltanIdentificadores > 1 ? 's' : '' }}
                                        </span>
                                        <span v-else class="text-[9px] text-green-600">
                                            ✅ Todos completos
                                        </span>
                                    </div>
                                    
                                    <div 
                                        v-for="concepto in conceptosConIdentificador" 
                                        :key="concepto.id"
                                        class="bg-amber-50 rounded-md p-2 border transition-all duration-200"
                                        :class="{ 
                                            'border-amber-400 bg-amber-100 shadow-sm': conceptoRequeridoActivo(concepto),
                                            'border-red-300 bg-red-50': conceptoRequeridoActivo(concepto) && !tieneIdentificadorSeleccionado(concepto.id)
                                        }"
                                    >
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-start md:items-center">
                                            <div class="flex items-center gap-2">
                                                <label class="block text-[10px] font-medium text-amber-700 whitespace-nowrap" :title="concepto.nombre">
                                                    {{ concepto.nombre }}
                                                    <span class="text-red-500 text-[8px] ml-0.5">*</span>
                                                </label>
                                                <div class="relative flex-1 md:max-w-[100px]">
                                                    <span class="absolute left-1.5 top-0.5 text-gray-400 text-[8px]">Bs</span>
                                                    <input 
                                                        v-model.number="montosPorConcepto[concepto.id]" 
                                                        type="number" 
                                                        step="0.01" 
                                                        min="0" 
                                                        class="no-spinner w-full border border-amber-200 rounded-md pl-5 pr-1 py-1 text-[10px] font-mono focus:border-amber-400 focus:ring-1 focus:ring-amber-200 transition-all"
                                                        :class="{ 'border-red-300 bg-red-50': conceptoRequeridoActivo(concepto) && !tieneIdentificadorSeleccionado(concepto.id) }"
                                                        placeholder="0.00"
                                                    />
                                                </div>
                                            </div>
                                            
                                            <div class="md:col-span-2 relative identificador-autocomplete-container">
                                                <div :class="`identificador-autocomplete-${concepto.id}`">
                                                    <div class="flex gap-1">
                                                        <div class="flex-1 relative">
                                                            <input 
                                                                type="text"
                                                                v-model="busquedaIdentificadorPorConcepto[concepto.id]"
                                                                @input="buscarIdentificadorParaConcepto(concepto.id, busquedaIdentificadorPorConcepto[concepto.id])"
                                                                @focus="mostrandoListaIdentificador[concepto.id] = true"
                                                                placeholder="Buscar cliente por CI/NIT o nombre..."
                                                                class="w-full border border-amber-200 rounded-md px-2 py-1 text-[10px] transition-all"
                                                                :class="{ 
                                                                    'border-red-300 bg-red-50': conceptoRequeridoActivo(concepto) && !tieneIdentificadorSeleccionado(concepto.id),
                                                                    'border-green-300 bg-green-50': tieneIdentificadorSeleccionado(concepto.id)
                                                                }"
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
                                                            class="px-2 py-1 bg-red-50 text-red-500 rounded-md text-[9px] hover:bg-red-100 whitespace-nowrap"
                                                            type="button"
                                                        >
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <div v-if="tieneIdentificadorSeleccionado(concepto.id)" class="mt-0.5 text-[9px] text-green-600">
                                                        <i class="fas fa-check-circle"></i> 
                                                        {{ identificadoresPorConcepto[concepto.id]?.nombre }} ({{ identificadoresPorConcepto[concepto.id]?.ci }})
                                                    </div>
                                                    <div v-else-if="conceptoRequeridoActivo(concepto)" class="mt-0.5 text-[9px] text-red-500 font-medium">
                                                        <i class="fas fa-exclamation-circle"></i> 
                                                        ⚠️ Requiere seleccionar cliente
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 🔥 OBSERVACIÓN (opcional) -->
                        <div class="border-t pt-3 mt-3 border-gray-200">
                            <div class="flex items-start gap-2">
                                <div class="flex-shrink-0 mt-0.5">
                                    <i class="fas fa-pen text-gray-400 text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-[10px] font-medium text-gray-600 mb-1">
                                        Observación <span class="text-gray-400 font-normal">(opcional)</span>
                                    </label>
                                    <textarea 
                                        v-model="observacion"
                                        rows="2"
                                        placeholder="Agregar una observación a esta venta..."
                                        class="w-full border border-gray-200 rounded-md px-3 py-2 text-xs focus:border-primary-400 focus:ring-1 focus:ring-primary-200 transition resize-none"
                                        maxlength="200"
                                    ></textarea>
                                    <div class="flex justify-between text-[8px] text-gray-400 mt-0.5">
                                        <span>Máximo 200 caracteres</span>
                                        <span>{{ observacion.length }}/200</span>
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

                        <!-- Alertas -->
                        <div v-if="!clienteCompradorSeleccionado" class="bg-red-50 rounded-md p-2 text-[11px] text-red-600 border border-red-200 flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Debes seleccionar el NIT/CI del cliente que realiza la compra.</span>
                        </div>

                        <div v-if="faltanIdentificadores > 0 && !loadingConceptos" class="bg-red-50 rounded-md p-2 text-[11px] text-red-600 border border-red-200 flex items-center gap-2">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>
                                ⚠️ Hay <strong>{{ faltanIdentificadores }}</strong> método{{ faltanIdentificadores > 1 ? 's' : '' }} de pago con monto que requiere{{ faltanIdentificadores > 1 ? 'n' : '' }} seleccionar un cliente.
                            </span>
                        </div>

                        <div v-if="pagoCorrecto && clienteCompradorSeleccionado && faltanIdentificadores === 0 && !loadingConceptos" class="bg-green-50 rounded-md p-2 text-[11px] text-green-600 border border-green-200 flex items-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            <span>✅ Todo listo para completar la venta.</span>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="px-4 py-3 bg-gray-50 border-t flex flex-col sm:flex-row justify-end gap-2">
                        <button 
                            @click="abrirModalVolver" 
                            class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-600 hover:bg-gray-100 transition order-2 sm:order-1"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="procesarPago" 
                            :disabled="botonDeshabilitado" 
                            class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5 order-1 sm:order-2"
                        >
                            <i v-if="procesando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-check text-[10px]"></i>
                            {{ procesando ? 'Procesando...' : 'Completar Venta' }}
                        </button>
                    </div>

                    <div v-if="botonDeshabilitado && !procesando && !loadingConceptos" class="px-4 pb-3 text-[9px] text-gray-400 text-center border-t pt-2 border-gray-100">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span v-if="!clienteCompradorSeleccionado">Selecciona el cliente comprador</span>
                        <span v-else-if="faltanIdentificadores > 0">Selecciona los clientes requeridos para los métodos de pago</span>
                        <span v-else-if="!pagoCorrecto">El monto total debe igualar la deuda</span>
                        <span v-else>Completa todos los campos requeridos</span>
                    </div>
                </div>

                <div class="mt-3 text-center text-[9px] text-gray-400">
                    <i class="fas fa-lock"></i> Datos seguros
                </div>
            </div>
        </div>

        <!-- 🔥 MODAL DE CONFIRMACIÓN PARA VOLVER -->
        <div v-if="mostrarModalVolver" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm transition-all">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden transform transition-all scale-100">
                <!-- Header del modal -->
                <div class="bg-gradient-to-r from-amber-500 to-amber-600 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-lg">¿Volver a la venta?</h3>
                            <p class="text-amber-100 text-xs">Los datos de pago no se guardarán</p>
                        </div>
                    </div>
                </div>
                
                <!-- Cuerpo del modal -->
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-600">
                        Si vuelves a la venta, <span class="font-semibold text-amber-600">perderás todo el progreso</span> del pago actual.
                    </p>
                    <div class="mt-3 bg-amber-50 rounded-lg p-3 border border-amber-200">
                        <div class="flex items-center gap-2 text-amber-700">
                            <i class="fas fa-info-circle text-sm"></i>
                            <span class="text-xs">Los montos ingresados no serán guardados</span>
                        </div>
                    </div>
                </div>
                
                <!-- Footer del modal -->
                <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">
                    <button 
                        @click="cerrarModalVolver"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition font-medium"
                    >
                        Seguir editando
                    </button>
                    <button 
                        @click="confirmarVolver"
                        class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium transition shadow-sm"
                    >
                        <i class="fas fa-arrow-left mr-1.5 text-xs"></i>
                        Sí, volver
                    </button>
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

.border-primary-300 {
    transition: all 0.2s ease;
}
.bg-primary-50 {
    transition: all 0.2s ease;
}

@media (max-width: 480px) {
    .xs\:inline {
        display: inline !important;
    }
    .xs\:hidden {
        display: none !important;
    }
}
</style>