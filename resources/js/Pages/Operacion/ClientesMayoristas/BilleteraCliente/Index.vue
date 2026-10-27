<script setup>
import { ref, computed, inject, onMounted, onUnmounted, nextTick } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    billeteras: {
        type: Object,
        default: () => ({ data: [] })
    },
    operadoresSinBilletera: {
        type: Array,
        default: () => []
    },
    filtros: {
        type: Object,
        default: () => ({})
    },
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 640
}

// ==================== ESTADO ====================
const buscador = ref(props.filtros.search || '')
const estadoFiltro = ref(props.filtros.estado || '')

// Modal crear billetera
const mostrarModalCrear = ref(false)
const operadorSeleccionado = ref(null)
const saldoMinimoAlerta = ref('')
const buscadorOperador = ref('')
const mostrarListaOperadores = ref(false)
const creando = ref(false)
const operadorDropdownRef = ref(null)
const operadorInputRef = ref(null)
const dropdownPos = ref({ top: 0, left: 0, width: 0 })

// ==================== COMPUTED ====================
const totalBilleteras = computed(() => props.billeteras?.total || 0)

const operadoresFiltrados = computed(() => {
    if (!buscadorOperador.value || buscadorOperador.value.trim() === '') {
        return props.operadoresSinBilletera
    }
    const termino = buscadorOperador.value.toLowerCase().trim()
    return props.operadoresSinBilletera.filter(op => {
        const nombre = (op.Nombre || '').toLowerCase()
        const ciNit = String(op.CI_NIT || '').toLowerCase()
        return nombre.includes(termino) || ciNit.includes(termino)
    })
})

const operadorNombre = computed(() => {
    if (!operadorSeleccionado.value) return ''
    return operadorSeleccionado.value.Nombre || ''
})

// ==================== FUNCIONES ====================
const actualizarPosicionDropdown = () => {
    if (operadorInputRef.value) {
        const rect = operadorInputRef.value.getBoundingClientRect()
        dropdownPos.value = {
            top: rect.bottom + 4,
            left: rect.left,
            width: rect.width
        }
    }
}

const abrirDropdown = () => {
    actualizarPosicionDropdown()
    mostrarListaOperadores.value = true
}

const aplicarFiltros = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/billeteras', {
        search: buscador.value || undefined,
        estado: estadoFiltro.value || undefined,
    }, { preserveState: true, replace: true })
}

const limpiarFiltros = () => {
    buscador.value = ''
    estadoFiltro.value = ''
    aplicarFiltros()
}

const formatearSaldo = (saldo) => {
    return parseFloat(saldo || 0).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO', {
        day: '2-digit', month: '2-digit', year: 'numeric'
    })
}

const abrirModalCrear = () => {
    operadorSeleccionado.value = null
    saldoMinimoAlerta.value = ''
    buscadorOperador.value = ''
    mostrarListaOperadores.value = false
    mostrarModalCrear.value = true
}

const cerrarModalCrear = () => {
    mostrarModalCrear.value = false
    operadorSeleccionado.value = null
    buscadorOperador.value = ''
    mostrarListaOperadores.value = false
}

const seleccionarOperador = (op) => {
    operadorSeleccionado.value = op
    buscadorOperador.value = op.Nombre
    mostrarListaOperadores.value = false
}

const limpiarOperador = () => {
    operadorSeleccionado.value = null
    buscadorOperador.value = ''
    mostrarListaOperadores.value = false
}

const handleClickOutside = (event) => {
    if (operadorDropdownRef.value && !operadorDropdownRef.value.contains(event.target)) {
        if (operadorInputRef.value && !operadorInputRef.value.contains(event.target)) {
            mostrarListaOperadores.value = false
        }
    }
}

const crearBilletera = async () => {
    if (!operadorSeleccionado.value) {
        toast?.warning('Selecciona un operador')
        return
    }

    creando.value = true

    try {
        const res = await axios.post('/operacion/pedidos/clientes-mayoristas/billeteras', {
            IdOperadorPedidoCliente: operadorSeleccionado.value.IdOperadorPedidoCliente,
            SaldoMinimoAlerta: saldoMinimoAlerta.value || null,
        })

        if (res.data.success) {
            toast?.success(res.data.message)
            cerrarModalCrear()
            router.reload({ only: ['billeteras', 'operadoresSinBilletera'] })
        }
    } catch (e) {
        toast?.error('Error', e.response?.data?.message || 'Error al crear billetera')
    } finally {
        creando.value = false
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
    window.addEventListener('scroll', () => {
        if (mostrarListaOperadores.value) actualizarPosicionDropdown()
    }, true)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-wallet text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Billeteras de Operadores</h1>
                            <p class="text-xs text-gray-500">{{ totalBilleteras }} billetera(s) creada(s)</p>
                        </div>
                    </div>
                    <button
                        @click="abrirModalCrear"
                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition"
                    >
                        <i class="fas fa-plus text-[10px]"></i> Nueva Billetera
                    </button>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[140px] max-w-[240px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar</label>
                            <input
                                type="text"
                                v-model="buscador"
                                @input="aplicarFiltros"
                                placeholder="Nombre o CI..."
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                        </div>

                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Estado</label>
                            <select
                                v-model="estadoFiltro"
                                @change="aplicarFiltros"
                                class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                                <option value="">Todas</option>
                                <option value="activas">Activas</option>
                                <option value="inactivas">Inactivas</option>
                            </select>
                        </div>

                        <button
                            v-if="buscador || estadoFiltro"
                            @click="limpiarFiltros"
                            class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-xs font-medium transition flex items-center gap-1.5"
                        >
                            <i class="fas fa-eraser text-[10px]"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- ==================== LISTA ==================== -->
                <div v-if="billeteras.data.length > 0" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="b in billeteras.data"
                            :key="b.IdBilletera"
                            class="px-3 py-2 hover:bg-gray-50 transition flex flex-col sm:flex-row sm:items-center gap-2"
                        >
                            <!-- Info operador -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-semibold text-gray-800 text-xs">
                                        {{ b.operador?.identificador?.Nombre || 'Sin nombre' }}
                                    </span>
                                    <span class="text-[9px] text-gray-400 font-mono">
                                        CI: {{ b.operador?.identificador?.CI_NIT || '-' }}
                                    </span>
                                    <span
                                        class="px-1.5 py-0.5 text-[8px] rounded-full font-medium"
                                        :class="b.ActivoInactivo === 0
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-gray-100 text-gray-500'"
                                    >
                                        <i :class="b.ActivoInactivo === 0 ? 'fas fa-check-circle' : 'fas fa-lock'" class="mr-0.5 text-[7px]"></i>
                                        {{ b.ActivoInactivo === 0 ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </div>
                                <p class="text-[9px] text-gray-400 mt-0.5">
                                    Creada: {{ formatearFecha(b.FechaCreacion) }}
                                    <span v-if="b.sucursal"> · {{ b.sucursal.Nombre }}</span>
                                </p>
                            </div>

                            <!-- Saldo -->
                            <div class="text-right sm:text-center flex-shrink-0">
                                <p class="text-[8px] uppercase text-gray-400 tracking-wide">Saldo</p>
                                <p class="text-sm font-bold text-primary-700">
                                    Bs. {{ formatearSaldo(b.SaldoActual) }}
                                </p>
                            </div>

                            <!-- Acciones -->
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <Link
                                    :href="`/operacion/pedidos/clientes-mayoristas/billeteras/${b.IdBilletera}`"
                                    class="px-2.5 py-1 text-[10px] bg-primary-50 hover:bg-primary-100 text-primary-700 rounded-md transition flex items-center gap-1 font-medium"
                                >
                                    <i class="fas fa-eye text-[9px]"></i>
                                    Ver
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div v-if="billeteras.links?.length > 3" class="px-3 py-2 border-t border-gray-200 bg-gray-50 flex justify-center">
                        <div class="flex gap-1 flex-wrap">
                            <Link
                                v-for="link in billeteras.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                class="px-2.5 py-1 rounded-lg border text-[10px] transition"
                                :class="{
                                    'bg-primary-600 text-white border-primary-600': link.active,
                                    'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url,
                                    'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url
                                }"
                                v-html="link.label"
                                preserve-state
                            />
                        </div>
                    </div>
                </div>

                <!-- ==================== SIN BILLETERAS ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <div class="w-16 h-16 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-wallet text-primary-400 text-2xl"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-700">No hay billeteras</h3>
                    <p class="text-xs text-gray-400 mt-1">
                        Crea la primera billetera para un operador.
                    </p>
                    <button
                        @click="abrirModalCrear"
                        class="inline-block mt-3 px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition"
                    >
                        <i class="fas fa-plus mr-1 text-[10px]"></i>
                        Crear primera billetera
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL CREAR BILLETERA ==================== -->
        <div
            v-if="mostrarModalCrear"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3"
            @click.self="cerrarModalCrear"
        >
            <div class="bg-white rounded-xl w-full max-w-md shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="px-4 py-2.5 bg-primary-600 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <i class="fas fa-wallet text-xs"></i>
                            Nueva Billetera
                        </h2>
                        <p class="text-[9px] text-white/80">Selecciona un operador</p>
                    </div>
                    <button @click="cerrarModalCrear" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-4 space-y-3">
                    <!-- Buscador de operador -->
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Operador *</label>
                        <div class="relative" ref="operadorDropdownRef">
                            <input
                                ref="operadorInputRef"
                                type="text"
                                v-model="buscadorOperador"
                                @focus="abrirDropdown"
                                @input="abrirDropdown"
                                placeholder="Buscar operador por nombre o CI..."
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-7 focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                            <button
                                v-if="operadorSeleccionado"
                                @click="limpiarOperador"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                type="button"
                            >
                                <i class="fas fa-times text-[10px]"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Saldo mínimo alerta -->
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Saldo mínimo de alerta (opcional)</label>
                        <input
                            type="number"
                            v-model="saldoMinimoAlerta"
                            placeholder="Ej: 500.00"
                            step="0.01"
                            min="0"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                        />
                        <p class="text-[8px] text-gray-400 mt-0.5">
                            Si el saldo baja de este monto, se mostrará una alerta.
                        </p>
                    </div>

                    <!-- Operador seleccionado -->
                    <div v-if="operadorSeleccionado" class="bg-emerald-50 border border-emerald-200 rounded-md p-2 flex items-center gap-1.5">
                        <i class="fas fa-check-circle text-emerald-600 text-xs"></i>
                        <span class="text-[10px] text-emerald-700">
                            Operador: <strong>{{ operadorNombre }}</strong>
                        </span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button
                        @click="cerrarModalCrear"
                        class="px-3 py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="crearBilletera"
                        :disabled="!operadorSeleccionado || creando"
                        class="px-3 py-1.5 text-xs bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md transition flex items-center gap-1.5 font-medium"
                    >
                        <i v-if="creando" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-check text-[10px]"></i>
                        {{ creando ? 'Creando...' : 'Crear Billetera' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- 🔥 DROPDOWN CON TELEPORT (FUERA DEL MODAL) -->
        <Teleport to="body">
            <div
                v-if="mostrarListaOperadores && operadoresFiltrados.length > 0"
                :style="{
                    position: 'fixed',
                    top: dropdownPos.top + 'px',
                    left: dropdownPos.left + 'px',
                    width: dropdownPos.width + 'px',
                    zIndex: 9999
                }"
                class="bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
            >
                <div
                    v-for="op in operadoresFiltrados"
                    :key="op.IdOperadorPedidoCliente"
                    @click="seleccionarOperador(op)"
                    class="px-2.5 py-1.5 hover:bg-primary-50 cursor-pointer text-xs border-b border-gray-100 last:border-0 flex items-center justify-between"
                    :class="operadorSeleccionado?.IdOperadorPedidoCliente === op.IdOperadorPedidoCliente ? 'bg-primary-50' : ''"
                >
                    <div>
                        <p class="font-medium text-gray-800">{{ op.Nombre }}</p>
                        <p class="text-[9px] text-gray-400">CI: {{ op.CI_NIT || '-' }}</p>
                    </div>
                    <i v-if="operadorSeleccionado?.IdOperadorPedidoCliente === op.IdOperadorPedidoCliente"
                       class="fas fa-check-circle text-primary-600 text-[10px]"></i>
                </div>
            </div>

            <!-- Sin resultados -->
            <div
                v-if="mostrarListaOperadores && operadoresFiltrados.length === 0 && buscadorOperador"
                :style="{
                    position: 'fixed',
                    top: dropdownPos.top + 'px',
                    left: dropdownPos.left + 'px',
                    width: dropdownPos.width + 'px',
                    zIndex: 9999
                }"
                class="bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-[10px]"
            >
                <i class="fas fa-search mr-1"></i> No se encontraron operadores
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

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