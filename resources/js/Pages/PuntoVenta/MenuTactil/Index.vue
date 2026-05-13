<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, inject, onMounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    categorias: Array,
    ruta: Array,
    titulo: String,
    comisionista: String
})

const loading = ref(false)
const totalItems = ref(0)

const cargarTotalItems = async () => {
    try {
        const response = await axios.get('/api/venta-tactil/carrito')
        if (response.data.success) {
            totalItems.value = response.data.items.reduce((sum, item) => sum + item.unidades, 0)
        }
    } catch (error) {
        console.error('Error cargando carrito:', error)
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
            }, 500)
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        toast?.error('Error', 'No se pudo cancelar la venta')
    }
}

const irACategoria = (id) => {
    loading.value = true
    router.get(`/venta-tactil/categoria/${id}`, {}, {
        onFinish: () => { loading.value = false }
    })
}

const volver = () => window.history.back()
const irAlCarrito = () => router.get('/venta-tactil/carrito')

onMounted(() => cargarTotalItems())
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="max-w-6xl mx-auto p-4">
            
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
                    <button @click="cancelarVenta" class="px-4 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 flex items-center gap-2">
                        <i class="fas fa-trash-alt"></i>
                        <span class="hidden sm:inline">Cancelar</span>
                    </button>
                    
                    <button @click="irAlCarrito" class="relative px-4 py-2 bg-emerald-100 text-emerald-600 rounded-lg hover:bg-emerald-200 flex items-center gap-2">
                        <i class="fas fa-shopping-cart text-lg"></i>
                        <span class="hidden sm:inline">Carrito</span>
                        <span v-if="totalItems > 0" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            {{ totalItems }}
                        </span>
                    </button>
                </div>
            </div>

            <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">{{ titulo }}</h1>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                <div v-for="cat in categorias" :key="cat.id_categoria"
                    @click="irACategoria(cat.id_categoria)"
                    class="bg-white rounded-2xl shadow-lg p-6 cursor-pointer hover:shadow-xl transition-all hover:scale-105 text-center">
                    <div class="w-24 h-24 mx-auto bg-gradient-to-br from-emerald-100 to-teal-100 rounded-2xl flex items-center justify-center mb-3">
                        <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover rounded-2xl">
                        <i v-else class="fas fa-folder-open text-4xl text-emerald-600"></i>
                    </div>
                    <h3 class="font-bold text-lg text-gray-800">{{ cat.nombre }}</h3>
                    <p class="text-xs text-gray-400 mt-1">Toca para ver</p>
                </div>
            </div>

            <div v-if="!categorias.length" class="text-center text-gray-500 py-12">
                <i class="fas fa-folder-open text-5xl mb-3 block text-gray-300"></i>
                <p>No hay categorías disponibles</p>
            </div>

            <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl p-6 flex items-center gap-3">
                    <i class="fas fa-spinner fa-spin text-2xl text-emerald-600"></i>
                    <span class="text-gray-700">Cargando...</span>
                </div>
            </div>
        </div>
    </div>
</template>