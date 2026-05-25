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
        <div class="max-w-7xl mx-auto p-4">
            
            <!-- Barra superior con diseño guindo -->
            <div class="bg-white rounded-xl shadow-md p-3 mb-4 flex items-center justify-between border-b-2 border-guindo-200">
                <button @click="volver" class="px-4 py-2 bg-guindo-100 text-guindo-700 rounded-lg hover:bg-guindo-200 transition flex items-center gap-2 font-medium">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
                
                <div class="text-center">
                    <span class="block text-xs text-amber-600 font-bold uppercase tracking-wide">Comisionista</span>
                    <span class="text-sm font-semibold text-guindo-800">{{ comisionista || 'Sin comisionista' }}</span>
                    <div class="text-xs text-gray-400 mt-1">
                        <span v-for="(item, idx) in ruta" :key="item.id">
                            <span v-if="idx > 0" class="mx-1">/</span>
                            <span class="font-medium">{{ item.nombre }}</span>
                        </span>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <!-- Botón Cancelar Venta -->
                    <button 
                        @click="cancelarVenta"
                        class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition flex items-center gap-2"
                        title="Cancelar venta actual"
                    >
                        <i class="fas fa-trash-alt"></i>
                        <span class="hidden sm:inline">Cancelar</span>
                    </button>
                    
                    <!-- Botón Carrito con contador mejorado -->
                    <button 
                        @click="irAlCarrito"
                        class="relative px-4 py-2 bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition flex items-center gap-2 font-medium"
                    >
                        <i class="fas fa-shopping-cart text-lg"></i>
                        <span class="hidden sm:inline">Carrito</span>
                        
                        <!-- Contador de items - MEJORADO -->
                        <span 
                            v-if="totalItems > 0" 
                            class="absolute -top-2 -right-2 min-w-[20px] h-5 bg-guindo-600 text-white text-xs rounded-full flex items-center justify-center px-1.5 font-bold shadow-md"
                        >
                            {{ totalItems > 99 ? '99+' : totalItems }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Título principal -->
            <h1 class="text-2xl font-bold text-guindo-800 mb-6 text-center">{{ titulo }}</h1>

            <!-- Grid de categorías -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <div 
                    v-for="cat in categorias" 
                    :key="cat.id_categoria"
                    @click="irACategoria(cat.id_categoria)"
                    class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all hover:scale-105 cursor-pointer overflow-hidden border border-gray-100"
                >
                    <div class="h-32 bg-gradient-to-br from-guindo-50 to-amber-50 flex items-center justify-center p-3">
                        <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover">
                        <i v-else class="fas fa-folder-open text-5xl text-guindo-300"></i>
                    </div>
                    <div class="p-3 text-center">
                        <h3 class="font-bold text-md text-guindo-800">{{ cat.nombre }}</h3>
                        <p class="text-xs text-gray-400 mt-1">Toca para ver</p>
                    </div>
                </div>
            </div>

            <!-- Mensaje si no hay categorías -->
            <div v-if="!categorias.length" class="text-center text-gray-500 py-12">
                <i class="fas fa-folder-open text-5xl mb-3 block text-gray-300"></i>
                <p class="text-lg">No hay categorías disponibles</p>
                <p class="text-sm mt-2">Contacta al administrador del sistema</p>
            </div>

            <!-- Loading overlay -->
            <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl p-6 flex items-center gap-3 shadow-xl">
                    <i class="fas fa-spinner fa-spin text-2xl text-guindo-600"></i>
                    <span class="text-gray-700">Cargando...</span>
                </div>
            </div>
        </div>
    </div>
</template>