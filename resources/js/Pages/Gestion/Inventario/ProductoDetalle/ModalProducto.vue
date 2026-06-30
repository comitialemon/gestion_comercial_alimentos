<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    producto: {
        type: Object,
        default: null
    },
    grupos: {
        type: Array,
        default: () => []
    },
    lineas: {
        type: Array,
        default: () => []
    },
    estados: {
        type: Array,
        default: () => []
    },
    unidades: {
        type: Array,
        default: () => []
    },
    unidadId: {
        type: Number,
        default: null
    },
    editando: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'saved'])

// ==================== ESTADO ====================
const form = ref({
    IdGrupoAnalisis: null,
    IdLineaProducto: null,
    IdEstadoProducto: null,
    IdUnidadMedida: props.unidadId,
    Codigo: '',
    Descripcion: '',
    OrdenInformes: 0,
    ActivoInactivo: 0,
})

const loading = ref(false)
const errors = ref({})
const errorMensaje = ref('')

// 🔥 Estado para mensajes de validación en tiempo real
const validacionCodigo = ref({ valido: true, mensaje: '' })
const validacionDescripcion = ref({ valido: true, mensaje: '' })

// ==================== COMPUTED ====================
const tituloModal = computed(() => {
    return props.editando ? 'Editar Producto' : 'Nuevo Producto'
})

const textoBoton = computed(() => {
    return loading.value ? 'Guardando...' : (props.editando ? 'Actualizar' : 'Guardar')
})

// ==================== FUNCIONES ====================
const resetForm = () => {
    form.value = {
        IdGrupoAnalisis: null,
        IdLineaProducto: null,
        IdEstadoProducto: null,
        IdUnidadMedida: props.unidadId,
        Codigo: '',
        Descripcion: '',
        OrdenInformes: 0,
        ActivoInactivo: 0,
    }
    errors.value = {}
    errorMensaje.value = ''
    validacionCodigo.value = { valido: true, mensaje: '' }
    validacionDescripcion.value = { valido: true, mensaje: '' }
}

const cerrarModal = () => {
    emit('update:modelValue', false)
    resetForm()
}

// Cargar datos al editar
watch(() => props.producto, (newVal) => {
    if (newVal && props.editando) {
        form.value = {
            IdGrupoAnalisis: newVal.IdGrupoAnalisis || null,
            IdLineaProducto: newVal.IdLineaProducto || null,
            IdEstadoProducto: newVal.IdEstadoProducto || null,
            IdUnidadMedida: newVal.IdUnidadMedida || props.unidadId,
            Codigo: newVal.Codigo || '',
            Descripcion: newVal.Descripcion || '',
            OrdenInformes: newVal.OrdenInformes || 0,
            ActivoInactivo: newVal.ActivoInactivo ?? 0,
        }
        validacionCodigo.value = { valido: true, mensaje: '' }
        validacionDescripcion.value = { valido: true, mensaje: '' }
    }
}, { immediate: true })

// Cuando se abre el modal y no está editando, setear unidad por defecto
watch(() => props.modelValue, (newVal) => {
    if (newVal && !props.editando && props.unidadId) {
        form.value.IdUnidadMedida = props.unidadId
        validacionCodigo.value = { valido: true, mensaje: '' }
        validacionDescripcion.value = { valido: true, mensaje: '' }
    }
})

// 🔥 VALIDACIÓN EN TIEMPO REAL - CÓDIGO
const validarCodigo = async () => {
    if (!form.value.Codigo || form.value.Codigo.trim() === '') {
        validacionCodigo.value = { valido: false, mensaje: 'El código es obligatorio' }
        return
    }
    
    try {
        const response = await axios.get('/gestion/inventario/productos-detalle/validar-codigo', {
            params: {
                codigo: form.value.Codigo,
                id: props.editando && props.producto ? props.producto.IdProducto : null
            }
        })
        
        if (response.data.existe) {
            validacionCodigo.value = { 
                valido: false, 
                mensaje: '¡El código ya existe para este cliente!' 
            }
            errors.value.Codigo = ['¡El código ya existe!']
        } else {
            validacionCodigo.value = { valido: true, mensaje: '✓ Código disponible' }
            if (errors.value.Codigo) {
                delete errors.value.Codigo
            }
        }
    } catch (error) {
        console.error('Error validando código:', error)
    }
}

// 🔥 VALIDACIÓN EN TIEMPO REAL - DESCRIPCIÓN
const validarDescripcion = async () => {
    if (!form.value.Descripcion || form.value.Descripcion.trim() === '') {
        validacionDescripcion.value = { valido: false, mensaje: 'La descripción es obligatoria' }
        return
    }
    
    try {
        const response = await axios.get('/gestion/inventario/productos-detalle/validar-descripcion', {
            params: {
                descripcion: form.value.Descripcion,
                id: props.editando && props.producto ? props.producto.IdProducto : null
            }
        })
        
        if (response.data.existe) {
            validacionDescripcion.value = { 
                valido: false, 
                mensaje: '¡La descripción ya existe para este cliente!' 
            }
            errors.value.Descripcion = ['¡La descripción ya existe!']
        } else {
            validacionDescripcion.value = { valido: true, mensaje: '✓ Descripción disponible' }
            if (errors.value.Descripcion) {
                delete errors.value.Descripcion
            }
        }
    } catch (error) {
        console.error('Error validando descripción:', error)
    }
}

// Guardar
const guardar = async () => {
    loading.value = true
    errors.value = {}
    errorMensaje.value = ''
    
    // 🔥 Validar código y descripción antes de guardar
    await validarCodigo()
    await validarDescripcion()
    
    // Validaciones
    if (!form.value.IdGrupoAnalisis) {
        errors.value.IdGrupoAnalisis = ['El grupo es obligatorio']
    }
    if (!form.value.IdLineaProducto) {
        errors.value.IdLineaProducto = ['La línea es obligatoria']
    }
    if (!form.value.IdEstadoProducto) {
        errors.value.IdEstadoProducto = ['El estado es obligatorio']
    }
    if (!form.value.IdUnidadMedida) {
        errors.value.IdUnidadMedida = ['La unidad es obligatoria']
    }
    if (!form.value.Codigo || form.value.Codigo.trim() === '') {
        errors.value.Codigo = ['El código es obligatorio']
    }
    if (!form.value.Descripcion || form.value.Descripcion.trim() === '') {
        errors.value.Descripcion = ['La descripción es obligatoria']
    }
    
    // 🔥 Si hay errores de validación de duplicados, no guardar
    if (!validacionCodigo.value.valido) {
        errors.value.Codigo = ['¡El código ya existe!']
    }
    if (!validacionDescripcion.value.valido) {
        errors.value.Descripcion = ['¡La descripción ya existe!']
    }
    
    if (Object.keys(errors.value).length > 0) {
        loading.value = false
        return
    }
    
    const data = {
        IdGrupoAnalisis: parseInt(form.value.IdGrupoAnalisis),
        IdLineaProducto: parseInt(form.value.IdLineaProducto),
        IdEstadoProducto: parseInt(form.value.IdEstadoProducto),
        IdUnidadMedida: parseInt(form.value.IdUnidadMedida),
        Codigo: form.value.Codigo.trim(),
        Descripcion: form.value.Descripcion.trim(),
        OrdenInformes: parseInt(form.value.OrdenInformes) || 0,
        ActivoInactivo: form.value.ActivoInactivo ? 1 : 0,
    }
    
    try {
        let response
        if (props.editando && props.producto) {
            response = await axios.put(`/gestion/inventario/productos-detalle/${props.producto.IdProducto}`, data)
        } else {
            response = await axios.post('/gestion/inventario/productos-detalle', data)
        }
        
        if (response.data.success) {
            emit('saved')
            cerrarModal()
        } else {
            errorMensaje.value = response.data.message || 'Error al guardar'
        }
    } catch (error) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            const mensajes = Object.values(errors.value).flat()
            errorMensaje.value = mensajes.join(' • ')
        } else if (error.response?.data?.message) {
            errorMensaje.value = error.response.data.message
        } else {
            errorMensaje.value = 'Error al guardar: ' + (error.message || 'Error desconocido')
        }
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="cerrarModal">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                
                <!-- HEADER -->
                <div class="flex justify-between items-center p-4 border-b bg-gray-50">
                    <h2 class="text-base font-bold text-gray-800">{{ tituloModal }}</h2>
                    <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Error general -->
                <div v-if="errorMensaje" class="mx-4 mt-3 p-2 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-600">{{ errorMensaje }}</p>
                </div>

                <!-- FORMULARIO -->
                <div class="p-4 space-y-3">
                    
                    <!-- FILA 1: Grupo Analisis + Linea Producto -->
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Grupo Analisis -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Grupo de Análisis <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdGrupoAnalisis" 
                                    class="w-full border rounded-lg px-2 py-1.5 text-sm"
                                    :class="{ 'border-red-500': errors.IdGrupoAnalisis }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in grupos" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdGrupoAnalisis" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdGrupoAnalisis[0] }}</p>
                        </div>

                        <!-- Linea Producto -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Línea <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdLineaProducto" 
                                    class="w-full border rounded-lg px-2 py-1.5 text-sm"
                                    :class="{ 'border-red-500': errors.IdLineaProducto }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in lineas" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdLineaProducto" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdLineaProducto[0] }}</p>
                        </div>
                    </div>

                    <!-- FILA 2: Estado Producto + Unidad Medida -->
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Estado Producto -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Estado <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdEstadoProducto" 
                                    class="w-full border rounded-lg px-2 py-1.5 text-sm"
                                    :class="{ 'border-red-500': errors.IdEstadoProducto }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in estados" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdEstadoProducto" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdEstadoProducto[0] }}</p>
                        </div>

                        <!-- Unidad Medida -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Unidad <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdUnidadMedida" 
                                    class="w-full border rounded-lg px-2 py-1.5 text-sm"
                                    :class="{ 'border-red-500': errors.IdUnidadMedida }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in unidades" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdUnidadMedida" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdUnidadMedida[0] }}</p>
                            <p v-if="unidadId && form.IdUnidadMedida === unidadId" class="text-[10px] text-green-600 mt-0.5">
                                <i class="fas fa-check-circle"></i> Unidad predefinida
                            </p>
                        </div>
                    </div>

                    <!-- FILA 3: Código -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Código <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Codigo" 
                               @blur="validarCodigo"
                               @change="validarCodigo"
                               @input="validarCodigo"
                               class="w-full border rounded-lg px-3 py-1.5 text-sm uppercase"
                               :class="{ 
                                   'border-red-500': errors.Codigo || !validacionCodigo.valido && validacionCodigo.mensaje,
                                   'border-green-500': validacionCodigo.valido && form.Codigo && form.Codigo.length > 0
                               }"
                               placeholder="CÓDIGO" />
                        <!-- 🔥 Mensaje de validación en tiempo real -->
                        <p v-if="validacionCodigo.mensaje && !validacionCodigo.valido" class="text-[10px] text-red-500 mt-0.5">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            {{ validacionCodigo.mensaje }}
                        </p>
                        <p v-else-if="validacionCodigo.valido && form.Codigo && form.Codigo.length > 0" class="text-[10px] text-green-600 mt-0.5">
                            <i class="fas fa-check-circle mr-1"></i>
                            Código disponible
                        </p>
                        <p v-if="errors.Codigo && !validacionCodigo.mensaje" class="text-[10px] text-red-500 mt-0.5">
                            {{ errors.Codigo[0] }}
                        </p>
                    </div>

                    <!-- FILA 4: Descripción -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Descripción <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Descripcion" 
                               @blur="validarDescripcion"
                               @change="validarDescripcion"
                               @input="validarDescripcion"
                               class="w-full border rounded-lg px-3 py-1.5 text-sm"
                               :class="{ 
                                   'border-red-500': errors.Descripcion || !validacionDescripcion.valido && validacionDescripcion.mensaje,
                                   'border-green-500': validacionDescripcion.valido && form.Descripcion && form.Descripcion.length > 0
                               }"
                               placeholder="DESCRIPCIÓN" />
                        <!-- 🔥 Mensaje de validación en tiempo real -->
                        <p v-if="validacionDescripcion.mensaje && !validacionDescripcion.valido" class="text-[10px] text-red-500 mt-0.5">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            {{ validacionDescripcion.mensaje }}
                        </p>
                        <p v-else-if="validacionDescripcion.valido && form.Descripcion && form.Descripcion.length > 0" class="text-[10px] text-green-600 mt-0.5">
                            <i class="fas fa-check-circle mr-1"></i>
                            Descripción disponible
                        </p>
                        <p v-if="errors.Descripcion && !validacionDescripcion.mensaje" class="text-[10px] text-red-500 mt-0.5">
                            {{ errors.Descripcion[0] }}
                        </p>
                    </div>

                    <!-- FILA 5: Orden Informes -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Orden en Informes</label>
                        <input type="number" v-model.number="form.OrdenInformes" 
                               class="w-full border rounded-lg px-3 py-1.5 text-sm"
                               placeholder="0" min="0" />
                    </div>

                    <!-- FILA 6: Estado Activo/Inactivo -->
                    <div class="border-t pt-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Estado</label>
                                <p class="text-[10px] text-gray-400">0=Activo / 1=Inactivo</p>
                            </div>
                            <button 
                                type="button"
                                @click="form.ActivoInactivo = form.ActivoInactivo === 0 ? 1 : 0"
                                class="relative inline-flex items-center h-7 rounded-full w-14 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                                :class="form.ActivoInactivo === 0 ? 'bg-emerald-600' : 'bg-gray-300'"
                            >
                                <span 
                                    class="inline-block h-5 w-5 transform rounded-full bg-white shadow-lg transition duration-200 flex items-center justify-center"
                                    :class="form.ActivoInactivo === 0 ? 'translate-x-8' : 'translate-x-1'"
                                >
                                    <svg v-if="form.ActivoInactivo === 0" class="h-2.5 w-2.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <svg v-else class="h-2.5 w-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </span>
                            </button>
                        </div>
                        <span class="text-[10px] font-medium px-2 py-0.5 rounded-full mt-1 inline-block"
                              :class="form.ActivoInactivo === 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                            {{ form.ActivoInactivo === 0 ? 'ACTIVO' : 'INACTIVO' }}
                        </span>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="flex justify-end gap-2 p-3 border-t bg-gray-50">
                    <button @click="cerrarModal" 
                            class="px-4 py-1.5 border rounded-lg text-gray-700 text-sm hover:bg-gray-100">
                        Cancelar
                    </button>
                    <button @click="guardar" :disabled="loading" 
                            class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-2">
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ textoBoton }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>