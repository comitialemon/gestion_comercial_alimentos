<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    metodosPago: {
        type: Array,
        required: true,
        default: () => []
    },
    cuentasContables: {
        type: Array,
        required: true,
        default: () => []
    },
    mapeosPorMetodo: {
        type: Object,
        required: true,
        default: () => ({})
    },
    clienteId: {
        type: Number,
        required: true
    },
    sucursalId: {
        type: Number,
        required: true
    }
})

// Buscadores
const busquedaMetodo = ref('')
const busquedaCuenta = ref('')

// Métodos de pago filtrados para el buscador
const metodosFiltrados = computed(() => {
    if (!busquedaMetodo.value) return props.metodosPago
    const termino = busquedaMetodo.value.toLowerCase()
    return props.metodosPago.filter(m => 
        m.codigo.toString().includes(termino) ||
        m.descripcion.toLowerCase().includes(termino)
    )
})

// Cuentas contables filtradas para el buscador
const cuentasFiltradas = computed(() => {
    if (!busquedaCuenta.value) return props.cuentasContables
    const termino = busquedaCuenta.value.toLowerCase()
    return props.cuentasContables.filter(c => 
        c.nombre.toLowerCase().includes(termino) ||
        (c.descripcion && c.descripcion.toLowerCase().includes(termino))
    )
})

// Mapeos existentes para mostrar en la tabla
const mapeosExistentes = computed(() => {
    const lista = []
    for (const [codigoSiat, cuentasIds] of Object.entries(props.mapeosPorMetodo)) {
        const metodo = props.metodosPago.find(m => m.codigo == codigoSiat)
        if (metodo && cuentasIds.length > 0) {
            const cuentas = props.cuentasContables.filter(c => cuentasIds.includes(c.id))
            lista.push({
                codigo: metodo.codigo,
                descripcion: metodo.descripcion,
                cuentas: cuentas
            })
        }
    }
    return lista.sort((a, b) => (a.codigo || 0) - (b.codigo || 0))
})

const modalAbierto = ref(false)
const editando = ref(false)
const metodoSeleccionado = ref(null)
const cuentasSeleccionadas = ref([])
const guardando = ref(false)
const mensaje = ref('')
const tipoMensaje = ref('')

const metodosDisponibles = computed(() => props.metodosPago)

const abrirNuevo = () => {
    editando.value = false
    metodoSeleccionado.value = null
    cuentasSeleccionadas.value = []
    busquedaMetodo.value = ''
    busquedaCuenta.value = ''
    modalAbierto.value = true
}

const editarMapeo = (metodo) => {
    editando.value = true
    metodoSeleccionado.value = metodo
    cuentasSeleccionadas.value = metodo.cuentas.map(c => c.id)
    busquedaMetodo.value = ''
    busquedaCuenta.value = ''
    modalAbierto.value = true
}

const eliminarMapeo = async (metodo) => {
    if (!confirm(`¿Eliminar el mapeo para "${metodo.descripcion}"?`)) return
    guardando.value = true
    try {
        for (const cuenta of metodo.cuentas) {
            await axios.post('/facturacion/metodos-pago/mapeo', {
                codigo_siat: metodo.codigo,
                idContaCuenta: cuenta.id,
                idCliente: props.clienteId,
                idSucursal: props.sucursalId,
                activo: false
            })
        }
        mostrarMensaje('Mapeo eliminado correctamente', 'success')
        setTimeout(() => window.location.reload(), 1000)
    } catch (error) {
        console.error('Error eliminar:', error.response?.data || error)
        mostrarMensaje('Error al eliminar: ' + (error.response?.data?.error || error.message), 'error')
    } finally {
        guardando.value = false
    }
}

const guardarMapeo = async () => {
    if (!metodoSeleccionado.value) {
        mostrarMensaje('Selecciona un método de pago', 'error')
        return
    }
    if (cuentasSeleccionadas.value.length === 0) {
        mostrarMensaje('Selecciona al menos una cuenta contable', 'error')
        return
    }
    guardando.value = true
    try {
        if (editando.value) {
            const metodoOriginal = mapeosExistentes.value.find(m => m.codigo === metodoSeleccionado.value.codigo)
            if (metodoOriginal) {
                for (const cuenta of metodoOriginal.cuentas) {
                    await axios.post('/facturacion/metodos-pago/mapeo', {
                        codigo_siat: metodoSeleccionado.value.codigo,
                        idContaCuenta: cuenta.id,
                        idCliente: props.clienteId,
                        idSucursal: props.sucursalId,
                        activo: false
                    })
                }
            }
        }
        for (const cuentaId of cuentasSeleccionadas.value) {
            await axios.post('/facturacion/metodos-pago/mapeo', {
                codigo_siat: metodoSeleccionado.value.codigo,
                idContaCuenta: cuentaId,
                idCliente: props.clienteId,
                idSucursal: props.sucursalId,
                activo: true
            })
        }
        mostrarMensaje(editando.value ? 'Mapeo actualizado' : 'Mapeo guardado', 'success')
        cerrarModal()
        setTimeout(() => window.location.reload(), 1000)
    } catch (error) {
        console.error('Error guardar:', error.response?.data || error)
        const errorMsg = error.response?.data?.error || error.response?.data?.message || error.message
        mostrarMensaje('Error al guardar: ' + errorMsg, 'error')
    } finally {
        guardando.value = false
    }
}

const cerrarModal = () => {
    modalAbierto.value = false
    metodoSeleccionado.value = null
    cuentasSeleccionadas.value = []
    busquedaMetodo.value = ''
    busquedaCuenta.value = ''
}

const mostrarMensaje = (texto, tipo) => {
    mensaje.value = texto
    tipoMensaje.value = tipo
    setTimeout(() => { mensaje.value = '' }, 3000)
}

const toggleCuenta = (cuentaId) => {
    if (cuentasSeleccionadas.value.includes(cuentaId)) {
        cuentasSeleccionadas.value = cuentasSeleccionadas.value.filter(id => id !== cuentaId)
    } else {
        cuentasSeleccionadas.value.push(cuentaId)
    }
}

const formatearDescripcion = (descripcion) => {
    if (!descripcion) return ''
    if (descripcion.length > 80) {
        return descripcion.substring(0, 80) + '...'
    }
    return descripcion
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-2xl mb-4">
                        <i class="fas fa-credit-card text-2xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">Configuración de Métodos de Pago</h1>
                    <p class="mt-2 text-sm text-gray-500">Administra qué cuentas contables corresponden a cada método de pago</p>
                </div>

                <!-- Botón Nuevo -->
                <div class="mb-4 flex justify-end">
                    <button @click="abrirNuevo" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow-sm">
                        <i class="fas fa-plus text-sm"></i>
                        <span>Nuevo Mapeo</span>
                    </button>
                </div>

                <!-- Mensaje flotante -->
                <div v-if="mensaje" class="fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg" :class="{
                    'bg-green-100 text-green-800 border border-green-300': tipoMensaje === 'success',
                    'bg-red-100 text-red-800 border border-red-300': tipoMensaje === 'error'
                }">
                    {{ mensaje }}
                </div>

                <!-- Tabla de mapeos -->
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Método de Pago</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cuentas Contables</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="metodo in mapeosExistentes" :key="metodo.codigo" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-mono bg-gray-100 rounded-full">{{ metodo.codigo }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ metodo.descripcion }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="cuenta in metodo.cuentas" :key="cuenta.id" class="inline-flex flex-col px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded-full" :title="cuenta.descripcion">
                                                {{ cuenta.nombre }}
                                                <span v-if="cuenta.descripcion" class="text-[10px] text-emerald-600">{{ cuenta.descripcion.substring(0, 30) }}...</span>
                                            </span>
                                        </div>
                                        <div v-if="metodo.cuentas.length === 0" class="text-sm text-gray-400 italic">
                                            Sin cuentas asignadas
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <button @click="editarMapeo(metodo)" class="text-blue-600 hover:text-blue-800 mr-3" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="eliminarMapeo(metodo)" class="text-red-600 hover:text-red-800" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="mapeosExistentes.length === 0">
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                        No hay mapeos configurados. Haz clic en "Nuevo Mapeo" para crear uno.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal con buscadores -->
                <div v-if="modalAbierto" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="cerrarModal"></div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                            <i class="fas fa-credit-card mr-2 text-indigo-600"></i>
                                            {{ editando ? 'Editar Mapeo' : 'Nuevo Mapeo' }}
                                        </h3>
                                        
                                        <!-- Método de Pago con buscador -->
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago (Facturación)</label>
                                            <div v-if="!editando" class="relative">
                                                <input 
                                                    type="text"
                                                    v-model="busquedaMetodo"
                                                    placeholder="Buscar por código o descripción..."
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 pl-8 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                />
                                                <i class="fas fa-search absolute left-2 top-3 text-gray-400 text-xs"></i>
                                                <div class="mt-1 max-h-40 overflow-y-auto border border-gray-200 rounded-lg">
                                                    <div 
                                                        v-for="m in metodosFiltrados" 
                                                        :key="m.codigo"
                                                        @click="metodoSeleccionado = m"
                                                        :class="[
                                                            'p-2 cursor-pointer hover:bg-indigo-50 transition-colors',
                                                            metodoSeleccionado?.codigo === m.codigo ? 'bg-indigo-100 border-l-4 border-indigo-500' : ''
                                                        ]"
                                                    >
                                                        <div class="flex items-center justify-between">
                                                            <span class="font-mono text-sm font-medium">{{ m.codigo }}</span>
                                                            <span v-if="metodoSeleccionado?.codigo === m.codigo" class="text-indigo-600 text-xs">
                                                                <i class="fas fa-check-circle"></i> Seleccionado
                                                            </span>
                                                        </div>
                                                        <div class="text-xs text-gray-600">{{ m.descripcion }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <select v-else :disabled="editando" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100 text-gray-500">
                                                <option>{{ metodoSeleccionado?.codigo }} - {{ metodoSeleccionado?.descripcion }}</option>
                                            </select>
                                            <p v-if="editando" class="text-xs text-gray-400 mt-1">No se puede cambiar el método en edición</p>
                                            <p v-if="!editando && metodoSeleccionado" class="text-xs text-green-600 mt-1">
                                                <i class="fas fa-check-circle"></i> Método seleccionado: {{ metodoSeleccionado.codigo }} - {{ formatearDescripcion(metodoSeleccionado.descripcion) }}
                                            </p>
                                        </div>
                                        
                                        <!-- Cuentas Contables con buscador -->
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Cuentas Contables (Gestión)</label>
                                            <div class="relative mb-2">
                                                <input 
                                                    type="text"
                                                    v-model="busquedaCuenta"
                                                    placeholder="Buscar cuenta por nombre o descripción..."
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 pl-8 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                />
                                                <i class="fas fa-search absolute left-2 top-3 text-gray-400 text-xs"></i>
                                            </div>
                                            <div class="space-y-2 max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3">
                                                <label 
                                                    v-for="cuenta in cuentasFiltradas" 
                                                    :key="cuenta.id" 
                                                    class="flex items-start gap-2 p-2 rounded hover:bg-gray-50 cursor-pointer"
                                                    :class="{ 'bg-indigo-50': cuentasSeleccionadas.includes(cuenta.id) }"
                                                >
                                                    <input 
                                                        type="checkbox" 
                                                        :checked="cuentasSeleccionadas.includes(cuenta.id)" 
                                                        @change="toggleCuenta(cuenta.id)" 
                                                        class="w-4 h-4 mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    >
                                                    <div class="flex-1">
                                                        <div class="text-sm font-medium text-gray-700">{{ cuenta.nombre }}</div>
                                                        <div v-if="cuenta.descripcion" class="text-xs text-gray-500">{{ cuenta.descripcion }}</div>
                                                    </div>
                                                </label>
                                                <div v-if="cuentasFiltradas.length === 0" class="text-center text-gray-400 py-4">
                                                    No hay cuentas que coincidan con la búsqueda
                                                </div>
                                            </div>
                                            <div class="mt-2 text-xs text-gray-500">
                                                <i class="fas fa-info-circle"></i> Seleccionadas: {{ cuentasSeleccionadas.length }} cuentas
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                                <button @click="guardarMapeo" :disabled="guardando" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                                    <i v-if="guardando" class="fas fa-spinner fa-spin mr-2"></i>
                                    {{ guardando ? 'Guardando...' : 'Guardar' }}
                                </button>
                                <button @click="cerrarModal" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de ayuda -->
                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                    <div class="flex gap-3">
                        <i class="fas fa-info-circle text-blue-500"></i>
                        <div class="text-sm text-blue-800">
                            <p class="font-medium">¿Cómo funciona?</p>
                            <p class="text-xs mt-1">1. Cada método de pago de Facturación puede tener una o varias cuentas contables de Gestión.</p>
                            <p class="text-xs">2. Ejemplo: Código 108816 "EFECTIVO - TARJETA - VALE" se asocia con Efectivo, Tarjeta y Vales.</p>
                            <p class="text-xs">3. Esto permite que al vender, el sistema sepa qué código de facturación usar según las cuentas seleccionadas.</p>
                            <p class="text-xs">4. Usa los buscadores para encontrar rápidamente el método o cuenta que necesitas.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>