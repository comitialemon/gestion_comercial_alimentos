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

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const fecha = ref(props.fechaSeleccionada || new Date().toISOString().split('T')[0])
const exportandoPdf = ref(false)
const exportandoExcel = ref(false)

// ==================== COMPUTED ====================
const hayDatos = computed(() => {
    return props.grupos && props.grupos.length > 0 && props.solicitantes && props.solicitantes.length > 0
})

const theme = computed(() => usePage().props?.theme || { primary: '#1f2937' })
const primaryColor = computed(() => theme.value.primary || '#1f2937')

// ==================== FUNCIONES ====================
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha + 'T00:00:00').toLocaleDateString('es-BO')
}

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

const obtenerUnidades = (producto, solicitante) => {
    if (!producto || !solicitante) return 0
    const key = solicitante.IdSucursal + '_' + solicitante.IdOperador
    const unidades = producto.unidades_por_solicitante?.[key]
    return unidades || 0
}

const calcularTotalProducto = (producto) => {
    if (!producto || !props.solicitantes) return 0
    let total = 0
    props.solicitantes.forEach(s => {
        const key = s.IdSucursal + '_' + s.IdOperador
        total += producto.unidades_por_solicitante?.[key] || 0
    })
    return total
}

const generarInforme = () => {
    router.get('/operacion/pedidos/reportes/informe-pedidos', {
        fecha: fecha.value
    }, { preserveState: true, replace: true })
}

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

const getProductoTooltip = (descripcion, orden) => {
    return (orden || '') + ':' + descripcion
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
                    <div class="w-9 h-9 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-file-pdf text-blue-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Informe de Pedidos</h1>
                        <p class="text-xs text-gray-500">Generación de informe de pedidos por fecha</p>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[160px] max-w-[220px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha del Pedido</label>
                            <input 
                                type="date" 
                                v-model="fecha" 
                                @change="generarInforme"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                        </div>
                        <div class="flex gap-1.5">
                            <button 
                                @click="generarInforme"
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1.5"
                            >
                                <i class="fas fa-search text-[10px]"></i>
                                <span>Generar</span>
                            </button>
                            <button 
                                @click="exportarPdf"
                                :disabled="exportandoPdf || !hayDatos"
                                class="px-3 py-1.5 bg-red-600 text-white rounded-md text-xs font-medium hover:bg-red-700 transition flex items-center gap-1.5 disabled:opacity-50"
                            >
                                <i v-if="exportandoPdf" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-file-pdf text-[10px]"></i>
                                <span>PDF</span>
                            </button>
                            <button 
                                @click="exportarExcel"
                                :disabled="exportandoExcel || !hayDatos"
                                class="px-3 py-1.5 bg-emerald-600 text-white rounded-md text-xs font-medium hover:bg-emerald-700 transition flex items-center gap-1.5 disabled:opacity-50"
                            >
                                <i v-if="exportandoExcel" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-file-excel text-[10px]"></i>
                                <span>Excel</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Información de cabecera -->
                    <div v-if="hayDatos" class="mt-2 pt-2 border-t border-gray-200 flex flex-wrap gap-3 text-[10px] text-gray-600">
                        <span><span class="font-medium">Empresa:</span> {{ empresa.Nombre || '-' }}</span>
                        <span><span class="font-medium">Operador:</span> {{ operador.nombre || '-' }}</span>
                        <span><span class="font-medium">Fecha:</span> {{ formatearFecha(fechaSeleccionada) }}</span>
                    </div>
                </div>

                <!-- ==================== INFORME ==================== -->
                <div v-if="hayDatos" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Cabecera del informe -->
                    <div class="text-center py-2 px-3 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-sm font-bold uppercase text-gray-800">Pedido de Productos</h2>
                        <div class="flex flex-wrap justify-center gap-3 text-[10px] text-gray-500 mt-0.5">
                            <span><strong>Empresa:</strong> {{ empresa.Nombre || '-' }}</span>
                            <span><strong>Operador:</strong> {{ operador.nombre || '-' }}</span>
                            <span><strong>Fecha:</strong> {{ formatearFecha(fechaSeleccionada) }}</span>
                        </div>
                    </div>

                    <!-- Iterar por cada grupo -->
                    <div v-for="grupo in grupos" :key="grupo.IdGrupoAnalisis" class="p-3 border-b border-gray-200 last:border-b-0">
                        <!-- Título del grupo -->
                        <h3 class="text-sm font-bold text-primary-700 mb-2 bg-primary-50 px-2 py-1 rounded flex items-center justify-between">
                            <span>{{ grupo.Grupo }}</span>
                            <span class="text-[10px] font-normal text-gray-500">({{ grupo.productos.length }} productos)</span>
                        </h3>

                        <!-- Tabla del grupo -->
                        <div class="overflow-x-auto -mx-1">
                            <table class="w-full border-collapse text-[10px] sm:text-xs">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-300 px-1.5 py-1 text-center font-bold w-8">Nº</th>
                                        <th class="border border-gray-300 px-1.5 py-1 text-left font-bold min-w-[80px]">Solicitante</th>
                                        <th 
                                            v-for="producto in grupo.productos" 
                                            :key="producto.IdProducto"
                                            class="border border-gray-300 px-1 py-1 text-center font-bold text-[9px]"
                                            :title="getProductoTooltip(producto.Descripcion, producto.OrdenInformes)"
                                        >
                                            {{ getProductoNombre(producto.Descripcion, producto.OrdenInformes) }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(solicitante, idx) in solicitantes" :key="idx" class="hover:bg-gray-50">
                                        <td class="border border-gray-300 px-1.5 py-1 text-center">{{ idx + 1 }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1 font-medium text-[9px]">
                                            {{ solicitante.Solicitante }}
                                        </td>
                                        <td 
                                            v-for="producto in grupo.productos" 
                                            :key="producto.IdProducto"
                                            class="border border-gray-300 px-1 py-1 text-right font-mono text-[9px]"
                                        >
                                            {{ formatearUnidades(obtenerUnidades(producto, solicitante)) }}
                                        </td>
                                    </tr>
                                    <!-- Fila de totales -->
                                    <tr class="bg-gray-50 font-bold">
                                        <td class="border border-gray-300 px-1.5 py-1 text-center"></td>
                                        <td class="border border-gray-300 px-1.5 py-1 text-[9px]">TOTALES</td>
                                        <td 
                                            v-for="producto in grupo.productos" 
                                            :key="'total-' + producto.IdProducto"
                                            class="border border-gray-300 px-1 py-1 text-right font-mono text-[9px]"
                                        >
                                            {{ formatearUnidades(calcularTotalProducto(producto)) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Resumen del grupo -->
                        <div class="mt-1.5 text-[9px] text-gray-500 flex flex-wrap gap-3">
                            <span>Productos: <strong>{{ grupo.productos.length }}</strong></span>
                            <span>Total unidades: <strong>{{ formatearUnidades(grupo.productos.reduce((sum, p) => sum + calcularTotalProducto(p), 0)) }}</strong></span>
                        </div>
                    </div>

                    <!-- Footer del informe -->
                    <div class="text-center text-[9px] text-gray-400 py-2 border-t border-gray-200 bg-gray-50">
                        Reporte generado el {{ new Date().toLocaleString('es-BO') }}
                    </div>
                </div>

                <!-- ==================== SIN DATOS ==================== -->
                <div v-else-if="fecha" class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                    <p class="text-sm font-medium">No hay pedidos para la fecha seleccionada</p>
                    <p class="text-xs mt-1">Selecciona otra fecha para generar el informe</p>
                </div>

                <!-- ==================== MENSAJE INICIAL ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-calendar-alt text-3xl mb-2 block text-primary-300"></i>
                    <p class="text-sm font-medium">Selecciona una fecha</p>
                    <p class="text-xs mt-1">Elige la fecha del pedido para generar el informe</p>
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

@media (max-width: 400px) {
    table {
        font-size: 8px !important;
    }
    table th, table td {
        padding: 2px 3px !important;
    }
}

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