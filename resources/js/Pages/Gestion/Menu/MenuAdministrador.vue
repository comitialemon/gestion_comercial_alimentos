<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ModalNuevoMenu from './ModalNuevoMenuAdministrativo.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    menus: { type: Array, required: true },
    columnasPermisos: { type: Array, required: true }
})

// ==================== ESTADO ====================
const menuEditando = ref(null)
const modalAgregar = ref(false)
const modalEliminar = ref(null)
const guardando = ref(false)
const busqueda = ref('')
const expandidos = ref({})
const editandoOrden = ref(null)
const nuevoOrden = ref(0)

const formEditar = ref({
    Description: '',
    Link: ''
})

// ==================== CONSTRUIR ÁRBOL ====================
const construirArbol = (items, parentId = 0) => {
    if (!items || !items.length) return []
    
    const resultado = []
    const hijos = items.filter(m => m.Parent === parentId)
    
    hijos.sort((a, b) => (a.Node_Order || 0) - (b.Node_Order || 0))
    
    for (const hijo of hijos) {
        const hijosDelHijo = construirArbol(items, hijo.Id)
        resultado.push({
            ...hijo,
            hijos: hijosDelHijo,
            nivel: 0
        })
    }
    return resultado
}

const asignarNiveles = (nodos, nivel = 0) => {
    if (!nodos || !nodos.length) return
    for (const nodo of nodos) {
        nodo.nivel = nivel
        if (nodo.hijos && nodo.hijos.length) {
            asignarNiveles(nodo.hijos, nivel + 1)
        }
    }
}

const flattenTree = (nodos, expandidosMap) => {
    if (!nodos || !nodos.length) return []
    
    const resultado = []
    
    const recorrer = (nodosList) => {
        for (const nodo of nodosList) {
            if (nodo && nodo.Id !== undefined) {
                resultado.push(nodo)
                if (nodo.hijos && nodo.hijos.length && expandidosMap[nodo.Id]) {
                    recorrer(nodo.hijos)
                }
            }
        }
    }
    
    recorrer(nodos)
    return resultado
}

const filtrarArbol = (nodos, termino) => {
    if (!nodos || !nodos.length) return []
    if (!termino) return nodos
    
    const terminoLower = termino.toLowerCase()
    
    return nodos.filter(nodo => {
        if (!nodo) return false
        
        const coincide = (nodo.Description || '').toLowerCase().includes(terminoLower) ||
                         (nodo.Link && nodo.Link.toLowerCase().includes(terminoLower))
        
        const hijosFiltrados = nodo.hijos ? filtrarArbol(nodo.hijos, termino) : []
        
        if (hijosFiltrados.length > 0 && !expandidos.value[nodo.Id]) {
            expandidos.value[nodo.Id] = true
        }
        
        if (coincide || hijosFiltrados.length > 0) {
            nodo.hijos = hijosFiltrados
            return true
        }
        return false
    })
}

// ==================== DATOS COMPUTADOS ====================
const arbolCompleto = computed(() => {
    if (!props.menus || !props.menus.length) return []
    const arbol = construirArbol(props.menus)
    asignarNiveles(arbol)
    return arbol
})

const arbolFiltrado = computed(() => {
    if (!arbolCompleto.value || !arbolCompleto.value.length) return []
    const copia = JSON.parse(JSON.stringify(arbolCompleto.value))
    return filtrarArbol(copia, busqueda.value)
})

const itemsParaRenderizar = computed(() => {
    if (!arbolFiltrado.value || !Array.isArray(arbolFiltrado.value) || arbolFiltrado.value.length === 0) {
        return []
    }
    const resultado = flattenTree(arbolFiltrado.value, expandidos.value)
    return resultado.filter(item => item && item.Id !== undefined)
})

const todosMenusPlano = computed(() => {
    const resultado = [{ id: 0, nombre: '📁 [RAÍZ]', nivel: 0 }]
    
    const recorrer = (nodos, prefix = '') => {
        if (!nodos || !nodos.length) return
        for (const nodo of nodos) {
            if (nodo && nodo.Id) {
                resultado.push({
                    id: nodo.Id,
                    nombre: prefix + (nodo.Description || ''),
                    nivel: nodo.nivel || 0
                })
                if (nodo.hijos?.length) {
                    recorrer(nodo.hijos, prefix + '— ')
                }
            }
        }
    }
    recorrer(arbolCompleto.value)
    return resultado
})

const contarHijosTotales = (nodo) => {
    if (!nodo) return 0
    let total = nodo.hijos?.length || 0
    for (const hijo of (nodo.hijos || [])) {
        total += contarHijosTotales(hijo)
    }
    return total
}

// ==================== ACCIONES ====================
const toggleExpandir = (id) => {
    if (!id) return
    expandidos.value = {
        ...expandidos.value,
        [id]: !expandidos.value[id]
    }
}

const expandirTodo = () => {
    const nuevosExpandidos = {}
    const recorrer = (nodos) => {
        if (!nodos || !nodos.length) return
        for (const nodo of nodos) {
            if (nodo && nodo.hijos && nodo.hijos.length) {
                nuevosExpandidos[nodo.Id] = true
                recorrer(nodo.hijos)
            }
        }
    }
    if (arbolFiltrado.value && arbolFiltrado.value.length) {
        recorrer(arbolFiltrado.value)
    }
    expandidos.value = nuevosExpandidos
}

const contraerTodo = () => {
    expandidos.value = {}
}

const activarEdicion = (menu) => {
    if (!menu) return
    menuEditando.value = menu.Id
    formEditar.value = {
        Description: menu.Description || '',
        Link: menu.Link || ''
    }
}

const guardarTexto = (id) => {
    if (!id) return
    router.put(`/gestion/menu-administrador/${id}`, formEditar.value, {
        preserveScroll: true,
        onSuccess: () => {
            menuEditando.value = null
        }
    })
}

const cancelarEdicion = () => {
    menuEditando.value = null
}

const togglePermiso = (menu, columna) => {
    if (!menu) return
    const valorActual = menu[columna]
    const nuevoValor = valorActual == 1 ? 0 : 1

    router.put(`/gestion/menu-administrador/${menu.Id}`, {
        columna: columna,
        valor: nuevoValor
    }, {
        preserveScroll: true
    })
}

const iniciarEditarOrden = (item) => {
    if (!item) return
    editandoOrden.value = item.Id
    nuevoOrden.value = item.Node_Order || 0
}

const guardarOrden = (id) => {
    if (!id) return
    router.put(`/gestion/menu-administrador/${id}`, {
        Node_Order: parseInt(nuevoOrden.value) || 0
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editandoOrden.value = null
        }
    })
}

const cancelarEditarOrden = () => {
    editandoOrden.value = null
}

const abrirAgregar = () => {
    modalAgregar.value = true
}

const guardarNuevo = (datos, callback) => {
    router.post('/gestion/menu-administrador', datos, {
        preserveScroll: true,
        onSuccess: () => {
            if (callback) callback()
        },
        onError: (errors) => {
            console.error('Error:', errors)
            alert('Error al crear el menú. Revisa la consola.')
            if (callback) callback()
        }
    })
}

const confirmarEliminar = (menu) => {
    if (!menu) return
    const tieneHijos = menu.hijos && menu.hijos.length > 0
    if (tieneHijos) {
        alert(`No se puede eliminar "${menu.Description}" porque tiene ${contarHijosTotales(menu)} submenús`)
        return
    }
    modalEliminar.value = menu
}

const eliminarMenu = () => {
    if (!modalEliminar.value) return
    
    router.delete(`/gestion/menu-administrador/${modalEliminar.value.Id}`, {
        preserveScroll: true,
        onSuccess: () => {
            modalEliminar.value = null
        }
    })
}

const limpiarBusqueda = () => {
    busqueda.value = ''
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 py-3 sm:py-4 px-2 sm:px-3 lg:px-5">
        <div class="max-w-full mx-auto">
            <!-- Header más delgado -->
            <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-3 sm:mb-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-bars text-primary-600 text-sm sm:text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-medium text-gray-800">Gestión del Menú</h1>
                            <p class="text-[10px] text-gray-400 hidden sm:block">Administra la estructura, enlaces y permisos del sistema</p>
                        </div>
                    </div>
                    <button 
                        @click="abrirAgregar"
                        class="px-3 sm:px-4 py-1.5 bg-primary-700 hover:bg-primary-800 text-white rounded-lg text-xs sm:text-sm font-medium shadow-sm transition-all flex items-center justify-center gap-1.5"
                    >
                        <i class="fas fa-plus text-[10px] sm:text-xs"></i>
                        <span class="hidden sm:inline">Nuevo Menú</span>
                        <span class="sm:hidden">Agregar</span>
                    </button>
                </div>
            </div>

            <!-- Buscador y acciones más delgados -->
            <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-3 sm:mb-4">
                <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
                    <div class="relative flex-1 max-w-md">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[11px]"></i>
                        <input 
                            type="text" 
                            v-model="busqueda" 
                            placeholder="Buscar por nombre o enlace..."
                            class="w-full pl-8 pr-7 py-1.5 border border-gray-200 rounded-lg text-xs focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                        />
                        <button 
                            v-if="busqueda" 
                            @click="limpiarBusqueda"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </div>
                    <div class="flex gap-1.5">
                        <button 
                            @click="expandirTodo"
                            class="px-2.5 py-1 text-[10px] sm:text-xs bg-gray-100 hover:bg-gray-200 rounded transition"
                        >
                            <i class="fas fa-expand-alt mr-0.5"></i> Expandir
                        </button>
                        <button 
                            @click="contraerTodo"
                            class="px-2.5 py-1 text-[10px] sm:text-xs bg-gray-100 hover:bg-gray-200 rounded transition"
                        >
                            <i class="fas fa-compress-alt mr-0.5"></i> Contraer
                        </button>
                    </div>
                </div>
            </div>

            <!-- TABLA DESKTOP - Filas más delgadas, sin ID -->
            <div class="hidden lg:block bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="relative overflow-x-auto overflow-y-auto" style="max-height: calc(100vh - 220px);">
                    <table class="min-w-max w-full divide-y divide-gray-200 text-xs">
                        <thead class="sticky top-0 z-20">
                            <tr class="bg-gray-50">
                                <th class="sticky left-0 top-0 bg-gray-50 z-30 px-3 py-2 text-left text-[10px] font-medium text-gray-600 uppercase min-w-[380px] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.05)]">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-folder-open text-primary-500 text-[11px]"></i>
                                            <span>Menú / Submenús</span>
                                        </div>
                                        <div class="text-center w-[100px]">
                                            Orden
                                        </div>
                                    </div>
                                </th>
                                <th class="bg-gray-50 px-3 py-2 text-left text-[10px] font-medium text-gray-600 uppercase min-w-[180px]">
                                    <i class="fas fa-link mr-0.5 text-[9px]"></i> Enlace
                                </th>
                                <th v-for="col in columnasPermisos" :key="col" 
                                    class="bg-gray-50 px-2 py-2 text-center text-[10px] font-medium text-gray-600 uppercase whitespace-nowrap">
                                    {{ col }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!itemsParaRenderizar || itemsParaRenderizar.length === 0">
                                <td :colspan="2 + (columnasPermisos?.length || 0)" class="px-4 py-8 text-center text-gray-400 text-xs">
                                    <i class="fas fa-search text-2xl mb-1 block"></i>
                                    No se encontraron resultados
                                </td>
                            </tr>
                            <tr v-for="(item, idx) in itemsParaRenderizar" :key="item ? item.Id : `item-${idx}`" class="hover:bg-gray-50 transition-colors group">
                                <td class="sticky left-0 bg-white group-hover:bg-gray-50 px-3 py-1.5 align-top shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] z-10">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-start flex-1">
                                            <div :style="{ width: `${(item?.nivel || 0) * 20}px`, minWidth: '0' }" class="flex-shrink-0"></div>
                                            <button 
                                                v-if="item?.hijos && item.hijos.length"
                                                @click="toggleExpandir(item.Id)"
                                                class="w-5 h-5 flex items-center justify-center rounded hover:bg-gray-200 flex-shrink-0"
                                            >
                                                <i :class="expandidos[item.Id] ? 'fas fa-chevron-down text-[9px]' : 'fas fa-chevron-right text-[9px]'"></i>
                                            </button>
                                            <div v-else class="w-5 flex-shrink-0"></div>
                                            
                                            <div v-if="menuEditando !== item.Id" class="flex-1">
                                                <div :class="{ 'font-semibold text-gray-900': (item?.nivel || 0) === 0, 'text-gray-700': (item?.nivel || 0) > 0 }">
                                                    {{ item?.Description || '' }}
                                                </div>
                                                <div class="text-[9px] text-gray-400 mt-0.5">
                                                    <span v-if="item?.hijos && item.hijos.length" class="text-primary-500">
                                                        <i class="fas fa-folder-open mr-0.5 text-[8px]"></i>{{ item.hijos.length }} sub
                                                    </span>
                                                </div>
                                            </div>
                                            <input 
                                                v-else 
                                                v-model="formEditar.Description" 
                                                type="text" 
                                                class="flex-1 border border-primary-300 rounded px-2 py-0.5 text-xs focus:ring-1 focus:ring-primary-500 bg-primary-50"
                                                @keyup.enter="guardarTexto(item.Id)"
                                                autofocus
                                            />
                                        </div>
                                        
                                        <!-- Columna ORDEN -->
                                        <div class="flex items-center gap-1 flex-shrink-0 w-[100px] justify-end">
                                            <div v-if="editandoOrden !== item.Id" class="flex items-center gap-0.5">
                                                <span class="text-xs font-mono text-primary-600 bg-primary-50 px-1.5 py-0.5 rounded min-w-[40px] text-center">
                                                    {{ item?.Node_Order || 0 }}
                                                </span>
                                                <button 
                                                    @click="iniciarEditarOrden(item)" 
                                                    class="text-primary-400 hover:text-primary-600 p-0.5 rounded"
                                                    title="Editar orden"
                                                >
                                                    <i class="fas fa-pencil-alt text-[9px]"></i>
                                                </button>
                                            </div>
                                            <div v-else class="flex items-center gap-0.5">
                                                <input 
                                                    type="text" 
                                                    :value="nuevoOrden"
                                                    @input="nuevoOrden = $event.target.value.replace(/[^0-9]/g, '')"
                                                    class="w-12 text-center border border-primary-300 rounded px-0.5 py-0.5 text-xs font-mono"
                                                    autofocus
                                                    @keyup.enter="guardarOrden(item.Id)"
                                                />
                                                <button 
                                                    @click="guardarOrden(item.Id)" 
                                                    class="text-emerald-600 hover:text-emerald-800 p-0.5"
                                                    title="Guardar"
                                                >
                                                    <i class="fas fa-check text-[9px]"></i>
                                                </button>
                                                <button 
                                                    @click="cancelarEditarOrden" 
                                                    class="text-red-500 hover:text-red-700 p-0.5"
                                                    title="Cancelar"
                                                >
                                                    <i class="fas fa-times text-[9px]"></i>
                                                </button>
                                            </div>
                                            
                                            <div v-if="menuEditando !== item.Id">
                                                <button 
                                                    @click="activarEdicion(item)" 
                                                    class="text-primary-600 hover:text-primary-800 p-1 rounded hover:bg-primary-50 transition" 
                                                    title="Editar"
                                                >
                                                    <i class="fas fa-edit text-[10px]"></i>
                                                </button>
                                                <button 
                                                    @click="confirmarEliminar(item)" 
                                                    class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50 transition" 
                                                    title="Eliminar"
                                                    :disabled="item?.hijos && item.hijos.length > 0"
                                                >
                                                    <i class="fas fa-trash-alt text-[10px]" :class="{ 'opacity-40': item?.hijos && item.hijos.length > 0 }"></i>
                                                </button>
                                            </div>
                                            <div v-else class="flex gap-0.5">
                                                <button 
                                                    @click="guardarTexto(item.Id)" 
                                                    class="text-emerald-600 hover:text-emerald-800 p-1 rounded hover:bg-emerald-50 transition" 
                                                    title="Guardar"
                                                >
                                                    <i class="fas fa-save text-[10px]"></i>
                                                </button>
                                                <button 
                                                    @click="cancelarEdicion" 
                                                    class="text-gray-500 hover:text-gray-700 p-1 rounded hover:bg-gray-100 transition" 
                                                    title="Cancelar"
                                                >
                                                    <i class="fas fa-times text-[10px]"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-3 py-1.5 align-top">
                                    <div v-if="menuEditando !== item.Id">
                                        <span v-if="item?.Link" class="font-mono text-[10px] text-primary-600 bg-primary-50 px-1.5 py-0.5 rounded inline-block break-all max-w-[180px]">
                                            <i class="fas fa-link text-[8px] mr-0.5"></i>
                                            {{ item.Link }}
                                        </span>
                                        <span v-else class="text-[10px] text-gray-400 italic">(carpeta)</span>
                                    </div>
                                    <input 
                                        v-else 
                                        v-model="formEditar.Link" 
                                        type="text" 
                                        class="w-full border border-primary-300 rounded px-2 py-0.5 text-xs font-mono focus:ring-1 focus:ring-primary-500 bg-primary-50"
                                        placeholder="/ruta"
                                        @keyup.enter="guardarTexto(item.Id)"
                                    />
                                </td>
                                
                                <td v-for="col in columnasPermisos" :key="col" class="px-2 py-1.5 text-center">
                                    <button
                                        @click="togglePermiso(item, col)"
                                        class="inline-flex items-center justify-center w-14 px-1.5 py-0.5 rounded text-[9px] font-medium transition"
                                        :class="(item?.[col] || 0) == 1 ? 'bg-primary-100 text-primary-700 hover:bg-primary-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                    >
                                        <i v-if="(item?.[col] || 0) == 1" class="fas fa-check-circle mr-0.5 text-[8px]"></i>
                                        {{ (item?.[col] || 0) == 1 ? 'Activo' : 'Inactivo' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- VISTA MÓVIL (sin cambios significativos) -->
            <div class="lg:hidden space-y-2">
                <div v-if="!itemsParaRenderizar || itemsParaRenderizar.length === 0" class="bg-white rounded-lg p-6 text-center text-gray-400 text-xs">
                    <i class="fas fa-search text-2xl mb-1 block"></i>
                    No hay resultados
                </div>
                <div v-else v-for="(item, idx) in itemsParaRenderizar" :key="item?.Id || `mob-${idx}`"
                     class="bg-white rounded-lg shadow-sm overflow-hidden"
                     :style="{ marginLeft: `${(item?.nivel || 0) * 6}px` }">
                    <div class="p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button 
                                        v-if="item?.hijos && item.hijos.length"
                                        @click="toggleExpandir(item?.Id)"
                                        class="w-5 h-5 flex items-center justify-center rounded-full bg-gray-100 flex-shrink-0"
                                    >
                                        <i :class="expandidos[item?.Id] ? 'fas fa-minus text-[8px]' : 'fas fa-plus text-[8px]'"></i>
                                    </button>
                                    <div class="font-medium text-gray-900 text-sm">{{ item?.Description || '' }}</div>
                                </div>
                                
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <div class="flex items-center gap-0.5">
                                        <span class="text-[9px] text-gray-500">Orden:</span>
                                        <span v-if="editandoOrden !== item?.Id" class="text-[10px] font-mono text-primary-600 bg-primary-50 px-1.5 py-0.5 rounded">
                                            {{ item?.Node_Order || 0 }}
                                        </span>
                                        <div v-else class="flex items-center gap-0.5">
                                            <input 
                                                type="text" 
                                                :value="nuevoOrden"
                                                @input="nuevoOrden = $event.target.value.replace(/[^0-9]/g, '')"
                                                class="w-12 text-center border border-primary-300 rounded px-0.5 py-0.5 text-xs font-mono"
                                                autofocus
                                                @keyup.enter="guardarOrden(item.Id)"
                                            />
                                            <button @click="guardarOrden(item.Id)" class="text-emerald-600 hover:text-emerald-800 p-0.5">
                                                <i class="fas fa-check text-[9px]"></i>
                                            </button>
                                            <button @click="cancelarEditarOrden" class="text-red-500 hover:text-red-700 p-0.5">
                                                <i class="fas fa-times text-[9px]"></i>
                                            </button>
                                        </div>
                                        <button v-if="editandoOrden !== item?.Id" @click="iniciarEditarOrden(item)" class="text-primary-400 hover:text-primary-600 p-0.5">
                                            <i class="fas fa-pencil-alt text-[9px]"></i>
                                        </button>
                                    </div>
                                    <span v-if="item?.hijos && item.hijos.length" class="text-[9px] text-primary-500">
                                        📁 {{ item.hijos.length }} sub
                                    </span>
                                </div>
                                
                                <div v-if="item?.Link" class="mt-1.5">
                                    <span class="text-[10px] font-mono text-primary-600 bg-primary-50 px-1.5 py-0.5 rounded break-all inline-block">
                                        🔗 {{ item.Link }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex gap-0.5 flex-shrink-0">
                                <button @click="activarEdicion(item)" class="text-primary-600 p-1">
                                    <i class="fas fa-edit text-[11px]"></i>
                                </button>
                                <button @click="confirmarEliminar(item)" class="text-red-500 p-1">
                                    <i class="fas fa-trash-alt text-[11px]"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-2 pt-2 border-t border-gray-100">
                            <div class="text-[9px] font-medium text-gray-500 mb-1.5">Permisos:</div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <div v-for="col in columnasPermisos" :key="col" class="flex items-center justify-between">
                                    <span class="text-[9px] text-gray-600 truncate mr-0.5">{{ col }}</span>
                                    <button
                                        @click="togglePermiso(item, col)"
                                        class="px-1.5 py-0.5 rounded text-[9px] font-medium transition flex-shrink-0"
                                        :class="(item?.[col] || 0) == 1 ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-500'"
                                    >
                                        {{ (item?.[col] || 0) == 1 ? 'Activo' : 'Inactivo' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="menuEditando === item?.Id" class="mt-2 pt-2 border-t border-gray-100">
                            <div class="space-y-1.5">
                                <input 
                                    v-model="formEditar.Description" 
                                    type="text" 
                                    class="w-full border border-primary-300 rounded px-2 py-1 text-xs bg-primary-50"
                                    placeholder="Nombre"
                                />
                                <input 
                                    v-model="formEditar.Link" 
                                    type="text" 
                                    class="w-full border border-primary-300 rounded px-2 py-1 text-xs bg-primary-50"
                                    placeholder="/ruta"
                                />
                                <div class="flex gap-1.5">
                                    <button @click="guardarTexto(item.Id)" class="flex-1 px-2 py-1 bg-primary-600 text-white rounded text-[10px] hover:bg-primary-700">
                                        Guardar
                                    </button>
                                    <button @click="cancelarEdicion" class="flex-1 px-2 py-1 bg-gray-200 text-gray-700 rounded text-[10px] hover:bg-gray-300">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL AGREGAR (componente separado) -->
            <ModalNuevoMenu
                :visible="modalAgregar"
                :columnasPermisos="columnasPermisos"
                :menusPlano="todosMenusPlano"
                @close="modalAgregar = false"
                @save="guardarNuevo"
            />

            <!-- MODAL ELIMINAR (más delgado) -->
            <div v-if="modalEliminar" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="modalEliminar = null"></div>
                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                        <div class="bg-red-600 px-5 py-3">
                            <h3 class="text-base font-semibold text-white flex items-center gap-2">
                                <i class="fas fa-exclamation-triangle text-sm"></i> Confirmar Eliminación
                            </h3>
                        </div>
                        <div class="bg-white px-5 pt-4 pb-3">
                            <p class="text-sm text-gray-700">
                                ¿Eliminar <strong class="text-primary-700">"{{ modalEliminar?.Description }}"</strong>?
                            </p>
                            <p class="text-[10px] text-gray-500 mt-1">Esta acción no se puede deshacer.</p>
                        </div>
                        <div class="bg-gray-50 px-5 py-3 flex flex-col sm:flex-row justify-end gap-2">
                            <button @click="modalEliminar = null" class="px-3 py-1 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition">
                                Cancelar
                            </button>
                            <button @click="eliminarMenu" class="px-4 py-1 bg-red-600 text-white rounded-lg text-xs hover:bg-red-700 transition">
                                Sí, Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sticky.left-0 {
    position: sticky !important;
    left: 0 !important;
    background-color: white !important;
    z-index: 15 !important;
}

thead.sticky {
    position: sticky !important;
    top: 0 !important;
    z-index: 20 !important;
}

.sticky.left-0.top-0 {
    position: sticky !important;
    left: 0 !important;
    top: 0 !important;
    background-color: rgb(249, 250, 251) !important;
    z-index: 30 !important;
}

.group:hover .sticky.left-0 {
    background-color: rgb(249, 250, 251) !important;
}

.sticky.left-0 {
    box-shadow: 2px 0 4px -2px rgba(0, 0, 0, 0.08);
}

.sticky.left-0.top-0 {
    box-shadow: 2px 0 4px -2px rgba(0, 0, 0, 0.08), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
}
</style>