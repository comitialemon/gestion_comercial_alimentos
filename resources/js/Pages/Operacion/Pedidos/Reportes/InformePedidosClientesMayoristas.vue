<template>
    <div class="p-6">
        <!-- HEADER -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Informe de Pedidos - Clientes Mayoristas</h1>
            <p class="text-gray-600">Visualiza y exporta pedidos agrupados por sucursal y operador</p>
        </div>

        <!-- FILTROS -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <div class="flex flex-wrap items-end gap-3">
                <!-- Fecha -->
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        Fecha de Entrega
                    </label>
                    <input
                        type="date"
                        v-model="fecha"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-1.5 px-2"
                        @change="cargarDatos"
                    />
                </div>

                <!-- Botones en fila -->
                <div class="flex gap-2 flex-wrap">
                    <button
                        @click="exportarPDFResumen"
                        :disabled="cargando || !tieneDatos"
                        class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                    >
                        <i class="fas fa-file-pdf mr-1.5 text-xs"></i>
                        PDF Resumen
                    </button>
                    <button
                        @click="exportarPDFDetalle"
                        :disabled="cargando || !tieneDatos"
                        class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs rounded-md hover:bg-green-700 disabled:opacity-50 transition-colors"
                    >
                        <i class="fas fa-file-pdf mr-1.5 text-xs"></i>
                        PDF Detalle
                    </button>
                    <button
                        @click="cargarDatos"
                        :disabled="cargando"
                        class="inline-flex items-center px-3 py-1.5 bg-gray-600 text-white text-xs rounded-md hover:bg-gray-700 disabled:opacity-50 transition-colors"
                    >
                        <i class="fas fa-sync-alt mr-1.5 text-xs" :class="{'animate-spin': cargando}"></i>
                        Actualizar
                    </button>
                </div>

                <!-- Estado -->
                <div class="text-right text-xs text-gray-500" v-if="!cargando && datosCargados">
                    <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded">
                        <i class="fas fa-check-circle mr-1 text-xs"></i>
                        Actualizado
                    </span>
                </div>
            </div>
        </div>

        <!-- CONTENIDO -->
        <div v-if="cargando" class="flex justify-center items-center py-12">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin text-3xl text-blue-500 mb-3"></i>
                <p class="text-gray-600 text-sm">Cargando datos...</p>
            </div>
        </div>

        <div v-else-if="!datosCargados" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
            <div class="flex items-center text-sm">
                <i class="fas fa-info-circle text-yellow-400 mr-2"></i>
                <p class="text-yellow-700">
                    Selecciona una fecha y haz clic en "Actualizar" para cargar los datos.
                </p>
            </div>
        </div>

        <div v-else-if="!tieneDatos" class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
            <div class="flex items-center text-sm">
                <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                <p class="text-blue-700">
                    No hay pedidos para la fecha seleccionada.
                </p>
            </div>
        </div>

        <div v-else>
            <!-- SECCIÓN 1: MATRIZ -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-gray-100 px-4 py-2.5 border-b">
                    <h2 class="text-base font-semibold text-gray-800">
                        <i class="fas fa-table mr-2 text-blue-500"></i>
                        Resumen por Sucursal y Operador
                    </h2>
                </div>
                <div class="p-4 overflow-x-auto">
                    <table class="min-w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-blue-50">
                                <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700 text-xs" style="min-width: 180px;">
                                    SUCURSAL
                                </th>
                                <th
                                    v-for="producto in matriz.productos"
                                    :key="producto.id"
                                    class="border border-gray-300 px-2 py-2 text-center font-semibold text-gray-700 text-xs"
                                    style="min-width: 80px;"
                                >
                                    <div class="text-xs leading-tight">{{ producto.nombre }}</div>
                                </th>
                                <th class="border border-gray-300 px-3 py-2 text-center font-semibold text-gray-700 text-xs" style="min-width: 80px;">
                                    TOTAL
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="sucursal in matriz.sucursales" :key="sucursal.nombre">
                                <!-- FILA DE SUCURSAL (encabezado) -->
                                <tr class="bg-gray-100">
                                    <td 
                                        class="border border-gray-300 px-3 py-2 font-bold text-gray-800 text-sm" 
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
                                        class="border border-gray-300 px-3 py-2 text-left text-xs"
                                        style="padding-left: 25px; font-weight: normal; border-top: none;"
                                    >
                                        <span class="text-gray-400">-</span> {{ operador.nombre }}
                                    </td>
                                    <td
                                        v-for="valor in operador.valores"
                                        :key="valor"
                                        class="border border-gray-300 px-2 py-2 text-center text-xs"
                                        style="border-top: none;"
                                    >
                                        {{ formatearNumero(valor) }}
                                    </td>
                                    <td 
                                        class="border border-gray-300 px-3 py-2 text-center font-bold text-xs"
                                        style="border-top: none;"
                                    >
                                        {{ formatearNumero(operador.total) }}
                                    </td>
                                </tr>
                                
                                <!-- SUBTOTAL -->
                                <tr class="bg-green-50 font-bold">
                                    <td class="border border-gray-300 px-3 py-2 text-right pr-4 text-xs">
                                        SUBTOTAL
                                    </td>
                                    <td
                                        v-for="valor in sucursal.subtotal"
                                        :key="valor"
                                        class="border border-gray-300 px-2 py-2 text-center text-xs"
                                    >
                                        {{ formatearNumero(valor) }}
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-center text-xs">
                                        {{ formatearNumero(sucursal.total_sucursal) }}
                                    </td>
                                </tr>
                            </template>
                            
                            <!-- TOTAL GENERAL -->
                            <tr class="bg-orange-50 font-bold">
                                <td class="border border-gray-300 px-3 py-2.5 text-right pr-4 text-sm">
                                    TOTAL GENERAL
                                </td>
                                <td
                                    v-for="valor in matriz.totales_generales"
                                    :key="valor"
                                    class="border border-gray-300 px-2 py-2.5 text-center text-sm"
                                >
                                    {{ formatearNumero(valor) }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2.5 text-center text-sm">
                                    {{ formatearNumero(matriz.total_general) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECCIÓN 2: DETALLE -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-gray-100 px-4 py-2.5 border-b">
                    <h2 class="text-base font-semibold text-gray-800">
                        <i class="fas fa-list-ul mr-2 text-green-500"></i>
                        Detalle de Pedidos
                    </h2>
                </div>
                <div class="p-4">
                    <div v-for="sucursal in detalle" :key="sucursal.sucursal" class="mb-6">
                        <div class="bg-blue-50 border-l-4 border-blue-500 px-4 py-2 rounded-r mb-3">
                            <h3 class="text-base font-bold text-blue-800">
                                {{ sucursal.sucursal }}
                                <span class="text-sm font-normal text-gray-600 ml-2">
                                    (Total: {{ formatearNumero(sucursal.total_sucursal) }} und)
                                </span>
                            </h3>
                        </div>

                        <div v-for="operador in sucursal.operadores" :key="operador.nombre" class="ml-4 mb-4">
                            <div class="bg-gray-50 border-l-4 border-gray-400 px-3 py-1 rounded-r mb-2">
                                <h4 class="font-bold text-gray-700 text-sm">
                                    {{ operador.nombre }}
                                    <span class="text-xs font-normal text-gray-500 ml-2">
                                        (Total: {{ formatearNumero(operador.total_operador) }} und)
                                    </span>
                                </h4>
                            </div>

                            <div v-for="pedido in operador.pedidos" :key="pedido.numero" class="ml-4 mb-3">
                                <div class="text-sm font-medium text-gray-600 mb-1">
                                    Pedido #{{ pedido.numero }}
                                    <span class="text-xs text-gray-400 ml-2">
                                        Pedido: {{ pedido.fecha_pedido }} | Entrega: {{ pedido.fecha_entrega }}
                                    </span>
                                    <span class="float-right font-bold text-blue-600 text-sm">
                                        Total: {{ formatearNumero(pedido.total_pedido) }} und
                                    </span>
                                </div>

                                <div v-for="contenedor in pedido.contenedores" :key="contenedor.codigo" class="ml-6 mb-2">
                                    <div class="border border-gray-200 rounded-md p-2 bg-gray-50">
                                        <div class="font-semibold text-gray-700 text-sm mb-1">
                                            {{ contenedor.nombre }}
                                            <span class="text-xs text-gray-500 font-normal ml-2">
                                                (Cód: {{ contenedor.codigo }} | Cap: {{ formatearNumero(contenedor.capacidad, 0) }} und)
                                            </span>
                                            <span class="float-right font-bold text-green-600 text-sm">
                                                Total: {{ formatearNumero(contenedor.total) }} und
                                            </span>
                                        </div>

                                        <div v-for="producto in contenedor.productos" :key="producto.nombre" class="ml-4 text-sm">
                                            <span class="text-gray-700 text-xs">• {{ producto.nombre }}</span>
                                            <span class="float-right font-medium text-xs">
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
</template>

<script>
import { router } from '@inertiajs/vue3';

export default {
    props: {
        empresa: Object,
        operador: Object,
        fechaSeleccionada: String,
        matriz: Object,
        detalle: Array,
        resumen: Object,
    },

    data() {
        return {
            fecha: this.fechaSeleccionada || new Date().toISOString().split('T')[0],
            cargando: false,
            datosCargados: false,
            datos: {
                matriz: this.matriz || null,
                detalle: this.detalle || [],
                resumen: this.resumen || null,
            }
        };
    },

    computed: {
        tieneDatos() {
            return this.datos.matriz && this.datos.matriz.sucursales && this.datos.matriz.sucursales.length > 0;
        }
    },

    mounted() {
        if (this.fechaSeleccionada && this.matriz) {
            this.datosCargados = true;
        }
    },

    methods: {
        cargarDatos() {
            if (!this.fecha) {
                alert('Por favor selecciona una fecha');
                return;
            }

            this.cargando = true;
            
            const url = `/operacion/pedidos/reportes/informe-clientes-mayoristas?fecha=${this.fecha}`;
            
            router.visit(url, {
                method: 'get',
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    this.datos.matriz = page.props.matriz || null;
                    this.datos.detalle = page.props.detalle || [];
                    this.datos.resumen = page.props.resumen || null;
                    this.datosCargados = true;
                    this.cargando = false;
                },
                onError: () => {
                    this.cargando = false;
                    alert('Error al cargar los datos');
                }
            });
        },

        exportarPDFResumen() {
            if (!this.fecha) {
                alert('Por favor selecciona una fecha');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/operacion/pedidos/reportes/informe-clientes-mayoristas/exportar-pdf-resumen`;
            
            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = document.querySelector('meta[name="csrf-token"]').content;
            form.appendChild(token);

            const fechaInput = document.createElement('input');
            fechaInput.type = 'hidden';
            fechaInput.name = 'fecha';
            fechaInput.value = this.fecha;
            form.appendChild(fechaInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        },

        exportarPDFDetalle() {
            if (!this.fecha) {
                alert('Por favor selecciona una fecha');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/operacion/pedidos/reportes/informe-clientes-mayoristas/exportar-pdf-detalle`;
            
            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = document.querySelector('meta[name="csrf-token"]').content;
            form.appendChild(token);

            const fechaInput = document.createElement('input');
            fechaInput.type = 'hidden';
            fechaInput.name = 'fecha';
            fechaInput.value = this.fecha;
            form.appendChild(fechaInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        },

        formatearNumero(valor, decimales = 2) {
            if (valor === null || valor === undefined) return '0.00';
            return Number(valor).toFixed(decimales).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    }
};
</script>

<style scoped>
/* Estilos adicionales si son necesarios */
</style>