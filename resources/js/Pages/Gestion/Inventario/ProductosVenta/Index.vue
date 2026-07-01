<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, onMounted, watch, onUnmounted, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Object,
    categorias: Array,
    totalActivos: Number,
    totalInactivos: Number,
    filtros: Object,
})

// ==================== ESTADO ====================
const search = ref(props.filtros?.search || '')
const estado = ref(props.filtros?.estado || '')
const categoriasSeleccionadas = ref([])
const isMobile = ref(false)
const isTablet = ref(false)
const filtrosAbiertos = ref(false)

// 🔥 Controlar si la búsqueda se está escribiendo (para no cerrar filtros)
const escribiendo = ref(false)

// 🔥 IMPORTANTE: Declarar setTimeout para usarlo en el template
const setTimeoutFn = (fn, delay) => {
    return setTimeout(fn, delay)
}

// 🔥 También declarar clearTimeout por si acaso
const clearTimeoutFn = (timeoutId) => {
    return clearTimeout(timeoutId)
}

// ==================== COMPUTED ====================
const filtrosActivos = computed(() => {
    let count = 0
    if (search.value && search.value.trim() !== '') count++
    if (estado.value && estado.value !== '') count++
    if (categoriasSeleccionadas.value.length > 0) count++
    return count
})

// ==================== FUNCIONES ====================
// Procesar categorías seleccionadas desde los filtros al cargar
const procesarCategoriasSeleccionadas = () => {
    if (props.filtros?.categorias) {
        const queryCategorias = props.filtros.categorias;
        if (typeof queryCategorias === 'string' && queryCategorias.trim() !== '') {
            categoriasSeleccionadas.value = queryCategorias.split(',').map(Number);
        } else if (Array.isArray(queryCategorias)) {
            categoriasSeleccionadas.value = queryCategorias.map(Number);
        }
    }
}

// Obtener el ID real de la categoría
const getCategoriaId = (categoria) => {
    return categoria ? (categoria.id ?? categoria.id_categoria ?? categoria.IdCategoria) : null;
}

// Obtener el nombre real de la categoría
const getCategoriaNombre = (categoria) => {
    return categoria ? (categoria.nombre ?? categoria.Detalle) : '';
}

// Alternar selección de categorías
const toggleCategoria = (categoria) => {
    const id = getCategoriaId(categoria);
    if (id === null || id === undefined) return;
    
    const idNum = Number(id);
    const index = categoriasSeleccionadas.value.indexOf(idNum);
    
    if (index === -1) {
        categoriasSeleccionadas.value.push(idNum);
    } else {
        categoriasSeleccionadas.value.splice(index, 1);
    }
}

// Verificar si una categoría está seleccionada
const isCategoriaSelected = (categoria) => {
    const id = getCategoriaId(categoria);
    if (id === null || id === undefined) return false;
    return categoriasSeleccionadas.value.includes(Number(id));
}

// Aplicar filtros a la URL
const aplicarFiltros = (cerrarFiltros = true) => {
    const params = {}

    if (search.value && search.value.trim() !== '') {
        params.search = search.value;
    }
    
    if (estado.value !== undefined && estado.value !== null && estado.value !== '') {
        params.estado = estado.value;
    }
    
    if (categoriasSeleccionadas.value.length > 0) {
        params.categorias = categoriasSeleccionadas.value.join(',');
    }

    router.get('/gestion/inventario/productos-venta', params, {
        preserveState: true,
        replace: true,
    })
    
    // 🔥 Cerrar filtros en móvil solo si no está escribiendo
    if (isMobile.value && cerrarFiltros && !escribiendo.value) {
        filtrosAbiertos.value = false
    }
}

// 🔥 Función específica para buscar (con debounce)
let timeoutBuscador
const buscarConDebounce = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        // 🔥 No cerrar filtros al buscar
        aplicarFiltros(false)
    }, 500)
}

// Limpiar todo
const limpiarFiltros = () => {
    search.value = ''
    estado.value = ''
    categoriasSeleccionadas.value = []
    filtrosAbiertos.value = false
    escribiendo.value = false
    
    router.get('/gestion/inventario/productos-venta', {}, {
        preserveState: true,
        replace: true,
    })
}

// Ver detalle de aprobación
const verAprobacion = (id) => {
    router.get(`/gestion/inventario/productos-aprobacion/ver/${id}`)
}

const estadoTexto = (activo) => {
    if (activo === 0) return 'Activo'
    if (activo === 1) return 'Inactivo'
    if (activo === 2) return 'Pendiente'
    if (activo === 3) return 'Rechazado'
    return 'Desconocido'
}

const estadoClase = (activo) => {
    if (activo === 0) return 'bg-green-100 text-green-800'
    if (activo === 1) return 'bg-red-100 text-red-800'
    if (activo === 2) return 'bg-yellow-100 text-yellow-800'
    if (activo === 3) return 'bg-red-100 text-red-800'
    return 'bg-gray-100 text-gray-800'
}

// Obtener nombre de categoría para mostrar en la tabla
const getCategoriaNombreProducto = (producto) => {
    if (producto.categoria) {
        return producto.categoria.nombre
    }
    return 'Sin categoría'
}

const getCategoriaClase = (producto) => {
    if (producto.categoria) {
        return 'bg-primary-100 text-primary-700'
    }
    return 'bg-gray-100 text-gray-500'
}

// Toggle filtros en móvil
const toggleFiltros = () => {
    filtrosAbiertos.value = !filtrosAbiertos.value
    if (filtrosAbiertos.value) {
        // Cuando se abren los filtros, enfocar el input de búsqueda
        setTimeout(() => {
            const input = document.querySelector('.filtros-buscar-input')
            if (input) input.focus()
        }, 100)
    }
}

// ==================== DETECTAR RESPONSIVE ====================
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
    isTablet.value = window.innerWidth >= 768 && window.innerWidth < 1024
    // En desktop, los filtros siempre están abiertos
    if (!isMobile.value) {
        filtrosAbiertos.value = true
    }
}

// ==================== WATCHES ====================
// 🔥 Watch para la búsqueda con debounce y sin cerrar filtros
watch(search, (newVal, oldVal) => {
    // Marcar que está escribiendo
    if (newVal !== oldVal) {
        escribiendo.value = true
    }
    
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros(false)
        // Después de aplicar filtros, permitir cerrar manualmente
        setTimeout(() => {
            escribiendo.value = false
        }, 300)
    }, 500)
})

// ==================== LIFECYCLE ====================
onMounted(() => {
    // Inicializar el estado responsive
    handleResize()
    
    // Procesar categorías seleccionadas
    procesarCategoriasSeleccionadas()
    
    // Escuchar cambios de tamaño
    window.addEventListener('resize', handleResize)
    
    // En desktop, abrir filtros por defecto
    if (!isMobile.value) {
        filtrosAbiertos.value = true
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    clearTimeout(timeoutBuscador)
})
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header Responsive -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-3 sm:mb-4">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                             :style="{ backgroundColor: `var(--color-primary-100)` }">
                            <i class="fas fa-boxes text-primary-600 text-[11px] sm:text-sm"
                               :style="{ color: `var(--color-primary-600)` }"></i>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-sm sm:text-base font-bold text-gray-800 truncate">Productos de Venta</h1>
                            <p class="text-[9px] sm:text-[10px] text-gray-500 truncate">Gestión de productos para punto de venta</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <!-- Botón toggle filtros en móvil -->
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
                        <Link href="/gestion/inventario/productos-venta/create" 
                              class="flex-1 sm:flex-none bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1 transition">
                            <i class="fas fa-plus text-[10px]"></i> 
                            <span class="hidden sm:inline">Nuevo Producto</span>
                            <span class="sm:hidden">Nuevo</span>
                        </Link>
                    </div>
                </div>

                <!-- Layout Principal -->
                <div class="flex flex-col lg:flex-row gap-3 sm:gap-4">
                    
                    <!-- FILTROS - Colapsable en móvil -->
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
                                        class="filtros-buscar-input w-full border rounded-md pl-7 pr-2 py-1.5 text-[11px] focus:ring-2 focus:outline-none"
                                        :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                                        @keyup.enter="aplicarFiltros(true)"
                                        @focus="escribiendo = true"
                                        @blur="setTimeoutFn(() => { escribiendo = false }, 300)"
                                    >
                                </div>
                            </div>

                            <!-- Estado -->
                            <div class="mb-3">
                                <label class="block text-[10px] font-medium text-gray-700 mb-1">Estado</label>
                                <div class="grid grid-cols-2 sm:grid-cols-1 gap-0.5">
                                    <label class="flex items-center gap-2 cursor-pointer py-0.5">
                                        <input type="radio" value="" v-model="estado" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }" @change="aplicarFiltros(true)"> 
                                        <span class="text-[11px] text-gray-700">Todos</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer py-0.5">
                                        <input type="radio" value="0" v-model="estado" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }" @change="aplicarFiltros(true)"> 
                                        <span class="text-[11px] text-gray-700">Activos ({{ totalActivos }})</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer py-0.5">
                                        <input type="radio" value="1" v-model="estado" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }" @change="aplicarFiltros(true)"> 
                                        <span class="text-[11px] text-gray-700">Inactivos ({{ totalInactivos }})</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer py-0.5">
                                        <input type="radio" value="2" v-model="estado" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }" @change="aplicarFiltros(true)"> 
                                        <span class="text-[11px] text-gray-700">Pendientes</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer py-0.5">
                                        <input type="radio" value="3" v-model="estado" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }" @change="aplicarFiltros(true)"> 
                                        <span class="text-[11px] text-gray-700">Rechazados</span>
                                    </label>
                                </div>
                            </div>

                            <!-- LISTA DE CATEGORÍAS -->
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[10px] font-medium text-gray-700">Categorías</label>
                                    <span v-if="categoriasSeleccionadas.length > 0" class="text-[9px] text-primary-600 font-bold">
                                        {{ categoriasSeleccionadas.length }} sel.
                                    </span>
                                </div>
                                <div class="max-h-48 overflow-y-auto border rounded-md bg-white"
                                     :style="{ borderColor: `var(--color-primary-200)` }">
                                    <div 
                                        v-for="(categoria, index) in categorias" 
                                        :key="getCategoriaId(categoria) || index" 
                                        class="flex items-center justify-between px-2 py-1.5 hover:bg-gray-50 border-b border-gray-100 last:border-b-0"
                                    >
                                        <label class="flex items-center gap-1.5 flex-1 min-w-0 cursor-pointer select-none py-0.5">
                                            <input 
                                                type="checkbox" 
                                                :checked="isCategoriaSelected(categoria)" 
                                                @change="toggleCategoria(categoria); aplicarFiltros(true)"
                                                class="w-3 h-3 rounded border-gray-300 cursor-pointer flex-shrink-0"
                                                :style="{ accentColor: `var(--color-primary-600)` }"
                                            >
                                            <span class="text-[11px] text-gray-700 truncate">
                                                {{ getCategoriaNombre(categoria) }}
                                            </span>
                                        </label>
                                        <span class="text-[9px] text-gray-400 pl-1 pr-1 flex-shrink-0">
                                            ({{ categoria.productos_count || 0 }})
                                        </span>
                                    </div>
                                    <div v-if="!categorias || categorias.length === 0" class="px-2 py-3 text-center text-gray-400 text-[10px]">
                                        No hay categorías disponibles
                                    </div>
                                </div>
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
                            
                            <!-- Indicador de filtros activos (móvil) -->
                            <div class="p-2 border-b flex flex-wrap gap-1 lg:hidden"
                                :style="{ borderColor: `var(--color-primary-200)` }">
                                <span v-if="search" class="px-1.5 py-0.5 bg-primary-50 rounded text-[9px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-search text-[8px]"></i> {{ search }}
                                </span>
                                <span v-if="estado !== ''" class="px-1.5 py-0.5 bg-primary-50 rounded text-[9px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-circle text-[6px]" :class="estado == '0' ? 'text-green-500' : estado == '2' ? 'text-yellow-500' : 'text-red-500'"></i>
                                    {{ estado == '0' ? 'Activos' : estado == '1' ? 'Inactivos' : estado == '2' ? 'Pendientes' : 'Rechazados' }}
                                </span>
                                <span v-if="categoriasSeleccionadas.length > 0" class="px-1.5 py-0.5 bg-primary-50 rounded text-[9px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-tag text-[8px]"></i> {{ categoriasSeleccionadas.length }} cat.
                                </span>
                            </div>

                            <!-- Versión Desktop: Tabla -->
                            <div class="hidden lg:block overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-primary-50" :style="{ backgroundColor: `var(--color-primary-50)` }">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Código</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Producto</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Categoría</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Precio</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Estado</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Aprobación</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="producto in productos.data" :key="producto.IdDetalleProducto" class="hover:bg-gray-50 transition">
                                            <td class="px-3 py-2 text-[11px] text-gray-600 font-mono">{{ producto.Codigo }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-800">{{ producto.Detalle }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="getCategoriaClase(producto)">
                                                    <i v-if="producto.categoria" class="fas fa-tag mr-1 text-[8px]"></i>
                                                    <i v-else class="fas fa-question-circle mr-1 text-[8px]"></i>
                                                    {{ getCategoriaNombreProducto(producto) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-right font-semibold" :style="{ color: `var(--color-primary-600)` }">
                                                {{ Number(producto.PrecioVenta).toFixed(2) }} Bs
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                    {{ estadoTexto(producto.ActivoInactivo) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span v-if="producto.ActivoInactivo === 2" class="px-1.5 py-0.5 text-[9px] rounded-full bg-yellow-100 text-yellow-800">
                                                    <i class="fas fa-clock mr-0.5"></i> Pendiente
                                                </span>
                                                <span v-else-if="producto.ActivoInactivo === 3" class="px-1.5 py-0.5 text-[9px] rounded-full bg-red-100 text-red-800">
                                                    <i class="fas fa-times mr-0.5"></i> Rechazado
                                                </span>
                                                <button v-else-if="producto.ActivoInactivo === 0" @click="verAprobacion(producto.IdDetalleProducto)" class="transition" :style="{ color: `var(--color-primary-600)` }" title="Ver aprobación">
                                                    <i class="fas fa-check-circle text-xs"></i>
                                                </button>
                                                <span v-else class="text-gray-400 text-[9px]">-</span>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <Link :href="`/gestion/inventario/productos-venta/${producto.IdDetalleProducto}/edit`" 
                                                      class="transition" 
                                                      :style="{ color: `var(--color-primary-600)` }"
                                                      title="Editar">
                                                    <i class="fas fa-edit text-[11px]"></i>
                                                </Link>
                                            </td>
                                        </tr>
                                        <tr v-if="!productos.data || productos.data.length === 0">
                                            <td colspan="8" class="px-3 py-8 text-center text-gray-400 text-[11px]">
                                                <i class="fas fa-box-open text-xl mb-1 block text-gray-300"></i>
                                                No se encontraron productos con los filtros seleccionados.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Versión Tablet: Tabla simplificada -->
                            <div class="hidden sm:block lg:hidden overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-primary-50" :style="{ backgroundColor: `var(--color-primary-50)` }">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Producto</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Categoría</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Precio</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Estado</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold" :style="{ color: `var(--color-primary-700)` }">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="producto in productos.data" :key="producto.IdDetalleProducto" class="hover:bg-gray-50 transition">
                                            <td class="px-3 py-2">
                                                <div class="text-[11px] font-medium text-gray-800">{{ producto.Detalle }}</div>
                                                <div class="text-[9px] text-gray-400 font-mono">{{ producto.Codigo }}</div>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="getCategoriaClase(producto)">
                                                    {{ getCategoriaNombreProducto(producto) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-right font-semibold" :style="{ color: `var(--color-primary-600)` }">
                                                {{ Number(producto.PrecioVenta).toFixed(2) }} Bs
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                    {{ estadoTexto(producto.ActivoInactivo) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <button v-if="producto.ActivoInactivo === 2 || producto.ActivoInactivo === 3" 
                                                        @click="verAprobacion(producto.IdDetalleProducto)" 
                                                        class="mr-2 transition" 
                                                        :style="{ color: `var(--color-primary-600)` }"
                                                        title="Ver aprobación">
                                                    <i class="fas fa-info-circle text-[11px]"></i>
                                                </button>
                                                <Link :href="`/gestion/inventario/productos-venta/${producto.IdDetalleProducto}/edit`" 
                                                      class="transition" 
                                                      :style="{ color: `var(--color-primary-600)` }"
                                                      title="Editar">
                                                    <i class="fas fa-edit text-[11px]"></i>
                                                </Link>
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

                            <!-- Versión Móvil: Tarjetas -->
                            <div class="sm:hidden divide-y divide-gray-100">
                                <div v-for="producto in productos.data" :key="producto.IdDetalleProducto" 
                                     class="p-3 hover:bg-gray-50 transition">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span class="text-xs font-medium text-gray-800 truncate">{{ producto.Detalle }}</span>
                                            </div>
                                            <div class="text-[10px] text-gray-400 font-mono mb-1">
                                                <i class="fas fa-hashtag mr-0.5 text-[8px]"></i>
                                                {{ producto.Codigo }}
                                            </div>
                                            <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                                <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="getCategoriaClase(producto)">
                                                    {{ getCategoriaNombreProducto(producto) }}
                                                </span>
                                                <span class="text-[10px] font-semibold" :style="{ color: `var(--color-primary-600)` }">
                                                    {{ Number(producto.PrecioVenta).toFixed(2) }} Bs
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                    {{ estadoTexto(producto.ActivoInactivo) }}
                                                </span>
                                                <span v-if="producto.ActivoInactivo === 2 || producto.ActivoInactivo === 3" 
                                                      class="px-1.5 py-0.5 text-[8px] rounded-full bg-yellow-100 text-yellow-800">
                                                    <i class="fas fa-clock mr-0.5"></i> Por aprobar
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex gap-2 flex-shrink-0">
                                            <button v-if="producto.ActivoInactivo === 2 || producto.ActivoInactivo === 3" 
                                                    @click="verAprobacion(producto.IdDetalleProducto)" 
                                                    class="p-1.5 rounded-lg transition"
                                                    :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-600)` }"
                                                    title="Ver aprobación">
                                                <i class="fas fa-info-circle text-sm"></i>
                                            </button>
                                            <Link :href="`/gestion/inventario/productos-venta/${producto.IdDetalleProducto}/edit`" 
                                                  class="p-1.5 rounded-lg transition"
                                                  :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-600)` }"
                                                  title="Editar">
                                                <i class="fas fa-edit text-sm"></i>
                                            </Link>
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
    </div>
</template>

<style scoped>
/* Transiciones suaves */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}

input:focus {
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

/* Scrollbar personalizada para el listado de categorías */
.max-h-48::-webkit-scrollbar {
    width: 4px;
}

.max-h-48::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.max-h-48::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.max-h-48::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Animación para los filtros en móvil */
@media (max-width: 1023px) {
    .max-h-0 {
        max-height: 0;
    }
    .max-h-\[600px\] {
        max-height: 600px;
    }
}

/* Estilos para pantallas muy pequeñas */
@media (max-width: 380px) {
    .xs\:flex-row {
        flex-direction: column !important;
    }
}

/* Scroll suave */
* {
    scroll-behavior: smooth;
}
</style>