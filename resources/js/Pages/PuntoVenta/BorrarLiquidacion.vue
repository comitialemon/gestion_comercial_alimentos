<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: {
        type: Array,
        default: () => []
    }
})

// ==================== ESTADO ====================
const sucursalId = ref('')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const liquidacionId = ref('')
const liquidacionBusqueda = ref('')
const mostrarLiquidaciones = ref(false)
const liquidacionesDisponibles = ref([])

const loading = ref(false)
const eliminando = ref(false)

// ==================== MODALES ====================
const modalConfirmacionVisible = ref(false)
const modalResultadoVisible = ref(false)
const modalErrorVisible = ref(false)

const modalResultado = ref({
    id_liquidacion: null,
    message: '',
    resultados: {
        conta_diario_propiamente: 0,
        impuestos_ventas_liquidacion_vendedor: 0,
        impuestos_ventas: 0,
        ventas_encontradas: 0
    }
})

const modalError = ref({
    message: ''
})

// ==================== TOAST ====================
const toastVisible = ref(false)
const toastMensaje = ref('')
const toastTipo = ref('success')

const mostrarToast = (mensaje, tipo = 'success') => {
    toastMensaje.value = mensaje
    toastTipo.value = tipo
    toastVisible.value = true
    setTimeout(() => {
        toastVisible.value = false
    }, 4000)
}

// ==================== COMPUTADOS ====================
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id === sucursalId.value)
    return suc?.nombre || ''
})

const liquidacionSeleccionadaTexto = computed(() => {
    if (!liquidacionId.value) return ''
    const liq = liquidacionesDisponibles.value?.find(l => l.id == liquidacionId.value)
    return liq?.display_text || ''
})

// ==================== ACCIONES ====================
const seleccionarSucursal = async (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    
    liquidacionId.value = ''
    liquidacionBusqueda.value = ''
    liquidacionesDisponibles.value = []
    
    loading.value = true
    try {
        const response = await axios.get(`/pdv/borrar-liquidacion/liquidaciones?sucursal_id=${sucursalId.value}`)
        if (response.data.success) {
            liquidacionesDisponibles.value = response.data.liquidaciones
        }
    } catch (error) {
        console.error('Error cargando liquidaciones:', error)
        liquidacionesDisponibles.value = []
    } finally {
        loading.value = false
    }
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    liquidacionId.value = ''
    liquidacionBusqueda.value = ''
    liquidacionesDisponibles.value = []
}

const seleccionarLiquidacion = (liquidacion) => {
    liquidacionId.value = liquidacion.id
    liquidacionBusqueda.value = liquidacion.display_text
    mostrarLiquidaciones.value = false
}

const limpiarLiquidacion = () => {
    liquidacionId.value = ''
    liquidacionBusqueda.value = ''
    mostrarLiquidaciones.value = false
}

// ==================== ABRIR MODALES ====================
const abrirConfirmacion = () => {
    if (!sucursalId.value) {
        mostrarToast('Seleccione una sucursal', 'error')
        return
    }
    if (!liquidacionId.value) {
        mostrarToast('Seleccione una liquidación', 'error')
        return
    }
    modalConfirmacionVisible.value = true
}

const cerrarConfirmacion = () => {
    modalConfirmacionVisible.value = false
}

// ==================== ELIMINAR LIQUIDACIÓN ====================
const eliminarLiquidacion = async () => {
    modalConfirmacionVisible.value = false
    eliminando.value = true
    
    try {
        const response = await axios.post('/pdv/borrar-liquidacion/eliminar', {
            id_liquidacion: liquidacionId.value
        })
        
        if (response.data.success) {
            modalResultado.value = {
                id_liquidacion: response.data.id_liquidacion,
                message: response.data.message || 'Proceso completado exitosamente',
                resultados: response.data.resultados || {
                    conta_diario_propiamente: 0,
                    impuestos_ventas_liquidacion_vendedor: 0,
                    impuestos_ventas: 0,
                    ventas_encontradas: 0
                }
            }
            modalResultadoVisible.value = true
            
            // Recargar lista de liquidaciones
            const refreshResponse = await axios.get(`/pdv/borrar-liquidacion/liquidaciones?sucursal_id=${sucursalId.value}`)
            if (refreshResponse.data.success) {
                liquidacionesDisponibles.value = refreshResponse.data.liquidaciones
            }
            
            liquidacionId.value = ''
            liquidacionBusqueda.value = ''
            
        } else {
            modalError.value = {
                message: response.data.message || 'Error al eliminar la liquidación'
            }
            modalErrorVisible.value = true
        }
        
    } catch (error) {
        console.error('Error:', error)
        modalError.value = {
            message: error.response?.data?.message || error.message || 'Ocurrió un error inesperado'
        }
        modalErrorVisible.value = true
    } finally {
        eliminando.value = false
    }
}

const cerrarResultado = () => {
    modalResultadoVisible.value = false
}

const cerrarError = () => {
    modalErrorVisible.value = false
}

// ==================== NAVEGACIÓN ====================
const volver = () => {
    router.get('/oficial')
}

// ==================== CERRAR SUGERENCIAS ====================
const handleClickOutside = (event) => {
    const sucursalContainer = document.querySelector('.sucursal-autocomplete')
    if (sucursalContainer && !sucursalContainer.contains(event.target)) {
        mostrarSucursales.value = false
    }
    
    const liquidacionContainer = document.querySelector('.liquidacion-autocomplete')
    if (liquidacionContainer && !liquidacionContainer.contains(event.target)) {
        mostrarLiquidaciones.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-4 sm:py-6 px-3 sm:px-4 lg:px-8">
            <div class="max-w-2xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center"
                             :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                            <i class="fas fa-trash-alt text-base sm:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-gray-800">Mantenimiento - Borrar Liquidación</h1>
                            <p class="text-xs text-gray-500 hidden sm:block">Seleccione sucursal y liquidación para eliminar</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Seleccione sucursal y liquidación para eliminar</p>
                </div>

                <!-- Contenedor único -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                    <div class="space-y-4">
                        <!-- Selector de Sucursal -->
                        <div class="sucursal-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-store mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Sucursal <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="sucursalBusqueda"
                                    @focus="mostrarSucursales = true"
                                    @input="mostrarSucursales = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Escriba para buscar sucursal..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="sucursalBusqueda"
                                    @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="suc in sucursalesDisponibles" 
                                        :key="suc.id"
                                        @click="seleccionarSucursal(suc)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="sucursalId === suc.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="sucursalId === suc.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <div>
                                            <span class="font-medium text-sm">{{ suc.nombre }}</span>
                                            <span v-if="suc.numero" class="text-xs text-gray-400 ml-2">(N° {{ suc.numero }})</span>
                                        </div>
                                        <i v-if="sucursalId === suc.id" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Indicador sucursal seleccionada -->
                        <div v-if="sucursalId" class="text-xs flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-500">Sucursal:</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle mr-1 text-xs"></i> {{ sucursalNombre }}
                            </span>
                        </div>

                        <!-- Selector de Liquidación (solo si hay sucursal seleccionada) -->
                        <div v-if="sucursalId" class="liquidacion-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-receipt mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Liquidación <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="liquidacionBusqueda"
                                    @focus="mostrarLiquidaciones = true"
                                    @input="mostrarLiquidaciones = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Seleccione la liquidación..."
                                    autocomplete="off"
                                    :disabled="loading"
                                />
                                <div v-if="loading" class="absolute right-2 top-1/2 -translate-y-1/2">
                                    <i class="fas fa-spinner fa-spin text-gray-400"></i>
                                </div>
                                <button 
                                    v-if="liquidacionBusqueda && !loading"
                                    @click="limpiarLiquidacion"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarLiquidaciones && liquidacionesDisponibles.length > 0 && !loading" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="liq in liquidacionesDisponibles" 
                                        :key="liq.id"
                                        @click="seleccionarLiquidacion(liq)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="liquidacionId == liq.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="liquidacionId == liq.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <div>
                                            <span class="text-sm">{{ liq.display_text }}</span>
                                        </div>
                                        <i v-if="liquidacionId == liq.id" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                                
                                <div v-if="mostrarLiquidaciones && liquidacionesDisponibles.length === 0 && !loading && sucursalId" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    No hay liquidaciones registradas en esta sucursal
                                </div>
                            </div>
                        </div>

                        <!-- Indicador liquidación seleccionada -->
                        <div v-if="liquidacionId" class="text-xs flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-500">Liquidación:</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-700)` }">
                                <i class="fas fa-check-circle mr-1 text-xs"></i> ID: {{ liquidacionId }}
                            </span>
                        </div>

                        <!-- Alerta de advertencia -->
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mt-2">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-exclamation-triangle text-red-500 text-sm mt-0.5"></i>
                                <div class="text-xs text-red-700">
                                    <strong>⚠️ Advertencia:</strong> Esta acción eliminará permanentemente:
                                    <ul class="list-disc list-inside mt-1 ml-2">
                                        <li>Registros en conta_diario_propiamente</li>
                                        <li>Registros en impuestos_ventas_liquidacion_vendedor</li>
                                        <li>Actualizará impuestos_ventas (LiquidadoVendedor = 0)</li>
                                    </ul>
                                    <strong class="block mt-1">Esta operación NO se puede deshacer.</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t mt-4">
                            <button 
                                type="button"
                                @click="volver"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-sm"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="button"
                                @click="abrirConfirmacion"
                                :disabled="eliminando || !sucursalId || !liquidacionId"
                                class="px-5 py-2 bg-red-600 text-white rounded-lg transition text-sm flex items-center justify-center gap-2 disabled:opacity-50 hover:bg-red-700"
                            >
                                <i v-if="eliminando" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-trash-alt"></i>
                                {{ eliminando ? 'Eliminando...' : 'Eliminar Liquidación' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 rounded-lg text-xs"
                     :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Información:</strong> Seleccione la sucursal y luego la liquidación que desea eliminar. 
                    El sistema ejecutará las siguientes operaciones en transacción:
                    <ol class="list-decimal list-inside mt-1 ml-2">
                        <li>Eliminar registros de conta_diario_propiamente</li>
                        <li>Eliminar registros de impuestos_ventas_liquidacion_vendedor</li>
                        <li>Actualizar impuestos_ventas (LiquidadoVendedor = 0)</li>
                    </ol>
                    Si algo falla, todos los cambios serán revertidos automáticamente.
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL DE CONFIRMACIÓN ==================== -->
    <div v-if="modalConfirmacionVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarConfirmacion">
        <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-xl">
            <div class="p-4 border-b bg-red-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-red-100">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-800 text-sm sm:text-base">Confirmar Eliminación</h3>
                        <p class="text-[10px] sm:text-xs text-gray-500">Liquidación ID: {{ liquidacionId }}</p>
                    </div>
                </div>
            </div>
            <div class="p-4 sm:p-5">
                <p class="text-xs sm:text-sm text-gray-700 text-center">
                    ¿Estás seguro de <span class="font-bold text-red-600">ELIMINAR</span> esta liquidación?
                </p>
                <div class="mt-2 text-xs text-gray-500 bg-gray-50 p-3 rounded-lg">
                    <p><strong>ID Liquidación:</strong> {{ liquidacionId }}</p>
                    <p><strong>Detalle:</strong> {{ liquidacionSeleccionadaTexto }}</p>
                    <p class="mt-1 text-red-600"><i class="fas fa-info-circle mr-1"></i> Esta acción NO se puede deshacer</p>
                </div>
            </div>
            <div class="p-3 sm:p-4 bg-gray-50 flex justify-end gap-2 sm:gap-3">
                <button @click="cerrarConfirmacion" class="px-3 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition">
                    Cancelar
                </button>
                <button @click="eliminarLiquidacion" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition flex items-center gap-2 bg-red-600 hover:bg-red-700">
                    <i class="fas fa-trash-alt"></i>
                    Eliminar
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL DE RESULTADO ÉXITO ==================== -->
    <div v-if="modalResultadoVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarResultado">
        <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-xl">
            <div class="p-4 border-b bg-green-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-green-100">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-800 text-sm sm:text-base">✅ Proceso Completado</h3>
                        <p class="text-[10px] sm:text-xs text-gray-500">ID Liquidación: {{ modalResultado.id_liquidacion }}</p>
                    </div>
                </div>
            </div>
            <div class="p-4 sm:p-5">
                <p class="text-xs sm:text-sm text-green-700 text-center">{{ modalResultado.message }}</p>
                <div class="mt-3 text-xs bg-gray-50 p-3 rounded-lg">
                    <p><strong>Registros eliminados:</strong></p>
                    <ul class="list-disc list-inside mt-1 ml-2">
                        <li>conta_diario_propiamente: <span class="font-bold">{{ modalResultado.resultados.conta_diario_propiamente || 0 }}</span></li>
                        <li>impuestos_ventas_liquidacion_vendedor: <span class="font-bold">{{ modalResultado.resultados.impuestos_ventas_liquidacion_vendedor || 0 }}</span></li>
                        <li>impuestos_ventas actualizados: <span class="font-bold">{{ modalResultado.resultados.impuestos_ventas || 0 }}</span></li>
                        <li>Ventas encontradas: <span class="font-bold">{{ modalResultado.resultados.ventas_encontradas || 0 }}</span></li>
                    </ul>
                </div>
            </div>
            <div class="p-3 sm:p-4 bg-gray-50 flex justify-end">
                <button @click="cerrarResultado" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition bg-green-600 hover:bg-green-700">
                    Aceptar
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL DE ERROR ==================== -->
    <div v-if="modalErrorVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarError">
        <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-xl">
            <div class="p-4 border-b bg-red-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-red-100">
                        <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-800 text-sm sm:text-base">❌ Error</h3>
                        <p class="text-[10px] sm:text-xs text-gray-500">ID Liquidación: {{ liquidacionId }}</p>
                    </div>
                </div>
            </div>
            <div class="p-4 sm:p-5">
                <p class="text-xs sm:text-sm text-red-700 text-center">{{ modalError.message }}</p>
                <p class="mt-2 text-xs text-gray-500 text-center">Todos los cambios fueron revertidos.</p>
            </div>
            <div class="p-3 sm:p-4 bg-gray-50 flex justify-end">
                <button @click="cerrarError" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition bg-red-600 hover:bg-red-700">
                    Aceptar
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== TOAST ==================== -->
    <div v-if="toastVisible" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white flex items-center gap-2"
         :class="toastTipo === 'success' ? 'bg-green-500' : (toastTipo === 'error' ? 'bg-red-500' : 'bg-blue-500')">
        <i :class="toastTipo === 'success' ? 'fas fa-check-circle' : (toastTipo === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle')"></i>
        {{ toastMensaje }}
    </div>
</template>

<style scoped>
input:focus {
    --tw-ring-color: var(--color-primary-500);
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

.transition {
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>