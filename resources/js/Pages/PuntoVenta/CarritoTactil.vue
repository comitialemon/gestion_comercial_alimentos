<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, inject, onMounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const carrito = ref([])
const total = ref(0)
const loading = ref(false)

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
        eliminarProducto(item)
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

const eliminarProducto = async (item) => {
    if (!confirm(`¿Eliminar "${item.nombre}" del carrito?`)) return
    
    try {
        const response = await axios.delete(`/api/venta-tactil/carrito/${item.id}`)
        if (response.data.success) {
            await cargarCarrito()
            toast?.success('Producto eliminado', item.nombre)
        } else {
            toast?.error('Error', response.data.message || 'Error al eliminar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', 'No se pudo eliminar el producto')
    }
}

const cancelarVenta = async () => {
    if (!confirm('¿Cancelar toda la venta? Todos los productos se perderán.')) return
    
    try {
        const response = await axios.delete('/api/venta-tactil/cancelar')
        if (response.data.success) {
            toast?.success('Venta cancelada', 'Inicia una nueva venta')
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
    }
}

const seguirAgregando = () => router.get('/venta-tactil')
// 🔥 CORREGIDO: ruta correcta para pago táctil
const irAPagar = () => router.get('/venta-tactil/pago')

onMounted(() => cargarCarrito())
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="max-w-4xl mx-auto p-4">
            
            <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex items-center justify-between">
                <button @click="seguirAgregando" class="px-4 py-2 bg-gray-200 rounded-lg text-gray-700 hover:bg-gray-300 flex items-center gap-2 transition">
                    <i class="fas fa-arrow-left"></i> Seguir agregando
                </button>
                <h1 class="text-xl font-bold text-gray-800">Mi Pedido</h1>
                <button @click="cancelarVenta" class="px-4 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center gap-2 transition">
                    <i class="fas fa-trash-alt"></i> Cancelar
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div v-if="loading" class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-2xl text-emerald-600"></i>
                    <p class="mt-2 text-gray-500">Cargando carrito...</p>
                </div>
                
                <div v-else-if="carrito.length === 0" class="text-center py-12">
                    <i class="fas fa-shopping-cart text-5xl text-gray-300 mb-3 block"></i>
                    <p class="text-gray-500">Tu carrito está vacío</p>
                    <button @click="seguirAgregando" class="mt-4 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
                        Agregar productos
                    </button>
                </div>

                <div v-else>
                    <div v-for="item in carrito" :key="item.id" class="p-4 border-b hover:bg-gray-50 transition">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex-1">
                                <p class="font-medium text-gray-800">{{ item.nombre }}</p>
                                <p class="text-sm text-gray-500">{{ item.precio.toFixed(2) }} Bs c/u</p>
                            </div>
                            
                            <div class="flex items-center justify-center gap-3">
                                <button @click="actualizarCantidad(item, -1)" class="w-8 h-8 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 transition flex items-center justify-center">
                                    <i class="fas fa-minus text-sm"></i>
                                </button>
                                <span class="w-12 text-center font-semibold text-gray-800">{{ item.cantidad }}</span>
                                <button @click="actualizarCantidad(item, 1)" class="w-8 h-8 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 transition flex items-center justify-center">
                                    <i class="fas fa-plus text-sm"></i>
                                </button>
                            </div>
                            
                            <div class="text-right sm:text-left min-w-[100px]">
                                <span class="font-bold text-emerald-600">{{ item.subtotal.toFixed(2) }} Bs</span>
                            </div>
                            
                            <div class="text-right">
                                <button @click="eliminarProducto(item)" class="text-red-500 hover:text-red-700 w-8 h-8 rounded-full flex items-center justify-center hover:bg-red-50 transition">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 border-t">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                            <div class="text-sm text-gray-500">
                                <p>Total productos: {{ carrito.length }}</p>
                                <p>Total unidades: {{ carrito.reduce((s, i) => s + i.cantidad, 0) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">TOTAL</p>
                                <p class="text-2xl font-bold text-emerald-600">{{ total.toFixed(2) }} Bs</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="carrito.length > 0" class="mt-4 flex flex-col sm:flex-row gap-3">
                <button @click="seguirAgregando" class="flex-1 py-3 rounded-xl bg-gray-200 text-gray-700 font-medium hover:bg-gray-300 transition">
                    <i class="fas fa-plus mr-2"></i> Seguir agregando
                </button>
                <button @click="irAPagar" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-medium hover:shadow-lg transition">
                    <i class="fas fa-credit-card mr-2"></i> Ir a pagar
                </button>
            </div>
        </div>
    </div>
</template>