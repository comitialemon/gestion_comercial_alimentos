<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import ModalReporteVentasSupervisorPorOperador from './components/ModalReporteVentasSupervisorPorOperador.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: Object,
    sucursal: Object,
    reporte: Array,
    operadores: Array,
    anios: Array,
    filtros: Object,
    totales: Object,
})

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const expandidosAnios = ref({})
const expandidosFechas = ref({})

// ==================== ESTADO DEL MODAL ====================
const modalVisible = ref(false)
const operadorSeleccionado = ref(null)

// Filtros
const fechaInicio = ref(props.filtros?.fecha_inicio || '')
const fechaFin = ref(props.filtros?.fecha_fin || '')
const operadorId = ref(props.filtros?.operador_id || '')
const anio = ref(props.filtros?.anio || '')
const buscando = ref(false)

// Autocompletado para operador
const operadorBusqueda = ref('')
const mostrarOperadores = ref(false)

// ==================== COMPUTADOS ====================
const operadoresFiltrados = computed(() => {
    if (!props.operadores) return []
    if (!operadorBusqueda.value) return props.operadores
    
    const termino = operadorBusqueda.value.toLowerCase()
    return props.operadores.filter(op => 
        op.nombre?.toLowerCase().includes(termino) ||
        op.id?.toString().includes(termino)
    )
})

const hayFiltrosActivos = computed(() => {
    return fechaInicio.value || fechaFin.value || operadorId.value || anio.value
})

// ==================== ACCIONES ====================
const toggleAnio = (anioIndex) => {
    expandidosAnios.value = {
        ...expandidosAnios.value,
        [anioIndex]: !expandidosAnios.value[anioIndex]
    }
}

const toggleFecha = (anioIndex, fechaIndex) => {
    const key = `${anioIndex}_${fechaIndex}`
    expandidosFechas.value = {
        ...expandidosFechas.value,
        [key]: !expandidosFechas.value[key]
    }
}

// 🔥 ABRIR MODAL CON EL DETALLE DEL OPERADOR
const verDetalleOperador = (operador) => {
    operadorSeleccionado.value = operador
    modalVisible.value = true
}

const expandirTodo = () => {
    const nuevosExpandidosAnios = {}
    const nuevosExpandidosFechas = {}
    
    props.reporte.forEach((anioData, aIdx) => {
        nuevosExpandidosAnios[aIdx] = true
        anioData.fechas.forEach((_, fIdx) => {
            nuevosExpandidosFechas[`${aIdx}_${fIdx}`] = true
        })
    })
    
    expandidosAnios.value = nuevosExpandidosAnios
    expandidosFechas.value = nuevosExpandidosFechas
}

const contraerTodo = () => {
    expandidosAnios.value = {}
    expandidosFechas.value = {}
}

const seleccionarOperador = (operador) => {
    operadorId.value = operador.id
    operadorBusqueda.value = operador.nombre
    mostrarOperadores.value = false
}

const limpiarOperador = () => {
    operadorId.value = ''
    operadorBusqueda.value = ''
    mostrarOperadores.value = false
}

const limpiarFiltros = () => {
    fechaInicio.value = ''
    fechaFin.value = ''
    operadorId.value = ''
    operadorBusqueda.value = ''
    anio.value = ''
    aplicarFiltros()
}

const aplicarFiltros = () => {
    buscando.value = true
    
    const params = new URLSearchParams()
    if (fechaInicio.value) params.append('fecha_inicio', fechaInicio.value)
    if (fechaFin.value) params.append('fecha_fin', fechaFin.value)
    if (operadorId.value) params.append('operador_id', operadorId.value)
    if (anio.value) params.append('anio', anio.value)
    
    router.get('/gestion/reportes/ventas-por-operador', params.toString(), {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            buscando.value = false
            expandidosAnios.value = {}
            expandidosFechas.value = {}
        }
    })
}

// Debounce para búsqueda
let timeout
const onBuscar = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

// Formatear número
const formatearNumero = (numero) => {
    if (numero === undefined || numero === null) return '0.00'
    return parseFloat(numero).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

const formatearNumeroEntero = (numero) => {
    if (numero === undefined || numero === null) return '0'
    return parseFloat(numero).toLocaleString('es-BO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    })
}

// Calcular total general
const totalGeneralVentas = computed(() => {
    return props.totales?.ventas || 0
})

const totalGeneralUnidades = computed(() => {
    return props.totales?.unidades || 0
})

// Cerrar sugerencias
const handleClickOutside = (event) => {
    const container = document.querySelector('.operador-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarOperadores.value = false
    }
}

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
    
    // Expandir primer año por defecto
    if (props.reporte && props.reporte.length > 0) {
        expandidosAnios.value = { 0: true }
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                
                <!-- Header -->
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chart-line text-primary-600 text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-lg lg:text-xl font-bold text-gray-800">Análisis de Ventas por Operador</h1>
                        <p class="text-xs text-gray-500">Reporte agrupado por Año → Fecha → Vendedor → Producto</p>
                    </div>
                </div>

                <!-- Información de empresa y sucursal (BANNER UNIFICADO) -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-building text-primary-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Empresa</p>
                            <p class="text-sm font-bold text-gray-800">{{ empresa?.Nombre || 'No seleccionada' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-store text-primary-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Sucursal</p>
                            <p class="text-sm font-bold text-gray-800">
                                {{ sucursal?.Nombre || 'No seleccionada' }}
                                <span v-if="sucursal?.NumeroSucursal" class="text-xs font-normal text-gray-500 ml-1">
                                    (N° {{ sucursal.NumeroSucursal }})
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-calendar-alt text-primary-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Período</p>
                            <p class="text-sm font-bold text-gray-800">
                                {{ fechaInicio || 'Inicio' }} al {{ fechaFin || 'Fin' }}
                                <span v-if="anio" class="text-xs font-normal text-gray-500 ml-1">(Año {{ anio }})</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <!-- Filtro por Año -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                <i class="fas fa-calendar-alt mr-1 text-xs"></i> Año
                            </label>
                            <select v-model="anio" @change="aplicarFiltros" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Todos los años</option>
                                <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>

                        <!-- Filtro por Fecha Inicio -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                <i class="fas fa-calendar-plus mr-1 text-xs"></i> Fecha Inicio
                            </label>
                            <input type="date" v-model="fechaInicio" @change="aplicarFiltros" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <!-- Filtro por Fecha Fin -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                <i class="fas fa-calendar-minus mr-1 text-xs"></i> Fecha Fin
                            </label>
                            <input type="date" v-model="fechaFin" @change="aplicarFiltros" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <!-- Filtro por Operador con autocompletado -->
                        <div class="operador-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                <i class="fas fa-user mr-1 text-xs"></i> Vendedor
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="operadorBusqueda"
                                    @focus="mostrarOperadores = true"
                                    @input="mostrarOperadores = true"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500 pr-8"
                                    placeholder="Buscar vendedor..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="operadorBusqueda"
                                    @click="limpiarOperador"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-sm"></i>
                                </button>
                                
                                <div v-if="mostrarOperadores && operadoresFiltrados.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                    <div 
                                        v-for="op in operadoresFiltrados" 
                                        :key="op.id"
                                        @click="seleccionarOperador(op)"
                                        class="px-3 py-2.5 cursor-pointer border-b border-gray-100 last:border-b-0 hover:bg-primary-50 transition flex justify-between items-center"
                                        :class="operadorId == op.id ? 'bg-primary-50 text-primary-700' : ''"
                                    >
                                        <span class="text-sm">{{ op.nombre }}</span>
                                        <span v-if="operadorId == op.id" class="text-primary-600 text-xs">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex items-end gap-2">
                            <button 
                                @click="aplicarFiltros"
                                class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition flex items-center justify-center gap-2"
                            >
                                <i class="fas fa-search text-sm"></i>
                                <span>Buscar</span>
                            </button>
                            <button 
                                @click="limpiarFiltros"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition flex items-center justify-center gap-2"
                            >
                                <i class="fas fa-eraser text-sm"></i>
                                <span>Limpiar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="buscando" class="bg-white rounded-xl shadow-sm p-8 text-center">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary-600"></i>
                    <p class="text-gray-500 mt-2">Cargando reporte...</p>
                </div>

                <!-- Resumen de totales -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                    <div class="bg-white rounded-xl shadow-sm p-4 flex items-center justify-between">
                        <span class="text-sm text-gray-600">Total General Ventas:</span>
                        <span class="text-xl font-bold text-primary-700">
                            Bs. {{ formatearNumero(totalGeneralVentas) }}
                        </span>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-4 flex items-center justify-between">
                        <span class="text-sm text-gray-600">Total General Unidades:</span>
                        <span class="text-xl font-bold text-primary-700">
                            {{ formatearNumeroEntero(totalGeneralUnidades) }}
                        </span>
                    </div>
                </div>

                <!-- Botones Expandir/Contraer -->
                <div v-if="reporte && reporte.length > 0" class="flex gap-2 mb-4">
                    <button 
                        @click="expandirTodo"
                        class="px-4 py-2 text-sm rounded-lg transition flex items-center gap-2 bg-primary-50 text-primary-700 hover:bg-primary-100"
                    >
                        <i class="fas fa-expand-alt text-xs"></i>
                        <span>Expandir todo</span>
                    </button>
                    <button 
                        @click="contraerTodo"
                        class="px-4 py-2 text-sm rounded-lg transition flex items-center gap-2 bg-gray-100 text-gray-600 hover:bg-gray-200"
                    >
                        <i class="fas fa-compress-alt text-xs"></i>
                        <span>Contraer todo</span>
                    </button>
                </div>

                <!-- Reporte agrupado -->
                <div v-if="reporte && reporte.length" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Por cada AÑO -->
                    <div v-for="(anioData, anioIndex) in reporte" :key="anioIndex" class="border-b border-gray-200 last:border-b-0">
                        <!-- Cabecera de año -->
                        <div 
                            @click="toggleAnio(anioIndex)"
                            class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50 transition"
                            :style="{ backgroundColor: expandidosAnios[anioIndex] ? 'var(--color-primary-50)' : 'white' }"
                        >
                            <div class="flex items-center gap-3">
                                <i :class="expandidosAnios[anioIndex] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                   class="text-gray-400 text-sm"></i>
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-primary-100 text-primary-600">
                                    <i class="fas fa-calendar-alt text-base"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-gray-800">Gestión {{ anioData.anio }}</h2>
                                    <p class="text-xs text-gray-500">{{ anioData.fechas.length }} día(s) con ventas</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-primary-700">
                                    Total año: Bs. {{ formatearNumero(anioData.total_anio) }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ formatearNumeroEntero(anioData.total_unidades_anio) }} unidades
                                </p>
                            </div>
                        </div>

                        <!-- Contenido del año (Fechas) -->
                        <div v-if="expandidosAnios[anioIndex]" class="border-t border-gray-100">
                            <div v-for="(fechaData, fechaIndex) in anioData.fechas" :key="fechaIndex" class="ml-4 sm:ml-8">
                                <!-- Cabecera de fecha -->
                                <div 
                                    @click="toggleFecha(anioIndex, fechaIndex)"
                                    class="flex items-center justify-between p-3 pl-4 cursor-pointer hover:bg-gray-50 transition"
                                    :style="{ backgroundColor: expandidosFechas[`${anioIndex}_${fechaIndex}`] ? 'var(--color-primary-50)' : 'white' }"
                                >
                                    <div class="flex items-center gap-3">
                                        <i :class="expandidosFechas[`${anioIndex}_${fechaIndex}`] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                           class="text-gray-400 text-sm"></i>
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-primary-100 text-primary-600">
                                            <i class="fas fa-calendar-day text-sm"></i>
                                        </div>
                                        <div>
                                            <span class="font-semibold text-gray-800">{{ fechaData.fecha }}</span>
                                            <p class="text-xs text-gray-500">{{ fechaData.operadores.length }} vendedor(es)</p>
                                        </div>
                                    </div>
                                    <div class="text-right text-sm">
                                        <span class="font-medium text-primary-600">
                                            {{ formatearNumero(fechaData.total_fecha) }} Bs
                                        </span>
                                        <span class="text-xs text-gray-400 ml-2">
                                            ({{ formatearNumeroEntero(fechaData.total_unidades_fecha) }} und)
                                        </span>
                                    </div>
                                </div>

                                <!-- Contenido de la fecha (Operadores) -->
                                <div v-if="expandidosFechas[`${anioIndex}_${fechaIndex}`]" class="ml-4 sm:ml-8 border-l-2 border-gray-200">
                                    <div v-for="(operador, operadorIndex) in fechaData.operadores" :key="operadorIndex">
                                        <!-- 🔥 CABECERA DE OPERADOR - ABRE MODAL -->
                                        <div 
                                            @click="verDetalleOperador(operador)"
                                            class="flex items-center justify-between p-3 pl-4 cursor-pointer hover:bg-gray-50 transition rounded-lg"
                                        >
                                            <div class="flex items-center gap-3">
                                                <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 bg-primary-100 text-primary-600">
                                                    <i class="fas fa-user text-xs"></i>
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-gray-800">{{ operador.nombre }}</span>
                                                    <span class="text-xs text-gray-400 ml-2 hidden sm:inline">
                                                        <i class="fas fa-receipt mr-1"></i>{{ operador.productos.length }} productos
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="text-right">
                                                    <span class="font-medium text-primary-600">
                                                        {{ formatearNumero(operador.total_ventas) }} Bs
                                                    </span>
                                                    <span class="text-xs text-gray-400 ml-2">
                                                        ({{ formatearNumeroEntero(operador.total_unidades) }} und)
                                                    </span>
                                                </div>
                                                <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sin resultados -->
                <div v-else-if="!buscando" class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">
                    <i class="fas fa-chart-line text-4xl mb-3 block"></i>
                    <p class="text-base font-medium">No hay ventas registradas con los filtros seleccionados</p>
                    <p class="text-sm mt-1">Prueba cambiando el rango de fechas o el vendedor</p>
                </div>
            </div>
        </div>

        <!-- 🔥 MODAL DE DETALLE DEL OPERADOR -->
        <ModalReporteVentasSupervisorPorOperador
            v-model:visible="modalVisible"
            :operador="operadorSeleccionado"
            :fecha-inicio="fechaInicio"
            :fecha-fin="fechaFin"
            :anio="anio"
            @close="modalVisible = false"
        />
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

.cursor-pointer {
    cursor: pointer;
}

.hover\:bg-gray-50:hover {
    background-color: #f9fafb;
}

.hover\:bg-gray-200:hover {
    background-color: #e5e7eb;
}

.hover\:bg-primary-50:hover {
    background-color: var(--color-primary-50, #fdf2f2);
}

.hover\:bg-primary-100:hover {
    background-color: var(--color-primary-100, #fce4e4);
}

.bg-primary-50 {
    background-color: var(--color-primary-50, #fdf2f2);
}

.bg-primary-100 {
    background-color: var(--color-primary-100, #fce4e4);
}

.text-primary-600 {
    color: var(--color-primary-600, #61131a);
}

.text-primary-700 {
    color: var(--color-primary-700, #4a0f14);
}

.bg-primary-600 {
    background-color: var(--color-primary-600, #61131a);
}

.hover\:bg-primary-700:hover {
    background-color: var(--color-primary-700, #4a0f14);
}

.border-primary-500 {
    border-color: var(--color-primary-500, #7a1a22);
}
</style>