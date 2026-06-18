<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    categoriasConProductos: Array,
    productos: Array,
    asignaciones: Array,
    sucursalId: Number,
    sucursalNombre: String,
    totalProductos: Number,
    totalHabilitados: Number,
})

// ==================== ESTADO ====================
const productosSeleccionados = ref([...(props.asignaciones || [])])
const buscando = ref('')
const guardando = ref(false)
const mostrarSoloHabilitados = ref(false)
const isMobile = ref(false)
const isTablet = ref(false)
const filtrosAbiertos = ref(false)

// ==================== DETECTAR RESPONSIVE ====================
const handleResize = () => {
    isMobile.value = window.innerWidth < 640
    isTablet.value = window.innerWidth >= 640 && window.innerWidth < 1024
}

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// ==================== FUNCIONES ====================
const productoCoincideConBusqueda = (producto) => {
    if (!buscando.value) return true
    const termino = buscando.value.toLowerCase()
    return producto.nombre?.toLowerCase().includes(termino) || 
           producto.id?.toString().includes(termino) ||
           producto.Codigo?.toLowerCase().includes(termino)
}

const productoEstaHabilitado = (producto) => {
    if (!mostrarSoloHabilitados.value) return true
    return productosSeleccionados.value.includes(producto.id)
}

const categoriasFiltradas = computed(() => {
    if (!props.categoriasConProductos) return []
    
    return props.categoriasConProductos
        .map(categoria => {
            const productosFiltrados = (categoria.productos || []).filter(producto => {
                return productoCoincideConBusqueda(producto) && productoEstaHabilitado(producto)
            })
            
            if (productosFiltrados.length === 0) return null
            
            return {
                ...categoria,
                productos: productosFiltrados
            }
        })
        .filter(categoria => categoria !== null)
})

const seleccionarTodos = () => {
    const todosIds = []
    categoriasFiltradas.value.forEach(categoria => {
        categoria.productos.forEach(producto => {
            todosIds.push(producto.id)
        })
    })
    productosSeleccionados.value = todosIds
}

const deseleccionarTodos = () => {
    productosSeleccionados.value = []
}

const estaSeleccionado = (id) => {
    return productosSeleccionados.value.includes(id)
}

const toggleProducto = (id) => {
    if (estaSeleccionado(id)) {
        productosSeleccionados.value = productosSeleccionados.value.filter(i => i !== id)
    } else {
        productosSeleccionados.value.push(id)
    }
}

const guardarAsignaciones = () => {
    if (guardando.value) return
    
    guardando.value = true
    router.post('/gestion/inventario/asignar-productos-categoria', {
        productos_ids: productosSeleccionados.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast?.success('Éxito', 'Productos habilitados correctamente para esta sucursal')
        },
        onError: (error) => {
            console.error('Error:', error)
            toast?.error('Error', 'No se pudieron guardar las habilitaciones')
        },
        onFinish: () => { 
            guardando.value = false 
        }
    })
}

const toggleFiltros = () => {
    filtrosAbiertos.value = !filtrosAbiertos.value
}

// Contador de seleccionados
const totalSeleccionados = computed(() => productosSeleccionados.value.length)
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-6">
            <div class="max-w-6xl mx-auto">
                <!-- Header Responsive -->
                <div class="text-center mb-4 sm:mb-6">
                    <div class="inline-flex items-center justify-center w-10 h-10 sm:w-14 sm:h-14 rounded-2xl mb-2 sm:mb-3"
                         :style="{ backgroundColor: `var(--color-primary-100)` }">
                        <i class="fas fa-store text-primary-600 text-base sm:text-xl"
                           :style="{ color: `var(--color-primary-600)` }"></i>
                    </div>
                    <h1 class="text-base sm:text-xl font-bold text-gray-900">Habilitar Productos para Sucursal</h1>
                    <p class="text-[10px] sm:text-xs text-gray-500 px-2">
                        Selecciona qué productos estarán disponibles en el menú táctil para esta sucursal
                    </p>
                    <p class="text-[10px] sm:text-xs text-primary-600 font-medium mt-1">
                        📍 Sucursal actual: <strong>{{ sucursalNombre }} (ID: {{ sucursalId }})</strong>
                    </p>
                    <div class="flex flex-wrap justify-center gap-2 sm:gap-4 mt-2 text-[10px] sm:text-xs">
                        <span class="text-gray-500">Total: <strong>{{ totalProductos }}</strong></span>
                        <span class="text-primary-600">Habilitados: <strong>{{ totalSeleccionados }}</strong></span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4">
                    <!-- Barra de búsqueda y filtros -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-3 sm:mb-4 pb-3 border-b"
                         :style="{ borderColor: `var(--color-primary-200)` }">
                        
                        <!-- Búsqueda -->
                        <div class="relative w-full sm:w-80">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[10px] sm:text-sm"></i>
                            <input 
                                type="text" 
                                v-model="buscando" 
                                placeholder="Buscar producto..."
                                class="w-full border rounded-lg pl-8 sm:pl-10 pr-8 sm:pr-10 py-1.5 sm:py-2 text-[11px] sm:text-sm focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                            >
                            <button 
                                v-if="buscando"
                                @click="buscando = ''"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <i class="fas fa-times text-[10px] sm:text-sm"></i>
                            </button>
                        </div>
                        
                        <!-- Botones de acción móvil -->
                        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                            <!-- Botón toggle filtros móvil -->
                            <button 
                                @click="toggleFiltros"
                                class="sm:hidden flex-1 px-3 py-1.5 bg-white border rounded-lg text-[10px] flex items-center justify-center gap-1.5 transition"
                                :style="{ borderColor: `var(--color-primary-300)` }"
                            >
                                <i class="fas fa-sliders-h text-[10px]" :style="{ color: `var(--color-primary-600)` }"></i>
                                <span class="text-gray-700">{{ filtrosAbiertos ? 'Ocultar' : 'Filtros' }}</span>
                            </button>
                            
                            <!-- Botones desktop -->
                            <div class="hidden sm:flex gap-2">
                                <label class="flex items-center gap-1.5 text-[10px] sm:text-xs cursor-pointer">
                                    <input type="checkbox" v-model="mostrarSoloHabilitados" 
                                           class="rounded border-gray-300 focus:ring-0 cursor-pointer"
                                           :style="{ accentColor: `var(--color-primary-600)` }">
                                    Solo habilitados
                                </label>
                                <button @click="seleccionarTodos" 
                                        class="text-[10px] sm:text-xs transition"
                                        :style="{ color: `var(--color-primary-600)` }">
                                    <i class="fas fa-check-square mr-1"></i> Seleccionar
                                </button>
                                <button @click="deseleccionarTodos" 
                                        class="text-[10px] sm:text-xs text-gray-500 hover:text-gray-700 transition">
                                    <i class="fas fa-square mr-1"></i> Deseleccionar
                                </button>
                            </div>
                            
                            <!-- Botón guardar (móvil) -->
                            <button @click="guardarAsignaciones" :disabled="guardando"
                                    class="flex-1 sm:flex-none px-3 sm:px-5 py-1.5 sm:py-2 text-white rounded-lg text-[10px] sm:text-sm font-medium transition disabled:opacity-50 flex items-center justify-center gap-1.5"
                                    :style="{ backgroundColor: `var(--color-primary-600)` }">
                                <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px] sm:text-sm"></i>
                                <i v-else class="fas fa-save text-[10px] sm:text-sm"></i>
                                {{ guardando ? 'Guardando...' : (isMobile ? 'Guardar' : 'Guardar Habilitaciones') }}
                            </button>
                        </div>
                    </div>

                    <!-- Filtros móvil (colapsable) -->
                    <div v-if="isMobile" 
                         class="transition-all duration-300 overflow-hidden"
                         :class="filtrosAbiertos ? 'max-h-40 opacity-100 mb-3' : 'max-h-0 opacity-0'">
                        <div class="bg-gray-50 rounded-lg p-3 border" :style="{ borderColor: `var(--color-primary-200)` }">
                            <div class="flex flex-col gap-2">
                                <label class="flex items-center gap-2 text-[11px] cursor-pointer">
                                    <input type="checkbox" v-model="mostrarSoloHabilitados" 
                                           class="rounded border-gray-300 focus:ring-0 cursor-pointer"
                                           :style="{ accentColor: `var(--color-primary-600)` }">
                                    Mostrar solo habilitados
                                </label>
                                <div class="flex gap-2">
                                    <button @click="seleccionarTodos" 
                                            class="flex-1 px-3 py-1.5 rounded-md text-[10px] transition"
                                            :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-600)` }">
                                        <i class="fas fa-check-square mr-1"></i> Seleccionar visibles
                                    </button>
                                    <button @click="deseleccionarTodos" 
                                            class="flex-1 px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-[10px] hover:bg-gray-300 transition">
                                        <i class="fas fa-square mr-1"></i> Deseleccionar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contador de seleccionados (móvil) -->
                    <div v-if="isMobile" class="text-[10px] text-gray-500 mb-2">
                        <span class="text-primary-600 font-medium">{{ totalSeleccionados }}</span> de {{ totalProductos }} productos habilitados
                    </div>

                    <!-- Lista de categorías con productos -->
                    <div class="max-h-[450px] sm:max-h-[550px] overflow-y-auto space-y-3 sm:space-y-4">
                        <div 
                            v-for="categoria in categoriasFiltradas" 
                            :key="categoria.id"
                            class="border rounded-lg overflow-hidden"
                            :style="{ borderColor: `var(--color-primary-200)` }"
                        >
                            <!-- Header de categoría -->
                            <div class="px-2 sm:px-3 py-1.5 sm:py-2 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-1 sm:gap-2"
                                 :style="{ backgroundColor: `var(--color-primary-50)`, borderColor: `var(--color-primary-200)` }">
                                <div class="flex items-center gap-1.5 sm:gap-2">
                                    <i class="fas fa-folder-open text-primary-600 text-[10px] sm:text-sm"
                                       :style="{ color: `var(--color-primary-600)` }"></i>
                                    <span class="text-[11px] sm:text-sm font-semibold" :style="{ color: `var(--color-primary-700)` }">
                                        {{ categoria.nombre }}
                                    </span>
                                </div>
                                <span class="text-[9px] sm:text-xs text-gray-500">
                                    {{ categoria.productos.filter(p => productosSeleccionados.includes(p.id)).length }}/{{ categoria.productos.length }} habilitados
                                </span>
                            </div>
                            
                            <!-- Grid de productos de la categoría -->
                            <div class="p-2 sm:p-3">
                                <div class="grid grid-cols-1 xs:grid-cols-2 lg:grid-cols-3 gap-1.5 sm:gap-2">
                                    <div 
                                        v-for="producto in categoria.productos"
                                        :key="producto.id"
                                        :class="[
                                            'flex items-center gap-1.5 sm:gap-3 p-1.5 sm:p-2 rounded-lg border cursor-pointer transition-all',
                                            estaSeleccionado(producto.id) 
                                                ? 'border-primary-400 bg-primary-50' 
                                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                                        ]"
                                        @click="toggleProducto(producto.id)"
                                    >
                                        <div class="w-4 h-4 sm:w-5 sm:h-5 rounded border flex items-center justify-center flex-shrink-0"
                                            :class="estaSeleccionado(producto.id) ? 'bg-primary-500 border-primary-500' : 'border-gray-300 bg-white'">
                                            <i v-if="estaSeleccionado(producto.id)" class="fas fa-check text-white text-[8px] sm:text-xs"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-[10px] sm:text-sm font-medium text-gray-800 truncate" :title="producto.nombre">
                                                {{ producto.nombre }}
                                            </div>
                                            <div class="text-[9px] sm:text-xs font-semibold" :style="{ color: `var(--color-primary-600)` }">
                                                {{ Number(producto.PrecioVenta).toFixed(2) }} Bs
                                            </div>
                                        </div>
                                        <div class="text-[8px] sm:text-xs text-gray-400 font-mono flex-shrink-0 hidden xs:block">#{{ producto.id }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mensaje sin resultados -->
                        <div v-if="categoriasFiltradas.length === 0" class="text-center text-gray-400 py-8 sm:py-12">
                            <i class="fas fa-box-open text-2xl sm:text-3xl mb-2 block"></i>
                            <p class="text-[10px] sm:text-sm" v-if="buscando">No hay productos que coincidan con "{{ buscando }}"</p>
                            <p class="text-[10px] sm:text-sm" v-else>No hay productos disponibles</p>
                        </div>
                    </div>

                    <!-- Botón guardar (mobile fixed) -->
                    <div v-if="isMobile" class="mt-3 pt-3 border-t flex justify-end sticky bottom-0 bg-white pb-2"
                         :style="{ borderColor: `var(--color-primary-200)` }">
                        <button @click="guardarAsignaciones" :disabled="guardando"
                                class="w-full px-4 py-2 text-white rounded-lg text-xs font-medium transition disabled:opacity-50 flex items-center justify-center gap-2"
                                :style="{ backgroundColor: `var(--color-primary-600)` }">
                            <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ guardando ? 'Guardando...' : `Guardar (${totalSeleccionados} habilitados)` }}
                        </button>
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

/* Scrollbar personalizada */
.max-h-\[450px\]::-webkit-scrollbar,
.max-h-\[550px\]::-webkit-scrollbar {
    width: 4px;
}

.max-h-\[450px\]::-webkit-scrollbar-track,
.max-h-\[550px\]::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.max-h-\[450px\]::-webkit-scrollbar-thumb,
.max-h-\[550px\]::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.max-h-\[450px\]::-webkit-scrollbar-thumb:hover,
.max-h-\[550px\]::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Estilos para pantallas muy pequeñas */
@media (min-width: 480px) {
    .xs\:grid-cols-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .xs\:block {
        display: block;
    }
}

@media (max-width: 479px) {
    .xs\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    .xs\:block {
        display: none;
    }
}

/* Scroll suave */
* {
    scroll-behavior: smooth;
}
</style>