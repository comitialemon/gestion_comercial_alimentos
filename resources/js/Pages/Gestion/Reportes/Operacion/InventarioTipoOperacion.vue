<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursal: {
        type: Object,
        default: null
    },
    estados: {
        type: Array,
        default: () => []
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
const fechaInicialId = ref(props.fechaDefault || '')
const fechaFinalId = ref(props.fechaDefault || '')
const estadoProductoId = ref('')
const loading = ref(false)

// ==================== AUTOSUGERENCIA ====================
const fechaInicialBusqueda = ref('')
const fechaFinalBusqueda = ref('')
const estadoBusqueda = ref('')

const mostrarFechasInicial = ref(false)
const mostrarFechasFinal = ref(false)
const mostrarEstados = ref(false)

// ==================== COMPUTADOS ====================
const nombreSucursal = computed(() => {
    return props.sucursal?.nombre || 'No definida'
})

const fechasInicialDisponibles = computed(() => {
    if (!props.fechas) return []
    if (!fechaInicialBusqueda.value) return props.fechas
    const termino = fechaInicialBusqueda.value.toLowerCase()
    return props.fechas.filter(f => f.fecha_formateada.toLowerCase().includes(termino))
})

const fechasFinalDisponibles = computed(() => {
    if (!props.fechas) return []
    if (!fechaFinalBusqueda.value) return props.fechas
    const termino = fechaFinalBusqueda.value.toLowerCase()
    return props.fechas.filter(f => f.fecha_formateada.toLowerCase().includes(termino))
})

const estadosDisponibles = computed(() => {
    if (!props.estados) return []
    if (!estadoBusqueda.value) return props.estados
    const termino = estadoBusqueda.value.toLowerCase()
    return props.estados.filter(e => e.Estado.toLowerCase().includes(termino))
})

const fechaInicialSeleccionada = computed(() => {
    if (!fechaInicialId.value) return ''
    const f = props.fechas?.find(f => f.IdFecha === fechaInicialId.value)
    return f?.fecha_formateada || ''
})

const fechaFinalSeleccionada = computed(() => {
    if (!fechaFinalId.value) return ''
    const f = props.fechas?.find(f => f.IdFecha === fechaFinalId.value)
    return f?.fecha_formateada || ''
})

const estadoSeleccionado = computed(() => {
    if (!estadoProductoId.value) return ''
    const e = props.estados?.find(e => e.IdEstado === estadoProductoId.value)
    return e?.Estado || ''
})

// ==================== ACCIONES ====================
const seleccionarFechaInicial = (fecha) => {
    fechaInicialId.value = fecha.IdFecha
    fechaInicialBusqueda.value = fecha.fecha_formateada
    mostrarFechasInicial.value = false
}

const limpiarFechaInicial = () => {
    fechaInicialId.value = ''
    fechaInicialBusqueda.value = ''
    mostrarFechasInicial.value = false
}

const seleccionarFechaFinal = (fecha) => {
    fechaFinalId.value = fecha.IdFecha
    fechaFinalBusqueda.value = fecha.fecha_formateada
    mostrarFechasFinal.value = false
}

const limpiarFechaFinal = () => {
    fechaFinalId.value = ''
    fechaFinalBusqueda.value = ''
    mostrarFechasFinal.value = false
}

const seleccionarEstado = (estado) => {
    estadoProductoId.value = estado.IdEstado
    estadoBusqueda.value = estado.Estado
    mostrarEstados.value = false
}

const limpiarEstado = () => {
    estadoProductoId.value = ''
    estadoBusqueda.value = ''
    mostrarEstados.value = false
}

// ==================== GENERAR REPORTE ====================
const generarReporte = () => {
    if (!estadoProductoId.value) {
        alert('Seleccione un estado de producto')
        return
    }
    if (!fechaInicialId.value) {
        alert('Seleccione una fecha inicial')
        return
    }
    if (!fechaFinalId.value) {
        alert('Seleccione una fecha final')
        return
    }
    
    loading.value = true
    
    window.open(`/operacion/reportes/inventario-tipo-operacion/exportar?estado_producto=${estadoProductoId.value}&fecha_inicial=${fechaInicialId.value}&fecha_final=${fechaFinalId.value}`, '_blank')
    
    setTimeout(() => {
        loading.value = false
    }, 1000)
}

const volver = () => {
    router.get('/oficial')
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    // Si hay fecha default, cargarla en los buscadores
    if (props.fechaDefault) {
        const fecha = props.fechas?.find(f => f.IdFecha === props.fechaDefault)
        if (fecha) {
            fechaInicialBusqueda.value = fecha.fecha_formateada
            fechaFinalBusqueda.value = fecha.fecha_formateada
        }
    }
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
                            <h1 class="text-lg sm:text-xl font-bold text-gray-800">Análisis de Inventario por Tipo de Operación</h1>
                            <p class="text-xs text-gray-500 hidden sm:block">Reporte automático de la sucursal logueada</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Reporte automático de la sucursal logueada</p>
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

                        <!-- Estado Producto -->
                        <div class="estado-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-tag mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Estado de Producto <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="estadoBusqueda"
                                    @focus="mostrarEstados = true"
                                    @input="mostrarEstados = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Escriba para buscar estado..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="estadoBusqueda"
                                    @click="limpiarEstado"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarEstados && estadosDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="est in estadosDisponibles" 
                                        :key="est.IdEstado"
                                        @click="seleccionarEstado(est)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="estadoProductoId === est.IdEstado ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="estadoProductoId === est.IdEstado ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <span class="text-sm">{{ est.Estado }}</span>
                                        <i v-if="estadoProductoId === est.IdEstado" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fecha Inicial -->
                        <div class="fecha-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-calendar-alt mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Fecha Inicial <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="fechaInicialBusqueda"
                                    @focus="mostrarFechasInicial = true"
                                    @input="mostrarFechasInicial = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Escriba para buscar fecha..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="fechaInicialBusqueda"
                                    @click="limpiarFechaInicial"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarFechasInicial && fechasInicialDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="f in fechasInicialDisponibles" 
                                        :key="f.IdFecha"
                                        @click="seleccionarFechaInicial(f)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="fechaInicialId === f.IdFecha ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="fechaInicialId === f.IdFecha ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <span class="text-sm">{{ f.fecha_formateada }}</span>
                                        <i v-if="fechaInicialId === f.IdFecha" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fecha Final -->
                        <div class="fecha-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-calendar-alt mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Fecha Final <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="fechaFinalBusqueda"
                                    @focus="mostrarFechasFinal = true"
                                    @input="mostrarFechasFinal = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Escriba para buscar fecha..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="fechaFinalBusqueda"
                                    @click="limpiarFechaFinal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarFechasFinal && fechasFinalDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="f in fechasFinalDisponibles" 
                                        :key="f.IdFecha"
                                        @click="seleccionarFechaFinal(f)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="fechaFinalId === f.IdFecha ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="fechaFinalId === f.IdFecha ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <span class="text-sm">{{ f.fecha_formateada }}</span>
                                        <i v-if="fechaFinalId === f.IdFecha" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Indicadores de selección -->
                        <div class="flex flex-wrap gap-2 text-xs">
                            <div v-if="estadoProductoId" class="flex items-center gap-1 px-2 py-1 rounded-full" :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle text-[10px]"></i>
                                Estado: {{ estadoSeleccionado }}
                            </div>
                            <div v-if="fechaInicialId" class="flex items-center gap-1 px-2 py-1 rounded-full" :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle text-[10px]"></i>
                                Inicio: {{ fechaInicialSeleccionada }}
                            </div>
                            <div v-if="fechaFinalId" class="flex items-center gap-1 px-2 py-1 rounded-full" :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle text-[10px]"></i>
                                Fin: {{ fechaFinalSeleccionada }}
                            </div>
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
                                :disabled="loading || !estadoProductoId || !fechaInicialId || !fechaFinalId"
                                class="px-5 py-2 text-white rounded-lg transition text-sm flex items-center justify-center gap-2 disabled:opacity-50"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-file-excel"></i>
                                {{ loading ? 'Generando...' : 'Exportar Excel' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 rounded-lg text-xs"
                     :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Información:</strong> El reporte genera un análisis de inventario agrupado por tipo de operación para la sucursal logueada, mostrando saldos iniciales, aumentos, disminuciones y saldos finales.
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

.z-10 {
    z-index: 10;
}
</style>