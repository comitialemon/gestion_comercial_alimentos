<script setup>
import { ref, computed, onMounted, onUnmounted, inject, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    conceptos: {
        type: Array,
        default: () => []
    },
    cuentasContables: {
        type: Array,
        default: () => []
    }
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
const modalOpen = ref(false)
const editando = ref(false)
const editId = ref(null)

const formData = ref({
    Concepto: '',
    IdCuenta: '',
    activo: 1,
    requiere_identificador: false,
    usa_identificador_factura: false
})
const errors = ref({})
const guardando = ref(false)
const eliminando = ref(false)

// Estado para el buscador de cuentas
const busquedaCuenta = ref('')
const mostrarListaCuentas = ref(false)

// ==================== COMPUTED ====================
const cuentasFiltradas = computed(() => {
    if (!busquedaCuenta.value) return props.cuentasContables || []
    
    const termino = busquedaCuenta.value.toLowerCase()
    return (props.cuentasContables || []).filter(cuenta => {
        return cuenta?.nombre?.toLowerCase().includes(termino) ||
               cuenta?.descripcion?.toLowerCase().includes(termino) ||
               cuenta?.id?.toString().includes(termino)
    })
})

const nombreCuentaSeleccionada = computed(() => {
    if (!formData.value.IdCuenta) return ''
    const cuenta = props.cuentasContables?.find(c => c?.id === formData.value.IdCuenta)
    return cuenta ? `${cuenta.nombre} - ${cuenta.descripcion || ''}` : ''
})

// ==================== FUNCIONES ====================
const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = {
        Concepto: '',
        IdCuenta: '',
        activo: 1,
        requiere_identificador: false,
        usa_identificador_factura: false
    }
    busquedaCuenta.value = ''
    errors.value = {}
}

const abrirModalCrear = () => {
    resetForm()
    editando.value = false
    modalOpen.value = true
}

const abrirModalEditar = (item) => {
    if (!item) return
    
    editando.value = true
    editId.value = item.IdConceptoLiquidacion
    
    let activoNum = 0
    if (item.activo === true || item.activo === 1 || item.activo === '1') {
        activoNum = 1
    } else {
        activoNum = 0
    }
    
    const requiereBool = (item.requiere_identificador === 1 || item.requiere_identificador === true)
    const usaBool = (item.usa_identificador_factura === 1 || item.usa_identificador_factura === true)
    
    formData.value = {
        Concepto: item.Concepto || '',
        IdCuenta: item.IdCuenta || '',
        activo: activoNum,
        requiere_identificador: requiereBool,
        usa_identificador_factura: usaBool
    }
    
    const cuenta = props.cuentasContables?.find(c => c?.id === item.IdCuenta)
    if (cuenta) {
        busquedaCuenta.value = `${cuenta.nombre} - ${cuenta.descripcion || ''}`
    } else {
        busquedaCuenta.value = ''
    }
    
    modalOpen.value = true
}

const cerrarModal = () => {
    modalOpen.value = false
    resetForm()
}

const guardar = async () => {
    guardando.value = true
    errors.value = {}
    
    try {
        let response
        const dataToSend = {
            Concepto: formData.value.Concepto,
            IdCuenta: formData.value.IdCuenta,
            activo: formData.value.activo,
            requiere_identificador: formData.value.requiere_identificador ? 1 : 0,
            usa_identificador_factura: formData.value.usa_identificador_factura ? 1 : 0
        }
        
        if (editando.value) {
            response = await axios.post(`/gestion/impuestos/liquidacion-concepto/${editId.value}`, {
                ...dataToSend,
                _method: 'PUT'
            })
        } else {
            response = await axios.post('/gestion/impuestos/liquidacion-concepto', dataToSend)
        }
        
        if (response.status === 200 || response.status === 201) {
            toast?.success(editando.value ? 'Concepto actualizado' : 'Concepto agregado', 
                editando.value ? 'El concepto fue actualizado correctamente' : 'El concepto fue agregado correctamente')
            cerrarModal()
            setTimeout(() => {
                router.reload()
            }, 500)
        }
    } catch (error) {
        console.error('Error:', error)
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            toast?.error('Error de validación', 'Por favor completa los campos correctamente')
        } else {
            toast?.error('Error', error.response?.data?.message || 'Error al guardar')
        }
    } finally {
        guardando.value = false
    }
}

const eliminar = async (id, nombre) => {
    if (!id) {
        toast?.error('Error', 'No se pudo identificar el registro')
        return
    }
    
    if (!confirm(`¿Eliminar "${nombre}"?`)) return
    
    eliminando.value = true
    try {
        const response = await axios.delete(`/gestion/impuestos/liquidacion-concepto/${id}`)
                
        if (response.data.success) {
            toast?.success('Concepto eliminado', `"${nombre}" fue eliminado correctamente`)
            setTimeout(() => {
                router.reload()
            }, 500)
        } else {
            toast?.error('Error', response.data.message || 'Error al eliminar')
        }
    } catch (error) {
        console.error('Error:', error)
        if (error.response?.status === 405) {
            toast?.success('Éxito', 'Concepto eliminado correctamente')
            setTimeout(() => {
                router.reload()
            }, 500)
        } else {
            toast?.error('Error', error.response?.data?.message || 'Error al eliminar')
        }
    } finally {
        eliminando.value = false
    }
}

const seleccionarCuenta = (cuenta) => {
    if (!cuenta) return
    formData.value.IdCuenta = cuenta.id
    busquedaCuenta.value = `${cuenta.nombre} - ${cuenta.descripcion || ''}`
    mostrarListaCuentas.value = false
}

const handleClickOutside = (event) => {
    const container = document.querySelector('.cuentas-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarListaCuentas.value = false
    }
}

const handleEscKey = (event) => {
    if (event.key === 'Escape' && modalOpen.value) {
        cerrarModal()
    }
}

const getEstadoClase = (activo) => {
    return activo === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Activo' : 'Inactivo'
}

const mostrarRequiereIdentificador = (item) => {
    const valor = item.requiere_identificador
    if (valor === 1 || valor === true) {
        return { texto: 'Activo', clase: 'bg-emerald-100 text-emerald-700', punto: 'bg-emerald-500' }
    }
    return { texto: 'Inactivo', clase: 'bg-gray-100 text-gray-500', punto: 'bg-gray-300' }
}

const mostrarUsaIdentificadorFactura = (item) => {
    const valor = item.usa_identificador_factura
    if (valor === 1 || valor === true) {
        return { texto: 'Activo', clase: 'bg-purple-100 text-purple-700', punto: 'bg-purple-500' }
    }
    return { texto: 'Inactivo', clase: 'bg-gray-100 text-gray-500', punto: 'bg-gray-300' }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
    document.addEventListener('keydown', handleEscKey)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
    document.removeEventListener('keydown', handleEscKey)
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
                            <i class="fas fa-coins text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Conceptos de Liquidación</h1>
                            <p class="text-xs text-gray-500">Configurar métodos de pago</p>
                        </div>
                    </div>
                    <button 
                        @click="abrirModalCrear"
                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition"
                    >
                        <i class="fas fa-plus text-[10px]"></i> Nuevo Concepto
                    </button>
                </div>

                <!-- ==================== TARJETAS ==================== -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div 
                        v-for="item in conceptos" 
                        :key="item.IdConceptoLiquidacion" 
                        class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden border border-gray-200"
                    >
                        <!-- Header -->
                        <div class="px-3 py-2 border-b"
                            :class="item.activo === 1 ? 'bg-primary-50 border-primary-100' : 'bg-gray-50 border-gray-200'">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0"
                                        :class="item.activo === 1 ? 'bg-primary-200 text-primary-700' : 'bg-gray-200 text-gray-500'">
                                        <i class="fas fa-tag text-[10px]"></i>
                                    </div>
                                    <h3 class="font-semibold text-sm text-gray-800 truncate">{{ item.Concepto }}</h3>
                                </div>
                                <span class="px-1.5 py-0.5 text-[8px] rounded-full flex-shrink-0" :class="getEstadoClase(item.activo)">
                                    {{ getEstadoTexto(item.activo) }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Cuerpo -->
                        <div class="p-3 space-y-2">
                            <!-- Cuenta Contable -->
                            <div class="flex items-start gap-2">
                                <i class="fas fa-book text-gray-400 text-[10px] mt-0.5"></i>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[8px] text-gray-400 uppercase tracking-wide">Cuenta Contable</p>
                                    <p class="text-xs font-medium text-gray-800 truncate">
                                        <span class="font-mono text-[10px] font-bold">{{ item.cuenta_contable?.Cuenta || item.IdCuenta }}</span>
                                    </p>
                                    <p v-if="item.cuenta_contable?.Descripcion" class="text-[9px] text-gray-500 truncate">
                                        {{ item.cuenta_contable.Descripcion }}
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Requiere Identificador -->
                            <div class="flex items-center justify-between pt-1.5 border-t border-gray-100">
                                <div class="flex items-center gap-1.5">
                                    <i class="fas fa-users text-gray-400 text-[10px]"></i>
                                    <span class="text-[10px] text-gray-600">Seleccionar Cliente</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <div class="w-1.5 h-1.5 rounded-full" :class="mostrarRequiereIdentificador(item).punto"></div>
                                    <span :class="mostrarRequiereIdentificador(item).clase" class="text-[8px] px-1.5 py-0.5 rounded-full">
                                        {{ mostrarRequiereIdentificador(item).texto }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Usa ID Factura -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <i class="fas fa-qrcode text-gray-400 text-[10px]"></i>
                                    <span class="text-[10px] text-gray-600">Usar ID Factura</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <div class="w-1.5 h-1.5 rounded-full" :class="mostrarUsaIdentificadorFactura(item).punto"></div>
                                    <span :class="mostrarUsaIdentificadorFactura(item).clase" class="text-[8px] px-1.5 py-0.5 rounded-full">
                                        {{ mostrarUsaIdentificadorFactura(item).texto }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div class="px-3 py-1.5 bg-gray-50 flex justify-end gap-1.5 border-t border-gray-200">
                            <button @click="abrirModalEditar(item)" 
                                class="px-2 py-0.5 text-primary-600 hover:bg-primary-100 rounded-md transition text-[10px] flex items-center gap-1">
                                <i class="fas fa-edit text-[9px]"></i> Editar
                            </button>
                            <button @click="eliminar(item.IdConceptoLiquidacion, item.Concepto)" 
                                class="px-2 py-0.5 text-red-600 hover:bg-red-100 rounded-md transition text-[10px] flex items-center gap-1">
                                <i class="fas fa-trash-alt text-[9px]"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Mensaje vacío -->
                    <div v-if="!conceptos || conceptos.length === 0" class="col-span-full">
                        <div class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">
                            <i class="fas fa-info-circle text-2xl mb-2 block"></i>
                            <p class="text-sm">No hay conceptos configurados</p>
                            <p class="text-xs text-gray-400 mt-1">Haz clic en "Nuevo Concepto" para agregar uno</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL ==================== -->
        <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" @click="cerrarModal"></div>
            
            <div class="flex min-h-full items-center justify-center p-3">
                <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full mx-auto">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 py-2.5 bg-primary-600 rounded-t-xl">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-coins text-white text-xs"></i>
                            </div>
                            <h3 class="text-sm font-semibold text-white">
                                {{ editando ? 'Editar Concepto' : 'Nuevo Concepto' }}
                            </h3>
                        </div>
                        <button @click="cerrarModal" class="text-white/80 hover:text-white transition text-sm">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Cuerpo -->
                    <div class="px-4 py-4 space-y-3">
                        <!-- Concepto -->
                        <div>
                            <label class="block text-[10px] font-medium text-gray-700 mb-0.5">
                                Concepto <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                v-model="formData.Concepto" 
                                placeholder="Ej: Efectivo, Tarjeta, QR"
                                class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{ 'border-red-500': errors.Concepto }"
                            />
                            <p v-if="errors.Concepto" class="text-[8px] text-red-500 mt-0.5">{{ errors.Concepto }}</p>
                        </div>
                        
                        <!-- Cuenta Contable -->
                        <div class="relative cuentas-autocomplete">
                            <label class="block text-[10px] font-medium text-gray-700 mb-0.5">
                                Cuenta Contable <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text"
                                v-model="busquedaCuenta"
                                @focus="mostrarListaCuentas = true"
                                placeholder="Buscar cuenta..."
                                class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{ 'border-red-500': errors.IdCuenta }"
                            />
                            <div v-if="mostrarListaCuentas && cuentasFiltradas.length > 0" 
                                class="absolute z-50 w-full max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg mt-1">
                                <div 
                                    v-for="cuenta in cuentasFiltradas" 
                                    :key="cuenta.id"
                                    @click="seleccionarCuenta(cuenta)"
                                    class="px-3 py-1.5 hover:bg-primary-50 cursor-pointer border-b last:border-b-0 text-sm"
                                >
                                    <div class="font-mono">
                                        <span class="font-bold">{{ cuenta.id }}</span> - {{ cuenta.nombre }}
                                    </div>
                                    <div v-if="cuenta.descripcion" class="text-[10px] text-gray-500">{{ cuenta.descripcion }}</div>
                                </div>
                            </div>
                            <p v-if="errors.IdCuenta" class="text-[8px] text-red-500 mt-0.5">{{ errors.IdCuenta }}</p>
                        </div>
                        
                        <!-- Estado -->
                        <div>
                            <label class="block text-[10px] font-medium text-gray-700 mb-1">Estado</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" :value="1" v-model.number="formData.activo" class="w-3.5 h-3.5 text-primary-600"/>
                                    <span class="text-sm">Activo</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" :value="0" v-model.number="formData.activo" class="w-3.5 h-3.5 text-primary-600"/>
                                    <span class="text-sm">Inactivo</span>
                                </label>
                            </div>
                            <p class="text-[8px] text-gray-400 mt-0.5">
                                Valor actual: 
                                <span :class="formData.activo === 1 ? 'text-emerald-600 font-medium' : 'text-red-600 font-medium'">
                                    {{ formData.activo === 1 ? 'Activo' : 'Inactivo' }}
                                </span>
                            </p>
                        </div>
                        
                        <!-- Configuración adicional -->
                        <div class="border-t border-gray-200 pt-3">
                            <p class="text-[10px] font-medium text-gray-700 mb-2">Configuración adicional:</p>
                            
                            <!-- Requiere Identificador -->
                            <label class="flex items-center gap-2 cursor-pointer p-2.5 rounded-lg mb-1.5 transition"
                                :class="formData.requiere_identificador ? 'bg-yellow-50 border border-yellow-200' : 'bg-gray-50 border border-gray-200'">
                                <input 
                                    type="checkbox" 
                                    v-model="formData.requiere_identificador"
                                    @change="formData.usa_identificador_factura = false"
                                    class="w-3.5 h-3.5 text-yellow-600 rounded focus:ring-yellow-500"
                                />
                                <div class="flex-1">
                                    <span class="text-xs" :class="formData.requiere_identificador ? 'text-yellow-700 font-medium' : 'text-gray-700'">
                                        <i class="fas fa-users mr-1.5"></i>
                                        Requiere Seleccionar Cliente
                                    </span>
                                    <p class="text-[8px] text-gray-400">El vendedor debe elegir un cliente de la base de datos</p>
                                </div>
                                <span v-if="formData.requiere_identificador" class="px-1.5 py-0.5 text-[8px] rounded-full bg-yellow-100 text-yellow-700 flex-shrink-0">
                                    Activo
                                </span>
                            </label>
                            
                            <!-- Usa Identificador Factura -->
                            <label class="flex items-center gap-2 cursor-pointer p-2.5 rounded-lg transition"
                                :class="formData.usa_identificador_factura ? 'bg-purple-50 border border-purple-200' : 'bg-gray-50 border border-gray-200'">
                                <input 
                                    type="checkbox" 
                                    v-model="formData.usa_identificador_factura"
                                    @change="formData.requiere_identificador = false"
                                    class="w-3.5 h-3.5 text-purple-600 rounded focus:ring-purple-500"
                                />
                                <div class="flex-1">
                                    <span class="text-xs" :class="formData.usa_identificador_factura ? 'text-purple-700 font-medium' : 'text-gray-700'">
                                        <i class="fas fa-qrcode mr-1.5"></i>
                                        Usar Identificador de la Factura
                                    </span>
                                    <p class="text-[8px] text-gray-400">Usa el NIT/CI del cliente que está pagando la factura</p>
                                </div>
                                <span v-if="formData.usa_identificador_factura" class="px-1.5 py-0.5 text-[8px] rounded-full bg-purple-100 text-purple-700 flex-shrink-0">
                                    Activo
                                </span>
                            </label>
                        </div>
                        
                        <!-- Mensaje de error -->
                        <div v-if="formData.requiere_identificador && formData.usa_identificador_factura" class="p-2 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-[10px] text-red-600 text-center">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                No puedes seleccionar ambas opciones simultáneamente
                            </p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="px-4 py-2.5 bg-gray-50 rounded-b-xl flex justify-end gap-2 border-t border-gray-200">
                        <button @click="cerrarModal" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                            Cancelar
                        </button>
                        <button 
                            @click="guardar" 
                            :disabled="guardando || (formData.requiere_identificador && formData.usa_identificador_factura)"
                            class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            {{ guardando ? 'Guardando...' : (editando ? 'Actualizar' : 'Guardar') }}
                        </button>
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

/* Scrollbar personalizada */
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