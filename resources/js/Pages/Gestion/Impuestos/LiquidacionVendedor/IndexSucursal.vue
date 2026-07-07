<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    liquidaciones: Object,
    resumenOperadores: Array,
    titulo: String,
    subtitulo: String,
    nombreSucursal: String,
})

// 🔥 DETECTAR TAMAÑO DE PANTALLA
const windowWidth = ref(window.innerWidth)
const mostrarResumen = ref(true)

const isMobile = computed(() => windowWidth.value < 640)

const handleResize = () => {
    windowWidth.value = window.innerWidth
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// 🔥 YA NO NECESITAS formatearFecha() - la fecha viene formateada del backend
// Solo conservamos formatearNumero para los montos

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

const totalLiquidaciones = computed(() => {
    return props.liquidaciones?.total || 0
})

const totalVentasSucursal = computed(() => {
    if (!props.liquidaciones?.data) return 0
    return props.liquidaciones.data.reduce((sum, item) => sum + (item.vEntasConfirma || 0), 0)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-5">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- HEADER -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-3 mb-3 sm:mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-store text-primary-600 text-xs sm:text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-sm sm:text-base lg:text-lg font-bold text-gray-800">{{ titulo }}</h1>
                            <p class="text-[9px] sm:text-[10px] text-gray-500 truncate max-w-[180px] sm:max-w-none">{{ subtitulo }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 sm:gap-2 text-[10px] sm:text-xs w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
                        <span class="bg-primary-50 text-primary-700 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full whitespace-nowrap flex items-center gap-1">
                            <i class="fas fa-file-invoice text-[8px] sm:text-[10px]"></i>
                            <span class="hidden xs:inline">Liquidaciones: </span>
                            {{ totalLiquidaciones }}
                        </span>
                        <span class="bg-emerald-50 text-emerald-700 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full whitespace-nowrap flex items-center gap-1">
                            <i class="fas fa-dollar-sign text-[8px] sm:text-[10px]"></i>
                            <span class="hidden xs:inline">Total: </span>
                            {{ formatearNumero(totalVentasSucursal) }}
                        </span>
                    </div>
                </div>

                <!-- RESUMEN POR OPERADOR -->
                <div v-if="resumenOperadores?.length > 0" class="bg-white rounded-lg shadow-sm p-2 sm:p-3 mb-3 sm:mb-4">
                    <div class="flex justify-between items-center mb-1.5 sm:mb-2">
                        <h3 class="text-xs sm:text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                            <i class="fas fa-users text-primary-500 text-[10px] sm:text-sm"></i>
                            <span class="hidden xs:inline">Resumen por Operador</span>
                            <span class="xs:hidden">Resumen</span>
                        </h3>
                        <button @click="mostrarResumen = !mostrarResumen" class="text-[10px] sm:text-xs text-primary-600 hover:text-primary-800">
                            <i :class="mostrarResumen ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
                            <span class="hidden sm:inline ml-1">{{ mostrarResumen ? 'Ocultar' : 'Mostrar' }}</span>
                        </button>
                    </div>
                    
                    <div v-if="mostrarResumen" class="overflow-x-auto -mx-1 sm:mx-0">
                        <table v-if="!isMobile" class="min-w-full text-[10px] sm:text-xs">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-1.5 sm:px-2 py-1 text-left text-gray-500">Operador</th>
                                    <th class="px-1.5 sm:px-2 py-1 text-center text-gray-500">Liquidaciones</th>
                                    <th class="px-1.5 sm:px-2 py-1 text-right text-gray-500">Total Ventas</th>
                                    <th class="px-1.5 sm:px-2 py-1 text-right text-gray-500">Diferencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="op in resumenOperadores" :key="op.nombre_operador" class="border-t border-gray-100 hover:bg-gray-50">
                                    <td class="px-1.5 sm:px-2 py-1 font-medium text-gray-700 text-[10px] sm:text-xs">{{ op.nombre_operador }}</td>
                                    <td class="px-1.5 sm:px-2 py-1 text-center font-bold text-primary-600">{{ op.total_liquidaciones }}</td>
                                    <td class="px-1.5 sm:px-2 py-1 text-right font-semibold text-primary-600">
                                        {{ formatearNumero(op.total_ventas) }}
                                    </td>
                                    <td class="px-1.5 sm:px-2 py-1 text-right" :class="getDiferenciaColor(op.total_diferencia)">
                                        {{ formatearNumero(op.total_diferencia) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- TARJETAS MÓVIL -->
                        <div v-else class="space-y-1.5">
                            <div v-for="op in resumenOperadores" :key="op.nombre_operador" class="bg-gray-50 rounded-md p-2 flex justify-between items-center">
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-medium text-gray-700 truncate">{{ op.nombre_operador }}</p>
                                    <div class="flex items-center gap-2 text-[9px] text-gray-500 mt-0.5">
                                        <span><i class="fas fa-file-invoice mr-0.5"></i> {{ op.total_liquidaciones }}</span>
                                        <span class="text-gray-300">|</span>
                                        <span :class="getDiferenciaColor(op.total_diferencia)">
                                            <i :class="getDiferenciaIcono(op.total_diferencia)" class="text-[8px]"></i>
                                            {{ formatearNumero(op.total_diferencia) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0 ml-2">
                                    <span class="text-xs font-bold text-primary-600">{{ formatearNumero(op.total_ventas) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LISTADO DE LIQUIDACIONES -->
                
                <!-- TARJETAS MÓVIL -->
                <div v-if="isMobile" class="space-y-2.5">
                    <div v-for="liquidacion in liquidaciones.data" :key="liquidacion.iDLiquidacionVendedor" class="bg-white rounded-lg shadow-sm p-3">
                        <div class="flex justify-between items-start border-b border-gray-100 pb-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded">
                                    #{{ liquidacion.iDLiquidacionVendedor }}
                                </span>
                                <span class="text-[9px] text-gray-400">{{ liquidacion.fecha_formateada }}</span> <!-- 🔥 USAR fecha_formateada -->
                            </div>
                            <button @click="reimprimirPDF(liquidacion.iDLiquidacionVendedor)" class="text-primary-600 hover:text-primary-800">
                                <i class="fas fa-print text-xs sm:text-sm"></i>
                            </button>
                        </div>
                        
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] text-gray-500">Operador</span>
                                <span class="text-[10px] font-medium text-gray-700 truncate max-w-[150px]">{{ liquidacion.nombre_operador || 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] text-gray-500">N° Diario</span>
                                <span 
                                    @click="abrirPdfDiario(liquidacion.IdDiario)"
                                    class="text-[10px] font-mono text-blue-600 cursor-pointer hover:text-blue-800 hover:underline flex items-center gap-1"
                                    :class="{ 'opacity-50 cursor-not-allowed': !liquidacion.IdDiario || liquidacion.IdDiario === 0 }"
                                >
                                    <i class="fas fa-book-open text-[8px]"></i>
                                    {{ liquidacion.numero_diario || '-' }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-2 pt-2 border-t border-gray-100 flex justify-between items-center">
                            <div>
                                <span class="text-[9px] text-gray-400 block">Total Ventas</span>
                                <span class="text-sm font-bold text-primary-600">{{ formatearNumero(liquidacion.vEntasConfirma) }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[9px] text-gray-400 block">Diferencia</span>
                                <span class="text-sm font-bold flex items-center gap-1 justify-end" :class="getDiferenciaColor(liquidacion.dIfVendedorConfirma)">
                                    <i :class="getDiferenciaIcono(liquidacion.dIfVendedorConfirma)" class="text-[10px]"></i>
                                    {{ formatearNumero(liquidacion.dIfVendedorConfirma) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="liquidaciones.data?.length === 0" class="bg-white rounded-lg shadow-sm p-6 text-center">
                        <i class="fas fa-file-invoice text-2xl sm:text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-xs text-gray-400">No hay liquidaciones registradas</p>
                    </div>
                </div>

                <!-- TABLA TABLET Y ESCRITORIO -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left text-[10px] sm:text-xs font-medium text-primary-700 uppercase">N° Liq.</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left text-[10px] sm:text-xs font-medium text-primary-700 uppercase">Operador</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left text-[10px] sm:text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left text-[10px] sm:text-xs font-medium text-primary-700 uppercase">N° Diario</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right text-[10px] sm:text-xs font-medium text-primary-700 uppercase">Total Ventas</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right text-[10px] sm:text-xs font-medium text-primary-700 uppercase">Diferencia</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right text-[10px] sm:text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="liquidacion in liquidaciones.data" :key="liquidacion.iDLiquidacionVendedor" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs">
                                        <span class="font-mono font-bold text-primary-600">{{ liquidacion.iDLiquidacionVendedor }}</span>
                                    </td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs">
                                        <span class="font-medium text-gray-700 truncate block max-w-[120px] sm:max-w-[160px] lg:max-w-none">
                                            {{ liquidacion.nombre_operador || 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs text-gray-500 whitespace-nowrap">
                                        {{ liquidacion.fecha_formateada }} <!-- 🔥 USAR fecha_formateada -->
                                    </td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs">
                                        <span 
                                            @click="abrirPdfDiario(liquidacion.IdDiario)"
                                            class="font-mono text-blue-600 cursor-pointer hover:text-blue-800 hover:underline inline-flex items-center gap-1"
                                            :class="{ 'opacity-50 cursor-not-allowed': !liquidacion.IdDiario || liquidacion.IdDiario === 0 }"
                                        >
                                            <i class="fas fa-book-open text-[8px] sm:text-[10px]"></i>
                                            {{ liquidacion.numero_diario || '-' }}
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs text-right font-semibold text-primary-600 whitespace-nowrap">
                                        {{ formatearNumero(liquidacion.vEntasConfirma) }}
                                    </td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs text-right">
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap" :class="getDiferenciaColor(liquidacion.dIfVendedorConfirma)">
                                            <i :class="getDiferenciaIcono(liquidacion.dIfVendedorConfirma)" class="text-[8px] sm:text-[10px]"></i>
                                            {{ formatearNumero(liquidacion.dIfVendedorConfirma) }}
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-right">
                                        <button 
                                            @click="reimprimirPDF(liquidacion.iDLiquidacionVendedor)" 
                                            class="text-primary-600 hover:text-primary-800 transition-colors p-1 hover:bg-primary-50 rounded"
                                            title="Reimprimir"
                                        >
                                            <i class="fas fa-print text-xs sm:text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="liquidaciones.data?.length === 0">
                                    <td colspan="7" class="px-3 sm:px-4 py-6 sm:py-8 text-center text-gray-400 text-[10px] sm:text-xs">
                                        <i class="fas fa-file-invoice text-xl sm:text-2xl mb-1 block"></i>
                                        No hay liquidaciones registradas en esta sucursal
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINACIÓN -->
                    <div v-if="liquidaciones.links && liquidaciones.links.length > 1" class="px-2 sm:px-3 py-1.5 sm:py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col xs:flex-row justify-between items-center gap-1.5 sm:gap-2 text-[10px] sm:text-xs">
                            <div class="text-gray-500 text-[9px] sm:text-xs">
                                <span class="hidden xs:inline">Mostrando </span>
                                {{ liquidaciones.from || 0 }} - {{ liquidaciones.to || 0 }} 
                                <span class="hidden xs:inline">de {{ liquidaciones.total || 0 }}</span>
                            </div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link 
                                    v-for="link in liquidaciones.links" 
                                    :key="link.label" 
                                    :href="link.url || '#'" 
                                    class="px-1.5 sm:px-2 py-0.5 rounded border text-[9px] sm:text-xs min-w-[24px] sm:min-w-[28px] text-center"
                                    :class="{ 
                                        'bg-primary-600 text-white border-primary-600': link.active, 
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
@media (max-width: 480px) {
    .xs\:inline { display: inline !important; }
    .xs\:hidden { display: none !important; }
}

@media (min-width: 481px) {
    .xs\:inline { display: inline !important; }
    .xs\:hidden { display: none !important; }
}

@media (max-width: 640px) {
    button, 
    [role="button"],
    .cursor-pointer {
        min-height: 36px;
        min-width: 36px;
    }
    
    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
    }
}

.transition-colors {
    transition-property: background-color, border-color, color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.overflow-x-auto::-webkit-scrollbar {
    height: 4px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 2px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>