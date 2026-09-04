<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    facturas: Object,
    operadores: Array,
    filtros: Object,
    tieneFiltros: Boolean,
})

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== FILTROS ====================
const fecha = ref(props.filtros?.fecha || '')
const fechaDesde = ref(props.filtros?.fecha_desde || '')
const fechaHasta = ref(props.filtros?.fecha_hasta || '')
const estado = ref(props.filtros?.estado || '')
const numeroFactura = ref(props.filtros?.numero_factura || '')
const tipoBusqueda = ref(props.filtros?.fecha ? 'dia' : (props.filtros?.fecha_desde ? 'rango' : 'dia'))

// ==================== ESTADO DE VISTA ====================
const mostrarAgrupado = ref(false)
const gruposExpandidos = ref({})

// ==================== COMPUTED ====================
const hayFiltrosAplicados = computed(() => {
    if (tipoBusqueda.value === 'dia') {
        return !!fecha.value
    }
    return !!fechaDesde.value && !!fechaHasta.value
})

const facturasPorFecha = computed(() => {
    if (!props.facturas?.data || props.facturas.data.length === 0) return []
    
    const grupos = {}
    
    props.facturas.data.forEach(factura => {
        const fechaStr = factura.FechaVenta ? new Date(factura.FechaVenta).toLocaleDateString('es-BO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }) : 'Sin fecha'
        
        if (!grupos[fechaStr]) {
            grupos[fechaStr] = {
                fecha: fechaStr,
                fechaRaw: factura.FechaVenta,
                cantidad: 0,
                total: 0,
                facturas: []
            }
        }
        grupos[fechaStr].cantidad++
        grupos[fechaStr].total += parseFloat(factura.ImporteVenta) || 0
        grupos[fechaStr].facturas.push(factura)
    })
    
    return Object.values(grupos).sort((a, b) => {
        const dateA = a.fechaRaw ? new Date(a.fechaRaw) : 0
        const dateB = b.fechaRaw ? new Date(b.fechaRaw) : 0
        return dateB - dateA
    })
})

const totalImporte = computed(() => {
    if (!props.facturas?.data || props.facturas.data.length === 0) return 0
    return props.facturas.data.reduce((sum, factura) => {
        return sum + (parseFloat(factura.ImporteVenta) || 0)
    }, 0)
})

const totalFacturas = computed(() => {
    return props.facturas?.data?.length || 0
})

const mostrarBotonAgrupar = computed(() => {
    return tipoBusqueda.value === 'rango' && 
           props.tieneFiltros && 
           props.facturas?.data && 
           props.facturas.data.length > 0
})

// ==================== MÉTODOS ====================
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
    
    mostrarAgrupado.value = false
    gruposExpandidos.value = {}
    
    router.get('/gestion/reportes/listado-facturas', params, {
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
    mostrarAgrupado.value = false
    gruposExpandidos.value = {}
    router.get('/gestion/reportes/listado-facturas', {}, {
        preserveState: true,
        replace: true,
    })
}

const toggleAgrupado = () => {
    mostrarAgrupado.value = !mostrarAgrupado.value
    if (mostrarAgrupado.value) {
        facturasPorFecha.value.forEach((grupo, index) => {
            gruposExpandidos.value[index] = true
        })
    }
}

const toggleGrupo = (index) => {
    gruposExpandidos.value[index] = !gruposExpandidos.value[index]
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
    return estado == 1 ? 'Activa' : estado == 2 ? 'Anulada' : 'Pendiente'
}

const estadoClase = (estado) => {
    if (estado == 1) return 'bg-emerald-100 text-emerald-700'
    if (estado == 2) return 'bg-red-100 text-red-700'
    return 'bg-yellow-100 text-yellow-700'
}

const reimprimir = (id) => {
    window.open(`/gestion/reportes/listado-facturas/reimprimir/${id}`, '_blank')
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
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-file-invoice text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-lg lg:text-xl font-bold text-gray-800">Listado de Facturas</h1>
                        <p class="text-xs text-gray-500">Historial de comprobantes emitidos</p>
                    </div>
                </div>

                <!-- ==================== FILTROS COMPACTOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <!-- Fila única con todos los filtros -->
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Tipo de búsqueda -->
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer whitespace-nowrap">
                                <input type="radio" v-model="tipoBusqueda" value="dia" class="w-3 h-3 text-primary-600"> 
                                <span>Día</span>
                            </label>
                            <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer whitespace-nowrap">
                                <input type="radio" v-model="tipoBusqueda" value="rango" class="w-3 h-3 text-primary-600"> 
                                <span>Rango</span>
                            </label>
                        </div>

                        <!-- Fecha (día) -->
                        <div v-if="tipoBusqueda === 'dia'" class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Fecha:</label>
                            <input type="date" v-model="fecha" class="w-36 border border-gray-300 rounded-md px-2 py-1 text-sm">
                        </div>

                        <!-- Fechas (rango) -->
                        <div v-if="tipoBusqueda === 'rango'" class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Desde:</label>
                            <input type="date" v-model="fechaDesde" class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Hasta:</label>
                            <input type="date" v-model="fechaHasta" class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm">
                        </div>

                        <!-- Estado -->
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Estado:</label>
                            <select v-model="estado" class="w-28 border border-gray-300 rounded-md px-2 py-1 text-sm">
                                <option value="">Todos</option>
                                <option value="1">Activa</option>
                                <option value="2">Anulada</option>
                            </select>
                        </div>

                        <!-- N° Factura -->
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">N° Factura:</label>
                            <input type="text" v-model="numeroFactura" placeholder="Buscar..." 
                                class="w-28 border border-gray-300 rounded-md px-2 py-1 text-sm">
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-1.5 ml-auto">
                            <button @click="aplicarFiltros" 
                                :disabled="!hayFiltrosAplicados"
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-search text-[10px]"></i>
                                <span>Buscar</span>
                            </button>
                            <button @click="limpiarFiltros" 
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-eraser text-[10px]"></i>
                                <span>Limpiar</span>
                            </button>
                            
                            <button 
                                v-if="mostrarBotonAgrupar"
                                @click="toggleAgrupado"
                                class="px-3 py-1.5 bg-emerald-600 text-white rounded-md text-xs font-medium hover:bg-emerald-700 transition flex items-center gap-1"
                            >
                                <i class="fas" :class="mostrarAgrupado ? 'fa-list-ul' : 'fa-calendar-alt'"></i>
                                <span>{{ mostrarAgrupado ? 'Lista' : 'Resumen' }}</span>
                                <span class="bg-white/20 rounded-full px-1.5 py-0.5 text-[9px]">{{ facturasPorFecha.length }}</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Resumen rápido -->
                    <div v-if="tieneFiltros && facturas.data && facturas.data.length > 0" 
                        class="flex items-center gap-4 mt-2 pt-2 border-t border-gray-100 text-xs">
                        <span class="text-gray-600">
                            <i class="fas fa-file-invoice text-primary-500 mr-1"></i>
                            {{ totalFacturas }} facturas
                        </span>
                        <span class="text-gray-600">
                            <i class="fas fa-money-bill-wave text-primary-500 mr-1"></i>
                            {{ formatearNumero(totalImporte) }} Bs
                        </span>
                    </div>
                </div>

                <!-- ==================== MENSAJE SIN FILTROS ==================== -->
                <div v-if="!tieneFiltros" class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <i class="fas fa-calendar-alt text-5xl text-gray-300 mb-3 block"></i>
                    <p class="text-base font-medium text-gray-600">Seleccione fechas para ver las facturas</p>
                    <p class="text-sm text-gray-400 mt-1">Elija un día específico o un rango de fechas</p>
                </div>

                <!-- ==================== VISTA AGRUPADA POR FECHA ==================== -->
                <div v-else-if="mostrarAgrupado && facturas.data && facturas.data.length > 0">
                    
                    <div 
                        v-for="(grupo, index) in facturasPorFecha" 
                        :key="grupo.fecha"
                        class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200 mb-3"
                    >
                        <!-- Cabecera del día -->
                        <div 
                            @click="toggleGrupo(index)"
                            class="flex flex-wrap justify-between items-center px-4 py-2.5 bg-primary-50 border-b border-primary-100 cursor-pointer hover:bg-primary-100 transition"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-6 h-6 rounded-full bg-primary-600 flex items-center justify-center text-white text-[10px] font-bold">
                                    {{ index + 1 }}
                                </div>
                                <div>
                                    <span class="font-semibold text-gray-800 text-sm">{{ grupo.fecha }}</span>
                                    <span class="ml-2 text-xs text-gray-500">
                                        {{ grupo.cantidad }} factura{{ grupo.cantidad > 1 ? 's' : '' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-primary-600">{{ formatearNumero(grupo.total) }} Bs</span>
                                <i class="fas text-gray-400 text-xs" 
                                   :class="gruposExpandidos[index] ? 'fa-chevron-up' : 'fa-chevron-down'">
                                </i>
                            </div>
                        </div>
                        
                        <!-- Lista de facturas del día -->
                        <div v-show="gruposExpandidos[index]" class="divide-y divide-gray-100">
                            <div v-if="!isMobile">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-1.5 text-left text-[10px] font-medium text-gray-500 uppercase">N° Factura</th>
                                            <th class="px-3 py-1.5 text-left text-[10px] font-medium text-gray-500 uppercase">Cliente</th>
                                            <th class="px-3 py-1.5 text-right text-[10px] font-medium text-gray-500 uppercase">Importe</th>
                                            <th class="px-3 py-1.5 text-center text-[10px] font-medium text-gray-500 uppercase">Estado</th>
                                            <th class="px-3 py-1.5 text-center text-[10px] font-medium text-gray-500 uppercase">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <tr v-for="factura in grupo.facturas" :key="factura.IdVentas" class="hover:bg-gray-50 transition">
                                            <td class="px-3 py-1.5 text-xs font-mono text-gray-900 whitespace-nowrap">#{{ factura.NumeroFactura }}</td>
                                            <td class="px-3 py-1.5 text-xs text-gray-700">
                                                <div class="max-w-[180px] truncate" :title="factura.NombreCliente || 'CONSUMIDOR FINAL'">
                                                    {{ factura.NombreCliente || 'CONSUMIDOR FINAL' }}
                                                </div>
                                            </td>
                                            <td class="px-3 py-1.5 text-xs text-right font-semibold" :class="factura.IdEstado === 2 ? 'text-gray-400' : 'text-primary-600'">
                                                {{ formatearNumero(factura.ImporteVenta) }} Bs
                                            </td>
                                            <td class="px-3 py-1.5 text-center">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="estadoClase(factura.IdEstado)">
                                                    {{ estadoTexto(factura.IdEstado) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-1.5 text-center">
                                                <button @click.stop="reimprimir(factura.IdVentas)" class="text-primary-600 hover:text-primary-800 p-1" title="Reimprimir">
                                                    <i class="fas fa-print text-xs"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr class="border-t border-gray-200">
                                            <td colspan="2" class="px-3 py-1.5 text-xs font-bold text-gray-800 text-right">TOTAL DEL DÍA</td>
                                            <td class="px-3 py-1.5 text-xs text-right font-bold text-primary-700">{{ formatearNumero(grupo.total) }} Bs</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Vista móvil -->
                            <div v-else class="p-2 space-y-2">
                                <div v-for="factura in grupo.facturas" :key="factura.IdVentas" class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                    <div class="flex justify-between items-start mb-1.5">
                                        <span class="text-xs font-mono font-bold text-gray-800">#{{ factura.NumeroFactura }}</span>
                                        <span class="text-[9px] px-1.5 py-0.5 rounded-full" :class="estadoClase(factura.IdEstado)">
                                            {{ estadoTexto(factura.IdEstado) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-700 truncate">{{ factura.NombreCliente || 'CONSUMIDOR FINAL' }}</p>
                                    <div class="flex justify-between items-center pt-1.5 border-t border-gray-200 mt-1.5">
                                        <span class="text-sm font-bold text-primary-600">{{ formatearNumero(factura.ImporteVenta) }} Bs</span>
                                        <button @click.stop="reimprimir(factura.IdVentas)" class="text-primary-600 hover:text-primary-800 p-1">
                                            <i class="fas fa-print text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total general -->
                    <div class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-xl shadow-sm p-3 text-white mt-4">
                        <div class="flex flex-wrap justify-between items-center">
                            <div>
                                <p class="text-[10px] text-primary-100 font-medium">TOTAL GENERAL</p>
                                <p class="text-xs text-primary-200">{{ totalFacturas }} facturas en {{ facturasPorFecha.length }} días</p>
                            </div>
                            <p class="text-lg font-bold">{{ formatearNumero(totalImporte) }} Bs</p>
                        </div>
                    </div>

                    <!-- Paginación agrupada -->
                    <div v-if="facturas.links && facturas.links.length > 1" class="mt-4 bg-white rounded-xl shadow-sm px-4 py-2.5 border-t border-gray-200">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <div class="text-xs text-gray-500">
                                Mostrando {{ facturas.from || 0 }} a {{ facturas.to || 0 }} de {{ facturas.total || 0 }}
                            </div>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link v-for="link in facturas.links" :key="link.label" :href="link.url || '#'" 
                                    class="px-2.5 py-1 rounded-lg border text-xs transition"
                                    :class="{
                                        'bg-primary-600 text-white border-primary-600': link.active,
                                        'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url,
                                        'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url
                                    }"
                                    v-html="link.label"
                                    @click="mostrarAgrupado = true" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== VISTA NORMAL (LISTA) ==================== -->
                <div v-else-if="tieneFiltros && facturas.data && facturas.data.length > 0">
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                            <!-- VISTA MÓVIL -->
                            <div v-if="isMobile" class="p-2 space-y-2">
                                <div v-for="factura in facturas.data" :key="factura.IdVentas" class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                    <div class="flex justify-between items-start mb-1.5">
                                        <div>
                                            <p class="text-xs font-mono font-bold text-gray-800">#{{ factura.NumeroFactura }}</p>
                                            <p class="text-[9px] text-gray-500">{{ formatearFecha(factura.FechaVenta) }}</p>
                                        </div>
                                        <span class="text-[9px] px-1.5 py-0.5 rounded-full" :class="estadoClase(factura.IdEstado)">
                                            {{ estadoTexto(factura.IdEstado) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-700 truncate">{{ factura.NombreCliente || 'CONSUMIDOR FINAL' }}</p>
                                    <div class="flex justify-between items-center pt-1.5 border-t border-gray-200 mt-1.5">
                                        <span class="text-sm font-bold" :class="factura.IdEstado === 2 ? 'text-gray-400' : 'text-primary-600'">
                                            {{ formatearNumero(factura.ImporteVenta) }} Bs
                                        </span>
                                        <button @click="reimprimir(factura.IdVentas)" class="text-primary-600 hover:text-primary-800 p-1">
                                            <i class="fas fa-print text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- VISTA TABLET -->
                            <div v-else-if="isTablet" class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-primary-50 sticky top-0 z-10">
                                        <tr>
                                            <th class="px-2 py-1.5 text-left text-[10px] font-medium text-primary-700 uppercase">Fecha</th>
                                            <th class="px-2 py-1.5 text-left text-[10px] font-medium text-primary-700 uppercase">N°</th>
                                            <th class="px-2 py-1.5 text-left text-[10px] font-medium text-primary-700 uppercase">Cliente</th>
                                            <th class="px-2 py-1.5 text-right text-[10px] font-medium text-primary-700 uppercase">Importe</th>
                                            <th class="px-2 py-1.5 text-center text-[10px] font-medium text-primary-700 uppercase">Estado</th>
                                            <th class="px-2 py-1.5 text-center text-[10px] font-medium text-primary-700 uppercase">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="factura in facturas.data" :key="factura.IdVentas" class="hover:bg-gray-50 transition">
                                            <td class="px-2 py-1.5 text-[10px] text-gray-600 whitespace-nowrap">{{ formatearFecha(factura.FechaVenta) }}</td>
                                            <td class="px-2 py-1.5 text-[10px] font-mono text-gray-900 whitespace-nowrap">#{{ factura.NumeroFactura }}</td>
                                            <td class="px-2 py-1.5 text-[10px] text-gray-700 max-w-[100px] truncate">{{ factura.NombreCliente || 'CONSUMIDOR FINAL' }}</td>
                                            <td class="px-2 py-1.5 text-[10px] text-right font-semibold" :class="factura.IdEstado === 2 ? 'text-gray-400' : 'text-primary-600'">
                                                {{ formatearNumero(factura.ImporteVenta) }}
                                            </td>
                                            <td class="px-2 py-1.5 text-center">
                                                <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(factura.IdEstado)">
                                                    {{ estadoTexto(factura.IdEstado) }}
                                                </span>
                                            </td>
                                            <td class="px-2 py-1.5 text-center">
                                                <button @click="reimprimir(factura.IdVentas)" class="text-primary-600 hover:text-primary-800 p-0.5">
                                                    <i class="fas fa-print text-[10px]"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50 sticky bottom-0">
                                        <tr class="border-t border-gray-200">
                                            <td colspan="3" class="px-2 py-1.5 text-[10px] font-bold text-gray-800">TOTAL</td>
                                            <td class="px-2 py-1.5 text-[10px] text-right font-bold text-primary-700">{{ formatearNumero(totalImporte) }} Bs</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- VISTA ESCRITORIO -->
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-primary-50 sticky top-0 z-10">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">N° Factura</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Cliente</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase w-28">Importe</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase w-20">Estado</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase w-14">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="factura in facturas.data" :key="factura.IdVentas" class="hover:bg-gray-50 transition">
                                            <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">{{ formatearFecha(factura.FechaVenta) }}</td>
                                            <td class="px-3 py-2 text-xs font-mono text-gray-900 whitespace-nowrap">#{{ factura.NumeroFactura }}</td>
                                            <td class="px-3 py-2 text-xs text-gray-700">
                                                <div class="max-w-[180px] truncate" :title="factura.NombreCliente || 'CONSUMIDOR FINAL'">
                                                    {{ factura.NombreCliente || 'CONSUMIDOR FINAL' }}
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-xs text-right font-semibold" :class="factura.IdEstado === 2 ? 'text-gray-400' : 'text-primary-600'">
                                                {{ formatearNumero(factura.ImporteVenta) }} Bs
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="estadoClase(factura.IdEstado)">
                                                    {{ estadoTexto(factura.IdEstado) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button @click="reimprimir(factura.IdVentas)" class="text-primary-600 hover:text-primary-800 p-1" title="Reimprimir">
                                                    <i class="fas fa-print text-xs"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50 sticky bottom-0">
                                        <tr class="border-t border-gray-200">
                                            <td colspan="3" class="px-3 py-2 text-xs font-bold text-gray-800">TOTAL GENERAL</td>
                                            <td class="px-3 py-2 text-xs text-right font-bold text-primary-700">{{ formatearNumero(totalImporte) }} Bs</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Paginación -->
                        <div v-if="facturas.links && facturas.links.length > 1" class="px-4 py-2.5 border-t border-gray-200 bg-gray-50">
                            <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                                <div class="text-xs text-gray-500">
                                    Mostrando {{ facturas.from || 0 }} a {{ facturas.to || 0 }} de {{ facturas.total || 0 }}
                                </div>
                                <div class="flex gap-1 flex-wrap justify-center">
                                    <Link v-for="link in facturas.links" :key="link.label" :href="link.url || '#'" 
                                        class="px-2.5 py-1 rounded-lg border text-xs transition"
                                        :class="{
                                            'bg-primary-600 text-white border-primary-600': link.active,
                                            'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url,
                                            'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url
                                        }"
                                        v-html="link.label"
                                        @click="mostrarAgrupado = false" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sin resultados -->
                <div v-else-if="tieneFiltros && facturas.data && facturas.data.length === 0" 
                    class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <i class="fas fa-file-invoice text-5xl text-gray-300 mb-3 block"></i>
                    <p class="text-base font-medium text-gray-600">No se encontraron facturas</p>
                    <p class="text-sm text-gray-400 mt-1">Prueba con otros filtros o fechas diferentes</p>
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
</style>