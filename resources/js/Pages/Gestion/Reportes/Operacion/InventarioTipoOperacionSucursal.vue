<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    sucursales: {
        type: Array,
        default: () => []
    },
    sucursalActual: {
        type: Number,
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
const loading = ref(false)
const errors = ref({})

// 🔥 Inicializar vacío - SIN auto-selección
const sucursalId = ref('')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

// Fechas
const fechaInicialId = ref(props.fechaDefault || '')
const fechaFinalId = ref(props.fechaDefault || '')
const estadoProductoId = ref('')

// Autosugerencia
const fechaInicialBusqueda = ref('')
const fechaFinalBusqueda = ref('')
const estadoBusqueda = ref('')

const mostrarFechasInicial = ref(false)
const mostrarFechasFinal = ref(false)
const mostrarEstados = ref(false)

// ==================== COMPUTADOS ====================
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre?.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const s = props.sucursales?.find(s => s.id == sucursalId.value)
    return s?.nombre || ''
})

// Verificar si hay sucursal seleccionada
const haySucursalSeleccionada = computed(() => {
    return sucursalId.value && sucursalId.value !== '' && Number(sucursalId.value) > 0
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
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    if (errors.value.sucursal_id) delete errors.value.sucursal_id
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
}

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

// ==================== VALIDACIÓN ====================
const validar = () => {
    const newErrors = {}
    if (!sucursalId.value) newErrors.sucursal_id = 'Seleccione una sucursal'
    if (!estadoProductoId.value) newErrors.estado_producto = 'Seleccione un estado'
    if (!fechaInicialId.value) newErrors.fecha_inicial = 'Seleccione fecha inicial'
    if (!fechaFinalId.value) newErrors.fecha_final = 'Seleccione fecha final'
    
    errors.value = newErrors
    return Object.keys(newErrors).length === 0
}

// ==================== GENERAR REPORTE ====================
const generarReporte = () => {
    if (!validar()) {
        toast?.warning('⚠️', 'Complete todos los campos requeridos')
        return
    }
    
    loading.value = true
    
    const params = new URLSearchParams({
        sucursal_id: sucursalId.value,
        estado_producto: estadoProductoId.value,
        fecha_inicial: fechaInicialId.value,
        fecha_final: fechaFinalId.value
    })
    
    window.open(`/operacion/reportes/inventario-tipo-operacion-sucursal/exportar?${params.toString()}`, '_blank')
    
    setTimeout(() => {
        loading.value = false
    }, 1500)
}

const volver = () => {
    router.get('/oficial')
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    // 🔥 Ya no se carga la sucursal automáticamente
    
    // Cargar fecha default si existe
    if (props.fechaDefault) {
        const f = props.fechas?.find(f => f.IdFecha === props.fechaDefault)
        if (f) {
            fechaInicialBusqueda.value = f.fecha_formateada
            fechaFinalBusqueda.value = f.fecha_formateada
        }
    }
})

// ==================== CERRAR AUTOCOMPLETES ====================
const handleClickOutside = (event) => {
    if (!document.querySelector('.sucursal-autocomplete')?.contains(event.target)) {
        mostrarSucursales.value = false
    }
    if (!document.querySelector('.fecha-autocomplete-inicial')?.contains(event.target)) {
        mostrarFechasInicial.value = false
    }
    if (!document.querySelector('.fecha-autocomplete-final')?.contains(event.target)) {
        mostrarFechasFinal.value = false
    }
    if (!document.querySelector('.estado-autocomplete')?.contains(event.target)) {
        mostrarEstados.value = false
    }
}

// 🔥 Agregar event listeners al montar
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

// 🔥 Eliminar event listeners al desmontar
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
                            <h1 class="text-lg sm:text-xl font-bold text-gray-800">Análisis de Inventario por Tipo de Operación</h1>
                            <p class="text-xs text-gray-500 hidden sm:block">Seleccione la sucursal para generar el reporte</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Seleccione la sucursal para generar el reporte</p>
                </div>

                <!-- Contenedor -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                    <div class="space-y-4">
                        
                        <!-- 🔥 Sucursal - Autocomplete (SIN auto-selección) -->
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
                                    :style="{ 
                                        borderColor: errors.sucursal_id ? '#ef4444' : `var(--color-primary-300)`,
                                        '--tw-ring-color': `var(--color-primary-500)`
                                    }"
                                    placeholder="Seleccione Sucursal..."
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
                                        @mousedown="seleccionarSucursal(suc)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="sucursalId === suc.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="sucursalId === suc.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <span class="text-sm">{{ suc.nombre }}</span>
                                        <span v-if="suc.numero" class="text-xs text-gray-400">N° {{ suc.numero }}</span>
                                        <i v-if="sucursalId === suc.id" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                            </div>
                            <!-- 🔥 Badge de sucursal seleccionada -->
                            <span v-if="sucursalId && sucursalNombre" class="text-xs text-primary-600 font-medium mt-1 inline-block">
                                <i class="fas fa-check-circle"></i> {{ sucursalNombre }}
                            </span>
                            <span v-else class="text-xs text-gray-400 mt-1 inline-block">
                                <i class="fas fa-store"></i> Ninguna
                            </span>
                            <p v-if="errors.sucursal_id" class="mt-1 text-xs text-red-500">{{ errors.sucursal_id }}</p>
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
                                    :style="{ 
                                        borderColor: errors.estado_producto ? '#ef4444' : `var(--color-primary-300)`,
                                        '--tw-ring-color': `var(--color-primary-500)`
                                    }"
                                    placeholder="Buscar estado..."
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
                                        @mousedown="seleccionarEstado(est)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="estadoProductoId === est.IdEstado ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="estadoProductoId === est.IdEstado ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <span class="text-sm">{{ est.Estado }}</span>
                                        <i v-if="estadoProductoId === est.IdEstado" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                            </div>
                            <p v-if="errors.estado_producto" class="mt-1 text-xs text-red-500">{{ errors.estado_producto }}</p>
                        </div>

                        <!-- Fecha Inicial -->
                        <div class="fecha-autocomplete-inicial">
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
                                    :style="{ 
                                        borderColor: errors.fecha_inicial ? '#ef4444' : `var(--color-primary-300)`,
                                        '--tw-ring-color': `var(--color-primary-500)`
                                    }"
                                    placeholder="Buscar fecha..."
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
                                        @mousedown="seleccionarFechaInicial(f)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="fechaInicialId === f.IdFecha ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="fechaInicialId === f.IdFecha ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <span class="text-sm">{{ f.fecha_formateada }}</span>
                                        <i v-if="fechaInicialId === f.IdFecha" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                            </div>
                            <p v-if="errors.fecha_inicial" class="mt-1 text-xs text-red-500">{{ errors.fecha_inicial }}</p>
                        </div>

                        <!-- Fecha Final -->
                        <div class="fecha-autocomplete-final">
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
                                    :style="{ 
                                        borderColor: errors.fecha_final ? '#ef4444' : `var(--color-primary-300)`,
                                        '--tw-ring-color': `var(--color-primary-500)`
                                    }"
                                    placeholder="Buscar fecha..."
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
                                        @mousedown="seleccionarFechaFinal(f)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="fechaFinalId === f.IdFecha ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="fechaFinalId === f.IdFecha ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <span class="text-sm">{{ f.fecha_formateada }}</span>
                                        <i v-if="fechaFinalId === f.IdFecha" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                            </div>
                            <p v-if="errors.fecha_final" class="mt-1 text-xs text-red-500">{{ errors.fecha_final }}</p>
                        </div>

                        <!-- Indicadores de selección -->
                        <div class="flex flex-wrap gap-2 text-xs">
                            <div v-if="sucursalId && sucursalNombre" class="flex items-center gap-1 px-2 py-1 rounded-full" :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle text-[10px]"></i>
                                {{ sucursalNombre }}
                            </div>
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
                                :disabled="loading || !sucursalId || !estadoProductoId || !fechaInicialId || !fechaFinalId"
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
                    <strong>Información:</strong> El reporte genera un análisis de inventario agrupado por tipo de operación para la sucursal seleccionada, mostrando saldos iniciales, aumentos, disminuciones y saldos finales.
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