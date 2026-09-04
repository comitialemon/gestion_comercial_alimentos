<script setup>
import { ref, onMounted, onUnmounted, computed, inject } from 'vue'
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

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
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

// ==================== FUNCIONES ====================
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
        toast?.warning('Validación', 'Las unidades deben ser mayor a cero')
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

// Cerrar dropdown
const handleClickOutside = (event) => {
    const containerProducto = document.querySelector('.dropdown-producto-container')
    if (containerProducto && !containerProducto.contains(event.target)) {
        mostrandoListaProducto.value = false
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    resetForm()
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-red-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-clock text-red-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Pedido Extraordinario</h1>
                        <p class="text-xs text-gray-500">Registre pedidos extraordinarios ANTES de las 07:00 AM</p>
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-blue-50 text-blue-700 rounded-full border border-blue-200 text-[10px]">
                                <i class="fas fa-store text-[9px]"></i>
                                {{ sucursalTexto }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-red-50 text-red-700 rounded-full border border-red-200 text-[10px]">
                                <i class="fas fa-hourglass-end text-[9px]"></i>
                                Límite: <strong>{{ horaLimiteExtra }}</strong>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-gray-50 text-gray-600 rounded-full border border-gray-200 text-[10px]">
                                <i class="fas fa-clock text-[9px]"></i>
                                Actual: <strong>{{ horaActual }}</strong>
                            </span>
                            <span v-if="horaValida" class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200 text-[10px]">
                                <i class="fas fa-check-circle text-[8px]"></i> Permitido
                            </span>
                            <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-50 text-red-700 rounded-full border border-red-200 text-[10px]">
                                <i class="fas fa-times-circle text-[8px]"></i> No permitido
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ==================== FORMULARIO ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-4 border border-red-200 formulario-container">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                            <i class="fas fa-pencil-alt text-red-500 text-[10px]"></i>
                            {{ editando ? 'Editar Pedido' : 'Nuevo Pedido Extraordinario' }}
                        </h2>
                        <div class="text-[10px] text-gray-500">
                            <i class="fas fa-user mr-1"></i>
                            Operador: <strong>{{ operadorNombre || operadorId }}</strong>
                        </div>
                    </div>

                    <!-- Validación de hora -->
                    <div v-if="!horaValida" class="mb-3 p-2 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center gap-1.5 text-red-700 text-xs">
                            <i class="fas fa-exclamation-circle text-[10px]"></i>
                            <span class="font-medium">{{ mensajeHora }}</span>
                        </div>
                    </div>
                    <div v-else-if="horaValida" class="mb-3 p-2 bg-emerald-50 border border-emerald-200 rounded-lg">
                        <div class="flex items-center gap-1.5 text-emerald-700 text-xs">
                            <i class="fas fa-check-circle text-[10px]"></i>
                            <span class="font-medium">Puede realizar pedidos extraordinarios</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Sucursal (solo lectura) -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Sucursal</label>
                            <div class="w-full border border-gray-200 rounded-md px-2.5 py-1 text-sm bg-gray-50 text-gray-600">
                                <i class="fas fa-store mr-1.5 text-gray-400 text-[10px]"></i>
                                {{ sucursalTexto }}
                            </div>
                        </div>

                        <!-- Fecha Realiza -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha Realiza</label>
                            <div class="w-full border border-gray-200 rounded-md px-2.5 py-1 text-sm bg-gray-50 text-gray-600">
                                <i class="fas fa-calendar-alt mr-1.5 text-gray-400 text-[10px]"></i>
                                {{ fechaHoraServidor ? new Date(fechaHoraServidor).toLocaleString('es-BO') : '-' }}
                            </div>
                        </div>

                        <!-- Fecha del Pedido (solo lectura) -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha del Pedido</label>
                            <div class="w-full border border-gray-200 rounded-md px-2.5 py-1 text-sm bg-gray-50 text-gray-600">
                                <i class="fas fa-calendar-day mr-1.5 text-gray-400 text-[10px]"></i>
                                {{ formData.FechaDelPedido ? new Date(formData.FechaDelPedido).toLocaleDateString('es-BO') : '-' }}
                            </div>
                            <p class="text-[8px] text-gray-400 mt-0.5">Solo se permite pedidos para el día de hoy</p>
                        </div>

                        <!-- Producto -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Producto *</label>
                            <div class="relative dropdown-producto-container">
                                <div class="relative">
                                    <i class="fas fa-search absolute left-2.5 top-1.5 text-gray-400 text-[10px]"></i>
                                    <input type="text"
                                        :value="busquedaProducto"
                                        @input="onBuscarProducto"
                                        @focus="onFocusProducto"
                                        placeholder="Buscar producto..."
                                        class="w-full border border-gray-300 rounded-md pl-7 pr-6 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                        :class="{ 'border-red-500': errors.IdProducto }" />
                                    <button v-if="formData.IdProducto" @click="limpiarSeleccionProducto"
                                        class="absolute right-1.5 top-1 text-gray-400 hover:text-gray-600 text-[10px]">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div v-if="mostrandoListaProducto && productosFiltrados.length > 0"
                                    class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto w-full">
                                    <div v-for="producto in productosFiltrados" :key="producto.id"
                                        @click="seleccionarProducto(producto)"
                                        class="px-2.5 py-1.5 hover:bg-red-50 cursor-pointer border-b last:border-b-0 text-sm flex items-center gap-2">
                                        <span class="font-mono text-[10px] text-gray-500">{{ producto.codigo }}</span>
                                        <span class="text-gray-800">{{ producto.descripcion }}</span>
                                    </div>
                                </div>
                                <div v-if="mostrandoListaProducto && busquedaProducto && busquedaProducto.length >= 1 && productosFiltrados.length === 0"
                                    class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-xs w-full">
                                    No se encontraron productos
                                </div>
                                <p v-if="errors.IdProducto" class="text-[8px] text-red-500 mt-0.5">{{ errors.IdProducto }}</p>
                            </div>
                        </div>

                        <!-- Unidades -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Unidades *</label>
                            <input type="number" v-model.number="formData.Unidades" step="any" min="0.01"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{ 'border-red-500': errors.Unidades }" />
                            <p v-if="errors.Unidades" class="text-[8px] text-red-500 mt-0.5">{{ errors.Unidades }}</p>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex flex-wrap justify-end gap-1.5 mt-3 pt-3 border-t border-gray-200">
                        <button @click="resetForm" :disabled="guardando"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition disabled:opacity-50 flex items-center gap-1">
                            <i class="fas fa-undo text-[10px]"></i> Limpiar
                        </button>
                        <button @click="guardar" :disabled="guardando || !horaValida"
                            class="px-4 py-1 bg-red-600 text-white rounded-md text-xs font-medium hover:bg-red-700 transition disabled:opacity-50 flex items-center gap-1.5">
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            {{ guardando ? 'Guardando...' : (editando ? 'Actualizar' : 'Guardar') }}
                        </button>
                    </div>
                </div>

                <!-- ==================== LISTA DE PEDIDOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                            <i class="fas fa-list text-red-500 text-[10px]"></i>
                            Mis Pedidos de Hoy
                        </h3>
                        <span class="text-[10px] text-gray-500">{{ pedidos.length }} pedido(s)</span>
                    </div>

                    <div class="relative overflow-x-auto" style="max-height: 60vh; overflow-y: auto;">
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="(pedido, idx) in pedidos" :key="pedido.IdPedidos" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-gray-800 truncate">
                                            {{ pedido.producto ? pedido.producto.Codigo + ' - ' + pedido.producto.Descripcion : '-' }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="text-xs font-bold text-red-600">{{ pedido.Unidades }} und</span>
                                        <div class="flex gap-1">
                                            <button @click="editarPedido(pedido.IdPedidos)" class="text-amber-600 hover:text-amber-700 text-[10px] p-1" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button @click="eliminarPedido(pedido.IdPedidos)" class="text-red-500 hover:text-red-700 text-[10px] p-1" title="Eliminar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="pedidos.length === 0" class="text-center text-gray-400 py-6">
                                <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                <span class="text-xs">No tienes pedidos registrados para hoy</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-20">Unidades</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-24">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(pedido, idx) in pedidos" :key="pedido.IdPedidos" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-1.5 text-xs text-gray-500">{{ idx + 1 }}</td>
                                    <td class="px-3 py-1.5 text-xs max-w-[200px] truncate" :title="pedido.producto ? pedido.producto.Descripcion : ''">
                                        {{ pedido.producto ? pedido.producto.Codigo + ' - ' + pedido.producto.Descripcion : '-' }}
                                    </td>
                                    <td class="px-3 py-1.5 text-center text-xs font-mono font-bold">{{ pedido.Unidades }}</td>
                                    <td class="px-3 py-1.5 text-center">
                                        <div class="flex justify-center gap-1.5">
                                            <button @click="editarPedido(pedido.IdPedidos)" class="text-amber-600 hover:text-amber-700 text-xs p-1" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button @click="eliminarPedido(pedido.IdPedidos)" class="text-red-500 hover:text-red-700 text-xs p-1" title="Eliminar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="pedidos.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                        No tienes pedidos registrados para hoy
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== INFORMACIÓN ==================== -->
                <div class="mt-3 p-2.5 bg-red-50 rounded-xl border border-red-100 text-xs text-red-700 flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5 text-red-500 text-[10px]"></i>
                    <div>
                        <span class="font-medium">Pedido Extraordinario:</span>
                        <ul class="list-disc list-inside mt-0.5 text-[11px] space-y-0.5">
                            <li>Solo se permite hasta las <strong class="text-red-800">07:00 AM</strong></li>
                            <li>Los pedidos son para el <strong>día de hoy</strong></li>
                            <li>La <strong>sucursal</strong> es la del operador logueado</li>
                            <li><strong>No valida cronograma</strong> de producción</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
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

/* Quitar flechas de inputs number */
input[type="number"] {
    appearance: textfield;
    -moz-appearance: textfield;
}
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>