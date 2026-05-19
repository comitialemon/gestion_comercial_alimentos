<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    sucursal: Object,
    plazas: Array,
    categorias: Array,
    editando: Boolean,
})

const emit = defineEmits(['update:modelValue', 'saved'])

const form = ref({
    IdPlaza: '',
    Nombre: '',
    Direccion: '',
    Telefono: '',
    Celular: '',
    NumeroSucursal: null,  // 🔥 NULL por defecto
    Orden: null,            // 🔥 NULL por defecto
    Categoria: '',
    ActivoInactivo: true,
})

const loading = ref(false)
const errors = ref({})

const resetForm = () => {
    form.value = {
        IdPlaza: '',
        Nombre: '',
        Direccion: '',
        Telefono: '',
        Celular: '',
        NumeroSucursal: null,
        Orden: null,
        Categoria: '',
        ActivoInactivo: true,
    }
    errors.value = {}
}

watch(() => props.sucursal, (newVal) => {
    if (newVal && props.editando) {
        form.value = {
            IdPlaza: newVal.IdPlaza || '',
            Nombre: newVal.Nombre || '',
            Direccion: newVal.Direccion || '',
            Telefono: newVal.Telefono || '',
            Celular: newVal.Celular || '',
            NumeroSucursal: newVal.NumeroSucursal || null,
            Orden: newVal.Orden ?? null,
            Categoria: newVal.Categoria || '',
            ActivoInactivo: newVal.ActivoInactivo === 0,
        }
    } else if (!props.editando) {
        resetForm()
    }
}, { immediate: true })

const closeModal = () => {
    emit('update:modelValue', false)
    resetForm()
}

const save = async () => {
    loading.value = true
    errors.value = {}
    
    const datos = {
        IdPlaza: form.value.IdPlaza,
        Nombre: form.value.Nombre,
        Direccion: form.value.Direccion,
        Telefono: form.value.Telefono,
        Celular: form.value.Celular,
        NumeroSucursal: form.value.NumeroSucursal ?? 0,
        Orden: form.value.Orden ?? 0,
        Categoria: form.value.Categoria,
        ActivoInactivo: form.value.ActivoInactivo ? 0 : 1,
    }
    
    try {
        let response
        if (props.editando) {
            response = await axios.put(`/gestion/sucursales/${props.sucursal.IdClienteSucursal}`, datos)
        } else {
            response = await axios.post('/gestion/sucursales', datos)
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
        } else {
            errors.value = { general: error.response?.data?.message || 'Error al guardar' }
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
                <div class="flex items-center justify-between px-5 py-3 border-b bg-guindo-600 rounded-t-lg">
                    <h3 class="text-sm font-semibold text-white">
                        {{ editando ? 'Editar Sucursal' : 'Nueva Sucursal' }}
                    </h3>
                    <button @click="closeModal" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-5">
                    <form @submit.prevent="save" class="space-y-3">
                        <div v-if="errors.general" class="bg-red-50 border border-red-200 rounded-md p-2 mb-3">
                            <p class="text-red-600 text-xs">{{ errors.general }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Plaza -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Plaza *</label>
                                <select v-model="form.IdPlaza" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.IdPlaza }">
                                    <option value="">Seleccione una plaza</option>
                                    <option v-for="p in plazas" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                                </select>
                                <p v-if="errors.IdPlaza" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdPlaza }}</p>
                            </div>

                            <!-- Número Sucursal (opcional) -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">N° Sucursal (opcional)</label>
                                <input type="number" v-model.number="form.NumeroSucursal" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.NumeroSucursal }" placeholder="Ej: 1, 2, 3...">
                                <p v-if="errors.NumeroSucursal" class="text-[10px] text-red-500 mt-0.5">{{ errors.NumeroSucursal }}</p>
                            </div>
                        </div>

                        <!-- Nombre -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Nombre *</label>
                            <input type="text" v-model="form.Nombre" class="w-full border rounded-md px-2 py-1.5 text-xs uppercase" :class="{ 'border-red-500': errors.Nombre }" placeholder="NOMBRE DE LA SUCURSAL">
                            <p v-if="errors.Nombre" class="text-[10px] text-red-500 mt-0.5">{{ errors.Nombre }}</p>
                        </div>

                        <!-- Dirección -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Dirección *</label>
                            <input type="text" v-model="form.Direccion" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.Direccion }" placeholder="Dirección completa">
                            <p v-if="errors.Direccion" class="text-[10px] text-red-500 mt-0.5">{{ errors.Direccion }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Teléfono *</label>
                                <input type="text" v-model="form.Telefono" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.Telefono }" placeholder="Teléfono fijo">
                                <p v-if="errors.Telefono" class="text-[10px] text-red-500 mt-0.5">{{ errors.Telefono }}</p>
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Celular *</label>
                                <input type="text" v-model="form.Celular" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.Celular }" placeholder="Celular / WhatsApp">
                                <p v-if="errors.Celular" class="text-[10px] text-red-500 mt-0.5">{{ errors.Celular }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Orden (opcional) -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Orden (opcional)</label>
                                <input type="number" v-model.number="form.Orden" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.Orden }" placeholder="0, 1, 2...">
                                <p v-if="errors.Orden" class="text-[10px] text-red-500 mt-0.5">{{ errors.Orden }}</p>
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Categoría *</label>
                                <select v-model="form.Categoria" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.Categoria }">
                                    <option value="">Seleccione una categoría</option>
                                    <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                                <p v-if="errors.Categoria" class="text-[10px] text-red-500 mt-0.5">{{ errors.Categoria }}</p>
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
</template>