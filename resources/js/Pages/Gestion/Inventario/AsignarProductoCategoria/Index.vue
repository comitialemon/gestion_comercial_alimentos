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

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const productosSeleccionados = ref([...(props.asignaciones || [])])
const buscando = ref('')
const guardando = ref(false)
const mostrarSoloHabilitados = ref(false)

// ==================== COMPUTED ====================
const totalSeleccionados = computed(() => productosSeleccionados.value.length)

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

// ==================== FUNCIONES ====================
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
                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-store text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Habilitar Productos para Sucursal</h1>
                        <p class="text-[10px] text-gray-500">Selecciona qué productos estarán disponibles en el menú táctil</p>
                    </div>
                </div>

                <!-- ==================== INFO SUCURSAL ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="bg-primary-50 rounded-lg px-2.5 py-1.5 flex items-center gap-1.5">
                                <i class="fas fa-map-marker-alt text-primary-600 text-[10px]"></i>
                                <span class="text-xs font-medium text-primary-700">{{ sucursalNombre }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-[10px]">
                            <span class="text-gray-500">Total: <strong class="text-gray-800">{{ totalProductos }}</strong></span>
                            <span class="text-gray-500">|</span>
                            <span class="text-gray-500">Habilitados: <strong class="text-primary-600">{{ totalSeleccionados }}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- ==================== FILTROS COMPACTOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Buscador -->
                        <div class="relative flex-1 min-w-[120px] max-w-[280px]">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                            <input 
                                type="text" 
                                v-model="buscando" 
                                placeholder="Buscar producto..."
                                class="w-full border border-gray-300 rounded-md pl-7 pr-6 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                            <button 
                                v-if="buscando"
                                @click="buscando = ''"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <i class="fas fa-times text-[10px]"></i>
                            </button>
                        </div>

                        <!-- Checkbox -->
                        <label class="flex items-center gap-1.5 text-[10px] text-gray-600 cursor-pointer whitespace-nowrap">
                            <input type="checkbox" v-model="mostrarSoloHabilitados" 
                                class="w-3.5 h-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                            <span>Solo habilitados</span>
                        </label>

                        <!-- Botones de selección -->
                        <div class="flex gap-1">
                            <button @click="seleccionarTodos" 
                                class="px-2.5 py-1 text-[10px] text-primary-600 hover:bg-primary-50 rounded transition flex items-center gap-1">
                                <i class="fas fa-check-square text-[9px]"></i> Seleccionar
                            </button>
                            <button @click="deseleccionarTodos" 
                                class="px-2.5 py-1 text-[10px] text-gray-500 hover:bg-gray-100 rounded transition flex items-center gap-1">
                                <i class="fas fa-square text-[9px]"></i> Deseleccionar
                            </button>
                        </div>

                        <!-- Botón guardar -->
                        <button @click="guardarAsignaciones" :disabled="guardando"
                            class="ml-auto px-3 py-1.5 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5 disabled:opacity-50"
                            :style="{ backgroundColor: `var(--color-primary-600)` }">
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            {{ guardando ? 'Guardando...' : 'Guardar' }}
                            <span class="bg-white/20 rounded-full px-1.5 py-0.5 text-[9px]">{{ totalSeleccionados }}</span>
                        </button>
                    </div>
                </div>

                <!-- ==================== LISTA DE CATEGORÍAS ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-y-auto" style="max-height: 65vh;">
                        <div class="p-3 space-y-3">
                            <div 
                                v-for="categoria in categoriasFiltradas" 
                                :key="categoria.id"
                                class="border border-gray-200 rounded-lg overflow-hidden"
                            >
                                <!-- Header de categoría -->
                                <div class="flex flex-wrap justify-between items-center px-3 py-1.5 bg-primary-50 border-b border-primary-100">
                                    <div class="flex items-center gap-1.5">
                                        <i class="fas fa-folder-open text-primary-600 text-[10px]"></i>
                                        <span class="text-xs font-semibold text-primary-700">{{ categoria.nombre }}</span>
                                    </div>
                                    <span class="text-[9px] text-gray-500">
                                        {{ categoria.productos.filter(p => productosSeleccionados.includes(p.id)).length }}/{{ categoria.productos.length }} habilitados
                                    </span>
                                </div>
                                
                                <!-- Grid de productos -->
                                <div class="p-2">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                                        <div 
                                            v-for="producto in categoria.productos"
                                            :key="producto.id"
                                            :class="[
                                                'flex items-center gap-2 p-1.5 rounded-md border cursor-pointer transition-all',
                                                estaSeleccionado(producto.id) 
                                                    ? 'border-primary-400 bg-primary-50' 
                                                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                                            ]"
                                            @click="toggleProducto(producto.id)"
                                        >
                                            <!-- Checkbox -->
                                            <div class="w-4 h-4 rounded border flex items-center justify-center flex-shrink-0"
                                                :class="estaSeleccionado(producto.id) ? 'bg-primary-500 border-primary-500' : 'border-gray-300 bg-white'">
                                                <i v-if="estaSeleccionado(producto.id)" class="fas fa-check text-white text-[8px]"></i>
                                            </div>
                                            
                                            <!-- Info -->
                                            <div class="flex-1 min-w-0">
                                                <div class="text-[11px] font-medium text-gray-800 truncate" :title="producto.nombre">
                                                    {{ producto.nombre }}
                                                </div>
                                                <div class="text-[10px] font-semibold text-primary-600">
                                                    {{ Number(producto.PrecioVenta).toFixed(2) }} Bs
                                                </div>
                                            </div>
                                            <div class="text-[8px] text-gray-400 font-mono flex-shrink-0 hidden sm:block">#{{ producto.id }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mensaje sin resultados -->
                            <div v-if="categoriasFiltradas.length === 0" class="text-center text-gray-400 py-10">
                                <i class="fas fa-box-open text-2xl mb-2 block"></i>
                                <p class="text-xs" v-if="buscando">No hay productos que coincidan con "{{ buscando }}"</p>
                                <p class="text-xs" v-else>No hay productos disponibles</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="mt-3 text-[8px] text-gray-400 text-center">
                    <i class="fas fa-info-circle"></i> Selecciona los productos que estarán disponibles en el menú táctil de esta sucursal
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

/* Scrollbar personalizada */
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