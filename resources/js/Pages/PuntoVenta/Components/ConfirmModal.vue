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
        default: 'warning' // warning, danger, info, success
    },
    icon: {
        type: String,
        default: null
    },
    showItems: {
        type: Boolean,
        default: false
    },
    totalItems: {
        type: Number,
        default: 0
    },
    itemName: {
        type: String,
        default: ''
    },
    // 🔥 NUEVO: para mensaje personalizado de cancelación
    isCancelVenta: {
        type: Boolean,
        default: false
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

const getIcon = () => {
    if (props.icon) return props.icon
    
    switch (props.type) {
        case 'danger':
            return 'fa-trash-alt'
        case 'info':
            return 'fa-info-circle'
        case 'success':
            return 'fa-check-circle'
        default:
            return 'fa-exclamation-triangle'
    }
}

const getIconColor = () => {
    switch (props.type) {
        case 'danger': return 'text-red-600'
        case 'info': return 'text-blue-600'
        case 'success': return 'text-green-600'
        default: return 'text-amber-600'
    }
}

const getBgColor = () => {
    switch (props.type) {
        case 'danger': return 'bg-red-100'
        case 'info': return 'bg-blue-100'
        case 'success': return 'bg-green-100'
        default: return 'bg-amber-100'
    }
}

const getButtonColor = () => {
    switch (props.type) {
        case 'danger': return 'bg-red-600 hover:bg-red-700 focus:ring-red-500'
        case 'info': return 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
        case 'success': return 'bg-green-600 hover:bg-green-700 focus:ring-green-500'
        default: return 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500'
    }
}

// 🔥 Mensaje dinámico para cancelar venta
const getMessage = () => {
    if (props.isCancelVenta) {
        return '¿Estás seguro de cancelar la venta actual? Si lo haces, perderás todos los registros realizados.'
    }
    return props.message
}
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm transition-opacity" @click="cancelar"></div>
        
        <!-- Modal -->
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden transform transition-all scale-100 animate-fadeIn">
            <!-- Icono y título -->
            <div class="text-center pt-6">
                <div class="mx-auto w-16 h-16 rounded-full flex items-center justify-center mb-3 shadow-inner" :class="getBgColor()">
                    <i class="fas text-2xl" :class="[getIcon(), getIconColor()]"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">{{ title }}</h3>
                
                <!-- 🔥 Mensaje con estilo mejorado -->
                <div class="mt-2 px-4">
                    <p class="text-sm text-gray-600 leading-relaxed">
                        {{ getMessage() }}
                    </p>
                    <!-- Advertencia adicional para cancelar venta -->
                    <div v-if="isCancelVenta" class="mt-3 bg-red-50 rounded-lg p-3 border border-red-200">
                        <div class="flex items-start gap-2">
                            <i class="fas fa-exclamation-circle text-red-500 text-sm mt-0.5"></i>
                            <div class="text-left">
                                <p class="text-xs text-red-700 font-medium">⚠️ Esta acción no se puede deshacer</p>
                                <p class="text-xs text-red-600 mt-0.5">
                                    Se eliminarán todos los productos agregados al carrito.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Items a perder (opcional) -->
                <p v-if="showItems && totalItems > 0" class="text-xs text-red-500 mt-2 font-medium">
                    <i class="fas fa-shopping-cart mr-1"></i>
                    {{ totalItems }} producto(s) en el carrito se perderán
                </p>
                <p v-if="itemName" class="text-xs text-gray-500 mt-1">
                    <span class="font-medium">{{ itemName }}</span>
                </p>
            </div>
            
            <!-- Botones -->
            <div class="flex gap-3 p-4 pt-2">
                <button 
                    @click="cancelar" 
                    class="flex-1 py-2.5 rounded-xl border-2 border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 hover:border-gray-400 transition duration-200"
                >
                    {{ cancelText }}
                </button>
                <button 
                    @click="confirmar" 
                    class="flex-1 py-2.5 rounded-xl text-white text-sm font-medium transition duration-200 shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2"
                    :class="getButtonColor()"
                >
                    <i v-if="type === 'danger'" class="fas fa-trash-alt mr-1.5 text-xs"></i>
                    <i v-else-if="type === 'warning'" class="fas fa-exclamation-triangle mr-1.5 text-xs"></i>
                    <i v-else class="fas fa-check mr-1.5 text-xs"></i>
                    {{ confirmText }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.animate-fadeIn {
    animation: fadeIn 0.25s ease-out;
}
</style>