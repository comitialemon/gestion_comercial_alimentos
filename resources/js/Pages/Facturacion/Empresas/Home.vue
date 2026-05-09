<!-- resources/js/Pages/Facturacion/Empresas/Home.vue -->
<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresas: Array,
    flash: Object,
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-100 rounded-2xl mb-3">
                        <i class="fas fa-building text-xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Empresas Facturación</h1>
                    <p class="text-xs text-gray-500 mt-1">Gestión de empresas y mapeo con gestión comercial</p>
                </div>

                <!-- Flash Messages -->
                <div v-if="flash?.success" class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg">
                    ✅ {{ flash.success }}
                </div>
                <div v-if="flash?.error" class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg">
                    ❌ {{ flash.error }}
                </div>

                <div class="grid sm:grid-cols-2 gap-6">
                    <Link href="/facturacion/empresas/crear" class="block rounded-2xl p-6 bg-white shadow hover:shadow-lg transition">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-plus text-emerald-600"></i>
                            </div>
                            <h2 class="text-lg font-medium">Crear empresa desde cero</h2>
                        </div>
                        <p class="text-sm text-gray-600">
                            Registra la empresa en Gestión (todos_cliente) y en Facturación (empresa), y genera el mapeo automáticamente.
                        </p>
                    </Link>

                    <Link href="/facturacion/empresas/importar" class="block rounded-2xl p-6 bg-white shadow hover:shadow-lg transition">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-upload text-blue-600"></i>
                            </div>
                            <h2 class="text-lg font-medium">Importar empresa desde gestión</h2>
                        </div>
                        <p class="text-sm text-gray-600">
                            Elige base, selecciona un cliente existente y complétalo con modalidad/ambiente, token y código de sistema.
                        </p>
                    </Link>
                </div>

                <!-- Tabla de empresas existentes -->
                <div class="mt-8 bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b bg-gray-50">
                        <h3 class="font-semibold text-gray-900">Empresas registradas</h3>
                        <p class="text-xs text-gray-500">Empresas sincronizadas con facturación</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIT</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ambiente</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modalidad</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="emp in empresas" :key="emp.idEmpresa" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ emp.idEmpresa }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ emp.nombre }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ emp.nit }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="emp.ambiente === 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                                            {{ emp.ambiente === 1 ? 'Producción' : 'Pruebas y Piloto' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                            {{ emp.modalidad === 1 ? 'Electrónica' : 'Computarizada' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="empresas.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        No hay empresas registradas
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>