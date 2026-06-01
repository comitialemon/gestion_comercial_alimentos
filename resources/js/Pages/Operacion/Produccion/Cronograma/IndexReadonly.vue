<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    cronogramas: {
        type: Array,
        default: () => []
    },
    productos: {
        type: Array,
        default: () => []
    },
    dias: {
        type: Array,
        default: () => ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo']
    },
    titulo: {
        type: String,
        default: 'Cronograma de Producción'
    },
    subtitulo: {
        type: String,
        default: 'Consulta de productos programados por día'
    }
})

// Estado responsivo
const isMobile = ref(false)
const isTablet = ref(false)

// Verificar tamaño de pantalla
const checkScreenSize = () => {
    isMobile.value = window.innerWidth < 640
    isTablet.value = window.innerWidth >= 640 && window.innerWidth < 1024
}

// Día actual
const diaActual = computed(() => {
    const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado']
    const hoy = new Date().getDay()
    return diasSemana[hoy]
})

// Procesar datos para mostrar
const filas = computed(() => {
    if (!props.cronogramas || props.cronogramas.length === 0) return []
    
    return props.cronogramas.map(c => ({
        id: c.IdCronograma,
        Lunes: c.Lunes || null,
        Martes: c.Martes || null,
        Miercoles: c.Miercoles || null,
        Jueves: c.Jueves || null,
        Viernes: c.Viernes || null,
        Sabado: c.Sabado || null,
        Domingo: c.Domingo || null,
    }))
})

// Obtener texto del producto por ID
const getProductoTexto = (productoId) => {
    if (!productoId) return '—'
    const producto = props.productos.find(p => p.id === productoId)
    return producto ? producto.texto : '—'
}

// Obtener código del producto
const getProductoCodigo = (productoId) => {
    if (!productoId) return ''
    const producto = props.productos.find(p => p.id === productoId)
    return producto ? producto.codigo : ''
}

const diasAbreviados = {
    Lunes: 'LUN',
    Martes: 'MAR',
    Miercoles: 'MIÉ',
    Jueves: 'JUE',
    Viernes: 'VIE',
    Sabado: 'SÁB',
    Domingo: 'DOM'
}

const getDiaClass = (dia) => {
    if (diaActual.value === dia) {
        return 'bg-purple-100 text-purple-800'
    }
    return 'bg-gray-50 text-gray-500'
}

onMounted(() => {
    checkScreenSize()
    window.addEventListener('resize', checkScreenSize)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkScreenSize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-3 px-2 sm:py-4 sm:px-3 md:py-6 md:px-4 lg:px-6 xl:px-8">
            <div class="w-full mx-auto">
                <!-- Header - Responsive -->
                <div class="text-center mb-3 sm:mb-4 md:mb-6">
                    <div class="inline-flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 bg-purple-100 rounded-xl sm:rounded-2xl mb-1 sm:mb-2 md:mb-3">
                        <i class="fas fa-calendar-alt text-base sm:text-lg md:text-xl text-purple-600"></i>
                    </div>
                    <h1 class="text-sm sm:text-base md:text-lg lg:text-xl font-bold text-gray-900">
                        {{ titulo }}
                    </h1>
                    <p class="text-[9px] sm:text-[10px] md:text-xs text-gray-500 mt-0.5 sm:mt-1">
                        {{ subtitulo }}
                    </p>
                    <p class="text-[8px] sm:text-[9px] md:text-[10px] text-gray-400 mt-1">
                        Hoy es <strong class="text-purple-600">{{ diaActual }}</strong>
                    </p>
                </div>

                <!-- CONTENEDOR PRINCIPAL - Responsive -->
                <div class="bg-white rounded-lg sm:rounded-xl shadow-sm overflow-hidden" style="min-height: 400px; min-height: min(600px, 80vh);">
                    <!-- Scroll horizontal para móvil -->
                    <div class="overflow-x-auto" style="overflow-y: auto; -webkit-overflow-scrolling: touch;">
                        <table class="min-w-[800px] sm:min-w-[900px] md:min-w-[1000px] lg:min-w-[1100px] xl:min-w-[1200px] divide-y divide-gray-200">
                            <thead class="sticky top-0 bg-white z-10">
                                <tr>
                                    <th class="px-1 sm:px-2 py-2 sm:py-3 text-center text-[10px] sm:text-xs font-medium text-gray-500 uppercase bg-white w-8 sm:w-12">#</th>
                                    <th 
                                        v-for="dia in dias" 
                                        :key="dia"
                                        class="px-1 sm:px-2 py-2 sm:py-3 text-center text-[9px] sm:text-[10px] md:text-xs font-medium uppercase bg-white"
                                        :class="getDiaClass(dia)"
                                        style="width: 120px; min-width: 100px; max-width: 160px;"
                                    >
                                        <span class="block sm:hidden">{{ diasAbreviados[dia] }}</span>
                                        <span class="hidden sm:block md:hidden">{{ dia.substring(0, 3) }}</span>
                                        <span class="hidden md:block">{{ dia }}</span>
                                    </th>
                                </tr>
                            </thead>
                            
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(fila, idx) in filas" :key="fila.id" class="hover:bg-gray-50 transition">
                                    <td class="px-1 sm:px-2 py-2 sm:py-3 text-center text-xs sm:text-sm text-gray-500 font-medium align-top bg-white">
                                        {{ idx + 1 }}
                                    </td>
                                    
                                    <td 
                                        v-for="dia in dias" 
                                        :key="dia"
                                        class="px-1 sm:px-2 py-1.5 sm:py-2 align-top"
                                        style="width: 120px; min-width: 100px; max-width: 160px;"
                                    >
                                        <div class="w-full border border-gray-100 rounded-md px-1 sm:px-2 py-1 sm:py-1.5 bg-gray-50">
                                            <div v-if="fila[dia]" class="flex flex-col">
                                                <span class="font-mono text-[8px] sm:text-[10px] text-gray-500 truncate" :title="getProductoCodigo(fila[dia])">
                                                    {{ getProductoCodigo(fila[dia]) }}
                                                </span>
                                                <span class="text-gray-800 text-[9px] sm:text-xs break-words line-clamp-2 sm:line-clamp-3" :title="getProductoTexto(fila[dia])">
                                                    {{ getProductoTexto(fila[dia]) }}
                                                </span>
                                            </div>
                                            <div v-else class="text-center text-gray-400 text-[9px] sm:text-xs py-1">
                                                <i class="fas fa-minus text-[8px] sm:text-[10px]"></i>
                                                <span class="ml-1 hidden sm:inline">Sin producto</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr v-if="filas.length === 0">
                                    <td :colspan="dias.length + 1" class="px-2 sm:px-4 py-8 sm:py-12 text-center text-gray-400">
                                        <i class="fas fa-calendar-day text-base sm:text-xl md:text-2xl mb-1 sm:mb-2 block"></i>
                                        <span class="text-[10px] sm:text-xs">No hay registros en el cronograma de producción</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Resumen de información -->
                <div class="mt-3 sm:mt-4 p-2 sm:p-3 bg-gray-50 rounded-lg">
                    <div class="flex flex-wrap justify-between gap-2 text-[9px] sm:text-[10px] md:text-xs text-gray-600">
                        <div class="flex items-center gap-1">
                            <i class="fas fa-chart-line text-purple-500 text-[10px]"></i>
                            <span><strong class="font-semibold">Total filas:</strong> {{ filas.length }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <i class="fas fa-calendar-week text-purple-500 text-[10px]"></i>
                            <span><strong class="font-semibold">Días configurados:</strong> 
                                {{ dias.filter(dia => filas.some(f => f[dia])).length }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1">
                            <i class="fas fa-box text-purple-500 text-[10px]"></i>
                            <span><strong class="font-semibold">Total productos:</strong> 
                                {{ props.productos.length }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Información de colores -->
                <div class="mt-3 p-2 sm:p-3 bg-blue-50 rounded-lg text-[9px] sm:text-[10px] md:text-xs text-blue-700 flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-2">
                    <i class="fas fa-info-circle mt-0.5 text-[10px] sm:text-xs"></i>
                    <div class="flex-1">
                        <strong>Información:</strong>
                        <ul class="list-disc list-inside mt-0.5 sm:mt-1 space-y-0.5">
                            <li>La columna del <strong class="text-purple-600">día actual</strong> se resalta en color morado</li>
                            <li>Pasa el mouse sobre cualquier producto para ver el nombre completo</li>
                            <li>Los productos sin asignación muestran "Sin producto"</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Mejoras para móvil */
@media (max-width: 640px) {
    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
}

/* Para tablet */
@media (min-width: 641px) and (max-width: 1024px) {
    .overflow-x-auto {
        scrollbar-width: thin;
    }
    
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
}

/* Para desktop */
@media (min-width: 1025px) {
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
}

/* Cabecera sticky */
thead tr th {
    position: sticky;
    top: 0;
    background-color: white;
    z-index: 10;
}

thead tr th:first-child {
    left: 0;
    z-index: 20;
}

tbody tr:hover td {
    background-color: #f9fafb;
}
</style>