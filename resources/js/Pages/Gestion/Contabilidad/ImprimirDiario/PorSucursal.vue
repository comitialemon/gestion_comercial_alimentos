<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: Array,
    sucursalSeleccionada: Number,
    diarios: Array,
    esSupervisor: Boolean,
})

// ==================== ESTADO ====================
const sucursalId = ref(props.sucursalSeleccionada || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)
const sucursalesFiltradas = ref([])
const diarios = ref(props.diarios || [])
const cargando = ref(false)
const buscando = ref(false)
const diarioSeleccionado = ref(null)
const mostrarSugerencias = ref(false)
const diariosSugeridos = ref([])
const form = ref({ numero_diario: '' })
const errorBusqueda = ref('')
const filtrandoPor = ref('todos')

// Computed para filtrar sucursales por búsqueda
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

// Computed para filtrar diarios por período
const diariosFiltrados = computed(() => {
    if (!diarios.value.length) return []
    
    if (filtrandoPor.value === 'todos') {
        return diarios.value
    }
    
    const hoy = new Date()
    hoy.setHours(0, 0, 0, 0)
    
    const inicioSemana = new Date(hoy)
    inicioSemana.setDate(hoy.getDate() - hoy.getDay())
    
    const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
    
    return diarios.value.filter(diario => {
        if (!diario.fecha) return false
        const [dia, mes, anio] = diario.fecha.split('/')
        const fechaDiario = new Date(anio, mes - 1, dia)
        fechaDiario.setHours(0, 0, 0, 0)
        
        if (filtrandoPor.value === 'hoy') {
            return fechaDiario.getTime() === hoy.getTime()
        } else if (filtrandoPor.value === 'semana') {
            return fechaDiario >= inicioSemana
        } else if (filtrandoPor.value === 'mes') {
            return fechaDiario >= inicioMes
        }
        return true
    })
})

// Obtener nombre de sucursal seleccionada
const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id === sucursalId.value)
    return suc?.nombre || ''
})

// ==================== ACCIONES SUCURSAL ====================
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    limpiarBusqueda()
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    diarios.value = []
    form.value.numero_diario = ''
    diarioSeleccionado.value = null
}

// ==================== CARGAR DIARIOS ====================
const cargarDiarios = async () => {
    if (!sucursalId.value) {
        diarios.value = []
        return
    }
    
    cargando.value = true
    try {
        const response = await axios.get('/gestion/imprimir-diario/diarios-por-sucursal', {
            params: { sucursal_id: sucursalId.value }
        })
        if (response.data.success) {
            diarios.value = response.data.diarios
        }
    } catch (error) {
        console.error('Error cargando diarios:', error)
        diarios.value = []
    } finally {
        cargando.value = false
    }
}

// ==================== BUSCAR DIARIO POR NÚMERO ====================
const buscarDiarios = async () => {
    const q = form.value.numero_diario.trim()
    
    if (q.length === 0) {
        diariosSugeridos.value = []
        mostrarSugerencias.value = false
        return
    }
    
    buscando.value = true
    
    try {
        const response = await axios.get('/gestion/imprimir-diario/buscar', {
            params: {
                q: q,
                sucursal_id: sucursalId.value,
            }
        })
        
        if (response.data.success) {
            diariosSugeridos.value = response.data.diarios
            mostrarSugerencias.value = response.data.diarios.length > 0
        } else {
            diariosSugeridos.value = []
            mostrarSugerencias.value = false
        }
    } catch (error) {
        console.error('Error:', error)
        diariosSugeridos.value = []
        mostrarSugerencias.value = false
    } finally {
        buscando.value = false
    }
}

// Seleccionar diario de sugerencias (como en Index)
const seleccionarDiario = (diario) => {
    diarioSeleccionado.value = diario
    form.value.numero_diario = diario.numero.toString()
    mostrarSugerencias.value = false
    errorBusqueda.value = ''
    
    // Scroll al resultado seleccionado
    setTimeout(() => {
        document.querySelector('.resultado-seleccionado')?.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        })
    }, 100)
}

// 🔥 Seleccionar diario desde la lista (mismo comportamiento)
const seleccionarDiarioDesdeLista = (diario) => {
    seleccionarDiario(diario)
}

// Limpiar búsqueda de diario
const limpiarBusqueda = () => {
    form.value.numero_diario = ''
    diarioSeleccionado.value = null
    diariosSugeridos.value = []
    errorBusqueda.value = ''
    mostrarSugerencias.value = false
}

// Imprimir diario
const imprimirDiario = () => {
    if (!diarioSeleccionado.value) return
    window.open(`/gestion/imprimir-diario/pdf/${diarioSeleccionado.value.id}`, '_blank')
}

// Cerrar sugerencias al hacer clic fuera
const handleClickOutside = (event) => {
    // Cerrar sugerencias de sucursal
    const sucursalContainer = document.querySelector('.sucursal-autocomplete')
    if (sucursalContainer && !sucursalContainer.contains(event.target)) {
        mostrarSucursales.value = false
    }
    
    // Cerrar sugerencias de diario
    const diarioContainer = document.querySelector('.diario-autocomplete')
    if (diarioContainer && !diarioContainer.contains(event.target)) {
        mostrarSugerencias.value = false
    }
}

// Debounce para búsqueda de diario
let timeout
const onInputDiario = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        buscarDiarios()
    }, 300)
}

// Watch para cambio de sucursal
watch(sucursalId, () => {
    cargarDiarios()
    limpiarBusqueda()
})

// Filtros rápidos
const filtros = [
    { id: 'todos', nombre: 'Todos', icono: 'fa-list' },
    { id: 'hoy', nombre: 'Hoy', icono: 'fa-sun' },
    { id: 'semana', nombre: 'Semana', icono: 'fa-calendar-week' },
    { id: 'mes', nombre: 'Mes', icono: 'fa-calendar-alt' },
]

// Lifecycle
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    if (sucursalId.value) {
        const sucursal = props.sucursales?.find(s => s.id === sucursalId.value)
        if (sucursal) {
            sucursalBusqueda.value = sucursal.nombre
        }
        cargarDiarios()
    }
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    clearTimeout(timeout)
})

const volver = () => {
    router.get('/oficial')
}
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-4 sm:py-6 px-3 sm:px-4 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center"
                                 :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                <i class="fas fa-print text-base sm:text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-lg sm:text-xl font-bold text-gray-800">Imprimir Diario por Sucursal</h1>
                                <p class="text-xs text-gray-500 hidden sm:block">Seleccione sucursal y busque por número de diario</p>
                            </div>
                        </div>
                        <button 
                            @click="volver"
                            class="px-3 py-1.5 text-xs rounded-lg transition sm:w-auto w-full flex items-center justify-center gap-1"
                            :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }"
                        >
                            <i class="fas fa-arrow-left text-xs"></i>
                            <span>Volver</span>
                        </button>
                    </div>
                </div>

                <!-- 🔥 FILA: SELECTOR SUCURSAL + BUSCADOR NÚMERO 🔥 -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <!-- Selector de Sucursal con autocompletado -->
                        <div class="w-full sm:w-80 sucursal-autocomplete">
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                <i class="fas fa-store mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Sucursal
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="sucursalBusqueda"
                                    @focus="mostrarSucursales = true"
                                    @input="mostrarSucursales = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)`, focusRingColor: `var(--color-primary-500)` }"
                                    placeholder="Escriba para buscar sucursal..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="sucursalBusqueda"
                                    @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <!-- Lista de sucursales sugeridas -->
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

                        <!-- Buscador de Número de Diario -->
                        <div class="flex-1 diario-autocomplete">
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                <i class="fas fa-hashtag mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Número de Diario
                            </label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="form.numero_diario"
                                    @input="onInputDiario"
                                    @focus="form.numero_diario && diariosSugeridos.length > 0 ? mostrarSugerencias = true : null"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-20 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)`, focusRingColor: `var(--color-primary-500)` }"
                                    placeholder="Escribe el número de diario..."
                                    autocomplete="off"
                                    :disabled="!sucursalId"
                                />
                                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex gap-1">
                                    <div v-if="buscando" class="text-gray-400">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </div>
                                    <button 
                                        v-if="form.numero_diario"
                                        @click="limpiarBusqueda" 
                                        class="text-gray-400 hover:text-gray-600 p-1"
                                        type="button"
                                    >
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                </div>
                                
                                <!-- Sugerencias de diarios -->
                                <div v-if="mostrarSugerencias && diariosSugeridos.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="diario in diariosSugeridos" 
                                        :key="diario.id"
                                        @click="seleccionarDiario(diario)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1"
                                        :style="{ hoverBgColor: `var(--color-primary-50)` }"
                                    >
                                        <div>
                                            <span class="font-mono font-bold text-sm" :style="{ color: `var(--color-primary-700)` }">N° {{ diario.numero }}</span>
                                            <span class="text-xs text-gray-500 ml-2">{{ diario.tipo }}</span>
                                        </div>
                                        <div class="text-xs text-gray-400">{{ diario.fecha }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Indicador de sucursal seleccionada -->
                    <div v-if="sucursalId" class="mt-3 text-xs flex items-center gap-2 flex-wrap">
                        <span class="font-medium text-gray-500">Sucursal actual:</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                              :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-700)` }">
                            <i class="fas fa-check-circle mr-1 text-xs"></i> {{ sucursalNombre }}
                        </span>
                    </div>

                    <!-- 🔥 Resultado seleccionado (como en Index, sin modal) -->
                    <div v-if="diarioSeleccionado" class="p-3 rounded-lg mt-4 resultado-seleccionado"
                         :style="{ backgroundColor: `var(--color-primary-50)` }">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium" :style="{ color: `var(--color-primary-800)` }">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    Diario seleccionado
                                </p>
                                <div class="mt-1 text-xs text-gray-600 flex flex-wrap gap-x-3 gap-y-1">
                                    <span><span class="font-medium">N°:</span> {{ diarioSeleccionado.numero }}</span>
                                    <span><span class="font-medium">Tipo:</span> {{ diarioSeleccionado.tipo }}</span>
                                    <span><span class="font-medium">Fecha:</span> {{ diarioSeleccionado.fecha }}</span>
                                </div>
                            </div>
                            <button 
                                @click="imprimirDiario" 
                                class="px-4 py-2 text-white rounded-lg transition text-sm flex items-center justify-center gap-2"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i class="fas fa-print text-xs"></i> Imprimir
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Lista de diarios recientes -->
                <div v-if="sucursalId" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Header con filtros -->
                    <div class="px-4 sm:px-6 py-3 sm:py-4 bg-gray-50 border-b flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-list text-sm" :style="{ color: `var(--color-primary-600)` }"></i>
                            <h2 class="font-semibold text-gray-800 text-sm sm:text-base">
                                Diarios de {{ sucursalNombre }}
                            </h2>
                            <span class="text-xs text-gray-400 bg-gray-200 px-2 py-0.5 rounded-full">
                                {{ diariosFiltrados.length }}
                            </span>
                        </div>
                        
                        <!-- Filtros desktop -->
                        <div class="hidden sm:flex gap-1">
                            <button 
                                v-for="filtro in filtros"
                                :key="filtro.id"
                                @click="filtrandoPor = filtro.id"
                                class="px-3 py-1 text-xs rounded-lg transition flex items-center gap-1"
                                :class="filtrandoPor === filtro.id ? 'text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200'"
                                :style="filtrandoPor === filtro.id ? { backgroundColor: `var(--color-primary-600)` } : {}"
                            >
                                <i :class="`fas ${filtro.icono} text-xs`"></i>
                                {{ filtro.nombre }}
                            </button>
                        </div>
                        
                        <!-- Filtros mobile -->
                        <div class="sm:hidden">
                            <select v-model="filtrandoPor" class="w-full border rounded-lg px-3 py-2 text-sm"
                                    :style="{ borderColor: `var(--color-primary-300)` }">
                                <option v-for="filtro in filtros" :key="filtro.id" :value="filtro.id">
                                    {{ filtro.nombre }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Loading -->
                    <div v-if="cargando" class="p-8 text-center">
                        <i class="fas fa-spinner fa-spin text-2xl" :style="{ color: `var(--color-primary-600)` }"></i>
                        <p class="text-gray-500 mt-2 text-sm">Cargando diarios...</p>
                    </div>

                    <!-- Tabla desktop -->
                    <div v-else-if="diariosFiltrados.length > 0" class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Diario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Operador</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="diario in diariosFiltrados" :key="diario.id" class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-mono font-bold text-sm" :style="{ color: `var(--color-primary-700)` }">#{{ diario.numero }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ diario.tipo }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ diario.fecha }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ diario.operador }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button 
                                            @click="seleccionarDiarioDesdeLista(diario)"
                                            class="mr-3 transition"
                                            :style="{ color: `var(--color-primary-500)` }"
                                            title="Seleccionar"
                                        >
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                        <button 
                                            @click="imprimirDiario(diario)"
                                            class="transition"
                                            :style="{ color: `var(--color-primary-600)` }"
                                            title="Imprimir"
                                        >
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Cards mobile -->
                    <div v-else-if="diariosFiltrados.length > 0" class="md:hidden divide-y divide-gray-100">
                        <div v-for="diario in diariosFiltrados" :key="diario.id" class="p-4 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-mono font-bold text-base" :style="{ color: `var(--color-primary-700)` }">#{{ diario.numero }}</span>
                                <div class="flex gap-3">
                                    <button @click="seleccionarDiarioDesdeLista(diario)" :style="{ color: `var(--color-primary-500)` }" title="Seleccionar">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <button @click="imprimirDiario(diario)" :style="{ color: `var(--color-primary-600)` }" title="Imprimir">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="text-sm text-gray-600">{{ diario.tipo }}</div>
                            <div class="flex justify-between items-center mt-2">
                                <div class="text-xs text-gray-400">
                                    <i class="far fa-calendar-alt mr-1"></i> {{ diario.fecha }}
                                </div>
                                <div class="text-xs text-gray-500 truncate max-w-[150px]">
                                    <i class="fas fa-user mr-1"></i> {{ diario.operador }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sin diarios -->
                    <div v-else class="p-8 text-center">
                        <i class="fas fa-folder-open text-gray-300 text-4xl mb-3 block"></i>
                        <p class="text-gray-500">No hay diarios contabilizados en esta sucursal</p>
                        <p class="text-xs text-gray-400 mt-1">Los diarios aparecerán aquí cuando sean contabilizados</p>
                    </div>
                </div>

                <!-- Mensaje cuando no hay sucursal seleccionada -->
                <div v-else class="bg-white rounded-xl shadow-sm p-8 text-center">
                    <i class="fas fa-arrow-left text-gray-300 text-4xl mb-3 block"></i>
                    <p class="text-gray-500">Seleccione una sucursal para buscar diarios</p>
                </div>

                <!-- Botón volver al inicio -->
                <div class="mt-6 flex justify-end">
                    <button 
                        @click="volver"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-sm sm:text-base"
                    >
                        Volver al inicio
                    </button>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 rounded-lg text-xs"
                     :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                    <i class="fas fa-info-circle mr-1"></i>
                    Escriba para buscar sucursal, luego busque por número de diario o seleccione directamente de la lista.
                    <strong>Todos los diarios mostrados están contabilizados.</strong>
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
