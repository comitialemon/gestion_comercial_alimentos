<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center">
        <!-- Fondo oscuro -->
        <div class="absolute inset-0 bg-black/50" @click="cerrar"></div>
        
        <!-- Modal -->
        <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <!-- Icono de éxito/error -->
            <div class="flex justify-center mb-4">
                <div 
                    class="w-16 h-16 rounded-full flex items-center justify-center"
                    :class="exito ? 'bg-green-100' : 'bg-red-100'"
                >
                    <i 
                        class="text-3xl"
                        :class="exito ? 'fas fa-check-circle text-green-600' : 'fas fa-times-circle text-red-600'"
                    ></i>
                </div>
            </div>
            
            <!-- Título -->
            <h3 class="text-lg font-bold text-center mb-2" :class="exito ? 'text-green-700' : 'text-red-700'">
                {{ exito ? '✅ ¡Reprocesado con éxito!' : '❌ Error al reprocesar' }}
            </h3>
            
            <!-- Fecha -->
            <div v-if="exito && fecha" class="text-center text-sm text-gray-600 mb-4">
                <span class="font-medium">Fecha de la venta:</span>
                <span class="font-bold text-primary-700">{{ fecha }}</span>
            </div>
            
            <!-- Mensaje -->
            <p class="text-sm text-gray-600 text-center mb-4">
                {{ mensaje }}
            </p>
            
            <!-- Detalles del reprocesamiento -->
            <div v-if="exito && detalles" class="bg-gray-50 rounded-lg p-4 mb-4">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">📊 Resumen del reprocesamiento</h4>
                
                <!-- Movimientos eliminados -->
                <div class="mb-3 pb-3 border-b border-gray-200">
                    <div class="flex items-center gap-2 text-sm text-red-600">
                        <i class="fas fa-trash-alt"></i>
                        <span class="font-medium">Movimientos eliminados:</span>
                        <span class="font-bold">{{ detalles.eliminados || 0 }}</span>
                    </div>
                    <div v-if="detalles.fecha_anterior" class="text-xs text-gray-500 ml-6">
                        Fecha anterior: {{ detalles.fecha_anterior }}
                    </div>
                </div>
                
                <!-- Movimientos insertados -->
                <div>
                    <div class="flex items-center gap-2 text-sm text-green-600">
                        <i class="fas fa-plus-circle"></i>
                        <span class="font-medium">Movimientos insertados:</span>
                        <span class="font-bold">{{ detalles.insertados || 0 }}</span>
                    </div>
                    <div v-if="detalles.fecha_nueva" class="text-xs text-gray-500 ml-6">
                        Nueva fecha: {{ detalles.fecha_nueva }}
                    </div>
                </div>
                
                <!-- Productos afectados -->
                <div v-if="detalles.productos && detalles.productos.length > 0" class="mt-3 pt-3 border-t border-gray-200">
                    <h5 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">📦 Productos afectados</h5>
                    <div class="space-y-1">
                        <div v-for="prod in detalles.productos" :key="prod.id" class="flex justify-between text-xs">
                            <span class="text-gray-600">{{ prod.nombre }}</span>
                            <span class="font-medium text-gray-700">{{ prod.cantidad }} unidades</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Botón de cerrar -->
            <button 
                @click="cerrar"
                class="w-full px-4 py-2 rounded-lg text-sm font-medium transition"
                :class="exito ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-red-600 hover:bg-red-700 text-white'"
            >
                {{ exito ? '✅ Aceptar' : '❌ Cerrar' }}
            </button>
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
    fecha: {
        type: String,
        default: ''
    },
    detalles: {
        type: Object,
        default: () => ({
            eliminados: 0,
            insertados: 0,
            fecha_anterior: '',
            fecha_nueva: '',
            productos: []
        })
    }
})

const emit = defineEmits(['close'])

const cerrar = () => {
    emit('close')
}
</script>

<style scoped>
.fixed {
    animation: fadeIn 0.3s ease-out;
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

/* Scroll suave */
.max-h-\[90vh\] {
    max-height: 90vh;
}
</style>