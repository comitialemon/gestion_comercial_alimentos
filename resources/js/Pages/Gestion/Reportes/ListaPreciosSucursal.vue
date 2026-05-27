<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: Object,
    sucursal: Object,
})

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

const loading = ref(false)

const exportarExcel = () => {
    loading.value = true
    window.location.href = '/gestion/reportes/lista-precios/exportar-sucursal'
    setTimeout(() => {
        loading.value = false
    }, 2000)
}

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-3xl mx-auto">
                
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-location-dot text-guindo-600 text-lg sm:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg lg:text-xl font-bold text-gray-800">Lista de Precios por Sucursal</h1>
                            <p class="text-[10px] sm:text-xs text-gray-500">Exporta precios de la sucursal actual</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                    
                    <!-- Info de empresa -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-5">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-store mr-1 text-guindo-600"></i> Empresa
                            </label>
                            <div class="text-xs sm:text-sm text-gray-800 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
                                {{ empresa?.Nombre || 'No registrado' }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-id-card mr-1 text-guindo-600"></i> NIT
                            </label>
                            <div class="text-xs sm:text-sm text-gray-800 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
                                {{ empresa?.NIT || 'No registrado' }}
                            </div>
                        </div>
                    </div>

                    <!-- Sucursal actual destacada -->
                    <div class="mb-5 p-3 sm:p-4 bg-guindo-50 rounded-xl border border-guindo-200">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fas fa-check-circle text-guindo-600 text-sm"></i>
                            <span class="text-xs sm:text-sm font-semibold text-guindo-700">Sucursal Seleccionada</span>
                        </div>
                        <div class="text-sm sm:text-base lg:text-lg font-bold text-gray-800 break-words">
                            {{ sucursal?.nombre || 'No registrado' }}
                        </div>
                        <p class="text-[10px] sm:text-xs text-guindo-600 mt-1">El reporte incluirá SOLO esta sucursal</p>
                    </div>

                    <!-- Botones -->
                    <div class="flex flex-col sm:flex-row gap-3 pt-3 border-t border-gray-200">
                        <button 
                            @click="exportarExcel" 
                            :disabled="loading"
                            class="w-full sm:w-auto px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition disabled:opacity-50 flex items-center justify-center gap-2 text-xs sm:text-sm font-medium"
                        >
                            <i v-if="loading" class="fas fa-spinner fa-spin text-sm"></i>
                            <i v-else class="fas fa-file-excel text-sm"></i>
                            {{ loading ? 'Generando...' : '📊 Exportar Lista de Precios' }}
                        </button>
                    </div>
                </div>

                <!-- Información del reporte -->
                <div class="mt-4 p-3 sm:p-4 bg-blue-50 rounded-lg border border-blue-100">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-info-circle text-blue-600 text-xs sm:text-sm mt-0.5"></i>
                        <div class="text-[10px] sm:text-xs text-blue-700">
                            El reporte incluye: precio general, precio de la sucursal 
                            <strong class="font-semibold">{{ sucursal?.nombre || 'actual' }}</strong> 
                            y precios especiales por mayorista de esta sucursal.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Asegurar que los textos sean legibles en PC */
@media (min-width: 1024px) {
    .text-sm {
        font-size: 14px !important;
    }
}
</style>