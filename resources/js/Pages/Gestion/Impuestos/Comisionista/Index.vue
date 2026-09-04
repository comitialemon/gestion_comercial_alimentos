<script setup>
import { ref, computed, watch, onMounted, inject } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    comisionistas: {
        type: Object,
        default: () => ({ data: [], links: [], from: null, to: null, total: 0 })
    },
    filtros: {
        type: Object,
        default: () => ({})
    },
    contexto_actual: {
        type: Object,
        default: () => ({})
    }
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
const search = ref(props.filtros?.search || '')
const editando = ref(false)
const editId = ref(null)
const formData = ref({
    IdIdentificador: '',
    Comision: 0
})
const errors = ref({})
const processing = ref(false)

// Estado para buscador de identificador
const busquedaIdentificador = ref('')
const identificadoresList = ref([])
const mostrarResultados = ref(false)
const buscandoIdentificador = ref(false)
const identificadorSeleccionado = ref(null)

// ==================== FUNCIONES ====================
const buscarIdentificador = async () => {
    const termino = busquedaIdentificador.value?.trim()
    
    if (!termino || termino.length < 2) {
        identificadoresList.value = []
        mostrarResultados.value = false
        return
    }
    
    buscandoIdentificador.value = true
    mostrarResultados.value = true
    
    try {
        const url = `/gestion/impuestos/comisionista/buscar-identificador?q=${encodeURIComponent(termino)}`
        const response = await axios.get(url)
        identificadoresList.value = response.data || []
    } catch (err) {
        console.error('Error buscando identificador:', err)
        identificadoresList.value = []
    } finally {
        buscandoIdentificador.value = false
    }
}

const seleccionarIdentificador = (id, ci, nombre) => {
    formData.value.IdIdentificador = id
    identificadorSeleccionado.value = { id, ci, nombre }
    busquedaIdentificador.value = `${ci} - ${nombre}`
    identificadoresList.value = []
    mostrarResultados.value = false
}

const limpiarSeleccion = () => {
    if (editando.value) return
    formData.value.IdIdentificador = ''
    identificadorSeleccionado.value = null
    busquedaIdentificador.value = ''
    identificadoresList.value = []
    mostrarResultados.value = false
}

const ocultarResultados = () => {
    setTimeout(() => {
        if (!formData.value.IdIdentificador) {
            mostrarResultados.value = false
        }
    }, 200)
}

// Debounce para búsqueda
let timeout
watch(busquedaIdentificador, (newVal) => {
    if (editando.value && formData.value.IdIdentificador && newVal === `${identificadorSeleccionado.value?.ci} - ${identificadorSeleccionado.value?.nombre}`) {
        identificadoresList.value = []
        mostrarResultados.value = false
        return
    }
    
    clearTimeout(timeout)
    
    if (!newVal || newVal.trim() === '') {
        if (!editando.value) {
            formData.value.IdIdentificador = ''
            identificadorSeleccionado.value = null
        }
        identificadoresList.value = []
        mostrarResultados.value = false
        return
    }
    
    if (formData.value.IdIdentificador && newVal === `${identificadorSeleccionado.value?.ci} - ${identificadorSeleccionado.value?.nombre}`) {
        identificadoresList.value = []
        mostrarResultados.value = false
        return
    }
    
    if (!editando.value) {
        formData.value.IdIdentificador = ''
        identificadorSeleccionado.value = null
    }
    
    timeout = setTimeout(() => {
        buscarIdentificador()
    }, 400)
})

const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = {
        IdIdentificador: '',
        Comision: 0
    }
    identificadorSeleccionado.value = null
    busquedaIdentificador.value = ''
    identificadoresList.value = []
    mostrarResultados.value = false
    errors.value = {}
}

const editar = (item) => {
    editando.value = true
    editId.value = item.IdComisionista
    formData.value = {
        IdIdentificador: item.IdIdentificador,
        Comision: item.Comision
    }
    const identificador = item.identificador
    if (identificador) {
        identificadorSeleccionado.value = {
            id: identificador.IdIdentificador,
            ci: identificador.CI_NIT,
            nombre: identificador.Nombre
        }
        busquedaIdentificador.value = `${identificador.CI_NIT} - ${identificador.Nombre}`
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const guardar = async () => {
    if (!formData.value.IdIdentificador) {
        toast?.error('Validación', 'Debes seleccionar una persona')
        return
    }
    
    if (formData.value.Comision < 0 || formData.value.Comision > 100) {
        toast?.error('Validación', 'La comisión debe estar entre 0 y 100')
        return
    }
    
    processing.value = true
    
    try {
        if (editando.value) {
            await router.put(`/gestion/impuestos/comisionista/${editId.value}`, formData.value, {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.success('Éxito', 'Comisionista actualizado correctamente')
                    resetForm()
                },
                onError: (err) => {
                    toast?.error('Error', Object.values(err)[0]?.[0] || 'Error al actualizar')
                }
            })
        } else {
            await router.post('/gestion/impuestos/comisionista', formData.value, {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.success('Éxito', 'Comisionista creado correctamente')
                    resetForm()
                },
                onError: (err) => {
                    toast?.error('Error', Object.values(err)[0]?.[0] || 'Error al guardar')
                }
            })
        }
    } finally {
        processing.value = false
    }
}

// Búsqueda con debounce
let searchTimeout
watch(search, (newVal) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get('/gestion/impuestos/comisionista', { search: newVal || undefined }, {
            preserveState: true,
            replace: true,
        })
    }, 500)
})

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    
    const flashSuccess = page.props.flash?.success
    const flashError = page.props.flash?.error
    
    if (flashSuccess && !sessionStorage.getItem('last_flash_success')) {
        toast?.success('Éxito', flashSuccess)
        sessionStorage.setItem('last_flash_success', flashSuccess)
        setTimeout(() => sessionStorage.removeItem('last_flash_success'), 500)
    }
    if (flashError && !sessionStorage.getItem('last_flash_error')) {
        toast?.error('Error', flashError)
        sessionStorage.setItem('last_flash_error', flashError)
        setTimeout(() => sessionStorage.removeItem('last_flash_error'), 500)
    }
    
    resetForm()
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
                            <i class="fas fa-user-tie text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Comisionistas</h1>
                            <p class="text-[10px] text-gray-500">Administra los vendedores o comisionistas</p>
                            <p v-if="contexto_actual?.cliente_nombre" class="text-[9px] text-primary-600">
                                <i class="fas fa-building mr-1"></i> {{ contexto_actual.cliente_nombre }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ==================== FILTRO DE BÚSQUEDA ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex-1 min-w-[140px] max-w-[280px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar</label>
                            <input 
                                type="text" 
                                v-model="search" 
                                placeholder="Nombre o CI..." 
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- ==================== FORMULARIO INLINE COMPACTO ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4 border border-primary-200">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Identificador -->
                        <div class="flex-1 min-w-[160px] max-w-[280px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Persona *</label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="busquedaIdentificador"
                                    @focus="mostrarResultados = !!identificadoresList.length"
                                    @blur="ocultarResultados"
                                    :disabled="editando"
                                    placeholder="Buscar por CI o nombre..." 
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-7 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    :class="{ 'border-red-500': errors.IdIdentificador }"
                                />
                                <button 
                                    v-if="busquedaIdentificador && !editando && !formData.IdIdentificador"
                                    @click="limpiarSeleccion"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                
                                <!-- Lista de resultados -->
                                <div 
                                    v-if="mostrarResultados && identificadoresList.length > 0 && !formData.IdIdentificador"
                                    class="absolute z-20 mt-1 w-full border border-gray-200 rounded-md max-h-40 overflow-y-auto bg-white shadow-lg"
                                >
                                    <div 
                                        v-for="item in identificadoresList" 
                                        :key="item.id"
                                        @click="seleccionarIdentificador(item.id, item.ci, item.nombre)"
                                        class="px-2.5 py-1.5 hover:bg-primary-50 cursor-pointer border-b last:border-b-0 text-sm flex items-center gap-2"
                                    >
                                        <span class="font-mono text-[10px] bg-gray-100 px-1.5 py-0.5 rounded">{{ item.ci }}</span>
                                        <span class="text-xs text-gray-700">{{ item.nombre }}</span>
                                    </div>
                                </div>
                                
                                <!-- Indicador de búsqueda -->
                                <div v-if="buscandoIdentificador" class="text-[10px] text-gray-400 mt-0.5">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Buscando...
                                </div>
                                
                                <!-- Sin resultados -->
                                <div v-if="mostrarResultados && !buscandoIdentificador && busquedaIdentificador && busquedaIdentificador.length >= 2 && identificadoresList.length === 0 && !formData.IdIdentificador" 
                                    class="text-[10px] text-gray-400 mt-0.5">
                                    <i class="fas fa-search mr-1"></i> No se encontraron resultados
                                </div>
                                
                                <!-- Seleccionado -->
                                <div v-if="formData.IdIdentificador" class="text-[10px] text-primary-600 mt-0.5 flex items-center gap-1">
                                    <i class="fas fa-check-circle"></i>
                                    <span class="font-medium">{{ identificadorSeleccionado?.ci }} - {{ identificadorSeleccionado?.nombre }}</span>
                                </div>
                            </div>
                            <p v-if="errors.IdIdentificador" class="text-[8px] text-red-500 mt-0.5">{{ errors.IdIdentificador }}</p>
                        </div>

                        <!-- Comisión -->
                        <div class="w-28">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Comisión (%) *</label>
                            <div class="relative">
                                <input 
                                    type="number" 
                                    v-model.number="formData.Comision" 
                                    min="0" 
                                    max="100" 
                                    step="0.01"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-7 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    :class="{ 'border-red-500': errors.Comision }"
                                />
                                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]">%</span>
                            </div>
                            <p v-if="errors.Comision" class="text-[8px] text-red-500 mt-0.5">{{ errors.Comision }}</p>
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-1.5 ml-auto">
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.IdIdentificador"
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition disabled:opacity-50 flex items-center gap-1.5"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-plus'" class="text-[10px]"></i>
                                {{ processing ? 'Guardando...' : (editando ? 'Actualizar' : 'Guardar') }}
                            </button>
                            <button 
                                v-if="editando" 
                                @click="resetForm" 
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1.5"
                            >
                                <i class="fas fa-times text-[10px]"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== TABLA DE COMISIONISTAS ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 65vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="item in comisionistas.data" :key="item.IdComisionista" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-gray-800 truncate">{{ item.identificador?.Nombre || '-' }}</p>
                                        <p class="text-[10px] font-mono text-gray-500">{{ item.identificador?.CI_NIT || '-' }}</p>
                                    </div>
                                    <span class="px-1.5 py-0.5 text-[9px] font-semibold rounded-full bg-blue-100 text-blue-700 flex-shrink-0 ml-2">
                                        {{ item.Comision }}%
                                    </span>
                                </div>
                                <div class="flex justify-end pt-2 mt-1.5 border-t border-gray-200">
                                    <button @click="editar(item)" 
                                        class="px-2.5 py-1 text-[9px] rounded bg-primary-50 text-primary-600 hover:bg-primary-100 transition flex items-center gap-1">
                                        <i class="fas fa-edit text-[8px]"></i> Editar
                                    </button>
                                </div>
                            </div>
                            <div v-if="!comisionistas.data || comisionistas.data.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-user-tie text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay comisionistas registrados</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET (tabla compacta) -->
                        <div v-else-if="isTablet" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">CI/NIT</th>
                                        <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Nombre</th>
                                        <th class="px-3 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase w-24">Comisión</th>
                                        <th class="px-3 py-1.5 text-right text-[9px] font-medium text-primary-700 uppercase w-16">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in comisionistas.data" :key="item.IdComisionista" class="hover:bg-gray-50">
                                        <td class="px-3 py-1.5 text-[10px] font-mono text-gray-600">{{ item.identificador?.CI_NIT || '-' }}</td>
                                        <td class="px-3 py-1.5 text-[10px] text-gray-700">
                                            <i class="fas fa-user text-primary-400 mr-1 text-[8px]"></i>
                                            {{ item.identificador?.Nombre || '-' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-center">
                                            <span class="px-1.5 py-0.5 text-[8px] font-semibold rounded-full bg-blue-100 text-blue-700">
                                                {{ item.Comision }}%
                                            </span>
                                        </td>
                                        <td class="px-3 py-1.5 text-right">
                                            <button @click="editar(item)" class="text-primary-600 hover:text-primary-800 transition text-[10px] p-1" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!comisionistas.data || comisionistas.data.length === 0">
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-xs">
                                            <i class="fas fa-user-tie text-2xl mb-1 block"></i>
                                            No hay comisionistas registrados
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- VISTA ESCRITORIO (tabla completa) -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">CI/NIT</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">Nombre</th>
                                        <th class="px-4 py-2 text-center text-[10px] font-medium text-primary-700 uppercase w-24">Comisión</th>
                                        <th class="px-4 py-2 text-right text-[10px] font-medium text-primary-700 uppercase w-16">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in comisionistas.data" :key="item.IdComisionista" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-2 text-xs font-mono text-gray-600">{{ item.identificador?.CI_NIT || '-' }}</td>
                                        <td class="px-4 py-2 text-xs text-gray-700">
                                            <i class="fas fa-user text-primary-400 mr-1 text-[10px]"></i>
                                            {{ item.identificador?.Nombre || '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="px-2 py-0.5 text-[9px] font-semibold rounded-full bg-blue-100 text-blue-700">
                                                {{ item.Comision }}%
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <button @click="editar(item)" class="text-primary-600 hover:text-primary-800 transition text-xs p-1 rounded hover:bg-primary-50" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!comisionistas.data || comisionistas.data.length === 0">
                                        <td colspan="4" class="px-4 py-10 text-center text-gray-400 text-sm">
                                            <i class="fas fa-user-tie text-2xl mb-1 block"></i>
                                            No hay comisionistas registrados
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ==================== PAGINACIÓN ==================== -->
                <div v-if="comisionistas.links && comisionistas.links.length > 1" class="mt-4 px-3 py-2 bg-white rounded-xl shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                        <div class="text-[10px] text-gray-500">
                            Mostrando {{ comisionistas.from || 0 }} a {{ comisionistas.to || 0 }} de {{ comisionistas.total || 0 }}
                        </div>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in comisionistas.links" 
                                :key="link.label" 
                                :href="link.url || '#'" 
                                class="px-2.5 py-1 rounded-lg border text-[10px] transition"
                                :class="{ 
                                    'bg-primary-600 text-white border-primary-600': link.active, 
                                    'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url, 
                                    'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url 
                                }" 
                                v-html="link.label" 
                            />
                        </div>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="mt-3 text-[8px] text-gray-400 text-center">
                    <i class="fas fa-info-circle"></i> Los comisionistas reciben un porcentaje de las ventas que realizan
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