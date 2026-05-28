<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Object,
    categorias: Array,  // 🔥 Recibir categorías desde el controlador
    totalActivos: Number,
    totalInactivos: Number,
    filtros: Object,
})

// Filtros Reactivos
const search = ref(props.filtros?.search || '')
const estado = ref(props.filtros?.estado || '')
const categoriasSeleccionadas = ref([])  // 🔥 Array de IDs de categorías seleccionadas

// Procesar categorías seleccionadas desde los filtros al cargar
onMounted(() => {
    if (props.filtros?.categorias) {
        const queryCategorias = props.filtros.categorias;
        if (typeof queryCategorias === 'string' && queryCategorias.trim() !== '') {
            categoriasSeleccionadas.value = queryCategorias.split(',').map(Number);
        } else if (Array.isArray(queryCategorias)) {
            categoriasSeleccionadas.value = queryCategorias.map(Number);
        }
    }
})

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
const aplicarFiltros = () => {
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

    router.get('/gestion/productos-venta', params, {
        preserveState: true,
        replace: true,
    })
}

// Limpiar todo
const limpiarFiltros = () => {
    search.value = ''
    estado.value = ''
    categoriasSeleccionadas.value = []
    
    router.get('/gestion/productos-venta', {}, {
        preserveState: true,
        replace: true,
    })
}

// Ver detalle de aprobación
const verAprobacion = (id) => {
    router.get(`/gestion/productos-aprobacion/ver/${id}`)
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
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-boxes text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">Productos de Venta</h1>
                            <p class="text-[10px] text-gray-500">Gestión de productos para punto de venta</p>
                        </div>
                    </div>
                    <Link href="/gestion/productos-venta/create" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center gap-1 transition">
                        <i class="fas fa-plus text-[10px]"></i> Nuevo Producto
                    </Link>
                </div>

                <!-- Layout Principal -->
                <div class="flex flex-row gap-4">
                    <!-- FILTROS -->
                    <div class="w-64 flex-shrink-0">
                        <div class="bg-white rounded-lg shadow-sm p-3 sticky top-24">
                            <h3 class="text-xs font-semibold text-gray-800 mb-3 flex items-center gap-1">
                                <i class="fas fa-filter text-primary-600 text-[10px]"></i> Filtros
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
                                        class="w-full border rounded-md pl-7 pr-2 py-1.5 text-[11px]"
                                        @keyup.enter="aplicarFiltros"
                                    >
                                </div>
                            </div>

                            <!-- Estado -->
                            <div class="mb-3">
                                <label class="block text-[10px] font-medium text-gray-700 mb-1">Estado</label>
                                <div class="flex flex-col gap-1">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="" v-model="estado" class="w-3 h-3 text-primary-600"> 
                                        <span class="text-[11px] text-gray-700">Todos</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="0" v-model="estado" class="w-3 h-3 text-primary-600"> 
                                        <span class="text-[11px] text-gray-700">Activos ({{ totalActivos }})</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="1" v-model="estado" class="w-3 h-3 text-primary-600"> 
                                        <span class="text-[11px] text-gray-700">Inactivos ({{ totalInactivos }})</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="2" v-model="estado" class="w-3 h-3 text-primary-600"> 
                                        <span class="text-[11px] text-gray-700">Pendientes</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" value="3" v-model="estado" class="w-3 h-3 text-primary-600"> 
                                        <span class="text-[11px] text-gray-700">Rechazados</span>
                                    </label>
                                </div>
                            </div>

                            <!-- 🔥 LISTA DE CATEGORÍAS (Checkboxes) -->
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[10px] font-medium text-gray-700">Categorías</label>
                                    <span v-if="categoriasSeleccionadas.length > 0" class="text-[9px] text-primary-600 font-bold">
                                        {{ categoriasSeleccionadas.length }} sel.
                                    </span>
                                </div>
                                <div class="max-h-48 overflow-y-auto border rounded-md bg-white">
                                    <div 
                                        v-for="(categoria, index) in categorias" 
                                        :key="getCategoriaId(categoria) || index" 
                                        class="flex items-center justify-between px-2 py-1.5 hover:bg-gray-50 border-b border-gray-100 last:border-b-0"
                                    >
                                        <label class="flex items-center gap-1.5 flex-1 min-w-0 cursor-pointer select-none py-0.5">
                                            <input 
                                                type="checkbox" 
                                                :checked="isCategoriaSelected(categoria)" 
                                                @change="toggleCategoria(categoria)"
                                                class="w-3 h-3 rounded border-gray-300 text-primary-600 focus:ring-0 cursor-pointer"
                                            >
                                            <span class="text-[11px] text-gray-700 truncate">
                                                {{ getCategoriaNombre(categoria) }}
                                            </span>
                                        </label>
                                        <span class="text-[9px] text-gray-400 pl-1 pr-1">
                                            ({{ categoria.productos_count || 0 }})
                                        </span>
                                    </div>
                                    <div v-if="!categorias || categorias.length === 0" class="px-2 py-3 text-center text-gray-400 text-[10px]">
                                        No hay categorías disponibles
                                    </div>
                                </div>
                            </div>

                            <!-- Botonera -->
                            <div class="flex gap-2 pt-2 border-t">
                                <button @click="aplicarFiltros" class="flex-1 px-2 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-[10px] transition flex items-center justify-center gap-1">
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
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-primary-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Código</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Producto</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Grupo</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Categoría</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Precio</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Estado</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Aprobación</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="producto in productos.data" :key="producto.IdDetalleProducto" class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-[11px] text-gray-600 font-mono">{{ producto.Codigo }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-800">{{ producto.Detalle }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-500">
                                                {{ producto.grupo?.Detalle || producto.grupo?.nombre || 'Sin Grupo' }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="getCategoriaClase(producto)">
                                                    <i v-if="producto.categoria" class="fas fa-tag mr-1 text-[8px]"></i>
                                                    <i v-else class="fas fa-question-circle mr-1 text-[8px]"></i>
                                                    {{ getCategoriaNombreProducto(producto) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-right font-semibold text-primary-600">
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
                                                <button v-else-if="producto.ActivoInactivo === 0" @click="verAprobacion(producto.IdDetalleProducto)" class="text-primary-600 hover:text-primary-800" title="Ver aprobación">
                                                    <i class="fas fa-check-circle text-xs"></i>
                                                </button>
                                                <span v-else class="text-gray-400 text-[9px]">-</span>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <Link :href="`/gestion/productos-venta/${producto.IdDetalleProducto}/edit`" class="text-primary-600 hover:text-primary-800 text-[11px]">
                                                    <i class="fas fa-edit"></i>
                                                </Link>
                                            </td>
                                        </tr>
                                        <tr v-if="!productos.data || productos.data.length === 0">
                                            <td colspan="8" class="px-3 py-8 text-center text-gray-400 text-[11px]">
                                                <i class="fas fa-box-open text-xl mb-1 block"></i>
                                                No se encontraron productos con los filtros seleccionados.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginación -->
                            <div v-if="productos.links && productos.links.length > 1" class="px-3 py-2 border-t border-gray-200">
                                <div class="flex justify-between items-center flex-wrap gap-2">
                                    <div class="text-[9px] text-gray-500">
                                        Mostrando {{ productos.from || 0 }} a {{ productos.to || 0 }} de {{ productos.total || 0 }}
                                    </div>
                                    <div class="flex gap-1 flex-wrap">
                                        <Link 
                                            v-for="link in productos.links" 
                                            :key="link.label" 
                                            :href="link.url || '#'" 
                                            class="px-2 py-0.5 rounded border text-[9px] transition"
                                            :class="{ 
                                                'bg-primary-600 text-white border-primary-600': link.active, 
                                                'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 
                                                'opacity-50 cursor-not-allowed': !link.url 
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