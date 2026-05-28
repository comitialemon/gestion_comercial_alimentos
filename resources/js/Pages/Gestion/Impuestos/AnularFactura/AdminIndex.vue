<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import ConfirmacionModal from './components/ConfirmacionModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    facturas: Array,
    sucursales: Array,
    todasSucursales: Array,
    operadores: Array,
    filtros: Object,
})

const facturaSeleccionada = ref('')
const anulando = ref(false)
const mensaje = ref('')
const error = ref('')
const exito = ref(false)
const isMobile = ref(window.innerWidth < 768)
const mostrarFiltros = ref(false)

// 🔥 Estado del modal
const modalVisible = ref(false)
const modalCargando = ref(false)
const facturaParaAnular = ref(null)

// Filtros
const sucursalId = ref(props.filtros?.sucursal_id || '')
const operadorId = ref(props.filtros?.operador_id || '')
const fecha = ref(props.filtros?.fecha || '')

// Buscadores
const buscarSucursal = ref(props.filtros?.buscar_sucursal || '')
const buscarOperador = ref(props.filtros?.buscar_operador || '')

// Listas para mostrar en autocomplete
const sucursalesFiltradas = ref([])
const operadoresFiltrados = ref([])
const mostrarSucursales = ref(false)
const mostrarOperadores = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
    if (!isMobile.value) {
        mostrarFiltros.value = false
    }
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
    sucursalesFiltradas.value = props.todasSucursales || []
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// Filtrar sucursales
const filtrarSucursales = () => {
    if (!buscarSucursal.value) {
        sucursalesFiltradas.value = props.todasSucursales || []
    } else {
        const termino = buscarSucursal.value.toLowerCase()
        sucursalesFiltradas.value = (props.todasSucursales || []).filter(s => 
            s.nombre.toLowerCase().includes(termino)
        )
    }
    mostrarSucursales.value = true
}

// Seleccionar sucursal
const seleccionarSucursal = (suc) => {
    sucursalId.value = suc.id
    buscarSucursal.value = suc.nombre
    operadorId.value = ''
    buscarOperador.value = ''
    mostrarSucursales.value = false
    aplicarFiltros()
}

// Limpiar sucursal
const limpiarSucursal = () => {
    sucursalId.value = ''
    buscarSucursal.value = ''
    operadorId.value = ''
    buscarOperador.value = ''
    sucursalesFiltradas.value = props.todasSucursales || []
    mostrarSucursales.value = false
    aplicarFiltros()
}

// Buscar operadores
const buscarOperadoresLocal = () => {
    if (!sucursalId.value) {
        operadoresFiltrados.value = []
        return
    }
    
    if (!buscarOperador.value) {
        operadoresFiltrados.value = props.operadores || []
    } else {
        const termino = buscarOperador.value.toLowerCase()
        operadoresFiltrados.value = (props.operadores || []).filter(op => 
            op.nombre.toLowerCase().includes(termino) || 
            op.ci.toString().includes(termino)
        )
    }
    mostrarOperadores.value = true
}

// Seleccionar operador
const seleccionarOperador = (op) => {
    operadorId.value = op.id
    buscarOperador.value = `${op.ci} - ${op.nombre}`
    mostrarOperadores.value = false
    aplicarFiltros()
}

// Limpiar operador
const limpiarOperador = () => {
    operadorId.value = ''
    buscarOperador.value = ''
    operadoresFiltrados.value = []
    mostrarOperadores.value = false
    aplicarFiltros()
}

// Aplicar filtros
let timeoutFiltros
const aplicarFiltros = () => {
    clearTimeout(timeoutFiltros)
    timeoutFiltros = setTimeout(() => {
        const params = {}
        if (sucursalId.value) params.sucursal_id = sucursalId.value
        if (operadorId.value) params.operador_id = operadorId.value
        if (fecha.value) params.fecha = fecha.value
        if (buscarSucursal.value) params.buscar_sucursal = buscarSucursal.value
        if (buscarOperador.value) params.buscar_operador = buscarOperador.value
        
        router.get('/gestion/anular-factura/admin', params, {
            preserveState: true,
            replace: true,
        })
    }, 500)
}

watch(fecha, () => {
    aplicarFiltros()
})

const ocultarListas = () => {
    setTimeout(() => {
        mostrarSucursales.value = false
        mostrarOperadores.value = false
    }, 200)
}

const limpiarTodo = () => {
    sucursalId.value = ''
    operadorId.value = ''
    fecha.value = ''
    buscarSucursal.value = ''
    buscarOperador.value = ''
    sucursalesFiltradas.value = props.todasSucursales || []
    operadoresFiltrados.value = []
    router.get('/gestion/anular-factura/admin', {}, {
        preserveState: true,
        replace: true,
    })
    if (isMobile.value) mostrarFiltros.value = false
}

const toggleFiltros = () => {
    mostrarFiltros.value = !mostrarFiltros.value
}

const seleccionarFactura = (id) => {
    facturaSeleccionada.value = id
}

// 🔥 ABRIR MODAL DE CONFIRMACIÓN
const abrirModalConfirmacion = () => {
    if (!facturaSeleccionada.value) {
        error.value = 'Seleccione una factura para anular'
        return
    }
    
    const factura = props.facturas.find(f => f.IdVentas === facturaSeleccionada.value)
    if (factura) {
        facturaParaAnular.value = factura
        modalVisible.value = true
    }
}

// 🔥 EJECUTAR ANULACIÓN
const ejecutarAnulacion = async () => {
    modalCargando.value = true
    
    try {
        const response = await axios.post('/gestion/anular-factura/anular', {
            IdVentas: facturaParaAnular.value.IdVentas
        })
        
        if (response.data.success) {
            modalVisible.value = false
            exito.value = true
            mensaje.value = response.data.message
            
            // Abrir PDF de la factura anulada
            const pdfUrl = `/venta-factura/factura-pdf/${facturaParaAnular.value.IdVentas}`
            window.open(pdfUrl, '_blank')
            
            setTimeout(() => {
                window.location.reload()
            }, 2000)
        }
    } catch (err) {
        modalVisible.value = false
        error.value = err.response?.data?.message || 'Error al anular la factura'
        setTimeout(() => {
            error.value = ''
        }, 3000)
    } finally {
        modalCargando.value = false
    }
}

// 🔥 CERRAR MODAL
const cerrarModal = () => {
    modalVisible.value = false
    facturaParaAnular.value = null
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-ban text-red-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Anular Factura - Administración</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">Seleccione sucursal, busque operador y/o filtre por fecha</p>
                        </div>
                    </div>
                    
                    <button 
                        v-if="isMobile"
                        @click="toggleFiltros"
                        class="w-full sm:w-auto bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1"
                    >
                        <i :class="mostrarFiltros ? 'fas fa-chevron-up' : 'fas fa-sliders-h'"></i>
                        {{ mostrarFiltros ? 'Ocultar filtros' : 'Mostrar filtros' }}
                    </button>
                </div>

                <!-- Mensajes -->
                <div v-if="exito" class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-green-600 text-sm"></i>
                        <p class="text-xs text-green-700">{{ mensaje }}</p>
                    </div>
                </div>

                <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-circle text-red-600 text-sm"></i>
                        <p class="text-xs text-red-700">{{ error }}</p>
                    </div>
                </div>

                <!-- Filtros -->
                <div 
                    class="bg-white rounded-lg shadow-sm p-3 mb-4 transition-all duration-300"
                    :class="{ 'hidden': isMobile && !mostrarFiltros }"
                >
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <!-- Sucursal con buscador -->
                        <div class="relative">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Sucursal</label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="buscarSucursal" 
                                    @input="filtrarSucursales"
                                    @focus="mostrarSucursales = true"
                                    @blur="ocultarListas"
                                    class="w-full border rounded-md px-2 py-1 text-sm pr-7"
                                    placeholder="Escriba para buscar sucursal..."
                                >
                                <button 
                                    v-if="buscarSucursal" 
                                    @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                            <div v-if="mostrarSucursales && sucursalesFiltradas.length > 0" class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-40 overflow-y-auto">
                                <div 
                                    v-for="suc in sucursalesFiltradas" 
                                    :key="suc.id"
                                    @click="seleccionarSucursal(suc)"
                                    class="px-2 py-1.5 hover:bg-gray-100 cursor-pointer text-xs border-b last:border-b-0"
                                >
                                    {{ suc.nombre }}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Operador con buscador -->
                        <div class="relative">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Operador</label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="buscarOperador" 
                                    :disabled="!sucursalId"
                                    @input="buscarOperadoresLocal"
                                    @focus="mostrarOperadores = true"
                                    @blur="ocultarListas"
                                    class="w-full border rounded-md px-2 py-1 text-sm pr-7"
                                    :class="{ 'bg-gray-100': !sucursalId }"
                                    placeholder="Buscar por nombre o CI..."
                                >
                                <button 
                                    v-if="buscarOperador && sucursalId" 
                                    @click="limpiarOperador"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                            <div v-if="mostrarOperadores && operadoresFiltrados.length > 0 && sucursalId" class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-40 overflow-y-auto">
                                <div 
                                    v-for="op in operadoresFiltrados" 
                                    :key="op.id"
                                    @click="seleccionarOperador(op)"
                                    class="px-2 py-1.5 hover:bg-gray-100 cursor-pointer text-xs border-b last:border-b-0"
                                >
                                    <span class="font-mono">{{ op.ci }}</span> - {{ op.nombre }}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fecha (opcional) -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fecha (opcional)</label>
                            <input type="date" v-model="fecha" class="w-full border rounded-md px-2 py-1 text-sm">
                        </div>
                        
                        <!-- Botón Limpiar -->
                        <div class="flex items-end">
                            <button @click="limpiarTodo" class="w-full px-3 py-1 border border-gray-300 rounded-md text-sm text-gray-600 hover:bg-gray-100 transition">
                                <i class="fas fa-eraser text-[10px] mr-1"></i> Limpiar filtros
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Vista MÓVIL (tarjetas) -->
                <div v-if="isMobile" class="space-y-3">
                    <div 
                        v-for="factura in facturas" 
                        :key="factura.IdVentas"
                        @click="seleccionarFactura(factura.IdVentas)"
                        class="bg-white rounded-lg shadow-sm p-3 cursor-pointer transition-all"
                        :class="{ 'ring-2 ring-red-500 bg-red-50': facturaSeleccionada == factura.IdVentas }"
                    >
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-mono font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded">
                                N° {{ factura.NumeroFactura }}
                            </span>
                            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                                :class="facturaSeleccionada == factura.IdVentas ? 'bg-red-500 border-red-500' : 'border-gray-300'">
                                <i v-if="facturaSeleccionada == factura.IdVentas" class="fas fa-check text-white text-[10px]"></i>
                            </div>
                        </div>
                        
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Fecha:</span>
                                <span class="font-medium">{{ formatearFecha(factura.FechaVenta) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Sucursal:</span>
                                <span class="font-medium">{{ factura.sucursal_nombre }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Operador:</span>
                                <span class="font-medium">{{ factura.operador_nombre }}</span>
                            </div>
                            <div class="flex justify-between pt-1 border-t mt-1">
                                <span class="text-gray-500">Importe:</span>
                                <span class="font-bold text-primary-600">{{ formatearNumero(factura.ImporteVenta) }} Bs</span>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="facturas.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-receipt text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-xs text-gray-400">No hay facturas pendientes de anulación</p>
                    </div>
                </div>

                <!-- Vista ESCRITORIO (tabla) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Factura</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sucursal</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Operador</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Importe</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-12">Seleccionar</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr 
                                    v-for="factura in facturas" 
                                    :key="factura.IdVentas"
                                    class="hover:bg-gray-50 transition cursor-pointer"
                                    :class="{ 'bg-red-50': facturaSeleccionada == factura.IdVentas }"
                                    @click="seleccionarFactura(factura.IdVentas)"
                                >
                                    <td class="px-4 py-2 text-sm font-mono text-gray-900">{{ factura.NumeroFactura }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ formatearFecha(factura.FechaVenta) }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ factura.sucursal_nombre }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ factura.operador_nombre }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-semibold text-primary-600">{{ formatearNumero(factura.ImporteVenta) }} Bs</td>
                                    <td class="px-4 py-2 text-center">
                                        <input 
                                            type="radio" 
                                            :value="factura.IdVentas"
                                            v-model="facturaSeleccionada"
                                            class="w-4 h-4 text-red-600 focus:ring-red-500 cursor-pointer"
                                            @click.stop
                                        >
                                    </td>
                                </tr>
                                <tr v-if="facturas.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                        <i class="fas fa-receipt text-3xl mb-2 block"></i>
                                        No hay facturas pendientes de anulación
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Botón Anular (con modal) -->
                <div class="mt-4 flex justify-end" :class="{ 'fixed bottom-4 right-4 z-50': isMobile && facturas.length > 0 }">
                    <button 
                        @click="abrirModalConfirmacion"
                        :disabled="!facturaSeleccionada || anulando"
                        class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm"
                    >
                        <i v-if="anulando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-ban"></i>
                        {{ anulando ? 'Anulando...' : 'Anular Factura' }}
                    </button>
                </div>

                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Solo se pueden anular facturas que NO hayan sido liquidadas.
                </div>
            </div>
        </div>

        <!-- 🔥 MODAL DE CONFIRMACIÓN -->
        <ConfirmacionModal
            :visible="modalVisible"
            :cargando="modalCargando"
            titulo="Anular Factura"
            descripcion="¿Estás seguro de que deseas anular esta factura?"
            mensaje-adicional="Esta acción no se puede deshacer. La factura quedará marcada como ANULADA."
            boton-texto="Sí, Anular"
            accion="anular"
            :numero-factura="facturaParaAnular?.NumeroFactura"
            @confirmar="ejecutarAnulacion"
            @cerrar="cerrarModal"
        />
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:inline {
        display: inline;
    }
    .xs\:block {
        display: block;
    }
}
</style>