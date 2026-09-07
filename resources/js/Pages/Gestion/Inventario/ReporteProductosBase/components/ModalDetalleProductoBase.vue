<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    producto: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['update:visible', 'close'])

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const sueltos = ref([])
const compuestos = ref([])

// ==================== FUNCIONES ====================
const cerrar = () => {
    emit('update:visible', false)
    emit('close')
}

const formatNumber = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(0)
}

// ==================== WATCHERS ====================
watch(() => props.producto, (newVal) => {
    if (newVal && newVal.detalles_venta) {
        sueltos.value = newVal.detalles_venta.filter(d => d.es_suelto === true)
        compuestos.value = newVal.detalles_venta.filter(d => d.es_suelto === false)
    }
}, { immediate: true })

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
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="cerrar"></div>
        
        <div class="relative bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-gray-200">
            
            <!-- ==================== HEADER ==================== -->
            <div class="p-3 flex-shrink-0 bg-primary-600 text-white rounded-t-xl">
                <div class="flex justify-between items-start">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-sm text-white">Desglose de Ventas</h3>
                        <p class="text-xs text-white/80 truncate" :title="producto?.Descripcion">{{ producto?.Descripcion || 'Producto' }}</p>
                        <p class="text-[10px] text-white/60">Código: {{ producto?.Codigo || '-' }}</p>
                    </div>
                    <button @click="cerrar" class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition flex-shrink-0 ml-2">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            </div>
            
            <!-- ==================== BODY ==================== -->
            <div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-3">
                
                <!-- Resumen -->
                <div class="grid grid-cols-3 gap-2">
                    <div class="bg-emerald-50 rounded-lg p-2 text-center border border-emerald-100">
                        <p class="text-[8px] text-gray-400 uppercase tracking-wide">Suelto</p>
                        <p class="text-lg font-bold text-emerald-600">{{ formatNumber(producto?.venta_suelto) }}</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-2 text-center border border-blue-100">
                        <p class="text-[8px] text-gray-400 uppercase tracking-wide">Combo/Pack</p>
                        <p class="text-lg font-bold text-blue-600">{{ formatNumber(producto?.venta_compuesta) }}</p>
                    </div>
                    <div class="bg-primary-50 rounded-lg p-2 text-center border border-primary-100">
                        <p class="text-[8px] text-gray-400 uppercase tracking-wide">Total</p>
                        <p class="text-lg font-bold text-primary-700">{{ formatNumber(producto?.total_vendido) }}</p>
                    </div>
                </div>

                <!-- VISTA MÓVIL: tarjetas -->
                <div v-if="isMobile">
                    <!-- Sueltos -->
                    <div v-if="sueltos.length > 0" class="border border-emerald-200 rounded-lg overflow-hidden">
                        <div class="bg-emerald-50 px-3 py-1.5 border-b border-emerald-200 flex justify-between items-center">
                            <span class="text-[10px] font-medium text-emerald-700">
                                <i class="fas fa-box text-emerald-600 mr-1 text-[9px]"></i> Sueltos
                            </span>
                            <span class="text-xs font-bold text-emerald-600">{{ formatNumber(producto?.venta_suelto) }}</span>
                        </div>
                        <div v-for="item in sueltos" :key="item.id_producto_venta" class="px-3 py-1.5 border-b border-gray-100 last:border-b-0 flex justify-between items-center">
                            <span class="text-xs text-gray-800 truncate max-w-[120px]" :title="item.nombre">{{ item.nombre }}</span>
                            <span class="text-xs font-bold text-emerald-600">{{ formatNumber(item.cantidad_base) }}</span>
                        </div>
                    </div>

                    <!-- Compuestos -->
                    <div v-if="compuestos.length > 0" class="border border-blue-200 rounded-lg overflow-hidden">
                        <div class="bg-blue-50 px-3 py-1.5 border-b border-blue-200 flex justify-between items-center">
                            <span class="text-[10px] font-medium text-blue-700">
                                <i class="fas fa-layer-group text-blue-600 mr-1 text-[9px]"></i> Combos
                            </span>
                            <span class="text-xs font-bold text-blue-600">{{ formatNumber(producto?.venta_compuesta) }}</span>
                        </div>
                        <div v-for="item in compuestos" :key="item.id_producto_venta" class="px-3 py-1.5 border-b border-gray-100 last:border-b-0">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-800 truncate max-w-[100px]" :title="item.nombre">{{ item.nombre }}</span>
                                <span class="text-xs font-bold text-blue-600">{{ formatNumber(item.cantidad_base) }}</span>
                            </div>
                            <div class="flex gap-2 text-[8px] text-gray-400 mt-0.5">
                                <span>Porción: {{ item.porcion }}</span>
                                <span>Vendidos: {{ formatNumber(item.unidades_vendidas) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VISTA TABLET Y ESCRITORIO: tabla -->
                <div v-else>
                    <!-- Sueltos -->
                    <div v-if="sueltos.length > 0" class="border border-emerald-200 rounded-lg overflow-hidden">
                        <div class="bg-emerald-50 px-3 py-1.5 border-b border-emerald-200 flex justify-between items-center">
                            <span class="text-xs font-medium text-emerald-700">
                                <i class="fas fa-box text-emerald-600 mr-1.5 text-[10px]"></i> Venta Suelta
                            </span>
                            <span class="text-xs font-bold text-emerald-600">{{ formatNumber(producto?.venta_suelto) }} unidades</span>
                        </div>
                        <div v-for="item in sueltos" :key="item.id_producto_venta" class="px-3 py-1.5 border-b border-gray-100 last:border-b-0 flex justify-between items-center">
                            <div class="flex-1 min-w-0">
                                <span class="text-xs font-medium text-gray-800">{{ item.nombre }}</span>
                                <span class="text-[9px] text-gray-400 ml-2">(Código: {{ item.codigo || '-' }})</span>
                            </div>
                            <span class="text-xs font-bold text-emerald-600 flex-shrink-0 ml-2">{{ formatNumber(item.cantidad_base) }} unid.</span>
                        </div>
                    </div>

                    <!-- Compuestos -->
                    <div v-if="compuestos.length > 0" class="border border-blue-200 rounded-lg overflow-hidden">
                        <div class="bg-blue-50 px-3 py-1.5 border-b border-blue-200 flex justify-between items-center">
                            <span class="text-xs font-medium text-blue-700">
                                <i class="fas fa-layer-group text-blue-600 mr-1.5 text-[10px]"></i> En Combo/Pack
                            </span>
                            <span class="text-xs font-bold text-blue-600">{{ formatNumber(producto?.venta_compuesta) }} unidades</span>
                        </div>
                        <div v-for="item in compuestos" :key="item.id_producto_venta" class="px-3 py-1.5 border-b border-gray-100 last:border-b-0">
                            <div class="flex justify-between items-center">
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs font-medium text-gray-800">{{ item.nombre }}</span>
                                    <span class="text-[9px] text-gray-400 ml-2">(Código: {{ item.codigo || '-' }})</span>
                                </div>
                                <span class="text-xs font-bold text-blue-600 flex-shrink-0 ml-2">{{ formatNumber(item.cantidad_base) }} unid.</span>
                            </div>
                            <div class="flex gap-3 mt-0.5 text-[9px] text-gray-500">
                                <span>Porción por unidad: {{ item.porcion }}</span>
                                <span>Unidades vendidas: {{ formatNumber(item.unidades_vendidas) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sin datos -->
                <div v-if="(!sueltos.length && !compuestos.length) || !producto?.detalles_venta?.length" 
                    class="text-center text-gray-400 py-6">
                    <i class="fas fa-box-open text-2xl mb-1 block"></i>
                    <span class="text-sm">No hay ventas registradas para este producto</span>
                </div>
            </div>
            
            <!-- ==================== FOOTER ==================== -->
            <div class="border-t border-gray-200 p-2.5 bg-gray-50 flex justify-end flex-shrink-0 rounded-b-xl">
                <button @click="cerrar" 
                    class="px-3 py-1 bg-primary-600 text-white font-medium rounded-md text-xs hover:opacity-90 transition">
                    Cerrar
                </button>
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