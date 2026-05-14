<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    compras: Object
})

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Compras</h1>
                            <p class="text-sm text-gray-500">Historial de compras realizadas</p>
                        </div>
                    </div>
                    <Link href="/gestion/compras/create" class="bg-guindo-600 hover:bg-guindo-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                        <i class="fas fa-plus"></i> Nueva Compra
                    </Link>
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-guindo-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">N° Correlativo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">N° Documento</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Proveedor</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Importe</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-guindo-700 uppercase">Estado</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="compra in compras.data" :key="compra.IdCompras" class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ compra.NumeroCorrelativo }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ formatearFecha(compra.FechaIngreso) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ compra.NumeroFactura }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ compra.proveedor?.Nombre || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-right font-semibold text-guindo-600">{{ Number(compra.ImporteFactura).toFixed(2) }} Bs</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i> Contabilizada
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <Link :href="`/gestion/compras/${compra.IdCompras}`" class="text-guindo-600 hover:text-guindo-800" title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </Link>
                                    <a :href="`/gestion/compras/${compra.IdCompras}/pdf`" target="_blank" class="text-red-600 hover:text-red-800" title="Descargar PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="compras.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                    No hay compras registradas
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="compras.links && compras.links.length > 1" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">Mostrando {{ compras.from || 0 }} a {{ compras.to || 0 }} de {{ compras.total || 0 }}</div>
                            <div class="flex gap-1">
                                <Link v-for="link in compras.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border text-sm" :class="{ 'bg-guindo-600 text-white border-guindo-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>