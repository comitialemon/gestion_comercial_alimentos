<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: Array,
    sucursalId: Number,
    sucursalNombre: String,
    esSupervisor: Boolean,
    diariosRecientes: Object,
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
const form = ref({
    numero_diario: '',
    sucursal_id: props.sucursalId || '',
})

const diariosSugeridos = ref([])
const diarioSeleccionado = ref(null)
const buscando = ref(false)
const errorBusqueda = ref('')
const mostrarSugerencias = ref(false)
const filtrandoPor = ref('todos')

// ==================== COMPUTED ====================
const listaDiarios = computed(() => {
    return props.diariosRecientes?.data || []
})

const diariosFiltrados = computed(() => {
    if (!listaDiarios.value.length) return []
    if (filtrandoPor.value === 'todos') return listaDiarios.value
    
    const hoy = new Date()
    hoy.setHours(0, 0, 0, 0)
    
    const inicioSemana = new Date(hoy)
    inicioSemana.setDate(hoy.getDate() - hoy.getDay())
    
    const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
    
    return listaDiarios.value.filter(diario => {
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

// ==================== BUSCADOR ====================
const buscarDiarios = async () => {
    const q = form.value.numero_diario.trim()
    
    if (q.length === 0) {
        diariosSugeridos.value = []
        mostrarSugerencias.value = false
        errorBusqueda.value = ''
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
        // 🔥 URL CORREGIDA: agregar /contabilidad/
        const response = await axios.get('/gestion/contabilidad/imprimir-diario/buscar', {
            params: { q: q, sucursal_id: form.value.sucursal_id }
        })
        
        if (response.data.success && response.data.diarios.length > 0) {
            diariosSugeridos.value = response.data.diarios
            mostrarSugerencias.value = true
            errorBusqueda.value = ''
            
            if (response.data.diarios.length === 1) {
                seleccionarDiario(response.data.diarios[0])
            }
        } else {
            diariosSugeridos.value = []
            mostrarSugerencias.value = false
            errorBusqueda.value = `No se encontró el diario N° ${q} en esta sucursal`
        }
    } catch (error) {
        console.error('Error buscando:', error)
        diariosSugeridos.value = []
        mostrarSugerencias.value = false
        errorBusqueda.value = error.response?.data?.message || 'Error al buscar. Intente nuevamente.'
    } finally {
        buscando.value = false
    }
}

const buscarAlPresionarEnter = () => buscarDiarios()
const buscarAlHacerClic = () => buscarDiarios()

const seleccionarDiario = (diario) => {
    diarioSeleccionado.value = diario
    form.value.numero_diario = diario.numero.toString()
    mostrarSugerencias.value = false
    errorBusqueda.value = ''
}

const limpiarBusqueda = () => {
    form.value.numero_diario = ''
    diarioSeleccionado.value = null
    diariosSugeridos.value = []
    errorBusqueda.value = ''
    mostrarSugerencias.value = false
}

const imprimirDiario = (diario) => {
    const id = diario.id || diarioSeleccionado.value?.id
    if (!id) return
    // 🔥 URL CORREGIDA: agregar /contabilidad/
    window.open(`/gestion/contabilidad/imprimir-diario/pdf/${id}`, '_blank')
}

const verDetalle = (diario) => {
    seleccionarDiario(diario)
    setTimeout(() => {
        document.querySelector('.resultado-seleccionado')?.scrollIntoView({ 
            behavior: 'smooth', block: 'center' 
        })
    }, 100)
}

const handleClickOutside = (event) => {
    const container = document.querySelector('.autocomplete-container')
    if (container && !container.contains(event.target)) {
        mostrarSugerencias.value = false
    }
}

const onInput = () => {
    const q = form.value.numero_diario.trim()
    if (q === '') {
        diariosSugeridos.value = []
        mostrarSugerencias.value = false
        errorBusqueda.value = ''
        diarioSeleccionado.value = null
    } else {
        if (diarioSeleccionado.value && form.value.numero_diario !== diarioSeleccionado.value.numero.toString()) {
            diarioSeleccionado.value = null
        }
        errorBusqueda.value = ''
    }
}

const volver = () => router.get('/oficial')
// 🔥 URL CORREGIDA: agregar /contabilidad/
const irPorSucursal = () => router.get('/gestion/contabilidad/imprimir-diario/por-sucursal')

// ==================== FILTROS ====================
const filtros = [
    { id: 'todos', nombre: 'Todos', icono: 'fa-list' },
    { id: 'hoy', nombre: 'Hoy', icono: 'fa-sun' },
    { id: 'semana', nombre: 'Semana', icono: 'fa-calendar-week' },
    { id: 'mes', nombre: 'Mes', icono: 'fa-calendar-alt' },
]

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- HEADER -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-print text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Imprimir Diario</h1>
                            <p class="text-xs text-gray-500">Busque por número exacto o seleccione de la lista</p>
                        </div>
                    </div>
                    <button 
                        v-if="esSupervisor"
                        @click="irPorSucursal"
                        class="px-3 py-1.5 bg-primary-50 text-primary-700 rounded-md text-xs font-medium hover:bg-primary-100 transition flex items-center gap-1.5 border border-primary-200"
                    >
                        <i class="fas fa-store text-[10px]"></i>
                        <span>Ver por sucursal</span>
                    </button>
                </div>

                <!-- BANNER SUCURSAL -->
                <div class="rounded-xl p-3 mb-4 bg-primary-50 border-l-4 border-primary-600">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 bg-primary-100 text-primary-600">
                            <i class="fas fa-store text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-medium text-primary-600">Sucursal actual</p>
                            <p class="text-sm font-bold text-primary-800">{{ sucursalNombre || 'No seleccionada' }}</p>
                        </div>
                    </div>
                </div>

                <!-- BUSCADOR -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <h3 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1.5">
                        <i class="fas fa-search text-[10px] text-primary-600"></i>
                        Buscar por número exacto
                    </h3>
                    
                    <div class="relative autocomplete-container">
                        <input 
                            type="text" 
                            v-model="form.numero_diario"
                            @input="onInput"
                            @keyup.enter="buscarAlPresionarEnter"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm pr-20 focus:ring-primary-500 focus:border-primary-500 outline-none"
                            placeholder="Escribe el número y presiona Enter o la lupa..."
                            autocomplete="off"
                            inputmode="numeric"
                        />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex gap-1 items-center">
                            <div v-if="buscando" class="text-gray-400 px-1">
                                <i class="fas fa-spinner fa-spin text-[10px]"></i>
                            </div>
                            <button 
                                v-if="form.numero_diario && !buscando"
                                @click="buscarAlHacerClic" 
                                class="bg-primary-600 text-white px-2 py-1 rounded-md hover:bg-primary-700 transition flex items-center justify-center"
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
                        
                        <div v-if="mostrarSugerencias && diariosSugeridos.length > 1" 
                            class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto">
                            <div 
                                v-for="diario in diariosSugeridos" 
                                :key="diario.id"
                                @click="seleccionarDiario(diario)"
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
                    <p class="text-[9px] text-gray-400 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Ingrese el número exacto del diario y presione Enter o la lupa.
                    </p>

                    <!-- Error -->
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
                                @click="imprimirDiario(diarioSeleccionado)" 
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition text-xs font-medium flex items-center gap-1.5"
                            >
                                <i class="fas fa-print text-[10px]"></i> Imprimir
                            </button>
                        </div>
                    </div>
                </div>

                <!-- LISTA DE DIARIOS -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <i class="fas fa-list text-[10px] text-primary-600"></i>
                            <h3 class="text-xs font-semibold text-gray-800">
                                Diarios de {{ sucursalNombre || 'esta sucursal' }}
                            </h3>
                            <span class="text-[9px] text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded-full">
                                {{ diariosFiltrados.length }}
                            </span>
                        </div>
                        
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
                        
                        <div class="sm:hidden">
                            <select v-model="filtrandoPor" class="border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option v-for="filtro in filtros" :key="filtro.id" :value="filtro.id">
                                    {{ filtro.nombre }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="relative overflow-x-auto" style="max-height: 60vh; overflow-y: auto;">
                        <!-- VISTA MÓVIL -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="diario in diariosFiltrados" :key="diario.id" class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start mb-1.5">
                                    <span class="font-mono font-bold text-sm text-primary-700">#{{ diario.numero }}</span>
                                    <div class="flex gap-2">
                                        <button @click="verDetalle(diario)" class="text-primary-500 hover:text-primary-700 text-xs p-1" title="Seleccionar">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                        <button @click="imprimirDiario(diario)" class="text-primary-600 hover:text-primary-800 text-xs p-1" title="Imprimir">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-600">{{ diario.tipo }}</div>
                                <div class="text-[10px] text-gray-400 mt-0.5">
                                    <i class="far fa-calendar-alt mr-1"></i> {{ diario.fecha }}
                                </div>
                            </div>
                            <div v-if="!diariosFiltrados.length" class="text-center text-gray-400 py-8">
                                <i class="fas fa-folder-open text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay diarios en este período</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">N° Diario</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-3 py-1.5 text-right text-[9px] font-medium text-gray-500 uppercase w-24">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="diario in diariosFiltrados" :key="diario.id" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 text-sm font-mono font-bold text-primary-700">#{{ diario.numero }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-600">{{ diario.tipo }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ diario.fecha }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                        <button @click="verDetalle(diario)" class="text-primary-500 hover:text-primary-700 text-xs p-1 transition mr-1" title="Seleccionar">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                        <button @click="imprimirDiario(diario)" class="text-primary-600 hover:text-primary-800 text-xs p-1 transition" title="Imprimir">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!diariosFiltrados.length">
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-folder-open text-2xl mb-1 block"></i>
                                        No hay diarios en este período
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINACIÓN -->
                    <div v-if="diariosRecientes?.links && diariosRecientes.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <p class="text-[10px] text-gray-500">
                                Mostrando {{ diariosRecientes.from || 0 }} - {{ diariosRecientes.to || 0 }} de {{ diariosRecientes.total || 0 }}
                            </p>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link 
                                    v-for="link in diariosRecientes.links" 
                                    :key="link.label" 
                                    :href="link.url || '#'"
                                    class="px-2.5 py-0.5 rounded border text-[10px] transition"
                                    :class="[
                                        link.active ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300',
                                        !link.url ? 'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400' : 'cursor-pointer'
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VOLVER -->
                <div class="flex justify-end pt-3 mt-3">
                    <button type="button" @click="volver"
                        class="px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-100 transition text-xs font-medium flex items-center gap-1.5">
                        <i class="fas fa-arrow-left text-[10px]"></i> Volver al inicio
                    </button>
                </div>

                <!-- INFO -->
                <div class="mt-3 p-2.5 rounded-lg bg-primary-50 border border-primary-100 text-xs text-primary-700 flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5 text-primary-500 text-[10px]"></i>
                    <div>
                        <span class="font-medium">Sucursal actual: {{ sucursalNombre || 'No seleccionada' }}</span>
                        <p class="text-[10px] mt-0.5">Esta vista muestra todos los diarios contabilizados de la sucursal donde estás logueado.</p>
                        <p v-if="esSupervisor" class="text-[10px] mt-0.5">👑 Como supervisor, puedes usar "Ver por sucursal" para consultar otras sucursales.</p>
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