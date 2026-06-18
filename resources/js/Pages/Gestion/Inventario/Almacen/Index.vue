<script setup>
import { ref, computed, onMounted, inject } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    almacenes: {
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
    Almacen: '', 
    AlmacenPrincipal: false
})
const errors = ref({})
const processing = ref(false)

// 🔥 ESTADO PARA AGRUPACIÓN POR SUCURSAL
const sucursalesExpandidas = ref({})

// 🔥 Agrupar almacenes por sucursal
const almacenesAgrupados = computed(() => {
    if (!props.almacenes.data || props.almacenes.data.length === 0) {
        return []
    }
    
    const grupos = {}
    
    props.almacenes.data.forEach(item => {
        const sucursalId = item.IdSucursal
        const sucursalNombre = item.sucursal?.Nombre || 'Sin sucursal'
        const sucursalNumero = item.sucursal?.NumeroSucursal
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                numero: sucursalNumero,
                almacenes: []
            }
            // Inicializar expandido (por defecto todos expandidos)
            if (sucursalesExpandidas.value[sucursalId] === undefined) {
                sucursalesExpandidas.value[sucursalId] = true
            }
        }
        grupos[sucursalId].almacenes.push(item)
    })
    
    return Object.values(grupos)
})

// 🔥 Alternar expansión de una sucursal
const toggleSucursal = (sucursalId) => {
    sucursalesExpandidas.value[sucursalId] = !sucursalesExpandidas.value[sucursalId]
}

// 🔥 Verificar si una sucursal está expandida
const isExpandida = (sucursalId) => {
    return sucursalesExpandidas.value[sucursalId] !== false
}

// 🔥 Expandir todas
const expandirTodas = () => {
    almacenesAgrupados.value.forEach(grupo => {
        sucursalesExpandidas.value[grupo.id] = true
    })
}

// 🔥 Contraer todas
const contraerTodas = () => {
    almacenesAgrupados.value.forEach(grupo => {
        sucursalesExpandidas.value[grupo.id] = false
    })
}

// 🔥 Contar almacenes totales
const totalAlmacenes = computed(() => {
    return props.almacenes.data?.length || 0
})

// MODAL DE ELIMINACIÓN
const modalEliminarOpen = ref(false)
const eliminarId = ref(null)
const eliminarNombre = ref('')
const eliminando = ref(false)

// Abrir modal de confirmación
const abrirModalEliminar = (id, nombre) => {
    eliminarId.value = id
    eliminarNombre.value = nombre
    modalEliminarOpen.value = true
}

// Cerrar modal
const cerrarModalEliminar = () => {
    modalEliminarOpen.value = false
    eliminarId.value = null
    eliminarNombre.value = ''
}

// Confirmar eliminación
const confirmarEliminar = async () => {
    if (!eliminarId.value) return
    
    eliminando.value = true
    
    router.delete(`/gestion/inventario/almacen/${eliminarId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (toast) toast.success('Éxito', `Almacén "${eliminarNombre.value}" eliminado correctamente`)
            cerrarModalEliminar()
        },
        onError: (err) => {
            if (toast) toast.error('Error', 'No se pudo eliminar el almacén')
            cerrarModalEliminar()
        },
        onFinish: () => {
            eliminando.value = false
        }
    })
}

// Verificar mensajes flash al cargar
onMounted(() => {
    const flashSuccess = page.props.flash?.success
    const flashError = page.props.flash?.error
    
    if (flashSuccess && toast) {
        toast.success('Éxito', flashSuccess)
    }
    if (flashError && toast) {
        toast.error('Error', flashError)
    }
    
    // Si hay sucursal seleccionada, mostrar el nombre en el campo
    if (sucursalId.value) {
        const suc = (props.sucursales || []).find(s => s.id === sucursalId.value)
        if (suc) {
            busquedaSucursal.value = `${suc.nombre} ${suc.NumeroSucursal ? `(N° ${suc.NumeroSucursal})` : ''}`
            formData.value.sucursal_id = sucursalId.value
        }
    }
    
    resetForm()
})

// Sucursales filtradas para búsqueda
const sucursalesFiltradas = computed(() => {
    if (!busquedaSucursal.value) return props.sucursales || []
    const termino = busquedaSucursal.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.NumeroSucursal?.toString().includes(termino)
    )
})

// Cerrar dropdown
const cerrarDropdown = () => {
    setTimeout(() => {
        mostrarDropdown.value = false
    }, 200)
}

// Seleccionar TODAS las sucursales
const seleccionarTodas = () => {
    sucursalId.value = ''
    busquedaSucursal.value = 'Todas las sucursales'
    mostrarDropdown.value = false
    formData.value.sucursal_id = ''
    
    router.get('/gestion/inventario/almacen', {}, {
        preserveState: true,
        replace: true,
    })
}

// Seleccionar una sucursal específica
const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    busquedaSucursal.value = `${sucursal.nombre} ${sucursal.NumeroSucursal ? `(N° ${sucursal.NumeroSucursal})` : ''}`
    mostrarDropdown.value = false
    formData.value.sucursal_id = sucursal.id
    
    router.get('/gestion/inventario/almacen', { sucursal_id: sucursal.id }, {
        preserveState: true,
        replace: true,
    })
}

// Resetear formulario
const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = { 
        sucursal_id: sucursalId.value || '',
        Almacen: '', 
        AlmacenPrincipal: false
    }
    errors.value = {}
}

// Editar
const editar = (item) => {
    editando.value = true
    editId.value = item.IdAlmacen
    formData.value = {
        sucursal_id: item.IdSucursal,
        Almacen: item.Almacen,
        AlmacenPrincipal: item.AlmacenPrincipal === 1
    }
    if (item.IdSucursal) {
        const suc = (props.sucursales || []).find(s => s.id === item.IdSucursal)
        if (suc) {
            busquedaSucursal.value = `${suc.nombre} ${suc.NumeroSucursal ? `(N° ${suc.NumeroSucursal})` : ''}`
        }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

// Guardar
const guardar = async () => {
    if (!formData.value.sucursal_id) {
        if (toast) toast.error('Validación', 'Seleccione una sucursal')
        return
    }
    
    if (!formData.value.Almacen || formData.value.Almacen.trim() === '') {
        if (toast) toast.error('Validación', 'Ingrese el nombre del almacén')
        return
    }
    
    processing.value = true
    
    const datos = {
        sucursal_id: formData.value.sucursal_id,
        Almacen: formData.value.Almacen,
        AlmacenPrincipal: formData.value.AlmacenPrincipal ? 1 : 0
    }
    
    try {
        if (editando.value) {
            await router.put(`/gestion/inventario/almacen/${editId.value}`, datos, {
                preserveScroll: true,
                onSuccess: () => {
                    if (toast) toast.success('Éxito', 'Almacén actualizado correctamente')
                    resetForm()
                },
                onError: (err) => {
                    if (toast) toast.error('Error', Object.values(err)[0]?.[0] || 'Error al actualizar')
                }
            })
        } else {
            await router.post('/gestion/inventario/almacen', datos, {
                preserveScroll: true,
                onSuccess: () => {
                    if (toast) toast.success('Éxito', 'Almacén creado correctamente')
                    resetForm()
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
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-warehouse text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">Almacenes</h1>
                            <p class="text-[10px] text-gray-500">Administra los almacenes por sucursal</p>
                        </div>
                    </div>
                    <!-- 🔥 Botones Expandir/Contraer (solo cuando no hay filtro) -->
                    <div v-if="!sucursalId && almacenesAgrupados.length > 0" class="flex gap-2">
                        <button 
                            @click="expandirTodas" 
                            class="px-3 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded-md transition"
                        >
                            <i class="fas fa-plus-circle mr-1"></i> Expandir todo
                        </button>
                        <button 
                            @click="contraerTodas" 
                            class="px-3 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded-md transition"
                        >
                            <i class="fas fa-minus-circle mr-1"></i> Contraer todo
                        </button>
                    </div>
                </div>

                <!-- Formulario - Fila horizontal -->
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
                                    placeholder="Buscar sucursal..."
                                    class="w-full border rounded-md px-3 py-2 text-sm pr-8"
                                    :class="{ 'border-red-500': errors.sucursal_id }"
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
                                    <!-- 🔥 OPCIÓN "TODAS" -->
                                    <div
                                        @click="seleccionarTodas"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b text-sm font-medium text-primary-600"
                                    >
                                        <i class="fas fa-warehouse mr-2"></i> Todas las sucursales
                                    </div>
                                    
                                    <div
                                        v-for="s in sucursalesFiltradas"
                                        :key="s.id"
                                        @click="seleccionarSucursal(s)"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-sm"
                                        :class="{ 'bg-primary-50': sucursalId === s.id }"
                                    >
                                        {{ s.nombre }} 
                                        <span v-if="s.NumeroSucursal" class="text-gray-400 text-xs">(N° {{ s.NumeroSucursal }})</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Nombre Almacén -->
                        <div class="w-full sm:flex-1">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre Almacén *</label>
                            <input 
                                type="text" 
                                v-model="formData.Almacen" 
                                placeholder="Ej: Principal, Secundario, Depósito" 
                                class="w-full border rounded-md px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.Almacen }"
                                @keyup.enter="guardar"
                            />
                        </div>

                        <!-- Checkbox Principal -->
                        <div class="w-full sm:w-auto flex items-center">
                            <label class="flex items-center gap-2 cursor-pointer py-1">
                                <input 
                                    type="checkbox" 
                                    v-model="formData.AlmacenPrincipal" 
                                    class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                <span class="text-sm text-gray-700 whitespace-nowrap">Principal</span>
                            </label>
                        </div>

                        <!-- Botones -->
                        <div class="w-full sm:w-auto flex gap-2">
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.sucursal_id || !formData.Almacen"
                                class="flex-1 sm:flex-none px-4 py-2 bg-primary-600 text-white rounded-md text-sm hover:bg-primary-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-plus'" class="text-xs"></i>
                                {{ processing ? 'Procesando...' : (editando ? 'Actualizar' : 'Crear') }}
                            </button>
                            <button 
                                v-if="editando" 
                                @click="resetForm" 
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300 transition"
                            >
                                <i class="fas fa-times text-xs"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 🔥 VISTA AGRUPADA POR SUCURSAL (ACORDEÓN) -->
                <div v-if="!sucursalId" class="space-y-3">
                    <!-- Total de almacenes -->
                    <div class="text-xs text-gray-500 mb-2">
                        <i class="fas fa-warehouse mr-1"></i> 
                        {{ totalAlmacenes }} almacenes en {{ almacenesAgrupados.length }} sucursales
                    </div>

                    <div 
                        v-for="grupo in almacenesAgrupados" 
                        :key="grupo.id" 
                        class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-200"
                    >
                        <!-- Cabecera de la sucursal (click para expandir/contraer) -->
                        <div 
                            @click="toggleSucursal(grupo.id)"
                            class="flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 cursor-pointer transition border-b border-gray-200"
                        >
                            <div class="flex items-center gap-3">
                                <i 
                                    :class="isExpandida(grupo.id) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                    class="text-gray-400 text-xs transition-transform"
                                ></i>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-store text-primary-500 text-sm"></i>
                                    <span class="font-semibold text-gray-800 text-sm">{{ grupo.nombre }}</span>
                                    <span v-if="grupo.numero" class="text-xs text-gray-400">(N° {{ grupo.numero }})</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs bg-gray-200 px-2 py-0.5 rounded-full text-gray-600">
                                    {{ grupo.almacenes.length }} almacenes
                                </span>
                                <i 
                                    :class="isExpandida(grupo.id) ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"
                                    class="text-gray-400 text-xs"
                                ></i>
                            </div>
                        </div>

                        <!-- Contenido (almacenes de la sucursal) -->
                        <div 
                            v-show="isExpandida(grupo.id)"
                            class="overflow-x-auto transition-all"
                        >
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">Almacén</th>
                                        <th class="px-4 py-2 text-center text-[10px] font-semibold text-gray-500 uppercase">Principal</th>
                                        <th class="px-4 py-2 text-right text-[10px] font-semibold text-gray-500 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="item in grupo.almacenes" :key="item.IdAlmacen" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800">
                                            <i class="fas fa-warehouse text-primary-400 mr-2 text-xs"></i>
                                            {{ item.Almacen }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-center">
                                            <span v-if="item.AlmacenPrincipal === 1" class="inline-flex items-center px-2 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-0.5 text-[8px]"></i> Principal
                                            </span>
                                            <span v-else class="inline-flex items-center px-2 py-0.5 text-[10px] rounded-full bg-gray-100 text-gray-600">
                                                Secundario
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">
                                            <button @click="editar(item)" class="text-primary-600 hover:text-primary-800 mr-2 transition" title="Editar">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button @click="abrirModalEliminar(item.IdAlmacen, item.Almacen)" class="text-red-600 hover:text-red-800 transition" title="Eliminar">
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="grupo.almacenes.length === 0">
                                        <td colspan="3" class="px-4 py-6 text-center text-gray-400 text-xs">
                                            <i class="fas fa-warehouse text-gray-300 mr-1"></i>
                                            No hay almacenes en esta sucursal
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mensaje cuando no hay almacenes -->
                    <div v-if="almacenesAgrupados.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-warehouse text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-sm text-gray-400">No hay almacenes registrados</p>
                        <p class="text-xs text-gray-400 mt-1">Selecciona una sucursal o crea uno nuevo</p>
                    </div>
                </div>

                <!-- 🔥 VISTA FILTRADA POR UNA SUCURSAL (tabla normal) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Almacén</th>
                                    <th class="px-4 py-2 text-center text-[10px] font-semibold text-primary-700 uppercase">Principal</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-semibold text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in almacenes.data" :key="item.IdAlmacen" class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800">
                                        <i class="fas fa-warehouse text-primary-400 mr-2 text-xs"></i>
                                        {{ item.Almacen }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-center">
                                        <span v-if="item.AlmacenPrincipal === 1" class="inline-flex items-center px-2 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-0.5 text-[8px]"></i> Principal
                                        </span>
                                        <span v-else class="inline-flex items-center px-2 py-0.5 text-[10px] rounded-full bg-gray-100 text-gray-600">
                                            Secundario
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-right">
                                        <button @click="editar(item)" class="text-primary-600 hover:text-primary-800 mr-2 transition" title="Editar">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button @click="abrirModalEliminar(item.IdAlmacen, item.Almacen)" class="text-red-600 hover:text-red-800 transition" title="Eliminar">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!almacenes.data || almacenes.data.length === 0">
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-warehouse text-2xl mb-1 block text-gray-300"></i>
                                        No hay almacenes para esta sucursal
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="almacenes.links && almacenes.links.length > 1" class="mt-4 px-3 py-2 bg-white rounded-lg shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                        <div class="text-[10px] text-gray-500">
                            Mostrando {{ almacenes.from || 0 }} a {{ almacenes.to || 0 }} de {{ almacenes.total || 0 }}
                        </div>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in almacenes.links" 
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

        <!-- 🔥 MODAL DE CONFIRMACIÓN PARA ELIMINAR -->
        <div v-if="modalEliminarOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="cerrarModalEliminar">
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden animate-fade-in-up">
                <div class="bg-red-50 p-4 text-center">
                    <div class="w-12 h-12 mx-auto bg-red-100 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-trash-alt text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">¿Eliminar almacén?</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        ¿Estás seguro de que deseas eliminar el almacén 
                        <span class="font-semibold text-gray-700">"{{ eliminarNombre }}"</span>?
                    </p>
                    <p class="text-xs text-red-500 mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="p-4 flex gap-3">
                    <button 
                        @click="cerrarModalEliminar"
                        class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition"
                    >
                        Cancelar
                    </button>
                    <button 
                        @click="confirmarEliminar"
                        :disabled="eliminando"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        <i v-if="eliminando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-trash-alt"></i>
                        {{ eliminando ? 'Eliminando...' : 'Eliminar' }}
                    </button>
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

/* Transición suave para el acordeón */
[v-show] {
    transition: all 0.2s ease;
}
</style>