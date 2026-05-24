<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: Array,
    operadores: Array,
    sucursalId: Number,
    operadorId: Number,
    esSupervisor: Boolean,
})

const form = ref({
    numero_diario: '',
    SucursalId: props.sucursalId || '',
    OperadorId: props.operadorId || '',
})

const diariosSugeridos = ref([])
const diarioSeleccionado = ref(null)
const buscando = ref(false)
const errorBusqueda = ref('')
const mostrarSugerencias = ref(false)
const operadoresFiltrados = ref(props.operadores || [])
const cargandoOperadores = ref(false)

// Cargar operadores al cambiar sucursal
const cargarOperadores = async (sucursalId) => {
    if (!sucursalId) {
        operadoresFiltrados.value = props.operadores || []
        return
    }
    
    cargandoOperadores.value = true
    try {
        const response = await axios.get(`/gestion/imprimir-diario/operadores/${sucursalId}`)
        if (response.data.success) {
            operadoresFiltrados.value = response.data.operadores
            form.value.OperadorId = ''
        }
    } catch (error) {
        console.error('Error cargando operadores:', error)
    } finally {
        cargandoOperadores.value = false
    }
}

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
                sucursal_id: form.value.SucursalId,
                operador_id: form.value.OperadorId,
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

// Seleccionar un diario de la lista
const seleccionarDiario = (diario) => {
    diarioSeleccionado.value = diario
    form.value.numero_diario = diario.numero.toString()
    mostrarSugerencias.value = false
    errorBusqueda.value = ''
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
const imprimirDiario = () => {
    if (!diarioSeleccionado.value) return
    window.open(`/gestion/imprimir-diario/pdf/${diarioSeleccionado.value.id}`, '_blank')
}

// Cerrar sugerencias al hacer clic fuera
const handleClickOutside = (event) => {
    const container = document.querySelector('.autocomplete-container')
    if (container && !container.contains(event.target)) {
        mostrarSugerencias.value = false
    }
}

// Debounce para búsqueda
let timeout
const onInput = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        buscarDiarios()
    }, 300)
}

// Watch para cambios en sucursal y operador
if (props.esSupervisor) {
    watch(() => form.value.SucursalId, (newVal) => {
        if (newVal) {
            cargarOperadores(newVal)
        } else {
            operadoresFiltrados.value = props.operadores || []
            form.value.OperadorId = ''
        }
        limpiarBusqueda()
    })
    
    watch(() => form.value.OperadorId, () => {
        limpiarBusqueda()
    })
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
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-print text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Imprimir Diario</h1>
                            <p class="text-xs text-gray-500">Busque por número de diario para imprimir</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario de búsqueda -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <!-- Filtros para supervisores -->
                    <div v-if="esSupervisor" class="space-y-4 mb-6 pb-4 border-b">
                        <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <i class="fas fa-filter text-guindo-600"></i> Filtros
                        </h3>
                        
                        <!-- Sucursal -->
                        <div v-if="sucursales && sucursales.length > 0">
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Sucursal
                            </label>
                            <select v-model="form.SucursalId" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todas las sucursales</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                        </div>

                        <!-- Operador -->
                        <div v-if="operadoresFiltrados.length > 0">
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Operador
                            </label>
                            <select v-model="form.OperadorId" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="cargandoOperadores">
                                <option value="">Todos los operadores</option>
                                <option v-for="op in operadoresFiltrados" :key="op.id" :value="op.id">{{ op.nombre }}</option>
                            </select>
                            <p v-if="cargandoOperadores" class="text-xs text-gray-400 mt-1">Cargando operadores...</p>
                        </div>
                    </div>

                    <!-- Buscador de diario (autocomplete) -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-hashtag mr-1 text-guindo-600"></i> Número de Diario *
                            </label>
                            <div class="relative autocomplete-container">
                                <input 
                                    type="text" 
                                    v-model="form.numero_diario"
                                    @input="onInput"
                                    @focus="form.numero_diario && diariosSugeridos.length > 0 ? mostrarSugerencias = true : null"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-24"
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
                                        class="text-gray-400 hover:text-gray-600"
                                        type="button"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <!-- Lista de sugerencias -->
                                <div v-if="mostrarSugerencias && diariosSugeridos.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="diario in diariosSugeridos" 
                                        :key="diario.id"
                                        @click="seleccionarDiario(diario)"
                                        class="px-3 py-2 hover:bg-guindo-50 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                    >
                                        <div>
                                            <span class="font-mono font-bold text-guindo-700">N° {{ diario.numero }}</span>
                                            <span class="text-xs text-gray-500 ml-2">{{ diario.tipo }}</span>
                                        </div>
                                        <div class="text-xs text-gray-400">{{ diario.fecha }}</div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Ingrese el número de diario y seleccione de la lista</p>
                        </div>

                        <!-- Resultado seleccionado -->
                        <div v-if="diarioSeleccionado" class="p-3 bg-green-50 rounded-lg">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div>
                                    <p class="text-sm text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Diario seleccionado
                                    </p>
                                    <div class="mt-1 text-xs text-gray-600">
                                        <span class="font-medium">N°:</span> {{ diarioSeleccionado.numero }} &nbsp;|&nbsp;
                                        <span class="font-medium">Tipo:</span> {{ diarioSeleccionado.tipo }} &nbsp;|&nbsp;
                                        <span class="font-medium">Fecha:</span> {{ diarioSeleccionado.fecha }}
                                    </div>
                                </div>
                                <button 
                                    @click="imprimirDiario" 
                                    class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition text-sm flex items-center gap-2"
                                >
                                    <i class="fas fa-print"></i> Imprimir
                                </button>
                            </div>
                        </div>

                        <!-- Mensaje de error -->
                        <div v-if="errorBusqueda" class="p-3 bg-red-50 rounded-lg">
                            <p class="text-sm text-red-600 flex items-center gap-2">
                                <i class="fas fa-exclamation-triangle"></i> {{ errorBusqueda }}
                            </p>
                        </div>

                        <!-- Mensaje de no resultados -->
                        <div v-if="!buscando && form.numero_diario && diariosSugeridos.length === 0 && !diarioSeleccionado" class="p-3 bg-yellow-50 rounded-lg">
                            <p class="text-sm text-yellow-700 flex items-center gap-2">
                                <i class="fas fa-info-circle"></i> No se encontraron diarios con el número "{{ form.numero_diario }}"
                            </p>
                        </div>
                    </div>

                    <!-- Botones de navegación -->
                    <div class="flex justify-end pt-5 mt-3 border-t">
                        <button 
                            type="button"
                            @click="volver"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition"
                        >
                            Volver al inicio
                        </button>
                    </div>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Escriba el número de diario que desea imprimir. Aparecerá una lista con los diarios que coinciden con el número ingresado.
                    <span v-if="!esSupervisor" class="block mt-1">🔒 Solo puedes imprimir diarios que hayas creado tú.</span>
                    <span v-else class="block mt-1">👑 Como supervisor, puedes buscar diarios de todas las sucursales y operadores.</span>
                </div>
            </div>
        </div>
    </div>
</template>