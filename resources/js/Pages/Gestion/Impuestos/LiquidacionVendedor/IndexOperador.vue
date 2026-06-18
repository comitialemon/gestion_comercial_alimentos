<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    liquidaciones: Object,
    titulo: String,
    subtitulo: String,
    nombreOperador: String,
})

const isMobile = ref(window.innerWidth < 768)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

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
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header Responsive -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-invoice-dollar text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">{{ titulo }}</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">{{ subtitulo }}</p>
                            <p v-if="nombreOperador" class="text-[10px] text-primary-600">Operador: {{ nombreOperador }}</p>
                        </div>
                    </div>
                </div>

                <!-- Vista MÓVIL (tarjetas) -->
                <div v-if="isMobile" class="space-y-3">
                    <div v-for="liquidacion in liquidaciones.data" :key="liquidacion.iDLiquidacionVendedor" class="bg-white rounded-lg shadow-sm p-3">
                        <!-- Cabecera de tarjeta -->
                        <div class="flex justify-between items-start border-b pb-2 mb-2">
                            <div>
                                <span class="text-xs font-mono font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded">
                                    N° {{ liquidacion.iDLiquidacionVendedor }}
                                </span>
                            </div>
                            <div>
                                <button 
                                    @click="reimprimirPDF(liquidacion.iDLiquidacionVendedor)" 
                                    class="text-primary-600 hover:text-primary-800"
                                    title="Reimprimir"
                                >
                                    <i class="fas fa-print text-sm"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Datos principales -->
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Fecha:</span>
                                <span class="font-medium">{{ formatearFecha(liquidacion.fecha?.Fecha) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">N° Diario:</span>
                                <span 
                                    @click="abrirPdfDiario(liquidacion.IdDiario)"
                                    class="font-mono text-blue-600 cursor-pointer hover:text-blue-800 hover:underline inline-flex items-center gap-1"
                                    :class="{ 'opacity-50 cursor-not-allowed': !liquidacion.IdDiario || liquidacion.IdDiario === 0 }"
                                >
                                    <i class="fas fa-book-open text-[10px]"></i>
                                    {{ liquidacion.numero_diario || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Total Ventas:</span>
                                <span class="font-bold text-primary-600">{{ formatearNumero(liquidacion.vEntasConfirma) }} Bs</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Diferencia:</span>
                                <span class="font-bold flex items-center gap-1" :class="getDiferenciaColor(liquidacion.dIfVendedorConfirma)">
                                    <i :class="getDiferenciaIcono(liquidacion.dIfVendedorConfirma)" class="text-[10px]"></i>
                                    {{ formatearNumero(liquidacion.dIfVendedorConfirma) }} Bs
                                </span>
                            </div>
                            <div class="flex justify-end pt-1 border-t mt-1">
                                <span class="px-1.5 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-check-circle mr-0.5 text-[8px]"></i> Contabilizada
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="liquidaciones.data?.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-file-invoice text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-xs text-gray-400">No hay liquidaciones registradas</p>
                    </div>
                </div>

                <!-- Vista ESCRITORIO (tabla) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">N° Liquidación</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">N° Diario</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Total Ventas</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Diferencia</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="liquidacion in liquidaciones.data" :key="liquidacion.iDLiquidacionVendedor" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">
                                        {{ liquidacion.iDLiquidacionVendedor }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">
                                        {{ formatearFecha(liquidacion.fecha?.Fecha) }}
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
                                    <td class="px-3 py-2 text-xs text-right font-semibold text-primary-600">
                                        {{ formatearNumero(liquidacion.vEntasConfirma) }} Bs
                                    </td>
                                    <td class="px-3 py-2 text-xs text-right">
                                        <span class="inline-flex items-center gap-1" :class="getDiferenciaColor(liquidacion.dIfVendedorConfirma)">
                                            <i :class="getDiferenciaIcono(liquidacion.dIfVendedorConfirma)" class="text-[10px]"></i>
                                            {{ formatearNumero(liquidacion.dIfVendedorConfirma) }} Bs
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-0.5 text-[8px]"></i> Contabilizada
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button 
                                            @click="reimprimirPDF(liquidacion.iDLiquidacionVendedor)" 
                                            class="text-primary-600 hover:text-primary-800 transition-colors"
                                            title="Reimprimir liquidación"
                                        >
                                            <i class="fas fa-print text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="liquidaciones.data?.length === 0">
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-file-invoice text-2xl mb-1 block"></i>
                                        No hay liquidaciones registradas
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación desktop -->
                    <div v-if="liquidaciones.links && liquidaciones.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-xs">
                            <div class="text-gray-500">Mostrando {{ liquidaciones.from || 0 }} a {{ liquidaciones.to || 0 }} de {{ liquidaciones.total || 0 }}</div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link 
                                    v-for="link in liquidaciones.links" 
                                    :key="link.label" 
                                    :href="link.url || '#'" 
                                    class="px-2 py-0.5 rounded border text-xs"
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
                            class="px-2 py-1 rounded border text-xs min-w-[32px] text-center"
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
@media (max-width: 640px) {
    .xs\:inline {
        display: inline;
    }
    .xs\:block {
        display: block;
    }
}
</style>