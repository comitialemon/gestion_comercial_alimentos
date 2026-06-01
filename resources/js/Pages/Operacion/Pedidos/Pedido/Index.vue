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

// Estado del grid
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

// Responsive
const isMobile = ref(false)

const checkScreenSize = () => {
    isMobile.value = window.innerWidth < 768
}

// Fechas
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

// Inicializar
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

// Filtrar productos
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

// Validaciones
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

// Guardar fila
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

// Cancelar edición
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

// Iniciar edición
const iniciarEdicion = (index) => {
    filaEditando.value[index] = true
    const fila = filas.value[index]
    if (fila.IdProducto) {
        const producto = props.productos.find(p => p.id === fila.IdProducto)
        if (producto) busqueda.value[index] = producto.texto
    }
}

// Eliminar con toast (sin confirm nativo)
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

onMounted(() => {
    inicializarFilas()
    document.addEventListener('click', handleClickOutside)
    checkScreenSize()
    window.addEventListener('resize', checkScreenSize)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    window.removeEventListener('resize', checkScreenSize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-3 px-2 sm:py-4 sm:px-3 md:py-6 md:px-4">
            <div class="w-full max-w-7xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-4">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl mb-2"
                         :style="{ backgroundColor: primaryColor + '20', color: primaryColor }">
                        <i class="fas fa-shopping-cart text-xl"></i>
                    </div>
                    <h1 class="text-lg sm:text-xl font-bold text-gray-900">Pedidos de Producción</h1>
                    <p class="text-xs text-gray-500">Registre pedidos para producción (fecha futura)</p>
                </div>

                <!-- Botón Agregar -->
                <div class="flex justify-end mb-3">
                    <button @click="agregarFila"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 flex items-center gap-2 shadow-md">
                        <i class="fas fa-plus text-xs"></i> Agregar Pedido
                    </button>
                </div>

                <!-- VISTA MÓVIL: CARDS -->
                <div v-if="isMobile" class="space-y-3">
                    <div v-for="(fila, idx) in filas" :key="idx" class="bg-white rounded-xl shadow-sm p-3 pedido-card">
                        <!-- Número de fila -->
                        <div class="text-xs text-gray-400 mb-2">Pedido #{{ idx + 1 }}</div>
                        
                        <!-- Fecha Realiza -->
                        <div class="mb-2">
                            <label class="text-[10px] text-gray-500">Fecha Realiza</label>
                            <div class="text-xs text-gray-700 bg-gray-50 rounded px-2 py-1">{{ formatearFechaHora(fila.FechaRealiza) }}</div>
                        </div>

                        <!-- Fecha Producción -->
                        <div class="mb-2">
                            <label class="text-[10px] text-gray-500">Fecha Producción</label>
                            <div v-if="filaEditando[idx]">
                                <input type="date" v-model="fila.FechaDelPedido" 
                                    :min="new Date().toISOString().split('T')[0]"
                                    @change="() => { validarProducto(idx); validarHoraLimite(idx) }"
                                    class="w-full border rounded-lg px-2 py-1.5 text-sm">
                                <div v-if="!horaValida[idx] && mensajeHora[idx]" class="text-[10px] text-red-500 mt-0.5">{{ mensajeHora[idx] }}</div>
                            </div>
                            <div v-else class="text-xs text-gray-700 bg-gray-50 rounded px-2 py-1">{{ formatearFecha(fila.FechaDelPedido) }}</div>
                        </div>

                        <!-- Producto -->
                        <div class="mb-2">
                            <label class="text-[10px] text-gray-500">Producto</label>
                            <div v-if="filaEditando[idx]" class="relative" :ref="(el) => setDropdownRef(el, idx)">
                                <div class="relative">
                                    <i class="fas fa-search absolute left-2 top-2 text-gray-400 text-xs"></i>
                                    <input type="text" :value="busqueda[idx] || ''"
                                        @input="(e) => onBuscar(idx, e)" @focus="() => onFocus(idx)"
                                        placeholder="Buscar producto..."
                                        class="w-full border rounded-lg pl-8 pr-8 py-1.5 text-sm">
                                    <button v-if="fila.IdProducto" @click="limpiarSeleccion(idx)"
                                        class="absolute right-2 top-1.5 text-gray-400">✕</button>
                                </div>
                                <div v-if="mostrandoLista[idx] && productosFiltrados[idx]?.length > 0"
                                    class="absolute z-50 mt-1 bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto w-full">
                                    <div v-for="prod in productosFiltrados[idx]" :key="prod.id"
                                        @click="seleccionarProducto(idx, prod)"
                                        class="px-2 py-1.5 hover:bg-purple-50 cursor-pointer border-b text-sm">
                                        <span class="font-mono text-xs text-gray-500">{{ prod.codigo }}</span>
                                        <span class="ml-1">{{ prod.descripcion }}</span>
                                    </div>
                                </div>
                                <div v-if="!productoValido[idx] && mensajeProducto[idx]" class="text-[10px] text-red-500 mt-0.5">{{ mensajeProducto[idx] }}</div>
                            </div>
                            <div v-else class="text-xs text-gray-700 bg-gray-50 rounded px-2 py-1">{{ fila.producto_texto || '-' }}</div>
                        </div>

                        <!-- Unidades -->
                        <div class="mb-3">
                            <label class="text-[10px] text-gray-500">Unidades</label>
                            <div v-if="filaEditando[idx]">
                                <input type="number" v-model.number="fila.Unidades" step="any" min="0.01"
                                    class="w-28 border rounded-lg px-2 py-1.5 text-sm"
                                    style="appearance: textfield; -moz-appearance: textfield;">
                            </div>
                            <div v-else class="text-sm font-bold text-gray-800">{{ fila.Unidades }}</div>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-2 pt-2 border-t">
                            <template v-if="filaEditando[idx]">
                                <button @click="guardarFila(idx)" :disabled="guardando"
                                    class="px-3 py-1 bg-emerald-600 text-white rounded-md text-xs disabled:opacity-50">
                                    <i class="fas fa-check mr-1"></i> Guardar
                                </button>
                                <button @click="cancelarEdicion(idx)" class="px-3 py-1 bg-gray-200 rounded-md text-xs">
                                    <i class="fas fa-times mr-1"></i> Cancelar
                                </button>
                            </template>
                            <template v-else>
                                <button v-if="fila.id" @click="iniciarEdicion(idx)" class="text-amber-600 hover:text-amber-700 text-sm">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button v-if="fila.id" @click="confirmarEliminar(idx)" class="text-red-500 hover:text-red-700 text-sm">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div v-if="filas.length === 0" class="text-center text-gray-400 py-8">
                        <i class="fas fa-clipboard-list text-2xl mb-2 block"></i>
                        No hay pedidos registrados
                    </div>
                </div>

                <!-- VISTA DESKTOP: TABLA -->
                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase w-12">#</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Fecha Realiza</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Fecha Producción</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase w-24">Unidades</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase w-28">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(fila, idx) in filas" :key="idx" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-center text-sm text-gray-500">{{ idx + 1 }}</td>
                                    <td class="px-3 py-2 text-center text-xs text-gray-500">{{ formatearFechaHora(fila.FechaRealiza) }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <div v-if="filaEditando[idx]">
                                            <input type="date" v-model="fila.FechaDelPedido" :min="new Date().toISOString().split('T')[0]"
                                                @change="() => { validarProducto(idx); validarHoraLimite(idx) }"
                                                class="border rounded px-2 py-1 text-sm">
                                            <div v-if="!horaValida[idx] && mensajeHora[idx]" class="text-[10px] text-red-500">{{ mensajeHora[idx] }}</div>
                                        </div>
                                        <div v-else class="text-sm">{{ formatearFecha(fila.FechaDelPedido) }}</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div v-if="filaEditando[idx]" class="relative" :ref="(el) => setDropdownRef(el, idx)">
                                            <div class="relative">
                                                <i class="fas fa-search absolute left-2 top-2 text-gray-400 text-xs"></i>
                                                <input type="text" :value="busqueda[idx] || ''" @input="(e) => onBuscar(idx, e)"
                                                    @focus="() => onFocus(idx)" placeholder="Buscar..."
                                                    class="w-64 border rounded pl-7 pr-7 py-1 text-sm">
                                                <button v-if="fila.IdProducto" @click="limpiarSeleccion(idx)"
                                                    class="absolute right-2 top-1.5 text-gray-400">✕</button>
                                            </div>
                                            <div v-if="mostrandoLista[idx] && productosFiltrados[idx]?.length > 0"
                                                class="absolute z-50 mt-1 bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto w-80">
                                                <div v-for="prod in productosFiltrados[idx]" :key="prod.id"
                                                    @click="seleccionarProducto(idx, prod)"
                                                    class="px-2 py-1.5 hover:bg-purple-50 cursor-pointer border-b text-sm">
                                                    <span class="font-mono text-xs">{{ prod.codigo }}</span> - {{ prod.descripcion }}
                                                </div>
                                            </div>
                                            <div v-if="!productoValido[idx] && mensajeProducto[idx]" class="text-[10px] text-red-500">{{ mensajeProducto[idx] }}</div>
                                        </div>
                                        <div v-else class="text-sm">{{ fila.producto_texto || '-' }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <div v-if="filaEditando[idx]">
                                            <input type="number" v-model.number="fila.Unidades" step="any" min="0.01"
                                                class="w-20 text-center border rounded px-2 py-1 text-sm"
                                                style="appearance: textfield; -moz-appearance: textfield;">
                                        </div>
                                        <div v-else class="text-sm font-mono font-bold">{{ fila.Unidades }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex justify-center gap-2">
                                            <template v-if="filaEditando[idx]">
                                                <button @click="guardarFila(idx)" :disabled="guardando" class="text-emerald-600 hover:text-emerald-700">
                                                    <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                                                    <i v-else class="fas fa-check"></i>
                                                </button>
                                                <button @click="cancelarEdicion(idx)" class="text-gray-500 hover:text-gray-700">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </template>
                                            <template v-else>
                                                <button v-if="fila.id" @click="iniciarEdicion(idx)" class="text-amber-600 hover:text-amber-700">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button v-if="fila.id" @click="confirmarEliminar(idx)" class="text-red-500 hover:text-red-700">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filas.length === 0">
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-400">No hay pedidos registrados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Validaciones:</strong> El producto debe estar programado en el cronograma de producción para el día seleccionado.
                </div>
            </div>
        </div>
    </div>
</template>