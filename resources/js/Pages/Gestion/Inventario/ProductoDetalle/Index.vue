<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, onMounted, watch, computed } from 'vue'
import ModalProducto from './ModalProducto.vue'
import ModalFichaTecnica from './ModalFichaTecnica.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Object,
    totalActivos: Number,
    totalInactivos: Number,
    grupos: Array,
    lineas: Array,
    estados: Array,
    unidades: Array,
    unidadId: Number,
    filtros: Object,
})

// ==================== ESTADO ====================
const search = ref(props.filtros?.search || '')
const estadoActivo = ref(props.filtros?.estado || '')
const linea = ref(props.filtros?.linea || '')
const estadoProducto = ref(props.filtros?.estadoProducto || '')
const isMobile = ref(false)
const filtrosAbiertos = ref(false)
const escribiendo = ref(false)

// ==================== MODALES ====================
const modalOpen = ref(false)
const editando = ref(false)
const productoSeleccionado = ref(null)

// 🔥 MODAL FICHA TÉCNICA
const modalFichaOpen = ref(false)
const productoParaFicha = ref(null)

// ==================== COMPUTED ====================
const filtrosActivos = computed(() => {
    let count = 0
    if (search.value && search.value.trim() !== '') count++
    if (estadoActivo.value && estadoActivo.value !== '') count++
    if (linea.value && linea.value !== '') count++
    if (estadoProducto.value && estadoProducto.value !== '') count++
    return count
})

// ==================== FUNCIONES ====================
const aplicarFiltros = (cerrarFiltros = true) => {
    const params = {}
    if (search.value && search.value.trim() !== '') params.search = search.value
    if (estadoActivo.value !== '' && estadoActivo.value !== null) params.estado = estadoActivo.value
    if (linea.value && linea.value !== '') params.linea = linea.value
    if (estadoProducto.value && estadoProducto.value !== '') params.estadoProducto = estadoProducto.value

    router.get('/gestion/inventario/productos-detalle', params, {
        preserveState: true,
        replace: true,
    })
    
    if (isMobile.value && cerrarFiltros && !escribiendo.value) {
        filtrosAbiertos.value = false
    }
}

let timeoutBuscador
const buscarConDebounce = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros(false)
    }, 500)
}

const limpiarFiltros = () => {
    search.value = ''
    estadoActivo.value = ''
    linea.value = ''
    estadoProducto.value = ''
    filtrosAbiertos.value = false
    escribiendo.value = false
    
    router.get('/gestion/inventario/productos-detalle', {}, {
        preserveState: true,
        replace: true,
    })
}

// ==================== MODALES ====================
const abrirModalNuevo = () => {
    productoSeleccionado.value = null
    editando.value = false
    modalOpen.value = true
}

const abrirModalEditar = async (producto) => {
    console.log('📦 Producto COMPLETO:', producto)
    console.log('📊 IdGrupoAnalisis:', producto.IdGrupoAnalisis)
    console.log('📊 IdLineaProducto:', producto.IdLineaProducto)
    console.log('📊 grupoAnalisis:', producto.grupoAnalisis)
    console.log('📊 linea:', producto.linea)
    
    // 🔥 AHORA LOS DATOS VIENEN COMPLETOS DESDE EL CONTROLADOR
    productoSeleccionado.value = {
        IdProducto: producto.IdProducto,
        Codigo: producto.Codigo,
        Descripcion: producto.Descripcion,
        ActivoInactivo: producto.ActivoInactivo,
        IdGrupoAnalisis: producto.IdGrupoAnalisis || null,
        IdLineaProducto: producto.IdLineaProducto || null,
        IdEstadoProducto: producto.IdEstadoProducto || null,
        IdUnidadMedida: producto.IdUnidadMedida || null,
        OrdenInformes: producto.OrdenInformes || 0,
        estado: producto.estado || { Estado: '' },
        grupoAnalisis: producto.grupoAnalisis || { Grupo: '' },
        linea: producto.linea || { Linea: '' },
        unidadMedida: producto.unidadMedida || { UnidadMedida: '' },
    }
    editando.value = true
    modalOpen.value = true
}

// 🔥 ABRIR MODAL FICHA TÉCNICA
const abrirModalFicha = (producto) => {
    productoParaFicha.value = producto
    modalFichaOpen.value = true
}

const recargarDatos = () => {
    aplicarFiltros(false)
}

// ==================== ESTADOS ====================
const estadoTexto = (activo) => {
    return activo === 0 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo === 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
}

const toggleFiltros = () => {
    filtrosAbiertos.value = !filtrosAbiertos.value
}

// ==================== RESPONSIVE ====================
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
    if (!isMobile.value) {
        filtrosAbiertos.value = true
    }
}

// ==================== WATCHES ====================
watch(search, () => {
    escribiendo.value = true
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros(false)
        setTimeout(() => { escribiendo.value = false }, 300)
    }, 500)
})

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    if (!isMobile.value) filtrosAbiertos.value = true
})
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-3 sm:mb-4">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                             :style="{ backgroundColor: `var(--color-primary-100)` }">
                            <i class="fas fa-boxes text-primary-600 text-[11px] sm:text-sm"
                               :style="{ color: `var(--color-primary-600)` }"></i>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-sm sm:text-base font-bold text-gray-800 truncate">Productos de Inventario</h1>
                            <p class="text-[9px] sm:text-[10px] text-gray-500 truncate">Gestión de productos para inventario</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button 
                            @click="toggleFiltros"
                            class="lg:hidden flex-1 sm:flex-none px-3 py-1.5 bg-white border rounded-lg text-xs flex items-center justify-center gap-1.5 transition"
                            :style="{ borderColor: `var(--color-primary-300)` }"
                        >
                            <i class="fas fa-sliders-h text-[10px]" :style="{ color: `var(--color-primary-600)` }"></i>
                            <span class="text-gray-700">{{ filtrosAbiertos ? 'Ocultar' : 'Filtros' }}</span>
                            <span v-if="filtrosActivos > 0" 
                                  class="inline-flex items-center justify-center w-4 h-4 text-[8px] font-bold text-white rounded-full"
                                  :style="{ backgroundColor: `var(--color-primary-600)` }">
                                {{ filtrosActivos }}
                            </span>
                        </button>
                        <button 
                            @click="abrirModalNuevo"
                            class="flex-1 sm:flex-none bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1 transition">
                            <i class="fas fa-plus text-[10px]"></i> 
                            <span class="hidden sm:inline">Nuevo Producto</span>
                            <span class="sm:hidden">Nuevo</span>
                        </button>
                    </div>
                </div>

                <!-- Layout Principal -->
                <div class="flex flex-col lg:flex-row gap-3 sm:gap-4">
                    
                    <!-- FILTROS -->
                    <div 
                        class="lg:w-64 flex-shrink-0 transition-all duration-300 overflow-hidden"
                        :class="{
                            'max-h-[600px] opacity-100': filtrosAbiertos || !isMobile,
                            'max-h-0 opacity-0 lg:max-h-full lg:opacity-100': !filtrosAbiertos && isMobile
                        }"
                    >
                        <div class="bg-white rounded-lg shadow-sm p-3 sticky top-2 lg:top-24">
                            <h3 class="text-xs font-semibold text-gray-800 mb-3 flex items-center gap-1">
                                <i class="fas fa-filter text-[10px]" :style="{ color: `var(--color-primary-600)` }"></i> 
                                Filtros
                                <span v-if="filtrosActivos > 0" 
                                      class="text-[9px] bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded-full ml-auto">
                                    {{ filtrosActivos }} activos
                                </span>
                            </h3>

                            <!-- Buscar -->
                            <div class="mb-3">
                                <label class="block text-[10px] font-medium text-gray-700 mb-1">Buscar</label>
                                <div class="relative">
                                    <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[9px]"></i>
                                    <input 
                                        type="text" 
                                        v-model="search" 
                                        placeholder="Código o nombre..." 
                                        class="w-full border rounded-md pl-7 pr-2 py-1.5 text-[11px] focus:ring-2 focus:outline-none"
                                        :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                                        @keyup.enter="aplicarFiltros(true)"
                                    >
                                </div>
                            </div>

                            <!-- Estado (Activo/Inactivo) -->
                            <div class="mb-3">
                                <label class="block text-[10px] font-medium text-gray-700 mb-1">Estado</label>
                                <div class="grid grid-cols-2 gap-0.5">
                                    <label class="flex items-center gap-2 cursor-pointer py-0.5">
                                        <input type="radio" value="" v-model="estadoActivo" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }" @change="aplicarFiltros(true)"> 
                                        <span class="text-[11px] text-gray-700">Todos</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer py-0.5">
                                        <input type="radio" value="0" v-model="estadoActivo" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }" @change="aplicarFiltros(true)"> 
                                        <span class="text-[11px] text-gray-700">Activos ({{ totalActivos }})</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer py-0.5">
                                        <input type="radio" value="1" v-model="estadoActivo" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }" @change="aplicarFiltros(true)"> 
                                        <span class="text-[11px] text-gray-700">Inactivos ({{ totalInactivos }})</span>
                                    </label>
                                </div>
                            </div>

                            <!-- 🔥 NUEVO: Estado del Producto (Terminado, Insumos, etc.) -->
                            <div class="mb-3">
                                <label class="block text-[10px] font-medium text-gray-700 mb-1">Tipo de Producto</label>
                                <select v-model="estadoProducto" @change="aplicarFiltros(true)"
                                        class="w-full border rounded-md px-2 py-1.5 text-[11px] focus:ring-2 focus:outline-none"
                                        :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }">
                                    <option value="">Todos</option>
                                    <option v-for="item in estados" :key="item.id" :value="item.id">
                                        {{ item.nombre }}
                                    </option>
                                </select>
                                <p class="text-[8px] text-gray-400 mt-0.5">Filtra por Tipo de Producto (Terminado, Insumos, etc.)</p>
                            </div>

                            <!-- Línea -->
                            <div class="mb-3">
                                <label class="block text-[10px] font-medium text-gray-700 mb-1">Línea</label>
                                <select v-model="linea" @change="aplicarFiltros(true)"
                                        class="w-full border rounded-md px-2 py-1.5 text-[11px] focus:ring-2 focus:outline-none"
                                        :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }">
                                    <option value="">Todas</option>
                                    <option v-for="item in lineas" :key="item.id" :value="item.id">
                                        {{ item.nombre }}
                                    </option>
                                </select>
                            </div>

                            <!-- Botonera -->
                            <div class="flex gap-2 pt-2 border-t" :style="{ borderColor: `var(--color-primary-200)` }">
                                <button @click="aplicarFiltros(true)" 
                                        class="flex-1 px-2 py-1.5 text-white rounded-md text-[10px] transition flex items-center justify-center gap-1"
                                        :style="{ backgroundColor: `var(--color-primary-600)` }">
                                    <i class="fas fa-search text-[8px]"></i> Filtrar
                                </button>
                                <button @click="limpiarFiltros" 
                                        class="px-2 py-1.5 border border-gray-300 rounded-md text-[10px] text-gray-700 hover:bg-gray-50 transition" 
                                        title="Limpiar Filtros">
                                    <i class="fas fa-eraser text-[8px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TABLA DE PRODUCTOS -->
                    <div class="flex-1 min-w-0">
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                            
                            <!-- Indicador de filtros activos -->
                            <div class="p-2 border-b flex flex-wrap gap-1 lg:hidden"
                                :style="{ borderColor: `var(--color-primary-200)` }">
                                <span v-if="search" class="px-1.5 py-0.5 bg-primary-50 rounded text-[9px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-search text-[8px]"></i> {{ search }}
                                </span>
                                <span v-if="estadoActivo !== ''" class="px-1.5 py-0.5 bg-primary-50 rounded text-[9px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-circle text-[6px]" :class="estadoActivo == '0' ? 'text-green-500' : 'text-red-500'"></i>
                                    {{ estadoActivo == '0' ? 'Activos' : 'Inactivos' }}
                                </span>
                                <span v-if="estadoProducto" class="px-1.5 py-0.5 bg-primary-50 rounded text-[9px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-tag text-[8px]"></i> 
                                    {{ estados.find(e => e.id === estadoProducto)?.nombre || 'Tipo' }}
                                </span>
                                <span v-if="linea" class="px-1.5 py-0.5 bg-primary-50 rounded text-[9px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-tag text-[8px]"></i> Línea seleccionada
                                </span>
                            </div>

                            <!-- 🔥 TABLA DESKTOP -->
                            <div class="hidden lg:block overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-primary-50" :style="{ backgroundColor: `var(--color-primary-50)` }">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Estado</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Línea</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Unidad</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Código</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Descripción</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Activo</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="producto in productos.data" :key="producto.IdProducto" class="hover:bg-gray-50 transition">
                                            <td class="px-3 py-2 text-[11px] text-gray-500">
                                                {{ producto.estado?.Estado || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-gray-500">
                                                {{ producto.linea?.Linea || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-gray-500">
                                                {{ producto.unidadMedida?.UnidadMedida || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-gray-600 font-mono">
                                                {{ producto.Codigo }}
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-gray-800">
                                                {{ producto.Descripcion }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                    {{ estadoTexto(producto.ActivoInactivo) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <!-- Botón Editar -->
                                                    <button @click="abrirModalEditar(producto)" 
                                                            class="transition p-1 rounded hover:bg-primary-50" 
                                                            :style="{ color: `var(--color-primary-600)` }"
                                                            title="Editar">
                                                        <i class="fas fa-edit text-[11px]"></i>
                                                    </button>
                                                    
                                                    <!-- 🔥 Botón Ficha Técnica -->
                                                    <button 
                                                        v-if="producto.estado?.Estado === 'Terminado'"
                                                        @click="abrirModalFicha(producto)" 
                                                        class="transition p-1 rounded hover:bg-amber-50" 
                                                        style="color: #D97706"
                                                        title="Ficha Técnica">
                                                        <i class="fas fa-clipboard-list text-[11px]"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!productos.data || productos.data.length === 0">
                                            <td colspan="8" class="px-3 py-8 text-center text-gray-400 text-[11px]">
                                                <i class="fas fa-box-open text-xl mb-1 block text-gray-300"></i>
                                                No se encontraron productos
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 🔥 TABLA TABLET -->
                            <div class="hidden sm:block lg:hidden overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-primary-50" :style="{ backgroundColor: `var(--color-primary-50)` }">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Código</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Descripción</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Estado</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Activo</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="producto in productos.data" :key="producto.IdProducto" class="hover:bg-gray-50 transition">
                                            <td class="px-3 py-2 text-[11px] text-gray-600 font-mono">{{ producto.Codigo }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-800">{{ producto.Descripcion }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-500">{{ producto.estado?.Estado || '-' }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                    {{ estadoTexto(producto.ActivoInactivo) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button @click="abrirModalEditar(producto)" 
                                                            class="transition p-1 rounded hover:bg-primary-50" 
                                                            :style="{ color: `var(--color-primary-600)` }"
                                                            title="Editar">
                                                        <i class="fas fa-edit text-[11px]"></i>
                                                    </button>
                                                    <button 
                                                        v-if="producto.estado?.Estado === 'Terminado'"
                                                        @click="abrirModalFicha(producto)" 
                                                        class="transition p-1 rounded hover:bg-amber-50" 
                                                        style="color: #D97706"
                                                        title="Ficha Técnica">
                                                        <i class="fas fa-clipboard-list text-[11px]"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!productos.data || productos.data.length === 0">
                                            <td colspan="5" class="px-3 py-8 text-center text-gray-400 text-[11px]">
                                                <i class="fas fa-box-open text-xl mb-1 block text-gray-300"></i>
                                                No se encontraron productos
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 🔥 TARJETAS MÓVIL -->
                            <div class="sm:hidden divide-y divide-gray-100">
                                <div v-for="producto in productos.data" :key="producto.IdProducto" 
                                     class="p-3 hover:bg-gray-50 transition">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="text-xs font-medium text-gray-800 truncate">{{ producto.Descripcion }}</div>
                                            <div class="text-[10px] text-gray-400 font-mono mb-1">
                                                <i class="fas fa-hashtag mr-0.5 text-[8px]"></i>
                                                {{ producto.Codigo }}
                                            </div>
                                            <div class="flex flex-wrap items-center gap-1.5 text-[10px] text-gray-500">
                                                <span>{{ producto.estado?.Estado || '-' }}</span>
                                                <span>|</span>
                                                <span>{{ producto.linea?.Linea || '-' }}</span>
                                                <span>|</span>
                                                <span>{{ producto.unidadMedida?.UnidadMedida || '-' }}</span>
                                            </div>
                                            <div class="mt-1 flex items-center gap-1.5">
                                                <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                    {{ estadoTexto(producto.ActivoInactivo) }}
                                                </span>
                                                <span v-if="producto.estado?.Estado === 'Terminado'" 
                                                      class="px-1.5 py-0.5 text-[8px] rounded-full bg-amber-100 text-amber-700">
                                                    <i class="fas fa-clipboard-list mr-0.5"></i>
                                                    Ficha
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 flex-shrink-0">
                                            <button @click="abrirModalEditar(producto)" 
                                                    class="p-1.5 rounded-lg transition flex-shrink-0"
                                                    :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-600)` }"
                                                    title="Editar">
                                                <i class="fas fa-edit text-sm"></i>
                                            </button>
                                            <button 
                                                v-if="producto.estado?.Estado === 'Terminado'"
                                                @click="abrirModalFicha(producto)" 
                                                class="p-1.5 rounded-lg transition flex-shrink-0"
                                                style="background-color: #FEF3C7; color: #D97706"
                                                title="Ficha Técnica">
                                                <i class="fas fa-clipboard-list text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="!productos.data || productos.data.length === 0" class="p-8 text-center text-gray-400 text-sm">
                                    <i class="fas fa-box-open text-2xl mb-2 block text-gray-300"></i>
                                    No se encontraron productos
                                </div>
                            </div>

                            <!-- Paginación -->
                            <div v-if="productos.links && productos.links.length > 1" class="px-2 sm:px-3 py-2 border-t border-gray-200 bg-gray-50">
                                <div class="flex flex-col xs:flex-row justify-between items-center gap-2 text-[9px] sm:text-[10px]">
                                    <div class="text-gray-500 text-[8px] sm:text-[10px]">
                                        Mostrando {{ productos.from || 0 }} - {{ productos.to || 0 }} de {{ productos.total || 0 }}
                                    </div>
                                    <div class="flex gap-0.5 flex-wrap justify-center">
                                        <Link 
                                            v-for="link in productos.links" 
                                            :key="link.label" 
                                            :href="link.url || '#'" 
                                            class="px-1.5 sm:px-2 py-0.5 rounded border text-[8px] sm:text-[10px] transition min-w-[22px] text-center"
                                            :style="{
                                                borderColor: link.active ? `var(--color-primary-600)` : '#e5e7eb',
                                                backgroundColor: link.active ? `var(--color-primary-600)` : 'white',
                                                color: link.active ? 'white' : '#374151'
                                            }"
                                            v-html="link.label" 
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL PRODUCTO -->
        <ModalProducto
            v-model="modalOpen"
            :producto="productoSeleccionado"
            :grupos="grupos"
            :lineas="lineas"
            :estados="estados"
            :unidades="unidades"
            :unidad-id="unidadId"
            :editando="editando"
            @saved="recargarDatos"
        />

        <!-- 🔥 MODAL FICHA TÉCNICA -->
        <!-- En Index.vue -->
        <ModalFichaTecnica
            v-model="modalFichaOpen"
            :producto="productoParaFicha"
            :grupos="grupos"
            :lineas="lineas"
            :unidades="unidades"
            :unidad-id="unidadId"
            @saved="recargarDatos"
        />
    </div>
</template>

<style scoped>
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}

input:focus, select:focus {
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

@media (max-width: 1023px) {
    .max-h-0 { max-height: 0; }
    .max-h-\[600px\] { max-height: 600px; }
}

@media (max-width: 380px) {
    .xs\:flex-row { flex-direction: column !important; }
}

* { scroll-behavior: smooth; }
</style>