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
    IdSucursal: props.sucursalDefault || '',
    FechaDelPedido: '',
    IdProducto: null,
    Unidades: 1,
})

// Búsqueda de sucursal
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

// ==================== COMPUTED ====================
const fechaMinima = computed(() => {
    const hoy = new Date()
    const manana = new Date(hoy)
    manana.setDate(hoy.getDate() + 1)
    return manana.toISOString().split('T')[0]
})

// ==================== FUNCIONES ====================
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
        toast?.warning('Validación', 'Las unidades deben ser mayor a cero')
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
            
            const sucursal = props.sucursales.find(s => s.id === data.IdSucursal)
            busquedaSucursal.value = sucursal ? sucursal.texto : ''
            
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

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

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
            <div class="max-w-6xl mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-orange-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-orange-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Pedido Extraordinario</h1>
                        <p class="text-xs text-gray-500">Registre pedidos fuera del horario normal (+2 horas de tolerancia)</p>
                        <div v-if="horaLimite !== null" class="mt-1 text-[10px]">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-blue-50 text-blue-700 rounded-full border border-blue-200">
                                <i class="fas fa-clock text-[9px]"></i>
                                Normal: <strong>{{ horaLimite }}:00</strong>
                                <span class="mx-1 text-gray-300">|</span>
                                Extra: <strong class="text-orange-600">{{ horaLimiteExtra }}:00</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ==================== FORMULARIO ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-4 border border-orange-200 formulario-container">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                            <i class="fas fa-pencil-alt text-orange-500 text-[10px]"></i>
                            {{ editando ? 'Editar Pedido' : 'Nuevo Pedido Extraordinario' }}
                        </h2>
                        <div class="text-[10px] text-gray-500">
                            <i class="fas fa-user mr-1"></i>
                            Operador: <strong>{{ operadorNombre || operadorId }}</strong>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Sucursal -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Sucursal *</label>
                            <div class="relative dropdown-sucursal-container">
                                <div class="relative">
                                    <i class="fas fa-store absolute left-2.5 top-1.5 text-gray-400 text-[10px]"></i>
                                    <input type="text"
                                        :value="busquedaSucursal"
                                        @input="onBuscarSucursal"
                                        @focus="onFocusSucursal"
                                        placeholder="Buscar sucursal..."
                                        class="w-full border border-gray-300 rounded-md pl-7 pr-6 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                        :class="{ 'border-red-500': errors.IdSucursal }" />
                                    <button v-if="formData.IdSucursal" @click="limpiarSeleccionSucursal"
                                        class="absolute right-1.5 top-1 text-gray-400 hover:text-gray-600 text-[10px]">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div v-if="mostrandoListaSucursal && sucursalesFiltradas.length > 0"
                                    class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto w-full">
                                    <div v-for="sucursal in sucursalesFiltradas" :key="sucursal.id"
                                        @click="seleccionarSucursal(sucursal)"
                                        class="px-2.5 py-1.5 hover:bg-orange-50 cursor-pointer border-b last:border-b-0 text-sm">
                                        {{ sucursal.texto }}
                                    </div>
                                </div>
                                <div v-if="mostrandoListaSucursal && busquedaSucursal && busquedaSucursal.length >= 1 && sucursalesFiltradas.length === 0"
                                    class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-xs w-full">
                                    No se encontraron sucursales
                                </div>
                                <p v-if="errors.IdSucursal" class="text-[8px] text-red-500 mt-0.5">{{ errors.IdSucursal }}</p>
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

                        <!-- Fecha del Pedido -->
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha Producción *</label>
                            <input type="date" v-model="formData.FechaDelPedido" :min="fechaMinima"
                                @change="() => { validarProducto(); validarHoraLimite() }"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{ 'border-red-500': errors.FechaDelPedido }" />
                            <p v-if="errors.FechaDelPedido" class="text-[8px] text-red-500 mt-0.5">{{ errors.FechaDelPedido }}</p>
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
                                        class="px-2.5 py-1.5 hover:bg-orange-50 cursor-pointer border-b last:border-b-0 text-sm flex items-center gap-2">
                                        <span class="font-mono text-[10px] text-gray-500">{{ producto.codigo }}</span>
                                        <span class="text-gray-800">{{ producto.descripcion }}</span>
                                    </div>
                                </div>
                                <div v-if="mostrandoListaProducto && busquedaProducto && busquedaProducto.length >= 1 && productosFiltrados.length === 0"
                                    class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-xs w-full">
                                    No se encontraron productos
                                </div>
                                <div v-if="!productoValido && mensajeProducto" class="text-[8px] text-red-500 mt-0.5">
                                    <i class="fas fa-exclamation-circle mr-0.5"></i> {{ mensajeProducto }}
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

                        <!-- Validaciones -->
                        <div class="flex items-end">
                            <div v-if="formData.FechaDelPedido && formData.IdProducto" class="flex flex-col gap-0.5">
                                <div v-if="validandoProducto" class="text-[9px] text-gray-400">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Validando producto...
                                </div>
                                <div v-else-if="productoValido" class="text-[9px] text-green-600">
                                    <i class="fas fa-check-circle mr-1"></i> Producto válido
                                </div>
                                <div v-else-if="mensajeProducto" class="text-[9px] text-red-500">
                                    <i class="fas fa-exclamation-circle mr-1"></i> {{ mensajeProducto }}
                                </div>
                                <div v-if="validandoHora" class="text-[9px] text-gray-400">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Validando hora...
                                </div>
                                <div v-else-if="!horaValida" class="text-[9px] text-red-500">
                                    <i class="fas fa-exclamation-circle mr-1"></i> {{ mensajeHora }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex flex-wrap justify-end gap-1.5 mt-3 pt-3 border-t border-gray-200">
                        <button @click="resetForm" :disabled="guardando"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition disabled:opacity-50 flex items-center gap-1">
                            <i class="fas fa-undo text-[10px]"></i> Limpiar
                        </button>
                        <button @click="guardar" :disabled="guardando || !productoValido || !horaValida || !formData.IdSucursal"
                            class="px-4 py-1 bg-orange-600 text-white rounded-md text-xs font-medium hover:bg-orange-700 transition disabled:opacity-50 flex items-center gap-1.5">
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
                            <i class="fas fa-list text-orange-500 text-[10px]"></i>
                            Mis Pedidos Futuros
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
                                            {{ pedido.sucursal ? pedido.sucursal.NumeroSucursal + ' - ' + pedido.sucursal.Nombre : '-' }}
                                        </p>
                                        <p class="text-[10px] text-gray-500">{{ formatearFecha(pedido.FechaDelPedido) }}</p>
                                        <p class="text-[10px] text-gray-700 truncate">
                                            {{ pedido.producto ? pedido.producto.Codigo + ' - ' + pedido.producto.Descripcion : '-' }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="text-xs font-bold text-orange-600">{{ pedido.Unidades }} und</span>
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
                                <span class="text-xs">No tienes pedidos futuros registrados</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Sucursal</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-20">Unidades</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-24">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(pedido, idx) in pedidos" :key="pedido.IdPedidos" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-1.5 text-xs text-gray-500">{{ idx + 1 }}</td>
                                    <td class="px-3 py-1.5 text-xs">
                                        {{ pedido.sucursal ? pedido.sucursal.NumeroSucursal + ' - ' + pedido.sucursal.Nombre : '-' }}
                                    </td>
                                    <td class="px-3 py-1.5 text-xs">{{ formatearFecha(pedido.FechaDelPedido) }}</td>
                                    <td class="px-3 py-1.5 text-xs max-w-[180px] truncate" :title="pedido.producto ? pedido.producto.Descripcion : ''">
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
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                        No tienes pedidos futuros registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== INFORMACIÓN ==================== -->
                <div class="mt-3 p-2.5 bg-orange-50 rounded-xl border border-orange-100 text-xs text-orange-700 flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5 text-orange-500 text-[10px]"></i>
                    <div>
                        <span class="font-medium">Pedido Extraordinario:</span>
                        <ul class="list-disc list-inside mt-0.5 text-[11px] space-y-0.5">
                            <li>Tiene <strong class="text-orange-800">+2 horas</strong> de tolerancia</li>
                            <li>El producto debe estar en el <strong>cronograma de producción</strong> para el día seleccionado</li>
                            <li>Los pedidos con fecha pasada <strong>no se pueden editar ni eliminar</strong></li>
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