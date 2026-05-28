<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: Object,
    sucursales: Array,
    sucursalId: Number,
    gestiones: Array,
})

// ==================== ESTADO ====================
const sucursalId = ref(props.sucursalId || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)
const gestion = ref(props.gestiones?.[0] || new Date().getFullYear().toString())
const procesando = ref(false)

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

// ==================== ACCIONES ====================
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
}

const exportarReporte = () => {
    if (!sucursalId.value) {
        alert('Seleccione una sucursal')
        return
    }
    if (!gestion.value) {
        alert('Seleccione una gestión')
        return
    }
    
    procesando.value = true
    
    // 🔥 CAMBIAR DE POST A GET
    window.open(`/gestion/reportes/resultados-comparativo/exportar?sucursal_id=${sucursalId.value}&gestion=${gestion.value}`, '_blank')
    
    procesando.value = false
}

const volver = () => {
    router.get('/oficial')
}

// Cerrar sugerencias al hacer clic fuera
const handleClickOutside = (event) => {
    const container = document.querySelector('.sucursal-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarSucursales.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    if (sucursalId.value) {
        const sucursal = props.sucursales?.find(s => s.id === sucursalId.value)
        if (sucursal) {
            sucursalBusqueda.value = sucursal.nombre
        }
    }
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
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center"
                                 :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                <i class="fas fa-chart-line text-base sm:text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-lg sm:text-xl font-bold text-gray-800">Estado de Resultados Comparativo</h1>
                                <p class="text-xs text-gray-500 hidden sm:block">Reporte comparativo por meses</p>
                            </div>
                        </div>
                        <button 
                            @click="volver"
                            class="px-3 py-1.5 text-xs rounded-lg transition sm:w-auto w-full flex items-center justify-center gap-1"
                            :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }"
                        >
                            <i class="fas fa-arrow-left text-xs"></i>
                            <span>Volver</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Reporte comparativo por meses</p>
                </div>

                <!-- Banner de empresa -->
                <div class="rounded-xl p-3 sm:p-4 mb-4 sm:mb-6"
                     :style="{ backgroundColor: `var(--color-primary-50)`, borderLeftColor: `var(--color-primary-600)` }"
                     style="border-left-width: 4px;">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                 :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                                <i class="fas fa-building text-xs sm:text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs font-medium" :style="{ color: `var(--color-primary-600)` }">Empresa</p>
                                <p class="text-sm sm:text-base font-bold" :style="{ color: `var(--color-primary-800)` }">
                                    {{ empresa?.Nombre || 'No seleccionada' }}
                                </p>
                            </div>
                        </div>
                        <div class="text-xs text-gray-400">
                            NIT: {{ empresa?.NIT || '-' }}
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                    <div class="space-y-5">
                        <!-- Sucursal con autocompletado -->
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
                                
                                <div v-if="mostrarSucursales && sucursalBusqueda && sucursalesDisponibles.length === 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    No se encontraron sucursales con "{{ sucursalBusqueda }}"
                                </div>
                            </div>
                            <div v-if="sucursalId" class="mt-2 text-xs" :style="{ color: `var(--color-primary-600)` }">
                                <i class="fas fa-check-circle mr-1"></i> Sucursal seleccionada: {{ sucursalNombre }}
                            </div>
                        </div>

                        <!-- Gestión -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-calendar-alt mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Gestión <span class="text-red-500">*</span>
                            </label>
                            <select v-model="gestion" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }">
                                <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                            </select>
                        </div>

                        <!-- Información del reporte -->
                        <div class="p-3 rounded-lg text-xs"
                             :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Reporte:</strong> Estado de Resultados Comparativo<br>
                            <strong>Formato:</strong> Excel con dos hojas (Resultados y Gastos No Deducibles)<br>
                            <strong>Periodos:</strong> 
                            <span v-if="empresa?.IdTipoCliente === 4">Abril a Marzo (gestión industrial)</span>
                            <span v-else>Enero a Diciembre (gestión general)</span>
                        </div>

                        <!-- Botones -->
                        <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t">
                            <button 
                                type="button"
                                @click="volver"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-sm"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="button"
                                @click="exportarReporte"
                                :disabled="procesando || !sucursalId"
                                class="px-5 py-2 text-white rounded-lg transition text-sm flex items-center justify-center gap-2 disabled:opacity-50"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="procesando" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-file-excel"></i>
                                {{ procesando ? 'Generando...' : 'Exportar a Excel' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:focus, select:focus {
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