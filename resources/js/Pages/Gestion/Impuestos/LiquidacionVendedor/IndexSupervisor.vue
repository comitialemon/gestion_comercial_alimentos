<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    liquidaciones: Object,
    resumenOperadores: Array,
    sucursales: Array,
    sucursalSeleccionada: Number,
    nombreSucursal: String,
    operadores: Array,
    operadorSeleccionado: Number,
    nombreOperadorSeleccionado: String,
    titulo: String,
    subtitulo: String,
})

// ==================== ESTADO ====================
const loading = ref(false)
const mostrarResumen = ref(true)

// Estado para autocomplete de Sucursal
const sucursalId = ref(props.sucursalSeleccionada || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

// Estado para autocomplete de Operador
const operadorId = ref(props.operadorSeleccionado || '')
const operadorBusqueda = ref('')
const mostrarOperadores = ref(false)

// ==================== COMPUTADOS ====================
const isMobile = computed(() => window.innerWidth < 640)

// Sucursales filtradas
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre.toLowerCase().includes(termino)
    )
})

// Operadores filtrados
const operadoresDisponibles = computed(() => {
    if (!props.operadores) return []
    if (!operadorBusqueda.value) return props.operadores
    
    const termino = operadorBusqueda.value.toLowerCase()
    return props.operadores.filter(o => 
        o.nombre.toLowerCase().includes(termino)
    )
})

// Nombre de la sucursal seleccionada
const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id === Number(sucursalId.value))
    return suc?.nombre || ''
})

// Nombre del operador seleccionado
const operadorNombre = computed(() => {
    if (!operadorId.value) return ''
    const op = props.operadores?.find(o => o.id === Number(operadorId.value))
    return op?.nombre || ''
})

// Total de liquidaciones
const totalLiquidaciones = computed(() => {
    return props.liquidaciones?.total || 0
})

const totalVentasSucursal = computed(() => {
    if (!props.liquidaciones?.data) return 0
    return props.liquidaciones.data.reduce((sum, item) => sum + (item.vEntasConfirma || 0), 0)
})

const totalDiferenciaSucursal = computed(() => {
    if (!props.liquidaciones?.data) return 0
    return props.liquidaciones.data.reduce((sum, item) => sum + (item.dIfVendedorConfirma || 0), 0)
})

const hayFiltroOperador = computed(() => {
    return operadorId.value && operadorId.value !== ''
})

// ==================== ACCIONES ====================
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    aplicarFiltros()
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    // Limpiar también operador
    operadorId.value = ''
    operadorBusqueda.value = ''
    aplicarFiltros()
}

const seleccionarOperador = (operador) => {
    operadorId.value = operador.id
    operadorBusqueda.value = operador.nombre
    mostrarOperadores.value = false
    aplicarFiltros()
}

const limpiarOperador = () => {
    operadorId.value = ''
    operadorBusqueda.value = ''
    mostrarOperadores.value = false
    aplicarFiltros()
}

const aplicarFiltros = () => {
    loading.value = true
    const params = {}
    
    if (sucursalId.value) {
        params.sucursal_id = sucursalId.value
    }
    
    if (operadorId.value) {
        params.operador_id = operadorId.value
    }
    
    router.get('/gestion/impuestos/liquidacion-vendedor/supervisor', 
        params,
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => { 
                loading.value = false
            }
        }
    )
}

// ==================== CIERRE DE SUGERENCIAS ====================
const handleClickOutside = (event) => {
    const sucursalContainer = document.querySelector('.sucursal-autocomplete')
    if (sucursalContainer && !sucursalContainer.contains(event.target)) {
        mostrarSucursales.value = false
    }
    
    const operadorContainer = document.querySelector('.operador-autocomplete')
    if (operadorContainer && !operadorContainer.contains(event.target)) {
        mostrarOperadores.value = false
    }
}

// ==================== FUNCIONES DE FORMATEO ====================
const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

const reimprimirPDF = (id) => {
    window.open(`/gestion/impuestos/liquidacion-vendedor/pdf/${id}`, '_blank')
}

const abrirPdfDiario = (idDiario) => {
    if (idDiario && idDiario > 0) {
        window.open(`/gestion/contabilidad/imprimir-diario/pdf/${idDiario}`, '_blank')
    } else {
        const toast = document.createElement('div')
        toast.className = 'fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-sm text-sm text-white bg-yellow-500 flex items-center gap-2'
        toast.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Esta liquidación no tiene un diario asociado'
        document.body.appendChild(toast)
        setTimeout(() => toast.remove(), 2000)
    }
}

const getDiferenciaColor = (valor) => {
    return valor >= 0 ? 'text-emerald-600' : 'text-red-600'
}

const getDiferenciaIcono = (valor) => {
    return valor >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-4 sm:py-6 px-3 sm:px-4 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- ========================================== -->
                <!-- HEADER CON FILTROS AUTOCOMPLETE            -->
                <!-- ========================================== -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-4">
                    <div class="flex flex-col gap-4">
                        <!-- Título -->
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center"
                                 :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                <i class="fas fa-user-shield text-base sm:text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-lg sm:text-xl font-bold text-gray-800">{{ titulo }}</h1>
                                <p class="text-xs text-gray-500 hidden sm:block">{{ subtitulo }}</p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 sm:hidden">{{ subtitulo }}</p>

                        <!-- Filtros -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- 🔥 AUTOCOMPLETE SUCURSAL -->
                            <div class="sucursal-autocomplete">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-store mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                    Seleccione Sucursal: <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input 
                                        type="text"
                                        v-model="sucursalBusqueda"
                                        @focus="mostrarSucursales = true"
                                        @input="mostrarSucursales = true"
                                        class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                        :style="{ borderColor: `var(--color-primary-300)` }"
                                        placeholder="Buscar sucursal..."
                                        autocomplete="off"
                                        :disabled="loading"
                                    />
                                    <button 
                                        v-if="sucursalBusqueda"
                                        @click="limpiarSucursal"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                        type="button"
                                    >
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                    
                                    <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                        class="absolute z-20 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                        <div 
                                            v-for="suc in sucursalesDisponibles" 
                                            :key="suc.id"
                                            @click="seleccionarSucursal(suc)"
                                            class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                            :class="sucursalId === suc.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                            :style="sucursalId === suc.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                        >
                                            <span class="text-sm">{{ suc.nombre }}</span>
                                            <i v-if="sucursalId === suc.id" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 🔥 AUTOCOMPLETE OPERADOR -->
                            <div class="operador-autocomplete">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-user mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                    Seleccione Operador:
                                </label>
                                <div class="relative">
                                    <input 
                                        type="text"
                                        v-model="operadorBusqueda"
                                        @focus="mostrarOperadores = true"
                                        @input="mostrarOperadores = true"
                                        class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                        :style="{ borderColor: `var(--color-primary-300)` }"
                                        placeholder="Buscar operador..."
                                        autocomplete="off"
                                        :disabled="loading || !sucursalId"
                                    />
                                    <button 
                                        v-if="operadorBusqueda"
                                        @click="limpiarOperador"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                        type="button"
                                    >
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                    
                                    <div v-if="mostrarOperadores && operadoresDisponibles.length > 0" 
                                        class="absolute z-20 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                        <div 
                                            v-for="op in operadoresDisponibles" 
                                            :key="op.id"
                                            @click="seleccionarOperador(op)"
                                            class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                            :class="operadorId === op.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                            :style="operadorId === op.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                        >
                                            <span class="text-sm">{{ op.nombre }}</span>
                                            <i v-if="operadorId === op.id" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                        </div>
                                    </div>
                                    
                                    <div v-if="mostrarOperadores && sucursalId && operadoresDisponibles.length === 0" 
                                        class="absolute z-20 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        No hay operadores en esta sucursal
                                    </div>
                                    
                                    <div v-if="mostrarOperadores && !sucursalId" 
                                        class="absolute z-20 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Primero seleccione una sucursal
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 🔥 ESTADÍSTICAS RÁPIDAS - SOLO NÚMEROS -->
                    <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-gray-100 text-xs">
                        <span class="bg-primary-50 text-primary-700 px-2 py-1 rounded-full flex items-center gap-1">
                            <i class="fas fa-store text-[10px]"></i>
                            {{ sucursalNombre || 'Sin sucursal' }}
                        </span>
                        <span v-if="hayFiltroOperador" class="bg-blue-50 text-blue-700 px-2 py-1 rounded-full flex items-center gap-1">
                            <i class="fas fa-user text-[10px]"></i>
                            {{ operadorNombre }}
                        </span>
                        <span class="bg-purple-50 text-purple-700 px-2 py-1 rounded-full flex items-center gap-1">
                            <i class="fas fa-file-invoice text-[10px]"></i>
                            {{ totalLiquidaciones }}
                        </span>

                        <span v-if="loading" class="text-purple-600 animate-pulse ml-auto">
                            <i class="fas fa-spinner fa-spin mr-1"></i> Cargando...
                        </span>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- RESUMEN POR OPERADOR                       -->
                <!-- ========================================== -->
                <div v-if="resumenOperadores?.length > 0" class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <i class="fas fa-users text-purple-500"></i>
                            <span class="hidden xs:inline">Resumen por Operador</span>
                            <span class="xs:hidden">Resumen</span>
                            <span v-if="hayFiltroOperador" class="text-xs text-blue-600 font-normal">
                                (Filtrado: {{ operadorNombre }})
                            </span>
                        </h3>
                        <button @click="mostrarResumen = !mostrarResumen" class="text-xs text-purple-600 hover:text-purple-800">
                            <i :class="mostrarResumen ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
                            <span class="hidden sm:inline ml-1">{{ mostrarResumen ? 'Ocultar' : 'Mostrar' }}</span>
                        </button>
                    </div>
                    
                    <div v-if="mostrarResumen" class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-3 py-1.5 text-left text-gray-500">Operador</th>
                                    <th class="px-3 py-1.5 text-center text-gray-500">Liquidaciones</th>
                                    <th class="px-3 py-1.5 text-right text-gray-500">Total Ventas</th>
                                    <th class="px-3 py-1.5 text-right text-gray-500">Diferencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="op in resumenOperadores" :key="op.nombre_operador" class="border-t border-gray-100 hover:bg-gray-50">
                                    <td class="px-3 py-1.5 font-medium text-gray-700">{{ op.nombre_operador }}</td>
                                    <td class="px-3 py-1.5 text-center font-bold text-purple-600">{{ op.total_liquidaciones }}</td>
                                    <td class="px-3 py-1.5 text-right font-semibold text-primary-600">
                                        {{ formatearNumero(op.total_ventas) }}
                                    </td>
                                    <td class="px-3 py-1.5 text-right" :class="getDiferenciaColor(op.total_diferencia)">
                                        {{ formatearNumero(op.total_diferencia) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- LISTADO DE LIQUIDACIONES                   -->
                <!-- ========================================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-purple-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">#</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">Operador</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-purple-700 uppercase">N° Diario</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-purple-700 uppercase">Total Ventas</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-purple-700 uppercase">Diferencia</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-purple-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="liquidacion in liquidaciones.data" :key="liquidacion.iDLiquidacionVendedor" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 py-2 text-xs">
                                        <span class="font-mono font-bold text-purple-600 bg-purple-50 px-2 py-0.5 rounded">
                                            {{ liquidacion.correlativo_sucursal }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs">
                                        <span class="font-medium text-gray-700">{{ liquidacion.nombre_operador || 'N/A' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">
                                        {{ liquidacion.fecha_formateada }}
                                    </td>
                                    <td class="px-3 py-2 text-xs">
                                        <span 
                                            @click="abrirPdfDiario(liquidacion.IdDiario)"
                                            class="font-mono text-blue-600 cursor-pointer hover:text-blue-800 hover:underline inline-flex items-center gap-1"
                                            :class="{ 'opacity-50 cursor-not-allowed': !liquidacion.IdDiario || liquidacion.IdDiario === 0 }"
                                        >
                                            <i class="fas fa-book-open text-[10px]"></i>
                                            {{ liquidacion.numero_diario || '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-right font-semibold text-primary-600 whitespace-nowrap">
                                        {{ formatearNumero(liquidacion.vEntasConfirma) }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-right">
                                        <span class="inline-flex items-center gap-1" :class="getDiferenciaColor(liquidacion.dIfVendedorConfirma)">
                                            <i :class="getDiferenciaIcono(liquidacion.dIfVendedorConfirma)" class="text-[10px]"></i>
                                            {{ formatearNumero(liquidacion.dIfVendedorConfirma) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button 
                                            @click="reimprimirPDF(liquidacion.iDLiquidacionVendedor)" 
                                            class="text-purple-600 hover:text-purple-800 transition-colors p-1 hover:bg-purple-50 rounded"
                                            title="Reimprimir"
                                        >
                                            <i class="fas fa-print text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="liquidaciones.data?.length === 0">
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        <i class="fas fa-file-invoice text-2xl mb-2 block"></i>
                                        No hay liquidaciones en esta sucursal
                                        <span v-if="hayFiltroOperador"> para el operador seleccionado</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="liquidaciones.links && liquidaciones.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-xs">
                            <div class="text-gray-500">
                                Mostrando {{ liquidaciones.from || 0 }} - {{ liquidaciones.to || 0 }} de {{ liquidaciones.total || 0 }}
                            </div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link 
                                    v-for="link in liquidaciones.links" 
                                    :key="link.label" 
                                    :href="link.url || '#'" 
                                    class="px-2 py-0.5 rounded border text-xs min-w-[28px] text-center"
                                    :class="{ 
                                        'bg-purple-600 text-white border-purple-600': link.active, 
                                        'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 
                                        'opacity-50 cursor-not-allowed': !link.url 
                                    }" 
                                    v-html="link.label" 
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:focus {
    --tw-ring-color: var(--color-primary-500);
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

.transition {
    transition-property: color, background-color, border-color;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

input:disabled {
    background-color: #f3f4f6;
    cursor: not-allowed;
}

.absolute.z-20 {
    animation: slideDown 0.15s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 480px) {
    .xs\:inline { display: inline !important; }
    .xs\:hidden { display: none !important; }
}
</style>