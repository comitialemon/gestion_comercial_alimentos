<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    pedidosPendientes: { type: Array, default: () => [] },
    pedidosEnCamara: { type: Array, default: () => [] },
    pedidosEnDistribucion: { type: Array, default: () => [] },
    pedidosEnTransportadora: { type: Array, default: () => [] },
    transportadoras: { type: Array, default: () => [] },
})

const toast = inject('toast')
const page = usePage()

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const tabActiva = ref('pendiente')

const modalEstadoVisible = ref(false)
const modalTransportadoraVisible = ref(false)
const pedidoSeleccionado = ref(null)
const nuevoEstado = ref('')
const transportadoraSeleccionada = ref('')
const numeroGuia = ref('')
const costoEnvio = ref(0)

const nuevaTransportadora = ref({ Nombre: '', CI_NIT: '' })

// ==================== COMPUTED ====================
const totalPedidos = computed(() => {
    return props.pedidosPendientes.length +
        props.pedidosEnCamara.length +
        props.pedidosEnDistribucion.length +
        props.pedidosEnTransportadora.length
})

const pedidosActuales = computed(() => {
    switch (tabActiva.value) {
        case 'pendiente': return props.pedidosPendientes
        case 'camara': return props.pedidosEnCamara
        case 'distribucion': return props.pedidosEnDistribucion
        case 'transportadora': return props.pedidosEnTransportadora
        default: return []
    }
})

// ==================== FUNCIONES ====================
const abrirModalEstado = (pedido, estado) => {
    pedidoSeleccionado.value = pedido
    nuevoEstado.value = estado
    transportadoraSeleccionada.value = ''
    numeroGuia.value = ''
    costoEnvio.value = 0
    modalEstadoVisible.value = true
}

const confirmarCambioEstado = async () => {
    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/distribucion/${pedidoSeleccionado.value.IdPedidoCliente}/cambiar-estado`,
            {
                nuevo_estado: nuevoEstado.value,
                id_transportadora: transportadoraSeleccionada.value || null,
                numero_guia: numeroGuia.value || null,
                costo_envio: costoEnvio.value || 0,
            }
        )

        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            modalEstadoVisible.value = false
            router.reload()
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al cambiar estado')
    }
}

const crearTransportadora = async () => {
    if (!nuevaTransportadora.value.Nombre.trim()) {
        toast?.warning('Atención', 'El nombre de la transportadora es obligatorio.')
        return
    }
    if (!nuevaTransportadora.value.CI_NIT.toString().trim()) {
        toast?.warning('Atención', 'El CI/NIT es obligatorio.')
        return
    }

    try {
        const response = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/distribucion/transportadoras',
            nuevaTransportadora.value
        )
        if (response.data.success) {
            toast?.success('Éxito', 'Transportadora creada correctamente')
            modalTransportadoraVisible.value = false
            nuevaTransportadora.value = { Nombre: '', CI_NIT: '' }
            router.reload()
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al crear transportadora')
    }
}

const formatoFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
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
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-truck text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Panel de Distribución</h1>
                            <p class="text-xs text-gray-500">Gestiona el flujo de pedidos desde Cámara hasta la entrega</p>
                        </div>
                    </div>
                    <button
                        @click="modalTransportadoraVisible = true"
                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition"
                    >
                        <i class="fas fa-plus text-[10px]"></i> Nueva Transportadora
                    </button>
                </div>

                <!-- ==================== TABS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-1.5 mb-4 overflow-x-auto">
                    <div class="flex gap-1 min-w-max">
                        <button
                            @click="tabActiva = 'pendiente'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'pendiente'
                                ? 'bg-primary-600 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-100'"
                        >
                            <i class="fas fa-clock text-[10px]"></i>
                            Pendientes
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="tabActiva === 'pendiente' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ pedidosPendientes.length }}
                            </span>
                        </button>
                        <button
                            @click="tabActiva = 'camara'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'camara'
                                ? 'bg-orange-500 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-100'"
                        >
                            <i class="fas fa-warehouse text-[10px]"></i>
                            En Cámara
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="tabActiva === 'camara' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ pedidosEnCamara.length }}
                            </span>
                        </button>
                        <button
                            @click="tabActiva = 'distribucion'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'distribucion'
                                ? 'bg-indigo-500 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-100'"
                        >
                            <i class="fas fa-shipping-fast text-[10px]"></i>
                            En Distribución
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="tabActiva === 'distribucion' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ pedidosEnDistribucion.length }}
                            </span>
                        </button>
                        <button
                            @click="tabActiva = 'transportadora'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'transportadora'
                                ? 'bg-cyan-600 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-100'"
                        >
                            <i class="fas fa-truck text-[10px]"></i>
                            En Transportadora
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="tabActiva === 'transportadora' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ pedidosEnTransportadora.length }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- ==================== TABLA ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        <!-- VISTA MÓVIL -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="p in pedidosActuales" :key="p.IdPedidoCliente" class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start mb-1.5">
                                    <span class="text-xs font-bold text-primary-700 font-mono">#{{ p.NumeroPedido }}</span>
                                    <span class="text-[9px] text-gray-400">{{ formatoFecha(p.FechaPedido) }}</span>
                                </div>
                                <p class="text-xs font-medium text-gray-800 truncate">{{ p.cliente?.Nombre || '-' }}</p>
                                <p class="text-[10px] text-gray-500">{{ p.TotalContenedores }} contenedores</p>
                                
                                <!-- Info transportadora -->
                                <div v-if="tabActiva === 'transportadora'" class="mt-1.5 pt-1.5 border-t border-gray-200">
                                    <p class="text-[10px] font-bold text-cyan-700">{{ p.envio?.transportadora?.identificador?.Nombre || 'Sin transportadora' }}</p>
                                    <p class="text-[9px] text-gray-500">Guía: {{ p.envio?.NumeroGuia || '-' }} | Costo: Bs. {{ p.envio?.CostoEnvio || '0.00' }}</p>
                                </div>

                                <div class="flex justify-end mt-2">
                                    <button v-if="tabActiva === 'pendiente'" @click="abrirModalEstado(p, 'En Cámara')" 
                                        class="bg-orange-500 hover:bg-orange-600 text-white px-2.5 py-1 rounded-md text-[10px] font-medium transition flex items-center gap-1">
                                        <i class="fas fa-play text-[8px]"></i> Comenzar Armado
                                    </button>
                                    <button v-else-if="tabActiva === 'camara'" @click="abrirModalEstado(p, 'En Distribución')" 
                                        class="bg-indigo-500 hover:bg-indigo-600 text-white px-2.5 py-1 rounded-md text-[10px] font-medium transition flex items-center gap-1">
                                        <i class="fas fa-check text-[8px]"></i> Termo Listo
                                    </button>
                                    <button v-else-if="tabActiva === 'distribucion'" @click="abrirModalEstado(p, 'En Transportadora')" 
                                        class="bg-cyan-500 hover:bg-cyan-600 text-white px-2.5 py-1 rounded-md text-[10px] font-medium transition flex items-center gap-1">
                                        <i class="fas fa-truck text-[8px]"></i> Entregar a Transportadora
                                    </button>
                                </div>
                            </div>
                            <div v-if="pedidosActuales.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay pedidos en esta etapa</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase w-20">Pedido</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Cliente</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase w-24">Fecha</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase w-20">Cont.</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Detalle / Guía</th>
                                    <th class="px-3 py-1.5 text-right text-[9px] font-medium text-primary-700 uppercase w-40">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="p in pedidosActuales" :key="p.IdPedidoCliente" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 text-xs font-mono font-bold text-primary-700">#{{ p.NumeroPedido }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-700 max-w-[150px] truncate" :title="p.cliente?.Nombre">{{ p.cliente?.Nombre || '-' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">{{ formatoFecha(p.FechaPedido) }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ p.TotalContenedores }} cont.</td>
                                    <td class="px-3 py-2 text-xs">
                                        <template v-if="tabActiva === 'transportadora'">
                                            <div class="font-bold text-cyan-700">{{ p.envio?.transportadora?.identificador?.Nombre || 'Sin transportadora' }}</div>
                                            <div class="text-[9px] text-gray-500">Guía: {{ p.envio?.NumeroGuia || '-' }} | Costo: Bs. {{ p.envio?.CostoEnvio || '0.00' }}</div>
                                        </template>
                                        <template v-else>
                                            <span class="text-gray-500">{{ p.TotalContenedores }} contenedores</span>
                                        </template>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button v-if="tabActiva === 'pendiente'" @click="abrirModalEstado(p, 'En Cámara')" 
                                            class="inline-flex items-center gap-1 bg-orange-500 hover:bg-orange-600 text-white px-2.5 py-1 rounded-md text-[10px] font-medium transition">
                                            <i class="fas fa-play text-[8px]"></i> Comenzar Armado
                                        </button>
                                        <button v-else-if="tabActiva === 'camara'" @click="abrirModalEstado(p, 'En Distribución')" 
                                            class="inline-flex items-center gap-1 bg-indigo-500 hover:bg-indigo-600 text-white px-2.5 py-1 rounded-md text-[10px] font-medium transition">
                                            <i class="fas fa-check text-[8px]"></i> Termo Listo
                                        </button>
                                        <button v-else-if="tabActiva === 'distribucion'" @click="abrirModalEstado(p, 'En Transportadora')" 
                                            class="inline-flex items-center gap-1 bg-cyan-500 hover:bg-cyan-600 text-white px-2.5 py-1 rounded-md text-[10px] font-medium transition">
                                            <i class="fas fa-truck text-[8px]"></i> Entregar a Transportadora
                                        </button>
                                        <!-- 🔥 En la pestaña Transportadora NO HAY BOTÓN -->
                                        <span v-else class="text-[10px] text-gray-400 italic">
                                            <i class="fas fa-info-circle mr-1"></i> En tránsito
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="pedidosActuales.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                        No hay pedidos en esta etapa
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- ==================== MODAL CAMBIO DE ESTADO ==================== -->
        <div v-if="modalEstadoVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3" @click.self="modalEstadoVisible = false">
            <div class="bg-white rounded-xl w-full max-w-md shadow-xl overflow-hidden">
                <div class="px-4 py-2.5 bg-primary-600 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <i class="fas fa-exchange-alt text-xs"></i>
                            Confirmar Cambio de Estado
                        </h3>
                        <p class="text-[9px] text-white/80">Pedido #{{ pedidoSeleccionado?.NumeroPedido }}</p>
                    </div>
                    <button @click="modalEstadoVisible = false" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="p-4 space-y-3">
                    <p class="text-xs text-gray-600">
                        Vas a cambiar el estado del pedido a <strong class="text-primary-600">{{ nuevoEstado }}</strong>.
                    </p>

                    <!-- Datos transportadora (solo si cambia a transportadora) -->
                    <div v-if="nuevoEstado === 'En Transportadora'" class="space-y-2">
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Transportadora</label>
                            <select v-model="transportadoraSeleccionada" class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Seleccione...</option>
                                <option v-for="t in transportadoras" :key="t.IdTransportadora" :value="t.IdTransportadora">
                                    {{ t.identificador?.Nombre || 'Sin nombre' }}
                                </option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">N° Guía</label>
                                <input v-model="numeroGuia" type="text" class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" placeholder="Ej: ABC-123">
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Costo (Bs.)</label>
                                <input v-model.number="costoEnvio" type="number" min="0" step="0.01" class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button @click="modalEstadoVisible = false" class="px-3 py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition">
                        Cancelar
                    </button>
                    <button @click="confirmarCambioEstado" class="px-3 py-1.5 text-xs bg-primary-600 hover:bg-primary-700 text-white rounded-md transition flex items-center gap-1.5 font-medium">
                        <i class="fas fa-check text-[10px]"></i> Confirmar
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL CREAR TRANSPORTADORA ==================== -->
        <div v-if="modalTransportadoraVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3" @click.self="modalTransportadoraVisible = false">
            <div class="bg-white rounded-xl w-full max-w-md shadow-xl overflow-hidden">
                <div class="px-4 py-2.5 bg-primary-600 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <i class="fas fa-truck text-xs"></i>
                            Nueva Transportadora
                        </h3>
                        <p class="text-[9px] text-white/80">Se creará en la tabla de Identificadores</p>
                    </div>
                    <button @click="modalTransportadoraVisible = false" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="p-4 space-y-3">
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Nombre *</label>
                        <input v-model="nuevaTransportadora.Nombre" type="text" class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" placeholder="Ej: Transportes Illimani">
                    </div>
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">CI / NIT *</label>
                        <input v-model.number="nuevaTransportadora.CI_NIT" type="number" class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" placeholder="Ej: 12345678">
                    </div>
                </div>

                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button @click="modalTransportadoraVisible = false" class="px-3 py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition">
                        Cancelar
                    </button>
                    <button @click="crearTransportadora" class="px-3 py-1.5 text-xs bg-primary-600 hover:bg-primary-700 text-white rounded-md transition flex items-center gap-1.5 font-medium">
                        <i class="fas fa-save text-[10px]"></i> Guardar
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

.overflow-x-auto {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

.overflow-x-auto::-webkit-scrollbar {
    height: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
}

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
</style>