<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted, computed, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    inventarios: Object,
    sucursales: Array,
    sucursalSeleccionada: Number,
    filtroEstado: String,
    buscar: String,
})

const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')
const isMobile = ref(window.innerWidth < 768)

// 🔥 Modal
const mostrarModal = ref(false)
const inventarioSeleccionado = ref(null)
const loadingDetalle = ref(false)

// 🔥 Colores del cliente (usando inject)
const primaryColor = inject('primaryColor', 'var(--color-primary)')
const primaryLight = inject('primaryLight', 'var(--color-primary-50)')

// Detectar cambios de tamaño de pantalla
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// 🔥 Formatear número
const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

// 🔥 Abrir modal con detalle
const verDetalle = async (id) => {
    loadingDetalle.value = true
    mostrarModal.value = true
    
    try {
        const response = await axios.get(`/gestion/inventario/inventario-fisico-diario/obtener-por-id/${id}`)
        
        if (response.data.success) {
            inventarioSeleccionado.value = response.data.data
        } else {
            inventarioSeleccionado.value = null
        }
    } catch (error) {
        console.error('Error al obtener detalle:', error)
        inventarioSeleccionado.value = null
    } finally {
        loadingDetalle.value = false
    }
}

const cerrarModal = () => {
    mostrarModal.value = false
    inventarioSeleccionado.value = null
}

// 🔥 Reimprimir PDF
const reimprimirPDF = (id) => {
    window.open(`/gestion/inventario/inventario-fisico-diario/pdf/${id}`, '_blank')
}

// APLICAR FILTROS
const aplicarFiltros = () => {
    router.get('/gestion/inventario/inventario-fisico-diario', {
        estado: estadoFiltro.value || undefined,
        buscar: buscador.value || undefined
    }, {
        preserveState: true,
        replace: true
    })
}

// BUSCAR (con debounce)
let timeoutBuscador
const buscarInventarios = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

// Limpiar búsqueda
const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

// Watch para filtro de estado
watch(estadoFiltro, () => {
    aplicarFiltros()
})

// Computed para estadísticas
const totalInventarios = computed(() => {
    return props.inventarios?.total || 0
})

const totalCompletados = computed(() => {
    if (!props.inventarios?.data) return 0
    return props.inventarios.data.filter(item => item.ActivoInactivo === 1).length
})

const totalBorradores = computed(() => {
    if (!props.inventarios?.data) return 0
    return props.inventarios.data.filter(item => item.ActivoInactivo === 0).length
})

const totalAnulados = computed(() => {
    if (!props.inventarios?.data) return 0
    return props.inventarios.data.filter(item => item.ActivoInactivo === 2).length
})

const getEstadoColor = (activo) => {
    if (activo === 1) return 'bg-green-100 text-green-800'
    if (activo === 2) return 'bg-red-100 text-red-800'
    return 'bg-yellow-100 text-yellow-800'
}

const getEstadoIcono = (activo) => {
    if (activo === 1) return 'fas fa-check-circle'
    if (activo === 2) return 'fas fa-times-circle'
    return 'fas fa-pencil-alt'
}

const getEstadoTexto = (activo) => {
    if (activo === 1) return 'Completado'
    if (activo === 2) return 'Anulado'
    return 'Borrador'
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header Responsive -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Inventario Físico Diario</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">Historial de inventarios físicos realizados</p>
                        </div>
                    </div>
                </div>

                <!-- Filtros Responsive - Compactos -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Selector de estado -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" class="border rounded-md px-2 py-1 text-xs w-36 sm:w-40">
                                <option value="">Todos</option>
                                <option value="completados">Completados</option>
                                <option value="borradores">Borradores</option>
                                <option value="anulados">Anulados</option>
                            </select>
                        </div>
                        
                        <!-- BUSCADOR -->
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarInventarios"
                                placeholder="N° Correlativo..."
                                class="border rounded-md px-2 py-1 text-xs w-28 sm:w-32"
                            >
                            <button 
                                v-if="buscador" 
                                @click="limpiarBusqueda" 
                                class="text-gray-400 hover:text-gray-600 text-xs"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Resultados de búsqueda -->
                    <div v-if="buscador" class="mt-2 text-[10px] text-gray-500">
                        <span class="font-semibold">{{ buscador }}</span>
                        <span class="ml-2">({{ totalInventarios }} resultados)</span>
                    </div>
                </div>

                <!-- Vista para MÓVIL (tarjetas) -->
                <div v-if="isMobile" class="space-y-3">
                    <div v-for="item in inventarios.data" :key="item.IdFisicoDiario" class="bg-white rounded-lg shadow-sm p-3">
                        <!-- Cabecera de tarjeta -->
                        <div class="flex justify-between items-start border-b pb-2 mb-2">
                            <div class="flex flex-col gap-1">
                                <span class="text-xs font-mono font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded self-start">
                                    #{{ item.NumeroCorrelativo || 'Sin número' }}
                                </span>
                                <span class="text-[10px] text-gray-500">{{ item.fecha_formateada || '-' }}</span>
                            </div>
                            <div class="flex gap-2">
                                <button @click="verDetalle(item.IdFisicoDiario)" class="text-blue-600" title="Ver detalle">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                                <button v-if="item.ActivoInactivo === 1" @click="reimprimirPDF(item.IdFisicoDiario)" class="text-red-600" title="PDF">
                                    <i class="fas fa-file-pdf text-sm"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Datos principales -->
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Sucursal:</span>
                                <span class="font-medium">{{ item.sucursal_nombre || 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Operador:</span>
                                <span class="font-medium truncate max-w-[150px]" :title="item.nombre_operador">
                                    {{ item.nombre_operador || 'N/A' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Productos:</span>
                                <span class="font-medium">{{ item.CantidadContados || 0 }} / {{ item.CantidadTotalProductos || 0 }}</span>
                            </div>
                            <div class="flex justify-end pt-1 border-t mt-1">
                                <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(item.ActivoInactivo)">
                                    <i :class="getEstadoIcono(item.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                    {{ getEstadoTexto(item.ActivoInactivo) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mensaje sin resultados móvil -->
                    <div v-if="inventarios.data?.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-clipboard-list text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-xs text-gray-400">
                            <span v-if="buscador">No hay inventarios que coincidan con "{{ buscador }}"</span>
                            <span v-else>No hay inventarios físicos registrados</span>
                        </p>
                    </div>
                </div>

                <!-- Vista para TABLET Y ESCRITORIO (tabla) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">N°</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Sucursal</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Operador</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Productos</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in inventarios.data" :key="item.IdFisicoDiario" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs font-mono font-bold text-primary-600">
                                        {{ item.NumeroCorrelativo || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-700">{{ item.sucursal_nombre || 'N/A' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ item.fecha_formateada || '-' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-700 max-w-[150px] truncate" :title="item.nombre_operador">
                                        {{ item.nombre_operador || 'N/A' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-center">
                                        <span class="font-medium">{{ item.CantidadContados || 0 }} / {{ item.CantidadTotalProductos || 0 }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(item.ActivoInactivo)">
                                            <i :class="getEstadoIcono(item.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                            {{ getEstadoTexto(item.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right space-x-1.5 whitespace-nowrap">
                                        <button @click="verDetalle(item.IdFisicoDiario)" class="text-blue-600 hover:text-blue-800 text-xs" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button v-if="item.ActivoInactivo === 1" @click="reimprimirPDF(item.IdFisicoDiario)" class="text-red-600 hover:text-red-800 text-xs" title="PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="inventarios.data?.length === 0">
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-clipboard-list text-2xl mb-1 block"></i>
                                        <span v-if="buscador">No hay inventarios que coincidan con "{{ buscador }}"</span>
                                        <span v-else>No hay inventarios físicos registrados</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación desktop -->
                    <div v-if="inventarios.links && inventarios.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-xs">
                            <div class="text-gray-500">Mostrando {{ inventarios.from || 0 }} a {{ inventarios.to || 0 }} de {{ inventarios.total || 0 }}</div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link v-for="link in inventarios.links" :key="link.label" :href="link.url || '#'" class="px-2 py-0.5 rounded border text-xs" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paginación móvil -->
                <div v-if="isMobile && inventarios.links && inventarios.links.length > 1" class="mt-3 bg-white rounded-lg shadow-sm p-2">
                    <div class="flex justify-center gap-0.5 flex-wrap">
                        <Link v-for="link in inventarios.links" :key="link.label" :href="link.url || '#'" class="px-2 py-1 rounded border text-xs min-w-[32px] text-center" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔥 MODAL DE DETALLE -->
    <div v-if="mostrarModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="cerrarModal"></div>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                <!-- Header -->
                <div class="px-4 pt-4 pb-3 border-b bg-primary-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-clipboard-list text-primary-600 text-lg"></i>
                            <h3 class="text-base font-medium text-gray-900">Detalle del Inventario Físico</h3>
                            <span v-if="inventarioSeleccionado" class="text-xs text-gray-500 ml-2">
                                #{{ inventarioSeleccionado.numero_correlativo || 'Sin número' }}
                            </span>
                        </div>
                        <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- Cuerpo -->
                <div class="px-4 py-4 sm:px-6">
                    <div v-if="loadingDetalle" class="flex justify-center py-8">
                        <i class="fas fa-spinner fa-spin text-primary-600 text-2xl"></i>
                    </div>

                    <div v-else-if="!inventarioSeleccionado" class="text-center py-8 text-gray-500">
                        <i class="fas fa-info-circle text-3xl mb-2 block"></i>
                        <p class="text-sm">No se encontró información</p>
                    </div>

                    <div v-else>
                        <!-- Resumen -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            <div class="bg-gray-50 rounded-lg p-2 text-center">
                                <p class="text-[10px] text-gray-500">Fecha</p>
                                <p class="text-sm font-semibold">{{ inventarioSeleccionado.fecha || '-' }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2 text-center">
                                <p class="text-[10px] text-gray-500">Productos</p>
                                <p class="text-sm font-semibold text-primary-600">{{ inventarioSeleccionado.total_productos || 0 }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2 text-center">
                                <p class="text-[10px] text-gray-500">Con diferencia</p>
                                <p class="text-sm font-semibold text-yellow-600">{{ inventarioSeleccionado.con_diferencia || 0 }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2 text-center">
                                <p class="text-[10px] text-gray-500">Operador</p>
                                <p class="text-sm font-semibold truncate">{{ inventarioSeleccionado.operador || 'N/A' }}</p>
                            </div>
                        </div>

                        <!-- Tabla -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left font-medium text-gray-500">#</th>
                                        <th class="px-2 py-1.5 text-left font-medium text-gray-500">Código</th>
                                        <th class="px-2 py-1.5 text-left font-medium text-gray-500">Producto</th>
                                        <th class="px-2 py-1.5 text-right font-medium text-gray-500">Sistema</th>
                                        <th class="px-2 py-1.5 text-right font-medium text-gray-500">Contado</th>
                                        <th class="px-2 py-1.5 text-right font-medium text-gray-500">Diferencia</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(item, index) in inventarioSeleccionado.detalles" :key="index" class="hover:bg-gray-50">
                                        <td class="px-2 py-1.5 text-gray-500">{{ index + 1 }}</td>
                                        <td class="px-2 py-1.5 font-mono text-gray-600">{{ item.codigo || '-' }}</td>
                                        <td class="px-2 py-1.5 text-gray-700 max-w-[200px] truncate" :title="item.producto">{{ item.producto }}</td>
                                        <td class="px-2 py-1.5 text-right font-mono">{{ formatearNumero(item.sistema) }}</td>
                                        <td class="px-2 py-1.5 text-right font-mono font-semibold" :class="item.contado > 0 ? 'text-green-600' : 'text-gray-500'">
                                            {{ formatearNumero(item.contado) }}
                                        </td>
                                        <td class="px-2 py-1.5 text-right font-mono font-bold" :class="item.diferencia > 0 ? 'text-green-600' : item.diferencia < 0 ? 'text-red-600' : 'text-gray-400'">
                                            {{ formatearNumero(item.diferencia) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6 flex justify-end">
                    <button @click="cerrarModal" class="px-4 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:inline {
        display: inline;
    }
    .xs\:block {
        display: block;
    }
}
</style>