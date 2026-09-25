<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted, computed, inject } from 'vue'
import axios from 'axios'
import ShowModal from './ShowModal.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    contenedores: Object,
    sucursales: Array,
    sucursalActual: Number,
    filtroEstado: String,
    buscar: String,
    sucursalSeleccionada: String
})

// =============================================
// ESTADO DE FILTROS
// =============================================
const sucursalId = ref(props.sucursalSeleccionada || '')
const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')

// =============================================
// MODAL DE DETALLE
// =============================================
const modalVisible = ref(false)
const contenedorSeleccionado = ref(null)

const abrirModal = (contenedor) => {
    contenedorSeleccionado.value = contenedor
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    contenedorSeleccionado.value = null
}

// =============================================
// COMPUTADOS
// =============================================
const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id == sucursalId.value)
    return suc?.nombre || 'Sucursal'
})

const contenedoresData = ref(props.contenedores)

const contenedoresAgrupados = computed(() => {
    if (!contenedoresData.value?.data) return {}
    
    const grupos = {}
    
    contenedoresData.value.data.forEach(contenedor => {
        const sucursalNombre = contenedor.sucursal?.Nombre || 'Sin sucursal'
        const sucursalId = contenedor.IdSucursal || 0
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                contenedores: [],
                total_capacidad: 0
            }
        }
        
        grupos[sucursalId].contenedores.push(contenedor)
        grupos[sucursalId].total_capacidad += contenedor.CapacidadTotal || 0
    })
    
    return grupos
})

const sucursalesConContenedores = computed(() => {
    return Object.values(contenedoresAgrupados.value)
})

// =============================================
// EXPANDIR/CONTRAER
// =============================================
const sucursalesExpandidas = ref({})

const toggleSucursal = (id) => {
    if (sucursalesExpandidas.value[id] !== undefined) {
        sucursalesExpandidas.value[id] = !sucursalesExpandidas.value[id]
    }
}

const expandirTodas = () => {
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        sucursalesExpandidas.value[id] = true
    })
}

const contraerTodas = () => {
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        sucursalesExpandidas.value[id] = false
    })
}

const actualizarExpandidas = () => {
    const grupos = contenedoresAgrupados.value
    const idsActuales = Object.keys(grupos)
    
    idsActuales.forEach(id => {
        if (sucursalesExpandidas.value[id] === undefined) {
            sucursalesExpandidas.value[id] = true
        }
    })
    
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        if (!idsActuales.includes(id)) {
            delete sucursalesExpandidas.value[id]
        }
    })
}

// =============================================
// ACCIONES
// =============================================
const irANuevo = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/contenedores/create')
}

const actualizarDatosLocales = () => {
    const params = {
        sucursal_id: sucursalId.value || undefined,
        estado: estadoFiltro.value || undefined,
        buscar: buscador.value || undefined
    }
    
    router.get('/operacion/pedidos/clientes-mayoristas/contenedores', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: (page) => {
            contenedoresData.value = page.props.contenedores
            setTimeout(() => actualizarExpandidas(), 100)
        }
    })
}

const aplicarFiltros = () => {
    actualizarDatosLocales()
}

let timeoutBuscador
const buscarContenedores = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => aplicarFiltros(), 500)
}

const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

const construirUrlConFiltros = (url) => {
    if (!url) return '#'
    
    try {
        const urlObj = new URL(url, window.location.origin)
        const params = new URLSearchParams(urlObj.search)
        
        if (sucursalId.value) params.set('sucursal_id', sucursalId.value)
        if (estadoFiltro.value) params.set('estado', estadoFiltro.value)
        if (buscador.value) params.set('buscar', buscador.value)
        
        urlObj.search = params.toString()
        return urlObj.toString()
    } catch (error) {
        console.error('Error construyendo URL:', error)
        return url
    }
}

// =============================================
// CAMBIAR ESTADO (ÚNICA ACCIÓN DISPONIBLE)
// =============================================
const cambiando = ref({})

const cambiarEstado = async (contenedor) => {
    const accion = contenedor.ActivoInactivo === 1 ? 'inactivar' : 'activar'
    
    if (!confirm(`¿Estás seguro de ${accion} el contenedor "${contenedor.Codigo}"?`)) {
        return
    }
    
    cambiando.value[contenedor.IdContenedor] = true
    
    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/contenedores/${contenedor.IdContenedor}/cambiar-estado`
        )
        
        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            actualizarDatosLocales()
        } else {
            toast?.error('Error', response.data.message || 'Error al cambiar estado')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al cambiar estado')
    } finally {
        cambiando.value[contenedor.IdContenedor] = false
    }
}

// =============================================
// UTILIDADES
// =============================================
const formatearNumero = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(2)
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Activo' : 'Borrador'
}

const getEstadoBadge = (activo) => {
    return activo === 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
}

// =============================================
// CICLO DE VIDA
// =============================================
const isMobile = ref(false)
const handleResize = () => { isMobile.value = window.innerWidth < 768 }

onMounted(() => {
    window.addEventListener('resize', handleResize)
    handleResize()
    contenedoresData.value = props.contenedores
    setTimeout(() => actualizarExpandidas(), 100)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

watch(() => props.contenedores, (newVal) => {
    contenedoresData.value = newVal
    setTimeout(() => actualizarExpandidas(), 100)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">

                <!-- HEADER -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-boxes text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Gestión de Contenedores</h1>
                            <p class="text-[10px] text-gray-500">Administra los contenedores por sucursal</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button 
                            @click="irANuevo"
                            class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1.5 transition"
                        >
                            <i class="fas fa-plus text-[10px]"></i>
                            Nuevo Contenedor
                        </button>
                    </div>
                </div>

                <!-- FILTROS -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-1">
                            <label class="text-xs font-medium text-gray-700">Sucursal:</label>
                            <span 
                                v-if="sucursalId && sucursalNombre" 
                                class="inline-flex items-center gap-1.5 bg-primary-50 border border-primary-200 text-primary-700 text-xs font-semibold px-2.5 py-1 rounded-lg"
                            >
                                <i class="fas fa-store text-primary-500 text-[10px]"></i>
                                {{ sucursalNombre }}
                            </span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" @change="aplicarFiltros" class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-32 sm:w-36 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Todos</option>
                                <option value="activos">Activos</option>
                                <option value="borradores">Borradores</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarContenedores"
                                placeholder="Buscar..."
                                class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-28 sm:w-32 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                            <button 
                                v-if="buscador" 
                                @click="limpiarBusqueda" 
                                class="text-gray-400 hover:text-gray-600 text-xs"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="flex gap-1 ml-auto flex-wrap">
                            <button @click="expandirTodas" class="text-[10px] bg-primary-100 hover:bg-primary-200 text-primary-700 px-2 py-1 rounded transition">
                                <i class="fas fa-plus-circle"></i> Expandir
                            </button>
                            <button @click="contraerTodas" class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-1 rounded transition">
                                <i class="fas fa-minus-circle"></i> Contraer
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> 
                        <span class="text-green-600">● Activo</span> = Contenedor listo para usar | 
                        <span class="text-yellow-600">● Borrador</span> = En edición
                    </div>
                </div>

                <!-- CONTENIDO PRINCIPAL -->
                <div v-if="!sucursalId" class="bg-white rounded-xl shadow-sm p-8 sm:p-12 text-center">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-store text-primary-400 text-3xl sm:text-4xl"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-700">Sin Sucursal Asignada</h3>
                </div>

                <div v-else-if="sucursalesConContenedores.length > 0">
                    <div v-for="grupo in sucursalesConContenedores" :key="grupo.id" class="mb-3">
                        
                        <div 
                            @click="toggleSucursal(grupo.id)"
                            class="flex flex-wrap items-center justify-between gap-2 bg-primary-50 rounded-t-lg px-3 sm:px-4 py-2 border border-primary-200 cursor-pointer hover:bg-primary-100 transition"
                        >
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <i 
                                    class="text-primary-600 text-[10px] sm:text-sm transition-transform duration-200 flex-shrink-0"
                                    :class="sucursalesExpandidas[grupo.id] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                ></i>
                                <i class="fas fa-store text-primary-600 text-xs sm:text-sm flex-shrink-0"></i>
                                <h2 class="font-bold text-primary-800 text-xs sm:text-sm truncate">{{ grupo.nombre }}</h2>
                                <span class="text-[9px] sm:text-xs text-primary-600 bg-primary-100 px-1.5 sm:px-2 py-0.5 rounded-full flex-shrink-0">
                                    {{ grupo.contenedores.length }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-xs sm:text-sm text-primary-700 flex-shrink-0">
                                <span class="font-bold">
                                    {{ formatearNumero(grupo.total_capacidad) }} und
                                </span>
                            </div>
                        </div>

                        <transition 
                            enter-active-class="transition-all duration-300 ease-in-out"
                            enter-from-class="max-h-0 opacity-0 overflow-hidden"
                            enter-to-class="max-h-[5000px] opacity-100 overflow-hidden"
                            leave-active-class="transition-all duration-300 ease-in-out"
                            leave-from-class="max-h-[5000px] opacity-100 overflow-hidden"
                            leave-to-class="max-h-0 opacity-0 overflow-hidden"
                        >
                            <div v-if="sucursalesExpandidas[grupo.id]" class="bg-white rounded-b-lg shadow-sm overflow-hidden border border-t-0 border-primary-200">
                                
                                <!-- DESKTOP -->
                                <div class="hidden md:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Capacidad</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Cambiar Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="item in grupo.contenedores" :key="item.IdContenedor" class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">{{ item.Codigo }}</td>
                                                <td class="px-3 py-2 text-center text-xs">{{ item.TipoContenedor }}</td>
                                                <td class="px-3 py-2 text-center text-xs">{{ formatearNumero(item.CapacidadTotal) }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoBadge(item.ActivoInactivo)">
                                                        {{ getEstadoTexto(item.ActivoInactivo) }}
                                                    </span>
                                                </td>

                                                <td class="px-3 py-2 text-center">
                                                    <button 
                                                        @click="cambiarEstado(item)"
                                                        :disabled="cambiando[item.IdContenedor]"
                                                        class="transition p-1.5 rounded disabled:opacity-50 inline-flex items-center gap-1 text-xs"
                                                        :class="item.ActivoInactivo === 1 
                                                            ? 'text-red-500 hover:text-red-700 hover:bg-red-50' 
                                                            : 'text-green-600 hover:text-green-800 hover:bg-green-50'"
                                                        :title="item.ActivoInactivo === 1 ? 'Inactivar' : 'Activar'"
                                                    >
                                                        <i v-if="cambiando[item.IdContenedor]" class="fas fa-spinner fa-spin text-xs"></i>
                                                        <i v-else :class="item.ActivoInactivo === 1 ? 'fas fa-pause-circle text-xs' : 'fas fa-play-circle text-xs'"></i>
                                                        <span class="text-[10px]">
                                                            {{ item.ActivoInactivo === 1 ? 'Inactivar' : 'Activar' }}
                                                        </span>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- MÓVIL -->
                                <div class="md:hidden divide-y divide-gray-100">
                                    <div v-for="item in grupo.contenedores" :key="item.IdContenedor" class="p-3 hover:bg-gray-50 transition">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-bold text-primary-700 text-sm font-mono">{{ item.Codigo }}</span>
                                                    <span class="text-xs text-gray-500">{{ item.TipoContenedor }}</span>
                                                </div>
                                                <div class="flex items-center gap-3 mt-1">
                                                    <span class="text-xs text-gray-500">Cap: {{ formatearNumero(item.CapacidadTotal) }}</span>
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoBadge(item.ActivoInactivo)">
                                                        {{ getEstadoTexto(item.ActivoInactivo) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                                <div class="flex gap-2">
                                                    <button 
                                                        @click="abrirModal(item)" 
                                                        class="text-blue-500 hover:text-blue-700" 
                                                        title="Ver detalle"
                                                    >
                                                        <i class="fas fa-eye text-sm"></i>
                                                    </button>
                                                    <button 
                                                        @click="cambiarEstado(item)"
                                                        :disabled="cambiando[item.IdContenedor]"
                                                        class="disabled:opacity-50"
                                                        :class="item.ActivoInactivo === 1 ? 'text-red-500' : 'text-green-600'"
                                                        :title="item.ActivoInactivo === 1 ? 'Inactivar' : 'Activar'"
                                                    >
                                                        <i v-if="cambiando[item.IdContenedor]" class="fas fa-spinner fa-spin"></i>
                                                        <i v-else :class="item.ActivoInactivo === 1 ? 'fas fa-pause-circle' : 'fas fa-play-circle'"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </transition>
                    </div>
                    
                    <!-- PAGINACIÓN -->
                    <div v-if="contenedoresData?.data?.length" class="bg-white rounded-xl shadow-sm mt-4 px-3 sm:px-4 py-2 border border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <p class="text-[10px] sm:text-xs text-gray-500">
                            Mostrando {{ contenedoresData.from || 0 }} - {{ contenedoresData.to || 0 }} de {{ contenedoresData.total || 0 }}
                        </p>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in contenedoresData.links" 
                                :key="link.label" 
                                :href="construirUrlConFiltros(link.url)"
                                class="px-2 sm:px-2.5 py-1 rounded text-[10px] sm:text-xs transition" 
                                :class="{ 
                                    'bg-primary-600 text-white': link.active, 
                                    'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200': !link.active && link.url, 
                                    'opacity-50 cursor-not-allowed': !link.url 
                                }" 
                                v-html="link.label"
                                preserve-state
                            />
                        </div>
                    </div>
                </div>

                <!-- ESTADO VACÍO -->
                <div v-else class="bg-white rounded-xl shadow-sm p-6 sm:p-8 text-center text-gray-500">
                    <i class="fas fa-box-open text-3xl sm:text-4xl block mb-2 text-gray-300"></i>
                    <p class="text-sm sm:text-base">
                        <span v-if="buscador">No hay contenedores que coincidan con "{{ buscador }}"</span>
                        <span v-else>No hay contenedores en esta sucursal</span>
                    </p>
                    <div class="mt-4">
                        <button 
                            @click="irANuevo"
                            class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-800"
                        >
                            <i class="fas fa-plus"></i> Crear nuevo contenedor
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL DETALLE -->
        <ShowModal 
            :visible="modalVisible" 
            :contenedor="contenedorSeleccionado"
            @close="cerrarModal"
        />
    </div>
</template>

<style scoped>
.custom-toast {
    max-width: 90%;
    z-index: 9999;
}

.max-h-0 {
    max-height: 0;
}
.max-h-\[5000px\] {
    max-height: 5000px;
}
.transition-all {
    transition-property: all;
}
.duration-300 {
    transition-duration: 300ms;
}
.ease-in-out {
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
.overflow-hidden {
    overflow: hidden;
}
</style>