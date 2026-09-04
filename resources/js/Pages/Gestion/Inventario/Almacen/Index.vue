<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
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

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
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

// Estado para agrupación por sucursal
const sucursalesExpandidas = ref({})

// ==================== COMPUTED ====================
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
            if (sucursalesExpandidas.value[sucursalId] === undefined) {
                sucursalesExpandidas.value[sucursalId] = true
            }
        }
        grupos[sucursalId].almacenes.push(item)
    })
    
    return Object.values(grupos)
})

const sucursalesFiltradas = computed(() => {
    if (!busquedaSucursal.value) return props.sucursales || []
    const termino = busquedaSucursal.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.NumeroSucursal?.toString().includes(termino)
    )
})

const totalAlmacenes = computed(() => {
    return props.almacenes.data?.length || 0
})

// ==================== FUNCIONES ====================
const cerrarDropdown = () => {
    setTimeout(() => {
        mostrarDropdown.value = false
    }, 200)
}

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

const toggleSucursal = (sucursalId) => {
    sucursalesExpandidas.value[sucursalId] = !sucursalesExpandidas.value[sucursalId]
}

const isExpandida = (sucursalId) => {
    return sucursalesExpandidas.value[sucursalId] !== false
}

const expandirTodas = () => {
    almacenesAgrupados.value.forEach(grupo => {
        sucursalesExpandidas.value[grupo.id] = true
    })
}

const contraerTodas = () => {
    almacenesAgrupados.value.forEach(grupo => {
        sucursalesExpandidas.value[grupo.id] = false
    })
}

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

const guardar = async () => {
    if (!formData.value.sucursal_id) {
        toast?.error('Validación', 'Seleccione una sucursal')
        return
    }
    
    if (!formData.value.Almacen || formData.value.Almacen.trim() === '') {
        toast?.error('Validación', 'Ingrese el nombre del almacén')
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
                    toast?.success('Éxito', 'Almacén actualizado correctamente')
                    resetForm()
                },
                onError: (err) => {
                    toast?.error('Error', Object.values(err)[0]?.[0] || 'Error al actualizar')
                }
            })
        } else {
            await router.post('/gestion/inventario/almacen', datos, {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.success('Éxito', 'Almacén creado correctamente')
                    resetForm()
                },
                onError: (err) => {
                    toast?.error('Error', Object.values(err)[0]?.[0] || 'Error al guardar')
                }
            })
        }
    } finally {
        processing.value = false
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    
    const flashSuccess = page.props.flash?.success
    const flashError = page.props.flash?.error
    
    if (flashSuccess && !sessionStorage.getItem('last_flash_success')) {
        toast?.success('Éxito', flashSuccess)
        sessionStorage.setItem('last_flash_success', flashSuccess)
        setTimeout(() => sessionStorage.removeItem('last_flash_success'), 500)
    }
    if (flashError && !sessionStorage.getItem('last_flash_error')) {
        toast?.error('Error', flashError)
        sessionStorage.setItem('last_flash_error', flashError)
        setTimeout(() => sessionStorage.removeItem('last_flash_error'), 500)
    }
    
    if (sucursalId.value) {
        const suc = (props.sucursales || []).find(s => s.id === sucursalId.value)
        if (suc) {
            busquedaSucursal.value = `${suc.nombre} ${suc.NumeroSucursal ? `(N° ${suc.NumeroSucursal})` : ''}`
            formData.value.sucursal_id = sucursalId.value
        }
    }
    
    resetForm()
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-warehouse text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Almacenes</h1>
                            <p class="text-xs text-gray-500">Administra los almacenes por sucursal</p>
                        </div>
                    </div>
                    <div v-if="!sucursalId && almacenesAgrupados.length > 0" class="flex gap-1.5">
                        <button @click="expandirTodas" 
                            class="px-3 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center gap-1">
                            <i class="fas fa-expand text-[10px]"></i> Expandir
                        </button>
                        <button @click="contraerTodas" 
                            class="px-3 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center gap-1">
                            <i class="fas fa-compress text-[10px]"></i> Contraer
                        </button>
                    </div>
                </div>

                <!-- ==================== FORMULARIO ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4 border border-primary-200">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Sucursal -->
                        <div class="flex-1 min-w-[180px] max-w-[240px]">
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Sucursal *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaSucursal"
                                    @focus="mostrarDropdown = true"
                                    @blur="cerrarDropdown"
                                    placeholder="Buscar sucursal..."
                                    class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-sm pr-7 focus:ring-primary-500 focus:border-primary-500 outline-none"
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
                                    <div @click="seleccionarTodas"
                                        class="px-3 py-2 hover:bg-primary-50 cursor-pointer border-b text-sm font-medium text-primary-600">
                                        <i class="fas fa-warehouse mr-2"></i> Todas las sucursales
                                    </div>
                                    <div v-for="s in sucursalesFiltradas" :key="s.id"
                                        @click="seleccionarSucursal(s)"
                                        class="px-3 py-2 hover:bg-primary-50 cursor-pointer border-b last:border-b-0 text-sm flex items-center justify-between"
                                        :class="{ 'bg-primary-50': sucursalId === s.id }">
                                        <span>{{ s.nombre }}</span>
                                        <span v-if="s.NumeroSucursal" class="text-xs text-gray-400">N° {{ s.NumeroSucursal }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Nombre Almacén -->
                        <div class="flex-[2] min-w-[180px] max-w-[280px]">
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Nombre Almacén *</label>
                            <input 
                                type="text" 
                                v-model="formData.Almacen" 
                                placeholder="Ej: Principal, Secundario, Depósito" 
                                class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{ 'border-red-500': errors.Almacen }"
                                @keyup.enter="guardar"
                            />
                        </div>

                        <!-- Checkbox Principal -->
                        <div class="flex items-center py-1.5">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    v-model="formData.AlmacenPrincipal" 
                                    class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                <span class="text-sm text-gray-700 whitespace-nowrap">Principal</span>
                            </label>
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-1.5 ml-auto">
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.sucursal_id || !formData.Almacen"
                                class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-sm font-medium hover:bg-primary-700 transition disabled:opacity-50 flex items-center gap-1.5"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-plus'" class="text-xs"></i>
                                {{ processing ? 'Guardando...' : (editando ? 'Actualizar' : 'Crear') }}
                            </button>
                            <button v-if="editando" @click="resetForm" 
                                class="px-4 py-1.5 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition flex items-center gap-1.5">
                                <i class="fas fa-times text-xs"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== CONTENIDO ==================== -->
                <div v-if="!sucursalId" class="space-y-3">
                    <!-- Total -->
                    <div class="text-xs text-gray-500 mb-2">
                        <i class="fas fa-warehouse mr-1"></i> 
                        {{ totalAlmacenes }} almacenes en {{ almacenesAgrupados.length }} sucursales
                    </div>

                    <!-- Grupos por sucursal -->
                    <div v-for="grupo in almacenesAgrupados" :key="grupo.id" 
                        class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                        <!-- Cabecera -->
                        <div @click="toggleSucursal(grupo.id)"
                            class="flex items-center justify-between px-4 py-2 bg-primary-50 hover:bg-primary-100 cursor-pointer transition border-b border-primary-100">
                            <div class="flex items-center gap-2">
                                <i :class="isExpandida(grupo.id) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                    class="text-primary-500 text-xs transition-transform"></i>
                                <i class="fas fa-store text-primary-500 text-sm"></i>
                                <span class="text-sm font-semibold text-gray-800">{{ grupo.nombre }}</span>
                                <span v-if="grupo.numero" class="text-xs text-gray-400">N° {{ grupo.numero }}</span>
                                <span class="text-xs bg-primary-100 px-2 py-0.5 rounded-full text-primary-700">
                                    {{ grupo.almacenes.length }}
                                </span>
                            </div>
                            <i :class="isExpandida(grupo.id) ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"
                                class="text-primary-500 text-xs"></i>
                        </div>

                        <!-- Tabla de almacenes -->
                        <div v-show="isExpandida(grupo.id)">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Almacén</th>
                                            <th class="px-4 py-1.5 text-center text-xs font-medium text-gray-500 uppercase w-28">Principal</th>
                                            <th class="px-4 py-1.5 text-right text-xs font-medium text-gray-500 uppercase w-16">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <tr v-for="item in grupo.almacenes" :key="item.IdAlmacen" class="hover:bg-gray-50 transition">
                                            <td class="px-4 py-2 text-sm text-gray-800">
                                                <i class="fas fa-warehouse text-primary-400 mr-2 text-xs"></i>
                                                {{ item.Almacen }}
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <span v-if="item.AlmacenPrincipal === 1" 
                                                    class="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700">
                                                    <i class="fas fa-check mr-0.5 text-[8px]"></i> Principal
                                                </span>
                                                <span v-else 
                                                    class="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">
                                                    Secundario
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-right">
                                                <button @click="editar(item)" 
                                                    class="text-primary-600 hover:text-primary-800 transition text-sm p-1 rounded hover:bg-primary-50" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="grupo.almacenes.length === 0">
                                            <td colspan="3" class="px-4 py-6 text-center text-gray-400 text-sm">
                                                <i class="fas fa-warehouse text-gray-300 mr-1"></i>
                                                No hay almacenes en esta sucursal
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Sin almacenes -->
                    <div v-if="almacenesAgrupados.length === 0" class="bg-white rounded-xl shadow-sm p-8 text-center">
                        <i class="fas fa-warehouse text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-sm text-gray-500">No hay almacenes registrados</p>
                        <p class="text-xs text-gray-400 mt-1">Selecciona una sucursal o crea uno nuevo</p>
                    </div>
                </div>

                <!-- ==================== VISTA FILTRADA POR SUCURSAL ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-primary-700 uppercase">Almacén</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-primary-700 uppercase w-28">Principal</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-primary-700 uppercase w-16">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in almacenes.data" :key="item.IdAlmacen" class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-2 text-sm text-gray-800">
                                        <i class="fas fa-warehouse text-primary-400 mr-2 text-xs"></i>
                                        {{ item.Almacen }}
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <span v-if="item.AlmacenPrincipal === 1" 
                                            class="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700">
                                            <i class="fas fa-check mr-0.5 text-[8px]"></i> Principal
                                        </span>
                                        <span v-else 
                                            class="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">
                                            Secundario
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <button @click="editar(item)" 
                                            class="text-primary-600 hover:text-primary-800 transition text-sm p-1 rounded hover:bg-primary-50" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!almacenes.data || almacenes.data.length === 0">
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        <i class="fas fa-warehouse text-2xl mb-1 block text-gray-300"></i>
                                        No hay almacenes para esta sucursal
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== PAGINACIÓN ==================== -->
                <div v-if="almacenes.links && almacenes.links.length > 1" class="mt-3 px-4 py-2 bg-white rounded-xl shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                        <div class="text-sm text-gray-500">
                            Mostrando {{ almacenes.from || 0 }} a {{ almacenes.to || 0 }} de {{ almacenes.total || 0 }}
                        </div>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link v-for="link in almacenes.links" :key="link.label" :href="link.url || '#'" 
                                class="px-3 py-1 rounded-lg border text-sm transition"
                                :class="{ 
                                    'bg-primary-600 text-white border-primary-600': link.active, 
                                    'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url, 
                                    'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url 
                                }" 
                                v-html="link.label" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 14px !important;
    }
}

/* Scrollbar personalizada */
.overflow-x-auto::-webkit-scrollbar,
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}

.overflow-x-auto::-webkit-scrollbar-track,
.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb,
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover,
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>