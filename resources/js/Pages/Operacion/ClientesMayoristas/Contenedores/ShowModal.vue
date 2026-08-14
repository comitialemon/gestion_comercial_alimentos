<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    contenedor: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['close'])

// ==================== ESTADO ====================
const loading = ref(false)
const detalles = ref([])
const isMobile = ref(window.innerWidth < 768)

// ==================== COMPUTADOS ====================
const mostrar = computed({
    get: () => props.visible,
    set: (val) => {
        if (!val) emit('close')
    }
})

const contenedorData = computed(() => {
    return props.contenedor || {}
})

const totalUnidades = computed(() => {
    if (!detalles.value.length) return 0
    return detalles.value.reduce((sum, item) => sum + (Number(item.Cantidad) || 0), 0)
})

const totalProductos = computed(() => {
    return detalles.value.length
})

const capacidadTotal = computed(() => {
    return Number(contenedorData.value.CapacidadTotal) || 0
})

const estaCompleto = computed(() => {
    if (!detalles.value.length) return false
    return Math.round(totalUnidades.value * 100) / 100 === Math.round(capacidadTotal.value * 100) / 100
})

const estadoTexto = computed(() => {
    return contenedorData.value.ActivoInactivo === 1 ? 'Activo' : 'Borrador'
})

const estadoColor = computed(() => {
    return contenedorData.value.ActivoInactivo === 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
})

const estadoIcono = computed(() => {
    return contenedorData.value.ActivoInactivo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'
})

// ==================== CARGAR DETALLES ====================
const cargarDetalles = async () => {
    if (!props.contenedor?.IdContenedor) {
        console.warn('No hay contenedor para cargar detalles')
        return
    }

    loading.value = true

    try {
        const response = await axios.get(`/operacion/pedidos/clientes-mayoristas/contenedores/${props.contenedor.IdContenedor}`)

        if (response.data.success) {
            const data = response.data.data
            detalles.value = data.detalles || []
            
            // Actualizar el contenedor con los datos completos
            if (data) {
                contenedorData.value = {
                    ...contenedorData.value,
                    ...data
                }
            }
        } else {
            console.error('Error al cargar detalles:', response.data.message)
        }
    } catch (error) {
        console.error('Error al cargar detalles:', error)
    } finally {
        loading.value = false
    }
}

// ==================== UTILIDADES ====================
const formatearNumero = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(2)
}

const formatearEntero = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(0)
}

const getEstadoBadge = (activo) => {
    return activo === 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Activo' : 'Borrador'
}

const getEstadoIcono = (activo) => {
    return activo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'
}

// ==================== CERRAR MODAL ====================
const cerrarModal = () => {
    emit('close')
}

// Cerrar al hacer clic fuera
const handleOverlayClick = (event) => {
    if (event.target === event.currentTarget) {
        cerrarModal()
    }
}

// Cerrar con ESC
const handleKeydown = (event) => {
    if (event.key === 'Escape' && props.visible) {
        cerrarModal()
    }
}

// ==================== WATCH ====================
watch(
    () => props.visible,
    (newVal) => {
        if (newVal && props.contenedor) {
            cargarDetalles()
        }
    },
    { immediate: true }
)

watch(
    () => props.contenedor,
    (newVal) => {
        if (newVal && props.visible) {
            cargarDetalles()
        }
    }
)

// ==================== LIFECYCLE ====================
onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
    window.addEventListener('resize', () => {
        isMobile.value = window.innerWidth < 768
    })
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <!-- Modal Overlay -->
    <div 
        v-if="visible"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        @click="handleOverlayClick"
    >
        <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden shadow-xl transition-all duration-300 ease-in-out">
            
            <!-- Header -->
            <div class="p-4 border-b bg-primary-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-primary-100">
                            <i class="fas fa-box text-primary-600 text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold text-gray-800 text-sm sm:text-base truncate">
                                {{ contenedorData.Nombre || 'Contenedor' }}
                            </h3>
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-[10px] sm:text-xs text-gray-500 font-mono">
                                    Código: {{ contenedorData.Codigo || '-' }}
                                </p>
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full" :class="estadoColor">
                                    <i :class="estadoIcono" class="mr-0.5 text-[8px]"></i>
                                    {{ estadoTexto }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <button 
                        @click="cerrarModal" 
                        class="text-gray-400 hover:text-gray-600 transition flex-shrink-0 ml-2"
                        title="Cerrar"
                    >
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>
            
            <!-- Body -->
            <div class="p-4 sm:p-5 overflow-y-auto" style="max-height: calc(90vh - 80px);">
                
                <!-- Loading -->
                <div v-if="loading" class="flex justify-center items-center py-12">
                    <i class="fas fa-spinner fa-spin text-primary-600 text-3xl"></i>
                </div>

                <!-- Contenido -->
                <div v-else>
                    <!-- Resumen -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                        <div class="bg-gray-50 rounded-lg p-2 sm:p-3">
                            <p class="text-[10px] text-gray-500">Capacidad Total</p>
                            <p class="text-sm sm:text-base font-bold text-gray-800">
                                {{ formatearNumero(capacidadTotal) }}
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2 sm:p-3">
                            <p class="text-[10px] text-gray-500">Total Unidades</p>
                            <p class="text-sm sm:text-base font-bold" :class="estaCompleto ? 'text-green-600' : 'text-red-600'">
                                {{ formatearNumero(totalUnidades) }}
                                <span v-if="!estaCompleto" class="text-[10px] text-red-500 ml-1">
                                    (¡Inc!)
                                </span>
                                <span v-else class="text-[10px] text-green-500 ml-1">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2 sm:p-3 col-span-2 sm:col-span-1">
                            <p class="text-[10px] text-gray-500">Productos</p>
                            <p class="text-sm sm:text-base font-bold text-gray-800">
                                {{ totalProductos }} producto(s)
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2 sm:p-3 col-span-2 sm:col-span-3">
                            <p class="text-[10px] text-gray-500">Sucursal</p>
                            <p class="text-sm font-bold text-gray-800 truncate">
                                {{ contenedorData.Sucursal || contenedorData.sucursal?.Nombre || '-' }}
                            </p>
                        </div>
                    </div>

                    <!-- Estado de validación -->
                    <div v-if="detalles.length > 0" class="mb-4">
                        <div v-if="estaCompleto" class="p-2 bg-green-50 border border-green-200 rounded-lg text-xs text-green-700">
                            <i class="fas fa-check-circle mr-1"></i>
                            La suma de productos coincide con la capacidad total. ¡Contenedor completo!
                        </div>
                        <div v-else class="p-2 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            La suma de productos ({{ formatearNumero(totalUnidades) }}) no coincide con la capacidad total ({{ formatearNumero(capacidadTotal) }})
                        </div>
                    </div>

                    <!-- Tabla de productos -->
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">
                        Productos
                        <span v-if="detalles.length > 0" class="text-[10px] text-gray-400 font-normal ml-2">
                            ({{ detalles.length }})
                        </span>
                    </h4>
                    
                    <div class="border rounded-lg overflow-hidden">
                        <div v-if="detalles.length === 0" class="text-center text-gray-400 py-8 text-sm">
                            <i class="fas fa-box-open text-2xl mb-2 block"></i>
                            No hay productos en este contenedor
                        </div>
                        
                        <div v-else>
                            <!-- DESKTOP: Tabla -->
                            <div class="hidden sm:block overflow-x-auto">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-gray-500 uppercase font-medium">Código</th>
                                            <th class="px-3 py-2 text-left text-gray-500 uppercase font-medium">Producto</th>
                                            <th class="px-3 py-2 text-right text-gray-500 uppercase font-medium">Cantidad</th>
                                            <th class="px-3 py-2 text-center text-gray-500 uppercase font-medium">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr 
                                            v-for="detalle in detalles" 
                                            :key="detalle.IdProducto" 
                                            class="hover:bg-gray-50 transition"
                                            :class="{'bg-red-50': Number(detalle.Cantidad) > capacidadTotal}"
                                        >
                                            <td class="px-3 py-2 font-mono text-gray-500">{{ detalle.Codigo || '-' }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ detalle.Producto || detalle.Descripcion || '-' }}</td>
                                            <td class="px-3 py-2 text-right font-bold" :class="Number(detalle.Cantidad) > capacidadTotal ? 'text-red-600' : 'text-gray-800'">
                                                {{ formatearNumero(detalle.Cantidad) }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span v-if="Number(detalle.Cantidad) > capacidadTotal" class="text-red-500 text-[10px]">
                                                    <i class="fas fa-exclamation-circle"></i> Excede
                                                </span>
                                                <span v-else class="text-green-500 text-[10px]">
                                                    <i class="fas fa-check-circle"></i> OK
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-semibold">
                                        <tr>
                                            <td colspan="2" class="px-3 py-2 text-right">TOTAL:</td>
                                            <td class="px-3 py-2 text-right" :class="estaCompleto ? 'text-green-600' : 'text-red-600'">
                                                {{ formatearNumero(totalUnidades) }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span v-if="estaCompleto" class="text-green-600 text-[10px]">
                                                    <i class="fas fa-check-circle"></i> Completo
                                                </span>
                                                <span v-else class="text-red-600 text-[10px]">
                                                    <i class="fas fa-exclamation-circle"></i> Incompleto
                                                </span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- MÓVIL: Tarjetas -->
                            <div class="sm:hidden divide-y divide-gray-100">
                                <div 
                                    v-for="detalle in detalles" 
                                    :key="detalle.IdProducto" 
                                    class="p-3"
                                    :class="{'bg-red-50': Number(detalle.Cantidad) > capacidadTotal}"
                                >
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-mono text-gray-500">{{ detalle.Codigo || '-' }}</p>
                                            <p class="text-sm font-medium text-gray-800 truncate">{{ detalle.Producto || detalle.Descripcion || '-' }}</p>
                                        </div>
                                        <span v-if="Number(detalle.Cantidad) > capacidadTotal" class="text-red-500 text-[10px] flex-shrink-0 ml-2">
                                            <i class="fas fa-exclamation-circle"></i> Excede
                                        </span>
                                        <span v-else class="text-green-500 text-[10px] flex-shrink-0 ml-2">
                                            <i class="fas fa-check-circle"></i> OK
                                        </span>
                                    </div>
                                    <div class="mt-2 pt-2 border-t border-gray-200">
                                        <p class="text-[10px] text-gray-400">Cantidad</p>
                                        <p class="text-sm font-semibold" :class="Number(detalle.Cantidad) > capacidadTotal ? 'text-red-600' : 'text-gray-800'">
                                            {{ formatearNumero(detalle.Cantidad) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Observación / Notas -->
                    <div v-if="contenedorData.Observacion" class="mt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-1">Observación</h4>
                        <div class="bg-gray-50 rounded-lg p-3 text-xs text-gray-600 border border-gray-200">
                            {{ contenedorData.Observacion }}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="p-3 sm:p-4 bg-gray-50 flex justify-end gap-2 border-t">
                <button 
                    @click="cerrarModal" 
                    class="px-4 py-1.5 rounded-lg text-xs bg-primary-600 text-white hover:bg-primary-700 transition"
                >
                    <i class="fas fa-times mr-1"></i>
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Animación de entrada */
.fixed {
    animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Scroll personalizado */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 8px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 8px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>