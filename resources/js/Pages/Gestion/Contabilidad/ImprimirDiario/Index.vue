<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: Array,
    sucursalId: Number,
    sucursalNombre: String,
    esSupervisor: Boolean,
    diariosRecientes: Array,
})

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
const menuAbierto = ref(false)

// Computed para filtrar diarios recientes
const diariosFiltrados = computed(() => {
    if (!props.diariosRecientes) return []
    
    if (filtrandoPor.value === 'todos') {
        return props.diariosRecientes
    }
    
    const hoy = new Date()
    hoy.setHours(0, 0, 0, 0)
    
    const inicioSemana = new Date(hoy)
    inicioSemana.setDate(hoy.getDate() - hoy.getDay())
    
    const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
    
    return props.diariosRecientes.filter(diario => {
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

// Buscar diarios mientras escribe
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
                sucursal_id: form.value.sucursal_id,
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

// Seleccionar un diario
const seleccionarDiario = (diario) => {
    diarioSeleccionado.value = diario
    form.value.numero_diario = diario.numero.toString()
    mostrarSugerencias.value = false
    errorBusqueda.value = ''
    if (window.innerWidth < 768) {
        menuAbierto.value = false
    }
}

// Limpiar búsqueda
const limpiarBusqueda = () => {
    form.value.numero_diario = ''
    diarioSeleccionado.value = null
    diariosSugeridos.value = []
    errorBusqueda.value = ''
    mostrarSugerencias.value = false
}

// Imprimir diario
const imprimirDiario = (diario) => {
    const id = diario.id || diarioSeleccionado.value?.id
    if (!id) return
    window.open(`/gestion/contabilidad/imprimir-diario/pdf/${id}`, '_blank')
}

// Ver detalle
const verDetalle = (diario) => {
    seleccionarDiario(diario)
    setTimeout(() => {
        document.querySelector('.resultado-seleccionado')?.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        })
    }, 100)
}

// Cerrar sugerencias al hacer clic fuera
const handleClickOutside = (event) => {
    const container = document.querySelector('.autocomplete-container')
    if (container && !container.contains(event.target)) {
        mostrarSugerencias.value = false
    }
}

// Debounce
let timeout
const onInput = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        buscarDiarios()
    }, 300)
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    clearTimeout(timeout)
})

const volver = () => {
    router.get('/oficial')
}

const irPorSucursal = () => {
    router.get('/gestion/imprimir-diario/por-sucursal')
}

// Filtros rápidos
const filtros = [
    { id: 'todos', nombre: 'Todos', icono: 'fa-list' },
    { id: 'hoy', nombre: 'Hoy', icono: 'fa-sun' },
    { id: 'semana', nombre: 'Semana', icono: 'fa-calendar-week' },
    { id: 'mes', nombre: 'Mes', icono: 'fa-calendar-alt' },
]
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <div class="py-4 sm:py-6 px-3 sm:px-4 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center" 
                                 :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                <i class="fas fa-print text-base sm:text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-lg sm:text-xl font-bold text-gray-800">Imprimir Diario</h1>
                                <p class="text-xs text-gray-500 hidden sm:block">Busque por número o seleccione de la lista</p>
                            </div>
                        </div>
                        <button 
                            v-if="esSupervisor"
                            @click="irPorSucursal"
                            class="px-3 py-1.5 text-xs rounded-lg transition flex items-center justify-center gap-1 sm:w-auto w-full"
                            :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }"
                        >
                            <i class="fas fa-store text-xs"></i>
                            <span>Ver por sucursal</span>
                        </button>
                    </div>
                </div>

                <!-- Banner Sucursal Actual -->
                <div class="rounded-xl p-3 sm:p-4 mb-4 sm:mb-6"
                     :style="{ backgroundColor: `var(--color-primary-50)`, borderLeftColor: `var(--color-primary-600)` }"
                     style="border-left-width: 4px;">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                 :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                <i class="fas fa-store text-xs sm:text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs font-medium" :style="{ color: `var(--color-primary-600)` }">Sucursal actual</p>
                                <p class="text-sm sm:text-base font-bold" :style="{ color: `var(--color-primary-800)` }">
                                    {{ sucursalNombre || 'No seleccionada' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buscador -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3 sm:mb-4 flex items-center gap-2">
                        <i class="fas fa-search text-sm" :style="{ color: `var(--color-primary-600)` }"></i>
                        Buscar por número
                    </h3>
                    
                    <div class="relative autocomplete-container">
                        <input 
                            type="text" 
                            v-model="form.numero_diario"
                            @input="onInput"
                            @focus="form.numero_diario && diariosSugeridos.length > 0 ? mostrarSugerencias = true : null"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2.5 sm:py-3 text-sm pr-20 focus:ring-2 focus:outline-none"
                            :style="{ focusRingColor: `var(--color-primary-500)` }"
                            placeholder="Escribe el número de diario..."
                            autocomplete="off"
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
                        
                        <!-- Sugerencias -->
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
                    <p class="text-xs text-gray-400 mt-2">Ingrese el número y seleccione de la lista</p>

                    <!-- Resultado seleccionado -->
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
                                @click="imprimirDiario(diarioSeleccionado)" 
                                class="px-4 py-2 text-white rounded-lg transition text-sm flex items-center justify-center gap-2"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i class="fas fa-print text-xs"></i> Imprimir
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Lista de diarios recientes -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Header con filtros -->
                    <div class="px-4 sm:px-6 py-3 sm:py-4 bg-gray-50 border-b flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-list text-sm" :style="{ color: `var(--color-primary-600)` }"></i>
                            <h3 class="font-semibold text-gray-800 text-sm sm:text-base">
                                Diarios de {{ sucursalNombre || 'esta sucursal' }}
                            </h3>
                            <span class="text-xs text-gray-400 bg-gray-200 px-2 py-0.5 rounded-full">
                                {{ diariosFiltrados.length }}
                            </span>
                        </div>
                        
                        <!-- Filtros responsive - desktop -->
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
                        
                        <!-- Filtros responsive - mobile (select) -->
                        <div class="sm:hidden">
                            <select v-model="filtrandoPor" class="w-full border rounded-lg px-3 py-2 text-sm"
                                    :style="{ borderColor: `var(--color-primary-300)` }">
                                <option v-for="filtro in filtros" :key="filtro.id" :value="filtro.id">
                                    {{ filtro.nombre }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Tabla desktop -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Diario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="diario in diariosFiltrados" :key="diario.id" class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <span class="font-mono font-bold text-sm" :style="{ color: `var(--color-primary-700)` }">#{{ diario.numero }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ diario.tipo }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ diario.fecha }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                        <button 
                                            @click="verDetalle(diario)"
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
                                <tr v-if="!diariosFiltrados.length">
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                                        <i class="fas fa-folder-open text-3xl mb-2 block"></i>
                                        No hay diarios en este período
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Cards mobile -->
                    <div class="md:hidden divide-y divide-gray-100">
                        <div v-for="diario in diariosFiltrados" :key="diario.id" class="p-4 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-mono font-bold text-base" :style="{ color: `var(--color-primary-700)` }">#{{ diario.numero }}</span>
                                <div class="flex gap-3">
                                    <button @click="verDetalle(diario)" :style="{ color: `var(--color-primary-500)` }" title="Seleccionar">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <button @click="imprimirDiario(diario)" :style="{ color: `var(--color-primary-600)` }" title="Imprimir">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="text-sm text-gray-600">{{ diario.tipo }}</div>
                            <div class="text-xs text-gray-400 mt-1">
                                <i class="far fa-calendar-alt mr-1"></i> {{ diario.fecha }}
                            </div>
                        </div>
                        <div v-if="!diariosFiltrados.length" class="p-8 text-center text-gray-400">
                            <i class="fas fa-folder-open text-3xl mb-2 block"></i>
                            No hay diarios en este período
                        </div>
                    </div>
                </div>

                <!-- Botón volver -->
                <div class="flex justify-end pt-5 mt-3">
                    <button 
                        type="button"
                        @click="volver"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-sm sm:text-base"
                    >
                        Volver al inicio
                    </button>
                </div>

                <!-- Info -->
                <div class="mt-4 p-3 rounded-lg text-xs"
                     :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Sucursal actual: {{ sucursalNombre || 'No seleccionada' }}</strong><br class="sm:hidden">
                    <span class="text-xs">Esta vista muestra todos los diarios contabilizados de la sucursal donde estás logueado.</span>
                    <span v-if="esSupervisor" class="block mt-1">👑 Como supervisor, puedes usar "Ver por sucursal" para consultar otras sucursales.</span>
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

/* Hover para sugerencias */
.suggestion-hover:hover {
    background-color: var(--color-primary-50);
}

/* Transiciones suaves */
.transition {
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>