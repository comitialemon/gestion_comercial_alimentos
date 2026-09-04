<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: Object,
    operador: Object,
    fechaSeleccionada: String,
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
const cargando = ref(false)
const datosCargados = ref(false)

const datos = ref({
    matriz: props.matriz || null,
    detalle: props.detalle || [],
    resumen: props.resumen || null,
})

// ==================== COMPUTED ====================
const tieneDatos = computed(() => {
    return datos.value.matriz && datos.value.matriz.sucursales && datos.value.matriz.sucursales.length > 0
})

// ==================== FUNCIONES ====================
const cargarDatos = () => {
    if (!fecha.value) {
        alert('Por favor selecciona una fecha')
        return
    }

    cargando.value = true

    const url = `/operacion/pedidos/reportes/informe-clientes-mayoristas?fecha=${fecha.value}`

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
        },
        onError: () => {
            cargando.value = false
            alert('Error al cargar los datos')
        }
    })
}

const exportarPDFResumen = () => {
    if (!fecha.value) {
        alert('Por favor selecciona una fecha')
        return
    }

    const form = document.createElement('form')
    form.method = 'POST'
    form.action = `/operacion/pedidos/reportes/informe-clientes-mayoristas/exportar-pdf-resumen`

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

    document.body.appendChild(form)
    form.submit()
    document.body.removeChild(form)
}

const exportarPDFDetalle = () => {
    if (!fecha.value) {
        alert('Por favor selecciona una fecha')
        return
    }

    const form = document.createElement('form')
    form.method = 'POST'
    form.action = `/operacion/pedidos/reportes/informe-clientes-mayoristas/exportar-pdf-detalle`

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
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chart-bar text-blue-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Informe de Pedidos - Clientes Mayoristas</h1>
                        <p class="text-xs text-gray-500">Visualiza y exporta pedidos agrupados por sucursal y operador</p>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[160px] max-w-[220px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha de Entrega</label>
                            <input
                                type="date"
                                v-model="fecha"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                @change="cargarDatos"
                            />
                        </div>

                        <div class="flex gap-1.5 flex-wrap">
                            <button
                                @click="exportarPDFResumen"
                                :disabled="cargando || !tieneDatos"
                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                            >
                                <i class="fas fa-file-pdf mr-1.5 text-[10px]"></i>
                                PDF Resumen
                            </button>
                            <button
                                @click="exportarPDFDetalle"
                                :disabled="cargando || !tieneDatos"
                                class="inline-flex items-center px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-md hover:bg-emerald-700 disabled:opacity-50 transition-colors"
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

                        <!-- Estado -->
                        <div class="text-right text-[10px] text-gray-500" v-if="!cargando && datosCargados">
                            <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">
                                <i class="fas fa-check-circle mr-1 text-[8px]"></i>
                                Actualizado
                            </span>
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

                <div v-else-if="!tieneDatos" class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-xl">
                    <div class="flex items-center text-sm">
                        <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                        <p class="text-blue-700">
                            No hay pedidos para la fecha seleccionada.
                        </p>
                    </div>
                </div>

                <div v-else>
                    <!-- ==================== SECCIÓN 1: MATRIZ ==================== -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-4">
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
                                        <!-- FILA DE SUCURSAL -->
                                        <tr class="bg-gray-100">
                                            <td 
                                                class="border border-gray-300 px-2 py-1.5 font-bold text-gray-800 text-xs" 
                                                :colspan="matriz.productos.length + 2"
                                            >
                                                {{ sucursal.nombre }}
                                            </td>
                                        </tr>

                                        <!-- FILAS DE OPERADORES -->
                                        <tr 
                                            v-for="operador in sucursal.operadores" 
                                            :key="operador.nombre"
                                            class="hover:bg-gray-50"
                                        >
                                            <td 
                                                class="border border-gray-300 px-2 py-1.5 text-left text-[10px]"
                                                style="padding-left: 20px;"
                                            >
                                                <span class="text-gray-400">-</span> {{ operador.nombre }}
                                            </td>
                                            <td
                                                v-for="valor in operador.valores"
                                                :key="valor"
                                                class="border border-gray-300 px-1.5 py-1.5 text-center text-[10px]"
                                            >
                                                {{ formatearNumero(valor) }}
                                            </td>
                                            <td 
                                                class="border border-gray-300 px-2 py-1.5 text-center font-bold text-[10px]"
                                            >
                                                {{ formatearNumero(operador.total) }}
                                            </td>
                                        </tr>

                                        <!-- SUBTOTAL -->
                                        <tr class="bg-emerald-50 font-bold">
                                            <td class="border border-gray-300 px-2 py-1.5 text-right pr-3 text-[10px]">
                                                SUBTOTAL
                                            </td>
                                            <td
                                                v-for="valor in sucursal.subtotal"
                                                :key="valor"
                                                class="border border-gray-300 px-1.5 py-1.5 text-center text-[10px]"
                                            >
                                                {{ formatearNumero(valor) }}
                                            </td>
                                            <td class="border border-gray-300 px-2 py-1.5 text-center text-[10px]">
                                                {{ formatearNumero(sucursal.total_sucursal) }}
                                            </td>
                                        </tr>
                                    </template>

                                    <!-- TOTAL GENERAL -->
                                    <tr class="bg-orange-50 font-bold">
                                        <td class="border border-gray-300 px-2 py-2 text-right pr-3 text-xs">
                                            TOTAL GENERAL
                                        </td>
                                        <td
                                            v-for="valor in matriz.totales_generales"
                                            :key="valor"
                                            class="border border-gray-300 px-1.5 py-2 text-center text-xs"
                                        >
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

                    <!-- ==================== SECCIÓN 2: DETALLE ==================== -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                            <h2 class="text-xs font-semibold text-gray-800 flex items-center gap-1.5">
                                <i class="fas fa-list-ul text-emerald-500 text-[10px]"></i>
                                Detalle de Pedidos
                            </h2>
                        </div>
                        <div class="p-3">
                            <div v-for="sucursal in detalle" :key="sucursal.sucursal" class="mb-4 last:mb-0">
                                <div class="bg-blue-50 border-l-4 border-blue-500 px-3 py-1.5 rounded-r mb-2">
                                    <h3 class="text-sm font-bold text-blue-800">
                                        {{ sucursal.sucursal }}
                                        <span class="text-xs font-normal text-gray-600 ml-2">
                                            (Total: {{ formatearNumero(sucursal.total_sucursal) }} und)
                                        </span>
                                    </h3>
                                </div>

                                <div v-for="operador in sucursal.operadores" :key="operador.nombre" class="ml-3 mb-3">
                                    <div class="bg-gray-50 border-l-4 border-gray-400 px-2.5 py-1 rounded-r mb-1.5">
                                        <h4 class="font-bold text-gray-700 text-xs">
                                            {{ operador.nombre }}
                                            <span class="text-[10px] font-normal text-gray-500 ml-2">
                                                (Total: {{ formatearNumero(operador.total_operador) }} und)
                                            </span>
                                        </h4>
                                    </div>

                                    <div v-for="pedido in operador.pedidos" :key="pedido.numero" class="ml-3 mb-2">
                                        <div class="text-xs font-medium text-gray-600 mb-0.5">
                                            Pedido #{{ pedido.numero }}
                                            <span class="text-[10px] text-gray-400 ml-2">
                                                Pedido: {{ pedido.fecha_pedido }} | Entrega: {{ pedido.fecha_entrega }}
                                            </span>
                                            <span class="float-right font-bold text-primary-600 text-xs">
                                                Total: {{ formatearNumero(pedido.total_pedido) }} und
                                            </span>
                                        </div>

                                        <div v-for="contenedor in pedido.contenedores" :key="contenedor.codigo" class="ml-4 mb-1.5">
                                            <div class="border border-gray-200 rounded-md p-2 bg-gray-50">
                                                <div class="font-semibold text-gray-700 text-xs mb-0.5">
                                                    {{ contenedor.nombre }}
                                                    <span class="text-[10px] text-gray-500 font-normal ml-2">
                                                        (Cód: {{ contenedor.codigo }} | Cap: {{ formatearNumero(contenedor.capacidad, 0) }} und)
                                                    </span>
                                                    <span class="float-right font-bold text-emerald-600 text-xs">
                                                        Total: {{ formatearNumero(contenedor.total) }} und
                                                    </span>
                                                </div>

                                                <div v-for="producto in contenedor.productos" :key="producto.nombre" class="ml-3 text-xs">
                                                    <span class="text-gray-700 text-[10px]">• {{ producto.nombre }}</span>
                                                    <span class="float-right font-medium text-[10px]">
                                                        {{ formatearNumero(producto.cantidad) }} und
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

.overflow-x-auto::-webkit-scrollbar {
    height: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>