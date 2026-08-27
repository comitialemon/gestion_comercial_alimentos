<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, watch, computed, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import MenuTree from './MenuTree.vue' // 🔥 IMPORTAR EL COMPONENTE RECURSIVO

defineOptions({ layout: AppLayout })

// 🔥 INYECTAR EL TOAST
const toast = inject('toast')
const page = usePage()

const props = defineProps({
    operadores: Array,
    menuCompleto: Array,
    clienteId: Number
})

// Estado
const selectedOperador = ref('')
const menusAsignados = ref([])
const loading = ref(false)
const saving = ref(false)
const searchTerm = ref('')
const showDropdown = ref(false)

// Operadores filtrados por búsqueda
const operadoresFiltrados = computed(() => {
    if (!searchTerm.value) return props.operadores
    const term = searchTerm.value.toLowerCase()
    return props.operadores.filter(op => 
        op.nombre?.toLowerCase().includes(term) || 
        op.ci?.toString().includes(term)
    )
})

// Cerrar dropdown con delay
const cerrarDropdown = () => {
    setTimeout(() => {
        showDropdown.value = false
    }, 200)
}

// Seleccionar operador
const seleccionarOperador = (operador) => {
    selectedOperador.value = operador.id
    searchTerm.value = `${operador.ci} - ${operador.nombre}`
    showDropdown.value = false
    cargarMenusAsignados()
}

// Limpiar selección
const limpiarSeleccion = () => {
    selectedOperador.value = ''
    searchTerm.value = ''
    menusAsignados.value = []
    showDropdown.value = false
}

// Cargar menús asignados al seleccionar un operador
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

// 🔥 GUARDAR ASIGNACIÓN - CON TOAST
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

// 🔥 VERIFICAR MENSAJES FLASH AL CARGAR
onMounted(() => {
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

// ==================== ACTUALIZAR MENUS ASIGNADOS ====================
const actualizarMenusAsignados = (nuevosAsignados) => {
    menusAsignados.value = nuevosAsignados
}

// 🔥 WATCH: Solo para depuración
watch([() => props.menuCompleto, menusAsignados], () => {
    console.log('🔄 Datos actualizados')
    console.log('menuCompleto:', props.menuCompleto)
    console.log('menusAsignados:', menusAsignados.value)
}, { deep: true, immediate: true })
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-tasks text-primary-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Asignar Menús por Operador</h1>
                            <p class="text-xs text-gray-500">Selecciona un operador y asigna los menús correspondientes</p>
                        </div>
                    </div>
                </div>

                <!-- Selector de operador con búsqueda -->
                <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
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
                                    class="w-full rounded-lg border-gray-300 focus:ring-primary-500 focus:border-primary-500 py-2 px-3 pr-8"
                                >
                                <button 
                                    v-if="searchTerm"
                                    @click="limpiarSeleccion"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Dropdown de resultados -->
                        <div 
                            v-if="showDropdown && operadoresFiltrados.length > 0"
                            class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                        >
                            <div
                                v-for="op in operadoresFiltrados"
                                :key="op.id"
                                @click="seleccionarOperador(op)"
                                class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 transition-colors"
                                :class="{ 'bg-primary-50': selectedOperador == op.id }"
                            >
                                <span class="font-mono text-sm text-gray-600">{{ op.ci }}</span>
                                <span class="mx-2 text-gray-400">-</span>
                                <span class="text-sm text-gray-800">{{ op.nombre }}</span>
                            </div>
                        </div>
                        
                        <div 
                            v-if="showDropdown && searchTerm && operadoresFiltrados.length === 0"
                            class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm"
                        >
                            No se encontraron operadores
                        </div>
                    </div>
                    
                    <!-- Operador seleccionado actualmente -->
                    <div v-if="selectedOperador" class="mt-3 text-sm text-primary-600 flex items-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        Operador seleccionado: <span class="font-semibold">{{ searchTerm }}</span>
                    </div>
                    
                    <div v-if="loading" class="mt-3 text-sm text-gray-500 flex items-center gap-2">
                        <i class="fas fa-spinner fa-spin"></i> Cargando menús asignados...
                    </div>
                </div>

                <!-- Árbol de menús -->
                <div v-if="selectedOperador" class="bg-white rounded-xl shadow-sm p-5">
                    <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-sitemap text-primary-600"></i> Menús disponibles
                        </h2>
                    </div>

                    <div class="border rounded-xl p-4 max-h-[500px] overflow-y-auto bg-gray-50">
                        <div v-if="!menuCompleto || menuCompleto.length === 0" class="text-center text-gray-400 py-8">
                            <i class="fas fa-folder-open text-3xl mb-2 block"></i>
                            <p>No hay menús disponibles</p>
                        </div>
                        
                        <!-- 🔥 USAR EL COMPONENTE RECURSIVO -->
                        <MenuTree
                            v-else
                            :items="menuCompleto"
                            :asignados="menusAsignados"
                            @update:asignados="actualizarMenusAsignados"
                        />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button 
                            @click="guardarAsignacion"
                            :disabled="saving"
                            class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg shadow-md transition disabled:opacity-50 flex items-center gap-2"
                        >
                            <i v-if="saving" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ saving ? 'Guardando...' : 'Guardar asignación' }}
                        </button>
                    </div>
                </div>

                <!-- Mensaje cuando no hay operador seleccionado -->
                <div v-else class="bg-white rounded-xl shadow-sm p-8 text-center">
                    <i class="fas fa-user-circle text-5xl text-gray-300 mb-3 block"></i>
                    <p class="text-gray-500">Selecciona un operador para asignar sus menús</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
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
    font-size: 16px;
    margin-right: 4px;
    display: inline-block;
    width: 24px;
}

:deep(.nested) {
    display: none;
    margin-left: 20px;
    border-left: 2px solid #e5e7eb;
    padding-left: 12px;
}

:deep(.nested.active) {
    display: block;
}

:deep(label) {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

:deep(input[type="checkbox"]) {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #61131a;
}
</style>