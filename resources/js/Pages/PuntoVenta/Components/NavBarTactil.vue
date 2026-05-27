<script setup>
import { router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, inject } from 'vue'
import axios from 'axios'
import CancelarModal from '../MenuTactil/Components/CancelarModal.vue'

const toast = inject('toast')
const props = defineProps({
    comisionista: {
        type: String,
        default: 'Sin comisionista'
    },
    ruta: {
        type: Array,
        default: () => []
    },
    mostrarRuta: {
        type: Boolean,
        default: true
    },
    mostrarCancelar: {
        type: Boolean,
        default: true
    },
    volverUrl: {
        type: String,
        default: null
    }
})

// Estado
const totalItems = ref(0)
const cargandoCarrito = ref(false)
const isMobile = ref(window.innerWidth < 768)
const modalCancelarVisible = ref(false)

// Detectar cambios de tamaño
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

// Cargar total de items del carrito
const cargarTotalItems = async () => {
    cargandoCarrito.value = true
    try {
        const response = await axios.get('/api/venta-tactil/carrito')
        if (response.data?.success) {
            totalItems.value = response.data.items.reduce((sum, item) => sum + item.unidades, 0)
        }
    } catch (error) {
        console.error('Error cargando carrito:', error)
    } finally {
        cargandoCarrito.value = false
    }
}

// Abrir modal de cancelación
const abrirModalCancelar = () => {
    modalCancelarVisible.value = true
}

// Cancelar venta (se ejecuta después de confirmar en el modal)
const cancelarVenta = async () => {
    modalCancelarVisible.value = false
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

// Volver atrás
const volver = () => {
    if (props.volverUrl) {
        router.get(props.volverUrl)
    } else {
        window.history.back()
    }
}

// Ir al carrito
const irAlCarrito = () => router.get('/venta-tactil/carrito')

// Actualizar carrito periódicamente
let intervalo
onMounted(() => {
    cargarTotalItems()
    window.addEventListener('resize', handleResize)
    intervalo = setInterval(cargarTotalItems, 5000)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    if (intervalo) clearInterval(intervalo)
})
</script>

<template>
    <div class="bg-white rounded-xl shadow-md p-2 sm:p-3 mb-4 flex flex-wrap items-center justify-between gap-2 border-b-2 border-guindo-200">
        
        <!-- Botón Volver -->
        <button 
            @click="volver" 
            class="px-2 sm:px-4 py-1.5 sm:py-2 bg-guindo-100 text-guindo-700 rounded-lg hover:bg-guindo-200 transition flex items-center gap-1 sm:gap-2 font-medium text-xs sm:text-sm"
        >
            <i class="fas fa-arrow-left text-xs sm:text-sm"></i>
            <span class="hidden xs:inline">Volver</span>
        </button>
        
        <!-- Información central (comisionista y ruta) -->
        <div class="text-center flex-1 min-w-0 px-2">
            <span class="block text-[10px] sm:text-xs text-amber-600 font-bold uppercase tracking-wide">Comisionista</span>
            <span class="text-xs sm:text-sm font-semibold text-guindo-800 truncate block max-w-[150px] sm:max-w-[250px] mx-auto">
                {{ comisionista }}
            </span>
            <div v-if="mostrarRuta && ruta.length" class="text-[10px] sm:text-xs text-gray-400 mt-0.5 truncate max-w-[200px] sm:max-w-[300px] mx-auto">
                <span v-for="(item, idx) in ruta" :key="item.id">
                    <span v-if="idx > 0" class="mx-0.5 sm:mx-1">/</span>
                    <span class="font-medium">{{ item.nombre }}</span>
                </span>
            </div>
        </div>
        
        <!-- Botones de acción (derecha) -->
        <div class="flex items-center gap-1 sm:gap-2">
            <!-- Botón Cancelar Venta -->
            <button 
                v-if="mostrarCancelar"
                @click="abrirModalCancelar"
                class="px-2 sm:px-3 py-1.5 sm:py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition flex items-center gap-1 sm:gap-2 text-xs sm:text-sm"
                title="Cancelar venta actual"
            >
                <i class="fas fa-trash-alt text-xs sm:text-sm"></i>
                <span class="hidden xs:inline">Cancelar</span>
            </button>
            
            <!-- Botón Carrito -->
            <button 
                @click="irAlCarrito"
                class="relative px-2 sm:px-4 py-1.5 sm:py-2 bg-amber-400 hover:bg-amber-500 transition rounded-lg flex items-center gap-1 sm:gap-2 font-semibold shadow-md"
                :class="{ 'bg-amber-500': totalItems > 0 }"
            >
                <i class="fas fa-shopping-cart text-sm sm:text-lg" :class="{ 'text-guindo-800': totalItems > 0, 'text-amber-800': totalItems === 0 }"></i>
                <span class="text-xs sm:text-sm font-medium" :class="{ 'text-guindo-800': totalItems > 0, 'text-amber-800': totalItems === 0 }">
                    Carrito
                </span>
                
                <!-- Contador de items -->
                <span 
                    v-if="totalItems > 0" 
                    class="absolute -top-2 -right-2 min-w-[20px] sm:min-w-[24px] h-5 sm:h-6 bg-guindo-600 text-white text-[10px] sm:text-xs rounded-full flex items-center justify-center px-1 sm:px-1.5 font-bold shadow-lg border-2 border-white"
                >
                    {{ totalItems > 99 ? '99+' : totalItems }}
                </span>
            </button>
        </div>
    </div>

    <!-- 🔥 MODAL DE CANCELACIÓN -->
    <CancelarModal
        v-model="modalCancelarVisible"
        :total-items="totalItems"
        @confirm="cancelarVenta"
        @cancel="modalCancelarVisible = false"
    />
</template>

<style scoped>
@media (min-width: 480px) {
    .xs\:inline {
        display: inline;
    }
}
</style>