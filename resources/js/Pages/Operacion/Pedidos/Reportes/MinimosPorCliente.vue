<script setup>
import { ref, computed, watch, inject, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfigurarMinimosModal from '@/Pages/Operacion/ClientesMayoristas/PedidosClientes/ConfigurarMinimosModal.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    clientes: { type: Array, default: () => [] },
    sucursales: { type: Array, default: () => [] },
    grupos: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
    contadores: { type: Object, default: () => ({
        total_clientes: 0,
        total_grupos: 0,
        total_productos: 0,
        total_sin_minimo: 0,
        total_sin_precio: 0,
    }) }
})

// =============================================
// AUTOCOMPLETE CLIENTE
// =============================================
const clienteSeleccionado = ref(props.filtros?.identificador_id || null)
const textoBusquedaCliente = ref('')
const mostrarSugerencias = ref(false)
const sugerenciasClientes = ref([])
const buscandoClientes = ref(false)
let timeoutBusqueda = null

// Sucursal y filtros
const sucursalId = ref(props.filtros?.sucursal_id || '')
const idGrupo = ref(props.filtros?.id_grupo || '')
const soloSinPrecio = ref(props.filtros?.solo_sin_precio === true || props.filtros?.solo_sin_precio === 'true')
const soloSinMinimo = ref(props.filtros?.solo_sin_minimo === true || props.filtros?.solo_sin_minimo === 'true')

// Nombre del cliente seleccionado (para mostrar en el input)
const clienteSeleccionadoNombre = ref('')

// =============================================
// BUSCAR CLIENTES
// =============================================
const buscarClientes = async () => {
    buscandoClientes.value = true
    try {
        const params = new URLSearchParams({
            q: textoBusquedaCliente.value || '',
        })
        if (sucursalId.value) params.append('sucursal_id', sucursalId.value)

        const res = await fetch(`/operacion/pedidos/reportes/minimos-por-cliente/buscar-clientes?${params}`)
        const data = await res.json()
        sugerenciasClientes.value = data.clientes || []
    } catch (e) {
        sugerenciasClientes.value = []
    } finally {
        buscandoClientes.value = false
    }
}

watch(textoBusquedaCliente, () => {
    if (!textoBusquedaCliente.value) {
        clienteSeleccionado.value = null
    }
    clearTimeout(timeoutBusqueda)
    timeoutBusqueda = setTimeout(buscarClientes, 300)
})

const seleccionarCliente = (cli) => {
    clienteSeleccionado.value = cli.IdIdentificador
    clienteSeleccionadoNombre.value = cli.Nombre
    textoBusquedaCliente.value = cli.Nombre
    mostrarSugerencias.value = false
    aplicarFiltros()
}

const limpiarCliente = () => {
    clienteSeleccionado.value = null
    clienteSeleccionadoNombre.value = ''
    textoBusquedaCliente.value = ''
    sugerenciasClientes.value = []
    aplicarFiltros()
}

const manejarBlur = () => {
    setTimeout(() => {
        mostrarSugerencias.value = false
    }, 200)
}

// =============================================
// EXPANSIÓN DE CLIENTES
// =============================================
const clientesExpandidos = ref({})

const toggleCliente = (id) => {
    clientesExpandidos.value[id] = !clientesExpandidos.value[id]
}

const estaExpandido = (id) => {
    return !!clientesExpandidos.value[id]
}

const expandirTodos = () => {
    const nuevo = {}
    props.clientes.forEach(c => {
        nuevo[c.IdIdentificador] = true
    })
    clientesExpandidos.value = nuevo
}

const contraerTodos = () => {
    clientesExpandidos.value = {}
}

// =============================================
// MODAL DE MÍNIMOS
// =============================================
const modalMinimosVisible = ref(false)
const clienteParaMinimos = ref(null)

const abrirModalMinimos = (cliente) => {
    clienteParaMinimos.value = cliente
    modalMinimosVisible.value = true
}

const cerrarModalMinimos = () => {
    modalMinimosVisible.value = false
    clienteParaMinimos.value = null
}

const onMinimosGuardados = () => {
    toast?.success('Éxito', 'Mínimos guardados correctamente')
    aplicarFiltros()
}

// =============================================
// FILTROS
// =============================================
const aplicarFiltros = () => {
    const params = {}
    if (clienteSeleccionado.value) params.identificador_id = clienteSeleccionado.value
    if (sucursalId.value) params.sucursal_id = sucursalId.value
    if (idGrupo.value) params.id_grupo = idGrupo.value
    if (soloSinPrecio.value) params.solo_sin_precio = true
    if (soloSinMinimo.value) params.solo_sin_minimo = true

    router.get('/operacion/pedidos/reportes/minimos-por-cliente', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const limpiarFiltros = () => {
    clienteSeleccionado.value = null
    clienteSeleccionadoNombre.value = ''
    textoBusquedaCliente.value = ''
    sucursalId.value = ''
    idGrupo.value = ''
    soloSinPrecio.value = false
    soloSinMinimo.value = false
    aplicarFiltros()
}

watch([sucursalId, idGrupo, soloSinPrecio, soloSinMinimo], () => {
    aplicarFiltros()
})

// =============================================
// EXPORTACIONES
// =============================================
const exportar = (tipo) => {
    const params = new URLSearchParams()
    if (clienteSeleccionado.value) params.append('identificador_id', clienteSeleccionado.value)
    if (sucursalId.value) params.append('sucursal_id', sucursalId.value)
    if (idGrupo.value) params.append('id_grupo', idGrupo.value)
    if (soloSinPrecio.value) params.append('solo_sin_precio', '1')
    if (soloSinMinimo.value) params.append('solo_sin_minimo', '1')

    const url = tipo === 'pdf'
        ? `/operacion/pedidos/reportes/minimos-por-cliente/exportar-pdf?${params.toString()}`
        : `/operacion/pedidos/reportes/minimos-por-cliente/exportar-excel?${params.toString()}`

    window.open(url, '_blank')
}

// =============================================
// LIFECYCLE
// =============================================
onMounted(() => {
    if (clienteSeleccionado.value) {
        // Buscar el nombre del cliente para mostrarlo
        fetch(`/operacion/pedidos/reportes/minimos-por-cliente/buscar-clientes?q=`)
            .then(res => res.json())
            .then(data => {
                const cli = (data.clientes || []).find(c => c.IdIdentificador == clienteSeleccionado.value)
                if (cli) {
                    clienteSeleccionadoNombre.value = cli.Nombre
                    textoBusquedaCliente.value = cli.Nombre
                }
            })
    }

    if (props.clientes.length > 0) {
        expandirTodos()
    }
})

onUnmounted(() => {
    clearTimeout(timeoutBusqueda)
})

// =============================================
// UTILIDADES
// =============================================
const formatearNumero = (valor, decimales = 0) => {
    if (valor === null || valor === undefined) return '0'
    return Number(valor).toFixed(decimales).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- HEADER -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-list-alt text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Mínimos y Precios por Cliente</h1>
                            <p class="text-xs text-gray-500">
                                Visualiza y configura los mínimos y precios por cliente
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="exportar('pdf')"
                            :disabled="clientes.length === 0"
                            class="px-3 py-1.5 bg-red-600 text-white rounded-md text-xs font-medium hover:bg-red-700 transition flex items-center gap-1.5 disabled:opacity-50"
                        >
                            <i class="fas fa-file-pdf text-[10px]"></i>
                            PDF
                        </button>
                        <button
                            @click="exportar('excel')"
                            :disabled="clientes.length === 0"
                            class="px-3 py-1.5 bg-green-600 text-white rounded-md text-xs font-medium hover:bg-green-700 transition flex items-center gap-1.5 disabled:opacity-50"
                        >
                            <i class="fas fa-file-excel text-[10px]"></i>
                            Excel
                        </button>
                    </div>
                </div>

                <!-- CONTADORES -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 mb-4">
                    <div class="bg-white rounded-lg shadow-sm p-2.5 border-l-4 border-primary-500">
                        <div class="text-[10px] text-gray-500 uppercase">Clientes</div>
                        <div class="text-lg font-bold text-primary-700">{{ contadores.total_clientes }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-2.5 border-l-4 border-indigo-500">
                        <div class="text-[10px] text-gray-500 uppercase">Grupos</div>
                        <div class="text-lg font-bold text-indigo-700">{{ contadores.total_grupos }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-2.5 border-l-4 border-purple-500">
                        <div class="text-[10px] text-gray-500 uppercase">Productos</div>
                        <div class="text-lg font-bold text-purple-700">{{ contadores.total_productos }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-2.5 border-l-4 border-yellow-500">
                        <div class="text-[10px] text-gray-500 uppercase">Sin Mínimo</div>
                        <div class="text-lg font-bold text-yellow-600">{{ contadores.total_sin_minimo }}</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-2.5 border-l-4 border-orange-500">
                        <div class="text-[10px] text-gray-500 uppercase">Sin Precio</div>
                        <div class="text-lg font-bold text-orange-600">{{ contadores.total_sin_precio }}</div>
                    </div>
                </div>

                <!-- FILTROS -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- AUTOCOMPLETE CLIENTE -->
                        <div class="flex-1 min-w-[220px] max-w-[320px] relative">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Cliente</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[11px]"></i>
                                <input
                                    type="text"
                                    v-model="textoBusquedaCliente"
                                    @focus="mostrarSugerencias = true"
                                    @blur="manejarBlur"
                                    placeholder="Todos los clientes..."
                                    class="w-full border border-gray-300 rounded-md pl-7 pr-7 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                />
                                <button
                                    v-if="textoBusquedaCliente"
                                    @click="limpiarCliente"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[11px]"></i>
                                </button>
                                <i v-else-if="buscandoClientes" class="fas fa-spinner fa-spin absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-[11px]"></i>
                            </div>

                            <!-- Sugerencias -->
                            <div
                                v-if="mostrarSugerencias && sugerenciasClientes.length > 0"
                                class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-56 overflow-y-auto"
                            >
                                <button
                                    v-for="cli in sugerenciasClientes"
                                    :key="cli.IdIdentificador"
                                    type="button"
                                    @mousedown.prevent="seleccionarCliente(cli)"
                                    class="w-full text-left px-3 py-1.5 text-xs hover:bg-primary-50 flex items-center justify-between gap-2"
                                >
                                    <span class="text-gray-700">
                                        <i class="fas fa-user text-gray-400 text-[10px] mr-1.5"></i>
                                        {{ cli.Nombre }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 font-mono">{{ cli.CI_NIT }}</span>
                                </button>
                            </div>

                            <div
                                v-else-if="mostrarSugerencias && textoBusquedaCliente && !buscandoClientes"
                                class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg p-3 text-center"
                            >
                                <p class="text-[11px] text-gray-400">Sin resultados</p>
                            </div>
                        </div>

                        <!-- Sucursal -->
                        <div class="w-[180px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Sucursal</label>
                            <select
                                v-model="sucursalId"
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                                <option value="">Todas</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">
                                    {{ s.nombre }}
                                </option>
                            </select>
                        </div>

                        <!-- Grupo -->
                        <div class="w-[180px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Grupo</label>
                            <select
                                v-model="idGrupo"
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                                <option value="">Todos</option>
                                <option v-for="g in grupos" :key="g.id" :value="g.id">
                                    {{ g.nombre }}
                                </option>
                            </select>
                        </div>

                        <!-- Checkboxes -->
                        <div class="flex items-center gap-3 pb-1">
                            <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                                <input type="checkbox" v-model="soloSinPrecio" class="rounded" />
                                <span>Sin precio</span>
                            </label>
                            <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                                <input type="checkbox" v-model="soloSinMinimo" class="rounded" />
                                <span>Sin mínimo</span>
                            </label>
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-1.5 ml-auto">
                            <button
                                @click="expandirTodos"
                                class="px-3 py-1.5 bg-primary-100 text-primary-700 rounded-md text-xs font-medium hover:bg-primary-200 transition flex items-center gap-1.5"
                            >
                                <i class="fas fa-expand-alt text-[10px]"></i>
                                Expandir
                            </button>
                            <button
                                @click="contraerTodos"
                                class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-200 transition flex items-center gap-1.5"
                            >
                                <i class="fas fa-compress-alt text-[10px]"></i>
                                Contraer
                            </button>
                            <button
                                @click="limpiarFiltros"
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1.5"
                            >
                                <i class="fas fa-eraser text-[10px]"></i>
                                Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SIN DATOS -->
                <div v-if="clientes.length === 0" class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                    <p class="text-sm">No hay clientes con los filtros aplicados</p>
                </div>

                <!-- LISTA DE CLIENTES -->
                <div v-else class="space-y-3">
                    <div
                        v-for="cliente in clientes"
                        :key="cliente.IdIdentificador"
                        class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200"
                    >
                        <!-- Header del CLIENTE -->
                        <button
                            @click="toggleCliente(cliente.IdIdentificador)"
                            class="w-full px-3 py-2.5 bg-gradient-to-r from-primary-50 to-primary-100/50 border-b border-primary-100 flex items-center justify-between gap-2 hover:from-primary-100/60 transition text-left"
                        >
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <div class="w-8 h-8 rounded-lg bg-primary-600 text-white flex items-center justify-center flex-shrink-0 transition-transform duration-200"
                                    :class="estaExpandido(cliente.IdIdentificador) ? 'rotate-90' : ''"
                                >
                                    <i class="fas fa-chevron-right text-[10px]"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2 class="font-bold text-gray-800 text-sm truncate flex items-center gap-1.5">
                                        <i class="fas fa-user text-primary-600 text-xs"></i>
                                        {{ cliente.Nombre }}
                                    </h2>
                                    <div class="text-[10px] text-gray-500 mt-0.5">
                                        <i class="fas fa-id-card text-[8px] mr-1"></i>
                                        CI/NIT: {{ cliente.CI_NIT }}
                                        <span class="mx-1.5">•</span>
                                        <i class="fas fa-store text-[8px] mr-1"></i>
                                        {{ cliente.Sucursal }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span class="text-[10px] bg-white text-primary-700 px-2 py-0.5 rounded-full border border-primary-200 font-medium">
                                    {{ cliente.TotalGrupos }} grupo(s)
                                </span>
                            </div>
                        </button>

                        <!-- GRUPOS -->
                        <div v-show="estaExpandido(cliente.IdIdentificador)" class="bg-gray-50/50 p-3 space-y-3">
                            <div
                                v-for="grupo in cliente.Grupos"
                                :key="grupo.IdGrupoAnalisis"
                                class="bg-white rounded-lg border border-gray-200 overflow-hidden"
                            >
                                <!-- Header del GRUPO -->
                                <div class="bg-primary-50 px-3 py-2 border-b border-primary-100 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <div class="w-7 h-7 rounded-md bg-primary-100 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-layer-group text-primary-600 text-xs"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="font-bold text-gray-800 text-xs truncate">
                                                {{ grupo.NombreGrupo }}
                                            </h3>
                                            <div class="text-[10px] text-gray-500">
                                                {{ grupo.Productos.length }} producto(s)
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <div v-if="grupo.Configurado" class="flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md">
                                            <span class="text-[10px] text-emerald-700 font-medium">Mínimo:</span>
                                            <span class="text-xs font-bold text-emerald-700">
                                                {{ formatearNumero(grupo.CantidadMinimaGrupo) }} und
                                            </span>
                                            <i class="fas fa-check-circle text-emerald-500 text-[10px]"></i>
                                        </div>
                                        <div v-else class="flex items-center gap-1.5 bg-yellow-50 border border-yellow-200 px-2 py-0.5 rounded-md">
                                            <i class="fas fa-exclamation-triangle text-yellow-600 text-[10px]"></i>
                                            <span class="text-[10px] text-yellow-700 font-medium">Sin configurar</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tabla de PRODUCTOS -->
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-2 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase w-8">#</th>
                                                <th class="px-2 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Producto</th>
                                                <th class="px-2 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-20">S/F</th>
                                                <th class="px-2 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-20">C/F</th>
                                                <th class="px-2 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-24">Mín Prod</th>
                                                <th class="px-2 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-20">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr
                                                v-for="(producto, idx) in grupo.Productos"
                                                :key="producto.IdProducto"
                                                class="hover:bg-gray-50 transition"
                                                :class="{ 'opacity-60': !producto.TienePrecio }"
                                            >
                                                <td class="px-2 py-1.5 text-center text-[10px] text-gray-400">
                                                    {{ idx + 1 }}
                                                </td>
                                                <td class="px-2 py-1.5">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="font-mono text-[9px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                                                            {{ producto.Codigo }}
                                                        </span>
                                                        <span class="text-xs text-gray-800 truncate">
                                                            {{ producto.Descripcion }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <span v-if="producto.TienePrecio" class="font-medium text-gray-700 text-xs">
                                                        Bs. {{ Number(producto.PrecioSinFactura).toFixed(2) }}
                                                    </span>
                                                    <span v-else class="text-[10px] text-red-400 italic">0.00</span>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <span v-if="producto.TienePrecio" class="font-medium text-gray-700 text-xs">
                                                        Bs. {{ Number(producto.PrecioConFactura).toFixed(2) }}
                                                    </span>
                                                    <span v-else class="text-[10px] text-red-400 italic">0.00</span>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <span v-if="producto.TienePrecio" class="font-medium text-gray-700 text-xs">
                                                        {{ formatearNumero(producto.PedidoMinimo) }}
                                                    </span>
                                                    <span v-else class="text-[10px] text-red-400 italic">0</span>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <span
                                                        class="inline-flex items-center gap-1 text-[9px] px-1.5 py-0.5 rounded-full font-medium"
                                                        :class="producto.TienePrecio 
                                                            ? 'bg-emerald-100 text-emerald-700' 
                                                            : 'bg-orange-100 text-orange-700'"
                                                    >
                                                        <i :class="producto.TienePrecio ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'" class="text-[8px]"></i>
                                                        {{ producto.TienePrecio ? 'OK' : 'Sin precio' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE CONFIGURAR MÍNIMOS -->
        <ConfigurarMinimosModal
            :visible="modalMinimosVisible"
            :identificador="clienteParaMinimos"
            :contenedor="null"
            @close="cerrarModalMinimos"
            @saved="onMinimosGuardados"
        />
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}
.overflow-x-auto {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}
.overflow-x-auto::-webkit-scrollbar { height: 4px; }
.overflow-x-auto::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.overflow-x-auto::-webkit-scrollbar-track { background: #f1f5f9; }
.animate-spin { animation: spin 1s linear infinite; }
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>