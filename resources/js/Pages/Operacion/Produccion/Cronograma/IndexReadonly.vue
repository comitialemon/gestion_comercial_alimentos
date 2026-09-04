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

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== COMPUTED ====================
const diaActual = computed(() => {
    const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado']
    const hoy = new Date().getDay()
    return diasSemana[hoy]
})

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

const totalFilas = computed(() => filas.value.length)

const diasConProductos = computed(() => {
    return props.dias.filter(dia => filas.value.some(f => f[dia])).length
})

const totalProductos = computed(() => props.productos.length)

// ==================== FUNCIONES ====================
const getProductoTexto = (productoId) => {
    if (!productoId) return '—'
    const producto = props.productos.find(p => p.id === productoId)
    return producto ? producto.texto : '—'
}

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

// ==================== LIFECYCLE ====================
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
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-purple-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-purple-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">{{ titulo }}</h1>
                        <p class="text-xs text-gray-500">
                            {{ subtitulo }}
                            <span class="text-purple-600 font-medium ml-1">Hoy es {{ diaActual }}</span>
                        </p>
                    </div>
                </div>

                <!-- ==================== TABLA ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto" style="overflow-y: auto; -webkit-overflow-scrolling: touch;">
                        <table class="min-w-[600px] sm:min-w-[700px] md:min-w-[800px] lg:min-w-[900px] divide-y divide-gray-200">
                            <thead class="sticky top-0 bg-white z-10">
                                <tr>
                                    <th 
                                        v-for="dia in dias" 
                                        :key="dia"
                                        class="px-1 sm:px-2 py-2 text-center text-[10px] font-medium uppercase bg-white"
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
                                    <td 
                                        v-for="dia in dias" 
                                        :key="dia"
                                        class="px-1 sm:px-2 py-1.5 align-top"
                                        style="width: 120px; min-width: 100px; max-width: 160px;"
                                    >
                                        <div class="w-full border border-gray-100 rounded-md px-1.5 sm:px-2 py-1 sm:py-1.5 bg-gray-50 min-h-[36px] sm:min-h-[40px] flex items-center">
                                            <div v-if="fila[dia]" class="flex flex-col w-full">
                                                <span class="font-mono text-[8px] text-gray-500 truncate" :title="getProductoCodigo(fila[dia])">
                                                    {{ getProductoCodigo(fila[dia]) }}
                                                </span>
                                                <span class="text-gray-800 text-[10px] sm:text-xs break-words line-clamp-2" :title="getProductoTexto(fila[dia])">
                                                    {{ getProductoTexto(fila[dia]) }}
                                                </span>
                                            </div>
                                            <div v-else class="text-center text-gray-400 text-[9px] w-full">
                                                <i class="fas fa-minus text-[8px]"></i>
                                                <span class="ml-1 hidden sm:inline">Sin producto</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr v-if="filas.length === 0">
                                    <td :colspan="dias.length" class="px-4 py-10 text-center text-gray-400">
                                        <i class="fas fa-calendar-day text-2xl mb-2 block"></i>
                                        <span class="text-sm">No hay registros en el cronograma de producción</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== INFORMACIÓN ==================== -->
                <div class="mt-3 p-3 bg-blue-50 rounded-xl border border-blue-100 text-xs text-blue-700 flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5 text-blue-500"></i>
                    <div>
                        <span class="font-medium">Información:</span>
                        <ul class="list-disc list-inside mt-1 space-y-0.5 text-[11px]">
                            <li>La columna del <span class="font-medium text-purple-600">día actual</span> se resalta en morado</li>
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
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

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
}

@media (min-width: 641px) {
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
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

tbody tr:hover td {
    background-color: #f9fafb;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>