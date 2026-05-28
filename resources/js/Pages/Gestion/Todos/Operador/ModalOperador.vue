<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    operador: Object,
    tiposOperador: Array,
    identificadores: Array,
    editando: Boolean,
})

const emit = defineEmits(['update:modelValue', 'saved'])

// Formulario
const form = ref({
    IdIdentificador: '',
    Iniciales: '',
    Clave: '',
    NombreAcceso: '',
    DireccionDomicilio: '',
    TelefonoDomicilio: '',
    NumeroCelular: '',
    IdOperadorTipo: '',
    ActivoInactivo: true,
})

const loading = ref(false)
const errors = ref({})

// Buscador de identificadores
const busquedaIdentificador = ref('')
const identificadoresFiltrados = ref([])

// Modal de nuevo identificador
const modalIdentificadorVisible = ref(false)
const nuevoIdentificador = ref({ CI_NIT: '', Nombre: '' })
const guardandoIdentificador = ref(false)

// Resetear formulario
const resetForm = () => {
    form.value = {
        IdIdentificador: '',
        Iniciales: '',
        Clave: '',
        NombreAcceso: '',
        DireccionDomicilio: '',
        TelefonoDomicilio: '',
        NumeroCelular: '',
        IdOperadorTipo: '',
        ActivoInactivo: true,
    }
    errors.value = {}
    busquedaIdentificador.value = ''
}

// Cargar datos del operador a editar
watch(() => props.operador, (newVal) => {
    if (newVal && props.editando) {
        form.value = {
            IdIdentificador: newVal.IdIdentificador || '',
            Iniciales: newVal.Iniciales || '',
            Clave: '',
            NombreAcceso: newVal.NombreAcceso || '',
            DireccionDomicilio: newVal.DireccionDomicilio || '',
            TelefonoDomicilio: newVal.TelefonoDomicilio || '',
            NumeroCelular: newVal.NumeroCelular || '',
            IdOperadorTipo: newVal.IdOperadorTipo || '',
            ActivoInactivo: newVal.ActivoInactivo === 0,
        }
        
        if (newVal.IdIdentificador) {
            const ident = props.identificadores?.find(i => i.id === newVal.IdIdentificador)
            if (ident) {
                busquedaIdentificador.value = `${ident.ci} - ${ident.nombre}`
            }
        }
    } else if (!props.editando) {
        resetForm()
    }
}, { immediate: true })

// Filtrar identificadores
watch(busquedaIdentificador, (val) => {
    if (!val || !props.identificadores) {
        identificadoresFiltrados.value = []
        return
    }
    const termino = val.toLowerCase()
    identificadoresFiltrados.value = props.identificadores.filter(i => 
        i.ci?.toString().includes(termino) || 
        i.nombre?.toLowerCase().includes(termino)
    )
})

// Seleccionar identificador
const seleccionarIdentificador = (ident) => {
    form.value.IdIdentificador = ident.id
    busquedaIdentificador.value = `${ident.ci} - ${ident.nombre}`
    identificadoresFiltrados.value = []
}

// Cerrar modal
const closeModal = () => {
    emit('update:modelValue', false)
    resetForm()
}

// Abrir modal de nuevo identificador
const abrirModalIdentificador = () => {
    nuevoIdentificador.value = { CI_NIT: '', Nombre: '' }
    modalIdentificadorVisible.value = true
}

// Guardar nuevo identificador
const guardarNuevoIdentificador = async () => {
    if (!nuevoIdentificador.value.CI_NIT) {
        alert('Ingrese el CI/NIT')
        return
    }
    if (!nuevoIdentificador.value.Nombre) {
        alert('Ingrese el nombre')
        return
    }
    
    guardandoIdentificador.value = true
    try {
        const response = await axios.post('/gestion/todos/identificador', {
            CI_NIT: nuevoIdentificador.value.CI_NIT,
            Nombre: nuevoIdentificador.value.Nombre.toUpperCase(),
        })
        
        if (response.data.success) {
            modalIdentificadorVisible.value = false
            // Recargar identificadores
            const newIdent = {
                id: response.data.identificador.IdIdentificador,
                ci: response.data.identificador.CI_NIT,
                nombre: response.data.identificador.Nombre
            }
            props.identificadores.push(newIdent)
            seleccionarIdentificador(newIdent)
        }
    } catch (error) {
        console.error('Error:', error)
        alert(error.response?.data?.message || 'Error al guardar')
    } finally {
        guardandoIdentificador.value = false
    }
}

// Guardar operador
const save = async () => {
    loading.value = true
    errors.value = {}
    
    const datos = {
        IdIdentificador: form.value.IdIdentificador,
        Iniciales: form.value.Iniciales.toUpperCase(),
        NombreAcceso: form.value.NombreAcceso,
        DireccionDomicilio: form.value.DireccionDomicilio,
        TelefonoDomicilio: form.value.TelefonoDomicilio,
        NumeroCelular: form.value.NumeroCelular,
        IdOperadorTipo: form.value.IdOperadorTipo,
        ActivoInactivo: form.value.ActivoInactivo,
    }
    
    if (form.value.Clave) {
        datos.Clave = form.value.Clave
    }
    
    try {
        let response
        if (props.editando) {
            response = await axios.put(`/gestion/operadores/${props.operador.IdOperador}`, datos)
        } else {
            response = await axios.post('/gestion/operadores', datos)
        }
        
        if (response.data.success) {
            emit('saved')
            closeModal()
        } else {
            errors.value = response.data.errors || { general: response.data.message }
        }
    } catch (error) {
        console.error('Error:', error)
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
        } else if (error.response?.data?.message) {
            errors.value = { general: error.response.data.message }
        } else {
            errors.value = { general: 'Error al guardar el operador' }
        }
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <!-- Modal overlay -->
    <div v-if="modelValue" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="closeModal"></div>
            
            <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-auto transform transition-all duration-300 scale-100">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-3 border-b bg-primary-600 rounded-t-lg">
                    <h3 class="text-sm font-semibold text-white">
                        {{ editando ? 'Editar Operador' : 'Nuevo Operador' }}
                    </h3>
                    <button @click="closeModal" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5">
                    <form @submit.prevent="save" class="space-y-3">
                        <!-- Error general -->
                        <div v-if="errors.general" class="bg-red-50 border border-red-200 rounded-md p-2 mb-3">
                            <p class="text-red-600 text-xs">{{ errors.general }}</p>
                        </div>

                        <!-- Identificador -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Persona (CI/NIT) *</label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <input 
                                        type="text" 
                                        v-model="busquedaIdentificador" 
                                        class="w-full border rounded-md px-2 py-1.5 text-xs" 
                                        :class="{ 'border-red-500': errors.IdIdentificador }" 
                                        placeholder="Buscar por CI/NIT o nombre..." 
                                        @focus="busquedaIdentificador = ''"
                                    >
                                    <div v-if="busquedaIdentificador && identificadoresFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-48 overflow-y-auto">
                                        <div v-for="item in identificadoresFiltrados" :key="item.id" @click="seleccionarIdentificador(item)" class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-xs">
                                            <span class="font-mono">{{ item.ci }}</span> - {{ item.nombre }}
                                        </div>
                                    </div>
                                </div>
                                <button type="button" @click="abrirModalIdentificador" class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs hover:bg-primary-700 flex items-center gap-1">
                                    <i class="fas fa-plus text-[10px]"></i> Nuevo
                                </button>
                            </div>
                            <p v-if="errors.IdIdentificador" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdIdentificador }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Iniciales -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Iniciales *</label>
                                <input type="text" v-model="form.Iniciales" class="w-full border rounded-md px-2 py-1.5 text-xs uppercase" :class="{ 'border-red-500': errors.Iniciales }" placeholder="Ej: JPG" maxlength="5">
                                <p v-if="errors.Iniciales" class="text-[10px] text-red-500 mt-0.5">{{ errors.Iniciales }}</p>
                            </div>

                            <!-- Nombre de Acceso -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Nombre de Acceso *</label>
                                <input type="text" v-model="form.NombreAcceso" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.NombreAcceso }" placeholder="Usuario para login">
                                <p v-if="errors.NombreAcceso" class="text-[10px] text-red-500 mt-0.5">{{ errors.NombreAcceso }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Contraseña -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">
                                    Contraseña {{ editando ? '(opcional)' : '*' }}
                                </label>
                                <input type="password" v-model="form.Clave" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.Clave }" placeholder="••••••••">
                                <p v-if="errors.Clave" class="text-[10px] text-red-500 mt-0.5">{{ errors.Clave }}</p>
                            </div>

                            <!-- Tipo de Operador -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Tipo de Operador *</label>
                                <select v-model="form.IdOperadorTipo" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.IdOperadorTipo }">
                                    <option value="">Seleccione un tipo</option>
                                    <option v-for="t in tiposOperador" :key="t.IdOperadorTipo" :value="t.IdOperadorTipo">{{ t.Detalle }}</option>
                                </select>
                                <p v-if="errors.IdOperadorTipo" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdOperadorTipo }}</p>
                            </div>
                        </div>

                        <!-- Dirección -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Dirección Domicilio</label>
                            <input type="text" v-model="form.DireccionDomicilio" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.DireccionDomicilio }" placeholder="Dirección completa">
                            <p v-if="errors.DireccionDomicilio" class="text-[10px] text-red-500 mt-0.5">{{ errors.DireccionDomicilio }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Teléfono Domicilio -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Teléfono Domicilio</label>
                                <input type="text" v-model="form.TelefonoDomicilio" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.TelefonoDomicilio }" placeholder="Teléfono fijo">
                                <p v-if="errors.TelefonoDomicilio" class="text-[10px] text-red-500 mt-0.5">{{ errors.TelefonoDomicilio }}</p>
                            </div>

                            <!-- Número Celular -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Número Celular</label>
                                <input type="text" v-model="form.NumeroCelular" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.NumeroCelular }" placeholder="Celular / WhatsApp">
                                <p v-if="errors.NumeroCelular" class="text-[10px] text-red-500 mt-0.5">{{ errors.NumeroCelular }}</p>
                            </div>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Estado</label>
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-1">
                                    <input type="radio" v-model="form.ActivoInactivo" :value="true" class="w-3 h-3"> Activo
                                </label>
                                <label class="flex items-center gap-1">
                                    <input type="radio" v-model="form.ActivoInactivo" :value="false" class="w-3 h-3"> Inactivo
                                </label>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-0.5">* Los operadores inactivos no podrán acceder al sistema</p>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-2 pt-3 border-t mt-3">
                            <button type="button" @click="closeModal" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="loading" class="px-4 py-1.5 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1">
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

    <!-- Modal Nuevo Identificador -->
    <div v-if="modalIdentificadorVisible" class="fixed inset-0 z-50 overflow-y-auto" @click.self="modalIdentificadorVisible = false">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="bg-primary-600 rounded-t-lg px-4 py-3">
                    <h3 class="text-white font-semibold text-sm">Nuevo Identificador</h3>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">CI / NIT *</label>
                        <input type="number" v-model.number="nuevoIdentificador.CI_NIT" class="w-full border rounded-md px-2 py-1.5 text-xs" placeholder="12345678">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Nombre *</label>
                        <input type="text" v-model="nuevoIdentificador.Nombre" @input="nuevoIdentificador.Nombre = nuevoIdentificador.Nombre.toUpperCase()" class="w-full border rounded-md px-2 py-1.5 text-xs uppercase" placeholder="NOMBRE COMPLETO">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button @click="modalIdentificadorVisible = false" class="px-3 py-1.5 border rounded-md text-xs text-gray-700 hover:bg-gray-100">Cancelar</button>
                        <button @click="guardarNuevoIdentificador" :disabled="guardandoIdentificador" class="px-3 py-1.5 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 flex items-center gap-1">
                            <i v-if="guardandoIdentificador" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>