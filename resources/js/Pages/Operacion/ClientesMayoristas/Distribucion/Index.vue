<script setup>
import { ref } from 'vue'
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

const toast = usePage().props.toast

const tabActiva = ref('pendiente')

const modalEstadoVisible = ref(false)
const modalTransportadoraVisible = ref(false)
const pedidoSeleccionado = ref(null)
const nuevoEstado = ref('')
const transportadoraSeleccionada = ref('')
const numeroGuia = ref('')
const costoEnvio = ref(0)

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

const nuevaTransportadora = ref({ Nombre: '', CI_NIT: '' })

const crearTransportadora = async () => {
    // Validación en el frontend
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
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-10">
        <div class="max-w-7xl mx-auto px-4 py-6">

            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Panel de Distribución</h1>
                    <p class="text-sm text-gray-500">Gestiona el flujo de pedidos desde Cámara hasta la entrega.</p>
                </div>
                <button @click="modalTransportadoraVisible = true"
                        class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> Nueva Transportadora
                </button>
            </div>

            <div class="flex border-b border-gray-200 mb-6 overflow-x-auto">
                <button @click="tabActiva = 'pendiente'" :class="tabActiva === 'pendiente' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 font-medium text-sm border-b-2 whitespace-nowrap">
                    Pendientes ({{ pedidosPendientes.length }})
                </button>
                <button @click="tabActiva = 'camara'" :class="tabActiva === 'camara' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 font-medium text-sm border-b-2 whitespace-nowrap">
                    En Cámara ({{ pedidosEnCamara.length }})
                </button>
                <button @click="tabActiva = 'distribucion'" :class="tabActiva === 'distribucion' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 font-medium text-sm border-b-2 whitespace-nowrap">
                    En Distribución ({{ pedidosEnDistribucion.length }})
                </button>
                <button @click="tabActiva = 'transportadora'" :class="tabActiva === 'transportadora' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="px-4 py-2 font-medium text-sm border-b-2 whitespace-nowrap">
                    En Transportadora ({{ pedidosEnTransportadora.length }})
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3">Pedido</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Detalle / Guía</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="tabActiva === 'pendiente'" v-for="p in pedidosPendientes" :key="p.IdPedidoCliente">
                            <td class="px-4 py-3 font-mono font-bold text-primary-700">#{{ p.NumeroPedido }}</td>
                            <td class="px-4 py-3 text-sm">{{ p.cliente?.Nombre }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatoFecha(p.FechaPedido) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ p.TotalContenedores }} cont.</td>
                            <td class="px-4 py-3 text-right">
                                <button @click="abrirModalEstado(p, 'En Cámara')" class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-xs font-medium">
                                    Comenzar Armado
                                </button>
                            </td>
                        </tr>

                        <tr v-if="tabActiva === 'camara'" v-for="p in pedidosEnCamara" :key="p.IdPedidoCliente">
                            <td class="px-4 py-3 font-mono font-bold text-primary-700">#{{ p.NumeroPedido }}</td>
                            <td class="px-4 py-3 text-sm">{{ p.cliente?.Nombre }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatoFecha(p.FechaPedido) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ p.TotalContenedores }} cont.</td>
                            <td class="px-4 py-3 text-right">
                                <button @click="abrirModalEstado(p, 'En Distribución')" class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded text-xs font-medium">
                                    Termo Listo (Pasar a Chófer)
                                </button>
                            </td>
                        </tr>

                        <tr v-if="tabActiva === 'distribucion'" v-for="p in pedidosEnDistribucion" :key="p.IdPedidoCliente">
                            <td class="px-4 py-3 font-mono font-bold text-primary-700">#{{ p.NumeroPedido }}</td>
                            <td class="px-4 py-3 text-sm">{{ p.cliente?.Nombre }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatoFecha(p.FechaPedido) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ p.TotalContenedores }} cont.</td>
                            <td class="px-4 py-3 text-right">
                                <button @click="abrirModalEstado(p, 'En Transportadora')" class="bg-cyan-500 hover:bg-cyan-600 text-white px-3 py-1 rounded text-xs font-medium">
                                    Entregar a Transportadora
                                </button>
                            </td>
                        </tr>

                        <tr v-if="tabActiva === 'transportadora'" v-for="p in pedidosEnTransportadora" :key="p.IdPedidoCliente">
                            <td class="px-4 py-3 font-mono font-bold text-primary-700">#{{ p.NumeroPedido }}</td>
                            <td class="px-4 py-3 text-sm">{{ p.cliente?.Nombre }}</td>
                            <td class="px-4 py-3 text-sm">{{ formatoFecha(p.FechaPedido) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="font-bold text-cyan-700">{{ p.envio?.transportadora?.identificador?.Nombre || 'Sin transportadora' }}</div>
                                <div class="text-xs text-gray-500">Guía: {{ p.envio?.NumeroGuia }} | Costo: Bs. {{ p.envio?.CostoEnvio }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button @click="abrirModalEstado(p, 'Recibido')" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs font-medium">
                                    Marcar Recibido
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="
                    (tabActiva === 'pendiente' && pedidosPendientes.length === 0) ||
                    (tabActiva === 'camara' && pedidosEnCamara.length === 0) ||
                    (tabActiva === 'distribucion' && pedidosEnDistribucion.length === 0) ||
                    (tabActiva === 'transportadora' && pedidosEnTransportadora.length === 0)
                " class="p-12 text-center text-gray-400">
                    <i class="fas fa-inbox text-4xl mb-3 block"></i>
                    <p>No hay pedidos en esta etapa.</p>
                </div>
            </div>
        </div>

        <!-- MODAL CAMBIO DE ESTADO -->
        <div v-if="modalEstadoVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold mb-4">Confirmar Cambio de Estado</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Vas a cambiar el pedido <strong>#{{ pedidoSeleccionado?.NumeroPedido }}</strong> al estado <strong>{{ nuevoEstado }}</strong>.
                </p>

                <div v-if="nuevoEstado === 'En Transportadora'" class="space-y-3 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Transportadora</label>
                        <select v-model="transportadoraSeleccionada" class="w-full border border-gray-300 rounded p-2 text-sm">
                            <option value="">Seleccione...</option>
                            <option v-for="t in transportadoras" :key="t.IdTransportadora" :value="t.IdTransportadora">
                                {{ t.identificador?.Nombre || 'Sin nombre' }}
                            </option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Número de Guía</label>
                            <input v-model="numeroGuia" type="text" class="w-full border border-gray-300 rounded p-2 text-sm" placeholder="Ej: ABC-123">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Costo Envío (Bs.)</label>
                            <input v-model.number="costoEnvio" type="number" min="0" step="0.01" class="w-full border border-gray-300 rounded p-2 text-sm" placeholder="0.00">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button @click="modalEstadoVisible = false" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded hover:bg-gray-200">Cancelar</button>
                    <button @click="confirmarCambioEstado" class="px-4 py-2 text-sm text-white bg-primary-600 rounded hover:bg-primary-700">Confirmar</button>
                </div>
            </div>
        </div>

        <!-- MODAL CREAR TRANSPORTADORA -->
        <div v-if="modalTransportadoraVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold mb-1">Nueva Transportadora</h3>
                <p class="text-xs text-gray-500 mb-4">Se creará automáticamente en la tabla de Identificadores.</p>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Nombre *</label>
                        <input v-model="nuevaTransportadora.Nombre" type="text" class="w-full border border-gray-300 rounded p-2 text-sm" placeholder="Ej: Transportes Illimani">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">CI / NIT *</label>
                        <input v-model.number="nuevaTransportadora.CI_NIT" type="number" class="w-full border border-gray-300 rounded p-2 text-sm" placeholder="Ej: 12345678">
                    </div>
                </div>
                
                <div class="flex justify-end gap-2 mt-6">
                    <button @click="modalTransportadoraVisible = false" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded hover:bg-gray-200">Cancelar</button>
                    <button @click="crearTransportadora" class="px-4 py-2 text-sm text-white bg-primary-600 rounded hover:bg-primary-700">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</template>