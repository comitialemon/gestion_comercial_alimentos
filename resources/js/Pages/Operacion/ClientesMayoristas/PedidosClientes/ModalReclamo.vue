<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    visible: { type: Boolean, default: false },
    pedido: { type: Object, default: null },
})

const emit = defineEmits(['close', 'success'])

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const handleResize = () => {
    isMobile.value = window.innerWidth < 640
}

// ==================== ESTADO ====================
const descripcion = ref('')
const productos = ref([])
const buscador = ref('')
const cargando = ref(false)
const enviando = ref(false)

// ==================== COMPUTED ====================
const productosFiltrados = computed(() => {
    if (!buscador.value) return productos.value
    const termino = buscador.value.toLowerCase()
    return productos.value.filter(p =>
        p.Descripcion.toLowerCase().includes(termino) ||
        p.Codigo.toLowerCase().includes(termino)
    )
})

const totalProductosReclamados = computed(() => {
    return productos.value.filter(p => p.CantidadReclamada > 0).length
})

const totalUnidadesReclamadas = computed(() => {
    return productos.value.reduce((sum, p) => sum + (Number(p.CantidadReclamada) || 0), 0)
})

// ==================== FUNCIONES ====================
const cargarProductos = async () => {
    cargando.value = true
    descripcion.value = ''
    buscador.value = ''
    productos.value = []

    try {
        const response = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${props.pedido.IdPedidoCliente}/detalles`
        )

        if (response.data.success && response.data.productos_reclamo) {
            productos.value = response.data.productos_reclamo.map(p => ({
                ...p,
                CantidadReclamada: 0,
                Motivo: ''
            }))
        } else {
            const agrupados = {}
            response.data.detalles.forEach(container => {
                container.productos.forEach(prod => {
                    const id = prod.IdProducto
                    if (!agrupados[id]) {
                        agrupados[id] = {
                            IdProducto: id,
                            Codigo: prod.Codigo,
                            Descripcion: prod.Descripcion,
                            CantidadTotalPedida: 0,
                            CantidadReclamada: 0,
                            Motivo: ''
                        }
                    }
                    agrupados[id].CantidadTotalPedida += Number(prod.Cantidad) || 0
                })
            })
            productos.value = Object.values(agrupados)
        }
    } catch (error) {
        console.error('Error cargando productos:', error)
    } finally {
        cargando.value = false
    }
}

const validarCantidad = (prod) => {
    let val = Number(prod.CantidadReclamada) || 0
    if (val < 0) val = 0
    if (val > prod.CantidadTotalPedida) val = prod.CantidadTotalPedida
    prod.CantidadReclamada = Math.floor(val)
}

const enviar = async () => {
    if (!descripcion.value.trim()) {
        emit('success', { tipo: 'warning', message: 'Por favor, describe el problema general.' })
        return
    }

    const productosAReclamar = productos.value.filter(p => p.CantidadReclamada > 0)
    if (productosAReclamar.length === 0) {
        emit('success', { tipo: 'warning', message: 'Debes seleccionar al menos un producto.' })
        return
    }

    enviando.value = true
    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/reclamos/${props.pedido.IdPedidoCliente}/crear`,
            {
                descripcion: descripcion.value,
                productos: productosAReclamar.map(p => ({
                    IdProducto: p.IdProducto,
                    CantidadReclamada: p.CantidadReclamada,
                    Motivo: p.Motivo || null
                }))
            }
        )

        if (response.data.success) {
            emit('success', { tipo: 'success', message: response.data.message })
            cerrar()
        }
    } catch (error) {
        emit('success', { tipo: 'error', message: error.response?.data?.message || 'Error al enviar el reclamo' })
    } finally {
        enviando.value = false
    }
}

const cerrar = () => {
    if (!enviando.value) {
        emit('close')
    }
}

// ==================== WATCHERS ====================
watch(() => props.visible, async (nuevoValor) => {
    if (nuevoValor && props.pedido) {
        await cargarProductos()
    }
})

// ==================== CICLO DE VIDA ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <Teleport to="body">
        <div
            v-if="visible"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-3"
            @click.self="cerrar"
        >
            <div class="bg-white rounded-xl w-full max-w-2xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh] animate-fade-in-up">

                <!-- ==================== HEADER ==================== -->
                <div class="px-4 py-3 bg-primary-600 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                        <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-white text-base"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold text-sm text-white truncate">Reportar Problema</h3>
                            <p class="text-[10px] text-white/80 truncate">
                                Pedido #{{ pedido?.NumeroPedido }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="cerrar"
                        class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition flex-shrink-0"
                        :disabled="enviando"
                    >
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <!-- ==================== BODY ==================== -->
                <div class="p-3 overflow-y-auto flex-1 space-y-3">

                    <!-- Info -->
                    <div class="bg-primary-50 border border-primary-200 rounded-lg p-2 flex items-start gap-2">
                        <i class="fas fa-info-circle text-primary-500 text-[10px] mt-0.5 flex-shrink-0"></i>
                        <p class="text-[10px] text-primary-700">
                            Indica cuántos productos llegaron en mal estado. Calidad revisará tu reclamo.
                        </p>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label class="text-[10px] font-bold text-gray-700 block mb-0.5">
                            Descripción del problema <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="descripcion"
                            rows="2"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none resize-none"
                            placeholder="Ej: El termo llegó abierto y varias salteñas estaban aplastadas..."
                            :disabled="enviando"
                        ></textarea>
                    </div>

                    <!-- Productos -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-[10px] font-bold text-gray-700">
                                Productos con falla <span class="text-red-500">*</span>
                                <span class="text-gray-400 font-normal">({{ productos.length }} disponibles)</span>
                            </label>
                            <span
                                v-if="totalProductosReclamados > 0"
                                class="text-[9px] bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded-full font-bold"
                            >
                                {{ totalProductosReclamados }} prod · {{ totalUnidadesReclamadas }} und
                            </span>
                        </div>

                        <!-- Buscador -->
                        <div class="relative mb-2">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                            <input
                                type="text"
                                v-model="buscador"
                                placeholder="Buscar producto..."
                                class="w-full border border-gray-300 rounded-md pl-7 pr-3 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :disabled="enviando"
                            />
                            <button
                                v-if="buscador"
                                @click="buscador = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <i class="fas fa-times text-[10px]"></i>
                            </button>
                        </div>

                        <!-- Loading -->
                        <div v-if="cargando" class="text-center py-6">
                            <i class="fas fa-spinner fa-spin text-2xl text-primary-500"></i>
                            <p class="text-[10px] text-gray-400 mt-1">Cargando productos...</p>
                        </div>

                        <!-- Lista -->
                        <div v-else class="space-y-1 border border-gray-200 rounded-lg p-2 bg-gray-50 max-h-64 overflow-y-auto">
                            <div v-if="productosFiltrados.length === 0" class="text-center text-[10px] text-gray-400 py-4">
                                <i class="fas fa-box-open text-xl mb-1 block"></i>
                                {{ buscador ? 'No se encontraron productos' : 'No hay productos en este pedido' }}
                            </div>

                            <div
                                v-for="prod in productosFiltrados"
                                :key="prod.IdProducto"
                                class="bg-white p-2 rounded border transition-colors"
                                :class="prod.CantidadReclamada > 0 ? 'border-primary-300 bg-primary-50/50' : 'border-gray-200'"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-[9px] font-mono text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">
                                                {{ prod.Codigo }}
                                            </span>
                                            <span class="text-xs font-semibold text-gray-800 truncate">{{ prod.Descripcion }}</span>
                                        </div>
                                        <p class="text-[9px] text-gray-500 mt-0.5">
                                            Total pedido: <span class="font-bold text-gray-700">{{ prod.CantidadTotalPedida }}</span> und
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        <label class="text-[9px] text-gray-500 whitespace-nowrap">Fallida:</label>
                                        <input
                                            type="number"
                                            min="0"
                                            :max="prod.CantidadTotalPedida"
                                            v-model.number="prod.CantidadReclamada"
                                            @input="validarCantidad(prod)"
                                            @blur="validarCantidad(prod)"
                                            class="w-14 border rounded px-1 py-0.5 text-xs text-center font-bold focus:ring-primary-500 focus:border-primary-500 outline-none"
                                            :class="prod.CantidadReclamada > 0 ? 'border-primary-400 bg-primary-50 text-primary-700' : 'border-gray-300'"
                                            :disabled="enviando"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="px-4 py-2.5 border-t border-gray-200 bg-gray-50 flex justify-between items-center gap-2 flex-shrink-0">
                    <div class="text-[10px] text-gray-500">
                        <span v-if="totalProductosReclamados === 0" class="text-gray-400">
                            <i class="fas fa-info-circle mr-1"></i>
                            Selecciona al menos 1 producto
                        </span>
                        <span v-else class="text-primary-600 font-medium">
                            <i class="fas fa-check-circle mr-1"></i>
                            {{ totalProductosReclamados }} producto(s) · {{ totalUnidadesReclamadas }} und
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="cerrar"
                            class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100 transition font-medium"
                            :disabled="enviando"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="enviar"
                            class="px-4 py-1.5 text-xs text-white bg-primary-600 rounded-md hover:bg-primary-700 transition font-medium flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                            :disabled="enviando || cargando"
                        >
                            <i :class="enviando ? 'fas fa-spinner fa-spin' : 'fas fa-paper-plane'" class="text-[10px]"></i>
                            {{ enviando ? 'Enviando...' : 'Enviar Reclamo' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.animate-fade-in-up {
    animation: fadeInUp 0.2s ease-out;
}

/* Quitar flechas de inputs number */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
}

/* Scrollbar personalizada */
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
    input, textarea, button {
        font-size: 13px !important;
    }
}
</style>