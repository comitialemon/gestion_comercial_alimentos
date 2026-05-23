<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { debounce } from 'lodash'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Object,
    categorias: Array,
    grupos: Array,
    totalProductos: Number,
    totalConCategoria: Number,
    totalSinCategoria: Number,
    filtros: Object,
})

// Filtros reactivos
const search = ref(props.filtros?.search || '')
const categoria = ref(props.filtros?.categoria || '')
const grupo = ref(props.filtros?.grupo || '')
const estado = ref(props.filtros?.estado || '')

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/gestion/productos-venta/catalogo', {
        search: search.value || undefined,
        categoria: categoria.value || undefined,
        grupo: grupo.value || undefined,
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
    grupo.value = ''
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

watch([categoria, grupo, estado], () => {
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
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-boxes text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Catálogo de Productos</h1>
                            <p class="text-sm text-gray-500">Todos los productos con su categoría asignada</p>
                        </div>
                    </div>
                    <Link href="/gestion/productos-venta/create" class="bg-guindo-600 hover:bg-guindo-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                        <i class="fas fa-plus"></i> Nuevo Producto
                    </Link>
                </div>

                <!-- Estadísticas -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-3 flex justify-between items-center">
                        <div>
                            <p class="text-xs text-gray-500">Total Productos</p>
                            <p class="text-2xl font-bold text-gray-800">{{ totalProductos }}</p>
                        </div>
                        <i class="fas fa-boxes text-3xl text-guindo-200"></i>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-3 flex justify-between items-center">
                        <div>
                            <p class="text-xs text-gray-500">Con Categoría</p>
                            <p class="text-2xl font-bold text-green-600">{{ totalConCategoria }}</p>
                        </div>
                        <i class="fas fa-tag text-3xl text-green-200"></i>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-3 flex justify-between items-center">
                        <div>
                            <p class="text-xs text-gray-500">Sin Categoría</p>
                            <p class="text-2xl font-bold text-amber-600">{{ totalSinCategoria }}</p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-3xl text-amber-200"></i>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" v-model="search" placeholder="Código o nombre..." class="w-full border rounded-lg pl-10 pr-3 py-2 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Categoría</label>
                            <select v-model="categoria" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todas las categorías</option>
                                <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Grupo</label>
                            <select v-model="grupo" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todos los grupos</option>
                                <option v-for="g in grupos" :key="g.id" :value="g.id">{{ g.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Estado</label>
                            <select v-model="estado" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todos</option>
                                <option value="0">Activo</option>
                                <option value="1">Borrador</option>
                                <option value="2">Pendiente de Aprobación</option>
                                <option value="3">Rechazado</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button @click="limpiarFiltros" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition">
                                <i class="fas fa-eraser mr-1"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-guindo-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Código</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Producto</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Grupo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Categoría</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Precio</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-guindo-700 uppercase">Estado</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="producto in productos.data" :key="producto.IdDetalleProducto" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ producto.Codigo }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-800">{{ producto.Detalle }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ producto.grupo?.Detalle || '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span v-if="producto.categoria" class="px-2 py-0.5 text-xs rounded-full bg-guindo-100 text-guindo-700">
                                            <i class="fas fa-tag mr-1 text-[9px]"></i> {{ producto.categoria.nombre }}
                                        </span>
                                        <span v-else class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700">
                                            <i class="fas fa-exclamation-triangle mr-1 text-[9px]"></i> Sin categoría
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-guindo-600">{{ Number(producto.PrecioVenta).toFixed(2) }} Bs</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 text-xs rounded-full" :class="estadoClase(producto.ActivoInactivo)">
                                            {{ estadoTexto(producto.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <Link :href="`/gestion/productos-venta/${producto.IdDetalleProducto}/edit`" class="text-guindo-600 hover:text-guindo-800" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </Link>
                                        <Link :href="`/gestion/inventario/asignar-productos-categoria?producto=${producto.IdDetalleProducto}`" class="text-emerald-600 hover:text-emerald-800" title="Habilitar en sucursal">
                                            <i class="fas fa-store"></i>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="productos.data.length === 0">
                                    <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                        <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                        No hay productos que coincidan con los filtros
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="productos.links && productos.links.length > 1" class="px-4 py-3 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ productos.from || 0 }} a {{ productos.to || 0 }} de {{ productos.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link v-for="link in productos.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border text-sm" :class="{ 'bg-guindo-600 text-white border-guindo-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>