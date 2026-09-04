<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, defineComponent, h, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    datos: {
        type: Object,
        required: true,
        default: () => ({ agrupado: {}, nombres: {} })
    }
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
const operadoresExpandidos = ref({})
const arboles = ref({})
const cargando = ref({})

// ==================== FUNCIONES ====================
const toggleOperador = async (operadorId) => {
    if (operadoresExpandidos.value[operadorId]) {
        operadoresExpandidos.value[operadorId] = false
    } else {
        operadoresExpandidos.value[operadorId] = true
        
        if (!arboles.value[operadorId]) {
            await cargarArbol(operadorId)
        }
    }
}

const cargarArbol = async (operadorId) => {
    cargando.value[operadorId] = true
    try {
        const response = await axios.get(`/gestion/menu/reporte/arbol/${operadorId}`)
        if (response.data.success) {
            arboles.value[operadorId] = response.data.arbol
        }
    } catch (error) {
        console.error('Error cargando árbol:', error)
    } finally {
        cargando.value[operadorId] = false
    }
}

// ==================== COMPONENTE ÁRBOL RECURSIVO ====================
const MenuTreeNode = defineComponent({
    name: 'MenuTreeNode',
    props: {
        node: {
            type: Object,
            required: true
        },
        level: {
            type: Number,
            default: 0
        }
    },
    setup(props) {
        const hasChildren = computed(() => props.node.children && props.node.children.length > 0)
        const expanded = ref(true)
        
        const toggle = () => {
            expanded.value = !expanded.value
        }
        
        return { hasChildren, expanded, toggle }
    },
    render() {
        const hasChildren = this.hasChildren
        const expanded = this.expanded
        const toggle = this.toggle
        const node = this.node
        
        if (!node) return null
        
        return h('li', { class: 'menu-node' }, [
            h('div', {
                class: 'menu-item flex items-center gap-1.5 py-0.5 cursor-pointer hover:bg-gray-50 rounded px-1 transition',
                onClick: toggle
            }, [
                h('span', { class: 'w-5 text-gray-500 text-xs flex-shrink-0' }, [
                    hasChildren 
                        ? h('i', { class: expanded ? 'fas fa-chevron-down' : 'fas fa-chevron-right' })
                        : h('i', { class: 'fas fa-circle text-[4px] text-gray-300 ml-2' })
                ]),
                h('span', { class: 'text-sm flex-shrink-0' }, node.icono || (hasChildren ? '📂' : '📄')),
                h('span', { class: 'text-sm text-gray-700 truncate' }, node.desc || node.title || 'Sin título')
            ]),
            hasChildren && expanded ? h('ul', { class: 'ml-5 border-l border-gray-200 pl-2' }, 
                node.children.map(child => h(MenuTreeNode, { node: child, key: child.id }))
            ) : null
        ])
    }
})

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-tree text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Menús Asignados por Operador</h1>
                        <p class="text-xs text-gray-500">Visualización de menús asignados a cada operador</p>
                    </div>
                </div>

                <!-- ==================== LISTA DE OPERADORES ==================== -->
                <div v-if="Object.keys(datos.nombres).length === 0" class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-users text-3xl mb-2 block"></i>
                    <p class="text-sm">No hay operadores con menús asignados</p>
                </div>

                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="divide-y divide-gray-200">
                        <div 
                            v-for="(nombre, operadorId) in datos.nombres" 
                            :key="operadorId"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <!-- Cabecera del operador -->
                            <div 
                                @click="toggleOperador(operadorId)"
                                class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 cursor-pointer"
                            >
                                <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                    <i 
                                        class="fas text-gray-500 text-[10px] transition-transform duration-200 flex-shrink-0"
                                        :class="operadoresExpandidos[operadorId] ? 'fa-chevron-down' : 'fa-chevron-right'"
                                    ></i>
                                    <i class="fas fa-user-circle text-primary-500 text-sm flex-shrink-0"></i>
                                    <span class="font-semibold text-gray-800 text-sm truncate">{{ nombre.toUpperCase() }}</span>
                                </div>
                            </div>

                            <!-- Árbol de menús -->
                            <div 
                                v-show="operadoresExpandidos[operadorId]"
                                class="border-t border-gray-100 bg-gray-50/50"
                            >
                                <div class="p-3">
                                    <!-- Loading -->
                                    <div v-if="cargando[operadorId]" class="flex items-center justify-center py-6">
                                        <i class="fas fa-spinner fa-spin text-primary-500 text-sm mr-2"></i>
                                        <span class="text-xs text-gray-500">Cargando menús...</span>
                                    </div>
                                    
                                    <!-- Árbol -->
                                    <div v-else-if="arboles[operadorId] && arboles[operadorId].length > 0">
                                        <ul class="space-y-0.5">
                                            <MenuTreeNode 
                                                v-for="node in arboles[operadorId]" 
                                                :key="node.id" 
                                                :node="node" 
                                                :level="0"
                                            />
                                        </ul>
                                    </div>
                                    
                                    <!-- Sin menús -->
                                    <div v-else class="text-center py-4">
                                        <i class="fas fa-folder-open text-2xl text-gray-300 mb-1 block"></i>
                                        <p class="text-xs text-gray-400">No tiene menús asignados</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="mt-3 text-center text-[8px] text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Los menús están organizados jerárquicamente. Haz clic en el nombre del operador para ver sus menús asignados.
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

.menu-node {
    list-style: none;
}

.menu-item {
    transition: all 0.15s ease;
}

.menu-item:hover {
    background-color: #f9fafb;
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