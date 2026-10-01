<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onUnmounted, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    grupos: Object,
    filtroEstado: String,
    buscar: String,
})

// =============================================
// ESTADO
// =============================================
const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')
const gruposData = ref(props.grupos)

// ✅ NUEVO: Buscador de clientes
const busquedaCliente = ref('')
const resultadosCliente = ref([])
const buscandoCliente = ref(false)
const mostrarResultados = ref(false)
const dropdownRef = ref(null)

// =============================================
// COMPUTADOS
// =============================================
const gruposLista = computed(() => gruposData.value?.data || [])
const hayGrupos = computed(() => gruposLista.value.length > 0)

// =============================================
// ✅ BUSCADOR DE CLIENTES
// =============================================
let timeoutCliente
const buscarCliente = () => {
    clearTimeout(timeoutCliente)

    const q = busquedaCliente.value.trim()

    if (q.length < 2) {
        resultadosCliente.value = []
        mostrarResultados.value = false
        return
    }

    timeoutCliente = setTimeout(async () => {
        buscandoCliente.value = true
        mostrarResultados.value = true

        try {
            const response = await axios.get(
                '/operacion/pedidos/clientes-mayoristas/grupos-clientes/buscar-cliente',
                { params: { q } }
            )

            if (response.data.success) {
                resultadosCliente.value = response.data.data
            } else {
                resultadosCliente.value = []
            }
        } catch (error) {
            console.error('Error buscando cliente:', error)
            resultadosCliente.value = []
        } finally {
            buscandoCliente.value = false
        }
    }, 400)
}

const limpiarBusquedaCliente = () => {
    busquedaCliente.value = ''
    resultadosCliente.value = []
    mostrarResultados.value = false
}

const irAlGrupoDelCliente = (resultado) => {
    if (!resultado.IdGrupoCliente) {
        toast?.warning('Sin grupo', `${resultado.Nombre} no está asignado a ningún grupo`)
        return
    }

    limpiarBusquedaCliente()
    router.get(`/operacion/pedidos/clientes-mayoristas/grupos-clientes/${resultado.IdGrupoCliente}/edit`)
}

// Cerrar dropdown al hacer click afuera
const cerrarDropdownSiAfuera = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        mostrarResultados.value = false
    }
}

// =============================================
// ACCIONES
// =============================================
const aplicarFiltros = () => {
    const params = {
        estado: estadoFiltro.value || undefined,
        buscar: buscador.value || undefined,
    }

    router.get('/operacion/pedidos/clientes-mayoristas/grupos-clientes', params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            gruposData.value = page.props.grupos
        }
    })
}

let timeoutBuscador
const buscarGrupos = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

// =============================================
// CREAR GRUPO
// =============================================
const irACrear = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/grupos-clientes/create')
}

// =============================================
// EDITAR GRUPO
// =============================================
const irAEditar = (grupo) => {
    router.get(`/operacion/pedidos/clientes-mayoristas/grupos-clientes/${grupo.IdGrupoCliente}/edit`)
}

// =============================================
// CAMBIAR ESTADO
// =============================================
const cambiando = ref({})

const cambiarEstado = async (grupo) => {
    const accion = grupo.ActivoInactivo === 1 ? 'desactivar' : 'activar'

    if (!confirm(`¿Estás seguro de ${accion} el grupo "${grupo.Nombre}"?`)) {
        return
    }

    cambiando.value[grupo.IdGrupoCliente] = true

    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/grupos-clientes/${grupo.IdGrupoCliente}/cambiar-estado`
        )

        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            aplicarFiltros()
        } else {
            toast?.error('Error', response.data.message || 'Error al cambiar estado')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al cambiar estado')
    } finally {
        cambiando.value[grupo.IdGrupoCliente] = false
    }
}

// =============================================
// ELIMINAR GRUPO
// =============================================
const eliminarGrupo = async (grupo) => {
    if (!confirm(`¿Estás seguro de eliminar el grupo "${grupo.Nombre}"?\n\nEsta acción eliminará también:\n- Los clientes asignados\n- Los precios configurados\n- Los mínimos configurados`)) {
        return
    }

    try {
        const response = await axios.delete(
            `/operacion/pedidos/clientes-mayoristas/grupos-clientes/${grupo.IdGrupoCliente}`
        )

        if (response.data.success) {
            toast?.success('Éxito', 'Grupo eliminado correctamente')
            aplicarFiltros()
        } else {
            toast?.error('Error', response.data.message || 'Error al eliminar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al eliminar')
    }
}

// =============================================
// URL CON FILTROS (para paginación)
// =============================================
const construirUrlConFiltros = (url) => {
    if (!url) return '#'

    try {
        const urlObj = new URL(url, window.location.origin)
        const params = new URLSearchParams(urlObj.search)

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
// UTILIDADES
// =============================================
const getEstadoBadge = (activo) => {
    return activo === 1
        ? 'bg-green-100 text-green-800 border-green-200'
        : 'bg-gray-100 text-gray-600 border-gray-200'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Activo' : 'Inactivo'
}

const getEstadoIcono = (activo) => {
    return activo === 1 ? 'fas fa-check-circle' : 'fas fa-pause-circle'
}

// =============================================
// CICLO DE VIDA
// =============================================
const isMobile = ref(false)
const handleResize = () => { isMobile.value = window.innerWidth < 768 }

onMounted(() => {
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', cerrarDropdownSiAfuera)
    handleResize()
    gruposData.value = props.grupos
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', cerrarDropdownSiAfuera)
    clearTimeout(timeoutBuscador)
    clearTimeout(timeoutCliente)
})

watch(() => props.grupos, (newVal) => {
    gruposData.value = newVal
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
                            <i class="fas fa-layer-group text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Grupos de Clientes</h1>
                            <p class="text-[10px] text-gray-500">
                                Agrupa clientes que comparten productos y precios
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <a
                            href="/operacion/pedidos/clientes-mayoristas/grupos-clientes/exportar-excel"
                            target="_blank"
                            class="flex-1 sm:flex-initial bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1.5 transition"
                        >
                            <i class="fas fa-file-excel text-[10px]"></i>
                            <span>Exportar Excel</span>
                        </a>
                        <button
                            @click="irACrear"
                            class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1.5 transition"
                        >
                            <i class="fas fa-plus text-[10px]"></i>
                            <span>Nuevo Grupo</span>
                        </button>
                    </div>
                </div>

                <!-- INFO -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4 flex items-start gap-2">
                    <i class="fas fa-info-circle text-blue-500 text-sm flex-shrink-0 mt-0.5"></i>
                    <div class="text-xs text-blue-700">
                        <p class="font-medium mb-0.5">¿Cómo funciona?</p>
                        <p>Crea un grupo, asígnale clientes (operadores PedidoClientes), define los <strong>precios</strong> y los <strong>mínimos por grupo de análisis</strong>.</p>
                    </div>
                </div>

                <!-- ✅ BUSCADOR DE CLIENTES -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4 border border-purple-200" ref="dropdownRef">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-6 h-6 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-search text-purple-600 text-[10px]"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800">Buscar Cliente</p>
                            <p class="text-[9px] text-gray-500">Encuentra en qué grupo está un cliente</p>
                        </div>
                    </div>

                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input
                            type="text"
                            v-model="busquedaCliente"
                            @input="buscarCliente"
                            @focus="busquedaCliente.length >= 2 && (mostrarResultados = true)"
                            placeholder="Buscar por nombre o CI/NIT (mín. 2 caracteres)..."
                            class="w-full border border-gray-300 rounded-lg pl-9 pr-9 py-2 text-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 outline-none"
                        />
                        <button
                            v-if="busquedaCliente"
                            @click="limpiarBusquedaCliente"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>

                    <!-- Dropdown de resultados -->
                    <div
                        v-if="mostrarResultados"
                        class="mt-2 bg-white border border-gray-200 rounded-lg shadow-lg max-h-72 overflow-y-auto"
                    >
                        <!-- Cargando -->
                        <div v-if="buscandoCliente" class="p-3 text-center text-xs text-gray-500">
                            <i class="fas fa-spinner fa-spin mr-1"></i>
                            Buscando...
                        </div>

                        <!-- Sin resultados -->
                        <div v-else-if="resultadosCliente.length === 0" class="p-3 text-center text-xs text-gray-400">
                            <i class="fas fa-search mr-1"></i>
                            No se encontraron clientes
                        </div>

                        <!-- Resultados -->
                        <div v-else class="divide-y divide-gray-100">
                            <div
                                v-for="c in resultadosCliente"
                                :key="c.IdIdentificador"
                                @click="irAlGrupoDelCliente(c)"
                                class="px-3 py-2 hover:bg-purple-50 cursor-pointer transition flex items-center justify-between gap-2"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium text-gray-800 truncate">
                                        {{ c.Nombre }}
                                    </p>
                                    <p class="text-[9px] text-gray-500 font-mono">
                                        CI/NIT: {{ c.CI_NIT || '-' }}
                                    </p>
                                </div>
                                <div class="flex-shrink-0">
                                    <span
                                        v-if="c.IdGrupoCliente"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-medium"
                                        :class="c.GrupoActivo
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-amber-100 text-amber-700'"
                                    >
                                        <i class="fas fa-layer-group text-[8px]"></i>
                                        {{ c.NombreGrupo }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-medium bg-gray-100 text-gray-500"
                                    >
                                        <i class="fas fa-minus-circle text-[8px]"></i>
                                        Sin grupo
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FILTROS -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select
                                v-model="estadoFiltro"
                                @change="aplicarFiltros"
                                class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-32 sm:w-36 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                                <option value="">Todos</option>
                                <option value="activos">Activos</option>
                                <option value="inactivos">Inactivos</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-1 flex-1 min-w-[180px] max-w-[300px]">
                            <div class="relative flex-1">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                <input
                                    type="text"
                                    v-model="buscador"
                                    @input="buscarGrupos"
                                    placeholder="Buscar grupo..."
                                    class="w-full border border-gray-300 rounded-lg pl-7 pr-7 py-1 text-xs focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                >
                                <button
                                    v-if="buscador"
                                    @click="limpiarBusqueda"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div v-if="buscador" class="text-[10px] text-gray-500 ml-auto">
                            <span class="font-semibold">{{ buscador }}</span>
                            <span class="ml-2">({{ grupos?.total || 0 }} resultados)</span>
                        </div>
                    </div>
                </div>

                <!-- LISTA DE GRUPOS -->
                <div v-if="hayGrupos" class="space-y-3">

                    <!-- DESKTOP: Tabla -->
                    <div v-if="!isMobile" class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[10px] font-medium text-gray-500 uppercase">Grupo</th>
                                    <th class="px-3 py-2 text-center text-[10px] font-medium text-gray-500 uppercase">Clientes</th>
                                    <th class="px-3 py-2 text-center text-[10px] font-medium text-gray-500 uppercase">Productos</th>
                                    <th class="px-3 py-2 text-center text-[10px] font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="grupo in gruposLista"
                                    :key="grupo.IdGrupoCliente"
                                    class="hover:bg-gray-50 transition"
                                >
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-layer-group text-primary-600 text-xs"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold text-gray-800 truncate">
                                                    {{ grupo.Nombre }}
                                                </p>
                                                <p v-if="grupo.Descripcion" class="text-[10px] text-gray-400 truncate">
                                                    {{ grupo.Descripcion }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium"
                                            :class="grupo.TotalClientes > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'">
                                            <i class="fas fa-users text-[9px]"></i>
                                            {{ grupo.TotalClientes }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium"
                                            :class="grupo.TotalProductos > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                                            <i class="fas fa-box text-[9px]"></i>
                                            {{ grupo.TotalProductos }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium border"
                                            :class="getEstadoBadge(grupo.ActivoInactivo)">
                                            <i :class="getEstadoIcono(grupo.ActivoInactivo)" class="text-[9px]"></i>
                                            {{ grupo.EstadoTexto }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button
                                                @click="irAEditar(grupo)"
                                                class="text-primary-600 hover:text-primary-800 transition p-1.5 hover:bg-primary-50 rounded"
                                                title="Editar"
                                            >
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button
                                                @click="cambiarEstado(grupo)"
                                                :disabled="cambiando[grupo.IdGrupoCliente]"
                                                class="transition p-1.5 rounded disabled:opacity-50"
                                                :class="grupo.ActivoInactivo === 1
                                                    ? 'text-amber-600 hover:text-amber-800 hover:bg-amber-50'
                                                    : 'text-green-600 hover:text-green-800 hover:bg-green-50'"
                                                :title="grupo.ActivoInactivo === 1 ? 'Desactivar' : 'Activar'"
                                            >
                                                <i v-if="cambiando[grupo.IdGrupoCliente]" class="fas fa-spinner fa-spin text-xs"></i>
                                                <i v-else :class="grupo.ActivoInactivo === 1 ? 'fas fa-pause-circle text-xs' : 'fas fa-play-circle text-xs'"></i>
                                            </button>
                                            <button
                                                @click="eliminarGrupo(grupo)"
                                                class="text-red-500 hover:text-red-700 transition p-1.5 hover:bg-red-50 rounded"
                                                title="Eliminar"
                                            >
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- MÓVIL: Tarjetas -->
                    <div v-else class="space-y-2">
                        <div
                            v-for="grupo in gruposLista"
                            :key="grupo.IdGrupoCliente"
                            class="bg-white rounded-xl shadow-sm p-3 border border-gray-100"
                        >
                            <div class="flex justify-between items-start gap-2 mb-2">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <div class="w-9 h-9 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-layer-group text-primary-600 text-sm"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-bold text-gray-800 truncate">
                                            {{ grupo.Nombre }}
                                        </p>
                                        <p v-if="grupo.Descripcion" class="text-[10px] text-gray-400 truncate">
                                            {{ grupo.Descripcion }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="px-2 py-0.5 text-[9px] rounded-full font-medium border flex-shrink-0"
                                    :class="getEstadoBadge(grupo.ActivoInactivo)"
                                >
                                    {{ grupo.EstadoTexto }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 mb-3 text-center">
                                <div class="bg-blue-50 rounded-lg p-1.5">
                                    <p class="text-[9px] text-blue-600 font-medium uppercase">Clientes</p>
                                    <p class="text-sm font-bold text-blue-700">{{ grupo.TotalClientes }}</p>
                                </div>
                                <div class="bg-emerald-50 rounded-lg p-1.5">
                                    <p class="text-[9px] text-emerald-600 font-medium uppercase">Productos</p>
                                    <p class="text-sm font-bold text-emerald-700">{{ grupo.TotalProductos }}</p>
                                </div>
                            </div>

                            <div class="flex gap-1.5 pt-2 border-t border-gray-100">
                                <button
                                    @click="irAEditar(grupo)"
                                    class="flex-1 bg-primary-600 hover:bg-primary-700 text-white rounded-md py-1.5 text-[10px] font-medium flex items-center justify-center gap-1"
                                >
                                    <i class="fas fa-edit"></i>
                                    Editar
                                </button>
                                <button
                                    @click="cambiarEstado(grupo)"
                                    :disabled="cambiando[grupo.IdGrupoCliente]"
                                    class="px-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md py-1.5 text-[10px] disabled:opacity-50"
                                >
                                    <i v-if="cambiando[grupo.IdGrupoCliente]" class="fas fa-spinner fa-spin"></i>
                                    <i v-else :class="grupo.ActivoInactivo === 1 ? 'fas fa-pause-circle' : 'fas fa-play-circle'"></i>
                                </button>
                                <button
                                    @click="eliminarGrupo(grupo)"
                                    class="px-3 bg-red-50 hover:bg-red-100 text-red-600 rounded-md py-1.5 text-[10px]"
                                >
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- PAGINACIÓN -->
                    <div v-if="gruposData?.links && gruposData.links.length > 3" class="bg-white rounded-xl shadow-sm px-3 py-2 flex flex-col sm:flex-row justify-between items-center gap-2 border border-gray-200">
                        <p class="text-[10px] text-gray-500">
                            Mostrando {{ gruposData.from || 0 }} - {{ gruposData.to || 0 }} de {{ gruposData.total || 0 }}
                        </p>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link
                                v-for="link in gruposData.links"
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
                <div v-else class="bg-white rounded-xl shadow-sm p-8 sm:p-12 text-center">
                    <div class="w-16 h-16 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-layer-group text-primary-400 text-3xl"></i>
                    </div>
                    <h3 class="text-base font-semibold text-gray-700">No hay grupos de clientes</h3>
                    <p class="text-xs text-gray-400 mt-2 max-w-sm mx-auto">
                        <span v-if="buscador">No se encontraron grupos con "{{ buscador }}"</span>
                        <span v-else>Crea tu primer grupo para agrupar clientes y configurar precios en masa</span>
                    </p>
                    <button
                        v-if="!buscador"
                        @click="irACrear"
                        class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-medium transition"
                    >
                        <i class="fas fa-plus text-xs"></i>
                        Crear primer grupo
                    </button>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-toast {
    max-width: 90%;
    z-index: 9999;
}

@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}
</style>