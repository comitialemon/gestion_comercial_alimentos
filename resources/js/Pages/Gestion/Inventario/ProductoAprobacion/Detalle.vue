<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    producto: Object,
    solicitud: Object,
    preciosSucursal: Array,
    preciosMayorista: Array,
    detallesInventario: Array,
})

const activeTab = ref(0)
const isMobile = ref(false)
const isTablet = ref(false)

// ==================== DETECTAR RESPONSIVE ====================
const handleResize = () => {
    isMobile.value = window.innerWidth < 640
    isTablet.value = window.innerWidth >= 640 && window.innerWidth < 1024
}

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

const volver = () => {
    router.get('/gestion/inventario/productos-aprobacion/pendientes')
}

// Número de elementos por tab
const totalPreciosSucursal = props.preciosSucursal?.length || 0
const totalPreciosMayorista = props.preciosMayorista?.length || 0
const totalDetallesInventario = props.detallesInventario?.length || 0

// Tabs para móvil
const tabs = [
    { id: 0, icon: 'fa-store', label: 'Sucursal', count: totalPreciosSucursal },
    { id: 1, icon: 'fa-chart-line', label: 'Mayorista', count: totalPreciosMayorista },
    { id: 2, icon: 'fa-cubes', label: 'Detalle', count: totalDetallesInventario },
]
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-6">
            <div class="max-w-6xl mx-auto">
                <!-- Header con volver -->
                <div class="flex items-center gap-2 sm:gap-3 mb-3 sm:mb-6">
                    <button @click="volver" class="transition flex items-center gap-1 sm:gap-2 text-[10px] sm:text-sm"
                            :style="{ color: `var(--color-primary-600)` }">
                        <i class="fas fa-arrow-left text-[10px] sm:text-sm"></i> 
                        <span class="hidden xs:inline">Volver a pendientes</span>
                        <span class="xs:hidden">Volver</span>
                    </button>
                </div>

                <!-- Datos del producto -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-3 sm:mb-6">
                    <div class="px-3 sm:px-6 py-2.5 sm:py-4" :style="{ backgroundColor: `var(--color-primary-600)` }">
                        <h2 class="text-sm sm:text-lg font-bold text-white flex items-center gap-2">
                            <i class="fas fa-box text-[10px] sm:text-sm"></i> 
                            <span class="truncate">Datos del Producto</span>
                        </h2>
                    </div>
                    <div class="p-3 sm:p-6">
                        <!-- Grid responsive -->
                        <div class="grid grid-cols-1 xs:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-4">
                            <div>
                                <p class="text-[9px] sm:text-xs text-gray-500">Código</p>
                                <p class="text-[11px] sm:text-sm font-semibold text-gray-800 truncate">{{ producto?.Codigo || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] sm:text-xs text-gray-500">Detalle</p>
                                <p class="text-[11px] sm:text-sm font-semibold text-gray-800 truncate">{{ producto?.Detalle || '-' }}</p>
                            </div>
                            <div class="xs:col-span-2 lg:col-span-1">
                                <p class="text-[9px] sm:text-xs text-gray-500">Nombre para Factura</p>
                                <p class="text-[11px] sm:text-sm text-gray-700 truncate">{{ producto?.NombreCortoFactura || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] sm:text-xs text-gray-500">Precio Venta</p>
                                <p class="text-sm sm:text-lg font-bold" :style="{ color: `var(--color-primary-600)` }">
                                    {{ Number(producto?.PrecioVenta).toFixed(2) }} Bs
                                </p>
                            </div>
                            <div>
                                <p class="text-[9px] sm:text-xs text-gray-500">Grupo</p>
                                <p class="text-[11px] sm:text-sm text-gray-700 truncate">{{ producto?.grupo?.Detalle || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] sm:text-xs text-gray-500">Estado</p>
                                <span class="inline-block px-1.5 sm:px-2 py-0.5 text-[8px] sm:text-xs rounded-full" :class="{
                                    'bg-yellow-100 text-yellow-800': producto?.ActivoInactivo === 2,
                                    'bg-green-100 text-green-800': producto?.ActivoInactivo === 0,
                                    'bg-red-100 text-red-800': producto?.ActivoInactivo === 3
                                }">
                                    {{ producto?.ActivoInactivo === 2 ? 'Pendiente' : (producto?.ActivoInactivo === 0 ? 'Activo' : 'Rechazado') }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Imagen -->
                        <div v-if="producto?.ImagenProducto" class="mt-3 sm:mt-4">
                            <p class="text-[9px] sm:text-xs text-gray-500 mb-0.5 sm:mb-1">Imagen</p>
                            <img :src="producto.ImagenProducto" class="w-14 h-14 sm:w-20 sm:h-20 object-cover rounded-lg border"
                                 :style="{ borderColor: `var(--color-primary-200)` }">
                        </div>
                    </div>
                </div>

                <!-- Estado de aprobación -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-3 sm:mb-6">
                    <div class="px-3 sm:px-6 py-2.5 sm:py-4" :style="{ backgroundColor: `var(--color-secondary-600)` }">
                        <h2 class="text-sm sm:text-lg font-bold text-white flex items-center gap-2">
                            <i class="fas fa-clipboard-list text-[10px] sm:text-sm"></i>
                            <span class="truncate">Estado de Aprobación</span>
                        </h2>
                    </div>
                    <div class="p-3 sm:p-6">
                        <div class="space-y-2 sm:space-y-3">
                            <!-- Header -->
                            <div class="flex justify-between items-center pb-1.5 sm:pb-2 border-b" :style="{ borderColor: `var(--color-primary-200)` }">
                                <span class="text-[10px] sm:text-sm font-medium text-gray-600">Aprobador</span>
                                <span class="text-[10px] sm:text-sm font-medium text-gray-600">Estado</span>
                            </div>
                            
                            <!-- Votos -->
                            <div v-for="voto in solicitud?.votos" :key="voto.IdProductoAprobacionVoto" 
                                 class="flex justify-between items-center py-1.5 sm:py-2 flex-wrap gap-1">
                                <span class="text-[10px] sm:text-sm text-gray-700 flex items-center gap-1.5 sm:gap-2 min-w-0 flex-1">
                                    <i class="fas fa-user-circle text-gray-400 text-[10px] sm:text-sm flex-shrink-0"></i>
                                    <span class="truncate">{{ voto.aprobador?.identificador?.Nombre || '-' }}</span>
                                </span>
                                <span class="px-1.5 sm:px-2 py-0.5 text-[8px] sm:text-xs rounded-full flex-shrink-0" :class="{
                                    'bg-yellow-100 text-yellow-800': voto.Estado === 'pendiente',
                                    'bg-green-100 text-green-800': voto.Estado === 'aprobado',
                                    'bg-red-100 text-red-800': voto.Estado === 'rechazado'
                                }">
                                    {{ voto.Estado === 'pendiente' ? 'Pendiente' : (voto.Estado === 'aprobado' ? 'Aprobado' : 'Rechazado') }}
                                </span>
                            </div>
                            
                            <!-- Sin votos -->
                            <div v-if="!solicitud?.votos || solicitud.votos.length === 0" class="text-center py-4 text-gray-400 text-[10px] sm:text-sm">
                                <i class="fas fa-clock text-xl mb-2 block"></i>
                                No hay aprobadores asignados aún
                            </div>
                        </div>
                        
                        <!-- Estado final -->
                        <div v-if="solicitud?.Estado === 'rechazado'" class="mt-3 sm:mt-4 p-2 sm:p-3 bg-red-50 rounded-lg border border-red-200">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-times-circle text-red-600 text-[10px] sm:text-sm"></i>
                                <p class="text-[10px] sm:text-sm text-red-700 font-medium">Producto rechazado</p>
                            </div>
                            <p class="text-[9px] sm:text-xs text-red-600 mt-0.5 sm:mt-1">El producto no estará disponible para la venta</p>
                        </div>
                        <div v-if="solicitud?.Estado === 'aprobado'" class="mt-3 sm:mt-4 p-2 sm:p-3 bg-green-50 rounded-lg border border-green-200">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-check-circle text-green-600 text-[10px] sm:text-sm"></i>
                                <p class="text-[10px] sm:text-sm text-green-700 font-medium">Producto aprobado</p>
                            </div>
                            <p class="text-[9px] sm:text-xs text-green-600 mt-0.5 sm:mt-1">El producto ya está disponible para la venta</p>
                        </div>
                    </div>
                </div>

                <!-- Pestañas de detalles adicionales -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <!-- Desktop: Tabs horizontales -->
                    <div class="hidden sm:block border-b border-gray-200 overflow-x-auto">
                        <nav class="flex -mb-px min-w-max">
                            <button 
                                v-for="tab in tabs" 
                                :key="tab.id"
                                @click="activeTab = tab.id" 
                                class="px-4 sm:px-6 py-2.5 sm:py-3 text-[10px] sm:text-sm font-medium transition whitespace-nowrap"
                                :class="activeTab === tab.id ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                                :style="{ borderColor: activeTab === tab.id ? `var(--color-primary-600)` : 'transparent' }"
                            >
                                <i :class="`fas ${tab.icon} mr-1 sm:mr-2 text-[9px] sm:text-sm`"></i> 
                                {{ tab.label }}
                                <span v-if="tab.count > 0" class="ml-1 px-1.5 py-0.5 text-[8px] sm:text-xs rounded-full bg-gray-200 text-gray-600">
                                    {{ tab.count }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- Mobile: Tabs en Grid 2 columnas -->
                    <div class="sm:hidden grid grid-cols-3 gap-1 p-1.5 bg-gray-50 border-b border-gray-200">
                        <button 
                            v-for="tab in tabs" 
                            :key="tab.id"
                            @click="activeTab = tab.id" 
                            class="px-1 py-1.5 rounded-lg text-[9px] font-medium transition flex flex-col items-center justify-center gap-0.5"
                            :class="activeTab === tab.id 
                                ? 'bg-primary-600 text-white shadow-sm' 
                                : 'bg-white text-gray-600 hover:bg-gray-100'"
                            :style="{
                                backgroundColor: activeTab === tab.id ? `var(--color-primary-600)` : 'white',
                                color: activeTab === tab.id ? 'white' : '#4B5563'
                            }"
                        >
                            <i :class="`fas ${tab.icon} text-[10px]`"></i>
                            <span>{{ tab.label }}</span>
                            <span v-if="tab.count > 0" class="text-[7px]" :class="activeTab === tab.id ? 'text-white/80' : 'text-gray-400'">
                                ({{ tab.count }})
                            </span>
                        </button>
                    </div>

                    <!-- Contenido de las pestañas -->
                    <div class="p-2 sm:p-6">
                        <!-- Pestaña: Precio Sucursal -->
                        <div v-show="activeTab === 0">
                            <div v-if="totalPreciosSucursal === 0" class="text-center py-6 sm:py-8 text-gray-400">
                                <i class="fas fa-store text-2xl sm:text-3xl mb-2 block"></i>
                                <p class="text-[10px] sm:text-sm">No hay precios configurados por sucursal</p>
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50" :style="{ backgroundColor: `var(--color-primary-50)` }">
                                        <tr>
                                            <th class="px-2 sm:px-4 py-1.5 sm:py-2 text-left text-[8px] sm:text-xs font-medium uppercase"
                                                :style="{ color: `var(--color-primary-700)` }">Sucursal</th>
                                            <th class="px-2 sm:px-4 py-1.5 sm:py-2 text-right text-[8px] sm:text-xs font-medium uppercase"
                                                :style="{ color: `var(--color-primary-700)` }">Precio (Bs)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="precio in preciosSucursal" :key="precio.IdPrecio" class="hover:bg-gray-50 transition">
                                            <td class="px-2 sm:px-4 py-1.5 sm:py-2 text-[9px] sm:text-sm text-gray-700">
                                                <i class="fas fa-store text-gray-400 text-[8px] sm:text-xs mr-1 sm:mr-2"></i>
                                                <span class="truncate">{{ precio.sucursal?.Nombre || '-' }}</span>
                                            </td>
                                            <td class="px-2 sm:px-4 py-1.5 sm:py-2 text-[9px] sm:text-sm text-right font-semibold"
                                                :style="{ color: `var(--color-primary-600)` }">
                                                {{ Number(precio.Precio).toFixed(2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pestaña: Precio Mayorista -->
                        <div v-show="activeTab === 1">
                            <div v-if="totalPreciosMayorista === 0" class="text-center py-6 sm:py-8 text-gray-400">
                                <i class="fas fa-chart-line text-2xl sm:text-3xl mb-2 block"></i>
                                <p class="text-[10px] sm:text-sm">No hay precios mayoristas configurados</p>
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50" :style="{ backgroundColor: `var(--color-primary-50)` }">
                                        <tr>
                                            <th class="px-2 sm:px-4 py-1.5 sm:py-2 text-left text-[8px] sm:text-xs font-medium uppercase"
                                                :style="{ color: `var(--color-primary-700)` }">Sucursal</th>
                                            <th class="px-2 sm:px-4 py-1.5 sm:py-2 text-left text-[8px] sm:text-xs font-medium uppercase"
                                                :style="{ color: `var(--color-primary-700)` }">Mayorista</th>
                                            <th class="px-2 sm:px-4 py-1.5 sm:py-2 text-right text-[8px] sm:text-xs font-medium uppercase"
                                                :style="{ color: `var(--color-primary-700)` }">Precio (Bs)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="precio in preciosMayorista" :key="precio.IdPrecioMayorista" class="hover:bg-gray-50 transition">
                                            <td class="px-2 sm:px-4 py-1.5 sm:py-2 text-[9px] sm:text-sm text-gray-700">
                                                <i class="fas fa-store text-gray-400 text-[8px] sm:text-xs mr-1 sm:mr-2"></i>
                                                <span class="truncate">{{ precio.sucursal?.Nombre || '-' }}</span>
                                            </td>
                                            <td class="px-2 sm:px-4 py-1.5 sm:py-2 text-[9px] sm:text-sm text-gray-700">
                                                <i class="fas fa-user-tie text-gray-400 text-[8px] sm:text-xs mr-1 sm:mr-2"></i>
                                                <span class="truncate">{{ precio.identificador?.Nombre || '-' }}</span>
                                                <span class="text-[7px] sm:text-xs text-gray-400 ml-0.5 sm:ml-1">({{ precio.identificador?.CI_NIT }})</span>
                                            </td>
                                            <td class="px-2 sm:px-4 py-1.5 sm:py-2 text-[9px] sm:text-sm text-right font-semibold"
                                                :style="{ color: `var(--color-primary-600)` }">
                                                {{ Number(precio.Precio).toFixed(2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pestaña: Inventario Detalle -->
                        <div v-show="activeTab === 2">
                            <div v-if="totalDetallesInventario === 0" class="text-center py-6 sm:py-8 text-gray-400">
                                <i class="fas fa-cubes text-2xl sm:text-3xl mb-2 block"></i>
                                <p class="text-[10px] sm:text-sm">No hay productos asociados en el detalle de inventario</p>
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50" :style="{ backgroundColor: `var(--color-primary-50)` }">
                                        <tr>
                                            <th class="px-2 sm:px-4 py-1.5 sm:py-2 text-left text-[8px] sm:text-xs font-medium uppercase"
                                                :style="{ color: `var(--color-primary-700)` }">Código</th>
                                            <th class="px-2 sm:px-4 py-1.5 sm:py-2 text-left text-[8px] sm:text-xs font-medium uppercase"
                                                :style="{ color: `var(--color-primary-700)` }">Producto</th>
                                            <th class="px-2 sm:px-4 py-1.5 sm:py-2 text-right text-[8px] sm:text-xs font-medium uppercase"
                                                :style="{ color: `var(--color-primary-700)` }">Porción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="detalle in detallesInventario" :key="detalle.IdDetalleProductoPorcion" class="hover:bg-gray-50 transition">
                                            <td class="px-2 sm:px-4 py-1.5 sm:py-2 text-[9px] sm:text-sm font-mono text-gray-600">
                                                {{ detalle.producto?.Codigo || '-' }}
                                            </td>
                                            <td class="px-2 sm:px-4 py-1.5 sm:py-2 text-[9px] sm:text-sm text-gray-700">
                                                <i class="fas fa-box text-gray-400 text-[8px] sm:text-xs mr-1 sm:mr-2"></i>
                                                <span class="truncate">{{ detalle.producto?.Descripcion || '-' }}</span>
                                            </td>
                                            <td class="px-2 sm:px-4 py-1.5 sm:py-2 text-[9px] sm:text-sm text-right font-semibold"
                                                :style="{ color: `var(--color-primary-600)` }">
                                                {{ Number(detalle.Porcion).toFixed(6) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Estilos para pantallas muy pequeñas */
@media (min-width: 480px) {
    .xs\:inline {
        display: inline !important;
    }
    .xs\:hidden {
        display: none !important;
    }
    .xs\:col-span-2 {
        grid-column: span 2 / span 2;
    }
}

@media (max-width: 479px) {
    .xs\:inline {
        display: none !important;
    }
    .xs\:hidden {
        display: inline !important;
    }
    .xs\:col-span-2 {
        grid-column: span 1 / span 1;
    }
}

/* Scroll suave */
* {
    scroll-behavior: smooth;
}

/* Transiciones para tabs */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}
</style>