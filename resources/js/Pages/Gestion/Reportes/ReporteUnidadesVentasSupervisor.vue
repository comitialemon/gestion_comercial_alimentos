<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: Object,
    sucursales: Array,
    sucursalId: Number,
})

// ==================== ESTADO ====================
const sucursalId = ref(props.sucursalId || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const tipoFiltro = ref('fecha_unica')
const fechaUnica = ref('')
const fechaInicio = ref('')
const fechaFin = ref('')

const cargando = ref(false)
const reporte = ref([])
const totales = ref({ unidades: 0, ventas: 0, fechas: 0, productos: 0, detalles: 0 })
const expandidosFechas = ref({})
const expandidosProductos = ref({})
const errorFiltro = ref('')
const advertencia = ref('')

// ==================== COMPUTADOS ====================
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id === sucursalId.value)
    return suc?.nombre || ''
})

// ==================== ACCIONES ====================
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    reporte.value = []
    totales.value = { unidades: 0, ventas: 0, fechas: 0, productos: 0, detalles: 0 }
}

const limpiarTodosLosFiltros = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    tipoFiltro.value = 'fecha_unica'
    fechaUnica.value = ''
    fechaInicio.value = ''
    fechaFin.value = ''
    errorFiltro.value = ''
    advertencia.value = ''
    reporte.value = []
    totales.value = { unidades: 0, ventas: 0, fechas: 0, productos: 0, detalles: 0 }
    expandidosFechas.value = {}
    expandidosProductos.value = {}
}

const toggleFecha = (index) => {
    expandidosFechas.value = {
        ...expandidosFechas.value,
        [index]: !expandidosFechas.value[index]
    }
}

const toggleProducto = (fechaIndex, productoIndex) => {
    const key = `${fechaIndex}_${productoIndex}`
    expandidosProductos.value = {
        ...expandidosProductos.value,
        [key]: !expandidosProductos.value[key]
    }
}

const expandirTodo = () => {
    const nuevosExpandidosFechas = {}
    const nuevosExpandidosProductos = {}
    
    reporte.value.forEach((_, fIdx) => {
        nuevosExpandidosFechas[fIdx] = true
    })
    
    expandidosFechas.value = nuevosExpandidosFechas
    expandidosProductos.value = nuevosExpandidosProductos
}

const contraerTodo = () => {
    expandidosFechas.value = {}
    expandidosProductos.value = {}
}

const limpiarFechas = () => {
    fechaUnica.value = ''
    fechaInicio.value = ''
    fechaFin.value = ''
    errorFiltro.value = ''
}

const cargarReporte = async () => {
    if (!sucursalId.value) {
        errorFiltro.value = 'Seleccione una sucursal'
        return
    }
    
    if (tipoFiltro.value === 'fecha_unica') {
        if (!fechaUnica.value) {
            errorFiltro.value = 'Seleccione una fecha'
            return
        }
    } else {
        if (!fechaInicio.value || !fechaFin.value) {
            errorFiltro.value = 'Seleccione fecha de inicio y fin'
            return
        }
        if (fechaInicio.value > fechaFin.value) {
            errorFiltro.value = 'La fecha de inicio no puede ser mayor a la fecha de fin'
            return
        }
    }
    
    errorFiltro.value = ''
    advertencia.value = ''
    cargando.value = true
    
    try {
        const params = new URLSearchParams()
        params.append('sucursal_id', sucursalId.value)
        
        if (tipoFiltro.value === 'fecha_unica') {
            params.append('fecha_inicio', fechaUnica.value)
            params.append('fecha_fin', fechaUnica.value)
        } else {
            params.append('fecha_inicio', fechaInicio.value)
            params.append('fecha_fin', fechaFin.value)
        }
        
        const response = await axios.get('/gestion/reportes/unidades-ventas/data', { params })
        
        if (response.data.success) {
            reporte.value = response.data.reporte || []
            totales.value = response.data.totales || { unidades: 0, ventas: 0, fechas: 0, productos: 0, detalles: 0 }
            expandidosFechas.value = {}
            expandidosProductos.value = {}
            
            if (response.data.advertencia) {
                advertencia.value = response.data.advertencia.mensaje
                setTimeout(() => { advertencia.value = '' }, 5000)
            }
        } else {
            errorFiltro.value = response.data.error || 'Error al cargar el reporte'
            reporte.value = []
        }
    } catch (error) {
        console.error('Error cargando reporte:', error)
        errorFiltro.value = error.response?.data?.error || 'Error al cargar el reporte'
        reporte.value = []
    } finally {
        cargando.value = false
    }
}

const volver = () => {
    router.get('/oficial')
}

const handleClickOutside = (event) => {
    const container = document.querySelector('.sucursal-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarSucursales.value = false
    }
}

const formatearNumero = (numero) => {
    if (numero === undefined || numero === null) return '0'
    return parseFloat(numero).toLocaleString('es-BO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    })
}

const formatearMoneda = (numero) => {
    if (numero === undefined || numero === null) return '0.00'
    return parseFloat(numero).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    if (sucursalId.value) {
        const sucursal = props.sucursales?.find(s => s.id === sucursalId.value)
        if (sucursal) {
            sucursalBusqueda.value = sucursal.nombre
        }
    }
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-3 sm:py-4 px-2 sm:px-4 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header compacto -->
                <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3 mb-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center"
                                 :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                <i class="fas fa-chart-line text-sm"></i>
                            </div>
                            <h1 class="text-sm sm:text-base font-bold text-gray-800">Ventas por Producto</h1>
                        </div>
                        <div class="flex gap-1">
                            <button @click="expandirTodo" class="px-2 py-1 text-xs rounded transition"
                                :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-expand-alt text-xs"></i>
                            </button>
                            <button @click="contraerTodo" class="px-2 py-1 text-xs rounded transition"
                                :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-compress-alt text-xs"></i>
                            </button>
                            <button @click="volver" class="px-2 py-1 text-xs rounded transition"
                                :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-arrow-left text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3 mb-3">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Sucursal -->
                        <div class="sucursal-autocomplete" style="min-width: 100px; max-width: 130px;">
                            <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Sucursal</label>
                            <div class="relative">
                                <input type="text" v-model="sucursalBusqueda" @focus="mostrarSucursales = true"
                                    @input="mostrarSucursales = true"
                                    class="w-full border rounded-lg px-1.5 py-1.5 text-[11px] pr-5"
                                    placeholder="Buscar" autocomplete="off" />
                                <button v-if="sucursalBusqueda" @click="limpiarSucursal"
                                    class="absolute right-1 top-1/2 -translate-y-1/2 text-gray-400">
                                    <i class="fas fa-times text-[9px]"></i>
                                </button>
                                <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-32 overflow-y-auto text-[11px]">
                                    <div v-for="suc in sucursalesDisponibles" :key="suc.id"
                                        @click="seleccionarSucursal(suc)"
                                        class="px-1.5 py-1 cursor-pointer hover:bg-gray-50 truncate">
                                        {{ suc.nombre }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tipo filtro -->
                        <div style="min-width: 75px;">
                            <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Tipo</label>
                            <div class="flex gap-1">
                                <button @click="tipoFiltro = 'fecha_unica'; limpiarFechas()"
                                    class="px-1.5 py-1 text-[10px] rounded transition"
                                    :class="tipoFiltro === 'fecha_unica' ? 'text-white' : 'bg-gray-100 text-gray-600'"
                                    :style="tipoFiltro === 'fecha_unica' ? { backgroundColor: `var(--color-primary-600)` } : {}">
                                    Única
                                </button>
                                <button @click="tipoFiltro = 'rango'; limpiarFechas()"
                                    class="px-1.5 py-1 text-[10px] rounded transition"
                                    :class="tipoFiltro === 'rango' ? 'text-white' : 'bg-gray-100 text-gray-600'"
                                    :style="tipoFiltro === 'rango' ? { backgroundColor: `var(--color-primary-600)` } : {}">
                                    Rango
                                </button>
                            </div>
                        </div>

                        <!-- Fechas -->
                        <div v-if="tipoFiltro === 'fecha_unica'" style="min-width: 110px;">
                            <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Fecha</label>
                            <input type="date" v-model="fechaUnica" class="w-full border rounded-lg px-1.5 py-1.5 text-[11px]" />
                        </div>
                        <div v-else class="flex gap-1" style="min-width: 210px;">
                            <div style="width: 100px;">
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Desde</label>
                                <input type="date" v-model="fechaInicio" class="w-full border rounded-lg px-1.5 py-1.5 text-[11px]" />
                            </div>
                            <div style="width: 100px;">
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Hasta</label>
                                <input type="date" v-model="fechaFin" class="w-full border rounded-lg px-1.5 py-1.5 text-[11px]" />
                            </div>
                        </div>

                        <!-- Botón Buscar -->
                        <div>
                            <button @click="cargarReporte" :disabled="cargando || !sucursalId"
                                class="px-2 py-1.5 text-white rounded-lg text-[11px] font-medium flex items-center gap-1 disabled:opacity-50"
                                :style="{ backgroundColor: `var(--color-primary-600)` }">
                                <i v-if="cargando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-search text-[10px]"></i>
                            </button>
                        </div>

                        <!-- Botón Limpiar TODO -->
                        <div>
                            <button @click="limpiarTodosLosFiltros"
                                class="px-2 py-1.5 text-[11px] rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 flex items-center gap-1"
                                title="Limpiar todos los filtros">
                                <i class="fas fa-trash-alt text-[10px]"></i>
                                <span class="hidden sm:inline">Limpiar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Sucursal seleccionada -->
                    <div v-if="sucursalId && !errorFiltro" class="mt-1 text-[9px]" :style="{ color: `var(--color-primary-600)` }">
                        <i class="fas fa-check-circle mr-1"></i> {{ sucursalNombre }}
                    </div>

                    <!-- Mensaje error -->
                    <div v-if="errorFiltro" class="mt-1 text-[10px] text-red-600">
                        <i class="fas fa-exclamation-triangle mr-1"></i> {{ errorFiltro }}
                    </div>

                    <!-- Mensaje advertencia -->
                    <div v-if="advertencia" class="mt-1 text-[10px] text-amber-600">
                        <i class="fas fa-clock mr-1"></i> {{ advertencia }}
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="cargando" class="bg-white rounded-lg shadow-sm p-6 text-center">
                    <i class="fas fa-spinner fa-spin text-xl" :style="{ color: `var(--color-primary-600)` }"></i>
                    <p class="text-gray-500 mt-1 text-xs">Cargando...</p>
                </div>

                <!-- 🔥 TOTALES SIMPLIFICADOS - Solo 4 campos 🔥 -->
                <div v-else-if="reporte.length > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                    <div class="bg-white rounded-lg shadow-sm p-2 text-center">
                        <p class="text-[10px] text-gray-500">Unidades Vendidas</p>
                        <p class="text-base font-bold" :style="{ color: `var(--color-primary-700)` }">{{ formatearNumero(totales.unidades) }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-2 text-center">
                        <p class="text-[10px] text-gray-500">Total Bs</p>
                        <p class="text-base font-bold" :style="{ color: `var(--color-primary-700)` }">Bs {{ formatearMoneda(totales.ventas) }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-2 text-center">
                        <p class="text-[10px] text-gray-500">Días Trabajados</p>
                        <p class="text-base font-bold" :style="{ color: `var(--color-primary-700)` }">{{ totales.fechas }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-2 text-center">
                        <p class="text-[10px] text-gray-500">Productos Vendidos</p>
                        <p class="text-base font-bold" :style="{ color: `var(--color-primary-700)` }">{{ totales.productos }}</p>
                    </div>
                </div>

                <!-- Reporte agrupado -->
                <div v-if="reporte.length > 0 && !cargando" class="space-y-2">
                    <div v-for="(fechaData, fechaIndex) in reporte" :key="fechaIndex" class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <!-- FECHA -->
                        <div @click="toggleFecha(fechaIndex)"
                            class="flex items-center justify-between p-2 sm:p-3 cursor-pointer hover:bg-gray-50 transition">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <i :class="expandidosFechas[fechaIndex] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-gray-400 text-xs flex-shrink-0"></i>
                                <div class="w-6 h-6 rounded-lg flex items-center justify-center flex-shrink-0"
                                    :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                    <i class="fas fa-calendar-alt text-xs"></i>
                                </div>
                                <span class="font-semibold text-gray-800 text-sm truncate">{{ fechaData.fecha }}</span>
                                <span class="text-xs text-gray-400 flex-shrink-0">{{ fechaData.productos.length }} prod</span>
                            </div>
                            <div class="text-right flex-shrink-0 ml-2">
                                <span class="text-xs sm:text-sm font-semibold" :style="{ color: `var(--color-primary-700)` }">{{ formatearMoneda(fechaData.total_ventas_fecha) }}</span>
                                <span class="text-[10px] text-gray-400 ml-1">({{ formatearNumero(fechaData.total_unidades_fecha) }})</span>
                            </div>
                        </div>

                        <!-- PRODUCTOS -->
                        <div v-if="expandidosFechas[fechaIndex]" class="border-t border-gray-100">
                            <div v-for="(productoData, productoIndex) in fechaData.productos" :key="productoIndex" 
                                class="border-b border-gray-100 last:border-b-0">
                                
                                <!-- Producto -->
                                <div @click="toggleProducto(fechaIndex, productoIndex)"
                                    class="flex items-center justify-between p-2 pl-4 sm:pl-6 cursor-pointer hover:bg-gray-50 transition">
                                    <div class="flex items-center gap-2 flex-1 min-w-0">
                                        <i :class="expandidosProductos[`${fechaIndex}_${productoIndex}`] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-gray-400 text-xs flex-shrink-0"></i>
                                        <div class="w-5 h-5 rounded flex items-center justify-center flex-shrink-0"
                                            :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                            <i class="fas fa-box text-[10px]"></i>
                                        </div>
                                        <span class="font-medium text-gray-800 text-xs truncate">{{ productoData.producto }}</span>
                                        <span class="text-[10px] text-gray-400 flex-shrink-0">{{ productoData.detalles.length }} ventas</span>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-2">
                                        <span class="text-xs font-semibold" :style="{ color: `var(--color-primary-700)` }">{{ formatearMoneda(productoData.total_ventas_producto) }}</span>
                                        <span class="text-[10px] text-gray-400 ml-1">({{ formatearNumero(productoData.total_unidades_producto) }})</span>
                                    </div>
                                </div>

                                <!-- DETALLES -->
                                <div v-if="expandidosProductos[`${fechaIndex}_${productoIndex}`]" class="bg-gray-50">
                                    <div class="hidden sm:block overflow-x-auto p-2">
                                        <table class="min-w-full text-xs">
                                            <thead class="bg-gray-100">
                                                <tr>
                                                    <th class="px-2 py-1 text-left">Factura</th>
                                                    <th class="px-2 py-1 text-left">Grupo</th>
                                                    <th class="px-2 py-1 text-left">Producto</th>
                                                    <th class="px-2 py-1 text-center">Cantidad</th>
                                                    <th class="px-2 py-1 text-center">Precio</th>
                                                    <th class="px-2 py-1 text-center">Subtotal</th>
                                                    <th class="px-2 py-1 text-left">Vendedor</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-for="detalle in productoData.detalles" :key="detalle.numero_factura" class="hover:bg-gray-100">
                                                    <td class="px-2 py-1.5 font-mono">{{ detalle.numero_factura }}</td>
                                                    <td class="px-2 py-1.5">{{ detalle.id_venta_grupo }}</td>
                                                    <td class="px-2 py-1.5 truncate max-w-[120px]" :title="detalle.descripcion_producto">{{ detalle.descripcion_producto || '-' }}</td>
                                                    <td class="px-2 py-1.5 text-center">{{ formatearNumero(detalle.unidades) }}</td>
                                                    <td class="px-2 py-1.5 text-center">{{ formatearMoneda(detalle.precio_unitario) }}</td>
                                                    <td class="px-2 py-1.5 text-center font-medium" :style="{ color: `var(--color-primary-600)` }">
                                                        {{ formatearMoneda(detalle.total_bolivianos) }}
                                                    </td>
                                                    <td class="px-2 py-1.5">{{ detalle.operador || '-' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Mobile cards -->
                                    <div class="sm:hidden space-y-1 p-2">
                                        <div v-for="detalle in productoData.detalles" :key="detalle.numero_factura" 
                                            class="bg-white rounded-lg p-2 shadow-sm text-xs">
                                            <div class="flex justify-between items-start mb-1">
                                                <span class="font-mono font-bold">#{{ detalle.numero_factura }}</span>
                                                <span class="font-bold" :style="{ color: `var(--color-primary-600)` }">{{ formatearMoneda(detalle.total_bolivianos) }}</span>
                                            </div>
                                            <div class="text-gray-600">Grupo: {{ detalle.id_venta_grupo }}</div>
                                            <div class="text-gray-600 truncate">Producto: {{ detalle.descripcion_producto || detalle.detalle_producto }}</div>
                                            <div class="flex justify-between mt-1">
                                                <span class="text-gray-500">Cant: {{ formatearNumero(detalle.unidades) }}</span>
                                                <span class="text-gray-500">Precio: {{ formatearMoneda(detalle.precio_unitario) }}</span>
                                            </div>
                                            <div class="text-gray-500 mt-0.5">Vendedor: {{ detalle.operador || '-' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sin resultados -->
                <div v-else-if="!cargando && sucursalId && !errorFiltro && reporte.length === 0" class="bg-white rounded-lg shadow-sm p-6 text-center text-gray-400">
                    <i class="fas fa-chart-line text-xl mb-1 block"></i>
                    <span class="text-xs">No hay ventas con los filtros seleccionados</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:focus, select:focus {
    --tw-ring-color: var(--color-primary-500);
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>