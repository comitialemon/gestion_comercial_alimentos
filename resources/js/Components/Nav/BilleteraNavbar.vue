<script setup>
import { computed, watch, ref } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useBilletera } from '@/Composables/useBilletera'

const page = usePage()
const { saldoMasReciente } = useBilletera()

// ============================================================
// FUENTE DEL SALDO: primero polling, luego Inertia
// ============================================================
const billetera = computed(() => {
    // Si hay polling activo con datos, usar esos
    if (saldoMasReciente.value) return saldoMasReciente.value
    // Fallback: lo que viene de Inertia
    return page.props.billetera || null
})

const tieneBilletera = computed(() => billetera.value?.tiene_billetera === true)
const estaActiva = computed(() => billetera.value?.activa === true)
const saldoBajo = computed(() => billetera.value?.saldo_bajo === true)
const saldoFormateado = computed(() => billetera.value?.saldo_formateado || '0,00')

// ============================================================
// ESTILOS SEGÚN ESTADO
// ============================================================
const clasesBoton = computed(() => {
    if (!estaActiva.value) {
        // Inactiva: gris, apagado
        return 'bg-gray-500/40 text-gray-200 cursor-not-allowed'
    }
    if (saldoBajo.value) {
        // Saldo bajo: rojo con animación sutil
        return 'bg-red-500/40 text-red-50 hover:bg-red-500/50'
    }
    // Activa normal: blanco translúcido
    return 'bg-white/15 text-white hover:bg-white/25'
})

const tooltip = computed(() => {
    if (!estaActiva.value) {
        return 'Billetera inactiva — contacta al encargado'
    }
    if (saldoBajo.value) {
        return '⚠️ Saldo bajo. Recarga pronto.'
    }
    return 'Ver mi billetera'
})

// ============================================================
// ANIMACIÓN CUANDO EL SALDO CAMBIA
// ============================================================
const animacion = ref(null) // 'subio' | 'bajo' | null

watch(() => billetera.value?.saldo, (nuevo, viejo) => {
    if (viejo === undefined || nuevo === undefined) return
    if (nuevo === viejo) return

    if (nuevo > viejo) {
        animacion.value = 'subio'
    } else if (nuevo < viejo) {
        animacion.value = 'bajo'
    }

    setTimeout(() => {
        animacion.value = null
    }, 1500)
})

// ============================================================
// NAVEGAR A MI BILLETERA
// ============================================================
const irAMiBilletera = () => {
    if (!estaActiva.value) return
    router.visit('/operacion/pedidos/clientes-mayoristas/mi-billetera')
}
</script>

<template>
    <button
        v-if="tieneBilletera"
        @click="irAMiBilletera"
        :disabled="!estaActiva"
        :title="tooltip"
        class="flex items-center gap-2 px-3 py-1.5 rounded-full transition-all duration-300 flex-shrink-0"
        :class="[
            clasesBoton,
            animacion === 'subio' ? 'ring-2 ring-green-400 shadow-lg shadow-green-500/50' : '',
            animacion === 'bajo' ? 'ring-2 ring-red-400 shadow-lg shadow-red-500/50' : '',
        ]"
    >
        <!-- Ícono -->
        <i 
            :class="estaActiva ? 'fas fa-wallet' : 'fas fa-lock'"
            class="text-sm sm:text-base"
        ></i>

        <!-- Texto desktop -->
        <div class="hidden sm:flex flex-col leading-tight text-left">
            <span class="text-[9px] uppercase tracking-wide opacity-80 font-medium">
                Saldo disponible
            </span>
            <span class="text-xs sm:text-sm font-bold whitespace-nowrap">
                Bs. {{ saldoFormateado }}
            </span>
        </div>

        <!-- Texto mobile (compacto) -->
        <span class="sm:hidden text-xs font-bold whitespace-nowrap">
            Bs. {{ saldoFormateado }}
        </span>
    </button>
</template>

<style scoped>
button {
    transition: all 0.2s ease;
}

/* Animación cuando el saldo sube */
@keyframes pulse-green {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

button.ring-green-400 {
    animation: pulse-green 0.5s ease-in-out;
}
</style>