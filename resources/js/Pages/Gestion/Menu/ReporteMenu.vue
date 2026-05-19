<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, defineComponent, h } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    datos: {
        type: Object,
        required: true,
        default: () => ({ agrupado: {}, nombres: {} })
    }
})

const operadoresExpandidos = ref({})
const arboles = ref({})
const cargando = ref({})

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

// Componente recursivo para el árbol - DEFINIDO CORRECTAMENTE
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
                class: 'menu-item flex items-center gap-2 py-0.5 cursor-pointer hover:bg-gray-50 rounded px-1',
                onClick: toggle
            }, [
                h('span', { class: 'w-6 text-gray-500 text-sm' }, [
                    hasChildren 
                        ? h('i', { class: expanded ? 'fas fa-chevron-down' : 'fas fa-chevron-right' })
                        : h('i', { class: 'fas fa-circle text-[4px] text-gray-300 ml-2' })
                ]),
                h('span', { class: 'text-base' }, node.icono || (hasChildren ? '📂' : '📄')),
                h('span', { class: 'text-sm text-gray-700' }, node.desc || node.title || 'Sin título')
            ]),
            hasChildren && expanded ? h('ul', { class: 'ml-6 border-l border-gray-200 pl-2' }, 
                node.children.map(child => h(MenuTreeNode, { node: child, key: child.id }))
            ) : null
        ])
    }
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-tree text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Menús Asignados por Operador</h1>
                            <p class="text-xs text-gray-500">Visualización de menús asignados a cada operador</p>
                        </div>
                    </div>
                </div>

                <!-- Lista de operadores -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div v-if="Object.keys(datos.nombres).length === 0" class="text-center py-12">
                        <i class="fas fa-users text-5xl text-gray-300 mb-3 block"></i>
                        <p class="text-gray-500">No hay operadores con menús asignados</p>
                    </div>

                    <div v-else class="divide-y divide-gray-200">
                        <div 
                            v-for="(nombre, operadorId) in datos.nombres" 
                            :key="operadorId"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <!-- Cabecera del operador -->
                            <div 
                                @click="toggleOperador(operadorId)"
                                class="flex items-center justify-between px-5 py-3 cursor-pointer"
                            >
                                <div class="flex items-center gap-2">
                                    <i 
                                        class="fas text-gray-500 text-sm transition-transform duration-200"
                                        :class="operadoresExpandidos[operadorId] ? 'fa-chevron-down' : 'fa-chevron-right'"
                                    ></i>
                                    <i class="fas fa-user-circle text-guindo-500 text-lg"></i>
                                    <span class="font-semibold text-gray-800">{{ nombre.toUpperCase() }}</span>
                                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                                        ID: {{ operadorId }}
                                    </span>
                                </div>
                                <i class="fas fa-ellipsis-h text-gray-300 text-xs"></i>
                            </div>

                            <!-- Árbol de menús -->
                            <div 
                                v-show="operadoresExpandidos[operadorId]"
                                class="border-t border-gray-100 bg-gray-50/50"
                            >
                                <div class="p-4">
                                    <!-- Loading -->
                                    <div v-if="cargando[operadorId]" class="flex items-center justify-center py-8">
                                        <i class="fas fa-spinner fa-spin text-guindo-500 text-lg mr-2"></i>
                                        <span class="text-sm text-gray-500">Cargando menús...</span>
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
                                    <div v-else class="text-center py-6">
                                        <i class="fas fa-folder-open text-4xl text-gray-300 mb-2 block"></i>
                                        <p class="text-sm text-gray-400">No tiene menús asignados</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-4 text-center">
                    <p class="text-xs text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i>
                        Los menús están organizados jerárquicamente. Haz clic en el nombre del operador para ver sus menús asignados.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.menu-node {
    list-style: none;
}

.menu-item {
    transition: all 0.15s ease;
}

.menu-item:hover {
    background-color: #f9fafb;
}
</style>