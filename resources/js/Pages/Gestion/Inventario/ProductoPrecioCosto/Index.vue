<!-- resources/js/Pages/Gestion/Inventario/PrecioCosto/Index.vue -->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, onMounted, watch, onUnmounted, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Object,
    lineas: Array,
    totalActivos: Number,
    totalInactivos: Number,
    filtros: Object,
})

// ==================== ESTADO ====================
const search = ref(props.filtros?.search || '')
const estado = ref(props.filtros?.estado || '')
const lineaId = ref(props.filtros?.linea_id || '')
const isMobile = ref(window.innerWidth < 768)
const isTablet = ref(window.innerWidth >= 768 && window.innerWidth < 1024)
const filtrosAbiertos = ref(false)

// ==================== COMPUTED ====================
const lineasFiltradas = computed(() => {
    if (!props.lineas) return []
    return props.lineas
})

const lineaSeleccionadaNombre = computed(() => {
    if (!lineaId.value) return 'Todas las líneas'
    const linea = props.lineas?.find(l => l.id == lineaId.value)
    return linea?.nombre || 'Línea seleccionada'
})

// ==================== FUNCIONES ====================
const aplicarFiltros = () => {
    const params = {}

    if (search.value && search.value.trim() !== '') {
        params.search = search.value
    }
    
    if (estado.value !== undefined && estado.value !== null && estado.value !== '') {
        params.estado = estado.value
    }
    
    if (lineaId.value) {
        params.linea_id = lineaId.value
    }

    router.get('/gestion/inventario/precio-costo', params, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltros = () => {
    search.value = ''
    estado.value = ''
    lineaId.value = ''
    filtrosAbiertos.value = false
    
    router.get('/gestion/inventario/precio-costo', {}, {
        preserveState: true,
        replace: true,
    })
}

const estadoTexto = (activo) => {
    if (activo === 0) return 'Activo'
    if (activo === 1) return 'Inactivo'
    return 'Desconocido'
}

const estadoClase = (activo) => {
    if (activo === 0) return 'bg-green-100 text-green-800'
    if (activo === 1) return 'bg-red-100 text-red-800'
    return 'bg-gray-100 text-gray-800'
}

const toggleFiltros = () => {
    filtrosAbiertos.value = !filtrosAbiertos.value
}

// ==================== DETECTAR RESPONSIVE ====================
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
    isTablet.value = window.innerWidth >= 768 && window.innerWidth < 1024
}

// ==================== DEBOUNCE ====================
let timeout
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        aplicarFiltros()
    }, 500)
})

// ==================== LIFECYCLE ====================
onMounted(() => {
    window.addEventListener('resize', handleResize)
    handleResize()
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    clearTimeout(timeout)
})
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-3 px-3 sm:py-4 sm:px-4 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                             :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                            <i class="fas fa-dollar-sign text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">Precio Costo de Productos</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">Último precio costo registrado por producto</p>
                        </div>
                    </div>
                    
                    <!-- Botón toggle filtros (móvil) -->
                    <button 
                        @click="toggleFiltros"
                        class="sm:hidden flex items-center gap-2 px-3 py-1.5 border rounded-lg text-xs"
                        :style="{ borderColor: `var(--color-primary-300)` }"
                    >
                        <i class="fas fa-sliders-h" :style="{ color: `var(--color-primary-600)` }"></i>
                        <span>{{ filtrosAbiertos ? 'Ocultar filtros' : 'Mostrar filtros' }}</span>
                        <i class="fas" :class="filtrosAbiertos ? 'fa-chevron-up' : 'fa-chevron-down'" :style="{ color: `var(--color-primary-600)` }"></i>
                    </button>
                </div>

                <!-- Layout Principal -->
                <div class="flex flex-col lg:flex-row gap-4">
                    
                    <!-- FILTROS - Colapsable en móvil -->
                    <div 
                        class="lg:w-64 flex-shrink-0 transition-all duration-300 overflow-hidden"
                        :class="{
                            'max-h-[500px] opacity-100': filtrosAbiertos || !isMobile,
                            'max-h-0 opacity-0 sm:max-h-full sm:opacity-100': !filtrosAbiertos && isMobile
                        }"
                    >
                        <div class="bg-white rounded-lg shadow-sm p-3 sticky top-24">
                            <h3 class="text-xs font-semibold text-gray-800 mb-3 flex items-center gap-1">
                                <i class="fas fa-filter text-[10px]" :style="{ color: `var(--color-primary-600)` }"></i> 
                                Filtros
                                <span class="ml-auto text-[9px] text-gray-400 hidden lg:inline">
                                    {{ lineas?.length || 0 }} líneas
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
                                        @keyup.enter="aplicarFiltros"
                                    >
                                </div>
                            </div>

                            <!-- Estado -->
                            <div class="mb-3">
                                <label class="block text-[10px] font-medium text-gray-700 mb-1">Estado</label>
                                <div class="flex flex-col gap-1">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="" v-model="estado" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }"> 
                                        <span class="text-[11px] text-gray-700">Todos</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="0" v-model="estado" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }"> 
                                        <span class="text-[11px] text-gray-700">Activos <span class="text-[9px] text-gray-400">({{ totalActivos }})</span></span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="1" v-model="estado" class="w-3 h-3" :style="{ accentColor: `var(--color-primary-600)` }"> 
                                        <span class="text-[11px] text-gray-700">Inactivos <span class="text-[9px] text-gray-400">({{ totalInactivos }})</span></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Líneas -->
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[10px] font-medium text-gray-700">Líneas</label>
                                    <span v-if="lineaId" class="text-[9px] font-bold px-1.5 py-0.5 rounded"
                                        :style="{ color: `var(--color-primary-600)`, backgroundColor: `var(--color-primary-50)` }">
                                        Filtro activo
                                    </span>
                                </div>
                                <div class="max-h-48 overflow-y-auto border rounded-md bg-white"
                                    :style="{ borderColor: `var(--color-primary-200)` }">
                                    <!-- Opción "Todas" -->
                                    <div 
                                        class="flex items-center px-2 py-1.5 hover:bg-gray-50 border-b border-gray-100 cursor-pointer"
                                        @click="lineaId = ''; aplicarFiltros()"
                                    >
                                        <label class="flex items-center gap-1.5 flex-1 min-w-0 cursor-pointer select-none py-0.5">
                                            <input 
                                                type="radio" 
                                                value=""
                                                :checked="lineaId === ''"
                                                class="w-3 h-3 rounded border-gray-300 focus:ring-0 cursor-pointer"
                                                :style="{ accentColor: `var(--color-primary-600)` }"
                                            >
                                            <span class="text-[11px] text-gray-700 truncate font-medium">
                                                Todas las líneas
                                            </span>
                                        </label>
                                    </div>
                                    <!-- Líneas -->
                                    <div 
                                        v-for="(linea, index) in lineasFiltradas" 
                                        :key="linea.id || index" 
                                        class="flex items-center px-2 py-1.5 hover:bg-gray-50 border-b border-gray-100 last:border-b-0 cursor-pointer"
                                        @click="lineaId = linea.id; aplicarFiltros()"
                                    >
                                        <label class="flex items-center gap-1.5 flex-1 min-w-0 cursor-pointer select-none py-0.5">
                                            <input 
                                                type="radio" 
                                                :value="linea.id"
                                                :checked="lineaId == linea.id"
                                                class="w-3 h-3 rounded border-gray-300 focus:ring-0 cursor-pointer"
                                                :style="{ accentColor: `var(--color-primary-600)` }"
                                            >
                                            <span class="text-[11px] text-gray-700 truncate">
                                                {{ linea.nombre }}
                                            </span>
                                        </label>
                                    </div>
                                    <div v-if="!lineas || lineas.length === 0" class="px-2 py-3 text-center text-gray-400 text-[10px]">
                                        No hay líneas disponibles
                                    </div>
                                </div>
                            </div>

                            <!-- Botonera -->
                            <div class="flex gap-2 pt-2 border-t" :style="{ borderColor: `var(--color-primary-200)` }">
                                <button @click="aplicarFiltros" class="flex-1 px-2 py-1.5 text-white rounded-md text-[10px] transition flex items-center justify-center gap-1"
                                    :style="{ backgroundColor: `var(--color-primary-600)` }">
                                    <i class="fas fa-search text-[8px]"></i> Filtrar
                                </button>
                                <button @click="limpiarFiltros" class="px-2 py-1.5 border border-gray-300 rounded-md text-[10px] text-gray-700 hover:bg-gray-50 transition" title="Limpiar Filtros">
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
                                    <i class="fas fa-circle text-[6px]" :class="estado == '0' ? 'text-green-500' : 'text-red-500'"></i>
                                    {{ estado == '0' ? 'Activos' : 'Inactivos' }}
                                </span>
                                <span v-if="lineaId" class="px-1.5 py-0.5 bg-primary-50 rounded text-[9px] flex items-center gap-1"
                                    :style="{ color: `var(--color-primary-700)` }">
                                    <i class="fas fa-list-alt text-[8px]"></i> {{ lineaSeleccionadaNombre }}
                                </span>
                            </div>

                            <!-- Tabla Desktop -->
                            <div class="hidden md:block overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider"
                                                :style="{ color: `var(--color-primary-700)` }">Código</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider"
                                                :style="{ color: `var(--color-primary-700)` }">Producto</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider"
                                                :style="{ color: `var(--color-primary-700)` }">Línea</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider"
                                                :style="{ color: `var(--color-primary-700)` }">Unidad</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wider"
                                                :style="{ color: `var(--color-primary-700)` }">Precio Lista</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wider"
                                                :style="{ color: `var(--color-primary-700)` }">Precio Costo</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold uppercase tracking-wider"
                                                :style="{ color: `var(--color-primary-700)` }">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="producto in productos.data" :key="producto.IdProducto" class="hover:bg-gray-50 transition">
                                            <td class="px-3 py-2 text-[11px] text-gray-600 font-mono">{{ producto.Codigo }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-800">{{ producto.Descripcion }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-500">{{ producto.linea?.Linea || '-' }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-500">{{ producto.unidad_medida?.UnidadMedida || '-' }}</td>
                                            <td class="px-3 py-2 text-[11px] text-right font-semibold text-gray-700">
                                                {{ Number(producto.precio_lista || 0).toFixed(2) }} Bs
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-right">
                                                <span v-if="producto.ultimo_precio_costo > 0" class="font-semibold"
                                                    :style="{ color: `var(--color-primary-600)` }">
                                                    {{ Number(producto.ultimo_precio_costo).toFixed(2) }} Bs
                                                </span>
                                                <span v-else class="text-gray-400 text-[10px]">
                                                    <i class="fas fa-clock mr-0.5"></i> Sin registro
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                    {{ estadoTexto(producto.ActivoInactivo) }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-if="!productos.data || productos.data.length === 0">
                                            <td colspan="7" class="px-3 py-8 text-center text-gray-400 text-[11px]">
                                                <i class="fas fa-box-open text-xl mb-1 block"></i>
                                                No se encontraron productos con los filtros seleccionados.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Cards Mobile / Tablet -->
                            <div class="md:hidden divide-y divide-gray-100">
                                <div v-for="producto in productos.data" :key="producto.IdProducto" class="p-3 hover:bg-gray-50 transition">
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="font-mono text-xs text-gray-600">{{ producto.Codigo }}</span>
                                        <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                            {{ estadoTexto(producto.ActivoInactivo) }}
                                        </span>
                                    </div>
                                    <div class="text-sm font-medium text-gray-800">{{ producto.Descripcion }}</div>
                                    <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1 text-[10px] text-gray-500">
                                        <span><i class="fas fa-list-alt mr-1" :style="{ color: `var(--color-primary-400)` }"></i>{{ producto.linea?.Linea || '-' }}</span>
                                        <span><i class="fas fa-ruler mr-1" :style="{ color: `var(--color-primary-400)` }"></i>{{ producto.unidad_medida?.UnidadMedida || '-' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center mt-2 pt-2 border-t" :style="{ borderColor: `var(--color-primary-100)` }">
                                        <div>
                                            <span class="text-[9px] text-gray-400 block">Precio Lista</span>
                                            <span class="text-xs font-semibold text-gray-700">{{ Number(producto.precio_lista || 0).toFixed(2) }} Bs</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[9px] text-gray-400 block">Precio Costo</span>
                                            <span v-if="producto.ultimo_precio_costo > 0" class="text-xs font-semibold"
                                                :style="{ color: `var(--color-primary-600)` }">
                                                {{ Number(producto.ultimo_precio_costo).toFixed(2) }} Bs
                                            </span>
                                            <span v-else class="text-[10px] text-gray-400">
                                                <i class="fas fa-clock mr-0.5"></i> Sin registro
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="!productos.data || productos.data.length === 0" class="p-8 text-center text-gray-400 text-[11px]">
                                    <i class="fas fa-box-open text-xl mb-1 block"></i>
                                    No se encontraron productos con los filtros seleccionados.
                                </div>
                            </div>

                            <!-- Paginación -->
                            <div v-if="productos.links && productos.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                                <div class="flex flex-col sm:flex-row justify-between items-center gap-2 flex-wrap">
                                    <div class="text-[9px] text-gray-500 order-2 sm:order-1">
                                        Mostrando {{ productos.from || 0 }} a {{ productos.to || 0 }} de {{ productos.total || 0 }}
                                    </div>
                                    <div class="flex gap-0.5 flex-wrap justify-center order-1 sm:order-2">
                                        <Link 
                                            v-for="link in productos.links" 
                                            :key="link.label" 
                                            :href="link.url || '#'" 
                                            class="px-2 py-0.5 rounded border text-[9px] transition"
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

/* Scrollbar personalizada para el listado de líneas */
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

/* Estilos para móvil */
@media (max-width: 640px) {
    .xs\:block {
        display: block;
    }
}

/* Animación para los filtros en móvil */
@media (max-width: 1023px) {
    .max-h-0 {
        max-height: 0;
    }
    .max-h-\[500px\] {
        max-height: 500px;
    }
}
</style>