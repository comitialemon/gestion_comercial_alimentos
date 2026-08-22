<script setup>
import { ref, computed, watch, inject } from 'vue'
import axios from 'axios'

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
const nuevoClienteMinimo = ref('')
const editandoMinimo = ref({})

// =============================================
// COMPUTADOS
// =============================================

const clientesFiltrados = computed(() => {
    if (!busquedaCliente.value.trim()) {
        return clientesAsignados.value
    }
    const termino = busquedaCliente.value.toLowerCase().trim()
    return clientesAsignados.value.filter(c =>
        c.Nombre?.toLowerCase().includes(termino) ||
        c.CI_NIT?.toString().includes(termino)
    )
})

const clientesDisponibles = computed(() => {
    const idsAsignados = clientesAsignados.value.map(c => c.IdIdentificador)
    return todosClientes.value.filter(c => !idsAsignados.includes(c.IdIdentificador))
})

const clientesDisponiblesFiltrados = computed(() => {
    if (!busquedaClienteParaAgregar.value) {
        return clientesDisponibles.value.slice(0, 10)
    }
    const termino = busquedaClienteParaAgregar.value.toLowerCase().trim()
    return clientesDisponibles.value.filter(c =>
        c.Nombre?.toLowerCase().includes(termino) ||
        c.CI_NIT?.toString().includes(termino)
    )
})

const puedeAgregar = computed(() => {
    return clienteSeleccionadoParaAgregar.value &&
           nuevoClienteMinimo.value !== '' &&
           parseFloat(nuevoClienteMinimo.value) >= 0 &&
           parseFloat(nuevoClienteMinimo.value) <= capacidadContenedor.value
})

// ✅ COMPUTED PARA VALIDACIÓN DE MÍNIMO
const errorMinimo = computed(() => {
    if (!nuevoClienteMinimo.value) return ''
    const minimo = parseFloat(nuevoClienteMinimo.value)
    if (minimo < 0) return 'La cantidad mínima no puede ser negativa'
    if (minimo > capacidadContenedor.value) {
        return `La cantidad mínima (${minimo}) no puede ser mayor que la capacidad (${capacidadContenedor.value})`
    }
    return ''
})

// =============================================
// MÉTODOS
// =============================================

const cargarDatos = async () => {
    if (!props.contenedor) return

    loading.value = true

    try {
        // ✅ OBTENER CAPACIDAD DEL CONTENEDOR
        capacidadContenedor.value = parseFloat(props.contenedor.CapacidadTotal || 0)

        // ✅ OBTENER CLIENTES ASIGNADOS
        const responseAsignados = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/contenedores/${props.contenedor.IdContenedor}/clientes-asignados`
        )
        if (responseAsignados.data.success) {
            clientesAsignados.value = responseAsignados.data.data
        }

        // ✅ OBTENER CLIENTES DISPONIBLES
        const responseTodos = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/contenedores/clientes-disponibles`
        )
        if (responseTodos.data.success) {
            todosClientes.value = responseTodos.data.data
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
    // ✅ LIMPIAR ERROR AL SELECCIONAR
    errorMinimo.value = ''
}

const agregarCliente = async () => {
    if (!puedeAgregar.value) return

    // ✅ VALIDACIÓN EXTRA (por si acaso)
    const minimo = parseFloat(nuevoClienteMinimo.value)
    if (minimo > capacidadContenedor.value) {
        toast?.error('Error', `La cantidad mínima (${minimo}) no puede ser mayor que la capacidad del contenedor (${capacidadContenedor.value})`)
        return
    }

    guardando.value = true

    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/contenedores/${props.contenedor.IdContenedor}/clientes`,
            {
                IdIdentificador: clienteSeleccionadoParaAgregar.value.IdIdentificador,
                CantidadMinima: minimo
            }
        )

        if (response.data.success) {
            toast?.success('Éxito', 'Cliente agregado correctamente')
            await cargarDatos()
            emit('actualizar', { type: 'agregar' })
            
            clienteSeleccionadoParaAgregar.value = null
            nuevoClienteMinimo.value = ''
            busquedaClienteParaAgregar.value = ''
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

const actualizarMinimo = async (cliente) => {
    const id = cliente.IdContenedorCliente
    const minimo = cliente.cantidad_minima_temporal !== undefined 
        ? cliente.cantidad_minima_temporal 
        : cliente.CantidadMinima

    if (minimo === cliente.CantidadMinima) {
        return
    }

    // ✅ VALIDAR QUE NO SUPERE LA CAPACIDAD
    if (parseFloat(minimo) > capacidadContenedor.value) {
        toast?.error('Error', `La cantidad mínima (${minimo}) no puede ser mayor que la capacidad del contenedor (${capacidadContenedor.value})`)
        // Revertir al valor anterior
        cliente.cantidad_minima_temporal = cliente.CantidadMinima
        return
    }

    editandoMinimo.value[id] = true

    try {
        const response = await axios.put(
            `/operacion/pedidos/clientes-mayoristas/contenedores/clientes/${id}`,
            {
                CantidadMinima: parseFloat(minimo)
            }
        )

        if (response.data.success) {
            cliente.CantidadMinima = parseFloat(minimo)
            delete cliente.cantidad_minima_temporal
            toast?.success('Éxito', 'Cantidad mínima actualizada')
            emit('actualizar', { type: 'actualizar' })
        } else {
            toast?.error('Error', response.data.message || 'Error al actualizar')
            cliente.cantidad_minima_temporal = cliente.CantidadMinima
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al actualizar')
        cliente.cantidad_minima_temporal = cliente.CantidadMinima
    } finally {
        editandoMinimo.value[id] = false
    }
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

watch(() => props.visible, (newVal) => {
    if (newVal && props.contenedor) {
        cargarDatos()
    }
})

const formatearNumero = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(0)
}
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
                        <span class="mx-1">•</span>
                        <span class="text-orange-500 text-[9px]">
                            <i class="fas fa-info-circle"></i>
                            Mínimo ≤ {{ formatearNumero(capacidadContenedor) }} und
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
                        <div class="relative flex-1 min-w-[160px]">
                            <input
                                type="text"
                                v-model="busquedaClienteParaAgregar"
                                @focus="mostrarListaClientes = true"
                                @input="mostrarListaClientes = true"
                                placeholder="Buscar cliente..."
                                class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none transition bg-white"
                            />
                            <div 
                                v-if="mostrarListaClientes && clientesDisponiblesFiltrados.length > 0"
                                class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-36 overflow-y-auto"
                            >
                                <div
                                    v-for="cliente in clientesDisponiblesFiltrados"
                                    :key="cliente.IdIdentificador"
                                    @click="seleccionarClienteParaAgregar(cliente)"
                                    class="px-2 py-1.5 hover:bg-gray-100 cursor-pointer text-xs flex justify-between items-center border-b last:border-0"
                                >
                                    <span class="font-medium">{{ cliente.Nombre }}</span>
                                    <span class="text-[10px] text-gray-400 font-mono">{{ cliente.CI_NIT }}</span>
                                </div>
                            </div>
                            <div 
                                v-else-if="mostrarListaClientes && busquedaClienteParaAgregar && clientesDisponiblesFiltrados.length === 0"
                                class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg p-2 text-center text-gray-400 text-[10px]"
                            >
                                <i class="fas fa-search mr-1"></i>
                                No hay clientes disponibles
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <span class="text-[10px] text-gray-500">Mín:</span>
                            <input
                                type="number"
                                step="1"
                                min="0"
                                :max="capacidadContenedor"
                                v-model="nuevoClienteMinimo"
                                placeholder="0"
                                class="w-16 text-center border border-gray-300 rounded-md px-1 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none transition"
                                :class="{
                                    'border-red-400 bg-red-50': errorMinimo && parseFloat(nuevoClienteMinimo) > 0,
                                    'border-gray-300': !errorMinimo || parseFloat(nuevoClienteMinimo) === 0
                                }"
                            />
                            <span class="text-[10px] text-gray-400">und</span>
                        </div>

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

                    <!-- ✅ MENSAJE DE ERROR O ADVERTENCIA -->
                    <div v-if="errorMinimo" class="mt-1.5 text-[10px] text-red-500 flex items-center gap-1">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ errorMinimo }}
                    </div>
                    <div v-else-if="nuevoClienteMinimo && parseFloat(nuevoClienteMinimo) > 0" class="mt-1.5 text-[10px] text-green-600 flex items-center gap-1">
                        <i class="fas fa-check-circle"></i>
                        Cantidad mínima válida (máx: {{ formatearNumero(capacidadContenedor) }} und)
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
                            class="w-full border border-gray-200 rounded-md pl-7 pr-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none transition bg-gray-50 focus:bg-white"
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
                                    <th class="px-2 py-1.5 text-left font-medium text-gray-500">Cliente</th>
                                    <th class="px-2 py-1.5 text-center font-medium text-gray-500">Mínimo</th>
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
                                        <div class="flex items-center justify-center gap-1">
                                            <input
                                                type="number"
                                                step="1"
                                                min="0"
                                                :max="capacidadContenedor"
                                                :value="cliente.cantidad_minima_temporal !== undefined ? cliente.cantidad_minima_temporal : cliente.CantidadMinima"
                                                @input="(e) => { 
                                                    const val = parseFloat(e.target.value) || 0;
                                                    if (val > capacidadContenedor) {
                                                        toast?.error('Error', `La cantidad mínima (${val}) no puede ser mayor que la capacidad (${capacidadContenedor})`);
                                                        e.target.value = cliente.cantidad_minima_temporal !== undefined ? cliente.cantidad_minima_temporal : cliente.CantidadMinima;
                                                        return;
                                                    }
                                                    cliente.cantidad_minima_temporal = val;
                                                    if (cliente.cantidad_minima_temporal === cliente.CantidadMinima) {
                                                        delete cliente.cantidad_minima_temporal;
                                                    }
                                                }"
                                                @blur="actualizarMinimo(cliente)"
                                                class="w-16 text-center border rounded px-1 py-0.5 text-xs focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none transition"
                                                :class="{
                                                    'border-primary-400 bg-primary-50': cliente.cantidad_minima_temporal !== undefined,
                                                    'border-gray-200': cliente.cantidad_minima_temporal === undefined
                                                }"
                                            />
                                            <span class="text-[9px] text-gray-400">und</span>
                                        </div>
                                        <div v-if="cliente.cantidad_minima_temporal !== undefined" class="text-[8px] text-primary-500 mt-0.5">
                                            <i class="fas fa-pen"></i> Editando...
                                        </div>
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button
                                                @click="actualizarMinimo(cliente)"
                                                :disabled="editandoMinimo[cliente.IdContenedorCliente]"
                                                class="text-green-500 hover:text-green-700 text-[10px] disabled:opacity-50"
                                                title="Guardar"
                                            >
                                                <i v-if="editandoMinimo[cliente.IdContenedorCliente]" class="fas fa-spinner fa-spin"></i>
                                                <i v-else class="fas fa-save"></i>
                                            </button>
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

/* Transiciones */
.transition {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>