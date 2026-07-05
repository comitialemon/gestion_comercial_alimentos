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

// ESTADO
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

// Modales
const mostrarModalConfirmacion = ref(false)
const mostrarModalResultado = ref(false)
const resultadoExito = ref(true)
const resultadoMensaje = ref('')
const resultadoDetalles = ref({ total: 0, facturas: [], errores: [] })

// COMPUTADOS
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre?.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

// ⚡ FILTRO EN TIEMPO REAL PARA FACTURA INICIAL
const facturasInicialesFiltradas = computed(() => {
    if (!facturasDisponibles.value) return []
    // Si el usuario ya seleccionó una y sale el texto "N° X", o si está vacío, mostrar todas
    if (!facturaInicialBusqueda.value || facturaInicialBusqueda.value.startsWith('N°')) {
        return facturasDisponibles.value
    }
    const termino = facturaInicialBusqueda.value.trim()
    return facturasDisponibles.value.filter(f => 
        f.numero && f.numero.toString().includes(termino)
    )
})

// ⚡ FILTRO EN TIEMPO REAL PARA FACTURA FINAL
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

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    return props.sucursales?.find(s => s.id === sucursalId.value)?.nombre || ''
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

// MÉTODOS
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
            facturas: response.data.facturas || [],
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

const handleClickOutside = (event) => {
    if (!document.querySelector('.sucursal-autocomplete')?.contains(event.target)) mostrarSucursales.value = false
    if (!document.querySelector('.autocomplete-inicial')?.contains(event.target)) mostrarFacturaInicial.value = false
    if (!document.querySelector('.autocomplete-final')?.contains(event.target)) mostrarFacturaFinal.value = false
}

watch(sucursalId, (newVal) => newVal && cargarFacturas(newVal))

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    if (sucursalId.value) {
        cargarFacturas(sucursalId.value)
        const suc = props.sucursales?.find(s => s.id === sucursalId.value)
        if (suc) sucursalBusqueda.value = suc.nombre
    }
})
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<template>
    <div class="min-h-screen bg-slate-50 py-6">
        <div class="max-w-4xl mx-auto px-4">
            
            <div class="flex items-center gap-3 mb-4">
                <button @click="volver" class="text-slate-400 hover:text-slate-600 transition p-1">
                    <i class="fas fa-arrow-left text-base"></i>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-slate-800">Reprocesar Rango de Facturas</h1>
                    <p class="text-xs text-slate-500">Ajuste selectivo de stock e inventario por lote</p>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
                
                <div class="p-6 space-y-6">
                    
                    <div class="sucursal-autocomplete relative">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">📍 Sucursal</label>
                        <div class="relative">
                            <input 
                                type="text"
                                v-model="sucursalBusqueda"
                                @focus="mostrarSucursales = true"
                                class="w-full pl-3 pr-8 py-2 text-sm rounded-md border border-slate-300 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                                placeholder="Buscar sucursal..."
                                autocomplete="off"
                            />
                            <button v-if="sucursalBusqueda" @click="limpiarSucursal" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                        
                        <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" class="fixed z-50 mt-1 bg-white border border-slate-200 rounded-md shadow-xl max-h-[300px] overflow-y-auto text-sm w-[inherit] max-w-[864px]">
                            <div 
                                v-for="sucursal in sucursalesDisponibles" :key="sucursal.id"
                                @click="seleccionarSucursal(sucursal)"
                                class="px-4 py-2.5 cursor-pointer hover:bg-slate-50 flex justify-between items-center border-b border-slate-100 last:border-0"
                                :class="sucursalId == sucursal.id ? 'bg-blue-50 text-blue-700' : ''"
                            >
                                <span class="font-medium">{{ sucursal.nombre }}</span>
                                <span v-if="sucursal.numero" class="text-xs opacity-60">N° {{ sucursal.numero }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="autocomplete-inicial relative">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">📄 Desde Factura</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="facturaInicialBusqueda"
                                    @focus="mostrarFacturaInicial = true"
                                    class="w-full pl-3 pr-8 py-2 text-sm rounded-md border border-slate-300 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all disabled:bg-slate-50 disabled:text-slate-400"
                                    placeholder="Escribe N° de factura..."
                                    :disabled="!sucursalId || loading"
                                    autocomplete="off"
                                />
                                <button v-if="facturaInicialBusqueda" @click="facturaInicialId = ''; facturaInicialBusqueda = ''" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                            
                            <div v-if="mostrarFacturaInicial && facturasInicialesFiltradas.length > 0" class="fixed z-50 mt-1 bg-white border border-slate-200 rounded-md shadow-xl max-h-[350px] overflow-y-auto text-sm w-[inherit] max-w-[420px]">
                                <div 
                                    v-for="factura in facturasInicialesFiltradas" :key="factura.id"
                                    @click="seleccionarFacturaInicial(factura)"
                                    class="px-4 py-2.5 cursor-pointer hover:bg-slate-50 flex justify-between items-center border-b border-slate-100 last:border-0"
                                    :class="facturaInicialId == factura.id ? 'bg-blue-50 text-blue-700' : ''"
                                >
                                    <span class="font-medium">N° {{ factura.numero }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="autocomplete-final relative">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">📄 Hasta Factura</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="facturaFinalBusqueda"
                                    @focus="mostrarFacturaFinal = true"
                                    class="w-full pl-3 pr-8 py-2 text-sm rounded-md border border-slate-300 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all disabled:bg-slate-50 disabled:text-slate-400"
                                    placeholder="Escribe N° de factura..."
                                    :disabled="!sucursalId || loading"
                                    autocomplete="off"
                                />
                                <button v-if="facturaFinalBusqueda" @click="facturaFinalId = ''; facturaFinalBusqueda = ''" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                            
                            <div v-if="mostrarFacturaFinal && facturasFinalesFiltradas.length > 0" class="fixed z-50 mt-1 bg-white border border-slate-200 rounded-md shadow-xl max-h-[350px] overflow-y-auto text-sm w-[inherit] max-w-[420px]">
                                <div 
                                    v-for="factura in facturasFinalesFiltradas" :key="factura.id"
                                    @click="seleccionarFacturaFinal(factura)"
                                    class="px-4 py-2.5 cursor-pointer hover:bg-slate-50 flex justify-between items-center border-b border-slate-100 last:border-0"
                                    :class="facturaFinalId == factura.id ? 'bg-blue-50 text-blue-700' : ''"
                                >
                                    <span class="font-medium">N° {{ factura.numero }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="facturaInicialId && facturaFinalId && !rangoValido" class="p-2 bg-red-50 border border-red-100 rounded-md text-red-600 text-xs flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>El rango de facturas es inconsistente.</span>
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 text-right rounded-b-lg">
                    <button
                        type="button"
                        @click="validarRango() && (mostrarModalConfirmacion = true)"
                        :disabled="procesando || !sucursalId || !facturaInicialId || !facturaFinalId || !rangoValido"
                        class="px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 transition-all disabled:opacity-40 disabled:cursor-not-allowed inline-flex items-center gap-2"
                    >
                        <i v-if="procesando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-sync-alt"></i>
                        {{ procesando ? 'Procesando...' : 'Procesar Lote' }}
                    </button>
                </div>
            </div>

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

            <div v-if="loading || procesando" class="fixed inset-0 bg-slate-900/30 backdrop-blur-[1px] flex items-center justify-center z-50">
                <div class="bg-white border border-slate-200 rounded-lg p-3 flex items-center gap-2 shadow-md text-xs font-medium text-slate-700">
                    <i class="fas fa-spinner fa-spin text-blue-600"></i>
                    <span>{{ loading ? 'Cargando registros...' : 'Procesando...' }}</span>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
:deep(*) {
    overflow: visible !important;
}
.overflow-y-auto {
    overflow-y: auto !important;
}
</style>