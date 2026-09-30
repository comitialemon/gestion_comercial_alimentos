<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    pedido: { type: Object, required: true },
    detallesAgrupados: { type: Array, default: () => [] },
    clienteNombre: { type: String, default: '' },
    sucursalNombre: { type: String, default: '' },
    operadorNombre: { type: String, default: '' },
    progresoGrupos: { type: Array, default: () => [] },
    cumpleMinimos: { type: Boolean, default: true },
})

const emit = defineEmits(['cerrar'])

// ==================== RESPONSIVE ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== COMPUTED ====================
const totalUnidades = computed(() => {
    let total = 0
    props.detallesAgrupados?.forEach(item => {
        item.productos?.forEach(p => {
            total += Number(p.Cantidad) || 0
        })
    })
    return total
})

const totalContenedores = computed(() => props.detallesAgrupados?.length || 0)

const totalProductos = computed(() => {
    let total = 0
    props.detallesAgrupados?.forEach(item => {
        total += item.productos?.length || 0
    })
    return total
})

const fechaPedido = computed(() => {
    if (props.pedido?.FechaPedido) {
        return new Date(props.pedido.FechaPedido).toLocaleString('es-BO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
    }
    return '-'
})

const faltantesGrupo = computed(() =>
    (props.progresoGrupos || []).filter(g => !g.Cumple && g.Tipo === 'grupo')
)

const faltantesProducto = computed(() =>
    (props.progresoGrupos || []).filter(g => !g.Cumple && g.Tipo === 'producto')
)

const tieneFaltantes = computed(() =>
    faltantesGrupo.value.length > 0 || faltantesProducto.value.length > 0
)

const esFinalizado = computed(() => props.pedido?.ActivoInactivo === 1)

// ==================== HELPERS ====================
const formatearNumero = (valor) => {
    if (valor === undefined || valor === null || valor === '') return '0'
    const numero = parseFloat(valor)
    return isNaN(numero) ? '0' : numero.toFixed(0)
}

const formatearPrecio = (valor) => {
    if (valor === undefined || valor === null || valor === '') return '0.00'
    const numero = parseFloat(valor)
    return isNaN(numero) ? '0.00' : numero.toFixed(2)
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    })
}

const getEstadoBadge = (estado) => {
    const badges = {
        'Borrador': 'bg-amber-100 text-amber-700 border-amber-200',
        'Pendiente': 'bg-blue-100 text-blue-700 border-blue-200',
        'En Proceso': 'bg-orange-100 text-orange-700 border-orange-200',
        'Entregado': 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'Cancelado': 'bg-red-100 text-red-700 border-red-200'
    }
    return badges[estado] || 'bg-gray-100 text-gray-700 border-gray-200'
}

const getEstadoIcono = (estado) => {
    const iconos = {
        'Borrador': 'fa-pencil-alt',
        'Pendiente': 'fa-clock',
        'En Proceso': 'fa-cog fa-spin',
        'Entregado': 'fa-check-circle',
        'Cancelado': 'fa-times-circle'
    }
    return iconos[estado] || 'fa-circle'
}

// ==================== ACCIONES ====================
const cerrarModal = () => emit('cerrar')

const abrirPdf = (id) => {
    window.open(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${id}/pdf`, '_blank')
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-2 sm:p-4"
         @click.self="cerrarModal">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[95vh] flex flex-col overflow-hidden">

            <!-- ==================== HEADER ==================== -->
            <div class="bg-primary-600 p-3 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-file-invoice text-white text-base"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-white font-bold text-sm truncate flex items-center gap-2 flex-wrap">
                            Pedido #{{ pedido?.NumeroPedido || 'Nuevo' }}
                            <span class="px-2 py-0.5 text-[9px] rounded-full font-medium border flex items-center gap-1 whitespace-nowrap"
                                  :class="getEstadoBadge(pedido.EstadoPedido)">
                                <i :class="getEstadoIcono(pedido.EstadoPedido)" class="text-[8px]"></i>
                                {{ pedido.EstadoPedido || 'Borrador' }}
                            </span>
                        </h3>
                        <p class="text-white/80 text-[10px] truncate mt-0.5">
                            <i class="fas fa-user text-[9px] mr-1"></i>
                            {{ clienteNombre || 'Sin cliente' }}
                            <span class="mx-1 opacity-60">•</span>
                            <i class="fas fa-store text-[9px] mr-1"></i>
                            {{ sucursalNombre || 'Sin sucursal' }}
                        </p>
                    </div>
                </div>
                <button @click="cerrarModal"
                        class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0 transition">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- ==================== BODY ==================== -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2.5">

                <!-- ✅ ALERTA DE CUMPLIMIENTO DE MÍNIMOS -->
                <div v-if="esFinalizado && tieneFaltantes"
                     class="bg-red-50 border-l-4 border-red-500 rounded-lg p-2.5">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-exclamation-triangle text-red-500 text-sm flex-shrink-0 mt-0.5"></i>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-red-800 text-[11px]">Pedido finalizado con faltantes de mínimos</h4>
                            <p class="text-[10px] text-red-700 mt-0.5">Este pedido se guardó pero no cumplía los mínimos:</p>

                            <div v-if="faltantesGrupo.length > 0" class="mt-1">
                                <p class="text-[10px] font-bold text-primary-700">
                                    <i class="fas fa-layer-group mr-1"></i>Grupos:
                                </p>
                                <ul class="space-y-0.5 ml-3">
                                    <li v-for="g in faltantesGrupo" :key="'g-' + g.IdGrupoAnalisis"
                                        class="text-[10px] text-red-700">
                                        • <strong>{{ g.NombreGrupo }}</strong>: faltan {{ g.Falta }} und
                                    </li>
                                </ul>
                            </div>

                            <div v-if="faltantesProducto.length > 0" class="mt-1">
                                <p class="text-[10px] font-bold text-primary-700">
                                    <i class="fas fa-box mr-1"></i>Productos:
                                </p>
                                <ul class="space-y-0.5 ml-3">
                                    <li v-for="p in faltantesProducto" :key="'p-' + p.IdProducto"
                                        class="text-[10px] text-red-700">
                                        • <strong>{{ p.NombreGrupo }}</strong>: faltan {{ p.Falta }} und
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="esFinalizado && (progresoGrupos || []).length > 0"
                     class="bg-emerald-50 border-l-4 border-emerald-500 rounded-lg p-2.5">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-emerald-500 text-sm flex-shrink-0"></i>
                        <div class="flex-1">
                            <h4 class="font-bold text-emerald-800 text-[11px]">Pedido cumple todos los mínimos</h4>
                            <p class="text-[10px] text-emerald-700">Los mínimos de grupo y producto se cumplieron al finalizar.</p>
                        </div>
                    </div>
                </div>

                <!-- FECHA PEDIDO (ya no muestra cliente ni sucursal duplicados) -->
                <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-calendar text-sm"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <span class="text-[9px] text-gray-400 font-medium uppercase tracking-wide block">Fecha del Pedido</span>
                        <span class="text-xs font-medium text-gray-800 truncate block mt-0.5">{{ fechaPedido }}</span>
                    </div>
                </div>

                <!-- KPIS -->
                <div class="grid grid-cols-3 gap-2">
                    <div class="bg-primary-50 rounded-lg p-2.5 border border-primary-100 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-box text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] text-gray-500 font-medium uppercase block">Contenedores</span>
                            <span class="text-sm font-bold text-gray-800">{{ totalContenedores }}</span>
                        </div>
                    </div>
                    <div class="bg-emerald-50 rounded-lg p-2.5 border border-emerald-100 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-cubes text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] text-gray-500 font-medium uppercase block">Productos</span>
                            <span class="text-sm font-bold text-gray-800">{{ totalProductos }}</span>
                        </div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-2.5 border border-blue-100 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-shopping-basket text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] text-gray-500 font-medium uppercase block">Unidades</span>
                            <span class="text-sm font-bold text-gray-800">{{ formatearNumero(totalUnidades) }}</span>
                        </div>
                    </div>
                </div>

                <!-- FECHA ENTREGA + TIPO PRECIO + OBSERVACIONES -->
                <div class="grid grid-cols-1 gap-2">
                    <div v-if="pedido.FechaEntrega" class="bg-primary-50 rounded-lg p-2.5 border border-primary-100 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-truck text-sm"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="text-[9px] text-gray-500 font-medium uppercase block">Fecha de Entrega</span>
                            <span class="text-xs font-semibold text-primary-700">{{ formatearFecha(pedido.FechaEntrega) }}</span>
                        </div>
                    </div>

                    <div v-if="pedido.TipoPrecio" class="bg-indigo-50 rounded-lg p-2.5 border border-indigo-100 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0">
                            <i :class="pedido.TipoPrecio === 'con_factura' ? 'fas fa-file-invoice-dollar' : 'fas fa-receipt'" class="text-sm"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="text-[9px] text-gray-500 font-medium uppercase block">Tipo de Precio</span>
                            <span class="text-xs font-semibold text-indigo-700">
                                {{ pedido.TipoPrecio === 'con_factura' ? 'Con Factura' : 'Sin Factura' }}
                            </span>
                        </div>
                    </div>

                    <div v-if="pedido.Observaciones" class="bg-amber-50 rounded-lg p-2.5 border border-amber-100">
                        <div class="flex items-start gap-2">
                            <i class="fas fa-comment-alt text-amber-600 text-sm mt-0.5 flex-shrink-0"></i>
                            <div class="min-w-0 flex-1">
                                <span class="text-[9px] font-semibold text-amber-800 uppercase block mb-0.5">Observaciones</span>
                                <p class="text-xs text-gray-700 leading-relaxed break-words">{{ pedido.Observaciones }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LISTA DE CONTENEDORES -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                            <i class="fas fa-layer-group text-primary-500 text-[10px]"></i>
                            Contenedores y Productos
                        </h4>
                        <span class="text-[10px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
                            {{ totalContenedores }} contenedor(es)
                        </span>
                    </div>

                    <div v-if="detallesAgrupados.length === 0"
                         class="text-center text-gray-400 py-8 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                        <i class="fas fa-inbox text-2xl mb-2 block text-gray-300"></i>
                        <p class="text-sm">No hay productos registrados</p>
                    </div>

                    <div v-else class="space-y-2">
                        <div v-for="(item, idx) in detallesAgrupados"
                             :key="idx"
                             class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">

                            <!-- Header del contenedor -->
                            <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 bg-primary-50 border-b border-primary-100">
                                <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                    <span class="font-mono bg-primary-600 text-white px-2 py-0.5 rounded text-[10px] font-bold flex-shrink-0">
                                        #{{ idx + 1 }}
                                    </span>
                                    <span class="font-semibold text-gray-800 text-xs truncate">{{ item.Codigo }}</span>
                                    <span class="text-[9px] text-gray-600 bg-white px-1.5 py-0.5 rounded border border-primary-200 flex-shrink-0">
                                        Cap: {{ formatearNumero(item.CapacidadTotal) }}
                                    </span>
                                    <span v-if="item.SubClienteNombre"
                                          class="text-[9px] text-primary-700 bg-primary-100 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">
                                        <i class="fas fa-user-tag mr-0.5"></i>
                                        {{ item.SubClienteNombre }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="text-[10px] text-gray-500">
                                        <i class="fas fa-cubes mr-0.5"></i>
                                        {{ formatearNumero(item.total_unidades) }} und
                                    </span>
                                    <span v-if="item.subtotal != null" class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full">
                                        Bs. {{ formatearPrecio(item.subtotal) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Tabla de productos -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-gray-100 bg-gray-50/50 text-[9px] font-semibold text-gray-400 uppercase tracking-wider">
                                            <th class="py-1.5 px-3 w-20">Código</th>
                                            <th class="py-1.5 px-3">Producto</th>
                                            <th class="py-1.5 px-3 text-right w-20">Cant.</th>
                                            <th v-if="item.productos?.[0]?.Precio != null" class="py-1.5 px-3 text-right w-24">Precio</th>
                                            <th v-if="item.productos?.[0]?.Subtotal != null" class="py-1.5 px-3 text-right w-24">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="(producto, pIdx) in item.productos"
                                            :key="pIdx"
                                            class="hover:bg-gray-50 transition">
                                            <td class="py-1.5 px-3 font-mono text-[10px] text-gray-500">
                                                {{ producto.Codigo }}
                                            </td>
                                            <td class="py-1.5 px-3 text-xs text-gray-700" :title="producto.Descripcion">
                                                {{ producto.Descripcion }}
                                            </td>
                                            <td class="py-1.5 px-3 text-right font-semibold text-gray-800 tabular-nums text-xs">
                                                {{ formatearNumero(producto.Cantidad) }}
                                            </td>
                                            <td v-if="producto.Precio != null" class="py-1.5 px-3 text-right text-gray-600 tabular-nums text-[11px]">
                                                Bs. {{ formatearPrecio(producto.Precio) }}
                                            </td>
                                            <td v-if="producto.Subtotal != null" class="py-1.5 px-3 text-right font-bold text-primary-600 tabular-nums text-[11px]">
                                                Bs. {{ formatearPrecio(producto.Subtotal) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ==================== FOOTER ==================== -->
            <div class="border-t border-gray-200 p-2.5 bg-gray-50 flex flex-wrap justify-between items-center gap-2 flex-shrink-0">
                <button @click="cerrarModal"
                        class="px-3 py-1.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 rounded-md text-xs font-medium transition flex items-center gap-1.5">
                    <i class="fas fa-times text-[10px]"></i>
                    Cerrar
                </button>

                <div class="flex items-center gap-2">
                    <div v-if="pedido.TotalGeneral > 0" class="text-right mr-1">
                        <p class="text-[9px] text-gray-400 font-medium uppercase">Total General</p>
                        <p class="text-sm font-extrabold text-primary-700 tabular-nums">Bs. {{ formatearPrecio(pedido.TotalGeneral) }}</p>
                    </div>

                    <button v-if="esFinalizado"
                            @click="abrirPdf(pedido.IdPedidoCliente)"
                            class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5">
                        <i class="fas fa-file-pdf text-[10px]"></i>
                        Descargar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
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

.tabular-nums {
    font-variant-numeric: tabular-nums;
}

@media (min-width: 1024px) {
    input, button {
        font-size: 13px !important;
    }
}
</style>