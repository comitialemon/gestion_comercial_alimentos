<!-- resources/js/Pages/Gestion/Impuestos/AnularFactura/components/ConfirmacionModal.vue -->
<template>
    <div v-if="visible" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Overlay de fondo -->
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="cerrar"></div>

            <!-- Centrar modal -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal -->
            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <!-- Cabecera con color según acción -->
                <div class="px-6 pt-5 pb-4" :class="accion === 'anular' ? 'bg-red-50' : 'bg-yellow-50'">
                    <div class="flex items-start gap-4">
                        <!-- Icono -->
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-full" :class="accion === 'anular' ? 'bg-red-100' : 'bg-yellow-100'">
                            <i :class="accion === 'anular' ? 'fas fa-ban text-red-600' : 'fas fa-exclamation-triangle text-yellow-600'" class="text-xl"></i>
                        </div>
                        
                        <!-- Título y descripción -->
                        <div class="flex-1">
                            <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">
                                {{ titulo }}
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    {{ descripcion }}
                                </p>
                                <p v-if="numeroFactura" class="mt-2 text-sm font-semibold text-red-600">
                                    Factura N° {{ numeroFactura }}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Botón cerrar -->
                        <button @click="cerrar" class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Cuerpo del modal (opcional) -->
                <div v-if="mensajeAdicional" class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        {{ mensajeAdicional }}
                    </p>
                </div>

                <!-- Botones de acción -->
                <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="cerrar"
                        :disabled="cargando"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="confirmar"
                        :disabled="cargando"
                        class="px-4 py-2 text-sm font-medium text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 flex items-center gap-2"
                        :class="accion === 'anular' ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500' : 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500'"
                    >
                        <i v-if="cargando" class="fas fa-spinner fa-spin"></i>
                        <i v-else :class="accion === 'anular' ? 'fas fa-ban' : 'fas fa-check'"></i>
                        {{ cargando ? 'Procesando...' : botonTexto }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    titulo: {
        type: String,
        default: 'Confirmar acción'
    },
    descripcion: {
        type: String,
        default: '¿Estás seguro de realizar esta acción?'
    },
    botonTexto: {
        type: String,
        default: 'Confirmar'
    },
    accion: {
        type: String,
        default: 'anular' // 'anular' o 'advertencia'
    },
    numeroFactura: {
        type: [String, Number],
        default: null
    },
    mensajeAdicional: {
        type: String,
        default: null
    },
    cargando: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['confirmar', 'cerrar'])

const confirmar = () => {
    emit('confirmar')
}

const cerrar = () => {
    emit('cerrar')
}

// Prevenir scroll del body cuando el modal está abierto
watch(() => props.visible, (newVal) => {
    if (newVal) {
        document.body.style.overflow = 'hidden'
    } else {
        document.body.style.overflow = ''
    }
})
</script>