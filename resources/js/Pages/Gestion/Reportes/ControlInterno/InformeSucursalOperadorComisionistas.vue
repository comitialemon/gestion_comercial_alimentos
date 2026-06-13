<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

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

// 🔥 CORREGIDO: Obtener fecha actual en formato local YYYY-MM-DD
const obtenerFechaLocal = () => {
    const hoy = new Date()
    const year = hoy.getFullYear()
    const month = String(hoy.getMonth() + 1).padStart(2, '0')
    const day = String(hoy.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

const fecha = ref(obtenerFechaLocal())

const loading = ref(false)

// ==================== COMPUTADOS ====================
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id === sucursalId.value)
    return suc?.nombre || ''
})

// ==================== ACCIONES ====================
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
}

// ==================== EXPORTAR ====================
const generarReporte = () => {
    if (!sucursalId.value) {
        alert('Seleccione una sucursal')
        return
    }
    if (!fecha.value) {
        alert('Seleccione una fecha')
        return
    }
    
    loading.value = true
    
    window.open(`/gestion/reportes/control-interno/informe-sucursal-operador-comisionistas/exportar?fecha=${fecha.value}&sucursal_id=${sucursalId.value}`, '_blank')
    
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
}

// Lifecycle
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
                            <i class="fas fa-chart-line text-base sm:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-gray-800">Informe de Movimiento Diario Comisionista</h1>
                            <p class="text-xs text-gray-500 hidden sm:block">Seleccione sucursal y fecha para generar el reporte</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Seleccione sucursal y fecha para generar el reporte</p>
                </div>

                <!-- Contenedor único para sucursal y fecha -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                    <div class="space-y-4">
                        <!-- Selector de Sucursal con autocompletado -->
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
                                
                                <!-- Lista de sugerencias -->
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
                                
                                <!-- Mensaje sin resultados -->
                                <div v-if="mostrarSucursales && sucursalBusqueda && sucursalesDisponibles.length === 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    No se encontraron sucursales con "{{ sucursalBusqueda }}"
                                </div>
                            </div>
                        </div>

                        <!-- Indicador de sucursal seleccionada -->
                        <div v-if="sucursalId" class="text-xs flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-500">Sucursal seleccionada:</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle mr-1 text-xs"></i> {{ sucursalNombre }}
                            </span>
                        </div>

                        <!-- Selector de Fecha con calendario nativo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-calendar-alt mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Fecha <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="date" 
                                v-model="fecha"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)` }"
                            />
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
                                :disabled="loading || !sucursalId || !fecha"
                                class="px-5 py-2 text-white rounded-lg transition text-sm flex items-center justify-center gap-2 disabled:opacity-50"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-file-excel"></i>
                                {{ loading ? 'Generando...' : 'Generar Excel' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Focus ring con color dinámico */
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

/* Transiciones */
.transition {
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>