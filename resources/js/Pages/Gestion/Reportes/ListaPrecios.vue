<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: Object
})

const loading = ref(false)

const exportarExcel = () => {
    loading.value = true
    window.location.href = '/gestion/reportes/lista-precios/exportar'
    setTimeout(() => {
        loading.value = false
    }, 2000)
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto">
                
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-file-excel text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Lista de Precios</h1>
                            <p class="text-xs text-gray-500">Exporta precios general, por sucursal y mayorista</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <form @submit.prevent="exportarExcel" class="space-y-5">
                        
                        <!-- Info de empresa (solo texto, no tarjeta gruesa) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-store mr-1 text-guindo-600"></i> Empresa
                                </label>
                                <div class="text-sm text-gray-800 bg-gray-50 rounded-lg px-3 py-2 border">
                                    {{ empresa?.Nombre || 'No registrado' }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-id-card mr-1 text-guindo-600"></i> NIT
                                </label>
                                <div class="text-sm text-gray-800 bg-gray-50 rounded-lg px-3 py-2 border">
                                    {{ empresa?.NIT || 'No registrado' }}
                                </div>
                            </div>
                        </div>

                        <!-- Botón Exportar -->
                        <div class="flex justify-end gap-3 pt-3 border-t">
                            <button 
                                type="submit" 
                                :disabled="loading"
                                class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-2 text-sm"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-file-excel"></i>
                                {{ loading ? 'Generando...' : 'Exportar Lista de Precios' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Información del reporte -->
                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    El reporte incluye: precio general de cada producto, precios por sucursal y precios especiales por mayorista.
                </div>
            </div>
        </div>
    </div>
</template>