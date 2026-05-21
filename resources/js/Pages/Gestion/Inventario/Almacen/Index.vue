<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    almacenes: Object,
    sucursales: Array,
    sucursalSeleccionada: Number,
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
    AlmacenPrincipal: 0 
})
const errors = ref({})

// Sucursales filtradas para búsqueda
const sucursalesFiltradas = computed(() => {
    if (!busquedaSucursal.value) return props.sucursales || []
    const termino = busquedaSucursal.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.NumeroSucursal?.toString().includes(termino)
    )
})

// Texto de la sucursal seleccionada
const sucursalSeleccionadaTexto = computed(() => {
    const suc = (props.sucursales || []).find(s => s.id === sucursalId.value)
    return suc ? `${suc.nombre} ${suc.NumeroSucursal ? `(N° ${suc.NumeroSucursal})` : ''}` : 'Seleccione una sucursal'
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
        sucursal_id: sucursalId.value, 
        Almacen: '', 
        AlmacenPrincipal: 0 
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
        AlmacenPrincipal: item.AlmacenPrincipal
    }
    // Actualizar el selector de sucursal al editar
    if (item.IdSucursal) {
        const suc = (props.sucursales || []).find(s => s.id === item.IdSucursal)
        if (suc) {
            busquedaSucursal.value = `${suc.nombre} ${suc.NumeroSucursal ? `(N° ${suc.NumeroSucursal})` : ''}`
        }
    }
}

// Guardar
const guardar = () => {
    if (!formData.value.sucursal_id) {
        errors.value = { sucursal_id: 'Seleccione una sucursal' }
        return
    }
    
    const datos = {
        sucursal_id: formData.value.sucursal_id,
        Almacen: formData.value.Almacen,
        AlmacenPrincipal: formData.value.AlmacenPrincipal ? 1 : 0
    }
    
    if (editando.value) {
        router.put(`/gestion/inventario/almacen/${editId.value}`, datos, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
            onError: (err) => { errors.value = err }
        })
    } else {
        router.post('/gestion/inventario/almacen', datos, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
            onError: (err) => { errors.value = err }
        })
    }
}

// Eliminar
const eliminar = (id, nombre) => {
    if (confirm(`¿Eliminar el almacén "${nombre}"?`)) {
        router.delete(`/gestion/inventario/almacen/${id}`)
    }
}

// Inicializar al montar
if (sucursalId.value) {
    const suc = (props.sucursales || []).find(s => s.id === sucursalId.value)
    if (suc) {
        busquedaSucursal.value = `${suc.nombre} ${suc.NumeroSucursal ? `(N° ${suc.NumeroSucursal})` : ''}`
    }
}

resetForm()
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-guindo-100 rounded-2xl mb-3">
                        <i class="fas fa-warehouse text-xl text-guindo-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Almacenes</h1>
                    <p class="text-xs text-gray-500">Administra los almacenes por sucursal</p>
                </div>

                <!-- Formulario en una sola línea -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="flex flex-wrap items-end gap-3">
                        <!-- Selector de Sucursal con búsqueda -->
                        <div class="flex-1 min-w-[220px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Sucursal *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaSucursal"
                                    @focus="mostrarDropdown = true"
                                    @blur="cerrarDropdown"
                                    placeholder="Buscar sucursal..."
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8"
                                    :class="{ 'border-red-500': errors.sucursal_id }"
                                >
                                <button 
                                    v-if="busquedaSucursal"
                                    @click="busquedaSucursal = ''; sucursalId = ''"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <!-- Dropdown -->
                                <div 
                                    v-if="mostrarDropdown && sucursalesFiltradas.length > 0"
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div
                                        v-for="s in sucursalesFiltradas"
                                        :key="s.id"
                                        @click="seleccionarSucursal(s)"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-sm"
                                        :class="{ 'bg-guindo-50': sucursalId === s.id }"
                                    >
                                        {{ s.nombre }} {{ s.NumeroSucursal ? `(N° ${s.NumeroSucursal})` : '' }}
                                    </div>
                                </div>
                            </div>
                            <p v-if="errors.sucursal_id" class="text-xs text-red-500 mt-1">{{ errors.sucursal_id }}</p>
                        </div>

                        <!-- Nombre Almacén -->
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre Almacén *</label>
                            <input 
                                type="text" 
                                v-model="formData.Almacen" 
                                placeholder="Ej: Principal, Secundario, Depósito" 
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.Almacen }"
                            />
                            <p v-if="errors.Almacen" class="text-xs text-red-500 mt-1">{{ errors.Almacen }}</p>
                        </div>

                        <!-- Almacén Principal (checkbox) -->
                        <div class="flex items-center">
                            <label class="flex items-center gap-2 px-3 py-2 cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    v-model="formData.AlmacenPrincipal" 
                                    :true-value="1" 
                                    :false-value="0" 
                                    class="w-4 h-4 rounded border-gray-300 text-guindo-600 focus:ring-guindo-500"
                                />
                                <span class="text-sm text-gray-700">Almacén Principal</span>
                            </label>
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-2">
                            <button 
                                @click="guardar" 
                                :disabled="!formData.sucursal_id"
                                class="px-4 py-2 bg-guindo-600 text-white rounded-lg text-sm hover:bg-guindo-700 transition disabled:opacity-50 flex items-center gap-1"
                            >
                                <i class="fas" :class="editando ? 'fa-pencil-alt' : 'fa-plus'"></i>
                                {{ editando ? 'Actualizar' : 'Guardar' }}
                            </button>
                            <button 
                                v-if="editando" 
                                @click="resetForm" 
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300 transition"
                            >
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-guindo-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Sucursal</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Almacén</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-guindo-700 uppercase">Principal</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in almacenes.data" :key="item.IdAlmacen" class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        <i class="fas fa-store text-guindo-400 mr-2 text-xs"></i>
                                        {{ item.sucursal?.Nombre || '-' }} 
                                        <span v-if="item.sucursal?.NumeroSucursal" class="text-gray-400 text-xs">(N° {{ item.sucursal.NumeroSucursal }})</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 font-medium">
                                        <i class="fas fa-warehouse text-guindo-400 mr-2 text-xs"></i>
                                        {{ item.Almacen }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span v-if="item.AlmacenPrincipal === 1" class="inline-flex items-center px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1 text-[10px]"></i> Principal
                                        </span>
                                        <span v-else class="inline-flex items-center px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                            Secundario
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        <button @click="editar(item)" class="text-guindo-600 hover:text-guindo-800 mr-3 transition" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="eliminar(item.IdAlmacen, item.Almacen)" class="text-red-600 hover:text-red-800 transition" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="almacenes.data.length === 0">
                                    <td colspan="4" class="px-4 py-12 text-center text-gray-500">
                                        <i class="fas fa-warehouse text-3xl mb-2 block text-gray-300"></i>
                                        No hay almacenes para esta sucursal
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="almacenes.links && almacenes.links.length > 1" class="px-4 py-3 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-xs text-gray-500">
                                Mostrando {{ almacenes.from || 0 }} a {{ almacenes.to || 0 }} de {{ almacenes.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link v-for="link in almacenes.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border text-sm" :class="{ 'bg-guindo-600 text-white border-guindo-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>