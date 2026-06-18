<!-- resources/js/Pages/Gestion/Inventario/ProductoEstado/Index.vue -->
<script setup>
import { ref, onMounted, inject, watch } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    items: Object,
    fillable: Array,
    filtros: Object,
})

// Estado del formulario
const editando = ref(false)
const editId = ref(null)
const formData = ref({ Estado: '' })
const errors = ref({})
const processing = ref(false)
const search = ref(props.filtros?.search || '')

// Modal de eliminación
const modalEliminarOpen = ref(false)
const eliminarId = ref(null)
const eliminarNombre = ref('')
const eliminando = ref(false)

// Inicializar formulario
const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = { Estado: '' }
    errors.value = {}
}

// Editar
const editar = (item) => {
    editando.value = true
    editId.value = item.IdEstado
    formData.value = { Estado: item.Estado }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

// Guardar
const guardar = () => {
    if (!formData.value.Estado || formData.value.Estado.trim() === '') {
        toast?.error('Validación', 'Ingrese el nombre del estado')
        return
    }
    
    processing.value = true
    
    if (editando.value) {
        router.put(`/gestion/inventario/producto-estado/${editId.value}`, formData.value, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Estado actualizado correctamente')
                resetForm()
                processing.value = false
            },
            onError: (err) => {
                errors.value = err
                toast?.error('Error', Object.values(err)[0]?.[0] || 'Error al actualizar')
                processing.value = false
            }
        })
    } else {
        router.post('/gestion/inventario/producto-estado', formData.value, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Estado creado correctamente')
                resetForm()
                processing.value = false
            },
            onError: (err) => {
                errors.value = err
                toast?.error('Error', Object.values(err)[0]?.[0] || 'Error al guardar')
                processing.value = false
            }
        })
    }
}

// Abrir modal de eliminación
const abrirModalEliminar = (id, nombre) => {
    eliminarId.value = id
    eliminarNombre.value = nombre
    modalEliminarOpen.value = true
}

const cerrarModalEliminar = () => {
    modalEliminarOpen.value = false
    eliminarId.value = null
    eliminarNombre.value = ''
}

const confirmarEliminar = () => {
    if (!eliminarId.value) return
    
    eliminando.value = true
    
    router.delete(`/gestion/inventario/producto-estado/${eliminarId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast?.success('Éxito', `Estado "${eliminarNombre.value}" eliminado correctamente`)
            cerrarModalEliminar()
            eliminando.value = false
        },
        onError: () => {
            toast?.error('Error', 'No se pudo eliminar el estado')
            cerrarModalEliminar()
            eliminando.value = false
        }
    })
}

// Verificar mensajes flash al cargar
onMounted(() => {
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
    
    resetForm()
})

// 🔥 BÚSQUEDA CON DEBOUNCE
let timeout
const buscar = (val) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/gestion/inventario/producto-estado', { search: val || undefined }, {
            preserveState: true,
            replace: true
        })
    }, 500)
}

// 🔥 WATCH PARA BÚSQUEDA (alternativa)
watch(search, (newVal) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/gestion/inventario/producto-estado', { search: newVal || undefined }, {
            preserveState: true,
            replace: true
        })
    }, 500)
})

const limpiarBusqueda = () => {
    search.value = ''
    router.get('/gestion/inventario/producto-estado', {}, {
        preserveState: true,
        replace: true
    })
}
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                         :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                        <i class="fas fa-tag text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-gray-800">Estados de Producto</h1>
                        <p class="text-[10px] text-gray-500">Administra los estados de los productos</p>
                    </div>
                </div>

                <!-- Búsqueda -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input 
                            type="text" 
                            v-model="search" 
                            @input="buscar(search)"
                            placeholder="Buscar estado..." 
                            class="w-full border rounded-md pl-8 pr-8 py-2 text-sm focus:ring-2 focus:outline-none"
                            :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                        />
                        <button 
                            v-if="search" 
                            @click="limpiarBusqueda"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                    <p v-if="search" class="text-[10px] text-gray-400 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Mostrando resultados para: <span class="font-medium text-gray-600">{{ search }}</span>
                        <span class="ml-2">({{ items.total || 0 }} resultados)</span>
                    </p>
                </div>

                <!-- Formulario con título -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6 sticky top-2 z-10 border"
                     :style="{ borderColor: `var(--color-primary-200)` }">
                    
                    <!-- Título del formulario -->
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-plus-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                        <span class="text-xs font-semibold" :style="{ color: `var(--color-primary-700)` }">
                            {{ editando ? 'Editar Estado' : 'Creación de Estado' }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2 items-end">
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Estado *</label>
                            <input 
                                type="text" 
                                v-model="formData.Estado" 
                                :placeholder="editando ? 'Editar estado...' : 'Ej: Activo, Inactivo, Pendiente...'" 
                                class="w-full border rounded-md px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.Estado }"
                                :style="{ borderColor: errors.Estado ? '#ef4444' : `var(--color-primary-300)` }"
                                @keyup.enter="guardar"
                            />
                            <p v-if="errors.Estado" class="text-xs text-red-500 mt-0.5">{{ errors.Estado }}</p>
                        </div>

                        <div class="flex gap-2">
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.Estado"
                                class="px-4 py-2 text-white rounded-md text-sm transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-save'" class="text-xs"></i>
                                {{ processing ? 'Procesando...' : (editando ? 'Actualizar' : 'Guardar') }}
                            </button>
                            <button 
                                v-if="editando" 
                                @click="resetForm" 
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300 transition flex items-center gap-1"
                            >
                                <i class="fas fa-times text-xs"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABLA -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase"
                                        :style="{ color: `var(--color-primary-700)` }">Estado</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-semibold uppercase"
                                        :style="{ color: `var(--color-primary-700)` }">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in items.data" :key="item.IdEstado" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700">
                                        <i class="fas fa-tag mr-1 text-[10px]" :style="{ color: `var(--color-primary-400)` }"></i>
                                        {{ item.Estado }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">
                                        <button @click="editar(item)" class="mr-2 transition" :style="{ color: `var(--color-primary-600)` }" title="Editar">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button @click="abrirModalEliminar(item.IdEstado, item.Estado)" class="text-red-600 hover:text-red-800 transition" title="Eliminar">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!items.data || items.data.length === 0">
                                    <td colspan="2" class="px-3 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-tag text-2xl mb-1 block text-gray-300"></i>
                                        <span v-if="search">No hay estados que coincidan con "{{ search }}"</span>
                                        <span v-else>No hay estados de producto registrados</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="items.links && items.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-xs">
                            <div class="text-gray-500">
                                Mostrando {{ items.from || 0 }} a {{ items.to || 0 }} de {{ items.total || 0 }}
                            </div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link 
                                    v-for="link in items.links" 
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    class="px-2 py-0.5 rounded border text-xs transition"
                                    :style="{
                                        borderColor: link.active ? `var(--color-primary-600)` : '#e5e7eb',
                                        backgroundColor: link.active ? `var(--color-primary-600)` : 'white',
                                        color: link.active ? 'white' : '#374151'
                                    }"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE ELIMINACIÓN -->
        <div v-if="modalEliminarOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="cerrarModalEliminar">
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden animate-fade-in-up">
                <div class="bg-red-50 p-4 text-center">
                    <div class="w-12 h-12 mx-auto bg-red-100 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-trash-alt text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">¿Eliminar estado?</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        ¿Estás seguro de eliminar 
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

input:focus {
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}
</style>