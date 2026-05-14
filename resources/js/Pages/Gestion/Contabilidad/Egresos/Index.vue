<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    egresos: Object
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-guindo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-guindo-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">Comprobantes de Egreso</h1>
                            <p class="text-[10px] text-gray-500">Historial de egresos realizados</p>
                        </div>
                    </div>
                    <Link href="/gestion/egresos/create" class="bg-guindo-600 hover:bg-guindo-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center gap-1">
                        <i class="fas fa-plus text-[10px]"></i> Nuevo Egreso
                    </Link>
                </div>

                <!-- Tabla con sticky headers -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-guindo-50 sticky top-0 z-10">
                                <tr class="bg-guindo-50">
                                    <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700 uppercase">N° Egreso</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700 uppercase">N° Diario</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700 uppercase">Fecha</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700 uppercase">Entregado a</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700 uppercase">Glosa</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-guindo-700 uppercase">Monto</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-guindo-700 uppercase">Estado</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-guindo-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="egreso in egresos.data" :key="egreso.IdEgreso" class="hover:bg-gray-50 text-sm">
                                    <td class="px-4 py-2 text-xs font-mono text-gray-900">{{ egreso.NumeroEgreso }}</td>
                                    <td class="px-4 py-2 text-xs font-mono text-gray-500">{{ egreso.numero_diario }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ egreso.fecha_formateada }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-700 max-w-[150px] truncate">{{ egreso.identificador?.Nombre || '-' }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-600 max-w-[200px] truncate">{{ egreso.Glosa }}</td>
                                    <td class="px-4 py-2 text-xs text-right font-semibold text-guindo-600">{{ Number(egreso.TotalBolivianos).toFixed(2) }} Bs</td>
                                    <td class="px-4 py-2 text-center">
                                        <span v-if="egreso.ActivoInactivo === 1" class="px-1.5 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-0.5 text-[8px]"></i> Contabilizado
                                        </span>
                                        <span v-else class="px-1.5 py-0.5 text-[10px] rounded-full bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-pencil-alt mr-0.5 text-[8px]"></i> Borrador
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right space-x-1">
                                        <Link v-if="egreso.ActivoInactivo === 0" :href="`/gestion/egresos/${egreso.IdEgreso}/edit`" class="text-guindo-600 hover:text-guindo-800 text-xs" title="Editar">
                                            <i class="fas fa-edit text-xs"></i>
                                        </Link>
                                        <a :href="`/gestion/egresos/${egreso.IdEgreso}/pdf`" target="_blank" class="text-red-600 hover:text-red-800 text-xs" title="Ver PDF">
                                            <i class="fas fa-file-pdf text-xs"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr v-if="egresos.data.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-receipt text-2xl mb-1 block"></i>
                                        No hay comprobantes de egreso
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="egresos.links && egresos.links.length > 1" class="px-4 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex justify-between items-center text-xs">
                            <div class="text-gray-500">Mostrando {{ egresos.from || 0 }} a {{ egresos.to || 0 }} de {{ egresos.total || 0 }}</div>
                            <div class="flex gap-0.5">
                                <Link v-for="link in egresos.links" :key="link.label" :href="link.url || '#'" class="px-2 py-0.5 rounded border text-xs" :class="{ 'bg-guindo-600 text-white border-guindo-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sticky {
    position: sticky;
    top: 0;
    z-index: 10;
}

tr td {
    padding: 6px 12px;
}

tr th {
    padding: 8px 12px;
}
</style>