<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

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

const nuevoMenu = ref({
    Description: '',
    Link: '',
    Parent: 0,
    permisos: {}
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

// Editar orden - usando text input sin flechas
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

const soloNumeros = (event) => {
    nuevoOrden.value = event.target.value.replace(/[^0-9]/g, '')
}

const siguienteOrden = computed(() => {
    const parentId = nuevoMenu.value.Parent ?? 0
    const hijosDelPadre = props.menus.filter(m => m.Parent === parentId)
    const maxOrden = Math.max(...hijosDelPadre.map(m => m.Node_Order || 0), 0)
    return maxOrden + 1
})

const abrirAgregar = () => {
    nuevoMenu.value = {
        Description: '',
        Link: '',
        Parent: 0,
        permisos: {}
    }
    if (props.columnasPermisos && props.columnasPermisos.length) {
        for (const col of props.columnasPermisos) {
            nuevoMenu.value.permisos[col] = 0
        }
    }
    modalAgregar.value = true
}

const guardarNuevo = () => {
    if (!nuevoMenu.value.Description.trim()) {
        alert('El nombre del menú es obligatorio')
        return
    }
    
    guardando.value = true
    
    const parentValue = nuevoMenu.value.Parent === null || nuevoMenu.value.Parent === undefined ? 0 : nuevoMenu.value.Parent
    
    const datos = {
        Description: nuevoMenu.value.Description,
        Link: nuevoMenu.value.Link || '',
        Parent: parentValue,
        ...nuevoMenu.value.permisos
    }
    
    router.post('/gestion/menu-administrador', datos, {
        preserveScroll: true,
        onSuccess: () => {
            modalAgregar.value = false
            guardando.value = false
        },
        onError: (errors) => {
            console.error('Error:', errors)
            alert('Error al crear el menú. Revisa la consola.')
            guardando.value = false
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
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 py-4 sm:py-6 px-3 sm:px-4 lg:px-6">
        <div class="max-w-full mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-bars text-primary-600 text-lg sm:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Gestión del Menú</h1>
                            <p class="text-xs sm:text-sm text-gray-500 hidden sm:block">
                                Administra la estructura, enlaces y permisos del sistema
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="abrirAgregar"
                        class="px-4 sm:px-5 py-2 bg-primary-700 hover:bg-primary-800 text-white rounded-lg sm:rounded-xl font-medium shadow-sm transition-all flex items-center justify-center gap-2 text-sm sm:text-base"
                    >
                        <i class="fas fa-plus text-xs sm:text-sm"></i>
                        <span class="hidden sm:inline">Nuevo Menú</span>
                        <span class="sm:hidden">Agregar</span>
                    </button>
                </div>
            </div>

            <!-- Buscador y acciones -->
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-sm p-4 sm:p-5 mb-4 sm:mb-6">
                <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                    <div class="relative flex-1 max-w-md">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input 
                            type="text" 
                            v-model="busqueda" 
                            placeholder="Buscar por nombre o enlace..."
                            class="w-full pl-9 pr-8 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                        <button 
                            v-if="busqueda" 
                            @click="limpiarBusqueda"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <button 
                            @click="expandirTodo"
                            class="px-3 py-1.5 text-xs sm:text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                        >
                            <i class="fas fa-expand-alt mr-1"></i> Expandir todo
                        </button>
                        <button 
                            @click="contraerTodo"
                            class="px-3 py-1.5 text-xs sm:text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                        >
                            <i class="fas fa-compress-alt mr-1"></i> Contraer todo
                        </button>
                    </div>
                </div>
                <div v-if="busqueda" class="mt-2 text-xs text-primary-600">
                    <i class="fas fa-search mr-1"></i> Mostrando resultados para: "{{ busqueda }}"
                </div>
            </div>

            <!-- TABLA DESKTOP -->
            <div class="hidden lg:block bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="relative overflow-x-auto overflow-y-auto" style="max-height: calc(100vh - 280px);">
                    <table class="min-w-max w-full divide-y divide-gray-200 text-sm">
                        <thead class="sticky top-0 z-20">
                            <tr class="bg-gray-50">
                                <th class="sticky left-0 top-0 bg-gray-50 z-30 px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase min-w-[420px] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.1),0_2px_4px_-2px_rgba(0,0,0,0.05)]">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-folder-open text-primary-500"></i>
                                            Menú / Submenús
                                        </div>
                                        <div class="text-center w-[120px]">
                                            Orden
                                        </div>
                                    </div>
                                </th>
                                <th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase min-w-[220px]">
                                    <i class="fas fa-link mr-1"></i> Enlace
                                </th>
                                <th v-for="col in columnasPermisos" :key="col" 
                                    class="bg-gray-50 px-3 py-3 text-center text-xs font-semibold text-gray-700 uppercase whitespace-nowrap">
                                    {{ col }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!itemsParaRenderizar || itemsParaRenderizar.length === 0">
                                <td :colspan="2 + (columnasPermisos?.length || 0)" class="px-6 py-12 text-center text-gray-400">
                                    <i class="fas fa-search text-4xl mb-2 block"></i>
                                    No se encontraron resultados
                                </td>
                            </tr>
                            <tr v-for="(item, idx) in itemsParaRenderizar" :key="item ? item.Id : `item-${idx}`" class="hover:bg-gray-50 transition-colors group">
                                <td class="sticky left-0 bg-white group-hover:bg-gray-50 px-4 py-3 align-top shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] z-10">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-start flex-1">
                                            <div :style="{ width: `${(item?.nivel || 0) * 24}px`, minWidth: '0' }" class="flex-shrink-0"></div>
                                            <button 
                                                v-if="item?.hijos && item.hijos.length"
                                                @click="toggleExpandir(item.Id)"
                                                class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-200 flex-shrink-0"
                                            >
                                                <i :class="expandidos[item.Id] ? 'fas fa-chevron-down text-xs' : 'fas fa-chevron-right text-xs'"></i>
                                            </button>
                                            <div v-else class="w-6 flex-shrink-0"></div>
                                            
                                            <div v-if="menuEditando !== item.Id" class="flex-1">
                                                <div :class="{ 'font-bold text-gray-900': (item?.nivel || 0) === 0, 'text-gray-700': (item?.nivel || 0) > 0 }">
                                                    {{ item?.Description || '' }}
                                                </div>
                                                <div class="text-xs text-gray-400 mt-0.5">
                                                    <span class="font-mono">ID: {{ item?.Id || '-' }}</span>
                                                    <span v-if="item?.hijos && item.hijos.length" class="ml-2 text-primary-600">
                                                        <i class="fas fa-folder-open mr-1"></i>{{ item.hijos.length }} sub
                                                    </span>
                                                </div>
                                            </div>
                                            <input 
                                                v-else 
                                                v-model="formEditar.Description" 
                                                type="text" 
                                                class="flex-1 border border-primary-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-primary-50"
                                                @keyup.enter="guardarTexto(item.Id)"
                                                autofocus
                                            />
                                        </div>
                                        
                                        <!-- Columna ORDEN - sin flechas, solo texto -->
                                        <div class="flex items-center gap-2 flex-shrink-0 w-[120px] justify-end">
                                            <div v-if="editandoOrden !== item.Id" class="flex items-center gap-1">
                                                <span class="text-xs font-mono text-primary-600 bg-primary-50 px-2 py-1 rounded-lg min-w-[50px] text-center">
                                                    {{ item?.Node_Order || 0 }}
                                                </span>
                                                <button 
                                                    @click="iniciarEditarOrden(item)" 
                                                    class="text-primary-400 hover:text-primary-600 p-1 rounded"
                                                    title="Editar orden"
                                                >
                                                    <i class="fas fa-pencil-alt text-xs"></i>
                                                </button>
                                            </div>
                                            <div v-else class="flex items-center gap-1">
                                                <input 
                                                    type="text" 
                                                    :value="nuevoOrden"
                                                    @input="nuevoOrden = $event.target.value.replace(/[^0-9]/g, '')"
                                                    class="w-14 text-center border border-primary-300 rounded-lg px-1 py-1 text-xs font-mono"
                                                    autofocus
                                                    @keyup.enter="guardarOrden(item.Id)"
                                                />
                                                <button 
                                                    @click="guardarOrden(item.Id)" 
                                                    class="text-emerald-600 hover:text-emerald-800 p-1"
                                                    title="Guardar orden"
                                                >
                                                    <i class="fas fa-check text-xs"></i>
                                                </button>
                                                <button 
                                                    @click="cancelarEditarOrden" 
                                                    class="text-red-500 hover:text-red-700 p-1"
                                                    title="Cancelar"
                                                >
                                                    <i class="fas fa-times text-xs"></i>
                                                </button>
                                            </div>
                                            
                                            <div v-if="menuEditando !== item.Id">
                                                <button 
                                                    @click="activarEdicion(item)" 
                                                    class="text-primary-600 hover:text-primary-800 p-1.5 rounded-lg hover:bg-primary-50 transition" 
                                                    title="Editar texto/enlace"
                                                >
                                                    <i class="fas fa-edit text-sm"></i>
                                                </button>
                                                <button 
                                                    @click="confirmarEliminar(item)" 
                                                    class="text-red-500 hover:text-red-700 p-1.5 rounded-lg hover:bg-red-50 transition" 
                                                    title="Eliminar menú"
                                                    :disabled="item?.hijos && item.hijos.length > 0"
                                                >
                                                    <i class="fas fa-trash-alt text-sm" :class="{ 'opacity-40': item?.hijos && item.hijos.length > 0 }"></i>
                                                </button>
                                            </div>
                                            <div v-else class="flex gap-1">
                                                <button 
                                                    @click="guardarTexto(item.Id)" 
                                                    class="text-emerald-600 hover:text-emerald-800 p-1.5 rounded-lg hover:bg-emerald-50 transition" 
                                                    title="Guardar cambios"
                                                >
                                                    <i class="fas fa-save text-sm"></i>
                                                </button>
                                                <button 
                                                    @click="cancelarEdicion" 
                                                    class="text-gray-500 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition" 
                                                    title="Cancelar"
                                                >
                                                    <i class="fas fa-times text-sm"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-4 py-3 align-top">
                                    <div v-if="menuEditando !== item.Id">
                                        <span v-if="item?.Link" class="font-mono text-xs text-primary-600 bg-primary-50 px-2 py-1 rounded-lg inline-block break-all max-w-[200px]">
                                            <i class="fas fa-link text-xs mr-1"></i>
                                            {{ item.Link }}
                                        </span>
                                        <span v-else class="text-xs text-gray-400 italic">(carpeta)</span>
                                    </div>
                                    <input 
                                        v-else 
                                        v-model="formEditar.Link" 
                                        type="text" 
                                        class="w-full border border-primary-300 rounded-lg px-3 py-1.5 text-sm font-mono focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-primary-50"
                                        placeholder="/ruta/del/menu"
                                        @keyup.enter="guardarTexto(item.Id)"
                                    />
                                </td>
                                
                                <td v-for="col in columnasPermisos" :key="col" class="px-3 py-3 text-center">
                                    <button
                                        @click="togglePermiso(item, col)"
                                        class="inline-flex items-center justify-center w-16 px-2 py-1.5 rounded-lg text-xs font-medium transition"
                                        :class="(item?.[col] || 0) == 1 ? 'bg-primary-100 text-primary-700 hover:bg-primary-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                    >
                                        <i v-if="(item?.[col] || 0) == 1" class="fas fa-check-circle mr-1 text-primary-500"></i>
                                        {{ (item?.[col] || 0) == 1 ? 'Activo' : 'Inactivo' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- VISTA MÓVIL -->
            <div class="lg:hidden space-y-3">
                <div v-if="!itemsParaRenderizar || itemsParaRenderizar.length === 0" class="bg-white rounded-xl p-8 text-center text-gray-400">
                    <i class="fas fa-search text-3xl mb-2 block"></i>
                    No hay resultados
                </div>
                <div v-else v-for="(item, idx) in itemsParaRenderizar" :key="item?.Id || `mob-${idx}`"
                     class="bg-white rounded-xl shadow-sm overflow-hidden"
                     :style="{ marginLeft: `${(item?.nivel || 0) * 8}px` }">
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <button 
                                        v-if="item?.hijos && item.hijos.length"
                                        @click="toggleExpandir(item?.Id)"
                                        class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-100 flex-shrink-0"
                                    >
                                        <i :class="expandidos[item?.Id] ? 'fas fa-minus text-xs' : 'fas fa-plus text-xs'"></i>
                                    </button>
                                    <div class="font-medium text-gray-900 break-words">{{ item?.Description || '' }}</div>
                                    <span class="text-xs text-gray-400 font-mono">(#{{ item?.Id || '-' }})</span>
                                </div>
                                
                                <!-- ORDEN en móvil - sin flechas -->
                                <div class="flex items-center gap-3 mt-1 flex-wrap">
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs text-gray-500">Orden:</span>
                                        <span v-if="editandoOrden !== item?.Id" class="text-xs font-mono text-primary-600 bg-primary-50 px-2 py-0.5 rounded">
                                            {{ item?.Node_Order || 0 }}
                                        </span>
                                        <div v-else class="flex items-center gap-1">
                                            <input 
                                                type="text" 
                                                :value="nuevoOrden"
                                                @input="nuevoOrden = $event.target.value.replace(/[^0-9]/g, '')"
                                                class="w-14 text-center border border-primary-300 rounded-lg px-1 py-0.5 text-xs font-mono"
                                                autofocus
                                                @keyup.enter="guardarOrden(item.Id)"
                                            />
                                            <button @click="guardarOrden(item.Id)" class="text-emerald-600 hover:text-emerald-800 p-0.5">
                                                <i class="fas fa-check text-xs"></i>
                                            </button>
                                            <button @click="cancelarEditarOrden" class="text-red-500 hover:text-red-700 p-0.5">
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                        </div>
                                        <button v-if="editandoOrden !== item?.Id" @click="iniciarEditarOrden(item)" class="text-primary-400 hover:text-primary-600 p-1">
                                            <i class="fas fa-pencil-alt text-xs"></i>
                                        </button>
                                    </div>
                                    <span v-if="item?.hijos && item.hijos.length" class="text-xs text-primary-600">
                                        📁 {{ item.hijos.length }} submenús
                                    </span>
                                </div>
                                
                                <div v-if="item?.Link" class="mt-2">
                                    <span class="text-xs font-mono text-primary-600 bg-primary-50 px-2 py-1 rounded-lg break-all inline-block">
                                        🔗 {{ item.Link }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex gap-1 flex-shrink-0">
                                <button @click="activarEdicion(item)" class="text-primary-600 p-2">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button @click="confirmarEliminar(item)" class="text-red-500 p-2">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <div class="text-xs font-medium text-gray-500 mb-2">Permisos:</div>
                            <div class="grid grid-cols-2 gap-2">
                                <div v-for="col in columnasPermisos" :key="col" class="flex items-center justify-between">
                                    <span class="text-xs text-gray-600 truncate mr-1">{{ col }}</span>
                                    <button
                                        @click="togglePermiso(item, col)"
                                        class="px-2 py-1 rounded-lg text-xs font-medium transition flex-shrink-0"
                                        :class="(item?.[col] || 0) == 1 ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-500'"
                                    >
                                        {{ (item?.[col] || 0) == 1 ? 'Activo' : 'Inactivo' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="menuEditando === item?.Id" class="mt-3 pt-3 border-t border-gray-100">
                            <div class="space-y-2">
                                <input 
                                    v-model="formEditar.Description" 
                                    type="text" 
                                    class="w-full border border-primary-300 rounded-lg px-3 py-2 text-sm bg-primary-50"
                                    placeholder="Nombre del menú"
                                />
                                <input 
                                    v-model="formEditar.Link" 
                                    type="text" 
                                    class="w-full border border-primary-300 rounded-lg px-3 py-2 text-sm bg-primary-50"
                                    placeholder="/ruta/del/menu"
                                />
                                <div class="flex gap-2">
                                    <button @click="guardarTexto(item.Id)" class="flex-1 px-3 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700">
                                        Guardar
                                    </button>
                                    <button @click="cancelarEdicion" class="flex-1 px-3 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL AGREGAR -->
            <div v-if="modalAgregar" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="modalAgregar = false"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-primary-700 px-6 py-4">
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fas fa-plus-circle"></i> Nuevo Menú
                            </h3>
                            <p class="text-xs text-primary-100 mt-1">Complete los datos del nuevo elemento del menú</p>
                        </div>
                        
                        <div class="bg-white px-6 pt-5 pb-4">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 uppercase mb-1">Nombre / Descripción *</label>
                                    <input 
                                        type="text" 
                                        v-model="nuevoMenu.Description"
                                        placeholder="Ej. Reporte de Ventas"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 uppercase mb-1">Enlace / Ruta</label>
                                    <input 
                                        type="text" 
                                        v-model="nuevoMenu.Link"
                                        placeholder="Ej. /reportes/ventas (dejar vacío si es carpeta)"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-primary-500 focus:border-primary-500"
                                    />
                                    <p class="text-xs text-gray-400 mt-1">Dejar vacío si es solo una carpeta (con hijos)</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 uppercase mb-1">Ubicación (Menú Padre)</label>
                                    <select 
                                        v-model="nuevoMenu.Parent"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500"
                                    >
                                        <option v-for="m in todosMenusPlano" :key="m.id" :value="m.id">
                                            {{ m.nombre }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Etiqueta informativa del orden -->
                                <div class="bg-primary-50 rounded-lg p-3 border border-primary-200">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-primary-800">
                                            <i class="fas fa-sort-numeric-down-alt mr-1"></i> Orden asignado:
                                        </span>
                                        <span class="text-lg font-bold text-primary-700 bg-white px-3 py-1 rounded-lg shadow-sm">
                                            {{ siguienteOrden }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-primary-600 mt-2">
                                        El orden se calcula automáticamente al final de los hijos del padre seleccionado.
                                        Si deseas cambiarlo después, puedes editarlo en la tabla.
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 uppercase mb-2">Permisos iniciales</label>
                                    <div class="grid grid-cols-2 gap-2 border rounded-lg p-3 max-h-48 overflow-y-auto bg-gray-50">
                                        <div v-for="col in columnasPermisos" :key="col" class="flex items-center justify-between bg-white p-2 rounded-lg shadow-sm border">
                                            <span class="text-sm text-gray-700">{{ col }}</span>
                                            <button
                                                @click="nuevoMenu.permisos[col] = nuevoMenu.permisos[col] == 1 ? 0 : 1"
                                                class="px-3 py-1 rounded-lg text-xs font-medium transition min-w-[65px]"
                                                :class="nuevoMenu.permisos[col] == 1 ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-600'"
                                            >
                                                {{ nuevoMenu.permisos[col] == 1 ? 'Activo' : 'Inactivo' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex flex-col sm:flex-row justify-end gap-3">
                            <button 
                                type="button" 
                                @click="modalAgregar = false"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition order-2 sm:order-1"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="button" 
                                @click="guardarNuevo"
                                :disabled="guardando"
                                class="px-5 py-2 bg-primary-700 text-white rounded-lg hover:bg-primary-800 transition disabled:opacity-50 flex items-center justify-center gap-2 order-1 sm:order-2"
                            >
                                <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                                {{ guardando ? 'Guardando...' : 'Guardar Menú' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL ELIMINAR -->
            <div v-if="modalEliminar" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="modalEliminar = null"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                        <div class="bg-red-600 px-6 py-4">
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación
                            </h3>
                        </div>
                        
                        <div class="bg-white px-6 pt-5 pb-4">
                            <p class="text-gray-700">
                                ¿Estás seguro de eliminar <strong class="text-primary-700">"{{ modalEliminar?.Description }}"</strong>?
                            </p>
                            <p class="text-xs text-gray-500 mt-2">Esta acción no se puede deshacer.</p>
                        </div>
                        
                        <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex flex-col sm:flex-row justify-end gap-3">
                            <button 
                                type="button" 
                                @click="modalEliminar = null"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition order-2 sm:order-1"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="button" 
                                @click="eliminarMenu"
                                class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition order-1 sm:order-2"
                            >
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
/* Columna fija izquierda */
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
    box-shadow: 2px 0 4px -2px rgba(0, 0, 0, 0.08), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
}

/* Quitar spinners de inputs number */
input[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>