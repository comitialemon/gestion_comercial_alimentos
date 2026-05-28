<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { inject } from 'vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    fechas: Object,
})

// Estado para selección múltiple
const selectedIds = ref([])
const selectAll = ref(false)

// Estado para loading
const updating = ref(false)

// Filtros
const mesFiltro = ref(new Date().getMonth() + 1)
const anioFiltro = ref(new Date().getFullYear())

// Formatear fecha
const formatearFecha = (fechaStr) => {
    if (!fechaStr) return '-'
    const fecha = new Date(fechaStr)
    return fecha.toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

// Estado texto y clases
const estadoTexto = (activo) => {
    return activo === 0 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo === 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
}

const cierreTexto = (cierreSucursal, cierrePermanente) => {
    if (cierrePermanente === 1) return 'Permanente'
    if (cierreSucursal === 1) return 'Sucursal'
    return 'Abierto'
}

const cierreClase = (cierreSucursal, cierrePermanente) => {
    if (cierrePermanente === 1) return 'bg-red-100 text-red-800'
    if (cierreSucursal === 1) return 'bg-amber-100 text-amber-800'
    return 'bg-green-100 text-green-800'
}

// Aplicar filtro
const aplicarFiltro = () => {
    router.get('/gestion/cierre-fechas', {
        mes: mesFiltro.value,
        anio: anioFiltro.value,
    }, {
        preserveState: true,
        replace: true,
    })
}

// Limpiar filtro
const limpiarFiltro = () => {
    mesFiltro.value = new Date().getMonth() + 1
    anioFiltro.value = new Date().getFullYear()
    aplicarFiltro()
}

// Verificar si todos los elementos están seleccionados
const checkSelectAll = () => {
    if (props.fechas.data.length === 0) {
        selectAll.value = false
        return
    }
    selectAll.value = selectedIds.value.length === props.fechas.data.length
}

// Seleccionar/Deseleccionar todos
const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = props.fechas.data.map(f => f.IdFecha)
    } else {
        selectedIds.value = []
    }
    checkSelectAll()
}

// Seleccionar/Deseleccionar un item
const toggleSelect = (id) => {
    const index = selectedIds.value.indexOf(id)
    if (index === -1) {
        selectedIds.value.push(id)
    } else {
        selectedIds.value.splice(index, 1)
    }
    checkSelectAll()
}

// Cambiar estado individual
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

// Cambiar estado múltiple
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

// Obtener nombre del mes
const nombreMes = (mes) => {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
    return meses[mes - 1]
}

// Observar cambios en las fechas (para paginación)
watch(() => props.fechas.data, () => {
    if (selectAll.value) {
        selectedIds.value = props.fechas.data.map(f => f.IdFecha)
    }
}, { deep: true })
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-lock text-primary-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Cierre de Fechas</h1>
                            <p class="text-[11px] text-gray-500">Gestión de cierre de fechas contables</p>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-[10px] font-medium text-gray-700 mb-0.5">Mes</label>
                            <select v-model="mesFiltro" class="border rounded-md px-2 py-1.5 text-xs w-32">
                                <option v-for="m in 12" :key="m" :value="m">{{ nombreMes(m) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium text-gray-700 mb-0.5">Año</label>
                            <select v-model="anioFiltro" class="border rounded-md px-2 py-1.5 text-xs w-24">
                                <option v-for="a in 10" :key="a" :value="new Date().getFullYear() - a + 5">{{ new Date().getFullYear() - a + 5 }}</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button @click="aplicarFiltro" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs flex items-center gap-1 transition">
                                <i class="fas fa-search text-[10px]"></i> Filtrar
                            </button>
                            <button @click="limpiarFiltro" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded-md text-xs flex items-center gap-1 transition">
                                <i class="fas fa-eraser text-[10px]"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Acciones múltiples -->
                <div v-if="selectedIds.length > 0" class="bg-white rounded-lg shadow-sm p-3 mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div class="text-xs text-gray-600">
                        <i class="fas fa-check-circle text-primary-500 mr-1"></i>
                        {{ selectedIds.length }} fecha(s) seleccionada(s)
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button 
                            @click="cambiarEstadoMultiple('ActivoInactivo', true)"
                            class="px-2.5 py-1 bg-green-100 text-green-700 rounded-md text-[11px] hover:bg-green-200 transition"
                        >
                            Activar
                        </button>
                        <button 
                            @click="cambiarEstadoMultiple('ActivoInactivo', false)"
                            class="px-2.5 py-1 bg-red-100 text-red-700 rounded-md text-[11px] hover:bg-red-200 transition"
                        >
                            Desactivar
                        </button>
                        <button 
                            @click="cambiarEstadoMultiple('CierreSucursal', true)"
                            class="px-2.5 py-1 bg-amber-100 text-amber-700 rounded-md text-[11px] hover:bg-amber-200 transition"
                        >
                            Cerrar Sucursal
                        </button>
                        <button 
                            @click="cambiarEstadoMultiple('CierreSucursal', false)"
                            class="px-2.5 py-1 bg-gray-100 text-gray-700 rounded-md text-[11px] hover:bg-gray-200 transition"
                        >
                            Abrir Sucursal
                        </button>
                        <button 
                            @click="cambiarEstadoMultiple('CierrePermanente', true)"
                            class="px-2.5 py-1 bg-red-100 text-red-700 rounded-md text-[11px] hover:bg-red-200 transition"
                        >
                            Cierre Permanente
                        </button>
                        <button 
                            @click="cambiarEstadoMultiple('CierrePermanente', false)"
                            class="px-2.5 py-1 bg-gray-100 text-gray-700 rounded-md text-[11px] hover:bg-gray-200 transition"
                        >
                            Abrir Permanente
                        </button>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 w-8">
                                        <input 
                                            type="checkbox" 
                                            v-model="selectAll"
                                            @change="toggleSelectAll"
                                            class="w-3.5 h-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        >
                                    </th>
                                    <th class="px-3 py-2 text-left text-[11px] font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-center text-[11px] font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-center text-[11px] font-medium text-primary-700 uppercase">Cierre</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="fecha in fechas.data" :key="fecha.IdFecha" class="hover:bg-gray-50">
                                    <td class="px-3 py-2">
                                        <input 
                                            type="checkbox" 
                                            :value="fecha.IdFecha"
                                            v-model="selectedIds"
                                            @change="checkSelectAll"
                                            class="w-3.5 h-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-xs font-mono text-gray-700">{{ formatearFecha(fecha.Fecha) }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="estadoClase(fecha.ActivoInactivo)">
                                                {{ estadoTexto(fecha.ActivoInactivo) }}
                                            </span>
                                            <button 
                                                @click="cambiarEstado(fecha, 'ActivoInactivo', fecha.ActivoInactivo !== 0)"
                                                class="text-[10px] text-gray-400 hover:text-primary-600"
                                                :title="fecha.ActivoInactivo === 0 ? 'Desactivar' : 'Activar'"
                                            >
                                                <i :class="fecha.ActivoInactivo === 0 ? 'fas fa-toggle-on' : 'fas fa-toggle-off'"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="cierreClase(fecha.CierreSucursal, fecha.CierrePermanente)">
                                                {{ cierreTexto(fecha.CierreSucursal, fecha.CierrePermanente) }}
                                            </span>
                                            <button 
                                                v-if="fecha.CierrePermanente !== 1"
                                                @click="cambiarEstado(fecha, 'CierreSucursal', fecha.CierreSucursal !== 1)"
                                                class="text-[10px] text-gray-400 hover:text-amber-600"
                                                :title="fecha.CierreSucursal === 1 ? 'Abrir sucursal' : 'Cerrar sucursal'"
                                            >
                                                <i :class="fecha.CierreSucursal === 1 ? 'fas fa-toggle-on' : 'fas fa-toggle-off'"></i>
                                            </button>
                                            <button 
                                                v-if="fecha.CierrePermanente === 1"
                                                @click="cambiarEstado(fecha, 'CierrePermanente', false)"
                                                class="text-[10px] text-gray-400 hover:text-red-600"
                                                title="Abrir permanente"
                                            >
                                                <i class="fas fa-unlock-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="fechas.data.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-calendar-day text-2xl mb-2 block"></i>
                                        No hay fechas registradas para el período seleccionado
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="fechas.links && fechas.links.length > 1" class="px-4 py-2 border-t border-gray-200">
                        <div class="flex justify-between items-center text-xs">
                            <div class="text-gray-500">Mostrando {{ fechas.from || 0 }} a {{ fechas.to || 0 }} de {{ fechas.total || 0 }}</div>
                            <div class="flex gap-1">
                                <Link v-for="link in fechas.links" :key="link.label" :href="link.url || '#'" class="px-2 py-0.5 rounded border text-xs" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>