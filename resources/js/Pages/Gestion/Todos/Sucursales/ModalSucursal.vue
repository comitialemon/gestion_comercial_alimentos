<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    sucursal: {
        type: Object,
        default: null
    },
    plazas: {
        type: Array,
        default: () => []
    },
    editando: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'saved'])

// Estado del formulario
const form = ref({
    IdPlaza: null,
    Nombre: '',
    Direccion: '',
    Celular: '',
    NumeroSucursal: '',
    Orden: 0,
    ActivoInactivo: 0, // 0 = Activo, 1 = Inactivo
    ActivaInactivaR: 0, // 0 = Activa, 1 = Inactiva
})

const loading = ref(false)
const errors = ref({})
const errorMensaje = ref('')

// Computed para mostrar los estados
const estadoTexto = computed(() => {
    return form.value.ActivoInactivo === 0 ? 'ACTIVO' : 'INACTIVO'
})

const estadoColor = computed(() => {
    return form.value.ActivoInactivo === 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
})

const estadoRTexto = computed(() => {
    return form.value.ActivaInactivaR === 0 ? 'ACTIVA' : 'INACTIVA'
})

const estadoRColor = computed(() => {
    return form.value.ActivaInactivaR === 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'
})

// Obtener el siguiente número disponible
const obtenerSiguienteNumero = async () => {
    try {
        const response = await axios.get('/gestion/sucursales/siguiente-numero')
        if (response.data.success) {
            if (!props.editando) {
                form.value.NumeroSucursal = response.data.siguienteNumero
                form.value.Orden = response.data.siguienteOrden
            }
        }
    } catch (error) {
        console.error('Error al obtener siguiente número:', error)
        if (!props.editando) {
            form.value.NumeroSucursal = 1
            form.value.Orden = 0
        }
    }
}

// Cargar datos al editar - CORREGIDO
watch(() => props.sucursal, (newVal) => {
    if (newVal && props.editando) {
        console.log('📝 Cargando datos para editar:', newVal)
        form.value = {
            IdPlaza: newVal.IdPlaza || null,
            Nombre: newVal.Nombre || '',
            Direccion: newVal.Direccion || '',
            Celular: newVal.Celular || '',
            NumeroSucursal: newVal.NumeroSucursal || '',
            Orden: newVal.Orden || 0,
            ActivoInactivo: newVal.ActivoInactivo ?? 0, // Mantener el valor de la BD
            ActivaInactivaR: newVal.ActivaInactivaR ?? 0, // Mantener el valor de la BD
        }
        console.log('🔄 Formulario cargado:', form.value)
    }
}, { immediate: true, deep: true })

// Cuando se abre el modal, obtener el siguiente número
watch(() => props.modelValue, (newVal) => {
    if (newVal && !props.editando) {
        // Resetear a valores por defecto para nueva sucursal
        form.value = {
            IdPlaza: null,
            Nombre: '',
            Direccion: '',
            Celular: '',
            NumeroSucursal: '',
            Orden: 0,
            ActivoInactivo: 0, // Por defecto Activo
            ActivaInactivaR: 0, // Por defecto Activa
        }
        errors.value = {}
        errorMensaje.value = ''
        obtenerSiguienteNumero()
    }
}, { immediate: true })

// Resetear formulario al cerrar
const resetForm = () => {
    form.value = {
        IdPlaza: null,
        Nombre: '',
        Direccion: '',
        Celular: '',
        NumeroSucursal: '',
        Orden: 0,
        ActivoInactivo: 0,
        ActivaInactivaR: 0,
    }
    errors.value = {}
    errorMensaje.value = ''
}

const cerrarModal = () => {
    emit('update:modelValue', false)
    resetForm()
}

// Alternar estados - CORREGIDO
const toggleEstado = () => {
    // Si es 0 (Activo) pasa a 1 (Inactivo), si es 1 (Inactivo) pasa a 0 (Activo)
    form.value.ActivoInactivo = form.value.ActivoInactivo === 0 ? 1 : 0
    console.log('🔄 Estado ActivoInactivo cambiado a:', form.value.ActivoInactivo === 0 ? 'ACTIVO (0)' : 'INACTIVO (1)')
}

const toggleEstadoR = () => {
    form.value.ActivaInactivaR = form.value.ActivaInactivaR === 0 ? 1 : 0
    console.log('🔄 Estado ActivaInactivaR cambiado a:', form.value.ActivaInactivaR === 0 ? 'ACTIVA (0)' : 'INACTIVA (1)')
}

// Validar formulario antes de enviar
const validarFormulario = () => {
    const errores = {}
    
    if (!form.value.IdPlaza) {
        errores.IdPlaza = ['La plaza es obligatoria']
    }
    
    if (!form.value.Nombre || form.value.Nombre.trim() === '') {
        errores.Nombre = ['El nombre es obligatorio']
    }
    
    if (!form.value.Direccion || form.value.Direccion.trim() === '') {
        errores.Direccion = ['La dirección es obligatoria']
    }
    
    if (!form.value.Celular || form.value.Celular.trim() === '') {
        errores.Celular = ['El celular es obligatorio']
    }
    
    errors.value = errores
    return Object.keys(errores).length === 0
}

const guardar = async () => {
    loading.value = true
    errors.value = {}
    errorMensaje.value = ''
    
    // Validación previa
    if (!validarFormulario()) {
        loading.value = false
        return
    }
    
    // Preparar datos - CORREGIDO
    const data = {
        IdPlaza: parseInt(form.value.IdPlaza),
        Nombre: form.value.Nombre.toUpperCase().trim(),
        Direccion: form.value.Direccion.trim(),
        Celular: form.value.Celular.trim(),
        NumeroSucursal: parseInt(form.value.NumeroSucursal) || 0,
        Orden: parseInt(form.value.Orden) || 0,
        ActivoInactivo: form.value.ActivoInactivo, // 0 = Activo, 1 = Inactivo
        ActivaInactivaR: form.value.ActivaInactivaR, // 0 = Activa, 1 = Inactiva
    }
    
    console.log('📦 Datos completos a enviar:', data)
    
    try {
        let response
        if (props.editando && props.sucursal) {
            console.log('✏️ EDITANDO sucursal ID:', props.sucursal.IdClienteSucursal)
            response = await axios.put(`/gestion/sucursales/${props.sucursal.IdClienteSucursal}`, data)
        } else {
            console.log('➕ CREANDO nueva sucursal')
            response = await axios.post('/gestion/sucursales', data)
        }
        
        console.log('✅ Respuesta del servidor:', response.data)
        
        if (response.data.success) {
            emit('saved')
            cerrarModal()
        } else {
            errorMensaje.value = response.data.message || 'Error al guardar'
            console.error('❌ Error del servidor:', errorMensaje.value)
        }
    } catch (error) {
        console.error('❌ Error en la petición:', error)
        
        if (error.response) {
            console.error('  - Status:', error.response.status)
            console.error('  - Data:', error.response.data)
            
            if (error.response.data?.errors) {
                errors.value = error.response.data.errors
                console.error('  - Errores de validación:', errors.value)
                
                const errorMessages = Object.values(errors.value).flat()
                if (errorMessages.length > 0) {
                    errorMensaje.value = errorMessages.join(' • ')
                }
            } else if (error.response.data?.message) {
                errorMensaje.value = error.response.data.message
            } else {
                errorMensaje.value = 'Error al guardar: ' + (error.response.statusText || 'Error desconocido')
            }
        } else if (error.request) {
            errorMensaje.value = 'No se recibió respuesta del servidor'
            console.error('  - No response:', error.request)
        } else {
            errorMensaje.value = 'Error: ' + error.message
            console.error('  - Error:', error.message)
        }
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="cerrarModal">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="flex justify-between items-center p-4 border-b sticky top-0 bg-white">
                    <h2 class="text-lg font-semibold text-gray-800">
                        {{ editando ? 'Editar Sucursal' : 'Nueva Sucursal' }}
                    </h2>
                    <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Mostrar error si existe -->
                <div v-if="errorMensaje" class="mx-5 mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
                        <div>
                            <p class="text-sm font-medium text-red-800">Error al guardar</p>
                            <p class="text-xs text-red-600">{{ errorMensaje }}</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="p-5 space-y-4">
                    <!-- Plaza -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Plaza <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.IdPlaza" class="w-full border rounded-lg px-3 py-2 text-sm" :class="{ 'border-red-500': errors.IdPlaza }">
                            <option :value="null">Seleccione una plaza</option>
                            <option v-for="plaza in plazas" :key="plaza.id" :value="plaza.id">
                                {{ plaza.nombre }}
                            </option>
                        </select>
                        <div v-if="errors.IdPlaza" class="text-xs text-red-500 mt-1">
                            <span v-for="msg in errors.IdPlaza" :key="msg" class="block">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ msg }}
                            </span>
                        </div>
                    </div>

                    <!-- Nombre -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Nombre" class="w-full border rounded-lg px-3 py-2 text-sm uppercase" :class="{ 'border-red-500': errors.Nombre }" placeholder="NOMBRE DE SUCURSAL" />
                        <div v-if="errors.Nombre" class="text-xs text-red-500 mt-1">
                            <span v-for="msg in errors.Nombre" :key="msg" class="block">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ msg }}
                            </span>
                        </div>
                    </div>

                    <!-- Dirección -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Dirección <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Direccion" class="w-full border rounded-lg px-3 py-2 text-sm" :class="{ 'border-red-500': errors.Direccion }" placeholder="Dirección completa" />
                        <div v-if="errors.Direccion" class="text-xs text-red-500 mt-1">
                            <span v-for="msg in errors.Direccion" :key="msg" class="block">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ msg }}
                            </span>
                        </div>
                    </div>

                    <!-- Celular -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Celular / WhatsApp <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Celular" class="w-full border rounded-lg px-3 py-2 text-sm" :class="{ 'border-red-500': errors.Celular }" placeholder="9XXXXXXXX" />
                        <div v-if="errors.Celular" class="text-xs text-red-500 mt-1">
                            <span v-for="msg in errors.Celular" :key="msg" class="block">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ msg }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Número Sucursal -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Número Sucursal
                                <span v-if="!editando" class="text-xs text-gray-400 font-normal">
                                    (Auto-asignado)
                                </span>
                            </label>
                            <input 
                                type="number" 
                                v-model="form.NumeroSucursal" 
                                class="w-full border rounded-lg px-3 py-2 text-sm" 
                                :class="{ 'border-red-500': errors.NumeroSucursal, 'bg-gray-50': !editando }"
                                :readonly="!editando"
                                placeholder="Auto" 
                            />
                            <div v-if="errors.NumeroSucursal" class="text-xs text-red-500 mt-1">
                                <span v-for="msg in errors.NumeroSucursal" :key="msg" class="block">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ msg }}
                                </span>
                            </div>
                        </div>

                        <!-- Orden -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Orden
                                <span v-if="!editando" class="text-xs text-gray-400 font-normal">
                                    (Auto-asignado)
                                </span>
                            </label>
                            <input 
                                type="number" 
                                v-model="form.Orden" 
                                min="0" 
                                class="w-full border rounded-lg px-3 py-2 text-sm" 
                                :class="{ 'border-red-500': errors.Orden, 'bg-gray-50': !editando }"
                                :readonly="!editando"
                                placeholder="Auto" 
                            />
                            <div v-if="errors.Orden" class="text-xs text-red-500 mt-1">
                                <span v-for="msg in errors.Orden" :key="msg" class="block">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ msg }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- ESTADO ActivoInactivo (TOGGLE) -->
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Estado <span class="text-xs text-gray-400">(0=Activo / 1=Inactivo)</span>
                        </label>
                        
                        <div class="flex items-center gap-4">
                            <button 
                                type="button"
                                @click="toggleEstado"
                                class="relative inline-flex items-center h-8 rounded-full w-16 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                                :class="form.ActivoInactivo === 0 ? 'bg-emerald-600' : 'bg-gray-300'"
                            >
                                <span 
                                    class="inline-block h-6 w-6 transform rounded-full bg-white shadow-lg transition duration-200 flex items-center justify-center"
                                    :class="form.ActivoInactivo === 0 ? 'translate-x-9' : 'translate-x-1'"
                                >
                                    <svg v-if="form.ActivoInactivo === 0" class="h-3 w-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <svg v-else class="h-3 w-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </span>
                            </button>
                            <span class="text-sm font-medium px-3 py-1 rounded-full" :class="estadoColor">
                                {{ estadoTexto }}
                            </span>
                            <span class="text-xs text-gray-400">
                                ({{ form.ActivoInactivo === 0 ? '0=Activo' : '1=Inactivo' }} en BD)
                            </span>
                        </div>
                        
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            0 = Activo, 1 = Inactivo
                        </p>
                    </div>

                    <!-- ESTADO ActivaInactivaR (TOGGLE) -->
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Estado R <span class="text-xs text-gray-400">(0=Activa / 1=Inactiva)</span>
                        </label>
                        
                        <div class="flex items-center gap-4">
                            <button 
                                type="button"
                                @click="toggleEstadoR"
                                class="relative inline-flex items-center h-8 rounded-full w-16 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                :class="form.ActivaInactivaR === 0 ? 'bg-blue-600' : 'bg-gray-300'"
                            >
                                <span 
                                    class="inline-block h-6 w-6 transform rounded-full bg-white shadow-lg transition duration-200 flex items-center justify-center"
                                    :class="form.ActivaInactivaR === 0 ? 'translate-x-9' : 'translate-x-1'"
                                >
                                    <svg v-if="form.ActivaInactivaR === 0" class="h-3 w-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <svg v-else class="h-3 w-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </span>
                            </button>
                            <span class="text-sm font-medium px-3 py-1 rounded-full" :class="estadoRColor">
                                {{ estadoRTexto }}
                            </span>
                            <span class="text-xs text-gray-400">
                                ({{ form.ActivaInactivaR === 0 ? '0=Activa' : '1=Inactiva' }} en BD)
                            </span>
                        </div>
                        
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            0 = Activa, 1 = Inactiva
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-3 p-4 border-t sticky bottom-0 bg-white">
                    <button @click="cerrarModal" class="px-4 py-2 border rounded-lg text-gray-700 text-sm hover:bg-gray-100">
                        Cancelar
                    </button>
                    <button @click="guardar" :disabled="loading" class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-2">
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ loading ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>