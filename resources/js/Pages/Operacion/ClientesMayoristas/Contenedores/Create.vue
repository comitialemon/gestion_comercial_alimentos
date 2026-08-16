<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, inject, watch } from 'vue'
import axios from 'axios'
import CreateTipoContenedor from './CreateTipoContenedor.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    contenedor: {
        type: Object,
        default: null
    },
    sucursales: {
        type: Array,
        default: () => []
    },
    tiposContenedor: {
        type: Array,
        default: () => []
    },
    gruposAnalisis: {
        type: Array,
        default: () => []
    },
    gruposSeleccionados: {
        type: Array,
        default: () => []
    }
})

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

// ==================== ESTADO DEL CONTENEDOR ====================
const contenedorId = ref(props.contenedor?.IdContenedor || null)
const cabeceraGuardada = ref(!!props.contenedor?.IdContenedor)
const esBorrador = ref(props.contenedor?.ActivoInactivo === 0)
const estaActivo = ref(props.contenedor?.ActivoInactivo === 1)

// ==================== FORMULARIO CABECERA ====================
const form = ref({
    IdSucursal: props.contenedor?.IdSucursal || '',
    IdTipoContenedor: props.contenedor?.IdTipoContenedor || '',
    CapacidadTotal: props.contenedor?.CapacidadTotal || '',
})

// ==================== TIPOS DE CONTENEDOR (LOCAL) ====================
const tiposContenedorLocal = ref([...props.tiposContenedor])

// ==================== GRUPOS DE ANÁLISIS ====================
const gruposSeleccionadosIds = ref([...props.gruposSeleccionados])
const asignandoGrupos = ref(false)
const totalProductosDisponibles = ref(0)

// ==================== MODAL TIPO CONTENEDOR ====================
const modalTipoVisible = ref(false)

const abrirModalTipo = () => {
    modalTipoVisible.value = true
}

const cerrarModalTipo = () => {
    modalTipoVisible.value = false
}

const onTipoCreado = (nuevoTipo) => {
    tiposContenedorLocal.value.push(nuevoTipo)
    form.value.IdTipoContenedor = nuevoTipo.id
    toast?.success('Éxito', `Tipo "${nuevoTipo.nombre}" creado y seleccionado`)
}

// ==================== SUCURSAL - Autocomplete ====================
const busquedaSucursal = ref('')
const mostrarDropdownSucursal = ref(false)

const sucursalesFiltradas = computed(() => {
    if (!busquedaSucursal.value) return props.sucursales || []
    const termino = busquedaSucursal.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.numero?.toString().includes(termino)
    )
})

// ==================== ESTADOS ====================
const errors = ref({})
const processing = ref(false)
const finalizando = ref(false)

// ==================== COMPUTADOS ====================
const codigoGenerado = computed(() => {
    if (!form.value.IdTipoContenedor || !form.value.CapacidadTotal) return ''
    
    const tipo = tiposContenedorLocal.value.find(t => t.id === form.value.IdTipoContenedor)
    if (!tipo) return ''
    
    const nombreTipo = tipo.nombre.toUpperCase()
    return nombreTipo + '-' + parseInt(form.value.CapacidadTotal)
})

const capacidadTotalNumero = computed(() => {
    return parseFloat(form.value.CapacidadTotal) || 0
})

const tieneGruposSeleccionados = computed(() => {
    return gruposSeleccionadosIds.value.length > 0
})

// ✅ SOLO VALIDA QUE TENGA GRUPOS SELECCIONADOS (NO importa si tienen productos)
const puedeFinalizar = computed(() => {
    return cabeceraGuardada.value && tieneGruposSeleccionados.value
})

// ==================== SUCURSAL - Funciones ====================
const seleccionarSucursal = (sucursal) => {
    form.value.IdSucursal = sucursal.id
    busquedaSucursal.value = `${sucursal.nombre} ${sucursal.numero ? `(N° ${sucursal.numero})` : ''}`
    mostrarDropdownSucursal.value = false
}

const limpiarSucursal = () => {
    form.value.IdSucursal = ''
    busquedaSucursal.value = ''
}

const cerrarDropdownSucursal = () => {
    setTimeout(() => {
        mostrarDropdownSucursal.value = false
    }, 200)
}

// ==================== GUARDAR CABECERA (PASO 1) ====================
const guardarCabecera = async () => {
    errors.value = {}
    
    if (!form.value.IdSucursal) {
        toast?.error('Validación', 'Seleccione una sucursal')
        return
    }
    if (!form.value.IdTipoContenedor) {
        toast?.error('Validación', 'Seleccione un tipo de contenedor')
        return
    }
    if (!form.value.CapacidadTotal || parseFloat(form.value.CapacidadTotal) <= 0) {
        toast?.error('Validación', 'Ingrese la capacidad máxima por producto')
        return
    }
    
    processing.value = true
    
    try {
        let response
        
        if (contenedorId.value) {
            response = await axios.put(`/operacion/pedidos/clientes-mayoristas/contenedores/${contenedorId.value}`, {
                IdSucursal: form.value.IdSucursal,
                IdTipoContenedor: form.value.IdTipoContenedor,
                CapacidadTotal: form.value.CapacidadTotal,
            })
        } else {
            response = await axios.post('/operacion/pedidos/clientes-mayoristas/contenedores', {
                IdSucursal: form.value.IdSucursal,
                IdTipoContenedor: form.value.IdTipoContenedor,
                CapacidadTotal: form.value.CapacidadTotal,
            })
        }
        
        if (response.data.success) {
            if (!contenedorId.value && response.data.contenedor) {
                contenedorId.value = response.data.contenedor.IdContenedor
            }
            
            cabeceraGuardada.value = true
            esBorrador.value = true
            estaActivo.value = false
            
            toast?.success('Éxito', 'Cabecera guardada correctamente. Ahora seleccione los grupos de análisis.')
            
            if (!contenedorId.value) {
                router.get(`/operacion/pedidos/clientes-mayoristas/contenedores/${response.data.contenedor.IdContenedor}/edit`)
            } else {
                router.reload()
            }
        }
    } catch (error) {
        console.error('Error:', error)
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            toast?.error('Error de validación', Object.values(errors.value).join(', '))
        } else {
            toast?.error('Error', error.response?.data?.message || 'Error al guardar la cabecera')
        }
    } finally {
        processing.value = false
    }
}

// ==================== TOGGLE GRUPO Y GUARDAR AUTOMÁTICAMENTE ====================
const toggleGrupo = async (grupoId) => {
    if (!cabeceraGuardada.value || !esBorrador.value) return
    
    const isAdding = !gruposSeleccionadosIds.value.includes(grupoId)
    
    if (isAdding) {
        gruposSeleccionadosIds.value.push(grupoId)
    } else {
        const index = gruposSeleccionadosIds.value.indexOf(grupoId)
        if (index > -1) {
            gruposSeleccionadosIds.value.splice(index, 1)
        }
    }
    
    await guardarGruposAutomatico()
}

// ==================== GUARDAR GRUPOS AUTOMÁTICAMENTE ====================
const guardarGruposAutomatico = async () => {
    asignandoGrupos.value = true
    
    try {
        const response = await axios.post(`/operacion/pedidos/clientes-mayoristas/contenedores/${contenedorId.value}/asignar-grupos`, {
            grupos: gruposSeleccionadosIds.value
        })

        if (response.data.success) {
            totalProductosDisponibles.value = response.data.total_productos || 0
            
            if (gruposSeleccionadosIds.value.length === 0) {
                toast?.info('Grupos actualizados', 'No hay grupos seleccionados')
            } else {
                toast?.success('Grupos actualizados', `${gruposSeleccionadosIds.value.length} grupo(s) seleccionado(s)`)
            }
        } else {
            toast?.error('Error', response.data.message || 'Error al asignar grupos')
            router.reload()
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al asignar grupos')
        router.reload()
    } finally {
        asignandoGrupos.value = false
    }
}

// ==================== FINALIZAR CONTENEDOR (PASO 3) ====================
const finalizarContenedor = async () => {
    if (!puedeFinalizar.value) {
        toast?.warning('Validación', 'Seleccione al menos un grupo de análisis para finalizar')
        return
    }
    
    if (!confirm('¿Estás seguro de finalizar el contenedor? Una vez activado no se podrá modificar.')) {
        return
    }
    
    finalizando.value = true
    
    try {
        const response = await axios.post(`/operacion/pedidos/clientes-mayoristas/contenedores/${contenedorId.value}/finalizar`)
        
        if (response.data.success) {
            toast?.success('Éxito', `Contenedor activado correctamente`)
            router.get('/operacion/pedidos/clientes-mayoristas/contenedores')
        } else {
            toast?.error('Error', response.data.message || 'Error al finalizar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al finalizar')
    } finally {
        finalizando.value = false
    }
}

// ==================== CANCELAR ====================
const cancelar = () => {
    if (cabeceraGuardada.value && !confirm('¿Estás seguro de salir? Los cambios no guardados se perderán.')) {
        return
    }
    router.get('/operacion/pedidos/clientes-mayoristas/contenedores')
}

// ==================== WATCH ====================
watch(
    () => props.contenedor,
    (nuevoContenedor) => {
        if (nuevoContenedor) {
            contenedorId.value = nuevoContenedor.IdContenedor
            cabeceraGuardada.value = true
            esBorrador.value = nuevoContenedor.ActivoInactivo === 0
            estaActivo.value = nuevoContenedor.ActivoInactivo === 1
            
            form.value = {
                IdSucursal: nuevoContenedor.IdSucursal || '',
                IdTipoContenedor: nuevoContenedor.IdTipoContenedor || '',
                CapacidadTotal: nuevoContenedor.CapacidadTotal || '',
            }
            
            const suc = props.sucursales.find(s => s.id === nuevoContenedor.IdSucursal)
            if (suc) {
                busquedaSucursal.value = `${suc.nombre} ${suc.numero ? `(N° ${suc.numero})` : ''}`
            }
            
            gruposSeleccionadosIds.value = [...props.gruposSeleccionados]
            totalProductosDisponibles.value = nuevoContenedor.total_productos || 0
        } else {
            contenedorId.value = null
            cabeceraGuardada.value = false
            esBorrador.value = true
            estaActivo.value = false
            gruposSeleccionadosIds.value = []
            totalProductosDisponibles.value = 0
        }
    },
    { immediate: true, deep: true }
)

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-boxes text-indigo-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">
                                {{ contenedorId ? 'Editar Contenedor' : 'Nuevo Contenedor' }}
                            </h1>
                            <p class="text-[10px] text-gray-500">
                                {{ cabeceraGuardada ? 'Paso 2: Seleccionar grupos' : 'Paso 1: Datos básicos' }}
                                <span v-if="cabeceraGuardada && esBorrador" class="text-yellow-600"> (BORRADOR)</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button 
                            v-if="!cabeceraGuardada"
                            @click="guardarCabecera"
                            :disabled="processing"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 flex-1 sm:flex-initial justify-center transition disabled:opacity-50"
                        >
                            <i v-if="processing" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ processing ? 'Guardando...' : 'Guardar Borrador' }}
                        </button>
                        
                        <button 
                            v-if="cabeceraGuardada && esBorrador"
                            @click="finalizarContenedor"
                            :disabled="finalizando || !puedeFinalizar"
                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 flex-1 sm:flex-initial justify-center transition disabled:opacity-50"
                            :title="!puedeFinalizar ? 'Seleccione al menos un grupo' : ''"
                        >
                            <i v-if="finalizando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-check-circle"></i>
                            {{ finalizando ? 'Finalizando...' : 'Finalizar Contenedor' }}
                        </button>
                        
                        <button 
                            @click="cancelar"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded text-xs transition flex-1 sm:flex-initial"
                        >
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- FORMULARIO CABECERA (PASO 1) -->
                <!-- ============================================ -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">
                        <i class="fas fa-info-circle text-indigo-500 mr-1"></i>
                        Datos del Contenedor
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                        <!-- Sucursal -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Sucursal *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaSucursal"
                                    @focus="mostrarDropdownSucursal = true"
                                    @blur="cerrarDropdownSucursal"
                                    placeholder="Buscar sucursal..."
                                    class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                    :class="{'border-red-500': errors.IdSucursal}"
                                    :disabled="cabeceraGuardada && !esBorrador"
                                />
                                <button 
                                    v-if="busquedaSucursal && !cabeceraGuardada"
                                    @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div 
                                    v-if="mostrarDropdownSucursal && sucursalesFiltradas.length > 0 && !cabeceraGuardada"
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-32 overflow-y-auto"
                                >
                                    <div
                                        v-for="s in sucursalesFiltradas"
                                        :key="s.id"
                                        @click="seleccionarSucursal(s)"
                                        class="px-2 py-1.5 hover:bg-indigo-50 cursor-pointer border-b text-xs"
                                        :class="{ 'bg-indigo-50': form.IdSucursal === s.id }"
                                    >
                                        {{ s.nombre }}
                                        <span v-if="s.numero" class="text-gray-400">(N° {{ s.numero }})</span>
                                    </div>
                                </div>
                            </div>
                            <p v-if="errors.IdSucursal" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdSucursal }}</p>
                        </div>

                        <!-- Tipo de Contenedor (CON BOTÓN AGREGAR) -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Tipo de Contenedor *</label>
                            <div class="flex gap-2">
                                <select 
                                    v-model="form.IdTipoContenedor" 
                                    class="flex-1 border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                    :class="{'border-red-500': errors.IdTipoContenedor}"
                                    :disabled="cabeceraGuardada && !esBorrador"
                                >
                                    <option value="">Seleccione</option>
                                    <option v-for="tipo in tiposContenedorLocal" :key="tipo.id" :value="tipo.id">
                                        {{ tipo.nombre }}
                                    </option>
                                </select>
                                
                                <button 
                                    @click="abrirModalTipo"
                                    type="button"
                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition flex-shrink-0"
                                    title="Crear nuevo tipo de contenedor"
                                    :disabled="cabeceraGuardada && !esBorrador"
                                >
                                    <i class="fas fa-plus text-[10px]"></i>
                                    <span class="hidden sm:inline">Nuevo</span>
                                </button>
                            </div>
                            <p v-if="errors.IdTipoContenedor" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdTipoContenedor }}</p>
                        </div>

                        <!-- Capacidad Máxima por Producto -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Capacidad Máxima *</label>
                            <input 
                                type="number" 
                                v-model="form.CapacidadTotal" 
                                step="0.01"
                                placeholder="0.00" 
                                class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                :class="{'border-red-500': errors.CapacidadTotal}"
                                :disabled="cabeceraGuardada && !esBorrador"
                            />
                            <p v-if="errors.CapacidadTotal" class="text-red-500 text-[10px] mt-0.5">{{ errors.CapacidadTotal }}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Límite máximo de unidades por producto</p>
                        </div>
                    </div>

                    <!-- Código generado automáticamente -->
                    <div v-if="codigoGenerado && !cabeceraGuardada" class="mt-3 bg-gray-50 rounded-lg p-2 border border-gray-200">
                        <p class="text-[10px] text-gray-500">Código generado automáticamente</p>
                        <p class="text-sm font-mono font-bold text-indigo-600">{{ codigoGenerado }}</p>
                    </div>

                    <!-- Estado del contenedor -->
                    <div v-if="cabeceraGuardada" class="mt-3 flex items-center gap-2 flex-wrap">
                        <span class="text-xs text-gray-500">Estado:</span>
                        <span v-if="esBorrador" class="px-2 py-0.5 text-[10px] rounded-full bg-yellow-100 text-yellow-800">
                            <i class="fas fa-pencil-alt mr-0.5"></i> BORRADOR
                        </span>
                        <span v-else-if="estaActivo" class="px-2 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-0.5"></i> ACTIVO
                        </span>
                        <span v-if="esBorrador" class="text-[10px] text-gray-400 ml-2">
                            (Seleccione los grupos)
                        </span>
                        <span v-if="gruposSeleccionadosIds.length > 0" class="text-[10px] text-blue-500 ml-2">
                            {{ gruposSeleccionadosIds.length }} grupo(s) seleccionados
                        </span>
                        <span v-if="totalProductosDisponibles > 0" class="text-[10px] text-green-600 ml-2">
                            {{ totalProductosDisponibles }} productos disponibles
                        </span>
                        <span class="text-[10px] text-indigo-600 ml-2 bg-indigo-50 px-2 py-0.5 rounded-full">
                            <i class="fas fa-limit"></i> Máx: {{ capacidadTotalNumero }} por producto
                        </span>
                        <span v-if="codigoGenerado" class="text-[10px] text-gray-500 ml-2 font-mono">
                            Código: {{ codigoGenerado }}
                        </span>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECCIÓN DE GRUPOS DE ANÁLISIS (PASO 2) -->
                <!-- ============================================ -->
                <div v-if="cabeceraGuardada && esBorrador" class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="text-sm font-semibold text-gray-700">
                            <i class="fas fa-layer-group text-indigo-500 mr-1"></i>
                            Grupos de Análisis
                            <span v-if="asignandoGrupos" class="text-[10px] text-gray-400 ml-2">
                                <i class="fas fa-spinner fa-spin"></i> Guardando...
                            </span>
                        </h2>
                        <span class="text-xs text-gray-500">
                            {{ gruposSeleccionadosIds.length }} seleccionado(s)
                            <span v-if="totalProductosDisponibles > 0" class="text-green-600 ml-1">
                                ({{ totalProductosDisponibles }} productos)
                            </span>
                        </span>
                    </div>

                    <p class="text-xs text-gray-500 mb-3">
                        <i class="fas fa-info-circle mr-1 text-indigo-400"></i>
                        Haz clic en un grupo para seleccionarlo o deseleccionarlo. Los cambios se guardan automáticamente.
                    </p>

                    <!-- Grid de grupos -->
                    <div class="flex flex-wrap gap-2 mb-3">
                        <div 
                            v-for="grupo in gruposAnalisis" 
                            :key="grupo.id"
                            @click="toggleGrupo(grupo.id)"
                            class="px-3 py-1.5 rounded-full text-xs cursor-pointer transition border"
                            :class="gruposSeleccionadosIds.includes(grupo.id) 
                                ? 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700' 
                                : 'bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200'"
                        >
                            <i v-if="gruposSeleccionadosIds.includes(grupo.id)" class="fas fa-check-circle mr-1"></i>
                            {{ grupo.nombre }}
                            <span v-if="asignandoGrupos && gruposSeleccionadosIds.includes(grupo.id)" class="ml-1">
                                <i class="fas fa-spinner fa-spin text-[8px]"></i>
                            </span>
                        </div>
                        <div v-if="gruposAnalisis.length === 0" class="text-xs text-gray-400 py-2">
                            No hay grupos de análisis disponibles
                        </div>
                    </div>

                    <!-- Estado de los grupos -->
                    <div class="flex flex-wrap items-center gap-3 mt-2 border-t pt-3 border-gray-100">
                        <div v-if="gruposSeleccionadosIds.length === 0" class="text-xs text-red-500">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            Seleccione al menos un grupo para poder finalizar el contenedor
                        </div>
                        <div v-else class="text-xs text-green-600">
                            <i class="fas fa-check-circle mr-1"></i>
                            {{ gruposSeleccionadosIds.length }} grupo(s) seleccionado(s)
                            <span class="text-gray-400 ml-2">✅ Listo para finalizar</span>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- MENSAJES DE ESTADO -->
                <!-- ============================================ -->

                <!-- Mensaje si está ACTIVO -->
                <div v-if="cabeceraGuardada && estaActivo" class="bg-green-50 rounded-lg p-4 text-green-800 text-sm text-center border border-green-200">
                    <i class="fas fa-check-circle mr-1 text-green-600"></i>
                    Este contenedor ya está <strong>ACTIVO</strong>.
                    <span v-if="totalProductosDisponibles > 0" class="block text-xs mt-1 text-green-600">
                        {{ totalProductosDisponibles }} productos disponibles para pedidos.
                    </span>
                </div>

                <!-- Mensaje si cabecera no guardada -->
                <div v-if="!cabeceraGuardada" class="bg-amber-50 rounded-lg p-3 text-amber-800 text-xs text-center border border-amber-200">
                    <i class="fas fa-info-circle mr-1"></i>
                    Complete todos los campos y presione "Guardar Borrador" para continuar con la selección de grupos.
                </div>

                <!-- Mensaje de éxito (grupos seleccionados) -->
                <div v-if="cabeceraGuardada && esBorrador && gruposSeleccionadosIds.length > 0" class="bg-green-50 rounded-lg p-3 text-green-700 text-xs text-center border border-green-200">
                    <i class="fas fa-check-circle mr-1"></i>
                    {{ gruposSeleccionadosIds.length }} grupo(s) seleccionado(s).
                    <span class="block mt-1">¡Presione "Finalizar Contenedor" para activarlo!</span>
                </div>

                <!-- Información de pasos -->
                <div v-if="cabeceraGuardada && esBorrador" class="mt-3 text-center text-[10px] text-gray-400">
                    <i class="fas fa-arrow-right mr-1"></i>
                    Paso 2 de 2: Seleccione los grupos de análisis (los cambios se guardan automáticamente) y finalice el contenedor.
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- MODAL: CREAR TIPO DE CONTENEDOR -->
        <!-- ============================================ -->
        <CreateTipoContenedor 
            :visible="modalTipoVisible"
            :sucursales="sucursales"
            @close="cerrarModalTipo"
            @created="onTipoCreado"
        />
    </div>
</template>

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

[v-show] {
    transition: all 0.2s ease;
}
</style>