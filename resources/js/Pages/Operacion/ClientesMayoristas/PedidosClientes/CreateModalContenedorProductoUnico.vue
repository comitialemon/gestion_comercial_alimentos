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
    nombreOperador: { type: String, default: '' }
})

const emit = defineEmits(['close', 'agregar'])

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
    cantidadRejillas.value = 1
}

const limpiarSeleccion = () => {
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
    productoSeleccionado.value = null
    cantidadRejillas.value = 1
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

// ==================== AGREGAR ====================
const agregarRejillas = () => {
    if (!puedeAgregar.value) return

    const payload = {
        IdContenedor: props.contenedor.IdContenedor,
        IdProducto: productoSeleccionado.value.IdProducto,
        CantidadRejillas: Number(cantidadRejillas.value),
        TipoPrecio: props.tipoPrecio,
        IdSubClienteOperador: subClienteSeleccionado.value
    }

    emit('agregar', payload)
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
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
        @click.self="cerrarModal"
    >
        <div class="bg-white rounded-xl w-full max-w-3xl max-h-[90vh] overflow-hidden shadow-xl flex flex-col relative z-10">

            <!-- HEADER -->
            <div class="px-4 py-3 border-b bg-purple-50 flex items-center justify-between flex-shrink-0">
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-gray-800 text-sm truncate flex items-center gap-2">
                        <span class="w-7 h-7 bg-purple-500 text-white rounded-full flex items-center justify-center text-xs flex-shrink-0">
                            <i class="fas fa-cube"></i>
                        </span>
                        {{ contenedor?.Codigo || 'Producto Único' }}
                    </h3>
                    <p class="text-[10px] text-gray-500 mt-0.5">
                        Elige <strong>1 producto</strong> y la <strong>cantidad de rejillas</strong>. Cada rejilla lleva la cantidad mínima del producto.
                    </p>
                    <span class="text-[9px] px-2 py-0.5 rounded-full font-medium mt-1 inline-block"
                        :class="tipoPrecio === 'con_factura' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                        <i :class="tipoPrecio === 'con_factura' ? 'fas fa-file-invoice-dollar' : 'fas fa-receipt'" class="mr-1"></i>
                        {{ tipoPrecio === 'con_factura' ? 'Con Factura' : 'Sin Factura' }}
                    </span>
                </div>
                <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-1.5 transition flex-shrink-0">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- SUBCLIENTE -->
            <div class="px-4 py-2 border-b flex items-center gap-2 flex-shrink-0 transition-colors"
                 :class="esPropioOperador ? 'bg-green-50 border-green-200' : 'bg-blue-50 border-blue-200'">
                <label class="text-[10px] font-medium text-gray-600 whitespace-nowrap">
                    <i class="fas fa-user-tag mr-1" :class="esPropioOperador ? 'text-green-500' : 'text-blue-500'"></i>
                    Pedido para:
                </label>
                <div class="flex-1 min-w-0 relative" ref="subClienteDropdownRef">
                    <div
                        @click="abrirDropdownSubCliente"
                        class="w-full border rounded-lg px-2 py-1 text-xs bg-white font-medium cursor-pointer flex items-center justify-between gap-1 transition"
                        :class="esPropioOperador
                            ? 'border-green-300 hover:border-green-400 text-green-800'
                            : 'border-blue-300 hover:border-blue-400 text-blue-800'"
                    >
                        <span class="truncate flex-1">
                            {{ subClienteNombreActual }}
                            {{ esPropioOperador ? ' — (Tú)' : '' }}
                        </span>
                        <i class="fas fa-chevron-down text-[9px] opacity-60 flex-shrink-0"></i>
                    </div>

                    <div
                        v-if="mostrarDropdownSubCliente"
                        class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-72 overflow-hidden flex flex-col"
                    >
                        <div class="p-2 border-b bg-gray-50">
                            <div class="relative">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                <input
                                    type="text"
                                    v-model="busquedaSubCliente"
                                    placeholder="Buscar subcliente..."
                                    class="w-full border border-gray-200 rounded pl-7 pr-2 py-1 text-xs focus:ring-2 focus:ring-primary-400 outline-none bg-white"
                                    autofocus
                                    @click.stop
                                />
                            </div>
                        </div>

                        <div class="overflow-y-auto flex-1 max-h-56">
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
                                              class="text-[8px] bg-green-500 text-white px-1.5 py-0.5 rounded-full font-bold">
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
                      class="text-[8px] bg-green-500 text-white px-2 py-0.5 rounded-full font-bold whitespace-nowrap">
                    <i class="fas fa-user-check mr-0.5"></i>
                    Operador
                </span>
                <span v-else
                      class="text-[8px] bg-blue-500 text-white px-2 py-0.5 rounded-full font-bold whitespace-nowrap">
                    <i class="fas fa-user-tag mr-0.5"></i>
                    Subcliente
                </span>
            </div>

            <!-- CUERPO -->
            <div class="p-3 overflow-y-auto flex-1">

                <div v-if="!idIdentificador" class="mb-3 p-2 bg-yellow-50 border-l-4 border-yellow-400 rounded text-xs text-yellow-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Selecciona un cliente para ver los precios
                </div>

                <div v-if="errorCarga" class="mb-3 p-2 bg-red-50 border-l-4 border-red-400 rounded text-xs text-red-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ errorMensaje }}
                    <button @click="cargarProductos" class="ml-2 text-red-600 hover:text-red-800 underline">Reintentar</button>
                </div>

                <div v-if="loading" class="flex justify-center items-center py-10">
                    <div class="w-8 h-8 border-3 border-purple-200 border-t-purple-600 rounded-full animate-spin"></div>
                </div>

                <div v-else-if="!productoSeleccionado" class="space-y-3">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                        <input
                            type="text"
                            v-model="busquedaProducto"
                            placeholder="Buscar producto por nombre o código..."
                            class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-purple-400 focus:border-transparent outline-none bg-gray-50 focus:bg-white"
                        />
                    </div>

                    <p class="text-[10px] text-gray-500 font-medium">
                        <i class="fas fa-info-circle mr-1 text-purple-500"></i>
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
                            class="text-left border-2 border-gray-200 hover:border-purple-400 hover:bg-purple-50/50 rounded-lg p-2.5 transition group"
                        >
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="font-mono text-[9px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                                    {{ prod.Codigo }}
                                </span>
                                <span class="text-[9px] text-purple-600 bg-purple-100 px-1.5 py-0.5 rounded-full font-bold whitespace-nowrap">
                                    <i class="fas fa-cube text-[8px] mr-0.5"></i>
                                    {{ formatearNumero(prod.MinimoProducto) }} und
                                </span>
                            </div>
                            <p class="text-xs font-semibold text-gray-800 mb-1.5 line-clamp-2">
                                {{ prod.Descripcion }}
                            </p>
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] text-gray-500">{{ prod.GrupoAnalisis }}</span>
                                <span class="text-sm font-bold text-green-600">
                                    Bs. {{ formatearPrecio(prod.PrecioFinal) }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <div class="bg-purple-50 border-2 border-purple-200 rounded-lg p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-2 min-w-0 flex-1">
                                <div class="w-10 h-10 bg-purple-500 text-white rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-cube"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-mono text-[10px] text-purple-600 bg-purple-100 px-1.5 py-0.5 rounded inline-block">
                                        {{ productoSeleccionado.Codigo }}
                                    </p>
                                    <p class="text-sm font-bold text-purple-900 mt-1 leading-tight">
                                        {{ productoSeleccionado.Descripcion }}
                                    </p>
                                    <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                                        <span class="text-[10px] text-purple-700">
                                            <i class="fas fa-tag mr-0.5"></i>
                                            {{ productoSeleccionado.GrupoAnalisis }}
                                        </span>
                                        <span class="text-[10px] text-green-700 font-bold">
                                            <i class="fas fa-money-bill mr-0.5"></i>
                                            Bs. {{ formatearPrecio(productoSeleccionado.PrecioFinal) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button
                                @click="limpiarSeleccion"
                                class="text-purple-500 hover:text-purple-700 hover:bg-purple-100 rounded-lg p-1.5 transition flex-shrink-0"
                                title="Cambiar producto"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="mt-3 bg-white rounded-lg border border-purple-200 p-2.5">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-info-circle text-purple-500 text-xs"></i>
                                <p class="text-[11px] text-purple-800">
                                    Cantidad por rejilla:
                                    <strong class="text-purple-900 text-sm ml-1">{{ formatearNumero(cantidadPorRejilla) }} und</strong>
                                    <span class="text-purple-600 ml-1">(mínimo del producto)</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border-2 border-purple-200 rounded-lg p-3">
                        <label class="block text-xs font-bold text-gray-700 mb-2">
                            <i class="fas fa-layer-group text-purple-500 mr-1"></i>
                            ¿Cuántas rejillas quieres?
                        </label>

                        <div class="flex items-center justify-center gap-3">
                            <button
                                type="button"
                                @click="decrementarRejillas"
                                :disabled="cantidadRejillas <= 1"
                                class="w-12 h-12 rounded-full bg-purple-100 hover:bg-purple-200 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center transition flex-shrink-0"
                            >
                                <i class="fas fa-minus text-purple-600 text-lg"></i>
                            </button>

                            <input
                                type="text"
                                inputmode="numeric"
                                :value="cantidadRejillas"
                                @input="actualizarRejillas"
                                @focus="$event.target.select()"
                                class="w-24 text-center text-3xl font-extrabold border-2 border-purple-300 rounded-lg py-2 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none bg-white text-purple-700 tabular-nums"
                            />

                            <button
                                type="button"
                                @click="incrementarRejillas"
                                :disabled="cantidadRejillas >= 999"
                                class="w-12 h-12 rounded-full bg-purple-500 hover:bg-purple-600 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center transition flex-shrink-0"
                            >
                                <i class="fas fa-plus text-white text-lg"></i>
                            </button>
                        </div>

                        <div class="flex flex-wrap justify-center gap-1.5 mt-3">
                            <button
                                v-for="n in [1, 2, 3, 5, 10]"
                                :key="n"
                                @click="cantidadRejillas = n"
                                class="px-3 py-1 text-[10px] font-bold rounded-full transition"
                                :class="Number(cantidadRejillas) === n
                                    ? 'bg-purple-500 text-white'
                                    : 'bg-purple-100 text-purple-700 hover:bg-purple-200'"
                            >
                                {{ n }}
                            </button>
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-purple-50 to-indigo-50 border-2 border-purple-300 rounded-lg p-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="text-center">
                                <p class="text-[10px] text-purple-600 font-semibold uppercase">Rejillas</p>
                                <p class="text-2xl font-extrabold text-purple-700 tabular-nums">
                                    {{ formatearNumero(cantidadRejillas) }}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-[10px] text-purple-600 font-semibold uppercase">Total unidades</p>
                                <p class="text-2xl font-extrabold text-purple-700 tabular-nums">
                                    {{ formatearNumero(totalUnidades) }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-purple-200 text-center">
                            <p class="text-[10px] text-purple-600 font-semibold uppercase">Monto total</p>
                            <p class="text-2xl font-extrabold text-emerald-600 tabular-nums">
                                Bs. {{ formatearPrecio(totalMonto) }}
                            </p>
                            <p class="text-[10px] text-purple-500 mt-0.5">
                                {{ formatearNumero(cantidadRejillas) }} × {{ formatearNumero(cantidadPorRejilla) }} und × Bs. {{ formatearPrecio(productoSeleccionado.PrecioFinal) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="px-4 py-2.5 border-t bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-2 flex-shrink-0">
                <div class="text-[10px] text-gray-500 text-center sm:text-left">
                    <span v-if="!productoSeleccionado" class="text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i> Selecciona un producto
                    </span>
                    <span v-else class="text-purple-600">
                        <i class="fas fa-check-circle mr-1"></i>
                        {{ formatearNumero(cantidadRejillas) }} rejilla(s) · {{ formatearNumero(totalUnidades) }} und
                        <span v-if="subClienteSeleccionado" class="text-blue-600 ml-1">
                            · {{ subClienteNombreActual }}
                        </span>
                    </span>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button
                        v-if="productoSeleccionado"
                        @click="limpiarSeleccion"
                        class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-[10px] transition flex-1 sm:flex-none"
                    >
                        <i class="fas fa-eraser text-[8px] mr-1"></i> Cambiar
                    </button>
                    <button
                        @click="agregarRejillas"
                        :disabled="!puedeAgregar"
                        class="px-4 py-1.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-[10px] font-bold transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5 flex-1 sm:flex-none"
                    >
                        <i class="fas fa-cube text-[10px]"></i>
                        Agregar {{ formatearNumero(cantidadRejillas) }} rejilla(s)
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
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