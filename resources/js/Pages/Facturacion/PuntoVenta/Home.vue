<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    puntos: Array,
    empresaId: Number,
    sucursalId: Number,
})

const getEstadoClass = (activo) => {
    return activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
}
</script>

<template>
    <div class="max-w-6xl mx-auto p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold">Puntos de Venta</h1>
            <Link :href="route('facturacion.puntos-venta.create')" 
                  class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                + Nuevo Punto de Venta
            </Link>
        </div>

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Móvil</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="p in puntos" :key="p.idPuntoVenta" class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">{{ p.codigo }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ p.nombre }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ p.tipo_nombre || '-' }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full" :class="getEstadoClass(p.activo)">
                                {{ p.activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ p.es_movil ? 'Sí' : 'No' }}</td>
                    </tr>
                    <tr v-if="!puntos || puntos.length === 0">
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            No hay puntos de venta creados
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>