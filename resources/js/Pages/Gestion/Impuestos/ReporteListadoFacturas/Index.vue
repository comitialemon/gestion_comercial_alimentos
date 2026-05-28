<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    facturas: Object,
    operadores: Array,
    filtros: Object,
    tieneFiltros: Boolean,
})

// Filtros
const fecha = ref(props.filtros?.fecha || '')
const fechaDesde = ref(props.filtros?.fecha_desde || '')
const fechaHasta = ref(props.filtros?.fecha_hasta || '')
const estado = ref(props.filtros?.estado || '')
const numeroFactura = ref(props.filtros?.numero_factura || '')
const tipoBusqueda = ref(props.filtros?.fecha ? 'dia' : (props.filtros?.fecha_desde ? 'rango' : 'dia'))

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
    return new Date(fecha).toLocaleDateString('es-BO')
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
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-invoice text-primary-600"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">Listado de Facturas</h1>
                            <p class="text-xs text-gray-500">Historial de comprobantes emitidos</p>
                        </div>
                    </div>
                </div>

                <!-- Filtros en una sola fila -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-3">
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
                        <div v-if="tipoBusqueda === 'dia'">
                            <input type="date" v-model="fecha" class="w-44 border rounded-md px-2 py-1.5 text-sm">
                        </div>

                        <!-- Fechas (rango) -->
                        <div v-if="tipoBusqueda === 'rango'" class="flex items-center gap-1">
                            <input type="date" v-model="fechaDesde" class="w-36 border rounded-md px-2 py-1.5 text-sm" placeholder="Desde">
                            <span class="text-xs text-gray-400">-</span>
                            <input type="date" v-model="fechaHasta" class="w-36 border rounded-md px-2 py-1.5 text-sm" placeholder="Hasta">
                        </div>

                        <!-- Estado -->
                        <div class="w-32">
                            <select v-model="estado" class="w-full border rounded-md px-2 py-1.5 text-sm">
                                <option value="">Estado</option>
                                <option value="1">Activa</option>
                                <option value="2">Anulada</option>
                            </select>
                        </div>

                        <!-- N° Factura -->
                        <div class="w-36">
                            <input type="text" v-model="numeroFactura" class="w-full border rounded-md px-2 py-1.5 text-sm" placeholder="N° Factura">
                        </div>

                        <!-- Botones -->
                        <button @click="aplicarFiltros" class="px-3 py-1.5 text-sm bg-primary-600 text-white rounded-md hover:bg-primary-700 transition flex items-center gap-1">
                            <i class="fas fa-search text-xs"></i> Buscar
                        </button>
                        <button @click="limpiarFiltros" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 border rounded-md transition">
                            <i class="fas fa-eraser text-xs"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Mensaje sin filtros -->
                <div v-if="!tieneFiltros" class="bg-blue-50 rounded-lg p-8 text-center mb-4">
                    <i class="fas fa-calendar-alt text-blue-400 text-2xl mb-2 block"></i>
                    <p class="text-sm text-blue-700">Seleccione fechas y presione "Buscar" para ver las facturas</p>
                </div>

                <!-- Tabla -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-primary-700 uppercase">N° Factura</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-primary-700 uppercase">Cliente</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-primary-700 uppercase">Importe</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr 
                                    v-for="factura in facturas.data" 
                                    :key="factura.IdVentas"
                                    class="hover:bg-gray-50 transition"
                                    :style="factura.IdEstado === 2 ? 'color: #765618' : ''"
                                >
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ formatearFecha(factura.FechaVenta) }}</td>
                                    <td class="px-4 py-2 text-sm font-mono text-gray-900">{{ factura.NumeroFactura }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">
                                        <div class="font-medium">{{ factura.NombreCliente || 'CONSUMIDOR FINAL' }}</div>
                                        <div class="text-xs text-gray-400">NIT: {{ factura.NITCliente || '0' }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-semibold" :class="factura.IdEstado === 2 ? 'text-gray-400' : 'text-primary-600'">
                                        {{ formatearNumero(factura.ImporteVenta) }} Bs
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="estadoClase(factura.IdEstado)">
                                            {{ estadoTexto(factura.IdEstado) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <button 
                                            @click="reimprimir(factura.IdVentas)" 
                                            class="text-primary-600 hover:text-primary-800"
                                            title="Reimprimir comprobante"
                                        >
                                            <i class="fas fa-print text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="facturas.data.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                        <i class="fas fa-file-invoice text-3xl mb-2 block"></i>
                                        No hay facturas que coincidan con los filtros
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="facturas.links && facturas.links.length > 1" class="px-4 py-3 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ facturas.from || 0 }} a {{ facturas.to || 0 }} de {{ facturas.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link v-for="link in facturas.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border text-sm" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>