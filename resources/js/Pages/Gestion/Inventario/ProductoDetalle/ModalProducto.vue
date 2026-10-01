<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
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
    },
    // ✅ AHORA: Array de IDs activos: [1, 5, 8, ...]
    gruposConMinimo: {
        type: Array,
        default: () => []
    },
})

const emit = defineEmits(['update:modelValue', 'saved'])

// ==================== ESTADO ====================
const form = ref({
    IdGrupoAnalisis: null,
    IdLineaProducto: null,
    IdEstadoProducto: null,
    IdUnidadMedida: null,
    Codigo: '',
    Descripcion: '',
    OrdenInformes: 0,
    ActivoInactivo: 0,
    CantidadMinimaProducto: 0,
    DisponibleParaPedido: 1,
})

const loading = ref(false)
const errors = ref({})
const errorMensaje = ref('')

const validacionCodigo = ref({ valido: true, mensaje: '' })
const validacionDescripcion = ref({ valido: true, mensaje: '' })

// ==================== COMPUTED ====================
const tituloModal = computed(() => {
    return props.editando ? 'Editar Producto' : 'Nuevo Producto'
})

const textoBoton = computed(() => {
    return loading.value ? 'Guardando...' : (props.editando ? 'Actualizar' : 'Guardar')
})

// ✅ AHORA: gruposConMinimo es un array de IDs
const grupoActualTieneMinimo = computed(() => {
    if (!form.value.IdGrupoAnalisis) return false
    return props.gruposConMinimo.includes(Number(form.value.IdGrupoAnalisis))
})

// ✅ Nombre del grupo seleccionado (para mostrar en la sección)
const nombreGrupoActual = computed(() => {
    if (!form.value.IdGrupoAnalisis) return ''
    const g = props.grupos.find(x => Number(x.id) === Number(form.value.IdGrupoAnalisis))
    return g ? g.nombre : 'Grupo'
})

// ==================== FUNCIONES ====================
const resetForm = () => {
    form.value = {
        IdGrupoAnalisis: null,
        IdLineaProducto: null,
        IdEstadoProducto: null,
        IdUnidadMedida: props.unidadId || null,
        Codigo: '',
        Descripcion: '',
        OrdenInformes: 0,
        ActivoInactivo: 0,
        CantidadMinimaProducto: 0,
        DisponibleParaPedido: 1,
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

const cargarDatosEdicion = () => {
    if (props.producto && props.editando) {
        form.value = {
            IdGrupoAnalisis: props.producto.IdGrupoAnalisis || null,
            IdLineaProducto: props.producto.IdLineaProducto || null,
            IdEstadoProducto: props.producto.IdEstadoProducto || null,
            IdUnidadMedida: props.producto.IdUnidadMedida || props.unidadId || null,
            Codigo: props.producto.Codigo || '',
            Descripcion: props.producto.Descripcion || '',
            OrdenInformes: props.producto.OrdenInformes || 0,
            ActivoInactivo: props.producto.ActivoInactivo ?? 0,
            CantidadMinimaProducto: props.producto.minimo?.CantidadMinimaProducto ?? 0,
            DisponibleParaPedido: props.producto.minimo?.DisponibleParaPedido ?? 1,
        }
    }
}

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
            validacionCodigo.value = { valido: false, mensaje: '¡El código ya existe!' }
            errors.value.Codigo = ['¡El código ya existe!']
        } else {
            validacionCodigo.value = { valido: true, mensaje: '✓ Código disponible' }
            if (errors.value.Codigo) delete errors.value.Codigo
        }
    } catch (error) {
        console.error('Error validando código:', error)
    }
}

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
            validacionDescripcion.value = { valido: false, mensaje: '¡La descripción ya existe!' }
            errors.value.Descripcion = ['¡La descripción ya existe!']
        } else {
            validacionDescripcion.value = { valido: true, mensaje: '✓ Descripción disponible' }
            if (errors.value.Descripcion) delete errors.value.Descripcion
        }
    } catch (error) {
        console.error('Error validando descripción:', error)
    }
}

const guardar = async () => {
    loading.value = true
    errors.value = {}
    errorMensaje.value = ''

    await validarCodigo()
    await validarDescripcion()

    if (!form.value.IdGrupoAnalisis) errors.value.IdGrupoAnalisis = ['El grupo es obligatorio']
    if (!form.value.IdLineaProducto) errors.value.IdLineaProducto = ['La línea es obligatoria']
    if (!form.value.IdEstadoProducto) errors.value.IdEstadoProducto = ['El estado es obligatorio']
    if (!form.value.IdUnidadMedida) errors.value.IdUnidadMedida = ['La unidad es obligatoria']
    if (!form.value.Codigo || form.value.Codigo.trim() === '') errors.value.Codigo = ['El código es obligatorio']
    if (!form.value.Descripcion || form.value.Descripcion.trim() === '') errors.value.Descripcion = ['La descripción es obligatoria']

    if (!validacionCodigo.value.valido) errors.value.Codigo = ['¡El código ya existe!']
    if (!validacionDescripcion.value.valido) errors.value.Descripcion = ['¡La descripción ya existe!']

    // ✅ Si el grupo está ACTIVO, el mínimo es obligatorio
    if (grupoActualTieneMinimo.value) {
        if (!form.value.CantidadMinimaProducto || form.value.CantidadMinimaProducto <= 0) {
            errors.value.CantidadMinimaProducto = ['El mínimo por pedido es obligatorio (debe ser mayor a 0)']
        }
    }

    if (Object.keys(errors.value).length > 0) {
        loading.value = false
        return
    }

    const dataProducto = {
        IdGrupoAnalisis: parseInt(form.value.IdGrupoAnalisis),
        IdLineaProducto: parseInt(form.value.IdLineaProducto),
        IdEstadoProducto: parseInt(form.value.IdEstadoProducto),
        IdUnidadMedida: parseInt(form.value.IdUnidadMedida),
        Codigo: form.value.Codigo.trim(),
        Descripcion: form.value.Descripcion.trim(),
        OrdenInformes: parseInt(form.value.OrdenInformes) || 0,
        ActivoInactivo: form.value.ActivoInactivo ? 1 : 0,
    }

    let idProducto = null
    let productoFueCreado = false

    try {
        let responseProducto
        if (props.editando && props.producto) {
            responseProducto = await axios.put(`/gestion/inventario/productos-detalle/${props.producto.IdProducto}`, dataProducto)
            idProducto = props.producto.IdProducto
        } else {
            responseProducto = await axios.post('/gestion/inventario/productos-detalle', dataProducto)
            idProducto = responseProducto.data.producto.IdProducto
            productoFueCreado = true
        }

        if (!responseProducto.data.success) {
            throw new Error(responseProducto.data.message || 'Error al guardar el producto')
        }

        // ✅ Si el grupo está ACTIVO y hay mínimo, guardar el mínimo del producto
        if (grupoActualTieneMinimo.value && form.value.CantidadMinimaProducto > 0) {
            try {
                await axios.post('/operacion/pedidos/clientes-mayoristas/minimos-globales/producto/guardar', {
                    IdProducto: idProducto,
                    IdGrupoAnalisis: parseInt(form.value.IdGrupoAnalisis),
                    CantidadMinimaProducto: parseFloat(form.value.CantidadMinimaProducto),
                    DisponibleParaPedido: form.value.DisponibleParaPedido ? 1 : 0,
                })
            } catch (errorMinimo) {
                if (productoFueCreado) {
                    try {
                        await axios.delete(`/gestion/inventario/productos-detalle/${idProducto}`)
                    } catch (rollbackError) {
                        console.error('Error en rollback:', rollbackError)
                    }
                }
                throw new Error(
                    'No se pudo configurar el mínimo. ' +
                    (productoFueCreado ? 'El producto no se creó.' : 'Intenta de nuevo.')
                )
            }
        }

        emit('saved')
        cerrarModal()

    } catch (error) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            const mensajes = Object.values(errors.value).flat()
            errorMensaje.value = mensajes.join(' • ')
        } else if (error.response?.data?.message) {
            errorMensaje.value = error.response.data.message
        } else {
            errorMensaje.value = error.message || 'Error al guardar'
        }
    } finally {
        loading.value = false
    }
}

// ==================== WATCHES ====================
watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        if (props.editando && props.producto) {
            cargarDatosEdicion()
        } else {
            resetForm()
            if (props.unidadId) {
                form.value.IdUnidadMedida = props.unidadId
            }
        }
        validacionCodigo.value = { valido: true, mensaje: '' }
        validacionDescripcion.value = { valido: true, mensaje: '' }
    }
}, { immediate: true })

watch(() => props.producto, (newVal) => {
    if (newVal && props.editando && props.modelValue) {
        cargarDatosEdicion()
    }
}, { deep: true })

// ==================== LIFECYCLE ====================
const handleEscape = (event) => {
    if (event.key === 'Escape' && props.modelValue) {
        cerrarModal()
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-3 bg-black/50" @click.self="cerrarModal">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">

                <!-- HEADER -->
                <div class="flex justify-between items-center px-3 py-2 bg-primary-600 rounded-t-xl">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-white/20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-box text-white text-xs"></i>
                        </div>
                        <h2 class="text-sm font-semibold text-white">{{ tituloModal }}</h2>
                    </div>
                    <button @click="cerrarModal" class="text-white/80 hover:text-white transition p-1 rounded text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- ERROR -->
                <div v-if="errorMensaje" class="mx-3 mt-2 p-2 bg-red-50 border border-red-200 rounded-md">
                    <p class="text-[10px] text-red-600 flex items-start gap-1">
                        <i class="fas fa-exclamation-circle mt-0.5 text-[9px]"></i>
                        {{ errorMensaje }}
                    </p>
                </div>

                <!-- FORMULARIO -->
                <div class="flex-1 overflow-y-auto p-3 space-y-2.5">

                    <!-- Grupo + Línea -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                                Grupo Análisis <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdGrupoAnalisis"
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{ 'border-red-500': errors.IdGrupoAnalisis }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in grupos" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdGrupoAnalisis" class="text-[8px] text-red-500 mt-0.5">
                                <i class="fas fa-exclamation-circle mr-0.5"></i>
                                {{ errors.IdGrupoAnalisis[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                                Línea <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdLineaProducto"
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{ 'border-red-500': errors.IdLineaProducto }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in lineas" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdLineaProducto" class="text-[8px] text-red-500 mt-0.5">
                                <i class="fas fa-exclamation-circle mr-0.5"></i>
                                {{ errors.IdLineaProducto[0] }}
                            </p>
                        </div>
                    </div>

                    <!-- Estado + Unidad -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                                Estado <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdEstadoProducto"
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{ 'border-red-500': errors.IdEstadoProducto }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in estados" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdEstadoProducto" class="text-[8px] text-red-500 mt-0.5">
                                <i class="fas fa-exclamation-circle mr-0.5"></i>
                                {{ errors.IdEstadoProducto[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                                Unidad <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.IdUnidadMedida"
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{ 'border-red-500': errors.IdUnidadMedida }">
                                <option :value="null">Seleccionar</option>
                                <option v-for="item in unidades" :key="item.id" :value="item.id">
                                    {{ item.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdUnidadMedida" class="text-[8px] text-red-500 mt-0.5">
                                <i class="fas fa-exclamation-circle mr-0.5"></i>
                                {{ errors.IdUnidadMedida[0] }}
                            </p>
                        </div>
                    </div>

                    <!-- Código -->
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                            Código <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Codigo"
                            @blur="validarCodigo" @change="validarCodigo" @input="validarCodigo"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm uppercase focus:ring-primary-500 focus:border-primary-500 outline-none"
                            :class="{
                                'border-red-500': errors.Codigo || !validacionCodigo.valido && validacionCodigo.mensaje,
                                'border-emerald-500': validacionCodigo.valido && form.Codigo && form.Codigo.length > 0
                            }"
                            placeholder="CÓDIGO" />
                        <p v-if="validacionCodigo.mensaje && !validacionCodigo.valido" class="text-[8px] text-red-500 mt-0.5">
                            <i class="fas fa-exclamation-circle mr-0.5"></i> {{ validacionCodigo.mensaje }}
                        </p>
                        <p v-else-if="validacionCodigo.valido && form.Codigo && form.Codigo.length > 0" class="text-[8px] text-emerald-600 mt-0.5">
                            <i class="fas fa-check-circle mr-0.5"></i> Código disponible
                        </p>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                            Descripción <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Descripcion"
                            @blur="validarDescripcion" @change="validarDescripcion" @input="validarDescripcion"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            :class="{
                                'border-red-500': errors.Descripcion || !validacionDescripcion.valido && validacionDescripcion.mensaje,
                                'border-emerald-500': validacionDescripcion.valido && form.Descripcion && form.Descripcion.length > 0
                            }"
                            placeholder="DESCRIPCIÓN" />
                        <p v-if="validacionDescripcion.mensaje && !validacionDescripcion.valido" class="text-[8px] text-red-500 mt-0.5">
                            <i class="fas fa-exclamation-circle mr-0.5"></i> {{ validacionDescripcion.mensaje }}
                        </p>
                        <p v-else-if="validacionDescripcion.valido && form.Descripcion && form.Descripcion.length > 0" class="text-[8px] text-emerald-600 mt-0.5">
                            <i class="fas fa-check-circle mr-0.5"></i> Descripción disponible
                        </p>
                    </div>

                    <!-- Orden -->
                    <div>
                        <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Orden en Informes</label>
                        <input type="number" v-model.number="form.OrdenInformes" min="0"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            placeholder="0" />
                    </div>

                    <!-- ============================================================ -->
                    <!-- CONFIGURACIÓN PARA PEDIDOS (si el grupo está ACTIVO) -->
                    <!-- ============================================================ -->
                    <div v-if="grupoActualTieneMinimo" class="border border-primary-200 bg-primary-50/50 rounded-md p-2.5 space-y-2.5">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-primary-600 text-white flex items-center justify-center text-[10px] flex-shrink-0">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-[11px] font-bold text-primary-900">Configuración para Pedidos</h3>
                                <p class="text-[10px] text-blue-700">
                                    El grupo "<strong>{{ nombreGrupoActual }}</strong>" está <strong>ACTIVO</strong>.
                                    Configura el mínimo individual.
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] text-gray-600 font-medium block mb-0.5">
                                Mínimo por pedido <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="number"
                                    v-model.number="form.CantidadMinimaProducto"
                                    min="0.01" step="1" placeholder="0"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-10 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    :class="{ 'border-red-500': errors.CantidadMinimaProducto }" />
                                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400">und</span>
                            </div>
                            <p v-if="errors.CantidadMinimaProducto" class="text-[8px] text-red-500 mt-0.5">
                                <i class="fas fa-exclamation-circle mr-0.5"></i> {{ errors.CantidadMinimaProducto[0] }}
                            </p>
                            <p v-else class="text-[8px] text-gray-500 mt-0.5">
                                <i class="fas fa-info-circle mr-0.5"></i> Cantidad mínima que debe pedir el cliente.
                            </p>
                        </div>

                        <div class="border-t border-primary-200 pt-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <label class="text-[10px] text-gray-600 font-medium block">Disponible para pedidos</label>
                                    <p class="text-[8px] text-gray-500">
                                        {{ form.DisponibleParaPedido ? 'El producto se muestra al operador.' : 'Producto pausado.' }}
                                    </p>
                                </div>
                                <button type="button"
                                    @click="form.DisponibleParaPedido = form.DisponibleParaPedido ? 0 : 1"
                                    class="relative inline-flex items-center h-6 rounded-full w-12 transition-colors duration-200 focus:outline-none flex-shrink-0"
                                    :class="form.DisponibleParaPedido ? 'bg-primary-600' : 'bg-gray-300'">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200 flex items-center justify-center"
                                        :class="form.DisponibleParaPedido ? 'translate-x-7' : 'translate-x-1'">
                                        <i v-if="form.DisponibleParaPedido" class="fas fa-check text-[6px] text-primary-600"></i>
                                        <i v-else class="fas fa-times text-[6px] text-gray-400"></i>
                                    </span>
                                </button>
                            </div>
                            <span class="text-[8px] font-medium px-1.5 py-0.5 rounded-full mt-1 inline-block"
                                :class="form.DisponibleParaPedido ? 'bg-primary-100 text-primary-700' : 'bg-yellow-100 text-yellow-700'">
                                {{ form.DisponibleParaPedido ? 'DISPONIBLE' : 'PAUSADO' }}
                            </span>
                        </div>
                    </div>

                    <!-- Estado Activo/Inactivo -->
                    <div class="border-t border-gray-200 pt-2">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <label class="text-[10px] text-gray-600 font-medium block">Estado del producto</label>
                                <p class="text-[8px] text-gray-400">0=Activo / 1=Inactivo</p>
                            </div>
                            <button type="button"
                                @click="form.ActivoInactivo = form.ActivoInactivo === 0 ? 1 : 0"
                                class="relative inline-flex items-center h-6 rounded-full w-12 transition-colors duration-200 focus:outline-none flex-shrink-0"
                                :class="form.ActivoInactivo === 0 ? 'bg-emerald-600' : 'bg-gray-300'">
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200 flex items-center justify-center"
                                    :class="form.ActivoInactivo === 0 ? 'translate-x-7' : 'translate-x-1'">
                                    <i v-if="form.ActivoInactivo === 0" class="fas fa-check text-[6px] text-emerald-600"></i>
                                    <i v-else class="fas fa-times text-[6px] text-gray-400"></i>
                                </span>
                            </button>
                        </div>
                        <span class="text-[8px] font-medium px-1.5 py-0.5 rounded-full mt-1 inline-block"
                            :class="form.ActivoInactivo === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                            {{ form.ActivoInactivo === 0 ? 'ACTIVO' : 'INACTIVO' }}
                        </span>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="flex justify-end gap-2 p-3 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                    <button @click="cerrarModal"
                        class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button @click="guardar" :disabled="loading"
                        class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 disabled:opacity-50 flex items-center gap-1.5 transition">
                        <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-save text-[10px]"></i>
                        {{ textoBoton }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

/* Quitar flechas de inputs number */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
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