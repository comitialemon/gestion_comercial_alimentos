<script setup>
import { ref, computed, onMounted, watch, inject } from 'vue'
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

// 🔥 MODAL DE ELIMINACIÓN
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
            toast?.success('Éxito', `Almacén "${eliminarNombre.value}" eliminado correctamente`)
            cerrarModalEliminar()
        },
        onError: (err) => {
            toast?.error('Error', 'No se pudo eliminar el almacén')
            cerrarModalEliminar()
        },
        onFinish: () => {
            eliminando.value = false
        }
    })
}

// Verificar mensajes flash al cargar (SOLO UNA VEZ)
onMounted(() => {
    // Solo mostrar flash si es diferente al último mostrado
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

// Seleccionar sucursal
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
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6 sticky top-2 z-10">
                    <div class="space-y-3">
                        <div>
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
                                    v-if="mostrarDropdown && sucursalesFiltradas.length > 0"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                >
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

                        <div>
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

                        <div class="flex items-center">
                            <label class="flex items-center gap-2 cursor-pointer py-1">
                                <input 
                                    type="checkbox" 
                                    v-model="formData.AlmacenPrincipal" 
                                    class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                <span class="text-sm text-gray-700">Marcar como Almacén Principal</span>
                            </label>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.sucursal_id || !formData.Almacen"
                                class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-md text-sm hover:bg-primary-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-plus'" class="text-xs"></i>
                                {{ processing ? 'Procesando...' : (editando ? 'Actualizar Almacén' : 'Guardar Almacén') }}
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

                <!-- CARDS PARA MÓVIL -->
                <div class="block sm:hidden space-y-3">
                    <div 
                        v-for="item in almacenes.data" 
                        :key="item.IdAlmacen" 
                        class="bg-white rounded-lg shadow-sm p-4 border border-gray-100"
                    >
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-warehouse text-primary-600 text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800 text-sm">{{ item.Almacen }}</h3>
                                    <p class="text-[10px] text-gray-500">
                                        <i class="fas fa-store mr-1"></i>
                                        {{ item.sucursal?.Nombre || '-' }}
                                    </p>
                                </div>
                            </div>
                            <span 
                                v-if="item.AlmacenPrincipal === 1" 
                                class="px-2 py-0.5 text-[9px] rounded-full bg-green-100 text-green-700 flex items-center gap-1"
                            >
                                <i class="fas fa-star text-[8px]"></i> Principal
                            </span>
                            <span 
                                v-else 
                                class="px-2 py-0.5 text-[9px] rounded-full bg-gray-100 text-gray-500"
                            >
                                Secundario
                            </span>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-3 border-t border-gray-100 mt-2">
                            <button 
                                @click="editar(item)" 
                                class="text-primary-600 hover:text-primary-800 text-xs flex items-center gap-1"
                            >
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button 
                                @click="abrirModalEliminar(item.IdAlmacen, item.Almacen)" 
                                class="text-red-600 hover:text-red-800 text-xs flex items-center gap-1"
                            >
                                <i class="fas fa-trash-alt"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="!almacenes.data || almacenes.data.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-warehouse text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-sm text-gray-400">No hay almacenes para esta sucursal</p>
                    </div>
                </div>

                <!-- TABLA PARA DESKTOP -->
                <div class="hidden sm:block bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Sucursal</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Almacén</th>
                                    <th class="px-3 py-2 text-center text-[10px] font-semibold text-primary-700 uppercase">Principal</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-semibold text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in almacenes.data" :key="item.IdAlmacen" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700">
                                        <i class="fas fa-store text-primary-400 mr-1 text-[10px]"></i>
                                        {{ item.sucursal?.Nombre || '-' }} 
                                        <span v-if="item.sucursal?.NumeroSucursal" class="text-gray-400 text-[10px]">(N° {{ item.sucursal.NumeroSucursal }})</span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-800 font-medium">
                                        <i class="fas fa-warehouse text-primary-400 mr-1 text-[10px]"></i>
                                        {{ item.Almacen }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-center">
                                        <span v-if="item.AlmacenPrincipal === 1" class="inline-flex items-center px-2 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-0.5 text-[8px]"></i> Principal
                                        </span>
                                        <span v-else class="inline-flex items-center px-2 py-0.5 text-[10px] rounded-full bg-gray-100 text-gray-600">
                                            Secundario
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">
                                        <button @click="editar(item)" class="text-primary-600 hover:text-primary-800 mr-2 transition" title="Editar">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button @click="abrirModalEliminar(item.IdAlmacen, item.Almacen)" class="text-red-600 hover:text-red-800 transition" title="Eliminar">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!almacenes.data || almacenes.data.length === 0">
                                    <td colspan="4" class="px-3 py-8 text-center text-gray-400 text-xs">
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
</style>