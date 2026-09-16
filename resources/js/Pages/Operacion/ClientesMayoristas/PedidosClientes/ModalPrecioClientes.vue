<!-- resources/js/Pages/Operacion/ClientesMayoristas/PedidosClientes/ModalPrecioClientes.vue -->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    producto: Object,
    identificadores: Array,
    sucursalId: {
        type: Number,
        default: 0
    },
    // ✅ Los precios actuales del producto { IdIdentificador: precio }
    preciosActuales: {
        type: Object,
        default: () => ({})
    },
})

// ✅ Emits: 3 eventos específicos para actualización optimista
const emit = defineEmits([
    'update:modelValue',
    'precio-agregado',
    'precio-actualizado',
    'precio-eliminado',
])

// ==================== ESTADO ====================
const busqueda = ref('')
const listaLocal = ref({}) // { IdIdentificador: precio } — estado interno
const modoEdicion = ref({})
const precioEditando = ref({})

// ✅ Nuevo cliente (fila al inicio)
const mostrandoNuevaFila = ref(false)
const nuevoClienteForm = ref({
    busqueda: '',
    identificadorId: null,
    identificadorSeleccionado: null,
    precio: ''
})
const guardandoNuevo = ref(false)
const errorNuevo = ref('')

// ✅ Dropdown de búsqueda
const dropdownAbierto = ref(false)
const inputBusquedaClienteRef = ref(null)

// ✅ Cache de nombres
const nombreCache = ref({})

// ==================== COMPUTED ====================

/**
 * ✅ Clientes con precio YA asignado en este producto
 */
const clientesConPrecio = computed(() => {
    const entradas = Object.entries(listaLocal.value).map(([id, precio]) => {
        const identificadorId = parseInt(id)
        return {
            IdIdentificador: identificadorId,
            Precio: Number(precio),
            Nombre: obtenerNombre(identificadorId),
            NombreSolo: obtenerNombreSolo(identificadorId)
        }
    })
    
    // ✅ Ordenar alfabéticamente por nombre
    return entradas.sort((a, b) => 
        a.NombreSolo.localeCompare(b.NombreSolo, 'es', { sensitivity: 'base' })
    )
})

/**
 * ✅ Clientes filtrados por búsqueda (para el listado)
 */
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

/**
 * ✅ Clientes disponibles para asignar (que NO tienen precio aún)
 */
const clientesDisponiblesParaNuevo = computed(() => {
    const preciosExistentes = listaLocal.value
    
    return (props.identificadores || [])
        .filter(ident => !preciosExistentes[ident.IdIdentificador])
        .sort((a, b) => 
            (a.Nombre || '').localeCompare(b.Nombre || '', 'es', { sensitivity: 'base' })
        )
})

/**
 * ✅ Clientes filtrados por búsqueda en la fila nueva
 */
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

/**
 * ✅ Puede guardar el nuevo cliente
 */
const puedeGuardarNuevo = computed(() => {
    return nuevoClienteForm.value.identificadorId &&
           nuevoClienteForm.value.precio !== '' &&
           parseFloat(nuevoClienteForm.value.precio) >= 0
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
        const nombreCompleto = `${ident.CI_NIT} - ${ident.Nombre}`
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

const getClaveEdicion = (identificadorId) => {
    return `edit_${identificadorId}`
}

// ==================== INICIALIZAR ====================
const inicializar = () => {
    // Copiar precios actuales al estado local
    listaLocal.value = { ...props.preciosActuales }
    busqueda.value = ''
    modoEdicion.value = {}
    precioEditando.value = {}
    mostrandoNuevaFila.value = false
    nuevoClienteForm.value = {
        busqueda: '',
        identificadorId: null,
        identificadorSeleccionado: null,
        precio: ''
    }
    errorNuevo.value = ''
    nombreCache.value = {}
}

// ==================== NUEVA FILA ====================
const abrirNuevaFila = () => {
    mostrandoNuevaFila.value = true
    errorNuevo.value = ''
    nuevoClienteForm.value = {
        busqueda: '',
        identificadorId: null,
        identificadorSeleccionado: null,
        precio: ''
    }
    
    nextTick(() => {
        inputBusquedaClienteRef.value?.focus()
    })
}

const cancelarNuevaFila = () => {
    mostrandoNuevaFila.value = false
    errorNuevo.value = ''
    nuevoClienteForm.value = {
        busqueda: '',
        identificadorId: null,
        identificadorSeleccionado: null,
        precio: ''
    }
}

const seleccionarIdentificador = (ident) => {
    nuevoClienteForm.value.identificadorId = ident.IdIdentificador
    nuevoClienteForm.value.identificadorSeleccionado = ident
    nuevoClienteForm.value.busqueda = `${ident.CI_NIT} - ${ident.Nombre}`
    dropdownAbierto.value = false
    errorNuevo.value = ''
    
    nextTick(() => {
        document.getElementById('inputPrecioNuevo')?.focus()
    })
}

// ==================== GUARDAR NUEVO CLIENTE (OPTIMISTIC) ====================
const guardarNuevoCliente = async () => {
    errorNuevo.value = ''
    
    if (!nuevoClienteForm.value.identificadorId) {
        errorNuevo.value = 'Seleccione un cliente'
        return
    }
    
    if (!nuevoClienteForm.value.precio || parseFloat(nuevoClienteForm.value.precio) < 0) {
        errorNuevo.value = 'Ingrese un precio válido'
        return
    }
    
    guardandoNuevo.value = true
    
    try {
        const payload = {
            IdIdentificador: nuevoClienteForm.value.identificadorId,
            IdProducto: props.producto.IdProducto,
            Precio: parseFloat(nuevoClienteForm.value.precio),
            IdSucursal: props.sucursalId,
            Motivo: 'Asignación de precio',
        }
        
        console.log('📤 POST /precios', payload)
        
        const response = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/precios',
            payload
        )
        
        if (response.data.success) {
            const id = nuevoClienteForm.value.identificadorId
            const precio = parseFloat(nuevoClienteForm.value.precio)
            
            // ✅ Actualizar estado local del modal
            listaLocal.value[id] = precio
            
            const ident = nuevoClienteForm.value.identificadorSeleccionado
            if (ident) {
                nombreCache.value[id] = `${ident.CI_NIT} - ${ident.Nombre}`
            }
            
            // ✅ EMITIR DATOS EXACTOS AL PADRE (actualización optimista)
            emit('precio-agregado', {
                IdProducto: props.producto.IdProducto,
                IdIdentificador: id,
                Precio: precio,
            })
            
            cancelarNuevaFila()
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

// ==================== EDICIÓN INLINE (OPTIMISTIC) ====================
const activarEdicion = (identificadorId, precioActual) => {
    const clave = getClaveEdicion(identificadorId)
    modoEdicion.value[clave] = true
    precioEditando.value[clave] = precioActual
    
    nextTick(() => {
        document.getElementById(`inputEdit_${identificadorId}`)?.focus()
    })
}

const cancelarEdicion = (identificadorId) => {
    const clave = getClaveEdicion(identificadorId)
    modoEdicion.value[clave] = false
    delete precioEditando.value[clave]
}

const guardarEdicion = async (identificadorId) => {
    const clave = getClaveEdicion(identificadorId)
    const nuevoPrecio = precioEditando.value[clave]
    
    if (nuevoPrecio === undefined || nuevoPrecio === null || nuevoPrecio === '') {
        cancelarEdicion(identificadorId)
        return
    }
    
    const precioNumerico = parseFloat(nuevoPrecio)
    if (isNaN(precioNumerico) || precioNumerico < 0) {
        alert('Ingrese un precio válido')
        return
    }
    
    if (listaLocal.value[identificadorId] == precioNumerico) {
        cancelarEdicion(identificadorId)
        return
    }
    
    try {
        const response = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/precios',
            {
                IdIdentificador: identificadorId,
                IdProducto: props.producto.IdProducto,
                Precio: precioNumerico,
                IdSucursal: props.sucursalId,
                Motivo: 'Actualización de precio',
            }
        )
        
        if (response.data.success) {
            listaLocal.value[identificadorId] = precioNumerico
            cancelarEdicion(identificadorId)
            
            // ✅ EMITIR DATOS AL PADRE
            emit('precio-actualizado', {
                IdProducto: props.producto.IdProducto,
                IdIdentificador: identificadorId,
                Precio: precioNumerico,
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
    }, 150)
}

// ==================== ELIMINAR (OPTIMISTIC) ====================
const eliminarCliente = async (identificadorId) => {
    if (!confirm('¿Eliminar este precio para el cliente?')) return
    
    try {
        const response = await axios.delete(
            `/operacion/pedidos/clientes-mayoristas/precios/${props.producto.IdProducto}/${identificadorId}`
        )
        
        if (response.data.success) {
            delete listaLocal.value[identificadorId]
            
            // ✅ EMITIR DATOS AL PADRE
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

// ==================== CERRAR MODAL ====================
const cerrar = () => {
    emit('update:modelValue', false)
}

// ==================== CLICK OUTSIDE ====================
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
    if (newVal) {
        inicializar()
    }
})

watch(() => props.preciosActuales, () => {
    if (props.modelValue) {
        inicializar()
    }
}, { deep: true })

// ==================== LIFECYCLE ====================
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <Teleport to="body">
        <div 
            v-if="modelValue" 
            class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-4"
        >
            <!-- Overlay -->
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="cerrar"></div>
            
            <!-- Modal -->
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden max-h-[90vh] flex flex-col">
                
                <!-- ==================== HEADER ==================== -->
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 p-3 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-tags text-white text-base"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-white font-semibold text-sm truncate">
                                Clientes con Precio Asignado
                            </h3>
                            <p class="text-white/70 text-[10px] truncate" :title="producto?.Descripcion">
                                <span class="font-mono">{{ producto?.Codigo }}</span> — {{ producto?.Descripcion }}
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="cerrar" 
                        class="text-white/80 hover:text-white transition p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- ==================== INFO + BUSCADOR ==================== -->
                <div class="p-3 bg-gray-50 border-b flex-shrink-0">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px] font-medium">
                                <i class="fas fa-users text-[9px]"></i>
                                Existen {{ clientesConPrecio.length }} clientes
                            </span>
                            <span v-if="busqueda" class="text-[10px] text-gray-500">
                                ({{ clientesFiltrados.length }} filtrados)
                            </span>
                        </div>
                        
                        <button
                            @click="abrirNuevaFila"
                            :disabled="mostrandoNuevaFila"
                            class="px-3 py-1.5 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-[11px] font-medium flex items-center gap-1.5 transition-colors whitespace-nowrap"
                        >
                            <i class="fas fa-plus-circle text-[10px]"></i>
                            Nuevo cliente
                        </button>
                    </div>
                    
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                        <input 
                            type="text"
                            v-model="busqueda"
                            placeholder="Buscar cliente en el listado..."
                            class="w-full border border-gray-200 rounded-md pl-8 pr-8 py-1.5 text-xs focus:ring-2 focus:ring-primary-400 focus:border-primary-400 outline-none transition bg-white"
                        />
                        <button
                            v-if="busqueda"
                            @click="busqueda = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- ==================== LISTADO SCROLLEABLE ==================== -->
                <div class="flex-1 overflow-y-auto">
                    
                    <!-- ✅ FILA NUEVA -->
                    <div 
                        v-if="mostrandoNuevaFila"
                        class="p-3 bg-green-50 border-b-2 border-green-300"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-600 text-white rounded text-[9px] font-medium">
                                <i class="fas fa-plus text-[8px]"></i>
                                NUEVO CLIENTE
                            </span>
                            <span class="text-[10px] text-green-700">
                                Complete los datos para asignar precio
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative dropdown-nuevo-cliente" style="min-width: 260px; flex: 1;">
                                <input
                                    ref="inputBusquedaClienteRef"
                                    type="text"
                                    v-model="nuevoClienteForm.busqueda"
                                    @focus="dropdownAbierto = true"
                                    @input="dropdownAbierto = true"
                                    @keydown.esc="dropdownAbierto = false"
                                    placeholder="Buscar por CI/NIT o nombre..."
                                    class="w-full border rounded-md px-2.5 py-1.5 text-xs focus:ring-2 focus:ring-green-400 focus:border-green-400 outline-none transition"
                                    :class="{ 
                                        'border-red-500': errorNuevo,
                                        'border-green-400 bg-green-50': nuevoClienteForm.identificadorId
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
                                        class="px-2.5 py-1.5 cursor-pointer hover:bg-green-50 border-b border-gray-100 last:border-b-0 transition flex items-center gap-2"
                                    >
                                        <span class="font-mono text-[10px] text-primary-600 bg-primary-50 px-1.5 py-0.5 rounded flex-shrink-0">
                                            {{ item.CI_NIT }}
                                        </span>
                                        <span class="text-xs text-gray-800 truncate">{{ item.Nombre }}</span>
                                    </div>
                                </div>
                                
                                <div
                                    v-if="dropdownAbierto && nuevoClienteForm.busqueda && clientesFiltradosParaNuevo.length === 0"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-3 text-center"
                                >
                                    <i class="fas fa-search text-gray-300 text-base mb-1 block"></i>
                                    <p class="text-[10px] text-gray-500">No hay clientes disponibles con esa búsqueda</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-1">
                                <span class="text-gray-400 text-[10px]">Bs.</span>
                                <input
                                    id="inputPrecioNuevo"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="nuevoClienteForm.precio"
                                    @keydown="prevenirFlechas"
                                    @keyup="manejarEnterNuevo"
                                    class="no-spinner w-24 border rounded-md px-2 py-1.5 text-xs text-center focus:ring-2 focus:ring-green-400 focus:border-green-400 outline-none transition"
                                    placeholder="0.00"
                                />
                            </div>
                            
                            <button
                                @click="guardarNuevoCliente"
                                :disabled="!puedeGuardarNuevo || guardandoNuevo"
                                class="px-3 py-1.5 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-[11px] font-medium flex items-center gap-1.5 transition-colors"
                            >
                                <i v-if="guardandoNuevo" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-save text-[10px]"></i>
                                Guardar
                            </button>
                            
                            <button
                                @click="cancelarNuevaFila"
                                :disabled="guardandoNuevo"
                                class="px-2 py-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md text-[11px] transition"
                                title="Cancelar"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <p v-if="errorNuevo" class="text-red-500 text-[10px] mt-1.5 flex items-center gap-1">
                            <i class="fas fa-exclamation-circle"></i>
                            {{ errorNuevo }}
                        </p>
                    </div>

                    <!-- ✅ LISTADO DE CLIENTES CON PRECIO -->
                    <div v-if="clientesConPrecio.length > 0">
                        <div class="px-3 py-1.5 bg-gray-100 border-b border-gray-200 text-[9px] font-semibold text-gray-600 uppercase tracking-wider grid grid-cols-12 gap-2">
                            <div class="col-span-7 sm:col-span-6">Cliente</div>
                            <div class="col-span-3 sm:col-span-3 text-center">Precio</div>
                            <div class="col-span-2 sm:col-span-3 text-right">Acciones</div>
                        </div>
                        
                        <div
                            v-for="cliente in clientesFiltrados"
                            :key="cliente.IdIdentificador"
                            class="px-3 py-2 border-b border-gray-100 hover:bg-gray-50 transition grid grid-cols-12 gap-2 items-center"
                        >
                            <div class="col-span-7 sm:col-span-6 min-w-0">
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
                            
                            <div class="col-span-3 sm:col-span-3 flex items-center justify-center gap-1">
                                <template v-if="modoEdicion[getClaveEdicion(cliente.IdIdentificador)]">
                                    <span class="text-gray-400 text-[10px]">Bs.</span>
                                    <input
                                        :id="`inputEdit_${cliente.IdIdentificador}`"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="precioEditando[getClaveEdicion(cliente.IdIdentificador)]"
                                        @keydown="prevenirFlechas"
                                        @keyup="(e) => manejarEnterEdicion(cliente.IdIdentificador, e)"
                                        @blur="manejarBlurEdicion(cliente.IdIdentificador)"
                                        class="no-spinner w-20 border-2 border-primary-400 bg-primary-50 rounded px-2 py-0.5 text-xs text-center focus:outline-none focus:ring-2 focus:ring-primary-300"
                                    />
                                </template>
                                <template v-else>
                                    <span class="text-gray-400 text-[10px]">Bs.</span>
                                    <span class="font-semibold text-gray-800 text-xs tabular-nums">
                                        {{ Number(cliente.Precio).toFixed(2) }}
                                    </span>
                                </template>
                            </div>
                            
                            <div class="col-span-2 sm:col-span-3 flex items-center justify-end gap-1">
                                <template v-if="!modoEdicion[getClaveEdicion(cliente.IdIdentificador)]">
                                    <button
                                        @click="activarEdicion(cliente.IdIdentificador, cliente.Precio)"
                                        class="text-primary-500 hover:text-primary-700 hover:bg-primary-50 p-1.5 rounded transition"
                                        title="Editar precio"
                                    >
                                        <i class="fas fa-pencil-alt text-[11px]"></i>
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="guardarEdicion(cliente.IdIdentificador)"
                                        class="text-green-600 hover:text-green-800 hover:bg-green-50 p-1.5 rounded transition"
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
                                    title="Eliminar precio"
                                >
                                    <i class="fas fa-trash-alt text-[11px]"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div 
                            v-if="clientesFiltrados.length === 0 && busqueda"
                            class="text-center py-8 text-gray-400"
                        >
                            <i class="fas fa-search text-2xl mb-2 block"></i>
                            <p class="text-xs">No se encontraron clientes con "{{ busqueda }}"</p>
                            <button
                                @click="busqueda = ''"
                                class="mt-2 text-primary-600 hover:text-primary-800 text-[11px] underline"
                            >
                                Limpiar búsqueda
                            </button>
                        </div>
                    </div>
                    
                    <div 
                        v-else-if="!mostrandoNuevaFila"
                        class="text-center py-12 text-gray-400"
                    >
                        <i class="fas fa-user-plus text-4xl mb-3 block text-gray-300"></i>
                        <p class="text-sm font-medium text-gray-500">No hay clientes con precio asignado</p>
                        <p class="text-xs mt-1">Haz click en "Nuevo cliente" para agregar el primero</p>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="border-t border-gray-200 p-3 bg-gray-50 flex justify-between items-center flex-shrink-0">
                    <div class="text-[10px] text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Los cambios se guardan automáticamente
                    </div>
                    <button 
                        @click="cerrar"
                        class="px-4 py-1.5 bg-gray-700 hover:bg-gray-800 text-white rounded-md transition text-xs font-medium"
                    >
                        Cerrar
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
    width: 6px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
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