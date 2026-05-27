<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    modelValue: Boolean,
    totalItems: {
        type: Number,
        default: 0
    }
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const visible = ref(props.modelValue)

watch(() => props.modelValue, (newVal) => {
    visible.value = newVal
})

watch(visible, (newVal) => {
    emit('update:modelValue', newVal)
})

const confirmar = () => {
    emit('confirm')
    visible.value = false
}

const cancelar = () => {
    emit('cancel')
    visible.value = false
}
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="cancelar"></div>
        
        <!-- Modal -->
        <div class="relative bg-white rounded-2xl shadow-xl max-w-sm w-full overflow-hidden transform transition-all">
            <!-- Icono y título -->
            <div class="text-center pt-6">
                <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-3">
                    <i class="fas fa-trash-alt text-2xl text-red-600"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Cancelar Venta</h3>
                <p class="text-sm text-gray-500 mt-1 px-4">
                    ¿Estás seguro de cancelar la venta actual?
                </p>
                <p v-if="totalItems > 0" class="text-xs text-red-500 mt-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Se perderán {{ totalItems }} producto(s) agregados al carrito
                </p>
            </div>
            
            <!-- Botones -->
            <div class="flex gap-3 p-4 pt-2">
                <button 
                    @click="cancelar" 
                    class="flex-1 py-2.5 rounded-xl border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 transition"
                >
                    No, seguir
                </button>
                <button 
                    @click="confirmar" 
                    class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition shadow-md"
                >
                    Sí, cancelar
                </button>
            </div>
        </div>
    </div>
</template>