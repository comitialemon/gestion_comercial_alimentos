<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    estructura: {
        type: Array,
        default: () => []
    }
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 640
}

// ==================== ESTADO ====================
const grupos = ref([])
const guardando = ref(false)
const busqueda = ref('')

// ==================== COMPUTED ====================
const gruposFiltrados = computed(() => {
    if (!busqueda.value.trim()) return grupos.value
    const q = busqueda.value.toLowerCase()
    const regex = new RegExp(`(^|\\s|-)${escapeRegex(q)}`, 'i')
    return grupos.value.filter(g => regex.test(g.NombreGrupo))
})

const totalActivos = computed(() => {
    return grupos.value.filter(g => g.Aplica).length
})

const tieneCambios = computed(() => {
    return grupos.value.some(g => g.Aplica !== g._original)
})

// ==================== FUNCIONES ====================
const escapeRegex = (str) => {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

const toggleAplica = (grupo) => {
    grupo.Aplica = !grupo.Aplica
}

const guardar = async () => {
    if (!tieneCambios.value) {
        toast?.info('Sin cambios', 'No hay cambios para guardar')
        return
    }

    guardando.value = true

    try {
        const response = await axios.post('/operacion/pedidos/clientes-mayoristas/minimos-globales/guardar', {
            grupos: grupos.value.map(g => ({
                IdGrupoAnalisis: g.IdGrupoAnalisis,
                Aplica: g.Aplica,
            }))
        })

        if (response.data.success) {
            toast?.success('Éxito', response.data.message || 'Guardado correctamente')
            grupos.value.forEach(g => g._original = g.Aplica)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardando.value = false
    }
}

const limpiarTodo = async () => {
    if (!confirm('¿Estás seguro de desactivar TODOS los grupos? Esta acción no se puede deshacer.')) return

    try {
        const response = await axios.delete('/operacion/pedidos/clientes-mayoristas/minimos-globales/limpiar')
        if (response.data.success) {
            grupos.value.forEach(g => {
                g.Aplica = false
                g._original = false
            })
            toast?.success('Éxito', response.data.message)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al limpiar')
    }
}

const volver = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/grupos-clientes')
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)

    grupos.value = props.estructura.map(g => ({
        IdGrupoAnalisis: g.IdGrupoAnalisis,
        NombreGrupo: g.NombreGrupo,
        Aplica: !!g.Aplica,
        TotalProductos: g.TotalProductos || 0,
        _original: !!g.Aplica,
    }))
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-5xl mx-auto">

                <!-- HEADER -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <button
                            @click="volver"
                            class="w-9 h-9 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary-600 hover:border-primary-300 transition flex-shrink-0"
                        >
                            <i class="fas fa-arrow-left text-xs"></i>
                        </button>
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-toggle-on text-primary-600 text-base"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-base lg:text-lg font-bold text-gray-800 truncate">
                                Grupos Aplicables para Pedidos
                            </h1>
                            <p class="text-xs text-gray-500 truncate">
                                Activa los grupos que requieren mínimos de producto
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-1.5 flex-wrap">
                        <button
                            @click="guardar"
                            :disabled="guardando || !tieneCambios"
                            class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-xs font-medium transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            {{ guardando ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </div>

                <!-- INFO -->
                <div class="bg-primary-50 border border-primary-200 rounded-xl p-2.5 mb-4 flex items-start gap-2">
                    <i class="fas fa-info-circle text-primary-500 text-[10px] flex-shrink-0 mt-0.5"></i>
                    <div class="text-xs text-primary-700">
                        <p class="font-medium mb-0.5 text-[11px]">¿Cómo funciona?</p>
                        <p class="text-[10px]">
                            Los grupos <strong>ACTIVOS</strong> requieren configurar el mínimo individual de cada producto.
                            Los grupos <strong>INACTIVOS</strong> no aplican para pedidos (ej: Acuarios, Varios).
                        </p>
                    </div>
                </div>

                <!-- RESUMEN + BUSCADOR -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check-circle text-emerald-600 text-[10px]"></i>
                            </div>
                            <div>
                                <p class="text-[8px] text-gray-400 uppercase tracking-wide">Activos</p>
                                <p class="text-base font-bold text-gray-800">
                                    {{ totalActivos }} <span class="text-xs text-gray-400 font-normal">/ {{ grupos.length }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="relative flex-1 min-w-[180px] max-w-[280px] ml-auto">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                            <input
                                v-model="busqueda"
                                type="text"
                                placeholder="Buscar grupo..."
                                class="w-full border border-gray-300 rounded-md pl-7 pr-7 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                            <button
                                v-if="busqueda"
                                @click="busqueda = ''"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <i class="fas fa-times text-[10px]"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- LISTA DE GRUPOS -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                    <div class="relative overflow-x-auto" style="max-height: 65vh; overflow-y: auto;">

                        <!-- VISTA MÓVIL -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="grupo in gruposFiltrados" :key="grupo.IdGrupoAnalisis"
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-center mb-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-gray-800 truncate">{{ grupo.NombreGrupo }}</p>
                                        <p class="text-[9px] text-gray-500">{{ grupo.TotalProductos }} productos</p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="toggleAplica(grupo)"
                                        class="relative inline-flex items-center h-6 rounded-full w-11 transition-colors duration-200 flex-shrink-0"
                                        :class="grupo.Aplica ? 'bg-emerald-600' : 'bg-gray-300'"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                                            :class="grupo.Aplica ? 'translate-x-6' : 'translate-x-1'"
                                        ></span>
                                    </button>
                                </div>
                                <span
                                    class="text-[8px] px-1.5 py-0.5 rounded-full font-medium inline-block"
                                    :class="grupo.Aplica ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500'"
                                >
                                    {{ grupo.Aplica ? 'ACTIVO' : 'INACTIVO' }}
                                </span>
                            </div>
                            <div v-if="gruposFiltrados.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-search text-2xl mb-1 block"></i>
                                <span class="text-xs">No se encontraron grupos</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[9px] font-medium text-primary-700 uppercase">Grupo de Análisis</th>
                                    <th class="px-3 py-2 text-center text-[9px] font-medium text-primary-700 uppercase w-24">Productos</th>
                                    <th class="px-3 py-2 text-center text-[9px] font-medium text-primary-700 uppercase w-32">Aplica</th>
                                    <th class="px-3 py-2 text-center text-[9px] font-medium text-primary-700 uppercase w-24">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="grupo in gruposFiltrados"
                                    :key="grupo.IdGrupoAnalisis"
                                    class="hover:bg-gray-50 transition"
                                >
                                    <td class="px-3 py-2 text-xs text-gray-800 font-medium">
                                        {{ grupo.NombreGrupo }}
                                    </td>
                                    <td class="px-3 py-2 text-center text-xs text-gray-600">
                                        {{ grupo.TotalProductos }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            @click="toggleAplica(grupo)"
                                            class="relative inline-flex items-center h-6 rounded-full w-11 transition-colors duration-200"
                                            :class="grupo.Aplica ? 'bg-emerald-600' : 'bg-gray-300'"
                                        >
                                            <span
                                                class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                                                :class="grupo.Aplica ? 'translate-x-6' : 'translate-x-1'"
                                            ></span>
                                        </button>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span
                                            v-if="grupo.Aplica"
                                            class="inline-block px-1.5 py-0.5 text-[9px] font-medium rounded-full bg-emerald-100 text-emerald-700"
                                        >
                                            ACTIVO
                                        </span>
                                        <span
                                            v-else
                                            class="inline-block px-1.5 py-0.5 text-[9px] font-medium rounded-full bg-gray-200 text-gray-500"
                                        >
                                            INACTIVO
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="gruposFiltrados.length === 0">
                                    <td colspan="4" class="text-center py-8 text-gray-400 text-sm">
                                        <i class="fas fa-search text-2xl mb-1 block"></i>
                                        No se encontraron grupos
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- INFO FOOTER -->
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-2.5 mt-4 flex items-start gap-2">
                    <i class="fas fa-lightbulb text-amber-500 text-[10px] flex-shrink-0 mt-0.5"></i>
                    <div class="text-xs text-amber-700">
                        <p class="font-medium mb-0.5 text-[11px]">Recuerda:</p>
                        <p class="text-[10px]">
                            Al activar un grupo, sus productos podrán configurarse con mínimo individual en el módulo <strong>Producto Detalle</strong>.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, textarea, button {
        font-size: 13px !important;
    }
}

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