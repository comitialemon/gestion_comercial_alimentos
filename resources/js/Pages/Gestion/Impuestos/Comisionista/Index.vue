<!-- resources/js/Pages/Gestion/Impuestos/Comisionista/Index.vue -->
<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    comisionistas: Object,
    filtros: Object,
    empresas: Array,
    contexto_actual: Object,
})

// Filtros
const clienteId = ref(props.filtros?.cliente_id || '')
const search = ref(props.filtros?.search || '')

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/gestion/comisionista', {
        cliente_id: clienteId.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true })
}

// Limpiar filtros
const limpiarFiltros = () => {
    clienteId.value = ''
    search.value = ''
    aplicarFiltros()
}

// Eliminar
const eliminar = (id, nombre) => {
    if (confirm(`¿Eliminar al comisionista "${nombre}"?`)) {
        router.delete(`/gestion/comisionista/${id}`)
    }
}

// Debounce para búsqueda
let timeout
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        aplicarFiltros()
    }, 500)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-100 rounded-2xl mb-3">
                        <i class="fas fa-user-tie text-xl text-emerald-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Comisionistas</h1>
                    <p class="text-xs text-gray-500">Administra los vendedores o comisionistas</p>
                </div>

                <!-- Botón Nuevo -->
                <div class="mb-4 flex justify-end">
                    <Link href="/gestion/comisionista/create" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                        <i class="fas fa-plus text-sm"></i>
                        Nuevo Comisionista
                    </Link>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Empresa</label>
                            <select v-model="clienteId" @change="aplicarFiltros" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todas las empresas</option>
                                <option v-for="e in empresas" :key="e.id" :value="e.id">
                                    {{ e.nombre }}
                                </option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                            <input 
                                type="text" 
                                v-model="search" 
                                placeholder="Buscar por nombre o CI..." 
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CI/NIT</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comisión</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in comisionistas.data" :key="item.IdComisionista" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ item.IdComisionista }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                        {{ item.identificador?.CI_NIT || '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <i class="fas fa-user text-gray-400 mr-2"></i>
                                        {{ item.identificador?.Nombre || '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            {{ item.Comision }}%
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ item.cliente?.Nombre || '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link 
                                            :href="`/gestion/comisionista/${item.IdComisionista}/edit`" 
                                            class="text-indigo-600 hover:text-indigo-900 mr-3"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </Link>
                                        <button 
                                            @click="eliminar(item.IdComisionista, item.identificador?.Nombre)" 
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="comisionistas.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        No hay comisionistas registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="comisionistas.links && comisionistas.links.length > 1" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ comisionistas.from || 0 }} a {{ comisionistas.to || 0 }} de {{ comisionistas.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link 
                                    v-for="link in comisionistas.links" 
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    class="px-3 py-1 rounded border text-sm"
                                    :class="{
                                        'bg-emerald-600 text-white border-emerald-600': link.active,
                                        'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url,
                                        'opacity-50 cursor-not-allowed': !link.url
                                    }"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>