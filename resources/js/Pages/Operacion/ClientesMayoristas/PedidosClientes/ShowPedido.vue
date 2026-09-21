<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    pedido: {
        type: Object,
        required: true
    },
    detallesAgrupados: {
        type: Array,
        default: () => []
    },
    clienteNombre: {
        type: String,
        default: ''
    },
    sucursalNombre: {
        type: String,
        default: ''
    }
})

const emit = defineEmits(['cerrar'])

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const handleResize = () => { isMobile.value = window.innerWidth < 640 }

// ==================== COMPUTED ====================
const totalUnidades = computed(() => {
    let total = 0
    if (props.detallesAgrupados?.length > 0) {
        props.detallesAgrupados.forEach(item => {
            item.productos?.forEach(p => {
                total += Number(p.Cantidad) || 0
            })
        })
    }
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
    return new Date().toLocaleString('es-BO')
})

// ==================== HELPERS ====================
const formatearNumero = (valor) => {
    if (valor === undefined || valor === null || valor === '') return '0'
    const numero = parseFloat(valor)
    return isNaN(numero) ? '0' : numero.toFixed(0)
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
const cerrarModal = () => {
    emit('cerrar')
}

const abrirPdf = (id) => {
    window.open(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${id}/pdf`, '_blank')
}
</script>

<template>
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-3 sm:p-4" @click.self="cerrarModal">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
            
            <!-- ==================== HEADER ==================== -->
            <div class="bg-primary-600 p-3 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-file-invoice text-white text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-white font-semibold text-sm truncate flex items-center gap-2">
                            Pedido #{{ pedido?.NumeroPedido || 'Nuevo' }}
                            <span 
                                class="px-2 py-0.5 text-[9px] rounded-full font-medium border flex items-center gap-1"
                                :class="getEstadoBadge(pedido.EstadoPedido)"
                            >
                                <i :class="getEstadoIcono(pedido.EstadoPedido)" class="text-[8px]"></i>
                                {{ pedido.EstadoPedido || 'Borrador' }}
                            </span>
                        </h3>
                        <p class="text-white/80 text-[10px] truncate mt-0.5">
                            {{ clienteNombre || 'Sin cliente' }}
                            <span class="mx-1 opacity-60">•</span>
                            {{ sucursalNombre || 'Sin sucursal' }}
                        </p>
                    </div>
                </div>
                <button @click="cerrarModal" class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0 transition">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- ==================== BODY ==================== -->
            <div class="flex-1 overflow-y-auto p-3 space-y-3">

                <!-- DATOS PRINCIPALES (SIN OPERADOR) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                        <span class="text-[9px] text-gray-400 font-medium uppercase tracking-wide block">Cliente</span>
                        <span class="text-xs font-medium text-gray-800 truncate block" :title="clienteNombre">
                            {{ clienteNombre || 'Sin cliente' }}
                        </span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                        <span class="text-[9px] text-gray-400 font-medium uppercase tracking-wide block">Sucursal</span>
                        <span class="text-xs font-medium text-gray-800 truncate block" :title="sucursalNombre">
                            {{ sucursalNombre || 'Sin sucursal' }}
                        </span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                        <span class="text-[9px] text-gray-400 font-medium uppercase tracking-wide block">Fecha</span>
                        <span class="text-xs font-medium text-gray-800 truncate block">{{ fechaPedido }}</span>
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

                <!-- FECHA DE ENTREGA + OBSERVACIONES -->
                <div class="grid grid-cols-1 gap-2">
                    <div v-if="pedido.FechaEntrega" class="bg-primary-50 rounded-lg p-2.5 border border-primary-100 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-truck text-sm"></i>
                        </div>
                        <div>
                            <span class="text-[9px] text-gray-500 font-medium uppercase block">Fecha de Entrega</span>
                            <span class="text-xs font-semibold text-primary-700">{{ formatearFecha(pedido.FechaEntrega) }}</span>
                        </div>
                    </div>

                    <div v-if="pedido.Observaciones" class="bg-amber-50 rounded-lg p-2.5 border border-amber-100">
                        <div class="flex items-start gap-2">
                            <i class="fas fa-comment-alt text-amber-600 text-sm mt-0.5 flex-shrink-0"></i>
                            <div>
                                <span class="text-[9px] font-semibold text-amber-800 uppercase block mb-0.5">Observaciones</span>
                                <p class="text-xs text-gray-700 leading-relaxed">{{ pedido.Observaciones }}</p>
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
                            {{ detallesAgrupados.length }} grupo(s)
                        </span>
                    </div>

                    <div v-if="detallesAgrupados.length === 0" class="text-center text-gray-400 py-8 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                        <i class="fas fa-inbox text-2xl mb-2 block text-gray-300"></i>
                        <p class="text-sm">No hay productos registrados</p>
                    </div>

                    <div v-else class="space-y-2">
                        <div 
                            v-for="(item, idx) in detallesAgrupados" 
                            :key="idx"
                            class="border border-gray-200 rounded-lg overflow-hidden bg-white"
                        >
                            <div class="flex items-center justify-between px-3 py-2 bg-primary-50 border-b border-primary-100">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <span class="font-mono bg-primary-600 text-white px-1.5 py-0.5 rounded text-[10px] font-bold flex-shrink-0">
                                        #{{ idx + 1 }}
                                    </span>
                                    <span class="font-semibold text-gray-800 text-xs truncate">{{ item.Codigo }}</span>
                                    <span class="text-[9px] text-gray-500 bg-white px-1.5 py-0.5 rounded border border-primary-200 flex-shrink-0">
                                        Cap: {{ formatearNumero(item.CapacidadTotal) }} und
                                    </span>
                                </div>
                                <span class="font-bold text-primary-700 text-xs flex-shrink-0 ml-2">
                                    {{ formatearNumero(item.total_unidades) }} und
                                </span>
                            </div>

                            <div class="divide-y divide-gray-100">
                                <div 
                                    v-for="(producto, pIdx) in item.productos" 
                                    :key="pIdx"
                                    class="flex justify-between items-center py-2 px-3 hover:bg-gray-50 transition"
                                >
                                    <div class="flex items-center gap-2 min-w-0 flex-1 pr-2">
                                        <span class="font-mono text-[9px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded flex-shrink-0">
                                            {{ producto.Codigo }}
                                        </span>
                                        <span class="text-xs text-gray-700 truncate" :title="producto.Descripcion">
                                            {{ producto.Descripcion }}
                                        </span>
                                    </div>
                                    <span class="font-semibold text-gray-800 bg-gray-100 px-2 py-0.5 rounded text-xs flex-shrink-0 tabular-nums">
                                        {{ formatearNumero(producto.Cantidad) }} und
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== FOOTER ==================== -->
            <div class="border-t border-gray-200 p-3 bg-gray-50 flex justify-between items-center flex-shrink-0">
                <button 
                    @click="cerrarModal"
                    class="px-3 py-1.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 rounded-md text-xs font-medium transition flex items-center gap-1.5"
                >
                    <i class="fas fa-times text-[10px]"></i>
                    Cerrar
                </button>
                
                <button 
                    v-if="pedido.ActivoInactivo === 1"
                    @click="abrirPdf(pedido.IdPedidoCliente)"
                    class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5"
                >
                    <i class="fas fa-file-pdf text-[10px]"></i>
                    Descargar PDF
                </button>
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

@media (min-width: 1024px) {
    input, button {
        font-size: 13px !important;
    }
}
</style>