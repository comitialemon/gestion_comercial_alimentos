<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4">
        <div class="absolute inset-0 bg-black/50" @click="cerrar"></div>
        
        <div class="relative bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-auto max-h-[95vh] sm:max-h-[90vh] overflow-hidden flex flex-col">
            
            <!-- Header -->
            <div 
                class="p-3 sm:p-4 flex-shrink-0 text-white"
                :style="{ backgroundColor: exito ? 'var(--color-primary-600, #059669)' : 'var(--color-red-600, #dc2626)' }"
            >
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
                        <i class="fas text-lg sm:text-2xl flex-shrink-0" :class="exito ? 'fa-check-circle' : 'fa-times-circle'"></i>
                        <h3 class="font-bold text-sm sm:text-lg truncate">
                            {{ exito ? '✅ Procesado con éxito!' : '❌ Error al procesar' }}
                        </h3>
                    </div>
                    <button @click="cerrar" class="text-white hover:text-gray-200 transition flex-shrink-0">
                        <i class="fas fa-times text-lg sm:text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Cuerpo -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                <p class="text-sm sm:text-base text-gray-700 mb-4">{{ mensaje }}</p>
                
                <div v-if="exito && detalles">
                    
                    <!-- 🔥 Resumen -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="bg-primary-50 rounded-lg p-3 text-center" :style="{ backgroundColor: 'var(--color-primary-50, #eff6ff)' }">
                            <p class="text-xs text-gray-500">Facturas procesadas</p>
                            <p class="text-xl font-bold" :style="{ color: 'var(--color-primary-600, #2563eb)' }">
                                {{ detalles.total || 0 }}
                            </p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">Movimientos creados</p>
                            <p class="text-xl font-bold text-green-600">
                                {{ detalles.total_movimientos || 0 }}
                            </p>
                        </div>
                    </div>
                    
                    <!-- 🔥 Productos afectados -->
                    <div v-if="detalles.productos && detalles.productos.length > 0" class="bg-gray-50 rounded-lg p-3 sm:p-4 mb-4">
                        <h4 class="text-xs sm:text-sm font-bold text-gray-700 mb-2 sm:mb-3">
                            📦 Productos afectados
                            <span class="text-xs font-normal text-gray-400 ml-2">
                                ({{ detalles.productos.length }} productos)
                            </span>
                        </h4>
                        <div class="space-y-1.5 max-h-32 sm:max-h-40 overflow-y-auto">
                            <div v-for="producto in detalles.productos" :key="producto.nombre" 
                                class="flex flex-wrap items-center justify-between text-xs sm:text-sm py-1.5 border-b border-gray-100 last:border-0">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <i class="fas fa-box text-gray-400 text-[10px] sm:text-xs"></i>
                                    <span class="text-gray-700 truncate">{{ producto.nombre }}</span>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <span class="font-medium text-primary-700">
                                        {{ Number(producto.cantidad).toFixed(2) }} und
                                    </span>
                                    <span class="text-[10px] text-gray-400">
                                        {{ producto.facturas.length }} facturas
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista de facturas -->
                    <div v-if="detalles.facturas && detalles.facturas.length > 0" class="bg-gray-50 rounded-lg p-3 sm:p-4">
                        <h4 class="text-xs sm:text-sm font-bold text-gray-700 mb-2 sm:mb-3">
                            📊 Facturas procesadas
                            <span class="text-xs font-normal text-gray-400 ml-2">
                                ({{ detalles.facturas.length }} facturas)
                            </span>
                        </h4>
                        <div class="space-y-1 max-h-32 sm:max-h-40 overflow-y-auto">
                            <div v-for="factura in detalles.facturas" :key="factura.id" 
                                class="flex flex-wrap items-center justify-between text-xs sm:text-sm py-1 border-b border-gray-100 last:border-0">
                                <span class="text-gray-600">Factura N° {{ factura.numero }}</span>
                                <div class="flex items-center gap-3">
                                    <span :class="factura.estado === 'Válida' ? 'text-green-600' : 'text-yellow-600'">
                                        {{ factura.estado }}
                                    </span>
                                    <span class="text-[10px] text-gray-400">
                                        {{ factura.movimientos || 0 }} mov.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Errores -->
                    <div v-if="detalles.errores && detalles.errores.length > 0" class="mt-3">
                        <h5 class="text-xs sm:text-sm font-bold text-red-600 mb-2">⚠️ Errores:</h5>
                        <div class="space-y-1 max-h-32 sm:max-h-40 overflow-y-auto">
                            <div v-for="(error, index) in detalles.errores" :key="index" 
                                class="text-xs sm:text-sm text-red-600 py-1 border-b border-gray-100 last:border-0">
                                {{ error }}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mensaje de error sin detalles -->
                <div v-else-if="!exito" class="text-center py-4">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-3 block"></i>
                    <p class="text-sm text-gray-600">No se pudo completar el reprocesamiento.</p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="border-t p-3 sm:p-4 bg-gray-50 flex justify-end flex-shrink-0">
                <button 
                    @click="cerrar"
                    class="px-4 sm:px-6 py-1.5 sm:py-2 rounded-lg text-white text-xs sm:text-sm font-medium hover:opacity-90 transition"
                    :style="{ backgroundColor: exito ? 'var(--color-primary-600, #059669)' : 'var(--color-red-600, #dc2626)' }"
                >
                    {{ exito ? '✅ Aceptar' : '❌ Cerrar' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    exito: {
        type: Boolean,
        default: true
    },
    mensaje: {
        type: String,
        default: ''
    },
    detalles: {
        type: Object,
        default: () => ({
            total: 0,
            total_movimientos: 0,
            facturas: [],
            productos: [],
            errores: []
        })
    }
})

const emit = defineEmits(['close'])

const cerrar = () => {
    emit('close')
}
</script>

<style scoped>
.bg-primary-50 {
    background-color: var(--color-primary-50, #eff6ff);
}
.text-primary-600 {
    color: var(--color-primary-600, #2563eb);
}
.text-primary-700 {
    color: var(--color-primary-700, #1e40af);
}

.fixed {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
</style>