<script setup>
import { ref, onMounted, onUnmounted, computed, inject } from 'vue'  // ✅ AGREGADO onUnmounted
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    pedidos: {
        type: Array,
        default: () => []
    },
    productos: {
        type: Array,
        default: () => []
    },
    sucursales: {
        type: Array,
        default: () => []
    },
    tiposPedido: {
        type: Array,
        default: () => []
    },
    fechaHoraServidor: {
        type: String,
        default: null
    },
    horaLimite: {
        type: Number,
        default: null
    },
    horaLimiteExtra: {
        type: Number,
        default: null
    },
    operadorId: {
        type: Number,
        default: null
    },
    operadorNombre: {
        type: String,
        default: ''
    },
    sucursalDefault: {
        type: Number,
        default: null
    }
})

// Estado del formulario
const formData = ref({
    id: null,
    IdSucursal: props.sucursalDefault || '',
    FechaDelPedido: '',
    IdProducto: null,
    Unidades: 1,
})

// ✅ BÚSQUEDA DE SUCURSAL (autocompletado)
const busquedaSucursal = ref('')
const mostrandoListaSucursal = ref(false)
const sucursalesFiltradas = ref([])

// Búsqueda de producto
const busquedaProducto = ref('')
const mostrandoListaProducto = ref(false)
const productosFiltrados = ref([])

const guardando = ref(false)
const editando = ref(false)
const errors = ref({})

// Validaciones
const validandoProducto = ref(false)
const productoValido = ref(false)
const mensajeProducto = ref('')

const validandoHora = ref(false)
const horaValida = ref(true)
const mensajeHora = ref('')

// Fecha mínima (mañana)
const fechaMinima = computed(() => {
    const hoy = new Date()
    const manana = new Date(hoy)
    manana.setDate(hoy.getDate() + 1)
    return manana.toISOString().split('T')[0]
})

// ==================== FILTROS SUCURSAL ====================
const filtrarSucursales = (termino) => {
    if (!termino || termino.trim() === '') {
        sucursalesFiltradas.value = [...props.sucursales]
        return
    }
    const terminoLower = termino.toLowerCase().trim()
    sucursalesFiltradas.value = props.sucursales.filter(s => 
        (s.texto || '').toLowerCase().includes(terminoLower)
    )
}

const onBuscarSucursal = (event) => {
    const termino = event.target.value
    busquedaSucursal.value = termino
    filtrarSucursales(termino)
    mostrandoListaSucursal.value = termino.length >= 1 && sucursalesFiltradas.value.length > 0
}

const onFocusSucursal = () => {
    const termino = busquedaSucursal.value || ''
    if (termino.length >= 1) {
        filtrarSucursales(termino)
        mostrandoListaSucursal.value = sucursalesFiltradas.value.length > 0
    } else {
        sucursalesFiltradas.value = [...props.sucursales]
        mostrandoListaSucursal.value = true
    }
}

const seleccionarSucursal = (sucursal) => {
    formData.value.IdSucursal = sucursal.id
    busquedaSucursal.value = sucursal.texto
    mostrandoListaSucursal.value = false
}

const limpiarSeleccionSucursal = () => {
    formData.value.IdSucursal = null
    busquedaSucursal.value = ''
}

// ==================== FILTROS PRODUCTO ====================
const filtrarProductos = (termino) => {
    if (!termino || termino.trim() === '') {
        productosFiltrados.value = [...props.productos]
        return
    }
    const terminoLower = termino.toLowerCase().trim()
    productosFiltrados.value = props.productos.filter(p => 
        (p.codigo || '').toLowerCase().includes(terminoLower) ||
        (p.descripcion || '').toLowerCase().includes(terminoLower) ||
        (p.texto || '').toLowerCase().includes(terminoLower)
    )
}

const onBuscarProducto = (event) => {
    const termino = event.target.value
    busquedaProducto.value = termino
    filtrarProductos(termino)
    mostrandoListaProducto.value = termino.length >= 1 && productosFiltrados.value.length > 0
}

const onFocusProducto = () => {
    const termino = busquedaProducto.value || ''
    if (termino.length >= 1) {
        filtrarProductos(termino)
        mostrandoListaProducto.value = productosFiltrados.value.length > 0
    } else {
        productosFiltrados.value = [...props.productos]
        mostrandoListaProducto.value = true
    }
}

const seleccionarProducto = (producto) => {
    formData.value.IdProducto = producto.id
    busquedaProducto.value = producto.texto
    mostrandoListaProducto.value = false
    validarProducto()
}

const limpiarSeleccionProducto = () => {
    formData.value.IdProducto = null
    busquedaProducto.value = ''
    productoValido.value = false
    mensajeProducto.value = ''
}

// ==================== VALIDACIONES ====================
const validarProducto = async () => {
    if (!formData.value.IdProducto || !formData.value.FechaDelPedido) {
        productoValido.value = false
        return
    }
    
    validandoProducto.value = true
    try {
        const response = await axios.post('/operacion/pedidos/pedidos-extraordinarios/api/validar-producto', {
            IdProducto: formData.value.IdProducto,
            FechaDelPedido: formData.value.FechaDelPedido
        })
        productoValido.value = response.data.valido
        mensajeProducto.value = response.data.mensaje
    } catch (error) {
        productoValido.value = false
        mensajeProducto.value = 'Error al validar producto'
    } finally {
        validandoProducto.value = false
    }
}

const validarHoraLimite = async () => {
    if (!formData.value.FechaDelPedido) {
        horaValida.value = true
        return
    }
    
    validandoHora.value = true
    try {
        const response = await axios.post('/operacion/pedidos/pedidos-extraordinarios/api/validar-hora-limite', {
            FechaDelPedido: formData.value.FechaDelPedido
        })
        horaValida.value = response.data.valido
        mensajeHora.value = response.data.mensaje
    } catch (error) {
        horaValida.value = true
    } finally {
        validandoHora.value = false
    }
}

// ==================== CRUD ====================
const guardar = async () => {
    errors.value = {}
    
    if (!formData.value.IdSucursal) {
        toast?.warning('Validación', 'Seleccione una sucursal')
        return
    }
    if (!formData.value.FechaDelPedido) {
        toast?.warning('Validación', 'Complete la fecha del pedido')
        return
    }
    if (!formData.value.IdProducto) {
        toast?.warning('Validación', 'Seleccione un producto')
        return
    }
    if (formData.value.Unidades <= 0) {
        toast?.warning('Validación', '¡Las unidades deben ser mayor a cero...!')
        return
    }
    if (!productoValido.value) {
        toast?.warning('Validación', mensajeProducto.value || 'Producto no válido para esta fecha')
        return
    }
    if (!horaValida.value) {
        toast?.warning('Validación', mensajeHora.value)
        return
    }
    
    guardando.value = true
    try {
        const response = await axios.post('/operacion/pedidos/pedidos-extraordinarios', formData.value)
        
        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            resetForm()
            setTimeout(() => {
                router.reload()
            }, 1000)
        }
    } catch (error) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            toast?.error('Error de validación', Object.values(errors.value).join(', '))
        } else {
            toast?.error('Error', error.response?.data?.message || 'Error al guardar')
        }
    } finally {
        guardando.value = false
    }
}

const editarPedido = async (id) => {
    try {
        const response = await axios.get(`/operacion/pedidos/pedidos-extraordinarios/${id}/editar`)
        
        if (response.data.success) {
            const data = response.data.data
            formData.value = {
                id: data.id,
                IdSucursal: data.IdSucursal,
                FechaDelPedido: data.FechaDelPedido,
                IdProducto: data.IdProducto,
                Unidades: data.Unidades,
            }
            
            // ✅ Cargar texto de sucursal en el buscador
            const sucursal = props.sucursales.find(s => s.id === data.IdSucursal)
            busquedaSucursal.value = sucursal ? sucursal.texto : ''
            
            // ✅ Cargar texto de producto en el buscador
            busquedaProducto.value = data.producto_texto || ''
            editando.value = true
            
            await validarProducto()
            await validarHoraLimite()
            
            document.querySelector('.formulario-container')?.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            })
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al cargar el pedido')
    }
}

const resetForm = () => {
    formData.value = {
        id: null,
        IdSucursal: props.sucursalDefault || '',
        FechaDelPedido: fechaMinima.value,
        IdProducto: null,
        Unidades: 1,
    }
    busquedaSucursal.value = ''
    busquedaProducto.value = ''
    editando.value = false
    productoValido.value = false
    mensajeProducto.value = ''
    horaValida.value = true
    mensajeHora.value = ''
    errors.value = {}
    sucursalesFiltradas.value = [...props.sucursales]
    productosFiltrados.value = [...props.productos]
    
    // ✅ Si hay sucursal default, cargar su texto
    if (props.sucursalDefault) {
        const sucursal = props.sucursales.find(s => s.id === props.sucursalDefault)
        if (sucursal) {
            busquedaSucursal.value = sucursal.texto
        }
    }
}

const eliminarPedido = async (id) => {
    if (!confirm('¿Estás seguro de eliminar este pedido?')) return
    
    try {
        const response = await axios.delete(`/operacion/pedidos/pedidos-extraordinarios/${id}`)
        
        if (response.data.success) {
            toast?.success('Eliminado', response.data.message)
            setTimeout(() => {
                router.reload()
            }, 1000)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al eliminar')
    }
}

// Formatear fecha
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

// Cerrar dropdowns al hacer click fuera
const handleClickOutside = (event) => {
    const containerSucursal = document.querySelector('.dropdown-sucursal-container')
    if (containerSucursal && !containerSucursal.contains(event.target)) {
        mostrandoListaSucursal.value = false
    }
    
    const containerProducto = document.querySelector('.dropdown-producto-container')
    if (containerProducto && !containerProducto.contains(event.target)) {
        mostrandoListaProducto.value = false
    }
}

onMounted(() => {
    resetForm()
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-4 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-orange-100 rounded-2xl mb-3">
                        <i class="fas fa-exclamation-triangle text-xl text-orange-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Pedido Extraordinario</h1>
                    <p class="text-xs text-gray-500">Registre pedidos fuera del horario normal (+2 horas de tolerancia)</p>
                    <div v-if="horaLimite !== null" class="mt-2 text-xs">
                        <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-100 text-blue-700 rounded-full">
                            <i class="fas fa-clock"></i>
                            Hora límite normal: <strong>{{ horaLimite }}:00</strong> | 
                            Extraordinario: <strong class="text-orange-600">{{ horaLimiteExtra }}:00</strong>
                        </span>
                    </div>
                </div>

                <!-- FORMULARIO -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 formulario-container">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-pencil-alt text-orange-500 mr-2"></i>
                            {{ editando ? 'Editar Pedido' : 'Nuevo Pedido Extraordinario' }}
                        </h2>
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-user mr-1"></i>
                            Operador: <strong>{{ operadorNombre || operadorId }}</strong>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- ✅ SUCURSAL CON AUTOCOMPLETADO -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Sucursal *
                            </label>
                            <div class="relative dropdown-sucursal-container">
                                <div class="relative">
                                    <i class="fas fa-store absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                                    <input 
                                        type="text"
                                        :value="busquedaSucursal"
                                        @input="onBuscarSucursal"
                                        @focus="onFocusSucursal"
                                        placeholder="Buscar sucursal..."
                                        class="w-full border rounded-lg pl-9 pr-8 py-2 text-sm"
                                        :class="{ 'border-red-500': errors.IdSucursal }"
                                    />
                                    <button 
                                        v-if="formData.IdSucursal"
                                        @click="limpiarSeleccionSucursal"
                                        class="absolute right-2 top-2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <!-- Dropdown de sucursales -->
                                <div 
                                    v-if="mostrandoListaSucursal && sucursalesFiltradas.length > 0"
                                    class="absolute z-50 mt-1 bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto w-full"
                                >
                                    <div 
                                        v-for="sucursal in sucursalesFiltradas"
                                        :key="sucursal.id"
                                        @click="seleccionarSucursal(sucursal)"
                                        class="px-3 py-2 hover:bg-orange-50 cursor-pointer border-b last:border-b-0"
                                    >
                                        <span class="text-sm">{{ sucursal.texto }}</span>
                                    </div>
                                </div>

                                <div 
                                    v-if="mostrandoListaSucursal && busquedaSucursal && busquedaSucursal.length >= 1 && sucursalesFiltradas.length === 0"
                                    class="absolute z-50 mt-1 bg-white border rounded-lg shadow-lg p-3 text-center text-gray-400 text-sm w-full"
                                >
                                    No se encontraron sucursales
                                </div>
                                <p v-if="errors.IdSucursal" class="text-xs text-red-500 mt-1">
                                    {{ errors.IdSucursal }}
                                </p>
                            </div>
                        </div>

                        <!-- Fecha Realiza (Solo lectura) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Fecha Realiza
                            </label>
                            <div class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-600">
                                <i class="fas fa-calendar-alt mr-2 text-gray-400"></i>
                                {{ fechaHoraServidor ? new Date(fechaHoraServidor).toLocaleString('es-BO') : '-' }}
                            </div>
                        </div>

                        <!-- Fecha del Pedido -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Fecha para la Producción *
                            </label>
                            <input 
                                type="date" 
                                v-model="formData.FechaDelPedido"
                                :min="fechaMinima"
                                @change="() => { validarProducto(); validarHoraLimite() }"
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.FechaDelPedido }"
                            />
                            <p v-if="errors.FechaDelPedido" class="text-xs text-red-500 mt-1">
                                {{ errors.FechaDelPedido }}
                            </p>
                        </div>

                        <!-- ✅ PRODUCTO CON AUTOCOMPLETADO -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Producto *
                            </label>
                            <div class="relative dropdown-producto-container">
                                <div class="relative">
                                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                                    <input 
                                        type="text"
                                        :value="busquedaProducto"
                                        @input="onBuscarProducto"
                                        @focus="onFocusProducto"
                                        placeholder="Buscar producto..."
                                        class="w-full border rounded-lg pl-9 pr-8 py-2 text-sm"
                                        :class="{ 'border-red-500': errors.IdProducto }"
                                    />
                                    <button 
                                        v-if="formData.IdProducto"
                                        @click="limpiarSeleccionProducto"
                                        class="absolute right-2 top-2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <!-- Dropdown de productos -->
                                <div 
                                    v-if="mostrandoListaProducto && productosFiltrados.length > 0"
                                    class="absolute z-50 mt-1 bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto w-full"
                                >
                                    <div 
                                        v-for="producto in productosFiltrados"
                                        :key="producto.id"
                                        @click="seleccionarProducto(producto)"
                                        class="px-3 py-2 hover:bg-orange-50 cursor-pointer border-b last:border-b-0"
                                    >
                                        <span class="font-mono text-xs text-gray-500">{{ producto.codigo }}</span>
                                        <span class="ml-2 text-sm">{{ producto.descripcion }}</span>
                                    </div>
                                </div>

                                <div 
                                    v-if="mostrandoListaProducto && busquedaProducto && busquedaProducto.length >= 1 && productosFiltrados.length === 0"
                                    class="absolute z-50 mt-1 bg-white border rounded-lg shadow-lg p-3 text-center text-gray-400 text-sm w-full"
                                >
                                    No se encontraron productos
                                </div>

                                <div v-if="!productoValido && mensajeProducto" class="text-xs text-red-500 mt-1">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ mensajeProducto }}
                                </div>
                                <p v-if="errors.IdProducto" class="text-xs text-red-500 mt-1">
                                    {{ errors.IdProducto }}
                                </p>
                            </div>
                        </div>

                        <!-- Unidades -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Unidades *
                            </label>
                            <input 
                                type="number"
                                v-model.number="formData.Unidades"
                                step="any"
                                min="0.01"
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.Unidades }"
                            />
                            <p v-if="errors.Unidades" class="text-xs text-red-500 mt-1">
                                {{ errors.Unidades }}
                            </p>
                        </div>

                        <!-- Estado de validación -->
                        <div class="flex items-end">
                            <div v-if="formData.FechaDelPedido && formData.IdProducto" class="flex flex-col gap-1">
                                <div v-if="validandoProducto" class="text-xs text-gray-400">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Validando producto...
                                </div>
                                <div v-else-if="productoValido" class="text-xs text-green-600">
                                    <i class="fas fa-check-circle mr-1"></i> Producto válido para esta fecha
                                </div>
                                <div v-else-if="mensajeProducto" class="text-xs text-red-500">
                                    <i class="fas fa-exclamation-circle mr-1"></i> {{ mensajeProducto }}
                                </div>
                                
                                <div v-if="validandoHora" class="text-xs text-gray-400">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Validando hora...
                                </div>
                                <div v-else-if="!horaValida" class="text-xs text-red-500">
                                    <i class="fas fa-exclamation-circle mr-1"></i> {{ mensajeHora }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                        <button 
                            @click="resetForm"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300"
                            :disabled="guardando"
                        >
                            <i class="fas fa-undo mr-1"></i> Limpiar
                        </button>
                        <button 
                            @click="guardar"
                            :disabled="guardando || !productoValido || !horaValida || !formData.IdSucursal"
                            class="px-6 py-2 bg-orange-600 text-white rounded-lg text-sm hover:bg-orange-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ guardando ? 'Guardando...' : (editando ? 'Actualizar' : 'Guardar') }}
                        </button>
                    </div>
                </div>

                <!-- LISTA DE PEDIDOS EXISTENTES -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">
                            <i class="fas fa-list text-orange-500 mr-2"></i>
                            Mis Pedidos Futuros
                        </h3>
                        <span class="text-xs text-gray-500">{{ pedidos.length }} pedido(s)</span>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sucursal</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Unidades</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(pedido, idx) in pedidos" :key="pedido.IdPedidos" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ idx + 1 }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ pedido.sucursal ? pedido.sucursal.NumeroSucursal + ' - ' + pedido.sucursal.Nombre : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ formatearFecha(pedido.FechaDelPedido) }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ pedido.producto ? pedido.producto.Codigo + ' - ' + pedido.producto.Descripcion : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm font-mono font-bold">{{ pedido.Unidades }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex justify-center gap-2">
                                            <button 
                                                @click="editarPedido(pedido.IdPedidos)"
                                                class="text-amber-600 hover:text-amber-700"
                                                title="Editar"
                                            >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button 
                                                @click="eliminarPedido(pedido.IdPedidos)"
                                                class="text-red-500 hover:text-red-700"
                                                title="Eliminar"
                                            >
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="pedidos.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                        <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                        No tienes pedidos futuros registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 bg-orange-50 rounded-lg text-xs text-orange-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Pedido Extraordinario:</strong>
                    <ul class="list-disc list-inside mt-1 space-y-0.5">
                        <li>Los pedidos extraordinarios tienen <strong class="text-orange-800">+2 horas</strong> de tolerancia</li>
                        <li>Busca y selecciona la <strong>sucursal</strong> a la que pertenece el pedido</li>
                        <li>El <strong>operador</strong> se asigna automáticamente (el que está logueado)</li>
                        <li>Solo se pueden registrar pedidos con fecha <strong>futura</strong></li>
                        <li>El producto debe estar en el <strong>cronograma de producción</strong> para el día seleccionado</li>
                        <li>Los pedidos con fecha pasada <strong>no se pueden editar ni eliminar</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>