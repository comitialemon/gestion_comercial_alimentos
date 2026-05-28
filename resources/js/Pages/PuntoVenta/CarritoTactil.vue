<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, inject, onMounted } from 'vue'
import axios from 'axios'
import ConfirmModal from './Components/ConfirmModal.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const carrito = ref([])
const total = ref(0)
const loading = ref(false)

const modalEliminar = ref(false)
const modalCancelarVenta = ref(false)
const itemAEliminar = ref(null)

const cargarCarrito = async () => {
    loading.value = true
    try {
        const response = await axios.get('/api/venta-tactil/carrito')
        if (response.data.success) {
            carrito.value = response.data.items.map(item => ({
                id: item.id,
                id_producto: item.id_producto,
                nombre: item.nombre,
                precio: parseFloat(item.precio),
                cantidad: parseInt(item.unidades),
                subtotal: parseFloat(item.subtotal)
            }))
            total.value = parseFloat(response.data.total)
        }
    } catch (error) {
        console.error('Error cargando carrito:', error)
        toast?.error('Error', 'No se pudo cargar el carrito')
    } finally {
        loading.value = false
    }
}

const actualizarCantidad = async (item, delta) => {
    const nuevaCantidad = item.cantidad + delta
    
    if (nuevaCantidad < 1) {
        itemAEliminar.value = item
        modalEliminar.value = true
        return
    }
    
    const cantidadAnterior = item.cantidad
    const subtotalAnterior = item.subtotal
    
    item.cantidad = nuevaCantidad
    item.subtotal = item.precio * nuevaCantidad
    
    try {
        const response = await axios.put(`/api/venta-tactil/carrito/${item.id}`, { 
            unidades: nuevaCantidad
        })
        
        if (response.data.success) {
            await cargarCarrito()
            toast?.success('Cantidad actualizada', `${item.nombre} x ${nuevaCantidad}`)
        } else {
            item.cantidad = cantidadAnterior
            item.subtotal = subtotalAnterior
            toast?.error('Error', response.data.message || 'Error al actualizar')
        }
    } catch (error) {
        console.error('Error:', error)
        item.cantidad = cantidadAnterior
        item.subtotal = subtotalAnterior
        toast?.error('Error', error.response?.data?.message || error.message)
    }
}

const confirmarEliminar = async () => {
    if (!itemAEliminar.value) return
    
    try {
        const response = await axios.delete(`/api/venta-tactil/carrito/${itemAEliminar.value.id}`)
        if (response.data.success) {
            await cargarCarrito()
            toast?.success('Producto eliminado', itemAEliminar.value.nombre)
        } else {
            toast?.error('Error', response.data.message || 'Error al eliminar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', 'No se pudo eliminar el producto')
    } finally {
        itemAEliminar.value = null
        modalEliminar.value = false
    }
}

const confirmarCancelarVenta = async () => {
    try {
        const response = await axios.delete('/api/venta-tactil/cancelar')
        if (response.data.success) {
            toast?.success('Venta cancelada', 'Puedes iniciar una nueva venta')
            carrito.value = []
            total.value = 0
            setTimeout(() => {
                router.get('/venta-tactil/nueva')
            }, 500)
        } else {
            toast?.error('Error', response.data.message || 'Error al cancelar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', 'No se pudo cancelar la venta')
    } finally {
        modalCancelarVenta.value = false
    }
}

const seguirAgregando = () => router.get('/venta-tactil')
const irAPagar = () => router.get('/venta-tactil/pago')

onMounted(() => cargarCarrito())
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="max-w-4xl mx-auto p-3 sm:p-4">
            
            <div class="bg-white rounded-lg shadow-sm p-2.5 mb-3 flex items-center justify-between">
                <button @click="seguirAgregando" class="px-3 py-1.5 bg-gray-100 rounded-md text-gray-600 text-xs hover:bg-gray-200 flex items-center gap-1">
                    <i class="fas fa-arrow-left text-[10px]"></i> Seguir agregando
                </button>
                <h1 class="text-base font-bold text-gray-800">Mi Pedido</h1>
                <button @click="modalCancelarVenta = true" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-md text-xs hover:bg-red-100 flex items-center gap-1">
                    <i class="fas fa-trash-alt text-[10px]"></i> Cancelar
                </button>
            </div>

            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div v-if="loading" class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary-500"></i>
                    <p class="mt-2 text-xs text-gray-500">Cargando carrito...</p>
                </div>
                
                <div v-else-if="carrito.length === 0" class="text-center py-10">
                    <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-2 block"></i>
                    <p class="text-sm text-gray-500">Tu carrito está vacío</p>
                    <button @click="seguirAgregando" class="mt-3 px-4 py-1.5 bg-primary-600 text-white rounded-md text-xs hover:bg-primary-700">
                        Agregar productos
                    </button>
                </div>

                <div v-else>
                    <div v-for="item in carrito" :key="item.id" class="p-3 border-b hover:bg-gray-50">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex-1 min-w-[120px]">
                                <p class="font-medium text-gray-800 text-sm">{{ item.nombre }}</p>
                                <p class="text-[10px] text-gray-400">{{ item.precio.toFixed(2) }} Bs c/u</p>
                            </div>
                            
                            <div class="flex items-center gap-2 bg-gray-50 rounded-lg px-2 py-1">
                                <button @click="actualizarCantidad(item, -1)" class="w-6 h-6 rounded-full bg-gray-200 text-gray-600 text-xs hover:bg-gray-300 flex items-center justify-center">
                                    <i class="fas fa-minus text-[10px]"></i>
                                </button>
                                <span class="w-8 text-center font-semibold text-gray-800 text-sm">{{ item.cantidad }}</span>
                                <button @click="actualizarCantidad(item, 1)" class="w-6 h-6 rounded-full bg-gray-200 text-gray-600 text-xs hover:bg-gray-300 flex items-center justify-center">
                                    <i class="fas fa-plus text-[10px]"></i>
                                </button>
                            </div>
                            
                            <div class="min-w-[85px] text-right">
                                <span class="font-bold text-primary-600 text-sm">{{ item.subtotal.toFixed(2) }} Bs</span>
                            </div>
                            
                            <button @click="actualizarCantidad(item, -1)" class="text-red-400 hover:text-red-600 w-6 h-6 rounded-full flex items-center justify-center hover:bg-red-50">
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-3 bg-gray-50 border-t">
                        <div class="flex flex-wrap justify-between items-center gap-2">
                            <div class="text-[10px] text-gray-500">
                                <p>Total productos: {{ carrito.length }}</p>
                                <p>Total unidades: {{ carrito.reduce((s, i) => s + i.cantidad, 0) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-gray-500">TOTAL</p>
                                <p class="text-xl font-bold text-primary-700">{{ total.toFixed(2) }} Bs</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="carrito.length > 0" class="mt-3 flex flex-col sm:flex-row gap-2">
                <button @click="seguirAgregando" class="flex-1 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200 flex items-center justify-center gap-1">
                    <i class="fas fa-plus text-xs"></i> Seguir agregando
                </button>
                <button @click="irAPagar" class="flex-1 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 flex items-center justify-center gap-1">
                    <i class="fas fa-credit-card text-xs"></i> Ir a pagar
                </button>
            </div>

            <div class="mt-3 text-center text-[9px] text-gray-400">
                <i class="fas fa-shield-alt"></i> Datos seguros
            </div>
        </div>

        <!-- Modal Eliminar Producto -->
        <ConfirmModal
            v-model="modalEliminar"
            title="Eliminar producto"
            message="¿Estás seguro de eliminar este producto del carrito?"
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            type="danger"
            @confirm="confirmarEliminar"
        />

        <!-- Modal Cancelar Venta -->
        <ConfirmModal
            v-model="modalCancelarVenta"
            title="Cancelar venta"
            message="¿Estás seguro de cancelar toda la venta? Se perderán todos los productos."
            confirm-text="Cancelar venta"
            cancel-text="Seguir comprando"
            type="warning"
            @confirm="confirmarCancelarVenta"
        />
    </div>
</template>