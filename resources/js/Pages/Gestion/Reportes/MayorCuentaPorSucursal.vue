<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresa: Object,
    sucursales: Array,
    sucursalSeleccionada: Number,
    cuentas: Array,
    identificadores: Array,
})

// ==================== ESTADO ====================
const sucursalId = ref(props.sucursalSeleccionada || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const form = ref({
    cuenta_id: '',
    cuenta_busqueda: '',
    fecha_inicial: new Date().toISOString().slice(0, 10),
    identificador_id: '',
    identificador_busqueda: '',
    moneda: 1,
})

const errors = ref({})
const procesando = ref(false)
const mostrarSugerenciasCuenta = ref(false)
const mostrarSugerenciasIdentificador = ref(false)

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

const cuentasFiltradas = computed(() => {
    if (!props.cuentas) return []
    if (!form.value.cuenta_busqueda) return props.cuentas
    
    const termino = form.value.cuenta_busqueda.toLowerCase()
    return props.cuentas.filter(c => 
        c.Cuenta?.toLowerCase().includes(termino) ||
        c.Descripcion?.toLowerCase().includes(termino) ||
        `${c.Cuenta} ${c.Descripcion}`.toLowerCase().includes(termino)
    )
})

const identificadoresFiltrados = computed(() => {
    if (!props.identificadores) return []
    if (!form.value.identificador_busqueda) return props.identificadores
    
    const termino = form.value.identificador_busqueda.toLowerCase()
    return props.identificadores.filter(i => 
        i.CI_NIT?.toString().includes(termino) ||
        i.Nombre?.toLowerCase().includes(termino) ||
        `${i.CI_NIT} ${i.Nombre}`.toLowerCase().includes(termino)
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
    form.value.cuenta_id = ''
    form.value.cuenta_busqueda = ''
    form.value.identificador_id = ''
    form.value.identificador_busqueda = ''
    errors.value = {}
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
}

const seleccionarCuenta = (cuenta) => {
    form.value.cuenta_id = cuenta.id
    form.value.cuenta_busqueda = `${cuenta.Cuenta} - ${cuenta.Descripcion}`
    mostrarSugerenciasCuenta.value = false
    errors.value.cuenta_id = null
}

const limpiarCuenta = () => {
    form.value.cuenta_id = ''
    form.value.cuenta_busqueda = ''
    mostrarSugerenciasCuenta.value = false
}

const seleccionarIdentificador = (identificador) => {
    form.value.identificador_id = identificador.id
    form.value.identificador_busqueda = `${identificador.CI_NIT} - ${identificador.Nombre}`
    mostrarSugerenciasIdentificador.value = false
}

const limpiarIdentificador = () => {
    form.value.identificador_id = ''
    form.value.identificador_busqueda = ''
    mostrarSugerenciasIdentificador.value = false
}

// ==================== EXPORTAR ====================
const exportarReporte = () => {
    if (!sucursalId.value) {
        alert('Seleccione una sucursal')
        return
    }
    if (!form.value.cuenta_id) {
        errors.value = { cuenta_id: 'Seleccione una cuenta' }
        return
    }
    if (!form.value.fecha_inicial) {
        errors.value = { fecha_inicial: 'Seleccione una fecha' }
        return
    }
    
    errors.value = {}
    procesando.value = true
    
    const formData = new FormData()
    formData.append('sucursal_id', sucursalId.value)
    formData.append('cuenta_id', form.value.cuenta_id)
    formData.append('fecha_inicial', form.value.fecha_inicial)
    formData.append('identificador_id', form.value.identificador_id || '')
    formData.append('moneda', form.value.moneda)
    
    // 🔥 NOMBRE DEL ARCHIVO CON SUCURSAL
    const nombreSucursal = sucursalNombre.value || 'Sucursal'
    const nombreArchivo = `Mayor${nombreSucursal}.xls`
    
    axios.post('/gestion/reportes/mayor-cuenta/exportar-por-sucursal', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        },
        responseType: 'blob'
    })
    .then(response => {
        const blob = response.data
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = nombreArchivo  // ← NOMBRE PERSONALIZADO
        document.body.appendChild(a)
        a.click()
        window.URL.revokeObjectURL(url)
        a.remove()
        procesando.value = false
    })
    .catch(error => {
        console.error('Error:', error)
        if (error.response?.status === 419) {
            alert('Error de sesión. Por favor recargue la página.')
        } else {
            alert('Error al exportar: ' + (error.response?.data?.message || error.message))
        }
        procesando.value = false
    })
}

// ==================== CERRAR SUGERENCIAS ====================
const handleClickOutside = (event) => {
    const sucursalContainer = document.querySelector('.sucursal-autocomplete')
    if (sucursalContainer && !sucursalContainer.contains(event.target)) {
        mostrarSucursales.value = false
    }
    
    const cuentaContainer = document.querySelector('.cuenta-autocomplete')
    if (cuentaContainer && !cuentaContainer.contains(event.target)) {
        mostrarSugerenciasCuenta.value = false
    }
    
    const identificadorContainer = document.querySelector('.identificador-autocomplete')
    if (identificadorContainer && !identificadorContainer.contains(event.target)) {
        mostrarSugerenciasIdentificador.value = false
    }
}

const volver = () => {
    router.get('/oficial')
}

// Lifecycle
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    if (sucursalId.value) {
        const suc = props.sucursales?.find(s => s.id === sucursalId.value)
        if (suc) {
            sucursalBusqueda.value = suc.nombre
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
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center"
                             :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                            <i class="fas fa-chart-line text-base sm:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-gray-800">Mayor de Cuenta por Sucursal</h1>
                            <p class="text-xs text-gray-500 hidden sm:block">Seleccione sucursal y reporte de movimientos por cuenta contable</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Seleccione sucursal y reporte de movimientos por cuenta contable</p>
                </div>

                <!-- SELECTOR DE SUCURSAL -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
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
                    </div>

                    <div v-if="sucursalId" class="mt-3 text-xs flex items-center gap-2 flex-wrap">
                        <span class="font-medium text-gray-500">Sucursal seleccionada:</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                              :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-700)` }">
                            <i class="fas fa-check-circle mr-1 text-xs"></i> {{ sucursalNombre }}
                        </span>
                    </div>
                </div>

                <!-- Formulario de parámetros -->
                <div v-if="sucursalId" class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                    <div class="space-y-4">
                        <!-- Cuenta -->
                        <div class="cuenta-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Cuenta <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="form.cuenta_busqueda"
                                    @focus="mostrarSugerenciasCuenta = true"
                                    @input="mostrarSugerenciasCuenta = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    :class="{ 'border-red-500': errors.cuenta_id }"
                                    placeholder="Escriba para buscar cuenta..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="form.cuenta_busqueda"
                                    @click="limpiarCuenta"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarSugerenciasCuenta && cuentasFiltradas.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="cuenta in cuentasFiltradas" 
                                        :key="cuenta.id"
                                        @click="seleccionarCuenta(cuenta)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="form.cuenta_id === cuenta.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="form.cuenta_id === cuenta.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <div>
                                            <span class="font-mono text-sm font-medium">{{ cuenta.Cuenta }}</span>
                                            <span class="text-xs text-gray-500 ml-2">{{ cuenta.Descripcion }}</span>
                                        </div>
                                        <i v-if="form.cuenta_id === cuenta.id" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                                
                                <div v-if="mostrarSugerenciasCuenta && form.cuenta_busqueda && cuentasFiltradas.length === 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    No se encontraron cuentas con "{{ form.cuenta_busqueda }}"
                                </div>
                            </div>
                            <p v-if="errors.cuenta_id" class="text-xs text-red-500 mt-1">{{ errors.cuenta_id }}</p>
                        </div>

                        <!-- Fecha Inicial -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Fecha Inicial <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="date" 
                                v-model="form.fecha_inicial"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)` }"
                                :class="{ 'border-red-500': errors.fecha_inicial }"
                            />
                            <p v-if="errors.fecha_inicial" class="text-xs text-red-500 mt-1">{{ errors.fecha_inicial }}</p>
                        </div>

                        <!-- Identificador -->
                        <div class="identificador-autocomplete">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Identificador <span class="text-gray-400 text-xs">(opcional)</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="form.identificador_busqueda"
                                    @focus="mostrarSugerenciasIdentificador = true"
                                    @input="mostrarSugerenciasIdentificador = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Escriba para buscar por CI/NIT o nombre..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="form.identificador_busqueda"
                                    @click="limpiarIdentificador"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="mostrarSugerenciasIdentificador && identificadoresFiltrados.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="ident in identificadoresFiltrados" 
                                        :key="ident.id"
                                        @click="seleccionarIdentificador(ident)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="form.identificador_id === ident.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="form.identificador_id === ident.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <div>
                                            <span class="font-mono text-sm font-medium">{{ ident.CI_NIT }}</span>
                                            <span class="text-xs text-gray-500 ml-2">{{ ident.Nombre }}</span>
                                        </div>
                                        <i v-if="form.identificador_id === ident.id" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                                
                                <div v-if="mostrarSugerenciasIdentificador && form.identificador_busqueda && identificadoresFiltrados.length === 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    No se encontraron identificadores con "{{ form.identificador_busqueda }}"
                                </div>
                            </div>
                        </div>

                        <!-- Moneda -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Moneda del Informe <span class="text-red-500">*</span>
                            </label>
                            <div class="flex flex-col sm:flex-row gap-3 sm:gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.moneda" :value="1" class="w-4 h-4" />
                                    <span class="text-sm">Bolivianos</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.moneda" :value="2" class="w-4 h-4" />
                                    <span class="text-sm">Otra Moneda</span>
                                </label>
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
                                @click="exportarReporte"
                                :disabled="procesando"
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

                <!-- Mensaje cuando no hay sucursal -->
                <div v-else class="bg-white rounded-xl shadow-sm p-8 text-center">
                    <i class="fas fa-arrow-left text-gray-300 text-4xl mb-3 block"></i>
                    <p class="text-gray-500">Seleccione una sucursal para continuar</p>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 rounded-lg text-xs"
                     :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Empresa:</strong> {{ empresa?.Nombre || 'No seleccionada' }}<br>
                    Este reporte genera un archivo Excel con el mayor de la cuenta seleccionada para la sucursal elegida.
                    <span v-if="form.identificador_id">Se filtrará por el identificador seleccionado.</span>
                    <span v-else>Mostrará todos los movimientos de la cuenta.</span>
                </div>
            </div>
        </div>
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