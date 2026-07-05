<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4">
        <!-- Fondo oscuro -->
        <div class="absolute inset-0 bg-black/50" @click="cerrar"></div>
        
        <!-- Modal -->
        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full mx-auto p-5 sm:p-6 max-h-[90vh] overflow-y-auto">
            <!-- Icono -->
            <div class="flex justify-center mb-4">
                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-amber-100 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-2xl sm:text-3xl text-amber-600"></i>
                </div>
            </div>
            
            <!-- Título -->
            <h3 class="text-lg sm:text-xl font-bold text-center text-gray-800 mb-2">
                {{ titulo || '⚠️ ¿Estás seguro?' }}
            </h3>
            
            <!-- Mensaje -->
            <p class="text-sm sm:text-base text-gray-600 text-center mb-4">
                {{ mensaje || '¿Deseas continuar con esta acción?' }}
            </p>
            
            <!-- Detalle adicional -->
            <div v-if="detalle" class="bg-gray-50 rounded-lg p-3 mb-4 text-xs sm:text-sm text-gray-600">
                <div class="flex items-start gap-2">
                    <i class="fas fa-info-circle text-amber-500 mt-0.5"></i>
                    <span>{{ detalle }}</span>
                </div>
            </div>
            
            <!-- Información del rango -->
            <div v-if="rangoInfo" class="bg-primary-50 rounded-lg p-3 mb-4 text-xs sm:text-sm">
                <div class="flex items-start gap-2" :style="{ color: 'var(--color-primary-700, #1e40af)' }">
                    <i class="fas fa-list mt-0.5"></i>
                    <div>
                        <p class="font-medium">Rango a procesar:</p>
                        <p>{{ rangoInfo }}</p>
                    </div>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                <button 
                    @click="cerrar"
                    :disabled="cargando"
                    class="w-full sm:flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-sm sm:text-base font-medium disabled:opacity-50"
                >
                    Cancelar
                </button>
                <button 
                    @click="confirmar"
                    :disabled="cargando"
                    class="w-full sm:flex-1 px-4 py-2.5 rounded-lg text-white text-sm sm:text-base font-medium transition flex items-center justify-center gap-2 disabled:opacity-50"
                    :style="{ backgroundColor: 'var(--color-primary-600, #dc2626)' }"
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
        default: '⚠️ ¿Estás seguro?'
    },
    mensaje: {
        type: String,
        default: '¿Deseas continuar con esta acción?'
    },
    detalle: {
        type: String,
        default: ''
    },
    rangoInfo: {
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
.bg-primary-50 {
    background-color: var(--color-primary-50, #eff6ff);
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