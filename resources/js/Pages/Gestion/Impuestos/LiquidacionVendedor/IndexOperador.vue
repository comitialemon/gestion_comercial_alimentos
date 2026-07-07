<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    liquidaciones: Object,
    titulo: String,
    subtitulo: String,
    nombreOperador: String,
})

// 🔥 DETECTAR TAMAÑO DE PANTALLA
const windowWidth = ref(window.innerWidth)
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

// 🔥 Abrir PDF del diario (al hacer clic en el número de diario)
const abrirPdfDiario = (idDiario) => {
    if (idDiario && idDiario > 0) {
        window.open(`/gestion/contabilidad/imprimir-diario/pdf/${idDiario}`, '_blank')
    } else {
        const toast = document.createElement('div')
        toast.className = 'fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white bg-yellow-500 flex items-center gap-2'
        toast.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Esta liquidación no tiene un diario asociado'
        document.body.appendChild(toast)
        setTimeout(() => toast.remove(), 2000)
    }
}

// Formatear signo de diferencia
const getDiferenciaColor = (valor) => {
    return valor >= 0 ? 'text-emerald-600' : 'text-red-600'
}

const getDiferenciaIcono = (valor) => {
    return valor >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'
}

// 🔥 COMPUTADOS PARA ESTADÍSTICAS
const totalLiquidaciones = computed(() => {
    return props.liquidaciones?.total || 0
})

const totalVentasOperador = computed(() => {
    if (!props.liquidaciones?.data) return 0
    return props.liquidaciones.data.reduce((sum, item) => sum + (item.vEntasConfirma || 0), 0)
})

const totalDiferenciaOperador = computed(() => {
    if (!props.liquidaciones?.data) return 0
    return props.liquidaciones.data.reduce((sum, item) => sum + (item.dIfVendedorConfirma || 0), 0)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-5">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header Responsive -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-3 mb-3 sm:mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-check text-primary-600 text-xs sm:text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-sm sm:text-base lg:text-lg font-bold text-gray-800">{{ titulo }}</h1>
                            <p class="text-[9px] sm:text-[10px] text-gray-500 truncate max-w-[180px] sm:max-w-none">{{ subtitulo }}</p>
                            <p v-if="nombreOperador" class="text-[9px] sm:text-[10px] text-primary-600">Operador: {{ nombreOperador }}</p>
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
                            {{ formatearNumero(totalVentasOperador) }}
                        </span>
                        <span class="bg-blue-50 text-blue-700 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full whitespace-nowrap flex items-center gap-1">
                            <i class="fas fa-arrows-alt-h text-[8px] sm:text-[10px]"></i>
                            <span class="hidden xs:inline">Dif: </span>
                            <span :class="getDiferenciaColor(totalDiferenciaOperador)">
                                {{ formatearNumero(totalDiferenciaOperador) }}
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Vista MÓVIL (tarjetas) -->
                <div v-if="isMobile" class="space-y-2.5">
                    <div v-for="liquidacion in liquidaciones.data" :key="liquidacion.iDLiquidacionVendedor" class="bg-white rounded-lg shadow-sm p-3">
                        <!-- Cabecera de tarjeta -->
                        <div class="flex justify-between items-start border-b border-gray-100 pb-2 mb-2">
                            <div>
                                <span class="text-xs font-mono font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded">
                                    N° {{ liquidacion.iDLiquidacionVendedor }}
                                </span>
                                <span class="text-[9px] text-gray-400 ml-1.5">{{ liquidacion.fecha_formateada }}</span>
                            </div>
                            <div>
                                <button 
                                    @click="reimprimirPDF(liquidacion.iDLiquidacionVendedor)" 
                                    class="text-primary-600 hover:text-primary-800"
                                    title="Reimprimir"
                                >
                                    <i class="fas fa-print text-xs sm:text-sm"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Datos principales -->
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] text-gray-500">N° Diario:</span>
                                <span 
                                    @click="abrirPdfDiario(liquidacion.IdDiario)"
                                    class="text-[10px] font-mono text-blue-600 cursor-pointer hover:text-blue-800 hover:underline inline-flex items-center gap-1"
                                    :class="{ 'opacity-50 cursor-not-allowed': !liquidacion.IdDiario || liquidacion.IdDiario === 0 }"
                                >
                                    <i class="fas fa-book-open text-[8px]"></i>
                                    {{ liquidacion.numero_diario || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] text-gray-500">Total Ventas:</span>
                                <span class="text-xs font-bold text-primary-600">{{ formatearNumero(liquidacion.vEntasConfirma) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] text-gray-500">Diferencia:</span>
                                <span class="text-xs font-bold flex items-center gap-1" :class="getDiferenciaColor(liquidacion.dIfVendedorConfirma)">
                                    <i :class="getDiferenciaIcono(liquidacion.dIfVendedorConfirma)" class="text-[8px]"></i>
                                    {{ formatearNumero(liquidacion.dIfVendedorConfirma) }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Estado -->
                        <div class="mt-2 pt-2 border-t border-gray-100 flex justify-end">
                            <span class="px-1.5 py-0.5 text-[9px] rounded-full bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-0.5 text-[7px]"></i> Contabilizada
                            </span>
                        </div>
                    </div>
                    
                    <div v-if="liquidaciones.data?.length === 0" class="bg-white rounded-lg shadow-sm p-6 text-center">
                        <i class="fas fa-file-invoice text-2xl sm:text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-xs text-gray-400">No hay liquidaciones registradas</p>
                    </div>
                </div>

                <!-- Vista ESCRITORIO (tabla) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left text-[10px] sm:text-xs font-medium text-primary-700 uppercase">N° Liq.</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left text-[10px] sm:text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left text-[10px] sm:text-xs font-medium text-primary-700 uppercase">N° Diario</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right text-[10px] sm:text-xs font-medium text-primary-700 uppercase">Total Ventas</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right text-[10px] sm:text-xs font-medium text-primary-700 uppercase">Diferencia</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-center text-[10px] sm:text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right text-[10px] sm:text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="liquidacion in liquidaciones.data" :key="liquidacion.iDLiquidacionVendedor" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs">
                                        <span class="font-mono font-bold text-primary-600">{{ liquidacion.iDLiquidacionVendedor }}</span>
                                    </td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-xs text-gray-500 whitespace-nowrap">
                                        {{ liquidacion.fecha_formateada }}
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
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[9px] sm:text-[10px] rounded-full whitespace-nowrap bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-0.5 text-[7px] sm:text-[8px]"></i> Contabilizada
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-right">
                                        <button 
                                            @click="reimprimirPDF(liquidacion.iDLiquidacionVendedor)" 
                                            class="text-primary-600 hover:text-primary-800 transition-colors p-1 hover:bg-primary-50 rounded"
                                            title="Reimprimir liquidación"
                                        >
                                            <i class="fas fa-print text-xs sm:text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="liquidaciones.data?.length === 0">
                                    <td colspan="7" class="px-3 sm:px-4 py-6 sm:py-8 text-center text-gray-400 text-[10px] sm:text-xs">
                                        <i class="fas fa-file-invoice text-xl sm:text-2xl mb-1 block"></i>
                                        No hay liquidaciones registradas
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación desktop -->
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

                <!-- Paginación móvil -->
                <div v-if="isMobile && liquidaciones.links && liquidaciones.links.length > 1" class="mt-3 bg-white rounded-lg shadow-sm p-2">
                    <div class="flex justify-center gap-0.5 flex-wrap">
                        <Link 
                            v-for="link in liquidaciones.links" 
                            :key="link.label" 
                            :href="link.url || '#'" 
                            class="px-1.5 sm:px-2 py-0.5 rounded border text-[9px] sm:text-xs min-w-[28px] text-center"
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
</template>

<style scoped>
/* 🔥 BREAKPOINTS PERSONALIZADOS */
@media (max-width: 480px) {
    .xs\:inline { display: inline !important; }
    .xs\:hidden { display: none !important; }
}

@media (min-width: 481px) {
    .xs\:inline { display: inline !important; }
    .xs\:hidden { display: none !important; }
}

/* 🔥 MEJORAS DE TOUCH EN MÓVIL */
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

/* 🔥 ANIMACIONES SUAVES */
.transition-colors {
    transition-property: background-color, border-color, color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

/* 🔥 MEJORAR SCROLL EN TABLAS */
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