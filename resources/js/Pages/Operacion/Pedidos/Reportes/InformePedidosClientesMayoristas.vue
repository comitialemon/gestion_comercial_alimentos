<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: Object,
    operador: Object,
    fechaSeleccionada: String,
    operadorFiltro: [String, Number, null],
    operadorFiltroNombre: String,
    matriz: Object,
    detalle: Array,
    resumen: Object,
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
const fecha = ref(props.fechaSeleccionada || new Date().toISOString().split('T')[0])
const operadorSeleccionado = ref(props.operadorFiltro ?? '')
const operadorTexto = ref(props.operadorFiltroNombre || '')
const cargando = ref(false)
const datosCargados = ref(false)

// Autocomplete
const mostrarSugerencias = ref(false)
const sugerencias = ref([])
const buscandoOperadores = ref(false)
let timeoutBusqueda = null

const datos = ref({
    matriz: props.matriz || null,
    detalle: props.detalle || [],
    resumen: props.resumen || null,
})

// ✅ Sets reactivos (mucho más rápidos que objetos)
const sucursalesExpandidas = ref(new Set())
const operadoresExpandidos = ref(new Set())
const pedidosExpandidos = ref(new Set())

// ==================== COMPUTED ====================
const tieneDatos = computed(() => {
    return datos.value.matriz && datos.value.matriz.sucursales && datos.value.matriz.sucursales.length > 0
})

// ✅ Estructura jerárquica con keys precalculadas (una sola pasada)
const estructuraJerarquica = computed(() => {
    if (!datos.value.detalle) return []
    
    return datos.value.detalle.map(sucursal => {
        const sucKey = sucursal.sucursal
        return {
            key: sucKey,
            nombre: sucursal.sucursal,
            total_sucursal: sucursal.total_sucursal,
            total_operadores: (sucursal.operadores || []).length,
            operadores: (sucursal.operadores || []).map(operador => {
                const opKey = `${sucKey}__${operador.nombre}`
                const pedidos = (operador.pedidos || []).map(pedido => {
                    const pedKey = `${opKey}__${pedido.numero}`
                    const contenedoresConKey = (pedido.contenedores || []).map((cont, idx) => {
                        const contKey = `${pedKey}__${cont.codigo}__${idx}`
                        return {
                            ...cont,
                            _key: contKey,
                            productosConKey: (cont.productos || []).map((prod, pIdx) => ({
                                ...prod,
                                _key: `${contKey}__${pIdx}`,
                            })),
                        }
                    })
                    return {
                        ...pedido,
                        key: pedKey,
                        contenedores: contenedoresConKey,
                    }
                })
                return {
                    key: opKey,
                    nombre: operador.nombre,
                    total_operador: operador.total_operador,
                    total_pedidos: pedidos.length,
                    pedidos: pedidos,
                }
            }),
        }
    })
})

// ==================== EXPANSIÓN (con Sets) ====================
const toggleSet = (setRef, key) => {
    const nuevo = new Set(setRef.value)
    if (nuevo.has(key)) nuevo.delete(key)
    else nuevo.add(key)
    setRef.value = nuevo
}

const toggleSucursal = (key) => toggleSet(sucursalesExpandidas, key)
const toggleOperador = (key) => toggleSet(operadoresExpandidos, key)
const togglePedido = (key) => toggleSet(pedidosExpandidos, key)

const estaSucursalExpandida = (key) => sucursalesExpandidas.value.has(key)
const estaOperadorExpandido = (key) => operadoresExpandidos.value.has(key)
const estaPedidoExpandido = (key) => pedidosExpandidos.value.has(key)

const expandirTodo = () => {
    const sucSet = new Set()
    const opSet = new Set()
    const pedSet = new Set()
    
    estructuraJerarquica.value.forEach(sucursal => {
        sucSet.add(sucursal.key)
        sucursal.operadores.forEach(operador => {
            opSet.add(operador.key)
            operador.pedidos.forEach(pedido => {
                pedSet.add(pedido.key)
            })
        })
    })
    
    sucursalesExpandidas.value = sucSet
    operadoresExpandidos.value = opSet
    pedidosExpandidos.value = pedSet
}

const contraerTodo = () => {
    sucursalesExpandidas.value = new Set()
    operadoresExpandidos.value = new Set()
    pedidosExpandidos.value = new Set()
}

// ==================== AUTOCOMPLETE ====================
const buscarOperadores = async () => {
    buscandoOperadores.value = true
    try {
        const params = new URLSearchParams({
            fecha: fecha.value,
            q: operadorTexto.value || '',
        })
        const res = await fetch(`/operacion/pedidos/reportes/informe-clientes-mayoristas/buscar-operadores?${params}`)
        const data = await res.json()
        sugerencias.value = data.operadores || []
    } catch (e) {
        sugerencias.value = []
    } finally {
        buscandoOperadores.value = false
    }
}

watch(operadorTexto, () => {
    if (!operadorTexto.value) {
        operadorSeleccionado.value = ''
    }
    clearTimeout(timeoutBusqueda)
    timeoutBusqueda = setTimeout(buscarOperadores, 300)
})

const seleccionarOperador = (op) => {
    operadorSeleccionado.value = op.id
    operadorTexto.value = op.nombre
    mostrarSugerencias.value = false
    cargarDatos()
}

const limpiarOperador = () => {
    operadorSeleccionado.value = ''
    operadorTexto.value = ''
    sugerencias.value = []
    cargarDatos()
}

const manejarBlur = () => {
    setTimeout(() => {
        mostrarSugerencias.value = false
    }, 200)
}

// ==================== FUNCIONES ====================
const cargarDatos = () => {
    if (!fecha.value) {
        alert('Por favor selecciona una fecha')
        return
    }

    cargando.value = true

    const params = new URLSearchParams({ fecha: fecha.value })
    if (operadorSeleccionado.value) {
        params.append('operador_id', operadorSeleccionado.value)
    }

    const url = `/operacion/pedidos/reportes/informe-clientes-mayoristas?${params.toString()}`

    router.visit(url, {
        method: 'get',
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            datos.value.matriz = page.props.matriz || null
            datos.value.detalle = page.props.detalle || []
            datos.value.resumen = page.props.resumen || null
            datosCargados.value = true
            cargando.value = false
            contraerTodo()
        },
        onError: () => {
            cargando.value = false
            alert('Error al cargar los datos')
        }
    })
}

const exportarPDF = (tipo) => {
    if (!fecha.value) {
        alert('Por favor selecciona una fecha')
        return
    }

    const form = document.createElement('form')
    form.method = 'POST'
    form.action = tipo === 'resumen'
        ? `/operacion/pedidos/reportes/informe-clientes-mayoristas/exportar-pdf-resumen`
        : `/operacion/pedidos/reportes/informe-clientes-mayoristas/exportar-pdf-detalle`

    const token = document.createElement('input')
    token.type = 'hidden'
    token.name = '_token'
    token.value = document.querySelector('meta[name="csrf-token"]').content
    form.appendChild(token)

    const fechaInput = document.createElement('input')
    fechaInput.type = 'hidden'
    fechaInput.name = 'fecha'
    fechaInput.value = fecha.value
    form.appendChild(fechaInput)

    if (operadorSeleccionado.value) {
        const opInput = document.createElement('input')
        opInput.type = 'hidden'
        opInput.name = 'operador_id'
        opInput.value = operadorSeleccionado.value
        form.appendChild(opInput)
    }

    document.body.appendChild(form)
    form.submit()
    document.body.removeChild(form)
}

const formatearNumero = (valor, decimales = 2) => {
    if (valor === null || valor === undefined) return '0.00'
    return Number(valor).toFixed(decimales).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)

    if (props.fechaSeleccionada && props.matriz) {
        datosCargados.value = true
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    clearTimeout(timeoutBusqueda)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chart-bar text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Informe de Pedidos - Clientes Mayoristas</h1>
                        <p class="text-xs text-gray-500">
                            Visualiza y exporta pedidos por fecha y operador
                            <span v-if="operadorTexto" class="text-primary-600 font-medium">
                                · {{ operadorTexto }}
                            </span>
                        </p>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Fecha -->
                        <div class="flex-1 min-w-[160px] max-w-[200px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha de Entrega</label>
                            <input
                                type="date"
                                v-model="fecha"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                @change="cargarDatos"
                            />
                        </div>

                        <!-- Autocomplete Operador -->
                        <div class="flex-1 min-w-[220px] max-w-[300px] relative">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Operador</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[11px]"></i>
                                <input
                                    type="text"
                                    v-model="operadorTexto"
                                    @focus="mostrarSugerencias = true"
                                    @blur="manejarBlur"
                                    placeholder="Todos los operadores..."
                                    class="w-full border border-gray-300 rounded-md pl-7 pr-7 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                />
                                <button
                                    v-if="operadorTexto"
                                    @click="limpiarOperador"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[11px]"></i>
                                </button>
                                <i v-else-if="buscandoOperadores" class="fas fa-spinner fa-spin absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-[11px]"></i>
                            </div>

                            <div
                                v-if="mostrarSugerencias && sugerencias.length > 0"
                                class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-56 overflow-y-auto"
                            >
                                <button
                                    v-for="op in sugerencias"
                                    :key="op.id"
                                    type="button"
                                    @mousedown.prevent="seleccionarOperador(op)"
                                    class="w-full text-left px-3 py-1.5 text-xs hover:bg-primary-50 flex items-center justify-between gap-2"
                                >
                                    <span class="text-gray-700">
                                        <i class="fas fa-user text-gray-400 text-[10px] mr-1.5"></i>
                                        {{ op.nombre }}
                                    </span>
                                </button>
                            </div>

                            <div
                                v-else-if="mostrarSugerencias && operadorTexto && !buscandoOperadores"
                                class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg p-3 text-center"
                            >
                                <p class="text-[11px] text-gray-400">Sin resultados</p>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-1.5 flex-wrap">
                            <button
                                @click="exportarPDF('resumen')"
                                :disabled="cargando || !tieneDatos"
                                class="inline-flex items-center px-3 py-1.5 bg-primary-600 text-white text-xs font-medium rounded-md hover:bg-primary-700 disabled:opacity-50 transition-colors"
                            >
                                <i class="fas fa-file-pdf mr-1.5 text-[10px]"></i>
                                PDF Resumen
                            </button>
                            <button
                                @click="exportarPDF('detalle')"
                                :disabled="cargando || !tieneDatos"
                                class="inline-flex items-center px-3 py-1.5 bg-primary-600 text-white text-xs font-medium rounded-md hover:bg-primary-700 disabled:opacity-50 transition-colors"
                            >
                                <i class="fas fa-file-pdf mr-1.5 text-[10px]"></i>
                                PDF Detalle
                            </button>
                            <button
                                @click="cargarDatos"
                                :disabled="cargando"
                                class="inline-flex items-center px-3 py-1.5 bg-gray-600 text-white text-xs font-medium rounded-md hover:bg-gray-700 disabled:opacity-50 transition-colors"
                            >
                                <i class="fas fa-sync-alt mr-1.5 text-[10px]" :class="{'animate-spin': cargando}"></i>
                                Actualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== CONTENIDO ==================== -->
                <div v-if="cargando" class="flex justify-center items-center py-12">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-primary-500 mb-3 block"></i>
                        <p class="text-gray-600 text-sm">Cargando datos...</p>
                    </div>
                </div>

                <div v-else-if="!datosCargados" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-xl">
                    <div class="flex items-center text-sm">
                        <i class="fas fa-info-circle text-yellow-400 mr-2"></i>
                        <p class="text-yellow-700">
                            Selecciona una fecha y haz clic en "Actualizar" para cargar los datos.
                        </p>
                    </div>
                </div>

                <div v-else-if="!tieneDatos" class="bg-primary-50 border-l-4 border-primary-400 p-4 rounded-xl">
                    <div class="flex items-center text-sm">
                        <i class="fas fa-info-circle text-primary-400 mr-2"></i>
                        <p class="text-primary-700">
                            No hay pedidos para los filtros seleccionados.
                        </p>
                    </div>
                </div>

                <div v-else class="space-y-4">

                    <!-- ==================== MATRIZ RESUMEN ==================== -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                            <h2 class="text-xs font-semibold text-gray-800 flex items-center gap-1.5">
                                <i class="fas fa-table text-primary-500 text-[10px]"></i>
                                Resumen por Sucursal y Operador
                            </h2>
                        </div>
                        <div class="p-3 overflow-x-auto">
                            <table class="min-w-full border-collapse text-xs">
                                <thead>
                                    <tr class="bg-primary-50">
                                        <th class="border border-gray-300 px-2 py-1.5 text-left font-semibold text-primary-700 text-[9px]" style="min-width: 160px;">
                                            SUCURSAL
                                        </th>
                                        <th
                                            v-for="producto in matriz.productos"
                                            :key="producto.id"
                                            class="border border-gray-300 px-1.5 py-1.5 text-center font-semibold text-primary-700 text-[9px]"
                                            style="min-width: 70px;"
                                        >
                                            <div class="text-[9px] leading-tight">{{ producto.nombre }}</div>
                                        </th>
                                        <th class="border border-gray-300 px-2 py-1.5 text-center font-semibold text-primary-700 text-[9px]" style="min-width: 70px;">
                                            TOTAL
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="sucursal in matriz.sucursales" :key="sucursal.nombre">
                                        <tr class="bg-gray-100">
                                            <td class="border border-gray-300 px-2 py-1.5 font-bold text-gray-800 text-xs" :colspan="matriz.productos.length + 2">
                                                {{ sucursal.nombre }}
                                            </td>
                                        </tr>
                                        <tr v-for="operador in sucursal.operadores" :key="operador.nombre" class="hover:bg-gray-50">
                                            <td class="border border-gray-300 px-2 py-1.5 text-left text-[10px]" style="padding-left: 20px;">
                                                <span class="text-gray-400">-</span> {{ operador.nombre }}
                                            </td>
                                            <td v-for="(valor, idx) in operador.valores" :key="idx" class="border border-gray-300 px-1.5 py-1.5 text-center text-[10px]">
                                                {{ formatearNumero(valor) }}
                                            </td>
                                            <td class="border border-gray-300 px-2 py-1.5 text-center font-bold text-[10px]">
                                                {{ formatearNumero(operador.total) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-emerald-50 font-bold">
                                            <td class="border border-gray-300 px-2 py-1.5 text-right pr-3 text-[10px]">SUBTOTAL</td>
                                            <td v-for="(valor, idx) in sucursal.subtotal" :key="idx" class="border border-gray-300 px-1.5 py-1.5 text-center text-[10px]">
                                                {{ formatearNumero(valor) }}
                                            </td>
                                            <td class="border border-gray-300 px-2 py-1.5 text-center text-[10px]">
                                                {{ formatearNumero(sucursal.total_sucursal) }}
                                            </td>
                                        </tr>
                                    </template>
                                    <tr class="bg-orange-50 font-bold">
                                        <td class="border border-gray-300 px-2 py-2 text-right pr-3 text-xs">TOTAL GENERAL</td>
                                        <td v-for="(valor, idx) in matriz.totales_generales" :key="idx" class="border border-gray-300 px-1.5 py-2 text-center text-xs">
                                            {{ formatearNumero(valor) }}
                                        </td>
                                        <td class="border border-gray-300 px-2 py-2 text-center text-xs">
                                            {{ formatearNumero(matriz.total_general) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ==================== DETALLE CON ACORDEONES ==================== -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 flex items-center justify-between flex-wrap gap-2">
                            <h2 class="text-xs font-semibold text-gray-800 flex items-center gap-1.5">
                                <i class="fas fa-list-ul text-primary-500 text-[10px]"></i>
                                Detalle de Pedidos
                            </h2>
                            <div class="flex gap-1.5">
                                <button
                                    @click="expandirTodo"
                                    class="text-[10px] bg-primary-100 hover:bg-primary-200 text-primary-700 px-2 py-0.5 rounded transition flex items-center gap-1"
                                >
                                    <i class="fas fa-expand-alt text-[8px]"></i>
                                    Expandir todo
                                </button>
                                <button
                                    @click="contraerTodo"
                                    class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-0.5 rounded transition flex items-center gap-1"
                                >
                                    <i class="fas fa-compress-alt text-[8px]"></i>
                                    Contraer todo
                                </button>
                            </div>
                        </div>

                        <div class="p-3 space-y-3">
                            <!-- SUCURSAL -->
                            <div
                                v-for="sucursal in estructuraJerarquica"
                                :key="sucursal.key"
                                class="border border-gray-200 rounded-xl overflow-hidden"
                            >
                                <button
                                    @click="toggleSucursal(sucursal.key)"
                                    class="w-full bg-gradient-to-r from-primary-50 to-primary-100/30 px-3 py-2.5 flex items-center justify-between gap-2 hover:from-primary-100/60 transition-all border-b border-primary-100"
                                >
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <div class="w-7 h-7 rounded-lg bg-primary-600 text-white flex items-center justify-center flex-shrink-0 transition-transform duration-200"
                                            :class="estaSucursalExpandida(sucursal.key) ? 'rotate-90' : ''">
                                            <i class="fas fa-chevron-right text-[10px]"></i>
                                        </div>
                                        <div class="min-w-0 flex-1 text-left">
                                            <div class="text-sm font-bold text-gray-800 truncate flex items-center gap-1.5">
                                                <i class="fas fa-store text-primary-600 text-xs"></i>
                                                {{ sucursal.nombre }}
                                            </div>
                                            <div class="text-[10px] text-gray-500 mt-0.5">
                                                {{ sucursal.total_operadores }} operador{{ sucursal.total_operadores !== 1 ? 'es' : '' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span class="text-[10px] bg-white text-primary-700 px-2 py-0.5 rounded-full border border-primary-200 font-semibold">
                                            {{ formatearNumero(sucursal.total_sucursal) }} und
                                        </span>
                                    </div>
                                </button>

                                <div v-show="estaSucursalExpandida(sucursal.key)" class="bg-gray-50/50 p-2 space-y-2">
                                    <div
                                        v-for="operador in sucursal.operadores"
                                        :key="operador.key"
                                        class="bg-white border border-gray-200 rounded-lg overflow-hidden"
                                    >
                                        <button
                                            @click="toggleOperador(operador.key)"
                                            class="w-full px-3 py-2 flex items-center justify-between gap-2 hover:bg-primary-50/40 transition-colors border-b border-gray-100"
                                        >
                                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                                <div class="w-6 h-6 rounded-md bg-primary-100 text-primary-600 flex items-center justify-center flex-shrink-0 transition-transform duration-200"
                                                    :class="estaOperadorExpandido(operador.key) ? 'rotate-90' : ''">
                                                    <i class="fas fa-chevron-right text-[9px]"></i>
                                                </div>
                                                <div class="min-w-0 flex-1 text-left">
                                                    <div class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                                                        <i class="fas fa-user text-primary-500 text-[10px]"></i>
                                                        {{ operador.nombre }}
                                                    </div>
                                                    <div class="text-[9px] text-gray-400 mt-0.5">
                                                        {{ operador.total_pedidos }} pedido{{ operador.total_pedidos !== 1 ? 's' : '' }}
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full border border-emerald-200 font-semibold flex-shrink-0">
                                                {{ formatearNumero(operador.total_operador) }} und
                                            </span>
                                        </button>

                                        <div v-show="estaOperadorExpandido(operador.key)" class="p-2 space-y-2">
                                            <div
                                                v-for="pedido in operador.pedidos"
                                                :key="pedido.key"
                                                class="border border-gray-200 rounded-lg overflow-hidden bg-white"
                                            >
                                                <button
                                                    @click="togglePedido(pedido.key)"
                                                    class="w-full px-3 py-2 flex items-center justify-between gap-2 hover:bg-primary-50/40 transition-colors"
                                                >
                                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                                        <div class="w-5 h-5 rounded-md bg-primary-600 text-white flex items-center justify-center flex-shrink-0 transition-transform duration-200"
                                                            :class="estaPedidoExpandido(pedido.key) ? 'rotate-90' : ''">
                                                            <i class="fas fa-chevron-right text-[8px]"></i>
                                                        </div>
                                                        <div class="min-w-0 flex-1 text-left">
                                                            <div class="text-[11px] font-bold text-gray-800 flex items-center gap-1.5">
                                                                <i class="fas fa-receipt text-primary-500 text-[10px]"></i>
                                                                PEDIDO #{{ pedido.numero }}
                                                            </div>
                                                            <div class="text-[9px] text-gray-500 mt-0.5 flex flex-wrap gap-x-2">
                                                                <span><i class="fas fa-calendar-alt text-[8px] mr-0.5"></i>{{ pedido.fecha_pedido }}</span>
                                                                <span><i class="fas fa-truck text-[8px] mr-0.5"></i>{{ pedido.fecha_entrega }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <span class="text-[10px] bg-primary-50 text-primary-700 px-2 py-0.5 rounded-full border border-primary-200 font-semibold flex-shrink-0">
                                                        {{ formatearNumero(pedido.total_pedido) }} und
                                                    </span>
                                                </button>

                                                <div v-show="estaPedidoExpandido(pedido.key)" class="p-2 space-y-2 bg-gray-50/30">
                                                    <div
                                                        v-for="contenedor in pedido.contenedores"
                                                        :key="contenedor._key"
                                                        class="border border-gray-200 rounded-md overflow-hidden bg-white"
                                                    >
                                                        <div class="bg-primary-50 px-3 py-1.5 flex items-center justify-between flex-wrap gap-2 border-b border-primary-100">
                                                            <div class="flex items-center gap-2 flex-wrap">
                                                                <span class="text-[10px] font-bold text-primary-800 bg-primary-100 px-1.5 py-0.5 rounded">
                                                                    [{{ contenedor.codigo }}]
                                                                </span>
                                                                <span class="text-[10px] text-gray-700 font-medium">
                                                                    {{ contenedor.nombre }}
                                                                </span>
                                                                <span class="text-[9px] text-gray-500 bg-white px-1.5 py-0.5 rounded border border-primary-200">
                                                                    Cap: {{ formatearNumero(contenedor.capacidad, 0) }} und
                                                                </span>
                                                            </div>
                                                            <span class="text-[10px] font-bold text-primary-800">
                                                                Total: {{ formatearNumero(contenedor.total) }} und
                                                            </span>
                                                        </div>

                                                        <table class="w-full text-[10px]">
                                                            <thead>
                                                                <tr class="bg-gray-50 text-gray-500 border-b border-gray-200">
                                                                    <th class="text-left px-2 py-1 font-medium w-8">#</th>
                                                                    <th class="text-left px-2 py-1 font-medium">Producto</th>
                                                                    <th class="text-right px-2 py-1 font-medium w-24">Cantidad</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr
                                                                    v-for="producto in contenedor.productosConKey"
                                                                    :key="producto._key"
                                                                    class="border-b border-gray-50 last:border-0"
                                                                >
                                                                    <td class="px-2 py-1 text-gray-400 text-[9px]"></td>
                                                                    <td class="px-2 py-1 text-gray-700">{{ producto.nombre }}</td>
                                                                    <td class="px-2 py-1 text-right font-medium text-gray-800">
                                                                        {{ formatearNumero(producto.cantidad) }} und
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                            <tfoot>
                                                                <tr class="bg-primary-50 font-bold border-t border-primary-200">
                                                                    <td colspan="2" class="px-2 py-1 text-right text-primary-800 text-[10px]">
                                                                        TOTAL {{ contenedor.codigo }}
                                                                    </td>
                                                                    <td class="px-2 py-1 text-right text-primary-800 text-[10px]">
                                                                        {{ formatearNumero(contenedor.total) }} und
                                                                    </td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>

                                                    <div class="bg-primary-100 px-3 py-1.5 rounded-md border border-primary-200 flex justify-between items-center">
                                                        <span class="text-[10px] font-bold text-primary-800">
                                                            TOTAL PEDIDO #{{ pedido.numero }}
                                                        </span>
                                                        <span class="text-[11px] font-bold text-primary-800">
                                                            {{ formatearNumero(pedido.total_pedido) }} und
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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

.rotate-90 {
    transform: rotate(90deg);
}
</style>