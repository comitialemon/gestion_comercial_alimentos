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

const isMobile = ref(false)
const handleResize = () => { isMobile.value = window.innerWidth < 640 }

const totalUnidades = computed(() => {
    let total = 0
    props.detallesAgrupados?.forEach(item => {
        item.productos?.forEach(p => { total += Number(p.Cantidad) || 0 })
    })
    return total
})

const totalContenedores = computed(() => props.detallesAgrupados?.length || 0)
const totalProductos = computed(() => {
    let total = 0
    props.detallesAgrupados?.forEach(item => { total += item.productos?.length || 0 })
    return total
})

const fechaPedido = computed(() => {
    if (props.pedido?.FechaPedido) {
        return new Date(props.pedido.FechaPedido).toLocaleString('es-BO', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
        })
    }
    return '-'
})

// 🔥 LÍNEA DE TIEMPO
const timelineCompacto = computed(() => {
    const estado = props.pedido?.EstadoPedido
    const historial = props.pedido?.historial || []
    const envio = props.pedido?.envio

    const getFechaEstado = (estadoNombre) => {
        const h = historial.find(item => item.EstadoNuevo === estadoNombre)
        if (h) return new Date(h.FechaCambio).toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit' })
        return null
    }

    const pasos = [
        { key: 'Pendiente', short: 'Pagado', icon: 'fa-credit-card', fecha: getFechaEstado('Pendiente') },
        { key: 'En Cámara', short: 'Armado', icon: 'fa-box-open', fecha: getFechaEstado('En Cámara') },
        { key: 'En Distribución', short: 'Despachado', icon: 'fa-truck', fecha: getFechaEstado('En Distribución') },
        { key: 'En Transportadora', short: 'En Viaje', icon: 'fa-shipping-fast', fecha: getFechaEstado('En Transportadora') },
        { key: 'Recibido', short: 'Recibido', icon: 'fa-check-double', fecha: getFechaEstado('Recibido') },
    ]

    const ordenEstados = ['Pendiente', 'En Cámara', 'En Distribución', 'En Transportadora', 'Recibido']
    const indexActual = ordenEstados.indexOf(estado)

    return pasos.map((paso, index) => {
        let status = 'pending'
        if (index < indexActual) status = 'done'
        if (index === indexActual) status = 'current'
        return { ...paso, status }
    })
})

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
    return new Date(fecha).toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const cerrarModal = () => emit('cerrar')
const abrirPdf = (id) => window.open(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${id}/pdf`, '_blank')

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})
onUnmounted(() => window.removeEventListener('resize', handleResize))
</script>

<template>
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-2 sm:p-4" @click.self="cerrarModal">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[95vh] flex flex-col overflow-hidden">

            <div class="bg-primary-600 p-3 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-file-invoice text-white text-base"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-white font-bold text-sm truncate flex items-center gap-2 flex-wrap">
                            Pedido #{{ pedido?.NumeroPedido || 'Nuevo' }}
                        </h3>
                        <p class="text-white/80 text-[10px] truncate mt-0.5">
                            <i class="fas fa-user text-[9px] mr-1"></i> {{ clienteNombre || 'Sin cliente' }}
                            <span class="mx-1 opacity-60">•</span>
                            <i class="fas fa-store text-[9px] mr-1"></i> {{ sucursalNombre || 'Sin sucursal' }}
                        </p>
                    </div>
                </div>
                <button @click="cerrarModal" class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0 transition">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-3">

                <!-- LÍNEA DE TIEMPO -->
                <div v-if="['Pendiente', 'En Cámara', 'En Distribución', 'En Transportadora', 'Recibido'].includes(pedido.EstadoPedido)"
                     class="bg-gray-50 border border-gray-200 rounded-xl p-3 sm:p-4">

                    <div class="flex justify-between items-center mb-1 flex-wrap gap-2">
                        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Seguimiento del Pedido</span>
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- 🔥 Mostramos la transportadora desde el identificador y el costo -->
                            <span v-if="pedido.envio?.NumeroGuia" class="text-[9px] font-mono bg-white border border-gray-300 px-1.5 py-0.5 rounded text-gray-600">
                                Guía: {{ pedido.envio.NumeroGuia }}
                            </span>
                            <span v-if="pedido.envio?.CostoEnvio > 0" class="text-[9px] font-mono bg-white border border-gray-300 px-1.5 py-0.5 rounded text-gray-600">
                                Costo Envío: Bs. {{ formatearPrecio(pedido.envio.CostoEnvio) }}
                            </span>
                        </div>
                    </div>
                    <p v-if="pedido.envio?.transportadora" class="text-[10px] text-gray-500 mb-3">
                        <i class="fas fa-shipping-fast mr-1 text-gray-400"></i> {{ pedido.envio.transportadora.identificador?.Nombre || 'Sin transportadora' }}
                    </p>

                    <div class="relative flex justify-between items-center mt-4">
                        <div class="absolute left-0 right-0 top-3 h-0.5 bg-gray-200 -z-0"></div>

                        <div v-for="(paso, idx) in timelineCompacto" :key="idx" class="relative flex flex-col items-center flex-1 z-10">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center border-2 bg-white transition-all duration-300"
                                 :class="{
                                     'border-emerald-500 text-emerald-500': paso.status === 'done',
                                     'border-primary-500 text-primary-600 ring-4 ring-primary-100': paso.status === 'current',
                                     'border-gray-200 text-gray-300': paso.status === 'pending'
                                 }">
                                <i :class="['fas', paso.icon, paso.status === 'current' ? 'fa-spin' : '']" class="text-[10px]"></i>
                            </div>
                            <span class="text-[9px] sm:text-[10px] font-bold mt-1.5 text-center leading-tight"
                                  :class="{
                                      'text-emerald-600': paso.status === 'done',
                                      'text-primary-700': paso.status === 'current',
                                      'text-gray-400': paso.status === 'pending'
                                  }">
                                {{ paso.short }}
                            </span>
                            <span v-if="paso.fecha" class="text-[8px] text-gray-500 mt-0.5 bg-gray-100 px-1 rounded">
                                {{ paso.fecha }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- FECHA PEDIDO / ENTREGA -->
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200 flex items-center gap-2">
                        <i class="fas fa-calendar text-gray-500 text-sm"></i>
                        <div class="min-w-0">
                            <span class="text-[9px] text-gray-400 font-medium uppercase block">Fecha del Pedido</span>
                            <span class="text-xs font-medium text-gray-800 truncate block mt-0.5">{{ fechaPedido }}</span>
                        </div>
                    </div>
                    <div v-if="pedido.FechaEntrega" class="bg-primary-50 rounded-lg p-2.5 border border-primary-100 flex items-center gap-2">
                        <i class="fas fa-truck text-primary-600 text-sm"></i>
                        <div class="min-w-0">
                            <span class="text-[9px] text-gray-500 font-medium uppercase block">Entrega Estimada</span>
                            <span class="text-xs font-semibold text-primary-700">{{ formatearFecha(pedido.FechaEntrega) }}</span>
                        </div>
                    </div>
                </div>

                <!-- KPIS -->
                <div class="grid grid-cols-3 gap-2">
                    <div class="bg-primary-50 rounded-lg p-2.5 border border-primary-100 text-center">
                        <span class="text-[9px] text-gray-500 font-medium uppercase block">Contenedores</span>
                        <span class="text-sm font-bold text-gray-800">{{ totalContenedores }}</span>
                    </div>
                    <div class="bg-emerald-50 rounded-lg p-2.5 border border-emerald-100 text-center">
                        <span class="text-[9px] text-gray-500 font-medium uppercase block">Productos</span>
                        <span class="text-sm font-bold text-gray-800">{{ totalProductos }}</span>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-2.5 border border-blue-100 text-center">
                        <span class="text-[9px] text-gray-500 font-medium uppercase block">Unidades</span>
                        <span class="text-sm font-bold text-gray-800">{{ formatearNumero(totalUnidades) }}</span>
                    </div>
                </div>

                <!-- OBSERVACIONES -->
                <div v-if="pedido.Observaciones" class="bg-amber-50 rounded-lg p-2.5 border border-amber-100">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-comment-alt text-amber-600 text-sm mt-0.5 flex-shrink-0"></i>
                        <div class="min-w-0 flex-1">
                            <span class="text-[9px] font-semibold text-amber-800 uppercase block mb-0.5">Observaciones</span>
                            <p class="text-xs text-gray-700 leading-relaxed break-words">{{ pedido.Observaciones }}</p>
                        </div>
                    </div>
                </div>

                <!-- LISTA DE CONTENEDORES -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1.5">
                        <i class="fas fa-layer-group text-primary-500 text-[10px]"></i> Detalle de Productos
                    </h4>
                    <div v-if="detallesAgrupados.length === 0" class="text-center text-gray-400 py-6 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                        <p class="text-sm">No hay productos registrados</p>
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="(item, idx) in detallesAgrupados" :key="idx" class="border border-gray-200 rounded-lg overflow-hidden bg-white">
                            <div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-200">
                                <span class="font-semibold text-gray-800 text-xs">{{ item.Codigo }}</span>
                                <span class="text-[10px] text-gray-500">{{ formatearNumero(item.total_unidades) }} und</span>
                            </div>
                            <table class="w-full text-left border-collapse">
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="(producto, pIdx) in item.productos" :key="pIdx" class="hover:bg-gray-50 transition">
                                        <td class="py-1.5 px-3 text-xs text-gray-700">{{ producto.Descripcion }}</td>
                                        <td class="py-1.5 px-3 text-right font-semibold text-gray-800 text-xs">{{ formatearNumero(producto.Cantidad) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 p-2.5 bg-gray-50 flex justify-between items-center flex-shrink-0">
                <button @click="cerrarModal" class="px-3 py-1.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 rounded-md text-xs font-medium transition">
                    Cerrar
                </button>
                <div class="flex items-center gap-2">
                    <div v-if="pedido.TotalGeneral > 0" class="text-right mr-1">
                        <p class="text-[9px] text-gray-400 font-medium uppercase">Total</p>
                        <p class="text-sm font-extrabold text-primary-700">Bs. {{ formatearPrecio(pedido.TotalGeneral) }}</p>
                    </div>
                    <button v-if="pedido.ActivoInactivo === 1" @click="abrirPdf(pedido.IdPedidoCliente)" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.overflow-y-auto::-webkit-scrollbar { width: 4px; }
.overflow-y-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
</style>