<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: Array,
    empresaId: Number,
    flash: Object,
})
</script>

<template>
    <div class="max-w-4xl mx-auto p-6">
        <h1 class="text-2xl font-semibold mb-6">Sucursales</h1>

        <div v-if="flash?.success" class="mb-4 p-3 rounded bg-green-50 text-green-800">
            {{ flash.success }}
        </div>
        <div v-if="flash?.error" class="mb-4 p-3 rounded bg-red-50 text-red-800">
            {{ flash.error }}
        </div>

        <div class="grid sm:grid-cols-2 gap-6">
            <Link :href="route('facturacion.sucursales.create')" class="block rounded-2xl p-6 bg-white shadow hover:shadow-lg">
                <h2 class="text-lg font-medium mb-2">Crear sucursal desde cero</h2>
                <p class="text-sm text-gray-600">
                    Registra la sucursal en Gestión y en Facturación, creando el mapeo automáticamente.
                </p>
            </Link>

            <Link :href="route('facturacion.importar.sucursales.index')" class="block rounded-2xl p-6 bg-white shadow hover:shadow-lg">
                <h2 class="text-lg font-medium mb-2">Importar sucursal existente</h2>
                <p class="text-sm text-gray-600">
                    Selecciona una sucursal ya existente en Gestión y la vincula con Facturación.
                </p>
            </Link>
        </div>

        <!-- Tabla de sucursales existentes -->
        <div v-if="sucursales.length > 0" class="mt-8">
            <h2 class="text-lg font-semibold mb-3">Sucursales en Facturación</h2>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left">Código</th>
                            <th class="px-4 py-2 text-left">Nombre</th>
                            <th class="px-4 py-2 text-left">Dirección</th>
                            <th class="px-4 py-2 text-left">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in sucursales" :key="s.idSucursal" class="border-b">
                            <td class="px-4 py-2">{{ s.codigo }}</td>
                            <td class="px-4 py-2">{{ s.nombre }}</td>
                            <td class="px-4 py-2">{{ s.direccion || '-' }}</td>
                            <td class="px-4 py-2">
                                <span :class="s.activo ? 'text-green-600' : 'text-red-600'">
                                    {{ s.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>