<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { debounce } from 'lodash'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Object,
    categorias: Array,
    grupos: Array,
    totalProductos: Number,
    totalConCategoria: Number,
    totalSinCategoria: Number,
    totalConImagen: Number,
    totalSinImagen: Number,
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

// ==================== FILTROS ====================
const search = ref(props.filtros?.search || '')
const categoria = ref(props.filtros?.categoria || '')
const estado = ref(props.filtros?.estado || '')

// ==================== COMPUTED ====================
const hayFiltrosAplicados = computed(() => {
    return search.value || categoria.value || estado.value
})

// ==================== MÉTODOS ====================
const aplicarFiltros = () => {
    router.get('/gestion/inventario/productos-venta/catalogo', {
        search: search.value || undefined,
        categoria: categoria.value || undefined,
        estado: estado.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltros = () => {
    search.value = ''
    categoria.value = ''
    estado.value = ''
    aplicarFiltros()
}

// Debounce para búsqueda
const debouncedSearch = debounce(() => {
    aplicarFiltros()
}, 500)

watch(search, () => {
    debouncedSearch()
})

watch([categoria, estado], () => {
    aplicarFiltros()
})

const estadoTexto = (activo) => {
    switch(activo) {
        case 0: return 'Activo'
        case 1: return 'Borrador'
        case 2: return 'Pendiente'
        case 3: return 'Rechazado'
        default: return 'Desconocido'
    }
}

const estadoClase = (activo) => {
    switch(activo) {
        case 0: return 'bg-emerald-100 text-emerald-700'
        case 1: return 'bg-gray-100 text-gray-600'
        case 2: return 'bg-yellow-100 text-yellow-800'
        case 3: return 'bg-red-100 text-red-800'
        default: return 'bg-gray-100 text-gray-500'
    }
}

const getImagenUrl = (producto) => {
    if (producto.imagen_url) {
        return producto.imagen_url
    }
    if (producto.ImagenProducto) {
        if (producto.ImagenProducto.startsWith('http') || producto.ImagenProducto.startsWith('/storage')) {
            return producto.ImagenProducto
        }
        return '/storage/' + producto.ImagenProducto.replace(/^\/+/, '')
    }
    return null
}

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    debouncedSearch.cancel()
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-boxes text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Catálogo de Productos</h1>
                            <p class="text-[10px] text-gray-500">Todos los productos con su categoría asignada</p>
                        </div>
                    </div>
                    <Link href="/gestion/inventario/productos-venta/create" 
                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition whitespace-nowrap">
                        <i class="fas fa-plus text-[10px]"></i> Nuevo Producto
                    </Link>
                </div>

                <!-- ==================== TARJETAS DE ESTADÍSTICAS (5 tarjetas) ==================== -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 mb-4">
                    <div class="bg-white rounded-xl shadow-sm p-2.5 flex justify-between items-center">
                        <div>
                            <p class="text-[8px] text-gray-500 uppercase tracking-wide">Total</p>
                            <p class="text-base lg:text-lg font-bold text-gray-800">{{ totalProductos }}</p>
                        </div>
                        <i class="fas fa-boxes text-2xl text-primary-200"></i>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-2.5 flex justify-between items-center">
                        <div>
                            <p class="text-[8px] text-gray-500 uppercase tracking-wide">Con Categoría</p>
                            <p class="text-base lg:text-lg font-bold text-emerald-600">{{ totalConCategoria }}</p>
                        </div>
                        <i class="fas fa-tag text-2xl text-emerald-200"></i>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-2.5 flex justify-between items-center">
                        <div>
                            <p class="text-[8px] text-gray-500 uppercase tracking-wide">Sin Categoría</p>
                            <p class="text-base lg:text-lg font-bold text-orange-600">{{ totalSinCategoria }}</p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-2xl text-orange-200"></i>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-2.5 flex justify-between items-center">
                        <div>
                            <p class="text-[8px] text-gray-500 uppercase tracking-wide">Con Imagen</p>
                            <p class="text-base lg:text-lg font-bold text-blue-600">{{ totalConImagen }}</p>
                        </div>
                        <i class="fas fa-image text-2xl text-blue-200"></i>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-2.5 flex justify-between items-center">
                        <div>
                            <p class="text-[8px] text-gray-500 uppercase tracking-wide">Sin Imagen</p>
                            <p class="text-base lg:text-lg font-bold text-purple-600">{{ totalSinImagen }}</p>
                        </div>
                        <i class="fas fa-ban text-2xl text-purple-200"></i>
                    </div>
                </div>

                <!-- ==================== FILTROS COMPACTOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Buscar -->
                        <div class="flex items-center gap-1 flex-1 min-w-[120px] max-w-[240px]">
                            <i class="fas fa-search text-gray-400 text-[10px]"></i>
                            <input type="text" v-model="search" placeholder="Código o nombre..." 
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                        </div>

                        <!-- Categoría -->
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Categoría:</label>
                            <select v-model="categoria" class="w-36 border border-gray-300 rounded-md px-2 py-1 text-sm">
                                <option value="">Todas</option>
                                <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                            </select>
                        </div>

                        <!-- Estado -->
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Estado:</label>
                            <select v-model="estado" class="w-36 border border-gray-300 rounded-md px-2 py-1 text-sm">
                                <option value="">Todos</option>
                                <option value="0">Activo</option>
                                <option value="1">Borrador</option>
                                <option value="2">Pendiente</option>
                                <option value="3">Rechazado</option>
                            </select>
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-1.5 ml-auto">
                            <button @click="limpiarFiltros" 
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-eraser text-[10px]"></i>
                                <span>Limpiar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== TABLA DE PRODUCTOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="producto in productos.data" :key="producto.IdDetalleProducto" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex gap-2.5">
                                    <!-- Imagen -->
                                    <div class="w-14 h-14 flex-shrink-0 rounded-lg overflow-hidden bg-gray-200">
                                        <img v-if="getImagenUrl(producto)" :src="getImagenUrl(producto)" class="w-full h-full object-cover" alt="Producto">
                                        <div v-else class="w-full h-full flex items-center justify-center">
                                            <i class="fas fa-box-open text-gray-400 text-lg"></i>
                                        </div>
                                    </div>
                                    <!-- Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start gap-1">
                                            <div class="min-w-0">
                                                <p class="text-[10px] font-mono text-gray-500">{{ producto.Codigo }}</p>
                                                <p class="text-xs font-medium text-gray-800 truncate">{{ producto.Detalle }}</p>
                                            </div>
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full whitespace-nowrap" :class="estadoClase(producto.ActivoInactivo)">
                                                {{ estadoTexto(producto.ActivoInactivo) }}
                                            </span>
                                        </div>
                                        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-1">
                                            <div>
                                                <span v-if="producto.categoria" class="px-1.5 py-0.5 text-[9px] rounded-full bg-primary-100 text-primary-700">
                                                    {{ producto.categoria.nombre }}
                                                </span>
                                                <span v-else class="px-1.5 py-0.5 text-[9px] rounded-full bg-orange-100 text-orange-700">
                                                    Sin categoría
                                                </span>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-[8px] text-gray-400">Precio</p>
                                                <p class="text-xs font-bold text-primary-600">{{ Number(producto.PrecioVenta).toFixed(2) }} Bs</p>
                                            </div>
                                        </div>
                                        <div class="mt-1.5 pt-1.5 border-t border-gray-200">
                                            <Link :href="`/gestion/inventario/productos-venta/${producto.IdDetalleProducto}/edit`" 
                                                class="text-primary-600 hover:text-primary-800 text-[10px] font-medium flex items-center gap-1">
                                                <i class="fas fa-edit text-[9px]"></i> Editar
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="productos.data.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-box-open text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay productos que coincidan con los filtros</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET (tabla compacta) -->
                        <div v-else-if="isTablet" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Imagen</th>
                                        <th class="px-2 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Código</th>
                                        <th class="px-2 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Producto</th>
                                        <th class="px-2 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Categoría</th>
                                        <th class="px-2 py-1.5 text-right text-[9px] font-medium text-primary-700 uppercase">Precio</th>
                                        <th class="px-2 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase">Estado</th>
                                        <th class="px-2 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase w-10">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="producto in productos.data" :key="producto.IdDetalleProducto" class="hover:bg-gray-50">
                                        <td class="px-2 py-1.5">
                                            <div class="w-8 h-8 rounded-lg overflow-hidden bg-gray-100">
                                                <img v-if="getImagenUrl(producto)" :src="getImagenUrl(producto)" class="w-full h-full object-cover" alt="Producto">
                                                <div v-else class="w-full h-full flex items-center justify-center">
                                                    <i class="fas fa-box-open text-gray-400 text-[10px]"></i>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5 text-[10px] font-mono text-gray-600">{{ producto.Codigo }}</td>
                                        <td class="px-2 py-1.5 text-[10px] text-gray-800 max-w-[150px] truncate">{{ producto.Detalle }}</td>
                                        <td class="px-2 py-1.5">
                                            <span v-if="producto.categoria" class="px-1.5 py-0.5 text-[8px] rounded-full bg-primary-100 text-primary-700">
                                                {{ producto.categoria.nombre }}
                                            </span>
                                            <span v-else class="px-1.5 py-0.5 text-[8px] rounded-full bg-orange-100 text-orange-700">
                                                Sin categoría
                                            </span>
                                        </td>
                                        <td class="px-2 py-1.5 text-right text-[10px] font-semibold text-primary-600">{{ Number(producto.PrecioVenta).toFixed(2) }}</td>
                                        <td class="px-2 py-1.5 text-center">
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                {{ estadoTexto(producto.ActivoInactivo) }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-1.5 text-center">
                                            <Link :href="`/gestion/inventario/productos-venta/${producto.IdDetalleProducto}/edit`" 
                                                class="text-primary-600 hover:text-primary-800 text-xs" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- VISTA ESCRITORIO (tabla completa) -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">Imagen</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">Código</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">Producto</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">Categoría</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-medium text-primary-700 uppercase w-24">Precio</th>
                                        <th class="px-3 py-2 text-center text-[10px] font-medium text-primary-700 uppercase w-24">Estado</th>
                                        <th class="px-3 py-2 text-center text-[10px] font-medium text-primary-700 uppercase w-12">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="producto in productos.data" :key="producto.IdDetalleProducto" class="hover:bg-gray-50 transition">
                                        <td class="px-3 py-2">
                                            <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100">
                                                <img v-if="getImagenUrl(producto)" :src="getImagenUrl(producto)" class="w-full h-full object-cover" alt="Producto">
                                                <div v-else class="w-full h-full flex items-center justify-center">
                                                    <i class="fas fa-box-open text-gray-400 text-sm"></i>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-xs font-mono text-gray-600">{{ producto.Codigo }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-800 max-w-[250px] truncate">{{ producto.Detalle }}</td>
                                        <td class="px-3 py-2">
                                            <span v-if="producto.categoria" class="px-1.5 py-0.5 text-[9px] rounded-full bg-primary-100 text-primary-700">
                                                {{ producto.categoria.nombre }}
                                            </span>
                                            <span v-else class="px-1.5 py-0.5 text-[9px] rounded-full bg-orange-100 text-orange-700">
                                                Sin categoría
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold text-primary-600">{{ Number(producto.PrecioVenta).toFixed(2) }} Bs</td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                {{ estadoTexto(producto.ActivoInactivo) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <Link :href="`/gestion/inventario/productos-venta/${producto.IdDetalleProducto}/edit`" 
                                                class="text-primary-600 hover:text-primary-800 text-xs" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="productos.data.length === 0">
                                        <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                                            <i class="fas fa-box-open text-2xl mb-1 block"></i>
                                            No hay productos que coincidan con los filtros
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ==================== PAGINACIÓN ==================== -->
                    <div v-if="productos.links && productos.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <div class="text-[10px] text-gray-500">
                                Mostrando {{ productos.from || 0 }} a {{ productos.to || 0 }} de {{ productos.total || 0 }} resultados
                            </div>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link v-for="link in productos.links" :key="link.label" :href="link.url || '#'" 
                                    class="px-2.5 py-1 rounded-lg border text-[10px] transition"
                                    :class="{
                                        'bg-primary-600 text-white border-primary-600': link.active,
                                        'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url,
                                        'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url
                                    }"
                                    v-html="link.label" />
                            </div>
                        </div>
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
</style>