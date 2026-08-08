<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    ajustes: Object,
    filtroEstado: String,
    buscar: String
})

const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')
const isMobile = ref(window.innerWidth < 768)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// APLICAR FILTROS
const aplicarFiltros = () => {
    router.get('/gestion/inventario/ajustes', {
        estado: estadoFiltro.value || undefined,
        buscar: buscador.value || undefined
    }, {
        preserveState: true,
        replace: true
    })
}

// BUSCAR (con debounce)
let timeoutBuscador
const buscarAjustes = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

watch(estadoFiltro, () => {
    aplicarFiltros()
})

// ✅ CORREGIDO: Usar fecha_mostrar del controller
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return fecha // Ya viene formateada desde el controller como d/m/Y
}

const getConceptoColor = (concepto) => {
    return concepto === 'INGRESO' ? 'text-emerald-600' : 'text-red-600'
}

const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header Responsive -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Ajustes de Inventario</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">Historial de ajustes contabilizados</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/gestion/inventario/ajustes/create" class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-plus text-[10px]"></i>
                            <span>Nuevo Ajuste</span>
                        </Link>
                    </div>
                </div>

                <!-- Filtros Responsive -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" class="border rounded-md px-2 py-1 text-xs w-36 sm:w-40">
                                <option value="">Todos</option>
                                <option value="activos">Contabilizados</option>
                                <option value="inactivos">Borradores</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarAjustes"
                                placeholder="N° Ajuste..."
                                class="border rounded-md px-2 py-1 text-xs w-28 sm:w-32"
                            >
                            <button 
                                v-if="buscador" 
                                @click="limpiarBusqueda" 
                                class="text-gray-400 hover:text-gray-600 text-xs"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div v-if="buscador" class="mt-2 text-[10px] text-gray-500">
                        <span class="font-semibold">{{ buscador }}</span>
                        <span class="ml-2">({{ ajustes.total || 0 }} resultados)</span>
                    </div>
                </div>

                <!-- Vista MÓVIL (tarjetas) -->
                <div v-if="isMobile" class="space-y-3">
                    <div v-for="ajuste in ajustes.data" :key="ajuste.IdAjustesPrincipal" class="bg-white rounded-lg shadow-sm p-3">
                        <div class="flex justify-between items-start border-b pb-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded">
                                    N° {{ ajuste.NumeroCorrelativo }}
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <Link :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}`" class="text-primary-600" title="Ver">
                                    <i class="fas fa-eye text-xs"></i>
                                </Link>
                                <a :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}/pdf`" target="_blank" class="text-red-600" title="PDF">
                                    <i class="fas fa-file-pdf text-sm"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Fecha:</span>
                                <!-- ✅ FECHA CORREGIDA -->
                                <span class="font-medium">{{ ajuste.fecha_mostrar || '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Concepto:</span>
                                <span class="font-bold" :class="getConceptoColor(ajuste.ConceptoOperacion)">
                                    {{ ajuste.ConceptoOperacion || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Tipo:</span>
                                <span class="font-medium">{{ ajuste.tipo_operacion?.Detalle || '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Almacén:</span>
                                <span class="font-medium">{{ ajuste.almacen?.Almacen || '-' }}</span>
                            </div>
                            <div class="flex justify-end pt-1 border-t mt-1">
                                <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(ajuste.ActivoInactivo)">
                                    <i :class="ajuste.ActivoInactivo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'" class="mr-0.5 text-[8px]"></i>
                                    {{ ajuste.ActivoInactivo === 1 ? 'Contabilizado' : 'Borrador' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="ajustes.data?.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-clipboard-list text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-xs text-gray-400">
                            <span v-if="buscador">No hay ajustes que coincidan con "{{ buscador }}"</span>
                            <span v-else>No hay ajustes registrados</span>
                        </p>
                    </div>
                </div>

                <!-- Vista ESCRITORIO (tabla) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">N° Ajuste</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Concepto</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Almacén</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="ajuste in ajustes.data" :key="ajuste.IdAjustesPrincipal" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">{{ ajuste.NumeroCorrelativo }}</td>
                                    <!-- ✅ FECHA CORREGIDA -->
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ ajuste.fecha_mostrar || '-' }}</td>
                                    <td class="px-3 py-2 text-xs">
                                        <span class="font-bold" :class="getConceptoColor(ajuste.ConceptoOperacion)">
                                            {{ ajuste.ConceptoOperacion || '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-700">{{ ajuste.tipo_operacion?.Detalle || '-' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-700">{{ ajuste.almacen?.Almacen || '-' }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(ajuste.ActivoInactivo)">
                                            <i :class="ajuste.ActivoInactivo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'" class="mr-0.5 text-[8px]"></i>
                                            {{ ajuste.ActivoInactivo === 1 ? 'Contabilizado' : 'Borrador' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right space-x-1 whitespace-nowrap">
                                        <Link :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}`" class="text-primary-600 hover:text-primary-800" title="Ver">
                                            <i class="fas fa-eye text-xs"></i>
                                        </Link>
                                        <a :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}/pdf`" target="_blank" class="text-red-600 hover:text-red-800" title="PDF">
                                            <i class="fas fa-file-pdf text-sm"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr v-if="ajustes.data?.length === 0">
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-clipboard-list text-2xl mb-1 block"></i>
                                        <span v-if="buscador">No hay ajustes que coincidan con "{{ buscador }}"</span>
                                        <span v-else>No hay ajustes registrados</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación desktop -->
                    <div v-if="ajustes.links && ajustes.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-xs">
                            <div class="text-gray-500">Mostrando {{ ajustes.from || 0 }} a {{ ajustes.to || 0 }} de {{ ajustes.total || 0 }}</div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link v-for="link in ajustes.links" :key="link.label" :href="link.url || '#'" class="px-2 py-0.5 rounded border text-xs" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paginación móvil -->
                <div v-if="isMobile && ajustes.links && ajustes.links.length > 1" class="mt-3 bg-white rounded-lg shadow-sm p-2">
                    <div class="flex justify-center gap-0.5 flex-wrap">
                        <Link v-for="link in ajustes.links" :key="link.label" :href="link.url || '#'" class="px-2 py-1 rounded border text-xs min-w-[32px] text-center" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:inline { display: inline; }
    .xs\:block { display: block; }
}
</style>