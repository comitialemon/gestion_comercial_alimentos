<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: {
        type: Array,
        default: () => []
    },
    operadores: {
        type: Array,
        default: () => []
    },
    fechas: {
        type: Array,
        default: () => []
    }
})

// ==================== ESTADO ====================
const sucursalId = ref('')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const operadorId = ref('')
const operadorBusqueda = ref('')
const mostrarOperadores = ref(false)
const operadoresFiltrados = ref([])
const cargandoOperadores = ref(false)

const fechaId = ref('')
const fechaBusqueda = ref('')
const mostrarFechas = ref(false)

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

const operadoresDisponibles = computed(() => {
    if (sucursalId.value) {
        const lista = operadoresFiltrados.value
        if (!operadorBusqueda.value) return lista
        
        const termino = operadorBusqueda.value.toLowerCase()
        return lista.filter(o => 
            o.nombre_completo?.toLowerCase().includes(termino)
        )
    }
    return []
})

const fechasDisponibles = computed(() => {
    if (!props.fechas) return []
    if (!fechaBusqueda.value) return props.fechas
    
    const termino = fechaBusqueda.value.toLowerCase()
    return props.fechas.filter(f => 
        f.fecha_formateada.toLowerCase().includes(termino)
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id === sucursalId.value)
    return suc?.nombre || ''
})

const operadorNombre = computed(() => {
    if (!operadorId.value) return ''
    const op = operadoresDisponibles.value?.find(o => o.id === operadorId.value)
    return op?.nombre_completo || ''
})

const fechaSeleccionadaTexto = computed(() => {
    if (!fechaId.value) return ''
    const f = props.fechas?.find(f => f.IdFecha === fechaId.value)
    return f?.fecha_formateada || ''
})

// ==================== ACCIONES ====================
const seleccionarSucursal = async (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    
    // Limpiar operador seleccionado
    operadorId.value = ''
    operadorBusqueda.value = ''
    
    // Cargar operadores de la sucursal
    await cargarOperadoresPorSucursal(sucursal.id)
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    operadoresFiltrados.value = []
    operadorId.value = ''
    operadorBusqueda.value = ''
}

const cargarOperadoresPorSucursal = async (sucursalIdParam) => {
    if (!sucursalIdParam) {
        operadoresFiltrados.value = []
        return
    }
    
    cargandoOperadores.value = true
    
    try {
        const response = await fetch(`/gestion/reportes/control-interno/arqueo-caja-chica-ci/operadores?sucursal_id=${sucursalIdParam}`)
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }
        
        const data = await response.json()
        operadoresFiltrados.value = data || []
        
        if (operadoresFiltrados.value.length === 1) {
            operadorId.value = operadoresFiltrados.value[0].id
            operadorBusqueda.value = operadoresFiltrados.value[0].nombre_completo
        }
    } catch (error) {
        console.error('Error al cargar operadores:', error)
        operadoresFiltrados.value = []
    } finally {
        cargandoOperadores.value = false
    }
}

const seleccionarOperador = (operador) => {
    operadorId.value = operador.id
    operadorBusqueda.value = operador.nombre_completo
    mostrarOperadores.value = false
}

const limpiarOperador = () => {
    operadorId.value = ''
    operadorBusqueda.value = ''
    mostrarOperadores.value = false
}

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

const generarReporte = () => {
    if (!sucursalId.value) {
        alert('Seleccione una sucursal')
        return
    }
    if (!operadorId.value) {
        alert('Seleccione un operador')
        return
    }
    if (!fechaId.value) {
        alert('Seleccione una fecha')
        return
    }
    
    loading.value = true
    
    window.open(`/gestion/reportes/control-interno/arqueo-caja-chica-ci/pdf?sucursal_id=${sucursalId.value}&operador_id=${operadorId.value}&fecha_id=${fechaId.value}`, '_blank')
    
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
    
    const operadorContainer = document.querySelector('.operador-autocomplete')
    if (operadorContainer && !operadorContainer.contains(event.target)) {
        mostrarOperadores.value = false
    }
    
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
                            <i class="fas fa-cash-register text-base sm:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-gray-800">Arqueo de Caja Chica por Operador</h1>
                            <p class="text-xs text-gray-500 hidden sm:block">Seleccione sucursal, operador y fecha para generar el reporte</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Seleccione sucursal, operador y fecha para generar el reporte</p>
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

                        <!-- Selector de Operador -->
                        <div class="operador-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-user mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Operador <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="operadorBusqueda"
                                    @focus="mostrarOperadores = true"
                                    @input="mostrarOperadores = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Escriba para buscar operador..."
                                    autocomplete="off"
                                    :disabled="!sucursalId || cargandoOperadores"
                                />
                                <button 
                                    v-if="operadorBusqueda"
                                    @click="limpiarOperador"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <!-- Cargando -->
                                <div v-if="cargandoOperadores" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>
                                    Cargando operadores...
                                </div>
                                
                                <!-- Lista de operadores -->
                                <div v-else-if="mostrarOperadores && operadoresDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="op in operadoresDisponibles" 
                                        :key="op.id"
                                        @click="seleccionarOperador(op)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="operadorId === op.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="operadorId === op.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <div>
                                            <span class="text-sm">{{ op.nombre_completo }}</span>
                                        </div>
                                        <i v-if="operadorId === op.id" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                                
                                <!-- Mensaje cuando no hay operadores -->
                                <div v-else-if="mostrarOperadores && sucursalId && operadoresDisponibles.length === 0 && !cargandoOperadores" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    No hay operadores asignados a esta sucursal
                                </div>
                                
                                <!-- Mensaje cuando no hay sucursal seleccionada -->
                                <div v-else-if="mostrarOperadores && !sucursalId && !cargandoOperadores" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Primero seleccione una sucursal
                                </div>
                            </div>
                        </div>

                        <!-- Indicador operador seleccionado -->
                        <div v-if="operadorId" class="text-xs flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-500">Operador:</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle mr-1 text-xs"></i> {{ operadorNombre }}
                            </span>
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
                            </div>
                        </div>

                        <!-- Indicador fecha seleccionada -->
                        <div v-if="fechaId" class="text-xs flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-500">Fecha:</span>
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
                                :disabled="loading || !sucursalId || !operadorId || !fechaId"
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
                    <strong>Información:</strong> El reporte muestra el arqueo de caja chica en Bolivianos filtrado por operador, incluyendo saldo anterior, ingresos, egresos y saldo actual.
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

input:disabled {
    background-color: #f3f4f6;
    cursor: not-allowed;
}
</style>