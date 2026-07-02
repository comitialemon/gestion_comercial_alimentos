<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    cuentas: Array,
    soloLectura: Boolean,
})

// ==================== ESTADO ====================
const busqueda = ref('')
const tipoCuenta = ref('todos') // todos, B, P
const estadoCuenta = ref('todos') // todos, abiertas, cerradas

// ==================== COMPUTADOS ====================
const cuentasFiltradas = computed(() => {
    let resultado = props.cuentas || []
    
    // Filtro por búsqueda
    if (busqueda.value) {
        const termino = busqueda.value.toLowerCase()
        resultado = resultado.filter(c => 
            c.Cuenta?.toLowerCase().includes(termino) ||
            c.Descripcion?.toLowerCase().includes(termino)
        )
    }
    
    // Filtro por tipo de cuenta
    if (tipoCuenta.value !== 'todos') {
        resultado = resultado.filter(c => c.TipoDeCuenta === tipoCuenta.value)
    }
    
    // Filtro por estado
    if (estadoCuenta.value === 'abiertas') {
        resultado = resultado.filter(c => c.AbiertoCerrado == 0)
    } else if (estadoCuenta.value === 'cerradas') {
        resultado = resultado.filter(c => c.AbiertoCerrado == 1)
    }
    
    return resultado
})

// ==================== ACCIONES ====================
const limpiarFiltros = () => {
    busqueda.value = ''
    tipoCuenta.value = 'todos'
    estadoCuenta.value = 'todos'
}

const irAdministracion = () => {
    router.get('/gestion/contabilidad/cuentas/admin')
}

const volver = () => {
    router.get('/oficial')
}

// Obtener clase de tipo de cuenta
const getTipoClase = (tipo) => {
    return tipo === 'B' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'
}

const getTipoTexto = (tipo) => {
    return tipo === 'B' ? 'Balance' : 'Resultado'
}

const getEstadoClase = (estado) => {
    return estado == 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'
}

const getEstadoTexto = (estado) => {
    return estado == 0 ? 'Abierta' : 'Cerrada'
}
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-4 sm:py-6 px-3 sm:px-4 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center"
                                 :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                <i class="fas fa-chart-line text-base sm:text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-lg sm:text-xl font-bold text-gray-800">Plan de Cuentas</h1>
                                <p class="text-xs text-gray-500 hidden sm:block">Listado de cuentas contables</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Listado de cuentas contables</p>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <!-- Búsqueda -->
                        <div class="flex-1">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input 
                                    type="text" 
                                    v-model="busqueda" 
                                    placeholder="Buscar por número o descripción..."
                                    class="w-full pl-9 pr-8 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:outline-none"
                                    :style="{ focusRingColor: `var(--color-primary-500)` }"
                                />
                                <button 
                                    v-if="busqueda" 
                                    @click="busqueda = ''"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Tipo de Cuenta -->
                        <div class="w-full sm:w-40">
                            <select v-model="tipoCuenta" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="todos">Todos los tipos</option>
                                <option value="B">Balance (B)</option>
                                <option value="P">Resultado (P)</option>
                            </select>
                        </div>

                        <!-- Estado -->
                        <div class="w-full sm:w-40">
                            <select v-model="estadoCuenta" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="todos">Todos los estados</option>
                                <option value="abiertas">Abiertas</option>
                                <option value="cerradas">Cerradas</option>
                            </select>
                        </div>

                        <!-- Limpiar -->
                        <button 
                            @click="limpiarFiltros"
                            class="px-4 py-2 text-sm rounded-lg transition bg-gray-100 text-gray-600 hover:bg-gray-200"
                        >
                            <i class="fas fa-eraser mr-1"></i> Limpiar
                        </button>
                    </div>

                    <!-- Contador de resultados -->
                    <div class="mt-3 text-xs text-gray-500">
                        Mostrando {{ cuentasFiltradas.length }} de {{ cuentas?.length || 0 }} cuentas
                    </div>
                </div>

                <!-- Tabla de cuentas -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Desktop -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cuenta</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Moneda</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Activo Fijo</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="cuenta in cuentasFiltradas" :key="cuenta.IdCuenta" class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <span class="font-mono font-bold text-sm" :style="{ color: `var(--color-primary-700)` }">
                                            {{ cuenta.Cuenta }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ cuenta.Descripcion }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="getTipoClase(cuenta.TipoDeCuenta)">
                                            {{ getTipoTexto(cuenta.TipoDeCuenta) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-500">
                                        {{ cuenta.moneda?.Abreviacion || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="getEstadoClase(cuenta.AbiertoCerrado)">
                                            {{ getEstadoTexto(cuenta.AbiertoCerrado) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <i v-if="cuenta.ActivoFijo == 1" class="fas fa-check-circle text-emerald-500"></i>
                                        <i v-else class="fas fa-circle text-gray-300 text-xs"></i>
                                     </td>
                                 </tr>
                                <tr v-if="!cuentasFiltradas.length">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                        <i class="fas fa-search text-3xl mb-2 block"></i>
                                        No se encontraron cuentas
                                     </td>
                                 </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile (Cards) -->
                    <div class="md:hidden divide-y divide-gray-100">
                        <div v-for="cuenta in cuentasFiltradas" :key="cuenta.IdCuenta" class="p-4 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-mono font-bold text-base" :style="{ color: `var(--color-primary-700)` }">
                                    {{ cuenta.Cuenta }}
                                </span>
                                <span class="px-2 py-0.5 text-xs rounded-full" :class="getTipoClase(cuenta.TipoDeCuenta)">
                                    {{ getTipoTexto(cuenta.TipoDeCuenta) }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-700 mb-2">{{ cuenta.Descripcion }}</div>
                            <div class="flex justify-between items-center text-xs">
                                <div class="flex gap-3">
                                    <span class="text-gray-500">Moneda:</span>
                                    <span>{{ cuenta.moneda?.Abreviacion || '-' }}</span>
                                </div>
                                <div class="flex gap-3">
                                    <span class="text-gray-500">Estado:</span>
                                    <span :class="getEstadoClase(cuenta.AbiertoCerrado)" class="px-2 py-0.5 rounded-full">
                                        {{ getEstadoTexto(cuenta.AbiertoCerrado) }}
                                    </span>
                                </div>
                                <div v-if="cuenta.ActivoFijo == 1" class="text-emerald-500">
                                    <i class="fas fa-check-circle"></i> Activo Fijo
                                </div>
                            </div>
                        </div>
                        <div v-if="!cuentasFiltradas.length" class="p-8 text-center text-gray-400">
                            <i class="fas fa-search text-3xl mb-2 block"></i>
                            No se encontraron cuentas
                        </div>
                    </div>
                </div>

                <!-- Botón volver -->
                <div class="mt-6 flex justify-end">
                    <button 
                        @click="volver"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-sm"
                    >
                        Volver al inicio
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:focus, select:focus {
    --tw-ring-color: var(--color-primary-500);
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

.transition {
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>