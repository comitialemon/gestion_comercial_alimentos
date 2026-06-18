<!-- resources/js/Pages/Gestion/Inventario/UnidadMedida/Index.vue -->
<script setup>
import { ref, onMounted, inject, watch } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    items: Object,
    filtros: Object,
})

// Estado del formulario
const editando = ref(false)
const editId = ref(null)
const formData = ref({ UnidadMedida: '' })
const errors = ref({})
const processing = ref(false)
const search = ref(props.filtros?.search || '')

// Modal de eliminación
const modalEliminarOpen = ref(false)
const eliminarId = ref(null)
const eliminarNombre = ref('')
const eliminando = ref(false)

// Estado para controlar el formulario flotante en móvil
const formFloating = ref(false)

const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = { UnidadMedida: '' }
    errors.value = {}
}

const editar = (item) => {
    editando.value = true
    editId.value = item.IdUnidadMedida
    formData.value = { UnidadMedida: item.UnidadMedida }
    
    // En móvil, desplazar al formulario
    if (window.innerWidth < 768) {
        formFloating.value = true
        setTimeout(() => {
            document.querySelector('.form-container')?.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            })
        }, 100)
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' })
    }
}

const guardar = () => {
    if (!formData.value.UnidadMedida || formData.value.UnidadMedida.trim() === '') {
        toast?.error('Validación', 'Ingrese el nombre de la unidad de medida')
        return
    }
    
    processing.value = true
    
    if (editando.value) {
        router.put(`/gestion/inventario/unidad-medida/${editId.value}`, formData.value, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Unidad de medida actualizada correctamente')
                resetForm()
                processing.value = false
                formFloating.value = false
            },
            onError: (err) => {
                errors.value = err
                toast?.error('Error', Object.values(err)[0]?.[0] || 'Error al actualizar')
                processing.value = false
            }
        })
    } else {
        router.post('/gestion/inventario/unidad-medida', formData.value, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Unidad de medida creada correctamente')
                resetForm()
                processing.value = false
                formFloating.value = false
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
    
    router.delete(`/gestion/inventario/unidad-medida/${eliminarId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast?.success('Éxito', `Unidad "${eliminarNombre.value}" eliminada correctamente`)
            cerrarModalEliminar()
            eliminando.value = false
        },
        onError: () => {
            toast?.error('Error', 'No se pudo eliminar la unidad de medida')
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

// Búsqueda con debounce
let timeout
const buscar = (val) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/gestion/inventario/unidad-medida', { search: val || undefined }, {
            preserveState: true,
            replace: true
        })
    }, 500)
}

watch(search, (newVal) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/gestion/inventario/unidad-medida', { search: newVal || undefined }, {
            preserveState: true,
            replace: true
        })
    }, 500)
})

const limpiarBusqueda = () => {
    search.value = ''
    router.get('/gestion/inventario/unidad-medida', {}, {
        preserveState: true,
        replace: true
    })
}

// Cerrar formulario flotante
const cerrarFormularioFlotante = () => {
    formFloating.value = false
    resetForm()
}
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-6">
            <div class="max-w-4xl mx-auto">
                <!-- Header responsive -->
                <div class="flex items-center gap-2 mb-3 sm:mb-4">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                         :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                        <i class="fas fa-ruler text-[11px] sm:text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-sm sm:text-base font-bold text-gray-800 truncate">Unidades de Medida</h1>
                        <p class="text-[9px] sm:text-[10px] text-gray-500 truncate">Administra las unidades de medida (Kg, Lt, Pza, etc.)</p>
                    </div>
                </div>

                <!-- Búsqueda -->
                <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3 mb-3 sm:mb-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-2.5 sm:left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[10px] sm:text-xs"></i>
                        <input 
                            type="text" 
                            v-model="search" 
                            @input="buscar(search)"
                            placeholder="Buscar unidad..." 
                            class="w-full border rounded-md pl-7 sm:pl-8 pr-7 sm:pr-8 py-1.5 sm:py-2 text-xs sm:text-sm focus:ring-2 focus:outline-none"
                            :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                        />
                        <button 
                            v-if="search" 
                            @click="limpiarBusqueda"
                            class="absolute right-2.5 sm:right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-[10px] sm:text-xs"></i>
                        </button>
                    </div>
                    <p v-if="search" class="text-[9px] sm:text-[10px] text-gray-400 mt-1 truncate">
                        <i class="fas fa-info-circle mr-1"></i>
                        Mostrando resultados para: <span class="font-medium text-gray-600">{{ search }}</span>
                        <span class="ml-2 hidden sm:inline">({{ items.total || 0 }} resultados)</span>
                    </p>
                </div>

                <!-- Formulario -->
                <div class="form-container bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-4 sm:mb-6 border"
                     :class="{ 
                        'sticky top-2 z-10': !formFloating,
                        'fixed bottom-0 left-0 right-0 z-50 rounded-none shadow-lg border-t-4 animate-slide-up': formFloating
                     }"
                     :style="{ borderColor: `var(--color-primary-200)` }">
                    
                    <!-- Título del formulario con botón cerrar en móvil -->
                    <div class="flex items-center justify-between gap-2 mb-2 sm:mb-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="fas fa-plus-circle text-[10px] sm:text-xs flex-shrink-0" :style="{ color: `var(--color-primary-600)` }"></i>
                            <span class="text-[10px] sm:text-xs font-semibold truncate" :style="{ color: `var(--color-primary-700)` }">
                                {{ editando ? 'Editar Unidad' : 'Crear Unidad' }}
                            </span>
                        </div>
                        <button 
                            v-if="formFloating" 
                            @click="cerrarFormularioFlotante"
                            class="text-gray-400 hover:text-gray-600 flex-shrink-0"
                        >
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>

                    <div class="flex flex-col xs:flex-row gap-2 sm:gap-3">
                        <div class="flex-1 min-w-0">
                            <input 
                                type="text" 
                                v-model="formData.UnidadMedida" 
                                placeholder="Ej: Kilogramo, Litro, Pieza, Metro..." 
                                class="w-full border rounded-md px-2.5 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm"
                                :class="{ 'border-red-500': errors.UnidadMedida }"
                                :style="{ borderColor: errors.UnidadMedida ? '#ef4444' : `var(--color-primary-300)` }"
                                @keyup.enter="guardar"
                            />
                            <p v-if="errors.UnidadMedida" class="text-[9px] sm:text-xs text-red-500 mt-0.5 truncate">{{ errors.UnidadMedida }}</p>
                        </div>
                        <div class="flex gap-2 flex-shrink-0">
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.UnidadMedida"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 text-white rounded-md text-[11px] sm:text-sm transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-[10px] sm:text-xs"></i>
                                <i v-else :class="editando ? 'fa-pencil-alt' : 'fa-plus'" class="text-[10px] sm:text-xs"></i>
                                {{ processing ? 'Procesando...' : (editando ? 'Actualizar' : 'Guardar') }}
                            </button>
                            <button 
                                v-if="editando" 
                                @click="resetForm" 
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-gray-200 text-gray-700 rounded-md text-[11px] sm:text-sm hover:bg-gray-300 transition flex items-center gap-1.5"
                            >
                                <i class="fas fa-times text-[10px] sm:text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABLA -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <!-- Versión Desktop: Tabla completa -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase"
                                        :style="{ color: `var(--color-primary-700)` }">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase"
                                        :style="{ color: `var(--color-primary-700)` }">Unidad de Medida</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase"
                                        :style="{ color: `var(--color-primary-700)` }">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in items.data" :key="item.IdUnidadMedida" class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.IdUnidadMedida }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <i class="fas fa-ruler mr-2 text-[10px]" :style="{ color: `var(--color-primary-400)` }"></i>
                                        {{ item.UnidadMedida }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="editar(item)" class="mr-3 transition" :style="{ color: `var(--color-primary-600)` }" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="abrirModalEliminar(item.IdUnidadMedida, item.UnidadMedida)" class="text-red-600 hover:text-red-800 transition" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!items.data || items.data.length === 0">
                                    <td colspan="3" class="px-6 py-12 text-center text-gray-400 text-sm">
                                        <i class="fas fa-ruler text-3xl mb-2 block text-gray-300"></i>
                                        <span v-if="search">No hay unidades que coincidan con "{{ search }}"</span>
                                        <span v-else>No hay unidades de medida registradas</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Versión Móvil: Tarjetas -->
                    <div class="sm:hidden divide-y divide-gray-100">
                        <div v-for="item in items.data" :key="item.IdUnidadMedida" 
                             class="p-3 hover:bg-gray-50 transition">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <i class="fas fa-ruler text-[10px]" :style="{ color: `var(--color-primary-400)` }"></i>
                                        <span class="text-sm font-medium text-gray-800 truncate">{{ item.UnidadMedida }}</span>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        <i class="fas fa-hashtag mr-1 text-[10px]"></i>
                                        ID: {{ item.IdUnidadMedida }}
                                    </div>
                                </div>
                                <div class="flex gap-2 flex-shrink-0">
                                    <button @click="editar(item)" 
                                            class="p-1.5 rounded-lg transition"
                                            :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-600)` }"
                                            title="Editar">
                                        <i class="fas fa-edit text-sm"></i>
                                    </button>
                                    <button @click="abrirModalEliminar(item.IdUnidadMedida, item.UnidadMedida)" 
                                            class="p-1.5 rounded-lg transition bg-red-50 text-red-600"
                                            title="Eliminar">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-if="!items.data || items.data.length === 0" class="p-8 text-center text-gray-400 text-sm">
                            <i class="fas fa-ruler text-3xl mb-2 block text-gray-300"></i>
                            <span v-if="search">No hay unidades que coincidan con "{{ search }}"</span>
                            <span v-else>No hay unidades de medida registradas</span>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div v-if="items.links && items.links.length > 1" class="px-2 sm:px-6 py-2 sm:py-4 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col xs:flex-row justify-between items-center gap-2 text-[10px] sm:text-sm">
                            <div class="text-gray-500 text-[9px] sm:text-sm">
                                Mostrando {{ items.from || 0 }} - {{ items.to || 0 }} de {{ items.total || 0 }}
                            </div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link 
                                    v-for="link in items.links" 
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    class="px-1.5 sm:px-3 py-0.5 sm:py-1 rounded border text-[9px] sm:text-sm transition min-w-[24px] text-center"
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
        <div v-if="modalEliminarOpen" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/50 backdrop-blur-sm" @click.self="cerrarModalEliminar">
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden animate-fade-in-up mx-3 sm:mx-0">
                <div class="bg-red-50 p-4 sm:p-6 text-center">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 mx-auto bg-red-100 rounded-full flex items-center justify-center mb-2 sm:mb-3">
                        <i class="fas fa-trash-alt text-red-600 text-base sm:text-xl"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900">¿Eliminar unidad?</h3>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">
                        ¿Estás seguro de eliminar 
                        <span class="font-semibold text-gray-700 block sm:inline">"{{ eliminarNombre }}"</span>?
                    </p>
                    <p class="text-[10px] sm:text-xs text-red-500 mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="p-3 sm:p-4 flex flex-col xs:flex-row gap-2 sm:gap-3">
                    <button 
                        @click="cerrarModalEliminar"
                        class="flex-1 px-3 sm:px-4 py-1.5 sm:py-2 bg-gray-100 text-gray-700 rounded-lg text-xs sm:text-sm font-medium hover:bg-gray-200 transition order-2 xs:order-1"
                    >
                        Cancelar
                    </button>
                    <button 
                        @click="confirmarEliminar"
                        :disabled="eliminando"
                        class="flex-1 px-3 sm:px-4 py-1.5 sm:py-2 bg-red-600 text-white rounded-lg text-xs sm:text-sm font-medium hover:bg-red-700 transition disabled:opacity-50 flex items-center justify-center gap-2 order-1 xs:order-2"
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
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes slide-up {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.2s ease-out;
}

.animate-slide-up {
    animation: slide-up 0.3s ease-out;
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

/* Estilos para pantallas muy pequeñas */
@media (max-width: 380px) {
    .xs\:flex-row {
        flex-direction: column !important;
    }
    .xs\:order-1 {
        order: 1 !important;
    }
    .xs\:order-2 {
        order: 2 !important;
    }
}

/* Scroll suave */
* {
    scroll-behavior: smooth;
}
</style>