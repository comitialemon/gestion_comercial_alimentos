<!-- resources/js/Pages/Gestion/Inventario/ProductosVenta/components/DisponibilidadTab.vue -->

<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    productoId: {
        type: Number,
        required: true
    }
})

const emit = defineEmits(['update'])

const loading = ref(false)
const guardando = ref(false)
const sucursales = ref([])
const configuraciones = ref({})
const diasSemana = [
    { valor: 1, nombre: 'Lun', abrev: 'L' },
    { valor: 2, nombre: 'Mar', abrev: 'M' },
    { valor: 3, nombre: 'Mié', abrev: 'X' },
    { valor: 4, nombre: 'Jue', abrev: 'J' },
    { valor: 5, nombre: 'Vie', abrev: 'V' },
    { valor: 6, nombre: 'Sáb', abrev: 'S' },
    { valor: 7, nombre: 'Dom', abrev: 'D' }
]

// Cargar sucursales
const cargarSucursales = async () => {
    try {
        const response = await axios.get('/gestion/inventario/productos-venta/sucursales-disponibilidad')
        if (response.data.success) {
            sucursales.value = response.data.sucursales || []
            // Inicializar configuraciones
            sucursales.value.forEach(s => {
                if (!configuraciones.value[s.id]) {
                    configuraciones.value[s.id] = []
                }
            })
        }
    } catch (error) {
        console.error('Error cargando sucursales:', error)
    }
}

// Cargar configuración actual
const cargarConfiguracion = async () => {
    if (!props.productoId) return
    
    loading.value = true
    try {
        const response = await axios.get(`/gestion/inventario/productos-venta/${props.productoId}/disponibilidad-dias`)
        if (response.data.success) {
            const diasPorSucursal = response.data.dias || {}
            // Actualizar configuraciones
            Object.keys(diasPorSucursal).forEach(sucursalId => {
                configuraciones.value[sucursalId] = diasPorSucursal[sucursalId] || []
            })
        }
    } catch (error) {
        console.error('Error cargando configuración:', error)
    } finally {
        loading.value = false
    }
}

// Toggle día
const toggleDia = (sucursalId, diaValor) => {
    if (!configuraciones.value[sucursalId]) {
        configuraciones.value[sucursalId] = []
    }
    
    const index = configuraciones.value[sucursalId].indexOf(diaValor)
    if (index === -1) {
        configuraciones.value[sucursalId].push(diaValor)
    } else {
        configuraciones.value[sucursalId].splice(index, 1)
    }
}

// Verificar si un día está seleccionado
const diaSeleccionado = (sucursalId, diaValor) => {
    if (!configuraciones.value[sucursalId]) return false
    return configuraciones.value[sucursalId].includes(diaValor)
}

// Guardar configuración
const guardarConfiguracion = async () => {
    if (!props.productoId) {
        alert('Primero guarda el producto antes de configurar disponibilidad')
        return
    }
    
    guardando.value = true
    try {
        const datos = {
            IdProducto: props.productoId,
            dias_por_sucursal: Object.keys(configuraciones.value).map(sucursalId => ({
                IdSucursal: parseInt(sucursalId),
                dias: configuraciones.value[sucursalId] || []
            }))
        }
        
        await axios.post('/gestion/inventario/productos-venta/disponibilidad-dias', datos)
        
        emit('update', configuraciones.value)
        alert('✅ Días de disponibilidad guardados correctamente')
        
    } catch (error) {
        console.error('Error guardando:', error)
        alert('❌ Error al guardar: ' + (error.response?.data?.message || error.message))
    } finally {
        guardando.value = false
    }
}

// Cargar datos al montar
onMounted(async () => {
    await cargarSucursales()
    if (props.productoId) {
        await cargarConfiguracion()
    }
})

// Recargar cuando cambia el productoId
watch(() => props.productoId, async (nuevoId) => {
    if (nuevoId) {
        await cargarConfiguracion()
    }
})
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
            <div class="flex items-center gap-2.5">
                <div class="p-2 bg-primary-50 rounded-lg text-primary-600">
                    <i class="fas fa-calendar-day text-xs sm:text-sm"></i>
                </div>
                <div>
                    <span class="text-xs sm:text-sm font-semibold text-gray-800 block">Días de Disponibilidad</span>
                    <span class="text-[10px] sm:text-xs text-gray-500">Seleccione los días en que este producto estará disponible para la venta.</span>
                </div>
            </div>
            <button @click="guardarConfiguracion" :disabled="guardando || loading || !productoId"
                    class="w-full sm:w-auto px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-xs font-medium flex items-center justify-center gap-2 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                <i v-else class="fas fa-save text-[10px]"></i>
                {{ guardando ? 'Guardando...' : 'Guardar configuración' }}
            </button>
        </div>

        <!-- Mensaje si no hay producto guardado -->
        <div v-if="!productoId" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
            <i class="fas fa-info-circle text-yellow-500 text-sm block mb-1"></i>
            <p class="text-xs text-yellow-700">
                Guarda el producto primero para poder configurar los días de disponibilidad.
            </p>
        </div>

        <!-- Configuración por sucursal -->
        <div v-else-if="!loading" class="space-y-4">
            <div v-for="sucursal in sucursales" :key="sucursal.id" 
                 class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                
                <!-- Header de sucursal -->
                <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-store text-primary-500 text-xs"></i>
                    <span class="text-xs font-semibold text-gray-700">{{ sucursal.nombre }}</span>
                    <span v-if="sucursal.NumeroSucursal" class="text-[10px] text-gray-400">(N° {{ sucursal.NumeroSucursal }})</span>
                    <span class="ml-auto text-[10px] text-gray-400">
                        {{ configuraciones[sucursal.id]?.length || 0 }} días seleccionados
                    </span>
                </div>
                
                <!-- Días de la semana -->
                <div class="p-3 grid grid-cols-7 gap-1.5">
                    <div v-for="dia in diasSemana" :key="dia.valor"
                         @click="toggleDia(sucursal.id, dia.valor)"
                         class="flex flex-col items-center p-2 rounded-lg border-2 cursor-pointer transition-all"
                         :class="diaSeleccionado(sucursal.id, dia.valor) 
                             ? 'border-primary-500 bg-primary-50' 
                             : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                        
                        <!-- Checkbox personalizado -->
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center mb-0.5"
                             :class="diaSeleccionado(sucursal.id, dia.valor) 
                                 ? 'border-primary-500 bg-primary-500' 
                                 : 'border-gray-300'">
                            <i v-if="diaSeleccionado(sucursal.id, dia.valor)" 
                               class="fas fa-check text-white text-[8px]"></i>
                        </div>
                        
                        <!-- Letra del día -->
                        <span class="text-[10px] font-medium"
                              :class="diaSeleccionado(sucursal.id, dia.valor) 
                                  ? 'text-primary-700' 
                                  : 'text-gray-500'">
                            {{ dia.abrev }}
                        </span>
                    </div>
                </div>
                
                <!-- Leyenda -->
                <div class="px-3 pb-2 flex items-center gap-4 text-[9px] text-gray-400">
                    <span><span class="text-green-600 font-bold">✓</span> Disponible</span>
                    <span><span class="text-gray-300">□</span> No disponible</span>
                    <span class="ml-auto">Los días no seleccionados NO estarán disponibles para la venta</span>
                </div>
            </div>
            
            <!-- Mensaje si no hay sucursales -->
            <div v-if="sucursales.length === 0" class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                <i class="fas fa-store text-gray-300 text-2xl mb-2 block"></i>
                <p class="text-xs text-gray-500">No hay sucursales configuradas para este cliente.</p>
            </div>
        </div>
        
        <!-- Loading -->
        <div v-else class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-primary-500 text-xl"></i>
            <p class="text-xs text-gray-400 mt-2">Cargando configuración...</p>
        </div>
    </div>
</template>