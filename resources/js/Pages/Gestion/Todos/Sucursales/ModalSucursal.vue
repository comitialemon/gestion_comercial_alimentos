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

// ==================== ESTADO ====================
const form = ref({
    IdPlaza: null,
    Nombre: '',
    Direccion: '',
    Celular: '',
    NumeroSucursal: '',
    Orden: 0,
    ActivoInactivo: 0,
    ActivaInactivaR: 0,
})

const loading = ref(false)
const errors = ref({})
const errorMensaje = ref('')

// ==================== COMPUTED ====================
const estadoTexto = computed(() => {
    return form.value.ActivoInactivo === 0 ? 'ACTIVO' : 'INACTIVO'
})

const estadoColor = computed(() => {
    return form.value.ActivoInactivo === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
})

const estadoRTexto = computed(() => {
    return form.value.ActivaInactivaR === 0 ? 'ACTIVA' : 'INACTIVA'
})

const estadoRColor = computed(() => {
    return form.value.ActivaInactivaR === 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'
})

// ==================== FUNCIONES ====================
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

const toggleEstado = () => {
    form.value.ActivoInactivo = form.value.ActivoInactivo === 0 ? 1 : 0
}

const toggleEstadoR = () => {
    form.value.ActivaInactivaR = form.value.ActivaInactivaR === 0 ? 1 : 0
}

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
    
    if (!validarFormulario()) {
        loading.value = false
        return
    }
    
    const data = {
        IdPlaza: parseInt(form.value.IdPlaza),
        Nombre: form.value.Nombre.toUpperCase().trim(),
        Direccion: form.value.Direccion.trim(),
        Celular: form.value.Celular.trim(),
        NumeroSucursal: parseInt(form.value.NumeroSucursal) || 0,
        Orden: parseInt(form.value.Orden) || 0,
        ActivoInactivo: form.value.ActivoInactivo,
        ActivaInactivaR: form.value.ActivaInactivaR,
    }
    
    try {
        let response
        if (props.editando && props.sucursal) {
            response = await axios.put(`/gestion/sucursales/${props.sucursal.IdClienteSucursal}`, data)
        } else {
            response = await axios.post('/gestion/sucursales', data)
        }
        
        if (response.data.success) {
            emit('saved')
            cerrarModal()
        } else {
            errorMensaje.value = response.data.message || 'Error al guardar'
        }
    } catch (error) {
        console.error('❌ Error:', error)
        
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            const errorMessages = Object.values(errors.value).flat()
            if (errorMessages.length > 0) {
                errorMensaje.value = errorMessages.join(' • ')
            }
        } else if (error.response?.data?.message) {
            errorMensaje.value = error.response.data.message
        } else {
            errorMensaje.value = 'Error al guardar'
        }
    } finally {
        loading.value = false
    }
}

// ==================== WATCHERS ====================
watch(() => props.sucursal, (newVal) => {
    if (newVal && props.editando) {
        form.value = {
            IdPlaza: newVal.IdPlaza || null,
            Nombre: newVal.Nombre || '',
            Direccion: newVal.Direccion || '',
            Celular: newVal.Celular || '',
            NumeroSucursal: newVal.NumeroSucursal || '',
            Orden: newVal.Orden || 0,
            ActivoInactivo: newVal.ActivoInactivo ?? 0,
            ActivaInactivaR: newVal.ActivaInactivaR ?? 0,
        }
    }
}, { immediate: true, deep: true })

watch(() => props.modelValue, (newVal) => {
    if (newVal && !props.editando) {
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
        obtenerSiguienteNumero()
    }
}, { immediate: true })
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="cerrarModal">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex justify-between items-center px-4 py-2.5 bg-primary-600 rounded-t-xl">
                    <h2 class="text-sm font-semibold text-white">
                        {{ editando ? 'Editar Sucursal' : 'Nueva Sucursal' }}
                    </h2>
                    <button @click="cerrarModal" class="text-white/80 hover:text-white transition text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- ==================== ERROR ==================== -->
                <div v-if="errorMensaje" class="mx-4 mt-3 p-2 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-start gap-1.5">
                        <i class="fas fa-exclamation-circle text-red-500 mt-0.5 text-[10px]"></i>
                        <div>
                            <p class="text-[10px] font-medium text-red-800">Error al guardar</p>
                            <p class="text-[9px] text-red-600">{{ errorMensaje }}</p>
                        </div>
                    </div>
                </div>

                <!-- ==================== FORMULARIO ==================== -->
                <div class="p-4 space-y-3">
                    <!-- Plaza -->
                    <div>
                        <label class="text-[10px] font-medium text-gray-700 mb-0.5 block">Plaza <span class="text-red-500">*</span></label>
                        <select v-model="form.IdPlaza" class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" :class="{ 'border-red-500': errors.IdPlaza }">
                            <option :value="null">Seleccione una plaza</option>
                            <option v-for="plaza in plazas" :key="plaza.id" :value="plaza.id">
                                {{ plaza.nombre }}
                            </option>
                        </select>
                        <div v-if="errors.IdPlaza" class="text-[8px] text-red-500 mt-0.5">
                            <span v-for="msg in errors.IdPlaza" :key="msg" class="block">
                                <i class="fas fa-exclamation-circle mr-0.5"></i> {{ msg }}
                            </span>
                        </div>
                    </div>

                    <!-- Nombre -->
                    <div>
                        <label class="text-[10px] font-medium text-gray-700 mb-0.5 block">Nombre <span class="text-red-500">*</span></label>
                        <input type="text" v-model="form.Nombre" class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm uppercase focus:ring-primary-500 focus:border-primary-500 outline-none" :class="{ 'border-red-500': errors.Nombre }" placeholder="NOMBRE DE SUCURSAL" />
                        <div v-if="errors.Nombre" class="text-[8px] text-red-500 mt-0.5">
                            <span v-for="msg in errors.Nombre" :key="msg" class="block">
                                <i class="fas fa-exclamation-circle mr-0.5"></i> {{ msg }}
                            </span>
                        </div>
                    </div>

                    <!-- Dirección -->
                    <div>
                        <label class="text-[10px] font-medium text-gray-700 mb-0.5 block">Dirección <span class="text-red-500">*</span></label>
                        <input type="text" v-model="form.Direccion" class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" :class="{ 'border-red-500': errors.Direccion }" placeholder="Dirección completa" />
                        <div v-if="errors.Direccion" class="text-[8px] text-red-500 mt-0.5">
                            <span v-for="msg in errors.Direccion" :key="msg" class="block">
                                <i class="fas fa-exclamation-circle mr-0.5"></i> {{ msg }}
                            </span>
                        </div>
                    </div>

                    <!-- Celular -->
                    <div>
                        <label class="text-[10px] font-medium text-gray-700 mb-0.5 block">Celular / WhatsApp <span class="text-red-500">*</span></label>
                        <input type="text" v-model="form.Celular" class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" :class="{ 'border-red-500': errors.Celular }" placeholder="9XXXXXXXX" />
                        <div v-if="errors.Celular" class="text-[8px] text-red-500 mt-0.5">
                            <span v-for="msg in errors.Celular" :key="msg" class="block">
                                <i class="fas fa-exclamation-circle mr-0.5"></i> {{ msg }}
                            </span>
                        </div>
                    </div>

                    <!-- Número y Orden -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] font-medium text-gray-700 mb-0.5 block">
                                Número Sucursal
                                <span v-if="!editando" class="text-[8px] text-gray-400 font-normal">(Auto)</span>
                            </label>
                            <input type="number" v-model="form.NumeroSucursal" class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" :class="{ 'border-red-500': errors.NumeroSucursal, 'bg-gray-50': !editando }" :readonly="!editando" placeholder="Auto" />
                            <div v-if="errors.NumeroSucursal" class="text-[8px] text-red-500 mt-0.5">
                                <span v-for="msg in errors.NumeroSucursal" :key="msg" class="block">
                                    <i class="fas fa-exclamation-circle mr-0.5"></i> {{ msg }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-medium text-gray-700 mb-0.5 block">
                                Orden
                                <span v-if="!editando" class="text-[8px] text-gray-400 font-normal">(Auto)</span>
                            </label>
                            <input type="number" v-model="form.Orden" min="0" class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" :class="{ 'border-red-500': errors.Orden, 'bg-gray-50': !editando }" :readonly="!editando" placeholder="Auto" />
                            <div v-if="errors.Orden" class="text-[8px] text-red-500 mt-0.5">
                                <span v-for="msg in errors.Orden" :key="msg" class="block">
                                    <i class="fas fa-exclamation-circle mr-0.5"></i> {{ msg }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Estado ActivoInactivo -->
                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50/50">
                        <label class="text-[10px] font-medium text-gray-700 block mb-1.5">
                            Estado <span class="text-[8px] text-gray-400">(0=Activo / 1=Inactivo)</span>
                        </label>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="toggleEstado"
                                class="relative inline-flex items-center h-7 rounded-full w-14 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                                :class="form.ActivoInactivo === 0 ? 'bg-emerald-600' : 'bg-gray-300'">
                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow-lg transition duration-200 flex items-center justify-center"
                                    :class="form.ActivoInactivo === 0 ? 'translate-x-8' : 'translate-x-1'">
                                    <svg v-if="form.ActivoInactivo === 0" class="h-2.5 w-2.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <svg v-else class="h-2.5 w-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </span>
                            </button>
                            <span class="text-[10px] font-medium px-2.5 py-0.5 rounded-full" :class="estadoColor">
                                {{ estadoTexto }}
                            </span>
                            <span class="text-[8px] text-gray-400">({{ form.ActivoInactivo === 0 ? '0' : '1' }})</span>
                        </div>
                    </div>

                    <!-- Estado ActivaInactivaR -->
                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50/50">
                        <label class="text-[10px] font-medium text-gray-700 block mb-1.5">
                            Estado R <span class="text-[8px] text-gray-400">(0=Activa / 1=Inactiva)</span>
                        </label>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="toggleEstadoR"
                                class="relative inline-flex items-center h-7 rounded-full w-14 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                :class="form.ActivaInactivaR === 0 ? 'bg-blue-600' : 'bg-gray-300'">
                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow-lg transition duration-200 flex items-center justify-center"
                                    :class="form.ActivaInactivaR === 0 ? 'translate-x-8' : 'translate-x-1'">
                                    <svg v-if="form.ActivaInactivaR === 0" class="h-2.5 w-2.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <svg v-else class="h-2.5 w-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </span>
                            </button>
                            <span class="text-[10px] font-medium px-2.5 py-0.5 rounded-full" :class="estadoRColor">
                                {{ estadoRTexto }}
                            </span>
                            <span class="text-[8px] text-gray-400">({{ form.ActivaInactivaR === 0 ? '0' : '1' }})</span>
                        </div>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                    <button @click="cerrarModal" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button @click="guardar" :disabled="loading" class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition disabled:opacity-50 flex items-center gap-1.5">
                        <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-save text-[10px]"></i>
                        {{ loading ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>