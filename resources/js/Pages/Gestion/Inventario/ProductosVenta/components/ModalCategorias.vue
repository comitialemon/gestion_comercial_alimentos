<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
    modelValue: Boolean,
    categorias: Array,
    categoriaSeleccionada: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['update:modelValue', 'select'])

const expandedFolders = ref({})
const selectedCategoria = ref(props.categoriaSeleccionada)

const expandirTodas = (items) => {
    if (!items || items.length === 0) return
    items.forEach(item => {
        if (item.children && item.children.length > 0) {
            expandedFolders.value[item.id_categoria] = true
            expandirTodas(item.children)
        }
    })
}

const contraerTodas = (items) => {
    if (!items || items.length === 0) return
    items.forEach(item => {
        if (item.children && item.children.length > 0) {
            expandedFolders.value[item.id_categoria] = false
            contraerTodas(item.children)
        }
    })
}

const arbolCategorias = computed(() => {
    if (!props.categorias || props.categorias.length === 0) return []
    
    const buildTree = (parentId = null) => {
        const hijos = props.categorias.filter(c => c.id_padre === parentId)
        return hijos.sort((a, b) => a.orden - b.orden).map(hijo => ({
            ...hijo,
            children: buildTree(hijo.id_categoria)
        }))
    }
    
    const tree = buildTree()
    expandirTodas(tree)
    return tree
})

watch(() => props.modelValue, (newVal) => {
    if (newVal && arbolCategorias.value.length > 0) {
        expandirTodas(arbolCategorias.value)
    }
    if (newVal && props.categoriaSeleccionada) {
        selectedCategoria.value = props.categoriaSeleccionada
    }
})

const toggleFolder = (itemId, hasChildren) => {
    if (!hasChildren) return
    expandedFolders.value[itemId] = !expandedFolders.value[itemId]
}

const isExpanded = (itemId) => {
    return expandedFolders.value[itemId] === true
}

const renderTree = (items, level = 0) => {
    if (!items || items.length === 0) return []
    
    let result = []
    for (const item of items) {
        result.push({
            ...item,
            level,
            hasChildren: item.children && item.children.length > 0,
            isExpanded: isExpanded(item.id_categoria)
        })
        if (item.children && item.children.length > 0 && isExpanded(item.id_categoria)) {
            result = [...result, ...renderTree(item.children, level + 1)]
        }
    }
    return result
}

const categoriasPlanas = computed(() => {
    return renderTree(arbolCategorias.value)
})

const seleccionarCategoria = (categoria) => {
    selectedCategoria.value = categoria
}

const confirmarSeleccion = () => {
    if (selectedCategoria.value) {
        emit('select', selectedCategoria.value)
        closeModal()
    }
}

const limpiarSeleccion = () => {
    selectedCategoria.value = null
}

const closeModal = () => {
    emit('update:modelValue', false)
}

const expandirTodo = () => {
    expandirTodas(arbolCategorias.value)
}

const contraerTodo = () => {
    contraerTodas(arbolCategorias.value)
}

const getPrefix = (level) => {
    if (level === 0) return ''
    return '—'.repeat(level) + ' '
}
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
        <div class="flex items-center justify-center min-h-screen p-3 sm:p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="closeModal"></div>
            
            <!-- Modal responsive -->
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg mx-auto transform transition-all duration-300">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 sm:px-5 py-2.5 sm:py-3 border-b bg-primary-600 rounded-t-xl">
                    <h3 class="text-white font-semibold text-[10px] sm:text-sm flex items-center gap-2">
                        <i class="fas fa-tree"></i> Seleccionar Categoría
                    </h3>
                    <button @click="closeModal" class="text-white/80 hover:text-white transition text-[10px] sm:text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-3 sm:p-4">
                    <!-- Botones de expandir/contraer -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3 pb-2 border-b">
                        <div class="text-[8px] sm:text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Seleccione la categoría para este producto
                        </div>
                        <div class="flex gap-1 sm:gap-2 w-full sm:w-auto">
                            <button 
                                @click="expandirTodo"
                                class="flex-1 sm:flex-none px-2 py-0.5 text-[8px] sm:text-[10px] bg-gray-100 hover:bg-gray-200 rounded transition"
                            >
                                <i class="fas fa-expand-alt mr-1"></i> Expandir
                            </button>
                            <button 
                                @click="contraerTodo"
                                class="flex-1 sm:flex-none px-2 py-0.5 text-[8px] sm:text-[10px] bg-gray-100 hover:bg-gray-200 rounded transition"
                            >
                                <i class="fas fa-compress-alt mr-1"></i> Contraer
                            </button>
                        </div>
                    </div>

                    <!-- Lista de categorías -->
                    <div class="max-h-60 sm:max-h-96 overflow-y-auto border rounded-lg p-1 sm:p-2 bg-gray-50">
                        <div v-if="categoriasPlanas.length === 0" class="text-center text-gray-400 py-6 sm:py-8">
                            <i class="fas fa-folder-open text-2xl sm:text-3xl mb-2 block"></i>
                            <p class="text-[10px] sm:text-xs">No hay categorías disponibles</p>
                        </div>
                        
                        <div v-else>
                            <div 
                                v-for="cat in categoriasPlanas"
                                :key="cat.id_categoria"
                                class="flex items-center py-1 hover:bg-gray-100 rounded-lg px-1 sm:px-2 transition-colors cursor-pointer group"
                                :class="{ 'bg-primary-50 border-l-4 border-primary-500': selectedCategoria?.id_categoria === cat.id_categoria }"
                                @click="seleccionarCategoria(cat)"
                            >
                                <!-- Espaciador según nivel -->
                                <div class="w-3 sm:w-4" :style="{ marginLeft: (cat.level * 12) + 'px' }"></div>
                                
                                <!-- Botón expandir/contraer -->
                                <span 
                                    v-if="cat.hasChildren"
                                    @click.stop="toggleFolder(cat.id_categoria, cat.hasChildren)"
                                    class="w-4 h-4 sm:w-5 sm:h-5 flex items-center justify-center cursor-pointer text-gray-400 hover:text-primary-600 transition rounded"
                                >
                                    <i :class="cat.isExpanded ? 'fas fa-chevron-down text-[7px] sm:text-[9px]' : 'fas fa-chevron-right text-[7px] sm:text-[9px]'"></i>
                                </span>
                                <span v-else class="w-4 sm:w-5"></span>
                                
                                <!-- Icono -->
                                <i :class="cat.hasChildren ? 'fas fa-folder-open text-secondary-500' : 'fas fa-tag text-primary-400'" class="text-[10px] sm:text-xs"></i>
                                
                                <!-- Nombre -->
                                <span class="ml-1 sm:ml-2 text-[10px] sm:text-sm text-gray-700 flex-1">
                                    <span class="text-gray-400 text-[8px] sm:text-[10px] mr-1">{{ getPrefix(cat.level) }}</span>
                                    {{ cat.nombre }}
                                </span>
                                
                                <!-- Indicador de selección -->
                                <i v-if="selectedCategoria?.id_categoria === cat.id_categoria" class="fas fa-check-circle text-primary-600 text-[8px] sm:text-xs mr-1"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Categoría seleccionada -->
                    <div class="mt-2 sm:mt-3 p-2 bg-gray-100 rounded-lg">
                        <div class="text-[8px] sm:text-xs text-gray-500 mb-0.5 sm:mb-1">Categoría seleccionada:</div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 min-w-0">
                                <i class="fas fa-tag text-primary-500 text-[10px] sm:text-sm flex-shrink-0"></i>
                                <span class="text-[10px] sm:text-sm font-semibold text-primary-700 truncate">
                                    {{ selectedCategoria?.nombre || 'Ninguna' }}
                                </span>
                            </div>
                            <button 
                                v-if="selectedCategoria"
                                @click="limpiarSeleccion"
                                class="text-[8px] sm:text-xs text-red-500 hover:text-red-700 flex-shrink-0"
                            >
                                <i class="fas fa-times-circle"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex flex-col sm:flex-row justify-end gap-2 px-4 sm:px-5 py-3 border-t bg-gray-50 rounded-b-xl">
                    <button @click="closeModal" class="w-full sm:w-auto px-4 py-1.5 border border-gray-300 rounded-md text-[10px] sm:text-xs text-gray-700 hover:bg-gray-100 transition order-2 sm:order-1">
                        Cancelar
                    </button>
                    <button 
                        @click="confirmarSeleccion" 
                        :disabled="!selectedCategoria"
                        class="w-full sm:w-auto px-4 py-1.5 bg-primary-600 text-white rounded-md text-[10px] sm:text-xs hover:bg-primary-700 transition disabled:opacity-50 flex items-center justify-center gap-1 order-1 sm:order-2"
                    >
                        <i class="fas fa-check-circle text-[8px] sm:text-[10px]"></i>
                        Seleccionar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.folder-toggle {
    transition: all 0.2s ease;
}

.group:hover .folder-toggle {
    color: #61131a;
}

.transition-all {
    transition: all 0.15s ease;
}
</style>