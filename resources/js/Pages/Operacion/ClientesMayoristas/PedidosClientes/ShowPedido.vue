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
    },
    operadorNombre: {
        type: String,
        default: ''
    }
})

const emit = defineEmits(['cerrar'])

// ==================== COMPUTADOS ====================
const totalUnidades = computed(() => {
    let total = 0
    if (props.detallesAgrupados && props.detallesAgrupados.length > 0) {
        props.detallesAgrupados.forEach(item => {
            if (item.productos) {
                item.productos.forEach(p => {
                    total += Number(p.Cantidad) || 0
                })
            }
        })
    }
    return total
})

const totalContenedores = computed(() => {
    return props.detallesAgrupados ? props.detallesAgrupados.length : 0
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
        'Borrador': 'bg-amber-50 text-amber-700 border-amber-200',
        'Pendiente': 'bg-blue-50 text-blue-700 border-blue-200',
        'En Proceso': 'bg-orange-50 text-orange-700 border-orange-200',
        'Entregado': 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Cancelado': 'bg-rose-50 text-rose-700 border-rose-200'
    }
    return badges[estado] || 'bg-gray-50 text-gray-700 border-gray-200'
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

// ==================== FUNCIONES ====================
const cerrarModal = () => {
    emit('cerrar')
}

const abrirPdf = (id) => {
    const url = `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${id}/pdf`
    window.open(url, '_blank')
}
</script>

<template>
    <!-- CONTENEDOR PRINCIPAL DEL MODAL CON ALTURA MÁXIMA ESTRICTA -->
    <div class="w-full max-h-[85vh] flex flex-col bg-white text-gray-800 text-xs rounded-2xl shadow-2xl overflow-hidden">
        
        <!-- HEADER FIJO -->
        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="font-bold text-gray-900 text-sm">
                    Pedido #{{ pedido?.NumeroPedido || 'Nuevo' }}
                </span>
                <span 
                    class="px-2 py-0.5 text-[11px] rounded-full font-semibold border flex items-center gap-1"
                    :class="getEstadoBadge(pedido.EstadoPedido)"
                >
                    <i :class="getEstadoIcono(pedido.EstadoPedido)"></i>
                    {{ pedido.EstadoPedido || 'Borrador' }}
                </span>
            </div>
            <button 
                @click="cerrarModal"
                class="w-7 h-7 rounded-lg hover:bg-gray-200 text-gray-400 hover:text-gray-700 transition flex items-center justify-center cursor-pointer"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- CUERPO CON SCROLL FORZADO -->
        <div class="p-4 overflow-y-auto flex-1 space-y-3 custom-scroll">
            
            <!-- DATOS BÁSICOS -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-gray-50/60 p-2.5 rounded-xl border border-gray-100">
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-semibold">Cliente</span>
                    <span class="font-medium text-gray-700 truncate block">{{ clienteNombre || 'Sin cliente' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-semibold">Sucursal</span>
                    <span class="font-medium text-gray-700 truncate block">{{ sucursalNombre || 'Sin sucursal' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-semibold">Operador</span>
                    <span class="font-medium text-gray-700 truncate block">{{ operadorNombre || 'Sin operador' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-semibold">Fecha</span>
                    <span class="font-medium text-gray-700 truncate block">{{ fechaPedido }}</span>
                </div>
            </div>

            <!-- MINI KPIS -->
            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="bg-primary-50/20 border border-primary-100/50 rounded-lg p-2">
                    <span class="text-[10px] text-gray-400 font-semibold block uppercase">Contenedores</span>
                    <span class="text-sm font-bold text-gray-800">{{ totalContenedores }}</span>
                </div>
                <div class="bg-primary-50/20 border border-primary-100/50 rounded-lg p-2">
                    <span class="text-[10px] text-gray-400 font-semibold block uppercase">Total Unidades</span>
                    <span class="text-sm font-bold text-primary-600">{{ formatearNumero(totalUnidades) }}</span>
                </div>
                <div class="bg-primary-50/20 border border-primary-100/50 rounded-lg p-2">
                    <span class="text-[10px] text-gray-400 font-semibold block uppercase">Entrega</span>
                    <span class="text-xs font-bold text-gray-700 mt-0.5 block">
                        {{ pedido.FechaEntrega ? formatearFecha(pedido.FechaEntrega) : 'N/D' }}
                    </span>
                </div>
            </div>

            <!-- OBSERVACIONES -->
            <div v-if="pedido.Observaciones" class="bg-amber-50/40 border border-amber-100 p-2.5 rounded-lg">
                <span class="text-[10px] font-bold text-amber-800 uppercase block mb-0.5">Observaciones</span>
                <p class="text-gray-600 leading-tight">{{ pedido.Observaciones }}</p>
            </div>

            <!-- LISTA DE PRODUCTOS -->
            <div class="space-y-2 pt-1">
                <div class="flex items-center justify-between text-gray-400 font-semibold uppercase text-[10px] px-1">
                    <span>Contenedores y Productos</span>
                    <span>{{ detallesAgrupados.length }} grupo(s)</span>
                </div>

                <div v-if="detallesAgrupados.length === 0" class="text-center text-gray-400 py-6 bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                    <i class="fas fa-inbox text-xl mb-1 block"></i>
                    <span>No hay productos registrados</span>
                </div>

                <div v-else class="space-y-2">
                    <div 
                        v-for="(item, idx) in detallesAgrupados" 
                        :key="idx"
                        class="border border-gray-200/70 rounded-lg overflow-hidden bg-white shadow-2xs"
                    >
                        <div class="flex items-center justify-between px-3 py-1.5 bg-gray-50/90 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="font-mono bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded text-[10px] font-bold">
                                    #{{ idx + 1 }}
                                </span>
                                <span class="font-semibold text-gray-800">{{ item.Codigo }}</span>
                                <span class="text-gray-400 text-[10px]">(Cap: {{ formatearNumero(item.CapacidadTotal) }})</span>
                            </div>
                            <span class="font-bold text-primary-700 text-[11px]">
                                {{ formatearNumero(item.total_unidades) }} und
                            </span>
                        </div>

                        <div class="divide-y divide-gray-50">
                            <div 
                                v-for="(producto, pIdx) in item.productos" 
                                :key="pIdx"
                                class="flex justify-between items-center py-1.5 px-3 hover:bg-gray-50/40"
                            >
                                <div class="flex items-center gap-2 pr-2">
                                    <span class="font-mono text-gray-400 text-[10px]">{{ producto.Codigo }}</span>
                                    <span class="text-gray-700">{{ producto.Descripcion }}</span>
                                </div>
                                <span class="font-semibold text-gray-900 bg-gray-100 px-2 py-0.5 rounded text-[11px] flex-shrink-0">
                                    {{ formatearNumero(producto.Cantidad) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FOOTER FIJO -->
        <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50 flex justify-between items-center flex-shrink-0">
            <button 
                @click="cerrarModal"
                class="px-3.5 py-1.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-lg font-medium transition shadow-2xs cursor-pointer"
            >
                Cerrar
            </button>
            
            <button 
                v-if="pedido.ActivoInactivo === 1"
                @click="abrirPdf(pedido.IdPedidoCliente)"
                class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
            >
                <i class="fas fa-file-pdf"></i>
                <span>PDF</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Asegura que el scroll funcione perfectamente en navegadores webkit */
.custom-scroll {
    overflow-y: auto;
    max-height: 55vh;
}
.custom-scroll::-webkit-scrollbar {
    width: 5px;
}
.custom-scroll::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.custom-scroll::-webkit-scrollbar-thumb {
    body { background: #cbd5e1; }
    background: #cbd5e1;
    border-radius: 4px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>