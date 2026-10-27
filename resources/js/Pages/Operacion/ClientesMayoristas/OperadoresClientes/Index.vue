<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ModalOperadorPedidoClientes from './ModalOperadorPedidoClientes.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    operadores: Object,
    sucursales: Array,
    identificadores: Array,
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
const operadorSeleccionado = ref(null)
const asignacionSeleccionada = ref(null)

// Filtros
const search = ref(props.filtros?.search || '')
const estado = ref(props.filtros?.estado || '')

// ==================== HELPERS ====================

// ✅ Devuelve el array de asignaciones de un operador (para esta empresa)
const getAsignaciones = (operador) => {
    return operador.asignaciones_sucursal || []
}

// ✅ Devuelve la config PedidoClientes de una asignación
const getConfig = (asignacion) => {
    return asignacion?.pedido_cliente_config || {}
}

// ✅ Devuelve destino en mayúsculas
const getDestinoMayusculas = (asignacion) => {
    const destino = getConfig(asignacion).Destino
    return destino ? destino.toUpperCase() : null
}

// ✅ Determinar tipo de ubicación (Ciudad o Provincia)
const getTipoUbicacion = (asignacion) => {
    const config = getConfig(asignacion)
    const ciudad = config.Ciudad === 'Sí' || config.Ciudad === 1 || config.Ciudad === true
    const provincia = config.Provincia === 'Sí' || config.Provincia === 1 || config.Provincia === true
    if (ciudad) return 'Ciudad'
    if (provincia) return 'Provincia'
    return null
}

// ✅ Clase de color según el tipo
const getTipoUbicacionClase = (tipo) => {
    if (tipo === 'Ciudad') return 'bg-blue-100 text-blue-700 border-blue-200'
    if (tipo === 'Provincia') return 'bg-green-100 text-green-700 border-green-200'
    return 'bg-gray-100 text-gray-500 border-gray-200'
}

// ✅ Ícono según el tipo
const getTipoUbicacionIcono = (tipo) => {
    if (tipo === 'Ciudad') return 'fas fa-city'
    if (tipo === 'Provincia') return 'fas fa-tree'
    return 'fas fa-minus'
}

// ==================== FUNCIONES ====================
const aplicarFiltros = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/operadores-pedidoclientes', {
        search: search.value || undefined,
        estado: estado.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltros = () => {
    search.value = ''
    estado.value = ''
    aplicarFiltros()
}

let timeout
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => aplicarFiltros(), 500)
})

watch(estado, () => aplicarFiltros())

const nuevoOperador = () => {
    operadorSeleccionado.value = null
    asignacionSeleccionada.value = null
    editando.value = false
    modalOpen.value = true
}

// ✅ Editar una asignación específica (no todo el operador)
const editarAsignacion = (operador, asignacion) => {
    operadorSeleccionado.value = operador
    asignacionSeleccionada.value = asignacion
    editando.value = true
    modalOpen.value = true
}

const recargarDatos = () => aplicarFiltros()

const estadoTexto = (activo) => activo === 0 ? 'Activo' : 'Inactivo'
const estadoClase = (activo) => activo === 0
    ? 'bg-emerald-100 text-emerald-700'
    : 'bg-red-100 text-red-700'

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    clearTimeout(timeout)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- HEADER -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-users text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Operadores PedidoClientes</h1>
                            <p class="text-xs text-gray-500">Usuarios con permisos para realizar pedidos</p>
                        </div>
                    </div>
                    <button @click="nuevoOperador" 
                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition">
                        <i class="fas fa-plus text-[10px]"></i> Nuevo Operador
                    </button>
                </div>

                <!-- FILTROS -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[140px] max-w-[240px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar</label>
                            <input type="text" v-model="search" placeholder="Nombre, CI o usuario..."
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Estado</label>
                            <select v-model="estado" class="w-28 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Todos</option>
                                <option value="0">Activos</option>
                                <option value="1">Inactivos</option>
                            </select>
                        </div>
                        <div class="flex gap-1.5 ml-auto">
                            <button @click="limpiarFiltros" 
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-eraser text-[10px]"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABLA -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- ==================== VISTA MÓVIL ==================== -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="operador in operadores.data" :key="operador.IdOperador" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <!-- Cabecera del operador -->
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-user text-primary-500 text-[10px]"></i>
                                            <span class="text-xs font-semibold text-gray-800 truncate">
                                                {{ operador.identificador?.Nombre || '-' }}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-0.5 mt-1 text-[9px]">
                                            <div><span class="text-gray-500">CI:</span> <span class="font-mono">{{ operador.identificador?.CI_NIT || '-' }}</span></div>
                                            <div><span class="text-gray-500">Usuario:</span> <span>{{ operador.NombreAcceso }}</span></div>
                                        </div>
                                    </div>
                                    <span class="px-1.5 py-0.5 text-[7px] rounded-full" :class="estadoClase(operador.ActivoInactivo)">
                                        {{ estadoTexto(operador.ActivoInactivo) }}
                                    </span>
                                </div>

                                <!-- Asignaciones (N por operador) -->
                                <div v-if="getAsignaciones(operador).length > 0" class="space-y-1.5 pt-2 border-t border-gray-200">
                                    <div class="text-[9px] font-semibold text-gray-600 flex items-center gap-1">
                                        <i class="fas fa-store text-primary-500"></i>
                                        Sucursales asignadas ({{ getAsignaciones(operador).length }})
                                    </div>
                                    <div v-for="asig in getAsignaciones(operador)" :key="asig.IdSucursalDB"
                                        class="bg-white border border-gray-200 rounded-md p-1.5">
                                        <div class="flex justify-between items-start gap-1">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-[10px] font-medium text-gray-800 truncate">
                                                    <i class="fas fa-store text-gray-400 text-[8px] mr-1"></i>
                                                    {{ asig.sucursal?.Nombre || 'Sucursal' }}
                                                </div>
                                                <div class="flex items-center gap-1 flex-wrap mt-1">
                                                    <span v-if="getTipoUbicacion(asig)"
                                                        class="inline-flex items-center gap-0.5 text-[8px] px-1.5 py-0.5 rounded-full border font-medium"
                                                        :class="getTipoUbicacionClase(getTipoUbicacion(asig))">
                                                        <i :class="getTipoUbicacionIcono(getTipoUbicacion(asig))" class="text-[7px]"></i>
                                                        {{ getTipoUbicacion(asig) }}
                                                    </span>
                                                    <span v-else class="text-[8px] text-gray-400 italic">Sin ubicación</span>
                                                </div>
                                                <div v-if="getDestinoMayusculas(asig)" class="text-[9px] text-gray-600 mt-0.5">
                                                    <i class="fas fa-location-dot text-orange-500 mr-0.5 text-[8px]"></i>
                                                    <span class="font-medium">{{ getDestinoMayusculas(asig) }}</span>
                                                </div>
                                            </div>
                                            <button @click="editarAsignacion(operador, asig)" 
                                                class="text-primary-600 hover:text-primary-800 text-[10px] p-1 rounded hover:bg-primary-50 flex-shrink-0">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-[9px] text-gray-400 italic pt-2 border-t border-gray-200">
                                    Sin sucursales asignadas en esta empresa
                                </div>
                            </div>
                            <div v-if="operadores.data.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-users text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay operadores registrados</span>
                            </div>
                        </div>

                        <!-- ==================== VISTA ESCRITORIO ==================== -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">ID</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">CI/NIT</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Nombre</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Usuario</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Sucursal(es)</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Ubicación / Destino</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase w-24">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template v-for="operador in operadores.data" :key="operador.IdOperador">
                                    <!-- Si no tiene asignaciones, mostrar fila con aviso -->
                                    <tr v-if="getAsignaciones(operador).length === 0" class="hover:bg-gray-50 transition">
                                        <td class="px-3 py-1.5 text-xs text-gray-500">{{ operador.IdOperador }}</td>
                                        <td class="px-3 py-1.5 text-xs font-mono text-gray-600">{{ operador.identificador?.CI_NIT || '-' }}</td>
                                        <td class="px-3 py-1.5 text-xs text-gray-700">
                                            <i class="fas fa-user text-gray-400 mr-1 text-[8px]"></i>
                                            {{ operador.identificador?.Nombre || '-' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ operador.NombreAcceso }}</td>
                                        <td colspan="2" class="px-3 py-1.5 text-xs text-gray-400 italic text-center">
                                            Sin sucursales en esta empresa
                                        </td>
                                        <td class="px-3 py-1.5 text-center">
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(operador.ActivoInactivo)">
                                                {{ estadoTexto(operador.ActivoInactivo) }}
                                            </span>
                                        </td>
                                    </tr>

                                    <!-- Fila por cada asignación -->
                                    <tr v-for="(asig, idx) in getAsignaciones(operador)" 
                                        :key="operador.IdOperador + '-' + asig.IdSucursalDB"
                                        class="hover:bg-gray-50 transition"
                                        :class="idx > 0 ? 'border-t border-dashed border-gray-200' : ''">
                                        
                                        <!-- Solo mostrar datos del operador en la primera fila -->
                                        <template v-if="idx === 0">
                                            <td class="px-3 py-1.5 text-xs text-gray-500" :rowspan="getAsignaciones(operador).length">
                                                {{ operador.IdOperador }}
                                            </td>
                                            <td class="px-3 py-1.5 text-xs font-mono text-gray-600" :rowspan="getAsignaciones(operador).length">
                                                {{ operador.identificador?.CI_NIT || '-' }}
                                            </td>
                                            <td class="px-3 py-1.5 text-xs text-gray-700" :rowspan="getAsignaciones(operador).length">
                                                <i class="fas fa-user text-gray-400 mr-1 text-[8px]"></i>
                                                {{ operador.identificador?.Nombre || '-' }}
                                            </td>
                                            <td class="px-3 py-1.5 text-xs text-gray-600" :rowspan="getAsignaciones(operador).length">
                                                {{ operador.NombreAcceso }}
                                            </td>
                                        </template>

                                        <!-- Sucursal -->
                                        <td class="px-3 py-1.5 text-xs text-gray-700">
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="flex items-center gap-1">
                                                    <i class="fas fa-store text-primary-500 text-[9px]"></i>
                                                    {{ asig.sucursal?.Nombre || '-' }}
                                                </span>
                                                <button @click="editarAsignacion(operador, asig)" 
                                                    class="text-primary-600 hover:text-primary-800 p-1 rounded hover:bg-primary-50"
                                                    title="Editar configuración de esta sucursal">
                                                    <i class="fas fa-edit text-[10px]"></i>
                                                </button>
                                            </div>
                                        </td>

                                        <!-- Ubicación + Destino -->
                                        <td class="px-3 py-1.5 text-xs">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span v-if="getTipoUbicacion(asig)"
                                                    class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full border font-medium"
                                                    :class="getTipoUbicacionClase(getTipoUbicacion(asig))">
                                                    <i :class="getTipoUbicacionIcono(getTipoUbicacion(asig))" class="text-[9px]"></i>
                                                    {{ getTipoUbicacion(asig) }}
                                                </span>
                                                <span v-else class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-400 border border-gray-200">
                                                    <i class="fas fa-minus text-[9px]"></i>
                                                    Sin asignar
                                                </span>
                                                <span v-if="getDestinoMayusculas(asig)" class="inline-flex items-center gap-1 text-[11px] text-gray-700">
                                                    <i class="fas fa-location-dot text-orange-500 text-[10px]"></i>
                                                    <span class="font-medium">{{ getDestinoMayusculas(asig) }}</span>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Estado (solo en primera fila) -->
                                        <template v-if="idx === 0">
                                            <td class="px-3 py-1.5 text-center" :rowspan="getAsignaciones(operador).length">
                                                <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(operador.ActivoInactivo)">
                                                    {{ estadoTexto(operador.ActivoInactivo) }}
                                                </span>
                                            </td>
                                        </template>
                                    </tr>
                                </template>

                                <tr v-if="operadores.data.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-users text-2xl mb-1 block"></i>
                                        No hay operadores registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINACIÓN -->
                    <div v-if="operadores.links && operadores.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <div class="text-[10px] text-gray-500">
                                Mostrando {{ operadores.from || 0 }} a {{ operadores.to || 0 }} de {{ operadores.total || 0 }}
                            </div>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link v-for="link in operadores.links" :key="link.label" :href="link.url || '#'" 
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

        <!-- MODAL -->
        <ModalOperadorPedidoClientes
            v-model="modalOpen"
            :operador="operadorSeleccionado"
            :asignacion="asignacionSeleccionada"
            :sucursales="sucursales"
            :identificadores="identificadores"
            :editando="editando"
            @saved="recargarDatos"
        />
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button { font-size: 13px !important; }
}
.sticky { position: sticky !important; top: 0 !important; z-index: 10 !important; }
.overflow-y-auto::-webkit-scrollbar { width: 4px; }
.overflow-y-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
</style>