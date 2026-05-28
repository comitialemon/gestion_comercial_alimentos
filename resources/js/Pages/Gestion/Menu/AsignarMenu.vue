<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

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
const expandedFolders = ref({})
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

// Operador seleccionado (para mostrar en el input)
const operadorSeleccionadoTexto = computed(() => {
    if (!selectedOperador.value) return ''
    const op = props.operadores?.find(o => o.id == selectedOperador.value)
    return op ? `${op.ci} - ${op.nombre}` : ''
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
        // Resetear expansión al cargar nuevo operador
        expandedFolders.value = {}
    } catch (error) {
        console.error('Error al cargar menús asignados:', error)
    } finally {
        loading.value = false
    }
}

// Guardar asignación
const guardarAsignacion = () => {
    if (!selectedOperador.value) {
        alert('Selecciona un operador primero')
        return
    }
    
    saving.value = true
    router.post('/gestion/menu/asignar', {
        operador_id: selectedOperador.value,
        menus: menusAsignados.value
    }, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false
        }
    })
}

// Alternar expansión de carpeta
const toggleFolder = (itemId) => {
    expandedFolders.value[itemId] = !expandedFolders.value[itemId]
}

// Verificar si un item está expandido
const isExpanded = (itemId) => {
    return expandedFolders.value[itemId] === true
}

// Verificar si un menú está asignado
const isChecked = (id) => {
    return menusAsignados.value.includes(id)
}

// Verificar si todos los hijos están asignados
const allChildrenChecked = (item) => {
    if (!item.children || item.children.length === 0) return false
    return item.children.every(child => isChecked(child.id))
}

// Verificar si algunos hijos están asignados (para estado indeterminado)
const someChildrenChecked = (item) => {
    if (!item.children || item.children.length === 0) return false
    const checkedCount = item.children.filter(child => isChecked(child.id)).length
    return checkedCount > 0 && checkedCount < item.children.length
}

// Toggle check recursivo (para hijos)
const toggleCheckRecursive = (item, checked) => {
    // Marcar/desmarcar el item actual
    if (checked) {
        if (!menusAsignados.value.includes(item.id)) {
            menusAsignados.value.push(item.id)
        }
    } else {
        menusAsignados.value = menusAsignados.value.filter(id => id !== item.id)
    }
    
    // Marcar/desmarcar todos los hijos
    if (item.children && item.children.length) {
        item.children.forEach(child => {
            toggleCheckRecursive(child, checked)
        })
    }
}

// Manejar cambio de checkbox
const onCheckboxChange = (item, event) => {
    const isCheckedVal = event.target.checked
    toggleCheckRecursive(item, isCheckedVal)
    
    // Actualizar padres después de marcar/desmarcar
    const updateParentState = (items, childId) => {
        for (const node of items) {
            if (node.children && node.children.length) {
                if (node.children.some(child => child.id === childId)) {
                    // Verificar estado del padre
                    if (allChildrenChecked(node)) {
                        if (!menusAsignados.value.includes(node.id)) {
                            menusAsignados.value.push(node.id)
                        }
                    } else if (!someChildrenChecked(node)) {
                        if (menusAsignados.value.includes(node.id)) {
                            menusAsignados.value = menusAsignados.value.filter(id => id !== node.id)
                        }
                    }
                    return true
                }
                if (updateParentState(node.children, childId)) return true
            }
        }
        return false
    }
    updateParentState(props.menuCompleto, item.id)
}

// Expandir todo
const expandirTodo = () => {
    const expandRecursive = (items) => {
        items.forEach(item => {
            if (item.children && item.children.length) {
                expandedFolders.value[item.id] = true
                expandRecursive(item.children)
            }
        })
    }
    if (props.menuCompleto && props.menuCompleto.length) {
        expandRecursive(props.menuCompleto)
    }
}

// Contraer todo
const contraerTodo = () => {
    expandedFolders.value = {}
}

// Renderizar árbol recursivamente con estado calculado
const menuTree = ref([])

// Actualizar árbol cuando cambian los menús asignados
watch([() => props.menuCompleto, menusAsignados], () => {
    const renderTree = (items) => {
        return items.map(item => {
            const hasChildren = item.children && item.children.length > 0
            const checked = isChecked(item.id)
            const indeterminate = !checked && someChildrenChecked(item)
            
            return {
                ...item,
                hasChildren,
                checked,
                indeterminate,
                children: hasChildren ? renderTree(item.children) : []
            }
        })
    }
    
    if (props.menuCompleto && props.menuCompleto.length) {
        menuTree.value = renderTree(props.menuCompleto)
    }
}, { deep: true, immediate: true })

// Cargar menús si ya hay operador seleccionado (por ejemplo, después de recargar)
onMounted(() => {
    if (selectedOperador.value) {
        cargarMenusAsignados()
    }
})
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
                        <div class="space-x-2">
                            <button 
                                @click="expandirTodo"
                                class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                            >
                                <i class="fas fa-expand-alt mr-1"></i> Expandir todo
                            </button>
                            <button 
                                @click="contraerTodo"
                                class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                            >
                                <i class="fas fa-compress-alt mr-1"></i> Contraer todo
                            </button>
                        </div>
                    </div>

                    <div class="border rounded-xl p-4 max-h-[500px] overflow-y-auto bg-gray-50">
                        <div v-if="menuTree.length === 0" class="text-center text-gray-400 py-8">
                            <i class="fas fa-folder-open text-3xl mb-2 block"></i>
                            <p>No hay menús disponibles</p>
                        </div>
                        
                        <div v-else class="space-y-1">
                            <template v-for="item in menuTree" :key="item.id">
                                <div class="menu-item-wrapper">
                                    <!-- Item actual -->
                                    <div class="flex items-center py-1 hover:bg-white rounded-lg px-2 transition-colors group">
                                        <!-- Botón de expandir/contraer para carpetas -->
                                        <span 
                                            v-if="item.hasChildren"
                                            @click.stop="toggleFolder(item.id)"
                                            class="folder-toggle w-6 h-6 flex items-center justify-center cursor-pointer text-gray-400 hover:text-primary-600 transition"
                                        >
                                            <i :class="isExpanded(item.id) ? 'fas fa-chevron-down text-xs' : 'fas fa-chevron-right text-xs'"></i>
                                        </span>
                                        <span v-else class="w-6"></span>
                                        
                                        <!-- Checkbox -->
                                        <label class="flex items-center gap-2 cursor-pointer flex-1">
                                            <input 
                                                type="checkbox"
                                                :checked="item.checked"
                                                :indeterminate.prop="item.indeterminate"
                                                @change="onCheckboxChange(item, $event)"
                                                class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                            >
                                            <i :class="item.hasChildren ? 'fas fa-folder text-secondary-500' : 'fas fa-file-alt text-blue-400'"></i>
                                            <span class="text-sm text-gray-700">{{ item.title }}</span>
                                        </label>
                                    </div>
                                    
                                    <!-- Hijos (recursivo) -->
                                    <div v-if="item.hasChildren && isExpanded(item.id)" class="ml-6 border-l-2 border-gray-200 pl-3 mt-0.5 space-y-0.5">
                                        <template v-for="child in item.children" :key="child.id">
                                            <div class="flex items-center py-1 hover:bg-white rounded-lg px-2 transition-colors group">
                                                <span 
                                                    v-if="child.hasChildren"
                                                    @click.stop="toggleFolder(child.id)"
                                                    class="folder-toggle w-6 h-6 flex items-center justify-center cursor-pointer text-gray-400 hover:text-primary-600 transition"
                                                >
                                                    <i :class="isExpanded(child.id) ? 'fas fa-chevron-down text-xs' : 'fas fa-chevron-right text-xs'"></i>
                                                </span>
                                                <span v-else class="w-6"></span>
                                                
                                                <label class="flex items-center gap-2 cursor-pointer flex-1">
                                                    <input 
                                                        type="checkbox"
                                                        :checked="child.checked"
                                                        :indeterminate.prop="child.indeterminate"
                                                        @change="onCheckboxChange(child, $event)"
                                                        class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                    >
                                                    <i :class="child.hasChildren ? 'fas fa-folder text-secondary-500' : 'fas fa-file-alt text-blue-400'"></i>
                                                    <span class="text-sm text-gray-700">{{ child.title }}</span>
                                                </label>
                                            </div>
                                            
                                            <!-- Nivel 3 y más -->
                                            <div v-if="child.hasChildren && isExpanded(child.id)" class="ml-6 border-l-2 border-gray-200 pl-3 mt-0.5 space-y-0.5">
                                                <div v-for="grandchild in child.children" :key="grandchild.id" class="flex items-center py-1 hover:bg-white rounded-lg px-2 transition-colors">
                                                    <span class="w-6"></span>
                                                    <label class="flex items-center gap-2 cursor-pointer flex-1">
                                                        <input 
                                                            type="checkbox"
                                                            :checked="grandchild.checked"
                                                            @change="onCheckboxChange(grandchild, $event)"
                                                            class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                        >
                                                        <i class="fas fa-file-alt text-blue-400"></i>
                                                        <span class="text-sm text-gray-700">{{ grandchild.title }}</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
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
.menu-item-wrapper {
    transition: all 0.15s ease;
}

input[type="checkbox"]:indeterminate {
    background-color: #61131a;
    border-color: #61131a;
    position: relative;
}

input[type="checkbox"]:indeterminate::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 2px;
    background-color: white;
    border-radius: 2px;
}

.folder-toggle {
    transition: all 0.2s ease;
}

.group:hover .folder-toggle {
    color: #61131a;
}
</style>