<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, Link } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    ventas: Object,
    estados: Array,
    sucursales: Array,
    vendedores: Array,
    estadisticas: Object,
    filtros: Object,
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
const sucursalId = ref(props.filtros?.sucursal_id || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const vendedorId = ref(props.filtros?.vendedor_id || '')
const vendedorBusqueda = ref('')
const mostrarVendedores = ref(false)

const estado = ref(props.filtros?.estado || '')
const search = ref(props.filtros?.search || '')
const loading = ref(false)

// ==================== COMPUTED ====================
const haySucursalSeleccionada = computed(() => {
    return sucursalId.value && sucursalId.value !== '' && Number(sucursalId.value) > 0
})

const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre?.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id === Number(sucursalId.value))
    return suc?.nombre || ''
})

const vendedoresDisponibles = computed(() => {
    if (!props.vendedores || props.vendedores.length === 0) return []
    if (!vendedorBusqueda.value) return props.vendedores
    
    const termino = vendedorBusqueda.value.toLowerCase()
    return props.vendedores.filter(v => 
        v.nombre_completo?.toLowerCase().includes(termino)
    )
})

const vendedorNombre = computed(() => {
    if (!vendedorId.value) return ''
    const ven = props.vendedores?.find(v => v.id === Number(vendedorId.value))
    return ven?.nombre_completo || ''
})

// ==================== FUNCIONES ====================
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    aplicarFiltros()
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    router.get('/gestion/ventas-editar', {}, {
        preserveState: true,
        replace: true,
    })
}

const seleccionarVendedor = (vendedor) => {
    vendedorId.value = vendedor.id
    vendedorBusqueda.value = vendedor.nombre_completo
    mostrarVendedores.value = false
    if (haySucursalSeleccionada.value) {
        aplicarFiltros()
    }
}

const limpiarVendedor = () => {
    vendedorId.value = ''
    vendedorBusqueda.value = ''
    mostrarVendedores.value = false
    if (haySucursalSeleccionada.value) {
        aplicarFiltros()
    }
}

const aplicarFiltros = () => {
    if (!haySucursalSeleccionada.value) return
    
    const params = {
        sucursal_id: sucursalId.value,
        vendedor_id: vendedorId.value || undefined,
        estado: estado.value || undefined,
        search: search.value || undefined,
    }
    
    router.get('/gestion/ventas-editar', params, {
        preserveState: true,
        preserveScroll: true,
    })
}

const limpiarFiltros = () => {
    vendedorId.value = ''
    vendedorBusqueda.value = ''
    estado.value = ''
    search.value = ''
    if (haySucursalSeleccionada.value) {
        aplicarFiltros()
    }
}

const editarVenta = (id) => {
    loading.value = true
    router.get(`/gestion/ventas-editar/${id}/edit`, {}, {
        onFinish: () => { loading.value = false }
    })
}

const formatDate = (date) => {
    if (!date) return '-'
    const d = new Date(date)
    return d.toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const getEstadoColor = (estadoId) => {
    const colors = {
        1: 'bg-emerald-100 text-emerald-700',
        2: 'bg-red-100 text-red-700',
        3: 'bg-yellow-100 text-yellow-700',
        4: 'bg-gray-100 text-gray-600',
        5: 'bg-orange-100 text-orange-700',
        6: 'bg-blue-100 text-blue-700',
    }
    return colors[estadoId] || 'bg-gray-100 text-gray-500'
}

const getEstadoAbrev = (estadoId) => {
    const abrev = {
        1: 'VÁLIDA',
        2: 'ANULADA',
        3: 'EXTRAV.',
        4: 'NO UTIL.',
        5: 'CONTING.',
        6: 'L. CONSIG.',
    }
    return abrev[estadoId] || '?'
}

// Cerrar autocompletes
const handleClickOutside = (event) => {
    const sucursalContainer = document.querySelector('.sucursal-autocomplete')
    if (sucursalContainer && !sucursalContainer.contains(event.target)) {
        mostrarSucursales.value = false
    }
    
    const vendedorContainer = document.querySelector('.vendedor-autocomplete')
    if (vendedorContainer && !vendedorContainer.contains(event.target)) {
        mostrarVendedores.value = false
    }
}

// 🔥 CONSTRUIR URL CON FILTROS PARA PAGINACIÓN
const construirUrlConFiltros = (url) => {
    if (!url) return '#'
    
    try {
        const urlObj = new URL(url, window.location.origin)
        const params = new URLSearchParams(urlObj.search)
        
        if (sucursalId.value) params.set('sucursal_id', sucursalId.value)
        if (vendedorId.value) params.set('vendedor_id', vendedorId.value)
        if (estado.value) params.set('estado', estado.value)
        if (search.value) params.set('search', search.value)
        
        urlObj.search = params.toString()
        return urlObj.toString()
    } catch (error) {
        return url
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
    
    // 🔥 RESTAURAR NOMBRE DE SUCURSAL DESDE EL ID
    if (sucursalId.value) {
        const sucursal = props.sucursales?.find(s => s.id === Number(sucursalId.value))
        if (sucursal) {
            sucursalBusqueda.value = sucursal.nombre
        }
    }
    
    if (vendedorId.value) {
        const vendedor = props.vendedores?.find(v => v.id === Number(vendedorId.value))
        if (vendedor) {
            vendedorBusqueda.value = vendedor.nombre_completo
        }
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-edit text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Editar Ventas</h1>
                        <p class="text-xs text-gray-500">Selecciona una sucursal para editar sus ventas</p>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Sucursal -->
                        <div class="sucursal-autocomplete flex-1 min-w-[180px] max-w-[280px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                                Sucursal <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="text" v-model="sucursalBusqueda"
                                    @focus="mostrarSucursales = true" @input="mostrarSucursales = true"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-6 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="Seleccione una sucursal..." autocomplete="off" />
                                <button v-if="sucursalId" @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" type="button">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                    class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-40 overflow-y-auto">
                                    <div v-for="suc in sucursalesDisponibles" :key="suc.id"
                                        @click="seleccionarSucursal(suc)"
                                        class="px-2.5 py-1.5 cursor-pointer hover:bg-primary-50 text-xs flex justify-between items-center border-b border-gray-100 last:border-0"
                                        :class="sucursalId === suc.id ? 'bg-primary-50' : ''">
                                        <span class="truncate">{{ suc.nombre }}</span>
                                        <span v-if="sucursalId === suc.id" class="text-primary-600"><i class="fas fa-check-circle text-[10px]"></i></span>
                                    </div>
                                </div>
                                <div v-else-if="mostrarSucursales && sucursalesDisponibles.length === 0 && sucursalBusqueda"
                                    class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-500 text-[10px]">
                                    <i class="fas fa-search mr-1"></i> No se encontraron sucursales
                                </div>
                            </div>
                            <span v-if="sucursalId" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-primary-50 text-primary-700 mt-0.5">
                                <i class="fas fa-check-circle text-[8px]"></i> {{ sucursalNombre }}
                            </span>
                            <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] text-gray-400 mt-0.5">
                                <i class="fas fa-store text-[8px]"></i> Ninguna seleccionada
                            </span>
                        </div>

                        <!-- Vendedor -->
                        <div class="vendedor-autocomplete flex-1 min-w-[140px] max-w-[200px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Vendedor</label>
                            <div class="relative">
                                <input type="text" v-model="vendedorBusqueda"
                                    @focus="mostrarVendedores = true" @input="mostrarVendedores = true"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-6 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="Todos..." autocomplete="off" />
                                <button v-if="vendedorBusqueda" @click="limpiarVendedor"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" type="button">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                <div v-if="mostrarVendedores && vendedoresDisponibles.length > 0" 
                                    class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-40 overflow-y-auto">
                                    <div v-for="ven in vendedoresDisponibles" :key="ven.id"
                                        @click="seleccionarVendedor(ven)"
                                        class="px-2.5 py-1.5 cursor-pointer hover:bg-primary-50 text-xs flex justify-between items-center border-b border-gray-100 last:border-0"
                                        :class="vendedorId === ven.id ? 'bg-primary-50' : ''">
                                        <span class="truncate">{{ ven.nombre_completo }}</span>
                                        <span v-if="vendedorId === ven.id" class="text-primary-600"><i class="fas fa-check-circle text-[10px]"></i></span>
                                    </div>
                                </div>
                                <div v-else-if="mostrarVendedores && vendedoresDisponibles.length === 0 && vendedorBusqueda"
                                    class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-500 text-[10px]">
                                    <i class="fas fa-search mr-1"></i> No se encontraron vendedores
                                </div>
                            </div>
                            <span v-if="vendedorId" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-primary-50 text-primary-700 mt-0.5">
                                <i class="fas fa-check-circle text-[8px]"></i> {{ vendedorNombre }}
                            </span>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Estado</label>
                            <select v-model="estado" @change="aplicarFiltros"
                                class="w-28 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Todos</option>
                                <option v-for="est in estados" :key="est.IdVentasEstado" :value="est.IdVentasEstado">
                                    {{ est.Detalle }}
                                </option>
                            </select>
                        </div>

                        <!-- Buscar -->
                        <div class="flex-1 min-w-[120px] max-w-[180px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar</label>
                            <div class="flex gap-1.5">
                                <input v-model="search" type="text" placeholder="N° Factura..."
                                    class="flex-1 border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    @keyup.enter="aplicarFiltros" />
                                <button @click="aplicarFiltros" :disabled="!haySucursalSeleccionada"
                                    class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1 rounded-md text-xs font-medium transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5 whitespace-nowrap">
                                    <i class="fas fa-search text-[10px]"></i>
                                    <span>Buscar</span>
                                </button>
                                <button @click="limpiarFiltros" 
                                    class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap">
                                    <i class="fas fa-eraser text-[10px]"></i>
                                    <span>Limpiar</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== MENSAJE: SIN SUCURSAL ==================== -->
                <div v-if="!haySucursalSeleccionada" class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <div class="w-16 h-16 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-store text-primary-400 text-3xl"></i>
                    </div>
                    <h3 class="text-base font-semibold text-gray-700">Seleccione una Sucursal</h3>
                    <p class="text-sm text-gray-400 mt-2 max-w-sm mx-auto">
                        Use el campo de búsqueda para seleccionar una sucursal y visualizar sus ventas.
                    </p>
                </div>

                <!-- ==================== TABLA ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="venta in ventas.data" :key="venta.IdVentas" class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-xs font-bold text-primary-700">{{ venta.NumeroFactura || 'S/N' }}</span>
                                            <span :class="['inline-flex px-1.5 py-0.5 text-[7px] font-medium rounded-full', getEstadoColor(venta.IdEstado)]">
                                                {{ getEstadoAbrev(venta.IdEstado) }}
                                            </span>
                                        </div>
                                        <div class="text-[9px] text-gray-500 mt-0.5">{{ formatDate(venta.FechaVenta) }}</div>
                                        <div class="text-[9px] text-gray-600 mt-1">
                                            <span class="text-gray-400">NIT:</span> {{ venta.cliente_nit }}
                                            <span class="text-gray-400 ml-2">Vend:</span> {{ venta.vendedor_nombre }}
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                        <span class="text-xs font-bold text-primary-700">{{ Number(venta.ImporteVenta).toFixed(2) }} Bs</span>
                                        <button @click.stop="editarVenta(venta.IdVentas)"
                                            class="inline-flex items-center gap-1 bg-primary-600 hover:bg-primary-700 text-white px-2 py-0.5 rounded-md text-[9px] font-medium transition">
                                            <i class="fas fa-edit text-[8px]"></i> Editar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!ventas.data || ventas.data.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay ventas que coincidan con los filtros</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Sucursal</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">N° Factura</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">NIT</th>
                                    <th class="px-3 py-1.5 text-right text-[9px] font-medium text-primary-700 uppercase w-20">Importe</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Vendedor</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase w-20">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="venta in ventas.data" :key="venta.IdVentas" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 text-xs text-gray-600">{{ venta.sucursal_nombre || '-' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">{{ formatDate(venta.FechaVenta) }}</td>
                                    <td class="px-3 py-2 text-xs font-semibold text-primary-700">{{ venta.NumeroFactura || '-' }}</td>
                                    <td class="px-3 py-2"><span :class="['inline-flex px-1.5 py-0.5 text-[8px] font-medium rounded-full', getEstadoColor(venta.IdEstado)]">{{ getEstadoAbrev(venta.IdEstado) }}</span></td>
                                    <td class="px-3 py-2 text-xs text-gray-600">{{ venta.cliente_nit }}</td>
                                    <td class="px-3 py-2 text-right text-xs font-bold text-primary-700">{{ Number(venta.ImporteVenta).toFixed(2) }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-600">{{ venta.vendedor_nombre }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <button @click.stop="editarVenta(venta.IdVentas)"
                                            class="inline-flex items-center gap-1 bg-primary-600 hover:bg-primary-700 text-white px-2.5 py-1 rounded-md text-[10px] font-medium transition">
                                            <i class="fas fa-edit text-[9px]"></i> Editar
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!ventas.data || ventas.data.length === 0">
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                        No hay ventas que coincidan con los filtros
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ==================== PAGINACIÓN ==================== -->
                    <div v-if="ventas?.data?.length && ventas.links && ventas.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <p class="text-[10px] text-gray-500">Mostrando {{ ventas.from || 0 }} - {{ ventas.to || 0 }} de {{ ventas.total || 0 }}</p>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link 
                                    v-for="link in ventas.links" 
                                    :key="link.label" 
                                    :href="construirUrlConFiltros(link.url)"
                                    class="px-2.5 py-0.5 rounded border text-[10px] transition"
                                    :class="[
                                        link.active ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="loading" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                    <div class="bg-white rounded-xl p-4 flex items-center gap-3 shadow-xl">
                        <i class="fas fa-spinner fa-spin text-lg text-primary-600"></i>
                        <span class="text-xs text-gray-700">Cargando...</span>
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

@media (max-width: 480px) {
    .sucursal-autocomplete .absolute,
    .vendedor-autocomplete .absolute {
        max-height: 150px;
        overflow-y: auto;
    }
}

/* 🔥 Z-INDEX PARA DROPDOWNS - POR ENCIMA DE TODO */
.sucursal-autocomplete .absolute,
.vendedor-autocomplete .absolute {
    z-index: 9999 !important;
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