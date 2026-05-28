<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'

const page = usePage()

const props = defineProps({
    modelValue: Boolean,
    asignacion: Object,
    sucursales: Array,
    operadores: Array,
    editando: Boolean,
})

const emit = defineEmits(['update:modelValue', 'saved'])

// Datos de la empresa logueada
const empresaNombre = computed(() => page.props?.empresaNombre || '')

const form = ref({
    IdSucursal: '',
    IdOperador: '',
})

const loading = ref(false)
const errors = ref({})

// Búsqueda de sucursales
const searchSucursal = ref('')
const showSucursalDropdown = ref(false)

const sucursalesFiltradas = computed(() => {
    if (!searchSucursal.value) return props.sucursales || []
    const term = searchSucursal.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(term) || 
        s.NumeroSucursal?.toString().includes(term)
    )
})

// Búsqueda de operadores
const searchOperador = ref('')
const showOperadorDropdown = ref(false)

const operadoresFiltrados = computed(() => {
    if (!searchOperador.value) return props.operadores || []
    const term = searchOperador.value.toLowerCase()
    return (props.operadores || []).filter(op => 
        op.nombre?.toLowerCase().includes(term) || 
        op.ci?.toString().includes(term) ||
        op.iniciales?.toLowerCase().includes(term)
    )
})

// Actualizar búsqueda de operador (corregido)
const onSearchOperadorInput = (event) => {
    if (!props.editando) {
        searchOperador.value = event.target.value
        if (searchOperador.value === '') {
            form.value.IdOperador = ''
        }
    }
}

// Actualizar búsqueda de sucursal
const onSearchSucursalInput = (event) => {
    searchSucursal.value = event.target.value
    if (searchSucursal.value === '') {
        form.value.IdSucursal = ''
    }
}

// Seleccionar sucursal
const seleccionarSucursal = (sucursal) => {
    form.value.IdSucursal = sucursal.id
    searchSucursal.value = `${sucursal.nombre} ${sucursal.NumeroSucursal ? `(N° ${sucursal.NumeroSucursal})` : ''}`
    showSucursalDropdown.value = false
}

// Seleccionar operador
const seleccionarOperador = (operador) => {
    form.value.IdOperador = operador.id
    searchOperador.value = `${operador.ci} - ${operador.nombre} (${operador.iniciales})`
    showOperadorDropdown.value = false
}

// Limpiar selecciones
const limpiarSucursal = () => {
    form.value.IdSucursal = ''
    searchSucursal.value = ''
}

const limpiarOperador = () => {
    if (!props.editando) {
        form.value.IdOperador = ''
        searchOperador.value = ''
    }
}

// Cerrar dropdowns con delay
const cerrarDropdownSucursal = () => {
    setTimeout(() => {
        showSucursalDropdown.value = false
    }, 200)
}

const cerrarDropdownOperador = () => {
    setTimeout(() => {
        showOperadorDropdown.value = false
    }, 200)
}

// Resetear formulario
const resetForm = () => {
    form.value = {
        IdSucursal: '',
        IdOperador: '',
    }
    searchSucursal.value = ''
    searchOperador.value = ''
    errors.value = {}
}

// Cargar datos cuando se abre el modal
const cargarDatos = async () => {
    await nextTick()
    
    if (props.editando && props.asignacion) {
        form.value.IdSucursal = props.asignacion.IdSucursal
        form.value.IdOperador = props.asignacion.IdOperador
        
        const sucursalEncontrada = props.sucursales?.find(s => s.id == props.asignacion.IdSucursal)
        if (sucursalEncontrada) {
            searchSucursal.value = `${sucursalEncontrada.nombre} ${sucursalEncontrada.NumeroSucursal ? `(N° ${sucursalEncontrada.NumeroSucursal})` : ''}`
        } else {
            searchSucursal.value = props.asignacion.sucursal?.Nombre || ''
        }
        
        const operadorEncontrado = props.operadores?.find(o => o.id == props.asignacion.IdOperador)
        if (operadorEncontrado) {
            searchOperador.value = `${operadorEncontrado.ci} - ${operadorEncontrado.nombre} (${operadorEncontrado.iniciales})`
        } else {
            searchOperador.value = props.asignacion.operador?.identificador?.Nombre || ''
        }
    } else {
        resetForm()
    }
}

// Watch para cuando se abre el modal
watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        cargarDatos()
    } else {
        resetForm()
    }
})

// Cerrar modal
const closeModal = () => {
    emit('update:modelValue', false)
    resetForm()
}

// Guardar
const save = async () => {
    if (!form.value.IdSucursal) {
        errors.value = { IdSucursal: 'Seleccione una sucursal' }
        return
    }
    if (!form.value.IdOperador) {
        errors.value = { IdOperador: 'Seleccione un operador' }
        return
    }
    
    loading.value = true
    errors.value = {}
    
    try {
        let response
        if (props.editando) {
            response = await axios.put(`/gestion/operador-sucursal/${props.asignacion.IdSucursalDB}`, {
                IdSucursal: form.value.IdSucursal,
                IdOperador: form.value.IdOperador,
            })
        } else {
            response = await axios.post('/gestion/operador-sucursal', {
                IdSucursal: form.value.IdSucursal,
                IdOperador: form.value.IdOperador,
            })
        }
        
        if (response.data.success) {
            emit('saved')
            closeModal()
        } else {
            errors.value = { general: response.data.message }
        }
    } catch (error) {
        console.error('Error:', error)
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
        } else {
            errors.value = { general: error.response?.data?.message || 'Error al guardar' }
        }
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
        <div class="flex items-center justify-center min-h-screen p-3">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="closeModal"></div>
            
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-auto transform transition-all duration-300">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-2 border-b bg-primary-600 rounded-t-lg">
                    <h3 class="text-sm font-semibold text-white">
                        {{ editando ? 'Editar Asignación' : 'Nueva Asignación' }}
                    </h3>
                    <button @click="closeModal" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="p-4">
                    <form @submit.prevent="save" class="space-y-3">
                        <!-- Empresa (solo lectura) -->
                        <div class="bg-gray-50 rounded-md p-2">
                            <label class="block text-xs font-medium text-gray-500 mb-0.5">Empresa</label>
                            <p class="text-sm font-semibold text-gray-800">{{ empresaNombre || 'Sin empresa' }}</p>
                        </div>

                        <!-- Error general -->
                        <div v-if="errors.general" class="bg-red-50 border border-red-200 rounded-md p-2">
                            <p class="text-red-600 text-xs">{{ errors.general }}</p>
                        </div>

                        <!-- Sucursal -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Sucursal *</label>
                            <div class="relative">
                                <div class="relative">
                                    <input 
                                        type="text"
                                        :value="searchSucursal"
                                        @input="onSearchSucursalInput"
                                        @focus="showSucursalDropdown = true"
                                        @blur="cerrarDropdownSucursal"
                                        placeholder="Buscar sucursal..."
                                        class="w-full border rounded-md px-2 py-1.5 text-xs pr-6"
                                        :class="{ 'border-red-500': errors.IdSucursal }"
                                    >
                                    <button 
                                        v-if="searchSucursal"
                                        @click="limpiarSucursal"
                                        class="absolute right-1 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                        type="button"
                                    >
                                        <i class="fas fa-times text-[10px]"></i>
                                    </button>
                                </div>
                                
                                <!-- Dropdown de sucursales -->
                                <div 
                                    v-if="showSucursalDropdown && sucursalesFiltradas.length > 0"
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-40 overflow-y-auto"
                                >
                                    <div
                                        v-for="s in sucursalesFiltradas"
                                        :key="s.id"
                                        @click="seleccionarSucursal(s)"
                                        class="px-2 py-1.5 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-xs"
                                        :class="{ 'bg-primary-50': form.IdSucursal == s.id }"
                                    >
                                        {{ s.nombre }} {{ s.NumeroSucursal ? `(N° ${s.NumeroSucursal})` : '' }}
                                    </div>
                                </div>
                            </div>
                            <p v-if="errors.IdSucursal" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdSucursal }}</p>
                        </div>

                        <!-- Operador -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Operador *</label>
                            <div class="relative">
                                <div class="relative">
                                    <input 
                                        type="text"
                                        :value="searchOperador"
                                        :readonly="editando"
                                        @input="onSearchOperadorInput"
                                        @focus="!editando && (showOperadorDropdown = true)"
                                        @blur="cerrarDropdownOperador"
                                        placeholder="Buscar por CI, nombre o iniciales..."
                                        class="w-full border rounded-md px-2 py-1.5 text-xs pr-6"
                                        :class="{ 'border-red-500': errors.IdOperador, 'bg-gray-100': editando }"
                                    >
                                    <button 
                                        v-if="searchOperador && !editando"
                                        @click="limpiarOperador"
                                        class="absolute right-1 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                        type="button"
                                    >
                                        <i class="fas fa-times text-[10px]"></i>
                                    </button>
                                </div>
                                
                                <!-- Dropdown de operadores -->
                                <div 
                                    v-if="!editando && showOperadorDropdown && operadoresFiltrados.length > 0"
                                    class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-40 overflow-y-auto"
                                >
                                    <div
                                        v-for="op in operadoresFiltrados"
                                        :key="op.id"
                                        @click="seleccionarOperador(op)"
                                        class="px-2 py-1.5 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-xs"
                                        :class="{ 'bg-primary-50': form.IdOperador == op.id }"
                                    >
                                        <span class="font-mono text-gray-600">{{ op.ci }}</span>
                                        <span class="mx-1 text-gray-400">-</span>
                                        <span class="text-gray-800">{{ op.nombre }}</span>
                                        <span class="ml-1 text-gray-400">({{ op.iniciales }})</span>
                                    </div>
                                </div>
                            </div>
                            <p v-if="errors.IdOperador" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdOperador }}</p>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-2 pt-2 border-t">
                            <button type="button" @click="closeModal" class="px-3 py-1 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="loading" class="px-3 py-1 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1">
                                <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-save text-[10px]"></i>
                                {{ loading ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>