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
const sucursalId = ref('')
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

const haySucursalSeleccionada = computed(() => {
    return sucursalId.value && sucursalId.value !== '' && Number(sucursalId.value) > 0
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
    errorFiltro.value = ''
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
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 sm:py-3 px-2 sm:px-4 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-lg shadow-sm px-3 py-1.5 mb-2 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded flex items-center justify-center"
                             :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                            <i class="fas fa-chart-line text-xs"></i>
                        </div>
                        <h1 class="text-sm font-bold text-gray-800">Ventas por Producto</h1>
                    </div>
                    <div class="flex gap-0.5">
                        <button @click="expandirTodo" class="px-1.5 py-0.5 text-[10px] rounded transition"
                            :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                            <i class="fas fa-expand-alt"></i>
                        </button>
                        <button @click="contraerTodo" class="px-1.5 py-0.5 text-[10px] rounded transition"
                            :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                            <i class="fas fa-compress-alt"></i>
                        </button>
                        <button @click="volver" class="px-1.5 py-0.5 text-[10px] rounded transition"
                            :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                    </div>
                </div>

                <!-- 🔥 FILTROS ALINEADOS -->
                <div class="bg-white rounded-lg shadow-sm px-3 py-2.5 mb-2">
                    <div class="flex items-end gap-2.5 flex-wrap">
                        
                        <!-- Sucursal -->
                        <div class="sucursal-autocomplete flex-1 min-w-[160px] max-w-[200px]">
                            <div class="flex justify-between items-center mb-1">
                                <label class="block text-[10px] font-medium text-gray-600 leading-none">Sucursal</label>
                                <span v-if="sucursalId && sucursalNombre" class="text-[9px] text-primary-600 font-medium leading-none truncate ml-1">
                                    <i class="fas fa-check-circle"></i> {{ sucursalNombre }}
                                </span>
                            </div>
                            <div class="relative">
                                <input type="text" v-model="sucursalBusqueda" @focus="mostrarSucursales = true"
                                    @input="mostrarSucursales = true"
                                    class="w-full h-[30px] border border-gray-300 rounded px-2 text-[11px] pr-6 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                    placeholder="Seleccione..." autocomplete="off" />
                                <button v-if="sucursalBusqueda" @click="limpiarSucursal"
                                    class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-[9px]"></i>
                                </button>
                                <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded shadow-lg max-h-32 overflow-y-auto text-[11px]">
                                    <div v-for="suc in sucursalesDisponibles" :key="suc.id"
                                        @mousedown="seleccionarSucursal(suc)"
                                        class="px-2 py-1.5 cursor-pointer hover:bg-gray-50 flex justify-between items-center border-b border-gray-100 last:border-b-0"
                                        :class="sucursalId === suc.id ? 'bg-primary-50' : ''">
                                        <span class="truncate text-[11px]">{{ suc.nombre }}</span>
                                        <i v-if="sucursalId === suc.id" class="fas fa-check-circle text-[10px]" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tipo -->
                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-1 leading-none">Seleccione Tipo De Filtro Fecha</label>
                            <div class="flex h-[30px] items-center bg-gray-100 p-0.5 rounded border border-gray-200">
                                <button @click="tipoFiltro = 'fecha_unica'; limpiarFechas()"
                                    class="px-2.5 h-full text-[10px] rounded transition font-medium flex items-center justify-center"
                                    :class="tipoFiltro === 'fecha_unica' ? 'text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                    :style="tipoFiltro === 'fecha_unica' ? { backgroundColor: `var(--color-primary-600)` } : {}">
                                    Única
                                </button>
                                <button @click="tipoFiltro = 'rango'; limpiarFechas()"
                                    class="px-2.5 h-full text-[10px] rounded transition font-medium flex items-center justify-center"
                                    :class="tipoFiltro === 'rango' ? 'text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                    :style="tipoFiltro === 'rango' ? { backgroundColor: `var(--color-primary-600)` } : {}">
                                    Rango
                                </button>
                            </div>
                        </div>

                        <!-- Fechas -->
                        <div v-if="tipoFiltro === 'fecha_unica'" class="w-[130px]">
                            <label class="block text-[10px] font-medium text-gray-600 mb-1 leading-none">Fecha</label>
                            <input type="date" v-model="fechaUnica" 
                                class="w-full h-[30px] border border-gray-300 rounded px-2 text-[11px] focus:border-primary-500 focus:ring-1 focus:ring-primary-500" />
                        </div>
                        <div v-else class="flex gap-1.5">
                            <div class="w-[125px]">
                                <label class="block text-[10px] font-medium text-gray-600 mb-1 leading-none">Desde</label>
                                <input type="date" v-model="fechaInicio" 
                                    class="w-full h-[30px] border border-gray-300 rounded px-2 text-[11px] focus:border-primary-500 focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div class="w-[125px]">
                                <label class="block text-[10px] font-medium text-gray-600 mb-1 leading-none">Hasta</label>
                                <input type="date" v-model="fechaFin" 
                                    class="w-full h-[30px] border border-gray-300 rounded px-2 text-[11px] focus:border-primary-500 focus:ring-1 focus:ring-primary-500" />
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex gap-1 h-[30px]">
                            <button @click="cargarReporte" :disabled="cargando || !sucursalId"
                                class="px-3 h-full text-[10px] font-medium text-white rounded transition disabled:opacity-50 flex items-center justify-center gap-1.5 shadow-sm"
                                :style="{ backgroundColor: `var(--color-primary-600)` }">
                                <i v-if="cargando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-search text-[10px]"></i>
                                <span>Buscar</span>
                            </button>
                            <button @click="limpiarTodosLosFiltros"
                                title="Limpiar todos los filtros"
                                class="px-2.5 h-full text-[10px] rounded bg-gray-100 text-gray-600 hover:bg-gray-200 border border-gray-300 transition flex items-center justify-center">
                                <i class="fas fa-eraser text-[10px]"></i>
                            </button>
                        </div>

                    </div>

                    <!-- Mensajes de Estado -->
                    <div v-if="errorFiltro" class="mt-2 text-[10px] text-red-600 font-medium flex items-center gap-1">
                        <i class="fas fa-exclamation-circle"></i> {{ errorFiltro }}
                    </div>
                    <div v-if="advertencia" class="mt-2 text-[10px] text-amber-600 font-medium flex items-center gap-1">
                        <i class="fas fa-clock"></i> {{ advertencia }}
                    </div>
                </div>

                <!-- Mensaje sin sucursal -->
                <div v-if="!haySucursalSeleccionada" class="bg-white rounded-lg shadow-sm py-8 text-center">
                    <i class="fas fa-calendar-alt text-3xl text-gray-300 mb-2 block"></i>
                    <p class="text-base font-medium text-gray-600">Seleccione una Sucursal y una Fecha</p>
                    <p class="text-xs text-gray-400 mt-1">Use los campos de búsqueda arriba para filtrar</p>
                </div>

                <!-- Loading -->
                <div v-if="cargando && haySucursalSeleccionada" class="bg-white rounded-lg shadow-sm py-8 text-center">
                    <i class="fas fa-spinner fa-spin text-2xl" :style="{ color: `var(--color-primary-600)` }"></i>
                    <p class="text-gray-400 text-xs mt-1">Cargando...</p>
                </div>

                <!-- Totales -->
                <div v-else-if="reporte.length > 0 && haySucursalSeleccionada" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-2">
                    <div class="bg-white rounded shadow-sm px-3 py-1.5 text-center">
                        <p class="text-[9px] text-gray-400 uppercase">Unidades</p>
                        <p class="text-base font-bold" :style="{ color: `var(--color-primary-700)` }">{{ formatearNumero(totales.unidades) }}</p>
                    </div>
                    <div class="bg-white rounded shadow-sm px-3 py-1.5 text-center">
                        <p class="text-[9px] text-gray-400 uppercase">Total Bs</p>
                        <p class="text-base font-bold" :style="{ color: `var(--color-primary-700)` }">Bs {{ formatearMoneda(totales.ventas) }}</p>
                    </div>
                    <div class="bg-white rounded shadow-sm px-3 py-1.5 text-center">
                        <p class="text-[9px] text-gray-400 uppercase">Días</p>
                        <p class="text-base font-bold" :style="{ color: `var(--color-primary-700)` }">{{ totales.fechas }}</p>
                    </div>
                    <div class="bg-white rounded shadow-sm px-3 py-1.5 text-center">
                        <p class="text-[9px] text-gray-400 uppercase">Productos</p>
                        <p class="text-base font-bold" :style="{ color: `var(--color-primary-700)` }">{{ totales.productos }}</p>
                    </div>
                </div>

                <!-- Reporte -->
                <div v-if="reporte.length > 0 && !cargando && haySucursalSeleccionada" class="space-y-1.5">
                    <div v-for="(fechaData, fechaIndex) in reporte" :key="fechaIndex" class="bg-white rounded shadow-sm overflow-hidden">
                        <div @click="toggleFecha(fechaIndex)"
                            class="flex items-center justify-between px-3 py-2 cursor-pointer hover:bg-gray-50 transition">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <i :class="expandidosFechas[fechaIndex] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-gray-400 text-[10px] flex-shrink-0"></i>
                                <div class="w-6 h-6 rounded flex items-center justify-center flex-shrink-0"
                                    :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                    <i class="fas fa-calendar-alt text-[10px]"></i>
                                </div>
                                <span class="font-semibold text-gray-800 text-xs truncate">{{ fechaData.fecha }}</span>
                                <span class="text-[9px] text-gray-400 flex-shrink-0">{{ fechaData.productos.length }} prod</span>
                            </div>
                            <div class="text-right flex-shrink-0 ml-2">
                                <span class="text-xs font-bold" :style="{ color: `var(--color-primary-700)` }">Bs {{ formatearMoneda(fechaData.total_ventas_fecha) }}</span>
                                <span class="text-[8px] text-gray-400 ml-0.5">({{ formatearNumero(fechaData.total_unidades_fecha) }})</span>
                            </div>
                        </div>

                        <div v-if="expandidosFechas[fechaIndex]" class="border-t border-gray-100">
                            <div v-for="(productoData, productoIndex) in fechaData.productos" :key="productoIndex" 
                                class="border-b border-gray-100 last:border-b-0">
                                
                                <div @click="toggleProducto(fechaIndex, productoIndex)"
                                    class="flex items-center justify-between px-3 pl-6 py-1.5 cursor-pointer hover:bg-gray-50 transition">
                                    <div class="flex items-center gap-2 flex-1 min-w-0">
                                        <i :class="expandidosProductos[`${fechaIndex}_${productoIndex}`] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-gray-400 text-[9px] flex-shrink-0"></i>
                                        <div class="w-5 h-5 rounded flex items-center justify-center flex-shrink-0"
                                            :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                            <i class="fas fa-box text-[9px]"></i>
                                        </div>
                                        <span class="font-medium text-gray-800 text-[10px] truncate">{{ productoData.producto }}</span>
                                        <span class="text-[8px] text-gray-400 flex-shrink-0">{{ productoData.detalles.length }}</span>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-2">
                                        <span class="text-[10px] font-bold" :style="{ color: `var(--color-primary-700)` }">Bs {{ formatearMoneda(productoData.total_ventas_producto) }}</span>
                                        <span class="text-[8px] text-gray-400 ml-0.5">({{ formatearNumero(productoData.total_unidades_producto) }})</span>
                                    </div>
                                </div>

                                <div v-if="expandidosProductos[`${fechaIndex}_${productoIndex}`]" class="bg-gray-50 px-3 py-2">
                                    <div class="hidden sm:block overflow-x-auto">
                                        <table class="min-w-full text-[10px]">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="px-2 py-1 text-left font-medium text-gray-500">Factura</th>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-500">Grupo</th>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-500">Producto</th>
                                                    <th class="px-2 py-1 text-center font-medium text-gray-500">Cant.</th>
                                                    <th class="px-2 py-1 text-center font-medium text-gray-500">Precio</th>
                                                    <th class="px-2 py-1 text-center font-medium text-gray-500">Subtotal</th>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-500">Vendedor</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-for="detalle in productoData.detalles" :key="detalle.numero_factura" class="hover:bg-gray-100">
                                                    <td class="px-2 py-1 font-mono">{{ detalle.numero_factura }}</td>
                                                    <td class="px-2 py-1">{{ detalle.id_venta_grupo }}</td>
                                                    <td class="px-2 py-1 truncate max-w-[120px]" :title="detalle.descripcion_producto">{{ detalle.descripcion_producto || '-' }}</td>
                                                    <td class="px-2 py-1 text-center">{{ formatearNumero(detalle.unidades) }}</td>
                                                    <td class="px-2 py-1 text-center">{{ formatearMoneda(detalle.precio_unitario) }}</td>
                                                    <td class="px-2 py-1 text-center font-semibold" :style="{ color: `var(--color-primary-600)` }">
                                                        {{ formatearMoneda(detalle.total_bolivianos) }}
                                                    </td>
                                                    <td class="px-2 py-1">{{ detalle.operador || '-' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="sm:hidden space-y-1">
                                        <div v-for="detalle in productoData.detalles" :key="detalle.numero_factura" 
                                            class="bg-white rounded p-2 shadow-sm text-[10px]">
                                            <div class="flex justify-between items-start">
                                                <span class="font-mono font-bold">#{{ detalle.numero_factura }}</span>
                                                <span class="font-bold" :style="{ color: `var(--color-primary-600)` }">Bs {{ formatearMoneda(detalle.total_bolivianos) }}</span>
                                            </div>
                                            <div class="text-gray-500 text-[9px]">Grupo: {{ detalle.id_venta_grupo }}</div>
                                            <div class="text-gray-500 text-[9px] truncate">{{ detalle.descripcion_producto || detalle.detalle_producto }}</div>
                                            <div class="flex justify-between mt-0.5 text-[9px]">
                                                <span>Cant: {{ formatearNumero(detalle.unidades) }}</span>
                                                <span>Precio: {{ formatearMoneda(detalle.precio_unitario) }}</span>
                                            </div>
                                            <div class="text-gray-500 text-[9px]">Vendedor: {{ detalle.operador || '-' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sin resultados -->
                <div v-else-if="!cargando && haySucursalSeleccionada && !errorFiltro && reporte.length === 0" class="bg-white rounded shadow-sm py-8 text-center text-gray-400">
                    <i class="fas fa-chart-line text-3xl mb-2 block text-gray-300"></i>
                    <p class="text-sm">No hay ventas con estos filtros</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:focus {
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

.absolute.z-20 {
    animation: slideDown 0.12s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>