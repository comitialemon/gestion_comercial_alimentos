<script setup>
import { ref, computed, onMounted, inject, watch } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    configuraciones: {
        type: Object,
        default: () => ({ data: [], links: [], from: null, to: null, total: 0 })
    },
    sucursales: {
        type: Array,
        default: () => []
    },
    sucursalSeleccionada: {
        type: Number,
        default: null
    }
})

// Estado
const sucursalId = ref(props.sucursalSeleccionada || '')
const busquedaSucursal = ref('')
const mostrarDropdown = ref(false)
const editando = ref(false)
const editId = ref(null)
const formData = ref({ 
    sucursal_id: '',
    cantidad_productos: ''
})
const errors = ref({})
const processing = ref(false)

// 🔥 ESTADO PARA AGRUPACIÓN POR SUCURSAL
const sucursalesExpandidas = ref({})

// 🔥 Watch para mantener sincronizado formData.sucursal_id con sucursalId
watch(sucursalId, (newVal) => {
    if (newVal) {
        formData.value.sucursal_id = newVal
    }
})

// 🔥 Agrupar configuraciones por sucursal
const configuracionesAgrupadas = computed(() => {
    if (!props.configuraciones.data || props.configuraciones.data.length === 0) {
        return []
    }
    
    const grupos = {}
    
    props.configuraciones.data.forEach(item => {
        const sucursalId = item.IdSucursal
        const sucursalNombre = item.sucursal?.Nombre || 'Sin sucursal'
        const sucursalNumero = item.sucursal?.NumeroSucursal
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                numero: sucursalNumero,
                items: []
            }
            if (sucursalesExpandidas.value[sucursalId] === undefined) {
                sucursalesExpandidas.value[sucursalId] = true
            }
        }
        grupos[sucursalId].items.push(item)
    })
    
    return Object.values(grupos)
})

// 🔥 Alternar expansión de una sucursal
const toggleSucursal = (sucursalId) => {
    sucursalesExpandidas.value[sucursalId] = !sucursalesExpandidas.value[sucursalId]
}

const isExpandida = (sucursalId) => {
    return sucursalesExpandidas.value[sucursalId] !== false
}

const expandirTodas = () => {
    configuracionesAgrupadas.value.forEach(grupo => {
        sucursalesExpandidas.value[grupo.id] = true
    })
}

const contraerTodas = () => {
    configuracionesAgrupadas.value.forEach(grupo => {
        sucursalesExpandidas.value[grupo.id] = false
    })
}

const totalRegistros = computed(() => {
    return props.configuraciones.data?.length || 0
})

onMounted(() => {
    const flashSuccess = page.props.flash?.success
    const flashError = page.props.flash?.error
    
    if (flashSuccess && toast) toast.success('Éxito', flashSuccess)
    if (flashError && toast) toast.error('Error', flashError)
    
    // 🔥 Si hay sucursal seleccionada, mostrarla
    if (props.sucursalSeleccionada) {
        const suc = (props.sucursales || []).find(s => s.id === props.sucursalSeleccionada)
        if (suc) {
            sucursalId.value = props.sucursalSeleccionada
            busquedaSucursal.value = `${suc.nombre} ${suc.NumeroSucursal ? `(N° ${suc.NumeroSucursal})` : ''}`
            formData.value.sucursal_id = props.sucursalSeleccionada
        }
    } else {
        // 🔥 Si NO hay sucursal seleccionada, mostrar TODAS
        busquedaSucursal.value = ''
        sucursalId.value = ''
        formData.value.sucursal_id = ''
    }
    
    resetForm()
    
    // 🔥 DEBUG: Verificar los datos
    console.log('Configuraciones:', props.configuraciones)
    console.log('Configuraciones data:', props.configuraciones?.data)
    console.log('Sucursal seleccionada:', props.sucursalSeleccionada)
})

const sucursalesFiltradas = computed(() => {
    if (!busquedaSucursal.value) return props.sucursales || []
    const termino = busquedaSucursal.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.NumeroSucursal?.toString().includes(termino)
    )
})

// Cerrar dropdown con delay para permitir clicks
const cerrarDropdown = () => {
    setTimeout(() => {
        mostrarDropdown.value = false
    }, 200)
}

// 🔥 CORREGIDO: Seleccionar TODAS las sucursales
const seleccionarTodas = () => {
    sucursalId.value = ''
    busquedaSucursal.value = 'Todas las sucursales'
    mostrarDropdown.value = false
    formData.value.sucursal_id = ''
    
    // Recargar sin filtro de sucursal
    router.get('/gestion/inventario/inventario-fisico-diario/config', {}, {
        preserveState: true,
        replace: true,
        onSuccess: () => {
            // Limpiar el estado visual
            setTimeout(() => {
                busquedaSucursal.value = ''
                sucursalId.value = ''
            }, 100)
        }
    })
}

// 🔥 Seleccionar una sucursal específica
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    busquedaSucursal.value = `${sucursal.nombre} ${sucursal.NumeroSucursal ? `(N° ${sucursal.NumeroSucursal})` : ''}`
    mostrarDropdown.value = false
    formData.value.sucursal_id = sucursal.id
    
    // Recargar con el filtro de sucursal
    router.get('/gestion/inventario/inventario-fisico-diario/config', 
        { sucursal_id: sucursal.id }, 
        {
            preserveState: true,
            replace: true,
        }
    )
}

const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = { 
        sucursal_id: '',
        cantidad_productos: ''
    }
    errors.value = {}
}

const editar = (item) => {
    editando.value = true
    editId.value = item.IdConfig
    formData.value = {
        sucursal_id: item.IdSucursal,
        cantidad_productos: item.CantidadProductos
    }
    if (item.IdSucursal) {
        const suc = (props.sucursales || []).find(s => s.id === item.IdSucursal)
        if (suc) {
            busquedaSucursal.value = `${suc.nombre} ${suc.NumeroSucursal ? `(N° ${suc.NumeroSucursal})` : ''}`
            sucursalId.value = item.IdSucursal
        }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const guardar = async () => {
    if (!formData.value.sucursal_id) {
        if (toast) toast.error('Validación', 'Seleccione una sucursal')
        return
    }
    
    if (!formData.value.cantidad_productos || formData.value.cantidad_productos <= 0) {
        if (toast) toast.error('Validación', 'Ingrese una cantidad válida de productos')
        return
    }
    
    if (formData.value.cantidad_productos > 100) {
        if (toast) toast.error('Validación', 'La cantidad no puede ser mayor a 100')
        return
    }
    
    // Validar duplicado
    let validarDuplicado = true
    
    if (editando.value) {
        const configActual = props.configuraciones?.data?.find(item => item.IdConfig === editId.value)
        if (configActual && configActual.IdSucursal === formData.value.sucursal_id) {
            validarDuplicado = false
        }
    }
    
    if (validarDuplicado) {
        const existe = props.configuraciones?.data?.some(item => 
            item.IdSucursal === formData.value.sucursal_id && 
            (!editando.value || item.IdConfig !== editId.value)
        )
        
        if (existe) {
            if (toast) toast.error('Error', '⚠️ Ya existe una configuración para esta sucursal')
            return
        }
    }
    
    processing.value = true
    
    const datos = {
        IdSucursal: formData.value.sucursal_id,
        CantidadProductos: formData.value.cantidad_productos
    }
    
    try {
        if (editando.value) {
            await router.put(`/gestion/inventario/inventario-fisico-diario/config/${editId.value}`, datos, {
                preserveScroll: true,
                onSuccess: () => {
                    if (toast) toast.success('Éxito', 'Configuración actualizada correctamente')
                    resetForm()
                    // Recargar sin filtro para mostrar todas
                    router.get('/gestion/inventario/inventario-fisico-diario/config', {}, { preserveState: true })
                },
                onError: (err) => {
                    if (toast) toast.error('Error', Object.values(err)[0]?.[0] || 'Error al actualizar')
                }
            })
        } else {
            await router.post('/gestion/inventario/inventario-fisico-diario/config', datos, {
                preserveScroll: true,
                onSuccess: () => {
                    if (toast) toast.success('Éxito', 'Configuración creada correctamente')
                    resetForm()
                    // Recargar sin filtro para mostrar todas
                    router.get('/gestion/inventario/inventario-fisico-diario/config', {}, { preserveState: true })
                },
                onError: (err) => {
                    if (toast) toast.error('Error', Object.values(err)[0]?.[0] || 'Error al guardar')
                }
            })
        }
    } finally {
        processing.value = false
    }
}

const cambiarEstado = async (id, estadoActual) => {
    const accion = estadoActual === 1 ? 'desactivar' : 'activar'
    if (!confirm(`¿Estás seguro de ${accion} esta configuración?`)) {
        return
    }
    
    try {
        await router.patch(`/gestion/inventario/inventario-fisico-diario/config/${id}/toggle`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (toast) toast.success('Éxito', `Configuración ${accion}da correctamente`)
            },
            onError: (err) => {
                if (toast) toast.error('Error', Object.values(err)[0]?.[0] || 'Error al cambiar estado')
            }
        })
    } catch (error) {
        if (toast) toast.error('Error', 'Error al cambiar estado')
    }
}

const eliminar = async (id) => {
    if (!confirm('¿Estás seguro de eliminar esta configuración? Solo se puede eliminar si está inactiva.')) {
        return
    }
    
    try {
        await router.delete(`/gestion/inventario/inventario-fisico-diario/config/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (toast) toast.success('Éxito', 'Configuración eliminada correctamente')
            },
            onError: (err) => {
                if (toast) toast.error('Error', Object.values(err)[0]?.[0] || 'Error al eliminar')
            }
        })
    } catch (error) {
        if (toast) toast.error('Error', 'Error al eliminar')
    }
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    const d = new Date(fecha)
    return d.toLocaleDateString('es-BO', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">Inventario Físico Diario</h1>
                            <p class="text-[10px] text-gray-500">Configuración de productos a contar por sucursal</p>
                        </div>
                    </div>
                    <div v-if="!sucursalId && configuracionesAgrupadas.length > 0" class="flex gap-2">
                        <button 
                            @click="expandirTodas" 
                            class="px-3 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center"
                        >
                            <i class="fas fa-plus-circle mr-1"></i> Expandir todo
                        </button>
                        <button 
                            @click="contraerTodas" 
                            class="px-3 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center"
                        >
                            <i class="fas fa-minus-circle mr-1"></i> Contraer todo
                        </button>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6 sticky top-2 z-10">
                    <div class="flex flex-col sm:flex-row items-end gap-3">
                        <!-- Sucursal -->
                        <div class="w-full sm:w-64 flex-shrink-0">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Sucursal *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaSucursal"
                                    @focus="mostrarDropdown = true"
                                    @blur="cerrarDropdown"
                                    placeholder="Seleccione Sucursal..."
                                    class="w-full border rounded-md px-3 py-2 text-sm pr-8"
                                    :class="{ 'border-red-500': errors.sucursal_id }"
                                    autocomplete="off"
                                >
                                <button 
                                    v-if="busquedaSucursal"
                                    @click="busquedaSucursal = ''; sucursalId = ''; formData.sucursal_id = ''"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div 
                                    v-if="mostrarDropdown"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <!-- 🔥 Siempre mostrar "Todas" -->
                                    <div
                                        @mousedown.prevent="seleccionarTodas"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b text-sm font-medium text-primary-600"
                                    >
                                        <i class="fas fa-warehouse mr-2"></i> Todas las sucursales
                                    </div>
                                    
                                    <div
                                        v-for="s in sucursalesFiltradas"
                                        :key="s.id"
                                        @mousedown.prevent="seleccionarSucursal(s)"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-sm"
                                        :class="{ 'bg-primary-50': sucursalId === s.id }"
                                    >
                                        {{ s.nombre }} 
                                        <span v-if="s.NumeroSucursal" class="text-gray-400 text-xs">(N° {{ s.NumeroSucursal }})</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cantidad -->
                        <div class="w-full sm:w-48 flex-shrink-0">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cantidad Productos *</label>
                            <input 
                                type="number" 
                                v-model="formData.cantidad_productos" 
                                placeholder="Ingrese cantidad"
                                min="1"
                                max="100"
                                class="w-full border rounded-md px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.cantidad_productos }"
                                @keyup.enter="guardar"
                            />
                        </div>

                        <!-- Botón Crear -->
                        <div class="w-full sm:w-auto flex-shrink-0">
                            <label class="block text-xs font-medium text-gray-700 mb-1 invisible">Acción</label>
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.sucursal_id || !formData.cantidad_productos"
                                class="w-full sm:w-auto px-6 py-2 bg-primary-600 text-white rounded-md text-sm hover:bg-primary-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-plus'" class="text-xs"></i>
                                {{ processing ? 'Procesando...' : (editando ? 'Actualizar' : 'Crear') }}
                            </button>
                        </div>

                        <!-- Cancelar -->
                        <div v-if="editando" class="w-full sm:w-auto flex-shrink-0">
                            <label class="block text-xs font-medium text-gray-700 mb-1 invisible">Cancelar</label>
                            <button 
                                @click="resetForm" 
                                class="w-full sm:w-auto px-6 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300 transition flex items-center justify-center gap-2"
                            >
                                <i class="fas fa-times text-xs"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 🔥 VISTA AGRUPADA (si NO hay sucursal seleccionada) -->
                <div v-if="!sucursalId" class="space-y-3">
                    <div class="text-xs text-gray-500 mb-2">
                        <i class="fas fa-clipboard-list mr-1"></i> 
                        {{ totalRegistros }} configuraciones en {{ configuracionesAgrupadas.length }} sucursales
                    </div>

                    <div 
                        v-for="grupo in configuracionesAgrupadas" 
                        :key="grupo.id" 
                        class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-200"
                    >
                        <div 
                            @click="toggleSucursal(grupo.id)"
                            class="flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 cursor-pointer transition border-b border-gray-200"
                        >
                            <div class="flex items-center gap-3">
                                <i :class="isExpandida(grupo.id) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-gray-400 text-xs transition-transform"></i>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-store text-primary-500 text-sm"></i>
                                    <span class="font-semibold text-gray-800 text-sm">{{ grupo.nombre }}</span>
                                    <span v-if="grupo.numero" class="text-xs text-gray-400">(N° {{ grupo.numero }})</span>
                                    <span class="text-xs bg-gray-200 px-2 py-0.5 rounded-full text-gray-600">
                                        {{ grupo.items.length }} registros
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <i :class="isExpandida(grupo.id) ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="text-gray-400 text-xs"></i>
                            </div>
                        </div>

                        <div v-show="isExpandida(grupo.id)" class="overflow-x-auto transition-all">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">#</th>
                                        <th class="px-4 py-2 text-center text-[10px] font-semibold text-gray-500 uppercase">Cantidad</th>
                                        <th class="px-4 py-2 text-center text-[10px] font-semibold text-gray-500 uppercase">Estado</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">Creado</th>
                                        <th class="px-4 py-2 text-right text-[10px] font-semibold text-gray-500 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="item in grupo.items" :key="item.IdConfig" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 font-mono">{{ item.IdConfig }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-bold bg-primary-100 text-primary-700">
                                                {{ item.CantidadProductos }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-center">
                                            <span 
                                                class="inline-flex items-center px-2 py-0.5 text-[10px] rounded-full"
                                                :class="item.ActivoInactivo === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                            >
                                                <i :class="item.ActivoInactivo === 1 ? 'fas fa-check-circle mr-0.5' : 'fas fa-times-circle mr-0.5'" class="text-[8px]"></i>
                                                {{ item.ActivoInactivo === 1 ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                            {{ formatearFecha(item.FechaIngreso) }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <button 
                                                    @click="editar(item)" 
                                                    class="text-primary-600 hover:text-primary-800 transition p-1 hover:bg-primary-50 rounded" 
                                                    title="Editar"
                                                >
                                                    <i class="fas fa-edit text-xs"></i>
                                                </button>
                                                <button 
                                                    @click="cambiarEstado(item.IdConfig, item.ActivoInactivo)" 
                                                    class="text-yellow-600 hover:text-yellow-800 transition p-1 hover:bg-yellow-50 rounded" 
                                                    :title="item.ActivoInactivo === 1 ? 'Desactivar' : 'Activar'"
                                                >
                                                    <i :class="item.ActivoInactivo === 1 ? 'fas fa-pause' : 'fas fa-play'" class="text-xs"></i>
                                                </button>
                                                <button 
                                                    v-if="item.ActivoInactivo === 0"
                                                    @click="eliminar(item.IdConfig)" 
                                                    class="text-red-600 hover:text-red-800 transition p-1 hover:bg-red-50 rounded" 
                                                    title="Eliminar"
                                                >
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="grupo.items.length === 0">
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-400 text-xs">
                                            <i class="fas fa-clipboard-list text-gray-300 mr-1"></i>
                                            No hay configuraciones en esta sucursal
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mensaje cuando no hay configuraciones -->
                    <div v-if="configuracionesAgrupadas.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-clipboard-list text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-sm text-gray-400">No hay configuraciones registradas</p>
                        <p class="text-xs text-gray-400 mt-1">Crea una nueva configuración para empezar</p>
                    </div>
                </div>

                <!-- 🔥 VISTA FILTRADA (si hay sucursal seleccionada) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">#</th>
                                    <th class="px-4 py-2 text-center text-[10px] font-semibold text-primary-700 uppercase">Cantidad</th>
                                    <th class="px-4 py-2 text-center text-[10px] font-semibold text-primary-700 uppercase">Estado</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Creado</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-semibold text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in configuraciones.data" :key="item.IdConfig" class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 font-mono">{{ item.IdConfig }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-bold bg-primary-100 text-primary-700">
                                            {{ item.CantidadProductos }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-center">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 text-[10px] rounded-full"
                                            :class="item.ActivoInactivo === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                        >
                                            <i :class="item.ActivoInactivo === 1 ? 'fas fa-check-circle mr-0.5' : 'fas fa-times-circle mr-0.5'" class="text-[8px]"></i>
                                            {{ item.ActivoInactivo === 1 ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                        {{ formatearFecha(item.FechaIngreso) }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button 
                                                @click="editar(item)" 
                                                class="text-primary-600 hover:text-primary-800 transition p-1 hover:bg-primary-50 rounded" 
                                                title="Editar"
                                            >
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button 
                                                @click="cambiarEstado(item.IdConfig, item.ActivoInactivo)" 
                                                class="text-yellow-600 hover:text-yellow-800 transition p-1 hover:bg-yellow-50 rounded" 
                                                :title="item.ActivoInactivo === 1 ? 'Desactivar' : 'Activar'"
                                            >
                                                <i :class="item.ActivoInactivo === 1 ? 'fas fa-pause' : 'fas fa-play'" class="text-xs"></i>
                                            </button>
                                            <button 
                                                v-if="item.ActivoInactivo === 0"
                                                @click="eliminar(item.IdConfig)" 
                                                class="text-red-600 hover:text-red-800 transition p-1 hover:bg-red-50 rounded" 
                                                title="Eliminar"
                                            >
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!configuraciones.data || configuraciones.data.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-clipboard-list text-2xl mb-1 block text-gray-300"></i>
                                        No hay configuraciones para esta sucursal
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="configuraciones.links && configuraciones.links.length > 1" class="mt-4 px-3 py-2 bg-white rounded-lg shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                        <div class="text-[10px] text-gray-500">
                            Mostrando {{ configuraciones.from || 0 }} a {{ configuraciones.to || 0 }} de {{ configuraciones.total || 0 }}
                        </div>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in configuraciones.links" 
                                :key="link.label" 
                                :href="link.url || '#'" 
                                class="px-2 py-0.5 rounded border text-[10px] transition"
                                :class="{ 
                                    'bg-primary-600 text-white border-primary-600': link.active, 
                                    'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 
                                    'opacity-50 cursor-not-allowed': !link.url 
                                }" 
                                v-html="link.label" 
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.2s ease-out;
}

[v-show] {
    transition: all 0.2s ease;
}

.bg-primary-50 {
    background-color: var(--color-primary-50, #eef2ff);
}
.bg-primary-100 {
    background-color: var(--color-primary-100, #e0e7ff);
}
.bg-primary-600 {
    background-color: var(--color-primary-600, #4f46e5);
}
.bg-primary-700 {
    background-color: var(--color-primary-700, #4338ca);
}
.text-primary-500 {
    color: var(--color-primary-500, #6366f1);
}
.text-primary-600 {
    color: var(--color-primary-600, #4f46e5);
}
.text-primary-700 {
    color: var(--color-primary-700, #4338ca);
}
.border-primary-600 {
    border-color: var(--color-primary-600, #4f46e5);
}
.hover\:bg-primary-50:hover {
    background-color: var(--color-primary-50, #eef2ff);
}
.hover\:bg-primary-700:hover {
    background-color: var(--color-primary-700, #4338ca);
}
.hover\:text-primary-800:hover {
    color: var(--color-primary-800, #3730a3);
}
.focus\:ring-primary-500:focus {
    --tw-ring-color: var(--color-primary-500, #6366f1);
}
</style>