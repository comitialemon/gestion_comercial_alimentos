<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    visible: Boolean,
    contenedor: Object
})

const emit = defineEmits(['close', 'confirmado'])

const loading = ref(false)
const error = ref('')

watch(() => props.visible, (val) => {
    if (val) {
        error.value = ''
        loading.value = false
    }
})

const cerrar = () => {
    if (loading.value) return
    emit('close')
}

const confirmar = async () => {
    if (!props.contenedor) return

    loading.value = true
    error.value = ''

    try {
        const id = props.contenedor.IdContenedor
        const response = await axios.post(`/operacion/pedidos/clientes-mayoristas/contenedores/${id}/toggle-estado`)

        if (response.data.success) {
            emit('confirmado', {
                id,
                message: response.data.message || 'Contenedor enviado a Borrador'
            })

            // Redirigir a la vista de edición
            setTimeout(() => {
                router.visit(`/operacion/pedidos/clientes-mayoristas/contenedores/${id}/edit`)
            }, 400)
        } else {
            error.value = response.data.message || 'Error al cambiar el estado'
            loading.value = false
        }
    } catch (e) {
        console.error('Error:', e)
        error.value = e.response?.data?.message || 'Error al cambiar el estado del contenedor'
        loading.value = false
    }
}
</script>

<template>
    <div 
        v-if="visible" 
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" 
        @click.self="cerrar"
    >
        <div class="bg-white rounded-xl w-full max-w-[90%] sm:max-w-md overflow-hidden shadow-xl">
            <!-- Header -->
            <div class="p-4 border-b bg-amber-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-amber-100">
                        <i class="fas fa-edit text-amber-600 text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-gray-800 text-sm sm:text-base">Editar Contenedor</h3>
                        <p class="text-[10px] sm:text-xs text-gray-500 truncate">
                            {{ contenedor?.Codigo || '—' }}
                        </p>
                    </div>
                    <button 
                        @click="cerrar" 
                        :disabled="loading"
                        class="text-gray-400 hover:text-gray-600 transition disabled:opacity-50"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="p-4 sm:p-5">
                <p class="text-xs sm:text-sm text-gray-700 text-center">
                    Este contenedor está en estado 
                    <span class="font-bold text-green-600">ACTIVO</span>.
                </p>
                <p class="text-xs sm:text-sm text-gray-700 text-center mt-2">
                    Para editarlo, primero debe volver al estado 
                    <span class="font-bold text-yellow-600">BORRADOR</span>.
                </p>
                <p class="text-[10px] sm:text-xs text-gray-400 text-center mt-3">
                    Una vez en Borrador podrás modificar sus grupos, capacidad y otros datos.
                </p>

                <!-- Error -->
                <div v-if="error" class="mt-3 p-2 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-[10px] sm:text-xs text-red-600 text-center">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        {{ error }}
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-3 sm:p-4 bg-gray-50 flex justify-end gap-2 sm:gap-3">
                <button 
                    @click="cerrar" 
                    :disabled="loading"
                    class="px-3 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition disabled:opacity-50"
                >
                    Cancelar
                </button>
                <button 
                    @click="confirmar" 
                    :disabled="loading" 
                    class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition flex items-center gap-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-50"
                >
                    <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                    <i v-else class="fas fa-edit"></i>
                    Volver a Borrador y Editar
                </button>
            </div>
        </div>
    </div>
</template>