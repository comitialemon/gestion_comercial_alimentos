<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    compra: Object
})

const isMobile = ref(window.innerWidth < 768)
const copiando = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// ✅ Formatear fecha a dd/mm/aaaa SIN problema de zona horaria
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    
    // Si la fecha viene como string 'Y-m-d' o 'Y-m-d H:i:s'
    let dia, mes, anio
    
    if (typeof fecha === 'string') {
        // Intentar parsear formato YYYY-MM-DD
        const partes = fecha.split('T')[0].split('-')
        if (partes.length === 3) {
            dia = parseInt(partes[2])
            mes = parseInt(partes[1])
            anio = parseInt(partes[0])
        } else {
            // Intentar otros formatos
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
    
    // Formatear con ceros a la izquierda
    const diaStr = String(dia).padStart(2, '0')
    const mesStr = String(mes).padStart(2, '0')
    
    return `${diaStr}/${mesStr}/${anio}`
}

const generarPDF = () => {
    window.open(`/gestion/compras/${props.compra.IdCompras}/pdf`, '_blank')
}

const volver = () => {
    router.get('/gestion/compras')
}

const numeroDiario = () => {
    return props.compra.diario?.NumeroDiario || props.compra.IdDiario || '-'
}

// ✅ Computado para obtener la fecha correcta formateada
const fechaMostrar = computed(() => {
    if (props.compra.fecha?.Fecha) {
        return formatearFecha(props.compra.fecha.Fecha)
    }
    return formatearFecha(props.compra.FechaIngreso)
})

// Copiar al portapapeles
const copiarTexto = (texto, mensaje) => {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(texto).then(() => {
            copiando.value = true
            setTimeout(() => copiando.value = false, 1500)
        })
    }
}

// Formatear montos
const formatearMonto = (monto) => {
    return Number(monto || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 py-3 px-2 sm:py-4 sm:px-3 md:px-4">
        <div class="max-w-full lg:max-w-5xl mx-auto">
            <!-- Header Responsive -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl flex items-center justify-center shadow-lg shadow-primary-200 flex-shrink-0">
                        <i class="fas fa-receipt text-white text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-base sm:text-lg md:text-2xl font-bold text-gray-800 flex flex-wrap items-center gap-2">
                            <span class="truncate">Detalle de Compra</span>
                            <span class="bg-primary-100 text-primary-700 text-[10px] sm:text-xs px-2 py-0.5 rounded-full font-mono whitespace-nowrap">
                                #{{ compra.NumeroCorrelativo }}
                            </span>
                        </h1>
                        <p class="text-[10px] sm:text-xs text-gray-400 flex items-center gap-1">
                            <i class="fas fa-calendar-alt text-[10px]"></i>
                            {{ fechaMostrar }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <!-- Botón PDF -->
                    <button @click="generarPDF" class="flex-1 sm:flex-initial bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-3 sm:px-4 py-2 rounded-lg text-xs flex items-center justify-center gap-2 transition-all duration-200 shadow-md shadow-red-200">
                        <i class="fas fa-file-pdf"></i>
                        <span>PDF</span>
                    </button>
                    <!-- ✅ Botón Volver con texto siempre visible -->
                    <button @click="volver" class="flex-1 sm:flex-initial bg-white hover:bg-gray-50 text-gray-700 px-3 sm:px-4 py-2 rounded-lg text-xs flex items-center justify-center gap-2 border border-gray-200 transition-all duration-200 hover:shadow-md">
                        <i class="fas fa-arrow-left"></i>
                        <span>Volver</span>
                    </button>
                </div>
            </div>

            <!-- Tarjeta de resumen -->
            <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden border border-gray-100">
                <!-- Badge de estado -->
                <div class="px-4 sm:px-6 py-2 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-medium text-gray-500">Estado:</span>
                        <span class="px-2 py-0.5 text-[10px] rounded-full" :class="compra.ActivoInactivo === 1 ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">
                            <i :class="compra.ActivoInactivo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'" class="mr-1 text-[8px]"></i>
                            {{ compra.ActivoInactivo === 1 ? 'Contabilizada' : 'Borrador' }}
                        </span>
                    </div>
                </div>

                <!-- Grid de datos -->
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="group">
                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">N° Correlativo</p>
                            <p class="text-sm font-bold text-gray-800 font-mono mt-0.5">{{ compra.NumeroCorrelativo }}</p>
                        </div>
                        <div class="group" @click="copiarTexto(numeroDiario(), 'Diario copiado')">
                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold flex items-center gap-1">
                                N° Diario
                                <i class="fas fa-copy text-[8px] text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"></i>
                            </p>
                            <p class="text-sm font-bold text-blue-600 font-mono mt-0.5">{{ numeroDiario() }}</p>
                        </div>
                        <div class="group">
                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">Fecha</p>
                            <p class="text-sm font-bold text-gray-800 mt-0.5">
                                <i class="fas fa-calendar-day text-primary-400 mr-1 text-xs"></i>
                                {{ fechaMostrar }}
                            </p>
                        </div>
                        <div class="group">
                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">Almacén</p>
                            <p class="text-sm font-medium text-gray-700 mt-0.5">
                                <i class="fas fa-warehouse text-primary-400 mr-1 text-xs"></i>
                                {{ compra.almacen?.Almacen || '-' }}
                            </p>
                        </div>
                        <div class="group">
                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">Tipo Documento</p>
                            <p class="text-sm font-medium text-gray-700 mt-0.5">
                                <span class="px-2 py-0.5 bg-gray-100 rounded text-xs">
                                    {{ compra.IdTipoFactura == 1 ? 'Factura' : 'Recibo' }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Proveedor -->
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4">
                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">Proveedor</p>
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
                        <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">Observación</p>
                        <p class="text-sm text-gray-600 mt-0.5 italic">{{ compra.Observacion }}</p>
                    </div>
                </div>
            </div>

            <!-- Productos - Versión MÓVIL (tarjetas) -->
            <div v-if="isMobile" class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                <div class="p-3 bg-gradient-to-r from-primary-50 to-white border-b border-primary-100 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-primary-700 flex items-center gap-2">
                        <i class="fas fa-boxes"></i>
                        Productos
                        <span class="bg-primary-100 text-primary-600 text-[10px] px-2 py-0.5 rounded-full">
                            {{ compra.detalles?.length || 0 }}
                        </span>
                    </h2>
                    <span class="text-xs font-bold text-primary-600">
                        {{ formatearMonto(compra.ImporteFactura) }} Bs
                    </span>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="(detalle, index) in compra.detalles" :key="detalle.IdComprasDetalle" class="p-3 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[10px] text-gray-400 font-mono">#{{ index + 1 }}</span>
                                    <span class="text-[10px] font-mono text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                                        {{ detalle.producto?.Codigo || '-' }}
                                    </span>
                                </div>
                                <p class="text-xs font-medium text-gray-800 mt-0.5 truncate">{{ detalle.producto?.Descripcion || '-' }}</p>
                            </div>
                            <div class="text-right flex-shrink-0 ml-2">
                                <span class="text-xs font-bold text-primary-600">{{ formatearMonto(detalle.TotalBolivianos) }} Bs</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3 mt-1 text-[10px] text-gray-400">
                            <span>Unidades: <span class="font-mono text-gray-600">{{ Number(detalle.Unidades || 0).toFixed(4) }}</span></span>
                            <span>Precio: <span class="font-mono text-gray-600">{{ formatearMonto(detalle.Precio) }} Bs</span></span>
                        </div>
                    </div>
                    <div v-if="compra.detalles?.length === 0" class="p-6 text-center text-gray-400 text-sm">
                        <i class="fas fa-box-open text-2xl block mb-2 text-gray-300"></i>
                        No hay productos registrados
                    </div>
                </div>
            </div>

            <!-- Productos - Versión ESCRITORIO (tabla elegante) -->
            <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                <div class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                        <h2 class="text-base sm:text-lg font-semibold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-boxes text-primary-500"></i>
                            Productos
                            <span class="bg-primary-100 text-primary-600 text-xs px-2 py-0.5 rounded-full">
                                {{ compra.detalles?.length || 0 }} items
                            </span>
                        </h2>
                        <div class="text-sm font-bold text-primary-700 bg-primary-50 px-4 py-1.5 rounded-lg">
                            Total: {{ formatearMonto(compra.ImporteFactura) }} Bs
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto -mx-4 sm:mx-0">
                        <div class="min-w-full inline-block align-middle">
                            <div class="overflow-hidden">
                                <table class="min-w-full text-xs sm:text-sm">
                                    <thead>
                                        <tr class="bg-gradient-to-r from-primary-50 to-primary-100/50">
                                            <th class="px-2 sm:px-4 py-2.5 text-left text-xs font-semibold text-primary-700 uppercase tracking-wider">#</th>
                                            <th class="px-2 sm:px-4 py-2.5 text-left text-xs font-semibold text-primary-700 uppercase tracking-wider">Código</th>
                                            <th class="px-2 sm:px-4 py-2.5 text-left text-xs font-semibold text-primary-700 uppercase tracking-wider">Producto</th>
                                            <th class="px-2 sm:px-4 py-2.5 text-right text-xs font-semibold text-primary-700 uppercase tracking-wider">Unidades</th>
                                            <th class="px-2 sm:px-4 py-2.5 text-right text-xs font-semibold text-primary-700 uppercase tracking-wider">Precio Unit.</th>
                                            <th class="px-2 sm:px-4 py-2.5 text-right text-xs font-semibold text-primary-700 uppercase tracking-wider">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="(detalle, index) in compra.detalles" :key="detalle.IdComprasDetalle" class="hover:bg-gray-50/80 transition-colors">
                                            <td class="px-2 sm:px-4 py-2.5 text-xs text-gray-400 font-mono">{{ index + 1 }}</td>
                                            <td class="px-2 sm:px-4 py-2.5 text-xs font-mono text-gray-600">{{ detalle.producto?.Codigo || '-' }}</td>
                                            <td class="px-2 sm:px-4 py-2.5 text-xs text-gray-700 max-w-[150px] sm:max-w-[250px] truncate" :title="detalle.producto?.Descripcion">
                                                {{ detalle.producto?.Descripcion || '-' }}
                                            </td>
                                            <td class="px-2 sm:px-4 py-2.5 text-right text-xs font-mono text-gray-600">{{ Number(detalle.Unidades || 0).toFixed(4) }}</td>
                                            <td class="px-2 sm:px-4 py-2.5 text-right text-xs font-mono text-gray-600">{{ formatearMonto(detalle.Precio) }}</td>
                                            <td class="px-2 sm:px-4 py-2.5 text-right text-xs font-bold text-primary-600">{{ formatearMonto(detalle.TotalBolivianos) }}</td>
                                        </tr>
                                        <tr v-if="compra.detalles?.length === 0">
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                                                <i class="fas fa-box-open text-2xl block mb-2 text-gray-300"></i>
                                                No hay productos registrados
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot v-if="compra.detalles?.length > 0" class="bg-gray-50/80">
                                        <tr>
                                            <td colspan="5" class="px-2 sm:px-4 py-3 text-right text-sm font-bold text-gray-700">TOTAL COMPRA</td>
                                            <td class="px-2 sm:px-4 py-3 text-right text-sm font-bold text-primary-600 text-base sm:text-lg">
                                                {{ formatearMonto(compra.ImporteFactura) }} Bs
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast de copiado -->
            <div v-if="copiando" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg text-xs flex items-center gap-2 animate-fade-in-up">
                <i class="fas fa-check-circle text-green-400"></i>
                ¡Copiado al portapapeles!
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Responsive */
@media (max-width: 640px) {
    .xs\:inline { display: inline; }
    .xs\:block { display: block; }
    .xs\:hidden { display: none !important; }
}

/* Para pantallas muy pequeñas */
@media (max-width: 400px) {
    .text-xs {
        font-size: 0.65rem !important;
    }
}

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

/* Scroll suave para tablas en móvil */
.overflow-x-auto {
    -webkit-overflow-scrolling: touch;
}

/* Mejor touch en móvil */
@media (max-width: 768px) {
    button, 
    [role="button"] {
        min-height: 44px;
        min-width: 44px;
    }
}
</style>