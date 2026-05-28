<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    egresos: Object,
    filtroEstado: String,
    buscar: String
})

const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')
const isMobile = ref(window.innerWidth < 768)

// Detectar cambios de tamaño de pantalla
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// 🔥 Abrir PDF del diario (al hacer clic en el número de diario)
const abrirPdfDiario = (egreso) => {
    if (egreso.IdDiario && egreso.IdDiario > 0) {
        // Construir URL del PDF del diario
        window.open(`/gestion/imprimir-diario/pdf/${egreso.IdDiario}`, '_blank')
    } else {
        // Mostrar mensaje si no tiene diario
        const toast = document.createElement('div')
        toast.className = 'fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white bg-yellow-500 flex items-center gap-2'
        toast.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Este egreso no tiene un diario asociado'
        document.body.appendChild(toast)
        setTimeout(() => toast.remove(), 2000)
    }
}

// APLICAR FILTROS
const aplicarFiltros = () => {
    router.get('/gestion/egresos', {
        estado: estadoFiltro.value || undefined,
        buscar: buscador.value || undefined
    }, {
        preserveState: true,
        replace: true
    })
}

// BUSCAR (con debounce)
let timeoutBuscador
const buscarEgresos = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

// Limpiar búsqueda
const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

// Watch para filtro de estado
watch(estadoFiltro, () => {
    aplicarFiltros()
})

// Formatear moneda
const formatearMonto = (monto) => {
    return Number(monto).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
}

const getEstadoIcono = (activo) => {
    return activo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Contabilizado' : 'Borrador'
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
                            <i class="fas fa-money-bill-wave text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Comprobantes de Egreso</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">Historial de egresos realizados</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/gestion/egresos/create" class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-plus text-[10px]"></i>
                            <span>Nuevo Egreso</span>
                        </Link>
                    </div>
                </div>

                <!-- Filtros Responsive - Compactos -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Selector de estado -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" class="border rounded-md px-2 py-1 text-xs w-36 sm:w-40">
                                <option value="">Todos</option>
                                <option value="activos">Contabilizados</option>
                                <option value="inactivos">Borradores</option>
                            </select>
                        </div>
                        
                        <!-- BUSCADOR pequeño -->
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarEgresos"
                                placeholder="N° Egreso..."
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
                    
                    <!-- Resultados de búsqueda -->
                    <div v-if="buscador" class="mt-2 text-[10px] text-gray-500">
                        <span class="font-semibold">{{ buscador }}</span>
                        <span class="ml-2">({{ egresos.total || 0 }} resultados)</span>
                    </div>
                </div>

                <!-- Vista para MÓVIL (tarjetas) -->
                <div v-if="isMobile" class="space-y-3">
                    <div v-for="egreso in egresos.data" :key="egreso.IdEgreso" class="bg-white rounded-lg shadow-sm p-3">
                        <!-- Cabecera de tarjeta -->
                        <div class="flex justify-between items-start border-b pb-2 mb-2">
                            <div class="flex flex-col gap-1">
                                <span class="text-xs font-mono font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded self-start">
                                    N° {{ egreso.NumeroEgreso }}
                                </span>
                                <!-- 🔥 Número de Diario CLICKEABLE -->
                                <span 
                                    @click="abrirPdfDiario(egreso)"
                                    class="text-xs font-mono text-blue-600 cursor-pointer hover:text-blue-800 hover:underline"
                                    :class="{ 'opacity-50 cursor-not-allowed': !egreso.IdDiario || egreso.IdDiario === 0 }"
                                >
                                    <i class="fas fa-book-open mr-1 text-[10px]"></i>
                                    Diario: {{ egreso.numero_diario || 'Sin diario' }}
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <Link v-if="egreso.ActivoInactivo === 0" :href="`/gestion/egresos/${egreso.IdEgreso}/edit`" class="text-primary-600" title="Editar">
                                    <i class="fas fa-edit text-xs"></i>
                                </Link>
                                <a :href="`/gestion/egresos/${egreso.IdEgreso}/pdf`" target="_blank" class="text-red-600" title="PDF">
                                    <i class="fas fa-file-pdf text-sm"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Datos principales -->
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Fecha:</span>
                                <span class="font-medium">{{ egreso.fecha_formateada || '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Entregado a:</span>
                                <span class="font-medium truncate max-w-[180px]" :title="egreso.identificador?.Nombre">
                                    {{ egreso.identificador?.Nombre || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Glosa:</span>
                                <span class="font-medium truncate max-w-[180px]" :title="egreso.Glosa">
                                    {{ egreso.Glosa || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Monto:</span>
                                <span class="font-bold text-primary-600">{{ formatearMonto(egreso.TotalBolivianos) }} Bs</span>
                            </div>
                            <div class="flex justify-end pt-1 border-t mt-1">
                                <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(egreso.ActivoInactivo)">
                                    <i :class="getEstadoIcono(egreso.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                    {{ getEstadoTexto(egreso.ActivoInactivo) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mensaje sin resultados móvil -->
                    <div v-if="egresos.data?.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-receipt text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-xs text-gray-400">
                            <span v-if="buscador">No hay egresos que coincidan con "{{ buscador }}"</span>
                            <span v-else>No hay comprobantes de egreso</span>
                        </p>
                    </div>
                </div>

                <!-- Vista para TABLET Y ESCRITORIO (tabla) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">N° Egreso</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">N° Diario</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Entregado a</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Glosa</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Monto</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="egreso in egresos.data" :key="egreso.IdEgreso" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">{{ egreso.NumeroEgreso }}</td>
                                    <!-- 🔥 Número de Diario CLICKEABLE -->
                                    <td class="px-3 py-2 text-xs">
                                        <span 
                                            @click="abrirPdfDiario(egreso)"
                                            class="font-mono text-blue-600 cursor-pointer hover:text-blue-800 hover:underline inline-flex items-center gap-1"
                                            :class="{ 'opacity-50 cursor-not-allowed': !egreso.IdDiario || egreso.IdDiario === 0 }"
                                        >
                                            <i class="fas fa-book-open text-[10px]"></i>
                                            {{ egreso.numero_diario || '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ egreso.fecha_formateada || '-' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-700 max-w-[150px] truncate" :title="egreso.identificador?.Nombre">
                                        {{ egreso.identificador?.Nombre || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600 max-w-[200px] truncate" :title="egreso.Glosa">
                                        {{ egreso.Glosa || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-right font-semibold text-primary-600">{{ formatearMonto(egreso.TotalBolivianos) }} Bs</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(egreso.ActivoInactivo)">
                                            <i :class="getEstadoIcono(egreso.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                            {{ getEstadoTexto(egreso.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right space-x-1 whitespace-nowrap">
                                        <Link v-if="egreso.ActivoInactivo === 0" :href="`/gestion/egresos/${egreso.IdEgreso}/edit`" class="text-primary-600 hover:text-primary-800 text-xs inline-block" title="Editar">
                                            <i class="fas fa-edit text-xs"></i>
                                        </Link>
                                        <a :href="`/gestion/egresos/${egreso.IdEgreso}/pdf`" target="_blank" class="text-red-600 hover:text-red-800 text-xs inline-block" title="Ver PDF">
                                            <i class="fas fa-file-pdf text-sm"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr v-if="egresos.data?.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-receipt text-2xl mb-1 block"></i>
                                        <span v-if="buscador">No hay egresos que coincidan con "{{ buscador }}"</span>
                                        <span v-else>No hay comprobantes de egreso</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación desktop -->
                    <div v-if="egresos.links && egresos.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-xs">
                            <div class="text-gray-500">Mostrando {{ egresos.from || 0 }} a {{ egresos.to || 0 }} de {{ egresos.total || 0 }}</div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link v-for="link in egresos.links" :key="link.label" :href="link.url || '#'" class="px-2 py-0.5 rounded border text-xs" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paginación móvil -->
                <div v-if="isMobile && egresos.links && egresos.links.length > 1" class="mt-3 bg-white rounded-lg shadow-sm p-2">
                    <div class="flex justify-center gap-0.5 flex-wrap">
                        <Link v-for="link in egresos.links" :key="link.label" :href="link.url || '#'" class="px-2 py-1 rounded border text-xs min-w-[32px] text-center" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:inline {
        display: inline;
    }
    .xs\:block {
        display: block;
    }
}
</style>