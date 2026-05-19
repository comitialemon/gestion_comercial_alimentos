<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    operador: Object,
    tiposOperador: Array,
    identificadores: Array,
    editando: Boolean,
})

// Formulario
const form = useForm({
    IdIdentificador: props.operador?.IdIdentificador || '',
    Iniciales: props.operador?.Iniciales || '',
    Clave: '',
    NombreAcceso: props.operador?.NombreAcceso || '',
    DireccionDomicilio: props.operador?.DireccionDomicilio || '',
    TelefonoDomicilio: props.operador?.TelefonoDomicilio || '',
    NumeroCelular: props.operador?.NumeroCelular || '',
    IdOperadorTipo: props.operador?.IdOperadorTipo || '',
    ActivoInactivo: props.operador?.ActivoInactivo === 0 ? true : false,
})

// Mostrar campo de contraseña solo en creación
const mostrarClave = ref(!props.editando)

// Buscador de identificadores
const busquedaIdentificador = ref('')
const identificadoresFiltrados = computed(() => {
    if (!busquedaIdentificador.value) return props.identificadores
    const termino = busquedaIdentificador.value.toLowerCase()
    return props.identificadores.filter(i => 
        i.ci?.toString().includes(termino) || 
        i.nombre?.toLowerCase().includes(termino)
    )
})

// Modal de nuevo identificador
const modalIdentificadorVisible = ref(false)
const nuevoIdentificador = ref({ CI_NIT: '', Nombre: '' })
const guardandoIdentificador = ref(false)

const abrirModalIdentificador = () => {
    nuevoIdentificador.value = { CI_NIT: '', Nombre: '' }
    modalIdentificadorVisible.value = true
}

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
            // Recargar la página para obtener los nuevos identificadores
            window.location.reload()
        }
    } catch (error) {
        console.error('Error:', error)
        alert(error.response?.data?.message || 'Error al guardar')
    } finally {
        guardandoIdentificador.value = false
    }
}

const submitForm = () => {
    if (props.editando) {
        form.put(`/gestion/operadores/${props.operador.IdOperador}`)
    } else {
        form.post('/gestion/operadores')
    }
}

onMounted(() => {
    if (props.operador?.IdIdentificador) {
        const ident = props.identificadores.find(i => i.id === props.operador.IdIdentificador)
        if (ident) {
            busquedaIdentificador.value = `${ident.ci} - ${ident.nombre}`
        }
    }
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user-plus text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">{{ editando ? 'Editar Operador' : 'Nuevo Operador' }}</h1>
                            <p class="text-sm text-gray-500">{{ editando ? 'Modifique los datos del operador' : 'Complete los datos del nuevo operador' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <form @submit.prevent="submitForm" class="space-y-5">
                        <!-- Identificador -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Persona (CI/NIT) *</label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <input type="text" v-model="busquedaIdentificador" class="w-full border rounded-lg px-3 py-2" :class="{ 'border-red-500': form.errors.IdIdentificador }" placeholder="Buscar por CI/NIT o nombre..." @focus="busquedaIdentificador = ''">
                                    <div v-if="busquedaIdentificador && identificadoresFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                        <div v-for="item in identificadoresFiltrados" :key="item.id" @click="form.IdIdentificador = item.id; busquedaIdentificador = `${item.ci} - ${item.nombre}`" class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0">
                                            <span class="font-mono text-sm">{{ item.ci }}</span> - {{ item.nombre }}
                                        </div>
                                    </div>
                                </div>
                                <button type="button" @click="abrirModalIdentificador" class="px-4 py-2 bg-guindo-600 text-white rounded-lg hover:bg-guindo-700 flex items-center gap-2">
                                    <i class="fas fa-plus"></i> Nuevo
                                </button>
                            </div>
                            <p v-if="form.errors.IdIdentificador" class="text-xs text-red-500 mt-1">{{ form.errors.IdIdentificador }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Iniciales -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Iniciales *</label>
                                <input type="text" v-model="form.Iniciales" class="w-full border rounded-lg px-3 py-2 uppercase" :class="{ 'border-red-500': form.errors.Iniciales }" placeholder="Ej: JPG, ABC" maxlength="5">
                                <p v-if="form.errors.Iniciales" class="text-xs text-red-500 mt-1">{{ form.errors.Iniciales }}</p>
                            </div>

                            <!-- Nombre de Acceso -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Acceso *</label>
                                <input type="text" v-model="form.NombreAcceso" class="w-full border rounded-lg px-3 py-2" :class="{ 'border-red-500': form.errors.NombreAcceso }" placeholder="Usuario para login">
                                <p v-if="form.errors.NombreAcceso" class="text-xs text-red-500 mt-1">{{ form.errors.NombreAcceso }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Contraseña -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Contraseña {{ editando ? '(dejar en blanco para no cambiar)' : '*' }}
                                </label>
                                <input type="password" v-model="form.Clave" class="w-full border rounded-lg px-3 py-2" :class="{ 'border-red-500': form.errors.Clave }" placeholder="••••••••">
                                <p v-if="form.errors.Clave" class="text-xs text-red-500 mt-1">{{ form.errors.Clave }}</p>
                            </div>

                            <!-- Tipo de Operador -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Operador *</label>
                                <select v-model="form.IdOperadorTipo" class="w-full border rounded-lg px-3 py-2" :class="{ 'border-red-500': form.errors.IdOperadorTipo }">
                                    <option value="">Seleccione un tipo</option>
                                    <option v-for="t in tiposOperador" :key="t.IdOperadorTipo" :value="t.IdOperadorTipo">{{ t.Detalle }}</option>
                                </select>
                                <p v-if="form.errors.IdOperadorTipo" class="text-xs text-red-500 mt-1">{{ form.errors.IdOperadorTipo }}</p>
                            </div>
                        </div>

                        <!-- Dirección -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Dirección Domicilio</label>
                            <input type="text" v-model="form.DireccionDomicilio" class="w-full border rounded-lg px-3 py-2" :class="{ 'border-red-500': form.errors.DireccionDomicilio }" placeholder="Dirección completa">
                            <p v-if="form.errors.DireccionDomicilio" class="text-xs text-red-500 mt-1">{{ form.errors.DireccionDomicilio }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Teléfono Domicilio -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono Domicilio</label>
                                <input type="text" v-model="form.TelefonoDomicilio" class="w-full border rounded-lg px-3 py-2" :class="{ 'border-red-500': form.errors.TelefonoDomicilio }" placeholder="Teléfono fijo">
                                <p v-if="form.errors.TelefonoDomicilio" class="text-xs text-red-500 mt-1">{{ form.errors.TelefonoDomicilio }}</p>
                            </div>

                            <!-- Número Celular -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número Celular</label>
                                <input type="text" v-model="form.NumeroCelular" class="w-full border rounded-lg px-3 py-2" :class="{ 'border-red-500': form.errors.NumeroCelular }" placeholder="Celular / WhatsApp">
                                <p v-if="form.errors.NumeroCelular" class="text-xs text-red-500 mt-1">{{ form.errors.NumeroCelular }}</p>
                            </div>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="radio" v-model="form.ActivoInactivo" :value="true" class="w-4 h-4 text-guindo-600"> Activo
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" v-model="form.ActivoInactivo" :value="false" class="w-4 h-4 text-guindo-600"> Inactivo
                                </label>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">* Los operadores inactivos no podrán acceder al sistema</p>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button type="button" @click="router.get('/gestion/operadores')" class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-2">
                                <i v-if="form.processing" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-save"></i>
                                {{ form.processing ? 'Guardando...' : 'Guardar Operador' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Nuevo Identificador -->
        <div v-if="modalIdentificadorVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl max-w-md w-full overflow-hidden shadow-xl">
                <div class="bg-gradient-to-r from-guindo-600 to-guindo-700 p-4">
                    <h3 class="text-white font-bold text-lg">Nuevo Identificador</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CI / NIT *</label>
                        <input type="number" v-model.number="nuevoIdentificador.CI_NIT" class="w-full border rounded-lg px-3 py-2" placeholder="12345678">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                        <input type="text" v-model="nuevoIdentificador.Nombre" @input="nuevoIdentificador.Nombre = nuevoIdentificador.Nombre.toUpperCase()" class="w-full border rounded-lg px-3 py-2 uppercase" placeholder="NOMBRE COMPLETO">
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button @click="modalIdentificadorVisible = false" class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-100">Cancelar</button>
                        <button @click="guardarNuevoIdentificador" :disabled="guardandoIdentificador" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 flex items-center gap-2">
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