<script setup>
import { ref, computed, watch, inject } from 'vue'
import axios from 'axios'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    contenedor: {
        type: Object,
        default: null
    },
    idIdentificador: {
        type: Number,
        default: null
    },
    modoEdicion: {
        type: Boolean,
        default: false
    },
    datosEdicion: {
        type: Object,
        default: null
    },
    tipoPrecio: { // ✅ NUEVO
        type: String,
        default: 'sin_factura'
    }
})

const emit = defineEmits(['close', 'agregar', 'actualizar'])

// ==================== ESTADO ====================
const loading = ref(false)
const productosSeleccionados = ref([])
const busquedaProducto = ref('')
const totalUnidadesSeleccionadas = ref(0)
const capacidadTotalContenedor = ref(0)
const contenedorData = ref(null)
const errorMensaje = ref('')
const excedeCapacidad = ref(false)
const errorCarga = ref(false)

const toast = inject('toast', null)

// ==================== COMPUTADOS ====================
const productosFiltrados = computed(() => {
    if (!busquedaProducto.value || busquedaProducto.value.length < 2) {
        return productosSeleccionados.value
    }
    const termino = busquedaProducto.value.toLowerCase()
    return productosSeleccionados.value.filter(p => 
        p.Descripcion?.toLowerCase().includes(termino) ||
        p.Codigo?.toLowerCase().includes(termino)
    )
})

const productosAgregados = computed(() => {
    return productosSeleccionados.value.filter(p => p.Cantidad > 0 && p.tiene_precio)
})

const totalRestante = computed(() => {
    return capacidadTotalContenedor.value - totalUnidadesSeleccionadas.value
})

const estaCompleto = computed(() => {
    return totalUnidadesSeleccionadas.value === capacidadTotalContenedor.value && capacidadTotalContenedor.value > 0
})

const puedeAgregar = computed(() => {
    return !loading.value && 
           productosAgregados.value.length > 0 && 
           !excedeCapacidad.value && 
           totalUnidadesSeleccionadas.value > 0
})

const porcentajeCompletado = computed(() => {
    if (capacidadTotalContenedor.value === 0) return 0
    return Math.min((totalUnidadesSeleccionadas.value / capacidadTotalContenedor.value) * 100, 100)
})

const colorBarra = computed(() => {
    if (excedeCapacidad.value) return 'bg-red-500'
    if (estaCompleto.value) return 'bg-green-500'
    if (porcentajeCompletado.value > 70) return 'bg-yellow-500'
    return 'bg-primary-500'
})

const formatearNumero = (valor) => {
    if (valor === undefined || valor === null || valor === '') return '0'
    const numero = parseInt(valor)
    if (isNaN(numero)) return '0'
    return numero.toFixed(0)
}

// ==================== FUNCIONES DE CANTIDAD ====================
const actualizarCantidad = (producto, event) => {
    const input = event.target
    let valor = input.value.replace(/,/g, '.').trim()
    
    if (valor === '' || valor === '-') {
        producto.Cantidad = 0
        recalcularTotal()
        validarCapacidad()
        return
    }
    
    let cantidad = parseInt(valor)
    
    if (isNaN(cantidad) || cantidad < 0) {
        producto.Cantidad = 0
        input.value = 0
        recalcularTotal()
        validarCapacidad()
        return
    }
    
    const otrasUnidades = totalUnidadesSeleccionadas.value - (producto.Cantidad || 0)
    const disponible = capacidadTotalContenedor.value - otrasUnidades
    
    if (cantidad > disponible) {
        cantidad = disponible
        input.value = disponible
        if (cantidad < 0) cantidad = 0
    }
    
    producto.Cantidad = cantidad
    recalcularTotal()
    validarCapacidad()
}

const incrementarCantidad = (producto) => {
    const actual = producto.Cantidad || 0
    const otrasUnidades = totalUnidadesSeleccionadas.value - actual
    const disponible = capacidadTotalContenedor.value - otrasUnidades
    
    if (actual < disponible) {
        producto.Cantidad = Math.min(actual + 1, disponible)
        recalcularTotal()
        validarCapacidad()
    }
}

const decrementarCantidad = (producto) => {
    if (producto.Cantidad > 0) {
        producto.Cantidad--
        recalcularTotal()
        validarCapacidad()
    }
}

const recalcularTotal = () => {
    totalUnidadesSeleccionadas.value = productosSeleccionados.value.reduce((sum, p) => sum + (p.Cantidad || 0), 0)
}

const validarCapacidad = () => {
    if (totalUnidadesSeleccionadas.value > capacidadTotalContenedor.value && capacidadTotalContenedor.value > 0) {
        excedeCapacidad.value = true
        errorMensaje.value = `⚠️ Máximo: ${formatearNumero(capacidadTotalContenedor.value)} und`
    } else {
        excedeCapacidad.value = false
        errorMensaje.value = ''
    }
}

const limpiarTodo = () => {
    productosSeleccionados.value.forEach(p => {
        p.Cantidad = 0
    })
    recalcularTotal()
    validarCapacidad()
    busquedaProducto.value = ''
    errorMensaje.value = ''
    excedeCapacidad.value = false
}

// ==================== CARGAR PRODUCTOS ====================
const cargarProductos = async () => {
    if (!props.contenedor) return
    if (!props.idIdentificador) {
        errorMensaje.value = '⚠️ Selecciona un cliente'
        return
    }
    
    loading.value = true
    errorCarga.value = false
    contenedorData.value = props.contenedor
    capacidadTotalContenedor.value = parseFloat(props.contenedor.CapacidadTotal) || 0
    busquedaProducto.value = ''
    productosSeleccionados.value = []
    totalUnidadesSeleccionadas.value = 0
    errorMensaje.value = ''
    excedeCapacidad.value = false

    try {
        // ✅ Pasar tipo_precio como query param
        const tipoPrecioParam = props.tipoPrecio || 'sin_factura'
        
        const response = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/contenedor/${props.contenedor.IdContenedor}/productos-precios`,
            {
                params: { tipo_precio: tipoPrecioParam } // ✅ NUEVO
            }
        )
        
        if (response.data.success) {
            let productosRaw = []
            
            if (response.data.data && response.data.data.productos_agrupados) {
                const agrupados = response.data.data.productos_agrupados
                agrupados.forEach(grupo => {
                    if (grupo.productos) {
                        grupo.productos.forEach(p => {
                            productosRaw.push({
                                IdProducto: p.IdProducto,
                                Codigo: p.Codigo,
                                Descripcion: p.Descripcion,
                                // ✅ Usar PrecioFinal del backend
                                PrecioFinal: p.PrecioFinal || 0,
                                tiene_precio: p.tiene_precio || false,
                                IdGrupoAnalisis: p.IdGrupoAnalisis,
                                GrupoAnalisis: grupo.grupo_nombre || 'Sin grupo',
                                CapacidadTotal: response.data.data.CapacidadTotal || 0,
                            })
                        })
                    }
                })
            }
            
            if (!productosRaw || productosRaw.length === 0) {
                productosSeleccionados.value = []
                return
            }
            
            productosSeleccionados.value = productosRaw.map(p => {
                let cantidad = 0
                let precio = p.PrecioFinal
                
                if (props.modoEdicion && props.datosEdicion) {
                    const existente = props.datosEdicion.productos.find(
                        ep => ep.IdProducto === p.IdProducto
                    )
                    if (existente) {
                        cantidad = parseInt(existente.Cantidad) || 0
                        // ✅ Usar el precio existente si está en edición
                        precio = existente.Precio || p.PrecioFinal
                    }
                }
                
                return {
                    ...p,
                    Cantidad: cantidad,
                    PrecioFinal: precio, // ✅ Precio correcto
                    CantidadMaxima: capacidadTotalContenedor.value
                }
            })
            
            recalcularTotal()
            validarCapacidad()
            
        } else {
            errorCarga.value = true
            errorMensaje.value = response.data.data?.mensaje || '❌ Error al cargar'
        }
    } catch (error) {
        console.error('Error:', error)
        errorCarga.value = true
        errorMensaje.value = '❌ Error al cargar productos'
    } finally {
        loading.value = false
    }
}

// ==================== AGREGAR / ACTUALIZAR ====================
const agregarAlCarrito = () => {
    if (!puedeAgregar.value) return

    const productosAgregar = productosSeleccionados.value
        .filter(p => p.Cantidad > 0 && p.tiene_precio)
        .map(p => ({
            IdProducto: p.IdProducto,
            Cantidad: p.Cantidad,
            Precio: p.PrecioFinal // ✅ Usar PrecioFinal
        }))

    if (props.modoEdicion && props.datosEdicion) {
        emit('actualizar', {
            IdPedidoCliente: props.datosEdicion.IdPedidoCliente,
            IdContenedor: contenedorData.value.IdContenedor,
            OrdenContenedor: props.datosEdicion.OrdenContenedor,
            productos: productosAgregar
        })
    } else {
        emit('agregar', {
            IdContenedor: contenedorData.value.IdContenedor,
            productos: productosAgregar,
            TipoPrecio: props.tipoPrecio // ✅ NUEVO
        })
    }
    
    cerrarModal()
}

const cerrarModal = () => {
    emit('close')
}

// ==================== WATCH ====================
watch(() => props.visible, (newVal) => {
    if (newVal && props.contenedor) {
        cargarProductos()
    }
}, { immediate: true })

watch(() => props.contenedor, (newVal) => {
    if (newVal && props.visible) {
        cargarProductos()
    }
})

watch(() => props.idIdentificador, (newVal) => {
    if (newVal && props.visible && props.contenedor) {
        cargarProductos()
    }
})

// ✅ Recargar cuando cambie tipo_precio
watch(() => props.tipoPrecio, (newVal) => {
    if (newVal && props.visible && props.contenedor) {
        cargarProductos()
    }
})
</script>
<template>
    <!-- Modal Overlay -->
    <div 
        v-if="visible"
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
        @click.self="cerrarModal"
    >
        <div class="bg-white rounded-xl w-full max-w-3xl max-h-[90vh] overflow-hidden shadow-xl animate-fade-in-up flex flex-col">
            
            <!-- HEADER -->
            <div class="px-4 py-3 border-b bg-primary-50 flex items-center justify-between flex-shrink-0">
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-gray-800 text-sm truncate">
                        <i class="fas fa-box text-primary-500 mr-2"></i>
                        {{ modoEdicion ? '✏️ Editando' : '' }} {{ contenedorData?.Codigo || 'Contenedor' }}
                        <span class="text-xs font-normal text-gray-500 ml-1">
                            (Cap: {{ formatearNumero(capacidadTotalContenedor) }} und)
                        </span>
                    </h3>
                    <p class="text-[10px] text-gray-400">
                        {{ modoEdicion ? 'Modifica las cantidades de los productos' : 'Selecciona las cantidades a pedir' }}
                    </p>
                    <!-- ✅ Mostrar tipo de precio -->
                    <span class="text-[9px] px-2 py-0.5 rounded-full font-medium mt-1 inline-block"
                        :class="tipoPrecio === 'con_factura' 
                            ? 'bg-blue-100 text-blue-700' 
                            : 'bg-gray-100 text-gray-600'">
                        <i :class="tipoPrecio === 'con_factura' ? 'fas fa-file-invoice-dollar' : 'fas fa-receipt'" class="mr-1"></i>
                        {{ tipoPrecio === 'con_factura' ? 'Con Factura' : 'Sin Factura' }}
                    </span>
                </div>
                <button 
                    @click="cerrarModal"
                    class="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-1.5 transition flex-shrink-0"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- BARRA DE PROGRESO -->
            <div class="px-4 py-2 bg-gray-50 border-b flex items-center gap-3 flex-shrink-0">
                <div class="flex-1">
                    <div class="flex justify-between text-[10px] text-gray-500">
                        <span>Usado: <strong class="text-primary-600">{{ totalUnidadesSeleccionadas }}</strong></span>
                        <span>Restante: <strong :class="totalRestante > 0 ? 'text-green-600' : 'text-red-500'">{{ totalRestante > 0 ? totalRestante : 0 }}</strong></span>
                        <span>Máx: <strong>{{ formatearNumero(capacidadTotalContenedor) }}</strong></span>
                    </div>
                    <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden mt-0.5">
                        <div 
                            class="h-full transition-all duration-300 rounded-full"
                            :class="colorBarra"
                            :style="{ width: Math.min(porcentajeCompletado, 100) + '%' }"
                        ></div>
                    </div>
                </div>
                <span v-if="estaCompleto" class="text-[10px] text-green-600 font-medium whitespace-nowrap">
                    <i class="fas fa-check-circle"></i> Completo
                </span>
            </div>

            <!-- CUERPO -->
            <div class="p-3 overflow-y-auto flex-1">
                
                <!-- Alertas -->
                <div v-if="!idIdentificador" class="mb-3 p-2 bg-yellow-50 border-l-4 border-yellow-400 rounded text-xs text-yellow-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Selecciona un cliente para ver los precios
                </div>

                <div v-if="errorCarga" class="mb-3 p-2 bg-red-50 border-l-4 border-red-400 rounded text-xs text-red-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ errorMensaje }}
                    <button @click="cargarProductos" class="ml-2 text-red-600 hover:text-red-800 underline">Reintentar</button>
                </div>

                <div v-if="excedeCapacidad && !errorCarga" class="mb-3 p-2 bg-red-50 border-l-4 border-red-400 rounded text-xs text-red-700 animate-shake">
                    <i class="fas fa-exclamation-circle mr-1"></i> {{ errorMensaje }}
                </div>

                <div v-if="!errorCarga && productosSeleccionados.length === 0 && idIdentificador" class="mb-3 p-2 bg-blue-50 border-l-4 border-blue-400 rounded text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i> No hay productos con precio para los grupos configurados
                </div>

                <!-- Buscador -->
                <div class="relative mb-3">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                    <input 
                        type="text"
                        v-model="busquedaProducto"
                        placeholder="Buscar producto..."
                        class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none transition bg-gray-50 focus:bg-white"
                        :disabled="loading || errorCarga || !idIdentificador"
                    />
                </div>

                <!-- Loading -->
                <div v-if="loading" class="flex justify-center items-center py-8">
                    <div class="w-8 h-8 border-3 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
                </div>

                <!-- Sin productos -->
                <div v-else-if="!errorCarga && productosSeleccionados.length === 0" class="text-center py-8 text-gray-400">
                    <i class="fas fa-box-open text-2xl block mb-2"></i>
                    <p class="text-xs">No hay productos disponibles</p>
                    <p class="text-[10px] text-gray-400 mt-1">Los grupos sin mínimo configurado no se muestran</p>
                </div>

                <!-- Lista de productos -->
                <div v-else-if="!errorCarga && productosSeleccionados.length > 0" class="space-y-1.5">
                    <div 
                        v-for="producto in productosFiltrados" 
                        :key="producto.IdProducto"
                        class="bg-gray-50 hover:bg-gray-100 rounded-lg p-2 transition-all duration-200 text-xs"
                        :class="{
                            'ring-1 ring-primary-300 bg-primary-50/50': producto.Cantidad > 0 && producto.tiene_precio
                        }"
                    >
                        <div class="grid grid-cols-12 gap-1 items-center">
                            <!-- Producto -->
                            <div class="col-span-12 sm:col-span-5 min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-[9px] font-mono text-gray-400 bg-white px-1.5 py-0.5 rounded">{{ producto.Codigo }}</span>
                                    <span class="font-medium text-gray-800 text-xs truncate">{{ producto.Descripcion }}</span>
                                    <span class="text-[8px] text-indigo-500 bg-indigo-50 px-1 py-0.5 rounded-full">{{ producto.GrupoAnalisis }}</span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                    <span class="text-[9px] text-gray-400">
                                        <i class="fas fa-arrow-up mr-0.5 text-[8px]"></i>
                                        Máx: {{ formatearNumero(capacidadTotalContenedor) }}
                                    </span>
                                    <span v-if="producto.Cantidad > 0 && producto.tiene_precio" class="text-[9px] text-primary-600 font-medium">
                                        <i class="fas fa-check-circle text-[8px]"></i> {{ formatearNumero(producto.Cantidad) }} und
                                    </span>
                                </div>
                            </div>

                            <!-- Precio - ✅ Corregido a PrecioFinal -->
                            <div class="col-span-3 sm:col-span-2 text-center">
                                <span v-if="producto.tiene_precio" class="text-green-600 font-medium text-xs">
                                    Bs. {{ Number(producto.PrecioFinal).toFixed(2) }}
                                </span>
                                <span v-else class="text-red-400 text-[9px]">Sin precio</span>
                            </div>

                            <!-- Cantidad -->
                            <div class="col-span-6 sm:col-span-3 flex items-center gap-0.5">
                                <button 
                                    @click="decrementarCantidad(producto)"
                                    :disabled="!producto.tiene_precio || producto.Cantidad <= 0"
                                    class="w-6 h-6 rounded-full bg-gray-200 hover:bg-gray-300 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center transition"
                                >
                                    <i class="fas fa-minus text-[8px]"></i>
                                </button>
                                <input 
                                    type="number"
                                    step="1"
                                    min="0"
                                    :max="capacidadTotalContenedor"
                                    :value="producto.Cantidad || 0"
                                    @input="actualizarCantidad(producto, $event)"
                                    @focus="$event.target.select()"
                                    :disabled="!producto.tiene_precio"
                                    class="w-full text-center border rounded px-1 py-0.5 text-xs focus:ring-1 focus:ring-primary-400 focus:border-transparent outline-none transition bg-white"
                                    :class="{
                                        'border-primary-300 bg-primary-50': producto.Cantidad > 0 && producto.tiene_precio,
                                        'border-gray-200': producto.Cantidad === 0 || !producto.tiene_precio
                                    }"
                                    placeholder="0"
                                />
                                <button 
                                    @click="incrementarCantidad(producto)"
                                    :disabled="!producto.tiene_precio"
                                    class="w-6 h-6 rounded-full bg-gray-200 hover:bg-gray-300 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center transition"
                                >
                                    <i class="fas fa-plus text-[8px]"></i>
                                </button>
                            </div>

                            <!-- Total - ✅ Corregido a PrecioFinal -->
                            <div class="col-span-3 sm:col-span-2 text-right font-bold text-xs">
                                <span v-if="producto.tiene_precio && producto.Cantidad > 0" class="text-primary-600">
                                    Bs. {{ (producto.Cantidad * producto.PrecioFinal).toFixed(2) }}
                                </span>
                                <span v-else class="text-gray-300">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="px-4 py-2.5 border-t bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-2 flex-shrink-0">
                <div class="text-[10px] text-gray-500 text-center sm:text-left">
                    <span v-if="!idIdentificador" class="text-yellow-600">
                        <i class="fas fa-info-circle mr-1"></i> Selecciona un cliente
                    </span>
                    <span v-else-if="productosAgregados.length === 0" class="text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i> Agrega productos para continuar
                    </span>
                    <span v-else class="text-green-600">
                        <i class="fas fa-check-circle mr-1"></i> {{ productosAgregados.length }} producto(s) · {{ totalUnidadesSeleccionadas }} und
                    </span>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button 
                        @click="limpiarTodo"
                        class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-[10px] transition flex-1 sm:flex-none"
                        :disabled="loading || errorCarga"
                    >
                        <i class="fas fa-eraser text-[8px] mr-1"></i> Limpiar
                    </button>
                    <button 
                        @click="agregarAlCarrito"
                        :disabled="!puedeAgregar || errorCarga || !idIdentificador"
                        class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-[10px] font-medium transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5 flex-1 sm:flex-none"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-save text-[10px]"></i>
                        {{ loading ? 'Cargando...' : (modoEdicion ? 'Actualizar' : 'Agregar') }}
                        <span v-if="productosAgregados.length > 0" class="bg-white/20 rounded-full px-1.5 py-0.5 text-[8px]">
                            {{ productosAgregados.length }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-3px); }
    75% { transform: translateX(3px); }
}

.animate-fade-in-up {
    animation: fadeInUp 0.2s ease-out;
}

.animate-shake {
    animation: shake 0.3s ease-in-out;
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
</style>