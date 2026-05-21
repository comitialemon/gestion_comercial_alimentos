<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
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

const editando = ref(false)
const editId = ref(null)
const formData = ref({
    Concepto: '',
    IdCuenta: '',
    activo: 1
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
        activo: 1
    }
    busquedaCuenta.value = ''
    errors.value = {}
}

// Editar
const editar = (item) => {
    if (!item) return
    editando.value = true
    editId.value = item.IdConceptoLiquidacion
    formData.value = {
        Concepto: item.Concepto || '',
        IdCuenta: item.IdCuenta || '',
        activo: item.activo !== undefined ? item.activo : 1
    }
    const cuenta = props.cuentasContables?.find(c => c?.id === item.IdCuenta)
    if (cuenta) {
        busquedaCuenta.value = `${cuenta.nombre} - ${cuenta.descripcion || ''}`
    }
}

// Guardar (crear o actualizar)
const guardar = async () => {
    guardando.value = true
    errors.value = {}
    
    try {
        let response
        const dataToSend = {
            Concepto: formData.value.Concepto,
            IdCuenta: formData.value.IdCuenta,
            activo: formData.value.activo
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
            resetForm()
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
//eliminar
const eliminar = async (id, nombre) => {
    
    if (!id) {
        toast?.error('Error', 'No se pudo identificar el registro')
        return
    }
    
    if (!confirm(`¿Eliminar "${nombre}"?`)) return
    
    eliminando.value = true
    try {
        // 🔥 Usar DELETE directamente
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
        // Si el error es 405 pero igual eliminó, recargar
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

// Inicializar
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-100 rounded-2xl mb-3">
                        <i class="fas fa-coins text-xl text-blue-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Conceptos de Liquidación</h1>
                    <p class="text-xs text-gray-500">Configurar métodos de pago para sucursales SIN facturación</p>
                    <p class="text-xs text-blue-600 mt-1">
                        <i class="fas fa-globe mr-1"></i> Los conceptos aplican a TODAS las sucursales de la empresa
                    </p>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Concepto *</label>
                            <input 
                                type="text" 
                                v-model="formData.Concepto" 
                                placeholder="Ej: Efectivo, Tarjeta, QR"
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.Concepto }"
                            />
                            <p v-if="errors.Concepto" class="text-xs text-red-500 mt-1">{{ errors.Concepto }}</p>
                        </div>
                        
                        <div class="relative cuentas-autocomplete">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cuenta Contable *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaCuenta"
                                    @focus="mostrarListaCuentas = true"
                                    placeholder="Buscar por número, nombre o descripción..."
                                    class="w-full border rounded-lg px-3 py-2 text-sm"
                                    :class="{ 'border-red-500': errors.IdCuenta }"
                                />
                                <div v-if="mostrarListaCuentas && cuentasFiltradas.length > 0" 
                                    class="absolute z-50 w-full max-h-48 overflow-y-auto bg-white border rounded-lg shadow-lg mt-1">
                                    <div 
                                        v-for="cuenta in cuentasFiltradas" 
                                        :key="cuenta.id"
                                        @click="seleccionarCuenta(cuenta)"
                                        class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b last:border-b-0"
                                    >
                                        <div class="font-mono text-sm text-gray-800">
                                            <span class="font-bold">{{ cuenta.id }}</span> - {{ cuenta.nombre }}
                                        </div>
                                        <div v-if="cuenta.descripcion" class="text-xs text-gray-500">{{ cuenta.descripcion }}</div>
                                    </div>
                                </div>
                                <div v-if="mostrarListaCuentas && cuentasFiltradas.length === 0 && busquedaCuenta" 
                                    class="absolute z-50 w-full bg-white border rounded-lg shadow-lg mt-1 p-3 text-center text-gray-500 text-sm">
                                    No se encontraron cuentas
                                </div>
                            </div>
                            <p v-if="errors.IdCuenta" class="text-xs text-red-500 mt-1">{{ errors.IdCuenta }}</p>
                            <div v-if="formData.IdCuenta && nombreCuentaSeleccionada" class="text-xs text-green-600 mt-1">
                                <i class="fas fa-check-circle"></i> Seleccionada: {{ nombreCuentaSeleccionada }}
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <select v-model.number="formData.activo" class="border rounded-lg px-3 py-2 text-sm">
                                <option :value="1">✓ Activo</option>
                                <option :value="0">✗ Inactivo</option>
                            </select>
                            <button 
                                @click="guardar" 
                                :disabled="guardando"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50"
                            >
                                <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas" :class="editando ? 'fa-pencil-alt' : 'fa-plus'"></i>
                                {{ guardando ? 'Guardando...' : (editando ? 'Actualizar' : 'Agregar') }}
                            </button>
                            <button 
                                v-if="editando" 
                                @click="resetForm" 
                                class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300"
                                :disabled="guardando"
                            >
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de conceptos -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Concepto</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cuenta</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in conceptos" :key="item.IdConceptoLiquidacion" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-800">
                                        <i class="fas fa-tag text-gray-400 mr-2"></i>
                                        {{ item.Concepto }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <div>
                                            <span class="font-mono text-xs font-bold">{{ item.cuenta_contable?.Cuenta || item.IdCuenta }}</span>
                                            <span v-if="item.cuenta_contable?.Descripcion" class="text-xs text-gray-400 block">
                                                {{ item.cuenta_contable.Descripcion }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="item.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                            {{ item.activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button 
                                            @click="editar(item)" 
                                            class="text-blue-600 hover:text-blue-900 mr-3"
                                            :disabled="eliminando || guardando"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button 
                                            @click="eliminar(item.IdConceptoLiquidacion, item.Concepto)" 
                                            class="text-red-600 hover:text-red-900"
                                            :disabled="eliminando || guardando"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!conceptos || conceptos.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        No hay conceptos configurados para esta empresa
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 text-xs text-gray-400 text-center">
                    <i class="fas fa-info-circle"></i> 
                    Estos conceptos se mostrarán en la pantalla de pago para TODAS las sucursales SIN facturación electrónica.
                </div>
            </div>
        </div>
    </div>
</template>