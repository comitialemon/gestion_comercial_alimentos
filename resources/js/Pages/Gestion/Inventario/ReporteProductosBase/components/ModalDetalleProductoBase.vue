<!-- resources/js/Pages/Gestion/Inventario/ReporteProductosBase/components/ModalDetalleProductoBase.vue -->

<script setup>
import { ref, watch } from 'vue'

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

const cerrar = () => {
    emit('update:visible', false)
    emit('close')
}

const formatNumber = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(0)
}

// Separar productos sueltos y compuestos
const sueltos = ref([])
const compuestos = ref([])

watch(() => props.producto, (newVal) => {
    if (newVal && newVal.detalles_venta) {
        sueltos.value = newVal.detalles_venta.filter(d => d.es_suelto === true)
        compuestos.value = newVal.detalles_venta.filter(d => d.es_suelto === false)
    }
}, { immediate: true })
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="cerrar"></div>
        
        <div class="relative bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-gray-100">
            
            <!-- Header -->
            <div class="p-4 flex-shrink-0" style="background-color: var(--color-primary, #61131a)">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-bold text-base text-white">Desglose de Ventas</h3>
                        <p class="text-sm text-white/80">{{ producto?.Descripcion || 'Producto' }}</p>
                        <p class="text-xs text-white/60">Código: {{ producto?.Codigo || '-' }}</p>
                    </div>
                    <button @click="cerrar" class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Cuerpo -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
                
                <!-- Resumen -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-emerald-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-500">Venta Suelta</p>
                        <p class="text-xl font-bold text-emerald-600">{{ formatNumber(producto?.venta_suelto) }}</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-500">En Combo/Pack</p>
                        <p class="text-xl font-bold text-blue-600">{{ formatNumber(producto?.venta_compuesta) }}</p>
                    </div>
                    <div class="bg-primary-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-500">Total</p>
                        <p class="text-xl font-bold" style="color: var(--color-primary, #61131a)">{{ formatNumber(producto?.total_vendido) }}</p>
                    </div>
                </div>

                <!-- Productos Sueltos -->
                <div v-if="sueltos.length > 0" class="border border-emerald-200 rounded-lg overflow-hidden">
                    <div class="bg-emerald-50 px-4 py-2 border-b border-emerald-200 flex justify-between items-center">
                        <span class="text-sm font-medium text-emerald-700">
                            <i class="fas fa-box text-emerald-600 mr-2"></i>Venta Suelta
                        </span>
                        <span class="text-sm font-bold text-emerald-600">{{ formatNumber(producto?.venta_suelto) }} unidades</span>
                    </div>
                    <div v-for="item in sueltos" :key="item.id_producto_venta" class="px-4 py-2 border-b border-gray-100 last:border-b-0 flex justify-between items-center">
                        <div>
                            <span class="text-sm font-medium text-gray-800">{{ item.nombre }}</span>
                            <span class="text-xs text-gray-400 ml-2">(Código: {{ item.codigo || '-' }})</span>
                        </div>
                        <span class="text-sm font-bold text-emerald-600">{{ formatNumber(item.cantidad_base) }} unid.</span>
                    </div>
                </div>

                <!-- Productos Compuestos (Combos/Packs) -->
                <div v-if="compuestos.length > 0">
                    <div class="border border-blue-200 rounded-lg overflow-hidden">
                        <div class="bg-blue-50 px-4 py-2 border-b border-blue-200 flex justify-between items-center">
                            <span class="text-sm font-medium text-blue-700">
                                <i class="fas fa-layer-group text-blue-600 mr-2"></i>En Combo/Pack
                            </span>
                            <span class="text-sm font-bold text-blue-600">{{ formatNumber(producto?.venta_compuesta) }} unidades</span>
                        </div>
                        <div v-for="item in compuestos" :key="item.id_producto_venta" class="px-4 py-2 border-b border-gray-100 last:border-b-0">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="text-sm font-medium text-gray-800">{{ item.nombre }}</span>
                                    <span class="text-xs text-gray-400 ml-2">(Código: {{ item.codigo || '-' }})</span>
                                </div>
                                <span class="text-sm font-bold text-blue-600">{{ formatNumber(item.cantidad_base) }} unid.</span>
                            </div>
                            <div class="flex gap-4 mt-1 text-xs text-gray-500">
                                <span>Porción por unidad: {{ item.porcion }}</span>
                                <span>Unidades vendidas: {{ formatNumber(item.unidades_vendidas) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sin datos -->
                <div v-if="(!sueltos.length && !compuestos.length) || !producto?.detalles_venta?.length" 
                    class="text-center text-gray-400 py-8">
                    <i class="fas fa-box-open text-3xl mb-2 block"></i>
                    No hay ventas registradas para este producto
                </div>
            </div>
            
            <!-- Footer -->
            <div class="border-t border-gray-100 p-3 bg-gray-50 flex justify-end flex-shrink-0 rounded-b-xl">
                <button @click="cerrar" 
                    class="px-5 py-2 text-white font-medium rounded-lg text-sm transition hover:opacity-90"
                    style="background-color: var(--color-primary, #61131a)">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-primary-600 {
    background-color: var(--color-primary, #61131a) !important;
}
.text-primary-700 {
    color: var(--color-primary, #61131a) !important;
}
</style>