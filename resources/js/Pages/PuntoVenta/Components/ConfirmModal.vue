<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    modelValue: Boolean,
    title: {
        type: String,
        default: 'Confirmar acción'
    },
    message: {
        type: String,
        default: '¿Estás seguro de realizar esta acción?'
    },
    confirmText: {
        type: String,
        default: 'Confirmar'
    },
    cancelText: {
        type: String,
        default: 'Cancelar'
    },
    type: {
        type: String,
        default: 'warning' // warning, danger, info
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

const getColorClasses = () => {
    switch (props.type) {
        case 'danger':
            return {
                button: 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
                icon: 'text-red-600',
                bg: 'bg-red-100'
            }
        case 'info':
            return {
                button: 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500',
                icon: 'text-blue-600',
                bg: 'bg-blue-100'
            }
        default:
            return {
                button: 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500',
                icon: 'text-amber-600',
                bg: 'bg-amber-100'
            }
    }
}
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="cancelar"></div>
        
        <!-- Modal -->
        <div class="relative bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden transform transition-all">
            <!-- Header -->
            <div class="p-4 flex items-center gap-3" :class="getColorClasses().bg">
                <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-xl" :class="getColorClasses().icon"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800">{{ title }}</h3>
            </div>
            
            <!-- Body -->
            <div class="p-4">
                <p class="text-sm text-gray-600">{{ message }}</p>
            </div>
            
            <!-- Footer -->
            <div class="p-4 border-t flex justify-end gap-2">
                <button @click="cancelar" class="px-4 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition">
                    {{ cancelText }}
                </button>
                <button @click="confirmar" class="px-4 py-1.5 rounded-lg text-sm text-white transition flex items-center gap-1" :class="getColorClasses().button">
                    <i class="fas fa-check text-xs"></i>
                    {{ confirmText }}
                </button>
            </div>
        </div>
    </div>
</template>