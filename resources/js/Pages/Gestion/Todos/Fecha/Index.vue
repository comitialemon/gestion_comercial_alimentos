<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import { inject } from 'vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    fechas: Object,
    monedas: Array,
    filtros: Object,
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
const modalOpen = ref(false)
const editando = ref(false)
const fechaSeleccionada = ref(null)

// Filtros
const searchFecha = ref(props.filtros?.fecha || '')
const searchCierre = ref(props.filtros?.cierre || '')

// ==================== COMPUTED ====================
const hayFiltrosAplicados = computed(() => {
    return searchFecha.value || searchCierre.value
})

// ==================== FORMULARIO ====================
const form = useForm({
    IdFecha: '',
    Fecha: '',
    ActivoInactivo: true,
    CierreSucursal: false,
    CierrePermanente: false,
    factores: [],
})

// Opciones para filtro de cierre
const opcionesCierre = [
    { value: '', label: 'Todos' },
    { value: 'abierta', label: 'Abierta' },
    { value: 'cierre_sucursal', label: 'Cierre de Sucursal' },
    { value: 'cierre_permanente', label: 'Cierre Permanente' },
]

// ==================== FUNCIONES ====================
// 🔥 FUNCIÓN CORREGIDA - SIN new Date() (muestra el día correcto)
const formatearFecha = (fechaStr) => {
    if (!fechaStr) return '-'
    // La fecha viene como "YYYY-MM-DD" del backend
    const partes = fechaStr.split('-')
    if (partes.length === 3) {
        return `${partes[2]}/${partes[1]}/${partes[0]}`
    }
    return fechaStr
}

const inicializarFactores = () => {
    form.factores = props.monedas.map(moneda => {
        let factorPorDefecto = 0
        
        if (moneda.Abreviacion === 'Bs' || moneda.Moneda === 'Bolivianos') {
            factorPorDefecto = 1
        } else if (moneda.Abreviacion === 'Sus' || moneda.Moneda === 'Dolares Americanos') {
            factorPorDefecto = 6.96
        }
        
        return {
            IdMoneda: moneda.IdMoneda,
            MonedaNombre: moneda.Moneda,
            Abreviacion: moneda.Abreviacion,
            FactorCambio: factorPorDefecto,
        }
    })
}

const aplicarFiltros = () => {
    router.get('/gestion/fechas', {
        fecha: searchFecha.value || undefined,
        cierre: searchCierre.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltros = () => {
    searchFecha.value = ''
    searchCierre.value = ''
    aplicarFiltros()
}

const nuevaFecha = () => {
    editando.value = false
    fechaSeleccionada.value = null
    form.reset()
    inicializarFactores()
    form.ActivoInactivo = true
    form.CierreSucursal = false
    form.CierrePermanente = false
    modalOpen.value = true
}

const editarFecha = (fecha) => {
    editando.value = true
    fechaSeleccionada.value = fecha
    form.IdFecha = fecha.IdFecha
    form.Fecha = fecha.Fecha.split(' ')[0]
    form.ActivoInactivo = fecha.ActivoInactivo === 0
    form.CierreSucursal = fecha.CierreSucursal === 1
    form.CierrePermanente = fecha.CierrePermanente === 1
    
    const factoresMap = new Map()
    fecha.factores_cambio?.forEach(factor => {
        factoresMap.set(factor.IdMoneda, factor.FactorCambio)
    })
    
    form.factores = props.monedas.map(moneda => {
        let factor = factoresMap.get(moneda.IdMoneda)
        
        if (factor === undefined) {
            if (moneda.Abreviacion === 'Bs' || moneda.Moneda === 'Bolivianos') {
                factor = 1
            } else if (moneda.Abreviacion === 'Sus' || moneda.Moneda === 'Dolares Americanos') {
                factor = 6.96
            } else {
                factor = 0
            }
        }
        
        return {
            IdMoneda: moneda.IdMoneda,
            MonedaNombre: moneda.Moneda,
            Abreviacion: moneda.Abreviacion,
            FactorCambio: factor,
        }
    })
    
    modalOpen.value = true
}

const guardarFecha = () => {
    const data = {
        Fecha: form.Fecha,
        ActivoInactivo: form.ActivoInactivo,
        CierreSucursal: form.CierreSucursal,
        CierrePermanente: form.CierrePermanente,
        factores: form.factores.map(f => ({
            IdMoneda: f.IdMoneda,
            FactorCambio: f.FactorCambio,
        })),
    }
    
    if (editando.value) {
        router.put(`/gestion/fechas/${form.IdFecha}`, data, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Fecha actualizada correctamente')
                modalOpen.value = false
                aplicarFiltros()
            },
        })
    } else {
        router.post('/gestion/fechas', data, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Fecha creada correctamente')
                modalOpen.value = false
                aplicarFiltros()
            },
        })
    }
}

const estadoTexto = (activo) => {
    return activo === 0 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
}

const cierreTexto = (cierreSucursal, cierrePermanente) => {
    if (cierrePermanente === 1) return 'Cierre Permanente'
    if (cierreSucursal === 1) return 'Cierre Sucursal'
    return 'Abierta'
}

const cierreClase = (cierreSucursal, cierrePermanente) => {
    if (cierrePermanente === 1) return 'bg-red-100 text-red-700'
    if (cierreSucursal === 1) return 'bg-orange-100 text-orange-700'
    return 'bg-emerald-100 text-emerald-700'
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    inicializarFactores()
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
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Fechas y Tipos de Cambio</h1>
                            <p class="text-[10px] text-gray-500">Gestión de fechas contables y factores de cambio</p>
                        </div>
                    </div>
                    <button @click="nuevaFecha" 
                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition">
                        <i class="fas fa-plus text-[10px]"></i> Nueva Fecha
                    </button>
                </div>

                <!-- ==================== FILTROS COMPACTOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar fecha</label>
                            <input 
                                type="text" 
                                v-model="searchFecha"
                                placeholder="YYYY-MM-DD"
                                class="w-36 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Tipo de cierre</label>
                            <select v-model="searchCierre" 
                                class="w-36 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option v-for="op in opcionesCierre" :key="op.value" :value="op.value">
                                    {{ op.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex gap-1.5">
                            <button @click="aplicarFiltros" 
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1">
                                <i class="fas fa-search text-[10px]"></i> Buscar
                            </button>
                            <button @click="limpiarFiltros" 
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-eraser text-[10px]"></i> Limpiar
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
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-xs font-mono text-gray-700">{{ formatearFecha(fecha.Fecha) }}</p>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(fecha.ActivoInactivo)">
                                                {{ estadoTexto(fecha.ActivoInactivo) }}
                                            </span>
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="cierreClase(fecha.CierreSucursal, fecha.CierrePermanente)">
                                                {{ cierreTexto(fecha.CierreSucursal, fecha.CierrePermanente) }}
                                            </span>
                                        </div>
                                    </div>
                                    <button @click="editarFecha(fecha)" 
                                        class="px-2.5 py-1 text-[9px] rounded bg-primary-50 text-primary-600 hover:bg-primary-100 transition flex items-center gap-1">
                                        <i class="fas fa-edit text-[8px]"></i> Editar
                                    </button>
                                </div>
                            </div>
                            <div v-if="fechas.data.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-calendar-day text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay fechas que coincidan con la búsqueda</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO (tabla con STICKY HEADER) -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <!-- 🔥 STICKY HEADER -->
                            <thead class="bg-primary-50 sticky top-0 z-10 shadow-sm" style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase w-24">Estado</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase w-32">Cierre</th>
                                    <th class="px-3 py-1.5 text-right text-[9px] font-medium text-primary-700 uppercase w-16">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="fecha in fechas.data" :key="fecha.IdFecha" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-1.5 text-xs font-mono text-gray-700">{{ formatearFecha(fecha.Fecha) }}</td>
                                    <td class="px-3 py-1.5 text-center">
                                        <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(fecha.ActivoInactivo)">
                                            {{ estadoTexto(fecha.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-1.5 text-center">
                                        <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="cierreClase(fecha.CierreSucursal, fecha.CierrePermanente)">
                                            {{ cierreTexto(fecha.CierreSucursal, fecha.CierrePermanente) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right">
                                        <button @click="editarFecha(fecha)" 
                                            class="text-primary-600 hover:text-primary-800 transition text-xs p-1 rounded hover:bg-primary-50" 
                                            title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="fechas.data.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-calendar-day text-2xl mb-1 block"></i>
                                        No hay fechas que coincidan con la búsqueda
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

        <!-- ==================== MODAL DE FECHA Y FACTORES DE CAMBIO ==================== -->
        <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="modalOpen = false">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="modalOpen = false"></div>
                
                <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full mx-auto transform transition-all duration-300">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 py-2.5 bg-primary-600 rounded-t-xl">
                        <h3 class="text-sm font-semibold text-white">
                            {{ editando ? 'Editar Fecha' : 'Nueva Fecha' }}
                        </h3>
                        <button @click="modalOpen = false" class="text-white/80 hover:text-white transition text-sm">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-4">
                        <form @submit.prevent="guardarFecha" class="space-y-3">
                            <!-- Fecha -->
                            <div>
                                <label class="block text-[10px] font-medium text-gray-700 mb-0.5">Fecha *</label>
                                <input type="date" v-model="form.Fecha" 
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    :class="{ 'border-red-500': form.errors.Fecha }">
                                <p v-if="form.errors.Fecha" class="text-[8px] text-red-500 mt-0.5">{{ form.errors.Fecha }}</p>
                            </div>

                            <!-- Estados -->
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.ActivoInactivo" 
                                        class="w-3.5 h-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <span class="text-[10px] text-gray-700">Activo</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.CierreSucursal" 
                                        class="w-3.5 h-3.5 rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                                    <span class="text-[10px] text-gray-700">Cierre de Sucursal</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer col-span-2">
                                    <input type="checkbox" v-model="form.CierrePermanente" 
                                        class="w-3.5 h-3.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <span class="text-[10px] text-gray-700">Cierre Permanente</span>
                                </label>
                            </div>

                            <!-- Tipos de Cambio -->
                            <div>
                                <label class="block text-[10px] font-medium text-gray-700 mb-1">Tipos de Cambio</label>
                                <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                    <div v-for="(factor, index) in form.factores" :key="factor.IdMoneda" 
                                        class="flex items-center gap-2">
                                        <div class="w-32">
                                            <span class="text-[10px] font-medium text-gray-600">{{ factor.Abreviacion }}</span>
                                            <span class="text-[8px] text-gray-400 block">{{ factor.MonedaNombre }}</span>
                                        </div>
                                        <div class="flex-1">
                                            <input 
                                                type="number" 
                                                v-model.number="factor.FactorCambio" 
                                                step="0.000001"
                                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                                placeholder="Factor"
                                            >
                                        </div>
                                    </div>
                                </div>
                                <p class="text-[8px] text-gray-400 mt-1">* Factor de cambio para conversión a Bolivianos</p>
                            </div>

                            <!-- Botones -->
                            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                                <button type="button" @click="modalOpen = false" 
                                    class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="form.processing" 
                                    class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition disabled:opacity-50 flex items-center gap-1.5">
                                    <i v-if="form.processing" class="fas fa-spinner fa-spin text-[10px]"></i>
                                    <i v-else class="fas fa-save text-[10px]"></i>
                                    {{ form.processing ? 'Guardando...' : 'Guardar' }}
                                </button>
                            </div>
                        </form>
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

/* Quitar flechas de inputs number */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
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