<script setup>
import { ref, onMounted, onUnmounted, inject } from 'vue'
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
    sucursalId: {
        type: Number,
        default: null
    },
    sucursalTexto: {
        type: String,
        default: ''
    },
    fechaHoraServidor: {
        type: String,
        default: null
    },
    fechaActual: {
        type: String,
        default: null
    },
    horaActual: {
        type: String,
        default: null
    },
    horaLimiteExtra: {
        type: String,
        default: '07:00'
    },
    operadorId: {
        type: Number,
        default: null
    },
    operadorNombre: {
        type: String,
        default: ''
    }
})

// Estado del formulario
const formData = ref({
    id: null,
    FechaDelPedido: props.fechaActual || '',
    IdProducto: null,
    Unidades: 1,
})

// Búsqueda de producto
const busquedaProducto = ref('')
const mostrandoListaProducto = ref(false)
const productosFiltrados = ref([])

const guardando = ref(false)
const editando = ref(false)
const errors = ref({})

// Validación de hora
const validandoHora = ref(false)
const horaValida = ref(true)
const mensajeHora = ref('')

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
}

const limpiarSeleccionProducto = () => {
    formData.value.IdProducto = null
    busquedaProducto.value = ''
}

// ==================== VALIDAR HORA ====================
const validarHora = async () => {
    validandoHora.value = true
    try {
        const response = await axios.post('/operacion/pedidos/pedidos-extraordinarios-sucursal/api/validar-hora')
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
    if (!horaValida.value) {
        toast?.warning('Validación', mensajeHora.value)
        return
    }
    
    guardando.value = true
    try {
        const response = await axios.post('/operacion/pedidos/pedidos-extraordinarios-sucursal', formData.value)
        
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
        const response = await axios.get(`/operacion/pedidos/pedidos-extraordinarios-sucursal/${id}/editar`)
        
        if (response.data.success) {
            const data = response.data.data
            formData.value = {
                id: data.id,
                FechaDelPedido: data.FechaDelPedido,
                IdProducto: data.IdProducto,
                Unidades: data.Unidades,
            }
            busquedaProducto.value = data.producto_texto || ''
            editando.value = true
            
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
        FechaDelPedido: props.fechaActual || '',
        IdProducto: null,
        Unidades: 1,
    }
    busquedaProducto.value = ''
    editando.value = false
    errors.value = {}
    productosFiltrados.value = [...props.productos]
    validarHora()
}

const eliminarPedido = async (id) => {
    if (!confirm('¿Estás seguro de eliminar este pedido?')) return
    
    try {
        const response = await axios.delete(`/operacion/pedidos/pedidos-extraordinarios-sucursal/${id}`)
        
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

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

// Cerrar dropdown al hacer click fuera
const handleClickOutside = (event) => {
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
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-red-100 rounded-2xl mb-3">
                        <i class="fas fa-clock text-xl text-red-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Pedido Extraordinario</h1>
                    <p class="text-xs text-gray-500">Registre pedidos extraordinarios ANTES de las 07:00 AM</p>
                    
                    <!-- Información de sucursal logueada -->
                    <div class="mt-2 inline-flex items-center gap-2 bg-blue-50 px-4 py-1.5 rounded-full">
                        <i class="fas fa-store text-blue-600 text-xs"></i>
                        <span class="text-sm font-medium text-blue-700">{{ sucursalTexto }}</span>
                    </div>
                    
                    <div class="mt-2 text-xs">
                        <span class="inline-flex items-center gap-2 px-3 py-1 bg-red-100 text-red-700 rounded-full">
                            <i class="fas fa-hourglass-end"></i>
                            Hora límite: <strong>{{ horaLimiteExtra }}</strong> | 
                            Hora actual: <strong>{{ horaActual }}</strong>
                            <span v-if="horaValida" class="text-green-600">
                                <i class="fas fa-check-circle"></i> Permitido
                            </span>
                            <span v-else class="text-red-600">
                                <i class="fas fa-times-circle"></i> No permitido
                            </span>
                        </span>
                    </div>
                </div>

                <!-- FORMULARIO -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 formulario-container">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-pencil-alt text-red-500 mr-2"></i>
                            {{ editando ? 'Editar Pedido' : 'Nuevo Pedido Extraordinario' }}
                        </h2>
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-user mr-1"></i>
                            Operador: <strong>{{ operadorNombre || operadorId }}</strong>
                        </div>
                    </div>

                    <!-- Estado de validación de hora -->
                    <div v-if="!horaValida" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center gap-2 text-red-700">
                            <i class="fas fa-exclamation-circle"></i>
                            <span class="text-sm font-medium">{{ mensajeHora }}</span>
                        </div>
                    </div>
                    <div v-else-if="horaValida" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center gap-2 text-green-700">
                            <i class="fas fa-check-circle"></i>
                            <span class="text-sm font-medium">Puede realizar pedidos extraordinarios</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Sucursal (SOLO LECTURA) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Sucursal
                            </label>
                            <div class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-600">
                                <i class="fas fa-store mr-2 text-gray-400"></i>
                                {{ sucursalTexto }}
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Sucursal actual del operador</p>
                        </div>

                        <!-- Fecha Realiza -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Fecha Realiza
                            </label>
                            <div class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-600">
                                <i class="fas fa-calendar-alt mr-2 text-gray-400"></i>
                                {{ fechaHoraServidor ? new Date(fechaHoraServidor).toLocaleString('es-BO') : '-' }}
                            </div>
                        </div>

                        <!-- Fecha del Pedido (solo lectura - día actual) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Fecha del Pedido
                            </label>
                            <div class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-600">
                                <i class="fas fa-calendar-day mr-2 text-gray-400"></i>
                                {{ formData.FechaDelPedido ? new Date(formData.FechaDelPedido).toLocaleDateString('es-BO') : '-' }}
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Solo se permite pedidos para el día de hoy</p>
                        </div>

                        <!-- Producto -->
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
                                
                                <div 
                                    v-if="mostrandoListaProducto && productosFiltrados.length > 0"
                                    class="absolute z-50 mt-1 bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto w-full"
                                >
                                    <div 
                                        v-for="producto in productosFiltrados"
                                        :key="producto.id"
                                        @click="seleccionarProducto(producto)"
                                        class="px-3 py-2 hover:bg-red-50 cursor-pointer border-b last:border-b-0"
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
                            :disabled="guardando || !horaValida"
                            class="px-6 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ guardando ? 'Guardando...' : (editando ? 'Actualizar' : 'Guardar') }}
                        </button>
                    </div>
                </div>

                <!-- LISTA DE PEDIDOS DEL DÍA -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">
                            <i class="fas fa-list text-red-500 mr-2"></i>
                            Mis Pedidos de Hoy
                        </h3>
                        <span class="text-xs text-gray-500">{{ pedidos.length }} pedido(s)</span>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Unidades</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(pedido, idx) in pedidos" :key="pedido.IdPedidos" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ idx + 1 }}</td>
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
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">
                                        <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                        No tienes pedidos registrados para hoy
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 bg-red-50 rounded-lg text-xs text-red-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Pedido Extraordinario:</strong>
                    <ul class="list-disc list-inside mt-1 space-y-0.5">
                        <li>Solo se permite hasta las <strong class="text-red-800">07:00 AM</strong></li>
                        <li>Los pedidos son para el <strong>día de hoy</strong> (no días futuros)</li>
                        <li>La <strong>sucursal</strong> es la del operador logueado</li>
                        <li>El <strong>operador</strong> se asigna automáticamente</li>
                        <li><strong>No valida cronograma</strong> de producción</li>
                        <li>Las <strong>UnidadesAutoriza</strong> se asignan automáticamente</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>