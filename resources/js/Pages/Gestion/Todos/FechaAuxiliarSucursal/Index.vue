<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: {
        type: Array,
        default: () => []
    },
    fechas: {
        type: Array,
        default: () => []
    },
    registros: {
        type: Array,
        default: () => []
    }
})

// Estado
const registrosLocal = ref([...props.registros])
const nuevaFila = ref({
    sucursal_id: '',
    fecha_id: '',
    agregando: false
})
const loading = ref(false)
const eliminandoId = ref(null)

// 🔥 AUTOCOMPLETADO PARA SUCURSAL
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

// 🔥 AUTOCOMPLETADO PARA FECHA
const fechaBusqueda = ref('')
const mostrarFechas = ref(false)

// Toast state
const toast = ref({
    show: false,
    message: '',
    type: 'success',
    title: ''
})

let toastTimeout = null

// Mostrar toast
const showToast = (message, type = 'success', title = '') => {
    if (toastTimeout) clearTimeout(toastTimeout)
    
    const titles = {
        success: '¡Éxito!',
        error: '¡Error!',
        warning: '¡Atención!',
        info: 'Información'
    }
    
    toast.value = {
        show: true,
        message: message,
        type: type,
        title: title || titles[type] || titles.success
    }
    
    toastTimeout = setTimeout(() => {
        toast.value.show = false
    }, 4000)
}

// 🔥 COMPUTADOS PARA FILTRADO
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.display?.toLowerCase().includes(termino)
    )
})

const fechasDisponibles = computed(() => {
    if (!props.fechas) return []
    if (!fechaBusqueda.value) return props.fechas
    
    const termino = fechaBusqueda.value.toLowerCase()
    return props.fechas.filter(f => 
        f.display?.toLowerCase().includes(termino)
    )
})

// 🔥 OBTENER DISPLAY
const getSucursalDisplay = (id) => {
    const sucursal = props.sucursales.find(s => s.id == id)
    return sucursal?.display || ''
}

const getFechaDisplay = (id) => {
    const fecha = props.fechas.find(f => f.IdFecha == id)
    return fecha?.display || ''
}

// 🔥 ACCIONES DE AUTOCOMPLETADO - SUCURSAL
const seleccionarSucursal = (sucursal) => {
    nuevaFila.value.sucursal_id = sucursal.id
    sucursalBusqueda.value = sucursal.display
    mostrarSucursales.value = false
}

const limpiarSucursal = () => {
    nuevaFila.value.sucursal_id = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
}

// 🔥 ACCIONES DE AUTOCOMPLETADO - FECHA
const seleccionarFecha = (fecha) => {
    nuevaFila.value.fecha_id = fecha.IdFecha
    fechaBusqueda.value = fecha.display
    mostrarFechas.value = false
}

const limpiarFecha = () => {
    nuevaFila.value.fecha_id = ''
    fechaBusqueda.value = ''
    mostrarFechas.value = false
}

// Agregar nueva fila (mostrar selectores)
const agregarFila = () => {
    nuevaFila.value.agregando = true
    // Limpiar búsquedas
    sucursalBusqueda.value = ''
    fechaBusqueda.value = ''
}

// Cancelar agregar
const cancelarAgregar = () => {
    nuevaFila.value = {
        sucursal_id: '',
        fecha_id: '',
        agregando: false
    }
    sucursalBusqueda.value = ''
    fechaBusqueda.value = ''
}

// Guardar nueva fila
const guardarNuevaFila = async () => {
    if (!nuevaFila.value.sucursal_id) {
        showToast('Seleccione una sucursal', 'error')
        return
    }
    if (!nuevaFila.value.fecha_id) {
        showToast('Seleccione una fecha', 'error')
        return
    }
    
    loading.value = true
    
    try {
        const response = await axios.post('/gestion/todos/fecha-auxiliar-sucursal', {
            sucursal_id: nuevaFila.value.sucursal_id,
            fecha_id: nuevaFila.value.fecha_id
        })
        
        if (response.data.success) {
            registrosLocal.value.unshift(response.data.registro)
            cancelarAgregar()
            showToast(response.data.message || 'Registro agregado correctamente', 'success')
        } else {
            showToast(response.data.message || 'Error al guardar', 'error')
        }
    } catch (error) {
        const message = error.response?.data?.message || error.response?.data?.error || 'Error al guardar el registro'
        showToast(message, 'error')
    } finally {
        loading.value = false
    }
}

// Eliminar registro
const eliminarRegistro = async (id, display) => {
    eliminandoId.value = id
    
    try {
        const response = await axios.delete(`/gestion/todos/fecha-auxiliar-sucursal/${id}`)
        
        if (response.data.success) {
            registrosLocal.value = registrosLocal.value.filter(r => r.id !== id)
            showToast(`Registro "${display}" eliminado correctamente`, 'success')
        } else {
            showToast(response.data.message || 'Error al eliminar', 'error')
        }
    } catch (error) {
        const message = error.response?.data?.message || error.response?.data?.error || 'Error al eliminar el registro'
        showToast(message, 'error')
    } finally {
        eliminandoId.value = null
    }
}

// 🔥 CERRAR SUGERENCIAS AL HACER CLICK FUERA
const handleClickOutside = (event) => {
    const sucursalContainer = document.querySelector('.sucursal-autocomplete')
    if (sucursalContainer && !sucursalContainer.contains(event.target)) {
        mostrarSucursales.value = false
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
    <div class="min-h-screen relative" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <!-- Toast Notification -->
        <div 
            v-if="toast.show"
            class="fixed top-4 right-4 left-4 sm:left-auto sm:right-4 sm:min-w-[320px] z-50 animate-slide-in"
        >
            <div 
                class="rounded-lg shadow-lg p-4 flex items-start gap-3 border-l-4"
                :class="{
                    'bg-green-50 border-green-500': toast.type === 'success',
                    'bg-red-50 border-red-500': toast.type === 'error',
                    'bg-yellow-50 border-yellow-500': toast.type === 'warning',
                    'bg-blue-50 border-blue-500': toast.type === 'info'
                }"
            >
                <div class="flex-shrink-0">
                    <i v-if="toast.type === 'success'" class="fas fa-check-circle text-green-500 text-lg"></i>
                    <i v-else-if="toast.type === 'error'" class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                    <i v-else-if="toast.type === 'warning'" class="fas fa-exclamation-triangle text-yellow-500 text-lg"></i>
                    <i v-else-if="toast.type === 'info'" class="fas fa-info-circle text-blue-500 text-lg"></i>
                </div>
                
                <div class="flex-1">
                    <h4 class="font-semibold text-sm" :class="{
                        'text-green-800': toast.type === 'success',
                        'text-red-800': toast.type === 'error',
                        'text-yellow-800': toast.type === 'warning',
                        'text-blue-800': toast.type === 'info'
                    }">{{ toast.title }}</h4>
                    <p class="text-xs mt-0.5" :class="{
                        'text-green-700': toast.type === 'success',
                        'text-red-700': toast.type === 'error',
                        'text-yellow-700': toast.type === 'warning',
                        'text-blue-700': toast.type === 'info'
                    }">{{ toast.message }}</p>
                </div>
                
                <button 
                    @click="toast.show = false"
                    class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition"
                >
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
        </div>

        <div class="py-3 sm:py-4 md:py-6 px-2 sm:px-4 md:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 md:p-5 mb-3 sm:mb-4 md:mb-6">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 md:w-10 md:h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                             :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                            <i class="fas fa-calendar-alt text-sm sm:text-base md:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg md:text-xl font-bold text-gray-800">Administra - Apertura Fechas</h1>
                            <p class="text-[10px] sm:text-xs text-gray-500 hidden sm:block">Gestión de apertura de fechas por sucursal</p>
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-500 mt-1.5 sm:hidden">Gestión de apertura de fechas por sucursal</p>
                </div>

                <!-- Botón Nuevo -->
                <div class="mb-3 sm:mb-4">
                    <button 
                        @click="agregarFila"
                        v-if="!nuevaFila.agregando"
                        class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-white text-xs sm:text-sm font-medium shadow-sm hover:shadow-md transition-all"
                        :style="{ backgroundColor: `var(--color-primary-600)` }"
                    >
                        <i class="fas fa-plus text-[10px] sm:text-xs"></i>
                        <span>Nuevo</span>
                    </button>
                </div>

                <!-- Versión Desktop: Tabla -->
                <div class="hidden md:block bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Sucursal</th>
                                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Fecha</th>
                                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Fecha Apertura</th>
                                    <th class="px-3 py-2.5 text-center text-[11px] font-semibold text-gray-600 uppercase tracking-wider w-24">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <!-- Fila para agregar nuevo -->
                                <tr v-if="nuevaFila.agregando" class="bg-primary-50">
                                    <td class="px-3 py-2">
                                        <!-- 🔥 AUTOCOMPLETADO SUCURSAL -->
                                        <div class="sucursal-autocomplete relative">
                                            <input 
                                                type="text"
                                                v-model="sucursalBusqueda"
                                                @focus="mostrarSucursales = true"
                                                @input="mostrarSucursales = true"
                                                class="w-full border rounded-lg px-2 py-1.5 text-sm pr-6"
                                                :style="{ borderColor: `var(--color-primary-300)` }"
                                                placeholder="Escriba para buscar..."
                                                autocomplete="off"
                                            />
                                            <button 
                                                v-if="sucursalBusqueda"
                                                @click="limpiarSucursal"
                                                class="absolute right-1 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-[10px]"
                                                type="button"
                                            >
                                                <i class="fas fa-times"></i>
                                            </button>
                                            
                                            <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                                class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div 
                                                    v-for="s in sucursalesDisponibles" 
                                                    :key="s.id"
                                                    @click="seleccionarSucursal(s)"
                                                    class="px-2 py-1.5 cursor-pointer border-b last:border-b-0 text-sm hover:bg-gray-50"
                                                    :class="nuevaFila.sucursal_id === s.id ? 'bg-primary-50' : ''"
                                                    :style="nuevaFila.sucursal_id === s.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                                >
                                                    {{ s.display }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <!-- 🔥 AUTOCOMPLETADO FECHA -->
                                        <div class="fecha-autocomplete relative">
                                            <input 
                                                type="text"
                                                v-model="fechaBusqueda"
                                                @focus="mostrarFechas = true"
                                                @input="mostrarFechas = true"
                                                class="w-full border rounded-lg px-2 py-1.5 text-sm pr-6"
                                                :style="{ borderColor: `var(--color-primary-300)` }"
                                                placeholder="Escriba para buscar..."
                                                autocomplete="off"
                                            />
                                            <button 
                                                v-if="fechaBusqueda"
                                                @click="limpiarFecha"
                                                class="absolute right-1 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-[10px]"
                                                type="button"
                                            >
                                                <i class="fas fa-times"></i>
                                            </button>
                                            
                                            <div v-if="mostrarFechas && fechasDisponibles.length > 0" 
                                                class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <div 
                                                    v-for="f in fechasDisponibles" 
                                                    :key="f.IdFecha"
                                                    @click="seleccionarFecha(f)"
                                                    class="px-2 py-1.5 cursor-pointer border-b last:border-b-0 text-sm hover:bg-gray-50"
                                                    :class="nuevaFila.fecha_id === f.IdFecha ? 'bg-primary-50' : ''"
                                                    :style="nuevaFila.fecha_id === f.IdFecha ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                                >
                                                    {{ f.display }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-400">
                                        <i class="fas fa-clock mr-1"></i> Se registrará automáticamente
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex gap-1.5 justify-center">
                                            <button 
                                                @click="guardarNuevaFila"
                                                :disabled="loading"
                                                class="px-2.5 py-1 bg-green-600 text-white rounded text-xs font-medium flex items-center gap-1 hover:bg-green-700 transition"
                                            >
                                                <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                                                <i v-else class="fas fa-save text-[10px]"></i>
                                                <span class="text-[11px]">Guardar</span>
                                            </button>
                                            <button 
                                                @click="cancelarAgregar"
                                                class="px-2.5 py-1 bg-gray-200 text-gray-700 rounded text-xs font-medium hover:bg-gray-300 transition"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Registros existentes -->
                                <tr v-for="registro in registrosLocal" :key="registro.id" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2.5 text-sm text-gray-700">
                                        <i class="fas fa-store text-gray-400 mr-2 text-xs"></i>
                                        <span class="break-words">{{ registro.sucursal_display || getSucursalDisplay(registro.sucursal_id) }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-gray-700">
                                        <i class="fas fa-calendar-day text-gray-400 mr-2 text-xs"></i>
                                        {{ registro.fecha_display || getFechaDisplay(registro.fecha_id) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-gray-500">
                                        <i class="fas fa-clock text-gray-400 mr-2 text-xs"></i>
                                        {{ registro.fecha_apertura }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <button 
                                            @click="eliminarRegistro(registro.id, registro.sucursal_display)"
                                            :disabled="eliminandoId === registro.id"
                                            class="text-red-500 hover:text-red-700 transition px-2 py-1 rounded-lg hover:bg-red-50"
                                        >
                                            <i v-if="eliminandoId === registro.id" class="fas fa-spinner fa-spin"></i>
                                            <i v-else class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                
                                <tr v-if="registrosLocal.length === 0 && !nuevaFila.agregando">
                                    <td colspan="4" class="px-3 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-calendar-times text-3xl mb-2 block"></i>
                                        No hay registros para mostrar
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Versión Móvil/Tablet: Cards -->
                <div class="md:hidden space-y-3">
                    <!-- Card para agregar nuevo -->
                    <div v-if="nuevaFila.agregando" class="bg-white rounded-xl shadow-sm overflow-hidden border-2" :style="{ borderColor: `var(--color-primary-200)` }">
                        <div class="p-3" :style="{ backgroundColor: `var(--color-primary-50)` }">
                            <div class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-plus-circle" :style="{ color: `var(--color-primary-600)` }"></i>
                                <span>Nuevo Registro</span>
                            </div>
                            <div class="space-y-2">
                                <div>
                                    <label class="text-[10px] font-medium text-gray-500 block mb-0.5">Sucursal</label>
                                    <div class="sucursal-autocomplete relative">
                                        <input 
                                            type="text"
                                            v-model="sucursalBusqueda"
                                            @focus="mostrarSucursales = true"
                                            @input="mostrarSucursales = true"
                                            class="w-full border rounded-lg px-2 py-1.5 text-sm pr-6"
                                            :style="{ borderColor: `var(--color-primary-300)` }"
                                            placeholder="Escriba para buscar..."
                                            autocomplete="off"
                                        />
                                        <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                            class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                            <div 
                                                v-for="s in sucursalesDisponibles" 
                                                :key="s.id"
                                                @click="seleccionarSucursal(s)"
                                                class="px-2 py-1.5 cursor-pointer border-b last:border-b-0 text-sm hover:bg-gray-50"
                                            >
                                                {{ s.display }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-medium text-gray-500 block mb-0.5">Fecha</label>
                                    <div class="fecha-autocomplete relative">
                                        <input 
                                            type="text"
                                            v-model="fechaBusqueda"
                                            @focus="mostrarFechas = true"
                                            @input="mostrarFechas = true"
                                            class="w-full border rounded-lg px-2 py-1.5 text-sm pr-6"
                                            :style="{ borderColor: `var(--color-primary-300)` }"
                                            placeholder="Escriba para buscar..."
                                            autocomplete="off"
                                        />
                                        <div v-if="mostrarFechas && fechasDisponibles.length > 0" 
                                            class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                            <div 
                                                v-for="f in fechasDisponibles" 
                                                :key="f.IdFecha"
                                                @click="seleccionarFecha(f)"
                                                class="px-2 py-1.5 cursor-pointer border-b last:border-b-0 text-sm hover:bg-gray-50"
                                            >
                                                {{ f.display }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2 mt-3 pt-2 border-t border-gray-100">
                                <button 
                                    @click="guardarNuevaFila"
                                    :disabled="loading"
                                    class="flex-1 py-2 bg-green-600 text-white rounded-lg text-sm font-medium flex items-center justify-center gap-1.5 disabled:opacity-50"
                                >
                                    <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                                    <i v-else class="fas fa-save"></i>
                                    {{ loading ? 'Guardando...' : 'Guardar' }}
                                </button>
                                <button 
                                    @click="cancelarAgregar"
                                    class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Cards de registros existentes -->
                    <div v-for="registro in registrosLocal" :key="registro.id" class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                        <div class="p-3 border-l-4" :style="{ borderLeftColor: `var(--color-primary-500)` }">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 mb-2">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: `var(--color-primary-100)` }">
                                            <i class="fas fa-store text-[10px]" :style="{ color: `var(--color-primary-600)` }"></i>
                                        </div>
                                        <span class="font-semibold text-sm text-gray-800 truncate">{{ registro.sucursal_display || getSucursalDisplay(registro.sucursal_id) }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-gray-500">
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-calendar-day w-3 text-gray-400"></i>
                                            <span>Fecha:</span>
                                            <span class="font-medium text-gray-700">{{ registro.fecha_display || getFechaDisplay(registro.fecha_id) }}</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-clock w-3 text-gray-400"></i>
                                            <span>Apertura:</span>
                                            <span class="font-medium text-gray-700">{{ registro.fecha_apertura }}</span>
                                        </div>
                                    </div>
                                </div>
                                <button 
                                    @click="eliminarRegistro(registro.id, registro.sucursal_display)"
                                    :disabled="eliminandoId === registro.id"
                                    class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-red-500 hover:bg-red-50 transition ml-2"
                                >
                                    <i v-if="eliminandoId === registro.id" class="fas fa-spinner fa-spin text-sm"></i>
                                    <i v-else class="fas fa-trash-alt text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="registrosLocal.length === 0 && !nuevaFila.agregando" class="bg-white rounded-xl shadow-sm p-6 text-center">
                        <i class="fas fa-calendar-times text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-gray-400 text-sm">No hay registros para mostrar</p>
                    </div>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 rounded-lg text-[10px] sm:text-xs"
                     :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Información:</strong> Agregue filas seleccionando la sucursal y la fecha. 
                    La fecha de apertura se registrará automáticamente con la fecha y hora actual del servidor.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
select:focus, input:focus {
    --tw-ring-color: var(--color-primary-500);
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.animate-slide-in {
    animation: slideIn 0.3s ease-out;
}

.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.transition-shadow {
    transition-property: box-shadow;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 200ms;
}

@media (max-width: 640px) {
    .space-y-3 {
        margin-top: 0.75rem;
    }
}

@media (hover: none) {
    button:active {
        transform: scale(0.97);
    }
}
</style>