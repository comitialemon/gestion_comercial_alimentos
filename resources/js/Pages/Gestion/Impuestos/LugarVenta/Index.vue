<!-- resources/js/Pages/Gestion/Impuestos/LugarVenta/Index.vue -->
<script setup>
import { ref, watch, onMounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    lugares: Object,
    filtros: Object,
    empresas: Array,
    sucursales: Array,
    contexto_actual: Object,
})

// Filtros
const clienteId = ref(props.filtros?.cliente_id || '')
const sucursalId = ref(props.filtros?.sucursal_id || '')
const sucursalesFiltro = ref([])

// Cargar sucursales para el filtro
const cargarSucursalesFiltro = async (id) => {
    if (!id) {
        sucursalesFiltro.value = []
        sucursalId.value = ''
        return
    }
    
    try {
        const response = await axios.get(`/gestion/lugar-venta/sucursales/${id}`)
        sucursalesFiltro.value = response.data
        sucursalId.value = ''
    } catch (error) {
        console.error('Error cargando sucursales:', error)
        sucursalesFiltro.value = []
    }
}

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/gestion/lugar-venta', {
        cliente_id: clienteId.value || undefined,
        sucursal_id: sucursalId.value || undefined,
    }, { preserveState: true, replace: true })
}

// Limpiar filtros
const limpiarFiltros = () => {
    clienteId.value = ''
    sucursalId.value = ''
    sucursalesFiltro.value = []
    aplicarFiltros()
}

// Eliminar
const eliminar = (id, nombre) => {
    if (confirm(`¿Eliminar "${nombre}"?`)) {
        router.delete(`/gestion/lugar-venta/${id}`)
    }
}

// Cuando cambia la empresa, cargar sus sucursales
watch(clienteId, (newVal) => {
    cargarSucursalesFiltro(newVal)
})

// Al montar, si hay cliente_id en filtros, cargar sus sucursales
onMounted(() => {
    if (clienteId.value) {
        cargarSucursalesFiltro(clienteId.value)
    }
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-100 rounded-2xl mb-3">
                        <i class="fas fa-store text-xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Lugares de Venta</h1>
                    <p class="text-xs text-gray-500">Administra los puntos de venta físicos o virtuales</p>
                </div>

                <!-- Botón Nuevo -->
                <div class="mb-4 flex justify-end">
                    <Link href="/gestion/lugar-venta/create" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        <i class="fas fa-plus text-sm"></i>
                        Nuevo Lugar de Venta
                    </Link>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Empresa</label>
                            <select v-model="clienteId" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todas las empresas</option>
                                <option v-for="e in empresas" :key="e.id" :value="e.id">
                                    {{ e.nombre }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Sucursal</label>
                            <select 
                                v-model="sucursalId" 
                                class="w-full border rounded-lg px-3 py-2 text-sm" 
                                :disabled="!clienteId || sucursalesFiltro.length === 0"
                            >
                                <option value="">Todas las sucursales</option>
                                <option v-for="s in sucursalesFiltro" :key="s.id" :value="s.id">
                                    {{ s.nombre }} <span v-if="s.numero" class="text-gray-400">(N° {{ s.numero }})</span>
                                </option>
                            </select>
                            <p v-if="clienteId && sucursalesFiltro.length === 0" class="text-xs text-gray-400 mt-1">
                                Esta empresa no tiene sucursales
                            </p>
                        </div>
                        <div class="flex items-end gap-2">
                            <button @click="aplicarFiltros" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">
                                <i class="fas fa-search mr-1"></i> Filtrar
                            </button>
                            <button @click="limpiarFiltros" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm">
                                <i class="fas fa-eraser mr-1"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orden</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lugar</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sucursal</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="lugar in lugares.data" :key="lugar.IdLugar" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ lugar.Orden }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <i class="fas fa-location-dot text-gray-400 mr-2"></i>
                                        {{ lugar.Lugar }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ lugar.cliente?.Nombre || '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ lugar.sucursal?.Nombre || '-' }}
                                        <span v-if="lugar.sucursal?.NumeroSucursal" class="text-xs text-gray-400 ml-1">
                                            (N° {{ lugar.sucursal.NumeroSucursal }})
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link 
                                            :href="`/gestion/lugar-venta/${lugar.IdLugar}/edit`" 
                                            class="text-indigo-600 hover:text-indigo-900 mr-3"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </Link>
                                        <button 
                                            @click="eliminar(lugar.IdLugar, lugar.Lugar)" 
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="lugares.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        No hay lugares de venta registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="lugares.links && lugares.links.length > 1" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ lugares.from || 0 }} a {{ lugares.to || 0 }} de {{ lugares.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link 
                                    v-for="link in lugares.links" 
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    class="px-3 py-1 rounded border text-sm"
                                    :class="{
                                        'bg-indigo-600 text-white border-indigo-600': link.active,
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