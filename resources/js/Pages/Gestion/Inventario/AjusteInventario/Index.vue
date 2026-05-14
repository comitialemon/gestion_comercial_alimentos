<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    ajustes: Object
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
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Ajustes de Inventario</h1>
                            <p class="text-sm text-gray-500">Historial de ajustes contabilizados</p>
                        </div>
                    </div>
                    <Link href="/gestion/inventario/ajustes/create" class="bg-guindo-600 hover:bg-guindo-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                        <i class="fas fa-plus"></i> Nuevo Ajuste
                    </Link>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-guindo-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">N° Correlativo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Concepto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Almacén</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-guindo-700 uppercase">Estado</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="ajuste in ajustes.data" :key="ajuste.IdAjustesPrincipal" class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ ajuste.NumeroCorrelativo }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ formatearFecha(ajuste.FechaIngreso) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <span :class="ajuste.ConceptoOperacion === 'INGRESO' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ ajuste.ConceptoOperacion }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ ajuste.tipo_operacion?.Detalle || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ ajuste.almacen?.Almacen || '-' }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i> Contabilizado
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <Link :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}`" class="text-guindo-600 hover:text-guindo-800" title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </Link>
                                    <a :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}/pdf`" target="_blank" class="text-red-600 hover:text-red-800" title="Descargar PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="ajustes.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-clipboard-list text-3xl mb-2 block"></i>
                                    No hay ajustes registrados
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Paginación -->
                    <div v-if="ajustes.links && ajustes.links.length > 1" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">Mostrando {{ ajustes.from || 0 }} a {{ ajustes.to || 0 }} de {{ ajustes.total || 0 }}</div>
                            <div class="flex gap-1">
                                <Link v-for="link in ajustes.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border text-sm" :class="{ 'bg-guindo-600 text-white border-guindo-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>