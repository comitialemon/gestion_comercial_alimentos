<script setup>
import { ref, reactive, computed, inject, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    pedidosEnDistribucion:   { type: Array, default: () => [] },
    pedidosEnTransportadora: { type: Array, default: () => [] },
    pedidosRecibidos:        { type: Array, default: () => [] },
    transportadoras:         { type: Array, default: () => [] },
})

const toast = inject('toast')

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const handleResize = () => { isMobile.value = window.innerWidth < 640 }

const actualizando = ref(false)
// ==================== TABS PRINCIPALES ====================
const tabActiva = ref('distribucion') // 'distribucion' | 'transportadora' | 'recibidos'

// ==================== SUB-TABS: CIUDAD / PROVINCIA ====================
const subTab = ref('ciudad') // 'ciudad' | 'provincia'

// ==================== FILTROS ====================
const filtros = reactive({ q: '', fechaEntrega: '' })

// ==================== MODAL CAMBIO ESTADO ====================
const modalEstadoVisible         = ref(false)
const pedidoSeleccionado         = ref(null)
const numeroGuia                 = ref('')
const costoEnvio                 = ref(0)
const transportadoraSeleccionada = ref('')

// Autocomplete transportadoras
const inputTransportadoraRef        = ref(null)
const busquedaTransportadora        = ref('')
const resultadosTransportadoras     = ref([])
const transportadoraSeleccionadaObj = ref(null)
const indiceActivoTransportadora    = ref(-1)
const dropdownTransportadoraStyle   = ref({})

// Modal nueva transportadora
const modalTransportadoraVisible = ref(false)
const nuevaTransportadora        = ref({ Nombre: '', CI_NIT: '' })

// Autocomplete identificadores
const inputIdentificadorRef     = ref(null)
const busquedaIdentificador     = ref('')
const resultadosIdentificadores = ref([])
const buscandoIdentificador     = ref(false)
const identificadorSeleccionado = ref(null)
const mostrarCrearIdentificador = ref(false)
const indiceActivo              = ref(-1)
let debounceTimer = null
const dropdownStyle = ref({})

// ==================== HELPERS FECHA ====================
const toYMD = (fecha) => {
    if (!fecha) return null
    if (typeof fecha === 'string') return fecha.slice(0, 10)
    const d = new Date(fecha)
    if (isNaN(d.getTime())) return null
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}

const diasHasta = (fecha) => {
    const ymd = toYMD(fecha)
    if (!ymd) return null
    const [y, m, d] = ymd.split('-').map(Number)
    const target = new Date(y, m - 1, d)
    const now = new Date(); now.setHours(0,0,0,0)
    return Math.round((target - now) / (1000 * 60 * 60 * 24))
}

// ==================== LISTA BASE (tab + subtab) ====================
const pedidosBase = computed(() => {
    let porTab = []
    if (tabActiva.value === 'distribucion')        porTab = props.pedidosEnDistribucion
    else if (tabActiva.value === 'transportadora') porTab = props.pedidosEnTransportadora
    else if (tabActiva.value === 'recibidos')      porTab = props.pedidosRecibidos

    return porTab.filter(p => {
        if (subTab.value === 'ciudad')    return p.Ciudad === true
        if (subTab.value === 'provincia') return p.Provincia === true
        return false
    })
})

// ==================== CONTADORES ====================
const countDistribucionCiudad    = computed(() => props.pedidosEnDistribucion.filter(p => p.Ciudad === true).length)
const countDistribucionProvincia = computed(() => props.pedidosEnDistribucion.filter(p => p.Provincia === true).length)
const countTransportadoraCiudad    = computed(() => props.pedidosEnTransportadora.filter(p => p.Ciudad === true).length)
const countTransportadoraProvincia = computed(() => props.pedidosEnTransportadora.filter(p => p.Provincia === true).length)
const countRecibidosCiudad    = computed(() => props.pedidosRecibidos.filter(p => p.Ciudad === true).length)
const countRecibidosProvincia = computed(() => props.pedidosRecibidos.filter(p => p.Provincia === true).length)

const countSubTabActual = computed(() => {
    if (subTab.value === 'ciudad') {
        if (tabActiva.value === 'distribucion')   return countDistribucionCiudad.value
        if (tabActiva.value === 'transportadora') return countTransportadoraCiudad.value
        return countRecibidosCiudad.value
    }
    if (tabActiva.value === 'distribucion')   return countDistribucionProvincia.value
    if (tabActiva.value === 'transportadora') return countTransportadoraProvincia.value
    return countRecibidosProvincia.value
})

// ==================== LISTA FILTRADA ====================
const pedidosFiltrados = computed(() => {
    let lista = [...pedidosBase.value]

    const q = filtros.q.trim().toLowerCase()
    if (q) {
        lista = lista.filter(p => {
            const operador = (p.OperadorNombre || '').toLowerCase()
            const numero   = String(p.NumeroPedido || '').toLowerCase()
            return operador.includes(q) || numero.includes(q)
        })
    }

    if (filtros.fechaEntrega) {
        lista = lista.filter(p => toYMD(p.FechaEntrega) === filtros.fechaEntrega)
    }

    const cmpPedido = (a, b) => {
        const da = new Date(a.FechaPedido || 0).getTime() || 0
        const db = new Date(b.FechaPedido || 0).getTime() || 0
        return da - db
    }

    lista.sort((a, b) => {
        const ya = toYMD(a.FechaEntrega)
        const yb = toYMD(b.FechaEntrega)
        if (!ya && !yb) return cmpPedido(a, b)
        if (!ya) return 1
        if (!yb) return -1
        if (ya !== yb) return ya < yb ? -1 : 1
        return cmpPedido(a, b)
    })

    // Transportadora y Recibidos: los más recientes primero (ya vienen así del backend)
    if (tabActiva.value === 'transportadora' || tabActiva.value === 'recibidos') lista.reverse()

    return lista
})

const pedidosActuales = pedidosFiltrados
const totalFiltrados = computed(() => pedidosFiltrados.value.length)
const totalBase = computed(() => pedidosBase.value.length)
const hayFiltrosActivos = computed(() => !!filtros.q || !!filtros.fechaEntrega)
const limpiarFiltros = () => {
    filtros.q = ''
    filtros.fechaEntrega = ''
}

// ==================== ACCIONES ====================
const abrirModalEstado = (pedido) => {
    pedidoSeleccionado.value         = pedido
    numeroGuia.value                 = ''
    costoEnvio.value                 = 0
    transportadoraSeleccionada.value    = ''
    transportadoraSeleccionadaObj.value = null
    busquedaTransportadora.value        = ''
    resultadosTransportadoras.value     = []
    indiceActivoTransportadora.value    = -1
    modalEstadoVisible.value            = true
}

const confirmarCambioEstado = async () => {
    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/distribucion/${pedidoSeleccionado.value.IdPedidoCliente}/cambiar-estado`,
            {
                modo:              'despacho',
                nuevo_estado:      'En Transportadora',
                id_transportadora: transportadoraSeleccionada.value || null,
                numero_guia:       numeroGuia.value || null,
                costo_envio:       costoEnvio.value || 0,
            }
        )
        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            modalEstadoVisible.value = false
            router.reload()
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al cambiar estado')
    }
}

// ==================== POSICIÓN DROPDOWNS ====================
const calcularPosicion = (elRef, alturaMax = 240) => {
    if (!elRef?.value) return {}
    const rect = elRef.value.getBoundingClientRect()
    const espacioAbajo = window.innerHeight - rect.bottom
    if (espacioAbajo < alturaMax && rect.top > alturaMax) {
        return {
            position: 'fixed', left: `${Math.max(8, rect.left)}px`,
            width: `${Math.min(rect.width, window.innerWidth - 16)}px`,
            bottom: `${window.innerHeight - rect.top + 4}px`,
            maxHeight: `${Math.min(alturaMax, rect.top - 8)}px`, zIndex: 9999,
        }
    }
    return {
        position: 'fixed', left: `${Math.max(8, rect.left)}px`,
        width: `${Math.min(rect.width, window.innerWidth - 16)}px`,
        top: `${rect.bottom + 4}px`,
        maxHeight: `${Math.min(alturaMax, espacioAbajo - 8)}px`, zIndex: 9999,
    }
}

const actualizarPosicionDropdown = () => { dropdownStyle.value = calcularPosicion(inputIdentificadorRef) }
const actualizarPosicionDropdownTransportadora = () => { dropdownTransportadoraStyle.value = calcularPosicion(inputTransportadoraRef) }

const onScrollOrResize = () => {
    if (resultadosIdentificadores.value.length)   actualizarPosicionDropdown()
    if (resultadosTransportadoras.value.length)   actualizarPosicionDropdownTransportadora()
}

// ==================== AUTOCOMPLETE IDENTIFICADORES ====================
const buscarIdentificadores = () => {
    clearTimeout(debounceTimer)
    const q = busquedaIdentificador.value.trim()
    if (q.length < 2) {
        resultadosIdentificadores.value = []
        indiceActivo.value = -1
        return
    }
    buscandoIdentificador.value = true
    debounceTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get(
                '/operacion/pedidos/clientes-mayoristas/distribucion/identificadores/buscar',
                { params: { q } }
            )
            resultadosIdentificadores.value = data || []
            indiceActivo.value = -1
        } catch (e) {
            resultadosIdentificadores.value = []
        } finally {
            buscandoIdentificador.value = false
        }
    }, 300)
}

const seleccionarIdentificador = (id) => {
    if (id.YaEsTransportadora) return
    identificadorSeleccionado.value = id
    busquedaIdentificador.value     = `${id.Nombre} (${id.CI_NIT})`
    resultadosIdentificadores.value = []
    mostrarCrearIdentificador.value = false
    indiceActivo.value              = -1
}

const limpiarSeleccionIdentificador = () => {
    identificadorSeleccionado.value = null
    busquedaIdentificador.value     = ''
    resultadosIdentificadores.value = []
    nextTick(() => inputIdentificadorRef.value?.focus())
}
const actualizar = () => {
    if (actualizando.value) return
    router.reload({
        only: ['pedidosEnDistribucion', 'pedidosEnTransportadora', 'pedidosRecibidos', 'transportadoras'],
        onStart: () => { actualizando.value = true },
        onFinish: () => { actualizando.value = false },
        onSuccess: () => {
            toast?.success('Actualizado', 'Datos refrescados correctamente')
        },
        onError: () => {
            toast?.error('Error', 'No se pudieron actualizar los datos')
        },
    })
}
const abrirCrearIdentificador = () => {
    mostrarCrearIdentificador.value = true
    nuevaTransportadora.value = { Nombre: busquedaIdentificador.value.trim(), CI_NIT: '' }
    resultadosIdentificadores.value = []
    indiceActivo.value = -1
}

const onKeydownIdentificador = (e) => {
    const total = resultadosIdentificadores.value.length
    if (!total) return
    if (e.key === 'ArrowDown') {
        e.preventDefault()
        indiceActivo.value = (indiceActivo.value + 1) % total
        let i = 0
        while (resultadosIdentificadores.value[indiceActivo.value]?.YaEsTransportadora && i < total) {
            indiceActivo.value = (indiceActivo.value + 1) % total; i++
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        indiceActivo.value = (indiceActivo.value - 1 + total) % total
        let i = 0
        while (resultadosIdentificadores.value[indiceActivo.value]?.YaEsTransportadora && i < total) {
            indiceActivo.value = (indiceActivo.value - 1 + total) % total; i++
        }
    } else if (e.key === 'Enter') {
        e.preventDefault()
        const item = resultadosIdentificadores.value[indiceActivo.value]
        if (item) seleccionarIdentificador(item)
    } else if (e.key === 'Escape') {
        resultadosIdentificadores.value = []
        indiceActivo.value = -1
    }
}

// ==================== AUTOCOMPLETE TRANSPORTADORAS ====================
const filtrarTransportadoras = () => {
    const q = busquedaTransportadora.value.trim().toLowerCase()
    if (q.length < 1) {
        resultadosTransportadoras.value = []
        indiceActivoTransportadora.value = -1
        return
    }
    resultadosTransportadoras.value = props.transportadoras
        .filter(t => {
            const nombre = (t.identificador?.Nombre || '').toLowerCase()
            const ciNit  = String(t.identificador?.CI_NIT || '').toLowerCase()
            return nombre.includes(q) || ciNit.includes(q)
        })
        .slice(0, 15)
    indiceActivoTransportadora.value = -1
}

const seleccionarTransportadora = (t) => {
    transportadoraSeleccionadaObj.value = t
    transportadoraSeleccionada.value    = t.IdTransportadora
    busquedaTransportadora.value        = `${t.identificador?.Nombre || 'Sin nombre'} (${t.identificador?.CI_NIT || '-'})`
    resultadosTransportadoras.value     = []
    indiceActivoTransportadora.value    = -1
}

const limpiarSeleccionTransportadora = () => {
    transportadoraSeleccionadaObj.value = null
    transportadoraSeleccionada.value    = ''
    busquedaTransportadora.value        = ''
    resultadosTransportadoras.value     = []
    nextTick(() => inputTransportadoraRef.value?.focus())
}

const onKeydownTransportadora = (e) => {
    const total = resultadosTransportadoras.value.length
    if (!total) return
    if (e.key === 'ArrowDown') {
        e.preventDefault()
        indiceActivoTransportadora.value = (indiceActivoTransportadora.value + 1) % total
    } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        indiceActivoTransportadora.value = (indiceActivoTransportadora.value - 1 + total) % total
    } else if (e.key === 'Enter') {
        e.preventDefault()
        const item = resultadosTransportadoras.value[indiceActivoTransportadora.value]
        if (item) seleccionarTransportadora(item)
    } else if (e.key === 'Escape') {
        resultadosTransportadoras.value = []
        indiceActivoTransportadora.value = -1
    }
}

// ==================== CREAR TRANSPORTADORA ====================
const crearTransportadora = async () => {
    try {
        if (identificadorSeleccionado.value) {
            const { data } = await axios.post(
                '/operacion/pedidos/clientes-mayoristas/distribucion/transportadoras',
                { IdIdentificador: identificadorSeleccionado.value.IdIdentificador }
            )
            if (data.success) {
                toast?.success('Éxito', data.message || 'Transportadora creada correctamente')
                cerrarModalTransportadora()
                router.reload()
            }
            return
        }
        if (!mostrarCrearIdentificador.value) {
            toast?.warning('Atención', 'Selecciona un identificador o usa el botón + para crear uno nuevo.')
            return
        }
        if (!nuevaTransportadora.value.Nombre.trim()) {
            toast?.warning('Atención', 'El nombre es obligatorio.')
            return
        }
        if (!nuevaTransportadora.value.CI_NIT.toString().trim()) {
            toast?.warning('Atención', 'El CI/NIT es obligatorio.')
            return
        }
        const { data } = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/distribucion/transportadoras',
            nuevaTransportadora.value
        )
        if (data.success) {
            toast?.success('Éxito', data.message || 'Transportadora creada correctamente')
            cerrarModalTransportadora()
            router.reload()
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al crear transportadora')
    }
}

const cerrarModalTransportadora = () => {
    modalTransportadoraVisible.value = false
    identificadorSeleccionado.value  = null
    busquedaIdentificador.value      = ''
    resultadosIdentificadores.value  = []
    mostrarCrearIdentificador.value  = false
    nuevaTransportadora.value        = { Nombre: '', CI_NIT: '' }
    indiceActivo.value               = -1
}

// ==================== UTILIDADES ====================
const formatoFecha = (fecha) => {
    if (!fecha) return '-'
    const ymd = toYMD(fecha); if (!ymd) return '-'
    const [y, m, d] = ymd.split('-')
    return `${d}/${m}/${y}`
}

const formatoFechaHora = (fecha) => {
    if (!fecha) return '-'
    const d = new Date(fecha)
    if (isNaN(d.getTime())) return '-'
    return d.toLocaleString('es-BO', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const badgeEntrega = (fecha) => {
    const dias = diasHasta(fecha)
    if (dias === null) return null
    if (dias < 0)  return { texto: `Vencido ${Math.abs(dias)}d`, clase: 'bg-red-100 text-red-700 border-red-300' }
    if (dias === 0) return { texto: 'Hoy',       clase: 'bg-amber-100 text-amber-800 border-amber-300' }
    if (dias === 1) return { texto: 'Mañana',    clase: 'bg-blue-100 text-blue-700 border-blue-300' }
    if (dias <= 3)  return { texto: `En ${dias}d`, clase: 'bg-sky-100 text-sky-700 border-sky-300' }
    return null
}

const verPdf = (idPedido) => {
    window.open(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${idPedido}/pdf`, '_blank')
}

// ==================== WATCHERS ====================
watch(busquedaIdentificador, (val) => {
    if (identificadorSeleccionado.value && val !== `${identificadorSeleccionado.value.Nombre} (${identificadorSeleccionado.value.CI_NIT})`) {
        identificadorSeleccionado.value = null
    }
})

watch(resultadosIdentificadores, async (val) => {
    if (val?.length) { await nextTick(); actualizarPosicionDropdown() }
})

watch(resultadosTransportadoras, async (val) => {
    if (val?.length) { await nextTick(); actualizarPosicionDropdownTransportadora() }
})

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    window.addEventListener('resize', onScrollOrResize)
    window.addEventListener('scroll', onScrollOrResize, true)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    window.removeEventListener('resize', onScrollOrResize)
    window.removeEventListener('scroll', onScrollOrResize, true)
    clearTimeout(debounceTimer)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- HEADER -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-cyan-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-truck text-cyan-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Distribución · Despacho</h1>
                            <p class="text-xs text-gray-500">Despacha pedidos y consulta los que ya fueron recibidos</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- ✅ NUEVO: Botón actualizar -->
                        <button @click="actualizar" :disabled="actualizando"
                            class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition shadow-sm disabled:opacity-60 disabled:cursor-not-allowed">
                            <i class="fas fa-sync-alt text-[10px]" :class="{ 'fa-spin': actualizando }"></i>
                            {{ actualizando ? 'Actualizando...' : 'Actualizar' }}
                        </button>

                        <!-- Botón existente -->
                        <button @click="modalTransportadoraVisible = true"
                            class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition shadow-sm">
                            <i class="fas fa-plus text-[10px]"></i> Nueva Transportadora
                        </button>
                    </div>
                </div>

                <!-- TABS PRINCIPALES -->
                <div class="bg-white rounded-xl shadow-sm p-1.5 mb-2 overflow-x-auto">
                    <div class="flex gap-1 min-w-max">
                        <button @click="tabActiva = 'distribucion'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'distribucion' ? 'bg-indigo-500 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'">
                            <i class="fas fa-shipping-fast text-[10px]"></i> En Distribución
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="tabActiva === 'distribucion' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ pedidosEnDistribucion.length }}
                            </span>
                        </button>
                        <button @click="tabActiva = 'transportadora'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'transportadora' ? 'bg-cyan-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'">
                            <i class="fas fa-truck text-[10px]"></i> En Transportadora
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="tabActiva === 'transportadora' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ pedidosEnTransportadora.length }}
                            </span>
                        </button>
                        <button @click="tabActiva = 'recibidos'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'recibidos' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'">
                            <i class="fas fa-check-double text-[10px]"></i> Finalizados
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="tabActiva === 'recibidos' ? 'bg-white/20' : 'bg-gray-200 text-gray-700'">
                                {{ pedidosRecibidos.length }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- SUB-TABS: CIUDAD / PROVINCIA -->
                <div class="bg-white rounded-xl shadow-sm p-1 mb-3 overflow-x-auto border border-gray-100">
                    <div class="flex gap-1 min-w-max">
                        <button @click="subTab = 'ciudad'"
                            class="px-3 py-1 rounded-md text-[11px] font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="subTab === 'ciudad' ? 'bg-blue-100 text-blue-700 ring-1 ring-blue-300' : 'text-gray-500 hover:bg-gray-100'">
                            <i class="fas fa-city text-[10px]"></i> Ciudad
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="subTab === 'ciudad' ? 'bg-blue-200 text-blue-800' : 'bg-gray-200 text-gray-600'">
                                {{ tabActiva === 'distribucion' ? countDistribucionCiudad : (tabActiva === 'transportadora' ? countTransportadoraCiudad : countRecibidosCiudad) }}
                            </span>
                        </button>
                        <button @click="subTab = 'provincia'"
                            class="px-3 py-1 rounded-md text-[11px] font-medium transition flex items-center gap-1.5 whitespace-nowrap"
                            :class="subTab === 'provincia' ? 'bg-purple-100 text-purple-700 ring-1 ring-purple-300' : 'text-gray-500 hover:bg-gray-100'">
                            <i class="fas fa-truck-moving text-[10px]"></i> Provincia
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold"
                                :class="subTab === 'provincia' ? 'bg-purple-200 text-purple-800' : 'bg-gray-200 text-gray-600'">
                                {{ tabActiva === 'distribucion' ? countDistribucionProvincia : (tabActiva === 'transportadora' ? countTransportadoraProvincia : countRecibidosProvincia) }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- FILTROS -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-3 p-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative flex-1 min-w-[160px]">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[11px]"></i>
                            <input v-model="filtros.q" type="text" placeholder="Buscar operador o #pedido..."
                                class="w-full border border-gray-300 rounded-md pl-7 pr-2.5 py-1.5 text-sm sm:text-xs outline-none focus:border-primary-500" />
                        </div>
                        <div class="flex items-center gap-1.5">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">
                                <i class="fas fa-calendar-check text-primary-500 mr-0.5"></i> Entrega:
                            </label>
                            <input v-model="filtros.fechaEntrega" type="date"
                                class="border border-gray-300 rounded-md px-2 py-1.5 text-sm sm:text-xs outline-none focus:border-primary-500" />
                        </div>
                        <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
                            class="px-2.5 py-1.5 text-[11px] bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-md transition flex items-center gap-1 whitespace-nowrap">
                            <i class="fas fa-times text-[10px]"></i> Limpiar
                        </button>
                    </div>
                    <div v-if="hayFiltrosActivos" class="text-[11px] text-gray-500 mt-1.5 px-0.5">
                        Mostrando <b>{{ totalFiltrados }}</b> de <b>{{ totalBase }}</b> pedidos
                    </div>
                </div>

                <!-- TABLA -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 75vh; overflow-y: auto;">

                        <!-- MÓVIL -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="p in pedidosActuales" :key="p.IdPedidoCliente" class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start mb-1.5 gap-2">
                                    <span class="text-xs font-bold text-primary-700 font-mono">#{{ p.NumeroPedido }}</span>
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="text-[9px] text-gray-400">{{ formatoFechaHora(p.FechaPedido) }}</span>
                                        <span v-if="p.FechaEntrega" class="text-[9px] text-gray-600 font-medium">
                                            <i class="fas fa-calendar-check mr-0.5 text-primary-500"></i>
                                            {{ formatoFecha(p.FechaEntrega) }}
                                        </span>
                                        <span v-if="badgeEntrega(p.FechaEntrega)" class="text-[8px] px-1.5 py-0.5 rounded font-bold border" :class="badgeEntrega(p.FechaEntrega).clase">
                                            {{ badgeEntrega(p.FechaEntrega).texto }}
                                        </span>
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-500 uppercase font-bold tracking-wider">Operador:</p>
                                <p class="text-xs font-bold text-gray-800 truncate mb-1">{{ p.OperadorNombre || 'Sin operador' }}</p>
                                <div class="flex items-center gap-1 mb-2">
                                    <span v-if="subTab === 'ciudad'" class="text-[8px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-bold border border-blue-200">CIUDAD</span>
                                    <span v-else class="text-[8px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded font-bold border border-purple-200">PROVINCIA</span>
                                </div>

                                <!-- Info transportadora (transportadora o recibidos) -->
                                <div v-if="tabActiva !== 'distribucion'" class="mt-1.5 pt-1.5 border-t border-gray-200">
                                    <p class="text-[10px] font-bold text-cyan-700">{{ p.envio?.transportadora?.identificador?.Nombre || 'Sin transportadora' }}</p>
                                    <p class="text-[9px] text-gray-500">Guía: {{ p.envio?.NumeroGuia || '-' }} | Costo: Bs. {{ p.envio?.CostoEnvio || '0.00' }}</p>
                                    <p v-if="tabActiva === 'recibidos' && p.envio?.FechaEntregaReal" class="text-[9px] text-emerald-600 font-bold mt-0.5">
                                        <i class="fas fa-check-circle mr-0.5"></i>
                                        Recibido: {{ formatoFechaHora(p.envio.FechaEntregaReal) }}
                                    </p>
                                </div>

                                <div class="flex justify-end items-center mt-2 pt-2 border-t border-gray-200 gap-2">
                                    <button @click="verPdf(p.IdPedidoCliente)"
                                        class="flex items-center gap-1 bg-rose-100 text-rose-700 hover:bg-rose-200 px-2.5 py-1.5 rounded-md text-[10px] font-bold transition border border-rose-200">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </button>

                                    <!-- Distribución + Provincia: despachar -->
                                    <template v-if="tabActiva === 'distribucion'">
                                        <button v-if="subTab === 'provincia'" @click="abrirModalEstado(p)"
                                            class="bg-cyan-500 hover:bg-cyan-600 text-white px-3 py-1.5 rounded-md text-[10px] font-bold transition flex items-center gap-1 shadow-sm">
                                            <i class="fas fa-truck text-[8px]"></i> Despachar
                                        </button>
                                        <span v-else class="text-[10px] text-gray-400 italic font-medium">
                                            <i class="fas fa-store text-gray-400 mr-1"></i> Reparto Local
                                        </span>
                                    </template>

                                    <!-- Transportadora: en tránsito -->
                                    <span v-else-if="tabActiva === 'transportadora'" class="text-[10px] text-cyan-600 italic font-medium">
                                        <i class="fas fa-truck-moving mr-1"></i> En tránsito
                                    </span>

                                    <!-- Recibidos: finalizado -->
                                    <span v-else class="text-[10px] text-emerald-600 italic font-bold">
                                        <i class="fas fa-check-double mr-1"></i> Recibido
                                    </span>
                                </div>
                            </div>
                            <div v-if="pedidosActuales.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay pedidos en {{ subTab }}</span>
                            </div>
                        </div>

                        <!-- DESKTOP -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase w-24">Pedido</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase">Operador</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase w-32">Fecha Pedido</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase w-32">Fecha Entrega</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase w-24">Cont.</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase">Detalle / Guía</th>
                                    <th class="px-4 py-3 text-right text-[10px] font-bold text-gray-500 uppercase w-56">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="p in pedidosActuales" :key="p.IdPedidoCliente" class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 text-xs font-mono font-bold text-primary-700">#{{ p.NumeroPedido }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs text-gray-800 mb-1"><b>{{ p.OperadorNombre || 'Sin operador' }}</b></div>
                                        <div class="flex items-center gap-1">
                                            <span v-if="subTab === 'ciudad'" class="text-[8px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-bold border border-blue-200">CIUDAD</span>
                                            <span v-else class="text-[8px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded font-bold border border-purple-200">PROVINCIA</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-[11px] text-gray-600 whitespace-nowrap">{{ formatoFechaHora(p.FechaPedido) }}</td>
                                    <td class="px-4 py-3 text-[11px] whitespace-nowrap">
                                        <div v-if="p.FechaEntrega" class="flex flex-col gap-0.5">
                                            <span class="text-gray-700 font-medium">{{ formatoFecha(p.FechaEntrega) }}</span>
                                            <span v-if="badgeEntrega(p.FechaEntrega)" class="text-[8px] px-1.5 py-0.5 rounded font-bold border w-fit" :class="badgeEntrega(p.FechaEntrega).clase">
                                                {{ badgeEntrega(p.FechaEntrega).texto }}
                                            </span>
                                        </div>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 font-medium">{{ p.TotalContenedores }} cont.</td>
                                    <td class="px-4 py-3 text-xs">
                                        <template v-if="tabActiva !== 'distribucion'">
                                            <div class="font-bold text-cyan-700">{{ p.envio?.transportadora?.identificador?.Nombre || 'Sin transportadora' }}</div>
                                            <div class="text-[9px] text-gray-500">Guía: {{ p.envio?.NumeroGuia || '-' }} | Costo: Bs. {{ p.envio?.CostoEnvio || '0.00' }}</div>
                                            <div v-if="tabActiva === 'recibidos' && p.envio?.FechaEntregaReal" class="text-[9px] text-emerald-600 font-bold mt-0.5">
                                                <i class="fas fa-check-circle mr-0.5"></i> Recibido: {{ formatoFechaHora(p.envio.FechaEntregaReal) }}
                                            </div>
                                        </template>
                                        <template v-else>
                                            <span class="text-gray-500">{{ p.TotalContenedores }} contenedores</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="verPdf(p.IdPedidoCliente)"
                                                class="flex items-center gap-1 bg-rose-100 text-rose-700 hover:bg-rose-200 border border-rose-200 px-2.5 py-1.5 rounded-md text-[10px] font-bold transition shadow-sm">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </button>

                                            <template v-if="tabActiva === 'distribucion'">
                                                <button v-if="subTab === 'provincia'" @click="abrirModalEstado(p)"
                                                    class="inline-flex items-center gap-1.5 bg-cyan-500 hover:bg-cyan-600 text-white px-3 py-1.5 rounded-md text-[10px] font-bold transition shadow-sm">
                                                    <i class="fas fa-truck text-[9px]"></i> A Transportadora
                                                </button>
                                                <span v-else class="text-[10px] text-gray-400 italic font-medium px-2">
                                                    <i class="fas fa-store text-gray-400 mr-1"></i> Reparto Local
                                                </span>
                                            </template>

                                            <span v-else-if="tabActiva === 'transportadora'" class="text-[10px] text-cyan-600 italic font-medium px-2">
                                                <i class="fas fa-truck-moving mr-1"></i> En tránsito
                                            </span>

                                            <span v-else class="text-[10px] text-emerald-600 italic font-bold px-2">
                                                <i class="fas fa-check-double mr-1"></i> Recibido
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="pedidosActuales.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                        No hay pedidos en {{ subTab }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL CAMBIO DE ESTADO -->
        <div v-if="modalEstadoVisible" class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-0 sm:p-3"
            @click.self="modalEstadoVisible = false">
            <div class="bg-white w-full sm:max-w-md sm:rounded-xl rounded-t-2xl shadow-xl overflow-hidden max-h-[92vh] flex flex-col">
                <div class="px-4 py-2.5 bg-cyan-600 flex items-center justify-between flex-shrink-0">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <i class="fas fa-truck text-xs"></i> Despachar a Transportadora
                        </h3>
                        <p class="text-[9px] text-white/80 truncate">Pedido #{{ pedidoSeleccionado?.NumeroPedido }}</p>
                    </div>
                    <button @click="modalEstadoVisible = false" class="text-white/80 hover:text-white p-1">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="p-4 space-y-3 overflow-y-auto flex-1">
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Transportadora</label>
                        <div class="flex gap-1">
                            <input ref="inputTransportadoraRef" v-model="busquedaTransportadora"
                                @input="filtrarTransportadoras" @focus="filtrarTransportadoras" @keydown="onKeydownTransportadora"
                                type="text" placeholder="Busca por nombre o CI/NIT..." autocomplete="off"
                                class="flex-1 min-w-0 border border-gray-300 rounded-md px-2.5 py-1.5 text-base sm:text-sm outline-none focus:border-primary-500" />
                            <button v-if="transportadoraSeleccionadaObj" @click="limpiarSeleccionTransportadora"
                                class="px-2.5 min-w-[40px] min-h-[40px] sm:min-w-0 sm:min-h-0 bg-gray-200 hover:bg-gray-300 rounded-md text-xs flex items-center justify-center">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <p v-if="!resultadosTransportadoras.length && busquedaTransportadora.trim().length >= 1 && !transportadoraSeleccionadaObj && props.transportadoras.length === 0"
                            class="text-[10px] text-amber-600 mt-0.5">
                            No hay transportadoras registradas. Crea una primero con el botón "Nueva Transportadora".
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">N° Guía</label>
                            <input v-model="numeroGuia" type="text" class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-base sm:text-sm outline-none focus:border-primary-500" placeholder="Ej: ABC-123">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Costo (Bs.)</label>
                            <input v-model.number="costoEnvio" type="number" inputmode="decimal" min="0" step="0.01" class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-base sm:text-sm outline-none focus:border-primary-500" placeholder="0.00">
                        </div>
                    </div>

                    <div v-if="transportadoraSeleccionadaObj" class="bg-green-50 border border-green-200 rounded-md p-2 text-[11px] text-green-800 break-words">
                        <i class="fas fa-check-circle mr-1"></i>
                        <b>{{ transportadoraSeleccionadaObj.identificador?.Nombre || 'Sin nombre' }}</b>
                        <span class="text-green-600">— CI/NIT: {{ transportadoraSeleccionadaObj.identificador?.CI_NIT || '-' }}</span>
                    </div>
                </div>

                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2 flex-shrink-0">
                    <button @click="modalEstadoVisible = false" class="px-3 py-2 sm:py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition min-h-[40px] sm:min-h-0">
                        Cancelar
                    </button>
                    <button @click="confirmarCambioEstado" class="px-3 py-2 sm:py-1.5 text-xs bg-cyan-500 hover:bg-cyan-600 text-white rounded-md transition flex items-center gap-1.5 font-medium min-h-[40px] sm:min-h-0">
                        <i class="fas fa-check text-[10px]"></i> Despachar
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL NUEVA TRANSPORTADORA -->
        <div v-if="modalTransportadoraVisible" class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-0 sm:p-3"
            @click.self="cerrarModalTransportadora">
            <div class="bg-white w-full sm:max-w-md sm:rounded-xl rounded-t-2xl shadow-xl overflow-hidden max-h-[92vh] flex flex-col">
                <div class="px-4 py-2.5 bg-primary-600 flex items-center justify-between flex-shrink-0">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <i class="fas fa-truck text-xs"></i> Nueva Transportadora
                        </h3>
                        <p class="text-[9px] text-white/80 truncate">Busca un identificador existente o crea uno nuevo</p>
                    </div>
                    <button @click="cerrarModalTransportadora" class="text-white/80 hover:text-white p-1">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="p-4 space-y-3 overflow-y-auto flex-1">
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                            Buscar identificador por nombre o CI/NIT
                        </label>
                        <div class="flex gap-1">
                            <input ref="inputIdentificadorRef" v-model="busquedaIdentificador"
                                @input="buscarIdentificadores" @focus="buscarIdentificadores" @keydown="onKeydownIdentificador"
                                type="text" placeholder="Escribe al menos 2 caracteres..." autocomplete="off"
                                class="flex-1 min-w-0 border border-gray-300 rounded-md px-2.5 py-1.5 text-base sm:text-sm outline-none focus:border-primary-500"
                                :disabled="!!identificadorSeleccionado" />
                            <button v-if="identificadorSeleccionado" @click="limpiarSeleccionIdentificador"
                                class="px-2.5 min-w-[40px] min-h-[40px] sm:min-w-0 sm:min-h-0 bg-gray-200 hover:bg-gray-300 rounded-md text-xs flex items-center justify-center">
                                <i class="fas fa-times"></i>
                            </button>
                            <button @click="abrirCrearIdentificador"
                                class="px-2.5 min-w-[40px] min-h-[40px] sm:min-w-0 sm:min-h-0 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs flex items-center justify-center">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <p v-if="buscandoIdentificador" class="text-[10px] text-gray-400 mt-0.5">Buscando...</p>
                        <p v-if="!buscandoIdentificador && busquedaIdentificador.trim().length >= 2 && !resultadosIdentificadores.length && !identificadorSeleccionado"
                            class="text-[10px] text-gray-400 mt-0.5">
                            No se encontraron coincidencias. Usa <i class="fas fa-plus text-primary-600"></i> para crear uno nuevo.
                        </p>
                    </div>

                    <div v-if="identificadorSeleccionado" class="bg-green-50 border border-green-200 rounded-md p-2 text-[11px] text-green-800 break-words">
                        <i class="fas fa-check-circle mr-1"></i>
                        Seleccionado: <b>{{ identificadorSeleccionado.Nombre }}</b>
                        <span class="text-green-600">— CI/NIT: {{ identificadorSeleccionado.CI_NIT }}</span>
                    </div>

                    <div v-if="mostrarCrearIdentificador && !identificadorSeleccionado" class="space-y-2 border-t pt-2">
                        <p class="text-[10px] text-gray-600 font-bold uppercase tracking-wide">
                            <i class="fas fa-plus-circle text-primary-600 mr-1"></i> Crear identificador nuevo
                        </p>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Nombre *</label>
                            <input v-model="nuevaTransportadora.Nombre" type="text"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-base sm:text-sm outline-none focus:border-primary-500"
                                placeholder="Ej: Transportes Illimani" />
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">CI / NIT *</label>
                            <input v-model.number="nuevaTransportadora.CI_NIT" type="number" inputmode="numeric"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-base sm:text-sm outline-none focus:border-primary-500"
                                placeholder="Ej: 12345678" />
                        </div>
                    </div>
                </div>

                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2 flex-shrink-0">
                    <button @click="cerrarModalTransportadora" class="px-3 py-2 sm:py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition min-h-[40px] sm:min-h-0">
                        Cancelar
                    </button>
                    <button @click="crearTransportadora" class="px-3 py-2 sm:py-1.5 text-xs bg-primary-600 hover:bg-primary-700 text-white rounded-md transition flex items-center gap-1.5 font-medium min-h-[40px] sm:min-h-0">
                        <i class="fas fa-save text-[10px]"></i> Guardar
                    </button>
                </div>
            </div>
        </div>

        <!-- DROPDOWN IDENTIFICADORES -->
        <Teleport to="body">
            <div v-if="resultadosIdentificadores.length && !identificadorSeleccionado && modalTransportadoraVisible"
                :style="dropdownStyle"
                class="bg-white border border-gray-200 rounded-md shadow-xl overflow-y-auto">
                <button v-for="(id, idx) in resultadosIdentificadores" :key="id.IdIdentificador"
                    @click="seleccionarIdentificador(id)" @mouseenter="indiceActivo = idx" :disabled="id.YaEsTransportadora"
                    class="w-full text-left px-3 py-2.5 text-xs flex justify-between items-center gap-2 border-b border-gray-100 last:border-b-0 transition"
                    :class="[id.YaEsTransportadora ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 active:bg-gray-100 cursor-pointer',
                             indiceActivo === idx && !id.YaEsTransportadora ? 'bg-primary-50' : '']">
                    <span class="truncate min-w-0 flex-1">
                        <b>{{ id.Nombre }}</b>
                        <span class="text-gray-400 ml-1">— {{ id.CI_NIT }}</span>
                    </span>
                    <span v-if="id.YaEsTransportadora" class="text-[9px] text-amber-600 font-bold whitespace-nowrap flex-shrink-0">
                        Ya es transportadora
                    </span>
                </button>
            </div>
        </Teleport>

        <!-- DROPDOWN TRANSPORTADORAS -->
        <Teleport to="body">
            <div v-if="resultadosTransportadoras.length && !transportadoraSeleccionadaObj && modalEstadoVisible"
                :style="dropdownTransportadoraStyle"
                class="bg-white border border-gray-200 rounded-md shadow-xl overflow-y-auto">
                <button v-for="(t, idx) in resultadosTransportadoras" :key="t.IdTransportadora"
                    @click="seleccionarTransportadora(t)" @mouseenter="indiceActivoTransportadora = idx"
                    class="w-full text-left px-3 py-2.5 text-xs flex justify-between items-center gap-2 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 active:bg-gray-100 cursor-pointer transition"
                    :class="indiceActivoTransportadora === idx ? 'bg-primary-50' : ''">
                    <span class="truncate min-w-0 flex-1">
                        <b>{{ t.identificador?.Nombre || 'Sin nombre' }}</b>
                        <span class="text-gray-400 ml-1">— {{ t.identificador?.CI_NIT || '-' }}</span>
                    </span>
                </button>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
@media (max-width: 640px) { input, select, textarea { font-size: 16px !important; } }
@media (min-width: 1024px) { input, select, button { font-size: 13px !important; } }
.overflow-x-auto { -webkit-overflow-scrolling: touch; scrollbar-width: thin; }
.overflow-x-auto::-webkit-scrollbar { height: 4px; }
.overflow-x-auto::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.overflow-x-auto::-webkit-scrollbar-track { background: #f1f5f9; }
.overflow-y-auto::-webkit-scrollbar { width: 4px; }
.overflow-y-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
</style>