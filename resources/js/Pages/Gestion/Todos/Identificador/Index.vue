<!-- resources/js/Pages/Gestion/Todos/Identificador/Index.vue -->
<script setup>
import { ref, watch, onMounted, inject } from 'vue'
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
const formData = ref({ CI_NIT: '', Nombre: '' })
const errors = ref({})
const search = ref(props.filtros?.search || '')
const processing = ref(false)

// Convertir a mayúsculas y solo números para CI_NIT
const formatearCI = () => {
    let valor = formData.value.CI_NIT.replace(/\D/g, '')
    formData.value.CI_NIT = valor
}

// Convertir a mayúsculas para Nombre
const formatearNombre = () => {
    formData.value.Nombre = formData.value.Nombre.toUpperCase()
}

// Resetear formulario
const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = { CI_NIT: '', Nombre: '' }
    errors.value = {}
}

// Editar
const editar = (item) => {
    editando.value = true
    editId.value = item.IdIdentificador
    formData.value = {
        CI_NIT: String(item.CI_NIT),
        Nombre: item.Nombre
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

// Guardar
const guardar = () => {
    if (formData.value.CI_NIT && !/^\d+$/.test(formData.value.CI_NIT)) {
        errors.value = { CI_NIT: 'El CI/NIT solo puede contener números' }
        return
    }
    
    processing.value = true
    
    if (editando.value) {
        router.put(`/gestion/todos/identificador/${editId.value}`, formData.value, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Identificador actualizado correctamente')
                resetForm()
                if (search.value) {
                    router.get('/gestion/todos/identificador', { search: search.value }, {
                        preserveState: true,
                        replace: true
                    })
                }
                processing.value = false
            },
            onError: (err) => { 
                errors.value = err
                toast?.error('Error', Object.values(err)[0]?.[0] || 'Error al actualizar')
                processing.value = false
            }
        })
    } else {
        router.post('/gestion/todos/identificador', formData.value, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Identificador creado correctamente')
                resetForm()
                if (search.value) {
                    router.get('/gestion/todos/identificador', { search: search.value }, {
                        preserveState: true,
                        replace: true
                    })
                }
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

// Limpiar búsqueda
const limpiarBusqueda = () => {
    search.value = ''
    router.get('/gestion/todos/identificador', {}, {
        preserveState: true,
        replace: true
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
watch(search, (newVal) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/gestion/todos/identificador', { search: newVal || undefined }, {
            preserveState: true,
            replace: true
        })
    }, 500)
})
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-6">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="flex items-center gap-2 mb-3 sm:mb-4">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                         :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                        <i class="fas fa-id-card text-xs sm:text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-sm sm:text-base font-bold text-gray-800 truncate">Identificadores</h1>
                        <p class="text-[9px] sm:text-[10px] text-gray-500 truncate">Administra las personas (CI/NIT) del sistema</p>
                    </div>
                </div>

                <!-- Búsqueda -->
                <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3 mb-3 sm:mb-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-2.5 sm:left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[10px] sm:text-xs"></i>
                        <input 
                            type="text" 
                            v-model="search" 
                            placeholder="Buscar por CI/NIT o nombre..." 
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
                        <span class="ml-2">({{ items.total || 0 }} resultados)</span>
                    </p>
                </div>

                <!-- Formulario inline -->
                <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-4 sm:mb-6 sticky top-2 z-10 border"
                     :style="{ borderColor: `var(--color-primary-200)` }">
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2 items-stretch sm:items-end">
                        <!-- CI/NIT -->
                        <div class="flex-1 min-w-[120px] sm:min-w-[150px]">
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">CI / NIT *</label>
                            <input 
                                type="text" 
                                v-model="formData.CI_NIT" 
                                @input="formatearCI"
                                placeholder="Solo números" 
                                class="w-full border rounded-md px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm font-mono"
                                :class="{ 'border-red-500': errors.CI_NIT }"
                                inputmode="numeric"
                            />
                            <p v-if="errors.CI_NIT" class="text-[10px] sm:text-xs text-red-500 mt-0.5">{{ errors.CI_NIT }}</p>
                        </div>

                        <!-- Nombre -->
                        <div class="flex-[2] min-w-[140px] sm:min-w-[180px]">
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Nombre *</label>
                            <input 
                                type="text" 
                                v-model="formData.Nombre" 
                                @input="formatearNombre"
                                @blur="formatearNombre"
                                placeholder="Nombre completo" 
                                class="w-full border rounded-md px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm uppercase"
                                :class="{ 'border-red-500': errors.Nombre }"
                            />
                            <p v-if="errors.Nombre" class="text-[10px] sm:text-xs text-red-500 mt-0.5">{{ errors.Nombre }}</p>
                        </div>

                        <!-- Botones -->
                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-2 w-full sm:w-auto">
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.CI_NIT || !formData.Nombre"
                                class="flex-1 sm:flex-none px-3 sm:px-4 py-1.5 sm:py-2 text-white rounded-md text-xs sm:text-sm transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-[10px] sm:text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-plus'" class="text-[10px] sm:text-xs"></i>
                                <span class="truncate">{{ processing ? 'Procesando...' : (editando ? 'Actualizar' : 'Guardar') }}</span>
                            </button>
                            <button 
                                v-if="editando" 
                                @click="resetForm" 
                                class="flex-1 sm:flex-none px-3 sm:px-4 py-1.5 sm:py-2 bg-gray-200 text-gray-700 rounded-md text-xs sm:text-sm hover:bg-gray-300 transition flex items-center justify-center gap-1"
                            >
                                <i class="fas fa-times text-[10px] sm:text-xs"></i> 
                                <span class="truncate">Cancelar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABLA -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <!-- Vista Desktop (tabla) -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase"
                                        :style="{ color: `var(--color-primary-700)` }">CI / NIT</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase"
                                        :style="{ color: `var(--color-primary-700)` }">Nombre</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase"
                                        :style="{ color: `var(--color-primary-700)` }">Fecha Ingreso</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-semibold uppercase"
                                        :style="{ color: `var(--color-primary-700)` }">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in items.data" :key="item.IdIdentificador" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 whitespace-nowrap text-xs font-mono text-gray-900">{{ item.CI_NIT }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700">
                                        <i class="fas fa-user mr-1 text-[10px]" :style="{ color: `var(--color-primary-400)` }"></i>
                                        {{ item.Nombre }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">
                                        {{ new Date(item.FechaIngreso).toLocaleDateString('es-BO') }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">
                                        <button @click="editar(item)" class="transition p-1 hover:bg-primary-50 rounded" :style="{ color: `var(--color-primary-600)` }" title="Editar">
                                            <i class="fas fa-edit text-xs sm:text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!items.data || items.data.length === 0">
                                    <td colspan="4" class="px-3 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-id-card text-2xl mb-1 block text-gray-300"></i>
                                        <span v-if="search">No hay identificadores que coincidan con "{{ search }}"</span>
                                        <span v-else>No hay identificadores registrados</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Vista Mobile (tarjetas) -->
                    <div class="sm:hidden divide-y divide-gray-200">
                        <div v-for="item in items.data" :key="item.IdIdentificador" class="p-3 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start mb-1">
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-semibold text-gray-900 truncate">
                                        <i class="fas fa-user mr-1" :style="{ color: `var(--color-primary-400)` }"></i>
                                        {{ item.Nombre }}
                                    </div>
                                    <div class="text-xs font-mono text-gray-600 mt-0.5">
                                        <i class="fas fa-id-card mr-1 text-gray-400"></i>
                                        {{ item.CI_NIT }}
                                    </div>
                                </div>
                                <button @click="editar(item)" class="ml-2 p-1.5 hover:bg-primary-50 rounded flex-shrink-0" :style="{ color: `var(--color-primary-600)` }" title="Editar">
                                    <i class="fas fa-edit text-sm"></i>
                                </button>
                            </div>
                            <div class="text-[10px] text-gray-400 mt-0.5">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                {{ new Date(item.FechaIngreso).toLocaleDateString('es-BO') }}
                            </div>
                        </div>
                        <div v-if="!items.data || items.data.length === 0" class="px-3 py-8 text-center text-gray-400 text-xs">
                            <i class="fas fa-id-card text-2xl mb-1 block text-gray-300"></i>
                            <span v-if="search">No hay identificadores que coincidan con "{{ search }}"</span>
                            <span v-else>No hay identificadores registrados</span>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div v-if="items.links && items.links.length > 1" class="px-2 sm:px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-[10px] sm:text-xs">
                            <div class="text-gray-500 text-center sm:text-left">
                                Mostrando {{ items.from || 0 }} a {{ items.to || 0 }} de {{ items.total || 0 }}
                            </div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link 
                                    v-for="link in items.links" 
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    class="px-1.5 sm:px-2 py-0.5 rounded border text-[10px] sm:text-xs transition min-w-[28px] sm:min-w-[32px] text-center"
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

/* Mejoras para mobile */
@media (max-width: 640px) {
    .sticky {
        position: sticky;
        top: 0.5rem;
    }
}

/* Transiciones suaves */
.transition {
    transition: all 0.15s ease-in-out;
}
</style>