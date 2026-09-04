<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, inject, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'
import ModalConfirmacionReprocesar from './components/ModalConfirmacionReprocesar.vue'
import ModalResultadoReprocesar from './components/ModalResultadoReprocesar.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    sucursales: { type: Array, default: () => [] },
    facturas: { type: Array, default: () => [] },
    sucursalSeleccionada: { type: Number, default: null },
    filtros: { type: Object, default: () => ({}) }
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const loading = ref(false)
const procesando = ref(false)

// Sucursal - Autocomplete
const sucursalId = ref(props.sucursalSeleccionada || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

// Facturas
const facturaInicialId = ref(props.filtros?.factura_inicial_id || '')
const facturaFinalId = ref(props.filtros?.factura_final_id || '')

// Autocomplete de facturas
const facturaInicialBusqueda = ref('')
const facturaFinalBusqueda = ref('')
const mostrarFacturaInicial = ref(false)
const mostrarFacturaFinal = ref(false)
const facturasDisponibles = ref(props.facturas || [])

// Refs para los contenedores
const sucursalContainer = ref(null)
const facturaInicialContainer = ref(null)
const facturaFinalContainer = ref(null)

// Modales
const mostrarModalConfirmacion = ref(false)
const mostrarModalResultado = ref(false)
const resultadoExito = ref(true)
const resultadoMensaje = ref('')
const resultadoDetalles = ref({ total: 0, facturas: [], errores: [] })

// ==================== COMPUTED ====================
const haySucursalSeleccionada = computed(() => {
    return sucursalId.value && sucursalId.value !== '' && Number(sucursalId.value) > 0
})

const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre?.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    return props.sucursales?.find(s => s.id === sucursalId.value)?.nombre || ''
})

const facturasInicialesFiltradas = computed(() => {
    if (!facturasDisponibles.value) return []
    if (!facturaInicialBusqueda.value || facturaInicialBusqueda.value.startsWith('N°')) {
        return facturasDisponibles.value
    }
    const termino = facturaInicialBusqueda.value.trim()
    return facturasDisponibles.value.filter(f => 
        f.numero && f.numero.toString().includes(termino)
    )
})

const facturasFinalesFiltradas = computed(() => {
    if (!facturasDisponibles.value) return []
    if (!facturaFinalBusqueda.value || facturaFinalBusqueda.value.startsWith('N°')) {
        return facturasDisponibles.value
    }
    const termino = facturaFinalBusqueda.value.trim()
    return facturasDisponibles.value.filter(f => 
        f.numero && f.numero.toString().includes(termino)
    )
})

const facturaInicialSeleccionada = computed(() => {
    if (!facturaInicialId.value) return null
    return facturasDisponibles.value.find(f => f.id == facturaInicialId.value)
})

const facturaFinalSeleccionada = computed(() => {
    if (!facturaFinalId.value) return null
    return facturasDisponibles.value.find(f => f.id == facturaFinalId.value)
})

const rangoValido = computed(() => {
    if (!facturaInicialId.value || !facturaFinalId.value) return true
    return parseInt(facturaInicialId.value) <= parseInt(facturaFinalId.value)
})

const rangoInfo = computed(() => {
    if (!facturaInicialSeleccionada.value || !facturaFinalSeleccionada.value) return ''
    const count = facturasDisponibles.value.filter(
        f => f.id >= parseInt(facturaInicialId.value) && f.id <= parseInt(facturaFinalId.value)
    ).length
    return `N° ${facturaInicialSeleccionada.value.numero} → N° ${facturaFinalSeleccionada.value.numero} (${count} u.)`
})

// ==================== FUNCIONES ====================
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    limpiarFacturas()
    cargarFacturas(sucursal.id)
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    facturasDisponibles.value = []
    limpiarFacturas()
}

const limpiarFacturas = () => {
    facturaInicialId.value = ''
    facturaFinalId.value = ''
    facturaInicialBusqueda.value = ''
    facturaFinalBusqueda.value = ''
}

const cargarFacturas = async (sucursalIdParam) => {
    if (!sucursalIdParam) return
    loading.value = true
    try {
        const response = await axios.get('/gestion/ventas-reprocesar-rango/facturas-por-sucursal', {
            params: { sucursal_id: sucursalIdParam }
        })
        facturasDisponibles.value = response.data || []
    } catch (error) {
        console.error(error)
        toast?.error('❌ Error', 'Error al cargar las facturas')
    } finally {
        loading.value = false
    }
}

const seleccionarFacturaInicial = (factura) => {
    facturaInicialId.value = factura.id
    facturaInicialBusqueda.value = `N° ${factura.numero}`
    mostrarFacturaInicial.value = false
}

const seleccionarFacturaFinal = (factura) => {
    facturaFinalId.value = factura.id
    facturaFinalBusqueda.value = `N° ${factura.numero}`
    mostrarFacturaFinal.value = false
}

const validarRango = () => {
    if (!sucursalId.value) return toast?.warning('⚠️', 'Selecciona una sucursal') && false
    if (!facturaInicialId.value || !facturaFinalId.value) return toast?.warning('⚠️', 'Completa el rango') && false
    if (parseInt(facturaInicialId.value) > parseInt(facturaFinalId.value)) {
        toast?.error('❌ Error', 'Rango inválido')
        return false
    }
    return true
}

const ejecutarProcesar = async () => {
    procesando.value = true
    mostrarModalConfirmacion.value = false
    try {
        const response = await axios.post('/gestion/ventas-reprocesar-rango/procesar', {
            sucursal_id: sucursalId.value,
            factura_inicial_id: facturaInicialId.value,
            factura_final_id: facturaFinalId.value,
        })
        resultadoExito.value = !!response.data.success
        resultadoMensaje.value = response.data.message
        resultadoDetalles.value = {
            total: response.data.total || 0,
            total_movimientos: response.data.total_movimientos || 0,
            facturas: response.data.facturas || [],
            productos: response.data.productos || [],
            errores: response.data.errores || []
        }
        resultadoExito.value ? toast?.success('✅', response.data.message) : toast?.error('❌', response.data.message)
        mostrarModalResultado.value = true
    } catch (error) {
        const msg = error.response?.data?.message || 'Error al reprocesar'
        resultadoExito.value = false
        resultadoMensaje.value = msg
        toast?.error('❌', msg)
        mostrarModalResultado.value = true
    } finally {
        procesando.value = false
    }
}

const volver = () => {
    router.get('/gestion/ventas-editar')
}

// Cerrar dropdowns
const handleClickOutside = (event) => {
    if (sucursalContainer.value && !sucursalContainer.value.contains(event.target)) {
        mostrarSucursales.value = false
    }
    if (facturaInicialContainer.value && !facturaInicialContainer.value.contains(event.target)) {
        mostrarFacturaInicial.value = false
    }
    if (facturaFinalContainer.value && !facturaFinalContainer.value.contains(event.target)) {
        mostrarFacturaFinal.value = false
    }
}

watch(sucursalId, (newVal) => {
    if (newVal) {
        cargarFacturas(newVal)
    }
})

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
    
    if (sucursalId.value) {
        cargarFacturas(sucursalId.value)
        const suc = props.sucursales?.find(s => s.id === sucursalId.value)
        if (suc) sucursalBusqueda.value = suc.nombre
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <button @click="volver" class="text-gray-400 hover:text-gray-600 transition p-1">
                        <i class="fas fa-arrow-left text-base"></i>
                    </button>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Reprocesar Rango de Facturas</h1>
                        <p class="text-xs text-gray-500">Ajuste selectivo de stock e inventario por lote</p>
                    </div>
                </div>

                <!-- ==================== TARJETA PRINCIPAL ==================== -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    
                    <!-- ==================== CUERPO ==================== -->
                    <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
                        
                        <!-- Sucursal -->
                        <div ref="sucursalContainer" class="relative">
                            <label class="block text-[10px] font-semibold text-gray-600 mb-0.5">
                                <i class="fas fa-store text-primary-500 mr-1 text-[10px]"></i>
                                Sucursal <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="sucursalBusqueda"
                                    @focus="mostrarSucursales = true"
                                    class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-sm pr-7 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="Buscar sucursal..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="sucursalBusqueda" 
                                    @click="limpiarSucursal" 
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                            
                            <!-- Badge de selección -->
                            <span v-if="sucursalId" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-primary-50 text-primary-700 mt-0.5">
                                <i class="fas fa-check-circle text-[8px]"></i> {{ sucursalNombre }}
                            </span>
                            <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] text-gray-400 mt-0.5">
                                <i class="fas fa-store text-[8px]"></i> Ninguna seleccionada
                            </span>
                            
                            <!-- Dropdown Sucursal -->
                            <div 
                                v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-y-auto text-sm w-full"
                            >
                                <div 
                                    v-for="sucursal in sucursalesDisponibles" 
                                    :key="sucursal.id"
                                    @click="seleccionarSucursal(sucursal)"
                                    class="px-3 py-2 cursor-pointer hover:bg-primary-50 flex justify-between items-center border-b border-gray-100 last:border-0"
                                    :class="sucursalId == sucursal.id ? 'bg-primary-50 text-primary-700' : ''"
                                >
                                    <span class="text-sm">{{ sucursal.nombre }}</span>
                                    <span v-if="sucursal.numero" class="text-[10px] text-gray-400">N° {{ sucursal.numero }}</span>
                                </div>
                            </div>
                            
                            <!-- Sin resultados -->
                            <div 
                                v-if="mostrarSucursales && sucursalesDisponibles.length === 0 && sucursalBusqueda" 
                                class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg p-3 text-sm text-gray-500 w-full text-center"
                            >
                                <i class="fas fa-search mr-1"></i> No se encontraron sucursales
                            </div>
                        </div>

                        <!-- Facturas -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            
                            <!-- Factura Inicial -->
                            <div ref="facturaInicialContainer" class="relative">
                                <label class="block text-[10px] font-semibold text-gray-600 mb-0.5">
                                    <i class="fas fa-file-invoice text-primary-500 mr-1 text-[10px]"></i>
                                    Desde Factura <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input 
                                        type="text"
                                        v-model="facturaInicialBusqueda"
                                        @focus="mostrarFacturaInicial = true"
                                        class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-sm pr-7 focus:ring-primary-500 focus:border-primary-500 outline-none disabled:bg-gray-50 disabled:text-gray-400"
                                        placeholder="Escribe N° de factura..."
                                        :disabled="!sucursalId || loading"
                                        autocomplete="off"
                                    />
                                    <button 
                                        v-if="facturaInicialBusqueda" 
                                        @click="facturaInicialId = ''; facturaInicialBusqueda = ''" 
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i class="fas fa-times text-[10px]"></i>
                                    </button>
                                </div>
                                
                                <!-- Badge de selección -->
                                <span v-if="facturaInicialSeleccionada" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-primary-50 text-primary-700 mt-0.5">
                                    <i class="fas fa-check-circle text-[8px]"></i> N° {{ facturaInicialSeleccionada.numero }}
                                </span>
                                
                                <!-- Dropdown Factura Inicial -->
                                <div 
                                    v-if="mostrarFacturaInicial && facturasInicialesFiltradas.length > 0" 
                                    class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-y-auto text-sm w-full"
                                >
                                    <div 
                                        v-for="factura in facturasInicialesFiltradas" 
                                        :key="factura.id"
                                        @click="seleccionarFacturaInicial(factura)"
                                        class="px-3 py-2 cursor-pointer hover:bg-primary-50 flex justify-between items-center border-b border-gray-100 last:border-0"
                                        :class="facturaInicialId == factura.id ? 'bg-primary-50 text-primary-700' : ''"
                                    >
                                        <span class="text-sm font-medium">N° {{ factura.numero }}</span>
                                        <span class="text-[10px] text-gray-400">ID: {{ factura.id }}</span>
                                    </div>
                                </div>
                                
                                <!-- Sin resultados -->
                                <div 
                                    v-if="mostrarFacturaInicial && facturasInicialesFiltradas.length === 0 && sucursalId" 
                                    class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg p-3 text-sm text-gray-500 w-full text-center"
                                >
                                    <i class="fas fa-info-circle mr-1"></i> No hay facturas disponibles
                                </div>
                            </div>

                            <!-- Factura Final -->
                            <div ref="facturaFinalContainer" class="relative">
                                <label class="block text-[10px] font-semibold text-gray-600 mb-0.5">
                                    <i class="fas fa-file-invoice text-primary-500 mr-1 text-[10px]"></i>
                                    Hasta Factura <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input 
                                        type="text"
                                        v-model="facturaFinalBusqueda"
                                        @focus="mostrarFacturaFinal = true"
                                        class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-sm pr-7 focus:ring-primary-500 focus:border-primary-500 outline-none disabled:bg-gray-50 disabled:text-gray-400"
                                        placeholder="Escribe N° de factura..."
                                        :disabled="!sucursalId || loading"
                                        autocomplete="off"
                                    />
                                    <button 
                                        v-if="facturaFinalBusqueda" 
                                        @click="facturaFinalId = ''; facturaFinalBusqueda = ''" 
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i class="fas fa-times text-[10px]"></i>
                                    </button>
                                </div>
                                
                                <!-- Badge de selección -->
                                <span v-if="facturaFinalSeleccionada" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-primary-50 text-primary-700 mt-0.5">
                                    <i class="fas fa-check-circle text-[8px]"></i> N° {{ facturaFinalSeleccionada.numero }}
                                </span>
                                
                                <!-- Dropdown Factura Final -->
                                <div 
                                    v-if="mostrarFacturaFinal && facturasFinalesFiltradas.length > 0" 
                                    class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-y-auto text-sm w-full"
                                >
                                    <div 
                                        v-for="factura in facturasFinalesFiltradas" 
                                        :key="factura.id"
                                        @click="seleccionarFacturaFinal(factura)"
                                        class="px-3 py-2 cursor-pointer hover:bg-primary-50 flex justify-between items-center border-b border-gray-100 last:border-0"
                                        :class="facturaFinalId == factura.id ? 'bg-primary-50 text-primary-700' : ''"
                                    >
                                        <span class="text-sm font-medium">N° {{ factura.numero }}</span>
                                        <span class="text-[10px] text-gray-400">ID: {{ factura.id }}</span>
                                    </div>
                                </div>
                                
                                <!-- Sin resultados -->
                                <div 
                                    v-if="mostrarFacturaFinal && facturasFinalesFiltradas.length === 0 && sucursalId" 
                                    class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg p-3 text-sm text-gray-500 w-full text-center"
                                >
                                    <i class="fas fa-info-circle mr-1"></i> No hay facturas disponibles
                                </div>
                            </div>
                        </div>

                        <!-- Info de rango -->
                        <div v-if="rangoInfo" class="flex flex-wrap items-center gap-2 p-2 bg-primary-50 border border-primary-100 rounded-md">
                            <i class="fas fa-arrows-alt-h text-primary-500 text-[10px]"></i>
                            <span class="text-xs text-primary-700 font-medium">{{ rangoInfo }}</span>
                            <span v-if="!rangoValido" class="text-xs text-red-600 font-medium ml-auto">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Rango inválido
                            </span>
                        </div>
                    </div>

                    <!-- ==================== FOOTER ==================== -->
                    <div class="px-4 sm:px-6 py-3 bg-gray-50 border-t border-gray-200 rounded-b-xl flex justify-end">
                        <button
                            type="button"
                            @click="validarRango() && (mostrarModalConfirmacion = true)"
                            :disabled="procesando || !sucursalId || !facturaInicialId || !facturaFinalId || !rangoValido"
                            class="px-4 sm:px-6 py-1.5 sm:py-2 bg-primary-600 text-white text-xs sm:text-sm font-medium rounded-md hover:bg-primary-700 transition-all disabled:opacity-40 disabled:cursor-not-allowed inline-flex items-center gap-2"
                        >
                            <i v-if="procesando" class="fas fa-spinner fa-spin text-[10px] sm:text-xs"></i>
                            <i v-else class="fas fa-sync-alt text-[10px] sm:text-xs"></i>
                            {{ procesando ? 'Procesando...' : 'Procesar Lote' }}
                        </button>
                    </div>
                </div>

                <!-- ==================== MODALES ==================== -->
                <ModalConfirmacionReprocesar
                    :visible="mostrarModalConfirmacion"
                    titulo="⚠️ Confirmar Reproceso"
                    mensaje="Se recalcularán los movimientos de inventario del rango seleccionado."
                    :rangoInfo="rangoInfo"
                    botonConfirmar="Confirmar"
                    :cargando="procesando"
                    @confirm="ejecutarProcesar"
                    @close="mostrarModalConfirmacion = false"
                />

                <ModalResultadoReprocesar
                    :visible="mostrarModalResultado"
                    :exito="resultadoExito"
                    :mensaje="resultadoMensaje"
                    :detalles="resultadoDetalles"
                    @close="mostrarModalResultado = false"
                />

                <!-- ==================== LOADING OVERLAY ==================== -->
                <div v-if="loading || procesando" class="fixed inset-0 bg-black/30 backdrop-blur-[1px] flex items-center justify-center z-50">
                    <div class="bg-white border border-gray-200 rounded-lg p-3 flex items-center gap-2 shadow-md text-xs font-medium text-gray-700">
                        <i class="fas fa-spinner fa-spin text-primary-600"></i>
                        <span>{{ loading ? 'Cargando registros...' : 'Procesando...' }}</span>
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

/* Scrollbar personalizado */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

.overflow-y-auto {
    scrollbar-width: thin;
    scrollbar-color: #d1d5db #f1f1f1;
}
</style>