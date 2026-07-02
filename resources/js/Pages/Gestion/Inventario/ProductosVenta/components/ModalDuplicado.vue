<template>
    <div v-if="visible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-3 sm:p-4" @click.self="cerrar">
        <div class="bg-white rounded-xl max-w-md w-full overflow-hidden shadow-xl transform transition-all mx-2 sm:mx-0">
            
            <!-- Header -->
            <div class="bg-yellow-500 text-white p-3 sm:p-4">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 bg-white rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-base sm:text-xl"></i>
                    </div>
                    <h3 class="text-sm sm:text-lg font-bold">{{ titulo || 'Producto Duplicado' }}</h3>
                </div>
            </div>
            
            <!-- Contenido -->
            <div class="p-4 sm:p-6">
                <p class="text-gray-700 text-[10px] sm:text-sm mb-3 sm:mb-4">
                    {{ mensaje || 'Ya existe un producto con la misma composición de inventario.' }}
                </p>
                
                <div v-if="productoExistente" class="bg-gray-50 rounded-lg p-2 sm:p-3 mb-3 sm:mb-4">
                    <p class="text-[8px] sm:text-xs text-gray-500 mb-0.5 sm:mb-1">Producto existente:</p>
                    <p class="font-medium text-gray-800 text-[10px] sm:text-sm">
                        {{ productoExistente.Detalle || productoExistente.nombre || 'Producto desconocido' }}
                    </p>
                    <p v-if="productoExistente.Codigo" class="text-[8px] sm:text-xs text-gray-400 mt-0.5">
                        Código: {{ productoExistente.Codigo }}
                    </p>
                </div>
                
                <p class="text-[10px] sm:text-sm text-gray-600 mb-3 sm:mb-4">
                    {{ mensajeAdicional || 'Puede continuar editando este producto con una composición diferente, o cancelar la creación para volver al listado.' }}
                </p>
                
                <!-- Botones -->
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                    <button 
                        @click="onContinuar"
                        class="flex-1 py-1.5 sm:py-2 bg-gray-200 text-gray-700 rounded-lg text-[10px] sm:text-sm font-medium hover:bg-gray-300 transition order-2 sm:order-1"
                    >
                        {{ textoContinuar || 'Continuar Editando' }}
                    </button>
                    <button 
                        @click="onCancelar"
                        class="flex-1 py-1.5 sm:py-2 bg-red-600 text-white rounded-lg text-[10px] sm:text-sm font-medium hover:bg-red-700 transition order-1 sm:order-2"
                    >
                        {{ textoCancelar || 'Cancelar Creación' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    titulo: {
        type: String,
        default: 'Producto Duplicado'
    },
    mensaje: {
        type: String,
        default: 'Ya existe un producto con la misma composición de inventario.'
    },
    mensajeAdicional: {
        type: String,
        default: 'Puede continuar editando este producto con una composición diferente, o cancelar la creación para volver al listado.'
    },
    textoContinuar: {
        type: String,
        default: 'Continuar Editando'
    },
    textoCancelar: {
        type: String,
        default: 'Cancelar Creación'
    },
    productoExistente: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['update:visible', 'continuar', 'cancelar'])

const cerrar = () => {
    emit('update:visible', false)
}

const onContinuar = () => {
    emit('continuar')
    emit('update:visible', false)
}

const onCancelar = () => {
    emit('cancelar')
    emit('update:visible', false)
}
</script>