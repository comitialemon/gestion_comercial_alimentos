<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import axios from 'axios'
import { inject } from 'vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    fechas: Object,
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const selectedIds = ref([])
const selectAll = ref(false)
const updating = ref(false)

// Filtros
const mesFiltro = ref(new Date().getMonth() + 1)
const anioFiltro = ref(new Date().getFullYear())

// ==================== COMPUTED ====================
const haySeleccionados = computed(() => selectedIds.value.length > 0)
const totalSeleccionados = computed(() => selectedIds.value.length)

// ==================== FUNCIONES ====================
// 🔥 FUNCIÓN CORREGIDA - SIN new Date()
const formatearFecha = (fechaStr) => {
    if (!fechaStr) return '-'
    // La fecha viene como "YYYY-MM-DD" del backend
    const partes = fechaStr.split('-')
    if (partes.length === 3) {
        return `${partes[2]}/${partes[1]}/${partes[0]}`
    }
    return fechaStr
}

const estadoTexto = (activo) => {
    return activo === 0 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
}

const cierreTexto = (cierreSucursal, cierrePermanente) => {
    if (cierrePermanente === 1) return 'Permanente'
    if (cierreSucursal === 1) return 'Sucursal'
    return 'Abierto'
}

const cierreClase = (cierreSucursal, cierrePermanente) => {
    if (cierrePermanente === 1) return 'bg-red-100 text-red-700'
    if (cierreSucursal === 1) return 'bg-orange-100 text-orange-700'
    return 'bg-emerald-100 text-emerald-700'
}

const aplicarFiltro = () => {
    router.get('/gestion/cierre-fechas', {
        mes: mesFiltro.value,
        anio: anioFiltro.value,
    }, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltro = () => {
    mesFiltro.value = new Date().getMonth() + 1
    anioFiltro.value = new Date().getFullYear()
    aplicarFiltro()
}

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = props.fechas.data.map(f => f.IdFecha)
    } else {
        selectedIds.value = []
    }
}

const toggleSelect = (id) => {
    const index = selectedIds.value.indexOf(id)
    if (index === -1) {
        selectedIds.value.push(id)
    } else {
        selectedIds.value.splice(index, 1)
    }
    selectAll.value = selectedIds.value.length === props.fechas.data.length && props.fechas.data.length > 0
}

watch(() => props.fechas.data, () => {
    if (selectAll.value) {
        selectedIds.value = props.fechas.data.map(f => f.IdFecha)
    }
    selectAll.value = selectedIds.value.length === props.fechas.data.length && props.fechas.data.length > 0
}, { deep: true })

const cambiarEstado = async (fecha, campo, valor) => {
    try {
        const data = {
            ActivoInactivo: campo === 'ActivoInactivo' ? valor : fecha.ActivoInactivo === 0,
            CierreSucursal: campo === 'CierreSucursal' ? valor : fecha.CierreSucursal === 1,
            CierrePermanente: campo === 'CierrePermanente' ? valor : fecha.CierrePermanente === 1,
        }
        
        const response = await axios.put(`/gestion/cierre-fechas/${fecha.IdFecha}`, data)
        
        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            router.reload({ preserveScroll: true })
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al actualizar')
    }
}

const cambiarEstadoMultiple = async (campo, valor) => {
    if (selectedIds.value.length === 0) {
        toast?.warning('Atención', 'Seleccione al menos una fecha')
        return
    }
    
    const campoNombre = {
        ActivoInactivo: 'estado',
        CierreSucursal: 'cierre de sucursal',
        CierrePermanente: 'cierre permanente'
    }[campo]
    
    const valorTexto = valor ? (campo === 'ActivoInactivo' ? 'Activar' : 'Marcar') : (campo === 'ActivoInactivo' ? 'Desactivar' : 'Desmarcar')
    
    if (!confirm(`¿${valorTexto} ${campoNombre} para ${selectedIds.value.length} fecha(s)?`)) {
        return
    }
    
    updating.value = true
    
    try {
        const response = await axios.post('/gestion/cierre-fechas/update-multiple', {
            ids: selectedIds.value,
            campo: campo,
            valor: valor
        })
        
        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            selectedIds.value = []
            selectAll.value = false
            router.reload({ preserveScroll: true })
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al actualizar')
    } finally {
        updating.value = false
    }
}

const nombreMes = (mes) => {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
    return meses[mes - 1]
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-lock text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Cierre de Fechas</h1>
                        <p class="text-[10px] text-gray-500">Gestión de cierre de fechas contables</p>
                    </div>
                </div>

                <!-- ==================== FILTROS COMPACTOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Mes</label>
                            <select v-model="mesFiltro" class="w-28 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500">
                                <option v-for="m in 12" :key="m" :value="m">{{ nombreMes(m) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Año</label>
                            <select v-model="anioFiltro" class="w-20 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500">
                                <option v-for="a in 10" :key="a" :value="new Date().getFullYear() - a + 5">{{ new Date().getFullYear() - a + 5 }}</option>
                            </select>
                        </div>
                        <div class="flex gap-1.5">
                            <button @click="aplicarFiltro" class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1">
                                <i class="fas fa-search text-[10px]"></i> Filtrar
                            </button>
                            <button @click="limpiarFiltro" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-eraser text-[10px]"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== ACCIONES MÚLTIPLES ==================== -->
                <div v-if="haySeleccionados" class="bg-white rounded-xl shadow-sm p-3 mb-4 border border-primary-200">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-xs text-gray-600">
                            <i class="fas fa-check-circle text-primary-500 mr-1"></i>
                            {{ totalSeleccionados }} fecha(s) seleccionada(s)
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button @click="cambiarEstadoMultiple('ActivoInactivo', true)"
                                class="px-2.5 py-1 text-[10px] rounded-md bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition">
                                Activar
                            </button>
                            <button @click="cambiarEstadoMultiple('ActivoInactivo', false)"
                                class="px-2.5 py-1 text-[10px] rounded-md bg-red-100 text-red-700 hover:bg-red-200 transition">
                                Desactivar
                            </button>
                            <button @click="cambiarEstadoMultiple('CierreSucursal', true)"
                                class="px-2.5 py-1 text-[10px] rounded-md bg-orange-100 text-orange-700 hover:bg-orange-200 transition">
                                Cerrar Sucursal
                            </button>
                            <button @click="cambiarEstadoMultiple('CierreSucursal', false)"
                                class="px-2.5 py-1 text-[10px] rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                                Abrir Sucursal
                            </button>
                            <button @click="cambiarEstadoMultiple('CierrePermanente', true)"
                                class="px-2.5 py-1 text-[10px] rounded-md bg-red-100 text-red-700 hover:bg-red-200 transition">
                                Cierre Permanente
                            </button>
                            <button @click="cambiarEstadoMultiple('CierrePermanente', false)"
                                class="px-2.5 py-1 text-[10px] rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                                Abrir Permanente
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== TABLA CON STICKY HEADER ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 65vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="fecha in fechas.data" :key="fecha.IdFecha" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex items-start gap-2">
                                    <input 
                                        type="checkbox" 
                                        :value="fecha.IdFecha"
                                        v-model="selectedIds"
                                        @change="selectAll = selectedIds.length === fechas.data.length && fechas.data.length > 0"
                                        class="w-3.5 h-3.5 mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    >
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-mono text-gray-700">{{ formatearFecha(fecha.Fecha) }}</p>
                                        <div class="flex flex-wrap items-center gap-2 mt-1">
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(fecha.ActivoInactivo)">
                                                {{ estadoTexto(fecha.ActivoInactivo) }}
                                            </span>
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="cierreClase(fecha.CierreSucursal, fecha.CierrePermanente)">
                                                {{ cierreTexto(fecha.CierreSucursal, fecha.CierrePermanente) }}
                                            </span>
                                        </div>
                                        <div class="flex gap-1 mt-1.5 pt-1.5 border-t border-gray-200">
                                            <button @click="cambiarEstado(fecha, 'ActivoInactivo', fecha.ActivoInactivo !== 0)"
                                                class="px-2 py-0.5 text-[8px] rounded bg-gray-100 text-gray-600 hover:bg-primary-100 transition flex items-center gap-0.5"
                                                :title="fecha.ActivoInactivo === 0 ? 'Desactivar' : 'Activar'">
                                                <i :class="fecha.ActivoInactivo === 0 ? 'fas fa-toggle-on' : 'fas fa-toggle-off'"></i>
                                                {{ fecha.ActivoInactivo === 0 ? 'Desactivar' : 'Activar' }}
                                            </button>
                                            <button v-if="fecha.CierrePermanente !== 1"
                                                @click="cambiarEstado(fecha, 'CierreSucursal', fecha.CierreSucursal !== 1)"
                                                class="px-2 py-0.5 text-[8px] rounded bg-gray-100 text-gray-600 hover:bg-orange-100 transition flex items-center gap-0.5"
                                                :title="fecha.CierreSucursal === 1 ? 'Abrir sucursal' : 'Cerrar sucursal'">
                                                <i :class="fecha.CierreSucursal === 1 ? 'fas fa-toggle-on' : 'fas fa-toggle-off'"></i>
                                                {{ fecha.CierreSucursal === 1 ? 'Abrir Suc.' : 'Cerrar Suc.' }}
                                            </button>
                                            <button v-if="fecha.CierrePermanente === 1"
                                                @click="cambiarEstado(fecha, 'CierrePermanente', false)"
                                                class="px-2 py-0.5 text-[8px] rounded bg-red-50 text-red-600 hover:bg-red-100 transition flex items-center gap-0.5">
                                                <i class="fas fa-unlock-alt"></i> Abrir Perm.
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="fechas.data.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-calendar-day text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay fechas registradas para el período seleccionado</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO (tabla con STICKY HEADER) -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50 sticky top-0 z-10 shadow-sm" style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th class="px-3 py-1.5 w-8">
                                        <input 
                                            type="checkbox" 
                                            v-model="selectAll"
                                            @change="toggleSelectAll"
                                            class="w-3.5 h-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        >
                                    </th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase">Cierre</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="fecha in fechas.data" :key="fecha.IdFecha" class="hover:bg-gray-50">
                                    <td class="px-3 py-1.5">
                                        <input 
                                            type="checkbox" 
                                            :value="fecha.IdFecha"
                                            v-model="selectedIds"
                                            @change="selectAll = selectedIds.length === fechas.data.length && fechas.data.length > 0"
                                            class="w-3.5 h-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        >
                                    </td>
                                    <td class="px-3 py-1.5 text-xs font-mono text-gray-700">{{ formatearFecha(fecha.Fecha) }}</td>
                                    <td class="px-3 py-1.5 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(fecha.ActivoInactivo)">
                                                {{ estadoTexto(fecha.ActivoInactivo) }}
                                            </span>
                                            <button 
                                                @click="cambiarEstado(fecha, 'ActivoInactivo', fecha.ActivoInactivo !== 0)"
                                                class="text-[10px] text-gray-400 hover:text-primary-600 transition"
                                                :title="fecha.ActivoInactivo === 0 ? 'Desactivar' : 'Activar'"
                                            >
                                                <i :class="fecha.ActivoInactivo === 0 ? 'fas fa-toggle-on' : 'fas fa-toggle-off'"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-1.5 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="cierreClase(fecha.CierreSucursal, fecha.CierrePermanente)">
                                                {{ cierreTexto(fecha.CierreSucursal, fecha.CierrePermanente) }}
                                            </span>
                                            <button 
                                                v-if="fecha.CierrePermanente !== 1"
                                                @click="cambiarEstado(fecha, 'CierreSucursal', fecha.CierreSucursal !== 1)"
                                                class="text-[10px] text-gray-400 hover:text-orange-600 transition"
                                                :title="fecha.CierreSucursal === 1 ? 'Abrir sucursal' : 'Cerrar sucursal'"
                                            >
                                                <i :class="fecha.CierreSucursal === 1 ? 'fas fa-toggle-on' : 'fas fa-toggle-off'"></i>
                                            </button>
                                            <button 
                                                v-if="fecha.CierrePermanente === 1"
                                                @click="cambiarEstado(fecha, 'CierrePermanente', false)"
                                                class="text-[10px] text-gray-400 hover:text-red-600 transition"
                                                title="Abrir permanente"
                                            >
                                                <i class="fas fa-unlock-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="fechas.data.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-calendar-day text-2xl mb-1 block"></i>
                                        No hay fechas registradas para el período seleccionado
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ==================== PAGINACIÓN ==================== -->
                    <div v-if="fechas.links && fechas.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <div class="text-[10px] text-gray-500">
                                Mostrando {{ fechas.from || 0 }} a {{ fechas.to || 0 }} de {{ fechas.total || 0 }}
                            </div>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link v-for="link in fechas.links" :key="link.label" :href="link.url || '#'" 
                                    class="px-2.5 py-1 rounded-lg border text-[10px] transition"
                                    :class="{ 
                                        'bg-primary-600 text-white border-primary-600': link.active, 
                                        'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url, 
                                        'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url 
                                    }" 
                                    v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

/* Scrollbar personalizada */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>