<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    sucursal: {
        type: Object,
        default: null
    },
    plazas: {
        type: Array,
        default: () => []
    },
    categorias: {
        type: Array,
        default: () => ['MAYORISTA', 'GRANDE', 'MEDIANA', 'PEQUEÑA', 'MINI']
    },
    editando: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'saved'])

// Estado del formulario
const form = ref({
    IdPlaza: '',
    Nombre: '',
    Direccion: '',
    Telefono: '',
    Celular: '',
    NumeroSucursal: '',
    Orden: 0,
    Categoria: 'PEQUEÑA',
    ActivoInactivo: true,  // true = Activo, false = Inactivo
    ActivaInactivaR: true   // true = Activa, false = Inactiva
})

const loading = ref(false)
const errors = ref({})

// Cargar datos al editar
watch(() => props.sucursal, (newVal) => {
    if (newVal && props.editando) {
        form.value = {
            IdPlaza: newVal.IdPlaza || '',
            Nombre: newVal.Nombre || '',
            Direccion: newVal.Direccion || '',
            Telefono: newVal.Telefono || '',
            Celular: newVal.Celular || '',
            NumeroSucursal: newVal.NumeroSucursal || '',
            Orden: newVal.Orden || 0,
            Categoria: newVal.Categoria || 'PEQUEÑA',
            ActivoInactivo: newVal.ActivoInactivo === 0,  // 0 = Activo
            ActivaInactivaR: newVal.ActivaInactivaR === 0  // 0 = Activa
        }
    }
}, { immediate: true })

// Resetear formulario al cerrar
const resetForm = () => {
    form.value = {
        IdPlaza: '',
        Nombre: '',
        Direccion: '',
        Telefono: '',
        Celular: '',
        NumeroSucursal: '',
        Orden: 0,
        Categoria: 'PEQUEÑA',
        ActivoInactivo: true,
        ActivaInactivaR: true
    }
    errors.value = {}
}

const cerrarModal = () => {
    emit('update:modelValue', false)
    resetForm()
}

const guardar = async () => {
    loading.value = true
    errors.value = {}
    
    const data = {
        IdPlaza: form.value.IdPlaza,
        Nombre: form.value.Nombre,
        Direccion: form.value.Direccion,
        Telefono: form.value.Telefono,
        Celular: form.value.Celular,
        NumeroSucursal: form.value.NumeroSucursal || 0,
        Orden: form.value.Orden,
        Categoria: form.value.Categoria,
        ActivoInactivo: form.value.ActivoInactivo,
        ActivaInactivaR: form.value.ActivaInactivaR
    }
    
    try {
        let response
        if (props.editando && props.sucursal) {
            response = await axios.put(`/gestion/sucursales/${props.sucursal.IdClienteSucursal}`, data)
        } else {
            response = await axios.post('/gestion/sucursales', data)
        }
        
        if (response.data.success) {
            emit('saved')
            cerrarModal()
        } else {
            alert(response.data.message || 'Error al guardar')
        }
    } catch (error) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
        } else {
            alert(error.response?.data?.message || 'Error al guardar')
        }
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="flex justify-between items-center p-4 border-b sticky top-0 bg-white">
                    <h2 class="text-lg font-semibold text-gray-800">
                        {{ editando ? 'Editar Sucursal' : 'Nueva Sucursal' }}
                    </h2>
                    <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Formulario -->
                <div class="p-5 space-y-4">
                    <!-- Plaza -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Plaza <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.IdPlaza" class="w-full border rounded-lg px-3 py-2 text-sm">
                            <option value="">Seleccione una plaza</option>
                            <option v-for="plaza in plazas" :key="plaza.id" :value="plaza.id">
                                {{ plaza.nombre }}
                            </option>
                        </select>
                        <p v-if="errors.IdPlaza" class="text-xs text-red-500 mt-1">{{ errors.IdPlaza }}</p>
                    </div>

                    <!-- Nombre -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Nombre" class="w-full border rounded-lg px-3 py-2 text-sm" />
                        <p v-if="errors.Nombre" class="text-xs text-red-500 mt-1">{{ errors.Nombre }}</p>
                    </div>

                    <!-- Dirección -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Dirección <span class="text-red-500">*</span>
                        </label>
                        <input type="text" v-model="form.Direccion" class="w-full border rounded-lg px-3 py-2 text-sm" />
                        <p v-if="errors.Direccion" class="text-xs text-red-500 mt-1">{{ errors.Direccion }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Teléfono -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Teléfono <span class="text-red-500">*</span>
                            </label>
                            <input type="text" v-model="form.Telefono" class="w-full border rounded-lg px-3 py-2 text-sm" />
                            <p v-if="errors.Telefono" class="text-xs text-red-500 mt-1">{{ errors.Telefono }}</p>
                        </div>

                        <!-- Celular -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Celular <span class="text-red-500">*</span>
                            </label>
                            <input type="text" v-model="form.Celular" class="w-full border rounded-lg px-3 py-2 text-sm" />
                            <p v-if="errors.Celular" class="text-xs text-red-500 mt-1">{{ errors.Celular }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Número Sucursal -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Número Sucursal
                            </label>
                            <input type="number" v-model="form.NumeroSucursal" class="w-full border rounded-lg px-3 py-2 text-sm" />
                        </div>

                        <!-- Orden -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Orden
                            </label>
                            <input type="number" v-model="form.Orden" min="0" class="w-full border rounded-lg px-3 py-2 text-sm" />
                        </div>
                    </div>

                    <!-- Categoría -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Categoría <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.Categoria" class="w-full border rounded-lg px-3 py-2 text-sm">
                            <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                        <p v-if="errors.Categoria" class="text-xs text-red-500 mt-1">{{ errors.Categoria }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Activo/Inactivo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Estado
                            </label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.ActivoInactivo" :value="true" class="w-4 h-4" />
                                    <span class="text-sm">Activo</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.ActivoInactivo" :value="false" class="w-4 h-4" />
                                    <span class="text-sm">Inactivo</span>
                                </label>
                            </div>
                        </div>

                        <!-- ActivaInactivaR -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Activa/Inactiva R
                            </label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.ActivaInactivaR" :value="true" class="w-4 h-4" />
                                    <span class="text-sm">Activa</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.ActivaInactivaR" :value="false" class="w-4 h-4" />
                                    <span class="text-sm">Inactiva</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-3 p-4 border-t sticky bottom-0 bg-white">
                    <button @click="cerrarModal" class="px-4 py-2 border rounded-lg text-gray-700 text-sm hover:bg-gray-100">
                        Cancelar
                    </button>
                    <button @click="guardar" :disabled="loading" class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 flex items-center gap-2">
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ loading ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>