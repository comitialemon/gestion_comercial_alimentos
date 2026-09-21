<script setup>
import { ref, computed, watch, inject, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import ConfigurarMinimosModal from '../PedidosClientes/ConfigurarMinimosModal.vue'

const toast = inject('toast')

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    contenedor: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['close', 'actualizar'])

// =============================================
// ESTADO
// =============================================
const loading = ref(false)
const guardando = ref(false)
const clientesAsignados = ref([])
const todosClientes = ref([])
const capacidadContenedor = ref(0)

const busquedaCliente = ref('')
const busquedaClienteParaAgregar = ref('')
const mostrarListaClientes = ref(false)
const clienteSeleccionadoParaAgregar = ref(null)

// ✅ MODAL DE MÍNIMOS
const modalMinimosVisible = ref(false)
const clienteParaMinimos = ref(null)

// REFS para detectar click fuera
const dropdownRef = ref(null)
const inputBusquedaRef = ref(null)

// =============================================
// HELPERS DE ORDENAMIENTO
// =============================================
const ordenarPorNombre = (arr) => {
    return [...arr].sort((a, b) => {
        return (a.Nombre || '').localeCompare(b.Nombre || '', 'es', {
            sensitivity: 'base',
            numeric: true
        })
    })
}

// =============================================
// COMPUTADOS
// =============================================
const clientesAsignadosOrdenados = computed(() => {
    return ordenarPorNombre(clientesAsignados.value)
})

const clientesFiltrados = computed(() => {
    if (!busquedaCliente.value.trim()) {
        return clientesAsignadosOrdenados.value
    }
    const termino = busquedaCliente.value.toLowerCase().trim()
    const filtrados = clientesAsignados.value.filter(c =>
        c.Nombre?.toLowerCase().includes(termino) ||
        c.CI_NIT?.toString().includes(termino)
    )
    return ordenarPorNombre(filtrados)
})

const clientesDisponibles = computed(() => {
    const idsAsignados = clientesAsignados.value.map(c => c.IdIdentificador)
    const disponibles = todosClientes.value.filter(c => !idsAsignados.includes(c.IdIdentificador))
    return ordenarPorNombre(disponibles)
})

const clientesDisponiblesFiltrados = computed(() => {
    if (!mostrarListaClientes.value) return []
    
    if (!busquedaClienteParaAgregar.value) {
        return clientesDisponibles.value.slice(0, 10)
    }
    const termino = busquedaClienteParaAgregar.value.toLowerCase().trim()
    const filtrados = clientesDisponibles.value.filter(c =>
        c.Nombre?.toLowerCase().includes(termino) ||
        c.CI_NIT?.toString().includes(termino)
    )
    return filtrados.slice(0, 20)
})

const puedeAgregar = computed(() => {
    return !!clienteSeleccionadoParaAgregar.value
})

// =============================================
// CONTROL DEL DROPDOWN
// =============================================
const abrirDropdown = () => {
    mostrarListaClientes.value = true
}

const cerrarDropdownConDelay = () => {
    setTimeout(() => {
        mostrarListaClientes.value = false
    }, 150)
}

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        mostrarListaClientes.value = false
    }
}

const handleEscape = (event) => {
    if (event.key === 'Escape') {
        mostrarListaClientes.value = false
    }
}

// =============================================
// MÉTODOS
// =============================================

const cargarDatos = async () => {
    if (!props.contenedor) return

    loading.value = true

    try {
        capacidadContenedor.value = parseFloat(props.contenedor.CapacidadTotal || 0)

        const responseAsignados = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/contenedores/${props.contenedor.IdContenedor}/clientes-asignados`
        )
        if (responseAsignados.data.success) {
            clientesAsignados.value = ordenarPorNombre(responseAsignados.data.data)
        }

        const responseTodos = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/contenedores/clientes-disponibles`
        )
        if (responseTodos.data.success) {
            todosClientes.value = ordenarPorNombre(responseTodos.data.data)
        }

    } catch (error) {
        console.error('Error al cargar datos:', error)
        toast?.error('Error', 'No se pudieron cargar los clientes')
    } finally {
        loading.value = false
    }
}

const seleccionarClienteParaAgregar = (cliente) => {
    clienteSeleccionadoParaAgregar.value = cliente
    busquedaClienteParaAgregar.value = `${cliente.Nombre} (${cliente.CI_NIT})`
    mostrarListaClientes.value = false
}

const limpiarSeleccionCliente = () => {
    clienteSeleccionadoParaAgregar.value = null
    busquedaClienteParaAgregar.value = ''
    mostrarListaClientes.value = false
    
    nextTick(() => {
        inputBusquedaRef.value?.focus()
    })
}

const agregarCliente = async () => {
    if (!puedeAgregar.value) return

    guardando.value = true

    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/contenedores/${props.contenedor.IdContenedor}/clientes`,
            {
                IdIdentificador: clienteSeleccionadoParaAgregar.value.IdIdentificador
                // ✅ Ya NO se envía CantidadMinima
            }
        )

        if (response.data.success) {
            toast?.success('Éxito', 'Cliente agregado correctamente')
            await cargarDatos()
            emit('actualizar', { type: 'agregar' })
            
            // ✅ GUARDAR datos del cliente para el modal de mínimos
            const clienteGuardado = { ...clienteSeleccionadoParaAgregar.value }
            
            // ✅ Limpiar formulario
            clienteSeleccionadoParaAgregar.value = null
            busquedaClienteParaAgregar.value = ''
            mostrarListaClientes.value = false

            // ✅ ABRIR MODAL DE CONFIGURAR MÍNIMOS AUTOMÁTICAMENTE
            clienteParaMinimos.value = clienteGuardado
            modalMinimosVisible.value = true
        } else {
            toast?.error('Error', response.data.message || 'Error al agregar cliente')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al agregar cliente')
    } finally {
        guardando.value = false
    }
}

const abrirModalMinimosCliente = (cliente) => {
    clienteParaMinimos.value = cliente
    modalMinimosVisible.value = true
}

const cerrarModalMinimos = () => {
    modalMinimosVisible.value = false
    clienteParaMinimos.value = null
}

const onMinimosGuardados = () => {
    toast?.success('Éxito', 'Mínimos configurados correctamente')
    cargarDatos()
}

const eliminarCliente = async (cliente) => {
    if (!confirm(`¿Eliminar a "${cliente.Nombre}" de este contenedor?`)) {
        return
    }

    const id = cliente.IdContenedorCliente

    try {
        const response = await axios.delete(
            `/operacion/pedidos/clientes-mayoristas/contenedores/clientes/${id}`
        )

        if (response.data.success) {
            toast?.success('Éxito', 'Cliente eliminado correctamente')
            await cargarDatos()
            emit('actualizar', { type: 'eliminar' })
        } else {
            toast?.error('Error', response.data.message || 'Error al eliminar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al eliminar')
    }
}

const cerrar = () => {
    emit('close')
}

const formatearNumero = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(0)
}

// =============================================
// WATCHERS
// =============================================
watch(() => props.visible, (newVal) => {
    if (newVal && props.contenedor) {
        cargarDatos()
        mostrarListaClientes.value = false
        clienteSeleccionadoParaAgregar.value = null
        busquedaClienteParaAgregar.value = ''
    }
})

watch(busquedaClienteParaAgregar, (newVal) => {
    if (newVal && !clienteSeleccionadoParaAgregar.value) {
        mostrarListaClientes.value = true
    }
})

watch(clienteSeleccionadoParaAgregar, (newVal, oldVal) => {
    if (oldVal && !newVal) {
        busquedaClienteParaAgregar.value = ''
    }
})

// =============================================
// LIFECYCLE
// =============================================
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <div 
        v-if="visible"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3 sm:p-4"
        @click.self="cerrar"
    >
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[95vh] overflow-hidden shadow-xl animate-fade-in-up">
            
            <!-- HEADER -->
            <div class="px-4 sm:px-5 py-2.5 sm:py-3 border-b bg-primary-50 flex items-center justify-between flex-shrink-0">
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-gray-800 text-sm sm:text-base truncate">
                        <i class="fas fa-users text-primary-500 mr-2"></i>
                        Asignar Clientes
                    </h3>
                    <p class="text-[10px] text-gray-500 truncate">
                        {{ contenedor?.Codigo || 'Contenedor' }} 
                        <span class="mx-1">•</span>
                        Cap: {{ formatearNumero(capacidadContenedor) }} und
                        <span class="mx-1">•</span>
                        <span class="text-primary-600 font-medium">
                            {{ clientesAsignados.length }} cliente(s)
                        </span>
                    </p>
                </div>
                <button 
                    @click="cerrar"
                    class="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-1.5 transition flex-shrink-0"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- CUERPO -->
            <div class="p-3 sm:p-4 overflow-y-auto" style="max-height: calc(95vh - 160px);">
                
                <!-- Loading -->
                <div v-if="loading" class="flex flex-col items-center justify-center py-8">
                    <div class="w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
                    <p class="text-xs text-gray-400 mt-2">Cargando clientes...</p>
                </div>

                <!-- AGREGAR NUEVO CLIENTE -->
                <div v-if="!loading" class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-xs font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-plus text-primary-500 mr-1"></i>
                        Agregar nuevo cliente
                    </p>
                    
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- INPUT CON DROPDOWN -->
                        <div 
                            ref="dropdownRef"
                            class="relative flex-1 min-w-[200px]"
                        >
                            <div class="relative">
                                <input
                                    ref="inputBusquedaRef"
                                    type="text"
                                    v-model="busquedaClienteParaAgregar"
                                    @focus="abrirDropdown"
                                    @blur="cerrarDropdownConDelay"
                                    :placeholder="clienteSeleccionadoParaAgregar ? '' : 'Buscar cliente...'"
                                    class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none transition"
                                    :class="{
                                        'bg-green-50 border-green-300 pr-7': clienteSeleccionadoParaAgregar,
                                        'bg-white border-gray-300 pr-7': !clienteSeleccionadoParaAgregar && busquedaClienteParaAgregar,
                                        'bg-white border-gray-300': !clienteSeleccionadoParaAgregar && !busquedaClienteParaAgregar
                                    }"
                                    autocomplete="off"
                                />
                                
                                <button
                                    v-if="clienteSeleccionadoParaAgregar || busquedaClienteParaAgregar"
                                    @mousedown.prevent="limpiarSeleccionCliente"
                                    type="button"
                                    class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-0.5 rounded hover:bg-gray-100"
                                    title="Limpiar"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>

                            <!-- DROPDOWN -->
                            <div 
                                v-if="mostrarListaClientes && clientesDisponiblesFiltrados.length > 0"
                                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                @mousedown.prevent
                            >
                                <div class="px-2 py-1 bg-gray-50 border-b text-[9px] text-gray-500 flex items-center justify-between sticky top-0">
                                    <span>
                                        <i class="fas fa-users text-[8px] mr-1"></i>
                                        {{ clientesDisponiblesFiltrados.length }} disponible(s)
                                    </span>
                                    <span class="text-gray-400">
                                        Presione <kbd class="px-1 bg-white rounded border border-gray-300 text-[8px]">Esc</kbd>
                                    </span>
                                </div>

                                <div
                                    v-for="cliente in clientesDisponiblesFiltrados"
                                    :key="cliente.IdIdentificador"
                                    @mousedown.prevent="seleccionarClienteParaAgregar(cliente)"
                                    class="px-2 py-1.5 hover:bg-primary-50 cursor-pointer text-xs flex justify-between items-center border-b border-gray-100 last:border-0 transition"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-5 h-5 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-user text-primary-600 text-[8px]"></i>
                                        </div>
                                        <span class="font-medium text-gray-800 truncate">{{ cliente.Nombre }}</span>
                                    </div>
                                    <span class="text-[10px] text-gray-400 font-mono flex-shrink-0 ml-2">{{ cliente.CI_NIT }}</span>
                                </div>
                            </div>

                            <!-- Sin resultados -->
                            <div 
                                v-else-if="mostrarListaClientes && busquedaClienteParaAgregar && !clienteSeleccionadoParaAgregar && clientesDisponiblesFiltrados.length === 0"
                                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-3 text-center"
                            >
                                <i class="fas fa-search text-gray-300 text-base mb-1 block"></i>
                                <p class="text-[10px] text-gray-500">No hay clientes con "{{ busquedaClienteParaAgregar }}"</p>
                            </div>
                        </div>

                        <!-- BOTÓN AGREGAR -->
                        <button
                            @click="agregarCliente"
                            :disabled="!puedeAgregar || guardando"
                            class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-plus text-[10px]"></i>
                            {{ guardando ? 'Agregando...' : 'Agregar' }}
                        </button>
                    </div>

                    <p v-if="clientesDisponibles.length === 0 && !busquedaClienteParaAgregar" class="text-[10px] text-gray-400 mt-1.5">
                        <i class="fas fa-info-circle mr-1"></i>
                        Todos los clientes ya están asignados
                    </p>
                </div>

                <!-- LISTA DE CLIENTES ASIGNADOS -->
                <div v-if="!loading">
                    <div class="relative mb-3">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                        <input
                            type="text"
                            v-model="busquedaCliente"
                            placeholder="Buscar cliente asignado..."
                            class="w-full border border-gray-200 rounded-md pl-7 pr-7 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none transition bg-gray-50 focus:bg-white"
                        />
                        <button 
                            v-if="busquedaCliente"
                            @click="busquedaCliente = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </div>

                    <div v-if="clientesAsignados.length === 0" class="text-center py-6 text-gray-400">
                        <i class="fas fa-users-slash text-2xl block mb-2 text-gray-300"></i>
                        <p class="text-sm font-medium">No hay clientes asignados</p>
                        <p class="text-[10px] mt-0.5">Agrega un cliente usando el formulario superior</p>
                    </div>

                    <div v-else class="overflow-x-auto border rounded-md">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-2 py-1.5 text-left font-medium text-gray-500">CI/NIT</th>
                                    <th class="px-2 py-1.5 text-left font-medium text-gray-500">
                                        Cliente
                                        <i class="fas fa-sort-alpha-down text-[9px] text-primary-500 ml-1" title="Ordenado alfabéticamente"></i>
                                    </th>
                                    <th class="px-2 py-1.5 text-center font-medium text-gray-500">Mínimos</th>
                                    <th class="px-2 py-1.5 text-center font-medium text-gray-500">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr 
                                    v-for="cliente in clientesFiltrados" 
                                    :key="cliente.IdContenedorCliente || cliente.IdIdentificador"
                                    class="hover:bg-gray-50 transition"
                                >
                                    <td class="px-2 py-1.5 font-mono text-gray-600">
                                        {{ cliente.CI_NIT }}
                                    </td>
                                    <td class="px-2 py-1.5 text-gray-700">
                                        {{ cliente.Nombre }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        <button
                                            @click="abrirModalMinimosCliente(cliente)"
                                            class="text-[10px] px-2 py-0.5 rounded-full font-medium transition inline-flex items-center gap-1"
                                            :class="cliente.TieneMinimosConfigurados 
                                                ? 'bg-green-100 text-green-700 hover:bg-green-200' 
                                                : 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200'"
                                            :title="cliente.TieneMinimosConfigurados ? 'Editar mínimos' : 'Configurar mínimos'"
                                        >
                                            <i :class="cliente.TieneMinimosConfigurados ? 'fas fa-cog' : 'fas fa-exclamation-triangle'"></i>
                                            {{ cliente.TieneMinimosConfigurados 
                                                ? (cliente.TotalGruposConfigurados + ' grupo(s)') 
                                                : 'Sin configurar' 
                                            }}
                                        </button>
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button
                                                @click="eliminarCliente(cliente)"
                                                class="text-red-500 hover:text-red-700 text-[10px]"
                                                title="Eliminar"
                                            >
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="clientesFiltrados.length === 0">
                                    <td colspan="4" class="px-2 py-3 text-center text-gray-400 text-[10px]">
                                        <i class="fas fa-search mr-1"></i>
                                        No se encontraron clientes
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="px-4 sm:px-5 py-2.5 border-t bg-gray-50 flex justify-end flex-shrink-0">
                <button 
                    @click="cerrar"
                    class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-xs transition"
                >
                    Cerrar
                </button>
            </div>

        </div>

        <!-- MODAL DE CONFIGURAR MÍNIMOS -->
        <ConfigurarMinimosModal
            :visible="modalMinimosVisible"
            :identificador="clienteParaMinimos"
            :contenedor="contenedor"
            @close="cerrarModalMinimos"
            @saved="onMinimosGuardados"
        />
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.2s ease-out;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] {
    -moz-appearance: textfield;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 8px;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d1d1;
    border-radius: 8px;
}
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.transition {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

kbd {
    font-family: monospace;
    font-size: 9px;
    line-height: 1;
}
</style>