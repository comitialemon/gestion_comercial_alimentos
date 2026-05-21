<script setup>
import { ref, computed, watch, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    categorias: Array,
    productos: Array,
    asignaciones: Object,
    categoriasLista: Array,
    sucursalId: Number,
    sucursalNombre: String,
})

const categoriaSeleccionada = ref(null)
const productosSeleccionados = ref([])
const buscando = ref('')
const guardando = ref(false)

const categoriaActual = computed(() => {
    if (!categoriaSeleccionada.value) return null
    return props.categoriasLista?.find(c => c.id_categoria === categoriaSeleccionada.value)
})

const productosAsignadosIds = computed(() => {
    if (!categoriaSeleccionada.value) return []
    return props.asignaciones[categoriaSeleccionada.value] || []
})

// ✅ Todos los productos del cliente (sin filtrar)
const productosFiltrados = computed(() => {
    if (!buscando.value) return props.productos || []
    const termino = buscando.value.toLowerCase()
    return (props.productos || []).filter(p => 
        p.nombre.toLowerCase().includes(termino) || 
        p.id.toString().includes(termino)
    )
})

const seleccionarTodos = () => {
    if (productosSeleccionados.value.length === productosFiltrados.value.length) {
        productosSeleccionados.value = []
    } else {
        productosSeleccionados.value = productosFiltrados.value.map(p => p.id)
    }
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

watch(categoriaSeleccionada, (nueva) => {
    if (nueva) {
        productosSeleccionados.value = [...productosAsignadosIds.value]
        buscando.value = ''
    } else {
        productosSeleccionados.value = []
    }
})

const guardarAsignaciones = () => {
    if (!categoriaSeleccionada.value) {
        toast?.warning('Atención', 'Selecciona una categoría')
        return
    }
    
    guardando.value = true
    router.post('/gestion/inventario/asignar-productos-categoria', {
        id_categoria: categoriaSeleccionada.value,
        productos_ids: productosSeleccionados.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast?.success('Éxito', 'Asignaciones guardadas correctamente')
        },
        onFinish: () => { guardando.value = false }
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-guindo-100 rounded-2xl mb-3">
                        <i class="fas fa-link text-xl text-guindo-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Asignar Productos a Categorías</h1>
                    <p class="text-xs text-gray-500">
                        Selecciona una categoría y elige qué productos mostrarán en el menú táctil
                    </p>
                    <p class="text-xs text-guindo-600 font-medium mt-1">
                        📍 Sucursal actual: <strong>{{ sucursalNombre }} (ID: {{ sucursalId }})</strong>
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Panel izquierdo: Categorías -->
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h2 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <i class="fas fa-tree text-guindo-600"></i> Categorías
                        </h2>
                        
                        <div class="space-y-1 max-h-96 overflow-y-auto">
                            <button
                                v-for="cat in categorias"
                                :key="cat.id"
                                @click="categoriaSeleccionada = cat.id"
                                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-all"
                                :class="{
                                    'bg-guindo-50 text-guindo-700 border-l-4 border-guindo-500': categoriaSeleccionada === cat.id,
                                    'hover:bg-gray-50 text-gray-700': categoriaSeleccionada !== cat.id
                                }"
                            >
                                <span class="font-mono text-gray-400 text-xs mr-2">{{ cat.id }}</span>
                                <span :class="{ 'font-semibold': categoriaSeleccionada === cat.id }">{{ cat.nombre }}</span>
                            </button>
                        </div>
                        
                        <div v-if="!categorias.length" class="text-center text-gray-400 py-4">
                            No hay categorías. Crea algunas primero.
                        </div>
                    </div>

                    <!-- Panel derecho: Productos -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-4">
                        <div v-if="categoriaSeleccionada">
                            <div class="flex items-center gap-3 mb-4 pb-3 border-b">
                                <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100">
                                    <img v-if="categoriaActual?.imagen_url" :src="categoriaActual.imagen_url" class="w-full h-full object-cover">
                                    <i v-else class="fas fa-folder-open text-gray-300 text-2xl flex items-center justify-center h-full"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-gray-800">{{ categoriaActual?.nombre }}</h2>
                                    <p class="text-xs text-gray-500">
                                        {{ productosAsignadosIds.length }} productos asignados actualmente para esta sucursal
                                    </p>
                                </div>
                            </div>

                            <!-- Buscador -->
                            <div class="mb-4">
                                <div class="relative">
                                    <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                                    <input type="text" v-model="buscando" placeholder="Buscar producto por nombre o ID..."
                                        class="w-full border rounded-lg pl-10 pr-4 py-2 text-sm">
                                </div>
                                <p class="text-xs text-gray-400 mt-1">
                                    Total productos disponibles: {{ productos.length }}
                                </p>
                            </div>

                            <!-- Acciones -->
                            <div class="flex justify-between items-center mb-3">
                                <button @click="seleccionarTodos" class="text-xs text-guindo-600 hover:text-guindo-700">
                                    <i class="fas" :class="productosSeleccionados.length === productosFiltrados.length ? 'fa-check-square' : 'fa-square'"></i>
                                    {{ productosSeleccionados.length === productosFiltrados.length ? 'Deseleccionar todos' : 'Seleccionar todos' }}
                                </button>
                                <span class="text-xs text-gray-500">{{ productosSeleccionados.length }} seleccionados</span>
                            </div>

                            <!-- Grid de productos -->
                            <div class="max-h-96 overflow-y-auto">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <div v-for="producto in productosFiltrados" :key="producto.id"
                                        @click="toggleProducto(producto.id)"
                                        class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all"
                                        :class="{
                                            'border-guindo-400 bg-guindo-50': estaSeleccionado(producto.id),
                                            'border-gray-200 hover:border-gray-300 hover:bg-gray-50': !estaSeleccionado(producto.id)
                                        }">
                                        <div class="w-5 h-5 rounded border flex items-center justify-center"
                                            :class="estaSeleccionado(producto.id) ? 'bg-guindo-500 border-guindo-500' : 'border-gray-300 bg-white'">
                                            <i v-if="estaSeleccionado(producto.id)" class="fas fa-check text-white text-xs"></i>
                                        </div>
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-800">{{ producto.nombre }}</div>
                                            <div class="text-xs text-guindo-600 font-semibold">{{ Number(producto.PrecioVenta).toFixed(2) }} Bs</div>
                                        </div>
                                        <div class="text-xs text-gray-400 font-mono">#{{ producto.id }}</div>
                                    </div>
                                </div>
                                
                                <div v-if="!productosFiltrados.length" class="text-center text-gray-400 py-8">
                                    <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                    No hay productos que coincidan con la búsqueda
                                </div>
                            </div>

                            <!-- Botón guardar -->
                            <div class="mt-4 pt-3 border-t flex justify-end">
                                <button @click="guardarAsignaciones" :disabled="guardando"
                                    class="px-5 py-2 bg-guindo-600 text-white rounded-lg text-sm font-medium hover:bg-guindo-700 disabled:opacity-50 flex items-center gap-2">
                                    <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                                    <i v-else class="fas fa-save"></i>
                                    {{ guardando ? 'Guardando...' : 'Guardar Asignaciones' }}
                                </button>
                            </div>
                        </div>

                        <div v-else class="text-center text-gray-400 py-12">
                            <i class="fas fa-folder-open text-4xl mb-3 block"></i>
                            Selecciona una categoría del panel izquierdo
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>