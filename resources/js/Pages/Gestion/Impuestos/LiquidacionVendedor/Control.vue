<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import axios from 'axios'
import Liquidacion from './Liquidacion.vue'
import MiniInventario from '@/Pages/Gestion/Inventario/InventarioFisicoDiario/MiniInventario.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    fechasPendientes: Array
})

const fechaSeleccionada = ref('')
const loading = ref(false)
const mostrarLiquidacion = ref(false)
const mostrarMiniInventario = ref(false)
const liquidacionData = ref(null)
const conceptosData = ref(null)
const fechaId = ref(null)
const fechaStr = ref('')

// Datos del mini inventario
const productosMiniInventario = ref([])
const cantidadRequerida = ref(0)
const idCabecera = ref(null)
const esBorrador = ref(false)

const seleccionarFecha = async () => {
    if (!fechaSeleccionada.value) {
        alert('Seleccione una fecha')
        return
    }
    
    loading.value = true
    try {
        // 1. Verificar estado del mini inventario
        const estadoResponse = await axios.get(
            `/gestion/inventario/inventario-fisico-diario/estado/${fechaSeleccionada.value}`
        )
        
        console.log('🔍 Estado del mini inventario:', estadoResponse.data)
        
        if (!estadoResponse.data.success) {
            alert('Error al verificar el estado del inventario')
            loading.value = false
            return
        }

        // 2. Si requiere mini inventario, mostrarlo
        if (estadoResponse.data.requiereMiniInventario) {
            console.log('✅ Requiere mini inventario - Cargando productos...')
            
            const productosResponse = await axios.get(
                `/gestion/inventario/inventario-fisico-diario/obtener-productos/${fechaSeleccionada.value}`
            )
            
            console.log('📦 Productos recibidos:', productosResponse.data)
            
            if (productosResponse.data.success) {
                if (productosResponse.data.already_done) {
                    // Ya completó (por si acaso)
                    await cargarLiquidacion(fechaSeleccionada.value)
                } else {
                    // Mostrar mini inventario
                    productosMiniInventario.value = productosResponse.data.productos || []
                    cantidadRequerida.value = productosResponse.data.cantidad_requerida || 0
                    idCabecera.value = productosResponse.data.id_cabecera
                    esBorrador.value = productosResponse.data.es_borrador || false
                    fechaId.value = productosResponse.data.fecha_id
                    fechaStr.value = productosResponse.data.fecha_str
                    mostrarMiniInventario.value = true
                    mostrarLiquidacion.value = false
                    loading.value = false
                }
            } else {
                alert('Error al cargar productos para inventario: ' + productosResponse.data.message)
                loading.value = false
            }
            return
        }

        // 3. No requiere mini inventario → cargar liquidación
        await cargarLiquidacion(fechaSeleccionada.value)

    } catch (error) {
        console.error('❌ Error:', error)
        console.error('❌ Detalles:', error.response?.data)
        alert('Error al cargar los datos: ' + (error.response?.data?.message || error.message))
        loading.value = false
    }
}

const cargarLiquidacion = async (fechaIdParam) => {
    try {
        const response = await axios.get(`/gestion/impuestos/liquidacion-vendedor/datos/${fechaIdParam}`)
        
        if (response.data.success) {
            if (response.data.liquidacion) {
                liquidacionData.value = response.data.liquidacion
                conceptosData.value = null
            } else {
                liquidacionData.value = response.data.data
                conceptosData.value = response.data.conceptos
            }
            fechaId.value = response.data.fechaId
            fechaStr.value = response.data.fechaStr
            mostrarLiquidacion.value = true
            mostrarMiniInventario.value = false
        } else {
            alert('Error al cargar los datos de liquidación')
        }
    } catch (error) {
        console.error('Error:', error)
        alert('Error al cargar los datos de liquidación')
    } finally {
        loading.value = false
    }
}

const continuarDesdeMiniInventario = () => {
    mostrarMiniInventario.value = false
    cargarLiquidacion(fechaId.value)
}

const volver = () => {
    mostrarLiquidacion.value = false
    mostrarMiniInventario.value = false
    fechaSeleccionada.value = ''
    liquidacionData.value = null
    conceptosData.value = null
    productosMiniInventario.value = []
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-chart-line text-primary-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Liquidación de Ventas</h1>
                            <p class="text-xs text-gray-500">
                                {{ mostrarMiniInventario ? 'Realiza el inventario físico rápido' : 
                                   mostrarLiquidacion ? 'Confirmar montos' : 'Seleccione la fecha a liquidar' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Selector de fechas -->
                <div v-if="!mostrarLiquidacion && !mostrarMiniInventario" class="bg-white rounded-xl shadow-sm p-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        📅 Fecha de Liquidación
                    </label>
                    <select v-model="fechaSeleccionada" class="w-full border rounded-lg px-3 py-2 mb-4">
                        <option value="">-- Seleccione una fecha --</option>
                        <option v-for="fecha in fechasPendientes" :key="fecha.id" :value="fecha.id">
                            {{ fecha.fecha }}
                        </option>
                    </select>

                    <div v-if="fechasPendientes.length === 0" class="text-center py-4 text-gray-500">
                        <i class="fas fa-check-circle text-green-500 text-2xl mb-2 block"></i>
                        <p>No hay ventas pendientes de liquidación</p>
                    </div>

                    <button 
                        @click="seleccionarFecha" 
                        :disabled="!fechaSeleccionada || loading"
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white py-2 rounded-lg transition disabled:opacity-50"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
                        {{ loading ? 'Cargando...' : 'Continuar' }}
                    </button>
                </div>

                <!-- Mini Inventario -->
                <MiniInventario 
                    v-if="mostrarMiniInventario"
                    :productos="productosMiniInventario"
                    :fecha-id="fechaId"
                    :fecha-str="fechaStr"
                    :cantidad-requerida="cantidadRequerida"
                    :id-cabecera="idCabecera"
                    :es-borrador="esBorrador"
                    @continuar="continuarDesdeMiniInventario"
                    @volver="volver"
                />

                <!-- Formulario de liquidación -->
                <Liquidacion 
                    v-if="mostrarLiquidacion"
                    :liquidacion="liquidacionData"
                    :conceptos="conceptosData"
                    :fecha-str="fechaStr"
                    :fecha-id="fechaId"
                    @volver="volver"
                />
            </div>
        </div>
    </div>
</template>