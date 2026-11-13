<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'

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

// ==================== COMPUTED ====================
const sumaSueltos = computed(() => {
    return sueltos.value.reduce((acc, d) => acc + Number(d.cantidad_base || 0), 0)
})

const sumaCompuestos = computed(() => {
    return compuestos.value.reduce((acc, d) => acc + Number(d.cantidad_base || 0), 0)
})

// Diferencia entre venta_suelto y la suma de detalles sueltos
const diferenciaSuelto = computed(() => {
    const ventaSuelto = Number(props.producto?.venta_suelto || 0)
    return Math.max(0, ventaSuelto - sumaSueltos.value)
})

// Indica si hay que mostrar la línea virtual de suelto
const mostrarSueltoVirtual = computed(() => {
    return diferenciaSuelto.value > 0
})

// Indica si NO hay ningún detalle
const sinDetalles = computed(() => {
    return sueltos.value.length === 0 && compuestos.value.length === 0 && !mostrarSueltoVirtual.value
})

// Total Real
const totalReal = computed(() => {
    return Number(props.producto?.total_vendido || 0)
})

// ==================== FUNCIONES ====================
const cerrar = () => {
    emit('update:visible', false)
    emit('close')
}

const formatNumber = (num) => {
    if (num === undefined || num === null || num === 0) return '0'
    const n = Number(num)
    return Number.isInteger(n) ? n.toString() : n.toFixed(2)
}

// ==================== WATCHERS ====================
watch(() => props.producto, (newVal) => {
    if (newVal && newVal.detalles_venta) {
        sueltos.value = newVal.detalles_venta.filter(d => d.es_suelto === true)
        compuestos.value = newVal.detalles_venta.filter(d => d.es_suelto === false)
    } else {
        sueltos.value = []
        compuestos.value = []
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
                        <p class="text-[8px] text-gray-400 uppercase tracking-wide">Total Real</p>
                        <p class="text-lg font-bold text-primary-700">{{ formatNumber(totalReal) }}</p>
                    </div>
                </div>

                <!-- Info validación -->
                <div class="bg-gray-50 rounded-lg px-3 py-1.5 border border-gray-200">
                    <p class="text-[10px] text-gray-600">
                        <i class="fas fa-info-circle text-gray-400"></i>
                        Desglose: {{ formatNumber(producto?.venta_suelto) }} sueltos + {{ formatNumber(sumaCompuestos) }} en combos
                        = {{ formatNumber(Number(producto?.venta_suelto || 0) + sumaCompuestos) }}
                    </p>
                </div>

                <!-- VISTA MÓVIL: tarjetas -->
                <div v-if="isMobile">
                    <!-- Sueltos -->
                    <div v-if="sueltos.length > 0 || mostrarSueltoVirtual" class="border border-emerald-200 rounded-lg overflow-hidden">
                        <div class="bg-emerald-50 px-3 py-1.5 border-b border-emerald-200 flex justify-between items-center">
                            <span class="text-[10px] font-medium text-emerald-700">
                                <i class="fas fa-box text-emerald-600 mr-1 text-[9px]"></i> Producto Suelto
                            </span>
                            <span class="text-xs font-bold text-emerald-600">{{ formatNumber(producto?.venta_suelto) }}</span>
                        </div>
                        <div v-for="item in sueltos" :key="item.id_producto_venta" class="px-3 py-1.5 border-b border-gray-100 last:border-b-0 flex justify-between items-center">
                            <span class="text-xs text-gray-800 truncate max-w-[120px]" :title="item.nombre">{{ item.nombre }}</span>
                            <span class="text-xs font-bold text-emerald-600">{{ formatNumber(item.cantidad_base) }}</span>
                        </div>
                        <!-- Línea virtual con nombre del producto -->
                        <div v-if="mostrarSueltoVirtual" class="px-3 py-1.5 flex justify-between items-center bg-emerald-50/50">
                            <span class="text-xs text-gray-800 truncate max-w-[120px]" :title="producto?.Descripcion">{{ producto?.Descripcion }}</span>
                            <span class="text-xs font-bold text-emerald-600">{{ formatNumber(diferenciaSuelto) }}</span>
                        </div>
                    </div>

                    <!-- Compuestos -->
                    <div v-if="compuestos.length > 0" class="border border-blue-200 rounded-lg overflow-hidden mt-2">
                        <div class="bg-blue-50 px-3 py-1.5 border-b border-blue-200 flex justify-between items-center">
                            <span class="text-[10px] font-medium text-blue-700">
                                <i class="fas fa-layer-group text-blue-600 mr-1 text-[9px]"></i> Combos
                            </span>
                            <span class="text-xs font-bold text-blue-600">{{ formatNumber(sumaCompuestos) }}</span>
                        </div>
                        <div v-for="item in compuestos" :key="item.id_producto_venta" class="px-3 py-1.5 border-b border-gray-100 last:border-b-0">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-800 truncate max-w-[100px]" :title="item.nombre">{{ item.nombre }}</span>
                                <span class="text-xs font-bold text-blue-600">{{ formatNumber(item.cantidad_base) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VISTA TABLET Y ESCRITORIO -->
                <div v-else>
                    <!-- Sueltos -->
                    <div v-if="sueltos.length > 0 || mostrarSueltoVirtual" class="border border-emerald-200 rounded-lg overflow-hidden">
                        <div class="bg-emerald-50 px-3 py-1.5 border-b border-emerald-200 flex justify-between items-center">
                            <span class="text-xs font-medium text-emerald-700">
                                <i class="fas fa-box text-emerald-600 mr-1.5 text-[10px]"></i> Producto Suelto
                            </span>
                            <span class="text-xs font-bold text-emerald-600">{{ formatNumber(producto?.venta_suelto) }} unidades</span>
                        </div>
                        <div v-for="item in sueltos" :key="item.id_producto_venta" class="px-3 py-1.5 border-b border-gray-100 last:border-b-0 flex justify-between items-center">
                            <div class="flex-1 min-w-0">
                                <span class="text-xs font-medium text-gray-800">{{ item.nombre }}</span>
                                <span class="text-[9px] text-gray-400 ml-2">(Código: {{ item.codigo || producto?.Codigo || '-' }})</span>
                            </div>
                            <span class="text-xs font-bold text-emerald-600 flex-shrink-0 ml-2">{{ formatNumber(item.cantidad_base) }} unid.</span>
                        </div>
                        <!-- Línea virtual con nombre del producto -->
                        <div v-if="mostrarSueltoVirtual" class="px-3 py-1.5 flex justify-between items-center bg-emerald-50/50">
                            <div class="flex-1 min-w-0">
                                <span class="text-xs font-medium text-gray-800">{{ producto?.Descripcion }}</span>
                                <span class="text-[9px] text-gray-400 ml-2">(Código: {{ producto?.Codigo || '-' }})</span>
                            </div>
                            <span class="text-xs font-bold text-emerald-600 flex-shrink-0 ml-2">{{ formatNumber(diferenciaSuelto) }} unid.</span>
                        </div>
                    </div>

                    <!-- Compuestos -->
                    <div v-if="compuestos.length > 0" class="border border-blue-200 rounded-lg overflow-hidden mt-2">
                        <div class="bg-blue-50 px-3 py-1.5 border-b border-blue-200 flex justify-between items-center">
                            <span class="text-xs font-medium text-blue-700">
                                <i class="fas fa-layer-group text-blue-600 mr-1.5 text-[10px]"></i> En Combo/Pack
                            </span>
                            <span class="text-xs font-bold text-blue-600">{{ formatNumber(sumaCompuestos) }} unidades</span>
                        </div>
                        <div v-for="item in compuestos" :key="item.id_producto_venta" class="px-3 py-1.5 border-b border-gray-100 last:border-b-0">
                            <div class="flex justify-between items-center">
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs font-medium text-gray-800">{{ item.nombre }}</span>
                                    <span class="text-[9px] text-gray-400 ml-2">(Código: {{ item.codigo || '-' }})</span>
                                </div>
                                <span class="text-xs font-bold text-blue-600 flex-shrink-0 ml-2">{{ formatNumber(item.cantidad_base) }} unid.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sin datos -->
                <div v-if="sinDetalles"
                    class="text-center text-gray-400 py-6">
                    <i class="fas fa-box-open text-2xl mb-1 block"></i>
                    <span class="text-sm">No hay desglose disponible para este producto</span>
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