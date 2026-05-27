<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, inject, onMounted } from 'vue'
import NavBarTactil from '../Components/NavBarTactil.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    categorias: Array,
    ruta: Array,
    titulo: String,
    comisionista: String
})

const loading = ref(false)

const irACategoria = (id) => {
    loading.value = true
    router.get(`/venta-tactil/categoria/${id}`, {}, {
        onFinish: () => { loading.value = false }
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="max-w-7xl mx-auto p-3 sm:p-4">
            
            <!-- 🔥 IMPORTACIÓN CORREGIDA -->
            <NavBarTactil 
                :comisionista="comisionista || 'Sin comisionista'"
                :ruta="ruta"
                :mostrar-ruta="true"
                :mostrar-cancelar="true"
            />

            <h1 class="text-xl sm:text-2xl font-bold text-guindo-800 mb-4 sm:mb-6 text-center">{{ titulo }}</h1>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4">
                <div 
                    v-for="cat in categorias" 
                    :key="cat.id_categoria"
                    @click="irACategoria(cat.id_categoria)"
                    class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all hover:scale-105 cursor-pointer overflow-hidden border border-gray-100"
                >
                    <div class="h-28 sm:h-32 bg-gradient-to-br from-guindo-50 to-amber-50 flex items-center justify-center p-2 sm:p-3">
                        <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover">
                        <i v-else class="fas fa-folder-open text-4xl sm:text-5xl text-guindo-300"></i>
                    </div>
                    <div class="p-2 sm:p-3 text-center">
                        <h3 class="font-bold text-sm sm:text-md text-guindo-800">{{ cat.nombre }}</h3>
                        <p class="text-[10px] sm:text-xs text-gray-400 mt-0.5 sm:mt-1">Toca para ver</p>
                    </div>
                </div>
            </div>

            <div v-if="!categorias.length" class="text-center text-gray-500 py-12">
                <i class="fas fa-folder-open text-5xl mb-3 block text-gray-300"></i>
                <p class="text-lg">No hay categorías disponibles</p>
            </div>

            <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl p-6 flex items-center gap-3 shadow-xl">
                    <i class="fas fa-spinner fa-spin text-2xl text-guindo-600"></i>
                    <span class="text-gray-700">Cargando...</span>
                </div>
            </div>
        </div>
    </div>
</template>