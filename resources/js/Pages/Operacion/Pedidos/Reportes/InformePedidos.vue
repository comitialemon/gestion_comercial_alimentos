<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePage } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: {
        type: Object,
        default: () => ({})
    },
    operador: {
        type: Object,
        default: () => ({})
    },
    fechaSeleccionada: {
        type: String,
        default: ''
    },
    grupos: {
        type: Array,
        default: () => []
    },
    solicitantes: {
        type: Array,
        default: () => []
    },
    totalProductos: {
        type: Number,
        default: 0
    }
})

const theme = computed(() => usePage().props?.theme || { primary: '#1f2937' })
const primaryColor = computed(() => theme.value.primary || '#1f2937')

// Estado
const fecha = ref(props.fechaSeleccionada || new Date().toISOString().split('T')[0])
const exportandoPdf = ref(false)
const exportandoExcel = ref(false)

// Responsive
const isMobile = ref(false)
const isTablet = ref(false)

const checkScreenSize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// Formatear fecha
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha + 'T00:00:00').toLocaleDateString('es-BO')
}

// Formatear unidades
const formatearUnidades = (valor) => {
    if (valor === undefined || valor === null || valor === '') {
        return '0.00'
    }
    const numero = parseFloat(valor)
    if (isNaN(numero)) {
        return '0.00'
    }
    return numero.toFixed(2)
}

// Obtener unidades de un producto para un solicitante
const obtenerUnidades = (producto, solicitante) => {
    if (!producto || !solicitante) return 0
    const key = solicitante.IdSucursal + '_' + solicitante.IdOperador
    const unidades = producto.unidades_por_solicitante?.[key]
    return unidades || 0
}

// Calcular total de un producto
const calcularTotalProducto = (producto) => {
    if (!producto || !props.solicitantes) return 0
    let total = 0
    props.solicitantes.forEach(s => {
        const key = s.IdSucursal + '_' + s.IdOperador
        total += producto.unidades_por_solicitante?.[key] || 0
    })
    return total
}

// Generar informe
const generarInforme = () => {
    router.get('/operacion/pedidos/reportes/informe-pedidos', {
        fecha: fecha.value
    }, { preserveState: true, replace: true })
}

// Exportar PDF
const exportarPdf = async () => {
    if (!fecha.value) {
        alert('Seleccione una fecha primero')
        return
    }
    
    exportandoPdf.value = true
    try {
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = '/operacion/pedidos/reportes/informe-pedidos/exportar-pdf'
        
        const token = document.createElement('input')
        token.type = 'hidden'
        token.name = '_token'
        token.value = document.querySelector('meta[name="csrf-token"]').content
        form.appendChild(token)
        
        const fechaInput = document.createElement('input')
        fechaInput.type = 'hidden'
        fechaInput.name = 'fecha'
        fechaInput.value = fecha.value
        form.appendChild(fechaInput)
        
        document.body.appendChild(form)
        form.submit()
        document.body.removeChild(form)
        
    } catch (error) {
        console.error('Error exportando PDF:', error)
        alert('Error al exportar PDF')
    } finally {
        exportandoPdf.value = false
    }
}

// Exportar Excel
const exportarExcel = async () => {
    if (!fecha.value) {
        alert('Seleccione una fecha primero')
        return
    }
    
    exportandoExcel.value = true
    try {
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = '/operacion/pedidos/reportes/informe-pedidos/exportar-excel'
        
        const token = document.createElement('input')
        token.type = 'hidden'
        token.name = '_token'
        token.value = document.querySelector('meta[name="csrf-token"]').content
        form.appendChild(token)
        
        const fechaInput = document.createElement('input')
        fechaInput.type = 'hidden'
        fechaInput.name = 'fecha'
        fechaInput.value = fecha.value
        form.appendChild(fechaInput)
        
        document.body.appendChild(form)
        form.submit()
        document.body.removeChild(form)
        
    } catch (error) {
        console.error('Error exportando Excel:', error)
        alert('Error al exportar Excel')
    } finally {
        exportandoExcel.value = false
    }
}

// Verificar si hay datos
const hayDatos = computed(() => {
    return props.grupos && props.grupos.length > 0 && props.solicitantes && props.solicitantes.length > 0
})

// Obtener nombre del producto para mostrar (truncado si es muy largo)
const getProductoNombre = (descripcion, orden) => {
    const nombre = (orden || '') + ':' + descripcion
    if (isMobile.value && nombre.length > 15) {
        return nombre.substring(0, 12) + '...'
    }
    if (isTablet.value && nombre.length > 20) {
        return nombre.substring(0, 17) + '...'
    }
    return nombre
}

// Obtener el título completo del producto para tooltip
const getProductoTooltip = (descripcion, orden) => {
    return (orden || '') + ':' + descripcion
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
        <div class="py-3 px-2 sm:py-4 sm:px-4 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex flex-col items-center text-center mb-4 sm:mb-6">
                    <div class="inline-flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 lg:w-14 lg:h-14 bg-blue-100 rounded-xl sm:rounded-2xl mb-1 sm:mb-2">
                        <i class="fas fa-file-pdf text-base sm:text-lg lg:text-xl text-blue-600"></i>
                    </div>
                    <h1 class="text-base sm:text-lg lg:text-xl font-bold text-gray-900">Informe de Pedidos</h1>
                    <p class="text-[10px] sm:text-xs text-gray-500">Generación de informe de pedidos por fecha</p>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg sm:rounded-xl shadow-sm p-3 sm:p-4 mb-4 sm:mb-6">
                    <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-end gap-3 sm:gap-4">
                        <div class="flex-1 min-w-[140px]">
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Fecha del Pedido</label>
                            <input 
                                type="date" 
                                v-model="fecha" 
                                @change="generarInforme"
                                class="w-full border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button 
                                @click="generarInforme"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-blue-600 text-white rounded-lg text-xs sm:text-sm hover:bg-blue-700 transition flex items-center gap-1 sm:gap-2"
                            >
                                <i class="fas fa-search text-[10px] sm:text-xs"></i>
                                <span>Generar</span>
                            </button>
                            <button 
                                @click="exportarPdf"
                                :disabled="exportandoPdf || !hayDatos"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-red-600 text-white rounded-lg text-xs sm:text-sm hover:bg-red-700 transition flex items-center gap-1 sm:gap-2 disabled:opacity-50"
                            >
                                <i v-if="exportandoPdf" class="fas fa-spinner fa-spin text-[10px] sm:text-xs"></i>
                                <i v-else class="fas fa-file-pdf text-[10px] sm:text-xs"></i>
                                <span class="hidden xs:inline">{{ exportandoPdf ? 'Generando...' : 'PDF' }}</span>
                                <span class="xs:hidden">{{ exportandoPdf ? '...' : 'PDF' }}</span>
                            </button>
                            <button 
                                @click="exportarExcel"
                                :disabled="exportandoExcel || !hayDatos"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-green-600 text-white rounded-lg text-xs sm:text-sm hover:bg-green-700 transition flex items-center gap-1 sm:gap-2 disabled:opacity-50"
                            >
                                <i v-if="exportandoExcel" class="fas fa-spinner fa-spin text-[10px] sm:text-xs"></i>
                                <i v-else class="fas fa-file-excel text-[10px] sm:text-xs"></i>
                                <span class="hidden xs:inline">{{ exportandoExcel ? 'Generando...' : 'Excel' }}</span>
                                <span class="xs:hidden">{{ exportandoExcel ? '...' : 'Excel' }}</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Información de cabecera -->
                    <div v-if="hayDatos" class="mt-3 pt-3 border-t grid grid-cols-1 xs:grid-cols-2 lg:grid-cols-3 gap-1 text-[10px] sm:text-xs text-gray-600">
                        <div>
                            <span class="font-medium">Empresa:</span> {{ empresa.Nombre || '-' }}
                        </div>
                        <div>
                            <span class="font-medium">Operador:</span> {{ operador.nombre || '-' }}
                        </div>
                        <div>
                            <span class="font-medium">Fecha:</span> {{ formatearFecha(fechaSeleccionada) }}
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- INFORME VISUAL - ESTILO PDF -->
                <!-- ========================================== -->
                <div v-if="hayDatos" class="bg-white rounded-lg sm:rounded-xl shadow-sm overflow-hidden">
                    <!-- Cabecera del informe -->
                    <div class="text-center py-2 sm:py-3 border-b">
                        <h2 class="text-base sm:text-lg lg:text-xl font-bold uppercase">Pedido de Productos</h2>
                        <div class="flex flex-wrap justify-center gap-2 sm:gap-4 text-[10px] sm:text-xs mt-1 sm:mt-2">
                            <span><strong>Empresa:</strong> {{ empresa.Nombre || '-' }}</span>
                            <span><strong>Operador:</strong> {{ operador.nombre || '-' }}</span>
                            <span><strong>Fecha:</strong> {{ formatearFecha(fechaSeleccionada) }}</span>
                        </div>
                    </div>

                    <!-- Iterar por cada grupo -->
                    <div v-for="grupo in grupos" :key="grupo.IdGrupoAnalisis" class="p-2 sm:p-3 lg:p-4 border-b last:border-b-0">
                        <!-- Título del grupo -->
                        <h3 class="text-sm sm:text-base lg:text-lg font-bold text-blue-800 mb-2 sm:mb-3 bg-blue-50 px-2 sm:px-3 py-1 rounded">
                            {{ grupo.Grupo }}
                            <span class="text-xs font-normal text-gray-500 ml-2">
                                ({{ grupo.productos.length }} productos)
                            </span>
                        </h3>

                        <!-- Tabla del grupo - RESPONSIVE -->
                        <div class="overflow-x-auto -mx-2 sm:mx-0">
                            <table class="w-full border-collapse text-[10px] sm:text-xs lg:text-sm">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-300 px-1 sm:px-2 lg:px-3 py-1 sm:py-2 text-center font-bold w-8 sm:w-10 lg:w-12">Nº</th>
                                        <th class="border border-gray-300 px-1 sm:px-2 lg:px-3 py-1 sm:py-2 text-left font-bold min-w-[80px] sm:min-w-[100px]">Solicitante</th>
                                        <th 
                                            v-for="producto in grupo.productos" 
                                            :key="producto.IdProducto"
                                            class="border border-gray-300 px-0.5 sm:px-1 lg:px-2 py-1 sm:py-2 text-center font-bold text-[8px] sm:text-[10px] lg:text-xs"
                                            :title="getProductoTooltip(producto.Descripcion, producto.OrdenInformes)"
                                        >
                                            {{ getProductoNombre(producto.Descripcion, producto.OrdenInformes) }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Filas de solicitantes -->
                                    <tr v-for="(solicitante, idx) in solicitantes" :key="idx" class="hover:bg-gray-50">
                                        <td class="border border-gray-300 px-1 sm:px-2 lg:px-3 py-1 sm:py-2 text-center">{{ idx + 1 }}</td>
                                        <td class="border border-gray-300 px-1 sm:px-2 lg:px-3 py-1 sm:py-2 font-medium text-[9px] sm:text-xs">
                                            {{ solicitante.Solicitante }}
                                        </td>
                                        <td 
                                            v-for="producto in grupo.productos" 
                                            :key="producto.IdProducto"
                                            class="border border-gray-300 px-0.5 sm:px-1 lg:px-2 py-1 sm:py-2 text-right font-mono text-[8px] sm:text-[10px] lg:text-xs"
                                        >
                                            {{ formatearUnidades(obtenerUnidades(producto, solicitante)) }}
                                        </td>
                                    </tr>
                                    <!-- Fila de totales -->
                                    <tr class="bg-gray-50 font-bold">
                                        <td class="border border-gray-300 px-1 sm:px-2 lg:px-3 py-1 sm:py-2 text-center"></td>
                                        <td class="border border-gray-300 px-1 sm:px-2 lg:px-3 py-1 sm:py-2 text-[9px] sm:text-xs">TOTALES</td>
                                        <td 
                                            v-for="producto in grupo.productos" 
                                            :key="'total-' + producto.IdProducto"
                                            class="border border-gray-300 px-0.5 sm:px-1 lg:px-2 py-1 sm:py-2 text-right font-mono text-[8px] sm:text-[10px] lg:text-xs"
                                        >
                                            {{ formatearUnidades(calcularTotalProducto(producto)) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Resumen del grupo -->
                        <div class="mt-1 sm:mt-2 text-[9px] sm:text-xs text-gray-500 flex flex-wrap gap-2 sm:gap-4">
                            <span>Productos: <strong>{{ grupo.productos.length }}</strong></span>
                            <span>Total unidades: <strong>{{ formatearUnidades(grupo.productos.reduce((sum, p) => sum + calcularTotalProducto(p), 0)) }}</strong></span>
                        </div>
                    </div>

                    <!-- Footer del informe -->
                    <div class="text-center text-[9px] sm:text-xs text-gray-400 py-2 sm:py-3 border-t">
                        Reporte generado el {{ new Date().toLocaleString('es-BO') }}
                    </div>
                </div>

                <!-- Sin datos -->
                <div v-else-if="fecha" class="bg-white rounded-lg sm:rounded-xl shadow-sm p-8 sm:p-12 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl sm:text-4xl mb-2 sm:mb-3 block"></i>
                    <p class="text-sm sm:text-base lg:text-lg font-medium">No hay pedidos para la fecha seleccionada</p>
                    <p class="text-xs sm:text-sm mt-1">Selecciona otra fecha para generar el informe</p>
                </div>

                <!-- Mensaje inicial -->
                <div v-else class="bg-white rounded-lg sm:rounded-xl shadow-sm p-8 sm:p-12 text-center text-gray-400">
                    <i class="fas fa-calendar-alt text-3xl sm:text-4xl mb-2 sm:mb-3 block text-blue-300"></i>
                    <p class="text-sm sm:text-base lg:text-lg font-medium">Selecciona una fecha</p>
                    <p class="text-xs sm:text-sm mt-1">Elige la fecha del pedido para generar el informe</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Responsive breakpoints */
@media (max-width: 480px) {
    .xs\:inline {
        display: inline !important;
    }
    .xs\:hidden {
        display: none !important;
    }
}

@media (min-width: 481px) {
    .xs\:inline {
        display: inline !important;
    }
    .xs\:hidden {
        display: none !important;
    }
}

/* Para pantallas muy pequeñas */
@media (max-width: 400px) {
    table {
        font-size: 8px !important;
    }
    table th, table td {
        padding: 2px 3px !important;
    }
}

/* Scroll horizontal suave */
.overflow-x-auto {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

.overflow-x-auto::-webkit-scrollbar {
    height: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
}
</style>