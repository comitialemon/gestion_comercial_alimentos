<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import axios from 'axios'
import ModalEditarPagos from './components/ModalEditarPagos.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    facturas: Array,
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
const modalOpen = ref(false)
const ventaSeleccionada = ref(null)

// ==================== COMPUTED ====================
const hayFacturas = computed(() => {
    return props.facturas && props.facturas.length > 0
})

// ==================== FUNCIONES ====================
const abrirModal = async (venta) => {
    ventaSeleccionada.value = venta
    modalOpen.value = true
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

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-5xl mx-auto">
                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-credit-card text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Mantenimiento de Métodos de Pago</h1>
                        <p class="text-xs text-gray-500">Modificar los métodos de pago de facturas no liquidadas</p>
                    </div>
                </div>

                <!-- ==================== TABLA ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="factura in facturas" :key="factura.IdVentas" 
                                @click="abrirModal(factura)"
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100 cursor-pointer hover:bg-gray-100 transition">
                                <div class="flex justify-between items-start">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-mono font-bold text-gray-800">#{{ factura.NumeroFactura }}</p>
                                        <p class="text-[10px] text-gray-500">{{ formatearFecha(factura.FechaVenta) }}</p>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-2">
                                        <p class="text-xs font-bold text-primary-600">{{ formatearNumero(factura.ImporteVenta) }} Bs</p>
                                        <button @click.stop="abrirModal(factura)" class="text-primary-600 hover:text-primary-800 text-[10px] p-1">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!hayFacturas" class="text-center text-gray-400 py-8">
                                <i class="fas fa-credit-card text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay facturas pendientes para modificar</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO (tabla con STICKY HEADER) -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">N° Factura</th>
                                    <th class="px-3 py-1.5 text-right text-[9px] font-medium text-primary-700 uppercase w-24">Total</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase w-12">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr 
                                    v-for="factura in facturas" 
                                    :key="factura.IdVentas"
                                    class="hover:bg-gray-50 cursor-pointer transition"
                                    @click="abrirModal(factura)"
                                >
                                    <td class="px-3 py-2 text-xs text-gray-600">{{ formatearFecha(factura.FechaVenta) }}</td>
                                    <td class="px-3 py-2 text-xs font-mono text-gray-900">#{{ factura.NumeroFactura }}</td>
                                    <td class="px-3 py-2 text-xs text-right font-semibold text-primary-600">
                                        {{ formatearNumero(factura.ImporteVenta) }} Bs
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button 
                                            @click.stop="abrirModal(factura)" 
                                            class="text-primary-600 hover:text-primary-800 text-xs p-1 rounded hover:bg-primary-50 transition"
                                            title="Editar métodos de pago"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!hayFacturas">
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-credit-card text-2xl mb-1 block"></i>
                                        No hay facturas pendientes para modificar
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="mt-3 text-center text-[8px] text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Solo se pueden modificar métodos de pago de facturas <strong>no liquidadas</strong>.
                </div>
            </div>
        </div>

        <!-- ==================== MODAL ==================== -->
        <ModalEditarPagos
            v-model="modalOpen"
            :venta="ventaSeleccionada"
            @actualizado="modalOpen = false"
        />
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>