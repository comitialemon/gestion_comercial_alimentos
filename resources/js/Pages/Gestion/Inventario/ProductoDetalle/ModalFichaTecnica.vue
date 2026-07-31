<!-- resources/js/Pages/Gestion/Inventario/ProductoDetalle/ModalFichaTecnica.vue -->

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import axios from 'axios'
import ModalCrearInsumo from './ModalCrearInsumo.vue'

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
const guardando = ref(false)
const fichaExistente = ref(false)
const fichaId = ref(null)
const errors = ref({})
const errorMensaje = ref('')
const cargandoUnidades = ref(false)
const buscandoInsumos = ref(false)

// Datos de la ficha
const form = ref({
    IdProductoTerminado: null,
    CantidadProduccion: null,
    IdUnidadMedidaProducto: null,
    detalles: []
})

// Insumos
const insumosDisponibles = ref([])
const busquedaInsumo = ref('')
const insumoSeleccionado = ref(null)
const cantidadInsumo = ref(1)
const unidadInsumoSeleccionada = ref(null)
const showInsumoDropdown = ref(false)

// Unidades de medida
const unidadesMedida = ref([])

// ==================== MODAL CREAR INSUMO ====================
const modalInsumoOpen = ref(false)

// ==================== COMPUTED ====================
const tituloModal = computed(() => {
    return props.producto ? `Ficha Técnica: ${props.producto.Descripcion}` : 'Ficha Técnica'
})

const totalInsumos = computed(() => form.value.detalles.length)
const tieneDetalles = computed(() => form.value.detalles.length > 0)

// ==================== FUNCIONES ====================
const resetForm = () => {
    form.value = {
        IdProductoTerminado: props.producto?.IdProducto || null,
        CantidadProduccion: null,
        IdUnidadMedidaProducto: props.producto?.IdUnidadMedida || null,
        detalles: []
    }
    fichaExistente.value = false
    fichaId.value = null
    errors.value = {}
    errorMensaje.value = ''
    insumoSeleccionado.value = null
    cantidadInsumo.value = 1
    unidadInsumoSeleccionada.value = null
    busquedaInsumo.value = ''
    insumosDisponibles.value = []
}

const cerrarModal = () => {
    emit('update:modelValue', false)
    resetForm()
}

// ==================== FUNCIÓN PARA CUANDO SE CREA UN INSUMO ====================
const onInsumoCreado = (nuevoInsumo) => {
    console.log('✅ Insumo creado:', nuevoInsumo)
    // Recargar la lista de insumos disponibles
    cargarInsumos()
    // Seleccionar automáticamente el nuevo insumo
    if (nuevoInsumo) {
        insumoSeleccionado.value = {
            IdProducto: nuevoInsumo.IdProducto,
            Codigo: nuevoInsumo.Codigo,
            Descripcion: nuevoInsumo.Descripcion
        }
        busquedaInsumo.value = `${nuevoInsumo.Codigo} - ${nuevoInsumo.Descripcion}`
        // Cerrar el modal de insumo
        modalInsumoOpen.value = false
    }
}

// Cargar unidades de medida
const cargarUnidades = async () => {
    if (unidadesMedida.value.length > 0) return
    
    cargandoUnidades.value = true
    try {
        const response = await axios.get('/api/unidades-medida')
        if (response.data && Array.isArray(response.data)) {
            unidadesMedida.value = response.data
        }
    } catch (error) {
        console.error('Error cargando unidades:', error)
        unidadesMedida.value = [
            { IdUnidadMedida: 1, UnidadMedida: 'Unidad' },
            { IdUnidadMedida: 2, UnidadMedida: 'Kilogramo' },
            { IdUnidadMedida: 3, UnidadMedida: 'Gramo' },
            { IdUnidadMedida: 4, UnidadMedida: 'Litro' }
        ]
    } finally {
        cargandoUnidades.value = false
    }
}

// Cargar insumos disponibles
const cargarInsumos = async (search = '') => {
    buscandoInsumos.value = true
    try {
        const response = await axios.get('/gestion/inventario/ficha-tecnica/insumos', {
            params: { search: search }
        })
        if (response.data.success) {
            insumosDisponibles.value = response.data.insumos || []
        } else {
            insumosDisponibles.value = []
        }
    } catch (error) {
        console.error('Error cargando insumos:', error)
        insumosDisponibles.value = []
    } finally {
        buscandoInsumos.value = false
    }
}

// Buscar insumos con debounce
let timeoutInsumos
const buscarInsumos = () => {
    clearTimeout(timeoutInsumos)
    timeoutInsumos = setTimeout(() => {
        if (busquedaInsumo.value.length >= 2) {
            cargarInsumos(busquedaInsumo.value)
            showInsumoDropdown.value = true
        } else {
            insumosDisponibles.value = []
            showInsumoDropdown.value = false
        }
    }, 300)
}

const seleccionarInsumo = (insumo) => {
    insumoSeleccionado.value = insumo
    busquedaInsumo.value = `${insumo.Codigo} - ${insumo.Descripcion}`
    showInsumoDropdown.value = false
}

const limpiarInsumoSeleccionado = () => {
    insumoSeleccionado.value = null
    busquedaInsumo.value = ''
    showInsumoDropdown.value = false
}

// Agregar insumo a la lista
const agregarInsumo = () => {
    if (!insumoSeleccionado.value) {
        errors.value.insumo = 'Seleccione un insumo'
        return
    }
    
    if (!cantidadInsumo.value || cantidadInsumo.value <= 0) {
        errors.value.cantidad = 'Ingrese una cantidad válida'
        return
    }
    
    if (!unidadInsumoSeleccionada.value) {
        errors.value.unidad = 'Seleccione unidad'
        return
    }
    
    const existe = form.value.detalles.some(d => d.IdProductoInsumo === insumoSeleccionado.value.IdProducto)
    if (existe) {
        errors.value.insumo = 'Insumo ya agregado'
        return
    }
    
    form.value.detalles.push({
        IdProductoInsumo: insumoSeleccionado.value.IdProducto,
        Codigo: insumoSeleccionado.value.Codigo,
        Descripcion: insumoSeleccionado.value.Descripcion,
        IdUnidadMedida: unidadInsumoSeleccionada.value,
        Unidades: cantidadInsumo.value,
        Orden: form.value.detalles.length + 1
    })
    
    insumoSeleccionado.value = null
    busquedaInsumo.value = ''
    cantidadInsumo.value = 1
    unidadInsumoSeleccionada.value = null
    errors.value = {}
}

// Eliminar insumo de la lista
const eliminarInsumo = (index) => {
    form.value.detalles.splice(index, 1)
    form.value.detalles.forEach((d, i) => {
        d.Orden = i + 1
    })
}

// Cargar ficha existente
const cargarFichaExistente = async () => {
    if (!props.producto?.IdProducto) return
    
    loading.value = true
    try {
        const response = await axios.get(`/gestion/inventario/ficha-tecnica/producto/${props.producto.IdProducto}`)
        if (response.data.success && response.data.existe) {
            const ficha = response.data.ficha
            fichaExistente.value = true
            fichaId.value = ficha.IdFicha
            
            form.value.CantidadProduccion = ficha.CantidadProduccion || null
            form.value.IdUnidadMedidaProducto = ficha.IdUnidadMedidaProducto || props.producto?.IdUnidadMedida
            
            if (response.data.detalles && response.data.detalles.length > 0) {
                form.value.detalles = response.data.detalles.map(d => ({
                    IdFichaDetalle: d.IdFichaDetalle,
                    IdProductoInsumo: d.IdProductoInsumo,
                    Codigo: d.insumo?.Codigo || '',
                    Descripcion: d.insumo?.Descripcion || '',
                    IdUnidadMedida: d.IdUnidadMedida,
                    Unidades: d.Unidades,
                    Orden: d.Orden || 0
                }))
            }
        }
    } catch (error) {
        console.error('Error cargando ficha:', error)
        fichaExistente.value = false
    } finally {
        loading.value = false
    }
}

// Guardar ficha
const guardarFicha = async () => {
    if (!form.value.CantidadProduccion || form.value.CantidadProduccion <= 0) {
        errors.value.CantidadProduccion = 'Ingrese una cantidad mayor a 0'
        return
    }
    
    if (!form.value.IdUnidadMedidaProducto) {
        errors.value.IdUnidadMedidaProducto = 'Seleccione una unidad'
        return
    }
    
    if (form.value.detalles.length === 0) {
        errors.value.detalles = 'Agregue al menos un insumo a la ficha'
        return
    }
    
    guardando.value = true
    errors.value = {}
    errorMensaje.value = ''
    
    try {
        const data = {
            IdProductoTerminado: props.producto.IdProducto,
            IdLineaProducto: props.producto.IdLineaProducto || 1,
            CantidadProduccion: form.value.CantidadProduccion,
            IdUnidadMedidaProducto: form.value.IdUnidadMedidaProducto,
            detalles: form.value.detalles.map(d => ({
                IdProductoInsumo: d.IdProductoInsumo,
                IdUnidadMedida: d.IdUnidadMedida,
                Unidades: d.Unidades,
                Orden: d.Orden || 0
            }))
        }
        
        const response = await axios.post('/gestion/inventario/ficha-tecnica/guardar', data)
        
        if (response.data.success) {
            emit('saved')
            cerrarModal()
        } else {
            errorMensaje.value = response.data.message || 'Error al guardar'
        }
    } catch (error) {
        console.error('Error guardando ficha:', error)
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
        } else if (error.response?.data?.message) {
            errorMensaje.value = error.response.data.message
        } else {
            errorMensaje.value = 'Error al guardar: ' + (error.message || 'Error desconocido')
        }
    } finally {
        guardando.value = false
    }
}

// ==================== WATCHES ====================
watch(() => props.modelValue, async (newVal) => {
    if (newVal && props.producto) {
        resetForm()
        await cargarUnidades()
        await cargarFichaExistente()
        await cargarInsumos()
    }
})

// ==================== LIFECYCLE ====================
onMounted(async () => {
    await cargarUnidades()
})
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm transition-all" @click.self="cerrarModal">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-100">
                
                <!-- HEADER -->
                <div class="px-6 py-4 border-b border-primary-100 bg-primary-50/70 flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary-100 text-primary-700 flex items-center justify-center font-bold text-lg shadow-sm border border-primary-200">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-primary-800 leading-tight">{{ tituloModal }}</h2>
                            <p v-if="producto" class="text-xs text-primary-600 mt-0.5 flex items-center gap-2">
                                <span>SKU: <strong class="font-mono text-primary-700">{{ producto.Codigo }}</strong></span>
                                <span class="text-primary-300">•</span>
                                <span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-primary-100 text-primary-700">
                                    {{ producto.estado?.Estado || 'Activo' }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <button @click="cerrarModal" class="w-8 h-8 rounded-lg text-primary-400 hover:text-primary-600 hover:bg-primary-100 transition flex items-center justify-center">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="p-12 text-center flex-1 flex flex-col items-center justify-center">
                    <i class="fas fa-spinner fa-spin text-3xl text-primary-600 mb-3"></i>
                    <p class="text-sm font-medium text-slate-600">Cargando ficha técnica...</p>
                </div>

                <!-- CONTENIDO PRINCIPAL -->
                <div v-else class="p-6 space-y-6 overflow-y-auto flex-1">
                    
                    <!-- Alert Error -->
                    <div v-if="errorMensaje" class="p-3 bg-red-50/80 border border-red-200 rounded-xl flex items-center gap-3 text-red-700 text-xs">
                        <i class="fas fa-exclamation-circle text-base text-red-500 shrink-0"></i>
                        <span>{{ errorMensaje }}</span>
                    </div>

                    <!-- 📋 SECCIÓN 1: CABECERA -->
                    <div class="bg-primary-50/40 p-4 rounded-xl border border-primary-200/60">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-cog text-primary-600 text-xs"></i>
                                <h3 class="text-xs font-bold text-primary-700 uppercase tracking-wider">Configuración de Producción</h3>
                            </div>
                            <span class="text-[10px] font-semibold bg-primary-100 text-primary-700 px-2.5 py-0.5 rounded-full border border-primary-200">
                                Base de Receta
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Cantidad -->
                            <div>
                                <label class="block text-xs font-medium text-primary-700 mb-1">
                                    Rendimiento / Lote <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="number" v-model.number="form.CantidadProduccion" 
                                           step="0.01" min="0.01"
                                           class="w-full border border-primary-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition shadow-sm bg-white"
                                           :class="{ 'border-red-400 focus:ring-red-200': errors.CantidadProduccion }"
                                           placeholder="Ej: 100" />
                                </div>
                                <p v-if="errors.CantidadProduccion" class="text-[11px] text-red-500 mt-1 flex items-center gap-1">
                                    <i class="fas fa-info-circle"></i> {{ errors.CantidadProduccion }}
                                </p>
                            </div>

                            <!-- Unidad de Medida Producto -->
                            <div>
                                <label class="block text-xs font-medium text-primary-700 mb-1">
                                    Unidad de Medida Producto <span class="text-red-500">*</span>
                                </label>
                                <select v-model="form.IdUnidadMedidaProducto" 
                                        class="w-full border border-primary-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition shadow-sm bg-white"
                                        :class="{ 'border-red-400 focus:ring-red-200': errors.IdUnidadMedidaProducto }">
                                    <option :value="null">Seleccionar unidad</option>
                                    <option v-for="item in unidadesMedida" :key="item.IdUnidadMedida" :value="item.IdUnidadMedida">
                                        {{ item.UnidadMedida }}
                                    </option>
                                </select>
                                <p v-if="errors.IdUnidadMedidaProducto" class="text-[11px] text-red-500 mt-1 flex items-center gap-1">
                                    <i class="fas fa-info-circle"></i> {{ errors.IdUnidadMedidaProducto }}
                                </p>
                            </div>
                        </div>

                        <!-- Badge Resumen -->
                        <div v-if="form.CantidadProduccion && form.CantidadProduccion > 0" class="mt-3 pt-3 border-t border-primary-200/60 flex items-center gap-2 text-xs text-primary-600">
                            <i class="fas fa-info-circle text-primary-500"></i>
                            <span>Esta receta produce <strong>{{ Number(form.CantidadProduccion).toFixed(2) }} {{ unidadesMedida.find(u => u.IdUnidadMedida === form.IdUnidadMedidaProducto)?.UnidadMedida || '' }}</strong> de {{ producto?.Descripcion }}.</span>
                        </div>
                    </div>

                    <!-- 📦 SECCIÓN 2: INSUMOS Y MATERIALES -->
                    <div>
                        <!-- TÍTULO CON BOTÓN AGREGAR INSUMO -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-cubes text-amber-600 text-xs"></i>
                                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Insumos y Materiales</h3>
                                <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">{{ totalInsumos }}</span>
                            </div>
                            <button 
                                @click="modalInsumoOpen = true"
                                type="button"
                                class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white text-[10px] font-semibold rounded-lg transition flex items-center gap-1.5 shadow-sm shadow-primary-600/20 active:scale-95"
                            >
                                <i class="fas fa-plus text-[9px]"></i>
                                Nuevo Insumo
                            </button>
                        </div>

                        <!-- FORMULARIO FILA ÚNICA -->
                        <div class="bg-primary-50/30 p-3 rounded-xl border border-primary-200/50 shadow-sm">
                            <div class="grid grid-cols-12 gap-2 items-end">
                                
                                <!-- 1. Buscador Insumo (5 col) -->
                                <div class="col-span-12 sm:col-span-5 relative">
                                    <label class="block text-[10px] font-semibold text-primary-600 mb-1 uppercase tracking-wider">Insumo</label>
                                    <div class="relative">
                                        <input type="text" 
                                               v-model="busquedaInsumo" 
                                               @input="buscarInsumos"
                                               @focus="showInsumoDropdown = true"
                                               class="w-full border border-primary-200 rounded-lg pl-8 pr-7 py-1.5 text-xs focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition bg-white"
                                               :class="{ 'border-red-400': errors.insumo }"
                                               placeholder="Buscar por código o nombre..." />
                                        <i class="fas fa-search absolute left-2.5 top-2.5 text-primary-400 text-xs"></i>
                                        <button v-if="busquedaInsumo" @click="limpiarInsumoSeleccionado" type="button" class="absolute right-2 top-2 text-primary-400 hover:text-primary-600 text-xs">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    </div>

                                    <!-- Autocomplete Dropdown -->
                                    <div v-if="showInsumoDropdown && insumosDisponibles.length > 0" 
                                         class="absolute z-30 mt-1 w-full bg-white border border-primary-200 rounded-lg shadow-xl max-h-48 overflow-y-auto divide-y divide-primary-50">
                                        <div v-for="insumo in insumosDisponibles" :key="insumo.IdProducto"
                                             @click="seleccionarInsumo(insumo)"
                                             class="px-3 py-2 hover:bg-primary-50 cursor-pointer text-xs flex items-center justify-between transition">
                                            <div class="truncate pr-2">
                                                <span class="font-semibold text-slate-800">{{ insumo.Descripcion }}</span>
                                            </div>
                                            <span class="font-mono text-[10px] bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded shrink-0">{{ insumo.Codigo }}</span>
                                        </div>
                                    </div>
                                    <div v-if="showInsumoDropdown && busquedaInsumo.length >= 2 && insumosDisponibles.length === 0 && !buscandoInsumos"
                                         class="absolute z-30 mt-1 w-full bg-white border border-primary-200 rounded-lg shadow-lg p-3 text-center text-primary-400 text-xs">
                                        No se encontraron insumos
                                    </div>
                                    <p v-if="errors.insumo" class="text-[10px] text-red-500 mt-0.5">{{ errors.insumo }}</p>
                                </div>

                                <!-- 2. Cantidad (3 col) - REDUCIDA -->
                                <div class="col-span-4 sm:col-span-2">
                                    <label class="block text-[10px] font-semibold text-primary-600 mb-1 uppercase tracking-wider">Cantidad</label>
                                    <input type="number" v-model.number="cantidadInsumo" 
                                           step="0.0001" min="0.0001"
                                           class="no-arrows w-full border border-primary-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition bg-white text-center"
                                           :class="{ 'border-red-400': errors.cantidad }"
                                           placeholder="0.0000" />
                                    <p v-if="errors.cantidad" class="text-[10px] text-red-500 mt-0.5">{{ errors.cantidad }}</p>
                                </div>

                                <!-- 3. Unidad (3 col) -->
                                <div class="col-span-4 sm:col-span-2">
                                    <label class="block text-[10px] font-semibold text-primary-600 mb-1 uppercase tracking-wider">Unidad</label>
                                    <select v-model="unidadInsumoSeleccionada" 
                                            class="w-full border border-primary-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition bg-white"
                                            :class="{ 'border-red-400': errors.unidad }">
                                        <option :value="null">Seleccionar</option>
                                        <option v-for="item in unidadesMedida" :key="item.IdUnidadMedida" :value="item.IdUnidadMedida">
                                            {{ item.UnidadMedida }}
                                        </option>
                                    </select>
                                    <p v-if="errors.unidad" class="text-[10px] text-red-500 mt-0.5">{{ errors.unidad }}</p>
                                </div>

                                <!-- 4. Botón Agregar (2 col) -->
                                <div class="col-span-4 sm:col-span-1">
                                    <button @click="agregarInsumo" 
                                            type="button"
                                            class="w-full h-[34px] bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition shadow-sm flex items-center justify-center gap-1.5 active:scale-95"
                                            title="Agregar Insumo">
                                        <i class="fas fa-plus text-[10px]"></i>
                                        <span class="hidden sm:inline">Agregar</span>
                                    </button>
                                </div>

                            </div>
                        </div>

                        <!-- TABLA DE INSUMOS DETALLE -->
                        <div v-if="tieneDetalles" class="mt-4 border border-primary-200 rounded-xl overflow-hidden shadow-sm">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-primary-50/80 border-b border-primary-200">
                                    <tr>
                                        <th class="px-3 py-2 text-[10px] font-bold text-primary-600 uppercase tracking-wider w-10 text-center">#</th>
                                        <th class="px-3 py-2 text-[10px] font-bold text-primary-600 uppercase tracking-wider w-24">Código</th>
                                        <th class="px-3 py-2 text-[10px] font-bold text-primary-600 uppercase tracking-wider">Descripción Insumo</th>
                                        <th class="px-3 py-2 text-[10px] font-bold text-primary-600 uppercase tracking-wider text-right w-24">Cantidad</th>
                                        <th class="px-3 py-2 text-[10px] font-bold text-primary-600 uppercase tracking-wider w-24">Unidad</th>
                                        <th class="px-3 py-2 text-[10px] font-bold text-primary-600 uppercase tracking-wider w-12 text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-primary-100 bg-white">
                                    <tr v-for="(detalle, index) in form.detalles" :key="index" class="hover:bg-primary-50/30 transition-colors">
                                        <td class="px-3 py-2 text-xs text-primary-400 font-medium text-center">{{ detalle.Orden || index + 1 }}</td>
                                        <td class="px-3 py-2 text-xs font-mono font-medium text-primary-600">{{ detalle.Codigo }}</td>
                                        <td class="px-3 py-2 text-xs font-semibold text-slate-800">{{ detalle.Descripcion }}</td>
                                        <td class="px-3 py-2 text-xs font-mono font-semibold text-primary-700 text-right bg-primary-50/30">
                                            {{ Number(detalle.Unidades).toFixed(4) }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-primary-600">
                                            <span class="inline-block px-2 py-0.5 bg-primary-100 rounded text-[11px] font-medium text-primary-700">
                                                {{ unidadesMedida.find(u => u.IdUnidadMedida === detalle.IdUnidadMedida)?.UnidadMedida || '-' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button @click="eliminarInsumo(index)" 
                                                    type="button"
                                                    class="w-7 h-7 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition flex items-center justify-center mx-auto" 
                                                    title="Eliminar insumo">
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Estado Vacío -->
                        <div v-else class="mt-4 p-8 text-center bg-primary-50/30 rounded-xl border border-dashed border-primary-200">
                            <div class="w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-2 text-primary-400">
                                <i class="fas fa-box-open text-lg"></i>
                            </div>
                            <p class="text-xs font-semibold text-primary-600">No hay insumos registrados</p>
                            <p class="text-[11px] text-primary-400 mt-0.5">Utilice el buscador superior para agregar insumos a la ficha técnica.</p>
                        </div>
                        
                        <p v-if="errors.detalles" class="text-xs text-red-500 mt-2 flex items-center gap-1">
                            <i class="fas fa-exclamation-triangle"></i> {{ errors.detalles }}
                        </p>
                    </div>

                </div>

                <!-- FOOTER / BOTONES -->
                <div class="px-6 py-3 border-t border-primary-200 bg-primary-50/50 flex items-center justify-between shrink-0">
                    <div class="text-xs text-primary-600">
                        <span v-if="fichaExistente" class="flex items-center gap-1 text-amber-600">
                            <i class="fas fa-edit"></i> Modo Edición
                        </span>
                        <span v-else class="flex items-center gap-1 text-primary-500">
                            <i class="fas fa-plus-circle"></i> Nueva Ficha
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button @click="cerrarModal" 
                                type="button"
                                class="px-4 py-2 border border-primary-300 rounded-lg text-primary-700 text-xs font-semibold hover:bg-primary-100 transition shadow-sm">
                            Cancelar
                        </button>
                        <button @click="guardarFicha" 
                                :disabled="guardando || loading" 
                                type="button"
                                class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-2 transition shadow-sm shadow-emerald-600/20 active:scale-95">
                            <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            <span>{{ guardando ? 'Guardando...' : (fichaExistente ? 'Actualizar Ficha' : 'Guardar Ficha') }}</span>
                        </button>
                    </div>
                </div>

                <!-- 🔥 MODAL CREAR INSUMO -->
                <ModalCrearInsumo
                    v-model="modalInsumoOpen"
                    :grupos="props.grupos"
                    :lineas="props.lineas"
                    :unidades="props.unidades"
                    :unidad-id="props.unidadId"
                    @saved="onInsumoCreado"
                />

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

/* Ocultar flechas numéricas */
.no-arrows::-webkit-outer-spin-button,
.no-arrows::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-arrows {
    -moz-appearance: textfield;
}
</style>