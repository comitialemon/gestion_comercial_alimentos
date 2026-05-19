<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import ModalOperador from './ModalOperador.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    operadores: Object,
    tiposOperador: Array,
    identificadores: Array,
    filtros: Object,
})

// Estado del modal
const modalOpen = ref(false)
const editando = ref(false)
const operadorSeleccionado = ref(null)

// Filtros
const search = ref(props.filtros?.search || '')
const estado = ref(props.filtros?.estado || '')
const tipo = ref(props.filtros?.tipo || '')

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/gestion/operadores', {
        search: search.value || undefined,
        estado: estado.value || undefined,
        tipo: tipo.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

// Limpiar filtros
const limpiarFiltros = () => {
    search.value = ''
    estado.value = ''
    tipo.value = ''
    aplicarFiltros()
}

// Debounce para búsqueda
let timeout
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        aplicarFiltros()
    }, 500)
})

watch([estado, tipo], () => {
    aplicarFiltros()
})

// Abrir modal para nuevo operador
const nuevoOperador = () => {
    operadorSeleccionado.value = null
    editando.value = false
    modalOpen.value = true
}

// Abrir modal para editar
const editarOperador = (operador) => {
    operadorSeleccionado.value = operador
    editando.value = true
    modalOpen.value = true
}

// Recargar datos después de guardar
const recargarDatos = () => {
    aplicarFiltros()
}

const estadoTexto = (activo) => {
    return activo === 0 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo === 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-users text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Operadores</h1>
                            <p class="text-sm text-gray-500">Gestión de usuarios del sistema</p>
                        </div>
                    </div>
                    <button @click="nuevoOperador" class="bg-guindo-600 hover:bg-guindo-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                        <i class="fas fa-plus"></i> Nuevo Operador
                    </button>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                            <input 
                                type="text" 
                                v-model="search" 
                                placeholder="Buscar por nombre, CI o usuario..." 
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Estado</label>
                            <select v-model="estado" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todos</option>
                                <option value="0">Activos</option>
                                <option value="1">Inactivos</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tipo de Operador</label>
                            <select v-model="tipo" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todos</option>
                                <option v-for="t in tiposOperador" :key="t.IdOperadorTipo" :value="t.IdOperadorTipo">
                                    {{ t.Detalle }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button @click="limpiarFiltros" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                            <i class="fas fa-eraser"></i> Limpiar filtros
                        </button>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-guindo-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">CI/NIT</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Usuario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-guindo-700 uppercase">Estado</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="operador in operadores.data" :key="operador.IdOperador" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ operador.IdOperador }}</td>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ operador.identificador?.CI_NIT || '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <i class="fas fa-user text-gray-400 mr-2"></i>
                                        {{ operador.identificador?.Nombre || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ operador.NombreAcceso }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ operador.tipo?.Detalle || '-' }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="estadoClase(operador.ActivoInactivo)">
                                            {{ estadoTexto(operador.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button @click="editarOperador(operador)" class="text-guindo-600 hover:text-guindo-800" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="operadores.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-users text-3xl mb-2 block"></i>
                                        No hay operadores registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="operadores.links && operadores.links.length > 1" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ operadores.from || 0 }} a {{ operadores.to || 0 }} de {{ operadores.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link 
                                    v-for="link in operadores.links" 
                                    :key="link.label" 
                                    :href="link.url || '#'" 
                                    class="px-3 py-1 rounded border text-sm"
                                    :class="{ 
                                        'bg-guindo-600 text-white border-guindo-600': link.active, 
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

        <!-- Modal -->
        <ModalOperador
            v-model="modalOpen"
            :operador="operadorSeleccionado"
            :tipos-operador="tiposOperador"
            :identificadores="identificadores"
            :editando="editando"
            @saved="recargarDatos"
        />
    </div>
</template>