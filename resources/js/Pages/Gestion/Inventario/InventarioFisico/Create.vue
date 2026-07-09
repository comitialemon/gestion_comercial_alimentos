<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    inventarioFisico: Object,
    detalles: Array,
    fechas: Array,
    sucursales: Array,
    almacenes: Array,
    identificadores: Array,
    editando: Boolean,
    esBorrador: Boolean,
    esContabilizado: Boolean,
    esDesactivado: Boolean,  // ← NUEVO
})

const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 640
    isTablet.value = window.innerWidth >= 640 && window.innerWidth < 1024
}

const form = ref({
    IdFisico: props.inventarioFisico?.IdFisico || null,
    IdFecha: props.inventarioFisico?.IdFecha || '',
    IdSucursal: props.inventarioFisico?.IdSucursal || '',
    IdAlmacen: props.inventarioFisico?.IdAlmacen || '',
    IdRealizadoPor: props.inventarioFisico?.IdRealizadoPor || '',
    IdEncargadoSucursal: props.inventarioFisico?.IdEncargadoSucursal || '',
    Observacion: props.inventarioFisico?.Observacion || '',
})

// ==================== CONSTANTES DE RUTAS ====================
const API_BASE = '/gestion/inventario/inventario-fisico'

// ==================== AUTOCOMPLETE SUCURSAL ====================
const busquedaSucursal = ref('')
const mostrarSucursales = ref(false)
const sucursalesFiltradas = ref([])

const filtrarSucursales = () => {
    const busqueda = busquedaSucursal.value.toLowerCase()
    sucursalesFiltradas.value = props.sucursales.filter(s => 
        s.nombre.toLowerCase().includes(busqueda)
    )
}

const seleccionarSucursal = (sucursal) => {
    form.value.IdSucursal = sucursal.id
    busquedaSucursal.value = sucursal.nombre
    mostrarSucursales.value = false
    errors.value.IdSucursal = null
    form.value.IdAlmacen = ''
    almacenesDisponibles.value = []
    cargarAlmacenes(sucursal.id)
}

const ocultarSucursales = () => {
    setTimeout(() => { mostrarSucursales.value = false }, 200)
}

// ==================== ALMACENES ====================
const almacenesDisponibles = ref([])
const cargandoAlmacenes = ref(false)

const cargarAlmacenes = async (sucursalId) => {
    if (!sucursalId) {
        almacenesDisponibles.value = []
        form.value.IdAlmacen = ''
        return
    }
    
    cargandoAlmacenes.value = true
    try {
        const response = await axios.get(`/api/almacenes-por-sucursal/${sucursalId}`)
        almacenesDisponibles.value = response.data
        form.value.IdAlmacen = ''
        if (almacenesDisponibles.value.length === 1) {
            form.value.IdAlmacen = almacenesDisponibles.value[0].id
        }
    } catch (error) {
        console.error('Error cargando almacenes:', error)
        almacenesDisponibles.value = []
    } finally {
        cargandoAlmacenes.value = false
    }
}

// ==================== AUTOCOMPLETE REALIZADO POR ====================
const busquedaRealizadoPor = ref('')
const mostrarRealizadoPor = ref(false)
const realizadosPorFiltrados = ref([])

const filtrarRealizadoPor = () => {
    const busqueda = busquedaRealizadoPor.value.toLowerCase()
    realizadosPorFiltrados.value = props.identificadores.filter(i => 
        i.texto.toLowerCase().includes(busqueda)
    )
}

const seleccionarRealizadoPor = (item) => {
    form.value.IdRealizadoPor = item.id
    busquedaRealizadoPor.value = item.texto
    mostrarRealizadoPor.value = false
    errors.value.IdRealizadoPor = null
}

const ocultarRealizadoPor = () => {
    setTimeout(() => { mostrarRealizadoPor.value = false }, 200)
}

// ==================== AUTOCOMPLETE ENCARGADO SUCURSAL ====================
const busquedaEncargado = ref('')
const mostrarEncargado = ref(false)
const encargadosFiltrados = ref([])

const filtrarEncargado = () => {
    const busqueda = busquedaEncargado.value.toLowerCase()
    encargadosFiltrados.value = props.identificadores.filter(i => 
        i.texto.toLowerCase().includes(busqueda)
    )
}

const seleccionarEncargado = (item) => {
    form.value.IdEncargadoSucursal = item.id
    busquedaEncargado.value = item.texto
    mostrarEncargado.value = false
    errors.value.IdEncargadoSucursal = null
}

const ocultarEncargado = () => {
    setTimeout(() => { mostrarEncargado.value = false }, 200)
}

// ==================== ESTADO ====================
const detallesGrid = ref([])
const guardandoCabecera = ref(false)
const reprocesando = ref(false)
const contabilizando = ref(false)
const errors = ref({})
const mostrarConfirmacion = ref(false)
const cabeceraGuardada = ref(false)

// ==================== BUSCADOR DE PRODUCTOS ====================
const busquedaProducto = ref('')
const productosFiltrados = computed(() => {
    if (!busquedaProducto.value.trim()) {
        return detallesGrid.value
    }
    const busqueda = busquedaProducto.value.toLowerCase()
    return detallesGrid.value.filter(item => 
        item.Codigo?.toLowerCase().includes(busqueda) || 
        item.Descripcion?.toLowerCase().includes(busqueda)
    )
})

// ==================== EDICIÓN DE UNIDADES ====================
const editandoUnidades = ref(null)
const editandoValorTemp = ref(0)

const iniciarEdicionUnidades = (item, index) => {
    editandoUnidades.value = index
    editandoValorTemp.value = item.Unidades
}

const cancelarEdicionUnidades = () => {
    editandoUnidades.value = null
    editandoValorTemp.value = 0
}

const guardarEdicionUnidades = async (item, index) => {
    if (editandoValorTemp.value < 0) {
        toast?.warning('Unidades inválidas', 'No pueden ser negativas')
        return
    }
    
    item.Unidades = editandoValorTemp.value
    
    try {
        const response = await axios.put(
            `${API_BASE}/${form.value.IdFisico}/detalle/${item.IdFisicoPropiamente}/unidades`,
            { Unidades: item.Unidades }
        )
        
        if (response.data.success) {
            item.UnidadesAjuste = response.data.unidades_ajuste
            toast?.success('Actualizado', `Conteo: ${item.Unidades}`)
            editandoUnidades.value = null
            editandoValorTemp.value = 0
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', 'No se pudo actualizar')
    }
}

const cargarDetalles = async () => {
    if (!form.value.IdFisico) return
    try {
        const response = await axios.get(`${API_BASE}/${form.value.IdFisico}/detalles`)
        if (response.data.success) {
            detallesGrid.value = response.data.detalles
        }
    } catch (error) {
        console.error('Error cargando detalles:', error)
    }
}

const totalProductos = computed(() => detallesGrid.value.length)
const totalConAjuste = computed(() => detallesGrid.value.filter(d => d.UnidadesAjuste !== 0).length)

const validarCamposCabecera = () => {
    const nuevosErrors = {}
    if (!form.value.IdFecha) nuevosErrors.IdFecha = 'Seleccione una fecha'
    if (!form.value.IdSucursal) nuevosErrors.IdSucursal = 'Seleccione una sucursal'
    if (!form.value.IdAlmacen) nuevosErrors.IdAlmacen = 'Seleccione un almacén'
    if (!form.value.IdRealizadoPor) nuevosErrors.IdRealizadoPor = 'Seleccione quien realizó'
    if (!form.value.IdEncargadoSucursal) nuevosErrors.IdEncargadoSucursal = 'Seleccione el encargado'
    errors.value = nuevosErrors
    return Object.keys(nuevosErrors).length === 0
}

const guardarCabecera = async () => {
    if (!validarCamposCabecera()) {
        toast?.warning('Datos incompletos', 'Complete todos los campos obligatorios')
        return
    }
    
    guardandoCabecera.value = true
    try {
        // 🔥 SIEMPRE POST a /cabecera (el Controller maneja si es creación o actualización)
        const response = await axios.post(`${API_BASE}/cabecera`, form.value)
        
        if (response.data.success) {
            if (response.data.id) {
                form.value.IdFisico = response.data.id
            }
            cabeceraGuardada.value = true
            await cargarDetalles()
            toast?.success('Cabecera guardada', 'Productos cargados automáticamente')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardandoCabecera.value = false
    }
}

// 🔥 REPROCESAR (antes Sincronizar)
const reprocesarProductos = async () => {
    if (!form.value.IdFisico) return
    reprocesando.value = true
    try {
        const response = await axios.post(`${API_BASE}/${form.value.IdFisico}/sincronizar`)
        if (response.data.success) {
            await cargarDetalles()
            toast?.success('Reprocesado', 'Lista de productos actualizada')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al reprocesar')
    } finally {
        reprocesando.value = false
    }
}

const contabilizar = () => {
    if (detallesGrid.value.length === 0) {
        toast?.warning('Sin productos', 'Reprocese productos primero')
        return
    }
    mostrarConfirmacion.value = true
}

const ejecutarContabilizar = async () => {
    contabilizando.value = true
    mostrarConfirmacion.value = false
    try {
        const response = await axios.post(`${API_BASE}/${form.value.IdFisico}/contabilizar`)
        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            if (response.data.pdf_url) {
                window.open(response.data.pdf_url, '_blank')
            }
            window.location.href = '/gestion/inventario/inventario-fisico'
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al contabilizar')
    } finally {
        contabilizando.value = false
    }
}

const cancelarConfirmacion = () => {
    mostrarConfirmacion.value = false
}

// ==================== INICIALIZAR ====================
const inicializar = () => {
    // 🔥 SIEMPRE activar cabeceraGuardada si existe inventarioFisico con IdFisico
    if (props.inventarioFisico && props.inventarioFisico.IdFisico) {
        cabeceraGuardada.value = true
        
        // Cargar datos del formulario (solo si tienen valor válido)
        if (props.inventarioFisico.IdFecha && props.inventarioFisico.IdFecha != 0) {
            form.value.IdFecha = props.inventarioFisico.IdFecha
        }
        if (props.inventarioFisico.IdSucursal && props.inventarioFisico.IdSucursal != 0) {
            form.value.IdSucursal = props.inventarioFisico.IdSucursal
            cargarAlmacenes(props.inventarioFisico.IdSucursal)
        }
        if (props.inventarioFisico.IdAlmacen && props.inventarioFisico.IdAlmacen != 0) {
            form.value.IdAlmacen = props.inventarioFisico.IdAlmacen
        }
        if (props.inventarioFisico.IdRealizadoPor && props.inventarioFisico.IdRealizadoPor != 0) {
            form.value.IdRealizadoPor = props.inventarioFisico.IdRealizadoPor
        }
        if (props.inventarioFisico.IdEncargadoSucursal && props.inventarioFisico.IdEncargadoSucursal != 0) {
            form.value.IdEncargadoSucursal = props.inventarioFisico.IdEncargadoSucursal
        }
        if (props.inventarioFisico.Observacion) {
            form.value.Observacion = props.inventarioFisico.Observacion
        }
    }
    
    // 🔥 Cargar nombres en autocompletar
    if (form.value.IdSucursal && props.sucursales) {
        const sucursal = props.sucursales.find(s => s.id === form.value.IdSucursal)
        if (sucursal) {
            busquedaSucursal.value = sucursal.nombre
        }
    }
    
    if (form.value.IdRealizadoPor && props.identificadores) {
        const realizado = props.identificadores.find(i => i.id === form.value.IdRealizadoPor)
        if (realizado) busquedaRealizadoPor.value = realizado.texto
    }
    
    if (form.value.IdEncargadoSucursal && props.identificadores) {
        const encargado = props.identificadores.find(i => i.id === form.value.IdEncargadoSucursal)
        if (encargado) busquedaEncargado.value = encargado.texto
    }
    
    // 🔥 Cargar detalles si existen
    if (props.detalles && props.detalles.length > 0) {
        detallesGrid.value = props.detalles
        cabeceraGuardada.value = true
    }
}

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    inicializar()
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<style scoped>
.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-spinner {
    -moz-appearance: textfield;
    appearance: textfield;
}

.absolute {
    position: absolute;
    z-index: 50;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.product-card {
    animation: fadeInUp 0.3s ease-out;
}

.edit-input {
    font-size: 14px !important;
}
</style>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-2 px-2 sm:py-3 sm:px-4 lg:py-4 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3 sm:mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-clipboard-list text-primary-600 text-[11px] sm:text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-sm sm:text-base lg:text-lg font-bold text-gray-800 truncate">Inventario Físico</h1>
                            <p class="text-[8px] sm:text-[10px] text-gray-500 truncate">Registro de conteo físico de productos</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-1.5 sm:gap-2 w-full sm:w-auto">
                        <!-- 🔥 BOTÓN GUARDAR (deshabilitado si está contabilizado) -->
                        <button 
                            @click="guardarCabecera"
                            :disabled="guardandoCabecera || props.esContabilizado"
                            class="flex-1 sm:flex-none bg-primary-600 hover:bg-primary-700 text-white px-2 sm:px-3 py-1.5 rounded text-[10px] sm:text-xs flex items-center justify-center gap-1 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <i v-if="guardandoCabecera" class="fas fa-spinner fa-spin text-[9px] sm:text-[10px]"></i>
                            <i v-else class="fas fa-save text-[9px] sm:text-[10px]"></i>
                            <span class="hidden xs:inline">{{ guardandoCabecera ? 'Guardando...' : 'Guardar' }}</span>
                            <span class="xs:hidden">{{ guardandoCabecera ? '...' : 'Guardar' }}</span>
                        </button>
                        
                        <!-- 🔥 BOTÓN CONTABILIZAR (solo si hay productos y no está contabilizado) -->
                        <button 
                            v-if="cabeceraGuardada && detallesGrid.length > 0 && !props.esContabilizado"
                            @click="contabilizar"
                            :disabled="contabilizando"
                            class="flex-1 sm:flex-none bg-green-600 hover:bg-green-700 text-white px-2 sm:px-3 py-1.5 rounded text-[10px] sm:text-xs flex items-center justify-center gap-1 transition disabled:opacity-50"
                        >
                            <i v-if="contabilizando" class="fas fa-spinner fa-spin text-[9px] sm:text-[10px]"></i>
                            <i v-else class="fas fa-check-circle text-[9px] sm:text-[10px]"></i>
                            <span class="hidden xs:inline">{{ contabilizando ? 'Contabilizando...' : 'Contabilizar' }}</span>
                            <span class="xs:hidden">{{ contabilizando ? '...' : 'Contab.' }}</span>
                        </button>
                        
                        <!-- 🔥 INDICADOR DE CONTABILIZADO -->
                        <span v-if="props.esContabilizado" class="bg-green-100 text-green-800 px-3 py-1.5 rounded text-xs font-semibold flex items-center gap-1">
                            <i class="fas fa-check-circle"></i>
                            Contabilizado N° {{ props.inventarioFisico?.NumeroCorrelativo }}
                        </span>
                    </div>
                </div>

                <!-- 🔥 ALERTA DE BORRADOR -->
                <div v-if="props.esBorrador" class="bg-amber-50 border border-amber-200 rounded-lg p-2 mb-4">
                    <div class="flex items-center gap-2 text-amber-700 text-xs">
                        <i class="fas fa-info-circle"></i>
                        <span>Borrador en progreso. Complete los datos y guarde la cabecera.</span>
                    </div>
                </div>

                <!-- 🔥 ALERTA DE CONTABILIZADO -->
                <div v-if="props.esContabilizado" class="bg-green-50 border border-green-200 rounded-lg p-2 mb-4">
                    <div class="flex items-center gap-2 text-green-700 text-xs">
                        <i class="fas fa-check-circle"></i>
                        <span>Documento contabilizado. Solo lectura.</span>
                    </div>
                </div>

                <!-- Formulario Cabecera -->
                <div class="bg-white rounded-lg sm:rounded-xl shadow-sm p-2.5 sm:p-3 lg:p-4 mb-3 sm:mb-4 relative">
                    <h2 class="text-[11px] sm:text-sm font-semibold text-gray-700 mb-2 sm:mb-3">Datos del Inventario Físico</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 text-[10px] sm:text-xs">
                        <div>
                            <label class="block text-gray-600 mb-0.5 text-[9px] sm:text-[10px]">Fecha *</label>
                            <select v-model="form.IdFecha" class="w-full border rounded-md px-2 py-1.5 text-[10px] sm:text-xs" :class="{'border-red-500': errors.IdFecha}" :disabled="props.esContabilizado">
                                <option value="">Seleccione</option>
                                <option v-for="f in fechas" :key="f.id" :value="f.id">{{ f.fecha_display }}</option>
                            </select>
                            <p v-if="errors.IdFecha" class="text-red-500 text-[8px] sm:text-[10px] mt-0.5">{{ errors.IdFecha }}</p>
                        </div>

                        <div class="relative">
                            <label class="block text-gray-600 mb-0.5 text-[9px] sm:text-[10px]">Sucursal *</label>
                            <input 
                                type="text"
                                v-model="busquedaSucursal"
                                @input="filtrarSucursales"
                                @focus="mostrarSucursales = true"
                                @blur="ocultarSucursales"
                                placeholder="Buscar..."
                                class="w-full border rounded-md px-2 py-1.5 text-[10px] sm:text-xs"
                                :class="{'border-red-500': errors.IdSucursal}"
                                :disabled="props.esContabilizado"
                            />
                            <div v-if="mostrarSucursales && sucursalesFiltradas.length > 0" 
                                 class="absolute z-50 mt-1 w-full border rounded-md max-h-36 sm:max-h-48 overflow-y-auto bg-white shadow-lg">
                                <div v-for="suc in sucursalesFiltradas" 
                                     :key="suc.id"
                                     @click="seleccionarSucursal(suc)"
                                     class="p-1.5 sm:p-2 hover:bg-gray-100 cursor-pointer text-[10px] sm:text-xs border-b last:border-b-0">
                                    {{ suc.nombre }}
                                </div>
                            </div>
                            <p v-if="errors.IdSucursal" class="text-red-500 text-[8px] sm:text-[10px] mt-0.5">{{ errors.IdSucursal }}</p>
                        </div>

                        <div>
                            <label class="block text-gray-600 mb-0.5 text-[9px] sm:text-[10px]">Almacén *</label>
                            <select 
                                v-model="form.IdAlmacen"
                                class="w-full border rounded-md px-2 py-1.5 text-[10px] sm:text-xs"
                                :class="{'border-red-500': errors.IdAlmacen}"
                                :disabled="cargandoAlmacenes || (!form.IdSucursal) || props.esContabilizado"
                            >
                                <option value="">-- Seleccione --</option>
                                <option v-for="alm in almacenesDisponibles" :key="alm.id" :value="alm.id">
                                    {{ alm.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdAlmacen" class="text-red-500 text-[8px] sm:text-[10px] mt-0.5">{{ errors.IdAlmacen }}</p>
                        </div>

                        <div class="relative">
                            <label class="block text-gray-600 mb-0.5 text-[9px] sm:text-[10px]">Realizado Por *</label>
                            <input 
                                type="text"
                                v-model="busquedaRealizadoPor"
                                @input="filtrarRealizadoPor"
                                @focus="mostrarRealizadoPor = true"
                                @blur="ocultarRealizadoPor"
                                placeholder="Buscar..."
                                class="w-full border rounded-md px-2 py-1.5 text-[10px] sm:text-xs"
                                :class="{'border-red-500': errors.IdRealizadoPor}"
                                :disabled="props.esContabilizado"
                            />
                            <div v-if="mostrarRealizadoPor && realizadosPorFiltrados.length > 0" 
                                 class="absolute z-50 mt-1 w-full border rounded-md max-h-36 sm:max-h-48 overflow-y-auto bg-white shadow-lg">
                                <div v-for="item in realizadosPorFiltrados" 
                                     :key="item.id"
                                     @click="seleccionarRealizadoPor(item)"
                                     class="p-1.5 sm:p-2 hover:bg-gray-100 cursor-pointer text-[10px] sm:text-xs border-b">
                                    {{ item.texto }}
                                </div>
                            </div>
                            <p v-if="errors.IdRealizadoPor" class="text-red-500 text-[8px] sm:text-[10px] mt-0.5">{{ errors.IdRealizadoPor }}</p>
                        </div>

                        <div class="relative">
                            <label class="block text-gray-600 mb-0.5 text-[9px] sm:text-[10px]">Encargado Sucursal *</label>
                            <input 
                                type="text"
                                v-model="busquedaEncargado"
                                @input="filtrarEncargado"
                                @focus="mostrarEncargado = true"
                                @blur="ocultarEncargado"
                                placeholder="Buscar..."
                                class="w-full border rounded-md px-2 py-1.5 text-[10px] sm:text-xs"
                                :class="{'border-red-500': errors.IdEncargadoSucursal}"
                                :disabled="props.esContabilizado"
                            />
                            <div v-if="mostrarEncargado && encargadosFiltrados.length > 0" 
                                 class="absolute z-50 mt-1 w-full border rounded-md max-h-36 sm:max-h-48 overflow-y-auto bg-white shadow-lg">
                                <div v-for="item in encargadosFiltrados" 
                                     :key="item.id"
                                     @click="seleccionarEncargado(item)"
                                     class="p-1.5 sm:p-2 hover:bg-gray-100 cursor-pointer text-[10px] sm:text-xs border-b">
                                    {{ item.texto }}
                                </div>
                            </div>
                            <p v-if="errors.IdEncargadoSucursal" class="text-red-500 text-[8px] sm:text-[10px] mt-0.5">{{ errors.IdEncargadoSucursal }}</p>
                        </div>

                        <div class="sm:col-span-2 lg:col-span-4">
                            <label class="block text-gray-600 mb-0.5 text-[9px] sm:text-[10px]">Observación</label>
                            <textarea v-model="form.Observacion" rows="1.5 sm:rows-2" class="w-full border rounded-md px-2 py-1.5 text-[10px] sm:text-xs" placeholder="Notas..." :disabled="props.esContabilizado"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Sección Detalle -->
                <div v-if="cabeceraGuardada" class="bg-white rounded-lg sm:rounded-xl shadow-sm p-2.5 sm:p-3 lg:p-4 mb-3 sm:mb-4">
                    <div class="flex flex-col xs:flex-row justify-between items-start xs:items-center gap-2 mb-3">
                        <h2 class="text-[11px] sm:text-sm font-semibold text-gray-700">Productos</h2>
                        <div class="flex flex-col xs:flex-row gap-2 w-full xs:w-auto">
                            <div class="relative w-full xs:w-48 sm:w-56 lg:w-64">
                                <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[9px] sm:text-[10px]"></i>
                                <input 
                                    type="text"
                                    v-model="busquedaProducto"
                                    placeholder="Buscar producto..."
                                    class="w-full border rounded-md pl-7 pr-2 py-1.5 text-[10px] sm:text-xs focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: 'var(--color-primary-300)' }"
                                    :disabled="props.esContabilizado"
                                />
                            </div>
                            <button 
                                v-if="!props.esContabilizado"
                                @click="reprocesarProductos"
                                :disabled="reprocesando"
                                class="bg-primary-600 hover:bg-primary-700 text-white px-2.5 sm:px-3 py-1.5 rounded text-[10px] sm:text-xs flex items-center justify-center gap-1 transition disabled:opacity-50"
                            >
                                <i v-if="reprocesando" class="fas fa-spinner fa-spin text-[8px] sm:text-[10px]"></i>
                                <i v-else class="fas fa-sync-alt text-[8px] sm:text-[10px]"></i>
                                <span class="hidden xs:inline">{{ reprocesando ? 'Reprocesando...' : 'Reprocesar' }}</span>
                                <span class="xs:hidden">{{ reprocesando ? '...' : 'Reprocesar' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Desktop: Tabla -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full text-[10px] sm:text-xs border">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left font-semibold" :style="{ color: 'var(--color-primary-700)' }">Código</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left font-semibold" :style="{ color: 'var(--color-primary-700)' }">Producto</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right font-semibold" :style="{ color: 'var(--color-primary-700)' }">Saldo</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right font-semibold" :style="{ color: 'var(--color-primary-700)' }">Conteo Físico</th>
                                    <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right font-semibold" :style="{ color: 'var(--color-primary-700)' }">Ajuste</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(item, idx) in productosFiltrados" :key="item.IdFisicoPropiamente" class="hover:bg-gray-50 transition">
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 font-mono text-[9px] sm:text-[11px]">{{ item.Codigo }}</td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-[9px] sm:text-[11px]">{{ item.Descripcion }}</td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-right text-[9px] sm:text-[11px]">{{ Number(item.UnidadesSaldo).toFixed(2) }}</td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-right">
                                        <div v-if="editandoUnidades === idx && !props.esContabilizado" class="flex gap-1 justify-end">
                                            <input type="number" step="0.01" v-model.number="editandoValorTemp" 
                                                   class="w-16 sm:w-20 border rounded px-1 py-0.5 text-[9px] sm:text-xs text-right no-spinner">
                                            <button @click="guardarEdicionUnidades(item, idx)" class="bg-green-500 text-white px-1 rounded text-[9px] sm:text-[10px]">✓</button>
                                            <button @click="cancelarEdicionUnidades" class="bg-gray-500 text-white px-1 rounded text-[9px] sm:text-[10px]">✗</button>
                                        </div>
                                        <div v-else class="flex items-center justify-end gap-1">
                                            <span class="text-[9px] sm:text-[11px]">{{ Number(item.Unidades).toFixed(2) }}</span>
                                            <button v-if="!props.esContabilizado" @click="iniciarEdicionUnidades(item, idx)" class="text-blue-500 hover:text-blue-700 transition">
                                                <i class="fas fa-edit text-[9px] sm:text-[10px]"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-right font-semibold text-[9px] sm:text-[11px]" 
                                        :class="item.UnidadesAjuste > 0 ? 'text-green-600' : (item.UnidadesAjuste < 0 ? 'text-red-600' : 'text-gray-500')">
                                        {{ item.UnidadesAjuste > 0 ? '+' : '' }}{{ Number(item.UnidadesAjuste).toFixed(2) }}
                                    </td>
                                </tr>
                                <tr v-if="productosFiltrados.length === 0">
                                    <td colspan="5" class="px-2 sm:px-3 py-6 sm:py-8 text-center text-gray-400 text-[10px] sm:text-xs">
                                        <i class="fas fa-box-open text-xl sm:text-2xl mb-2 block"></i>
                                        No hay productos. Presione "Reprocesar"
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="productosFiltrados.length > 0" class="bg-gray-50">
                                <tr>
                                    <td colspan="2" class="px-2 sm:px-3 py-1.5 sm:py-2 font-semibold text-[9px] sm:text-[11px]">Resumen</td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2"></td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-right font-semibold text-[9px] sm:text-[11px]">Total: {{ totalProductos }}</td>
                                    <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-right font-semibold text-[9px] sm:text-[11px]">Con Ajuste: {{ totalConAjuste }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Mobile/Tablet: Tarjetas -->
                    <div class="md:hidden space-y-2 sm:space-y-3">
                        <div v-for="(item, idx) in productosFiltrados" :key="item.IdFisicoPropiamente" 
                             class="product-card bg-white border rounded-lg p-2.5 sm:p-3 hover:shadow-md transition-shadow">
                            
                            <div class="flex flex-col gap-2">
                                <!-- Cabecera de la tarjeta -->
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono text-[9px] sm:text-[10px] bg-gray-100 px-1.5 py-0.5 rounded text-primary-600">{{ item.Codigo }}</span>
                                        </div>
                                        <p class="text-[10px] sm:text-xs font-medium text-gray-800 truncate mt-0.5">{{ item.Descripcion }}</p>
                                    </div>
                                    
                                    <!-- Botón de edición -->
                                    <div class="flex-shrink-0 ml-2">
                                        <button v-if="editandoUnidades !== idx && !props.esContabilizado" 
                                                @click="iniciarEdicionUnidades(item, idx)" 
                                                class="bg-primary-50 hover:bg-primary-100 text-primary-600 px-2.5 py-1.5 rounded-lg text-[10px] sm:text-xs flex items-center gap-1.5 transition border border-primary-200">
                                            <i class="fas fa-pen text-[8px] sm:text-[9px]"></i>
                                            <span>Editar Conteo</span>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Datos en fila -->
                                <div class="grid grid-cols-3 gap-1.5 sm:gap-2">
                                    <div class="bg-gray-50 rounded-lg p-1.5 sm:p-2 text-center border">
                                        <span class="text-[7px] sm:text-[8px] text-gray-500 block">Saldo</span>
                                        <span class="text-[10px] sm:text-xs font-semibold text-gray-700">{{ Number(item.UnidadesSaldo).toFixed(2) }}</span>
                                    </div>
                                    
                                    <div class="bg-primary-50 rounded-lg p-1.5 sm:p-2 text-center border border-primary-200">
                                        <span class="text-[7px] sm:text-[8px] text-gray-500 block">Conteo</span>
                                        <span v-if="editandoUnidades === idx && !props.esContabilizado" class="block">
                                            <input type="number" step="0.01" v-model.number="editandoValorTemp" 
                                                   class="w-full border rounded px-1 py-0.5 text-[10px] sm:text-xs text-center no-spinner edit-input"
                                                   :style="{ borderColor: 'var(--color-primary-300)' }"
                                                   @focus="$event.target.select()"
                                            >
                                        </span>
                                        <span v-else class="text-[10px] sm:text-xs font-semibold" :style="{ color: 'var(--color-primary-600)' }">
                                            {{ Number(item.Unidades).toFixed(2) }}
                                        </span>
                                    </div>
                                    
                                    <div class="rounded-lg p-1.5 sm:p-2 text-center border" 
                                         :class="item.UnidadesAjuste > 0 ? 'bg-green-50 border-green-200' : (item.UnidadesAjuste < 0 ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200')">
                                        <span class="text-[7px] sm:text-[8px] text-gray-500 block">Ajuste</span>
                                        <span class="text-[10px] sm:text-xs font-semibold" 
                                              :class="item.UnidadesAjuste > 0 ? 'text-green-600' : (item.UnidadesAjuste < 0 ? 'text-red-600' : 'text-gray-500')">
                                            {{ item.UnidadesAjuste > 0 ? '+' : '' }}{{ Number(item.UnidadesAjuste).toFixed(2) }}
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Botones de acción cuando está editando -->
                                <div v-if="editandoUnidades === idx && !props.esContabilizado" class="flex gap-2 mt-1">
                                    <button @click="guardarEdicionUnidades(item, idx)" 
                                            class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg text-[10px] sm:text-xs font-medium transition flex items-center justify-center gap-1.5">
                                        <i class="fas fa-check text-[8px] sm:text-[9px]"></i> Guardar Conteo
                                    </button>
                                    <button @click="cancelarEdicionUnidades" 
                                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 rounded-lg text-[10px] sm:text-xs font-medium transition flex items-center justify-center gap-1.5">
                                        <i class="fas fa-times text-[8px] sm:text-[9px]"></i> Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="productosFiltrados.length === 0" class="text-center py-6 sm:py-8 text-gray-400 text-[10px] sm:text-xs">
                            <i class="fas fa-box-open text-xl sm:text-2xl mb-2 block"></i>
                            No hay productos. Presione "Reprocesar"
                        </div>
                        
                        <!-- Resumen móvil -->
                        <div v-if="productosFiltrados.length > 0" class="bg-gray-50 rounded-lg p-2.5 sm:p-3 border">
                            <div class="flex justify-between text-[9px] sm:text-[10px]">
                                <span class="text-gray-500">Total productos:</span>
                                <span class="font-semibold">{{ totalProductos }}</span>
                            </div>
                            <div class="flex justify-between text-[9px] sm:text-[10px] mt-0.5">
                                <span class="text-gray-500">Con ajuste:</span>
                                <span class="font-semibold">{{ totalConAjuste }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-amber-50 rounded-lg p-3 sm:p-4 text-amber-800 text-[10px] sm:text-sm text-center border border-amber-200">
                    <i class="fas fa-info-circle mr-1"></i>
                    Complete la cabecera y presione "Guardar Cabecera"
                </div>

                <!-- Modal de Confirmación -->
                <div v-if="mostrarConfirmacion" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3 sm:p-4">
                    <div class="bg-white rounded-lg max-w-sm w-full mx-2 sm:mx-0">
                        <div class="bg-green-600 p-2.5 sm:p-3 rounded-t-lg">
                            <h3 class="text-white font-semibold text-[10px] sm:text-sm">Confirmar Contabilización</h3>
                        </div>
                        <div class="p-3 sm:p-4">
                            <p class="text-gray-700 text-[10px] sm:text-sm mb-2 sm:mb-3">¿Está seguro de contabilizar este inventario físico?</p>
                            <p class="text-gray-500 text-[8px] sm:text-xs mb-3 sm:mb-4">Se generarán los ajustes en el inventario.</p>
                            <div class="flex gap-2">
                                <button @click="cancelarConfirmacion" class="flex-1 py-1.5 sm:py-2 rounded bg-gray-200 text-gray-700 text-[10px] sm:text-sm hover:bg-gray-300 transition">Cancelar</button>
                                <button @click="ejecutarContabilizar" class="flex-1 py-1.5 sm:py-2 rounded bg-green-600 text-white text-[10px] sm:text-sm hover:bg-green-700 transition">Sí, Contabilizar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>