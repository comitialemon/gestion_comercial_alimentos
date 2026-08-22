<script setup>
import { ref, computed, onMounted, inject, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import CreateModalProductos from './CreateModalProductos.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    contenedores: {
        type: Array,
        default: () => []
    },
    clientes: {
        type: Array,
        default: () => []
    },
    sucursales: {
        type: Array,
        default: () => []
    },
    pedidoBorrador: {
        type: Object,
        default: null
    },
    carrito: {
        type: Array,
        default: () => []
    },
    sucursalDefault: {
        type: Number,
        default: null
    },
    comisionista: {
        type: String,
        default: ''
    },
    ruta: {
        type: Array,
        default: () => []
    },
    idIdentificador: {
        type: Number,
        default: null
    },
    nombreOperador: {
        type: String,
        default: ''
    }
})

// ==================== ESTADO ====================
const loading = ref(false)
const modalVisible = ref(false)
const contenedorSeleccionado = ref(null)
const carritoItems = ref([])
const pedidoId = ref(null)

// ==================== COMPUTADOS ====================
const totalCarrito = computed(() => {
    let total = 0
    carritoItems.value.forEach(item => {
        item.productos.forEach(p => {
            total += p.Cantidad
        })
    })
    return total
})

const totalContenedoresCarrito = computed(() => {
    return carritoItems.value.length
})

const hayProductosEnCarrito = computed(() => {
    return carritoItems.value.length > 0
})

// ==================== INICIALIZAR CARRITO ====================
const inicializarCarrito = () => {
    if (props.carrito && props.carrito.length > 0) {
        carritoItems.value = props.carrito.map(item => ({
            ...item,
            productos: item.productos.map(p => ({
                ...p,
                Cantidad: Number(p.Cantidad)
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

// ==================== AGREGAR AL CARRITO ====================
const agregarContenedorAlCarrito = async (data) => {
    loading.value = true
    try {
        const response = await axios.post('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/carrito/agregar', data)
        if (response.data.success) {
            toast?.success('Éxito', 'Productos agregados al carrito')
            if (response.data.pedido) {
                pedidoId.value = response.data.pedido.IdPedidoCliente
            }
            router.reload()
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

// ==================== WATCH ====================
watch(() => props.carrito, (newVal) => {
    if (newVal && newVal.length > 0) {
        carritoItems.value = newVal.map(item => ({
            ...item,
            productos: item.productos.map(p => ({
                ...p,
                Cantidad: Number(p.Cantidad)
            }))
        }))
    }
}, { immediate: true, deep: true })

onMounted(() => {
    inicializarCarrito()
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-10">
        <div class="max-w-7xl mx-auto px-3 py-3">
            
            <!-- HEADER -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Nuevo Pedido</h1>
                    <p class="text-[10px] text-gray-400">
                        Seleccione contenedores y agregue productos
                        <span v-if="nombreOperador" class="text-primary-600 font-medium">
                            • {{ nombreOperador }}
                        </span>
                    </p>
                </div>
                
                <!-- Botón Revisar Pedido -->
                <div class="flex items-center gap-3">
                    <div v-if="hayProductosEnCarrito" class="hidden sm:flex items-center gap-2 text-xs text-gray-500">
                        <i class="fas fa-shopping-cart text-primary-500"></i>
                        <span>{{ carritoItems.length }} contenedor(es)</span>
                        <span class="font-bold text-primary-600">{{ totalCarrito.toFixed(0) }} und</span>
                    </div>
                    
                    <button 
                        @click="irARevisarPedido"
                        :disabled="!hayProductosEnCarrito"
                        class="px-5 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-xl text-sm font-bold transition flex items-center gap-2 shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i class="fas fa-clipboard-list text-sm"></i>
                        Revisar Pedido
                        <span v-if="hayProductosEnCarrito" class="bg-white/20 rounded-full px-2 py-0.5 text-xs">
                            {{ carritoItems.length }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- MENÚ DE CONTENEDORES -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-gray-700">
                        <i class="fas fa-boxes mr-2 text-primary-500"></i>
                        Contenedores
                        <span class="text-xs text-gray-400 font-normal ml-1">({{ contenedores.length }})</span>
                    </h2>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    <div 
                        v-for="contenedor in contenedores" 
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
                            <p class="text-[10px] font-mono text-gray-400">{{ contenedor.Codigo }}</p>
                            <div class="flex justify-center items-center gap-2 mt-1">
                                <span class="text-[10px] text-gray-500">
                                    <i class="fas fa-weight-hanging mr-0.5"></i>
                                    {{ contenedor.CapacidadTotalFormateada }} und
                                </span>
                                <span class="text-[10px] text-gray-400">•</span>
                                <span class="text-[10px] text-gray-400">{{ contenedor.total_productos || 0 }} prod</span>
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
                    <p class="text-sm">No hay contenedores activos disponibles</p>
                    <p class="text-xs mt-1">Cree un contenedor en la sección correspondiente</p>
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
            @close="cerrarModal"
            @agregar="agregarContenedorAlCarrito"
            @actualizar="agregarContenedorAlCarrito"
        />
    </div>
</template>

<style scoped>
.grid {
    gap: 0.75rem;
}

@media (max-width: 640px) {
    .grid-cols-2 {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>