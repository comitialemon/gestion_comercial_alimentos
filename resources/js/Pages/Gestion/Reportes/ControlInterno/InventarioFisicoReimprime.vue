<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: {
        type: Array,
        default: () => []
    }
})

// ==================== ESTADO ====================
const sucursalId = ref('')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const correlativoId = ref('')
const correlativoBusqueda = ref('')
const mostrarCorrelativos = ref(false)
const correlativosDisponibles = ref([])

const loading = ref(false)

// ==================== COMPUTADOS ====================
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre.toLowerCase().includes(termino)
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id === sucursalId.value)
    return suc?.nombre || ''
})

const correlativoSeleccionadoTexto = computed(() => {
    if (!correlativoId.value) return ''
    const corr = correlativosDisponibles.value?.find(c => c.IdFisico == correlativoId.value)
    return corr ? `No. ${corr.NumeroCorrelativo}` : ''
})

// ==================== ACCIONES ====================
const seleccionarSucursal = async (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    
    // Limpiar correlativo anterior
    correlativoId.value = ''
    correlativoBusqueda.value = ''
    correlativosDisponibles.value = []
    
    // Cargar correlativos de la sucursal seleccionada
    try {
        const response = await axios.get(`/gestion/reportes/control-interno/inventario-fisico-reimprime/correlativos?sucursal_id=${sucursalId.value}`)
        if (response.data.success) {
            correlativosDisponibles.value = response.data.correlativos
        }
    } catch (error) {
        console.error('Error cargando correlativos:', error)
        correlativosDisponibles.value = []
    }
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    correlativoId.value = ''
    correlativoBusqueda.value = ''
    correlativosDisponibles.value = []
}

const seleccionarCorrelativo = (correlativo) => {
    correlativoId.value = correlativo.IdFisico
    correlativoBusqueda.value = `No. ${correlativo.NumeroCorrelativo}`
    mostrarCorrelativos.value = false
}

const limpiarCorrelativo = () => {
    correlativoId.value = ''
    correlativoBusqueda.value = ''
    mostrarCorrelativos.value = false
}

// ==================== GENERAR PDF ====================
const generarReporte = () => {
    if (!sucursalId.value) {
        alert('Seleccione una sucursal')
        return
    }
    if (!correlativoId.value) {
        alert('Seleccione un número correlativo')
        return
    }
    
    loading.value = true
    
    window.open(`/gestion/reportes/control-interno/inventario-fisico-reimprime/pdf?id_fisico=${correlativoId.value}`, '_blank')
    
    setTimeout(() => {
        loading.value = false
    }, 1000)
}

const volver = () => {
    router.get('/oficial')
}

// ==================== CERRAR SUGERENCIAS ====================
const handleClickOutside = (event) => {
    const sucursalContainer = document.querySelector('.sucursal-autocomplete')
    if (sucursalContainer && !sucursalContainer.contains(event.target)) {
        mostrarSucursales.value = false
    }
    
    const correlativoContainer = document.querySelector('.correlativo-autocomplete')
    if (correlativoContainer && !correlativoContainer.contains(event.target)) {
        mostrarCorrelativos.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-4 sm:py-6 px-3 sm:px-4 lg:px-8">
            <div class="max-w-2xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center"
                             :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                            <i class="fas fa-boxes text-base sm:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-gray-800">Reimpresión de Inventario Físico</h1>
                            <p class="text-xs text-gray-500 hidden sm:block">Seleccione sucursal y número correlativo para reimprimir</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Seleccione sucursal y número correlativo para reimprimir</p>
                </div>

                <!-- Contenedor único -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                    <div class="space-y-4">
                        <!-- Selector de Sucursal -->
                        <div class="sucursal-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-store mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Sucursal <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="sucursalBusqueda"
                                    @focus="mostrarSucursales = true"
                                    @input="mostrarSucursales = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Escriba para buscar sucursal..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="sucursalBusqueda"
                                    @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="suc in sucursalesDisponibles" 
                                        :key="suc.id"
                                        @click="seleccionarSucursal(suc)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="sucursalId === suc.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="sucursalId === suc.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <div>
                                            <span class="font-medium text-sm">{{ suc.nombre }}</span>
                                            <span v-if="suc.numero" class="text-xs text-gray-400 ml-2">(N° {{ suc.numero }})</span>
                                        </div>
                                        <i v-if="sucursalId === suc.id" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Indicador sucursal seleccionada -->
                        <div v-if="sucursalId" class="text-xs flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-500">Sucursal:</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle mr-1 text-xs"></i> {{ sucursalNombre }}
                            </span>
                        </div>

                        <!-- Selector de Número Correlativo (solo si hay sucursal seleccionada) -->
                        <div v-if="sucursalId" class="correlativo-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-hashtag mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Número Correlativo <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="correlativoBusqueda"
                                    @focus="mostrarCorrelativos = true"
                                    @input="mostrarCorrelativos = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Seleccione el número correlativo..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="correlativoBusqueda"
                                    @click="limpiarCorrelativo"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarCorrelativos && correlativosDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="corr in correlativosDisponibles" 
                                        :key="corr.IdFisico"
                                        @click="seleccionarCorrelativo(corr)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="correlativoId === corr.IdFisico ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="correlativoId === corr.IdFisico ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <div>
                                            <span class="font-medium text-sm">No. {{ corr.NumeroCorrelativo }}</span>
                                        </div>
                                        <i v-if="correlativoId === corr.IdFisico" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                                
                                <div v-if="mostrarCorrelativos && correlativosDisponibles.length === 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    No hay inventarios físicos registrados en esta sucursal
                                </div>
                            </div>
                        </div>

                        <!-- Indicador correlativo seleccionado -->
                        <div v-if="correlativoId" class="text-xs flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-500">Correlativo:</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle mr-1 text-xs"></i> {{ correlativoSeleccionadoTexto }}
                            </span>
                        </div>

                        <!-- Botones -->
                        <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t mt-4">
                            <button 
                                type="button"
                                @click="volver"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-sm"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="button"
                                @click="generarReporte"
                                :disabled="loading || !sucursalId || !correlativoId"
                                class="px-5 py-2 text-white rounded-lg transition text-sm flex items-center justify-center gap-2 disabled:opacity-50"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-file-pdf"></i>
                                {{ loading ? 'Generando...' : 'Reimprimir' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 rounded-lg text-xs"
                     :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Información:</strong> Este reporte permite reimprimir un inventario físico ya contabilizado. 
                    Seleccione la sucursal y el número correlativo del inventario que desea reimprimir.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:focus {
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