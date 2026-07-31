<!-- resources/js/Pages/Gestion/Inventario/ProductoDetalle/ModalCrearInsumo.vue -->

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    grupos: {
        type: Array,
        default: () => []
    },
    lineas: {
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
    }
})

const emit = defineEmits(['update:modelValue', 'saved'])

// ==================== ESTADO ====================
const loading = ref(false)
const errors = ref({})
const errorMensaje = ref('')
const estados = ref([])
const idEstadoInsumos = ref(null)
const nombreEstadoInsumos = ref('Insumos')
const cargandoEstado = ref(false)

const form = ref({
    IdGrupoAnalisis: null,
    IdLineaProducto: null,
    IdEstadoProducto: null,
    IdUnidadMedida: props.unidadId || null,
    Codigo: '',
    Descripcion: '',
    OrdenInformes: 0,
    ActivoInactivo: 0,
})

// ==================== COMPUTED ====================
const tituloModal = computed(() => 'Nuevo Insumo')
const textoBoton = computed(() => loading.value ? 'Guardando...' : 'Guardar Insumo')

// ==================== FUNCIONES ====================
const resetForm = () => {
    form.value = {
        IdGrupoAnalisis: null,
        IdLineaProducto: null,
        IdEstadoProducto: idEstadoInsumos.value || null,
        IdUnidadMedida: props.unidadId || null,
        Codigo: '',
        Descripcion: '',
        OrdenInformes: 0,
        ActivoInactivo: 0,
    }
    errors.value = {}
    errorMensaje.value = ''
}

const cerrarModal = () => {
    emit('update:modelValue', false)
    resetForm()
}

// 🔥 Obtener el ID del estado "Insumos" desde el backend
const obtenerEstadoInsumos = async () => {
    if (cargandoEstado.value) return
    
    cargandoEstado.value = true
    try {
        const response = await axios.get('/gestion/inventario/ficha-tecnica/estado-insumos')
        console.log('📡 Respuesta estado Insumos:', response.data)
        
        if (response.data.success && response.data.id) {
            idEstadoInsumos.value = response.data.id
            nombreEstadoInsumos.value = response.data.nombre || 'Insumos'
            form.value.IdEstadoProducto = idEstadoInsumos.value
            console.log(`✅ Estado Insumos cargado: ID=${idEstadoInsumos.value}, Nombre=${nombreEstadoInsumos.value}`)
        } else {
            console.warn('⚠️ No se encontró el estado Insumos:', response.data.message)
            errorMensaje.value = response.data.message || 'No se encontró el estado "Insumos" para este cliente'
            idEstadoInsumos.value = null
        }
    } catch (error) {
        console.error('❌ Error obteniendo estado Insumos:', error)
        errorMensaje.value = 'Error al obtener el estado "Insumos". Por favor recargue la página.'
        idEstadoInsumos.value = null
    } finally {
        cargandoEstado.value = false
    }
}

const abrirModal = async () => {
    await obtenerEstadoInsumos()
    resetForm()
    if (props.unidadId) {
        form.value.IdUnidadMedida = props.unidadId
    }
}

// Cargar estados (para mostrar en el select bloqueado)
const cargarEstados = async () => {
    try {
        const response = await axios.get('/gestion/inventario/productos-detalle/estados')
        if (response.data) {
            estados.value = response.data
        }
    } catch (error) {
        console.error('Error cargando estados:', error)
    }
}

const guardar = async () => {
    loading.value = true
    errors.value = {}
    errorMensaje.value = ''
    
    // 🔥 Validar que tenemos el ID del estado Insumos
    if (!idEstadoInsumos.value) {
        await obtenerEstadoInsumos()
        if (!idEstadoInsumos.value) {
            errorMensaje.value = 'No se puede crear el insumo: Estado "Insumos" no disponible'
            loading.value = false
            return
        }
    }
    
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
    
    if (Object.keys(errors.value).length > 0) {
        loading.value = false
        return
    }
    
    const data = {
        IdGrupoAnalisis: parseInt(form.value.IdGrupoAnalisis),
        IdLineaProducto: parseInt(form.value.IdLineaProducto),
        IdEstadoProducto: parseInt(form.value.IdEstadoProducto),
        IdUnidadMedida: parseInt(form.value.IdUnidadMedida),
        Codigo: form.value.Codigo.trim().toUpperCase(),
        Descripcion: form.value.Descripcion.trim(),
        OrdenInformes: parseInt(form.value.OrdenInformes) || 0,
        ActivoInactivo: form.value.ActivoInactivo ? 1 : 0,
    }
    
    try {
        const response = await axios.post('/gestion/inventario/productos-detalle', data)
        
        if (response.data.success) {
            emit('saved', response.data.producto)
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

// ==================== WATCHES ====================
watch(() => props.modelValue, async (newVal) => {
    if (newVal) {
        await abrirModal()
    }
})

// ==================== LIFECYCLE ====================
onMounted(async () => {
    await cargarEstados()
    await obtenerEstadoInsumos()
})
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" @click.self="cerrarModal">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                
                <!-- HEADER -->
                <div class="flex justify-between items-center p-4 border-b bg-primary-50/70 rounded-t-2xl">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-primary-100 text-primary-700 flex items-center justify-center text-lg">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-primary-800">{{ tituloModal }}</h2>
                            <p class="text-[10px] text-primary-600">Crea un nuevo producto con estado <span class="font-semibold">Insumos</span></p>
                        </div>
                    </div>
                    <button @click="cerrarModal" class="text-primary-400 hover:text-primary-600 hover:bg-primary-100 rounded-lg p-1.5 transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Error -->
                <div v-if="errorMensaje" class="mx-4 mt-3 p-2 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-600">{{ errorMensaje }}</p>
                </div>

                <!-- FORMULARIO -->
                <div class="p-4 space-y-3">
                    
                    <!-- FILA 1: Grupo + Línea -->
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Grupo Analisis -->
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Grupo de Análisis <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdGrupoAnalisis" 
                                    class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition"
                                    :class="{ 'border-red-500': errors.IdGrupoAnalisis }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in grupos" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdGrupoAnalisis" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdGrupoAnalisis[0] }}</p>
                        </div>

                        <!-- Línea -->
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Línea <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdLineaProducto" 
                                    class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition"
                                    :class="{ 'border-red-500': errors.IdLineaProducto }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in lineas" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdLineaProducto" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdLineaProducto[0] }}</p>
                        </div>
                    </div>

                    <!-- FILA 2: Estado + Unidad -->
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Estado Producto - Bloqueado como INSUMOS -->
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Estado <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select v-model="form.IdEstadoProducto" 
                                        disabled
                                        class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-sm bg-slate-100 cursor-not-allowed text-slate-700 font-medium"
                                        :class="{ 'border-red-500': errors.IdEstadoProducto }">
                                    <option :value="null">Seleccionar</option>
                                    <option v-for="item in estados" :key="item.id" :value="item.id">
                                        {{ item.nombre }}
                                    </option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <i class="fas fa-lock text-slate-400 text-xs"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-emerald-600 mt-0.5 flex items-center gap-1">
                                <i class="fas fa-check-circle"></i> 
                                <span v-if="idEstadoInsumos">Fijo como <strong>{{ nombreEstadoInsumos }}</strong></span>
                                <span v-else>Cargando estado...</span>
                            </p>
                            <p v-if="errors.IdEstadoProducto" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdEstadoProducto[0] }}</p>
                        </div>

                        <!-- Unidad Medida -->
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Unidad <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdUnidadMedida" 
                                    class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition"
                                    :class="{ 'border-red-500': errors.IdUnidadMedida }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in unidades" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdUnidadMedida" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdUnidadMedida[0] }}</p>
                            <p v-if="unidadId && form.IdUnidadMedida === unidadId" class="text-[10px] text-emerald-600 mt-0.5">
                                <i class="fas fa-check-circle"></i> Unidad predefinida
                            </p>
                        </div>
                    </div>

                    <!-- Código -->
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">
                            Código <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Codigo" 
                               class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-sm uppercase focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition"
                               :class="{ 'border-red-500': errors.Codigo }"
                               placeholder="CÓDIGO" />
                        <p v-if="errors.Codigo" class="text-[10px] text-red-500 mt-0.5">{{ errors.Codigo[0] }}</p>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">
                            Descripción <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Descripcion" 
                               class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition"
                               :class="{ 'border-red-500': errors.Descripcion }"
                               placeholder="DESCRIPCIÓN" />
                        <p v-if="errors.Descripcion" class="text-[10px] text-red-500 mt-0.5">{{ errors.Descripcion[0] }}</p>
                    </div>

                    <!-- Orden en Informes -->
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Orden en Informes</label>
                        <input type="number" v-model.number="form.OrdenInformes" 
                               class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition"
                               placeholder="0" min="0" />
                    </div>

                    <!-- Estado Activo/Inactivo -->
                    <div class="border-t pt-3 border-slate-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-medium text-slate-700">Estado</label>
                                <p class="text-[10px] text-slate-400">0=Activo / 1=Inactivo</p>
                            </div>
                            <button 
                                type="button"
                                @click="form.ActivoInactivo = form.ActivoInactivo === 0 ? 1 : 0"
                                class="relative inline-flex items-center h-7 rounded-full w-14 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                                :class="form.ActivoInactivo === 0 ? 'bg-emerald-600' : 'bg-slate-300'"
                            >
                                <span 
                                    class="inline-block h-5 w-5 transform rounded-full bg-white shadow-lg transition duration-200 flex items-center justify-center"
                                    :class="form.ActivoInactivo === 0 ? 'translate-x-8' : 'translate-x-1'"
                                >
                                    <svg v-if="form.ActivoInactivo === 0" class="h-2.5 w-2.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <svg v-else class="h-2.5 w-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <div class="flex justify-end gap-2 p-3 border-t bg-slate-50/80 rounded-b-2xl">
                    <button @click="cerrarModal" 
                            class="px-4 py-1.5 border border-slate-300 rounded-lg text-slate-700 text-sm hover:bg-slate-100 transition">
                        Cancelar
                    </button>
                    <button @click="guardar" :disabled="loading" 
                            class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-2 transition">
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ textoBoton }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
input:focus, select:focus {
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}
</style>