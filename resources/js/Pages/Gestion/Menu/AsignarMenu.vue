<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted, watch, computed, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import MenuTree from './MenuTree.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    operadores: Array,
    menuCompleto: Array,
    clienteId: Number
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
const selectedOperador = ref('')
const menusAsignados = ref([])
const loading = ref(false)
const saving = ref(false)
const searchTerm = ref('')
const showDropdown = ref(false)

// ==================== COMPUTED ====================
const operadoresFiltrados = computed(() => {
    if (!searchTerm.value) return props.operadores
    const term = searchTerm.value.toLowerCase()
    return props.operadores.filter(op => 
        op.nombre?.toLowerCase().includes(term) || 
        op.ci?.toString().includes(term)
    )
})

const hayOperadorSeleccionado = computed(() => {
    return selectedOperador.value && selectedOperador.value !== ''
})

// ==================== FUNCIONES ====================
const cerrarDropdown = () => {
    // 🔥 AUMENTAR EL TIMEOUT PARA QUE EL CLICK SE REGISTRE ANTES DE CERRAR
    setTimeout(() => {
        showDropdown.value = false
    }, 300)
}

const seleccionarOperador = (operador) => {
    selectedOperador.value = operador.id
    searchTerm.value = `${operador.ci} - ${operador.nombre}`
    showDropdown.value = false
    cargarMenusAsignados()
}

const limpiarSeleccion = () => {
    selectedOperador.value = ''
    searchTerm.value = ''
    menusAsignados.value = []
    showDropdown.value = false
}

const cargarMenusAsignados = async () => {
    if (!selectedOperador.value) return
    
    loading.value = true
    try {
        const response = await axios.get(`/gestion/menu/asignar/${selectedOperador.value}`)
        menusAsignados.value = response.data
    } catch (error) {
        console.error('Error al cargar menús asignados:', error)
        toast?.error('Error', 'No se pudieron cargar los menús asignados')
    } finally {
        loading.value = false
    }
}

const guardarAsignacion = () => {
    if (!selectedOperador.value) {
        toast?.warning('Advertencia', 'Selecciona un operador primero')
        return
    }
    
    saving.value = true
    
    router.post('/gestion/menu/asignar', {
        operador_id: selectedOperador.value,
        cliente_id: props.clienteId,
        menus: menusAsignados.value
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            saving.value = false
        },
        onError: (errors) => {
            console.error('Error al guardar:', errors)
            let errorMsg = 'Error al guardar la asignación'
            if (errors && typeof errors === 'object') {
                const firstError = Object.values(errors)[0]
                if (Array.isArray(firstError)) {
                    errorMsg = firstError[0] || errorMsg
                } else if (typeof firstError === 'string') {
                    errorMsg = firstError
                }
            }
            toast?.error('Error', errorMsg)
            saving.value = false
        },
        onFinish: () => {
            saving.value = false
        }
    })
}

const actualizarMenusAsignados = (nuevosAsignados) => {
    menusAsignados.value = nuevosAsignados
}

// 🔥 CERRAR DROPDOWN AL HACER CLICK FUERA
const handleClickOutside = (event) => {
    const container = document.querySelector('.dropdown-container')
    if (container && !container.contains(event.target)) {
        showDropdown.value = false
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
    
    const flashSuccess = page.props.flash?.success
    const flashError = page.props.flash?.error
    
    if (flashSuccess) {
        toast?.success('Éxito', flashSuccess)
        page.props.flash.success = null
    }
    if (flashError) {
        toast?.error('Error', flashError)
        page.props.flash.error = null
    }
    
    if (selectedOperador.value) {
        cargarMenusAsignados()
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})

watch([() => props.menuCompleto, menusAsignados], () => {
    console.log('🔄 Datos actualizados')
}, { deep: true })
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-tasks text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Asignar Menús por Operador</h1>
                        <p class="text-xs text-gray-500">Selecciona un operador y asigna los menús correspondientes</p>
                    </div>
                </div>

                <!-- ==================== SELECTOR DE OPERADOR ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4 dropdown-container">
                    <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                        👤 Selecciona un operador
                    </label>
                    <div class="relative">
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input 
                                    type="text"
                                    v-model="searchTerm"
                                    @focus="showDropdown = true"
                                    @blur="cerrarDropdown"
                                    placeholder="Buscar por CI o nombre..."
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-7 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                >
                                <button 
                                    v-if="searchTerm"
                                    @click="limpiarSeleccion"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- 🔥 DROPDOWN - USANDO @mousedown EN VEZ DE @click -->
                        <div 
                            v-if="showDropdown && operadoresFiltrados.length > 0"
                            class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                        >
                            <div
                                v-for="op in operadoresFiltrados"
                                :key="op.id"
                                @mousedown.prevent="seleccionarOperador(op)"
                                class="px-2.5 py-1.5 hover:bg-primary-50 cursor-pointer border-b last:border-b-0 text-sm flex items-center gap-2"
                                :class="{ 'bg-primary-50': selectedOperador == op.id }"
                            >
                                <span class="font-mono text-[10px] text-gray-600">{{ op.ci }}</span>
                                <span class="text-gray-300 text-[10px]">-</span>
                                <span class="text-sm text-gray-800 truncate">{{ op.nombre }}</span>
                            </div>
                        </div>
                        
                        <div 
                            v-if="showDropdown && searchTerm && operadoresFiltrados.length === 0"
                            class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-500 text-[10px]"
                        >
                            No se encontraron operadores
                        </div>
                    </div>
                    
                    <!-- Operador seleccionado -->
                    <div v-if="hayOperadorSeleccionado" class="mt-1.5 text-[10px] text-primary-600 flex items-center gap-1.5">
                        <i class="fas fa-check-circle text-[9px]"></i>
                        Operador seleccionado: <span class="font-semibold">{{ searchTerm }}</span>
                    </div>
                    
                    <div v-if="loading" class="mt-1.5 text-[10px] text-gray-500 flex items-center gap-1.5">
                        <i class="fas fa-spinner fa-spin text-[9px]"></i> Cargando menús asignados...
                    </div>
                </div>

                <!-- ==================== ÁRBOL DE MENÚS ==================== -->
                <div v-if="hayOperadorSeleccionado" class="bg-white rounded-xl shadow-sm p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                            <i class="fas fa-sitemap text-primary-600 text-[10px]"></i> Menús disponibles
                        </h2>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-3 max-h-[500px] overflow-y-auto bg-gray-50">
                        <div v-if="!menuCompleto || menuCompleto.length === 0" class="text-center text-gray-400 py-6">
                            <i class="fas fa-folder-open text-2xl mb-1 block"></i>
                            <p class="text-sm">No hay menús disponibles</p>
                        </div>
                        
                        <MenuTree
                            v-else
                            :items="menuCompleto"
                            :asignados="menusAsignados"
                            @update:asignados="actualizarMenusAsignados"
                        />
                    </div>

                    <div class="mt-3 flex justify-end">
                        <button 
                            @click="guardarAsignacion"
                            :disabled="saving"
                            class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium shadow-sm transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <i v-if="saving" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            {{ saving ? 'Guardando...' : 'Guardar asignación' }}
                        </button>
                    </div>
                </div>

                <!-- ==================== MENSAJE SIN OPERADOR ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">
                    <i class="fas fa-user-circle text-3xl mb-2 block"></i>
                    <p class="text-sm">Selecciona un operador para asignar sus menús</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

/* Estilos para el componente MenuTree */
:deep(.menu-tree) {
    list-style: none;
    padding-left: 0;
    margin: 0;
}

:deep(.menu-tree li) {
    margin: 2px 0;
}

:deep(.folder-toggle) {
    cursor: pointer;
    user-select: none;
    font-size: 14px;
    margin-right: 4px;
    display: inline-block;
    width: 20px;
}

:deep(.nested) {
    display: none;
    margin-left: 16px;
    border-left: 2px solid #e5e7eb;
    padding-left: 10px;
}

:deep(.nested.active) {
    display: block;
}

:deep(label) {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}

:deep(input[type="checkbox"]) {
    width: 14px;
    height: 14px;
    cursor: pointer;
    accent-color: var(--color-primary, #61131a);
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