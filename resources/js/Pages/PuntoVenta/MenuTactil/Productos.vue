<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, inject, onMounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

// 🔥 Sistema de notificaciones
const toast = inject('toast')

const props = defineProps({
    categoria: Object,
    productos: Array,
    ruta: Array,
    comisionista: String  // 👈 Agregar esta línea

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

// Total del carrito
const totalCarrito = computed(() => {
    return carrito.value.reduce((sum, item) => sum + (item.precio * item.cantidad), 0).toFixed(2)
})

const totalItems = computed(() => {
    return carrito.value.reduce((sum, item) => sum + item.cantidad, 0)
})

// Cargar carrito desde la BD al iniciar
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
            // ✅ ELIMINA esta línea: totalCarritoValor.value = parseFloat(response.data.total || 0)
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

// Abrir modal para elegir cantidad
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

// Cerrar modal
const cerrarModal = () => {
    modalVisible.value = false
    setTimeout(() => {
        if (!modalVisible.value) {
            productoSeleccionado.value = null
        }
    }, 300)
}

// Incrementar cantidad
const incrementarCantidad = () => {
    cantidad.value++
}

// Decrementar cantidad
const decrementarCantidad = () => {
    if (cantidad.value > 1) {
        cantidad.value--
    }
}

// Validar cantidad
const validarCantidad = () => {
    let val = parseInt(cantidad.value)
    if (isNaN(val) || val < 1) {
        cantidad.value = 1
    } else {
        cantidad.value = val
    }
}

// Agregar al carrito
// Agregar al carrito
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
            
            toast?.success(
                '¡Producto agregado!', 
                `${productoSeleccionado.value.nombre} x ${cantidad.value}`
            )
            
            cerrarModal()
        } else {
            toast?.error('Error', response.data.message || 'Error al agregar')
        }
    } catch (error) {
        console.error('Error:', error)
        
        let errorMsg = 'Error al agregar el producto'
        if (error.response?.data?.message) errorMsg = error.response.data.message
        else if (error.response?.data?.error) errorMsg = error.response.data.error
        else if (error.message) errorMsg = error.message
        
        toast?.error('Error', errorMsg)
    } finally {
        loading.value = false
    }
}

// Eliminar producto del carrito
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
        console.error('Error:', error)
        toast.error('Error', 'No se pudo eliminar el producto')
    }
}
const cancelarVenta = async () => {
    if (!confirm('¿Cancelar toda la venta? Se perderán todos los productos.')) return
    
    try {
        const response = await axios.delete('/api/venta-tactil/cancelar')
        if (response.data.success) {
            toast?.success('Venta cancelada', 'Inicia una nueva venta')
            setTimeout(() => {
                router.get('/venta-tactil/nueva')
            }, 1000)
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        toast?.error('Error', 'No se pudo cancelar la venta')
    }
}
// Actualizar cantidad desde el carrito
const actualizarCantidad = async (itemId, nuevaCantidad, nombre) => {
    if (nuevaCantidad < 1) {
        eliminarDelCarrito(itemId, nombre)
        return
    }
    
    try {
        const response = await axios.put(`/api/venta-tactil/carrito/${itemId}`, {
            unidades: nuevaCantidad
        })
        if (response.data.success) {
            await cargarCarrito()
        } else {
            toast.error('Error', response.data.message || 'Error al actualizar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast.error('Error', 'No se pudo actualizar la cantidad')
    }
}

// Ir al carrito completo
const irAlCarrito = () => {
    router.get('/venta-tactil/carrito')
}

const volver = () => {
    window.history.back()
}

// Cargar carrito al iniciar
onMounted(() => {
    cargarCarrito()
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="max-w-6xl mx-auto p-4">
            
            <!-- En la barra superior de Productos.vue -->
            <div class="bg-white rounded-xl shadow-sm p-3 mb-4 flex items-center justify-between">
                <button @click="volver" class="px-4 py-2 bg-gray-200 rounded-lg text-gray-700 hover:bg-gray-300 flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
                
                <div class="text-sm text-gray-500 text-center">
                    <span class="block text-xs text-emerald-600 font-medium">Comisionista: {{ comisionista || 'Sin comisionista' }}</span>
                    <span>
                        <span v-for="(item, idx) in ruta" :key="item.id">
                            <span v-if="idx > 0" class="mx-1">/</span>
                            <span class="font-medium">{{ item.nombre }}</span>
                        </span>
                    </span>
                </div>
                
                <div class="flex items-center gap-2">
                    <!-- Botón Cancelar Venta -->
                    <button 
                        @click="cancelarVenta"
                        class="px-4 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center gap-2"
                        title="Cancelar venta actual"
                    >
                        <i class="fas fa-trash-alt"></i>
                        <span class="hidden sm:inline">Cancelar</span>
                    </button>
                    
                    <!-- Botón Carrito -->
                    <button 
                        @click="irAlCarrito"
                        class="relative px-4 py-2 bg-emerald-100 text-emerald-600 rounded-lg hover:bg-emerald-200 flex items-center gap-2"
                    >
                        <i class="fas fa-shopping-cart text-lg"></i>
                        <span class="hidden sm:inline">Carrito</span>
                        <span v-if="totalItems > 0" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            {{ totalItems }}
                        </span>
                    </button>
                </div>
            </div>
            <!-- Título de categoría -->
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                    <img v-if="categoria?.imagen_url" :src="categoria.imagen_url" class="w-full h-full object-cover rounded-xl">
                    <i v-else class="fas fa-tag text-emerald-600 text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ categoria?.nombre }}</h1>
                    <p class="text-sm text-gray-500">{{ productos.length }} productos disponibles</p>
                </div>
            </div>

            <!-- Grid de productos -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <div 
                    v-for="prod in productos" 
                    :key="prod.id"
                    @click="abrirModal(prod)"
                    class="bg-white rounded-2xl shadow-lg p-4 cursor-pointer hover:shadow-xl transition-all hover:scale-105 text-center"
                >
                    <div class="w-20 h-20 mx-auto bg-gradient-to-br from-blue-100 to-indigo-100 rounded-xl flex items-center justify-center mb-3">
                        <i class="fas fa-box-open text-3xl text-blue-600"></i>
                    </div>
                    
                    <h3 class="font-bold text-sm text-gray-800 line-clamp-2 min-h-[40px]">
                        {{ prod.nombre }}
                    </h3>
                    
                    <div class="mt-2">
                        <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-sm font-bold">
                            {{ Number(prod.PrecioVenta).toFixed(2) }} Bs
                        </span>
                    </div>
                    
                    <div class="mt-3 text-xs text-emerald-600">
                        <i class="fas fa-plus-circle"></i> Tocar para agregar
                    </div>
                </div>
            </div>

            <!-- Mensaje si no hay productos -->
            <div v-if="!productos.length" class="text-center text-gray-500 py-12">
                <i class="fas fa-box-open text-5xl mb-3 block text-gray-300"></i>
                <p>No hay productos en esta categoría</p>
            </div>

            <!-- Modal de cantidad -->
            <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-xl">
                    <div class="bg-gradient-to-r from-emerald-500 to-teal-500 p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center">
                                <i class="fas fa-box-open text-emerald-600 text-xl"></i>
                            </div>
                            <div class="text-white">
                                <h3 class="font-bold text-lg">{{ productoSeleccionado?.nombre }}</h3>
                                <p class="text-sm opacity-90">
                                    {{ tipoPrecio === 'mayorista' ? 'Precio Mayorista' : tipoPrecio === 'sucursal' ? 'Precio Sucursal' : 'Precio Normal' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="text-center mb-6">
                            <span class="text-3xl font-bold text-emerald-600">{{ precioUnitario.toFixed(2) }} Bs</span>
                            <span class="text-gray-400 text-sm ml-1">cada uno</span>
                        </div>

                        <div class="flex items-center justify-center gap-4 mb-6">
                            <button @click="decrementarCantidad" class="w-12 h-12 rounded-full bg-gray-100 text-2xl font-bold text-gray-700 hover:bg-gray-200 transition">-</button>
                            <input type="number" v-model.number="cantidad" @input="validarCantidad" min="1" class="w-20 text-center text-2xl font-bold border-2 border-gray-200 rounded-xl py-2 focus:border-emerald-400 focus:outline-none">
                            <button @click="incrementarCantidad" class="w-12 h-12 rounded-full bg-gray-100 text-2xl font-bold text-gray-700 hover:bg-gray-200 transition">+</button>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-4 mb-6 text-center">
                            <p class="text-sm text-gray-500">Total a pagar</p>
                            <p class="text-2xl font-bold text-emerald-600">{{ totalModal }} Bs</p>
                        </div>

                        <div class="flex gap-3">
                            <button @click="cerrarModal" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-700 font-medium hover:bg-gray-200 transition">Cancelar</button>
                            <button @click="agregarAlCarrito" :disabled="loading || cargandoPrecio" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-medium hover:shadow-lg transition disabled:opacity-50 flex items-center justify-center gap-2">
                                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-cart-plus"></i>
                                {{ loading ? 'Agregando...' : `Agregar (${cantidad})` }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mini resumen del carrito (flotante) -->
            <div v-if="totalItems > 0" class="fixed bottom-4 left-4 bg-white rounded-xl shadow-lg p-3 border border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="bg-emerald-100 rounded-full w-10 h-10 flex items-center justify-center">
                        <i class="fas fa-shopping-cart text-emerald-600"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total productos</p>
                        <p class="font-bold text-gray-800">{{ totalItems }} items</p>
                    </div>
                    <div class="border-l pl-3 ml-1">
                        <p class="text-xs text-gray-500">Total Bs</p>
                        <p class="font-bold text-emerald-600">{{ totalCarrito }} Bs</p>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading && !modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl p-6 flex items-center gap-3">
                    <i class="fas fa-spinner fa-spin text-2xl text-emerald-600"></i>
                    <span class="text-gray-700">Cargando...</span>
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