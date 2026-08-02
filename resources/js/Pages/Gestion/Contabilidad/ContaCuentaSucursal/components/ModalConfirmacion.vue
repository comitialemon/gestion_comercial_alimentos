<!-- resources/js/Pages/Gestion/Contabilidad/ContaCuentaSucursal/components/ModalConfirmacion.vue -->
<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Fondo oscuro -->
        <div class="fixed inset-0 bg-black/50 transition-opacity" @click="closeModal"></div>
        
        <!-- Modal -->
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-auto transform transition-all duration-300">
            <!-- Icono y título -->
            <div class="p-4 border-b">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-800">{{ titulo || 'Confirmar eliminación' }}</h3>
                        <p class="text-xs text-gray-500">{{ mensaje || '¿Estás seguro de que deseas eliminar este elemento?' }}</p>
                    </div>
                </div>
            </div>
            
            <!-- Contenido adicional (slot) -->
            <div v-if="$slots.default" class="p-4">
                <slot></slot>
            </div>
            
            <!-- Botones -->
            <div class="p-4 border-t bg-gray-50 flex justify-end gap-2 rounded-b-lg">
                <button 
                    @click="closeModal" 
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition"
                >
                    Cancelar
                </button>
                <button 
                    @click="confirmAction" 
                    :disabled="cargando"
                    class="px-4 py-2 bg-red-600 text-white rounded-md text-sm hover:bg-red-700 transition flex items-center gap-2 disabled:opacity-50"
                >
                    <i v-if="cargando" class="fas fa-spinner fa-spin"></i>
                    <i v-else class="fas fa-trash-alt"></i>
                    {{ cargando ? 'Eliminando...' : 'Eliminar' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    titulo: {
        type: String,
        default: 'Confirmar eliminación'
    },
    mensaje: {
        type: String,
        default: '¿Estás seguro de que deseas eliminar este elemento?'
    },
    cargando: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const visible = ref(props.modelValue)

// Sincronizar con el v-model
watch(() => props.modelValue, (newVal) => {
    visible.value = newVal
})

watch(visible, (newVal) => {
    emit('update:modelValue', newVal)
})

const closeModal = () => {
    visible.value = false
    emit('cancel')
}

const confirmAction = () => {
    emit('confirm')
}
</script>

<style scoped>
/* Animación opcional para el modal */
.fixed {
    animation: fadeIn 0.2s ease-out;
}

.relative {
    animation: slideUp 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { 
        opacity: 0;
        transform: translateY(20px);
    }
    to { 
        opacity: 1;
        transform: translateY(0);
    }
}
</style>