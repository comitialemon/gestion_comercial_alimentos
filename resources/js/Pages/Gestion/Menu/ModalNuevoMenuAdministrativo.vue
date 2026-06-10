<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    columnasPermisos: {
        type: Array,
        default: () => []
    },
    menusPlano: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['close', 'save'])

const guardando = ref(false)
const busquedaPadre = ref('')
const mostrarListaPadre = ref(false)

const nuevoMenu = ref({
    Description: '',
    Link: '',
    Parent: 0,
    permisos: {}
})

// Inicializar permisos cuando cambian las columnas
watch(() => props.columnasPermisos, (nuevas) => {
    if (nuevas && nuevas.length) {
        const permisosIniciales = {}
        for (const col of nuevas) {
            permisosIniciales[col] = 0
        }
        nuevoMenu.value.permisos = permisosIniciales
    }
}, { immediate: true })

// Resetear formulario cuando se abre el modal
watch(() => props.visible, (val) => {
    if (val) {
        resetForm()
    }
})

const resetForm = () => {
    busquedaPadre.value = ''
    mostrarListaPadre.value = false
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
}

// Menús filtrados para el buscador
const menusFiltrados = computed(() => {
    if (!busquedaPadre.value.trim()) {
        return props.menusPlano || []
    }
    const termino = busquedaPadre.value.toLowerCase()
    return (props.menusPlano || []).filter(m => 
        m.nombre.toLowerCase().includes(termino)
    )
})

// Obtener nombre del padre seleccionado
const nombrePadreSeleccionado = computed(() => {
    if (nuevoMenu.value.Parent === 0) return '📁 [RAÍZ]'
    const encontrado = (props.menusPlano || []).find(m => m.id === nuevoMenu.value.Parent)
    return encontrado ? encontrado.nombre : 'Seleccione...'
})

// Siguiente orden
const siguienteOrden = computed(() => {
    const parentId = nuevoMenu.value.Parent ?? 0
    const hijosDelPadre = (props.menusPlano || []).filter(m => {
        if (!m.nombre) return false
        const match = m.nombre.match(/\(#(\d+)\)/)
        if (!match) return false
        return parseInt(match[1]) === parentId
    })
    const maxOrden = Math.max(...hijosDelPadre.map(() => 0), 0)
    return maxOrden + 1
})

const seleccionarPadre = (id) => {
    nuevoMenu.value.Parent = id
    busquedaPadre.value = ''
    mostrarListaPadre.value = false
}

const toggleListaPadre = () => {
    mostrarListaPadre.value = !mostrarListaPadre.value
    if (mostrarListaPadre.value) {
        busquedaPadre.value = ''
    }
}

const cerrarModal = () => {
    emit('close')
}

const guardar = () => {
    if (!nuevoMenu.value.Description.trim()) {
        alert('El nombre del menú es obligatorio')
        return
    }
    
    guardando.value = true
    
    const datos = {
        Description: nuevoMenu.value.Description,
        Link: nuevoMenu.value.Link || '',
        Parent: nuevoMenu.value.Parent === null || nuevoMenu.value.Parent === undefined ? 0 : nuevoMenu.value.Parent,
        ...nuevoMenu.value.permisos
    }
    
    emit('save', datos, () => {
        guardando.value = false
        cerrarModal()
    })
}
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="cerrarModal"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-primary-700 px-6 py-3">
                    <h3 class="text-base font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-plus-circle text-sm"></i> Nuevo Menú
                    </h3>
                    <p class="text-xs text-primary-100 mt-0.5">Complete los datos del nuevo elemento del menú</p>
                </div>
                
                <div class="bg-white px-6 pt-4 pb-3">
                    <div class="space-y-3">
                        <!-- Nombre -->
                        <div>
                            <label class="block text-[10px] font-medium text-gray-700 uppercase mb-0.5">Nombre / Descripción *</label>
                            <input 
                                type="text" 
                                v-model="nuevoMenu.Description"
                                placeholder="Ej. Reporte de Ventas"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                @keyup.enter="guardar"
                            />
                        </div>

                        <!-- Enlace -->
                        <div>
                            <label class="block text-[10px] font-medium text-gray-700 uppercase mb-0.5">Enlace / Ruta</label>
                            <input 
                                type="text" 
                                v-model="nuevoMenu.Link"
                                placeholder="Ej. /reportes/ventas (dejar vacío si es carpeta)"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm font-mono focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                            />
                            <p class="text-[10px] text-gray-400 mt-0.5">Dejar vacío si es solo una carpeta (con hijos)</p>
                        </div>

                        <!-- Ubicación (Padre) con buscador -->
                        <div>
                            <label class="block text-[10px] font-medium text-gray-700 uppercase mb-0.5">Ubicación (Menú Padre)</label>
                            <div class="relative">
                                <div 
                                    @click="toggleListaPadre"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm flex items-center justify-between cursor-pointer hover:bg-gray-50"
                                >
                                    <span class="truncate" :class="{ 'text-gray-500': nuevoMenu.Parent === 0, 'text-gray-800': nuevoMenu.Parent !== 0 }">
                                        {{ nombrePadreSeleccionado }}
                                    </span>
                                    <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                                </div>
                                
                                <div v-if="mostrarListaPadre" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg">
                                    <div class="p-2 border-b">
                                        <div class="relative">
                                            <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                            <input 
                                                type="text" 
                                                v-model="busquedaPadre"
                                                placeholder="Buscar menú..."
                                                class="w-full pl-7 pr-2 py-1 text-xs border border-gray-200 rounded focus:ring-1 focus:ring-primary-500"
                                                @click.stop
                                            />
                                        </div>
                                    </div>
                                    <div class="max-h-48 overflow-y-auto">
                                        <div 
                                            v-for="m in menusFiltrados" 
                                            :key="m.id"
                                            @click="seleccionarPadre(m.id)"
                                            class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm border-b border-gray-100 last:border-b-0"
                                            :class="{ 'bg-primary-50 text-primary-700': nuevoMenu.Parent === m.id }"
                                        >
                                            <div class="flex items-center gap-2">
                                                <span v-if="m.id === 0" class="text-amber-600">📁</span>
                                                <span v-else class="text-gray-400 text-xs">└─</span>
                                                <span class="truncate" :style="{ marginLeft: `${(m.nivel || 0) * 12}px` }">
                                                    {{ m.nombre }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-0.5">Selecciona dónde colocar este menú</p>
                        </div>

                        <!-- Orden informativo -->
                        <div class="bg-primary-50 rounded-lg p-2 border border-primary-200">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-primary-800">
                                    <i class="fas fa-sort-numeric-down-alt mr-1 text-[10px]"></i> Orden asignado:
                                </span>
                                <span class="text-sm font-bold text-primary-700 bg-white px-2 py-0.5 rounded shadow-sm">
                                    {{ siguienteOrden }}
                                </span>
                            </div>
                            <p class="text-[10px] text-primary-600 mt-1">
                                El orden se calcula automáticamente. Puedes cambiarlo después en la tabla.
                            </p>
                        </div>

                        <!-- Permisos -->
                        <div>
                            <label class="block text-[10px] font-medium text-gray-700 uppercase mb-1">Permisos iniciales</label>
                            <div class="grid grid-cols-2 gap-1.5 border rounded-lg p-2 max-h-40 overflow-y-auto bg-gray-50">
                                <div v-for="col in columnasPermisos" :key="col" class="flex items-center justify-between bg-white p-1.5 rounded shadow-sm border">
                                    <span class="text-xs text-gray-700 truncate mr-1">{{ col }}</span>
                                    <button
                                        @click="nuevoMenu.permisos[col] = nuevoMenu.permisos[col] == 1 ? 0 : 1"
                                        class="px-2 py-0.5 rounded text-[10px] font-medium transition min-w-[50px]"
                                        :class="nuevoMenu.permisos[col] == 1 ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-600'"
                                    >
                                        {{ nuevoMenu.permisos[col] == 1 ? 'Activo' : 'Inactivo' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-3 flex flex-col sm:flex-row justify-end gap-2">
                    <button 
                        type="button" 
                        @click="cerrarModal"
                        class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition order-2 sm:order-1"
                    >
                        Cancelar
                    </button>
                    <button 
                        type="button" 
                        @click="guardar"
                        :disabled="guardando"
                        class="px-4 py-1.5 bg-primary-700 text-white rounded-lg text-xs hover:bg-primary-800 transition disabled:opacity-50 flex items-center justify-center gap-1 order-1 sm:order-2"
                    >
                        <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-save text-[10px]"></i>
                        {{ guardando ? 'Guardando...' : 'Guardar Menú' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>