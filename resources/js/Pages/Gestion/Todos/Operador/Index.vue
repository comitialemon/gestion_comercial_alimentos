<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, onMounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
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
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-4 sm:px-4 lg:py-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-4 mb-4 sm:mb-6">
                    <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-users text-primary-600 text-sm sm:text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-base sm:text-2xl font-bold text-gray-800 truncate">Operadores</h1>
                            <p class="text-[10px] sm:text-sm text-gray-500 truncate">Gestión de usuarios del sistema</p>
                        </div>
                    </div>
                    <button @click="nuevoOperador" class="w-full sm:w-auto bg-primary-600 hover:bg-primary-700 text-white px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg flex items-center justify-center gap-2 transition text-sm sm:text-base">
                        <i class="fas fa-plus text-xs sm:text-sm"></i> 
                        <span>Nuevo Operador</span>
                    </button>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-3 sm:mb-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Buscar</label>
                            <input 
                                type="text" 
                                v-model="search" 
                                placeholder="Buscar por nombre, CI o usuario..." 
                                class="w-full border rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                            >
                        </div>
                        <div>
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Estado</label>
                            <select v-model="estado" class="w-full border rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }">
                                <option value="">Todos</option>
                                <option value="0">Activos</option>
                                <option value="1">Inactivos</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Tipo de Operador</label>
                            <select v-model="tipo" class="w-full border rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }">
                                <option value="">Todos</option>
                                <option v-for="t in tiposOperador" :key="t.IdOperadorTipo" :value="t.IdOperadorTipo">
                                    {{ t.Detalle }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2 sm:mt-3 flex justify-end">
                        <button @click="limpiarFiltros" class="text-[10px] sm:text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 transition">
                            <i class="fas fa-eraser text-xs"></i> Limpiar filtros
                        </button>
                    </div>
                </div>

                <!-- Tabla Desktop -->
                <div class="hidden md:block bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 lg:px-6 py-2 lg:py-3 text-left text-[10px] lg:text-xs font-medium text-primary-700 uppercase">ID</th>
                                    <th class="px-3 lg:px-6 py-2 lg:py-3 text-left text-[10px] lg:text-xs font-medium text-primary-700 uppercase">CI/NIT</th>
                                    <th class="px-3 lg:px-6 py-2 lg:py-3 text-left text-[10px] lg:text-xs font-medium text-primary-700 uppercase">Nombre</th>
                                    <th class="px-3 lg:px-6 py-2 lg:py-3 text-left text-[10px] lg:text-xs font-medium text-primary-700 uppercase">Usuario</th>
                                    <th class="px-3 lg:px-6 py-2 lg:py-3 text-left text-[10px] lg:text-xs font-medium text-primary-700 uppercase">Tipo</th>
                                    <th class="px-3 lg:px-6 py-2 lg:py-3 text-center text-[10px] lg:text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 lg:px-6 py-2 lg:py-3 text-right text-[10px] lg:text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="operador in operadores.data" :key="operador.IdOperador" class="hover:bg-gray-50 transition">
                                    <td class="px-3 lg:px-6 py-2 lg:py-4 text-xs lg:text-sm text-gray-500">{{ operador.IdOperador }}</td>
                                    <td class="px-3 lg:px-6 py-2 lg:py-4 text-xs lg:text-sm font-mono text-gray-900">{{ operador.identificador?.CI_NIT || '-' }}</td>
                                    <td class="px-3 lg:px-6 py-2 lg:py-4 text-xs lg:text-sm text-gray-700">
                                        <i class="fas fa-user text-gray-400 mr-1 lg:mr-2 text-[10px] lg:text-xs"></i>
                                        {{ operador.identificador?.Nombre || '-' }}
                                    </td>
                                    <td class="px-3 lg:px-6 py-2 lg:py-4 text-xs lg:text-sm text-gray-600">{{ operador.NombreAcceso }}</td>
                                    <td class="px-3 lg:px-6 py-2 lg:py-4 text-xs lg:text-sm text-gray-600">{{ operador.tipo?.Detalle || '-' }}</td>
                                    <td class="px-3 lg:px-6 py-2 lg:py-4 text-center">
                                        <span class="px-1.5 lg:px-2 py-0.5 lg:py-1 text-[9px] lg:text-xs rounded-full" :class="estadoClase(operador.ActivoInactivo)">
                                            {{ estadoTexto(operador.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-3 lg:px-6 py-2 lg:py-4 text-right">
                                        <button @click="editarOperador(operador)" class="text-primary-600 hover:text-primary-800 transition p-1 hover:bg-primary-50 rounded" title="Editar">
                                            <i class="fas fa-edit text-xs lg:text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="operadores.data.length === 0">
                                    <td colspan="7" class="px-3 lg:px-6 py-8 lg:py-12 text-center text-gray-500">
                                        <i class="fas fa-users text-2xl lg:text-3xl mb-1 lg:mb-2 block text-gray-300"></i>
                                        <span class="text-xs lg:text-sm">No hay operadores registrados</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación Desktop -->
                    <div v-if="operadores.links && operadores.links.length > 1" class="px-3 lg:px-6 py-2 lg:py-4 border-t border-gray-200">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <div class="text-[10px] lg:text-sm text-gray-500">
                                Mostrando {{ operadores.from || 0 }} a {{ operadores.to || 0 }} de {{ operadores.total || 0 }}
                            </div>
                            <div class="flex gap-0.5 lg:gap-1 flex-wrap justify-center">
                                <Link 
                                    v-for="link in operadores.links" 
                                    :key="link.label" 
                                    :href="link.url || '#'" 
                                    class="px-1.5 lg:px-3 py-0.5 lg:py-1 rounded border text-[10px] lg:text-sm transition min-w-[24px] lg:min-w-[32px] text-center"
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

                <!-- Vista Mobile (tarjetas) -->
                <div class="md:hidden space-y-3 sm:space-y-4">
                    <div v-for="operador in operadores.data" :key="operador.IdOperador" 
                         class="bg-white rounded-xl shadow-sm p-3 sm:p-4 hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="fas fa-user text-primary-500 text-xs sm:text-sm"></i>
                                    <span class="text-xs sm:text-sm font-semibold text-gray-900 truncate">
                                        {{ operador.identificador?.Nombre || '-' }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-1 text-[10px] sm:text-xs">
                                    <div class="text-gray-500">
                                        <span class="font-medium text-gray-600">CI/NIT:</span>
                                        <span class="font-mono ml-1">{{ operador.identificador?.CI_NIT || '-' }}</span>
                                    </div>
                                    <div class="text-gray-500">
                                        <span class="font-medium text-gray-600">Usuario:</span>
                                        <span class="ml-1">{{ operador.NombreAcceso }}</span>
                                    </div>
                                    <div class="text-gray-500 col-span-2">
                                        <span class="font-medium text-gray-600">Tipo:</span>
                                        <span class="ml-1">{{ operador.tipo?.Detalle || '-' }}</span>
                                    </div>
                                    <div class="col-span-2 mt-1">
                                        <span class="px-2 py-0.5 text-[9px] rounded-full inline-block" :class="estadoClase(operador.ActivoInactivo)">
                                            {{ estadoTexto(operador.ActivoInactivo) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button @click="editarOperador(operador)" 
                                    class="ml-2 p-1.5 sm:p-2 text-primary-600 hover:text-primary-800 hover:bg-primary-50 rounded-lg transition flex-shrink-0">
                                <i class="fas fa-edit text-xs sm:text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div v-if="operadores.data.length === 0" class="bg-white rounded-xl shadow-sm p-6 sm:p-8 text-center text-gray-500">
                        <i class="fas fa-users text-2xl sm:text-3xl mb-2 block text-gray-300"></i>
                        <span class="text-xs sm:text-sm">No hay operadores registrados</span>
                    </div>

                    <!-- Paginación Mobile -->
                    <div v-if="operadores.links && operadores.links.length > 1" class="bg-white rounded-xl shadow-sm p-2 sm:p-3">
                        <div class="flex flex-col items-center gap-2">
                            <div class="text-[9px] sm:text-xs text-gray-500">
                                Mostrando {{ operadores.from || 0 }} a {{ operadores.to || 0 }} de {{ operadores.total || 0 }}
                            </div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link 
                                    v-for="link in operadores.links" 
                                    :key="link.label" 
                                    :href="link.url || '#'" 
                                    class="px-1.5 sm:px-2 py-0.5 sm:py-1 rounded border text-[9px] sm:text-xs transition min-w-[24px] sm:min-w-[28px] text-center"
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

<style scoped>
/* Transiciones y efectos */
.transition {
    transition: all 0.15s ease-in-out;
}

input:focus, select:focus {
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

/* Mejoras para dispositivos muy pequeños */
@media (max-width: 360px) {
    .grid-cols-2 {
        grid-template-columns: 1fr;
    }
}
</style>