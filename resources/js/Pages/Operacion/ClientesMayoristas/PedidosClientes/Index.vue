<script setup>
import { ref, computed, inject, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    pedidos: {
        type: Object,
        default: () => ({ data: [] })
    }
})

// ==================== ESTADO ====================
const loading = ref(false)
const buscador = ref('')
const estadoFiltro = ref('')
const mostrarDetalle = ref(false)
const pedidoSeleccionado = ref(null)

// ==================== COMPUTADOS ====================
const pedidosFiltrados = computed(() => {
    if (!props.pedidos?.data) return []
    
    let filtrados = props.pedidos.data
    
    if (buscador.value) {
        const termino = buscador.value.toLowerCase()
        filtrados = filtrados.filter(p => {
            const numero = p.NumeroPedido?.toString() || ''
            const cliente = p.cliente?.Nombre || ''
            return numero.toLowerCase().includes(termino) || 
                   cliente.toLowerCase().includes(termino)
        })
    }
    
    if (estadoFiltro.value) {
        filtrados = filtrados.filter(p => p.EstadoPedido === estadoFiltro.value)
    }
    
    return filtrados
})

const totalPedidos = computed(() => {
    return props.pedidos?.total || 0
})

// ==================== FUNCIONES ====================
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    })
}

const formatearFechaHora = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const getEstadoBadge = (estado) => {
    const badges = {
        'Borrador': 'bg-yellow-100 text-yellow-800',
        'Pendiente': 'bg-blue-100 text-blue-800',
        'En Proceso': 'bg-orange-100 text-orange-800',
        'Entregado': 'bg-green-100 text-green-800',
        'Cancelado': 'bg-red-100 text-red-800'
    }
    return badges[estado] || 'bg-gray-100 text-gray-800'
}

const getEstadoIcono = (estado) => {
    const iconos = {
        'Borrador': 'fa-pencil-alt',
        'Pendiente': 'fa-clock',
        'En Proceso': 'fa-cog',
        'Entregado': 'fa-check-circle',
        'Cancelado': 'fa-times-circle'
    }
    return iconos[estado] || 'fa-circle'
}

const formatearNumero = (valor) => {
    if (valor === undefined || valor === null) return '0'
    return Number(valor).toFixed(0)
}

// 🔥 FUNCIÓN PARA ABRIR PDF - CORREGIDA
const abrirPdf = (id) => {
    // ✅ URL exacta que coincide con la ruta definida en web.php
    const url = `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${id}/pdf`
    window.open(url, '_blank')
}

const verDetalle = (pedido) => {
    pedidoSeleccionado.value = pedido
    mostrarDetalle.value = true
}

const cerrarDetalle = () => {
    mostrarDetalle.value = false
    pedidoSeleccionado.value = null
}

const aplicarFiltros = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes', {
        buscar: buscador.value || undefined,
        estado: estadoFiltro.value || undefined
    }, {
        preserveState: true,
        replace: true
    })
}

const limpiarFiltros = () => {
    buscador.value = ''
    estadoFiltro.value = ''
    aplicarFiltros()
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search)
    if (urlParams.get('buscar')) {
        buscador.value = urlParams.get('buscar')
    }
    if (urlParams.get('estado')) {
        estadoFiltro.value = urlParams.get('estado')
    }
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-10">
        <div class="max-w-7xl mx-auto px-3 py-4">
            
            <!-- HEADER -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Mis Pedidos</h1>
                    <p class="text-[10px] text-gray-400">
                        {{ totalPedidos }} pedido(s) realizados
                    </p>
                </div>
                <Link 
                    href="/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create"
                    class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition flex items-center gap-2 shadow-sm"
                >
                    <i class="fas fa-plus"></i>
                    Nuevo Pedido
                </Link>
            </div>

            <!-- FILTROS -->
            <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Buscador -->
                    <div class="flex items-center gap-2 flex-1 min-w-[150px]">
                        <div class="relative flex-1">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            <input 
                                type="text"
                                v-model="buscador"
                                @input="aplicarFiltros"
                                placeholder="Buscar por número o cliente..."
                                class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none"
                            />
                            <button 
                                v-if="buscador"
                                @click="buscador = ''; aplicarFiltros()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Estado -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500">Estado:</label>
                        <select 
                            v-model="estadoFiltro" 
                            @change="aplicarFiltros"
                            class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none"
                        >
                            <option value="">Todos</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="En Proceso">En Proceso</option>
                            <option value="Entregado">Entregado</option>
                            <option value="Cancelado">Cancelado</option>
                        </select>
                    </div>

                    <!-- Limpiar -->
                    <button 
                        v-if="buscador || estadoFiltro"
                        @click="limpiarFiltros"
                        class="text-xs text-gray-400 hover:text-gray-600 transition"
                    >
                        <i class="fas fa-times mr-1"></i>
                        Limpiar filtros
                    </button>
                </div>
            </div>

            <!-- LISTA DE PEDIDOS -->
            <div v-if="pedidosFiltrados.length > 0" class="space-y-3">
                <div 
                    v-for="pedido in pedidosFiltrados" 
                    :key="pedido.IdPedidoCliente"
                    class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow overflow-hidden border border-gray-100"
                >
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <!-- Info principal -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="font-bold text-primary-700 text-sm font-mono">
                                    #{{ pedido.NumeroPedido || 'Nuevo' }}
                                </span>
                                <span 
                                    class="px-2 py-0.5 text-[10px] rounded-full font-medium"
                                    :class="getEstadoBadge(pedido.EstadoPedido)"
                                >
                                    <i :class="getEstadoIcono(pedido.EstadoPedido)" class="mr-1 text-[8px]"></i>
                                    {{ pedido.EstadoPedido || 'Borrador' }}
                                </span>
                                <span class="text-xs text-gray-400">
                                    <i class="fas fa-boxes mr-1"></i>
                                    {{ pedido.TotalContenedores || 0 }} contenedor(es)
                                </span>
                                <span class="text-xs font-semibold text-primary-600">
                                    {{ formatearNumero(pedido.TotalUnidades) }} und
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-gray-500">
                                <span>
                                    <i class="fas fa-calendar mr-1"></i>
                                    {{ formatearFechaHora(pedido.FechaPedido) }}
                                </span>
                                <span v-if="pedido.FechaEntrega">
                                    <i class="fas fa-calendar-check mr-1 text-green-500"></i>
                                    Entrega: {{ formatearFecha(pedido.FechaEntrega) }}
                                </span>
                                <span>
                                    <i class="fas fa-store mr-1"></i>
                                    {{ pedido.sucursal?.Nombre || 'Sin sucursal' }}
                                </span>
                                <span v-if="pedido.cliente?.Nombre">
                                    <i class="fas fa-user mr-1"></i>
                                    {{ pedido.cliente.Nombre }}
                                </span>
                            </div>
                        </div>

                        <!-- Acciones -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <!-- Ver detalle -->
                            <button 
                                @click="verDetalle(pedido)"
                                class="px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition flex items-center gap-1"
                            >
                                <i class="fas fa-eye text-[10px]"></i>
                                Ver
                            </button>

                            <!-- PDF (solo si está finalizado) -->
                            <button 
                                v-if="pedido.ActivoInactivo === 1"
                                @click="abrirPdf(pedido.IdPedidoCliente)"
                                class="px-3 py-1.5 text-xs bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition flex items-center gap-1"
                            >
                                <i class="fas fa-file-pdf text-[10px]"></i>
                                PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="props.pedidos?.links" class="flex justify-center mt-4">
                    <div class="flex gap-1 flex-wrap justify-center">
                        <Link 
                            v-for="link in props.pedidos.links" 
                            :key="link.label"
                            :href="link.url || '#'"
                            class="px-3 py-1.5 rounded-lg text-sm transition"
                            :class="{
                                'bg-primary-600 text-white': link.active,
                                'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200': !link.active && link.url,
                                'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url
                            }"
                            v-html="link.label"
                            preserve-state
                        />
                    </div>
                </div>
            </div>

            <!-- SIN PEDIDOS -->
            <div v-else class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="w-20 h-20 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-inbox text-primary-400 text-3xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-700">No hay pedidos</h3>
                <p class="text-sm text-gray-400 mt-1">
                    {{ buscador || estadoFiltro ? 'No hay pedidos que coincidan con los filtros' : 'Aún no has realizado ningún pedido' }}
                </p>
                <Link 
                    v-if="!buscador && !estadoFiltro"
                    href="/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create"
                    class="inline-block mt-4 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm transition"
                >
                    <i class="fas fa-plus mr-1"></i>
                    Crear primer pedido
                </Link>
            </div>
        </div>

        <!-- MODAL DE DETALLE -->
        <div 
            v-if="mostrarDetalle && pedidoSeleccionado"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="cerrarDetalle"
        >
            <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden shadow-2xl animate-fade-in-up">
                <!-- Header -->
                <div class="p-4 border-b bg-primary-50 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-gray-800">
                            Pedido #{{ pedidoSeleccionado.NumeroPedido || 'Nuevo' }}
                        </h3>
                        <p class="text-xs text-gray-500">
                            {{ formatearFechaHora(pedidoSeleccionado.FechaPedido) }}
                        </p>
                    </div>
                    <button 
                        @click="cerrarDetalle"
                        class="text-gray-400 hover:text-gray-600 transition"
                    >
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-4 overflow-y-auto max-h-[60vh]">
                    <!-- Estado -->
                    <div class="flex items-center gap-2 mb-3">
                        <span 
                            class="px-2 py-0.5 text-xs rounded-full font-medium"
                            :class="getEstadoBadge(pedidoSeleccionado.EstadoPedido)"
                        >
                            <i :class="getEstadoIcono(pedidoSeleccionado.EstadoPedido)" class="mr-1 text-[8px]"></i>
                            {{ pedidoSeleccionado.EstadoPedido || 'Borrador' }}
                        </span>
                        <span class="text-xs text-gray-400">•</span>
                        <span class="text-xs text-gray-500">
                            <i class="fas fa-store mr-1"></i>
                            {{ pedidoSeleccionado.sucursal?.Nombre || 'Sin sucursal' }}
                        </span>
                    </div>

                    <!-- Totales -->
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-[10px] text-gray-400">Contenedores</p>
                            <p class="text-lg font-bold text-gray-800">{{ pedidoSeleccionado.TotalContenedores || 0 }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-[10px] text-gray-400">Unidades</p>
                            <p class="text-lg font-bold text-primary-600">{{ formatearNumero(pedidoSeleccionado.TotalUnidades) }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-[10px] text-gray-400">Entrega</p>
                            <p class="text-sm font-bold text-gray-800">{{ pedidoSeleccionado.FechaEntrega ? formatearFecha(pedidoSeleccionado.FechaEntrega) : '-' }}</p>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div v-if="pedidoSeleccionado.Observaciones" class="mb-3 p-3 bg-gray-50 rounded-lg">
                        <p class="text-[10px] text-gray-400">Observaciones</p>
                        <p class="text-sm text-gray-600">{{ pedidoSeleccionado.Observaciones }}</p>
                    </div>

                    <!-- Productos (resumen) -->
                    <div v-if="pedidoSeleccionado.detalles && pedidoSeleccionado.detalles.length > 0" class="border-t pt-3">
                        <p class="text-xs font-medium text-gray-500 mb-2">Productos</p>
                        <div class="space-y-1 max-h-40 overflow-y-auto">
                            <div 
                                v-for="detalle in pedidoSeleccionado.detalles.slice(0, 10)" 
                                :key="detalle.IdPedidoClienteDetalle"
                                class="flex justify-between text-xs border-b border-gray-100 py-1"
                            >
                                <span class="text-gray-600 truncate flex-1">{{ detalle.producto?.Descripcion || 'Producto' }}</span>
                                <span class="font-bold text-gray-800 ml-2">{{ formatearNumero(detalle.Cantidad) }} und</span>
                            </div>
                            <div v-if="pedidoSeleccionado.detalles.length > 10" class="text-[10px] text-gray-400 text-center">
                                + {{ pedidoSeleccionado.detalles.length - 10 }} más...
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t bg-gray-50 flex justify-end gap-2">
                    <!-- ✅ PDF con la URL corregida -->
                    <button 
                        v-if="pedidoSeleccionado.ActivoInactivo === 1"
                        @click="abrirPdf(pedidoSeleccionado.IdPedidoCliente)"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm transition flex items-center gap-2"
                    >
                        <i class="fas fa-file-pdf"></i>
                        PDF
                    </button>
                    <button 
                        @click="cerrarDetalle"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm transition"
                    >
                        Cerrar
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

.animate-fade-in-up {
    animation: fadeInUp 0.25s ease-out;
}
</style>