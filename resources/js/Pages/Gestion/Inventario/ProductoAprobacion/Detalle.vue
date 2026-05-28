<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    producto: Object,
    solicitud: Object,
    preciosSucursal: Array,
    preciosMayorista: Array,
    detallesInventario: Array,
})

const activeTab = ref(0)

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

const volver = () => {
    router.get('/gestion/productos-aprobacion/pendientes')
}

// Número de elementos por tab
const totalPreciosSucursal = props.preciosSucursal?.length || 0
const totalPreciosMayorista = props.preciosMayorista?.length || 0
const totalDetallesInventario = props.detallesInventario?.length || 0
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header con volver -->
                <div class="flex items-center gap-3 mb-6">
                    <button @click="volver" class="text-primary-600 hover:text-primary-800 flex items-center gap-2 text-sm">
                        <i class="fas fa-arrow-left"></i> Volver a pendientes
                    </button>
                </div>

                <!-- Datos del producto -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
                    <div class="bg-primary-600 px-6 py-4">
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fas fa-box"></i> Datos del Producto
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Código</p>
                                <p class="text-sm font-semibold text-gray-800">{{ producto?.Codigo || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Detalle</p>
                                <p class="text-sm font-semibold text-gray-800">{{ producto?.Detalle || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Nombre para Factura</p>
                                <p class="text-sm text-gray-700">{{ producto?.NombreCortoFactura || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Precio Venta</p>
                                <p class="text-lg font-bold text-primary-600">{{ Number(producto?.PrecioVenta).toFixed(2) }} Bs</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Grupo</p>
                                <p class="text-sm text-gray-700">{{ producto?.grupo?.Detalle || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Estado</p>
                                <span class="inline-block px-2 py-0.5 text-xs rounded-full" :class="{
                                    'bg-yellow-100 text-yellow-800': producto?.ActivoInactivo === 2,
                                    'bg-green-100 text-green-800': producto?.ActivoInactivo === 0,
                                    'bg-red-100 text-red-800': producto?.ActivoInactivo === 3
                                }">
                                    {{ producto?.ActivoInactivo === 2 ? 'Pendiente' : (producto?.ActivoInactivo === 0 ? 'Activo' : 'Rechazado') }}
                                </span>
                            </div>
                        </div>
                        <div v-if="producto?.ImagenProducto" class="mt-4">
                            <p class="text-xs text-gray-500 mb-1">Imagen</p>
                            <img :src="producto.ImagenProducto" class="w-20 h-20 object-cover rounded-lg border">
                        </div>
                    </div>
                </div>

                <!-- Estado de aprobación -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
                    <div class="bg-secondary-600 px-6 py-4">
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fas fa-clipboard-list"></i> Estado de Aprobación
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <div class="flex justify-between items-center pb-2 border-b">
                                <span class="text-sm font-medium text-gray-600">Aprobador</span>
                                <span class="text-sm font-medium text-gray-600">Estado</span>
                            </div>
                            <div v-for="voto in solicitud?.votos" :key="voto.IdProductoAprobacionVoto" class="flex justify-between items-center py-2">
                                <span class="text-sm text-gray-700 flex items-center gap-2">
                                    <i class="fas fa-user-circle text-gray-400"></i>
                                    {{ voto.aprobador?.identificador?.Nombre || '-' }}
                                </span>
                                <span class="px-2 py-0.5 text-xs rounded-full" :class="{
                                    'bg-yellow-100 text-yellow-800': voto.Estado === 'pendiente',
                                    'bg-green-100 text-green-800': voto.Estado === 'aprobado',
                                    'bg-red-100 text-red-800': voto.Estado === 'rechazado'
                                }">
                                    {{ voto.Estado === 'pendiente' ? 'Pendiente' : (voto.Estado === 'aprobado' ? 'Aprobado' : 'Rechazado') }}
                                </span>
                            </div>
                        </div>
                        <div v-if="solicitud?.Estado === 'rechazado'" class="mt-4 p-3 bg-red-50 rounded-lg">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-times-circle text-red-600"></i>
                                <p class="text-sm text-red-700 font-medium">Producto rechazado</p>
                            </div>
                            <p class="text-xs text-red-600 mt-1">El producto no estará disponible para la venta</p>
                        </div>
                        <div v-if="solicitud?.Estado === 'aprobado'" class="mt-4 p-3 bg-green-50 rounded-lg">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-check-circle text-green-600"></i>
                                <p class="text-sm text-green-700 font-medium">Producto aprobado</p>
                            </div>
                            <p class="text-xs text-green-600 mt-1">El producto ya está disponible para la venta</p>
                        </div>
                    </div>
                </div>

                <!-- Pestañas de detalles adicionales -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button 
                                @click="activeTab = 0" 
                                class="px-6 py-3 text-sm font-medium transition"
                                :class="activeTab === 0 ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                            >
                                <i class="fas fa-store mr-2"></i> Precio Sucursal
                                <span v-if="totalPreciosSucursal > 0" class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600">{{ totalPreciosSucursal }}</span>
                            </button>
                            <button 
                                @click="activeTab = 1" 
                                class="px-6 py-3 text-sm font-medium transition"
                                :class="activeTab === 1 ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                            >
                                <i class="fas fa-chart-line mr-2"></i> Precio Mayorista
                                <span v-if="totalPreciosMayorista > 0" class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600">{{ totalPreciosMayorista }}</span>
                            </button>
                            <button 
                                @click="activeTab = 2" 
                                class="px-6 py-3 text-sm font-medium transition"
                                :class="activeTab === 2 ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                            >
                                <i class="fas fa-cubes mr-2"></i> Inventario Detalle
                                <span v-if="totalDetallesInventario > 0" class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600">{{ totalDetallesInventario }}</span>
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <!-- Pestaña: Precio Sucursal -->
                        <div v-show="activeTab === 0">
                            <div v-if="totalPreciosSucursal === 0" class="text-center py-8 text-gray-400">
                                <i class="fas fa-store text-3xl mb-2 block"></i>
                                <p class="text-sm">No hay precios configurados por sucursal</p>
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sucursal</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Precio (Bs)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="precio in preciosSucursal" :key="precio.IdPrecio" class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-700">
                                                <i class="fas fa-store text-gray-400 mr-2"></i>
                                                {{ precio.sucursal?.Nombre || '-' }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-right font-semibold text-primary-600">
                                                {{ Number(precio.Precio).toFixed(2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pestaña: Precio Mayorista -->
                        <div v-show="activeTab === 1">
                            <div v-if="totalPreciosMayorista === 0" class="text-center py-8 text-gray-400">
                                <i class="fas fa-chart-line text-3xl mb-2 block"></i>
                                <p class="text-sm">No hay precios mayoristas configurados</p>
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sucursal</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mayorista</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Precio (Bs)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="precio in preciosMayorista" :key="precio.IdPrecioMayorista" class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-700">
                                                <i class="fas fa-store text-gray-400 mr-2"></i>
                                                {{ precio.sucursal?.Nombre || '-' }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-700">
                                                <i class="fas fa-user-tie text-gray-400 mr-2"></i>
                                                {{ precio.identificador?.Nombre || '-' }}
                                                <span class="text-xs text-gray-400 ml-1">({{ precio.identificador?.CI_NIT }})</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-right font-semibold text-primary-600">
                                                {{ Number(precio.Precio).toFixed(2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pestaña: Inventario Detalle -->
                        <div v-show="activeTab === 2">
                            <div v-if="totalDetallesInventario === 0" class="text-center py-8 text-gray-400">
                                <i class="fas fa-cubes text-3xl mb-2 block"></i>
                                <p class="text-sm">No hay productos asociados en el detalle de inventario</p>
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Porción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="detalle in detallesInventario" :key="detalle.IdDetalleProductoPorcion" class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm font-mono text-gray-600">
                                                {{ detalle.producto?.Codigo || '-' }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-700">
                                                <i class="fas fa-box text-gray-400 mr-2"></i>
                                                {{ detalle.producto?.Descripcion || '-' }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-right font-semibold text-primary-600">
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