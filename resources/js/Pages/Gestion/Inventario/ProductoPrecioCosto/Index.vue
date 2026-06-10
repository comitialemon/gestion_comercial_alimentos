<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Object,
    lineas: Array,           // 🔥 Líneas para filtrar (en lugar de categorías)
    totalActivos: Number,
    totalInactivos: Number,
    filtros: Object,
})

// Filtros Reactivos
const search = ref(props.filtros?.search || '')
const estado = ref(props.filtros?.estado || '')
const lineaId = ref(props.filtros?.linea_id || '')

// Aplicar filtros a la URL
const aplicarFiltros = () => {
    const params = {}

    if (search.value && search.value.trim() !== '') {
        params.search = search.value;
    }
    
    if (estado.value !== undefined && estado.value !== null && estado.value !== '') {
        params.estado = estado.value;
    }
    
    if (lineaId.value) {
        params.linea_id = lineaId.value;
    }

    router.get('/gestion/inventario/precio-costo', params, {
        preserveState: true,
        replace: true,
    })
}

// Limpiar todo
const limpiarFiltros = () => {
    search.value = ''
    estado.value = ''
    lineaId.value = ''
    
    router.get('/gestion/inventario/precio-costo', {}, {
        preserveState: true,
        replace: true,
    })
}

// Estado del producto
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

// Debounce para búsqueda
let timeout
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        aplicarFiltros()
    }, 500)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">Precio Costo de Productos</h1>
                            <p class="text-[10px] text-gray-500">Último precio costo registrado por producto</p>
                        </div>
                    </div>
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
                                </div>
                            </div>

                            <!-- 🔥 LISTA DE LÍNEAS (en lugar de categorías) -->
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[10px] font-medium text-gray-700">Líneas</label>
                                    <span v-if="lineaId" class="text-[9px] text-primary-600 font-bold">
                                        Filtro activo
                                    </span>
                                </div>
                                <div class="max-h-48 overflow-y-auto border rounded-md bg-white">
                                    <div 
                                        v-for="(linea, index) in lineas" 
                                        :key="linea.id || index" 
                                        class="flex items-center justify-between px-2 py-1.5 hover:bg-gray-50 border-b border-gray-100 last:border-b-0"
                                    >
                                        <label class="flex items-center gap-1.5 flex-1 min-w-0 cursor-pointer select-none py-0.5">
                                            <input 
                                                type="radio" 
                                                :value="linea.id"
                                                v-model="lineaId"
                                                @change="aplicarFiltros"
                                                class="w-3 h-3 rounded border-gray-300 text-primary-600 focus:ring-0 cursor-pointer"
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
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Línea</th>
                                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Unidad</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Precio Lista</th>
                                            <th class="px-3 py-2 text-right text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Precio Costo</th>
                                            <th class="px-3 py-2 text-center text-[10px] font-semibold text-primary-700 uppercase tracking-wider">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="producto in productos.data" :key="producto.IdProducto" class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-[11px] text-gray-600 font-mono">{{ producto.Codigo }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-800">{{ producto.Descripcion }}</td>
                                            <td class="px-3 py-2 text-[11px] text-gray-500">
                                                {{ producto.linea?.Linea || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-gray-500">
                                                {{ producto.unidad_medida?.UnidadMedida || '-' }}
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-right font-semibold text-gray-700">
                                                {{ Number(producto.precio_lista || 0).toFixed(2) }} Bs
                                            </td>
                                            <td class="px-3 py-2 text-[11px] text-right">
                                                <span v-if="producto.ultimo_precio_costo > 0" class="font-semibold text-blue-600">
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