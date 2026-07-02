<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { debounce } from 'lodash'

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

// ==================== COMPUTADOS ====================
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
    // Validaciones básicas
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

// ==================== WATCH ====================
onMounted(() => {
    if (props.cuentaEditar) {
        editarCuenta(props.cuentaEditar)
    }
})

// ==================== ESTILOS ====================
const getTipoClase = (tipo) => {
    return tipo === 'B' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'
}

const getTipoTexto = (tipo) => {
    return tipo === 'B' ? 'Balance' : 'Resultado'
}

const getEstadoClase = (estado) => {
    return estado == 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'
}

const getEstadoTexto = (estado) => {
    return estado == 0 ? 'Abierta' : 'Cerrada'
}
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
                                <h1 class="text-lg sm:text-xl font-bold text-gray-800">Administrar Plan de Cuentas</h1>
                                <p class="text-xs text-gray-500 hidden sm:block">Crear, editar y gestionar cuentas contables</p>
                            </div>
                        </div>
                        <button 
                            @click="abrirNuevo"
                            class="px-3 py-1.5 text-xs rounded-lg transition sm:w-auto w-full flex items-center justify-center gap-1"
                            :style="{ backgroundColor: `var(--color-primary-600)`, color: 'white' }"
                        >
                            <i class="fas fa-plus text-xs"></i>
                            <span>Nueva Cuenta</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Crear, editar y gestionar cuentas contables</p>
                </div>

                <!-- ============================================ -->
                <!-- FORMULARIO - TODOS LOS CAMPOS EN UNA SOLA FILA -->
                <!-- ============================================ -->
                <div v-if="mostrarFormulario" class="formulario-container bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4 sm:mb-6">
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="text-sm font-bold text-gray-800">
                            <i class="fas" :class="esEdicion ? 'fa-edit' : 'fa-plus-circle'" :style="{ color: `var(--color-primary-600)` }"></i>
                            {{ tituloFormulario }}
                        </h2>
                        <button @click="cancelarEdicion" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- 🔥 TODOS LOS CAMPOS EN UNA SOLA FILA (flex wrap) -->
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Cuenta -->
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-[10px] font-medium text-gray-700 mb-0.5">
                                Cuenta <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.Cuenta"
                                type="text"
                                class="w-full border rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                                placeholder="Ej: 1-01-01"
                            />
                        </div>

                        <!-- Descripción -->
                        <div class="flex-[2] min-w-[150px]">
                            <label class="block text-[10px] font-medium text-gray-700 mb-0.5">
                                Descripción <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="form.Descripcion"
                                type="text"
                                class="w-full border rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                                placeholder="Descripción"
                            />
                        </div>

                        <!-- Tipo de Cuenta -->
                        <div class="min-w-[100px]">
                            <label class="block text-[10px] font-medium text-gray-700 mb-0.5">
                                Tipo <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.TipoDeCuenta"
                                class="w-full border rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                            >
                                <option value="B">Balance</option>
                                <option value="P">Resultado</option>
                            </select>
                        </div>

                        <!-- Moneda -->
                        <div class="min-w-[120px]">
                            <label class="block text-[10px] font-medium text-gray-700 mb-0.5">
                                Moneda <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.IdMoneda"
                                class="w-full border rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                            >
                                <option value="">Seleccione</option>
                                <option v-for="moneda in monedas" :key="moneda.IdMoneda" :value="moneda.IdMoneda">
                                    {{ moneda.Abreviacion }}
                                </option>
                            </select>
                        </div>

                        <!-- Checkboxes en línea -->
                        <div class="flex items-center gap-3 min-w-[140px]">
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input 
                                    v-model="form.ActivoFijo"
                                    type="checkbox"
                                    class="w-3.5 h-3.5 rounded border-gray-300"
                                    :style="{ accentColor: `var(--color-primary-600)` }"
                                />
                                <span class="text-[10px] text-gray-700">Activo Fijo</span>
                            </label>
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input 
                                    v-model="form.AbiertoCerrado"
                                    type="checkbox"
                                    class="w-3.5 h-3.5 rounded border-gray-300"
                                    :style="{ accentColor: `var(--color-primary-600)` }"
                                />
                                <span class="text-[10px] text-gray-700">Cerrada</span>
                            </label>
                        </div>

                        <!-- Botones de acción -->
                        <div class="flex gap-1">
                            <button 
                                @click="cancelarEdicion"
                                class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-[10px]"
                            >
                                Cancelar
                            </button>
                            <button 
                                @click="guardarCuenta"
                                :disabled="loading"
                                class="px-3 py-1.5 text-white rounded-lg transition text-[10px] flex items-center gap-1 disabled:opacity-50"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas" :class="esEdicion ? 'fa-save' : 'fa-plus'"></i>
                                {{ loading ? '...' : (esEdicion ? 'Actualizar' : 'Crear') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- FILTROS -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input 
                                    type="text" 
                                    v-model="busqueda" 
                                    placeholder="Buscar por número o descripción..."
                                    class="w-full pl-9 pr-8 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:outline-none"
                                    :style="{ '--tw-ring-color': `var(--color-primary-500)` }"
                                />
                                <button 
                                    v-if="busqueda" 
                                    @click="busqueda = ''"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <div class="w-full sm:w-40">
                            <select v-model="tipoCuenta" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="todos">Todos los tipos</option>
                                <option value="B">Balance (B)</option>
                                <option value="P">Resultado (P)</option>
                            </select>
                        </div>

                        <div class="w-full sm:w-40">
                            <select v-model="estadoCuenta" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="todos">Todos los estados</option>
                                <option value="abiertas">Abiertas</option>
                                <option value="cerradas">Cerradas</option>
                            </select>
                        </div>

                        <button 
                            @click="limpiarFiltros"
                            class="px-4 py-2 text-sm rounded-lg transition bg-gray-100 text-gray-600 hover:bg-gray-200"
                        >
                            <i class="fas fa-eraser mr-1"></i> Limpiar
                        </button>
                    </div>

                    <div class="mt-3 text-xs text-gray-500">
                        Mostrando {{ cuentasFiltradas.length }} de {{ cuentas?.length || 0 }} cuentas
                    </div>
                </div>

                <!-- TABLA DE CUENTAS -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Desktop -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cuenta</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Moneda</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Activo Fijo</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="cuenta in cuentasFiltradas" :key="cuenta.IdCuenta" class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <span class="font-mono font-bold text-sm" :style="{ color: `var(--color-primary-700)` }">
                                            {{ cuenta.Cuenta }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ cuenta.Descripcion }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="getTipoClase(cuenta.TipoDeCuenta)">
                                            {{ getTipoTexto(cuenta.TipoDeCuenta) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-500">
                                        {{ cuenta.moneda?.Abreviacion || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full cursor-pointer" 
                                              :class="getEstadoClase(cuenta.AbiertoCerrado)"
                                              @click="toggleEstado(cuenta)"
                                              :title="'Haz clic para ' + (cuenta.AbiertoCerrado == 0 ? 'cerrar' : 'abrir')">
                                            {{ getEstadoTexto(cuenta.AbiertoCerrado) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <i v-if="cuenta.ActivoFijo == 1" class="fas fa-check-circle text-emerald-500"></i>
                                        <i v-else class="fas fa-circle text-gray-300 text-xs"></i>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button 
                                                @click="editarCuenta(cuenta)"
                                                class="p-1.5 rounded-lg transition hover:bg-primary-50"
                                                :style="{ color: `var(--color-primary-600)` }"
                                                title="Editar"
                                            >
                                                <i class="fas fa-edit text-sm"></i>
                                            </button>
                                            <button 
                                                @click="confirmarEliminar(cuenta)"
                                                class="p-1.5 rounded-lg transition hover:bg-red-50 text-red-500"
                                                title="Eliminar"
                                            >
                                                <i class="fas fa-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!cuentasFiltradas.length">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                        <i class="fas fa-search text-3xl mb-2 block"></i>
                                        No se encontraron cuentas
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile -->
                    <div class="md:hidden divide-y divide-gray-100">
                        <div v-for="cuenta in cuentasFiltradas" :key="cuenta.IdCuenta" class="p-4 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-mono font-bold text-base" :style="{ color: `var(--color-primary-700)` }">
                                    {{ cuenta.Cuenta }}
                                </span>
                                <span class="px-2 py-0.5 text-xs rounded-full" :class="getTipoClase(cuenta.TipoDeCuenta)">
                                    {{ getTipoTexto(cuenta.TipoDeCuenta) }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-700 mb-2">{{ cuenta.Descripcion }}</div>
                            <div class="flex flex-wrap justify-between items-center text-xs gap-1">
                                <div class="flex gap-3">
                                    <span class="text-gray-500">Moneda:</span>
                                    <span>{{ cuenta.moneda?.Abreviacion || '-' }}</span>
                                </div>
                                <div class="flex gap-3">
                                    <span class="text-gray-500">Estado:</span>
                                    <span :class="getEstadoClase(cuenta.AbiertoCerrado)" class="px-2 py-0.5 rounded-full cursor-pointer" @click="toggleEstado(cuenta)">
                                        {{ getEstadoTexto(cuenta.AbiertoCerrado) }}
                                    </span>
                                </div>
                                <div v-if="cuenta.ActivoFijo == 1" class="text-emerald-500">
                                    <i class="fas fa-check-circle"></i> Activo Fijo
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 mt-3 pt-2 border-t" :style="{ borderColor: `var(--color-primary-200)` }">
                                <button 
                                    @click="editarCuenta(cuenta)"
                                    class="text-xs px-3 py-1 rounded-lg transition"
                                    :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }"
                                >
                                    <i class="fas fa-edit mr-1"></i> Editar
                                </button>
                                <button 
                                    @click="confirmarEliminar(cuenta)"
                                    class="text-xs px-3 py-1 rounded-lg transition bg-red-50 text-red-500"
                                >
                                    <i class="fas fa-trash mr-1"></i> Eliminar
                                </button>
                            </div>
                        </div>
                        <div v-if="!cuentasFiltradas.length" class="p-8 text-center text-gray-400">
                            <i class="fas fa-search text-3xl mb-2 block"></i>
                            No se encontraron cuentas
                        </div>
                    </div>
                </div>

                <!-- Botón volver -->
                <div class="mt-6 flex justify-end">
                    <button 
                        @click="volver"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-sm"
                    >
                        Volver al inicio
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación para eliminar -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Confirmar eliminación</h3>
                        <p class="text-sm text-gray-500">Esta acción no se puede deshacer</p>
                    </div>
                </div>
                <p class="text-gray-700 mb-6">
                    ¿Estás seguro de eliminar la cuenta <strong>{{ cuentaEliminar?.Cuenta }}</strong>?
                </p>
                <div class="flex justify-end gap-3">
                    <button 
                        @click="showDeleteModal = false"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-sm"
                    >
                        Cancelar
                    </button>
                    <button 
                        @click="eliminarCuenta"
                        :disabled="loading"
                        class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition text-sm flex items-center gap-2 disabled:opacity-50"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        {{ loading ? 'Eliminando...' : 'Eliminar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:focus, select:focus {
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

.transition {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

input[type="checkbox"] {
    cursor: pointer;
}

input[type="checkbox"]:checked {
    accent-color: var(--color-primary-600);
}

* {
    scroll-behavior: smooth;
}

/* Estilo para el formulario en una sola fila */
.flex-wrap {
    flex-wrap: wrap;
}

.flex-wrap .flex-1,
.flex-wrap .flex-\[2\] {
    min-width: 0;
}

/* Responsive para el formulario */
@media (max-width: 768px) {
    .flex-wrap .min-w-\[120px\] {
        min-width: 100px;
    }
    .flex-wrap .min-w-\[150px\] {
        min-width: 120px;
    }
    .flex-wrap .min-w-\[100px\] {
        min-width: 80px;
    }
}
</style>