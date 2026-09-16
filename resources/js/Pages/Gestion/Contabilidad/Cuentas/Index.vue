<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    cuentas: Array,
    soloLectura: Boolean,
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const busqueda = ref('')
const tipoCuenta = ref('todos')
const estadoCuenta = ref('todos')

// ==================== COMPUTED ====================
const cuentasFiltradas = computed(() => {
    let resultado = props.cuentas || []
    
    if (busqueda.value) {
        const termino = busqueda.value.toLowerCase()
        resultado = resultado.filter(c => 
            c.Cuenta?.toLowerCase().includes(termino) ||
            c.Descripcion?.toLowerCase().includes(termino)
        )
    }
    
    if (tipoCuenta.value !== 'todos') {
        resultado = resultado.filter(c => c.TipoDeCuenta === tipoCuenta.value)
    }
    
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

const getTipoClase = (tipo) => {
    return tipo === 'B' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'
}

const getTipoTexto = (tipo) => {
    return tipo === 'B' ? 'Balance' : 'Resultado'
}

const getEstadoClase = (estado) => {
    return estado == 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'
}

const getEstadoTexto = (estado) => {
    return estado == 0 ? 'Abierta' : 'Cerrada'
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-chart-line text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Plan de Cuentas</h1>
                            <p class="text-xs text-gray-500">Listado de cuentas contables</p>
                        </div>
                    </div>
                </div>

                <!-- ==================== FILTROS COMPACTOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Búsqueda -->
                        <div class="flex-1 min-w-[180px] max-w-[280px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                <input 
                                    type="text" 
                                    v-model="busqueda" 
                                    placeholder="Número o descripción..."
                                    class="w-full border border-gray-300 rounded-md pl-7 pr-7 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                />
                                <button 
                                    v-if="busqueda" 
                                    @click="busqueda = ''"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Tipo de Cuenta -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Tipo</label>
                            <select v-model="tipoCuenta" class="w-36 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="todos">Todos</option>
                                <option value="B">Balance (B)</option>
                                <option value="P">Resultado (P)</option>
                            </select>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Estado</label>
                            <select v-model="estadoCuenta" class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="todos">Todos</option>
                                <option value="abiertas">Abiertas</option>
                                <option value="cerradas">Cerradas</option>
                            </select>
                        </div>

                        <!-- Limpiar -->
                        <div class="flex gap-1.5 ml-auto">
                            <button 
                                @click="limpiarFiltros"
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1"
                            >
                                <i class="fas fa-eraser text-[10px]"></i> Limpiar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Contador -->
                    <div class="mt-2 text-[10px] text-gray-500">
                        Mostrando <strong>{{ cuentasFiltradas.length }}</strong> de <strong>{{ cuentas?.length || 0 }}</strong> cuentas
                    </div>
                </div>

                <!-- ==================== TABLA DE CUENTAS ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="cuenta in cuentasFiltradas" :key="cuenta.IdCuenta" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start mb-1.5">
                                    <span class="font-mono font-bold text-sm text-primary-700">{{ cuenta.Cuenta }}</span>
                                    <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="getTipoClase(cuenta.TipoDeCuenta)">
                                        {{ getTipoTexto(cuenta.TipoDeCuenta) }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-700 mb-1.5">{{ cuenta.Descripcion }}</p>
                                <div class="flex flex-wrap justify-between items-center gap-2 text-[10px] pt-1.5 border-t border-gray-200">
                                    <span class="text-gray-500">Moneda: <strong>{{ cuenta.moneda?.Abreviacion || '-' }}</strong></span>
                                    <span :class="getEstadoClase(cuenta.AbiertoCerrado)" class="px-1.5 py-0.5 rounded-full">
                                        {{ getEstadoTexto(cuenta.AbiertoCerrado) }}
                                    </span>
                                    <span v-if="cuenta.ActivoFijo == 1" class="text-emerald-600">
                                        <i class="fas fa-check-circle"></i> Activo Fijo
                                    </span>
                                </div>
                            </div>
                            <div v-if="!cuentasFiltradas.length" class="text-center text-gray-400 py-8">
                                <i class="fas fa-search text-2xl mb-1 block"></i>
                                <span class="text-xs">No se encontraron cuentas</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Cuenta</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Descripción</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-24">Tipo</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-20">Moneda</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-24">Estado</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-24">Activo Fijo</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="cuenta in cuentasFiltradas" :key="cuenta.IdCuenta" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2">
                                        <span class="font-mono font-bold text-xs text-primary-700">{{ cuenta.Cuenta }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-700 truncate max-w-[250px]" :title="cuenta.Descripcion">{{ cuenta.Descripcion }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="getTipoClase(cuenta.TipoDeCuenta)">
                                            {{ getTipoTexto(cuenta.TipoDeCuenta) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center text-xs text-gray-500">
                                        {{ cuenta.moneda?.Abreviacion || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="getEstadoClase(cuenta.AbiertoCerrado)">
                                            {{ getEstadoTexto(cuenta.AbiertoCerrado) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <i v-if="cuenta.ActivoFijo == 1" class="fas fa-check-circle text-emerald-500 text-sm"></i>
                                        <i v-else class="fas fa-circle text-gray-300 text-[6px]"></i>
                                    </td>
                                </tr>
                                <tr v-if="!cuentasFiltradas.length">
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-search text-2xl mb-1 block"></i>
                                        No se encontraron cuentas
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== BOTÓN VOLVER ==================== -->
                <div class="flex justify-end pt-3 mt-3">
                    <button 
                        type="button"
                        @click="volver"
                        class="px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-100 transition text-xs font-medium flex items-center gap-1.5"
                    >
                        <i class="fas fa-arrow-left text-[10px]"></i> Volver al inicio
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>