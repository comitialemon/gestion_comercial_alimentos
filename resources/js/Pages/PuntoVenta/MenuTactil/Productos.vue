<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, inject, onMounted } from 'vue'
import axios from 'axios'
import NavBarTactil from '../Components/NavBarTactil.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    categoria: Object,
    productos: Array,
    ruta: Array,
    comisionista: String
})

const loading = ref(false)
const carrito = ref([])

// Modal
const modalVisible = ref(false)
const productoSeleccionado = ref(null)
const cantidad = ref(1)
const precioUnitario = ref(0)
const tipoPrecio = ref('')

const totalModal = computed(() => (cantidad.value * precioUnitario.value).toFixed(2))
const totalCarrito = computed(() => carrito.value.reduce((sum, item) => sum + (item.precio * item.cantidad), 0).toFixed(2))
const totalItems = computed(() => carrito.value.reduce((sum, item) => sum + item.cantidad, 0))

const cargarCarrito = async () => {
    try {
        const response = await axios.get('/api/venta-tactil/carrito')
        if (response.data?.success) {
            carrito.value = (response.data.items || []).map(item => ({
                id: item.id,
                id_producto: item.id_producto,
                nombre: item.nombre,
                precio: parseFloat(item.precio),
                cantidad: item.unidades,
                subtotal: parseFloat(item.subtotal)
            }))
        }
    } catch (error) {
        console.error('Error cargando carrito:', error)
    }
}

const abrirModal = (producto) => {
    if (!producto?.id) {
        toast?.error('Error', 'Producto inválido')
        return
    }
    productoSeleccionado.value = producto
    cantidad.value = 1
    precioUnitario.value = producto.precio_real
    tipoPrecio.value = producto.tipo_precio || 'default'
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    setTimeout(() => {
        if (!modalVisible.value) productoSeleccionado.value = null
    }, 200)
}

const incrementarCantidad = () => cantidad.value++
const decrementarCantidad = () => { if (cantidad.value > 1) cantidad.value-- }
const validarCantidad = () => { cantidad.value = parseInt(cantidad.value) || 1 }

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
        toast?.error('Error', error.response?.data?.message || 'Error al agregar')
    } finally {
        loading.value = false
    }
}

onMounted(() => cargarCarrito())
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto px-3 py-3">
            
            <!-- 🔥 USAR COMPONENTE -->
            <NavBarTactil 
                :comisionista="comisionista || 'Sin comisionista'"
                :ruta="ruta"
                :mostrar-ruta="true"
                :mostrar-cancelar="true"
            />

            <!-- Categoría con imagen -->
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-guindo-100 rounded-xl flex items-center justify-center shadow-sm overflow-hidden">
                    <img v-if="categoria?.imagen_url" :src="categoria.imagen_url" class="w-full h-full object-cover rounded-xl">
                    <i v-else class="fas fa-tag text-guindo-500 text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-guindo-800">{{ categoria?.nombre }}</h1>
                    <p class="text-[11px] text-gray-400">{{ productos.length }} productos disponibles</p>
                </div>
            </div>

            <!-- Grid de productos -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <div 
                    v-for="prod in productos" 
                    :key="prod.id"
                    @click="abrirModal(prod)"
                    class="bg-white rounded-lg shadow-sm hover:shadow-md transition cursor-pointer overflow-hidden border border-gray-100"
                >
                    <div class="h-20 bg-gradient-to-br from-guindo-50 to-amber-50 flex items-center justify-center overflow-hidden">
                        <img v-if="prod.imagen" :src="prod.imagen" class="w-full h-full object-cover">
                        <i v-else class="fas fa-box-open text-2xl text-guindo-400"></i>
                    </div>
                    <div class="p-2 text-center">
                        <h3 class="font-medium text-xs text-gray-800 line-clamp-2 min-h-[32px]">{{ prod.nombre }}</h3>
                        <div class="mt-1 flex flex-col items-center">
                            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-bold"
                                :class="prod.tipo_precio === 'mayorista' ? 'bg-amber-100 text-amber-700' : 'bg-guindo-100 text-guindo-700'"
                            >
                                {{ Number(prod.precio_real).toFixed(2) }} Bs
                            </span>
                            <span v-if="prod.precio_real !== prod.precio_normal" class="text-[9px] text-gray-400 line-through mt-0.5">
                                {{ Number(prod.precio_normal).toFixed(2) }} Bs
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!productos.length" class="text-center text-gray-400 py-10">
                <i class="fas fa-box-open text-3xl mb-2 block"></i>
                <p class="text-sm">No hay productos en esta categoría</p>
            </div>

            <!-- Modal -->
            <div v-if="modalVisible" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-3" @click.self="cerrarModal">
                <div class="bg-white rounded-xl max-w-sm w-full overflow-hidden shadow-xl">
                    <div class="bg-guindo-700 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center overflow-hidden">
                                <img v-if="productoSeleccionado?.imagen" :src="productoSeleccionado.imagen" class="w-full h-full object-cover">
                                <i v-else class="fas fa-box-open text-guindo-600 text-sm"></i>
                            </div>
                            <div class="text-white flex-1">
                                <h3 class="font-bold text-sm">{{ productoSeleccionado?.nombre }}</h3>
                                <p class="text-[10px] opacity-75">
                                    {{ tipoPrecio === 'mayorista' ? 'Precio Mayorista' : tipoPrecio === 'sucursal' ? 'Precio Sucursal' : 'Precio Normal' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="text-center mb-3">
                            <span class="text-2xl font-bold text-guindo-700">{{ Number(precioUnitario).toFixed(2) }}</span>
                            <span class="text-gray-400 text-xs ml-0.5">Bs c/u</span>
                        </div>
                        <div class="flex items-center justify-center gap-3 mb-4">
                            <button @click="decrementarCantidad" class="w-8 h-8 rounded-full bg-guindo-100 text-guindo-700 font-bold hover:bg-guindo-200">-</button>
                            <input type="number" v-model.number="cantidad" @input="validarCantidad" min="1" class="w-14 text-center text-lg font-bold border rounded-lg py-1 focus:border-guindo-400 focus:outline-none">
                            <button @click="incrementarCantidad" class="w-8 h-8 rounded-full bg-guindo-100 text-guindo-700 font-bold hover:bg-guindo-200">+</button>
                        </div>
                        <div class="bg-amber-50 rounded-lg p-2 mb-4 text-center">
                            <p class="text-[10px] text-amber-700">Total</p>
                            <p class="text-lg font-bold text-guindo-700">{{ totalModal }} Bs</p>
                        </div>
                        <div class="flex gap-2">
                            <button @click="cerrarModal" class="flex-1 py-2 rounded-lg bg-gray-100 text-gray-600 text-sm hover:bg-gray-200">Cancelar</button>
                            <button @click="agregarAlCarrito" :disabled="loading" class="flex-1 py-2 rounded-lg bg-guindo-600 hover:bg-guindo-700 text-white text-sm font-medium flex items-center justify-center gap-1">
                                <i v-if="loading" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else class="fas fa-cart-plus text-xs"></i>
                                {{ loading ? '' : `Agregar (${cantidad})` }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="totalItems > 0" class="fixed bottom-3 left-3 bg-white rounded-lg shadow-md p-2 border-l-3 border-guindo-500">
                <div class="flex items-center gap-2">
                    <div class="bg-guindo-100 rounded-full w-7 h-7 flex items-center justify-center">
                        <i class="fas fa-shopping-cart text-guindo-600 text-xs"></i>
                    </div>
                    <div class="text-[11px]">
                        <p class="text-gray-500 leading-tight">Total</p>
                        <p class="font-bold text-guindo-600">{{ totalCarrito }} Bs</p>
                    </div>
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
</style>