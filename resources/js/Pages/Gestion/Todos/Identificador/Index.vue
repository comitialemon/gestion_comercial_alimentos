<!-- resources/js/Pages/Gestion/Todos/Identificador/Index.vue -->
<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

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
}

// Guardar
const guardar = () => {
    if (formData.value.CI_NIT && !/^\d+$/.test(formData.value.CI_NIT)) {
        errors.value = { CI_NIT: 'El CI/NIT solo puede contener números' }
        return
    }
    
    if (editando.value) {
        router.put(`/gestion/todos/identificador/${editId.value}`, formData.value, {
            preserveScroll: true,
            onSuccess: () => {
                resetForm()
                // 🔥 Mantener la búsqueda actual después de actualizar
                if (search.value) {
                    router.get('/gestion/todos/identificador', { search: search.value }, {
                        preserveState: true,
                        replace: true
                    })
                }
            },
            onError: (err) => { errors.value = err }
        })
    } else {
        router.post('/gestion/todos/identificador', formData.value, {
            preserveScroll: true,
            onSuccess: () => {
                resetForm()
                // 🔥 Mantener la búsqueda actual después de crear
                if (search.value) {
                    router.get('/gestion/todos/identificador', { search: search.value }, {
                        preserveState: true,
                        replace: true
                    })
                }
            },
            onError: (err) => { errors.value = err }
        })
    }
}

// Eliminar
const eliminar = (id, nombre) => {
    if (confirm(`¿Eliminar el identificador "${nombre}"?`)) {
        router.delete(`/gestion/todos/identificador/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                // 🔥 Mantener la búsqueda actual después de eliminar
                if (search.value) {
                    router.get('/gestion/todos/identificador', { search: search.value }, {
                        preserveState: true,
                        replace: true
                    })
                }
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

resetForm()
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-purple-100 rounded-2xl mb-3">
                        <i class="fas fa-id-card text-xl text-purple-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Identificadores</h1>
                    <p class="text-xs text-gray-500">Administra las personas (CI/NIT) del sistema</p>
                </div>

                <!-- Búsqueda -->
                <div class="mb-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                        <input 
                            type="text" 
                            v-model="search" 
                            placeholder="Buscar por CI/NIT o nombre..." 
                            class="w-full border rounded-lg pl-10 pr-10 py-2 text-sm"
                        />
                        <button 
                            v-if="search" 
                            @click="limpiarBusqueda"
                            class="absolute right-3 top-2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        La búsqueda se mantiene después de guardar/eliminar
                    </p>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <input 
                                type="text" 
                                v-model="formData.CI_NIT" 
                                @input="formatearCI"
                                placeholder="CI / NIT (solo números)" 
                                class="w-full border rounded-lg px-3 py-2 text-sm font-mono"
                                :class="{ 'border-red-500': errors.CI_NIT }"
                                inputmode="numeric"
                            />
                            <p v-if="errors.CI_NIT" class="text-xs text-red-500 mt-1">{{ errors.CI_NIT }}</p>
                            <p class="text-xs text-gray-400 mt-1">Solo números, letras no permitidas</p>
                        </div>
                        <div class="flex gap-2">
                            <input 
                                type="text" 
                                v-model="formData.Nombre" 
                                @input="formatearNombre"
                                @blur="formatearNombre"
                                placeholder="Nombre completo" 
                                class="flex-1 border rounded-lg px-3 py-2 text-sm uppercase"
                                :class="{ 'border-red-500': errors.Nombre }"
                                style="text-transform: uppercase"
                            />
                            <button @click="guardar" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700">
                                <i class="fas" :class="editando ? 'fa-pencil-alt' : 'fa-plus'"></i>
                                {{ editando ? 'Actualizar' : 'Guardar' }}
                            </button>
                            <button v-if="editando" @click="resetForm" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CI / NIT</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Ingreso</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in items.data" :key="item.IdIdentificador" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.IdIdentificador }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ item.CI_NIT }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <i class="fas fa-user text-gray-400 mr-2"></i>
                                        {{ item.Nombre }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ new Date(item.FechaIngreso).toLocaleDateString('es-BO') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="editar(item)" class="text-purple-600 hover:text-purple-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="eliminar(item.IdIdentificador, item.Nombre)" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="items.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        No hay identificadores que coincidan con la búsqueda
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="items.links && items.links.length > 1" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ items.from || 0 }} a {{ items.to || 0 }} de {{ items.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link 
                                    v-for="link in items.links" 
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    class="px-3 py-1 rounded border text-sm"
                                    :class="{
                                        'bg-purple-600 text-white border-purple-600': link.active,
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
    </div>
</template>