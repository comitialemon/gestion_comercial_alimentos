<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted } from 'vue'
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

// Estado del modal
const modalOpen = ref(false)
const editando = ref(false)
const fechaSeleccionada = ref(null)

// Filtros
const searchFecha = ref(props.filtros?.fecha || '')
const searchCierre = ref(props.filtros?.cierre || '')

// Formulario
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

// Inicializar factores con valores por defecto
const inicializarFactores = () => {
    form.factores = props.monedas.map(moneda => {
        let factorPorDefecto = 0
        
        // Asignar valores por defecto según la moneda
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

// Aplicar filtros (solo al presionar el botón)
const aplicarFiltros = () => {
    router.get('/gestion/fechas', {
        fecha: searchFecha.value || undefined,
        cierre: searchCierre.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

// Limpiar filtros
const limpiarFiltros = () => {
    searchFecha.value = ''
    searchCierre.value = ''
    aplicarFiltros()
}

// Abrir modal para nueva fecha
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

// Abrir modal para editar
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
        
        // Si no tiene factor guardado, asignar valor por defecto
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

// Guardar
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

// Estado texto
const estadoTexto = (activo) => {
    return activo === 0 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo === 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
}

// Cierre texto
const cierreTexto = (cierreSucursal, cierrePermanente) => {
    if (cierrePermanente === 1) return 'Cierre Permanente'
    if (cierreSucursal === 1) return 'Cierre Sucursal'
    return 'Abierta'
}

const cierreClase = (cierreSucursal, cierrePermanente) => {
    if (cierrePermanente === 1) return 'bg-red-100 text-red-800'
    if (cierreSucursal === 1) return 'bg-secondary-100 text-secondary-800'
    return 'bg-green-100 text-green-800'
}

// Inicializar
onMounted(() => {
    inicializarFactores()
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-primary-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Fechas y Tipos de Cambio</h1>
                            <p class="text-[11px] text-gray-500">Gestión de fechas contables y factores de cambio</p>
                        </div>
                    </div>
                    <button @click="nuevaFecha" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center gap-1 transition">
                        <i class="fas fa-plus text-[10px]"></i> Nueva Fecha
                    </button>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <!-- Buscar por fecha (texto libre) -->
                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Buscar por fecha</label>
                            <input 
                                type="text" 
                                v-model="searchFecha"
                                placeholder="YYYY-MM-DD o texto"
                                class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-500"
                            >
                        </div>
                        
                        <!-- Filtro por tipo de cierre -->
                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Tipo de cierre</label>
                            <select v-model="searchCierre" class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-500">
                                <option v-for="op in opcionesCierre" :key="op.value" :value="op.value">
                                    {{ op.label }}
                                </option>
                            </select>
                        </div>
                        
                        <!-- Botones -->
                        <div class="flex items-end gap-2">
                            <button 
                                @click="aplicarFiltros" 
                                class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs flex items-center gap-1 transition"
                            >
                                <i class="fas fa-search text-[9px]"></i> Buscar
                            </button>
                            <button 
                                @click="limpiarFiltros" 
                                class="border border-gray-300 hover:bg-gray-100 text-gray-700 px-3 py-1.5 rounded-md text-xs flex items-center gap-1 transition"
                            >
                                <i class="fas fa-eraser text-[9px]"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de fechas -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[11px] font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-4 py-2 text-center text-[11px] font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-4 py-2 text-center text-[11px] font-medium text-primary-700 uppercase">Cierre</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="fecha in fechas.data" :key="fecha.IdFecha" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-xs font-mono text-gray-700">{{ fecha.Fecha }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="estadoClase(fecha.ActivoInactivo)">
                                            {{ estadoTexto(fecha.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="cierreClase(fecha.CierreSucursal, fecha.CierrePermanente)">
                                            {{ cierreTexto(fecha.CierreSucursal, fecha.CierrePermanente) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button @click="editarFecha(fecha)" class="text-primary-600 hover:text-primary-800 text-xs" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="fechas.data.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-calendar-day text-2xl mb-2 block"></i>
                                        No hay fechas que coincidan con la búsqueda
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación (50 por página) -->
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

        <!-- Modal de Fecha y Factores de Cambio -->
        <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="modalOpen = false">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="modalOpen = false"></div>
                
                <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-auto transform transition-all duration-300">
                    <div class="flex items-center justify-between px-4 py-2.5 border-b bg-primary-600 rounded-t-lg">
                        <h3 class="text-sm font-semibold text-white">
                            {{ editando ? 'Editar Fecha' : 'Nueva Fecha' }}
                        </h3>
                        <button @click="modalOpen = false" class="text-white/80 hover:text-white transition">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>

                    <div class="p-4">
                        <form @submit.prevent="guardarFecha" class="space-y-4">
                            <!-- Fecha -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-1">Fecha *</label>
                                <input type="date" v-model="form.Fecha" class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-500" :class="{ 'border-red-500': form.errors.Fecha }">
                                <p v-if="form.errors.Fecha" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Fecha }}</p>
                            </div>

                            <!-- Estados -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" v-model="form.ActivoInactivo" class="w-3.5 h-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                        <span class="text-[11px] text-gray-700">Activo</span>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" v-model="form.CierreSucursal" class="w-3.5 h-3.5 rounded border-gray-300 text-secondary-600 focus:ring-secondary-500">
                                        <span class="text-[11px] text-gray-700">Cierre de Sucursal</span>
                                    </label>
                                </div>
                                <div class="col-span-2">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" v-model="form.CierrePermanente" class="w-3.5 h-3.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                        <span class="text-[11px] text-gray-700">Cierre Permanente</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Tipos de Cambio -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-2">Tipos de Cambio</label>
                                <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                                    <div v-for="(factor, index) in form.factores" :key="factor.IdMoneda" class="flex items-center gap-3">
                                        <div class="w-32">
                                            <span class="text-[11px] font-medium text-gray-600">{{ factor.MonedaNombre }} ({{ factor.Abreviacion }})</span>
                                        </div>
                                        <div class="flex-1">
                                            <input 
                                                type="number" 
                                                v-model.number="factor.FactorCambio" 
                                                step="0.000001"
                                                class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-500"
                                                placeholder="Factor de cambio"
                                            >
                                        </div>
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-2">* Factor de cambio para conversión a Bolivianos</p>
                            </div>

                            <!-- Botones -->
                            <div class="flex justify-end gap-2 pt-3 border-t">
                                <button type="button" @click="modalOpen = false" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="form.processing" class="px-4 py-1.5 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1">
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
/* Quitar flechas de inputs number en todos los navegadores */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield; /* Firefox */
    appearance: textfield; /* Estándar */
}
</style>