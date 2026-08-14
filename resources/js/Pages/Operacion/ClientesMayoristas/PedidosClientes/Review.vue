<script setup>
import { ref, computed, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import ConfirmModal from './ConfirmModal.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

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

// ==================== ESTADO ====================
const loading = ref(false)
const observaciones = ref(props.pedido?.Observaciones || '')
const modalConfirmacionVisible = ref(false)

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

// ✅ FUNCIÓN PARA FORMATEAR NÚMEROS
const formatearNumero = (valor) => {
    if (valor === undefined || valor === null || valor === '') {
        return '0'
    }
    const numero = parseFloat(valor)
    if (isNaN(numero)) {
        return '0'
    }
    return numero.toFixed(0)
}

// ==================== FUNCIONES ====================
const irAtras = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create')
}

// ==================== ABRIR MODAL DE CONFIRMACIÓN ====================
const abrirModalConfirmacion = () => {
    if (props.detallesAgrupados.length === 0) {
        toast?.warning('Carrito vacío', 'Agregue productos antes de finalizar')
        return
    }
    modalConfirmacionVisible.value = true
}

// ==================== FINALIZAR PEDIDO (confirmado) - CORREGIDO ====================
const finalizarPedido = async () => {
    modalConfirmacionVisible.value = false
    loading.value = true
    
    try {
        const response = await axios.post(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${props.pedido.IdPedidoCliente}/finalizar`, {
            IdCliente: props.pedido.IdCliente,
            IdSucursal: props.pedido.IdSucursal,
            FechaEntrega: null,
            Observaciones: observaciones.value || null
        })
        
        if (response.data.success) {
            toast?.success('Pedido finalizado', `Pedido N° ${response.data.numero_pedido} creado correctamente`)
            
            // ✅ ABRIR PDF EN NUEVA PESTAÑA
            if (response.data.pdf_url) {
                window.open(response.data.pdf_url, '_blank')
            }
            
            // ✅ REDIRIGIR AL INDEX DE PEDIDOS
            setTimeout(() => {
                router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes')
            }, 1500)
        } else {
            toast?.error('Error', response.data.message || 'Error al finalizar el pedido')
        }
    } catch (error) {
        console.error('Error:', error)
        const mensaje = error.response?.data?.message || 'Error al finalizar el pedido'
        toast?.error('Error', mensaje)
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="max-w-4xl mx-auto px-3 py-4">
            
            <!-- HEADER - Botón volver -->
            <div class="flex items-center justify-between mb-4">
                <button 
                    @click="irAtras"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-800 transition text-sm font-medium"
                >
                    <i class="fas fa-arrow-left"></i>
                    Volver
                </button>
                <span class="text-xs text-gray-400">Pedido #{{ pedido?.NumeroPedido || 'Nuevo' }}</span>
            </div>

            <!-- DOCUMENTO DEL PEDIDO -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                
                <!-- CABECERA -->
                <div class="p-6 border-b bg-gray-50">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Pedido de Productos</h1>
                            <p class="text-sm text-gray-500 mt-1">
                                <span class="font-medium">N° Pedido:</span> {{ pedido?.NumeroPedido || 'Nuevo' }}
                            </p>
                        </div>
                        <div class="text-sm text-gray-500 sm:text-right">
                            <p><span class="font-medium">Fecha:</span> {{ fechaPedido }}</p>
                            <p><span class="font-medium">Operador:</span> {{ operadorNombre || props.pedido?.operador_nombre || 'Sin operador' }}</p>
                        </div>
                    </div>
                </div>

                <!-- SUCURSAL -->
                <div class="px-6 py-3 border-b bg-gray-50/50">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <i class="fas fa-store text-primary-500"></i>
                        <span class="font-medium">Sucursal:</span>
                        <span>{{ sucursalNombre || props.pedido?.sucursal_nombre || 'Sin sucursal' }}</span>
                    </div>
                </div>

                <!-- PRODUCTOS POR CONTENEDOR -->
                <div class="p-6 border-b">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-700 flex items-center">
                            <i class="fas fa-boxes mr-2 text-primary-500"></i>
                            Productos
                            <span class="text-xs text-gray-400 font-normal ml-2">
                                ({{ totalContenedores }} contenedor(es) · {{ formatearNumero(totalUnidades) }} unidades)
                            </span>
                        </h2>
                    </div>

                    <!-- Lista de contenedores -->
                    <div v-if="detallesAgrupados.length === 0" class="text-center text-gray-400 py-8">
                        <i class="fas fa-inbox text-3xl mb-2 block"></i>
                        <p>No hay productos en este pedido</p>
                    </div>

                    <div v-else class="space-y-6">
                        <div 
                            v-for="(item, idx) in detallesAgrupados" 
                            :key="idx"
                            class="border rounded-lg overflow-hidden bg-white shadow-xs"
                        >
                            <!-- Cabecera del contenedor -->
                            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs font-mono bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full font-semibold">#{{ idx + 1 }}</span>
                                    <span class="font-semibold text-gray-800">{{ item.Nombre }}</span>
                                    <span class="text-xs text-gray-400 font-mono">({{ item.Codigo }})</span>
                                </div>
                                <span class="text-xs font-bold bg-white border px-2.5 py-1 rounded-md text-primary-600 shadow-2xs">
                                    {{ formatearNumero(item.total_unidades) }} und
                                </span>
                            </div>

                            <!-- Tabla de productos del contenedor (Evita cortes de texto) -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b bg-gray-50/50 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                                            <th class="py-2 px-4">Descripción del Producto</th>
                                            <th class="py-2 px-4 text-right w-28">Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm">
                                        <tr 
                                            v-for="producto in item.productos" 
                                            :key="producto.IdProducto"
                                            class="hover:bg-gray-50/80 transition-colors"
                                        >
                                            <td class="py-2.5 px-4 text-gray-700 font-medium">
                                                {{ producto.Descripcion }}
                                            </td>
                                            <td class="py-2.5 px-4 text-right font-bold text-gray-800">
                                                {{ formatearNumero(producto.Cantidad) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Total general -->
                    <div v-if="detallesAgrupados.length > 0" class="mt-6 pt-4 border-t flex justify-end">
                        <div class="text-right">
                            <p class="text-xs text-gray-500 font-medium">Total unidades generales</p>
                            <p class="text-xl font-bold text-primary-700">{{ formatearNumero(totalUnidades) }}</p>
                        </div>
                    </div>
                </div>

                <!-- OBSERVACIONES -->
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <div class="w-32">
                            <label class="text-xs font-medium text-gray-500">Observaciones</label>
                        </div>
                        <div class="flex-1 w-full">
                            <textarea 
                                v-model="observaciones"
                                rows="2"
                                placeholder="Notas adicionales (opcional)..."
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500 resize-none"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- PIE / BOTONES -->
                <div class="p-6 bg-gray-50 border-t flex flex-col sm:flex-row justify-end gap-3">
                    <button 
                        @click="irAtras"
                        class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm font-medium transition"
                    >
                        <i class="fas fa-arrow-left mr-1"></i>
                        Seguir agregando
                    </button>
                    <button 
                        @click="abrirModalConfirmacion"
                        :disabled="loading || detallesAgrupados.length === 0"
                        class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition flex items-center justify-center gap-2 disabled:opacity-50 shadow-xs"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check-circle"></i>
                        {{ loading ? 'Procesando...' : 'Finalizar Pedido' }}
                    </button>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="mt-4 text-center text-xs text-gray-400">
                <i class="fas fa-shield-alt mr-1"></i>
                Al finalizar el pedido se generará un comprobante
            </div>
        </div>

        <!-- MODAL DE CONFIRMACIÓN -->
        <ConfirmModal
            v-model:visible="modalConfirmacionVisible"
            title="Confirmar Pedido"
            message="¿Estás seguro de finalizar este pedido? Una vez confirmado no se podrá modificar."
            confirm-text="Sí, finalizar pedido"
            cancel-text="Cancelar"
            type="success"
            @confirm="finalizarPedido"
        />
    </div>
</template>

<style scoped>
/* Estilos opcionales */
</style>