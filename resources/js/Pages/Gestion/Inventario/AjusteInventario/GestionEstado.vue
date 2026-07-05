<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted, computed, inject } from 'vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    ajustes: Object,
    sucursales: Array,
    sucursalActual: Number,
    filtroEstado: String,
    buscar: String,
    sucursalSeleccionada: String
})

// =============================================
// ESTADO DE FILTROS
// =============================================

// 🔥 Sucursal - Autocomplete
const sucursalId = ref(props.sucursalSeleccionada || props.sucursalActual || '')
const sucursalBusqueda = ref('')
const mostrarSucursales = ref(false)

const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')

// =============================================
// COMPUTADOS - Autocomplete
// =============================================

const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    if (!sucursalBusqueda.value) return props.sucursales
    
    const termino = sucursalBusqueda.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.nombre?.toLowerCase().includes(termino) ||
        (s.numero && s.numero.toString().includes(termino))
    )
})

const sucursalNombre = computed(() => {
    if (!sucursalId.value) return ''
    const suc = props.sucursales?.find(s => s.id == sucursalId.value)
    return suc?.nombre || ''
})

// =============================================
// ESTADO PARA CONTRAER/EXPANDIR
// =============================================
const sucursalesExpandidas = ref({})

const inicializarExpandidas = () => {
    const grupos = ajustesAgrupados.value
    Object.keys(grupos).forEach(id => {
        sucursalesExpandidas.value[id] = true
    })
}

const toggleSucursal = (id) => {
    if (sucursalesExpandidas.value[id] !== undefined) {
        sucursalesExpandidas.value[id] = !sucursalesExpandidas.value[id]
    }
}

const expandirTodas = () => {
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        sucursalesExpandidas.value[id] = true
    })
}

const contraerTodas = () => {
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        sucursalesExpandidas.value[id] = false
    })
}

// =============================================
// AGRUPACIÓN POR SUCURSAL
// =============================================
const ajustesAgrupados = computed(() => {
    if (!props.ajustes?.data) return {}
    
    const grupos = {}
    
    props.ajustes.data.forEach(ajuste => {
        const sucursalNombre = ajuste.sucursal_nombre || 'Sin sucursal'
        const sucursalId = ajuste.IdSucursal || 0
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                ajustes: [],
                total: 0
            }
        }
        
        grupos[sucursalId].ajustes.push(ajuste)
        grupos[sucursalId].total += 1
    })
    
    return grupos
})

const sucursalesConAjustes = computed(() => {
    return Object.values(ajustesAgrupados.value)
})

const actualizarExpandidas = () => {
    const grupos = ajustesAgrupados.value
    const idsActuales = Object.keys(grupos)
    
    idsActuales.forEach(id => {
        if (sucursalesExpandidas.value[id] === undefined) {
            sucursalesExpandidas.value[id] = true
        }
    })
    
    Object.keys(sucursalesExpandidas.value).forEach(id => {
        if (!idsActuales.includes(id)) {
            delete sucursalesExpandidas.value[id]
        }
    })
}

// =============================================
// ACCIONES
// =============================================
const aplicarFiltros = () => {
    const params = {
        sucursal_id: sucursalId.value || undefined,
        estado: estadoFiltro.value || undefined,
        buscar: buscador.value || undefined
    }
    
    router.get('/gestion/inventario/ajustes/gestion-estado', params, {
        preserveState: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                actualizarExpandidas()
            }, 100)
        }
    })
}

const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    sucursalBusqueda.value = sucursal.nombre
    mostrarSucursales.value = false
    aplicarFiltros()
}

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalBusqueda.value = ''
    mostrarSucursales.value = false
    aplicarFiltros()
}

let timeoutBuscador
const buscarAjustes = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

// Cerrar autocompletes
const handleClickOutside = (event) => {
    const container = document.querySelector('.sucursal-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarSucursales.value = false
    }
}

// =============================================
// ESTADO
// =============================================
const cambiando = ref({})
const loading = ref(false)
const isMobile = ref(window.innerWidth < 768)

// Modal
const modalVisible = ref(false)
const modalData = ref({
    id: null,
    numero: null
})

// =============================================
// TOAST
// =============================================
const mostrarToast = (mensaje, tipo = 'success') => {
    const toastAnterior = document.querySelector('.custom-toast')
    if (toastAnterior) toastAnterior.remove()
    
    const colores = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
        warning: 'bg-yellow-500'
    }
    const iconos = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle'
    }
    
    const toast = document.createElement('div')
    toast.className = `custom-toast fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white flex items-center gap-2 ${colores[tipo] || 'bg-blue-500'}`
    toast.innerHTML = `<i class="fas ${iconos[tipo] || 'fa-info-circle'}"></i> ${mensaje}`
    document.body.appendChild(toast)
    setTimeout(() => {
        if (toast && toast.remove) toast.remove()
    }, 4000)
}

// =============================================
// SWITCH / MODAL
// =============================================
const toggleSwitch = (ajuste) => {
    if (ajuste.ActivoInactivo === 0) {
        mostrarToast('Este ajuste está en estado BORRADOR. Solo se activa al editarlo y guardarlo.', 'info')
        return
    }
    
    if (cambiando.value[ajuste.IdAjustesPrincipal]) return
    abrirModalConfirmacion(ajuste)
}

const abrirModalConfirmacion = (ajuste) => {
    modalData.value = {
        id: ajuste.IdAjustesPrincipal,
        numero: ajuste.NumeroCorrelativo
    }
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    modalData.value = { id: null, numero: null }
}

const ejecutarCambioEstado = async () => {
    if (!modalData.value.id) return
    
    cambiando.value[modalData.value.id] = true
    loading.value = true
    
    try {
        const response = await fetch(`/gestion/inventario/ajustes/${modalData.value.id}/cambiar-estado`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                estado: 0
            })
        })
        
        const data = await response.json()
        
        if (data.success) {
            mostrarToast(data.message, 'success')
            aplicarFiltros()
        } else {
            mostrarToast(data.message, 'error')
            cerrarModal()
        }
    } catch (error) {
        console.error('Error:', error)
        mostrarToast('Error al cambiar el estado', 'error')
        cerrarModal()
    } finally {
        cambiando.value[modalData.value.id] = false
        loading.value = false
    }
}

// =============================================
// UTILIDADES
// =============================================
const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
}

const getEstadoIcono = (activo) => {
    return activo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Contabilizado' : 'Borrador'
}

const getConceptoColor = (concepto) => {
    return concepto === 'Ingreso' || concepto === 'INGRESO' ? 'text-emerald-600 font-bold' : 'text-red-600 font-bold'
}

const puedeDesactivar = (ajuste) => {
    return ajuste.ActivoInactivo === 1
}

// =============================================
// CICLO DE VIDA
// =============================================
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
    
    if (sucursalId.value) {
        const sucursal = props.sucursales?.find(s => s.id == sucursalId.value)
        if (sucursal) {
            sucursalBusqueda.value = sucursal.nombre
        }
    }
    
    setTimeout(() => {
        inicializarExpandidas()
    }, 100)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Gestión de Estados - Ajustes</h1>
                            <p class="text-[10px] text-gray-500">Desactivar ajustes de inventario (pasar a Borrador)</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/gestion/inventario/ajustes" class="flex-1 sm:flex-initial bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-list text-[10px]"></i>
                            <span>Listado</span>
                        </Link>
                        <Link href="/gestion/inventario/ajustes/create" class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-plus text-[10px]"></i>
                            <span>Nuevo Ajuste</span>
                        </Link>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        
                        <!-- 🔥 Sucursal - Autocomplete -->
                        <div class="sucursal-autocomplete flex items-center gap-1">
                            <label class="text-xs font-medium text-gray-700">Sucursal:</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="sucursalBusqueda"
                                    @focus="mostrarSucursales = true"
                                    @input="mostrarSucursales = true"
                                    class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-36 sm:w-44 pr-6 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="Todas..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="sucursalBusqueda"
                                    @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                
                                <div v-if="mostrarSucursales && sucursalesDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto min-w-[180px]">
                                    <div 
                                        v-for="suc in sucursalesDisponibles" 
                                        :key="suc.id"
                                        @click="seleccionarSucursal(suc)"
                                        class="px-3 py-1.5 cursor-pointer hover:bg-primary-50 text-xs flex justify-between items-center border-b border-gray-100 last:border-0"
                                        :class="sucursalId == suc.id ? 'bg-primary-50' : ''"
                                    >
                                        <span class="truncate">{{ suc.nombre }}</span>
                                        <span v-if="sucursalId == suc.id" class="text-primary-600">
                                            <i class="fas fa-check-circle text-[10px]"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div v-else-if="mostrarSucursales && sucursalesDisponibles.length === 0 && sucursalBusqueda" 
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-2 text-center text-gray-500 text-xs">
                                    <i class="fas fa-search mr-1"></i> No se encontraron sucursales
                                </div>
                            </div>
                            <span v-if="sucursalId && sucursalNombre" class="text-[10px] text-primary-600 font-medium ml-1">
                                <i class="fas fa-check-circle"></i> {{ sucursalNombre }}
                            </span>
                            <span v-else-if="!sucursalId" class="text-[10px] text-gray-400 ml-1">
                                <i class="fas fa-store"></i> Todas
                            </span>
                        </div>
                        
                        <!-- Estado -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-32 sm:w-36 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Todos</option>
                                <option value="activos">Contabilizados</option>
                                <option value="inactivos">Borradores</option>
                            </select>
                        </div>
                        
                        <!-- Buscador -->
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarAjustes"
                                placeholder="N° Ajuste..."
                                class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-28 sm:w-32 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                            <button 
                                v-if="buscador" 
                                @click="limpiarBusqueda" 
                                class="text-gray-400 hover:text-gray-600 text-xs"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <!-- Botones Expandir/Contraer -->
                        <div class="flex gap-1 ml-auto">
                            <button @click="expandirTodas" class="text-[10px] bg-primary-100 hover:bg-primary-200 text-primary-700 px-2 py-1 rounded transition">
                                <i class="fas fa-plus-circle"></i> Expandir
                            </button>
                            <button @click="contraerTodas" class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-1 rounded transition">
                                <i class="fas fa-minus-circle"></i> Contraer
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="buscador" class="mt-2 text-[10px] text-gray-500">
                        <span class="font-semibold">{{ buscador }}</span>
                        <span class="ml-2">({{ ajustes.total || 0 }} resultados)</span>
                    </div>
                    
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> Solo se pueden desactivar ajustes contabilizados (pasar a Borrador)
                    </div>
                </div>

                <!-- ============================================= -->
                <!-- GRID AGRUPADA POR SUCURSAL -->
                <!-- ============================================= -->
                <div v-if="sucursalesConAjustes.length > 0">
                    <div v-for="grupo in sucursalesConAjustes" :key="grupo.id" class="mb-3">
                        
                        <!-- Encabezado de Sucursal -->
                        <div 
                            @click="toggleSucursal(grupo.id)"
                            class="flex flex-wrap items-center justify-between gap-2 bg-primary-50 rounded-t-lg px-3 sm:px-4 py-2 border border-primary-200 cursor-pointer hover:bg-primary-100 transition"
                        >
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <i 
                                    class="text-primary-600 text-[10px] sm:text-sm transition-transform duration-200 flex-shrink-0"
                                    :class="sucursalesExpandidas[grupo.id] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                ></i>
                                <i class="fas fa-store text-primary-600 text-xs sm:text-sm flex-shrink-0"></i>
                                <h2 class="font-bold text-primary-800 text-xs sm:text-sm truncate">{{ grupo.nombre }}</h2>
                                <span class="text-[9px] sm:text-xs text-primary-600 bg-primary-100 px-1.5 sm:px-2 py-0.5 rounded-full flex-shrink-0">
                                    {{ grupo.ajustes.length }}
                                </span>
                            </div>
                            <div class="text-xs sm:text-sm font-bold text-primary-700 flex-shrink-0">
                                {{ grupo.total }} ajustes
                            </div>
                        </div>

                        <!-- Tabla de ajustes -->
                        <transition 
                            enter-active-class="transition-all duration-300 ease-in-out"
                            enter-from-class="max-h-0 opacity-0 overflow-hidden"
                            enter-to-class="max-h-[5000px] opacity-100 overflow-hidden"
                            leave-active-class="transition-all duration-300 ease-in-out"
                            leave-from-class="max-h-[5000px] opacity-100 overflow-hidden"
                            leave-to-class="max-h-0 opacity-0 overflow-hidden"
                        >
                            <div v-if="sucursalesExpandidas[grupo.id]" class="bg-white rounded-b-lg shadow-sm overflow-hidden border border-t-0 border-primary-200">
                                
                                <!-- DESKTOP: Tabla -->
                                <div class="hidden md:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Ajuste</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Concepto</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo Operación</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Almacén</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acción</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">PDF</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="ajuste in grupo.ajustes" :key="ajuste.IdAjustesPrincipal" class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">{{ ajuste.NumeroCorrelativo }}</td>
                                                <td class="px-3 py-2 text-xs text-gray-500">{{ ajuste.fecha_formateada || '-' }}</td>
                                                <td class="px-3 py-2 text-xs">
                                                    <span class="font-bold" :class="getConceptoColor(ajuste.ConceptoOperacion)">
                                                        {{ ajuste.ConceptoOperacion || '-' }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-xs text-gray-700">{{ ajuste.tipo_operacion?.Detalle || '-' }}</td>
                                                <td class="px-3 py-2 text-xs text-gray-700">{{ ajuste.almacen?.Almacen || '-' }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(ajuste.ActivoInactivo)">
                                                        <i :class="getEstadoIcono(ajuste.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                                        {{ getEstadoTexto(ajuste.ActivoInactivo) }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <div v-if="puedeDesactivar(ajuste)" class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(ajuste)">
                                                        <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out bg-primary-600">
                                                            <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out translate-x-[18px]">
                                                            </div>
                                                        </div>
                                                        <span class="ml-2 text-[10px]" :class="cambiando[ajuste.IdAjustesPrincipal] ? 'text-gray-400' : 'text-green-600'">
                                                            <i v-if="cambiando[ajuste.IdAjustesPrincipal]" class="fas fa-spinner fa-spin"></i>
                                                            <span v-else>Activo</span>
                                                        </span>
                                                    </div>
                                                    <span v-else class="text-[10px] text-gray-400">
                                                        <i class="fas fa-lock mr-1"></i> Borrador
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <a :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}/pdf`" target="_blank" class="text-red-600 hover:text-red-800" title="PDF">
                                                        <i class="fas fa-file-pdf text-sm"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- MÓVIL: Tarjetas -->
                                <div class="md:hidden divide-y divide-gray-100">
                                    <div v-for="ajuste in grupo.ajustes" :key="ajuste.IdAjustesPrincipal" class="p-3 hover:bg-gray-50 transition">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-bold text-primary-700 text-sm">N° {{ ajuste.NumeroCorrelativo }}</span>
                                                    <span class="text-[10px] font-bold" :class="getConceptoColor(ajuste.ConceptoOperacion)">
                                                        {{ ajuste.ConceptoOperacion || '-' }}
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    {{ ajuste.fecha_formateada || '-' }}
                                                </div>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    <span class="text-gray-400">Tipo:</span> {{ ajuste.tipo_operacion?.Detalle || '-' }}
                                                </div>
                                                <div class="text-xs text-gray-600 mt-0.5">
                                                    <span class="text-gray-400">Almacén:</span> {{ ajuste.almacen?.Almacen || '-' }}
                                                </div>
                                                <div class="flex items-center gap-3 mt-1">
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(ajuste.ActivoInactivo)">
                                                        <i :class="getEstadoIcono(ajuste.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                                        {{ getEstadoTexto(ajuste.ActivoInactivo) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                                <a :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}/pdf`" target="_blank" class="text-red-600" title="PDF">
                                                    <i class="fas fa-file-pdf text-lg"></i>
                                                </a>
                                                <div v-if="puedeDesactivar(ajuste)" class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(ajuste)">
                                                    <div class="w-8 h-4 rounded-full transition-colors duration-200 ease-in-out bg-primary-600">
                                                        <div class="absolute w-3.5 h-3.5 bg-white rounded-full top-[1px] transition-transform duration-200 ease-in-out translate-x-[16px]">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </transition>
                    </div>
                </div>

                <!-- Mensaje sin datos -->
                <div v-else class="bg-white rounded-lg shadow-sm p-6 sm:p-8 text-center text-gray-500">
                    <i class="fas fa-clipboard-list text-3xl sm:text-4xl block mb-2 text-gray-300"></i>
                    <p class="text-sm sm:text-base">
                        <span v-if="buscador">No hay ajustes que coincidan con "{{ buscador }}"</span>
                        <span v-else>No hay ajustes registrados</span>
                    </p>
                </div>

                <!-- Paginación -->
                <div v-if="props.ajustes?.data?.length" class="bg-white rounded-lg shadow-sm mt-4 px-3 sm:px-4 py-2 border border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-2">
                    <p class="text-[10px] sm:text-xs text-gray-500">
                        Mostrando {{ props.ajustes.from || 0 }} - {{ props.ajustes.to || 0 }} de {{ props.ajustes.total || 0 }}
                    </p>
                    <div class="flex gap-1 flex-wrap justify-center">
                        <Link v-for="link in props.ajustes.links" :key="link.label" :href="link.url || '#'" class="px-2 sm:px-2.5 py-1 rounded text-[10px] sm:text-xs transition" :class="{ 'bg-primary-600 text-white': link.active, 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE CONFIRMACIÓN -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarModal">
            <div class="bg-white rounded-xl w-full max-w-[90%] sm:max-w-sm overflow-hidden shadow-xl">
                <div class="p-4 border-b bg-yellow-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-yellow-100">
                            <i class="fas fa-ban text-yellow-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 text-sm sm:text-base">Desactivar Ajuste</h3>
                            <p class="text-[10px] sm:text-xs text-gray-500">Ajuste N° {{ modalData.numero }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-700 text-center">
                        ¿Estás seguro de <span class="font-bold text-red-600">DESACTIVAR</span> este ajuste?
                    </p>
                    <p class="text-[10px] sm:text-xs text-gray-400 text-center mt-2">
                        Al desactivarlo, el ajuste volverá a estado BORRADOR y podrá editarse.
                    </p>
                </div>
                <div class="p-3 sm:p-4 bg-gray-50 flex justify-end gap-2 sm:gap-3">
                    <button @click="cerrarModal" class="px-3 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button @click="ejecutarCambioEstado" :disabled="loading" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition flex items-center gap-2 bg-yellow-600 hover:bg-yellow-700">
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-ban"></i>
                        Desactivar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:inline { display: inline; }
    .xs\:block { display: block; }
}

.custom-toast {
    max-width: 90%;
    z-index: 9999;
}

/* Transición para expandir/contraer */
.max-h-0 {
    max-height: 0;
}
.max-h-\[5000px\] {
    max-height: 5000px;
}
.transition-all {
    transition-property: all;
}
.duration-300 {
    transition-duration: 300ms;
}
.ease-in-out {
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
.overflow-hidden {
    overflow: hidden;
}

/* Autocomplete */
.sucursal-autocomplete {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}
</style>