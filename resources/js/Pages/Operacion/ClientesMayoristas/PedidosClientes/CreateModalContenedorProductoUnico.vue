<script setup>
import { ref, computed, watch, inject, nextTick, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    visible: { type: Boolean, default: false },
    contenedor: { type: Object, default: null },
    idIdentificador: { type: [Number, String], default: null },
    tipoPrecio: { type: String, default: 'sin_factura' },
    subclientes: { type: Array, default: () => [] },
    idSubClienteOperadorDefault: { type: [Number, String], default: null },
    nombreOperador: { type: String, default: '' },
    modoEdicion: { type: Boolean, default: false },
    datosEdicion: { type: Object, default: null }
})

const emit = defineEmits(['close', 'agregar', 'actualizar'])

const toast = inject('toast', null)

// ==================== ESTADO ====================
const loading = ref(false)
const productos = ref([])
const productoSeleccionado = ref(null)
const cantidadRejillas = ref(1)
const busquedaProducto = ref('')
const errorCarga = ref(false)
const errorMensaje = ref('')

// Subcliente
const subClienteSeleccionado = ref(null)
const busquedaSubCliente = ref('')
const mostrarDropdownSubCliente = ref(false)
const subClienteDropdownRef = ref(null)

// ==================== COMPUTED ====================
const productosFiltrados = computed(() => {
    if (!busquedaProducto.value || busquedaProducto.value.length < 2) {
        return productos.value
    }
    const termino = busquedaProducto.value.toLowerCase().trim()
    return productos.value.filter(p =>
        p.Descripcion?.toLowerCase().includes(termino) ||
        p.Codigo?.toLowerCase().includes(termino)
    )
})

const cantidadPorRejilla = computed(() => {
    if (!productoSeleccionado.value) return 0
    return Number(productoSeleccionado.value.MinimoProducto) || 0
})

const totalUnidades = computed(() => {
    return (Number(cantidadRejillas.value) || 0) * cantidadPorRejilla.value
})

const totalMonto = computed(() => {
    if (!productoSeleccionado.value) return 0
    return totalUnidades.value * Number(productoSeleccionado.value.PrecioFinal || 0)
})

const puedeAgregar = computed(() => {
    return !loading.value
        && productoSeleccionado.value !== null
        && Number(cantidadRejillas.value) >= 1
        && cantidadPorRejilla.value > 0
})

const subClientePropio = computed(() => {
    if (!props.subclientes?.length || !props.idIdentificador) return null
    return props.subclientes.find(
        s => Number(s.IdIdentificador) === Number(props.idIdentificador)
    ) || null
})

const esPropioOperador = computed(() => {
    if (!subClienteSeleccionado.value || !subClientePropio.value) return false
    return Number(subClienteSeleccionado.value) === Number(subClientePropio.value.IdSubClienteOperador)
})

const subClienteNombreActual = computed(() => {
    if (!subClienteSeleccionado.value) return 'Sin subcliente'
    const sub = props.subclientes.find(
        s => Number(s.IdSubClienteOperador) === Number(subClienteSeleccionado.value)
    )
    return sub ? sub.Nombre : 'Sin subcliente'
})

const subClientesFiltrados = computed(() => {
    if (!busquedaSubCliente.value || busquedaSubCliente.value.length < 1) {
        return props.subclientes
    }
    const termino = busquedaSubCliente.value.toLowerCase().trim()
    return props.subclientes.filter(s => {
        const nombre = String(s.Nombre || '').toLowerCase()
        const ciNit = String(s.CI_NIT || '').toLowerCase()
        const alias = String(s.Alias || '').toLowerCase()
        return nombre.includes(termino) || ciNit.includes(termino) || alias.includes(termino)
    })
})

// ==================== HELPERS ====================
const formatearNumero = (valor) => {
    if (valor === undefined || valor === null || valor === '') return '0'
    const numero = parseInt(valor)
    return isNaN(numero) ? '0' : numero.toFixed(0)
}

const formatearPrecio = (valor) => {
    if (valor === undefined || valor === null || valor === '') return '0.00'
    const numero = parseFloat(valor)
    return isNaN(numero) ? '0.00' : numero.toFixed(2)
}

// ==================== INICIALIZAR SUBCLIENTE ====================
const inicializarSubCliente = () => {
    if (props.modoEdicion && props.datosEdicion?.IdSubClienteOperador) {
        subClienteSeleccionado.value = Number(props.datosEdicion.IdSubClienteOperador)
        return
    }
    if (props.idSubClienteOperadorDefault) {
        subClienteSeleccionado.value = Number(props.idSubClienteOperadorDefault)
        return
    }
    if (subClientePropio.value) {
        subClienteSeleccionado.value = Number(subClientePropio.value.IdSubClienteOperador)
        return
    }
}

const seleccionarSubCliente = (sub) => {
    subClienteSeleccionado.value = Number(sub.IdSubClienteOperador)
    busquedaSubCliente.value = ''
    mostrarDropdownSubCliente.value = false
}

const abrirDropdownSubCliente = () => {
    mostrarDropdownSubCliente.value = true
    busquedaSubCliente.value = ''
}

const cerrarDropdownSiAfuera = (event) => {
    if (subClienteDropdownRef.value && !subClienteDropdownRef.value.contains(event.target)) {
        mostrarDropdownSubCliente.value = false
        busquedaSubCliente.value = ''
    }
}

// ==================== SELECCIÓN PRODUCTO ====================
const seleccionarProducto = (prod) => {
    if (!prod.tiene_precio || !prod.MinimoProducto || prod.MinimoProducto <= 0) {
        toast?.warning('Producto no disponible', 'Este producto no tiene precio o mínimo configurado.')
        return
    }
    productoSeleccionado.value = prod
    if (!props.modoEdicion) {
        cantidadRejillas.value = 1
    }
}

const limpiarSeleccion = () => {
    if (props.modoEdicion) return
    productoSeleccionado.value = null
    cantidadRejillas.value = 1
    busquedaProducto.value = ''
}

// ==================== CANTIDAD REJILLAS ====================
const incrementarRejillas = () => {
    const actual = Number(cantidadRejillas.value) || 0
    if (actual < 999) {
        cantidadRejillas.value = actual + 1
    }
}

const decrementarRejillas = () => {
    const actual = Number(cantidadRejillas.value) || 0
    if (actual > 1) {
        cantidadRejillas.value = actual - 1
    }
}

const actualizarRejillas = (event) => {
    const valor = event.target.value.replace(/[^0-9]/g, '')
    let numero = parseInt(valor) || 0
    if (numero < 1) numero = 1
    if (numero > 999) numero = 999
    cantidadRejillas.value = numero
    event.target.value = numero
}

// ==================== CARGAR PRODUCTOS ====================
const cargarProductos = async () => {
    if (!props.contenedor) return
    if (!props.idIdentificador) {
        errorMensaje.value = 'Selecciona un cliente'
        return
    }

    loading.value = true
    errorCarga.value = false
    productos.value = []
    
    if (!props.modoEdicion) {
        productoSeleccionado.value = null
        cantidadRejillas.value = 1
    }
    
    busquedaProducto.value = ''
    errorMensaje.value = ''

    try {
        const tipoPrecioParam = props.tipoPrecio || 'sin_factura'

        const response = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/contenedor/${props.contenedor.IdContenedor}/productos-precios`,
            { params: { tipo_precio: tipoPrecioParam } }
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
                                PrecioFinal: p.PrecioFinal || 0,
                                tiene_precio: p.tiene_precio || false,
                                IdGrupoAnalisis: p.IdGrupoAnalisis,
                                GrupoAnalisis: grupo.grupo_nombre || 'Sin grupo',
                                MinimoProducto: p.MinimoProducto || 0,
                            })
                        })
                    }
                })
            }

            productos.value = productosRaw.filter(p => p.tiene_precio && p.MinimoProducto > 0)

            if (props.modoEdicion && props.datosEdicion && props.datosEdicion.productos?.length > 0) {
                const prodEdit = props.datosEdicion.productos[0]
                const prodEncontrado = productos.value.find(p => p.IdProducto === prodEdit.IdProducto)
                
                if (prodEncontrado) {
                    productoSeleccionado.value = prodEncontrado
                    
                    if (props.datosEdicion.CantidadRejillas) {
                        cantidadRejillas.value = Number(props.datosEdicion.CantidadRejillas)
                    } else if (prodEncontrado.MinimoProducto > 0) {
                        cantidadRejillas.value = Math.round(Number(prodEdit.Cantidad) / prodEncontrado.MinimoProducto)
                    }
                }
            }

            if (productos.value.length === 0) {
                errorMensaje.value = 'No hay productos con precio y mínimo configurados.'
            }
        } else {
            errorCarga.value = true
            errorMensaje.value = response.data.data?.mensaje || 'Error al cargar productos'
        }
    } catch (error) {
        console.error('Error:', error)
        errorCarga.value = true
        errorMensaje.value = 'Error al cargar productos'
    } finally {
        loading.value = false
    }
}

// ==================== AGREGAR / ACTUALIZAR ====================
const agregarRejillas = () => {
    if (!puedeAgregar.value) return

    const payload = {
        IdContenedor: props.contenedor.IdContenedor,
        IdProducto: productoSeleccionado.value.IdProducto,
        CantidadRejillas: Number(cantidadRejillas.value),
        TipoPrecio: props.tipoPrecio,
        IdSubClienteOperador: subClienteSeleccionado.value,
        Precio: productoSeleccionado.value.PrecioFinal,
        CantidadPorRejilla: cantidadPorRejilla.value
    }

    if (props.modoEdicion && props.datosEdicion) {
        emit('actualizar', {
            ...payload,
            IdPedidoCliente: props.datosEdicion.IdPedidoCliente,
            OrdenContenedor: props.datosEdicion.OrdenContenedor,
            productos: [{
                IdProducto: productoSeleccionado.value.IdProducto,
                Cantidad: Number(cantidadRejillas.value) * cantidadPorRejilla.value,
                Precio: productoSeleccionado.value.PrecioFinal
            }]
        })
    } else {
        emit('agregar', payload)
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
        nextTick(() => {
            inicializarSubCliente()
        })
    } else if (!newVal) {
        mostrarDropdownSubCliente.value = false
        busquedaSubCliente.value = ''
    }
}, { immediate: true })

watch(() => props.tipoPrecio, (newVal) => {
    if (newVal && props.visible && props.contenedor) {
        cargarProductos()
    }
})

watch(() => props.idSubClienteOperadorDefault, (newVal) => {
    if (newVal) {
        nextTick(() => {
            subClienteSeleccionado.value = Number(newVal)
        })
    }
})

watch(() => props.datosEdicion, (newVal) => {
    if (newVal && props.modoEdicion && props.visible) {
        cargarProductos()
        nextTick(() => {
            inicializarSubCliente()
        })
    }
}, { deep: true, immediate: true })

// ==================== LIFECYCLE ====================
onMounted(() => {
    inicializarSubCliente()
    document.addEventListener('click', cerrarDropdownSiAfuera)
})

onUnmounted(() => {
    document.removeEventListener('click', cerrarDropdownSiAfuera)
})
</script>

<template>
    <div
        v-if="visible"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3"
        @click.self="cerrarModal"
    >
        <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden shadow-xl flex flex-col relative z-10">

            <!-- ==================== HEADER ==================== -->
            <div class="px-4 py-2.5 bg-primary-600 flex items-center justify-between flex-shrink-0">
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-white text-sm truncate flex items-center gap-2">
                        <span class="w-6 h-6 bg-white/20 text-white rounded-full flex items-center justify-center text-[10px] flex-shrink-0">
                            <i class="fas fa-cube"></i>
                        </span>
                        {{ contenedor?.Codigo || 'Producto Único' }}
                        <span v-if="modoEdicion" class="text-[9px] bg-white/20 px-1.5 py-0.5 rounded text-white font-bold">
                            <i class="fas fa-pencil-alt"></i> EDITANDO
                        </span>
                    </h3>
                    <p class="text-[10px] text-white/80 mt-0.5">
                        Elige <strong>1 producto</strong> y la <strong>cantidad de rejillas</strong>.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[9px] px-2 py-0.5 rounded-full font-medium"
                        :class="tipoPrecio === 'con_factura' ? 'bg-blue-100 text-blue-700' : 'bg-white/20 text-white'">
                        <i :class="tipoPrecio === 'con_factura' ? 'fas fa-file-invoice-dollar' : 'fas fa-receipt'" class="mr-1 text-[8px]"></i>
                        {{ tipoPrecio === 'con_factura' ? 'Con Factura' : 'Sin Factura' }}
                    </span>
                    <button @click="cerrarModal" class="text-white/80 hover:text-white hover:bg-white/10 rounded-md p-1.5 transition flex-shrink-0">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- ==================== SUBCLIENTE ==================== -->
            <div class="px-4 py-2 border-b border-gray-200 flex items-center gap-2 flex-shrink-0"
                 :class="esPropioOperador ? 'bg-emerald-50' : 'bg-blue-50'">
                <label class="text-[10px] font-medium text-gray-600 whitespace-nowrap">
                    <i class="fas fa-user-tag mr-1" :class="esPropioOperador ? 'text-emerald-500' : 'text-blue-500'"></i>
                    Pedido para:
                </label>
                <div class="flex-1 min-w-0 relative" ref="subClienteDropdownRef">
                    <div
                        @click="!modoEdicion && abrirDropdownSubCliente()"
                        class="w-full border rounded-md px-2 py-1 text-xs bg-white font-medium flex items-center justify-between gap-1 transition"
                        :class="[
                            esPropioOperador ? 'border-emerald-300 text-emerald-800' : 'border-blue-300 text-blue-800',
                            modoEdicion ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer hover:border-opacity-80'
                        ]"
                    >
                        <span class="truncate flex-1">
                            {{ subClienteNombreActual }}
                            {{ esPropioOperador ? ' — (Tú)' : '' }}
                        </span>
                        <i v-if="!modoEdicion" class="fas fa-chevron-down text-[9px] opacity-60 flex-shrink-0"></i>
                    </div>

                    <div
                        v-if="mostrarDropdownSubCliente && !modoEdicion"
                        class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg z-50 max-h-64 overflow-hidden flex flex-col"
                    >
                        <div class="p-2 border-b border-gray-100 bg-gray-50">
                            <div class="relative">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                <input
                                    type="text"
                                    v-model="busquedaSubCliente"
                                    placeholder="Buscar subcliente..."
                                    class="w-full border border-gray-200 rounded-md pl-7 pr-2 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none bg-white"
                                    autofocus
                                    @click.stop
                                />
                            </div>
                        </div>

                        <div class="overflow-y-auto flex-1 max-h-52">
                            <div v-if="subClientesFiltrados.length === 0" class="text-center py-4 text-xs text-gray-400">
                                <i class="fas fa-search mr-1"></i>
                                No se encontraron subclientes
                            </div>
                            <div
                                v-else
                                v-for="sub in subClientesFiltrados"
                                :key="sub.IdSubClienteOperador"
                                @click="seleccionarSubCliente(sub)"
                                class="px-3 py-1.5 hover:bg-primary-50 cursor-pointer transition flex items-center justify-between gap-2 border-b border-gray-50 last:border-0"
                                :class="Number(sub.IdSubClienteOperador) === Number(subClienteSeleccionado) ? 'bg-primary-50' : ''"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-medium text-gray-800 truncate flex items-center gap-1.5">
                                        {{ sub.Nombre }}
                                        <span v-if="Number(sub.IdIdentificador) === Number(idIdentificador)"
                                              class="text-[8px] bg-emerald-500 text-white px-1.5 py-0.5 rounded-full font-bold">
                                            <i class="fas fa-user-check mr-0.5"></i>
                                            Tú
                                        </span>
                                    </div>
                                    <div class="text-[9px] text-gray-500 mt-0.5">
                                        <i class="fas fa-id-card mr-1"></i>
                                        {{ sub.CI_NIT || 'Sin NIT' }}
                                    </div>
                                </div>
                                <i v-if="Number(sub.IdSubClienteOperador) === Number(subClienteSeleccionado)"
                                   class="fas fa-check text-primary-600 text-[10px]"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <span v-if="esPropioOperador"
                      class="text-[8px] bg-emerald-500 text-white px-2 py-0.5 rounded-full font-bold whitespace-nowrap">
                    <i class="fas fa-user-check mr-0.5"></i>
                    Operador
                </span>
                <span v-else
                      class="text-[8px] bg-blue-500 text-white px-2 py-0.5 rounded-full font-bold whitespace-nowrap">
                    <i class="fas fa-user-tag mr-0.5"></i>
                    Subcliente
                </span>
            </div>

            <!-- ==================== CUERPO ==================== -->
            <div class="p-3 overflow-y-auto flex-1">

                <div v-if="!idIdentificador" class="mb-3 p-2 bg-yellow-50 border-l-4 border-yellow-400 rounded text-xs text-yellow-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Selecciona un cliente para ver los precios
                </div>

                <div v-if="errorCarga" class="mb-3 p-2 bg-red-50 border-l-4 border-red-400 rounded text-xs text-red-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ errorMensaje }}
                    <button @click="cargarProductos" class="ml-2 text-red-600 hover:text-red-800 underline">Reintentar</button>
                </div>

                <div v-if="loading" class="flex justify-center items-center py-10">
                    <div class="w-8 h-8 border-2 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
                </div>

                <div v-else-if="!productoSeleccionado" class="space-y-3">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                        <input
                            type="text"
                            v-model="busquedaProducto"
                            placeholder="Buscar producto por nombre o código..."
                            class="w-full border border-gray-300 rounded-md pl-8 pr-3 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none bg-gray-50 focus:bg-white"
                        />
                    </div>

                    <p class="text-[10px] text-gray-500 font-medium">
                        <i class="fas fa-info-circle mr-1 text-primary-500"></i>
                        Selecciona el producto ({{ productosFiltrados.length }} disponible{{ productosFiltrados.length !== 1 ? 's' : '' }})
                    </p>

                    <div v-if="productosFiltrados.length === 0" class="text-center py-8 text-gray-400">
                        <i class="fas fa-box-open text-2xl block mb-2"></i>
                        <p class="text-xs">No hay productos disponibles</p>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button
                            v-for="prod in productosFiltrados"
                            :key="prod.IdProducto"
                            @click="seleccionarProducto(prod)"
                            class="text-left border-2 border-gray-200 hover:border-primary-400 hover:bg-primary-50/50 rounded-md p-2 transition group"
                        >
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="font-mono text-[9px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                                    {{ prod.Codigo }}
                                </span>
                                <span class="text-[9px] text-primary-600 bg-primary-100 px-1.5 py-0.5 rounded-full font-bold whitespace-nowrap">
                                    <i class="fas fa-cube text-[8px] mr-0.5"></i>
                                    {{ formatearNumero(prod.MinimoProducto) }} und
                                </span>
                            </div>
                            <p class="text-xs font-semibold text-gray-800 mb-1.5 line-clamp-2">
                                {{ prod.Descripcion }}
                            </p>
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] text-gray-500">{{ prod.GrupoAnalisis }}</span>
                                <span class="text-sm font-bold text-emerald-600">
                                    Bs. {{ formatearPrecio(prod.PrecioFinal) }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <div v-else class="space-y-3">
                    <!-- Producto seleccionado -->
                    <div class="bg-primary-50 border-2 border-primary-200 rounded-lg p-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2 min-w-0 flex-1">
                                <div class="w-8 h-8 bg-primary-500 text-white rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-cube text-sm"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-mono text-[9px] text-primary-600 bg-primary-100 px-1.5 py-0.5 rounded inline-block">
                                        {{ productoSeleccionado.Codigo }}
                                    </p>
                                    <p class="text-xs font-bold text-primary-900 mt-0.5 leading-tight">
                                        {{ productoSeleccionado.Descripcion }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                                        <span class="text-[9px] text-primary-700">
                                            <i class="fas fa-tag mr-0.5"></i>
                                            {{ productoSeleccionado.GrupoAnalisis }}
                                        </span>
                                        <span class="text-[9px] text-emerald-700 font-bold">
                                            <i class="fas fa-money-bill mr-0.5"></i>
                                            Bs. {{ formatearPrecio(productoSeleccionado.PrecioFinal) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button
                                v-if="!modoEdicion"
                                @click="limpiarSeleccion"
                                class="text-primary-500 hover:text-primary-700 hover:bg-primary-100 rounded-md p-1 transition flex-shrink-0"
                                title="Cambiar producto"
                            >
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Cantidad de rejillas + Totales (lado a lado) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <!-- Cantidad de rejillas -->
                        <div class="bg-white border-2 border-primary-200 rounded-lg p-2.5">
                            <label class="block text-[10px] font-bold text-gray-700 mb-1.5">
                                <i class="fas fa-layer-group text-primary-500 mr-1"></i>
                                Rejillas
                            </label>

                            <div class="flex items-center justify-center gap-2">
                                <button
                                    type="button"
                                    @click="decrementarRejillas"
                                    :disabled="cantidadRejillas <= 1"
                                    class="w-8 h-8 rounded-full bg-primary-100 hover:bg-primary-200 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center transition flex-shrink-0"
                                >
                                    <i class="fas fa-minus text-primary-600 text-[10px]"></i>
                                </button>

                                <input
                                    type="text"
                                    inputmode="numeric"
                                    :value="cantidadRejillas"
                                    @input="actualizarRejillas"
                                    @focus="$event.target.select()"
                                    class="w-16 text-center text-2xl font-extrabold border-2 border-primary-300 rounded-md py-1 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white text-primary-700 tabular-nums"
                                />

                                <button
                                    type="button"
                                    @click="incrementarRejillas"
                                    :disabled="cantidadRejillas >= 999"
                                    class="w-8 h-8 rounded-full bg-primary-500 hover:bg-primary-600 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center transition flex-shrink-0"
                                >
                                    <i class="fas fa-plus text-white text-[10px]"></i>
                                </button>
                            </div>

                            <div class="flex flex-wrap justify-center gap-1 mt-2">
                                <button
                                    v-for="n in [1, 2, 3, 5, 10]"
                                    :key="n"
                                    @click="cantidadRejillas = n"
                                    class="px-2 py-0.5 text-[9px] font-bold rounded-full transition"
                                    :class="Number(cantidadRejillas) === n
                                        ? 'bg-primary-500 text-white'
                                        : 'bg-primary-100 text-primary-700 hover:bg-primary-200'"
                                >
                                    {{ n }}
                                </button>
                            </div>

                            <p class="text-[9px] text-gray-400 text-center mt-1.5">
                                <i class="fas fa-info-circle mr-0.5"></i>
                                {{ formatearNumero(cantidadPorRejilla) }} und por rejilla
                            </p>
                        </div>

                        <!-- Totales -->
                        <div class="bg-gradient-to-br from-primary-50 to-indigo-50 border-2 border-primary-300 rounded-lg p-2.5">
                            <p class="text-[9px] text-primary-600 font-semibold uppercase text-center mb-1.5">
                                <i class="fas fa-calculator mr-1"></i>
                                Resumen
                            </p>
                            
                            <div class="space-y-1.5">
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] text-gray-600">Rejillas:</span>
                                    <span class="text-sm font-bold text-primary-700 tabular-nums">
                                        {{ formatearNumero(cantidadRejillas) }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] text-gray-600">Unidades:</span>
                                    <span class="text-sm font-bold text-primary-700 tabular-nums">
                                        {{ formatearNumero(totalUnidades) }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center pt-1.5 border-t border-primary-200">
                                    <span class="text-[10px] text-gray-600 font-semibold">Total:</span>
                                    <span class="text-base font-extrabold text-emerald-600 tabular-nums">
                                        Bs. {{ formatearPrecio(totalMonto) }}
                                    </span>
                                </div>
                            </div>
                            
                            <p class="text-[8px] text-primary-500 text-center mt-1.5">
                                {{ formatearNumero(cantidadRejillas) }} × {{ formatearNumero(cantidadPorRejilla) }} und × Bs. {{ formatearPrecio(productoSeleccionado.PrecioFinal) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== FOOTER ==================== -->
            <div class="px-4 py-2 border-t border-gray-200 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-2 flex-shrink-0">
                <div class="text-[10px] text-gray-500 text-center sm:text-left">
                    <span v-if="!productoSeleccionado" class="text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i> Selecciona un producto
                    </span>
                    <span v-else class="text-primary-600">
                        <i class="fas fa-check-circle mr-1"></i>
                        {{ formatearNumero(cantidadRejillas) }} rejilla(s) · {{ formatearNumero(totalUnidades) }} und
                        <span v-if="subClienteSeleccionado" class="text-blue-600 ml-1">
                            · {{ subClienteNombreActual }}
                        </span>
                    </span>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button
                        v-if="productoSeleccionado && !modoEdicion"
                        @click="limpiarSeleccion"
                        class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-xs font-medium transition flex-1 sm:flex-none flex items-center justify-center gap-1.5"
                    >
                        <i class="fas fa-eraser text-[10px]"></i> Cambiar
                    </button>
                    <button
                        @click="agregarRejillas"
                        :disabled="!puedeAgregar"
                        class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5 flex-1 sm:flex-none"
                    >
                        <i :class="modoEdicion ? 'fas fa-save' : 'fas fa-cube'" class="text-[10px]"></i>
                        {{ modoEdicion ? 'Actualizar' : `Agregar ${formatearNumero(cantidadRejillas)} rejilla(s)` }}
                    </button>
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

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] {
    -moz-appearance: textfield;
}

.tabular-nums {
    font-variant-numeric: tabular-nums;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
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