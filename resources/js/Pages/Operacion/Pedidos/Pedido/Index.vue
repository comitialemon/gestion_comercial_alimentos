<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const page = usePage()
const theme = computed(() => page.props?.theme || { primary: '#1f2937' })
const primaryColor = computed(() => theme.value.primary || '#1f2937')

const props = defineProps({
    pedidos: {
        type: Array,
        default: () => []
    },
    productos: {
        type: Array,
        default: () => []
    },
    fechaHoraServidor: {
        type: String,
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
const filas = ref([])
const guardando = ref(false)
const eliminando = ref(false)

// Estado para selectores
const busqueda = ref({})
const mostrandoLista = ref({})
const productosFiltrados = ref({})

// Validaciones
const validandoProducto = ref({})
const productoValido = ref({})
const mensajeProducto = ref({})
const validandoHora = ref({})
const horaValida = ref({})
const mensajeHora = ref({})

// Estado de edición
const filaEditando = ref({})
const eliminarIndex = ref(null)

// ==================== FUNCIONES ====================
const getFechaManana = () => {
    const ahora = new Date()
    const manana = new Date(ahora)
    manana.setDate(ahora.getDate() + 1)
    return manana.toISOString().split('T')[0]
}

const getProductoTexto = (productoId) => {
    if (!productoId) return ''
    const producto = props.productos.find(p => p.id === productoId)
    return producto ? producto.texto : ''
}

const initBusquedaPorFila = (filaIndex) => {
    if (!busqueda.value[filaIndex]) busqueda.value[filaIndex] = ''
    if (!mostrandoLista.value[filaIndex]) mostrandoLista.value[filaIndex] = false
    if (!productosFiltrados.value[filaIndex]) productosFiltrados.value[filaIndex] = [...props.productos]
    
    const productoId = filas.value[filaIndex]?.IdProducto
    if (productoId) {
        const producto = props.productos.find(p => p.id === productoId)
        if (producto) busqueda.value[filaIndex] = producto.texto
    }
}

const inicializarFilas = () => {
    if (props.pedidos && props.pedidos.length > 0) {
        filas.value = props.pedidos.map(p => ({
            id: p.IdPedidos,
            FechaRealiza: p.FechaRealiza,
            FechaDelPedido: p.FechaDelPedido,
            IdProducto: p.IdProducto,
            Unidades: p.Unidades,
            producto_texto: getProductoTexto(p.IdProducto),
        }))
        filas.value.forEach((_, idx) => {
            filaEditando.value[idx] = false
            initBusquedaPorFila(idx)
        })
    }
    if (filas.value.length === 0) {
        agregarFila()
    }
}

const agregarFila = () => {
    const nuevaFila = {
        id: null,
        FechaRealiza: new Date().toISOString().slice(0, 19).replace('T', ' '),
        FechaDelPedido: getFechaManana(),
        IdProducto: null,
        Unidades: 1,
        producto_texto: '',
    }
    const idx = filas.value.length
    filas.value.push(nuevaFila)
    initBusquedaPorFila(idx)
    filaEditando.value[idx] = true
    
    nextTick(() => {
        const ultimo = document.querySelector('.pedido-card:last-child, tbody tr:last-child')
        if (ultimo) ultimo.scrollIntoView({ behavior: 'smooth', block: 'center' })
    })
}

const filtrarProductos = (filaIndex, termino) => {
    if (!termino || termino.trim() === '') {
        productosFiltrados.value[filaIndex] = [...props.productos]
        return
    }
    const terminoLower = termino.toLowerCase().trim()
    productosFiltrados.value[filaIndex] = props.productos.filter(p => 
        (p.codigo || '').toLowerCase().includes(terminoLower) ||
        (p.descripcion || '').toLowerCase().includes(terminoLower) ||
        (p.texto || '').toLowerCase().includes(terminoLower)
    )
}

const onBuscar = (filaIndex, event) => {
    const termino = event.target.value
    busqueda.value[filaIndex] = termino
    filtrarProductos(filaIndex, termino)
    mostrandoLista.value[filaIndex] = termino.length >= 1 && productosFiltrados.value[filaIndex]?.length > 0
}

const onFocus = (filaIndex) => {
    const termino = busqueda.value[filaIndex] || ''
    if (termino.length >= 1) {
        filtrarProductos(filaIndex, termino)
        mostrandoLista.value[filaIndex] = productosFiltrados.value[filaIndex]?.length > 0
    } else {
        productosFiltrados.value[filaIndex] = [...props.productos]
        mostrandoLista.value[filaIndex] = true
    }
}

const seleccionarProducto = (filaIndex, producto) => {
    filas.value[filaIndex].IdProducto = producto.id
    filas.value[filaIndex].producto_texto = producto.texto
    busqueda.value[filaIndex] = producto.texto
    mostrandoLista.value[filaIndex] = false
    validarProducto(filaIndex)
}

const limpiarSeleccion = (filaIndex) => {
    filas.value[filaIndex].IdProducto = null
    filas.value[filaIndex].producto_texto = ''
    busqueda.value[filaIndex] = ''
    productoValido.value[filaIndex] = false
}

const validarProducto = async (filaIndex) => {
    const fila = filas.value[filaIndex]
    if (!fila.IdProducto || !fila.FechaDelPedido) return
    
    validandoProducto.value[filaIndex] = true
    try {
        const response = await axios.post('/operacion/pedidos/pedido/api/validar-producto', {
            IdProducto: fila.IdProducto,
            FechaDelPedido: fila.FechaDelPedido
        })
        productoValido.value[filaIndex] = response.data.valido
        mensajeProducto.value[filaIndex] = response.data.mensaje
    } catch (error) {
        productoValido.value[filaIndex] = false
    } finally {
        validandoProducto.value[filaIndex] = false
    }
}

const validarHoraLimite = async (filaIndex) => {
    const fila = filas.value[filaIndex]
    if (!fila.FechaDelPedido) return
    
    validandoHora.value[filaIndex] = true
    try {
        const response = await axios.post('/operacion/pedidos/pedido/api/validar-hora-limite', {
            FechaDelPedido: fila.FechaDelPedido
        })
        horaValida.value[filaIndex] = response.data.valido
        mensajeHora.value[filaIndex] = response.data.mensaje
    } catch (error) {
        horaValida.value[filaIndex] = true
    } finally {
        validandoHora.value[filaIndex] = false
    }
}

const guardarFila = async (index) => {
    const fila = filas.value[index]
    
    if (!fila.FechaDelPedido) {
        toast?.warning('Validación', 'Complete la fecha del pedido')
        return
    }
    if (!fila.IdProducto) {
        toast?.warning('Validación', 'Seleccione un producto')
        return
    }
    if (fila.Unidades <= 0) {
        toast?.warning('Validación', 'Las unidades deben ser mayores a cero')
        return
    }
    if (productoValido.value[index] === false) {
        toast?.warning('Validación', mensajeProducto.value[index])
        return
    }
    if (horaValida.value[index] === false) {
        toast?.warning('Validación', mensajeHora.value[index])
        return
    }
    
    guardando.value = true
    try {
        const response = await axios.post('/operacion/pedidos/pedido', {
            pedidos: [{
                id: fila.id,
                FechaDelPedido: fila.FechaDelPedido,
                IdProducto: fila.IdProducto,
                Unidades: fila.Unidades,
            }]
        })
        
        if (response.data.success) {
            toast?.success('Éxito', 'Pedido guardado correctamente')
            filas.value[index] = { ...fila, id: fila.id || Date.now(), producto_texto: getProductoTexto(fila.IdProducto) }
            filaEditando.value[index] = false
            agregarFila()
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardando.value = false
    }
}

const cancelarEdicion = (index) => {
    const fila = filas.value[index]
    if (!fila.id) {
        filas.value.splice(index, 1)
        toast?.info('Cancelado', 'Fila eliminada')
        if (filas.value.length === 0) agregarFila()
        return
    }
    if (props.pedidos && props.pedidos[index]) {
        const original = props.pedidos[index]
        filas.value[index] = {
            id: original.IdPedidos,
            FechaRealiza: original.FechaRealiza,
            FechaDelPedido: original.FechaDelPedido,
            IdProducto: original.IdProducto,
            Unidades: original.Unidades,
            producto_texto: getProductoTexto(original.IdProducto),
        }
    }
    filaEditando.value[index] = false
}

const iniciarEdicion = (index) => {
    filaEditando.value[index] = true
    const fila = filas.value[index]
    if (fila.IdProducto) {
        const producto = props.productos.find(p => p.id === fila.IdProducto)
        if (producto) busqueda.value[index] = producto.texto
    }
}

const confirmarEliminar = (index) => {
    eliminarIndex.value = index
    toast?.warning('¿Eliminar pedido?', 'Esta acción no se puede deshacer', {
        action: {
            label: 'Eliminar',
            onClick: () => eliminarFila(index)
        },
        duration: 5000
    })
}

const eliminarFila = async (index) => {
    const fila = filas.value[index]
    if (!fila.id) return
    
    eliminando.value = true
    try {
        await axios.delete(`/operacion/pedidos/pedido/${fila.id}`)
        filas.value.splice(index, 1)
        toast?.success('Eliminado', 'Pedido eliminado correctamente')
        if (filas.value.length === 0) agregarFila()
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al eliminar')
    } finally {
        eliminando.value = false
        eliminarIndex.value = null
    }
}

// Click outside
const dropdownRefs = ref({})
const setDropdownRef = (el, idx) => { if (el) dropdownRefs.value[idx] = el }
const handleClickOutside = (event) => {
    Object.keys(mostrandoLista.value).forEach(idx => {
        const container = dropdownRefs.value[idx]
        if (container && !container.contains(event.target)) mostrandoLista.value[idx] = false
    })
}

const formatearFecha = (fecha) => fecha ? new Date(fecha).toLocaleDateString('es-BO') : ''
const formatearFechaHora = (fecha) => fecha ? new Date(fecha).toLocaleString('es-BO') : ''

// ==================== LIFECYCLE ====================
onMounted(() => {
    inicializarFilas()
    document.addEventListener('click', handleClickOutside)
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="w-full max-w-full mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center"
                             :style="{ backgroundColor: primaryColor + '20', color: primaryColor }">
                            <i class="fas fa-shopping-cart text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Pedidos de Producción</h1>
                            <p class="text-xs text-gray-500">Registre pedidos para producción (fecha futura)</p>
                        </div>
                    </div>
                    <button @click="agregarFila"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition shadow-sm">
                        <i class="fas fa-plus text-[10px]"></i> Agregar Pedido
                    </button>
                </div>

                <!-- ==================== TABLA ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto" style="overflow-y: visible; -webkit-overflow-scrolling: touch;">
                        
                        <!-- VISTA MÓVIL: CARDS -->
                        <div v-if="isMobile" class="p-2 space-y-2 overflow-visible">
                            <div v-for="(fila, idx) in filas" :key="idx" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100 overflow-visible pedido-card">
                                <div class="text-[11px] text-gray-400 mb-1.5">Pedido #{{ idx + 1 }}</div>
                                
                                <div class="mb-1.5">
                                    <label class="text-[9px] text-gray-500">Fecha Realiza</label>
                                    <div class="text-xs text-gray-700 bg-white rounded px-2 py-0.5">{{ formatearFechaHora(fila.FechaRealiza) }}</div>
                                </div>

                                <div class="mb-1.5">
                                    <label class="text-[9px] text-gray-500">Fecha Producción</label>
                                    <div v-if="filaEditando[idx]">
                                        <input type="date" v-model="fila.FechaDelPedido" 
                                            :min="new Date().toISOString().split('T')[0]"
                                            @change="() => { validarProducto(idx); validarHoraLimite(idx) }"
                                            class="w-full border border-gray-300 rounded-md px-2 py-0.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                        <div v-if="!horaValida[idx] && mensajeHora[idx]" class="text-[9px] text-red-500 mt-0.5">{{ mensajeHora[idx] }}</div>
                                    </div>
                                    <div v-else class="text-xs text-gray-700 bg-white rounded px-2 py-0.5">{{ formatearFecha(fila.FechaDelPedido) }}</div>
                                </div>

                                <!-- Producto -->
                                <div class="mb-1.5 overflow-visible">
                                    <label class="text-[9px] text-gray-500">Producto</label>
                                    <div v-if="filaEditando[idx]" class="relative overflow-visible" :ref="(el) => setDropdownRef(el, idx)">
                                        <div class="relative">
                                            <i class="fas fa-search absolute left-1.5 top-1.5 text-gray-400 text-[9px]"></i>
                                            <input type="text" :value="busqueda[idx] || ''"
                                                @input="(e) => onBuscar(idx, e)" @focus="() => onFocus(idx)"
                                                placeholder="Buscar..."
                                                class="w-full border border-gray-300 rounded-md pl-6 pr-6 py-0.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                            <button v-if="fila.IdProducto" @click="limpiarSeleccion(idx)"
                                                class="absolute right-1.5 top-1 text-gray-400 text-[11px]">✕</button>
                                        </div>
                                        <div v-if="mostrandoLista[idx] && productosFiltrados[idx]?.length > 0"
                                            class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-xl max-h-48 overflow-y-auto"
                                            style="min-width: 240px; width: 100%; left: 0; right: 0;">
                                            <div v-for="prod in productosFiltrados[idx]" :key="prod.id"
                                                @click="seleccionarProducto(idx, prod)"
                                                class="px-2 py-1.5 hover:bg-primary-50 cursor-pointer border-b last:border-b-0">
                                                <div class="flex flex-col">
                                                    <span class="font-mono text-[9px] text-gray-500">{{ prod.codigo }}</span>
                                                    <span class="text-xs text-gray-800">{{ prod.descripcion }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="!productoValido[idx] && mensajeProducto[idx]" class="text-[9px] text-red-500 mt-0.5">{{ mensajeProducto[idx] }}</div>
                                    </div>
                                    <div v-else class="text-xs text-gray-700 bg-white rounded px-2 py-0.5">{{ fila.producto_texto || '-' }}</div>
                                </div>

                                <div class="mb-2">
                                    <label class="text-[9px] text-gray-500">Unidades</label>
                                    <div v-if="filaEditando[idx]">
                                        <input type="number" v-model.number="fila.Unidades" step="any" min="0.01"
                                            class="w-20 border border-gray-300 rounded-md px-2 py-0.5 text-sm text-center focus:ring-primary-500 focus:border-primary-500 outline-none"
                                            style="appearance: textfield; -moz-appearance: textfield;">
                                    </div>
                                    <div v-else class="text-sm font-bold text-gray-800">{{ fila.Unidades }}</div>
                                </div>

                                <div class="flex justify-end gap-1.5 pt-1.5 border-t border-gray-200">
                                    <template v-if="filaEditando[idx]">
                                        <button @click="guardarFila(idx)" :disabled="guardando"
                                            class="px-2.5 py-0.5 bg-emerald-600 text-white rounded-md text-[11px] disabled:opacity-50 flex items-center gap-1">
                                            <i class="fas fa-check text-[9px]"></i> Guardar
                                        </button>
                                        <button @click="cancelarEdicion(idx)" class="px-2.5 py-0.5 bg-gray-200 text-gray-700 rounded-md text-[11px] flex items-center gap-1">
                                            <i class="fas fa-times text-[9px]"></i> Cancelar
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button v-if="fila.id" @click="iniciarEdicion(idx)" class="text-amber-600 hover:text-amber-700 text-xs p-1">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button v-if="fila.id" @click="confirmarEliminar(idx)" class="text-red-500 hover:text-red-700 text-xs p-1">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div v-if="filas.length === 0" class="text-center text-gray-400 py-6">
                                <i class="fas fa-clipboard-list text-2xl mb-1 block"></i>
                                <span class="text-sm">No hay pedidos registrados</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200 overflow-visible">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-2 text-center text-[10px] font-medium text-gray-500 uppercase w-10">#</th>
                                    <th class="px-2 py-2 text-center text-[10px] font-medium text-gray-500 uppercase">Fecha Realiza</th>
                                    <th class="px-2 py-2 text-center text-[10px] font-medium text-gray-500 uppercase">Fecha Producción</th>
                                    <th class="px-2 py-2 text-center text-[10px] font-medium text-gray-500 uppercase" style="min-width: 250px;">Producto</th>
                                    <th class="px-2 py-2 text-center text-[10px] font-medium text-gray-500 uppercase w-20">Unidades</th>
                                    <th class="px-2 py-2 text-center text-[10px] font-medium text-gray-500 uppercase w-24">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 overflow-visible">
                                <tr v-for="(fila, idx) in filas" :key="idx" class="hover:bg-gray-50 overflow-visible">
                                    <td class="px-2 py-2 text-center text-sm text-gray-500">{{ idx + 1 }}</td>
                                    <td class="px-2 py-2 text-center text-xs text-gray-500">{{ formatearFechaHora(fila.FechaRealiza) }}</td>
                                    <td class="px-2 py-2 text-center">
                                        <div v-if="filaEditando[idx]">
                                            <input type="date" v-model="fila.FechaDelPedido" :min="new Date().toISOString().split('T')[0]"
                                                @change="() => { validarProducto(idx); validarHoraLimite(idx) }"
                                                class="border border-gray-300 rounded-md px-2 py-0.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                            <div v-if="!horaValida[idx] && mensajeHora[idx]" class="text-[9px] text-red-500">{{ mensajeHora[idx] }}</div>
                                        </div>
                                        <div v-else class="text-sm">{{ formatearFecha(fila.FechaDelPedido) }}</div>
                                    </td>
                                    <!-- Producto -->
                                    <td class="px-2 py-2 overflow-visible">
                                        <div v-if="filaEditando[idx]" class="relative overflow-visible" :ref="(el) => setDropdownRef(el, idx)">
                                            <div class="relative">
                                                <i class="fas fa-search absolute left-1.5 top-1.5 text-gray-400 text-[9px]"></i>
                                                <input type="text" :value="busqueda[idx] || ''" @input="(e) => onBuscar(idx, e)"
                                                    @focus="() => onFocus(idx)" placeholder="Buscar..."
                                                    class="w-48 border border-gray-300 rounded-md pl-6 pr-6 py-0.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                                <button v-if="fila.IdProducto" @click="limpiarSeleccion(idx)"
                                                    class="absolute right-1.5 top-1 text-gray-400 text-[11px]">✕</button>
                                            </div>
                                            <div v-if="mostrandoLista[idx] && productosFiltrados[idx]?.length > 0"
                                                class="absolute z-50 mt-1 bg-white border border-gray-200 rounded-md shadow-xl max-h-60 overflow-y-auto"
                                                style="min-width: 280px; width: 100%; left: 0; right: 0;">
                                                <div v-for="prod in productosFiltrados[idx]" :key="prod.id"
                                                    @click="seleccionarProducto(idx, prod)"
                                                    class="px-2 py-1.5 hover:bg-primary-50 cursor-pointer border-b last:border-b-0">
                                                    <div class="flex flex-col">
                                                        <span class="font-mono text-[9px] text-gray-500">{{ prod.codigo }}</span>
                                                        <span class="text-xs text-gray-800">{{ prod.descripcion }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-if="!productoValido[idx] && mensajeProducto[idx]" class="text-[9px] text-red-500">{{ mensajeProducto[idx] }}</div>
                                        </div>
                                        <div v-else class="text-sm">{{ fila.producto_texto || '-' }}</div>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <div v-if="filaEditando[idx]">
                                            <input type="number" v-model.number="fila.Unidades" step="any" min="0.01"
                                                class="w-16 text-center border border-gray-300 rounded-md px-2 py-0.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                                style="appearance: textfield; -moz-appearance: textfield;">
                                        </div>
                                        <div v-else class="text-sm font-mono font-bold">{{ fila.Unidades }}</div>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <div class="flex justify-center gap-1.5">
                                            <template v-if="filaEditando[idx]">
                                                <button @click="guardarFila(idx)" :disabled="guardando" class="text-emerald-600 hover:text-emerald-700 text-xs p-1">
                                                    <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                                                    <i v-else class="fas fa-check"></i>
                                                </button>
                                                <button @click="cancelarEdicion(idx)" class="text-gray-500 hover:text-gray-700 text-xs p-1">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </template>
                                            <template v-else>
                                                <button v-if="fila.id" @click="iniciarEdicion(idx)" class="text-amber-600 hover:text-amber-700 text-xs p-1">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button v-if="fila.id" @click="confirmarEliminar(idx)" class="text-red-500 hover:text-red-700 text-xs p-1">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filas.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        <i class="fas fa-clipboard-list text-2xl mb-1 block"></i>
                                        No hay pedidos registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== INFORMACIÓN ==================== -->
                <div class="mt-3 p-2.5 bg-blue-50 rounded-xl border border-blue-100 text-xs text-blue-700 flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5 text-blue-500 text-[10px]"></i>
                    <div>
                        <span class="font-medium">Validaciones:</span>
                        <span class="text-[11px] ml-1">El producto debe estar programado en el cronograma de producción para el día seleccionado.</span>
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

/* ========== ESTILOS PARA DROPDOWN QUE SALE DEL CONTENEDOR ========== */
.overflow-visible {
    overflow: visible !important;
}

.relative {
    overflow: visible !important;
}

.absolute {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
}

/* Scroll del dropdown */
.max-h-48, .max-h-60 {
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
}

.max-h-48::-webkit-scrollbar,
.max-h-60::-webkit-scrollbar {
    width: 5px;
}

.max-h-48::-webkit-scrollbar-track,
.max-h-60::-webkit-scrollbar-track {
    background: transparent;
}

.max-h-48::-webkit-scrollbar-thumb,
.max-h-60::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 4px;
}

.max-h-48::-webkit-scrollbar-thumb:hover,
.max-h-60::-webkit-scrollbar-thumb:hover {
    background-color: #9ca3af;
}

/* Animación del dropdown */
.relative .absolute {
    animation: dropdownFade 0.15s ease-out;
}

@keyframes dropdownFade {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Asegurar que la tabla no corte el dropdown */
table {
    overflow: visible !important;
}
tbody {
    overflow: visible !important;
}
tr {
    overflow: visible !important;
}
td {
    overflow: visible !important;
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