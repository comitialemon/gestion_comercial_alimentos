<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center">
        <!-- Fondo oscuro -->
        <div class="absolute inset-0 bg-black/50" @click="cerrar"></div>
        
        <!-- Modal -->
        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6">
            <!-- Icono -->
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-2xl text-amber-600"></i>
                </div>
            </div>
            
            <!-- Título -->
            <h3 class="text-lg font-bold text-center text-gray-800 mb-2">
                {{ titulo || '¿Estás seguro?' }}
            </h3>
            
            <!-- Mensaje -->
            <p class="text-sm text-gray-600 text-center mb-6">
                {{ mensaje || '¿Deseas continuar con esta acción?' }}
            </p>
            
            <!-- Detalle adicional -->
            <div v-if="detalle" class="bg-gray-50 rounded-lg p-3 mb-4 text-sm text-gray-600">
                {{ detalle }}
            </div>
            
            <!-- Botones -->
            <div class="flex gap-3">
                <button 
                    @click="cerrar"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-sm font-medium"
                >
                    Cancelar
                </button>
                <button 
                    @click="confirmar"
                    :disabled="cargando"
                    class="flex-1 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium transition flex items-center justify-center gap-2 disabled:opacity-50"
                >
                    <i v-if="cargando" class="fas fa-spinner fa-spin"></i>
                    <i v-else class="fas fa-check"></i>
                    {{ cargando ? 'Procesando...' : botonConfirmar || 'Confirmar' }}
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
    titulo: {
        type: String,
        default: '¿Estás seguro?'
    },
    mensaje: {
        type: String,
        default: '¿Deseas continuar con esta acción?'
    },
    detalle: {
        type: String,
        default: ''
    },
    botonConfirmar: {
        type: String,
        default: 'Confirmar'
    },
    cargando: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['confirm', 'close'])

const confirmar = () => {
    emit('confirm')
}

const cerrar = () => {
    if (!props.cargando) {
        emit('close')
    }
}
</script>

<style scoped>
/* Animación de entrada */
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