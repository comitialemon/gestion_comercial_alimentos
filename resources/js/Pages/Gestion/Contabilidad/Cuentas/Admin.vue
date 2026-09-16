<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    cuentas: {
        type: Array,
        default: () => []
    },
    monedas: {
        type: Array,
        default: () => []
    },
    cuentaEditar: {
        type: Object,
        default: null
    },
    editId: {
        type: Number,
        default: null
    }
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
const busqueda = ref('')
const tipoCuenta = ref('todos')
const estadoCuenta = ref('todos')
const mostrarFormulario = ref(false)
const loading = ref(false)
const modoEdicion = ref(false)
const cuentaSeleccionada = ref(null)
const showDeleteModal = ref(false)
const cuentaEliminar = ref(null)

// ==================== FORMULARIO ====================
const form = ref({
    IdCuenta: null,
    Cuenta: '',
    Descripcion: '',
    TipoDeCuenta: 'B',
    IdMoneda: '',
    ActivoFijo: false,
    AbiertoCerrado: false
})

// ==================== COMPUTED ====================
const cuentasFiltradas = computed(() => {
    let resultado = props.cuentas || []
    
    if (busqueda.value) {
        const termino = busqueda.value.toLowerCase()
        resultado = resultado.filter(c => 
            c.Cuenta?.toLowerCase().includes(termino) ||
            c.Descripcion?.toLowerCase().includes(termino)
        )
    }
    
    if (tipoCuenta.value !== 'todos') {
        resultado = resultado.filter(c => c.TipoDeCuenta === tipoCuenta.value)
    }
    
    if (estadoCuenta.value === 'abiertas') {
        resultado = resultado.filter(c => c.AbiertoCerrado == 0)
    } else if (estadoCuenta.value === 'cerradas') {
        resultado = resultado.filter(c => c.AbiertoCerrado == 1)
    }
    
    return resultado
})

const esEdicion = computed(() => modoEdicion.value && cuentaSeleccionada.value)

const tituloFormulario = computed(() => {
    return esEdicion.value ? 'Editar Cuenta' : 'Nueva Cuenta'
})

// ==================== FUNCIONES ====================
const limpiarFiltros = () => {
    busqueda.value = ''
    tipoCuenta.value = 'todos'
    estadoCuenta.value = 'todos'
}

const resetForm = () => {
    form.value = {
        IdCuenta: null,
        Cuenta: '',
        Descripcion: '',
        TipoDeCuenta: 'B',
        IdMoneda: '',
        ActivoFijo: false,
        AbiertoCerrado: false
    }
    modoEdicion.value = false
    cuentaSeleccionada.value = null
}

const abrirNuevo = () => {
    resetForm()
    mostrarFormulario.value = true
    modoEdicion.value = false
    setTimeout(() => {
        document.querySelector('.formulario-container')?.scrollIntoView({ behavior: 'smooth' })
    }, 100)
}

const editarCuenta = (cuenta) => {
    form.value = {
        IdCuenta: cuenta.IdCuenta,
        Cuenta: cuenta.Cuenta,
        Descripcion: cuenta.Descripcion,
        TipoDeCuenta: cuenta.TipoDeCuenta,
        IdMoneda: cuenta.IdMoneda,
        ActivoFijo: cuenta.ActivoFijo == 1,
        AbiertoCerrado: cuenta.AbiertoCerrado == 1
    }
    cuentaSeleccionada.value = cuenta
    modoEdicion.value = true
    mostrarFormulario.value = true
    
    setTimeout(() => {
        document.querySelector('.formulario-container')?.scrollIntoView({ behavior: 'smooth' })
    }, 100)
}

const confirmarEliminar = (cuenta) => {
    cuentaEliminar.value = cuenta
    showDeleteModal.value = true
}

const eliminarCuenta = () => {
    if (!cuentaEliminar.value) return
    
    loading.value = true
    router.delete(`/gestion/contabilidad/cuentas/${cuentaEliminar.value.IdCuenta}`, {
        onFinish: () => {
            loading.value = false
            showDeleteModal.value = false
            cuentaEliminar.value = null
        }
    })
}

const toggleEstado = (cuenta) => {
    if (!confirm(`¿Deseas ${cuenta.AbiertoCerrado == 0 ? 'cerrar' : 'abrir'} la cuenta "${cuenta.Cuenta}"?`)) return
    
    loading.value = true
    router.post(`/gestion/contabilidad/cuentas/${cuenta.IdCuenta}/toggle-estado`, {}, {
        onFinish: () => {
            loading.value = false
        }
    })
}

const guardarCuenta = () => {
    if (!form.value.Cuenta.trim()) {
        alert('El campo Cuenta es obligatorio')
        return
    }
    if (!form.value.Descripcion.trim()) {
        alert('El campo Descripción es obligatorio')
        return
    }
    if (!form.value.IdMoneda) {
        alert('Seleccione una moneda')
        return
    }
    
    loading.value = true
    
    const data = {
        Cuenta: form.value.Cuenta,
        Descripcion: form.value.Descripcion,
        TipoDeCuenta: form.value.TipoDeCuenta,
        IdMoneda: form.value.IdMoneda,
        ActivoFijo: form.value.ActivoFijo ? 1 : 0,
        AbiertoCerrado: form.value.AbiertoCerrado ? 1 : 0
    }
    
    if (esEdicion.value) {
        router.put(`/gestion/contabilidad/cuentas/${form.value.IdCuenta}`, data, {
            onFinish: () => {
                loading.value = false
                mostrarFormulario.value = false
                resetForm()
            }
        })
    } else {
        router.post('/gestion/contabilidad/cuentas', data, {
            onFinish: () => {
                loading.value = false
                mostrarFormulario.value = false
                resetForm()
            }
        })
    }
}

const cancelarEdicion = () => {
    mostrarFormulario.value = false
    resetForm()
}

const volver = () => {
    router.get('/oficial')
}

// ==================== ESTILOS ====================
const getTipoClase = (tipo) => {
    return tipo === 'B' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'
}

const getTipoTexto = (tipo) => {
    return tipo === 'B' ? 'Balance' : 'Resultado'
}

const getEstadoClase = (estado) => {
    return estado == 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'
}

const getEstadoTexto = (estado) => {
    return estado == 0 ? 'Abierta' : 'Cerrada'
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    
    if (props.cuentaEditar) {
        editarCuenta(props.cuentaEditar)
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-chart-line text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Administrar Plan de Cuentas</h1>
                            <p class="text-xs text-gray-500">Crear, editar y gestionar cuentas contables</p>
                        </div>
                    </div>
                    <button 
                        @click="abrirNuevo"
                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition"
                    >
                        <i class="fas fa-plus text-[10px]"></i> Nueva Cuenta
                    </button>
                </div>

                <!-- ==================== FORMULARIO ==================== -->
                <div v-if="mostrarFormulario" class="formulario-container bg-white rounded-xl shadow-sm p-3 mb-4 border border-primary-200">
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="text-xs font-semibold text-gray-800 flex items-center gap-1.5">
                            <i class="fas text-[10px] text-primary-600" :class="esEdicion ? 'fa-edit' : 'fa-plus-circle'"></i>
                            {{ tituloFormulario }}
                        </h2>
                        <button @click="cancelarEdicion" class="text-gray-400 hover:text-gray-600 text-sm">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Cuenta -->
                        <div class="flex-1 min-w-[100px]">
                            <label class="text-[10px] font-medium text-gray-500 block mb-0.5">
                                Cuenta <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.Cuenta"
                                type="text"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                placeholder="Ej: 1-01-01"
                            />
                        </div>

                        <!-- Descripción -->
                        <div class="flex-[2] min-w-[140px]">
                            <label class="text-[10px] font-medium text-gray-500 block mb-0.5">
                                Descripción <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.Descripcion"
                                type="text"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                placeholder="Descripción de la cuenta"
                            />
                        </div>

                        <!-- Tipo -->
                        <div class="min-w-[100px]">
                            <label class="text-[10px] font-medium text-gray-500 block mb-0.5">
                                Tipo <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.TipoDeCuenta"
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                                <option value="B">Balance</option>
                                <option value="P">Resultado</option>
                            </select>
                        </div>

                        <!-- Moneda -->
                        <div class="min-w-[100px]">
                            <label class="text-[10px] font-medium text-gray-500 block mb-0.5">
                                Moneda <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.IdMoneda"
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                                <option value="">Seleccione</option>
                                <option v-for="moneda in monedas" :key="moneda.IdMoneda" :value="moneda.IdMoneda">
                                    {{ moneda.Abreviatura || moneda.Abreviacion }}
                                </option>
                            </select>
                        </div>

                        <!-- Checkboxes -->
                        <div class="flex items-center gap-3 min-w-[140px]">
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input 
                                    v-model="form.ActivoFijo"
                                    type="checkbox"
                                    class="w-3.5 h-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                <span class="text-[10px] text-gray-600">Activo Fijo</span>
                            </label>
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input 
                                    v-model="form.AbiertoCerrado"
                                    type="checkbox"
                                    class="w-3.5 h-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                <span class="text-[10px] text-gray-600">Cerrada</span>
                            </label>
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-1.5 ml-auto">
                            <button 
                                @click="cancelarEdicion"
                                class="px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-100 transition text-xs"
                            >
                                Cancelar
                            </button>
                            <button 
                                @click="guardarCuenta"
                                :disabled="loading"
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition text-xs font-medium flex items-center gap-1 disabled:opacity-50"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas text-[10px]" :class="esEdicion ? 'fa-save' : 'fa-plus'"></i>
                                {{ loading ? '...' : (esEdicion ? 'Actualizar' : 'Crear') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Búsqueda -->
                        <div class="flex-1 min-w-[180px] max-w-[280px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                <input 
                                    type="text" 
                                    v-model="busqueda" 
                                    placeholder="Número o descripción..."
                                    class="w-full border border-gray-300 rounded-md pl-7 pr-7 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                />
                                <button 
                                    v-if="busqueda" 
                                    @click="busqueda = ''"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Tipo -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Tipo</label>
                            <select v-model="tipoCuenta" class="w-36 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="todos">Todos</option>
                                <option value="B">Balance (B)</option>
                                <option value="P">Resultado (P)</option>
                            </select>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Estado</label>
                            <select v-model="estadoCuenta" class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="todos">Todos</option>
                                <option value="abiertas">Abiertas</option>
                                <option value="cerradas">Cerradas</option>
                            </select>
                        </div>

                        <!-- Limpiar -->
                        <div class="flex gap-1.5 ml-auto">
                            <button 
                                @click="limpiarFiltros"
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1"
                            >
                                <i class="fas fa-eraser text-[10px]"></i> Limpiar
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-2 text-[10px] text-gray-500">
                        Mostrando <strong>{{ cuentasFiltradas.length }}</strong> de <strong>{{ cuentas?.length || 0 }}</strong> cuentas
                    </div>
                </div>

                <!-- ==================== TABLA DE CUENTAS ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="cuenta in cuentasFiltradas" :key="cuenta.IdCuenta" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start mb-1.5">
                                    <span class="font-mono font-bold text-sm text-primary-700">{{ cuenta.Cuenta }}</span>
                                    <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="getTipoClase(cuenta.TipoDeCuenta)">
                                        {{ getTipoTexto(cuenta.TipoDeCuenta) }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-700 mb-1.5">{{ cuenta.Descripcion }}</p>
                                <div class="flex flex-wrap justify-between items-center gap-2 text-[10px] pt-1.5 border-t border-gray-200">
                                    <span class="text-gray-500">Moneda: <strong>{{ cuenta.moneda?.Abreviacion || '-' }}</strong></span>
                                    <span :class="getEstadoClase(cuenta.AbiertoCerrado)" class="px-1.5 py-0.5 rounded-full cursor-pointer" @click="toggleEstado(cuenta)">
                                        {{ getEstadoTexto(cuenta.AbiertoCerrado) }}
                                    </span>
                                    <span v-if="cuenta.ActivoFijo == 1" class="text-emerald-600">
                                        <i class="fas fa-check-circle"></i> Activo Fijo
                                    </span>
                                </div>
                                <div class="flex justify-end gap-1.5 mt-1.5 pt-1.5 border-t border-gray-200">
                                    <button @click="editarCuenta(cuenta)" class="px-2 py-0.5 text-[9px] rounded bg-primary-50 text-primary-700 hover:bg-primary-100 transition">
                                        <i class="fas fa-edit text-[8px]"></i> Editar
                                    </button>
                                    <button @click="confirmarEliminar(cuenta)" class="px-2 py-0.5 text-[9px] rounded bg-red-50 text-red-600 hover:bg-red-100 transition">
                                        <i class="fas fa-trash text-[8px]"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                            <div v-if="!cuentasFiltradas.length" class="text-center text-gray-400 py-8">
                                <i class="fas fa-search text-2xl mb-1 block"></i>
                                <span class="text-xs">No se encontraron cuentas</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Cuenta</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Descripción</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-24">Tipo</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-20">Moneda</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-24">Estado</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-20">Act. Fijo</th>
                                    <th class="px-3 py-1.5 text-right text-[9px] font-medium text-gray-500 uppercase w-20">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="cuenta in cuentasFiltradas" :key="cuenta.IdCuenta" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2">
                                        <span class="font-mono font-bold text-xs text-primary-700">{{ cuenta.Cuenta }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-700 truncate max-w-[250px]" :title="cuenta.Descripcion">{{ cuenta.Descripcion }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="getTipoClase(cuenta.TipoDeCuenta)">
                                            {{ getTipoTexto(cuenta.TipoDeCuenta) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center text-xs text-gray-500">
                                        {{ cuenta.moneda?.Abreviacion || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[9px] rounded-full cursor-pointer" 
                                              :class="getEstadoClase(cuenta.AbiertoCerrado)"
                                              @click="toggleEstado(cuenta)"
                                              :title="'Clic para ' + (cuenta.AbiertoCerrado == 0 ? 'cerrar' : 'abrir')">
                                            {{ getEstadoTexto(cuenta.AbiertoCerrado) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <i v-if="cuenta.ActivoFijo == 1" class="fas fa-check-circle text-emerald-500 text-sm"></i>
                                        <i v-else class="fas fa-circle text-gray-300 text-[6px]"></i>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button 
                                                @click="editarCuenta(cuenta)"
                                                class="text-primary-600 hover:text-primary-800 transition p-1 rounded hover:bg-primary-50"
                                                title="Editar"
                                            >
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button 
                                                @click="confirmarEliminar(cuenta)"
                                                class="text-red-500 hover:text-red-700 transition p-1 rounded hover:bg-red-50"
                                                title="Eliminar"
                                            >
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!cuentasFiltradas.length">
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-search text-2xl mb-1 block"></i>
                                        No se encontraron cuentas
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== BOTÓN VOLVER ==================== -->
                <div class="flex justify-end pt-3 mt-3">
                    <button 
                        type="button"
                        @click="volver"
                        class="px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-100 transition text-xs font-medium flex items-center gap-1.5"
                    >
                        <i class="fas fa-arrow-left text-[10px]"></i> Volver al inicio
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL ELIMINAR ==================== -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-9 h-9 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500 text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-800">Confirmar eliminación</h3>
                        <p class="text-[10px] text-gray-500">Esta acción no se puede deshacer</p>
                    </div>
                </div>
                <p class="text-xs text-gray-700 mb-4">
                    ¿Estás seguro de eliminar la cuenta <strong>{{ cuentaEliminar?.Cuenta }}</strong>?
                </p>
                <div class="flex justify-end gap-2">
                    <button 
                        @click="showDeleteModal = false"
                        class="px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-100 transition text-xs"
                    >
                        Cancelar
                    </button>
                    <button 
                        @click="eliminarCuenta"
                        :disabled="loading"
                        class="px-3 py-1.5 bg-red-500 text-white rounded-md hover:bg-red-600 transition text-xs font-medium flex items-center gap-1.5 disabled:opacity-50"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <span>{{ loading ? 'Eliminando...' : 'Eliminar' }}</span>
                    </button>
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