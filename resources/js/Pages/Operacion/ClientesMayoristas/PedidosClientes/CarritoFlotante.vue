<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    items: {
        type: Array,
        default: () => []
    },
    totalUnidades: {
        type: Number,
        default: 0
    },
    totalContenedores: {
        type: Number,
        default: 0
    },
    onVaciar: {
        type: Function,
        default: null
    },
    onFinalizar: {
        type: Function,
        default: null
    }
})

const emit = defineEmits(['vaciar', 'finalizar'])

const hayProductos = computed(() => {
    return props.items.length > 0
})

const vaciarCarrito = () => {
    if (props.onVaciar) {
        props.onVaciar()
    } else {
        emit('vaciar')
    }
}

const finalizarPedido = () => {
    if (props.onFinalizar) {
        props.onFinalizar()
    } else {
        emit('finalizar')
    }
}

const irAlCarrito = () => {
    // Si hay un carrito, podemos ir a una vista de detalle o hacer scroll
    const carritoSection = document.querySelector('.carrito-section')
    if (carritoSection) {
        carritoSection.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
}
</script>

<template>
    <!-- Carrito Flotante - Estilo Punto de Venta -->
    <div 
        v-if="hayProductos"
        class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 w-[95%] max-w-md"
    >
        <div 
            class="bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden animate-slide-up"
            style="box-shadow: 0 10px 40px rgba(0,0,0,0.15);"
        >
            <!-- Barra superior -->
            <div 
                class="flex items-center justify-between px-4 py-2.5"
                :class="totalUnidades > 0 ? 'bg-gradient-to-r from-primary-600 to-primary-700' : 'bg-gray-100'"
            >
                <div class="flex items-center gap-3 text-white">
                    <div class="relative">
                        <i class="fas fa-shopping-cart text-lg"></i>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[9px] font-bold rounded-full w-5 h-5 flex items-center justify-center">
                            {{ items.length }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-medium opacity-90">Mi Pedido</p>
                        <p class="text-sm font-bold">
                            {{ totalUnidades }} unidades
                            <span class="text-xs font-normal opacity-80 ml-1">
                                ({{ totalContenedores }} contenedores)
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Botón Ver Carrito -->
                    <button 
                        @click="irAlCarrito"
                        class="px-3 py-1 bg-white/20 hover:bg-white/30 rounded-lg text-white text-xs transition flex items-center gap-1"
                    >
                        <i class="fas fa-eye text-[10px]"></i>
                        Ver
                    </button>
                    <!-- Botón Finalizar (principal) -->
                    <button 
                        @click="finalizarPedido"
                        class="px-4 py-1.5 bg-green-500 hover:bg-green-600 rounded-lg text-white text-xs font-bold transition flex items-center gap-1 shadow-lg"
                    >
                        <i class="fas fa-check-circle text-[10px]"></i>
                        Finalizar
                    </button>
                </div>
            </div>

            <!-- Mini preview del carrito -->
            <div class="px-4 py-2 bg-gray-50 border-t max-h-20 overflow-y-auto">
                <div 
                    v-for="(item, idx) in items.slice(0, 3)" 
                    :key="idx"
                    class="flex justify-between items-center text-xs py-0.5 border-b border-gray-100 last:border-0"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-mono text-[10px] text-gray-400 bg-gray-200 px-1.5 rounded">#{{ idx + 1 }}</span>
                        <span class="font-medium text-gray-700 truncate">{{ item.Nombre || item.nombre }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500">{{ item.productos?.length || item.productos?.length || 0 }} prod</span>
                        <span class="font-bold text-primary-600">{{ (item.total_unidades || item.total_unidades || 0).toFixed(0) }} und</span>
                    </div>
                </div>
                <div v-if="items.length > 3" class="text-[10px] text-gray-400 text-center py-0.5">
                    + {{ items.length - 3 }} más...
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translate(-50%, 20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translate(-50%, 0) scale(1);
    }
}

.animate-slide-up {
    animation: slideUp 0.3s ease-out;
}
</style>