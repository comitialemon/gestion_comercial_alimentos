<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
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

// =============================================
// ESTADO DE FILTROS
// =============================================

// Sucursal
const sucursalId = ref(props.filtros?.sucursal_id || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

// Vendedor
const vendedorId = ref(props.filtros?.vendedor_id || '')
const vendedorBusqueda = ref('')
const mostrarVendedores = ref(false)

// Estado
const estado = ref(props.filtros?.estado || '')

// Búsqueda
const search = ref(props.filtros?.search || '')

// Loading
const loading = ref(false)

// =============================================
// ESTADO PARA CONTRAER/EXPANDIR
// =============================================

const sucursalesExpandidas = ref({})

const inicializarExpandidas = () => {
    const grupos = ventasAgrupadas.value
    Object.keys(grupos).forEach(id => {
        sucursalesExpandidas.value[id] = true
    })
}

const toggleSucursal = (id) => {
    if (sucursalesExpandidas.value[id] !== undefined) {
        sucursalesExpandidas.value[id] = !sucursalesExpandidas.value[id]
    }
}

const expandirTodas = () => {
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        sucursalesExpandidas.value[id] = true
    })
}

const contraerTodas = () => {
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        sucursalesExpandidas.value[id] = false
    })
}

// =============================================
// AGRUPACIÓN POR SUCURSAL
// =============================================

const ventasAgrupadas = computed(() => {
    if (!props.ventas?.data) return {}
    
    const grupos = {}
    
    props.ventas.data.forEach(venta => {
        const sucursalNombre = venta.sucursal_nombre || 'Sin sucursal'
        const sucursalId = venta.IdClienteSucursal || 0
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                ventas: [],
                total: 0
            }
        }
        
        grupos[sucursalId].ventas.push(venta)
        grupos[sucursalId].total += venta.ImporteVenta || 0
    })
    
    return grupos
})

const sucursalesConVentas = computed(() => {
    return Object.values(ventasAgrupadas.value)
})

const actualizarExpandidas = () => {
    const grupos = ventasAgrupadas.value
    const idsActuales = Object.keys(grupos)
    
    idsActuales.forEach(id => {
        if (sucursalesExpandidas.value[id] === undefined) {
            sucursalesExpandidas.value[id] = true
        }
    })
    
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        if (!idsActuales.includes(id)) {
            delete sucursalesExpandidas.value[id]
        }
    })
}

// =============================================
// AUTOCOMPLETE - Sucursal
// =============================================

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
    const suc = props.sucursales?.find(s => s.id === sucursalId.value)
    return suc?.nombre || ''
})

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
    aplicarFiltros()
}

// =============================================
// AUTOCOMPLETE - Vendedor
// =============================================

const vendedoresDisponibles = computed(() => {
    if (!props.vendedores || props.vendedores.length === 0) {
        return []
    }
    
    if (!vendedorBusqueda.value || vendedorBusqueda.value === '') {
        return props.vendedores
    }
    
    const termino = vendedorBusqueda.value.toLowerCase()
    return props.vendedores.filter(v => {
        const nombre = v.nombre_completo?.toLowerCase() || ''
        return nombre.includes(termino)
    })
})

const vendedorNombre = computed(() => {
    if (!vendedorId.value) return ''
    const ven = props.vendedores?.find(v => v.id === vendedorId.value)
    return ven?.nombre_completo || ''
})

const seleccionarVendedor = (vendedor) => {
    vendedorId.value = vendedor.id
    vendedorBusqueda.value = vendedor.nombre_completo
    mostrarVendedores.value = false
    aplicarFiltros()
}

const limpiarVendedor = () => {
    vendedorId.value = ''
    vendedorBusqueda.value = ''
    mostrarVendedores.value = false
    aplicarFiltros()
}

// =============================================
// ACCIONES
// =============================================

const aplicarFiltros = () => {
    const params = {
        sucursal_id: sucursalId.value,
        vendedor_id: vendedorId.value,
        estado: estado.value,
        search: search.value,
    }
    
    router.get('/gestion/ventas-editar', params, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            setTimeout(() => {
                actualizarExpandidas()
            }, 100)
        }
    })
}

const limpiarFiltros = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    vendedorId.value = ''
    vendedorBusqueda.value = ''
    estado.value = ''
    search.value = ''
    aplicarFiltros()
}

// 🔥 BOTÓN EDITAR - Abre el formulario de edición
const editarVenta = (id) => {
    console.log('🔍 Editando venta con ID:', id);  // 🔥 AGREGAR ESTO
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
        1: 'bg-green-100 text-green-800',
        2: 'bg-red-100 text-red-800',
        3: 'bg-yellow-100 text-yellow-800',
        4: 'bg-gray-100 text-gray-800',
        5: 'bg-orange-100 text-orange-800',
        6: 'bg-blue-100 text-blue-800',
    }
    return colors[estadoId] || 'bg-gray-100 text-gray-800'
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

// =============================================
// CICLO DE VIDA
// =============================================

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    
    if (vendedorId.value) {
        const vendedor = props.vendedores?.find(v => v.id === vendedorId.value)
        if (vendedor) {
            vendedorBusqueda.value = vendedor.nombre_completo
        }
    }
    
    if (sucursalId.value) {
        const sucursal = props.sucursales?.find(s => s.id === sucursalId.value)
        if (sucursal) {
            sucursalBusqueda.value = sucursal.nombre
        }
    }
    
    setTimeout(() => {
        actualizarExpandidas()
    }, 100)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

watch(() => props.ventas?.data, () => {
    actualizarExpandidas()
}, { deep: true })
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 py-4 sm:py-6">
            
            <!-- TÍTULO -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 sm:mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-primary-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-edit text-primary-600 text-sm sm:text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-xl font-bold text-gray-800">Editar Ventas</h1>
                        <p class="text-[10px] sm:text-xs text-gray-500 hidden xs:block">Listado agrupado por sucursal</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-500 bg-white px-3 py-1.5 rounded-lg shadow-sm">
                    <i class="fas fa-store text-primary-500"></i>
                    <span class="truncate max-w-[150px] sm:max-w-xs">{{ sucursales.find(s => s.id === sucursalId)?.nombre || 'Todas las sucursales' }}</span>
                </div>
            </div>

            <!-- ESTADÍSTICAS -->
            <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-4">
                <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3">
                    <p class="text-[10px] sm:text-xs text-gray-500">Total</p>
                    <p class="text-base sm:text-xl font-bold text-primary-700">{{ estadisticas.total }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3">
                    <p class="text-[10px] sm:text-xs text-gray-500">Válidas</p>
                    <p class="text-base sm:text-xl font-bold text-green-600">{{ estadisticas.validas }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3">
                    <p class="text-[10px] sm:text-xs text-gray-500">Anuladas</p>
                    <p class="text-base sm:text-xl font-bold text-red-600">{{ estadisticas.anuladas }}</p>
                </div>
            </div>

            <!-- FILTROS -->
            <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
                    
                    <!-- Sucursal -->
                    <div class="sucursal-autocomplete">
                        <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-store text-primary-500 mr-1"></i>
                            Sucursal
                        </label>
                        <div class="relative">
                            <input 
                                type="text"
                                v-model="sucursalBusqueda"
                                @focus="mostrarSucursales = true"
                                @input="mostrarSucursales = true"
                                class="w-full border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm pr-6 sm:pr-7 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                placeholder="Todas..."
                                autocomplete="off"
                            />
                            <button 
                                v-if="sucursalBusqueda"
                                @click="limpiarSucursal"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                type="button"
                            >
                                <i class="fas fa-times text-[10px] sm:text-xs"></i>
                            </button>
                            
                            <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 sm:max-h-48 overflow-y-auto">
                                <div 
                                    v-for="suc in sucursalesDisponibles" 
                                    :key="suc.id"
                                    @click="seleccionarSucursal(suc)"
                                    class="px-2 sm:px-3 py-1.5 cursor-pointer hover:bg-primary-50 text-xs sm:text-sm flex justify-between items-center border-b border-gray-100 last:border-0"
                                    :class="sucursalId === suc.id ? 'bg-primary-50' : ''"
                                >
                                    <span class="truncate">{{ suc.nombre }}</span>
                                    <span v-if="sucursalId === suc.id" class="text-primary-600">
                                        <i class="fas fa-check-circle text-[10px] sm:text-xs"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-if="sucursalId" class="mt-1">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-xs bg-primary-50 text-primary-700 truncate max-w-full">
                                <i class="fas fa-check-circle text-[8px] sm:text-[10px]"></i>
                                {{ sucursalNombre }}
                            </span>
                        </div>
                    </div>

                    <!-- Vendedor -->
                    <div class="vendedor-autocomplete">
                        <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-user text-primary-500 mr-1"></i>
                            Vendedor
                        </label>
                        <div class="relative">
                            <input 
                                type="text"
                                v-model="vendedorBusqueda"
                                @focus="mostrarVendedores = true"
                                @input="mostrarVendedores = true"
                                class="w-full border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm pr-6 sm:pr-7 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                placeholder="Todos..."
                                autocomplete="off"
                            />
                            <button 
                                v-if="vendedorBusqueda"
                                @click="limpiarVendedor"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                type="button"
                            >
                                <i class="fas fa-times text-[10px] sm:text-xs"></i>
                            </button>
                            
                            <div v-if="mostrarVendedores && vendedoresDisponibles.length > 0" 
                                class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 sm:max-h-48 overflow-y-auto">
                                <div 
                                    v-for="ven in vendedoresDisponibles" 
                                    :key="ven.id"
                                    @click="seleccionarVendedor(ven)"
                                    class="px-2 sm:px-3 py-1.5 cursor-pointer hover:bg-primary-50 text-xs sm:text-sm flex justify-between items-center border-b border-gray-100 last:border-0"
                                    :class="vendedorId === ven.id ? 'bg-primary-50' : ''"
                                >
                                    <span class="truncate">{{ ven.nombre_completo }}</span>
                                    <span v-if="vendedorId === ven.id" class="text-primary-600">
                                        <i class="fas fa-check-circle text-[10px] sm:text-xs"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div v-else-if="mostrarVendedores && props.vendedores && props.vendedores.length === 0" 
                                class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-2 text-center text-gray-500 text-[10px] sm:text-xs">
                                <i class="fas fa-info-circle mr-1"></i>
                                No hay vendedores registrados
                            </div>
                            
                            <div v-else-if="mostrarVendedores && vendedorBusqueda && vendedoresDisponibles.length === 0 && props.vendedores && props.vendedores.length > 0" 
                                class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-2 text-center text-gray-500 text-[10px] sm:text-xs">
                                <i class="fas fa-search mr-1"></i>
                                No se encontraron vendedores con "{{ vendedorBusqueda }}"
                            </div>
                        </div>
                        <div v-if="vendedorId" class="mt-1">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-xs bg-primary-50 text-primary-700 truncate max-w-full">
                                <i class="fas fa-check-circle text-[8px] sm:text-[10px]"></i>
                                {{ vendedorNombre }}
                            </span>
                        </div>
                    </div>

                    <!-- Estado -->
                    <div>
                        <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-tag text-primary-500 mr-1"></i>
                            Estado
                        </label>
                        <select 
                            v-model="estado"
                            @change="aplicarFiltros"
                            class="w-full border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                        >
                            <option value="">Todos</option>
                            <option v-for="est in estados" :key="est.IdVentasEstado" :value="est.IdVentasEstado">
                                {{ est.Detalle }}
                            </option>
                        </select>
                    </div>

                    <!-- Búsqueda -->
                    <div>
                        <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-search text-primary-500 mr-1"></i>
                            Buscar
                        </label>
                        <div class="flex gap-1.5">
                            <input 
                                v-model="search"
                                type="text"
                                placeholder="N° Factura..."
                                class="flex-1 border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none min-w-0"
                                @keyup.enter="aplicarFiltros"
                            />
                            <button 
                                @click="aplicarFiltros"
                                class="bg-primary-600 hover:bg-primary-700 text-white px-2 sm:px-3 py-1.5 rounded-lg text-xs sm:text-sm transition flex-shrink-0"
                                title="Filtrar"
                            >
                                <i class="fas fa-filter"></i>
                            </button>
                            <button 
                                @click="limpiarFiltros"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 sm:px-3 py-1.5 rounded-lg text-xs sm:text-sm transition flex-shrink-0"
                                title="Limpiar filtros"
                            >
                                <i class="fas fa-undo"></i>
                            </button>
                        </div>
                    </div>

                </div>
                
                <!-- Botones Expandir/Contraer -->
                <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-200">
                    <button 
                        @click="expandirTodas"
                        class="text-[10px] sm:text-xs bg-primary-100 hover:bg-primary-200 text-primary-700 px-2 sm:px-3 py-1 rounded-lg transition flex items-center gap-1"
                    >
                        <i class="fas fa-plus-circle text-[10px] sm:text-xs"></i>
                        <span class="hidden xs:inline">Expandir todas</span>
                        <span class="xs:hidden">Expandir</span>
                    </button>
                    <button 
                        @click="contraerTodas"
                        class="text-[10px] sm:text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 sm:px-3 py-1 rounded-lg transition flex items-center gap-1"
                    >
                        <i class="fas fa-minus-circle text-[10px] sm:text-xs"></i>
                        <span class="hidden xs:inline">Contraer todas</span>
                        <span class="xs:hidden">Contraer</span>
                    </button>
                    <span class="text-[10px] sm:text-xs text-gray-400 ml-auto self-center hidden sm:inline">
                        {{ sucursalesConVentas.length }} sucursales
                    </span>
                </div>
            </div>

            <!-- ============================================= -->
            <!-- GRID AGRUPADA POR SUCURSAL -->
            <!-- ============================================= -->
            <div v-for="grupo in sucursalesConVentas" :key="grupo.id" class="mb-3 sm:mb-4">
                
                <!-- Encabezado de Sucursal -->
                <div 
                    @click="toggleSucursal(grupo.id)"
                    class="flex flex-wrap items-center justify-between gap-2 bg-primary-50 rounded-t-lg px-3 sm:px-4 py-2 sm:py-2.5 border border-primary-200 cursor-pointer hover:bg-primary-100 transition"
                >
                    <div class="flex items-center gap-1.5 sm:gap-2 min-w-0 flex-1">
                        <i 
                            class="text-primary-600 text-[10px] sm:text-sm transition-transform duration-200 flex-shrink-0"
                            :class="sucursalesExpandidas[grupo.id] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                        ></i>
                        
                        <i class="fas fa-store text-primary-600 text-xs sm:text-sm flex-shrink-0"></i>
                        <h2 class="font-bold text-primary-800 text-xs sm:text-sm truncate">{{ grupo.nombre }}</h2>
                        <span class="text-[9px] sm:text-xs text-primary-600 bg-primary-100 px-1.5 sm:px-2 py-0.5 rounded-full flex-shrink-0">
                            {{ grupo.ventas.length }}
                        </span>
                    </div>
                    <div class="text-xs sm:text-sm font-bold text-primary-700 flex-shrink-0">
                        {{ Number(grupo.total).toFixed(2) }} Bs
                    </div>
                </div>

                <!-- Tabla de ventas -->
                <transition 
                    enter-active-class="transition-all duration-300 ease-in-out"
                    enter-from-class="max-h-0 opacity-0 overflow-hidden"
                    enter-to-class="max-h-[5000px] opacity-100 overflow-hidden"
                    leave-active-class="transition-all duration-300 ease-in-out"
                    leave-from-class="max-h-[5000px] opacity-100 overflow-hidden"
                    leave-to-class="max-h-0 opacity-0 overflow-hidden"
                >
                    <div v-if="sucursalesExpandidas[grupo.id]" class="bg-white rounded-b-lg shadow-sm overflow-hidden border border-t-0 border-primary-200">
                        
                        <!-- DESKTOP: Tabla -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Factura</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">NIT</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Importe</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="venta in grupo.ventas" :key="venta.IdVentas" class="hover:bg-gray-50 transition">
                                        <td class="px-3 py-2.5 text-sm text-gray-700 whitespace-nowrap">
                                            {{ formatDate(venta.FechaVenta) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-sm font-medium text-primary-700">
                                            {{ venta.NumeroFactura || '-' }}
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span :class="['inline-flex px-2 py-0.5 text-xs font-medium rounded-full', getEstadoColor(venta.IdEstado)]">
                                                {{ getEstadoAbrev(venta.IdEstado) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-sm text-gray-700">
                                            {{ venta.cliente_nit }}
                                        </td>
                                        <td class="px-3 py-2.5 text-sm font-bold text-primary-700">
                                            {{ Number(venta.ImporteVenta).toFixed(2) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-sm text-gray-700">
                                            {{ venta.vendedor_nombre }}
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <!-- 🔥 SOLO BOTÓN EDITAR -->
                                            <button 
                                                @click.stop="editarVenta(venta.IdVentas)"
                                                class="inline-flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition"
                                                title="Editar venta"
                                            >
                                                <i class="fas fa-edit text-[11px]"></i>
                                                Editar
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- MÓVIL: Tarjetas -->
                        <div class="md:hidden divide-y divide-gray-100">
                            <div v-for="venta in grupo.ventas" :key="venta.IdVentas" class="p-3 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-primary-700 text-sm">{{ venta.NumeroFactura || 'S/N' }}</span>
                                            <span :class="['inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full', getEstadoColor(venta.IdEstado)]">
                                                {{ getEstadoAbrev(venta.IdEstado) }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            {{ formatDate(venta.FechaVenta) }}
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
                                            <span><span class="text-gray-400">NIT:</span> {{ venta.cliente_nit }}</span>
                                            <span><span class="text-gray-400">Vendedor:</span> {{ venta.vendedor_nombre }}</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                        <span class="text-sm font-bold text-primary-700">{{ Number(venta.ImporteVenta).toFixed(2) }} Bs</span>
                                        <!-- 🔥 SOLO BOTÓN EDITAR -->
                                        <button 
                                            @click.stop="editarVenta(venta.IdVentas)"
                                            class="inline-flex items-center gap-1 bg-primary-600 hover:bg-primary-700 text-white px-2.5 py-1 rounded-lg text-[10px] font-medium transition"
                                            title="Editar venta"
                                        >
                                            <i class="fas fa-edit text-[10px]"></i>
                                            Editar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </transition>
            </div>

            <!-- Mensaje sin datos -->
            <div v-if="!sucursalesConVentas.length || !props.ventas?.data?.length" class="bg-white rounded-lg shadow-sm p-6 sm:p-8 text-center text-gray-500">
                <i class="fas fa-inbox text-3xl sm:text-4xl block mb-2 text-gray-300"></i>
                <p class="text-sm sm:text-base">No hay ventas que coincidan con los filtros</p>
            </div>

            <!-- PAGINACIÓN -->
            <div v-if="props.ventas?.data?.length" class="bg-white rounded-lg shadow-sm mt-4 px-3 sm:px-4 py-2 border border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-2">
                <p class="text-[10px] sm:text-xs text-gray-500">
                    Mostrando {{ props.ventas.from || 0 }} - {{ props.ventas.to || 0 }} de {{ props.ventas.total || 0 }}
                </p>
                <div class="flex gap-1 flex-wrap justify-center">
                    <button 
                        v-for="link in props.ventas.links" 
                        :key="link.label"
                        v-html="link.label"
                        @click="link.url && router.get(link.url)"
                        :class="[
                            'px-2 sm:px-2.5 py-1 rounded text-[10px] sm:text-xs transition',
                            link.active ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200',
                            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                        ]"
                    ></button>
                </div>
            </div>

            <!-- LOADING -->
            <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl p-4 sm:p-5 flex items-center gap-3 shadow-xl">
                    <i class="fas fa-spinner fa-spin text-lg sm:text-xl text-primary-600"></i>
                    <span class="text-xs sm:text-sm text-gray-700">Cargando...</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 480px) {
    .xs\:hidden {
        display: none !important;
    }
    .xs\:block {
        display: block !important;
    }
}

@media (max-width: 640px) {
    .max-h-40 {
        max-height: 10rem;
    }
}

@media (max-width: 768px) {
    .vendedor-autocomplete .absolute,
    .sucursal-autocomplete .absolute {
        max-height: 200px;
        overflow-y: auto;
    }
}
</style>