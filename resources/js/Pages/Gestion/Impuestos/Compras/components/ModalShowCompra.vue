<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    compraId: {
        type: Number,
        default: null
    }
})

const emit = defineEmits(['update:visible', 'close'])

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const compra = ref(null)
const cargando = ref(false)
const errorMessage = ref('')
const copiando = ref(false)

// ==================== MÉTODOS ====================
const cargarCompra = async () => {
    if (!props.compraId) return
    
    cargando.value = true
    errorMessage.value = ''
    compra.value = null
    
    try {
        const response = await axios.get(`/gestion/compras/${props.compraId}/json`)
        
        if (response.data) {
            compra.value = response.data
        } else {
            errorMessage.value = 'No se pudo cargar la compra'
        }
    } catch (error) {
        console.error('Error cargando compra:', error)
        errorMessage.value = error.response?.data?.message || 'Error de conexión'
    } finally {
        cargando.value = false
    }
}

const cerrar = () => {
    emit('update:visible', false)
    emit('close')
    compra.value = null
    errorMessage.value = ''
}

// ==================== COMPUTED ====================
const fechaMostrar = computed(() => {
    if (!compra.value) return '-'
    if (compra.value.fecha?.Fecha) {
        return formatearFecha(compra.value.fecha.Fecha)
    }
    return formatearFecha(compra.value.FechaIngreso)
})

const numeroDiario = computed(() => {
    if (!compra.value) return '-'
    return compra.value.diario?.NumeroDiario || compra.value.IdDiario || '-'
})

const totalFormateado = computed(() => {
    if (!compra.value) return '0.00'
    return formatearMonto(compra.value.ImporteFactura)
})

// ==================== HELPERS ====================
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    
    let dia, mes, anio
    
    if (typeof fecha === 'string') {
        const partes = fecha.split('T')[0].split('-')
        if (partes.length === 3) {
            dia = parseInt(partes[2])
            mes = parseInt(partes[1])
            anio = parseInt(partes[0])
        } else {
            const fechaObj = new Date(fecha + 'T00:00:00')
            if (!isNaN(fechaObj.getTime())) {
                dia = fechaObj.getUTCDate()
                mes = fechaObj.getUTCMonth() + 1
                anio = fechaObj.getUTCFullYear()
            } else {
                return '-'
            }
        }
    } else if (fecha instanceof Date) {
        dia = fecha.getUTCDate()
        mes = fecha.getUTCMonth() + 1
        anio = fecha.getUTCFullYear()
    } else {
        return '-'
    }
    
    return `${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}/${anio}`
}

const formatearMonto = (monto) => {
    return Number(monto || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Contabilizada' : 'Borrador'
}

const getTipoDocumento = (tipo) => {
    return tipo == 1 ? 'Factura' : 'Recibo'
}

const generarPDF = () => {
    if (compra.value) {
        window.open(`/gestion/compras/${compra.value.IdCompras}/pdf`, '_blank')
    }
}

const copiarTexto = (texto) => {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(texto).then(() => {
            copiando.value = true
            setTimeout(() => copiando.value = false, 1500)
        })
    }
}

// ==================== WATCHERS ====================
watch(() => props.visible, (newVal) => {
    if (newVal && props.compraId) {
        cargarCompra()
    } else {
        compra.value = null
        errorMessage.value = ''
    }
})

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
    <Teleport to="body">
        <div v-if="visible" class="fixed inset-0 z-50 overflow-y-auto" @click.self="cerrar">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" @click="cerrar"></div>
            
            <!-- Modal -->
            <div class="relative z-10 flex items-center justify-center min-h-screen p-2 sm:p-4">
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-auto transform transition-all duration-300 flex flex-col max-h-[95vh]">
                    
                    <!-- ==================== HEADER ==================== -->
                    <div class="flex-shrink-0 flex items-center justify-between px-4 sm:px-6 py-2.5 bg-primary-600 rounded-t-xl">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center text-white flex-shrink-0">
                                <i class="fas fa-receipt text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-sm sm:text-base text-white truncate">
                                    Detalle de Compra
                                    <span v-if="compra" class="ml-2 bg-white/20 text-white text-[10px] px-2 py-0.5 rounded-full font-mono">
                                        #{{ compra.NumeroCorrelativo }}
                                    </span>
                                </h3>
                                <p v-if="compra" class="text-[10px] text-white/80 truncate">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    {{ fechaMostrar }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button 
                                @click="generarPDF"
                                class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition"
                                title="PDF"
                            >
                                <i class="fas fa-file-pdf text-sm"></i>
                            </button>
                            <button 
                                @click="cerrar" 
                                class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition"
                                title="Cerrar"
                            >
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ==================== BODY ==================== -->
                    <div class="flex-1 overflow-y-auto p-3 sm:p-5 space-y-4">
                        
                        <!-- Loading -->
                        <div v-if="cargando" class="text-center py-12">
                            <i class="fas fa-circle-notch fa-spin text-3xl text-primary-600"></i>
                            <p class="mt-3 text-gray-500 text-xs font-medium">Cargando compra...</p>
                        </div>
                        
                        <!-- Error -->
                        <div v-else-if="errorMessage" class="text-center py-10 px-4">
                            <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-2 block"></i>
                            <p class="text-gray-800 font-medium text-sm">{{ errorMessage }}</p>
                            <button 
                                @click="cargarCompra" 
                                class="mt-3 px-4 py-2 bg-primary-600 text-white rounded-lg text-xs font-medium hover:bg-primary-700 transition"
                            >
                                Reintentar
                            </button>
                        </div>

                        <!-- Contenido -->
                        <div v-else-if="compra" class="space-y-4">
                            
                            <!-- ==================== RESUMEN ==================== -->
                            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                <!-- Estado -->
                                <div class="px-4 sm:px-6 py-2 bg-gray-50 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-[10px] font-medium text-gray-500">Estado:</span>
                                        <span class="px-2 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(compra.ActivoInactivo)">
                                            <i :class="compra.ActivoInactivo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'" class="mr-1 text-[8px]"></i>
                                            {{ getEstadoTexto(compra.ActivoInactivo) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-gray-500">
                                        <span><i class="far fa-clock mr-1"></i>{{ compra.FechaIngreso ? formatearFecha(compra.FechaIngreso) : '-' }}</span>
                                    </div>
                                </div>

                                <!-- Grid de datos -->
                                <div class="p-4 sm:p-5">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                                        <div>
                                            <p class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold">N° Correlativo</p>
                                            <p class="text-sm font-bold text-gray-800 font-mono">{{ compra.NumeroCorrelativo }}</p>
                                        </div>
                                        <div @click="copiarTexto(numeroDiario)" class="cursor-pointer group">
                                            <p class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold flex items-center gap-1">
                                                N° Diario
                                                <i class="fas fa-copy text-[8px] text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                            </p>
                                            <p class="text-sm font-bold text-blue-600 font-mono">{{ numeroDiario }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold">Fecha</p>
                                            <p class="text-sm font-bold text-gray-800">{{ fechaMostrar }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold">Almacén</p>
                                            <p class="text-sm font-medium text-gray-700">{{ compra.almacen?.Almacen || '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold">Tipo Documento</p>
                                            <p class="text-sm font-medium text-gray-700">
                                                <span class="px-2 py-0.5 bg-gray-100 rounded text-[10px]">
                                                    {{ getTipoDocumento(compra.IdTipoFactura) }}
                                                </span>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold">N° Documento</p>
                                            <p class="text-sm font-bold text-gray-800 font-mono">{{ compra.NumeroFactura || '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold">N° Autorización</p>
                                            <p class="text-sm font-medium text-gray-700">{{ compra.NumeroAutorizacion || '-' }}</p>
                                        </div>
                                        <!-- 🔥 NUEVA COLUMNA: OPERADOR -->
                                        <div>
                                            <p class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold">Operador</p>
                                            <p class="text-sm font-medium text-gray-700 flex items-center gap-1">
                                                <i class="fas fa-user text-primary-400 text-[10px]"></i>
                                                {{ compra.nombre_operador || 'S/D' }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Proveedor -->
                                    <div class="mt-3 pt-3 border-t border-gray-100">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4">
                                            <p class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold">Proveedor</p>
                                            <div class="flex flex-wrap items-center gap-3">
                                                <p class="text-sm font-bold text-gray-800">{{ compra.proveedor?.Nombre || '-' }}</p>
                                                <span class="text-[10px] text-gray-400 font-mono bg-gray-50 px-2 py-0.5 rounded">
                                                    NIT: {{ compra.proveedor?.CI_NIT || '-' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Observación -->
                                    <div v-if="compra.Observacion" class="mt-3 pt-3 border-t border-gray-100">
                                        <p class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold">Observación</p>
                                        <p class="text-sm text-gray-600 italic">{{ compra.Observacion }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- ==================== PRODUCTOS ==================== -->
                            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                <div class="p-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                                    <h4 class="text-xs font-semibold text-gray-700 flex items-center gap-2">
                                        <i class="fas fa-boxes text-primary-500"></i>
                                        Productos
                                        <span class="bg-primary-100 text-primary-600 text-[10px] px-2 py-0.5 rounded-full">
                                            {{ compra.detalles?.length || 0 }}
                                        </span>
                                    </h4>
                                    <span class="text-sm font-bold text-primary-600">
                                        {{ totalFormateado }} Bs
                                    </span>
                                </div>

                                <!-- VISTA MÓVIL (tarjetas) -->
                                <div v-if="isMobile" class="divide-y divide-gray-100">
                                    <div v-for="(detalle, index) in compra.detalles" :key="detalle.IdComprasDetalle" class="p-3 hover:bg-gray-50 transition">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-[9px] text-gray-400 font-mono">#{{ index + 1 }}</span>
                                                    <span class="text-[9px] font-mono text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                                                        {{ detalle.producto?.Codigo || '-' }}
                                                    </span>
                                                </div>
                                                <p class="text-xs font-medium text-gray-800 mt-0.5 truncate">{{ detalle.producto?.Descripcion || '-' }}</p>
                                            </div>
                                            <div class="text-right flex-shrink-0 ml-2">
                                                <span class="text-xs font-bold text-primary-600">{{ formatearMonto(detalle.TotalBolivianos) }} Bs</span>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-3 mt-1 text-[9px] text-gray-400">
                                            <span>Unidades: <span class="font-mono text-gray-600">{{ Number(detalle.Unidades || 0).toFixed(4) }}</span></span>
                                            <span>Precio: <span class="font-mono text-gray-600">{{ formatearMonto(detalle.Precio) }} Bs</span></span>
                                        </div>
                                    </div>
                                    <div v-if="compra.detalles?.length === 0" class="p-6 text-center text-gray-400 text-xs">
                                        <i class="fas fa-box-open text-2xl block mb-2 text-gray-300"></i>
                                        No hay productos registrados
                                    </div>
                                </div>

                                <!-- VISTA TABLET Y ESCRITORIO (tabla) -->
                                <div v-else class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-[9px] font-medium text-gray-500 uppercase">#</th>
                                                <th class="px-3 py-2 text-left text-[9px] font-medium text-gray-500 uppercase">Código</th>
                                                <th class="px-3 py-2 text-left text-[9px] font-medium text-gray-500 uppercase">Producto</th>
                                                <th class="px-3 py-2 text-right text-[9px] font-medium text-gray-500 uppercase">Unidades</th>
                                                <th class="px-3 py-2 text-right text-[9px] font-medium text-gray-500 uppercase">Precio Unit.</th>
                                                <th class="px-3 py-2 text-right text-[9px] font-medium text-gray-500 uppercase">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            <tr v-for="(detalle, index) in compra.detalles" :key="detalle.IdComprasDetalle" class="hover:bg-gray-50 transition">
                                                <td class="px-3 py-2 text-[10px] text-gray-400 font-mono">{{ index + 1 }}</td>
                                                <td class="px-3 py-2 text-[10px] font-mono text-gray-600">{{ detalle.producto?.Codigo || '-' }}</td>
                                                <td class="px-3 py-2 text-[10px] text-gray-700 max-w-[200px] truncate" :title="detalle.producto?.Descripcion">
                                                    {{ detalle.producto?.Descripcion || '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-right text-[10px] font-mono text-gray-600">{{ Number(detalle.Unidades || 0).toFixed(4) }}</td>
                                                <td class="px-3 py-2 text-right text-[10px] font-mono text-gray-600">{{ formatearMonto(detalle.Precio) }}</td>
                                                <td class="px-3 py-2 text-right text-[10px] font-bold text-primary-600">{{ formatearMonto(detalle.TotalBolivianos) }}</td>
                                            </tr>
                                            <tr v-if="compra.detalles?.length === 0">
                                                <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-xs">
                                                    <i class="fas fa-box-open text-2xl block mb-2 text-gray-300"></i>
                                                    No hay productos registrados
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot v-if="compra.detalles?.length > 0" class="bg-gray-50">
                                            <tr class="border-t border-gray-200">
                                                <td colspan="5" class="px-3 py-2 text-right text-xs font-bold text-gray-700">TOTAL COMPRA</td>
                                                <td class="px-3 py-2 text-right text-sm font-bold text-primary-600">
                                                    {{ totalFormateado }} Bs
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== FOOTER ==================== -->
                    <div class="flex-shrink-0 px-4 sm:px-6 py-2.5 border-t border-gray-200 bg-gray-50 rounded-b-xl flex flex-wrap justify-end gap-2">
                        <button 
                            @click="generarPDF" 
                            class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-medium transition flex items-center gap-2"
                        >
                            <i class="fas fa-file-pdf text-[10px]"></i>
                            PDF
                        </button>
                        <button 
                            @click="cerrar" 
                            class="px-4 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-medium transition"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Toast de copiado -->
    <div v-if="copiando" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-[60] bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg text-xs flex items-center gap-2 animate-fade-in-up">
        <i class="fas fa-check-circle text-emerald-400"></i>
        ¡Copiado al portapapeles!
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translate(-50%, 20px);
    }
    to {
        opacity: 1;
        transform: translate(-50%, 0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.3s ease-out;
}

.group-hover\:opacity-100 {
    transition: opacity 0.2s ease;
}

/* Scroll personalizado */
.overflow-y-auto::-webkit-scrollbar {
    width: 5px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>