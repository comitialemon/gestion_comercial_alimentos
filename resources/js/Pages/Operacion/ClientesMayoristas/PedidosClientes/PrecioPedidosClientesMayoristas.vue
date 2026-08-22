<script setup>
import { ref, shallowRef, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    identificadores: {
        type: Array,
        default: () => []
    },
    productos: {
        type: Array,
        default: () => []
    },
    sucursalId: {
        type: Number,
        default: 0
    }
})

// ==========================================
// FILTROS
// ==========================================
const busqueda = ref('')
const cargando = ref(false)
const productosCargados = ref(false)
const productosData = shallowRef(props.productos)

// ==========================================
// PAGINACIÓN
// ==========================================
const paginaActual = ref(1)
const itemsPorPagina = ref(6)

// ==========================================
// ESTADO DE EXPANSIÓN
// ==========================================
const productosExpandidos = ref({})

// ==========================================
// NUEVO CLIENTE (por producto)
// ==========================================
const nuevoClienteForm = ref({})
const guardandoNuevo = ref({})
const errorNuevo = ref({})

// ==========================================
// ✅ MODO EDICIÓN - POR CADA CLIENTE
// ==========================================
const modoEdicion = ref({})
const precioEditando = ref({})

// ==========================================
// LISTA DE IDENTIFICADORES (OPERADORES)
// ==========================================
const listaIdentificadores = ref(props.identificadores || [])

// ==========================================
// ✅ NUEVO: BUSCADOR DE CLIENTES DENTRO DEL PRODUCTO
// ==========================================
const busquedaClientePorProducto = ref({}) // { productoId: 'texto de búsqueda' }

// ==========================================
// DEBOUNCE PARA BÚSQUEDA
// ==========================================
let timeoutId = null

// ==========================================
// COMPUTED
// ==========================================
const productosFiltrados = computed(() => {
    let resultados = productosData.value
    if (busqueda.value) {
        const busquedaLower = busqueda.value.toLowerCase()
        resultados = resultados.filter(p =>
            p.Codigo?.toLowerCase().includes(busquedaLower) ||
            p.Descripcion?.toLowerCase().includes(busquedaLower)
        )
    }
    return resultados
})

const totalPaginas = computed(() => {
    return Math.ceil(productosFiltrados.value.length / itemsPorPagina.value)
})

const productosPaginados = computed(() => {
    const inicio = (paginaActual.value - 1) * itemsPorPagina.value
    const fin = inicio + itemsPorPagina.value
    return productosFiltrados.value.slice(inicio, fin)
})

// ==========================================
// MÉTODOS
// ==========================================

const cargarProductos = () => {
    cargando.value = true
    const url = `/operacion/pedidos/clientes-mayoristas/precios`
    router.visit(url, {
        method: 'get',
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            productosData.value = page.props.productos || []
            if (page.props.identificadores) {
                listaIdentificadores.value = page.props.identificadores
                nombreCache.value = {}
            }
            productosCargados.value = true
            cargando.value = false
        },
        onError: () => {
            cargando.value = false
            alert('Error al cargar los productos')
        }
    })
}

const buscarProductos = () => {
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => {
        paginaActual.value = 1
    }, 300)
}

const toggleExpandir = (productoId) => {
    productosExpandidos.value[productoId] = !productosExpandidos.value[productoId]
}

const estaExpandido = (productoId) => {
    return productosExpandidos.value[productoId] || false
}

const nombreCache = ref({})
const obtenerNombreIdentificador = (identificadorId) => {
    if (nombreCache.value[identificadorId]) {
        return nombreCache.value[identificadorId]
    }
    const ident = listaIdentificadores.value.find(
        i => i.IdIdentificador == identificadorId
    )
    if (ident) {
        const nombreCompleto = `${ident.CI_NIT} - ${ident.Nombre}`
        nombreCache.value[identificadorId] = nombreCompleto
        return nombreCompleto
    }
    return 'ID: ' + identificadorId
}

// ✅ OBTENER CLIENTES DE UN PRODUCTO CON FILTRO DE BÚSQUEDA
const getClientesProducto = (producto) => {
    if (!producto.precios || Object.keys(producto.precios).length === 0) {
        return []
    }
    
    let clientes = Object.entries(producto.precios).map(([id, precio]) => ({
        IdIdentificador: parseInt(id),
        Precio: precio,
        Nombre: obtenerNombreIdentificador(parseInt(id))
    }))
    
    // ✅ FILTRAR POR BÚSQUEDA DE CLIENTE DENTRO DEL PRODUCTO
    const busquedaCliente = busquedaClientePorProducto.value[producto.IdProducto] || ''
    if (busquedaCliente.trim()) {
        const termino = busquedaCliente.toLowerCase().trim()
        clientes = clientes.filter(c =>
            c.Nombre?.toLowerCase().includes(termino) ||
            c.IdIdentificador?.toString().includes(termino)
        )
    }
    
    return clientes
}

// ==========================================
// ✅ FUNCIONES DE EDICIÓN
// ==========================================

const getClaveEdicion = (productoId, identificadorId) => {
    return `${productoId}_${identificadorId}`
}

const activarEdicion = (productoId, identificadorId, precioActual) => {
    const clave = getClaveEdicion(productoId, identificadorId)
    modoEdicion.value[clave] = true
    precioEditando.value[clave] = precioActual
}

const cancelarEdicion = (productoId, identificadorId) => {
    const clave = getClaveEdicion(productoId, identificadorId)
    modoEdicion.value[clave] = false
    delete precioEditando.value[clave]
}

const guardarEdicion = async (productoId, identificadorId) => {
    const clave = getClaveEdicion(productoId, identificadorId)
    const nuevoPrecio = precioEditando.value[clave]
    
    if (nuevoPrecio === undefined || nuevoPrecio === null) {
        cancelarEdicion(productoId, identificadorId)
        return
    }
    
    const precioNumerico = parseFloat(nuevoPrecio)
    if (isNaN(precioNumerico) || precioNumerico < 0) {
        alert('Ingrese un precio válido')
        return
    }
    
    const producto = productosData.value.find(p => p.IdProducto === productoId)
    if (producto && producto.precios && producto.precios[identificadorId] == precioNumerico) {
        cancelarEdicion(productoId, identificadorId)
        return
    }
    
    try {
        const formData = new FormData()
        formData.append('IdIdentificador', identificadorId)
        formData.append('IdProducto', productoId)
        formData.append('Precio', precioNumerico)
        formData.append('IdSucursal', props.sucursalId)
        formData.append('Motivo', 'Actualización de precio')
        
        const response = await axios.post('/operacion/pedidos/clientes-mayoristas/precios', formData, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }
        })
        
        if (response.data.success) {
            const producto = productosData.value.find(p => p.IdProducto === productoId)
            if (producto) {
                if (!producto.precios) producto.precios = {}
                producto.precios[identificadorId] = precioNumerico
            }
            cancelarEdicion(productoId, identificadorId)
            const ident = listaIdentificadores.value.find(i => i.IdIdentificador == identificadorId)
            if (ident) {
                nombreCache.value[identificadorId] = `${ident.CI_NIT} - ${ident.Nombre}`
            }
        } else {
            alert(response.data.message || 'Error al actualizar')
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Error al actualizar')
    }
}

const manejarEnter = (productoId, identificadorId, event) => {
    if (event.key === 'Enter') {
        event.preventDefault()
        guardarEdicion(productoId, identificadorId)
    }
}

const manejarBlur = (productoId, identificadorId) => {
    guardarEdicion(productoId, identificadorId)
}

const prevenirFlechas = (event) => {
    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        event.preventDefault()
    }
}

// ==========================================
// AGREGAR NUEVO CLIENTE
// ==========================================

const iniciarNuevoCliente = (productoId) => {
    nuevoClienteForm.value[productoId] = {
        identificadorId: '',
        precio: '',
        busqueda: ''
    }
    errorNuevo.value[productoId] = ''
    productosExpandidos.value[productoId] = true
}

const cancelarNuevoCliente = (productoId) => {
    delete nuevoClienteForm.value[productoId]
    delete errorNuevo.value[productoId]
}

const seleccionarClienteParaProducto = (productoId, cliente) => {
    nuevoClienteForm.value[productoId].identificadorId = cliente.IdIdentificador
    nuevoClienteForm.value[productoId].busqueda = `${cliente.CI_NIT} - ${cliente.Nombre}`
    errorNuevo.value[productoId] = ''
}

const clientesFiltradosParaProducto = (productoId) => {
    const form = nuevoClienteForm.value[productoId]
    if (!form || !form.busqueda) return []
    const termino = form.busqueda.toLowerCase()
    const producto = productosData.value.find(p => p.IdProducto === productoId)
    const preciosExistentes = producto?.precios || {}
    
    return listaIdentificadores.value.filter(i =>
        !preciosExistentes[i.IdIdentificador] &&
        (i.CI_NIT?.toString().includes(termino) ||
        i.Nombre?.toLowerCase().includes(termino))
    )
}

const guardarNuevoCliente = async (productoId) => {
    const form = nuevoClienteForm.value[productoId]
    if (!form) return
    
    if (!form.identificadorId) {
        errorNuevo.value[productoId] = 'Seleccione un cliente'
        return
    }
    
    if (!form.precio || parseFloat(form.precio) < 0) {
        errorNuevo.value[productoId] = 'Ingrese un precio válido'
        return
    }
    
    guardandoNuevo.value[productoId] = true
    errorNuevo.value[productoId] = ''
    
    try {
        const formData = new FormData()
        formData.append('IdIdentificador', form.identificadorId)
        formData.append('IdProducto', productoId)
        formData.append('Precio', parseFloat(form.precio))
        formData.append('IdSucursal', props.sucursalId)
        formData.append('Motivo', 'Asignación de precio')
        
        const response = await axios.post('/operacion/pedidos/clientes-mayoristas/precios', formData, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }
        })
        
        if (response.data.success) {
            const producto = productosData.value.find(p => p.IdProducto === productoId)
            if (producto) {
                if (!producto.precios) producto.precios = {}
                producto.precios[form.identificadorId] = parseFloat(form.precio)
                const ident = listaIdentificadores.value.find(i => i.IdIdentificador == form.identificadorId)
                if (ident) {
                    nombreCache.value[form.identificadorId] = `${ident.CI_NIT} - ${ident.Nombre}`
                }
            }
            delete nuevoClienteForm.value[productoId]
            delete guardandoNuevo.value[productoId]
        } else {
            errorNuevo.value[productoId] = response.data.message || 'Error al guardar'
        }
    } catch (error) {
        errorNuevo.value[productoId] = error.response?.data?.message || 'Error al guardar'
    } finally {
        guardandoNuevo.value[productoId] = false
    }
}

// ==========================================
// ELIMINAR CLIENTE
// ==========================================
const eliminarCliente = async (productoId, identificadorId) => {
    if (!confirm('¿Eliminar este precio para el cliente?')) return
    
    try {
        const response = await axios.delete(`/operacion/pedidos/clientes-mayoristas/precios/${productoId}/${identificadorId}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }
        })
        
        if (response.data.success) {
            const producto = productosData.value.find(p => p.IdProducto === productoId)
            if (producto && producto.precios) {
                delete producto.precios[identificadorId]
                if (Object.keys(producto.precios).length === 0) {
                    producto.precios = {}
                }
            }
        } else {
            alert(response.data.message || 'Error al eliminar')
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Error al eliminar')
    }
}

// ==========================================
// ✅ IR A BITÁCORA
// ==========================================
const irABitacora = () => {
    router.visit('/operacion/pedidos/clientes-mayoristas/precios/bitacora')
}

// ==========================================
// LIFECYCLE
// ==========================================
onMounted(() => {
    productosCargados.value = true
    console.log('📋 IDENTIFICADORES RECIBIDOS:', props.identificadores)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-7xl mx-auto">
                
                <!-- HEADER -->
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-tag text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Asignación de Precios</h1>
                            <p class="text-[10px] text-gray-500 hidden sm:block">
                                Asigna precios a los operadores de tipo <span class="font-medium text-primary-600">PedidoClientes</span>
                            </p>
                        </div>
                    </div>
                    <button
                        @click="irABitacora"
                        class="px-3 py-1.5 bg-purple-600 text-white rounded-md text-xs hover:bg-purple-700 flex items-center gap-1 whitespace-nowrap transition-colors"
                    >
                        <i class="fas fa-history text-[10px]"></i>
                        Ver Bitácora
                    </button>
                </div>

                <!-- FILTROS -->
                <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-4">
                    <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-end gap-3">
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Buscar Producto</label>
                            <div class="relative">
                                <input
                                    type="text"
                                    v-model="busqueda"
                                    @input="buscarProductos"
                                    placeholder="Buscar por código o descripción..."
                                    class="w-full border rounded-md px-2 py-1.5 text-xs pl-7"
                                />
                                <i class="fas fa-search absolute left-2 top-2 text-gray-400 text-[10px]"></i>
                            </div>
                        </div>

                        <div class="flex gap-2 flex-shrink-0">
                            <button
                                @click="cargarProductos"
                                :disabled="cargando"
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs hover:bg-primary-700 disabled:opacity-50 flex items-center gap-1 whitespace-nowrap transition-colors"
                            >
                                <i class="fas fa-sync-alt" :class="{'animate-spin': cargando}"></i>
                                Actualizar
                            </button>
                        </div>

                        <div class="text-right flex items-center" v-if="!cargando && productosCargados">
                            <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-[10px] whitespace-nowrap">
                                <i class="fas fa-check-circle mr-1"></i>
                                {{ productosFiltrados.length }} productos
                            </span>
                        </div>
                    </div>
                </div>

                <!-- TABLA DE PRODUCTOS CON SUB-FILAS -->
                <div v-if="cargando" class="flex justify-center items-center py-12">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-primary-500 mb-3"></i>
                        <p class="text-gray-600 text-sm">Cargando productos...</p>
                    </div>
                </div>

                <div v-else>
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <!-- Versión Desktop -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full border-collapse text-xs">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b w-8">#</th>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">CÓDIGO</th>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">PRODUCTO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        v-for="(producto, index) in productosPaginados"
                                        :key="producto.IdProducto"
                                    >
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="px-3 py-2 text-center align-middle">
                                                <button
                                                    @click="toggleExpandir(producto.IdProducto)"
                                                    class="text-gray-500 hover:text-primary-600 transition w-6 h-6 flex items-center justify-center"
                                                >
                                                    <i :class="estaExpandido(producto.IdProducto) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
                                                </button>
                                            </td>
                                            <td class="px-3 py-2 font-mono align-middle">
                                                {{ producto.Codigo }}
                                            </td>
                                            <td class="px-3 py-2 align-middle">
                                                {{ producto.Descripcion }}
                                            </td>
                                        </tr>

                                        <!-- SUB-FILAS: CLIENTES CON PRECIO -->
                                        <tr v-if="estaExpandido(producto.IdProducto)">
                                            <td colspan="4" class="px-0 py-0 bg-gray-50">
                                                <div class="pl-8 pr-4 py-2 border-b border-gray-200">
                                                    
                                                    <!-- ✅ BUSCADOR DE CLIENTES DENTRO DEL PRODUCTO -->
                                                    <div class="mb-3 flex items-center gap-2">
                                                        <div class="relative flex-1 max-w-xs">
                                                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                                            <input
                                                                type="text"
                                                                :placeholder="'Buscar cliente en ' + producto.Codigo + '...'"
                                                                v-model="busquedaClientePorProducto[producto.IdProducto]"
                                                                class="w-full border border-gray-200 rounded-md pl-7 pr-3 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none transition bg-white"
                                                            />
                                                        </div>
                                                        <button
                                                            v-if="busquedaClientePorProducto[producto.IdProducto]"
                                                            @click="busquedaClientePorProducto[producto.IdProducto] = ''"
                                                            class="text-gray-400 hover:text-gray-600 text-xs"
                                                        >
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                        <span class="text-[9px] text-gray-400">
                                                            {{ getClientesProducto(producto).length }} clientes
                                                        </span>
                                                    </div>

                                                    <!-- CLIENTES CON PRECIO (FILTRADOS) -->
                                                    <div
                                                        v-for="cliente in getClientesProducto(producto)"
                                                        :key="cliente.IdIdentificador"
                                                        class="flex flex-wrap items-center gap-2 py-1.5 border-b border-dashed border-gray-200 last:border-b-0"
                                                    >
                                                        <span 
                                                            class="text-gray-600 text-[11px] truncate"
                                                            style="width: 280px; display: inline-block;"
                                                            :title="cliente.Nombre"
                                                        >
                                                            {{ cliente.Nombre }}
                                                        </span>
                                                        <span class="text-gray-400 text-[10px]">Bs.</span>
                                                        
                                                        <!-- MODO EDICIÓN ACTIVO -->
                                                        <template v-if="modoEdicion[getClaveEdicion(producto.IdProducto, cliente.IdIdentificador)]">
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                v-model="precioEditando[getClaveEdicion(producto.IdProducto, cliente.IdIdentificador)]"
                                                                @keydown="prevenirFlechas"
                                                                @keyup="(e) => manejarEnter(producto.IdProducto, cliente.IdIdentificador, e)"
                                                                @blur="manejarBlur(producto.IdProducto, cliente.IdIdentificador)"
                                                                class="no-spinner w-20 sm:w-24 border-2 border-primary-400 bg-primary-50 rounded px-2 py-0.5 text-xs text-center focus:outline-none focus:ring-2 focus:ring-primary-300"
                                                                autofocus
                                                            />
                                                            <span class="text-primary-500 text-[10px] animate-pulse">
                                                                <i class="fas fa-pen"></i> Editando...
                                                            </span>
                                                        </template>
                                                        
                                                        <!-- MODO LECTURA (normal) -->
                                                        <template v-else>
                                                            <span class="font-medium text-gray-800 w-16 text-center">
                                                                {{ Number(cliente.Precio).toFixed(2) }}
                                                            </span>
                                                            <button
                                                                @click="activarEdicion(producto.IdProducto, cliente.IdIdentificador, cliente.Precio)"
                                                                class="text-primary-500 hover:text-primary-700 text-[10px] ml-1 transition-colors"
                                                                title="Editar precio"
                                                            >
                                                                <i class="fas fa-pencil-alt"></i>
                                                            </button>
                                                        </template>
                                                        
                                                        <button
                                                            @click="eliminarCliente(producto.IdProducto, cliente.IdIdentificador)"
                                                            class="text-red-500 hover:text-red-700 text-[10px] ml-1 flex-shrink-0 transition-colors"
                                                            title="Eliminar precio"
                                                        >
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>

                                                    <!-- MENSAJE CUANDO NO HAY CLIENTES CON LA BÚSQUEDA -->
                                                    <div 
                                                        v-if="getClientesProducto(producto).length === 0 && Object.keys(producto.precios || {}).length > 0"
                                                        class="text-center py-3 text-gray-400 text-xs"
                                                    >
                                                        <i class="fas fa-search mr-1"></i>
                                                        No se encontraron clientes con esa búsqueda
                                                    </div>

                                                    <!-- Formulario para agregar nuevo cliente -->
                                                    <div
                                                        v-if="nuevoClienteForm[producto.IdProducto]"
                                                        class="flex flex-wrap items-center gap-2 py-2 mt-2 border-t border-primary-200 pt-2"
                                                    >
                                                        <div class="relative" style="width: 280px;">
                                                            <input
                                                                type="text"
                                                                v-model="nuevoClienteForm[producto.IdProducto].busqueda"
                                                                class="w-full border rounded-md px-2 py-1 text-xs"
                                                                :class="{ 'border-red-500': errorNuevo[producto.IdProducto] }"
                                                                placeholder="Buscar operador por CI/NIT o nombre..."
                                                                @focus="nuevoClienteForm[producto.IdProducto].busqueda = ''"
                                                            />
                                                            <div
                                                                v-if="nuevoClienteForm[producto.IdProducto].busqueda && clientesFiltradosParaProducto(producto.IdProducto).length"
                                                                class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-32 overflow-y-auto text-xs"
                                                            >
                                                                <div
                                                                    v-for="item in clientesFiltradosParaProducto(producto.IdProducto)"
                                                                    :key="item.IdIdentificador"
                                                                    @click="seleccionarClienteParaProducto(producto.IdProducto, item)"
                                                                    class="px-2 py-1 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 truncate"
                                                                >
                                                                    <span class="font-mono">{{ item.CI_NIT }}</span> - {{ item.Nombre }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <span class="text-gray-400 text-[10px]">Bs.</span>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            v-model="nuevoClienteForm[producto.IdProducto].precio"
                                                            @keydown="prevenirFlechas"
                                                            class="no-spinner w-20 sm:w-24 border rounded px-2 py-1 text-xs text-center"
                                                            placeholder="0.00"
                                                            @keyup.enter="guardarNuevoCliente(producto.IdProducto)"
                                                        />
                                                        <button
                                                            @click="guardarNuevoCliente(producto.IdProducto)"
                                                            :disabled="guardandoNuevo[producto.IdProducto]"
                                                            class="px-2 py-1 bg-primary-600 text-white rounded text-[10px] hover:bg-primary-700 disabled:opacity-50 flex items-center gap-1 whitespace-nowrap transition-colors"
                                                        >
                                                            <i v-if="guardandoNuevo[producto.IdProducto]" class="fas fa-spinner fa-spin text-[10px]"></i>
                                                            <i v-else class="fas fa-save text-[10px]"></i>
                                                            Guardar
                                                        </button>
                                                        <button
                                                            @click="cancelarNuevoCliente(producto.IdProducto)"
                                                            class="text-gray-500 hover:text-gray-700 text-[10px]"
                                                        >
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                        <span v-if="errorNuevo[producto.IdProducto]" class="text-red-500 text-[10px] w-full">
                                                            {{ errorNuevo[producto.IdProducto] }}
                                                        </span>
                                                    </div>

                                                    <!-- Botón "Agregar Cliente" dentro de sub-fila -->
                                                    <div v-if="!nuevoClienteForm[producto.IdProducto]" class="py-1">
                                                        <button
                                                            @click="iniciarNuevoCliente(producto.IdProducto)"
                                                            class="text-primary-600 hover:text-primary-800 text-[10px] flex items-center gap-1"
                                                        >
                                                            <i class="fas fa-plus-circle"></i>
                                                            Agregar otro operador
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Versión Mobile -->
                        <div class="md:hidden">
                            <div
                                v-for="producto in productosPaginados"
                                :key="producto.IdProducto"
                                class="border-b last:border-b-0"
                            >
                                <div class="p-3 bg-white hover:bg-gray-50 cursor-pointer" @click="toggleExpandir(producto.IdProducto)">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2 flex-1 min-w-0">
                                            <i :class="estaExpandido(producto.IdProducto) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-gray-400 text-xs flex-shrink-0"></i>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-mono text-gray-500">{{ producto.Codigo }}</div>
                                                <div class="text-sm font-medium text-gray-800 truncate">{{ producto.Descripcion }}</div>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 ml-2 flex items-center gap-2">
                                            <span class="text-[10px] text-gray-400">
                                                {{ producto.precios ? Object.keys(producto.precios).length : 0 }} clientes
                                            </span>
                                            <button
                                                @click.stop="iniciarNuevoCliente(producto.IdProducto)"
                                                class="px-2 py-0.5 bg-green-600 text-white rounded text-[9px] hover:bg-green-700 flex items-center gap-1"
                                            >
                                                <i class="fas fa-plus text-[8px]"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="estaExpandido(producto.IdProducto)" class="bg-gray-50 p-3 border-t border-gray-200">
                                    
                                    <!-- ✅ BUSCADOR DE CLIENTES (Mobile) -->
                                    <div class="mb-3 flex items-center gap-2">
                                        <div class="relative flex-1">
                                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[9px]"></i>
                                            <input
                                                type="text"
                                                :placeholder="'Buscar cliente...'"
                                                v-model="busquedaClientePorProducto[producto.IdProducto]"
                                                class="w-full border border-gray-200 rounded-md pl-7 pr-3 py-1 text-xs focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none transition bg-white"
                                            />
                                        </div>
                                        <span class="text-[8px] text-gray-400 whitespace-nowrap">
                                            {{ getClientesProducto(producto).length }}
                                        </span>
                                    </div>

                                    <div
                                        v-for="cliente in getClientesProducto(producto)"
                                        :key="cliente.IdIdentificador"
                                        class="flex flex-wrap items-center gap-2 py-1.5 border-b border-dashed border-gray-200 last:border-b-0"
                                    >
                                        <span 
                                            class="text-gray-600 text-xs truncate"
                                            style="width: 140px; display: inline-block;"
                                            :title="cliente.Nombre"
                                        >
                                            {{ cliente.Nombre }}
                                        </span>
                                        <span class="text-gray-400 text-[10px]">Bs.</span>
                                        
                                        <template v-if="modoEdicion[getClaveEdicion(producto.IdProducto, cliente.IdIdentificador)]">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                v-model="precioEditando[getClaveEdicion(producto.IdProducto, cliente.IdIdentificador)]"
                                                @keydown="prevenirFlechas"
                                                @keyup="(e) => manejarEnter(producto.IdProducto, cliente.IdIdentificador, e)"
                                                @blur="manejarBlur(producto.IdProducto, cliente.IdIdentificador)"
                                                class="no-spinner w-16 border-2 border-primary-400 bg-primary-50 rounded px-1 py-0.5 text-xs text-center focus:outline-none focus:ring-2 focus:ring-primary-300"
                                                autofocus
                                            />
                                        </template>
                                        <template v-else>
                                            <span class="font-medium text-gray-800 w-12 text-center text-xs">
                                                {{ Number(cliente.Precio).toFixed(2) }}
                                            </span>
                                            <button
                                                @click="activarEdicion(producto.IdProducto, cliente.IdIdentificador, cliente.Precio)"
                                                class="text-primary-500 hover:text-primary-700 text-[10px]"
                                                title="Editar precio"
                                            >
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                        </template>
                                        
                                        <button
                                            @click="eliminarCliente(producto.IdProducto, cliente.IdIdentificador)"
                                            class="text-red-500 hover:text-red-700 text-[10px] flex-shrink-0"
                                        >
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>

                                    <div 
                                        v-if="getClientesProducto(producto).length === 0 && Object.keys(producto.precios || {}).length > 0"
                                        class="text-center py-2 text-gray-400 text-[10px]"
                                    >
                                        <i class="fas fa-search mr-1"></i>
                                        No se encontraron clientes
                                    </div>

                                    <!-- Formulario agregar cliente (mobile) -->
                                    <div
                                        v-if="nuevoClienteForm[producto.IdProducto]"
                                        class="flex flex-wrap items-center gap-2 py-2 mt-2 border-t border-primary-200 pt-2"
                                    >
                                        <div class="relative" style="width: 140px; max-width: 100%;">
                                            <input
                                                type="text"
                                                v-model="nuevoClienteForm[producto.IdProducto].busqueda"
                                                class="w-full border rounded-md px-2 py-1 text-xs"
                                                :class="{ 'border-red-500': errorNuevo[producto.IdProducto] }"
                                                placeholder="Buscar operador..."
                                                @focus="nuevoClienteForm[producto.IdProducto].busqueda = ''"
                                            />
                                            <div
                                                v-if="nuevoClienteForm[producto.IdProducto].busqueda && clientesFiltradosParaProducto(producto.IdProducto).length"
                                                class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-32 overflow-y-auto text-xs"
                                            >
                                                <div
                                                    v-for="item in clientesFiltradosParaProducto(producto.IdProducto)"
                                                    :key="item.IdIdentificador"
                                                    @click="seleccionarClienteParaProducto(producto.IdProducto, item)"
                                                    class="px-2 py-1 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 truncate"
                                                >
                                                    <span class="font-mono">{{ item.CI_NIT }}</span> - {{ item.Nombre }}
                                                </div>
                                            </div>
                                        </div>
                                        <span class="text-gray-400 text-[10px]">Bs.</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            v-model="nuevoClienteForm[producto.IdProducto].precio"
                                            @keydown="prevenirFlechas"
                                            class="no-spinner w-16 border rounded px-1 py-0.5 text-xs text-center"
                                            placeholder="0"
                                            @keyup.enter="guardarNuevoCliente(producto.IdProducto)"
                                        />
                                        <button
                                            @click="guardarNuevoCliente(producto.IdProducto)"
                                            :disabled="guardandoNuevo[producto.IdProducto]"
                                            class="px-2 py-1 bg-primary-600 text-white rounded text-[10px] hover:bg-primary-700 disabled:opacity-50 flex items-center gap-1 transition-colors"
                                        >
                                            <i v-if="guardandoNuevo[producto.IdProducto]" class="fas fa-spinner fa-spin text-[10px]"></i>
                                            <i v-else class="fas fa-save text-[10px]"></i>
                                            Guardar
                                        </button>
                                        <button
                                            @click="cancelarNuevoCliente(producto.IdProducto)"
                                            class="text-gray-500 hover:text-gray-700 text-[10px]"
                                        >
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <span v-if="errorNuevo[producto.IdProducto]" class="text-red-500 text-[10px] w-full">
                                            {{ errorNuevo[producto.IdProducto] }}
                                        </span>
                                    </div>

                                    <div v-if="!nuevoClienteForm[producto.IdProducto]" class="pt-1">
                                        <button
                                            @click="iniciarNuevoCliente(producto.IdProducto)"
                                            class="text-primary-600 hover:text-primary-800 text-[10px] flex items-center gap-1"
                                        >
                                            <i class="fas fa-plus-circle"></i>
                                            Agregar operador
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Paginación -->
                        <div class="bg-gray-50 px-3 sm:px-4 py-2 border-t flex flex-col sm:flex-row items-center justify-between gap-2">
                            <div class="text-[10px] text-gray-500">
                                Mostrando {{ productosPaginados.length }} de {{ productosFiltrados.length }} productos
                            </div>
                            <div class="flex gap-1">
                                <button
                                    @click="paginaActual > 1 && paginaActual--"
                                    :disabled="paginaActual <= 1"
                                    class="px-2 py-1 border rounded text-[10px] hover:bg-gray-100 disabled:opacity-50"
                                >
                                    Anterior
                                </button>
                                <span class="px-2 py-1 text-[10px]">Pág. {{ paginaActual }} de {{ totalPaginas }}</span>
                                <button
                                    @click="paginaActual < totalPaginas && paginaActual++"
                                    :disabled="paginaActual >= totalPaginas"
                                    class="px-2 py-1 border rounded text-[10px] hover:bg-gray-100 disabled:opacity-50"
                                >
                                    Siguiente
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
.animate-pulse {
    animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Estilos para el scroll del autocomplete */
.max-h-32 {
    max-height: 8rem;
}
</style>