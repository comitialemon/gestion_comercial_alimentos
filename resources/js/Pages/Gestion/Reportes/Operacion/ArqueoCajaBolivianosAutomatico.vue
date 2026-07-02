<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursal: {
        type: Object,
        default: null
    },
    operador: {
        type: Object,
        default: null
    },
    fechas: {
        type: Array,
        default: () => []
    },
    fechaDefault: {
        type: Number,
        default: null
    }
})

// ==================== ESTADO ====================
const fechaId = ref(props.fechaDefault || '')
const fechaBusqueda = ref('')
const mostrarFechas = ref(false)
const loading = ref(false)

// ==================== COMPUTADOS ====================
const fechasDisponibles = computed(() => {
    if (!props.fechas) return []
    if (!fechaBusqueda.value) return props.fechas
    
    const termino = fechaBusqueda.value.toLowerCase()
    return props.fechas.filter(f => 
        f.fecha_formateada.toLowerCase().includes(termino)
    )
})

const fechaSeleccionadaTexto = computed(() => {
    if (!fechaId.value) return ''
    const f = props.fechas?.find(f => f.IdFecha === fechaId.value)
    return f?.fecha_formateada || ''
})

const nombreSucursal = computed(() => {
    return props.sucursal?.nombre || 'No definida'
})

const nombreOperador = computed(() => {
    return props.operador?.nombre_completo || 'No definido'
})

// ==================== ACCIONES ====================
const seleccionarFecha = (fecha) => {
    fechaId.value = fecha.IdFecha
    fechaBusqueda.value = fecha.fecha_formateada
    mostrarFechas.value = false
}

const limpiarFecha = () => {
    fechaId.value = ''
    fechaBusqueda.value = ''
    mostrarFechas.value = false
}

// ==================== GENERAR PDF ====================
const generarReporte = () => {
    if (!fechaId.value) {
        alert('Seleccione una fecha')
        return
    }
    
    loading.value = true
    
    window.open(`/operacion/reportes/arqueo-caja-bolivianos-automatico/pdf?fecha_id=${fechaId.value}`, '_blank')    
    setTimeout(() => {
        loading.value = false
    }, 1000)
}

const volver = () => {
    router.get('/oficial')
}

// ==================== CERRAR SUGERENCIAS ====================
const handleClickOutside = (event) => {
    const fechaContainer = document.querySelector('.fecha-autocomplete')
    if (fechaContainer && !fechaContainer.contains(event.target)) {
        mostrarFechas.value = false
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
                            <i class="fas fa-user-check text-base sm:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-gray-800">Arqueo de Caja Bolivianos</h1>
                            <p class="text-xs text-gray-500 hidden sm:block">Reporte automático del operador logueado</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Reporte automático del operador logueado</p>
                </div>

                <!-- Contenedor -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                    <div class="space-y-4">
                        <!-- Sucursal (solo lectura) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-store mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Sucursal
                            </label>
                            <div class="w-full border rounded-lg px-3 py-2 text-sm"
                                 :style="{ 
                                     borderColor: `var(--color-primary-300)`,
                                     backgroundColor: `var(--color-primary-50)`
                                 }">
                                <i class="fas fa-check-circle mr-2" :style="{ color: `var(--color-primary-600)` }"></i>
                                {{ nombreSucursal }}
                            </div>
                        </div>

                        <!-- Operador (solo lectura) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-user mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Operador
                            </label>
                            <div class="w-full border rounded-lg px-3 py-2 text-sm"
                                 :style="{ 
                                     borderColor: `var(--color-primary-300)`,
                                     backgroundColor: `var(--color-primary-50)`
                                 }">
                                <i class="fas fa-check-circle mr-2" :style="{ color: `var(--color-primary-600)` }"></i>
                                {{ nombreOperador }}
                            </div>
                        </div>

                        <!-- Selector de Fecha -->
                        <div class="fecha-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-calendar-alt mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Fecha <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="fechaBusqueda"
                                    @focus="mostrarFechas = true"
                                    @input="mostrarFechas = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Escriba para buscar fecha..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="fechaBusqueda"
                                    @click="limpiarFecha"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarFechas && fechasDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="f in fechasDisponibles" 
                                        :key="f.IdFecha"
                                        @click="seleccionarFecha(f)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="fechaId === f.IdFecha ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="fechaId === f.IdFecha ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <div>
                                            <span class="font-medium text-sm">{{ f.fecha_formateada }}</span>
                                        </div>
                                        <i v-if="fechaId === f.IdFecha" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                                
                                <div v-if="mostrarFechas && fechaBusqueda && fechasDisponibles.length === 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    No se encontraron fechas con "{{ fechaBusqueda }}"
                                </div>
                            </div>
                        </div>

                        <!-- Indicador fecha seleccionada -->
                        <div v-if="fechaId" class="text-xs flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-500">Fecha seleccionada:</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle mr-1 text-xs"></i> {{ fechaSeleccionadaTexto }}
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
                                :disabled="loading || !fechaId"
                                class="px-5 py-2 text-white rounded-lg transition text-sm flex items-center justify-center gap-2 disabled:opacity-50"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-file-pdf"></i>
                                {{ loading ? 'Generando...' : 'Generar PDF' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 rounded-lg text-xs"
                     :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Información:</strong> Este reporte genera automáticamente el arqueo de caja en Bolivianos para el operador y sucursal con los que inició sesión. Seleccione la fecha y presione "Generar PDF".
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