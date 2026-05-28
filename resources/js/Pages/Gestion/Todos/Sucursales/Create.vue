<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, inject } from 'vue'
import { useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    sucursal: Object,
    plazas: Array,
    categorias: Array,
    editando: Boolean,
})

const form = useForm({
    IdPlaza: props.sucursal?.IdPlaza || '',
    Nombre: props.sucursal?.Nombre || '',
    Direccion: props.sucursal?.Direccion || '',
    Telefono: props.sucursal?.Telefono || '',
    Celular: props.sucursal?.Celular || '',
    NumeroSucursal: props.sucursal?.NumeroSucursal || '',
    Orden: props.sucursal?.Orden || 0,
    Categoria: props.sucursal?.Categoria || '',
    ActivoInactivo: props.sucursal?.ActivoInactivo === 0 ? true : (props.sucursal?.ActivoInactivo === 1 ? false : true),
})

const submitForm = () => {
    // Convertir ActivoInactivo a número (0=activo, 1=inactivo)
    const datos = {
        ...form.data(),
        ActivoInactivo: form.ActivoInactivo ? 0 : 1
    }
    
    if (props.editando) {
        router.put(`/gestion/sucursales/${props.sucursal.IdClienteSucursal}`, datos, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Sucursal actualizada correctamente')
                router.get('/gestion/sucursales')
            }
        })
    } else {
        router.post('/gestion/sucursales', datos, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Sucursal creada correctamente')
                router.get('/gestion/sucursales')
            }
        })
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-3xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-store text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">{{ editando ? 'Editar Sucursal' : 'Nueva Sucursal' }}</h1>
                            <p class="text-[10px] text-gray-500">{{ editando ? 'Modifique los datos de la sucursal' : 'Complete los datos de la nueva sucursal' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <form @submit.prevent="submitForm" class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Plaza -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Plaza *</label>
                                <select v-model="form.IdPlaza" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': form.errors.IdPlaza }">
                                    <option value="">Seleccione una plaza</option>
                                    <option v-for="p in plazas" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                                </select>
                                <p v-if="form.errors.IdPlaza" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.IdPlaza }}</p>
                            </div>

                            <!-- Número Sucursal -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">N° Sucursal *</label>
                                <input type="number" v-model.number="form.NumeroSucursal" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': form.errors.NumeroSucursal }" placeholder="Ej: 1, 2, 3...">
                                <p v-if="form.errors.NumeroSucursal" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.NumeroSucursal }}</p>
                            </div>
                        </div>

                        <!-- Nombre -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Nombre *</label>
                            <input type="text" v-model="form.Nombre" class="w-full border rounded-md px-2 py-1.5 text-xs uppercase" :class="{ 'border-red-500': form.errors.Nombre }" placeholder="NOMBRE DE LA SUCURSAL">
                            <p v-if="form.errors.Nombre" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Nombre }}</p>
                        </div>

                        <!-- Dirección -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Dirección *</label>
                            <input type="text" v-model="form.Direccion" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': form.errors.Direccion }" placeholder="Dirección completa">
                            <p v-if="form.errors.Direccion" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Direccion }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Teléfono -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Teléfono *</label>
                                <input type="text" v-model="form.Telefono" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': form.errors.Telefono }" placeholder="Teléfono fijo">
                                <p v-if="form.errors.Telefono" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Telefono }}</p>
                            </div>

                            <!-- Celular -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Celular *</label>
                                <input type="text" v-model="form.Celular" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': form.errors.Celular }" placeholder="Celular / WhatsApp">
                                <p v-if="form.errors.Celular" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Celular }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Orden -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Orden *</label>
                                <input type="number" v-model.number="form.Orden" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': form.errors.Orden }" placeholder="0, 1, 2...">
                                <p v-if="form.errors.Orden" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Orden }}</p>
                            </div>

                            <!-- Categoría -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Categoría *</label>
                                <select v-model="form.Categoria" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': form.errors.Categoria }">
                                    <option value="">Seleccione una categoría</option>
                                    <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                                <p v-if="form.errors.Categoria" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Categoria }}</p>
                            </div>
                        </div>

                        <!-- Activo/Inactivo -->
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
                            <p class="text-[10px] text-gray-400 mt-0.5">* Las sucursales inactivas no aparecerán en los listados de ventas</p>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-2 pt-3 border-t">
                            <button type="button" @click="router.get('/gestion/sucursales')" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing" class="px-4 py-1.5 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1">
                                <i v-if="form.processing" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-save text-[10px]"></i>
                                {{ form.processing ? 'Guardando...' : 'Guardar Sucursal' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>