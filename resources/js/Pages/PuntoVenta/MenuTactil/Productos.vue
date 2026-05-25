<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, inject, onMounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    categoria: Object,
    productos: Array,
    ruta: Array,
    comisionista: String
})

// Estado
const loading = ref(false)
const carrito = ref([])
const cargandoCarrito = ref(false)

// Estado del modal
const modalVisible = ref(false)
const productoSeleccionado = ref(null)
const cantidad = ref(1)
const precioUnitario = ref(0)
const tipoPrecio = ref('')
const cargandoPrecio = ref(false)

// Calcular total del modal
const totalModal = computed(() => {
    return (cantidad.value * precioUnitario.value).toFixed(2)
})

const totalCarrito = computed(() => {
    return carrito.value.reduce((sum, item) => sum + (item.precio * item.cantidad), 0).toFixed(2)
})

const totalItems = computed(() => {
    return carrito.value.reduce((sum, item) => sum + item.cantidad, 0)
})

const cargarCarrito = async () => {
    cargandoCarrito.value = true
    try {
        const response = await axios.get('/api/venta-tactil/carrito')
        if (response.data && response.data.success) {
            carrito.value = (response.data.items || []).map(item => ({
                id: item.id,
                id_producto: item.id_producto,
                nombre: item.nombre,
                precio: parseFloat(item.precio),
                cantidad: item.unidades,
                subtotal: parseFloat(item.subtotal)
            }))
        } else {
            console.warn('Respuesta inesperada:', response.data)
            carrito.value = []
        }
    } catch (error) {
        console.error('Error cargando carrito:', error)
        if (toast) toast.error('Error', 'No se pudo cargar el carrito')
    } finally {
        cargandoCarrito.value = false
    }
}

const abrirModal = async (producto) => {
    if (!producto || !producto.id) {
        toast.error('Error', 'Producto inválido')
        return
    }
    
    productoSeleccionado.value = producto
    cantidad.value = 1
    
    cargandoPrecio.value = true
    modalVisible.value = true
    
    try {
        const response = await axios.get(`/api/venta-tactil/precio/${producto.id}`)
        
        if (response.data.success) {
            precioUnitario.value = response.data.precio
            tipoPrecio.value = response.data.tipo
        } else {
            precioUnitario.value = Number(producto.PrecioVenta) || 0
            tipoPrecio.value = 'default'
        }
    } catch (error) {
        console.error('Error obteniendo precio:', error)
        precioUnitario.value = Number(producto.PrecioVenta) || 0
        tipoPrecio.value = 'default'
    } finally {
        cargandoPrecio.value = false
    }
}

const cerrarModal = () => {
    modalVisible.value = false
    setTimeout(() => {
        if (!modalVisible.value) {
            productoSeleccionado.value = null
        }
    }, 300)
}

const incrementarCantidad = () => cantidad.value++
const decrementarCantidad = () => { if (cantidad.value > 1) cantidad.value-- }

const validarCantidad = () => {
    let val = parseInt(cantidad.value)
    if (isNaN(val) || val < 1) cantidad.value = 1
    else cantidad.value = val
}

const agregarAlCarrito = async () => {
    if (!productoSeleccionado.value) {
        toast?.error('Error', 'No se pudo identificar el producto')
        cerrarModal()
        return
    }
    if (cantidad.value < 1) {
        toast?.warning('Cantidad inválida', 'La cantidad debe ser al menos 1')
        return
    }
    
    loading.value = true
    
    try {
        const response = await axios.post('/api/venta-tactil/agregar', {
            id_producto: productoSeleccionado.value.id,
            unidades: cantidad.value,
            precio: precioUnitario.value
        })
        
        if (response.data.success) {
            await cargarCarrito()
            toast?.success('¡Producto agregado!', `${productoSeleccionado.value.nombre} x ${cantidad.value}`)
            cerrarModal()
        } else {
            toast?.error('Error', response.data.message || 'Error al agregar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al agregar el producto')
    } finally {
        loading.value = false
    }
}

const eliminarDelCarrito = async (itemId, nombre) => {
    if (!confirm(`¿Eliminar "${nombre}" del carrito?`)) return
    try {
        const response = await axios.delete(`/api/venta-tactil/carrito/${itemId}`)
        if (response.data.success) {
            await cargarCarrito()
            toast.success('Producto eliminado', `${nombre} fue removido del carrito`)
        } else {
            toast.error('Error', response.data.message || 'Error al eliminar')
        }
    } catch (error) {
        toast.error('Error', 'No se pudo eliminar el producto')
    }
}

const cancelarVenta = async () => {
    if (!confirm('¿Cancelar toda la venta? Se perderán todos los productos.')) return
    try {
        const response = await axios.delete('/api/venta-tactil/cancelar')
        if (response.data.success) {
            toast?.success('Venta cancelada', 'Inicia una nueva venta')
            setTimeout(() => router.get('/venta-tactil/nueva'), 1000)
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        toast?.error('Error', 'No se pudo cancelar la venta')
    }
}

const actualizarCantidad = async (itemId, nuevaCantidad, nombre) => {
    if (nuevaCantidad < 1) {
        eliminarDelCarrito(itemId, nombre)
        return
    }
    try {
        const response = await axios.put(`/api/venta-tactil/carrito/${itemId}`, { unidades: nuevaCantidad })
        if (response.data.success) await cargarCarrito()
        else toast.error('Error', response.data.message || 'Error al actualizar')
    } catch (error) {
        toast.error('Error', 'No se pudo actualizar la cantidad')
    }
}

const irAlCarrito = () => router.get('/venta-tactil/carrito')
const volver = () => window.history.back()

onMounted(() => cargarCarrito())
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="max-w-6xl mx-auto p-3 sm:p-4">
            
            <!-- Barra superior -->
            <div class="bg-white rounded-lg shadow-sm p-2 mb-3 flex items-center justify-between">
                <button @click="volver" class="px-3 py-1.5 bg-gray-100 rounded-md text-gray-600 text-xs hover:bg-gray-200 flex items-center gap-1">
                    <i class="fas fa-arrow-left text-[10px]"></i> Volver
                </button>
                
                <div class="text-xs text-gray-500 text-center">
                    <span class="block text-[10px] text-guindo-600 font-medium">{{ comisionista || 'Sin comisionista' }}</span>
                    <span>
                        <span v-for="(item, idx) in ruta" :key="item.id">
                            <span v-if="idx > 0" class="mx-1">/</span>
                            <span class="font-medium text-gray-700">{{ item.nombre }}</span>
                        </span>
                    </span>
                </div>
                
                <div class="flex items-center gap-1">
                    <button @click="cancelarVenta" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-md text-xs hover:bg-red-100 flex items-center gap-1">
                        <i class="fas fa-trash-alt text-[10px]"></i>
                        <span class="hidden sm:inline">Cancelar</span>
                    </button>
                    
                    <button @click="irAlCarrito" class="relative px-3 py-1.5 bg-guindo-50 text-guindo-700 rounded-md text-xs hover:bg-guindo-100 flex items-center gap-1">
                        <i class="fas fa-shopping-cart text-xs"></i>
                        <span class="hidden sm:inline">Carrito</span>
                        <span v-if="totalItems > 0" class="absolute -top-1 -right-1 bg-amber-500 text-white text-[9px] rounded-full w-4 h-4 flex items-center justify-center">
                            {{ totalItems }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Título de categoría -->
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 bg-guindo-100 rounded-lg flex items-center justify-center">
                    <img v-if="categoria?.imagen_url" :src="categoria.imagen_url" class="w-full h-full object-cover rounded-lg">
                    <i v-else class="fas fa-tag text-guindo-500 text-sm"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold text-gray-800">{{ categoria?.nombre }}</h1>
                    <p class="text-[10px] text-gray-400">{{ productos.length }} productos</p>
                </div>
            </div>

            <!-- Grid de productos -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                <div 
                    v-for="prod in productos" 
                    :key="prod.id"
                    @click="abrirModal(prod)"
                    class="bg-white rounded-lg shadow p-2 cursor-pointer hover:shadow-md transition-all hover:scale-[1.02] text-center"
                >
                    <div class="w-14 h-14 mx-auto bg-gradient-to-br from-guindo-50 to-amber-50 rounded-lg flex items-center justify-center mb-1">
                        <i class="fas fa-box-open text-xl text-guindo-400"></i>
                    </div>
                    
                    <h3 class="font-medium text-[11px] text-gray-700 line-clamp-2 min-h-[32px]">
                        {{ prod.nombre }}
                    </h3>
                    
                    <div class="mt-1">
                        <span class="inline-block px-2 py-0.5 bg-amber-50 text-amber-700 rounded-full text-[10px] font-bold">
                            {{ Number(prod.PrecioVenta).toFixed(2) }} Bs
                        </span>
                    </div>
                    
                    <div class="mt-1 text-[9px] text-guindo-400">
                        <i class="fas fa-plus-circle"></i> Agregar
                    </div>
                </div>
            </div>

            <div v-if="!productos.length" class="text-center text-gray-400 py-8 text-sm">
                <i class="fas fa-box-open text-3xl mb-2 block"></i>
                No hay productos en esta categoría
            </div>

            <!-- Modal de cantidad -->
            <div v-if="modalVisible" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-3">
                <div class="bg-white rounded-xl max-w-sm w-full overflow-hidden shadow-xl">
                    <div class="bg-gradient-to-r from-guindo-700 to-guindo-800 p-3">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                <i class="fas fa-box-open text-guindo-600 text-base"></i>
                            </div>
                            <div class="text-white">
                                <h3 class="font-semibold text-sm">{{ productoSeleccionado?.nombre }}</h3>
                                <p class="text-[10px] opacity-80">
                                    {{ tipoPrecio === 'mayorista' ? 'Precio Mayorista' : tipoPrecio === 'sucursal' ? 'Precio Sucursal' : 'Precio Normal' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="text-center mb-3">
                            <span class="text-xl font-bold text-guindo-700">{{ precioUnitario.toFixed(2) }} Bs</span>
                            <span class="text-gray-400 text-[10px] ml-1">c/u</span>
                        </div>

                        <div class="flex items-center justify-center gap-3 mb-3">
                            <button @click="decrementarCantidad" class="w-8 h-8 rounded-full bg-gray-100 text-base font-bold text-gray-600 hover:bg-gray-200">-</button>
                            <input type="number" v-model.number="cantidad" @input="validarCantidad" min="1" class="w-16 text-center text-base font-bold border border-gray-200 rounded-lg py-1 focus:border-guindo-400 focus:outline-none">
                            <button @click="incrementarCantidad" class="w-8 h-8 rounded-full bg-gray-100 text-base font-bold text-gray-600 hover:bg-gray-200">+</button>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-2 mb-3 text-center">
                            <p class="text-[10px] text-gray-500">Total</p>
                            <p class="text-lg font-bold text-guindo-700">{{ totalModal }} Bs</p>
                        </div>

                        <div class="flex gap-2">
                            <button @click="cerrarModal" class="flex-1 py-2 rounded-lg bg-gray-100 text-gray-600 text-xs font-medium hover:bg-gray-200">Cancelar</button>
                            <button @click="agregarAlCarrito" :disabled="loading || cargandoPrecio" class="flex-1 py-2 rounded-lg bg-guindo-600 text-white text-xs font-medium hover:bg-guindo-700 disabled:opacity-50 flex items-center justify-center gap-1">
                                <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-cart-plus text-[10px]"></i>
                                {{ loading ? 'Agregando...' : `Agregar ${cantidad}` }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mini resumen del carrito -->
            <div v-if="totalItems > 0" class="fixed bottom-3 left-3 bg-white rounded-lg shadow-md p-2 border border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="bg-guindo-100 rounded-full w-7 h-7 flex items-center justify-center">
                        <i class="fas fa-shopping-cart text-guindo-600 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-[9px] text-gray-400">Productos</p>
                        <p class="font-bold text-gray-800 text-xs">{{ totalItems }} items</p>
                    </div>
                    <div class="border-l pl-2 ml-0.5">
                        <p class="text-[9px] text-gray-400">Total</p>
                        <p class="font-bold text-guindo-600 text-xs">{{ totalCarrito }} Bs</p>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading && !modalVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-4 flex items-center gap-2">
                    <i class="fas fa-spinner fa-spin text-guindo-600 text-base"></i>
                    <span class="text-gray-600 text-sm">Cargando...</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>