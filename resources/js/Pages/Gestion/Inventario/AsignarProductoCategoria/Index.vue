<script setup>
import { ref, computed, inject } from 'vue'
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

// Estado
const productosSeleccionados = ref([...(props.asignaciones || [])])
const buscando = ref('')
const guardando = ref(false)
const mostrarSoloHabilitados = ref(false)

// 🔥 FUNCIÓN PARA FILTRAR PRODUCTOS POR BÚSQUEDA
const productoCoincideConBusqueda = (producto) => {
    if (!buscando.value) return true
    const termino = buscando.value.toLowerCase()
    return producto.nombre?.toLowerCase().includes(termino) || 
           producto.id?.toString().includes(termino)
}

// 🔥 FUNCIÓN PARA VERIFICAR SI PRODUCTO ESTÁ HABILITADO (para el toggle)
const productoEstaHabilitado = (producto) => {
    if (!mostrarSoloHabilitados.value) return true
    return productosSeleccionados.value.includes(producto.id)
}

// 🔥 FILTRAR CATEGORÍAS Y SUS PRODUCTOS
const categoriasFiltradas = computed(() => {
    if (!props.categoriasConProductos) return []
    
    return props.categoriasConProductos
        .map(categoria => {
            // Filtrar productos de esta categoría por búsqueda y toggle
            const productosFiltrados = (categoria.productos || []).filter(producto => {
                return productoCoincideConBusqueda(producto) && productoEstaHabilitado(producto)
            })
            
            // Solo devolver la categoría si tiene productos después del filtro
            if (productosFiltrados.length === 0) return null
            
            return {
                ...categoria,
                productos: productosFiltrados
            }
        })
        .filter(categoria => categoria !== null)
})

// Seleccionar todos los productos VISIBLES (los que están en categoríasFiltradas)
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
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-primary-100 rounded-2xl mb-3">
                        <i class="fas fa-store text-xl text-primary-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Habilitar Productos para Sucursal</h1>
                    <p class="text-xs text-gray-500">
                        Selecciona qué productos estarán disponibles en el menú táctil para esta sucursal
                    </p>
                    <p class="text-xs text-primary-600 font-medium mt-1">
                        📍 Sucursal actual: <strong>{{ sucursalNombre }} (ID: {{ sucursalId }})</strong>
                    </p>
                    <div class="flex justify-center gap-4 mt-2 text-xs">
                        <span class="text-gray-500">Total productos: <strong>{{ totalProductos }}</strong></span>
                        <span class="text-primary-600">Habilitados: <strong>{{ productosSeleccionados.length }}</strong></span>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-4">
                    <!-- Barra de búsqueda y filtros -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4 pb-3 border-b">
                        <div class="relative w-full sm:w-80">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input 
                                type="text" 
                                v-model="buscando" 
                                placeholder="Buscar producto por nombre o ID..."
                                class="w-full border rounded-lg pl-10 pr-4 py-2 text-sm"
                            >
                            <button 
                                v-if="buscando"
                                @click="buscando = ''"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-2 text-xs">
                                <input type="checkbox" v-model="mostrarSoloHabilitados" class="rounded border-gray-300 text-primary-600">
                                Mostrar solo habilitados
                            </label>
                            <button @click="seleccionarTodos" class="text-xs text-primary-600 hover:text-primary-700">
                                <i class="fas fa-check-square mr-1"></i> Seleccionar visibles
                            </button>
                            <button @click="deseleccionarTodos" class="text-xs text-gray-500 hover:text-gray-700">
                                <i class="fas fa-square mr-1"></i> Deseleccionar todos
                            </button>
                        </div>
                    </div>

                    <!-- Lista de categorías con productos -->
                    <div class="max-h-[550px] overflow-y-auto space-y-4">
                        <div 
                            v-for="categoria in categoriasFiltradas" 
                            :key="categoria.id"
                            class="border rounded-lg overflow-hidden"
                        >
                            <!-- Header de categoría -->
                            <div class="bg-primary-50 px-3 py-2 border-b flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-folder-open text-primary-600 text-sm"></i>
                                    <span class="text-sm font-semibold text-primary-700">{{ categoria.nombre }}</span>
                                </div>
                                <span class="text-xs text-gray-500">
                                    {{ categoria.productos.filter(p => productosSeleccionados.includes(p.id)).length }}/{{ categoria.productos.length }} habilitados
                                </span>
                            </div>
                            
                            <!-- Grid de productos de la categoría -->
                            <div class="p-3">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                    <div 
                                        v-for="producto in categoria.productos"
                                        :key="producto.id"
                                        :class="[
                                            'flex items-center gap-3 p-2 rounded-lg border cursor-pointer transition-all',
                                            estaSeleccionado(producto.id) 
                                                ? 'border-primary-400 bg-primary-50' 
                                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                                        ]"
                                        @click="toggleProducto(producto.id)"
                                    >
                                        <div class="w-5 h-5 rounded border flex items-center justify-center flex-shrink-0"
                                            :class="estaSeleccionado(producto.id) ? 'bg-primary-500 border-primary-500' : 'border-gray-300 bg-white'">
                                            <i v-if="estaSeleccionado(producto.id)" class="fas fa-check text-white text-xs"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-800 truncate" :title="producto.nombre">
                                                {{ producto.nombre }}
                                            </div>
                                            <div class="text-xs text-primary-600 font-semibold">
                                                {{ Number(producto.PrecioVenta).toFixed(2) }} Bs
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-400 font-mono flex-shrink-0">#{{ producto.id }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mensaje sin resultados -->
                        <div v-if="categoriasFiltradas.length === 0" class="text-center text-gray-400 py-12">
                            <i class="fas fa-box-open text-3xl mb-2 block"></i>
                            <p v-if="buscando">No hay productos que coincidan con "{{ buscando }}"</p>
                            <p v-else>No hay productos disponibles</p>
                        </div>
                    </div>

                    <!-- Botón guardar -->
                    <div class="mt-4 pt-3 border-t flex justify-end">
                        <button @click="guardarAsignaciones" :disabled="guardando"
                            class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 flex items-center gap-2">
                            <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ guardando ? 'Guardando...' : 'Guardar Habilitaciones' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>