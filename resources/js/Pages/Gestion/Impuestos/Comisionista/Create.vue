<!-- resources/js/Pages/Gestion/Impuestos/Comisionista/Create.vue -->
<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    comisionista: {
        type: Object,
        default: () => ({})
    },
    empresas: {
        type: Array,
        default: () => []
    },
    identificadores: {
        type: Array,
        default: () => []
    },
    defaults: {
        type: Object,
        default: () => ({})
    },
    editando: {
        type: Boolean,
        default: false
    },
})

const form = ref({
    IdIdentificador: props.comisionista?.IdIdentificador || '',
    Comision: props.comisionista?.Comision || 0,
    IdCliente: props.comisionista?.IdCliente || props.defaults?.IdCliente || '',
})

const identificadoresList = ref([])
const buscandoIdentificador = ref(false)
const searchIdentificador = ref('')
const selectedIdentificadorNombre = ref('')
const selectedIdentificadorCi = ref('')
const mostrarResultados = ref(false)

// Si estamos editando, mostrar el nombre del identificador seleccionado
if (props.editando && props.comisionista?.IdIdentificador) {
    selectedIdentificadorNombre.value = props.comisionista?.identificador?.Nombre || ''
    selectedIdentificadorCi.value = props.comisionista?.identificador?.CI_NIT || ''
    if (selectedIdentificadorCi.value || selectedIdentificadorNombre.value) {
        searchIdentificador.value = `${selectedIdentificadorCi.value} - ${selectedIdentificadorNombre.value}`.trim()
    }
}

// Buscar identificadores
const buscarIdentificador = async () => {
    const termino = searchIdentificador.value?.trim()
    
    if (!termino || termino.length < 2 || form.value.IdIdentificador) {
        identificadoresList.value = []
        mostrarResultados.value = false
        return
    }
    
    buscandoIdentificador.value = true
    mostrarResultados.value = true
    
    try {
        const url = `/gestion/comisionista/buscar-identificador?q=${encodeURIComponent(termino)}`
        const response = await axios.get(url)
        identificadoresList.value = response.data || []
    } catch (err) {
        console.error('Error buscando identificador:', err)
        identificadoresList.value = []
    } finally {
        buscandoIdentificador.value = false
    }
}

// Seleccionar identificador
const seleccionarIdentificador = (id, ci, nombre) => {
    form.value.IdIdentificador = id
    selectedIdentificadorCi.value = ci
    selectedIdentificadorNombre.value = nombre
    searchIdentificador.value = `${ci} - ${nombre}`
    identificadoresList.value = []
    mostrarResultados.value = false
}

// Limpiar selección
const limpiarSeleccion = () => {
    if (props.editando) return
    
    form.value.IdIdentificador = ''
    selectedIdentificadorCi.value = ''
    selectedIdentificadorNombre.value = ''
    searchIdentificador.value = ''
    identificadoresList.value = []
    mostrarResultados.value = false
}

// Ocultar resultados
const ocultarResultados = () => {
    setTimeout(() => {
        if (!form.value.IdIdentificador) {
            mostrarResultados.value = false
        }
    }, 200)
}

// Debounce para búsqueda
let timeout
watch(searchIdentificador, (newVal) => {
    if (props.editando) return
    
    clearTimeout(timeout)
    
    if (!newVal || newVal.trim() === '') {
        form.value.IdIdentificador = ''
        selectedIdentificadorCi.value = ''
        selectedIdentificadorNombre.value = ''
        identificadoresList.value = []
        mostrarResultados.value = false
        return
    }
    
    if (form.value.IdIdentificador && newVal === `${selectedIdentificadorCi.value} - ${selectedIdentificadorNombre.value}`) {
        identificadoresList.value = []
        mostrarResultados.value = false
        return
    }
    
    form.value.IdIdentificador = ''
    timeout = setTimeout(() => {
        buscarIdentificador()
    }, 400)
})

const guardar = () => {
    if (!form.value.IdIdentificador) {
        alert('Debes seleccionar un identificador')
        return
    }
    
    if (props.editando) {
        router.put(`/gestion/comisionista/${props.comisionista.IdComisionista}`, form.value)
    } else {
        router.post('/gestion/comisionista', form.value)
    }
}

const volver = () => {
    router.get('/gestion/comisionista')
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-3" :class="editando ? 'bg-amber-100' : 'bg-emerald-100'">
                        <i class="fas" :class="editando ? 'fa-pencil-alt text-amber-600' : 'fa-plus text-emerald-600'"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">
                        {{ editando ? 'Editar Comisionista' : 'Nuevo Comisionista' }}
                    </h1>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6 space-y-5">
                        <!-- Empresa -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Empresa *</label>
                            <select v-model="form.IdCliente" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="editando">
                                <option value="">Seleccione una empresa</option>
                                <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre }}</option>
                            </select>
                            <p v-if="editando" class="text-xs text-gray-400 mt-1">No se puede cambiar la empresa en edición</p>
                        </div>

                        <!-- Identificador (Persona) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Persona / Identificador *</label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="searchIdentificador" 
                                    :disabled="editando"
                                    @focus="mostrarResultados = !!identificadoresList.length"
                                    @blur="ocultarResultados"
                                    placeholder="Escribe para buscar por CI o nombre..." 
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8"
                                    :class="{ 'border-emerald-500 bg-emerald-50': form.IdIdentificador }"
                                />
                                <button 
                                    v-if="searchIdentificador && !editando && !form.IdIdentificador"
                                    @click="limpiarSeleccion"
                                    class="absolute right-2 top-2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <!-- Lista de resultados -->
                            <div 
                                v-if="mostrarResultados && identificadoresList.length > 0 && !form.IdIdentificador && !editando" 
                                class="absolute z-10 mt-1 w-[calc(100%-3rem)] border rounded-lg max-h-48 overflow-y-auto bg-white shadow-lg"
                            >
                                <div 
                                    v-for="item in identificadoresList" 
                                    :key="item.id"
                                    @click="seleccionarIdentificador(item.id, item.ci, item.nombre)"
                                    class="p-2 hover:bg-gray-100 cursor-pointer text-sm border-b last:border-b-0"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded-full">{{ item.ci }}</span>
                                        <span class="text-gray-700">{{ item.nombre }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Indicador de búsqueda -->
                            <div v-if="buscandoIdentificador" class="text-xs text-gray-400 mt-1">
                                <i class="fas fa-spinner fa-spin mr-1"></i> Buscando...
                            </div>
                            
                            <!-- Mensaje sin resultados -->
                            <div v-if="mostrarResultados && !buscandoIdentificador && searchIdentificador && searchIdentificador.length >= 2 && identificadoresList.length === 0 && !form.IdIdentificador && !editando" class="text-xs text-gray-400 mt-1">
                                <i class="fas fa-search mr-1"></i> No se encontraron resultados
                            </div>
                            
                            <!-- Identificador seleccionado -->
                            <div v-if="form.IdIdentificador && !editando" class="text-xs text-emerald-600 mt-1 flex items-center gap-1">
                                <i class="fas fa-check-circle"></i>
                                Identificador seleccionado: <span class="font-medium">{{ selectedIdentificadorCi }} - {{ selectedIdentificadorNombre }}</span>
                            </div>
                            
                            <!-- En edición -->
                            <div v-if="editando && form.IdIdentificador" class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Identificador actual: <span class="font-medium">{{ selectedIdentificadorCi }} - {{ selectedIdentificadorNombre }}</span>
                                (No se puede cambiar)
                            </div>
                        </div>

                        <!-- Comisión -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Comisión (%) *</label>
                            <div class="relative">
                                <input 
                                    type="number" 
                                    v-model.number="form.Comision" 
                                    min="0" 
                                    max="100" 
                                    step="0.01"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-12"
                                />
                                <span class="absolute right-3 top-2 text-gray-400">%</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Porcentaje de comisión sobre las ventas (0-100)</p>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">
                        <button @click="volver" class="px-4 py-2 border rounded-lg text-gray-700 text-sm hover:bg-gray-100">
                            Cancelar
                        </button>
                        <button 
                            @click="guardar" 
                            :disabled="!form.IdCliente || !form.IdIdentificador" 
                            class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {{ editando ? 'Actualizar' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>