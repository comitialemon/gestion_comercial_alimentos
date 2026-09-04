<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ModalOperador from './ModalOperador.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    operadores: Object,
    tiposOperador: Array,
    identificadores: Array,
    filtros: Object,
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
const modalOpen = ref(false)
const editando = ref(false)
const operadorSeleccionado = ref(null)

// Filtros
const search = ref(props.filtros?.search || '')
const estado = ref(props.filtros?.estado || '')
const tipo = ref(props.filtros?.tipo || '')

// ==================== COMPUTED ====================
const hayFiltrosAplicados = computed(() => {
    return search.value || estado.value || tipo.value
})

// ==================== FUNCIONES ====================
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

const nuevoOperador = () => {
    operadorSeleccionado.value = null
    editando.value = false
    modalOpen.value = true
}

const editarOperador = (operador) => {
    operadorSeleccionado.value = operador
    editando.value = true
    modalOpen.value = true
}

const recargarDatos = () => {
    aplicarFiltros()
}

const estadoTexto = (activo) => {
    return activo === 0 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    clearTimeout(timeout)
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
                            <i class="fas fa-users text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Operadores</h1>
                            <p class="text-xs text-gray-500">Gestión de usuarios del sistema</p>
                        </div>
                    </div>
                    <button @click="nuevoOperador" 
                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition">
                        <i class="fas fa-plus text-[10px]"></i> Nuevo Operador
                    </button>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[140px] max-w-[240px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar</label>
                            <input type="text" v-model="search" placeholder="Nombre, CI o usuario..."
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Estado</label>
                            <select v-model="estado" class="w-28 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Todos</option>
                                <option value="0">Activos</option>
                                <option value="1">Inactivos</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Tipo</label>
                            <select v-model="tipo" class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Todos</option>
                                <option v-for="t in tiposOperador" :key="t.IdOperadorTipo" :value="t.IdOperadorTipo">
                                    {{ t.Detalle }}
                                </option>
                            </select>
                        </div>
                        <div class="flex gap-1.5 ml-auto">
                            <button @click="limpiarFiltros" 
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-eraser text-[10px]"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== TABLA ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Scroll container -->
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="operador in operadores.data" :key="operador.IdOperador" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-user text-primary-500 text-[10px]"></i>
                                            <span class="text-xs font-semibold text-gray-800 truncate">{{ operador.identificador?.Nombre || '-' }}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-0.5 mt-1 text-[9px]">
                                            <div><span class="text-gray-500">CI:</span> <span class="font-mono">{{ operador.identificador?.CI_NIT || '-' }}</span></div>
                                            <div><span class="text-gray-500">Usuario:</span> <span>{{ operador.NombreAcceso }}</span></div>
                                            <div class="col-span-2"><span class="text-gray-500">Tipo:</span> <span>{{ operador.tipo?.Detalle || '-' }}</span></div>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="px-1.5 py-0.5 text-[7px] rounded-full" :class="estadoClase(operador.ActivoInactivo)">
                                            {{ estadoTexto(operador.ActivoInactivo) }}
                                        </span>
                                        <button @click="editarOperador(operador)" 
                                            class="text-primary-600 hover:text-primary-800 text-[10px] p-1 rounded hover:bg-primary-50">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="operadores.data.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-users text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay operadores registrados</span>
                            </div>
                            <!-- Paginación móvil -->
                            <div v-if="operadores.links && operadores.links.length > 1" class="bg-white rounded-lg p-2 border border-gray-200">
                                <div class="flex justify-center gap-0.5 flex-wrap">
                                    <Link v-for="link in operadores.links" :key="link.label" :href="link.url || '#'" 
                                        class="px-2 py-0.5 rounded border text-[9px] transition min-w-[24px] text-center"
                                        :class="{ 
                                            'bg-primary-600 text-white border-primary-600': link.active, 
                                            'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 
                                            'opacity-50 cursor-not-allowed': !link.url 
                                        }" 
                                        v-html="link.label" />
                                </div>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO (tabla) -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <!-- 🔥 THEAD STICKY (simplificado) -->
                            <thead class="bg-primary-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">ID</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">CI/NIT</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Nombre</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Usuario</th>
                                    <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Tipo</th>
                                    <th class="px-3 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase w-24">Estado</th>
                                    <th class="px-3 py-1.5 text-right text-[9px] font-medium text-primary-700 uppercase w-12">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="operador in operadores.data" :key="operador.IdOperador" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-1.5 text-xs text-gray-500">{{ operador.IdOperador }}</td>
                                    <td class="px-3 py-1.5 text-xs font-mono text-gray-600">{{ operador.identificador?.CI_NIT || '-' }}</td>
                                    <td class="px-3 py-1.5 text-xs text-gray-700">
                                        <i class="fas fa-user text-gray-400 mr-1 text-[8px]"></i>
                                        {{ operador.identificador?.Nombre || '-' }}
                                    </td>
                                    <td class="px-3 py-1.5 text-xs text-gray-600">{{ operador.NombreAcceso }}</td>
                                    <td class="px-3 py-1.5 text-xs text-gray-600">{{ operador.tipo?.Detalle || '-' }}</td>
                                    <td class="px-3 py-1.5 text-center">
                                        <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(operador.ActivoInactivo)">
                                            {{ estadoTexto(operador.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right">
                                        <button @click="editarOperador(operador)" 
                                            class="text-primary-600 hover:text-primary-800 transition text-xs p-1 rounded hover:bg-primary-50" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="operadores.data.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-users text-2xl mb-1 block"></i>
                                        No hay operadores registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ==================== PAGINACIÓN DESKTOP ==================== -->
                    <div v-if="operadores.links && operadores.links.length > 1 && !isMobile" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <div class="text-[10px] text-gray-500">
                                Mostrando {{ operadores.from || 0 }} a {{ operadores.to || 0 }} de {{ operadores.total || 0 }}
                            </div>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link v-for="link in operadores.links" :key="link.label" :href="link.url || '#'" 
                                    class="px-2.5 py-1 rounded-lg border text-[10px] transition"
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

        <!-- ==================== MODAL ==================== -->
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
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

/* 🔥 STICKY HEADER - Simplificado */
.sticky {
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
}

/* Scrollbar personalizada */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>