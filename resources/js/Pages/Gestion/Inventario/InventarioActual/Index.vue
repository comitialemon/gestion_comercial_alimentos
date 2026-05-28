<script setup>
import { ref, computed, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Object,
    almacenes: Array,
    almacenSeleccionado: Number,
    lineas: Array,
    grupos: Array,
    filtros: Object
})

// Filtros
const search = ref(props.filtros?.search || '')
const lineaId = ref(props.filtros?.linea_id || '')
const grupoId = ref(props.filtros?.grupo_id || '')
const almacenId = ref(props.almacenSeleccionado || '')

// Estado del modal de movimientos
const modalVisible = ref(false)
const productoSeleccionado = ref(null)
const movimientos = ref([])
const cargandoMovimientos = ref(false)

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/gestion/inventario/inventario-actual', {
        search: search.value || undefined,
        linea_id: lineaId.value || undefined,
        grupo_id: grupoId.value || undefined,
        almacen_id: almacenId.value || undefined
    }, { preserveState: true, replace: true })
}

// Limpiar filtros
const limpiarFiltros = () => {
    search.value = ''
    lineaId.value = ''
    grupoId.value = ''
    almacenId.value = props.almacenes?.[0]?.id || ''
    aplicarFiltros()
}

// Cambiar almacén
const cambiarAlmacen = () => {
    aplicarFiltros()
}

// Ver movimientos de un producto
const verMovimientos = async (producto) => {
    productoSeleccionado.value = producto
    modalVisible.value = true
    cargandoMovimientos.value = true
    
    try {
        const response = await axios.get(`/api/inventario/movimientos/${producto.IdProducto}`, {
            params: { almacen_id: almacenId.value }
        })
        movimientos.value = response.data.movimientos || []
    } catch (error) {
        console.error('Error cargando movimientos:', error)
        movimientos.value = []
    } finally {
        cargandoMovimientos.value = false
    }
}

// Clase para el color del stock
const getStockClass = (stock) => {
    const stockNum = Number(stock) || 0
    if (stockNum <= 0) return 'text-red-600 font-bold'
    if (stockNum < 10) return 'text-secondary-600 font-semibold'
    return 'text-emerald-600'
}

// Obtener nombre de línea con seguro
const getLineaNombre = (linea) => {
    return linea?.Linea || linea?.nombre || '-'
}

// Obtener nombre de grupo con seguro
const getGrupoNombre = (grupo) => {
    return grupo?.Grupo || grupo?.nombre || '-'
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
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-100 rounded-2xl mb-3">
                        <i class="fas fa-boxes text-xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Inventario Actual</h1>
                    <p class="text-xs text-gray-500">Stock disponible por producto</p>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Almacén</label>
                            <select v-model="almacenId" @change="cambiarAlmacen" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option v-for="alm in almacenes" :key="alm.id" :value="alm.id">
                                    {{ alm.nombre }} {{ alm.principal ? '(Principal)' : '' }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Línea</label>
                            <select v-model="lineaId" @change="aplicarFiltros" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todas las líneas</option>
                                <option v-for="linea in lineas" :key="linea.id" :value="linea.id">{{ linea.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Grupo</label>
                            <select v-model="grupoId" @change="aplicarFiltros" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todos los grupos</option>
                                <option v-for="grupo in grupos" :key="grupo.id" :value="grupo.id">{{ grupo.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                                <input type="text" v-model="search" placeholder="Código o descripción..." 
                                    class="w-full border rounded-lg pl-10 pr-3 py-2 text-sm">
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button @click="limpiarFiltros" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm">
                            <i class="fas fa-eraser mr-1"></i> Limpiar filtros
                        </button>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Línea/Grupo</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Unidad</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Entradas</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Salidas</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Stock Actual</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Movimientos</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in productos.data" :key="item.IdProducto" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ item.Codigo || '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">{{ item.Descripcion || 'Sin descripción' }}</p>
                                            <p class="text-xs text-gray-400 mt-1">ID: {{ item.IdProducto }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ getLineaNombre(item.linea) }} / {{ getGrupoNombre(item.grupo) }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-500">{{ item.unidad_medida?.UnidadMedida || '-' }}</td>
                                    <td class="px-4 py-3 text-center text-sm text-emerald-600 font-medium">{{ Number(item.stock_entradas || 0).toFixed(2) }}</td>
                                    <td class="px-4 py-3 text-center text-sm text-red-500 font-medium">{{ Number(item.stock_salidas || 0).toFixed(2) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-base font-bold" :class="getStockClass(item.stock_actual)">
                                            {{ Number(item.stock_actual || 0).toFixed(2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button @click="verMovimientos(item)" class="text-indigo-600 hover:text-indigo-800" title="Ver movimientos">
                                            <i class="fas fa-chart-line"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!productos.data || productos.data.length === 0">
                                    <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                        No hay productos que coincidan con la búsqueda
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="productos.links && productos.links.length > 1" class="px-4 py-3 border-t">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ productos.from || 0 }} a {{ productos.to || 0 }} de {{ productos.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link v-for="link in productos.links" :key="link.label" :href="link.url || '#'" 
                                    class="px-3 py-1 rounded border text-sm"
                                    :class="{
                                        'bg-indigo-600 text-white border-indigo-600': link.active,
                                        'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url,
                                        'opacity-50 cursor-not-allowed': !link.url
                                    }"
                                    v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de movimientos -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="modalVisible = false">
            <div class="bg-white rounded-xl max-w-3xl w-full max-h-[80vh] overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 p-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-white font-bold">Movimientos de Inventario</h3>
                        <p class="text-white text-sm opacity-90">{{ productoSeleccionado?.Descripcion || 'Producto' }}</p>
                    </div>
                    <button @click="modalVisible = false" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="overflow-y-auto max-h-[60vh] p-4">
                    <div v-if="cargandoMovimientos" class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl text-indigo-600"></i>
                        <p class="mt-2 text-gray-500">Cargando movimientos...</p>
                    </div>
                    <div v-else-if="movimientos.length === 0" class="text-center py-8 text-gray-400">
                        No hay movimientos registrados
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="mov in movimientos" :key="mov.IdInventarioPropiamente" class="border rounded-lg p-3 hover:bg-gray-50">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-mono text-xs text-gray-400">#{{ mov.IdInventarioPropiamente }}</span>
                                    <span class="ml-2 text-sm font-medium" :class="mov.D_H === 'D' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ mov.D_H === 'D' ? 'ENTRADA' : 'SALIDA' }}
                                    </span>
                                </div>
                                <span class="text-sm text-gray-500">{{ mov.almacen?.Almacen || 'Sin almacén' }}</span>
                            </div>
                            <div class="mt-1">
                                <p class="text-sm text-gray-700">{{ mov.Glosa || 'Sin glosa' }}</p>
                            </div>
                            <div class="flex justify-between mt-2 text-sm">
                                <span>{{ mov.tipo_operacion?.Detalle || 'Movimiento' }}</span>
                                <span class="font-bold">{{ Number(mov.Unidades || 0).toFixed(2) }} unidades</span>
                                <span class="text-gray-500">{{ new Date(mov.created_at || mov.IdFecha).toLocaleDateString() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>