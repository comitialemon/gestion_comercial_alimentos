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

// 🔥 DIAGNÓSTICO: Ver qué datos llegan
console.log('=== DATOS RECIBIDOS EN INDEX ===')
console.log('Conceptos:', props.conceptos)
props.conceptos?.forEach(item => {
    console.log(`📦 ${item.Concepto}:`, {
        requiere: item.requiere_identificador,
        usa: item.usa_identificador_factura,
        activo: item.activo,
        type_activo: typeof item.activo
    })
})

// Estado del modal
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

// Filtrar cuentas según búsqueda
const cuentasFiltradas = computed(() => {
    if (!busquedaCuenta.value) return props.cuentasContables || []
    
    const termino = busquedaCuenta.value.toLowerCase()
    return (props.cuentasContables || []).filter(cuenta => {
        return cuenta?.nombre?.toLowerCase().includes(termino) ||
               cuenta?.descripcion?.toLowerCase().includes(termino) ||
               cuenta?.id?.toString().includes(termino)
    })
})

// Obtener el nombre de la cuenta seleccionada
const nombreCuentaSeleccionada = computed(() => {
    if (!formData.value.IdCuenta) return ''
    const cuenta = props.cuentasContables?.find(c => c?.id === formData.value.IdCuenta)
    return cuenta ? `${cuenta.nombre} - ${cuenta.descripcion || ''}` : ''
})

// Reset formulario
const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = {
        Concepto: '',
        IdCuenta: '',
        activo: 1,  // 🔥 Número, no string
        requiere_identificador: false,
        usa_identificador_factura: false
    }
    busquedaCuenta.value = ''
    errors.value = {}
}

// Abrir modal para crear
const abrirModalCrear = () => {
    resetForm()
    editando.value = false
    modalOpen.value = true
}

// 🔥 CORREGIDO: Abrir modal para editar
const abrirModalEditar = (item) => {
    if (!item) return
    
    console.log('✏️ Editando concepto:', item)
    
    editando.value = true
    editId.value = item.IdConceptoLiquidacion
    
    // 🔥 CONVERTIR activo a número (0 o 1)
    let activoNum = 0
    if (item.activo === true || item.activo === 1 || item.activo === '1') {
        activoNum = 1
    } else {
        activoNum = 0
    }
    
    // Convertir booleanos
    const requiereBool = (item.requiere_identificador === 1 || item.requiere_identificador === true)
    const usaBool = (item.usa_identificador_factura === 1 || item.usa_identificador_factura === true)
    
    console.log('✅ Estado convertido:', {
        original: item.activo,
        tipo_original: typeof item.activo,
        convertido: activoNum
    })
    
    formData.value = {
        Concepto: item.Concepto || '',
        IdCuenta: item.IdCuenta || '',
        activo: activoNum,
        requiere_identificador: requiereBool,
        usa_identificador_factura: usaBool
    }
    
    // Mostrar la cuenta en el buscador
    const cuenta = props.cuentasContables?.find(c => c?.id === item.IdCuenta)
    if (cuenta) {
        busquedaCuenta.value = `${cuenta.nombre} - ${cuenta.descripcion || ''}`
    } else {
        busquedaCuenta.value = ''
    }
    
    modalOpen.value = true
}

// Cerrar modal
const cerrarModal = () => {
    modalOpen.value = false
    resetForm()
}

// Guardar
const guardar = async () => {
    guardando.value = true
    errors.value = {}
    
    try {
        let response
        const dataToSend = {
            Concepto: formData.value.Concepto,
            IdCuenta: formData.value.IdCuenta,
            activo: formData.value.activo,  // 🔥 Ya es número: 0 o 1
            requiere_identificador: formData.value.requiere_identificador ? 1 : 0,
            usa_identificador_factura: formData.value.usa_identificador_factura ? 1 : 0
        }
        
        console.log('📤 Enviando datos:', dataToSend)
        
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
            }, 1000)
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

// Eliminar
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

// Seleccionar cuenta
const seleccionarCuenta = (cuenta) => {
    if (!cuenta) return
    formData.value.IdCuenta = cuenta.id
    busquedaCuenta.value = `${cuenta.nombre} - ${cuenta.descripcion || ''}`
    mostrarListaCuentas.value = false
}

// Cerrar lista al hacer clic fuera
const handleClickOutside = (event) => {
    const container = document.querySelector('.cuentas-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarListaCuentas.value = false
    }
}

// Cerrar modal con ESC
const handleEscKey = (event) => {
    if (event.key === 'Escape' && modalOpen.value) {
        cerrarModal()
    }
}

// 🔥 CORREGIDO: Obtener clase de estado
const getEstadoClase = (activo) => {
    return activo === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Activo' : 'Inactivo'
}

// 🔥 Funciones para mostrar valores en tarjetas
const mostrarRequiereIdentificador = (item) => {
    const valor = item.requiere_identificador
    if (valor === 1 || valor === true) {
        return { texto: 'Activo', clase: 'bg-green-100 text-green-800', punto: 'bg-green-500' }
    }
    return { texto: 'Inactivo', clase: 'bg-gray-100 text-gray-500', punto: 'bg-gray-300' }
}

const mostrarUsaIdentificadorFactura = (item) => {
    const valor = item.usa_identificador_factura
    if (valor === 1 || valor === true) {
        return { texto: 'Activo', clase: 'bg-purple-100 text-purple-800', punto: 'bg-purple-500' }
    }
    return { texto: 'Inactivo', clase: 'bg-gray-100 text-gray-500', punto: 'bg-gray-300' }
}

// Inicializar
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    document.addEventListener('keydown', handleEscKey)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    document.removeEventListener('keydown', handleEscKey)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-3 px-2 sm:py-4 sm:px-3 lg:py-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-4 sm:mb-6">
                    <div class="inline-flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 lg:w-14 lg:h-14 bg-primary-100 rounded-xl sm:rounded-2xl mb-2 sm:mb-3">
                        <i class="fas fa-coins text-base sm:text-lg lg:text-xl text-primary-600"></i>
                    </div>
                    <h1 class="text-base sm:text-lg lg:text-xl font-bold text-gray-900">Conceptos de Liquidación</h1>
                    <p class="text-[10px] sm:text-xs text-gray-500">Configurar métodos de pago</p>
                </div>

                <!-- Botón Agregar -->
                <div class="mb-4 flex justify-end">
                    <button 
                        @click="abrirModalCrear"
                        class="inline-flex items-center gap-2 px-3 sm:px-4 py-1.5 sm:py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-[11px] sm:text-sm transition shadow-sm"
                    >
                        <i class="fas fa-plus text-[10px] sm:text-xs"></i>
                        Nuevo Concepto
                    </button>
                </div>

                <!-- 🔥 LISTA DE TARJETAS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div 
                        v-for="item in conceptos" 
                        :key="item.IdConceptoLiquidacion" 
                        class="bg-white rounded-lg sm:rounded-xl shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden border border-gray-100"
                    >
                        <!-- Header -->
                        <div class="px-3 sm:px-4 py-2 sm:py-3 border-b"
                            :class="item.activo === 1 ? 'bg-primary-50 border-primary-100' : 'bg-gray-50 border-gray-100'">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-full flex items-center justify-center"
                                        :class="item.activo === 1 ? 'bg-primary-200 text-primary-700' : 'bg-gray-200 text-gray-500'">
                                        <i class="fas fa-tag text-[10px] sm:text-xs"></i>
                                    </div>
                                    <h3 class="font-semibold text-sm sm:text-base text-gray-800">{{ item.Concepto }}</h3>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cuerpo -->
                        <div class="p-3 sm:p-4 space-y-2 sm:space-y-3">
                            <!-- Cuenta Contable -->
                            <div class="flex items-start gap-2">
                                <i class="fas fa-book text-gray-400 text-[10px] sm:text-xs mt-0.5"></i>
                                <div class="flex-1">
                                    <p class="text-[9px] sm:text-xs text-gray-500">Cuenta Contable</p>
                                    <p class="text-[11px] sm:text-sm font-medium text-gray-800">
                                        <span class="font-mono text-[10px] sm:text-xs font-bold">{{ item.cuenta_contable?.Cuenta || item.IdCuenta }}</span>
                                        <span v-if="item.cuenta_contable?.Descripcion" class="text-gray-500 text-[9px] sm:text-xs block">
                                            {{ item.cuenta_contable.Descripcion }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Requiere Identificador -->
                            <div class="flex items-center justify-between border-t pt-2">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-users text-gray-400 text-[10px] sm:text-xs"></i>
                                    <span class="text-[10px] sm:text-xs text-gray-600">Seleccionar Cliente</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <div class="w-2 h-2 rounded-full" :class="mostrarRequiereIdentificador(item).punto"></div>
                                    <span :class="mostrarRequiereIdentificador(item).clase" class="text-[10px] sm:text-xs px-1.5 py-0.5 rounded-full">
                                        {{ mostrarRequiereIdentificador(item).texto }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Usa ID Factura -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-qrcode text-gray-400 text-[10px] sm:text-xs"></i>
                                    <span class="text-[10px] sm:text-xs text-gray-600">Usar ID Factura</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <div class="w-2 h-2 rounded-full" :class="mostrarUsaIdentificadorFactura(item).punto"></div>
                                    <span :class="mostrarUsaIdentificadorFactura(item).clase" class="text-[10px] sm:text-xs px-1.5 py-0.5 rounded-full">
                                        {{ mostrarUsaIdentificadorFactura(item).texto }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Footer acciones -->
                        <div class="px-3 sm:px-4 py-2 sm:py-3 bg-gray-50 flex justify-end gap-2 border-t">
                            <button 
                                @click="abrirModalEditar(item)" 
                                class="px-2 sm:px-3 py-1 text-primary-600 hover:bg-primary-100 rounded-md transition text-[10px] sm:text-xs"
                            >
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button 
                                @click="eliminar(item.IdConceptoLiquidacion, item.Concepto)" 
                                class="px-2 sm:px-3 py-1 text-red-600 hover:bg-red-100 rounded-md transition text-[10px] sm:text-xs"
                            >
                                <i class="fas fa-trash-alt"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Mensaje vacío -->
                    <div v-if="!conceptos || conceptos.length === 0" class="col-span-full">
                        <div class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">
                            <i class="fas fa-info-circle text-3xl mb-2 block"></i>
                            <p class="text-sm">No hay conceptos configurados</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🔥 MODAL CORREGIDO -->
        <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="cerrarModal"></div>
            
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="relative bg-white rounded-xl shadow-xl max-w-md sm:max-w-lg md:max-w-2xl w-full mx-auto">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 bg-primary-50 rounded-t-xl">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-coins text-white text-xs sm:text-sm"></i>
                            </div>
                            <h3 class="text-sm sm:text-base font-semibold text-gray-900">
                                {{ editando ? 'Editar Concepto' : 'Nuevo Concepto' }}
                            </h3>
                        </div>
                        <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600 transition">
                            <i class="fas fa-times text-sm sm:text-base"></i>
                        </button>
                    </div>
                    
                    <!-- Cuerpo -->
                    <div class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
                        <!-- Concepto -->
                        <div>
                            <label class="block text-[11px] sm:text-xs font-medium text-gray-700 mb-1">
                                Concepto <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                v-model="formData.Concepto" 
                                placeholder="Ej: Efectivo, Tarjeta, QR"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                :class="{ 'border-red-500': errors.Concepto }"
                            />
                            <p v-if="errors.Concepto" class="text-xs text-red-500 mt-1">{{ errors.Concepto }}</p>
                        </div>
                        
                        <!-- Cuenta Contable -->
                        <div class="relative cuentas-autocomplete">
                            <label class="block text-[11px] sm:text-xs font-medium text-gray-700 mb-1">
                                Cuenta Contable <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text"
                                v-model="busquedaCuenta"
                                @focus="mostrarListaCuentas = true"
                                placeholder="Buscar cuenta..."
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                :class="{ 
                                    'border-red-500': errors.IdCuenta
                                }"
                            />
                            <div v-if="mostrarListaCuentas && cuentasFiltradas.length > 0" 
                                class="absolute z-50 w-full max-h-48 overflow-y-auto bg-white border rounded-lg shadow-lg mt-1">
                                <div 
                                    v-for="cuenta in cuentasFiltradas" 
                                    :key="cuenta.id"
                                    @click="seleccionarCuenta(cuenta)"
                                    class="px-3 py-2 hover:bg-primary-50 cursor-pointer border-b last:border-b-0"
                                >
                                    <div class="font-mono text-sm">
                                        <span class="font-bold">{{ cuenta.id }}</span> - {{ cuenta.nombre }}
                                    </div>
                                    <div v-if="cuenta.descripcion" class="text-xs text-gray-500">{{ cuenta.descripcion }}</div>
                                </div>
                            </div>
                            <p v-if="errors.IdCuenta" class="text-xs text-red-500 mt-1">{{ errors.IdCuenta }}</p>
                        </div>
                        
                        <!-- 🔥 ESTADO CON RADIOS CORREGIDOS - v-model.number -->
                        <div>
                            <label class="block text-[11px] sm:text-xs font-medium text-gray-700 mb-2">Estado</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" :value="1" v-model.number="formData.activo" class="w-4 h-4 text-primary-600"/>
                                    <span class="text-sm">✓ Activo</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" :value="0" v-model.number="formData.activo" class="w-4 h-4 text-primary-600"/>
                                    <span class="text-sm">✗ Inactivo</span>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Valor actual: 
                                <span :class="formData.activo === 1 ? 'text-green-600 font-medium' : 'text-red-600 font-medium'">
                                    {{ formData.activo === 1 ? 'Activo' : 'Inactivo' }}
                                </span>
                            </p>
                        </div>
                        
                        <!-- Separador -->
                        <div class="border-t pt-3">
                            <p class="text-xs font-medium text-gray-700 mb-3">Configuración adicional:</p>
                            
                            <!-- Requiere Identificador -->
                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg mb-2 transition"
                                :class="formData.requiere_identificador ? 'bg-yellow-50 border border-yellow-200' : 'bg-gray-50'">
                                <input 
                                    type="checkbox" 
                                    v-model="formData.requiere_identificador"
                                    @change="formData.usa_identificador_factura = false"
                                    class="w-4 h-4 text-yellow-600 rounded focus:ring-yellow-500"
                                />
                                <div class="flex-1">
                                    <span class="text-sm" :class="formData.requiere_identificador ? 'text-yellow-700 font-medium' : 'text-gray-700'">
                                        <i class="fas fa-users mr-2"></i>
                                        Requiere Seleccionar Cliente
                                    </span>
                                    <p class="text-xs text-gray-500">El vendedor debe elegir un cliente de la base de datos</p>
                                </div>
                                <span v-if="formData.requiere_identificador" class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                    Activo
                                </span>
                            </label>
                            
                            <!-- Usa Identificador Factura -->
                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg transition"
                                :class="formData.usa_identificador_factura ? 'bg-purple-50 border border-purple-200' : 'bg-gray-50'">
                                <input 
                                    type="checkbox" 
                                    v-model="formData.usa_identificador_factura"
                                    @change="formData.requiere_identificador = false"
                                    class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                                />
                                <div class="flex-1">
                                    <span class="text-sm" :class="formData.usa_identificador_factura ? 'text-purple-700 font-medium' : 'text-gray-700'">
                                        <i class="fas fa-qrcode mr-2"></i>
                                        Usar Identificador de la Factura
                                    </span>
                                    <p class="text-xs text-gray-500">Usa el NIT/CI del cliente que está pagando la factura</p>
                                </div>
                                <span v-if="formData.usa_identificador_factura" class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">
                                    Activo
                                </span>
                            </label>
                        </div>
                        
                        <!-- Mensaje de error -->
                        <div v-if="formData.requiere_identificador && formData.usa_identificador_factura" class="p-2 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-xs text-red-600 text-center">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                No puedes seleccionar ambas opciones simultáneamente
                            </p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="px-4 sm:px-6 py-3 sm:py-4 bg-gray-50 rounded-b-xl flex justify-end gap-2 border-t">
                        <button @click="cerrarModal" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition">
                            Cancelar
                        </button>
                        <button 
                            @click="guardar" 
                            :disabled="guardando || (formData.requiere_identificador && formData.usa_identificador_factura)"
                            class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 transition disabled:opacity-50 flex items-center gap-2"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ guardando ? 'Guardando...' : (editando ? 'Actualizar' : 'Guardar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>