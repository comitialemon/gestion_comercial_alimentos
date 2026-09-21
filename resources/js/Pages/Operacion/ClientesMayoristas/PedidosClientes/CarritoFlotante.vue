<script setup>
import { computed } from 'vue'

const props = defineProps({
    items: { type: Array, default: () => [] },
    totalUnidades: { type: Number, default: 0 },
    totalContenedores: { type: Number, default: 0 },
    cumpleMinimos: { type: Boolean, default: true },
    gruposFaltantes: { type: Array, default: () => [] },
    tipoPrecio: { type: String, default: 'sin_factura' } // ✅ NUEVO
})

const emit = defineEmits(['finalizar'])

const hayProductos = computed(() => props.items.length > 0)

const tipoPrecioTexto = computed(() => {
    return props.tipoPrecio === 'con_factura' ? 'Con Factura' : 'Sin Factura'
})

const finalizarPedido = () => {
    emit('finalizar')
}

const irAlCarrito = () => {
    const carritoSection = document.querySelector('.carrito-section')
    if (carritoSection) {
        carritoSection.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
}
</script>

<template>
    <div v-if="hayProductos" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 w-[95%] max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden animate-slide-up"
             style="box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
            
            <!-- Barra superior -->
            <div 
                class="flex items-center justify-between px-4 py-2.5 transition-colors"
                :class="cumpleMinimos 
                    ? 'bg-gradient-to-r from-green-600 to-emerald-700' 
                    : 'bg-gradient-to-r from-orange-500 to-amber-600'"
            >
                <div class="flex items-center gap-3 text-white">
                    <div class="relative">
                        <i class="fas fa-shopping-cart text-lg"></i>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[9px] font-bold rounded-full w-5 h-5 flex items-center justify-center border border-white">
                            {{ items.length }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-medium opacity-90">
                            {{ cumpleMinimos ? 'Todo listo' : 'Faltan mínimos' }}
                        </p>
                        <p class="text-sm font-bold">
                            {{ totalUnidades }} unidades
                            <span class="text-xs font-normal opacity-80 ml-1">
                                ({{ totalContenedores }} cont.)
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <!-- ✅ Badge tipo precio -->
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold"
                        :class="tipoPrecio === 'con_factura' 
                            ? 'bg-blue-400/30 text-white' 
                            : 'bg-white/20 text-white'">
                        {{ tipoPrecioTexto }}
                    </span>
                    <button @click="irAlCarrito" class="px-3 py-1 bg-white/20 hover:bg-white/30 rounded-lg text-white text-xs transition flex items-center gap-1">
                        <i class="fas fa-eye text-[10px]"></i>
                        Ver
                    </button>
                    <button 
                        @click="finalizarPedido"
                        :disabled="!cumpleMinimos"
                        class="px-4 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-lg"
                        :class="cumpleMinimos 
                            ? 'bg-white text-green-700 hover:bg-gray-100' 
                            : 'bg-white/30 text-white cursor-not-allowed'"
                    >
                        <i class="fas fa-check-circle text-[10px]"></i>
                        Revisar
                    </button>
                </div>
            </div>

            <!-- Alertas de grupos faltantes -->
            <div v-if="!cumpleMinimos && gruposFaltantes.length > 0" class="px-4 py-2 bg-orange-50 border-t border-orange-200">
                <p class="text-[10px] font-semibold text-orange-700 mb-1">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Faltan cumplir:
                </p>
                <div class="space-y-0.5 max-h-16 overflow-y-auto">
                    <div v-for="grupo in gruposFaltantes.slice(0, 3)" :key="grupo.IdGrupoAnalisis" class="flex justify-between items-center text-[10px] text-orange-700">
                        <span class="truncate">{{ grupo.NombreGrupo }}</span>
                        <span class="font-bold whitespace-nowrap ml-2">faltan {{ grupo.Falta }} und</span>
                    </div>
                </div>
            </div>

            <!-- Mini preview del carrito -->
            <div class="px-4 py-2 bg-gray-50 border-t max-h-20 overflow-y-auto">
                <div v-for="(item, idx) in items.slice(0, 3)" :key="idx" class="flex justify-between items-center text-xs py-0.5 border-b border-gray-100 last:border-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-mono text-[10px] text-gray-400 bg-gray-200 px-1.5 rounded">#{{ idx + 1 }}</span>
                        <span class="font-medium text-gray-700 truncate">{{ item.Codigo }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500">{{ item.productos?.length || 0 }} prod</span>
                        <span class="font-bold text-primary-600">{{ (item.total_unidades || 0).toFixed(0) }} und</span>
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
    from { opacity: 0; transform: translate(-50%, 20px) scale(0.95); }
    to { opacity: 1; transform: translate(-50%, 0) scale(1); }
}
.animate-slide-up {
    animation: slideUp 0.3s ease-out;
}
</style>