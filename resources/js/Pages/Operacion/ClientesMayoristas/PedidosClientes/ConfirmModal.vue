<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    title: {
        type: String,
        default: 'Confirmar'
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
        default: 'info' // info, danger, warning, success
    }
})

const emit = defineEmits(['confirm', 'cancel', 'update:visible'])

const mostrar = computed({
    get: () => props.visible,
    set: (val) => {
        emit('update:visible', val)
        if (!val) emit('cancel')
    }
})

const tipoClases = computed(() => {
    const clases = {
        info: {
            icon: 'fa-info-circle',
            color: 'text-blue-600',
            bg: 'bg-blue-50',
            border: 'border-blue-200',
            button: 'bg-blue-600 hover:bg-blue-700'
        },
        danger: {
            icon: 'fa-exclamation-triangle',
            color: 'text-red-600',
            bg: 'bg-red-50',
            border: 'border-red-200',
            button: 'bg-red-600 hover:bg-red-700'
        },
        warning: {
            icon: 'fa-exclamation-circle',
            color: 'text-yellow-600',
            bg: 'bg-yellow-50',
            border: 'border-yellow-200',
            button: 'bg-yellow-600 hover:bg-yellow-700'
        },
        success: {
            icon: 'fa-check-circle',
            color: 'text-green-600',
            bg: 'bg-green-50',
            border: 'border-green-200',
            button: 'bg-green-600 hover:bg-green-700'
        }
    }
    return clases[props.type] || clases.info
})

const confirmar = () => {
    emit('confirm')
    emit('update:visible', false)
}

const cancelar = () => {
    emit('cancel')
    emit('update:visible', false)
}
</script>

<template>
    <!-- Overlay -->
    <div 
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click.self="cancelar"
    >
        <!-- Modal -->
        <div 
            class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-2xl animate-fade-in-up"
            @click.stop
        >
            <!-- Icono -->
            <div class="flex justify-center pt-6">
                <div 
                    class="w-16 h-16 rounded-full flex items-center justify-center"
                    :class="tipoClases.bg"
                >
                    <i 
                        :class="[tipoClases.icon, tipoClases.color]"
                        class="text-3xl"
                    ></i>
                </div>
            </div>

            <!-- Título -->
            <div class="text-center px-6 pt-4">
                <h3 class="text-lg font-bold text-gray-800">{{ title }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ message }}</p>
            </div>

            <!-- Botones -->
            <div class="flex flex-col sm:flex-row gap-3 p-6 pt-4">
                <button 
                    @click="cancelar"
                    class="flex-1 py-2.5 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-medium transition"
                >
                    {{ cancelText }}
                </button>
                <button 
                    @click="confirmar"
                    class="flex-1 py-2.5 px-4 text-white rounded-xl text-sm font-medium transition flex items-center justify-center gap-2"
                    :class="tipoClases.button"
                >
                    <i class="fas fa-check-circle text-sm"></i>
                    {{ confirmText }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.25s ease-out;
}
</style>