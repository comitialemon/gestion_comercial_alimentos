<template>
    <div v-if="visible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrar">
        <div class="bg-white rounded-xl max-w-md w-full overflow-hidden shadow-xl transform transition-all">
            
            <!-- Header con color AMARILLO (warning) -->
            <div class="bg-yellow-500 text-white p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold">{{ titulo || 'Producto Duplicado' }}</h3>
                </div>
            </div>
            
            <!-- Contenido -->
            <div class="p-6">
                <p class="text-gray-700 mb-4">
                    {{ mensaje || 'Ya existe un producto con la misma composición de inventario.' }}
                </p>
                
                <div v-if="productoExistente" class="bg-gray-50 rounded-lg p-3 mb-4">
                    <p class="text-xs text-gray-500 mb-1">Producto existente:</p>
                    <p class="font-medium text-gray-800">{{ productoExistente.nombre || 'Producto desconocido' }}</p>
                </div>
                
                <p class="text-sm text-gray-600 mb-4">
                    {{ mensajeAdicional || 'Puede continuar editando este producto con una composición diferente, o cancelar la creación para volver al listado.' }}
                </p>
                
                <!-- Botones -->
                <div class="flex gap-3">
                    <button 
                        @click="onContinuar"
                        class="flex-1 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition"
                    >
                        {{ textoContinuar || 'Continuar Editando' }}
                    </button>
                    <button 
                        @click="onCancelar"
                        class="flex-1 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition"
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