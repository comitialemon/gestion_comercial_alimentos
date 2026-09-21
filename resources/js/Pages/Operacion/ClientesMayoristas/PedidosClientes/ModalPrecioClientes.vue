<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    producto: Object,
    identificadores: Array,
    sucursalId: { type: Number, default: 0 },
    preciosActuales: { type: Object, default: () => ({}) },
})

const emit = defineEmits([
    'update:modelValue',
    'precio-agregado',
    'precio-actualizado',
    'precio-eliminado',
])

// ==================== ESTADO ====================
const busqueda = ref('')
const listaLocal = ref({})
const modoEdicion = ref({})
const precioEditando = ref({})

const mostrarSubModal = ref(false)
const nuevoClienteForm = ref({
    busqueda: '',
    identificadorId: null,
    identificadorSeleccionado: null,
    PrecioSinFactura: '',
    PrecioConFactura: '',
    PedidoMinimo: ''
})
const guardandoNuevo = ref(false)
const errorNuevo = ref('')

const dropdownAbierto = ref(false)
const inputBusquedaClienteRef = ref(null)
const nombreCache = ref({})

// ==================== COMPUTED ====================
const clientesConPrecio = computed(() => {
    const entradas = Object.entries(listaLocal.value).map(([id, data]) => {
        const identificadorId = parseInt(id)
        const d = data || {}
        return {
            IdIdentificador: identificadorId,
            PrecioSinFactura: Number(d.PrecioSinFactura ?? 0),
            PrecioConFactura: Number(d.PrecioConFactura ?? 0),
            PedidoMinimo: Number(d.PedidoMinimo ?? 0),
            Nombre: obtenerNombre(identificadorId),
            NombreSolo: obtenerNombreSolo(identificadorId)
        }
    })

    return entradas.sort((a, b) =>
        a.NombreSolo.localeCompare(b.NombreSolo, 'es', { sensitivity: 'base' })
    )
})

const clientesFiltrados = computed(() => {
    if (!busqueda.value || busqueda.value.trim().length < 1) {
        return clientesConPrecio.value
    }
    const termino = busqueda.value.toLowerCase().trim()
    return clientesConPrecio.value.filter(c =>
        c.Nombre?.toLowerCase().includes(termino) ||
        c.IdIdentificador?.toString().includes(termino)
    )
})

const clientesDisponiblesParaNuevo = computed(() => {
    const preciosExistentes = listaLocal.value
    return (props.identificadores || [])
        .filter(ident => !preciosExistentes[ident.IdIdentificador])
        .sort((a, b) =>
            (a.Nombre || '').localeCompare(b.Nombre || '', 'es', { sensitivity: 'base' })
        )
})

const clientesFiltradosParaNuevo = computed(() => {
    const texto = nuevoClienteForm.value.busqueda
    if (!texto || texto.length < 1) return []
    const termino = texto.toLowerCase().trim()
    return clientesDisponiblesParaNuevo.value
        .filter(ident =>
            ident.CI_NIT?.toString().includes(termino) ||
            ident.Nombre?.toLowerCase().includes(termino)
        )
        .slice(0, 10)
})

const puedeGuardarNuevo = computed(() => {
    return nuevoClienteForm.value.identificadorId &&
           nuevoClienteForm.value.PrecioSinFactura !== '' &&
           parseFloat(nuevoClienteForm.value.PrecioSinFactura) >= 0
})

// ==================== HELPERS ====================
const obtenerNombre = (identificadorId) => {
    if (nombreCache.value[identificadorId]) {
        return nombreCache.value[identificadorId]
    }
    const ident = (props.identificadores || []).find(
        i => i.IdIdentificador == identificadorId
    )
    if (ident) {
        const nombreCompleto = `${ident.Nombre} - ${ident.CI_NIT}`
        nombreCache.value[identificadorId] = nombreCompleto
        return nombreCompleto
    }
    return `ID: ${identificadorId}`
}

const obtenerNombreSolo = (identificadorId) => {
    const ident = (props.identificadores || []).find(
        i => i.IdIdentificador == identificadorId
    )
    return ident ? ident.Nombre : ''
}

const getClaveEdicion = (identificadorId) => `edit_${identificadorId}`

// ==================== INICIALIZAR ====================
const inicializar = () => {
    const normalizado = {}
    const precios = props.preciosActuales || {}

    if (precios && typeof precios === 'object') {
        Object.entries(precios).forEach(([id, data]) => {
            if (data === undefined || data === null) return

            if (typeof data === 'number') {
                normalizado[id] = {
                    PrecioSinFactura: data,
                    PrecioConFactura: null,
                    PedidoMinimo: 0
                }
                return
            }

            if (typeof data === 'string' && !isNaN(parseFloat(data))) {
                normalizado[id] = {
                    PrecioSinFactura: parseFloat(data),
                    PrecioConFactura: null,
                    PedidoMinimo: 0
                }
                return
            }

            if (typeof data === 'object') {
                normalizado[id] = {
                    PrecioSinFactura: data.PrecioSinFactura ?? 0,
                    PrecioConFactura: data.PrecioConFactura ?? null,
                    PedidoMinimo: data.PedidoMinimo ?? 0
                }
            }
        })
    }

    listaLocal.value = normalizado
    busqueda.value = ''
    modoEdicion.value = {}
    precioEditando.value = {}
    mostrarSubModal.value = false
    nuevoClienteForm.value = {
        busqueda: '',
        identificadorId: null,
        identificadorSeleccionado: null,
        PrecioSinFactura: '',
        PrecioConFactura: '',
        PedidoMinimo: ''
    }
    errorNuevo.value = ''
    nombreCache.value = {}
}

// ==================== SUB-MODAL ====================
const abrirSubModal = () => {
    mostrarSubModal.value = true
    errorNuevo.value = ''
    nuevoClienteForm.value = {
        busqueda: '',
        identificadorId: null,
        identificadorSeleccionado: null,
        PrecioSinFactura: '',
        PrecioConFactura: '',
        PedidoMinimo: ''
    }
    nextTick(() => {
        inputBusquedaClienteRef.value?.focus()
    })
}

const cerrarSubModal = () => {
    if (guardandoNuevo.value) return
    mostrarSubModal.value = false
    errorNuevo.value = ''
}

const seleccionarIdentificador = (ident) => {
    nuevoClienteForm.value.identificadorId = ident.IdIdentificador
    nuevoClienteForm.value.identificadorSeleccionado = ident
    nuevoClienteForm.value.busqueda = `${ident.Nombre} - ${ident.CI_NIT}`
    dropdownAbierto.value = false
    errorNuevo.value = ''

    nextTick(() => {
        document.getElementById('inputPrecioSinFacturaNuevo')?.focus()
    })
}

// ==================== GUARDAR ====================
const guardarNuevoCliente = async () => {
    errorNuevo.value = ''

    if (!nuevoClienteForm.value.identificadorId) {
        errorNuevo.value = 'Seleccione un cliente'
        return
    }

    if (!nuevoClienteForm.value.PrecioSinFactura || parseFloat(nuevoClienteForm.value.PrecioSinFactura) < 0) {
        errorNuevo.value = 'Ingrese el Precio Sin Factura'
        return
    }

    guardandoNuevo.value = true

    try {
        const payload = {
            IdIdentificador: nuevoClienteForm.value.identificadorId,
            IdProducto: props.producto.IdProducto,
            PrecioSinFactura: parseFloat(nuevoClienteForm.value.PrecioSinFactura),
            PrecioConFactura: nuevoClienteForm.value.PrecioConFactura
                ? parseFloat(nuevoClienteForm.value.PrecioConFactura)
                : null,
            PedidoMinimo: nuevoClienteForm.value.PedidoMinimo
                ? parseInt(nuevoClienteForm.value.PedidoMinimo)
                : 0,
            IdSucursal: props.sucursalId,
            Motivo: 'Asignación de precio',
        }

        const response = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/precios',
            payload
        )

        if (response.data.success) {
            const id = nuevoClienteForm.value.identificadorId

            listaLocal.value[id] = {
                PrecioSinFactura: payload.PrecioSinFactura,
                PrecioConFactura: payload.PrecioConFactura,
                PedidoMinimo: payload.PedidoMinimo
            }

            const ident = nuevoClienteForm.value.identificadorSeleccionado
            if (ident) {
                nombreCache.value[id] = `${ident.Nombre} - ${ident.CI_NIT}`
            }

            emit('precio-agregado', {
                IdProducto: props.producto.IdProducto,
                IdIdentificador: id,
                PrecioSinFactura: payload.PrecioSinFactura,
                PrecioConFactura: payload.PrecioConFactura,
                PedidoMinimo: payload.PedidoMinimo,
            })

            mostrarSubModal.value = false

            nuevoClienteForm.value = {
                busqueda: '',
                identificadorId: null,
                identificadorSeleccionado: null,
                PrecioSinFactura: '',
                PrecioConFactura: '',
                PedidoMinimo: ''
            }
        } else {
            errorNuevo.value = response.data.message || 'Error al guardar'
        }
    } catch (error) {
        console.error('❌ Error al guardar:', error.response?.data)
        if (error.response?.status === 422) {
            const firstError = Object.values(error.response.data.errors || {})[0]
            errorNuevo.value = firstError ? firstError[0] : 'Datos inválidos'
        } else {
            errorNuevo.value = error.response?.data?.message || 'Error al guardar'
        }
    } finally {
        guardandoNuevo.value = false
    }
}

const manejarEnterNuevo = (event) => {
    if (event.key === 'Enter') {
        event.preventDefault()
        if (puedeGuardarNuevo.value) guardarNuevoCliente()
    }
}

// ==================== EDICIÓN INLINE ====================
const activarEdicion = (cliente) => {
    const clave = getClaveEdicion(cliente.IdIdentificador)
    modoEdicion.value[clave] = true
    precioEditando.value[clave] = {
        PrecioSinFactura: cliente.PrecioSinFactura,
        PrecioConFactura: cliente.PrecioConFactura || '',
        PedidoMinimo: cliente.PedidoMinimo || 0,
    }

    nextTick(() => {
        document.getElementById(`inputEdit_Sin_${cliente.IdIdentificador}`)?.focus()
    })
}

const cancelarEdicion = (identificadorId) => {
    const clave = getClaveEdicion(identificadorId)
    modoEdicion.value[clave] = false
    delete precioEditando.value[clave]
}

const guardarEdicion = async (identificadorId) => {
    const clave = getClaveEdicion(identificadorId)
    const editando = precioEditando.value[clave]

    if (!editando) {
        cancelarEdicion(identificadorId)
        return
    }

    if (editando.PrecioSinFactura === '' || parseFloat(editando.PrecioSinFactura) < 0) {
        alert('Ingrese un Precio Sin Factura válido')
        return
    }

    const payload = {
        IdIdentificador: identificadorId,
        IdProducto: props.producto.IdProducto,
        PrecioSinFactura: parseFloat(editando.PrecioSinFactura),
        PrecioConFactura: editando.PrecioConFactura !== '' && editando.PrecioConFactura !== null
            ? parseFloat(editando.PrecioConFactura)
            : null,
        PedidoMinimo: editando.PedidoMinimo !== '' && editando.PedidoMinimo !== null
            ? parseInt(editando.PedidoMinimo)
            : 0,
        IdSucursal: props.sucursalId,
        Motivo: 'Actualización de precio',
    }

    const actual = listaLocal.value[identificadorId]

    if (actual &&
        Number(actual.PrecioSinFactura) === payload.PrecioSinFactura &&
        Number(actual.PrecioConFactura) === (payload.PrecioConFactura ?? 0) &&
        Number(actual.PedidoMinimo) === payload.PedidoMinimo) {
        cancelarEdicion(identificadorId)
        return
    }

    try {
        const response = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/precios',
            payload
        )

        if (response.data.success) {
            listaLocal.value[identificadorId] = {
                PrecioSinFactura: payload.PrecioSinFactura,
                PrecioConFactura: payload.PrecioConFactura,
                PedidoMinimo: payload.PedidoMinimo
            }
            cancelarEdicion(identificadorId)

            emit('precio-actualizado', {
                IdProducto: props.producto.IdProducto,
                IdIdentificador: identificadorId,
                PrecioSinFactura: payload.PrecioSinFactura,
                PrecioConFactura: payload.PrecioConFactura,
                PedidoMinimo: payload.PedidoMinimo,
            })
        } else {
            alert(response.data.message || 'Error al actualizar')
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Error al actualizar')
    }
}

const manejarEnterEdicion = (identificadorId, event) => {
    if (event.key === 'Enter') {
        event.preventDefault()
        guardarEdicion(identificadorId)
    }
}

const manejarBlurEdicion = (identificadorId) => {
    setTimeout(() => {
        guardarEdicion(identificadorId)
    }, 200)
}

// ==================== ELIMINAR ====================
const eliminarCliente = async (identificadorId) => {
    if (!confirm('¿Eliminar este precio para el cliente?')) return

    try {
        const response = await axios.delete(
            `/operacion/pedidos/clientes-mayoristas/precios/${props.producto.IdProducto}/${identificadorId}`
        )

        if (response.data.success) {
            delete listaLocal.value[identificadorId]
            emit('precio-eliminado', {
                IdProducto: props.producto.IdProducto,
                IdIdentificador: identificadorId,
            })
        } else {
            alert(response.data.message || 'Error al eliminar')
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Error al eliminar')
    }
}

// ==================== PREVENIR FLECHAS ====================
const prevenirFlechas = (event) => {
    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        event.preventDefault()
    }
}

// ==================== CERRAR ====================
const cerrar = () => {
    emit('update:modelValue', false)
}

const handleClickOutside = (event) => {
    if (dropdownAbierto.value) {
        const target = event.target
        if (!target.closest('.dropdown-nuevo-cliente')) {
            dropdownAbierto.value = false
        }
    }
}

// ==================== WATCHERS ====================
watch(() => props.modelValue, (newVal) => {
    if (newVal) inicializar()
})

watch(() => props.preciosActuales, () => {
    if (props.modelValue) inicializar()
}, { deep: true })

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <Teleport to="body">
        <!-- ==================== MODAL PRINCIPAL ==================== -->
        <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="cerrar"></div>

            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-5xl overflow-hidden max-h-[90vh] flex flex-col">

                <!-- HEADER -->
                <div class="bg-primary-600 p-3 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-tags text-white text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-white font-semibold text-sm truncate">
                                Clientes con Precio Asignado
                            </h3>
                            <p class="text-white/80 text-[10px] truncate">
                                <span class="font-mono">{{ producto?.Codigo }}</span> — {{ producto?.Descripcion }}
                            </p>
                        </div>
                    </div>
                    <button @click="cerrar" class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <!-- INFO + BUSCADOR -->
                <div class="p-3 bg-gray-50 border-b border-gray-200 flex-shrink-0">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-primary-100 text-primary-700 rounded-full text-[10px] font-medium">
                                <i class="fas fa-users text-[9px]"></i>
                                Existen {{ clientesConPrecio.length }} clientes
                            </span>
                            <span v-if="busqueda" class="text-[10px] text-gray-500">
                                ({{ clientesFiltrados.length }} filtrados)
                            </span>
                        </div>
                        <button
                            @click="abrirSubModal"
                            class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-[11px] font-medium flex items-center gap-1.5 transition-colors whitespace-nowrap"
                        >
                            <i class="fas fa-plus-circle text-[10px]"></i>
                            Nuevo cliente
                        </button>
                    </div>

                    <div class="relative">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                        <input
                            type="text"
                            v-model="busqueda"
                            placeholder="Buscar cliente en el listado..."
                            class="w-full border border-gray-300 rounded-md pl-7 pr-7 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none bg-white"
                        />
                        <button v-if="busqueda" @click="busqueda = ''" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </div>
                </div>

                <!-- LISTADO -->
                <div class="flex-1 overflow-y-auto">
                    <div v-if="clientesConPrecio.length > 0">
                        <div class="px-3 py-1.5 bg-gray-100 border-b border-gray-200 text-[9px] font-semibold text-gray-600 uppercase tracking-wider grid grid-cols-12 gap-2">
                            <div class="col-span-5 sm:col-span-4">Cliente</div>
                            <div class="col-span-2 sm:col-span-2 text-center">S/F</div>
                            <div class="col-span-2 sm:col-span-2 text-center">C/F</div>
                            <div class="col-span-1 sm:col-span-1 text-center">Mín</div>
                            <div class="col-span-2 sm:col-span-3 text-right">Acciones</div>
                        </div>

                        <div
                            v-for="cliente in clientesFiltrados"
                            :key="cliente.IdIdentificador"
                            class="px-3 py-2 border-b border-gray-100 hover:bg-gray-50 transition grid grid-cols-12 gap-2 items-center"
                        >
                            <div class="col-span-5 sm:col-span-4 min-w-0">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-6 h-6 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-user text-primary-600 text-[9px]"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-medium text-gray-800 truncate" :title="cliente.Nombre">
                                            {{ cliente.Nombre }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-span-2 sm:col-span-2 flex items-center justify-center">
                                <template v-if="modoEdicion[getClaveEdicion(cliente.IdIdentificador)]">
                                    <input
                                        :id="`inputEdit_Sin_${cliente.IdIdentificador}`"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="precioEditando[getClaveEdicion(cliente.IdIdentificador)].PrecioSinFactura"
                                        @keydown="prevenirFlechas"
                                        @keyup="(e) => manejarEnterEdicion(cliente.IdIdentificador, e)"
                                        class="no-spinner w-16 border-2 border-primary-400 bg-primary-50 rounded px-1 py-0.5 text-xs text-center focus:outline-none"
                                    />
                                </template>
                                <template v-else>
                                    <span class="text-[10px] text-gray-400 mr-0.5">Bs.</span>
                                    <span class="font-semibold text-gray-800 text-xs tabular-nums">
                                        {{ Number(cliente.PrecioSinFactura).toFixed(2) }}
                                    </span>
                                </template>
                            </div>

                            <div class="col-span-2 sm:col-span-2 flex items-center justify-center">
                                <template v-if="modoEdicion[getClaveEdicion(cliente.IdIdentificador)]">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="precioEditando[getClaveEdicion(cliente.IdIdentificador)].PrecioConFactura"
                                        @keydown="prevenirFlechas"
                                        @keyup="(e) => manejarEnterEdicion(cliente.IdIdentificador, e)"
                                        class="no-spinner w-16 border-2 border-primary-400 bg-primary-50 rounded px-1 py-0.5 text-xs text-center focus:outline-none"
                                    />
                                </template>
                                <template v-else>
                                    <span v-if="cliente.PrecioConFactura" class="font-semibold text-gray-800 text-xs tabular-nums">
                                        <span class="text-[10px] text-gray-400 mr-0.5">Bs.</span>
                                        {{ Number(cliente.PrecioConFactura).toFixed(2) }}
                                    </span>
                                    <span v-else class="text-[10px] text-gray-300 italic">—</span>
                                </template>
                            </div>

                            <div class="col-span-1 sm:col-span-1 flex items-center justify-center">
                                <template v-if="modoEdicion[getClaveEdicion(cliente.IdIdentificador)]">
                                    <input
                                        type="number"
                                        step="1"
                                        min="0"
                                        v-model="precioEditando[getClaveEdicion(cliente.IdIdentificador)].PedidoMinimo"
                                        @keydown="prevenirFlechas"
                                        @keyup="(e) => manejarEnterEdicion(cliente.IdIdentificador, e)"
                                        class="no-spinner w-12 border-2 border-primary-400 bg-primary-50 rounded px-1 py-0.5 text-xs text-center focus:outline-none"
                                    />
                                </template>
                                <template v-else>
                                    <span class="text-xs font-medium text-gray-600 tabular-nums">
                                        {{ cliente.PedidoMinimo || 0 }}
                                    </span>
                                </template>
                            </div>

                            <div class="col-span-2 sm:col-span-3 flex items-center justify-end gap-1">
                                <template v-if="!modoEdicion[getClaveEdicion(cliente.IdIdentificador)]">
                                    <button
                                        @click="activarEdicion(cliente)"
                                        class="text-primary-500 hover:text-primary-700 hover:bg-primary-50 p-1.5 rounded transition"
                                        title="Editar"
                                    >
                                        <i class="fas fa-pencil-alt text-[11px]"></i>
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="guardarEdicion(cliente.IdIdentificador)"
                                        class="text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 p-1.5 rounded transition"
                                        title="Guardar"
                                    >
                                        <i class="fas fa-check text-[11px]"></i>
                                    </button>
                                    <button
                                        @click="cancelarEdicion(cliente.IdIdentificador)"
                                        class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-1.5 rounded transition"
                                        title="Cancelar"
                                    >
                                        <i class="fas fa-times text-[11px]"></i>
                                    </button>
                                </template>
                                <button
                                    @click="eliminarCliente(cliente.IdIdentificador)"
                                    class="text-red-500 hover:text-red-700 hover:bg-red-50 p-1.5 rounded transition"
                                    title="Eliminar"
                                >
                                    <i class="fas fa-trash-alt text-[11px]"></i>
                                </button>
                            </div>
                        </div>

                        <div v-if="clientesFiltrados.length === 0 && busqueda" class="text-center py-8 text-gray-400">
                            <i class="fas fa-search text-2xl mb-2 block"></i>
                            <p class="text-xs">No se encontraron clientes con "{{ busqueda }}"</p>
                            <button @click="busqueda = ''" class="mt-2 text-primary-600 hover:text-primary-800 text-[11px] underline">
                                Limpiar búsqueda
                            </button>
                        </div>
                    </div>

                    <div v-else class="text-center py-12 text-gray-400">
                        <i class="fas fa-user-plus text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-sm font-medium text-gray-500">No hay clientes con precio asignado</p>
                        <p class="text-xs mt-1">Haz click en "Nuevo cliente" para agregar el primero</p>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="border-t border-gray-200 p-3 bg-gray-50 flex justify-between items-center flex-shrink-0">
                    <div class="text-[10px] text-gray-500">
                        <i class="fas fa-info-circle mr-1 text-[9px]"></i>
                        Los cambios se guardan automáticamente
                    </div>
                    <button @click="cerrar" class="px-4 py-1.5 bg-gray-700 hover:bg-gray-800 text-white rounded-md transition text-xs font-medium">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== SUB-MODAL NUEVO CLIENTE (COLOR PRIMARY) ==================== -->
        <div v-if="modelValue && mostrarSubModal" class="fixed inset-0 z-[110] flex items-center justify-center p-3 sm:p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="cerrarSubModal"></div>

            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-primary-600 p-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                            <i class="fas fa-user-plus text-white text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold text-sm">Nuevo Cliente</h3>
                            <p class="text-white/80 text-[10px]">Agregar precio al cliente</p>
                        </div>
                    </div>
                    <button @click="cerrarSubModal" class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-4 space-y-3">
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Cliente *</label>
                        <div class="relative dropdown-nuevo-cliente">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                            <input
                                ref="inputBusquedaClienteRef"
                                type="text"
                                v-model="nuevoClienteForm.busqueda"
                                @focus="dropdownAbierto = true"
                                @input="dropdownAbierto = true"
                                @keydown.esc="dropdownAbierto = false"
                                placeholder="Buscar por CI/NIT o nombre..."
                                class="w-full border rounded-md pl-7 pr-7 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{
                                    'border-red-500': errorNuevo,
                                    'border-primary-400 bg-primary-50': nuevoClienteForm.identificadorId,
                                    'border-gray-300': !errorNuevo && !nuevoClienteForm.identificadorId
                                }"
                                autocomplete="off"
                            />

                            <div
                                v-if="dropdownAbierto && clientesFiltradosParaNuevo.length > 0"
                                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-56 overflow-y-auto"
                            >
                                <div
                                    v-for="item in clientesFiltradosParaNuevo"
                                    :key="item.IdIdentificador"
                                    @mousedown.prevent="seleccionarIdentificador(item)"
                                    class="px-2.5 py-1.5 cursor-pointer hover:bg-primary-50 border-b border-gray-100 last:border-b-0 text-xs text-gray-700"
                                >
                                    {{ item.Nombre }} - {{ item.CI_NIT }}
                                </div>
                            </div>

                            <div
                                v-else-if="dropdownAbierto && nuevoClienteForm.busqueda && clientesFiltradosParaNuevo.length === 0"
                                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center"
                            >
                                <p class="text-[10px] text-gray-400">Sin resultados</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Sin Factura *</label>
                            <div class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400">Bs.</span>
                                <input
                                    id="inputPrecioSinFacturaNuevo"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="nuevoClienteForm.PrecioSinFactura"
                                    @keydown="prevenirFlechas"
                                    @keyup="manejarEnterNuevo"
                                    class="no-spinner w-full border border-gray-300 rounded-md pl-7 pr-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="0.00"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Con Factura</label>
                            <div class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400">Bs.</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="nuevoClienteForm.PrecioConFactura"
                                    @keydown="prevenirFlechas"
                                    @keyup="manejarEnterNuevo"
                                    class="no-spinner w-full border border-gray-300 rounded-md pl-7 pr-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="0.00"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Mínimo</label>
                            <div class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400">u.</span>
                                <input
                                    type="number"
                                    step="1"
                                    min="0"
                                    v-model="nuevoClienteForm.PedidoMinimo"
                                    @keydown="prevenirFlechas"
                                    @keyup="manejarEnterNuevo"
                                    class="no-spinner w-full border border-gray-300 rounded-md pl-7 pr-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="0"
                                />
                            </div>
                        </div>
                    </div>

                    <p v-if="errorNuevo" class="text-red-500 text-[10px] flex items-center gap-1">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ errorNuevo }}
                    </p>
                </div>

                <!-- Footer -->
                <div class="border-t border-gray-200 p-3 bg-gray-50 flex justify-end gap-2">
                    <button
                        @click="cerrarSubModal"
                        :disabled="guardandoNuevo"
                        class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-xs font-medium transition"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="guardarNuevoCliente"
                        :disabled="!puedeGuardarNuevo || guardandoNuevo"
                        class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white rounded-md text-xs font-medium flex items-center gap-1.5 transition"
                    >
                        <i v-if="guardandoNuevo" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-save text-[10px]"></i>
                        {{ guardandoNuevo ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-spinner {
    -moz-appearance: textfield;
    appearance: textfield;
}

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tabular-nums {
    font-variant-numeric: tabular-nums;
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

@media (min-width: 1024px) {
    input, button {
        font-size: 13px !important;
    }
}
</style>