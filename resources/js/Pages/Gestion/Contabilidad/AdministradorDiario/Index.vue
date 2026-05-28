<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import axios from 'axios'
import ModalReabrir from './components/ModalReabrir.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    diarios: Object,
    tiposDiario: Array,
    sucursales: Array,
    filtros: Object,
    tieneFiltros: Boolean,
})

// Filtros
const tipoDiario = ref(props.filtros?.tipo_diario || '')
const sucursal = ref(props.filtros?.sucursal || '')
const numeroDiario = ref(props.filtros?.numero_diario || '')
const fecha = ref(props.filtros?.fecha || '')
const fechaDesde = ref(props.filtros?.fecha_desde || '')
const fechaHasta = ref(props.filtros?.fecha_hasta || '')
const tipoBusqueda = ref(props.filtros?.fecha ? 'dia' : (props.filtros?.fecha_desde ? 'rango' : 'dia'))

// Modal
const modalOpen = ref(false)
const diarioSeleccionado = ref(null)

const aplicarFiltros = () => {
    const params = {}
    
    if (tipoDiario.value) params.tipo_diario = tipoDiario.value
    if (sucursal.value) params.sucursal = sucursal.value
    if (numeroDiario.value) params.numero_diario = numeroDiario.value
    
    if (tipoBusqueda.value === 'dia') {
        if (fecha.value) params.fecha = fecha.value
    } else {
        if (fechaDesde.value) params.fecha_desde = fechaDesde.value
        if (fechaHasta.value) params.fecha_hasta = fechaHasta.value
    }
    
    router.get('/gestion/administrador-diario', params, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltros = () => {
    tipoDiario.value = ''
    sucursal.value = ''
    numeroDiario.value = ''
    fecha.value = ''
    fechaDesde.value = ''
    fechaHasta.value = ''
    tipoBusqueda.value = 'dia'
    aplicarFiltros()
}

const abrirModalReabrir = (diario) => {
    diarioSeleccionado.value = diario
    modalOpen.value = true
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    try {
        const date = new Date(fecha)
        if (isNaN(date.getTime())) return '-'
        return date.toLocaleDateString('es-BO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        })
    } catch (e) {
        return '-'
    }
}

const estadoTexto = (contabilizado) => {
    return contabilizado == 1 ? 'Contabilizado' : 'Abierto'
}

const estadoClase = (contabilizado) => {
    return contabilizado == 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-4 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-book-open text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">Administrador de Diarios</h1>
                            <p class="text-xs text-gray-500">Gestión de diarios contabilizados</p>
                        </div>
                    </div>
                </div>

                <!-- Filtros compactos -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Tipo de búsqueda -->
                        <div class="flex items-center gap-2 mr-2">
                            <label class="flex items-center gap-1">
                                <input type="radio" v-model="tipoBusqueda" value="dia" class="w-3.5 h-3.5 text-primary-600"> 
                                <span class="text-xs">Día</span>
                            </label>
                            <label class="flex items-center gap-1">
                                <input type="radio" v-model="tipoBusqueda" value="rango" class="w-3.5 h-3.5 text-primary-600"> 
                                <span class="text-xs">Rango</span>
                            </label>
                        </div>

                        <!-- Fechas -->
                        <div v-if="tipoBusqueda === 'dia'">
                            <input type="date" v-model="fecha" class="w-36 border rounded-md px-2 py-1.5 text-xs">
                        </div>
                        <div v-else class="flex items-center gap-1">
                            <input type="date" v-model="fechaDesde" class="w-36 border rounded-md px-2 py-1.5 text-xs">
                            <span class="text-xs text-gray-500">a</span>
                            <input type="date" v-model="fechaHasta" class="w-36 border rounded-md px-2 py-1.5 text-xs">
                        </div>

                        <!-- Tipo Diario -->
                        <select v-model="tipoDiario" class="w-40 border rounded-md px-2 py-1.5 text-xs">
                            <option value="">Tipo Diario</option>
                            <option v-for="t in tiposDiario" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                        </select>

                        <!-- Sucursal -->
                        <select v-model="sucursal" class="w-44 border rounded-md px-2 py-1.5 text-xs">
                            <option value="">Sucursal</option>
                            <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                        </select>

                        <!-- Número Diario -->
                        <input type="text" v-model="numeroDiario" class="w-28 border rounded-md px-2 py-1.5 text-xs" placeholder="N° Diario">

                        <!-- Botones -->
                        <button @click="aplicarFiltros" class="px-3 py-1.5 text-xs bg-primary-600 text-white rounded-md hover:bg-primary-700 transition">
                            <i class="fas fa-search mr-1 text-[10px]"></i> Buscar
                        </button>
                        <button @click="limpiarFiltros" class="px-3 py-1.5 text-xs text-gray-600 hover:text-gray-800 border rounded-md transition">
                            <i class="fas fa-eraser mr-1 text-[10px]"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Mensaje sin filtros -->
                <div v-if="!tieneFiltros" class="bg-blue-50 rounded-lg p-6 text-center mb-4">
                    <i class="fas fa-calendar-alt text-blue-400 text-xl mb-1 block"></i>
                    <p class="text-xs text-blue-700">Seleccione filtros y presione "Buscar" para ver los diarios</p>
                </div>

                <!-- Tabla -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-primary-700 uppercase">N° Diario</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-primary-700 uppercase">Fecha</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-primary-700 uppercase">Tipo Diario</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-primary-700 uppercase">Sucursal</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-primary-700 uppercase">Operador</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold text-primary-700 uppercase">Estado</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr v-for="diario in diarios.data" :key="diario.IdDiario" class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-2 text-sm font-mono text-primary-700 font-semibold">{{ diario.NumeroDiario }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ formatearFecha(diario.fecha?.Fecha) }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ diario.tipo_diario?.TipoDiario || '-' }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ diario.sucursal?.Nombre || '-' }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ diario.operador_ingreso?.identificador?.Nombre || '-' }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="estadoClase(diario.Contabilizado)">
                                            {{ estadoTexto(diario.Contabilizado) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <button 
                                            @click="abrirModalReabrir(diario)" 
                                            class="text-primary-600 hover:text-primary-800"
                                            title="Reabrir diario"
                                        >
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="diarios.data.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                        <i class="fas fa-book-open text-3xl mb-2 block"></i>
                                        No hay diarios que coincidan con los filtros
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="diarios.links && diarios.links.length > 1" class="px-4 py-3 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ diarios.from || 0 }} a {{ diarios.to || 0 }} de {{ diarios.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link v-for="link in diarios.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border text-sm" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Reabrir -->
        <ModalReabrir 
            v-model="modalOpen"
            :diario="diarioSeleccionado"
            @reabierto="aplicarFiltros"
        />
    </div>
</template>