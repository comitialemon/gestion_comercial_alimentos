<script setup>
import { ref, computed, onMounted, inject, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import CreateModalProductos from './CreateModalProductos.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    contenedores: { type: Array, default: () => [] },
    clientes: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    pedidoBorrador: { type: Object, default: null },
    carrito: { type: Array, default: () => [] },
    sucursalDefault: { type: Number, default: null },
    idIdentificador: { type: Number, default: null },
    nombreOperador: { type: String, default: '' },
    minimosGrupos: { type: Array, default: () => [] },
    progresoInicial: { type: Array, default: () => [] },
    tipoPrecio: { type: String, default: 'sin_factura' },
})

// ==================== ESTADO ====================
const loading = ref(false)
const modalVisible = ref(false)
const contenedorSeleccionado = ref(null)
const carritoItems = ref([])
const pedidoId = ref(null)
const busquedaContenedor = ref('')

// ✅ Tipo de precio
const tipoPrecioActual = ref(props.tipoPrecio || 'sin_factura')
const modalCambiarTipoVisible = ref(false)
const tipoPrecioSeleccionado = ref('sin_factura')
const cambiandoTipo = ref(false)

// ✅ Mínimos
const minimos = ref([...props.minimosGrupos])

// ==================== COMPUTADOS ====================
const totalCarrito = computed(() => {
    let total = 0
    carritoItems.value.forEach(item => {
        item.productos.forEach(p => {
            total += Number(p.Cantidad) || 0
        })
    })
    return total
})

const totalContenedoresCarrito = computed(() => carritoItems.value.length)
const hayProductosEnCarrito = computed(() => carritoItems.value.length > 0)

const contenedoresFiltrados = computed(() => {
    if (!busquedaContenedor.value) return props.contenedores
    const termino = busquedaContenedor.value.toLowerCase()
    return props.contenedores.filter(c =>
        c.Codigo?.toLowerCase().includes(termino) ||
        c.TipoContenedor?.toLowerCase().includes(termino)
    )
})

/**
 * ✅ PROGRESO CALCULADO LOCALMENTE
 * 
 * ⚠️ SOLO muestra los grupos que tienen al menos 1 producto en el carrito.
 * Los grupos configurados sin productos NO se muestran (no obligan al cliente).
 */
const progresoGrupos = computed(() => {
    // 1. Acumular cantidades por grupo (solo grupos con productos)
    const acumulado = {}

    carritoItems.value.forEach(item => {
        item.productos.forEach(p => {
            const grupoId = p.IdGrupoAnalisis
            if (grupoId) {
                acumulado[grupoId] = (acumulado[grupoId] || 0) + (Number(p.Cantidad) || 0)
            }
        })
    })

    // ✅ 2. Si no hay productos, no hay progreso
    if (Object.keys(acumulado).length === 0) {
        return []
    }

    // ✅ 3. Solo iterar los grupos que TIENEN productos
    return minimos.value
        .filter(minimo => acumulado[minimo.IdGrupoAnalisis] !== undefined)
        .map(minimo => {
            const pedida = acumulado[minimo.IdGrupoAnalisis] || 0
            const minima = Number(minimo.CantidadMinimaGrupo) || 0

            return {
                IdGrupoAnalisis: minimo.IdGrupoAnalisis,
                NombreGrupo: minimo.NombreGrupo,
                CantidadMinima: minima,
                CantidadPedida: pedida,
                Cumple: pedida >= minima,
                Falta: Math.max(0, minima - pedida),
                Porcentaje: minima > 0 ? Math.min((pedida / minima) * 100, 100) : 100
            }
        })
})

const gruposQueNoCumplen = computed(() => progresoGrupos.value.filter(g => !g.Cumple))
const cumpleTodosMinimos = computed(() => progresoGrupos.value.length === 0 || gruposQueNoCumplen.value.length === 0)

// ✅ Etiquetas de tipo de precio
const tipoPrecioLabel = computed(() => {
    return tipoPrecioActual.value === 'con_factura' ? 'Con Factura' : 'Sin Factura'
})

const tipoPrecioColor = computed(() => {
    return tipoPrecioActual.value === 'con_factura' 
        ? 'bg-blue-100 text-blue-700 border-blue-300' 
        : 'bg-gray-100 text-gray-700 border-gray-300'
})

// ==================== INICIALIZAR CARRITO ====================
const inicializarCarrito = () => {
    if (props.carrito && props.carrito.length > 0) {
        carritoItems.value = props.carrito.map(item => ({
            ...item,
            productos: item.productos.map(p => ({
                ...p,
                Cantidad: Number(p.Cantidad) || 0
            }))
        }))
    }
    if (props.pedidoBorrador) {
        pedidoId.value = props.pedidoBorrador.IdPedidoCliente
    }
}

// ==================== ABRIR MODAL ====================
const abrirModal = (contenedor) => {
    contenedorSeleccionado.value = contenedor
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    contenedorSeleccionado.value = null
}

// ==================== TIPO DE PRECIO ====================
const abrirModalCambiarTipo = (nuevoTipo) => {
    if (nuevoTipo === tipoPrecioActual.value) return
    
    tipoPrecioSeleccionado.value = nuevoTipo
    
    // Si no hay carrito, cambiar directo
    if (!hayProductosEnCarrito.value) {
        aplicarCambioTipo()
        return
    }
    
    // Si hay carrito, abrir modal de confirmación
    modalCambiarTipoVisible.value = true
}

const aplicarCambioTipo = async () => {
    cambiandoTipo.value = true
    
    try {
        if (pedidoId.value && hayProductosEnCarrito.value) {
            // ✅ Hay carrito → Recalcular precios en el backend
            const response = await axios.post(
                '/operacion/pedidos/clientes-mayoristas/pedidos-clientes/carrito/cambiar-tipo-precio',
                {
                    IdPedidoCliente: pedidoId.value,
                    TipoPrecio: tipoPrecioSeleccionado.value
                }
            )

            if (response.data.success) {
                toast?.success('Éxito', 'Tipo de precio actualizado y precios recalculados')
                
                // Si hay productos sin precio, avisar
                if (response.data.productos_sin_precio?.length > 0) {
                    toast?.warning(
                        'Atención', 
                        `${response.data.productos_sin_precio.length} producto(s) no tienen precio asignado para este tipo`
                    )
                }

                // Recargar
                router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create', {
                    tipo_precio: tipoPrecioSeleccionado.value
                }, {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        tipoPrecioActual.value = tipoPrecioSeleccionado.value
                    }
                })
            } else {
                toast?.error('Error', response.data.message || 'Error al cambiar tipo')
            }
        } else {
            // No hay carrito → Solo recargar con el nuevo tipo
            router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create', {
                tipo_precio: tipoPrecioSeleccionado.value
            }, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    tipoPrecioActual.value = tipoPrecioSeleccionado.value
                    toast?.success('Éxito', `Tipo de precio: ${tipoPrecioSeleccionado.value === 'con_factura' ? 'Con Factura' : 'Sin Factura'}`)
                }
            })
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al cambiar tipo de precio')
    } finally {
        cambiandoTipo.value = false
        modalCambiarTipoVisible.value = false
    }
}

const cerrarModalCambiarTipo = () => {
    modalCambiarTipoVisible.value = false
    tipoPrecioSeleccionado.value = tipoPrecioActual.value
}

// ==================== AGREGAR AL CARRITO ====================
const agregarContenedorAlCarrito = async (data) => {
    loading.value = true
    try {
        // ✅ Enviar TipoPrecio
        const payload = {
            ...data,
            TipoPrecio: tipoPrecioActual.value
        }
        
        const response = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/pedidos-clientes/carrito/agregar',
            payload
        )
        if (response.data.success) {
            toast?.success('Éxito', 'Productos agregados al carrito')
            if (response.data.pedido) {
                pedidoId.value = response.data.pedido.IdPedidoCliente
            }
            router.reload({ only: ['carrito', 'pedidoBorrador'] })
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al agregar al carrito')
    } finally {
        loading.value = false
    }
}

// ==================== IR A REVISAR PEDIDO ====================
const irARevisarPedido = () => {
    if (!pedidoId.value) {
        toast?.warning('Carrito vacío', 'Agregue productos antes de revisar')
        return
    }
    router.get(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${pedidoId.value}/review`)
}

// ==================== WATCHERS ====================
watch(() => props.carrito, (newVal) => {
    if (newVal && newVal.length > 0) {
        carritoItems.value = newVal.map(item => ({
            ...item,
            productos: item.productos.map(p => ({
                ...p,
                Cantidad: Number(p.Cantidad) || 0
            }))
        }))
    } else {
        carritoItems.value = []
    }
}, { immediate: true, deep: true })

watch(() => props.minimosGrupos, (newVal) => {
    minimos.value = [...newVal]
}, { deep: true })

watch(() => props.tipoPrecio, (newVal) => {
    tipoPrecioActual.value = newVal
})

onMounted(() => {
    inicializarCarrito()
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-32">
        <div class="max-w-7xl mx-auto px-3 py-3">

            <!-- ==================== HEADER ==================== -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-3">
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Nuevo Pedido</h1>
                    <p class="text-[10px] text-gray-400">
                        Seleccione contenedores y agregue productos
                        <span v-if="nombreOperador" class="text-primary-600 font-medium">
                            • {{ nombreOperador }}
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Info del carrito -->
                    <div v-if="hayProductosEnCarrito" class="hidden sm:flex items-center gap-3 text-xs">
                        <div class="flex items-center gap-1.5 text-gray-500">
                            <i class="fas fa-shopping-cart text-primary-500"></i>
                            <span><strong class="text-gray-700">{{ totalContenedoresCarrito }}</strong> cont.</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-gray-500">
                            <i class="fas fa-cubes text-primary-500"></i>
                            <span><strong class="text-primary-600">{{ totalCarrito }}</strong> und</span>
                        </div>
                    </div>

                    <!-- Botón Revisar -->
                    <button
                        @click="irARevisarPedido"
                        :disabled="!hayProductosEnCarrito"
                        class="px-5 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2 shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="hayProductosEnCarrito
                            ? (cumpleTodosMinimos
                                ? 'bg-green-500 hover:bg-green-600 text-white'
                                : 'bg-orange-500 hover:bg-orange-600 text-white')
                            : 'bg-gray-300 text-gray-500'"
                    >
                        <i :class="cumpleTodosMinimos && hayProductosEnCarrito ? 'fas fa-check-circle' : 'fas fa-clipboard-list'" class="text-sm"></i>
                        Revisar Pedido
                        <span v-if="hayProductosEnCarrito" class="bg-white/20 rounded-full px-2 py-0.5 text-xs">
                            {{ totalContenedoresCarrito }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ==================== SELECTOR TIPO DE PRECIO ==================== -->
            <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-file-invoice-dollar text-primary-600"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-gray-800">Tipo de Precio</h2>
                            <p class="text-[10px] text-gray-500">Selecciona cómo se cotizará el pedido</p>
                        </div>
                    </div>

                    <div class="flex bg-gray-100 rounded-lg p-1 w-full sm:w-auto">
                        <button
                            @click="abrirModalCambiarTipo('sin_factura')"
                            :disabled="cambiandoTipo"
                            class="flex-1 sm:flex-none px-4 py-2 rounded-md text-xs font-medium transition flex items-center justify-center gap-2"
                            :class="tipoPrecioActual === 'sin_factura'
                                ? 'bg-white text-gray-800 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'"
                        >
                            <i class="fas fa-receipt text-[10px]"></i>
                            Sin Factura
                            <i v-if="tipoPrecioActual === 'sin_factura'" class="fas fa-check-circle text-green-500 text-[10px]"></i>
                        </button>
                        <button
                            @click="abrirModalCambiarTipo('con_factura')"
                            :disabled="cambiandoTipo"
                            class="flex-1 sm:flex-none px-4 py-2 rounded-md text-xs font-medium transition flex items-center justify-center gap-2"
                            :class="tipoPrecioActual === 'con_factura'
                                ? 'bg-white text-gray-800 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'"
                        >
                            <i class="fas fa-file-invoice text-[10px]"></i>
                            Con Factura
                            <i v-if="tipoPrecioActual === 'con_factura'" class="fas fa-check-circle text-green-500 text-[10px]"></i>
                        </button>
                    </div>
                </div>

                <!-- Info del tipo seleccionado -->
                <div class="mt-2 flex items-center gap-2 text-[10px]" :class="tipoPrecioColor">
                    <i :class="tipoPrecioActual === 'con_factura' ? 'fas fa-file-invoice' : 'fas fa-receipt'" class="text-[9px]"></i>
                    <span>
                        Los productos se cotizarán con <strong>{{ tipoPrecioLabel }}</strong>
                        <span v-if="hayProductosEnCarrito" class="text-orange-600 ml-1">
                            • Si cambias el tipo, se recalcularán los precios del carrito
                        </span>
                    </span>
                </div>
            </div>

            <!-- ==================== PROGRESO DE GRUPOS ==================== -->
            <div v-if="progresoGrupos.length > 0 && hayProductosEnCarrito" class="bg-white rounded-xl shadow-sm p-4 mb-4 border-l-4"
                 :class="cumpleTodosMinimos ? 'border-green-500' : 'border-orange-500'">
                
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-chart-line" :class="cumpleTodosMinimos ? 'text-green-500' : 'text-orange-500'"></i>
                        Progreso del Pedido
                    </h2>
                    <span 
                        class="text-[10px] px-2 py-1 rounded-full font-medium"
                        :class="cumpleTodosMinimos ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'"
                    >
                        <i :class="cumpleTodosMinimos ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'" class="mr-1"></i>
                        {{ cumpleTodosMinimos ? 'Todo listo' : 'Faltan mínimos' }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div 
                        v-for="grupo in progresoGrupos" 
                        :key="grupo.IdGrupoAnalisis"
                        class="p-3 rounded-lg border-2 transition-all"
                        :class="grupo.Cumple 
                            ? 'border-green-200 bg-green-50/50' 
                            : 'border-orange-200 bg-orange-50/50'"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <i class="fas" :class="grupo.Cumple ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-orange-500'"></i>
                                <span class="font-semibold text-gray-800 text-sm truncate">{{ grupo.NombreGrupo }}</span>
                            </div>
                            <span class="text-[11px] font-bold whitespace-nowrap ml-2" :class="grupo.Cumple ? 'text-green-600' : 'text-orange-600'">
                                {{ grupo.CantidadPedida }} / {{ grupo.CantidadMinima }}
                            </span>
                        </div>

                        <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div 
                                class="h-full transition-all duration-300 rounded-full"
                                :class="grupo.Cumple ? 'bg-green-500' : 'bg-orange-500'"
                                :style="{ width: grupo.Porcentaje + '%' }"
                            ></div>
                        </div>

                        <p v-if="!grupo.Cumple" class="text-[10px] text-orange-600 mt-1.5 flex items-center gap-1">
                            <i class="fas fa-arrow-right"></i>
                            Faltan <strong>{{ grupo.Falta }}</strong> und
                        </p>
                        <p v-else class="text-[10px] text-green-600 mt-1.5 flex items-center gap-1">
                            <i class="fas fa-check"></i> Mínimo alcanzado
                        </p>
                    </div>
                </div>
            </div>

            <!-- ==================== CONTENEDORES ==================== -->
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-3">
                    <h2 class="text-sm font-semibold text-gray-700">
                        <i class="fas fa-boxes mr-2 text-primary-500"></i>
                        Contenedores
                        <span class="text-xs text-gray-400 font-normal ml-1">({{ contenedores.length }})</span>
                    </h2>

                    <div class="relative w-full sm:w-64">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input 
                            type="text"
                            v-model="busquedaContenedor"
                            placeholder="Buscar contenedor..."
                            class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-primary-400 outline-none"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    <div 
                        v-for="contenedor in contenedoresFiltrados" 
                        :key="contenedor.IdContenedor"
                        @click="abrirModal(contenedor)"
                        class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all cursor-pointer overflow-hidden border-2 border-transparent hover:border-primary-300"
                    >
                        <div class="h-24 bg-gradient-to-br from-primary-50 to-indigo-50 flex items-center justify-center">
                            <div class="w-14 h-14 bg-primary-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-box text-primary-500 text-2xl"></i>
                            </div>
                        </div>
                        <div class="p-3 text-center">
                            <h3 class="font-bold text-sm text-gray-800 truncate">{{ contenedor.Codigo }}</h3>
                            <p class="text-[10px] font-mono text-gray-400">{{ contenedor.TipoContenedor }}</p>
                            <div class="flex justify-center items-center gap-2 mt-1">
                                <span class="text-[10px] text-gray-500">
                                    <i class="fas fa-weight-hanging mr-0.5"></i>
                                    {{ contenedor.CapacidadTotalFormateada }} und
                                </span>
                            </div>
                            <button class="mt-2 w-full py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-xs transition flex items-center justify-center gap-1">
                                <i class="fas fa-plus text-[10px]"></i>
                                Seleccionar
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="contenedores.length === 0" class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">
                    <i class="fas fa-box-open text-3xl mb-2 block"></i>
                    <p class="text-sm">No hay contenedores disponibles</p>
                    <p class="text-xs mt-1">Contacte al administrador</p>
                </div>
            </div>

        </div>

        <!-- MODAL DE PRODUCTOS -->
        <CreateModalProductos
            :visible="modalVisible"
            :contenedor="contenedorSeleccionado"
            :idIdentificador="idIdentificador"
            :modoEdicion="false"
            :datosEdicion="null"
            :tipoPrecio="tipoPrecioActual"
            @close="cerrarModal"
            @agregar="agregarContenedorAlCarrito"
            @actualizar="agregarContenedorAlCarrito"
        />

        <!-- ==================== MODAL CONFIRMAR CAMBIO DE TIPO ==================== -->
        <div 
            v-if="modalCambiarTipoVisible"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-[70] p-4"
            @click.self="cerrarModalCambiarTipo"
        >
            <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-2xl animate-fade-in-up">
                
                <!-- Header -->
                <div class="p-4 border-b bg-orange-50 flex items-center gap-3">
                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-orange-600 text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-800">Cambiar Tipo de Precio</h3>
                        <p class="text-[10px] text-gray-500">Esta acción afectará a todo el carrito</p>
                    </div>
                </div>

                <!-- Body -->
                <div class="p-4">
                    <p class="text-sm text-gray-700 text-center">
                        Tienes <strong class="text-primary-600">{{ totalContenedoresCarrito }} contenedor(es)</strong> en el carrito.
                    </p>
                    <p class="text-sm text-gray-700 text-center mt-2">
                        Al cambiar a <strong class="text-blue-600">{{ tipoPrecioSeleccionado === 'con_factura' ? 'Con Factura' : 'Sin Factura' }}</strong>:
                    </p>
                    <ul class="mt-3 space-y-1.5 text-xs text-gray-600 bg-gray-50 p-3 rounded-lg">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-sync-alt text-blue-500 text-[10px] mt-0.5"></i>
                            <span>Se recalcularán los precios de todos los productos</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-calculator text-blue-500 text-[10px] mt-0.5"></i>
                            <span>Los totales se actualizarán automáticamente</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-info-circle text-orange-500 text-[10px] mt-0.5"></i>
                            <span>Si algún producto no tiene precio para este tipo, se te avisará</span>
                        </li>
                    </ul>
                    <p class="text-xs text-center mt-3 text-gray-500">
                        ¿Deseas continuar?
                    </p>
                </div>

                <!-- Footer -->
                <div class="p-3 bg-gray-50 flex justify-end gap-2">
                    <button 
                        @click="cerrarModalCambiarTipo"
                        :disabled="cambiandoTipo"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-medium transition"
                    >
                        Cancelar
                    </button>
                    <button 
                        @click="aplicarCambioTipo"
                        :disabled="cambiandoTipo"
                        class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-xs font-medium transition flex items-center gap-1.5 disabled:opacity-50"
                    >
                        <i v-if="cambiandoTipo" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-check text-[10px]"></i>
                        {{ cambiandoTipo ? 'Procesando...' : 'Sí, cambiar' }}
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

.animate-fade-in-up {
    animation: fadeInUp 0.2s ease-out;
}

.grid {
    gap: 0.75rem;
}

@media (max-width: 640px) {
    .grid-cols-2 {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>