<!-- resources/js/Pages/Gestion/Impuestos/Comisionista/Index.vue -->
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

// Estado
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

// Modal de eliminación
const modalEliminarOpen = ref(false)
const eliminarId = ref(null)
const eliminarNombre = ref('')
const eliminando = ref(false)

// Verificar mensajes flash al cargar
onMounted(() => {
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

// Buscar identificadores
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
        const url = `/gestion/comisionista/buscar-identificador?q=${encodeURIComponent(termino)}`
        const response = await axios.get(url)
        identificadoresList.value = response.data || []
    } catch (err) {
        console.error('Error buscando identificador:', err)
        identificadoresList.value = []
    } finally {
        buscandoIdentificador.value = false
    }
}

// Seleccionar identificador
const seleccionarIdentificador = (id, ci, nombre) => {
    formData.value.IdIdentificador = id
    identificadorSeleccionado.value = { id, ci, nombre }
    busquedaIdentificador.value = `${ci} - ${nombre}`
    identificadoresList.value = []
    mostrarResultados.value = false
}

// Limpiar selección
const limpiarSeleccion = () => {
    if (editando.value) return
    formData.value.IdIdentificador = ''
    identificadorSeleccionado.value = null
    busquedaIdentificador.value = ''
    identificadoresList.value = []
    mostrarResultados.value = false
}

// Ocultar resultados
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

// Resetear formulario
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

// Editar
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

// Guardar
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
            await router.put(`/gestion/comisionista/${editId.value}`, formData.value, {
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
            await router.post('/gestion/comisionista', formData.value, {
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

// Eliminar
const abrirModalEliminar = (id, nombre) => {
    eliminarId.value = id
    eliminarNombre.value = nombre || 'Comisionista'
    modalEliminarOpen.value = true
}

const cerrarModalEliminar = () => {
    modalEliminarOpen.value = false
    eliminarId.value = null
    eliminarNombre.value = ''
}

const confirmarEliminar = async () => {
    if (!eliminarId.value) return
    
    eliminando.value = true
    
    router.delete(`/gestion/comisionista/${eliminarId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast?.success('Éxito', `Comisionista "${eliminarNombre.value}" eliminado correctamente`)
            cerrarModalEliminar()
        },
        onError: (err) => {
            toast?.error('Error', 'No se pudo eliminar el comisionista')
            cerrarModalEliminar()
        },
        onFinish: () => {
            eliminando.value = false
        }
    })
}

// Búsqueda con debounce
let searchTimeout
watch(search, (newVal) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get('/gestion/comisionista', { search: newVal || undefined }, {
            preserveState: true,
            replace: true,
        })
    }, 500)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-tie text-emerald-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">Comisionistas</h1>
                            <p class="text-[10px] text-gray-500">Administra los vendedores o comisionistas</p>
                            <p v-if="contexto_actual?.cliente_nombre" class="text-[10px] text-emerald-600">
                                <i class="fas fa-building mr-1"></i> {{ contexto_actual.cliente_nombre }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Filtro de búsqueda -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                            <input 
                                type="text" 
                                v-model="search" 
                                placeholder="Buscar por nombre o CI..." 
                                class="w-full border rounded-md px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                </div>

                <!-- Formulario inline -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6 sticky top-2 z-10 border border-emerald-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <!-- Identificador -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Persona *</label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="busquedaIdentificador"
                                    @focus="mostrarResultados = !!identificadoresList.length"
                                    @blur="ocultarResultados"
                                    :disabled="editando"
                                    placeholder="Escribe para buscar por CI o nombre..." 
                                    class="w-full border rounded-md px-3 py-2 text-sm pr-8"
                                    :class="{ 'border-red-500': errors.IdIdentificador }"
                                />
                                <button 
                                    v-if="busquedaIdentificador && !editando && !formData.IdIdentificador"
                                    @click="limpiarSeleccion"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <!-- Lista de resultados -->
                                <div 
                                    v-if="mostrarResultados && identificadoresList.length > 0 && !formData.IdIdentificador"
                                    class="absolute z-20 mt-1 w-full border rounded-md max-h-48 overflow-y-auto bg-white shadow-lg"
                                >
                                    <div 
                                        v-for="item in identificadoresList" 
                                        :key="item.id"
                                        @click="seleccionarIdentificador(item.id, item.ci, item.nombre)"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-sm"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded-full">{{ item.ci }}</span>
                                            <span class="text-gray-700">{{ item.nombre }}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Indicador de búsqueda -->
                                <div v-if="buscandoIdentificador" class="text-xs text-gray-400 mt-1">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Buscando...
                                </div>
                                
                                <!-- Mensaje sin resultados -->
                                <div v-if="mostrarResultados && !buscandoIdentificador && busquedaIdentificador && busquedaIdentificador.length >= 2 && identificadoresList.length === 0 && !formData.IdIdentificador" class="text-xs text-gray-400 mt-1">
                                    <i class="fas fa-search mr-1"></i> No se encontraron resultados
                                </div>
                                
                                <!-- Identificador seleccionado -->
                                <div v-if="formData.IdIdentificador" class="text-xs text-emerald-600 mt-1 flex items-center gap-1">
                                    <i class="fas fa-check-circle"></i>
                                    Seleccionado: <span class="font-medium">{{ identificadorSeleccionado?.ci }} - {{ identificadorSeleccionado?.nombre }}</span>
                                </div>
                            </div>
                            <p v-if="errors.IdIdentificador" class="text-xs text-red-500 mt-1">{{ errors.IdIdentificador }}</p>
                        </div>

                        <!-- Comisión -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Comisión (%) *</label>
                            <div class="relative">
                                <input 
                                    type="number" 
                                    v-model.number="formData.Comision" 
                                    min="0" 
                                    max="100" 
                                    step="0.01"
                                    class="w-full border rounded-md px-3 py-2 text-sm pr-12"
                                    :class="{ 'border-red-500': errors.Comision }"
                                />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                            </div>
                            <p v-if="errors.Comision" class="text-xs text-red-500 mt-1">{{ errors.Comision }}</p>
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-2">
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.IdIdentificador"
                                class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-md text-sm hover:bg-emerald-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-plus'" class="text-xs"></i>
                                {{ processing ? 'Procesando...' : (editando ? 'Actualizar' : 'Guardar') }}
                            </button>
                            <button 
                                v-if="editando" 
                                @click="resetForm" 
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300 transition"
                            >
                                <i class="fas fa-times text-xs"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- CARDS PARA MÓVIL -->
                <div class="block sm:hidden space-y-3">
                    <div 
                        v-for="item in comisionistas.data" 
                        :key="item.IdComisionista" 
                        class="bg-white rounded-lg shadow-sm p-4 border border-gray-100"
                    >
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-gray-800 text-sm">{{ item.identificador?.Nombre || '-' }}</span>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ item.Comision }}%
                                    </span>
                                </div>
                                <p class="text-[11px] text-gray-500 font-mono">
                                    <i class="fas fa-id-card mr-1"></i> {{ item.identificador?.CI_NIT || '-' }}
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-3 border-t border-gray-100 mt-2">
                            <button 
                                @click="editar(item)" 
                                class="text-emerald-600 hover:text-emerald-800 text-xs flex items-center gap-1"
                            >
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button 
                                @click="abrirModalEliminar(item.IdComisionista, item.identificador?.Nombre)" 
                                class="text-red-600 hover:text-red-800 text-xs flex items-center gap-1"
                            >
                                <i class="fas fa-trash-alt"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="!comisionistas.data || comisionistas.data.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-user-tie text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-sm text-gray-400">No hay comisionistas registrados</p>
                    </div>
                </div>

                <!-- TABLA PARA DESKTOP -->
                <div class="hidden sm:block bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-emerald-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-emerald-700 uppercase">CI/NIT</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-emerald-700 uppercase">Nombre</th>
                                    <th class="px-3 py-2 text-center text-[10px] font-semibold text-emerald-700 uppercase">Comisión</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-semibold text-emerald-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in comisionistas.data" :key="item.IdComisionista" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 whitespace-nowrap text-xs font-mono text-gray-900">
                                        {{ item.identificador?.CI_NIT || '-' }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700">
                                        <i class="fas fa-user text-emerald-400 mr-1 text-[10px]"></i>
                                        {{ item.identificador?.Nombre || '-' }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full bg-blue-100 text-blue-800">
                                            {{ item.Comision }}%
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">
                                        <button @click="editar(item)" class="text-emerald-600 hover:text-emerald-800 mr-2 transition" title="Editar">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button @click="abrirModalEliminar(item.IdComisionista, item.identificador?.Nombre)" class="text-red-600 hover:text-red-800 transition" title="Eliminar">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!comisionistas.data || comisionistas.data.length === 0">
                                    <td colspan="4" class="px-3 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-user-tie text-2xl mb-1 block text-gray-300"></i>
                                        No hay comisionistas registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="comisionistas.links && comisionistas.links.length > 1" class="mt-4 px-3 py-2 bg-white rounded-lg shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                        <div class="text-[10px] text-gray-500">
                            Mostrando {{ comisionistas.from || 0 }} a {{ comisionistas.to || 0 }} de {{ comisionistas.total || 0 }}
                        </div>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in comisionistas.links" 
                                :key="link.label" 
                                :href="link.url || '#'" 
                                class="px-2 py-0.5 rounded border text-[10px] transition"
                                :class="{ 
                                    'bg-emerald-600 text-white border-emerald-600': link.active, 
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

        <!-- Modal de confirmación para eliminar -->
        <div v-if="modalEliminarOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="cerrarModalEliminar">
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden animate-fade-in-up">
                <div class="bg-red-50 p-4 text-center">
                    <div class="w-12 h-12 mx-auto bg-red-100 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-trash-alt text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">¿Eliminar comisionista?</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        ¿Estás seguro de que deseas eliminar a 
                        <span class="font-semibold text-gray-700">"{{ eliminarNombre }}"</span>?
                    </p>
                    <p class="text-xs text-red-500 mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="p-4 flex gap-3">
                    <button 
                        @click="cerrarModalEliminar"
                        class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition"
                    >
                        Cancelar
                    </button>
                    <button 
                        @click="confirmarEliminar"
                        :disabled="eliminando"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        <i v-if="eliminando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-trash-alt"></i>
                        {{ eliminando ? 'Eliminando...' : 'Eliminar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.2s ease-out;
}
</style>