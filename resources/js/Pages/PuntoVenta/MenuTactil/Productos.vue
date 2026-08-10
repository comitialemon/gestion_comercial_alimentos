<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, inject, onMounted } from 'vue'
import axios from 'axios'
import NavBarTactil from '../Components/NavBarTactil.vue'
import ModalCambioProducto from './ModalCambioProducto.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    categoria: Object,
    productos: Array,
    ruta: Array,
    comisionista: String
})

const loading = ref(false)
const carrito = ref([])

// Modal principal
const modalVisible = ref(false)
const productoSeleccionado = ref(null)
const cantidad = ref(1)
const precioUnitario = ref(0)
const tipoPrecio = ref('')
const tieneOpcionesCambio = ref(false)
const tipoProductoActual = ref('normal')

// Modal de personalización
const modalPersonalizarVisible = ref(false)
const comboActual = ref(null)
const cantidadCombos = ref(1)
const personalizacionesTemp = ref([])
const opcionesAgrupadas = ref([])

const totalModal = computed(() => (cantidad.value * precioUnitario.value).toFixed(2))
const totalCarrito = computed(() => carrito.value.reduce((sum, item) => sum + (item.precio * item.cantidad), 0).toFixed(2))
const totalItems = computed(() => carrito.value.reduce((sum, item) => sum + item.cantidad, 0))

// 🔥 Etiqueta dinámica para el botón de personalizar
const etiquetaPersonalizar = computed(() => {
    if (tipoProductoActual.value === 'pack') return 'Personalizar pack'
    if (tipoProductoActual.value === 'combo') return 'Personalizar combo'
    if (tipoProductoActual.value === 'con_opciones') return 'Personalizar producto'
    return 'Personalizar'
})

// Cargar carrito
const cargarCarrito = async () => {
    try {
        const response = await axios.get('/api/venta-tactil/carrito')
        if (response.data?.success) {
            carrito.value = (response.data.items || []).map(item => ({
                id: item.id,
                id_producto: item.id_producto,
                nombre: item.nombre,
                precio: parseFloat(item.precio),
                cantidad: item.unidades,
                subtotal: parseFloat(item.subtotal),
                personalizacion: item.personalizacion
            }))
        }
    } catch (error) {
        console.error('Error cargando carrito:', error)
    }
}

// 🔥 VERIFICAR OPCIONES (UNIVERSAL)
const verificarOpcionesProducto = async (producto) => {
    try {
        // Usar el endpoint correcto
        const response = await axios.get(`/venta-tactil/combo/${producto.id}`)
        console.log('📡 Respuesta de opciones:', response.data)
        
        if (response.data.success) {
            const data = response.data.producto || response.data
            const tieneOpciones = data.tiene_opciones || (data.opciones && data.opciones.length > 0)
            
            if (tieneOpciones) {
                tipoProductoActual.value = data.tipo || 'normal'
                producto.tipo_producto = data.tipo || 'normal'
                producto.opciones_data = data.opciones || []
                producto.composicion_data = data.composicion || []
            }
            
            return tieneOpciones
        }
        return false
    } catch (error) {
        console.error('❌ Error verificando opciones:', error)
        return false
    }
}

// 🔥 Cargar opciones del producto
const cargarOpcionesProducto = async (idProducto) => {
    try {
        const response = await axios.get(`/venta-tactil/combo/${idProducto}`)
        if (response.data.success) {
            const data = response.data.producto || response.data
            const opciones = data.opciones || []
            
            if (opciones.length > 0) {
                const agrupadas = {}
                opciones.forEach(op => {
                    if (!agrupadas[op.id_producto_original]) {
                        agrupadas[op.id_producto_original] = {
                            id_producto_original: op.id_producto_original,
                            nombre_original: op.nombre_original,
                            cantidad_total: 1,
                            opciones: []
                        }
                    }
                    agrupadas[op.id_producto_original].opciones.push({
                        id_sustituto: op.id_producto_sustituto,
                        nombre: op.nombre_sustituto,
                        codigo: op.codigo_sustituto || '',
                        cantidad_maxima: op.cantidad_maxima || 1,
                        es_default: op.es_default === 1
                    })
                })
                return Object.values(agrupadas)
            }
        }
        return []
    } catch (error) {
        console.error('Error cargando opciones:', error)
        return []
    }
}

// 🔥 Cargar detalles completos del producto
const cargarDetallesProducto = async (idProducto) => {
    try {
        const response = await axios.get(`/venta-tactil/combo/${idProducto}`)
        if (response.data.success) {
            const data = response.data.producto || response.data
            return {
                composicion: data.composicion || [],
                opciones: data.opciones || [],
                tipo: data.tipo || 'normal'
            }
        }
        return null
    } catch (error) {
        console.error('Error cargando detalles:', error)
        return null
    }
}

// 🔥 Preparar opciones agrupadas (con composición)
const prepararOpcionesAgrupadas = (productoCompleto) => {
    if (!productoCompleto) return []
    
    const grupos = {}
    
    productoCompleto.composicion?.forEach(item => {
        const idOriginal = item.id_producto
        
        const opcionesDelProducto = productoCompleto.opciones?.filter(
            op => op.id_producto_original === idOriginal
        ) || []
        
        grupos[idOriginal] = {
            id_producto_original: idOriginal,
            nombre_original: item.nombre || 'Producto',
            cantidad_total: item.porcion || 1,
            opciones: opcionesDelProducto.map(op => ({
                id_sustituto: op.id_producto_sustituto,
                nombre: op.nombre_sustituto,
                codigo: op.codigo_sustituto || '',
                cantidad_maxima: op.cantidad_maxima || item.porcion || 1
            }))
        }
    })
    
    if (Object.keys(grupos).length === 0 && productoCompleto.opciones?.length) {
        productoCompleto.opciones.forEach(op => {
            if (!grupos[op.id_producto_original]) {
                grupos[op.id_producto_original] = {
                    id_producto_original: op.id_producto_original,
                    nombre_original: op.nombre_original || 'Producto',
                    cantidad_total: 1,
                    opciones: []
                }
            }
            grupos[op.id_producto_original].opciones.push({
                id_sustituto: op.id_producto_sustituto,
                nombre: op.nombre_sustituto,
                codigo: op.codigo_sustituto || '',
                cantidad_maxima: op.cantidad_maxima || 1
            })
        })
    }
    
    return Object.values(grupos)
}

// Obtener día actual en español
const obtenerDiaActual = () => {
    const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
    return dias[new Date().getDay()]
}

// 🔥 ABRIR MODAL
const abrirModal = async (producto) => {
    if (!producto?.id) {
        toast?.error('Error', 'Producto inválido')
        return
    }
    
    console.log('🔍 Abriendo modal para:', producto.nombre, 'ID:', producto.id)
    console.log('📊 tiene_opciones:', producto.tiene_opciones)
    console.log('📊 tipo_producto:', producto.tipo_producto)
    
    // Verificar disponibilidad
    if (producto.disponible_hoy === false) {
        const diaActual = obtenerDiaActual()
        const diasDisponibles = producto.dias_disponibles || 'ningún día configurado'
        toast?.error(
            `❌ ${producto.nombre} no disponible hoy`,
            `📅 Solo disponible: ${diasDisponibles}\n📌 Hoy es ${diaActual}`,
            { duration: 5000 }
        )
        return
    }
    
    productoSeleccionado.value = producto
    cantidad.value = 1
    precioUnitario.value = producto.precio_real
    tipoPrecio.value = producto.tipo_precio || 'default'
    
    // 🔥 USAR LOS DATOS DEL BACKEND (más rápido)
    if (producto.tiene_opciones !== undefined) {
        tieneOpcionesCambio.value = producto.tiene_opciones
        tipoProductoActual.value = producto.tipo_producto || 'normal'
        
        if (producto.tiene_opciones) {
            producto.opciones_data = producto.opciones_data || []
            producto.composicion_data = producto.composicion_data || []
        }
    } else {
        const tieneOpciones = await verificarOpcionesProducto(producto)
        tieneOpcionesCambio.value = tieneOpciones
    }
    
    console.log('✅ Resultado:', {
        tieneOpcionesCambio: tieneOpcionesCambio.value,
        tipoProductoActual: tipoProductoActual.value
    })
    
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    setTimeout(() => {
        productoSeleccionado.value = null
        tieneOpcionesCambio.value = false
        tipoProductoActual.value = 'normal'
    }, 200)
}

// 🔥 ABRIR PERSONALIZACIÓN (UNIVERSAL)
const abrirPersonalizacionProducto = async () => {
    comboActual.value = productoSeleccionado.value
    cantidadCombos.value = cantidad.value
    
    let productoCompleto = null
    
    if (productoSeleccionado.value.composicion_data && productoSeleccionado.value.composicion_data.length > 0) {
        productoCompleto = {
            id: productoSeleccionado.value.id,
            nombre: productoSeleccionado.value.nombre,
            composicion: productoSeleccionado.value.composicion_data,
            opciones: productoSeleccionado.value.opciones_data || [],
            tipo: productoSeleccionado.value.tipo_producto || 'normal'
        }
    } else {
        try {
            const response = await axios.get(`/venta-tactil/combo/${productoSeleccionado.value.id}`)
            if (response.data.success) {
                const data = response.data.producto || response.data
                productoCompleto = {
                    id: productoSeleccionado.value.id,
                    nombre: productoSeleccionado.value.nombre,
                    composicion: data.composicion || [],
                    opciones: data.opciones || [],
                    tipo: data.tipo || 'normal'
                }
            }
        } catch (error) {
            console.error('Error cargando detalles:', error)
        }
    }
    
    if (productoCompleto) {
        comboActual.value = {
            ...productoSeleccionado.value,
            composicion: productoCompleto.composicion || [],
            opciones: productoCompleto.opciones || [],
            tipo_producto: productoCompleto.tipo || 'normal',
            precio_real: productoSeleccionado.value.precio_real || 0
        }
        
        opcionesAgrupadas.value = prepararOpcionesAgrupadas(productoCompleto)
        
        console.log('📦 comboActual:', comboActual.value)
        console.log('📦 opcionesAgrupadas:', opcionesAgrupadas.value)
    } else {
        opcionesAgrupadas.value = await cargarOpcionesProducto(productoSeleccionado.value.id)
    }
    
    personalizacionesTemp.value = []
    for (let i = 0; i < cantidadCombos.value; i++) {
        personalizacionesTemp.value.push({ sustitutos: [] })
    }
    
    modalVisible.value = false
    modalPersonalizarVisible.value = true
}

// Agregar producto con opciones POR DEFECTO
const agregarProductoDefault = async () => {
    if (cantidad.value < 1) {
        toast?.warning('Cantidad inválida', 'La cantidad debe ser al menos 1')
        return
    }
    
    loading.value = true
    try {
        const personalizacionesDefault = []
        for (let i = 0; i < cantidad.value; i++) {
            personalizacionesDefault.push({ sustitutos: [] })
        }
        
        const response = await axios.post('/api/venta-tactil/agregar-combo', {
            id_combo: productoSeleccionado.value.id,
            personalizaciones: personalizacionesDefault,
            cantidad_total: cantidad.value,
            precio_unitario: precioUnitario.value,
            tipo_producto: tipoProductoActual.value
        })
        if (response.data.success) {
            await cargarCarrito()
            const etiqueta = tipoProductoActual.value === 'pack' ? 'Pack' : 
                           tipoProductoActual.value === 'combo' ? 'Combo' : 'Producto'
            toast?.success(`¡${etiqueta} agregado!`, `${productoSeleccionado.value.nombre} x ${cantidad.value}`)
            cerrarModal()
        } else {
            toast?.error('Error', response.data.message || 'Error al agregar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al agregar')
    } finally {
        loading.value = false
    }
}

// Agregar productos personalizados al carrito
const agregarProductosPersonalizados = async (personalizaciones) => {
    loading.value = true
    try {
        const response = await axios.post('/api/venta-tactil/agregar-combo', {
            id_combo: comboActual.value.id,
            personalizaciones: personalizaciones,
            cantidad_total: personalizaciones.length,
            precio_unitario: precioUnitario.value,
            tipo_producto: comboActual.value.tipo_producto || 'normal'
        })
        if (response.data.success) {
            await cargarCarrito()
            const etiqueta = comboActual.value.tipo_producto === 'pack' ? 'Pack' : 
                           comboActual.value.tipo_producto === 'combo' ? 'Combo' : 'Producto'
            toast?.success(`¡${etiqueta}s agregados!`, `${comboActual.value.nombre} x ${personalizaciones.length}`)
            cerrarModalPersonalizacion()
        } else {
            toast?.error('Error', response.data.message || 'Error al agregar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al agregar')
    } finally {
        loading.value = false
    }
}

// Agregar producto simple al carrito
const agregarProductoSimpleAlCarrito = async () => {
    if (cantidad.value < 1) {
        toast?.warning('Cantidad inválida', 'La cantidad debe ser al menos 1')
        return
    }
    
    loading.value = true
    try {
        const response = await axios.post('/api/venta-tactil/agregar', {
            id_producto: productoSeleccionado.value.id,
            unidades: cantidad.value,
            precio: precioUnitario.value
        })
        if (response.data.success) {
            await cargarCarrito()
            toast?.success('¡Producto agregado!', `${productoSeleccionado.value.nombre} x ${cantidad.value}`)
            cerrarModal()
        } else {
            toast?.error('Error', response.data.message || 'Error al agregar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al agregar')
    } finally {
        loading.value = false
    }
}

const cerrarModalPersonalizacion = () => {
    modalPersonalizarVisible.value = false
    comboActual.value = null
    opcionesAgrupadas.value = []
    personalizacionesTemp.value = []
}

const incrementarCantidad = () => cantidad.value++
const decrementarCantidad = () => { if (cantidad.value > 1) cantidad.value-- }
const validarCantidad = () => { 
    let val = parseInt(cantidad.value) 
    if (isNaN(val) || val < 1) val = 1
    cantidad.value = val 
}

onMounted(() => cargarCarrito())
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto px-3 py-3">
            
            <NavBarTactil 
                :comisionista="comisionista || 'Sin comisionista'"
                :ruta="ruta"
                :mostrar-ruta="true"
                :mostrar-cancelar="true"
            />

            <!-- Categoría con imagen -->
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center shadow-sm overflow-hidden">
                    <img v-if="categoria?.imagen_url" :src="categoria.imagen_url" class="w-full h-full object-cover rounded-xl">
                    <i v-else class="fas fa-tag text-primary-500 text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-primary-800">{{ categoria?.nombre }}</h1>
                    <p class="text-[11px] text-gray-400">{{ productos.length }} productos disponibles</p>
                </div>
            </div>

            <!-- Grid de productos -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <div 
                    v-for="prod in productos" 
                    :key="prod.id"
                    @click="abrirModal(prod)"
                    class="bg-white rounded-lg shadow-sm hover:shadow-md transition cursor-pointer overflow-hidden border border-gray-100 relative"
                    :class="{ 'opacity-60 grayscale cursor-not-allowed': !prod.disponible_hoy }"
                >
                    <!-- Badge de No disponible -->
                    <div v-if="!prod.disponible_hoy" class="absolute top-0 right-0 bg-red-500 text-white text-[8px] px-2 py-0.5 rounded-bl-lg font-medium z-10">
                        No disponible
                    </div>
                    
                    <div class="h-20 bg-gradient-to-br from-primary-50 to-secondary-50 flex items-center justify-center overflow-hidden">
                        <img 
                            v-if="prod.imagen_url" 
                            :src="prod.imagen_url" 
                            :srcset="prod.imagen_srcset"
                            :sizes="prod.imagen_sizes"
                            class="w-full h-full object-cover"
                            loading="lazy"
                            decoding="async"
                            :alt="prod.nombre"
                        >
                        <i v-else class="fas fa-box-open text-2xl text-primary-400"></i>
                    </div>
                    <div class="p-2 text-center">
                        <h3 class="font-medium text-xs text-gray-800 line-clamp-2 min-h-[32px]">{{ prod.nombre }}</h3>
                        <div class="mt-1 flex flex-col items-center">
                            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-bold"
                                :class="prod.tipo_precio === 'mayorista' ? 'bg-secondary-100 text-secondary-700' : 'bg-primary-100 text-primary-700'"
                            >
                                {{ Number(prod.precio_real).toFixed(2) }} Bs
                            </span>
                            <span v-if="prod.precio_real !== prod.precio_normal" class="text-[9px] text-gray-400 line-through mt-0.5">
                                {{ Number(prod.precio_normal).toFixed(2) }} Bs
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!productos.length" class="text-center text-gray-400 py-10">
                <i class="fas fa-box-open text-3xl mb-2 block"></i>
                <p class="text-sm">No hay productos en esta categoría</p>
            </div>

            <!-- Modal principal -->
            <div v-if="modalVisible" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-3" @click.self="cerrarModal">
                <div class="bg-white rounded-xl max-w-sm w-full overflow-hidden shadow-xl">
                    <div class="bg-primary-700 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center overflow-hidden">
                                <img 
                                    v-if="productoSeleccionado?.imagen_url" 
                                    :src="productoSeleccionado.imagen_url" 
                                    class="w-full h-full object-cover"
                                >
                                <i v-else class="fas fa-box-open text-primary-600 text-sm"></i>
                            </div>
                            <div class="text-white flex-1">
                                <h3 class="font-bold text-sm">{{ productoSeleccionado?.nombre }}</h3>
                                <p class="text-[10px] opacity-75">
                                    {{ tipoPrecio === 'mayorista' ? 'Precio Mayorista' : tipoPrecio === 'sucursal' ? 'Precio Sucursal' : 'Precio Normal' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4">
                        <!-- Precio -->
                        <div class="text-center mb-3">
                            <span class="text-2xl font-bold text-primary-700">{{ Number(precioUnitario).toFixed(2) }}</span>
                            <span class="text-gray-400 text-xs ml-0.5">Bs c/u</span>
                        </div>
                        
                        <!-- Cantidad -->
                        <div class="flex items-center justify-center gap-3 mb-4">
                            <button @click="decrementarCantidad" class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold hover:bg-primary-200">-</button>
                            <input 
                                type="number" 
                                v-model.number="cantidad" 
                                @input="validarCantidad"
                                min="1" 
                                class="w-14 text-center text-lg font-bold border rounded-lg py-1 focus:border-primary-400 focus:outline-none"
                                style="appearance: textfield; -moz-appearance: textfield;"
                            >
                            <button @click="incrementarCantidad" class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold hover:bg-primary-200">+</button>
                        </div>
                        
                        <!-- Total -->
                        <div class="bg-secondary-50 rounded-lg p-2 mb-4 text-center">
                            <p class="text-[10px] text-secondary-700">Total</p>
                            <p class="text-lg font-bold text-primary-700">{{ totalModal }} Bs</p>
                        </div>
                        
                        <!-- Botones acción -->
                        <div class="flex flex-col gap-2">
                            <!-- 🔥 Botón Personalizar (UNIVERSAL) -->
                            <button 
                                v-if="tieneOpcionesCambio"
                                @click="abrirPersonalizacionProducto" 
                                :disabled="loading" 
                                class="w-full py-2 rounded-lg bg-amber-100 text-amber-700 text-sm font-medium hover:bg-amber-200 transition flex items-center justify-center gap-2"
                            >
                                <i class="fas fa-exchange-alt text-xs"></i>
                                {{ etiquetaPersonalizar }}
                            </button>
                            
                            <!-- Botón AGREGAR para productos con opciones -->
                            <button 
                                v-if="tieneOpcionesCambio"
                                @click="agregarProductoDefault" 
                                :disabled="loading" 
                                class="w-full py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium flex items-center justify-center gap-1"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else class="fas fa-cart-plus text-xs"></i>
                                {{ loading ? '' : `Agregar al carrito (${cantidad})` }}
                            </button>
                            
                            <!-- Botón AGREGAR para productos sin opciones -->
                            <button 
                                v-if="!tieneOpcionesCambio"
                                @click="agregarProductoSimpleAlCarrito" 
                                :disabled="loading" 
                                class="w-full py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium flex items-center justify-center gap-1"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else class="fas fa-cart-plus text-xs"></i>
                                {{ loading ? '' : `Agregar al carrito (${cantidad})` }}
                            </button>
                            
                            <button @click="cerrarModal" class="w-full py-2 rounded-lg bg-gray-100 text-gray-600 text-sm hover:bg-gray-200">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🔥 Modal de Personalización Universal -->
            <ModalCambioProducto
                v-model:visible="modalPersonalizarVisible"
                :producto="comboActual"
                :opciones="opcionesAgrupadas"
                :cantidad="cantidadCombos"
                :tipo-producto="comboActual?.tipo_producto || 'normal'"
                @confirm="agregarProductosPersonalizados"
                @close="cerrarModalPersonalizacion"
            />

            <!-- Carrito flotante -->
            <div v-if="totalItems > 0" class="fixed bottom-3 left-3 bg-white rounded-lg shadow-md p-2 border-l-3 border-primary-500">
                <div class="flex items-center gap-2">
                    <div class="bg-primary-100 rounded-full w-7 h-7 flex items-center justify-center">
                        <i class="fas fa-shopping-cart text-primary-600 text-xs"></i>
                    </div>
                    <div class="text-[11px]">
                        <p class="text-gray-500 leading-tight">Total</p>
                        <p class="font-bold text-primary-600">{{ totalCarrito }} Bs</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

input[type="number"] {
    -webkit-appearance: textfield;
    -moz-appearance: textfield;
    appearance: textfield;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>