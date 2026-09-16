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

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const sucursalId = ref('') // 🔥 SIN selección inicial
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)
const diarios = ref([]) // 🔥 SIN diarios iniciales
const cargando = ref(false)
const buscando = ref(false)
const diarioSeleccionado = ref(null)
const mostrarSugerencias = ref(false)
const diariosSugeridos = ref([])
const form = ref({ numero_diario: '' })
const errorBusqueda = ref('')
const filtrandoPor = ref('todos')

// ==================== COMPUTED ====================
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

const diariosFiltrados = computed(() => {
    if (!diarios.value.length) return []
    
    if (filtrandoPor.value === 'todos') return diarios.value
    
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
        
        if (filtrandoPor.value === 'hoy') return fechaDiario.getTime() === hoy.getTime()
        if (filtrandoPor.value === 'semana') return fechaDiario >= inicioSemana
        if (filtrandoPor.value === 'mes') return fechaDiario >= inicioMes
        return true
    })
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id === sucursalId.value)
    return suc?.nombre || ''
})

const haySucursalSeleccionada = computed(() => {
    return sucursalId.value && sucursalId.value !== ''
})

// ==================== ACCIONES SUCURSAL ====================
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    limpiarBusqueda()
    cargarDiarios() // 🔥 Cargar diarios al seleccionar
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
        const response = await axios.get('/gestion/contabilidad/imprimir-diario/diarios-por-sucursal', {
            params: { sucursal_id: sucursalId.value }
        })
        if (response.data.success) {
            diarios.value = response.data.diarios
        } else {
            diarios.value = []
        }
    } catch (error) {
        console.error('Error cargando diarios:', error)
        diarios.value = []
    } finally {
        cargando.value = false
    }
}

// ==================== BUSCAR DIARIO ====================
const buscarDiarios = async () => {
    const q = form.value.numero_diario.trim()
    
    if (q.length === 0) {
        diariosSugeridos.value = []
        mostrarSugerencias.value = false
        return
    }
    
    if (!/^\d+$/.test(q)) {
        errorBusqueda.value = 'Ingrese solo números'
        diariosSugeridos.value = []
        mostrarSugerencias.value = false
        return
    }
    
    buscando.value = true
    errorBusqueda.value = ''
    
    try {
        const response = await axios.get('/gestion/contabilidad/imprimir-diario/buscar', {
            params: {
                q: q,
                sucursal_id: sucursalId.value,
            }
        })
        
        if (response.data.success && response.data.diarios.length > 0) {
            diariosSugeridos.value = response.data.diarios
            mostrarSugerencias.value = true
            
            if (response.data.diarios.length === 1) {
                seleccionarDiario(response.data.diarios[0])
            }
        } else {
            diariosSugeridos.value = []
            mostrarSugerencias.value = false
            errorBusqueda.value = `No se encontró el diario N° ${q} en esta sucursal`
        }
    } catch (error) {
        console.error('Error:', error)
        diariosSugeridos.value = []
        mostrarSugerencias.value = false
        errorBusqueda.value = 'Error al buscar. Intente nuevamente.'
    } finally {
        buscando.value = false
    }
}

const seleccionarDiario = (diario) => {
    diarioSeleccionado.value = diario
    form.value.numero_diario = diario.numero.toString()
    mostrarSugerencias.value = false
    errorBusqueda.value = ''
    
    setTimeout(() => {
        document.querySelector('.resultado-seleccionado')?.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        })
    }, 100)
}

const seleccionarDiarioDesdeLista = (diario) => {
    seleccionarDiario(diario)
}

const limpiarBusqueda = () => {
    form.value.numero_diario = ''
    diarioSeleccionado.value = null
    diariosSugeridos.value = []
    errorBusqueda.value = ''
    mostrarSugerencias.value = false
}

const imprimirDiario = (diario = null) => {
    const target = diario || diarioSeleccionado.value
    if (!target) return
    window.open(`/gestion/contabilidad/imprimir-diario/pdf/${target.id}`, '_blank')
}

const handleClickOutside = (event) => {
    const sucursalContainer = document.querySelector('.sucursal-autocomplete')
    if (sucursalContainer && !sucursalContainer.contains(event.target)) {
        mostrarSucursales.value = false
    }
    
    const diarioContainer = document.querySelector('.diario-autocomplete')
    if (diarioContainer && !diarioContainer.contains(event.target)) {
        mostrarSugerencias.value = false
    }
}

let timeout
const onInputDiario = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        buscarDiarios()
    }, 300)
}

// ==================== FILTROS ====================
const filtros = [
    { id: 'todos', nombre: 'Todos', icono: 'fa-list' },
    { id: 'hoy', nombre: 'Hoy', icono: 'fa-sun' },
    { id: 'semana', nombre: 'Semana', icono: 'fa-calendar-week' },
    { id: 'mes', nombre: 'Mes', icono: 'fa-calendar-alt' },
]

const volver = () => {
    router.get('/gestion/contabilidad/imprimir-diario')
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
    // 🔥 NO seleccionar nada automáticamente
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
    clearTimeout(timeout)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <button @click="volver" class="text-gray-400 hover:text-gray-600 transition p-1">
                            <i class="fas fa-arrow-left text-base"></i>
                        </button>
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-print text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Imprimir Diario por Sucursal</h1>
                            <p class="text-xs text-gray-500">Seleccione sucursal y busque por número de diario</p>
                        </div>
                    </div>
                </div>

                <!-- ==================== FILA: SUCURSAL + BUSCADOR ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-col sm:flex-row gap-2">
                        <!-- Selector Sucursal -->
                        <div class="w-full sm:w-80 sucursal-autocomplete">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                                <i class="fas fa-store mr-1 text-primary-600 text-[10px]"></i>
                                Sucursal <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="sucursalBusqueda"
                                    @focus="mostrarSucursales = true"
                                    @input="mostrarSucursales = true"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-7 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="Seleccione una sucursal..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="sucursalBusqueda"
                                    @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                
                                <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                    <div 
                                        v-for="suc in sucursalesDisponibles" 
                                        :key="suc.id"
                                        @mousedown.prevent="seleccionarSucursal(suc)"
                                        class="px-2.5 py-1.5 cursor-pointer border-b border-gray-100 last:border-b-0 transition flex justify-between items-center text-sm"
                                        :class="sucursalId === suc.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                    >
                                        <div>
                                            <span class="font-medium text-xs">{{ suc.nombre }}</span>
                                            <span v-if="suc.numero" class="text-[9px] text-gray-400 ml-1">(N° {{ suc.numero }})</span>
                                        </div>
                                        <i v-if="sucursalId === suc.id" class="fas fa-check-circle text-[10px] text-primary-600"></i>
                                    </div>
                                </div>
                                
                                <div v-if="mostrarSucursales && sucursalBusqueda && sucursalesDisponibles.length === 0" 
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-500 text-[10px]">
                                    No se encontraron sucursales
                                </div>
                            </div>
                            <!-- Badge selección -->
                            <span v-if="haySucursalSeleccionada" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] bg-primary-50 text-primary-700 mt-0.5">
                                <i class="fas fa-check-circle text-[7px]"></i> {{ sucursalNombre }}
                            </span>
                            <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] text-gray-400 mt-0.5">
                                <i class="fas fa-store text-[7px]"></i> Ninguna seleccionada
                            </span>
                        </div>

                        <!-- Buscador Diario -->
                        <div class="flex-1 diario-autocomplete">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                                <i class="fas fa-hashtag mr-1 text-primary-600 text-[10px]"></i>
                                Número de Diario
                            </label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="form.numero_diario"
                                    @input="onInputDiario"
                                    @keyup.enter="buscarDiarios"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-16 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="Escribe el número y presiona Enter..."
                                    autocomplete="off"
                                    :disabled="!haySucursalSeleccionada"
                                    :class="{ 'opacity-50 cursor-not-allowed bg-gray-50': !haySucursalSeleccionada }"
                                />
                                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex gap-1 items-center">
                                    <div v-if="buscando" class="text-gray-400">
                                        <i class="fas fa-spinner fa-spin text-[10px]"></i>
                                    </div>
                                    <button 
                                        v-if="form.numero_diario && !buscando && haySucursalSeleccionada"
                                        @click="buscarDiarios" 
                                        class="bg-primary-600 text-white px-2 py-0.5 rounded hover:bg-primary-700 transition"
                                        type="button"
                                        title="Buscar"
                                    >
                                        <i class="fas fa-search text-[10px]"></i>
                                    </button>
                                    <button 
                                        v-if="form.numero_diario"
                                        @click="limpiarBusqueda" 
                                        class="text-gray-400 hover:text-gray-600 p-1"
                                        type="button"
                                        title="Limpiar"
                                    >
                                        <i class="fas fa-times text-[10px]"></i>
                                    </button>
                                </div>
                                
                                <div v-if="mostrarSugerencias && diariosSugeridos.length > 0" 
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                    <div 
                                        v-for="diario in diariosSugeridos" 
                                        :key="diario.id"
                                        @mousedown.prevent="seleccionarDiario(diario)"
                                        class="px-2.5 py-1.5 cursor-pointer border-b border-gray-100 last:border-b-0 hover:bg-primary-50 transition text-sm"
                                    >
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <span class="font-mono font-bold text-xs text-primary-700">N° {{ diario.numero }}</span>
                                                <span class="text-[10px] text-gray-500 ml-2">{{ diario.tipo }}</span>
                                            </div>
                                            <div class="text-[10px] text-gray-400">{{ diario.fecha }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-if="!haySucursalSeleccionada" class="text-[8px] text-gray-400 mt-0.5">
                                <i class="fas fa-info-circle mr-0.5"></i> Seleccione una sucursal primero
                            </p>
                        </div>
                    </div>

                    <!-- Error búsqueda -->
                    <div v-if="errorBusqueda" class="mt-2 p-2 rounded-md bg-red-50 border border-red-200">
                        <p class="text-xs text-red-700 flex items-center gap-1.5">
                            <i class="fas fa-exclamation-triangle text-[10px]"></i>
                            {{ errorBusqueda }}
                        </p>
                    </div>

                    <!-- Resultado seleccionado -->
                    <div v-if="diarioSeleccionado" class="p-2.5 rounded-lg mt-3 bg-primary-50 border border-primary-100 resultado-seleccionado">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-medium text-primary-800 flex items-center gap-1">
                                    <i class="fas fa-check-circle text-[10px]"></i>
                                    Diario seleccionado
                                </p>
                                <div class="mt-0.5 text-[10px] text-gray-600 flex flex-wrap gap-x-3 gap-y-0.5">
                                    <span><span class="font-medium">N°:</span> {{ diarioSeleccionado.numero }}</span>
                                    <span><span class="font-medium">Tipo:</span> {{ diarioSeleccionado.tipo }}</span>
                                    <span><span class="font-medium">Fecha:</span> {{ diarioSeleccionado.fecha }}</span>
                                </div>
                            </div>
                            <button 
                                @click="imprimirDiario()" 
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition text-xs font-medium flex items-center gap-1.5"
                            >
                                <i class="fas fa-print text-[10px]"></i> Imprimir
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== MENSAJE: SIN SUCURSAL ==================== -->
                <div v-if="!haySucursalSeleccionada" class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <div class="w-16 h-16 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-store text-primary-400 text-3xl"></i>
                    </div>
                    <h3 class="text-base font-semibold text-gray-700">Seleccione una Sucursal</h3>
                    <p class="text-sm text-gray-400 mt-2 max-w-sm mx-auto">
                        Use el campo de búsqueda para seleccionar una sucursal y visualizar sus diarios.
                    </p>
                </div>

                <!-- ==================== LISTA DE DIARIOS ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <i class="fas fa-list text-[10px] text-primary-600"></i>
                            <h2 class="text-xs font-semibold text-gray-800">
                                Diarios de {{ sucursalNombre }}
                            </h2>
                            <span class="text-[9px] text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded-full">
                                {{ diariosFiltrados.length }}
                            </span>
                        </div>
                        
                        <!-- Filtros desktop -->
                        <div class="hidden sm:flex gap-1">
                            <button 
                                v-for="filtro in filtros"
                                :key="filtro.id"
                                @click="filtrandoPor = filtro.id"
                                class="px-2.5 py-1 text-[10px] rounded-md transition flex items-center gap-1"
                                :class="filtrandoPor === filtro.id ? 'text-white bg-primary-600' : 'text-gray-600 bg-gray-100 hover:bg-gray-200'"
                            >
                                <i :class="`fas ${filtro.icono} text-[9px]`"></i>
                                {{ filtro.nombre }}
                            </button>
                        </div>
                        
                        <!-- Filtros mobile -->
                        <div class="sm:hidden">
                            <select v-model="filtrandoPor" class="border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option v-for="filtro in filtros" :key="filtro.id" :value="filtro.id">
                                    {{ filtro.nombre }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Loading -->
                    <div v-if="cargando" class="p-8 text-center">
                        <i class="fas fa-spinner fa-spin text-2xl text-primary-600"></i>
                        <p class="text-gray-500 mt-2 text-sm">Cargando diarios...</p>
                    </div>

                    <div v-else class="relative overflow-x-auto" style="max-height: 60vh; overflow-y: auto;">
                        <!-- VISTA MÓVIL -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="diario in diariosFiltrados" :key="diario.id" class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start mb-1.5">
                                    <span class="font-mono font-bold text-sm text-primary-700">#{{ diario.numero }}</span>
                                    <div class="flex gap-2">
                                        <button @click="seleccionarDiarioDesdeLista(diario)" class="text-primary-500 hover:text-primary-700 text-xs p-1" title="Seleccionar">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                        <button @click="imprimirDiario(diario)" class="text-primary-600 hover:text-primary-800 text-xs p-1" title="Imprimir">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-600">{{ diario.tipo }}</div>
                                <div class="flex justify-between items-center mt-1">
                                    <div class="text-[10px] text-gray-400">
                                        <i class="far fa-calendar-alt mr-1"></i> {{ diario.fecha }}
                                    </div>
                                    <div class="text-[10px] text-gray-500 truncate max-w-[120px]">
                                        <i class="fas fa-user mr-1"></i> {{ diario.operador }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="!diariosFiltrados.length" class="text-center text-gray-400 py-8">
                                <i class="fas fa-folder-open text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay diarios contabilizados en esta sucursal</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">N° Diario</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Operador</th>
                                    <th class="px-3 py-1.5 text-right text-[9px] font-medium text-gray-500 uppercase w-24">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="diario in diariosFiltrados" :key="diario.id" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 text-sm font-mono font-bold text-primary-700">#{{ diario.numero }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-600">{{ diario.tipo }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ diario.fecha }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 truncate max-w-[150px]">{{ diario.operador }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                        <button @click="seleccionarDiarioDesdeLista(diario)" class="text-primary-500 hover:text-primary-700 text-xs p-1 transition mr-1" title="Seleccionar">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                        <button @click="imprimirDiario(diario)" class="text-primary-600 hover:text-primary-800 text-xs p-1 transition" title="Imprimir">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!diariosFiltrados.length">
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-folder-open text-2xl mb-1 block"></i>
                                        No hay diarios contabilizados en esta sucursal
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== INFORMACIÓN ==================== -->
                <div class="mt-3 p-2.5 bg-primary-50 rounded-xl border border-primary-100 text-xs text-primary-700 flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5 text-primary-500 text-[10px]"></i>
                    <div>
                        <span class="font-medium">Instrucciones:</span>
                        <p class="text-[10px] mt-0.5">Seleccione una sucursal, luego busque por número de diario o seleccione directamente de la lista. <strong>Todos los diarios mostrados están contabilizados.</strong></p>
                    </div>
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