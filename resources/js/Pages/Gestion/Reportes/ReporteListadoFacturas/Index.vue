<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    facturas: Object,
    operadores: Array,
    filtros: Object,
    tieneFiltros: Boolean,
})

// Estado responsive
const vistaMobile = ref(window.innerWidth < 768)

// Filtros
const fecha = ref(props.filtros?.fecha || '')
const fechaDesde = ref(props.filtros?.fecha_desde || '')
const fechaHasta = ref(props.filtros?.fecha_hasta || '')
const estado = ref(props.filtros?.estado || '')
const numeroFactura = ref(props.filtros?.numero_factura || '')
const tipoBusqueda = ref(props.filtros?.fecha ? 'dia' : (props.filtros?.fecha_desde ? 'rango' : 'dia'))

// Estado para filtros expandidos en móvil
const filtrosExpandidos = ref(false)

// Detectar cambio de tamaño
const handleResize = () => {
    vistaMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

const aplicarFiltros = () => {
    const params = {}
    
    if (tipoBusqueda.value === 'dia') {
        if (fecha.value) params.fecha = fecha.value
    } else {
        if (fechaDesde.value) params.fecha_desde = fechaDesde.value
        if (fechaHasta.value) params.fecha_hasta = fechaHasta.value
    }
    
    if (estado.value !== '') params.estado = estado.value
    if (numeroFactura.value) params.numero_factura = numeroFactura.value
    
    router.get('/gestion/reporte-listado-facturas', params, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltros = () => {
    fecha.value = ''
    fechaDesde.value = ''
    fechaHasta.value = ''
    estado.value = ''
    numeroFactura.value = ''
    tipoBusqueda.value = 'dia'
    aplicarFiltros()
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    })
}

const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

const estadoTexto = (estado) => {
    return estado == 1 ? 'Activa' : 'Anulada'
}

const estadoClase = (estado) => {
    return estado == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
}

const reimprimir = (id) => {
    window.open(`/gestion/reporte-listado-facturas/reimprimir/${id}`, '_blank')
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-3 sm:py-4 px-2 sm:px-4 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-invoice text-primary-600 text-sm sm:text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Listado de Facturas</h1>
                            <p class="text-[10px] sm:text-xs text-gray-500">Historial de comprobantes emitidos</p>
                        </div>
                    </div>
                    
                    <!-- Botón toggle filtros en móvil -->
                    <button 
                        v-if="vistaMobile"
                        @click="filtrosExpandidos = !filtrosExpandidos"
                        class="flex items-center gap-1 px-3 py-1.5 text-xs bg-gray-100 rounded-md text-gray-600"
                    >
                        <i :class="filtrosExpandidos ? 'fas fa-chevron-up' : 'fas fa-filter'"></i>
                        {{ filtrosExpandidos ? 'Ocultar filtros' : 'Mostrar filtros' }}
                    </button>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4" :class="{ 'hidden sm:block': !filtrosExpandidos && vistaMobile }">
                    <div class="flex flex-col sm:flex-row flex-wrap items-start sm:items-end gap-3">
                        <!-- Tipo de búsqueda -->
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-1">
                                <input type="radio" v-model="tipoBusqueda" value="dia" class="w-3.5 h-3.5 text-primary-600"> 
                                <span class="text-xs">Un día</span>
                            </label>
                            <label class="flex items-center gap-1">
                                <input type="radio" v-model="tipoBusqueda" value="rango" class="w-3.5 h-3.5 text-primary-600"> 
                                <span class="text-xs">Rango</span>
                            </label>
                        </div>

                        <!-- Fecha (día) -->
                        <div v-if="tipoBusqueda === 'dia'" class="w-full sm:w-auto">
                            <label class="block sm:hidden text-[10px] text-gray-500 mb-0.5">Fecha</label>
                            <input type="date" v-model="fecha" class="w-full sm:w-44 border rounded-md px-2 py-1.5 text-sm">
                        </div>

                        <!-- Fechas (rango) -->
                        <div v-if="tipoBusqueda === 'rango'" class="w-full sm:w-auto">
                            <label class="block sm:hidden text-[10px] text-gray-500 mb-0.5">Desde - Hasta</label>
                            <div class="flex items-center gap-1">
                                <input type="date" v-model="fechaDesde" class="flex-1 sm:w-36 border rounded-md px-2 py-1.5 text-sm" placeholder="Desde">
                                <span class="text-xs text-gray-400">-</span>
                                <input type="date" v-model="fechaHasta" class="flex-1 sm:w-36 border rounded-md px-2 py-1.5 text-sm" placeholder="Hasta">
                            </div>
                        </div>

                        <!-- Estado -->
                        <div class="w-full sm:w-32">
                            <label class="block sm:hidden text-[10px] text-gray-500 mb-0.5">Estado</label>
                            <select v-model="estado" class="w-full border rounded-md px-2 py-1.5 text-sm">
                                <option value="">Estado</option>
                                <option value="1">Activa</option>
                                <option value="2">Anulada</option>
                            </select>
                        </div>

                        <!-- N° Factura -->
                        <div class="w-full sm:w-36">
                            <label class="block sm:hidden text-[10px] text-gray-500 mb-0.5">N° Factura</label>
                            <input type="text" v-model="numeroFactura" class="w-full border rounded-md px-2 py-1.5 text-sm" placeholder="N° Factura">
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                            <button @click="aplicarFiltros" class="flex-1 sm:flex-none px-3 py-1.5 text-sm bg-primary-600 text-white rounded-md hover:bg-primary-700 transition flex items-center justify-center gap-1">
                                <i class="fas fa-search text-xs"></i> Buscar
                            </button>
                            <button @click="limpiarFiltros" class="flex-1 sm:flex-none px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 border rounded-md transition flex items-center justify-center gap-1">
                                <i class="fas fa-eraser text-xs"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mensaje sin filtros -->
                <div v-if="!tieneFiltros" class="bg-blue-50 rounded-lg p-6 sm:p-8 text-center mb-4">
                    <i class="fas fa-calendar-alt text-blue-400 text-2xl sm:text-3xl mb-2 block"></i>
                    <p class="text-xs sm:text-sm text-blue-700">Seleccione fechas y presione "Buscar" para ver las facturas</p>
                </div>

                <!-- Tabla Desktop -->
                <div v-else-if="!vistaMobile" class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 sm:px-4 py-2 text-left text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 sm:px-4 py-2 text-left text-xs font-medium text-primary-700 uppercase">N° Factura</th>
                                    <th class="px-3 sm:px-4 py-2 text-left text-xs font-medium text-primary-700 uppercase">Cliente</th>
                                    <th class="px-3 sm:px-4 py-2 text-right text-xs font-medium text-primary-700 uppercase">Importe</th>
                                    <th class="px-3 sm:px-4 py-2 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 sm:px-4 py-2 text-center text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr 
                                    v-for="factura in facturas.data" 
                                    :key="factura.IdVentas"
                                    class="hover:bg-gray-50 transition"
                                >
                                    <td class="px-3 sm:px-4 py-2 text-sm text-gray-600 whitespace-nowrap">{{ formatearFecha(factura.FechaVenta) }}</td>
                                    <td class="px-3 sm:px-4 py-2 text-sm font-mono text-gray-900 whitespace-nowrap">{{ factura.NumeroFactura }}</td>
                                    <td class="px-3 sm:px-4 py-2 text-sm text-gray-700">
                                        <div class="font-medium max-w-[200px] truncate" :title="factura.NombreCliente || 'CONSUMIDOR FINAL'">
                                            {{ factura.NombreCliente || 'CONSUMIDOR FINAL' }}
                                        </div>
                                        <div class="text-xs text-gray-400">NIT: {{ factura.NITCliente || '0' }}</div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 text-sm text-right font-semibold whitespace-nowrap" :class="factura.IdEstado === 2 ? 'text-gray-400' : 'text-primary-600'">
                                        {{ formatearNumero(factura.ImporteVenta) }} Bs
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 text-center whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="estadoClase(factura.IdEstado)">
                                            {{ estadoTexto(factura.IdEstado) }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 text-center whitespace-nowrap">
                                        <button 
                                            @click="reimprimir(factura.IdVentas)" 
                                            class="text-primary-600 hover:text-primary-800 p-1"
                                            title="Reimprimir comprobante"
                                        >
                                            <i class="fas fa-print text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="facturas.data.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                        <i class="fas fa-file-invoice text-3xl mb-2 block"></i>
                                        <p class="text-sm">No hay facturas que coincidan con los filtros</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="facturas.links && facturas.links.length > 1" class="px-3 sm:px-4 py-3 border-t border-gray-200">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <div class="text-xs sm:text-sm text-gray-500">
                                Mostrando {{ facturas.from || 0 }} a {{ facturas.to || 0 }} de {{ facturas.total || 0 }}
                            </div>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link v-for="link in facturas.links" :key="link.label" :href="link.url || '#'" class="px-2 sm:px-3 py-1 rounded border text-xs sm:text-sm" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 🔥 Tarjetas MÓVIL -->
                <div v-else-if="vistaMobile && facturas.data && facturas.data.length > 0" class="space-y-3">
                    <div v-for="factura in facturas.data" :key="factura.IdVentas" class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-100">
                        <!-- Cabecera de tarjeta -->
                        <div class="bg-gray-50 px-3 py-2 border-b border-gray-100 flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-receipt text-primary-500 text-xs"></i>
                                <span class="text-xs font-mono font-bold text-gray-800">N° {{ factura.NumeroFactura }}</span>
                            </div>
                            <span class="text-[10px] text-gray-500">{{ formatearFecha(factura.FechaVenta) }}</span>
                        </div>
                        
                        <!-- Cuerpo de tarjeta -->
                        <div class="p-3 space-y-2">
                            <!-- Cliente -->
                            <div>
                                <p class="text-[10px] text-gray-500">Cliente</p>
                                <p class="text-sm font-medium text-gray-800 break-words">{{ factura.NombreCliente || 'CONSUMIDOR FINAL' }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">NIT: {{ factura.NITCliente || '0' }}</p>
                            </div>
                            
                            <!-- Importe y Estado -->
                            <div class="flex justify-between items-center pt-1">
                                <div>
                                    <p class="text-[10px] text-gray-500">Importe</p>
                                    <p class="text-base font-bold" :class="factura.IdEstado === 2 ? 'text-gray-400' : 'text-primary-600'">
                                        {{ formatearNumero(factura.ImporteVenta) }} Bs
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-1 text-[10px] rounded-full" :class="estadoClase(factura.IdEstado)">
                                        {{ estadoTexto(factura.IdEstado) }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Botón Reimprimir -->
                            <div class="pt-2 border-t border-gray-100">
                                <button 
                                    @click="reimprimir(factura.IdVentas)" 
                                    class="w-full flex items-center justify-center gap-2 px-3 py-1.5 bg-primary-50 text-primary-600 rounded-md text-xs font-medium hover:bg-primary-100 transition"
                                >
                                    <i class="fas fa-print text-xs"></i> Reimprimir comprobante
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Paginación móvil -->
                    <div v-if="facturas.links && facturas.links.length > 1" class="flex justify-center gap-1 py-3 flex-wrap">
                        <Link v-for="link in facturas.links" :key="link.label" :href="link.url || '#'" class="px-2 py-1 rounded border text-xs" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                    </div>
                </div>

                <!-- Sin resultados móvil -->
                <div v-else-if="vistaMobile && facturas.data.length === 0 && !cargando" class="bg-white rounded-lg p-8 text-center">
                    <i class="fas fa-file-invoice text-gray-300 text-3xl mb-2 block"></i>
                    <p class="text-xs text-gray-500">No hay facturas que coincidan con los filtros</p>
                </div>
            </div>
        </div>
    </div>
</template>