<!-- resources/js/Pages/Gestion/Inventario/ProductoGrupoAnalisis/Index.vue -->
<script setup>
import { ref, onMounted, inject } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    items: Object,
    filtros: Object,
})

const editando = ref(false)
const editId = ref(null)
const formData = ref({ Grupo: '' })
const errors = ref({})
const processing = ref(false)
const search = ref(props.filtros?.search || '')

const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = { Grupo: '' }
    errors.value = {}
}

const editar = (item) => {
    editando.value = true
    editId.value = item.IdGrupoAnalisis
    formData.value = { Grupo: item.Grupo }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const guardar = () => {
    if (!formData.value.Grupo || formData.value.Grupo.trim() === '') {
        toast?.error('Validación', 'Ingrese el nombre del grupo')
        return
    }
    
    processing.value = true
    
    if (editando.value) {
        router.put(`/gestion/inventario/producto-grupo-analisis/${editId.value}`, formData.value, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Grupo actualizado correctamente')
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
        router.post('/gestion/inventario/producto-grupo-analisis', formData.value, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Grupo creado correctamente')
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
        router.get('/gestion/inventario/producto-grupo-analisis', { search: val || undefined }, {
            preserveState: true,
            replace: true
        })
    }, 500)
}

const limpiarBusqueda = () => {
    search.value = ''
    router.get('/gestion/inventario/producto-grupo-analisis', {}, {
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
                        <i class="fas fa-chart-pie text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-gray-800">Grupos de Análisis</h1>
                        <p class="text-[10px] text-gray-500">Administra los grupos para análisis de ventas</p>
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
                            placeholder="Buscar grupo..." 
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

                <!-- Formulario inline -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6 sticky top-2 z-10 border"
                     :style="{ borderColor: `var(--color-primary-200)` }">
                    <div class="flex flex-wrap gap-2 items-end">
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Grupo de Análisis *</label>
                            <input 
                                type="text" 
                                v-model="formData.Grupo" 
                                placeholder="Ej: Alimentos, Bebidas, Limpieza" 
                                class="w-full border rounded-md px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.Grupo }"
                                :style="{ borderColor: errors.Grupo ? '#ef4444' : `var(--color-primary-300)` }"
                                @keyup.enter="guardar"
                            />
                            <p v-if="errors.Grupo" class="text-xs text-red-500 mt-0.5">{{ errors.Grupo }}</p>
                        </div>

                        <div class="flex gap-2">
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.Grupo"
                                class="px-4 py-2 text-white rounded-md text-sm transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-plus'" class="text-xs"></i>
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
                                        :style="{ color: `var(--color-primary-700)` }">Grupo de Análisis</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-semibold uppercase"
                                        :style="{ color: `var(--color-primary-700)` }">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in items.data" :key="item.IdGrupoAnalisis" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700">
                                        <i class="fas fa-chart-pie mr-1 text-[10px]" :style="{ color: `var(--color-primary-400)` }"></i>
                                        {{ item.Grupo }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">
                                        <button @click="editar(item)" class="transition" :style="{ color: `var(--color-primary-600)` }" title="Editar">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!items.data || items.data.length === 0">
                                    <td colspan="2" class="px-3 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-chart-pie text-2xl mb-1 block text-gray-300"></i>
                                        <span v-if="search">No hay grupos que coincidan con "{{ search }}"</span>
                                        <span v-else>No hay grupos de análisis registrados</span>
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
    </div>
</template>

<style scoped>
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