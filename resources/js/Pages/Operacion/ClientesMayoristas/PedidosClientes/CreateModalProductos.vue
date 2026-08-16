<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    contenedor: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['close', 'agregar'])

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

// ==================== COMPUTADOS ====================
const mostrar = computed({
    get: () => props.visible,
    set: (val) => {
        if (!val) emit('close')
    }
})

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
    return productosSeleccionados.value.filter(p => p.Cantidad > 0)
})

const totalRestante = computed(() => {
    return capacidadTotalContenedor.value - totalUnidadesSeleccionadas.value
})

const estaCompleto = computed(() => {
    return totalUnidadesSeleccionadas.value === capacidadTotalContenedor.value && capacidadTotalContenedor.value > 0
})

const puedeAgregar = computed(() => {
    return !loading.value && productosAgregados.value.length > 0 && !excedeCapacidad.value && totalUnidadesSeleccionadas.value > 0
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
    if (valor === undefined || valor === null || valor === '') {
        return '0'
    }
    const numero = parseFloat(valor)
    if (isNaN(numero)) {
        return '0'
    }
    return numero.toFixed(0)
}

// ==================== FUNCIONES DE CANTIDAD ====================
const actualizarCantidad = (producto, event) => {
    const input = event.target
    let valor = input.value.replace(/,/g, '.').trim()
    
    if (valor === '' || valor === '-') {
        producto.Cantidad = 0
        producto.selected = false
        recalcularTotal()
        validarCapacidad()
        return
    }
    
    let cantidad = parseFloat(valor)
    
    if (isNaN(cantidad) || cantidad < 0) {
        producto.Cantidad = 0
        producto.selected = false
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
    producto.selected = cantidad > 0
    recalcularTotal()
    validarCapacidad()
}

const recalcularTotal = () => {
    totalUnidadesSeleccionadas.value = productosSeleccionados.value.reduce((sum, p) => sum + (p.Cantidad || 0), 0)
}

const validarCapacidad = () => {
    if (totalUnidadesSeleccionadas.value > capacidadTotalContenedor.value && capacidadTotalContenedor.value > 0) {
        excedeCapacidad.value = true
        errorMensaje.value = `⚠️ Has superado la capacidad del contenedor. Máximo permitido: ${formatearNumero(capacidadTotalContenedor.value)} unidades.`
    } else {
        excedeCapacidad.value = false
        errorMensaje.value = ''
    }
}

const limpiarTodo = () => {
    productosSeleccionados.value.forEach(p => {
        p.Cantidad = 0
        p.selected = false
    })
    recalcularTotal()
    validarCapacidad()
    busquedaProducto.value = ''
    errorMensaje.value = ''
    excedeCapacidad.value = false
}

// ==================== CARGAR PRODUCTOS ====================
const cargarProductos = async () => {
    if (!props.contenedor) {
        console.warn('No hay contenedor seleccionado')
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
        const response = await axios.get(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/contenedor/${props.contenedor.IdContenedor}/productos`)
        
        console.log('📦 Respuesta del servidor:', response.data)
        
        if (response.data.success) {
            let productosRaw = []
            
            // 🔥 CASO 1: Tiene data.productos_agrupados
            if (response.data.data && response.data.data.productos_agrupados) {
                const agrupados = response.data.data.productos_agrupados
                agrupados.forEach(grupo => {
                    if (grupo.productos) {
                        grupo.productos.forEach(p => {
                            productosRaw.push({
                                IdProducto: p.IdProducto,
                                Codigo: p.Codigo,
                                Descripcion: p.Descripcion,
                                Precio: p.Precio || 0,
                                IdGrupoAnalisis: p.IdGrupoAnalisis,
                                GrupoAnalisis: grupo.grupo_nombre || 'Sin grupo'
                            })
                        })
                    }
                })
                console.log('✅ Productos extraídos de productos_agrupados:', productosRaw.length)
            }
            // 🔥 CASO 2: Tiene productos directamente (tu caso actual)
            else if (response.data.productos && Array.isArray(response.data.productos)) {
                productosRaw = response.data.productos.map(p => ({
                    IdProducto: p.IdProducto,
                    Codigo: p.Codigo,
                    Descripcion: p.Descripcion,
                    Precio: p.Precio || 0,
                    IdGrupoAnalisis: p.IdGrupoAnalisis || 0,
                    GrupoAnalisis: p.GrupoAnalisis || 'Sin grupo'
                }))
                console.log('✅ Productos extraídos de productos:', productosRaw.length)
            }
            // 🔥 CASO 3: Tiene data.productos
            else if (response.data.data && response.data.data.productos) {
                productosRaw = response.data.data.productos.map(p => ({
                    IdProducto: p.IdProducto,
                    Codigo: p.Codigo,
                    Descripcion: p.Descripcion,
                    Precio: p.Precio || 0,
                    IdGrupoAnalisis: p.IdGrupoAnalisis || 0,
                    GrupoAnalisis: p.GrupoAnalisis || 'Sin grupo'
                }))
                console.log('✅ Productos extraídos de data.productos:', productosRaw.length)
            }
            
            // ✅ Si no hay productos, mostrar mensaje
            if (!productosRaw || productosRaw.length === 0) {
                console.warn('⚠️ No se encontraron productos')
                productosSeleccionados.value = []
                return
            }
            
            // ✅ Agregar Cantidad a cada producto
            productosSeleccionados.value = productosRaw.map(p => ({
                ...p,
                Cantidad: 0,
                selected: false,
                CantidadMaxima: capacidadTotalContenedor.value
            }))
            
            console.log(`✅ ${productosSeleccionados.value.length} productos cargados`)
        } else {
            errorCarga.value = true
            errorMensaje.value = '❌ Error al cargar los productos: ' + (response.data.message || 'Error desconocido')
        }
    } catch (error) {
        console.error('❌ Error al cargar productos:', error)
        errorCarga.value = true
        errorMensaje.value = '❌ Error al cargar los productos. Intenta nuevamente.'
        
        if (error.response) {
            console.error('📡 Respuesta del error:', error.response.data)
        }
    } finally {
        loading.value = false
    }
}

// ==================== AGREGAR AL CARRITO ====================
const agregarAlCarrito = () => {
    if (!puedeAgregar.value) return

    const productosAgregar = productosSeleccionados.value
        .filter(p => p.Cantidad > 0)
        .map(p => ({
            IdProducto: p.IdProducto,
            Cantidad: p.Cantidad
        }))

    emit('agregar', {
        IdContenedor: contenedorData.value.IdContenedor,
        productos: productosAgregar
    })
    
    cerrarModal()
}

const cerrarModal = () => {
    emit('close')
}

// ==================== WATCH ====================
watch(
    () => props.visible,
    (newVal) => {
        if (newVal && props.contenedor) {
            cargarProductos()
        }
    },
    { immediate: true }
)

watch(
    () => props.contenedor,
    (newVal) => {
        if (newVal && props.visible) {
            cargarProductos()
        }
    }
)
</script>

<template>
    <!-- Modal Overlay -->
    <div 
        v-if="visible"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3 sm:p-4"
        @click.self="cerrarModal"
    >
        <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[95vh] overflow-hidden shadow-2xl animate-fade-in-up">
            
            <!-- Header -->
            <div class="p-4 sm:p-5 border-b bg-gradient-to-r from-primary-50 to-indigo-50">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-gray-800 text-base sm:text-lg truncate">
                            {{ contenedorData?.Codigo || 'Contenedor' }}
                            <span class="text-xs font-normal text-gray-500 ml-2">
                                {{ contenedorData?.TipoContenedor || '' }}
                            </span>
                        </h3>
                        <p class="text-xs text-gray-500">
                            <span class="font-mono bg-white/50 px-2 py-0.5 rounded">{{ contenedorData?.Codigo }}</span>
                            <span class="mx-1">•</span>
                            Capacidad: {{ formatearNumero(contenedorData?.CapacidadTotal || 0) }} und
                            <span class="mx-1">•</span>
                            <span class="text-primary-600">{{ productosSeleccionados.length }} productos</span>
                        </p>
                    </div>
                    <button 
                        @click="cerrarModal"
                        class="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-1.5 transition flex-shrink-0"
                    >
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                
                <!-- Barra de progreso -->
                <div class="mt-3">
                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                        <span>
                            <i class="fas fa-weight-hanging mr-1 text-primary-500"></i>
                            Usado: <strong class="text-primary-600">{{ totalUnidadesSeleccionadas }}</strong>
                        </span>
                        <span>
                            <span class="text-gray-400">Restante:</span>
                            <strong :class="totalRestante > 0 ? 'text-green-600' : 'text-red-500'">
                                {{ totalRestante > 0 ? totalRestante : 0 }}
                            </strong>
                        </span>
                        <span>
                            <span class="text-gray-400">Total:</span>
                            <strong>{{ formatearNumero(capacidadTotalContenedor) }}</strong>
                        </span>
                    </div>
                    <div class="w-full h-2.5 bg-gray-200 rounded-full overflow-hidden">
                        <div 
                            class="h-full transition-all duration-300 ease-in-out rounded-full"
                            :class="colorBarra"
                            :style="{ width: Math.min(porcentajeCompletado, 100) + '%' }"
                        ></div>
                    </div>
                    <div class="flex justify-between text-[10px] text-gray-400 mt-0.5">
                        <span>{{ productosAgregados.length }} producto(s) seleccionado(s)</span>
                        <span v-if="estaCompleto" class="text-green-600 font-medium">
                            <i class="fas fa-check-circle"></i> ¡Completo!
                        </span>
                        <span v-else-if="excedeCapacidad" class="text-red-500 font-medium">
                            <i class="fas fa-exclamation-circle"></i> Excedido
                        </span>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="p-3 sm:p-5 overflow-y-auto" style="max-height: calc(95vh - 240px);">
                
                <!-- Mensaje de error de carga -->
                <div v-if="errorCarga" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-start gap-2">
                    <i class="fas fa-exclamation-triangle mt-0.5 text-red-500"></i>
                    <div>
                        <p class="font-medium">Error al cargar productos</p>
                        <p class="text-xs text-red-600">{{ errorMensaje }}</p>
                        <button 
                            @click="cargarProductos"
                            class="mt-2 text-xs bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded transition"
                        >
                            <i class="fas fa-sync mr-1"></i> Reintentar
                        </button>
                    </div>
                </div>

                <!-- Mensaje de error de capacidad -->
                <div v-if="excedeCapacidad && !errorCarga" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-start gap-2 animate-shake">
                    <i class="fas fa-exclamation-triangle mt-0.5 text-red-500"></i>
                    <div>
                        <p class="font-medium">¡Capacidad excedida!</p>
                        <p class="text-xs text-red-600">{{ errorMensaje }}</p>
                    </div>
                </div>

                <!-- Mensaje de éxito -->
                <div v-else-if="estaCompleto && !excedeCapacidad && !errorCarga" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-start gap-2">
                    <i class="fas fa-check-circle mt-0.5 text-green-500"></i>
                    <div>
                        <p class="font-medium">¡Contenedor completo!</p>
                        <p class="text-xs text-green-600">Has alcanzado la capacidad máxima de {{ formatearNumero(capacidadTotalContenedor) }} unidades.</p>
                    </div>
                </div>

                <!-- Buscador -->
                <div class="relative mb-4">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input 
                        type="text"
                        v-model="busquedaProducto"
                        placeholder="Buscar producto por código o nombre..."
                        class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none transition bg-gray-50 focus:bg-white"
                        :disabled="loading || errorCarga"
                    />
                    <button 
                        v-if="busquedaProducto"
                        @click="busquedaProducto = ''"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>

                <!-- Loading -->
                <div v-if="loading" class="flex flex-col items-center justify-center py-12">
                    <div class="w-12 h-12 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
                    <p class="text-sm text-gray-400 mt-3">Cargando productos...</p>
                </div>

                <!-- Sin productos -->
                <div v-else-if="!errorCarga && productosSeleccionados.length === 0" class="text-center py-12 text-gray-400">
                    <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-box-open text-2xl text-gray-300"></i>
                    </div>
                    <p class="text-sm font-medium">No hay productos disponibles</p>
                    <p class="text-xs mt-1">Este contenedor no tiene grupos de análisis asignados</p>
                </div>

                <!-- Lista de productos SIMPLE -->
                <div v-else-if="!errorCarga && productosSeleccionados.length > 0" class="space-y-2">
                    <div 
                        v-for="producto in productosFiltrados" 
                        :key="producto.IdProducto"
                        class="bg-gray-50 hover:bg-gray-100 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-all duration-200"
                        :class="{'ring-2 ring-primary-300 bg-primary-50/50': producto.Cantidad > 0}"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-mono text-gray-400 bg-white px-2 py-0.5 rounded">{{ producto.Codigo }}</span>
                                <span class="text-sm font-medium text-gray-800 truncate">{{ producto.Descripcion }}</span>
                                <span class="text-[10px] text-indigo-500 bg-indigo-50 px-1.5 py-0.5 rounded-full">{{ producto.GrupoAnalisis }}</span>
                            </div>
                            <div class="flex items-center gap-3 mt-0.5">
                                <span class="text-[10px] text-gray-400">
                                    <i class="fas fa-arrow-up mr-0.5"></i>
                                    Máx: {{ formatearNumero(capacidadTotalContenedor) }} und
                                </span>
                                <span v-if="producto.Cantidad > 0" class="text-[10px] text-primary-600 font-medium">
                                    <i class="fas fa-check-circle"></i> {{ producto.Cantidad }} und
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <input 
                                type="number"
                                step="any"
                                min="0"
                                :max="capacidadTotalContenedor"
                                :value="producto.Cantidad || 0"
                                @input="actualizarCantidad(producto, $event)"
                                @focus="$event.target.select()"
                                class="w-full sm:w-32 text-right border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none transition bg-white"
                                :class="{
                                    'border-primary-400 bg-primary-50': producto.Cantidad > 0,
                                    'border-red-300': producto.Cantidad > capacidadTotalContenedor
                                }"
                                placeholder="0"
                            />
                            <span class="text-xs text-gray-400 w-6 text-center hidden sm:block">und</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 border-t bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="text-xs sm:text-sm text-gray-600 text-center sm:text-left">
                    <span v-if="errorCarga" class="text-red-600 font-medium">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        Error al cargar, reintenta
                    </span>
                    <span v-else-if="excedeCapacidad" class="text-red-600 font-medium">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        Reduce las cantidades para continuar
                    </span>
                    <span v-else-if="productosAgregados.length === 0" class="text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i>
                        Selecciona productos para agregar
                    </span>
                    <span v-else class="text-green-600">
                        <i class="fas fa-check-circle mr-1"></i>
                        {{ productosAgregados.length }} producto(s) listos
                    </span>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button 
                        @click="limpiarTodo"
                        class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm transition flex items-center gap-1.5 flex-1 sm:flex-none justify-center"
                        :disabled="loading || errorCarga"
                    >
                        <i class="fas fa-eraser text-xs"></i>
                        Limpiar
                    </button>
                    <button 
                        @click="agregarAlCarrito"
                        :disabled="!puedeAgregar || errorCarga"
                        class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 flex-1 sm:flex-none justify-center"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-cart-plus"></i>
                        {{ loading ? 'Cargando...' : 'Agregar al Carrito' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.animate-fade-in-up {
    animation: fadeInUp 0.25s ease-out;
}

.animate-shake {
    animation: shake 0.4s ease-in-out;
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
    width: 6px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 8px;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 8px;
}
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

[v-show] {
    transition: all 0.2s ease;
}
</style>