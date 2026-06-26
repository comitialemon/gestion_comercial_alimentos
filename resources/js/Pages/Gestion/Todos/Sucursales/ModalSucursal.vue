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
    IdPlaza: '',
    Nombre: '',
    Direccion: '',
    Celular: '',
    NumeroSucursal: '',
    Orden: 0,
    ActivoInactivo: true,  // true = Activo (1), false = Inactivo (0)
    ActivaInactivaR: true, // true = Activa (1), false = Inactiva (0)
})

const loading = ref(false)
const errors = ref({})
const errorMensaje = ref('')

// Computed para mostrar los estados
const estadoTexto = computed(() => {
    return form.value.ActivoInactivo ? 'ACTIVO' : 'INACTIVO'
})

const estadoColor = computed(() => {
    return form.value.ActivoInactivo ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
})

const estadoRTexto = computed(() => {
    return form.value.ActivaInactivaR ? 'ACTIVA' : 'INACTIVA'
})

const estadoRColor = computed(() => {
    return form.value.ActivaInactivaR ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'
})

// Obtener el siguiente número disponible
const obtenerSiguienteNumero = async () => {
    try {
        const response = await axios.get('/sucursales/siguiente-numero')
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

// Cargar datos al editar
watch(() => props.sucursal, (newVal) => {
    if (newVal && props.editando) {
        console.log('📝 Cargando datos para editar:', newVal)
        form.value = {
            IdPlaza: newVal.IdPlaza || '',
            Nombre: newVal.Nombre || '',
            Direccion: newVal.Direccion || '',
            Celular: newVal.Celular || '',
            NumeroSucursal: newVal.NumeroSucursal || '',
            Orden: newVal.Orden || 0,
            // 🔥 CORREGIDO: Activo = 1 → true, Inactivo = 0 → false
            ActivoInactivo: newVal.ActivoInactivo === 1,
            ActivaInactivaR: newVal.ActivaInactivaR === 1,
        }
        console.log('🔄 Formulario cargado:', form.value)
    }
}, { immediate: true })

// Cuando se abre el modal, obtener el siguiente número
watch(() => props.modelValue, (newVal) => {
    if (newVal && !props.editando) {
        obtenerSiguienteNumero()
    }
}, { immediate: true })

// Resetear formulario al cerrar
const resetForm = () => {
    form.value = {
        IdPlaza: '',
        Nombre: '',
        Direccion: '',
        Celular: '',
        NumeroSucursal: '',
        Orden: 0,
        ActivoInactivo: true,  // Por defecto Activo (1)
        ActivaInactivaR: true, // Por defecto Activa (1)
    }
    errors.value = {}
    errorMensaje.value = ''
}

const cerrarModal = () => {
    emit('update:modelValue', false)
    resetForm()
}

// Alternar estados
const toggleEstado = () => {
    form.value.ActivoInactivo = !form.value.ActivoInactivo
    console.log('🔄 Estado ActivoInactivo cambiado a:', form.value.ActivoInactivo ? 'ACTIVO (1)' : 'INACTIVO (0)')
}

const toggleEstadoR = () => {
    form.value.ActivaInactivaR = !form.value.ActivaInactivaR
    console.log('🔄 Estado ActivaInactivaR cambiado a:', form.value.ActivaInactivaR ? 'ACTIVA (1)' : 'INACTIVA (0)')
}

const guardar = async () => {
    loading.value = true
    errors.value = {}
    errorMensaje.value = ''
    
    console.log('📤 Datos a enviar:')
    console.log('  - ActivoInactivo (boolean):', form.value.ActivoInactivo)
    console.log('  - ActivoInactivo (convertido):', form.value.ActivoInactivo ? 1 : 0)
    console.log('  - ActivaInactivaR (boolean):', form.value.ActivaInactivaR)
    console.log('  - ActivaInactivaR (convertido):', form.value.ActivaInactivaR ? 1 : 0)
    
    // Preparar datos
    const data = {
        IdPlaza: form.value.IdPlaza,
        Nombre: form.value.Nombre.toUpperCase(),
        Direccion: form.value.Direccion,
        Celular: form.value.Celular,
        NumeroSucursal: parseInt(form.value.NumeroSucursal) || 0,
        Orden: parseInt(form.value.Orden) || 0,
        // 🔥 CORREGIDO: true → 1 (Activo), false → 0 (Inactivo)
        ActivoInactivo: form.value.ActivoInactivo ? 1 : 0,
        ActivaInactivaR: form.value.ActivaInactivaR ? 1 : 0,
    }
    
    console.log('📦 Datos completos a enviar:', data)
    
    try {
        let response
        if (props.editando && props.sucursal) {
            console.log('✏️ EDITANDO sucursal ID:', props.sucursal.IdClienteSucursal)
            response = await axios.put(`/sucursales/${props.sucursal.IdClienteSucursal}`, data)
        } else {
            console.log('➕ CREANDO nueva sucursal')
            response = await axios.post('/sucursales', data)
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

                <!-- 🔥 Mostrar error si existe -->
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
                            <option value="">Seleccione una plaza</option>
                            <option v-for="plaza in plazas" :key="plaza.id" :value="plaza.id">
                                {{ plaza.nombre }}
                            </option>
                        </select>
                        <p v-if="errors.IdPlaza" class="text-xs text-red-500 mt-1">{{ errors.IdPlaza }}</p>
                    </div>

                    <!-- Nombre -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Nombre" class="w-full border rounded-lg px-3 py-2 text-sm uppercase" :class="{ 'border-red-500': errors.Nombre }" placeholder="NOMBRE DE SUCURSAL" />
                        <p v-if="errors.Nombre" class="text-xs text-red-500 mt-1">{{ errors.Nombre }}</p>
                    </div>

                    <!-- Dirección -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Dirección <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Direccion" class="w-full border rounded-lg px-3 py-2 text-sm" :class="{ 'border-red-500': errors.Direccion }" placeholder="Dirección completa" />
                        <p v-if="errors.Direccion" class="text-xs text-red-500 mt-1">{{ errors.Direccion }}</p>
                    </div>

                    <!-- Celular -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Celular / WhatsApp <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Celular" class="w-full border rounded-lg px-3 py-2 text-sm" :class="{ 'border-red-500': errors.Celular }" placeholder="9XXXXXXXX" />
                        <p v-if="errors.Celular" class="text-xs text-red-500 mt-1">{{ errors.Celular }}</p>
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
                            <p v-if="errors.NumeroSucursal" class="text-xs text-red-500 mt-1">{{ errors.NumeroSucursal }}</p>
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
                            <p v-if="errors.Orden" class="text-xs text-red-500 mt-1">{{ errors.Orden }}</p>
                        </div>
                    </div>

                    <!-- ESTADO ActivoInactivo (TOGGLE) -->
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Estado <span class="text-xs text-gray-400">(Activo/Inactivo)</span>
                        </label>
                        
                        <div class="flex items-center gap-4">
                            <button 
                                type="button"
                                @click="toggleEstado"
                                class="relative inline-flex items-center h-8 rounded-full w-16 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                                :class="form.ActivoInactivo ? 'bg-emerald-600' : 'bg-gray-300'"
                            >
                                <span 
                                    class="inline-block h-6 w-6 transform rounded-full bg-white shadow-lg transition duration-200 flex items-center justify-center"
                                    :class="form.ActivoInactivo ? 'translate-x-9' : 'translate-x-1'"
                                >
                                    <svg v-if="form.ActivoInactivo" class="h-3 w-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                ({{ form.ActivoInactivo ? '1' : '0' }} en BD)
                            </span>
                        </div>
                        
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Activo = 1, Inactivo = 0
                        </p>
                    </div>

                    <!-- ESTADO ActivaInactivaR (TOGGLE) -->
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Estado R <span class="text-xs text-gray-400">(Activa/Inactiva R)</span>
                        </label>
                        
                        <div class="flex items-center gap-4">
                            <button 
                                type="button"
                                @click="toggleEstadoR"
                                class="relative inline-flex items-center h-8 rounded-full w-16 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                :class="form.ActivaInactivaR ? 'bg-blue-600' : 'bg-gray-300'"
                            >
                                <span 
                                    class="inline-block h-6 w-6 transform rounded-full bg-white shadow-lg transition duration-200 flex items-center justify-center"
                                    :class="form.ActivaInactivaR ? 'translate-x-9' : 'translate-x-1'"
                                >
                                    <svg v-if="form.ActivaInactivaR" class="h-3 w-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                ({{ form.ActivaInactivaR ? '1' : '0' }} en BD)
                            </span>
                        </div>
                        
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Activa = 1, Inactiva = 0
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