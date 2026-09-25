<script setup>
import { ref, computed, watch, inject, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'

const toast = inject('toast')

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    contenedor: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['close', 'actualizar'])

// =============================================
// ESTADO
// =============================================
const loading = ref(false)
const guardando = ref(false)
const gruposAsignados = ref([])
const gruposDisponibles = ref([])

const busquedaGrupo = ref('')
const busquedaGrupoParaAgregar = ref('')
const mostrarListaGrupos = ref(false)
const grupoSeleccionadoParaAgregar = ref(null)

const dropdownRef = ref(null)
const inputBusquedaRef = ref(null)

// =============================================
// COMPUTADOS
// =============================================
const gruposAsignadosOrdenados = computed(() => {
    return [...gruposAsignados.value].sort((a, b) =>
        (a.Nombre || '').localeCompare(b.Nombre || '', 'es', { sensitivity: 'base' })
    )
})

const gruposFiltrados = computed(() => {
    if (!busquedaGrupo.value.trim()) {
        return gruposAsignadosOrdenados.value
    }
    const termino = busquedaGrupo.value.toLowerCase().trim()
    const filtrados = gruposAsignados.value.filter(g =>
        g.Nombre?.toLowerCase().includes(termino) ||
        g.Descripcion?.toLowerCase().includes(termino)
    )
    return [...filtrados].sort((a, b) =>
        (a.Nombre || '').localeCompare(b.Nombre || '', 'es', { sensitivity: 'base' })
    )
})

const gruposDisponiblesFiltrados = computed(() => {
    if (!mostrarListaGrupos.value) return []

    let lista = gruposDisponibles.value

    if (busquedaGrupoParaAgregar.value) {
        const termino = busquedaGrupoParaAgregar.value.toLowerCase().trim()
        lista = lista.filter(g =>
            g.Nombre?.toLowerCase().includes(termino) ||
            g.Descripcion?.toLowerCase().includes(termino)
        )
    }

    return [...lista]
        .sort((a, b) => (a.Nombre || '').localeCompare(b.Nombre || '', 'es', { sensitivity: 'base' }))
        .slice(0, 20)
})

const puedeAgregar = computed(() => {
    return !!grupoSeleccionadoParaAgregar.value
})

const totalClientesAsignados = computed(() => {
    return gruposAsignados.value.reduce((sum, g) => sum + (g.TotalClientes || 0), 0)
})

// =============================================
// CONTROL DEL DROPDOWN
// =============================================
const abrirDropdown = () => {
    mostrarListaGrupos.value = true
}

const cerrarDropdownConDelay = () => {
    setTimeout(() => {
        mostrarListaGrupos.value = false
    }, 150)
}

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        mostrarListaGrupos.value = false
    }
}

const handleEscape = (event) => {
    if (event.key === 'Escape') {
        mostrarListaGrupos.value = false
    }
}

// =============================================
// MÉTODOS
// =============================================
const cargarDatos = async () => {
    if (!props.contenedor) return

    loading.value = true

    try {
        const [resAsignados, resDisponibles] = await Promise.all([
            axios.get(`/operacion/pedidos/clientes-mayoristas/contenedores/${props.contenedor.IdContenedor}/grupos-asignados`),
            axios.get(`/operacion/pedidos/clientes-mayoristas/contenedores/${props.contenedor.IdContenedor}/grupos-disponibles`),
        ])

        if (resAsignados.data.success) {
            gruposAsignados.value = resAsignados.data.data
        }
        if (resDisponibles.data.success) {
            gruposDisponibles.value = resDisponibles.data.data
        }

    } catch (error) {
        console.error('Error al cargar datos:', error)
        toast?.error('Error', 'No se pudieron cargar los grupos')
    } finally {
        loading.value = false
    }
}

const seleccionarGrupoParaAgregar = (grupo) => {
    grupoSeleccionadoParaAgregar.value = grupo
    busquedaGrupoParaAgregar.value = grupo.Nombre
    mostrarListaGrupos.value = false
}

const limpiarSeleccionGrupo = () => {
    grupoSeleccionadoParaAgregar.value = null
    busquedaGrupoParaAgregar.value = ''
    mostrarListaGrupos.value = false
    
    nextTick(() => {
        inputBusquedaRef.value?.focus()
    })
}

const agregarGrupo = async () => {
    if (!puedeAgregar.value) return

    guardando.value = true

    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/contenedores/${props.contenedor.IdContenedor}/grupos`,
            {
                IdGrupoCliente: grupoSeleccionadoParaAgregar.value.IdGrupoCliente
            }
        )

        if (response.data.success) {
            toast?.success('Éxito', 'Grupo asignado correctamente')
            await cargarDatos()
            emit('actualizar', { type: 'agregar' })
            
            grupoSeleccionadoParaAgregar.value = null
            busquedaGrupoParaAgregar.value = ''
            mostrarListaGrupos.value = false
        } else {
            toast?.error('Error', response.data.message || 'Error al asignar grupo')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al asignar grupo')
    } finally {
        guardando.value = false
    }
}

const eliminarGrupo = async (grupo) => {
    if (!confirm(`¿Eliminar el grupo "${grupo.Nombre}" de este contenedor?\n\nLos clientes del grupo ya no podrán usar este contenedor.`)) {
        return
    }

    try {
        const response = await axios.delete(
            `/operacion/pedidos/clientes-mayoristas/contenedores/grupos/${grupo.IdContenedorGrupoCliente}`
        )

        if (response.data.success) {
            toast?.success('Éxito', 'Grupo eliminado correctamente')
            await cargarDatos()
            emit('actualizar', { type: 'eliminar' })
        } else {
            toast?.error('Error', response.data.message || 'Error al eliminar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al eliminar')
    }
}

const cerrar = () => {
    emit('close')
}

const formatearNumero = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(0)
}

// =============================================
// WATCHERS
// =============================================
watch(() => props.visible, (newVal) => {
    if (newVal && props.contenedor) {
        cargarDatos()
        mostrarListaGrupos.value = false
        grupoSeleccionadoParaAgregar.value = null
        busquedaGrupoParaAgregar.value = ''
        busquedaGrupo.value = ''
    }
})

watch(busquedaGrupoParaAgregar, (newVal) => {
    if (newVal && !grupoSeleccionadoParaAgregar.value) {
        mostrarListaGrupos.value = true
    }
})

watch(grupoSeleccionadoParaAgregar, (newVal, oldVal) => {
    if (oldVal && !newVal) {
        busquedaGrupoParaAgregar.value = ''
    }
})

// =============================================
// LIFECYCLE
// =============================================
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <div 
        v-if="visible"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3 sm:p-4"
        @click.self="cerrar"
    >
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[95vh] overflow-hidden shadow-xl animate-fade-in-up">
            
            <!-- HEADER -->
            <div class="px-4 sm:px-5 py-2.5 sm:py-3 border-b bg-primary-50 flex items-center justify-between flex-shrink-0">
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-gray-800 text-sm sm:text-base truncate">
                        <i class="fas fa-layer-group text-primary-500 mr-2"></i>
                        Asignar Grupos al Contenedor
                    </h3>
                    <p class="text-[10px] text-gray-500 truncate">
                        {{ contenedor?.Codigo || 'Contenedor' }} 
                        <span class="mx-1">•</span>
                        Cap: {{ formatearNumero(contenedor?.CapacidadTotal) }} und
                        <span class="mx-1">•</span>
                        <span class="text-primary-600 font-medium">
                            {{ gruposAsignados.length }} grupo(s)
                        </span>
                        <span class="mx-1">•</span>
                        <span class="text-blue-600 font-medium">
                            {{ totalClientesAsignados }} cliente(s)
                        </span>
                    </p>
                </div>
                <button 
                    @click="cerrar"
                    class="text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg p-1.5 transition flex-shrink-0"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- CUERPO -->
            <div class="p-3 sm:p-4 overflow-y-auto" style="max-height: calc(95vh - 160px);">
                
                <!-- Loading -->
                <div v-if="loading" class="flex flex-col items-center justify-center py-8">
                    <div class="w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
                    <p class="text-xs text-gray-400 mt-2">Cargando grupos...</p>
                </div>

                <!-- AGREGAR NUEVO GRUPO -->
                <div v-if="!loading" class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-xs font-medium text-gray-700 mb-2">
                        <i class="fas fa-plus-circle text-primary-500 mr-1"></i>
                        Asignar nuevo grupo
                    </p>
                    
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- INPUT CON DROPDOWN -->
                        <div 
                            ref="dropdownRef"
                            class="relative flex-1 min-w-[200px]"
                        >
                            <div class="relative">
                                <input
                                    ref="inputBusquedaRef"
                                    type="text"
                                    v-model="busquedaGrupoParaAgregar"
                                    @focus="abrirDropdown"
                                    @blur="cerrarDropdownConDelay"
                                    :placeholder="grupoSeleccionadoParaAgregar ? '' : 'Buscar grupo...'"
                                    class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none transition"
                                    :class="{
                                        'bg-green-50 border-green-300 pr-7': grupoSeleccionadoParaAgregar,
                                        'bg-white border-gray-300 pr-7': !grupoSeleccionadoParaAgregar && busquedaGrupoParaAgregar,
                                        'bg-white border-gray-300': !grupoSeleccionadoParaAgregar && !busquedaGrupoParaAgregar
                                    }"
                                    autocomplete="off"
                                />
                                
                                <button
                                    v-if="grupoSeleccionadoParaAgregar || busquedaGrupoParaAgregar"
                                    @mousedown.prevent="limpiarSeleccionGrupo"
                                    type="button"
                                    class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-0.5 rounded hover:bg-gray-100"
                                    title="Limpiar"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>

                            <!-- DROPDOWN -->
                            <div 
                                v-if="mostrarListaGrupos && gruposDisponiblesFiltrados.length > 0"
                                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                @mousedown.prevent
                            >
                                <div class="px-2 py-1 bg-gray-50 border-b text-[9px] text-gray-500 flex items-center justify-between sticky top-0">
                                    <span>
                                        <i class="fas fa-layer-group text-[8px] mr-1"></i>
                                        {{ gruposDisponiblesFiltrados.length }} disponible(s)
                                    </span>
                                    <span class="text-gray-400">
                                        Presione <kbd class="px-1 bg-white rounded border border-gray-300 text-[8px]">Esc</kbd>
                                    </span>
                                </div>

                                <div
                                    v-for="grupo in gruposDisponiblesFiltrados"
                                    :key="grupo.IdGrupoCliente"
                                    @mousedown.prevent="seleccionarGrupoParaAgregar(grupo)"
                                    class="px-2 py-1.5 hover:bg-primary-50 cursor-pointer text-xs flex justify-between items-center border-b border-gray-100 last:border-0 transition"
                                >
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <div class="w-5 h-5 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-layer-group text-primary-600 text-[8px]"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-medium text-gray-800 truncate">{{ grupo.Nombre }}</p>
                                            <p v-if="grupo.Descripcion" class="text-[9px] text-gray-400 truncate">
                                                {{ grupo.Descripcion }}
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0 ml-2">
                                        <i class="fas fa-users text-[8px]"></i>
                                        {{ grupo.TotalClientes }}
                                    </span>
                                </div>
                            </div>

                            <!-- Sin resultados -->
                            <div 
                                v-else-if="mostrarListaGrupos && busquedaGrupoParaAgregar && !grupoSeleccionadoParaAgregar && gruposDisponiblesFiltrados.length === 0"
                                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-3 text-center"
                            >
                                <i class="fas fa-search text-gray-300 text-base mb-1 block"></i>
                                <p class="text-[10px] text-gray-500">No hay grupos con "{{ busquedaGrupoParaAgregar }}"</p>
                            </div>
                        </div>

                        <!-- BOTÓN AGREGAR -->
                        <button
                            @click="agregarGrupo"
                            :disabled="!puedeAgregar || guardando"
                            class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-plus text-[10px]"></i>
                            {{ guardando ? 'Asignando...' : 'Asignar' }}
                        </button>
                    </div>

                    <p v-if="gruposDisponibles.length === 0 && !busquedaGrupoParaAgregar && !loading" class="text-[10px] text-gray-400 mt-1.5">
                        <i class="fas fa-info-circle mr-1"></i>
                        Todos los grupos ya están asignados a este contenedor
                    </p>
                </div>

                <!-- LISTA DE GRUPOS ASIGNADOS -->
                <div v-if="!loading">
                    <div class="relative mb-3">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                        <input
                            type="text"
                            v-model="busquedaGrupo"
                            placeholder="Buscar grupo asignado..."
                            class="w-full border border-gray-200 rounded-md pl-7 pr-7 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none transition bg-gray-50 focus:bg-white"
                        />
                        <button 
                            v-if="busquedaGrupo"
                            @click="busquedaGrupo = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </div>

                    <div v-if="gruposAsignados.length === 0" class="text-center py-6 text-gray-400">
                        <i class="fas fa-layer-group text-2xl block mb-2 text-gray-300"></i>
                        <p class="text-sm font-medium">No hay grupos asignados</p>
                        <p class="text-[10px] mt-0.5">Asigna un grupo usando el formulario superior</p>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div 
                            v-for="grupo in gruposFiltrados" 
                            :key="grupo.IdContenedorGrupoCliente"
                            class="border border-gray-200 rounded-lg p-3 bg-white hover:border-primary-300 transition flex items-start justify-between gap-2"
                        >
                            <div class="flex items-start gap-2 min-w-0 flex-1">
                                <div class="w-9 h-9 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-layer-group text-primary-600 text-sm"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-gray-800 truncate">
                                        {{ grupo.Nombre }}
                                    </p>
                                    <p v-if="grupo.Descripcion" class="text-[10px] text-gray-400 truncate">
                                        {{ grupo.Descripcion }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[9px] text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded-full font-medium">
                                            <i class="fas fa-users text-[8px]"></i>
                                            {{ grupo.TotalClientes }} cliente(s)
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button
                                @click="eliminarGrupo(grupo)"
                                class="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded transition flex-shrink-0"
                                title="Eliminar del contenedor"
                            >
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Total -->
                    <div v-if="gruposAsignados.length > 0" class="mt-3 p-2.5 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
                        <span class="text-xs font-medium text-blue-700">
                            <i class="fas fa-calculator mr-1"></i>
                            Total de clientes con acceso:
                        </span>
                        <span class="text-sm font-bold text-blue-700">
                            {{ totalClientesAsignados }} cliente(s)
                        </span>
                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="px-4 sm:px-5 py-2.5 border-t bg-gray-50 flex justify-end flex-shrink-0">
                <button 
                    @click="cerrar"
                    class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-xs transition"
                >
                    Cerrar
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.2s ease-out;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 8px;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d1d1;
    border-radius: 8px;
}
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

kbd {
    font-family: monospace;
    font-size: 9px;
    line-height: 1;
}
</style>