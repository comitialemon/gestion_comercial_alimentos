<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    columnas: Array,
    filas: Array,
    totalesPorColumna: Object,
    totalGeneral: Number,
    sucursales: Array,
    fechaInicial: String,
    fechaFinal: String,
    sucursalSeleccionada: Number,
    sucursalNombreInicial: String,
    search: String,
    soloConMovimiento: Boolean,
})

// ==================== RESPONSIVE ====================
const isMobile = ref(false)
const handleResize = () => { isMobile.value = window.innerWidth < 640 }

// ==================== ESTADO ====================
const sucursalId = ref(props.sucursalSeleccionada || '')
const mostrarLista = ref(false)
const fechaInicial = ref(props.fechaInicial || '')
const fechaFinal = ref(props.fechaFinal || '')
const search = ref(props.search || '')

// 🔥 Precargar el nombre de la sucursal desde el backend
const sucursalSearch = ref(props.sucursalNombreInicial || '')

const soloConMovimiento = ref(props.soloConMovimiento ?? false)

const haySucursalSeleccionada = computed(() => Number(sucursalId.value) > 0)

const sucursalesFiltradas = computed(() => {
    if (!sucursalSearch.value) return props.sucursales || []
    const t = sucursalSearch.value.toLowerCase()
    return (props.sucursales || []).filter(s =>
        s.nombre.toLowerCase().includes(t) || s.numero?.toString().includes(t)
    )
})

// ==================== FUNCIONES ====================
const cargarSucursalNombre = () => {
    if (sucursalId.value && props.sucursales) {
        const e = props.sucursales.find(s => s.id === Number(sucursalId.value))
        if (e) sucursalSearch.value = e.nombre
    }
}

const aplicarFiltros = () => {
    if (!haySucursalSeleccionada.value) return
    router.get('/gestion/inventario/reporte-productos-base-por-combo', {
        sucursal_id: sucursalId.value || undefined,
        fecha_inicial: fechaInicial.value,
        fecha_final: fechaFinal.value,
        search: search.value || undefined,
        solo_con_movimiento: soloConMovimiento.value ? 1 : 0,
    }, { preserveState: true, replace: true })
}

const limpiarFiltros = () => {
    sucursalId.value = ''
    sucursalSearch.value = ''
    fechaInicial.value = new Date().toISOString().slice(0, 10)
    fechaFinal.value = new Date().toISOString().slice(0, 10)
    search.value = ''
    router.get('/gestion/inventario/reporte-productos-base-por-combo', {}, { preserveState: true, replace: true })
}

const seleccionarSucursal = (suc) => {
    sucursalId.value = suc.id
    sucursalSearch.value = suc.nombre
    mostrarLista.value = false
    aplicarFiltros()
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalSearch.value = ''
    mostrarLista.value = false
    router.get('/gestion/inventario/reporte-productos-base-por-combo', {}, { preserveState: true, replace: true })
}

const formatNumber = (num) => {
    if (num === undefined || num === null || num === 0) return '-'
    return Number(num).toFixed(2).replace(/\.00$/, '')
}

const getValor = (fila, colId) => fila.valores?.[colId] ?? 0

const exportarExcel = () => {
    if (!haySucursalSeleccionada.value) return

    const params = new URLSearchParams({
        sucursal_id: sucursalId.value,
        fecha_inicial: fechaInicial.value,
        fecha_final: fechaFinal.value,
        solo_con_movimiento: soloConMovimiento.value ? '1' : '0',
    })

    if (search.value) params.append('search', search.value)

    window.open(
        `/gestion/inventario/reporte-productos-base-por-combo/exportar?${params.toString()}`,
        '_blank'
    )
}

// Debounce search
let timeout
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        if (haySucursalSeleccionada.value) aplicarFiltros()
    }, 500)
})

// 🔥 Si cambia la sucursal desde el backend, actualizar el nombre en el input
watch(() => props.sucursalNombreInicial, (newVal) => {
    if (newVal) {
        sucursalSearch.value = newVal
    }
})

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    if (sucursalId.value && !sucursalSearch.value) {
        cargarSucursalNombre()
    }
})
onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    if (timeout) clearTimeout(timeout)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- HEADER -->
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-table-cells text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Productos Base por Combo</h1>
                            <p class="text-xs text-gray-500">Salidas reales por venta según inventario</p>
                        </div>
                    </div>
                    <button @click="exportarExcel" :disabled="!haySucursalSeleccionada"
                        class="px-3 py-1.5 bg-emerald-600 text-white rounded-md text-xs font-medium hover:bg-emerald-700 transition flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-file-excel text-[10px]"></i> Exportar Excel
                    </button>
                </div>

                <!-- FILTROS -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="relative flex-1 min-w-[160px] max-w-[220px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Sucursal *</label>
                            <div class="relative">
                                <input type="text" v-model="sucursalSearch"
                                    @focus="mostrarLista = true"
                                    @input="mostrarLista = true"
                                    placeholder="Seleccione..."
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-6 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    autocomplete="off" />
                                <button v-if="sucursalId" @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                            <span v-if="sucursalId" class="text-[9px] text-primary-600 font-medium">
                                <i class="fas fa-check-circle"></i> {{ sucursalSearch }}
                            </span>
                            <span v-else class="text-[9px] text-gray-400">
                                <i class="fas fa-store"></i> Ninguna
                            </span>
                            <div v-if="mostrarLista && sucursalesFiltradas.length > 0"
                                class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-40 overflow-y-auto">
                                <div v-for="suc in sucursalesFiltradas" :key="suc.id"
                                    @mousedown="seleccionarSucursal(suc)"
                                    class="px-2.5 py-1.5 hover:bg-primary-50 cursor-pointer border-b border-gray-100 last:border-b-0 text-sm"
                                    :class="{ 'bg-primary-50 text-primary-700': sucursalId === suc.id }">
                                    <div class="font-medium text-xs">{{ suc.nombre }}</div>
                                    <div class="text-[9px] text-gray-400">N° {{ suc.numero }}</div>
                                </div>
                            </div>
                            <div v-if="mostrarLista && sucursalesFiltradas.length === 0 && sucursalSearch"
                                class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-[10px]">
                                No se encontraron sucursales
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Desde:</label>
                            <input type="date" v-model="fechaInicial" @change="aplicarFiltros"
                                class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                        </div>
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Hasta:</label>
                            <input type="date" v-model="fechaFinal" @change="aplicarFiltros"
                                class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                        </div>
                        <div class="flex items-center gap-1 flex-1 min-w-[120px] max-w-[200px]">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Producto:</label>
                            <input type="text" v-model="search" placeholder="Código o descripción..."
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                        </div>
                        <label class="flex items-center gap-1 text-[11px] text-gray-600 cursor-pointer">
                            <input type="checkbox" v-model="soloConMovimiento" @change="aplicarFiltros"
                                class="w-3.5 h-3.5 text-primary-600 rounded">
                            Solo con movimiento
                        </label>
                        <div class="flex gap-1.5 ml-auto">
                            <button @click="aplicarFiltros" :disabled="!haySucursalSeleccionada"
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-search text-[10px]"></i> Buscar
                            </button>
                            <button @click="limpiarFiltros"
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-eraser text-[10px]"></i> Limpiar
                            </button>
                        </div>
                    </div>
                    <div class="mt-1 text-[8px] text-gray-400">
                        <i class="fas fa-info-circle"></i> Salidas reales de inventario (tipo operación = Ventas). Cada columna es un combo/pack. TOTAL REAL = suma de inventario_propiamente. "Producto Suelto" = diferencia (ventas sin combo).
                    </div>
                </div>

                <!-- SIN SUCURSAL -->
                <div v-if="!haySucursalSeleccionada" class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <i class="fas fa-building text-4xl text-gray-300 mb-2 block"></i>
                    <p class="text-sm font-medium text-gray-600">Selecciona una sucursal para ver la matriz</p>
                </div>

                <!-- TABLA -->
                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between text-[10px] text-gray-500">
                        <span>
                            <i class="fas fa-table text-primary-500"></i>
                            {{ columnas.length }} columnas · {{ filas.length }} productos base
                        </span>
                        <span>
                            <i class="fas fa-arrows-left-right text-gray-400"></i> Desliza para ver más columnas
                        </span>
                    </div>

                    <div class="overflow-x-auto overflow-y-auto" style="max-height: 70vh;">
                        <table class="min-w-full divide-y divide-gray-200 border-collapse">
                            <thead class="bg-primary-50">
                                <tr class="sticky top-0 z-20">
                                    <th class="px-3 py-2 text-left text-[10px] font-bold text-primary-700 uppercase sticky left-0 z-30 bg-primary-50 border-r border-primary-200"
                                        style="min-width: 200px; max-width: 200px;">
                                        Producto Base
                                    </th>
                                    <th v-for="col in columnas" :key="col.id"
                                        class="px-2 py-2 text-center text-[10px] font-medium uppercase border-r border-gray-200 bg-blue-100 text-blue-800"
                                        style="min-width: 120px; max-width: 120px; height: 60px; vertical-align: top;"
                                        :title="col.nombre + (col.codigo ? ' (' + col.codigo + ')' : '')">
                                        <div class="whitespace-normal break-words leading-tight text-[10px] line-clamp-3">
                                            {{ col.nombre }}
                                        </div>
                                    </th>
                                    <th class="px-2 py-2 text-center text-[10px] font-bold uppercase bg-amber-100 text-amber-800 border-r border-amber-200 sticky"
                                        style="min-width: 120px;">
                                        Producto Suelto
                                    </th>
                                    <th class="px-3 py-2 text-center text-[10px] font-bold text-primary-800 uppercase bg-primary-200 sticky right-0 z-30 border-l border-primary-300"
                                        style="min-width: 110px;">
                                        TOTAL REAL
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr v-for="fila in filas" :key="fila.id_producto" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-1.5 sticky left-0 z-10 bg-white border-r border-gray-200"
                                        style="min-width: 200px; max-width: 200px;">
                                        <div class="text-[10px] font-mono text-gray-400">{{ fila.codigo }}</div>
                                        <div class="text-xs font-medium text-gray-800 whitespace-normal break-words leading-tight" :title="fila.descripcion">
                                            {{ fila.descripcion }}
                                        </div>
                                    </td>
                                    <td v-for="col in columnas" :key="col.id"
                                        class="px-2 py-1.5 text-center border-r border-gray-100"
                                        :class="getValor(fila, col.id) > 0 ? 'text-gray-800 font-semibold' : 'text-gray-300'">
                                        {{ formatNumber(getValor(fila, col.id)) }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center bg-amber-50 font-bold text-amber-700 border-r border-amber-200"
                                        :class="fila.otras_salidas > 0 ? '' : 'text-gray-300'">
                                        {{ formatNumber(fila.otras_salidas) }}
                                    </td>
                                    <td class="px-3 py-1.5 text-center bg-primary-50 font-bold text-primary-700 text-xs sticky right-0 z-10 border-l border-primary-200">
                                        {{ formatNumber(fila.total) }}
                                    </td>
                                </tr>

                                <tr v-if="!filas || filas.length === 0">
                                    <td :colspan="columnas.length + 3" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-box-open text-2xl mb-1 block"></i>
                                        No hay datos para los filtros seleccionados
                                    </td>
                                </tr>
                            </tbody>

                            <tfoot class="bg-gray-100 sticky bottom-0 z-10">
                                <tr>
                                    <td class="px-3 py-2 text-xs font-bold text-gray-800 sticky left-0 z-20 bg-gray-100 border-r border-gray-300">
                                        TOTAL POR COLUMNA
                                    </td>
                                    <td v-for="col in columnas" :key="col.id"
                                        class="px-2 py-2 text-center text-xs font-bold border-r border-gray-200 text-blue-700 bg-blue-50">
                                        {{ formatNumber(totalesPorColumna?.[col.id]) }}
                                    </td>
                                    <td class="px-2 py-2 text-center text-xs font-bold text-amber-700 bg-amber-50 border-r border-amber-200">
                                        {{ formatNumber(filas.reduce((acc, f) => acc + (f.otras_salidas || 0), 0)) }}
                                    </td>
                                    <td class="px-3 py-2 text-center bg-primary-200 text-primary-800 font-bold text-xs sticky right-0 z-20 border-l border-primary-300">
                                        {{ formatNumber(totalGeneral) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.overflow-y-auto::-webkit-scrollbar { width: 6px; height: 6px; }
.overflow-y-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

.line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>