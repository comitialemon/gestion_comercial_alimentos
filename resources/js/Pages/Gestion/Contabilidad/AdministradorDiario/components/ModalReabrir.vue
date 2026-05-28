<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    diario: Object,
})

const emit = defineEmits(['update:modelValue', 'reabierto'])

const modalOpen = ref(props.modelValue)
const loading = ref(false)
const error = ref('')

watch(() => props.modelValue, (newVal) => {
    modalOpen.value = newVal
    if (!newVal) {
        error.value = ''
    }
})

watch(modalOpen, (newVal) => {
    emit('update:modelValue', newVal)
})

const cerrarModal = () => {
    modalOpen.value = false
}

const confirmarReabrir = async () => {
    if (!props.diario) return
    
    loading.value = true
    error.value = ''
    
    try {
        const response = await axios.post(`/gestion/administrador-diario/${props.diario.IdDiario}/reabrir`)
        
        if (response.data.success) {
            emit('reabierto')
            cerrarModal()
            // Mostrar notificación de éxito
            alert(response.data.message)
        } else {
            error.value = response.data.message || 'Error al reabrir'
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al reabrir el diario'
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="cerrarModal">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="cerrarModal"></div>
            
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all duration-300">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-3 border-b bg-secondary-600 rounded-t-lg">
                    <h3 class="text-sm font-semibold text-white">
                        <i class="fas fa-unlock-alt mr-2"></i> Reabrir Diario
                    </h3>
                    <button @click="cerrarModal" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5">
                    <div v-if="error" class="mb-4 p-2 bg-red-50 text-red-600 text-xs rounded">
                        <i class="fas fa-exclamation-circle mr-1"></i> {{ error }}
                    </div>
                    
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle text-secondary-500 text-3xl mb-3 block"></i>
                        <p class="text-sm text-gray-700 mb-2">
                            ¿Está seguro de que desea <strong class="text-secondary-600">reabrir</strong> el diario?
                        </p>
                        <div class="bg-gray-50 rounded-lg p-3 mb-4 text-left">
                            <p class="text-xs text-gray-600 mb-1"><strong>N° Diario:</strong> {{ diario?.NumeroDiario }}</p>
                            <p class="text-xs text-gray-600 mb-1"><strong>Tipo:</strong> {{ diario?.tipo_diario?.TipoDiario || '-' }}</p>
                            <p class="text-xs text-gray-600"><strong>Fecha:</strong> {{ diario?.fecha?.Fecha ? new Date(diario.fecha.Fecha).toLocaleDateString('es-BO') : '-' }}</p>
                        </div>
                        <p class="text-xs text-red-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Al reabrir, el diario perderá su número y podrá ser modificado nuevamente en el módulo de Diario de Ingresos.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 mt-5">
                        <button @click="cerrarModal" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm hover:bg-gray-100 transition">
                            Cancelar
                        </button>
                        <button @click="confirmarReabrir" :disabled="loading" class="px-4 py-2 bg-secondary-600 text-white rounded-lg text-sm hover:bg-secondary-700 transition flex items-center gap-2">
                            <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-unlock-alt"></i>
                            {{ loading ? 'Procesando...' : 'Reabrir Diario' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>