<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { debounce } from 'lodash'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Object,
    categorias: Array,
    grupos: Array,
    totalProductos: Number,
    totalConCategoria: Number,
    totalSinCategoria: Number,
    totalConImagen: Number,      // 🔥 NUEVO
    totalSinImagen: Number,      // 🔥 NUEVO
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

// Filtros reactivos
const search = ref(props.filtros?.search || '')
const categoria = ref(props.filtros?.categoria || '')
const estado = ref(props.filtros?.estado || '')

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/gestion/productos-venta/catalogo', {
        search: search.value || undefined,
        categoria: categoria.value || undefined,
        estado: estado.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

// Limpiar filtros
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

// Estado texto
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
        case 0: return 'bg-green-100 text-green-800'
        case 1: return 'bg-gray-100 text-gray-600'
        case 2: return 'bg-yellow-100 text-yellow-800'
        case 3: return 'bg-red-100 text-red-800'
        default: return 'bg-gray-100 text-gray-500'
    }
}

// Obtener URL de imagen
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
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-boxes text-primary-600 text-lg"></i>
                        </div>
                        <div>
                            <h1 class="text-lg lg:text-xl font-bold text-gray-800">Catálogo de Productos</h1>
                            <p class="text-xs text-gray-500">Todos los productos con su categoría asignada</p>
                        </div>
                    </div>
                    <Link href="/gestion/productos-venta/create" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm lg:text-[15px] font-medium flex items-center gap-2 transition w-full sm:w-auto justify-center">
                        <i class="fas fa-plus text-sm"></i> Nuevo Producto
                    </Link>
                </div>

                <!-- Tarjetas de estadísticas (5 tarjetas) -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
                    <!-- Total Productos -->
                    <div class="bg-white rounded-xl shadow-sm p-3 flex justify-between items-center">
                        <div>
                            <p class="text-xs text-gray-500">Total Productos</p>
                            <p class="text-xl lg:text-2xl font-bold text-gray-800">{{ totalProductos }}</p>
                        </div>
                        <i class="fas fa-boxes text-3xl text-primary-200"></i>
                    </div>
                    
                    <!-- Con Categoría -->
                    <div class="bg-white rounded-xl shadow-sm p-3 flex justify-between items-center">
                        <div>
                            <p class="text-xs text-gray-500">Con Categoría</p>
                            <p class="text-xl lg:text-2xl font-bold text-green-600">{{ totalConCategoria }}</p>
                        </div>
                        <i class="fas fa-tag text-3xl text-green-200"></i>
                    </div>
                    
                    <!-- Sin Categoría -->
                    <div class="bg-white rounded-xl shadow-sm p-3 flex justify-between items-center">
                        <div>
                            <p class="text-xs text-gray-500">Sin Categoría</p>
                            <p class="text-xl lg:text-2xl font-bold text-secondary-600">{{ totalSinCategoria }}</p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-3xl text-secondary-200"></i>
                    </div>
                    
                    <!-- 🔥 Con Imagen -->
                    <div class="bg-white rounded-xl shadow-sm p-3 flex justify-between items-center">
                        <div>
                            <p class="text-xs text-gray-500">Con Imagen</p>
                            <p class="text-xl lg:text-2xl font-bold text-blue-600">{{ totalConImagen }}</p>
                        </div>
                        <i class="fas fa-image text-3xl text-blue-200"></i>
                    </div>
                    
                    <!-- 🔥 Sin Imagen -->
                    <div class="bg-white rounded-xl shadow-sm p-3 flex justify-between items-center">
                        <div>
                            <p class="text-xs text-gray-500">Sin Imagen</p>
                            <p class="text-xl lg:text-2xl font-bold text-purple-600">{{ totalSinImagen }}</p>
                        </div>
                        <i class="fas fa-ban text-3xl text-purple-200"></i>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Buscar</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" v-model="search" placeholder="Código o nombre..." 
                                    class="w-full border border-gray-300 rounded-lg pl-10 pr-3 py-2 text-sm lg:text-[15px] focus:ring-primary-500 focus:border-primary-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Categoría</label>
                            <select v-model="categoria" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm lg:text-[15px] focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Todas las categorías</option>
                                <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado</label>
                            <select v-model="estado" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm lg:text-[15px] focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Todos</option>
                                <option value="0">Activo</option>
                                <option value="1">Borrador</option>
                                <option value="2">Pendiente de Aprobación</option>
                                <option value="3">Rechazado</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button @click="limpiarFiltros" class="px-4 py-2 border border-gray-300 rounded-lg text-sm lg:text-[15px] text-gray-700 hover:bg-gray-50 transition flex items-center gap-2">
                            <i class="fas fa-eraser text-sm"></i> Limpiar filtros
                        </button>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-3 space-y-3">
                            <div v-for="producto in productos.data" :key="producto.IdDetalleProducto" 
                                class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                <div class="flex gap-3">
                                    <!-- Imagen -->
                                    <div class="w-16 h-16 flex-shrink-0 rounded-lg overflow-hidden bg-gray-200">
                                        <img v-if="getImagenUrl(producto)" :src="getImagenUrl(producto)" class="w-full h-full object-cover" alt="Producto">
                                        <div v-else class="w-full h-full flex items-center justify-center">
                                            <i class="fas fa-box-open text-gray-400 text-xl"></i>
                                        </div>
                                    </div>
                                    <!-- Info -->
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="text-xs font-mono text-gray-500">{{ producto.Codigo }}</p>
                                                <p class="text-sm font-medium text-gray-800">{{ producto.Detalle }}</p>
                                            </div>
                                            <span class="px-2 py-0.5 text-[10px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                {{ estadoTexto(producto.ActivoInactivo) }}
                                            </span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <span v-if="producto.categoria" class="px-2 py-0.5 text-xs rounded-full bg-primary-100 text-primary-700">
                                                    <i class="fas fa-tag mr-1 text-[9px]"></i> {{ producto.categoria.nombre }}
                                                </span>
                                                <span v-else class="px-2 py-0.5 text-xs rounded-full bg-secondary-100 text-secondary-700">
                                                    <i class="fas fa-exclamation-triangle mr-1 text-[9px]"></i> Sin categoría
                                                </span>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-xs text-gray-400">Precio</p>
                                                <p class="text-sm font-bold text-primary-600">{{ Number(producto.PrecioVenta).toFixed(2) }} Bs</p>
                                            </div>
                                        </div>
                                        <div class="flex gap-3 mt-2 pt-2 border-t border-gray-200">
                                            <Link :href="`/gestion/productos-venta/${producto.IdDetalleProducto}/edit`" class="text-primary-600 hover:text-primary-800 text-sm" title="Editar">
                                                <i class="fas fa-edit"></i> Editar
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="productos.data.length === 0" class="text-center text-gray-400 py-10">
                                <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                No hay productos que coincidan con los filtros
                            </div>
                        </div>

                        <!-- VISTA TABLET (tabla compacta) -->
                        <div v-else-if="isTablet" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Imagen</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Código</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Producto</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Categoría</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Precio</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="producto in productos.data" :key="producto.IdDetalleProducto" class="hover:bg-gray-50">
                                        <td class="px-3 py-2">
                                            <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100">
                                                <img v-if="getImagenUrl(producto)" :src="getImagenUrl(producto)" class="w-full h-full object-cover" alt="Producto">
                                                <div v-else class="w-full h-full flex items-center justify-center">
                                                    <i class="fas fa-box-open text-gray-400 text-sm"></i>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-xs font-mono text-gray-600">{{ producto.Codigo }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-800 max-w-[180px] truncate">{{ producto.Detalle }}</td>
                                        <td class="px-3 py-2">
                                            <span v-if="producto.categoria" class="px-1.5 py-0.5 text-[10px] rounded-full bg-primary-100 text-primary-700">
                                                {{ producto.categoria.nombre }}
                                            </span>
                                            <span v-else class="px-1.5 py-0.5 text-[10px] rounded-full bg-secondary-100 text-secondary-700">
                                                Sin categoría
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold text-primary-600">{{ Number(producto.PrecioVenta).toFixed(2) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                {{ estadoTexto(producto.ActivoInactivo) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <Link :href="`/gestion/productos-venta/${producto.IdDetalleProducto}/edit`" class="text-primary-600 hover:text-primary-800" title="Editar">
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
                                        <th class="px-4 py-3 text-left text-sm font-medium text-primary-700 uppercase">Imagen</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-primary-700 uppercase">Código</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-primary-700 uppercase">Producto</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-primary-700 uppercase">Categoría</th>
                                        <th class="px-4 py-3 text-right text-sm font-medium text-primary-700 uppercase">Precio</th>
                                        <th class="px-4 py-3 text-center text-sm font-medium text-primary-700 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-center text-sm font-medium text-primary-700 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="producto in productos.data" :key="producto.IdDetalleProducto" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-3">
                                            <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100">
                                                <img v-if="getImagenUrl(producto)" :src="getImagenUrl(producto)" class="w-full h-full object-cover" alt="Producto">
                                                <div v-else class="w-full h-full flex items-center justify-center">
                                                    <i class="fas fa-box-open text-gray-400 text-xl"></i>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ producto.Codigo }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-800">{{ producto.Detalle }}</td>
                                        <td class="px-4 py-3">
                                            <span v-if="producto.categoria" class="px-2 py-0.5 text-xs rounded-full bg-primary-100 text-primary-700">
                                                <i class="fas fa-tag mr-1 text-[9px]"></i> {{ producto.categoria.nombre }}
                                            </span>
                                            <span v-else class="px-2 py-0.5 text-xs rounded-full bg-secondary-100 text-secondary-700">
                                                <i class="fas fa-exclamation-triangle mr-1 text-[9px]"></i> Sin categoría
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold text-primary-600">{{ Number(producto.PrecioVenta).toFixed(2) }} Bs</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2 py-0.5 text-xs rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                                {{ estadoTexto(producto.ActivoInactivo) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <Link :href="`/gestion/productos-venta/${producto.IdDetalleProducto}/edit`" class="text-primary-600 hover:text-primary-800" title="Editar">
                                                <i class="fas fa-edit text-base"></i>
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="productos.data.length === 0">
                                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                            <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                            No hay productos que coincidan con los filtros
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div v-if="productos.links && productos.links.length > 1" class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ productos.from || 0 }} a {{ productos.to || 0 }} de {{ productos.total || 0 }} resultados
                            </div>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link v-for="link in productos.links" :key="link.label" :href="link.url || '#'" 
                                    class="px-3 py-1.5 rounded-lg border text-sm transition"
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
/* Asegurar que los textos sean legibles en PC */
@media (min-width: 1024px) {
    .text-sm {
        font-size: 14px !important;
    }
}
</style>