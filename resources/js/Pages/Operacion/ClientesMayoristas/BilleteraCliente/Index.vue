<script setup>
import { ref, computed, inject } from 'vue'
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

const buscador = ref(props.filtros.search || '')
const estadoFiltro = ref(props.filtros.estado || '')

// Modal crear billetera
const mostrarModalCrear = ref(false)
const operadorSeleccionado = ref(null)
const saldoMinimoAlerta = ref('')
const buscadorOperador = ref('')
const creando = ref(false)

const operadoresFiltrados = computed(() => {
    if (!buscadorOperador.value) return props.operadoresSinBilletera
    const t = buscadorOperador.value.toLowerCase()
    return props.operadoresSinBilletera.filter(op =>
        op.Nombre.toLowerCase().includes(t) || (op.CI_NIT || '').includes(t)
    )
})

const totalBilleteras = computed(() => props.billeteras?.total || 0)

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
    mostrarModalCrear.value = true
}

const cerrarModalCrear = () => {
    mostrarModalCrear.value = false
    operadorSeleccionado.value = null
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
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-10">
        <div class="max-w-7xl mx-auto px-3 py-4">

            <!-- HEADER -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Billeteras de Operadores</h1>
                    <p class="text-[10px] text-gray-400">
                        {{ totalBilleteras }} billetera(s) creada(s)
                    </p>
                </div>
                <button
                    @click="abrirModalCrear"
                    class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition flex items-center gap-2 shadow-sm"
                >
                    <i class="fas fa-plus"></i>
                    Nueva Billetera
                </button>
            </div>

            <!-- FILTROS -->
            <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 min-w-[150px]">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input
                            type="text"
                            v-model="buscador"
                            @input="aplicarFiltros"
                            placeholder="Buscar por nombre o CI..."
                            class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none"
                        />
                    </div>

                    <select
                        v-model="estadoFiltro"
                        @change="aplicarFiltros"
                        class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm outline-none"
                    >
                        <option value="">Todas</option>
                        <option value="activas">Activas</option>
                        <option value="inactivas">Inactivas</option>
                    </select>

                    <button
                        v-if="buscador || estadoFiltro"
                        @click="limpiarFiltros"
                        class="text-xs text-gray-400 hover:text-gray-600 transition"
                    >
                        <i class="fas fa-times mr-1"></i>
                        Limpiar
                    </button>
                </div>
            </div>

            <!-- LISTA -->
            <div v-if="billeteras.data.length > 0" class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="divide-y divide-gray-100">
                    <div
                        v-for="b in billeteras.data"
                        :key="b.IdBilletera"
                        class="p-4 hover:bg-gray-50 transition flex flex-col sm:flex-row sm:items-center gap-3"
                    >
                        <!-- Info operador -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-gray-800 text-sm">
                                    {{ b.operador?.identificador?.Nombre || 'Sin nombre' }}
                                </span>
                                <span class="text-[10px] text-gray-400">
                                    CI: {{ b.operador?.identificador?.CI_NIT || '-' }}
                                </span>
                                <span
                                    class="px-2 py-0.5 text-[10px] rounded-full font-medium"
                                    :class="b.ActivoInactivo === 0
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-600'"
                                >
                                    <i :class="b.ActivoInactivo === 0 ? 'fas fa-check-circle' : 'fas fa-lock'" class="mr-1 text-[8px]"></i>
                                    {{ b.ActivoInactivo === 0 ? 'Activa' : 'Inactiva' }}
                                </span>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">
                                Creada: {{ formatearFecha(b.FechaCreacion) }}
                                <span v-if="b.sucursal"> · {{ b.sucursal.Nombre }}</span>
                            </p>
                        </div>

                        <!-- Saldo -->
                        <div class="text-right sm:text-center flex-shrink-0">
                            <p class="text-[10px] uppercase text-gray-400 tracking-wide">Saldo</p>
                            <p class="text-base font-bold text-primary-700">
                                Bs. {{ formatearSaldo(b.SaldoActual) }}
                            </p>
                        </div>

                        <!-- Acciones -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <Link
                                :href="`/operacion/pedidos/clientes-mayoristas/billeteras/${b.IdBilletera}`"
                                class="px-3 py-1.5 text-xs bg-primary-50 hover:bg-primary-100 text-primary-700 rounded-lg transition flex items-center gap-1"
                            >
                                <i class="fas fa-eye text-[10px]"></i>
                                Ver
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="billeteras.links?.length > 3" class="px-4 py-3 border-t border-gray-100 flex justify-center">
                    <div class="flex gap-1 flex-wrap">
                        <Link
                            v-for="link in billeteras.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="px-3 py-1.5 rounded-lg text-sm transition"
                            :class="{
                                'bg-primary-600 text-white': link.active,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200': !link.active && link.url,
                                'opacity-50 cursor-not-allowed': !link.url
                            }"
                            v-html="link.label"
                            preserve-state
                        />
                    </div>
                </div>
            </div>

            <!-- SIN BILLETERAS -->
            <div v-else class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="w-20 h-20 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-wallet text-primary-400 text-3xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-700">No hay billeteras</h3>
                <p class="text-sm text-gray-400 mt-1">
                    Crea la primera billetera para un operador PedidoClientes.
                </p>
                <button
                    @click="abrirModalCrear"
                    class="inline-block mt-4 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm transition"
                >
                    <i class="fas fa-plus mr-1"></i>
                    Crear primera billetera
                </button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL CREAR BILLETERA -->
        <!-- ============================================================ -->
        <div
            v-if="mostrarModalCrear"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="cerrarModalCrear"
        >
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-gray-800">
                            <i class="fas fa-wallet text-primary-500 mr-2"></i>
                            Nueva Billetera
                        </h2>
                        <p class="text-[10px] text-gray-400">
                            Selecciona un operador PedidoClientes
                        </p>
                    </div>
                    <button @click="cerrarModalCrear" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4">
                    <!-- Buscador -->
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input
                            type="text"
                            v-model="buscadorOperador"
                            placeholder="Buscar operador..."
                            class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-400"
                        />
                    </div>

                    <!-- Lista de operadores -->
                    <div v-if="!operadorSeleccionado" class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg">
                        <div v-if="operadoresFiltrados.length === 0" class="p-6 text-center text-sm text-gray-400">
                            <i class="fas fa-inbox text-2xl mb-2"></i>
                            <p>No hay operadores disponibles</p>
                        </div>
                        <button
                            v-for="op in operadoresFiltrados"
                            :key="op.IdOperadorPedidoCliente"
                            @click="operadorSeleccionado = op"
                            class="w-full text-left px-3 py-2 hover:bg-primary-50 transition border-b border-gray-100 last:border-b-0"
                        >
                            <p class="text-sm font-medium text-gray-800">{{ op.Nombre }}</p>
                            <p class="text-[10px] text-gray-400">CI: {{ op.CI_NIT }}</p>
                        </button>
                    </div>

                    <!-- Operador seleccionado -->
                    <div v-else class="bg-primary-50 border border-primary-200 rounded-lg p-3 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] text-primary-600 uppercase">Operador seleccionado</p>
                            <p class="text-sm font-bold text-primary-800">{{ operadorSeleccionado.Nombre }}</p>
                            <p class="text-[10px] text-primary-600">CI: {{ operadorSeleccionado.CI_NIT }}</p>
                        </div>
                        <button
                            @click="operadorSeleccionado = null"
                            class="text-primary-500 hover:text-primary-700"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Saldo mínimo alerta -->
                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">
                            Saldo mínimo de alerta (opcional)
                        </label>
                        <input
                            type="number"
                            v-model="saldoMinimoAlerta"
                            placeholder="Ej: 500.00"
                            step="0.01"
                            min="0"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-400"
                        />
                        <p class="text-[10px] text-gray-400 mt-1">
                            Si el saldo baja de este monto, se mostrará una alerta.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex gap-2 justify-end">
                    <button
                        @click="cerrarModalCrear"
                        class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-200 rounded-lg transition"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="crearBilletera"
                        :disabled="!operadorSeleccionado || creando"
                        class="px-4 py-2 text-sm bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition flex items-center gap-2"
                    >
                        <i v-if="creando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check"></i>
                        {{ creando ? 'Creando...' : 'Crear Billetera' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>