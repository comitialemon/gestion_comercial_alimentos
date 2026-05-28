<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: Object,
    sucursal: Object,
    reporte: Array,
    operadores: Array,
    anios: Array,
    filtros: Object,
})

// ==================== ESTADO ====================
const expandidos = ref({})
const operadoresExpandidos = ref({})
const expandidosAnios = ref({})

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

const operadorNombre = computed(() => {
    if (!operadorId.value) return ''
    const op = props.operadores?.find(o => o.id == operadorId.value)
    return op?.nombre || ''
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
    expandidos.value = {
        ...expandidos.value,
        [key]: !expandidos.value[key]
    }
}

const toggleOperador = (anioIndex, fechaIndex, operadorIndex) => {
    const key = `${anioIndex}_${fechaIndex}_${operadorIndex}`
    operadoresExpandidos.value = {
        ...operadoresExpandidos.value,
        [key]: !operadoresExpandidos.value[key]
    }
}

const expandirTodo = () => {
    const nuevosExpandidosAnios = {}
    const nuevosExpandidos = {}
    const nuevosOperadoresExpandidos = {}
    
    props.reporte.forEach((anioData, aIdx) => {
        nuevosExpandidosAnios[aIdx] = true
        anioData.fechas.forEach((_, fIdx) => {
            nuevosExpandidos[`${aIdx}_${fIdx}`] = true
            anioData.fechas[fIdx].operadores.forEach((_, oIdx) => {
                nuevosOperadoresExpandidos[`${aIdx}_${fIdx}_${oIdx}`] = true
            })
        })
    })
    
    expandidosAnios.value = nuevosExpandidosAnios
    expandidos.value = nuevosExpandidos
    operadoresExpandidos.value = nuevosOperadoresExpandidos
}

const contraerTodo = () => {
    expandidosAnios.value = {}
    expandidos.value = {}
    operadoresExpandidos.value = {}
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
            // Contraer todo después de filtrar
            expandidosAnios.value = {}
            expandidos.value = {}
            operadoresExpandidos.value = {}
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

// Calcular total general
const totalGeneral = computed(() => {
    let total = 0
    props.reporte.forEach(anioData => {
        total += anioData.total_anio
    })
    return total
})

const totalUnidadesGeneral = computed(() => {
    let total = 0
    props.reporte.forEach(anioData => {
        total += anioData.total_unidades_anio
    })
    return total
})

// Cerrar sugerencias
const handleClickOutside = (event) => {
    const container = document.querySelector('.operador-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarOperadores.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    // Expandir primer año por defecto
    if (props.reporte && props.reporte.length > 0) {
        expandidosAnios.value = { 0: true }
    }
})
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
                                <h1 class="text-lg sm:text-xl font-bold text-gray-800">Análisis de Ventas por Operador</h1>
                                <p class="text-xs text-gray-500 hidden sm:block">Reporte agrupado por Año → Fecha → Vendedor → Producto</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="expandirTodo"
                                class="px-3 py-1.5 text-xs rounded-lg transition flex items-center gap-1"
                                :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }"
                            >
                                <i class="fas fa-expand-alt text-xs"></i>
                                <span>Expandir todo</span>
                            </button>
                            <button 
                                @click="contraerTodo"
                                class="px-3 py-1.5 text-xs rounded-lg transition flex items-center gap-1"
                                :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }"
                            >
                                <i class="fas fa-compress-alt text-xs"></i>
                                <span>Contraer todo</span>
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Reporte agrupado por Año → Fecha → Vendedor → Producto</p>
                </div>

                <!-- Información de empresa y sucursal -->
                <div class="rounded-xl p-3 sm:p-4 mb-4 sm:mb-6"
                     :style="{ backgroundColor: `var(--color-primary-50)`, borderLeftColor: `var(--color-primary-600)` }"
                     style="border-left-width: 4px;">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                 :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                <i class="fas fa-building text-xs sm:text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs font-medium" :style="{ color: `var(--color-primary-600)` }">Empresa</p>
                                <p class="text-sm sm:text-base font-bold" :style="{ color: `var(--color-primary-800)` }">
                                    {{ empresa?.Nombre || 'No seleccionada' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                 :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                <i class="fas fa-store text-xs sm:text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs font-medium" :style="{ color: `var(--color-primary-600)` }">Sucursal</p>
                                <p class="text-sm sm:text-base font-bold" :style="{ color: `var(--color-primary-800)` }">
                                    {{ sucursal?.Nombre || 'No seleccionada' }}
                                    <span v-if="sucursal?.NumeroSucursal" class="text-xs font-normal text-gray-500 ml-1">
                                        (N° {{ sucursal.NumeroSucursal }})
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <!-- Filtro por Año -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                <i class="fas fa-calendar-alt mr-1 text-xs"></i> Año
                            </label>
                            <select v-model="anio" @change="aplicarFiltros" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todos los años</option>
                                <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>

                        <!-- Filtro por Fecha Inicio -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                <i class="fas fa-calendar-plus mr-1 text-xs"></i> Fecha Inicio
                            </label>
                            <input type="date" v-model="fechaInicio" @change="aplicarFiltros" 
                                   class="w-full border rounded-lg px-3 py-2 text-sm">
                        </div>

                        <!-- Filtro por Fecha Fin -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                <i class="fas fa-calendar-minus mr-1 text-xs"></i> Fecha Fin
                            </label>
                            <input type="date" v-model="fechaFin" @change="aplicarFiltros" 
                                   class="w-full border rounded-lg px-3 py-2 text-sm">
                        </div>

                        <!-- Filtro por Operador con autocompletado -->
                        <div class="operador-autocomplete">
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                <i class="fas fa-user mr-1 text-xs"></i> Vendedor
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="operadorBusqueda"
                                    @focus="mostrarOperadores = true"
                                    @input="mostrarOperadores = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8"
                                    placeholder="Buscar vendedor..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="operadorBusqueda"
                                    @click="limpiarOperador"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarOperadores && operadoresFiltrados.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                    <div 
                                        v-for="op in operadoresFiltrados" 
                                        :key="op.id"
                                        @click="seleccionarOperador(op)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition hover:bg-gray-50 flex justify-between items-center"
                                        :class="operadorId == op.id ? 'bg-primary-50' : ''"
                                        :style="operadorId == op.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <span class="text-sm">{{ op.nombre }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Limpiar filtros -->
                        <div class="flex items-end">
                            <button 
                                @click="limpiarFiltros"
                                class="w-full px-3 py-2 text-sm rounded-lg transition bg-gray-100 text-gray-600 hover:bg-gray-200"
                            >
                                <i class="fas fa-eraser mr-1"></i> Limpiar filtros
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="buscando" class="bg-white rounded-xl shadow-sm p-8 text-center">
                    <i class="fas fa-spinner fa-spin text-2xl" :style="{ color: `var(--color-primary-600)` }"></i>
                    <p class="text-gray-500 mt-2">Cargando reporte...</p>
                </div>

                <!-- Resumen de totales -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <div class="bg-white rounded-xl shadow-sm p-3 flex items-center justify-between">
                        <span class="text-sm text-gray-600">Total General Ventas:</span>
                        <span class="text-xl font-bold" :style="{ color: `var(--color-primary-700)` }">
                            Bs. {{ formatearNumero(totalGeneral) }}
                        </span>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-3 flex items-center justify-between">
                        <span class="text-sm text-gray-600">Total General Unidades:</span>
                        <span class="text-xl font-bold" :style="{ color: `var(--color-primary-700)` }">
                            {{ formatearNumero(totalUnidadesGeneral) }}
                        </span>
                    </div>
                </div>

                <!-- Reporte agrupado -->
                <div v-if="reporte && reporte.length" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Por cada AÑO -->
                    <div v-for="(anioData, anioIndex) in reporte" :key="anioIndex" class="border-b border-gray-200">
                        <!-- Cabecera de año -->
                        <div 
                            @click="toggleAnio(anioIndex)"
                            class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50 transition"
                            :style="{ backgroundColor: expandidosAnios[anioIndex] ? `var(--color-primary-50)` : 'white' }"
                        >
                            <div class="flex items-center gap-3">
                                <i :class="expandidosAnios[anioIndex] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                   class="text-gray-400 text-sm"></i>
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                                     :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                    <i class="fas fa-calendar-alt text-base"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-gray-800">Gestión {{ anioData.anio }}</h2>
                                    <p class="text-xs text-gray-500">{{ anioData.fechas.length }} día(s) con ventas</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold" :style="{ color: `var(--color-primary-700)` }">
                                    Total año: Bs. {{ formatearNumero(anioData.total_anio) }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ formatearNumero(anioData.total_unidades_anio) }} unidades
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
                                    :style="{ backgroundColor: expandidos[`${anioIndex}_${fechaIndex}`] ? `var(--color-primary-50)` : 'white' }"
                                >
                                    <div class="flex items-center gap-3">
                                        <i :class="expandidos[`${anioIndex}_${fechaIndex}`] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                           class="text-gray-400 text-sm"></i>
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                             :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                            <i class="fas fa-calendar-day text-sm"></i>
                                        </div>
                                        <div>
                                            <span class="font-semibold text-gray-800">{{ fechaData.fecha }}</span>
                                            <p class="text-xs text-gray-500">{{ fechaData.operadores.length }} vendedor(es)</p>
                                        </div>
                                    </div>
                                    <div class="text-right text-sm">
                                        <span class="font-medium" :style="{ color: `var(--color-primary-600)` }">
                                            {{ formatearNumero(fechaData.total_fecha) }} Bs
                                        </span>
                                        <span class="text-xs text-gray-400 ml-2">
                                            ({{ formatearNumero(fechaData.total_unidades_fecha) }} und)
                                        </span>
                                    </div>
                                </div>

                                <!-- Contenido de la fecha (Operadores) -->
                                <div v-if="expandidos[`${anioIndex}_${fechaIndex}`]" class="ml-4 sm:ml-8 border-l-2 border-gray-200">
                                    <div v-for="(operador, operadorIndex) in fechaData.operadores" :key="operadorIndex">
                                        <!-- Cabecera de operador -->
                                        <div 
                                            @click="toggleOperador(anioIndex, fechaIndex, operadorIndex)"
                                            class="flex items-center justify-between p-3 pl-4 cursor-pointer hover:bg-gray-50 transition"
                                        >
                                            <div class="flex items-center gap-3">
                                                <i :class="operadoresExpandidos[`${anioIndex}_${fechaIndex}_${operadorIndex}`] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                                   class="text-gray-400 text-sm"></i>
                                                <div class="w-7 h-7 rounded-full flex items-center justify-center"
                                                     :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                                    <i class="fas fa-user text-xs"></i>
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-gray-800">{{ operador.nombre }}</span>
                                                </div>
                                            </div>
                                            <div class="text-right text-sm">
                                                <span class="font-medium" :style="{ color: `var(--color-primary-600)` }">
                                                    {{ formatearNumero(operador.total_ventas) }} Bs
                                                </span>
                                                <span class="text-xs text-gray-400 ml-2">
                                                    ({{ formatearNumero(operador.total_unidades) }} und)
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Productos del operador -->
                                        <div v-if="operadoresExpandidos[`${anioIndex}_${fechaIndex}_${operadorIndex}`]" class="ml-8 sm:ml-12 pb-2">
                                            <!-- Tabla desktop -->
                                            <div class="hidden md:block overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Unidades</th>
                                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-32">Precio Unit.</th>
                                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-32">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-100">
                                                        <tr v-for="(producto, prodIndex) in operador.productos" :key="prodIndex" class="hover:bg-gray-50">
                                                            <td class="px-4 py-3 text-sm text-gray-700">{{ producto.detalle }}</td>
                                                            <td class="px-4 py-3 text-sm text-center text-gray-600">{{ formatearNumero(producto.unidades) }}</td>
                                                            <td class="px-4 py-3 text-sm text-center text-gray-600">Bs. {{ formatearNumero(producto.precio_unitario) }}</td>
                                                            <td class="px-4 py-3 text-sm text-center font-medium" :style="{ color: `var(--color-primary-600)` }">
                                                                Bs. {{ formatearNumero(producto.total) }}
                                                            </td>
                                                         </tr>
                                                    </tbody>
                                                    <tfoot class="bg-gray-50">
                                                        <tr>
                                                            <td colspan="3" class="px-4 py-2 text-right text-sm font-semibold text-gray-700">Total Vendedor:</td>
                                                            <td class="px-4 py-2 text-center text-sm font-bold" :style="{ color: `var(--color-primary-700)` }">
                                                                Bs. {{ formatearNumero(operador.total_ventas) }}
                                                            </td>
                                                         </tr>
                                                    </tfoot>
                                                </table>
                                            </div>

                                            <!-- Cards mobile -->
                                            <div class="md:hidden space-y-2 p-2">
                                                <div v-for="(producto, prodIndex) in operador.productos" :key="prodIndex" 
                                                     class="bg-gray-50 rounded-lg p-3">
                                                    <div class="font-medium text-gray-800 text-sm mb-2">{{ producto.detalle }}</div>
                                                    <div class="grid grid-cols-3 gap-2 text-xs">
                                                        <div>
                                                            <span class="text-gray-500">Unidades:</span>
                                                            <span class="font-medium ml-1">{{ formatearNumero(producto.unidades) }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-500">Precio:</span>
                                                            <span class="font-medium ml-1">Bs. {{ formatearNumero(producto.precio_unitario) }}</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-500">Total:</span>
                                                            <span class="font-medium ml-1" :style="{ color: `var(--color-primary-600)` }">
                                                                Bs. {{ formatearNumero(producto.total) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="bg-gray-100 rounded-lg p-2 text-right">
                                                    <span class="text-xs text-gray-600">Total vendedor:</span>
                                                    <span class="font-bold text-sm ml-2" :style="{ color: `var(--color-primary-700)` }">
                                                        Bs. {{ formatearNumero(operador.total_ventas) }}
                                                    </span>
                                                </div>
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
                    No hay ventas registradas con los filtros seleccionados
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