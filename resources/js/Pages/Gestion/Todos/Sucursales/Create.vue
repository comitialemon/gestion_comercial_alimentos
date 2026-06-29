<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, inject, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    sucursal: Object,
    plazas: Array,
    editando: Boolean,
})

// Estado para el formulario - CORREGIDO
const form = useForm({
    IdPlaza: props.sucursal?.IdPlaza || '',
    Nombre: props.sucursal?.Nombre || '',
    Direccion: props.sucursal?.Direccion || '',
    Celular: props.sucursal?.Celular || '',
    NumeroSucursal: props.sucursal?.NumeroSucursal || '',
    Orden: props.sucursal?.Orden || 0,
    ActivoInactivo: props.sucursal?.ActivoInactivo ?? 0, // 0 = Activo, 1 = Inactivo
})

// Computed para el estado
const estadoTexto = computed(() => {
    return form.ActivoInactivo === 0 ? 'Activo' : 'Inactivo'
})

const estadoColor = computed(() => {
    return form.ActivoInactivo === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
})

// Alternar estado - CORREGIDO
const toggleEstado = () => {
    form.ActivoInactivo = form.ActivoInactivo === 0 ? 1 : 0
}

const submitForm = () => {
    // Preparar datos - CORREGIDO
    const datos = {
        IdPlaza: parseInt(form.IdPlaza) || null,
        Nombre: form.Nombre.toUpperCase(),
        Direccion: form.Direccion,
        Celular: form.Celular,
        NumeroSucursal: parseInt(form.NumeroSucursal) || 0,
        Orden: parseInt(form.Orden) || 0,
        ActivoInactivo: form.ActivoInactivo, // 0 = Activo, 1 = Inactivo
        ActivaInactivaR: form.ActivoInactivo, // 0 = Activa, 1 = Inactiva
    }
    
    console.log('📤 Enviando datos:', datos)
    
    if (props.editando) {
        router.put(`/gestion/sucursales/${props.sucursal.IdClienteSucursal}`, datos, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Sucursal actualizada correctamente')
                router.get('/gestion/sucursales')
            },
            onError: (errors) => {
                console.error('❌ Errores de validación:', errors)
                toast?.error('Error', 'Verifique los datos ingresados')
            }
        })
    } else {
        router.post('/gestion/sucursales', datos, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Sucursal creada correctamente')
                router.get('/gestion/sucursales')
            },
            onError: (errors) => {
                console.error('❌ Errores de validación:', errors)
                toast?.error('Error', 'Verifique los datos ingresados')
            }
        })
    }
}
</script>

<template>
    <AppLayout>
        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Tarjeta principal -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <!-- Header con gradiente -->
                    <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="bg-white/20 rounded-lg p-2">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h1 class="text-xl font-bold text-white">
                                        {{ editando ? 'Editar Sucursal' : 'Nueva Sucursal' }}
                                    </h1>
                                    <p class="text-emerald-100 text-sm">
                                        {{ editando ? 'Modifique los datos de la sucursal' : 'Complete la información de la nueva sucursal' }}
                                    </p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-white text-xs font-medium">
                                {{ editando ? 'Edición' : 'Creación' }}
                            </span>
                        </div>
                    </div>

                    <!-- Formulario -->
                    <form @submit.prevent="submitForm" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Columna izquierda -->
                            <div class="space-y-4">
                                <!-- Plaza -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Plaza *
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <select 
                                        v-model="form.IdPlaza" 
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 text-sm transition duration-150"
                                        :class="{ 'border-red-500 focus:border-red-500': form.errors.IdPlaza }"
                                    >
                                        <option value="">Seleccione una plaza</option>
                                        <option v-for="p in plazas" :key="p.id" :value="p.id">
                                            {{ p.nombre }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.IdPlaza" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.IdPlaza }}
                                    </p>
                                </div>

                                <!-- Nombre -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre de Sucursal *
                                    </label>
                                    <input 
                                        type="text" 
                                        v-model="form.Nombre" 
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 text-sm uppercase transition duration-150"
                                        :class="{ 'border-red-500 focus:border-red-500': form.errors.Nombre }"
                                        placeholder="Ej: SUCURSAL CENTRO"
                                    >
                                    <p v-if="form.errors.Nombre" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.Nombre }}
                                    </p>
                                </div>

                                <!-- Dirección -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Dirección *
                                    </label>
                                    <input 
                                        type="text" 
                                        v-model="form.Direccion" 
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 text-sm transition duration-150"
                                        :class="{ 'border-red-500 focus:border-red-500': form.errors.Direccion }"
                                        placeholder="Ej: Av. Principal #123"
                                    >
                                    <p v-if="form.errors.Direccion" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.Direccion }}
                                    </p>
                                </div>
                            </div>

                            <!-- Columna derecha -->
                            <div class="space-y-4">
                                <!-- Celular -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Celular / WhatsApp *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                            </svg>
                                        </div>
                                        <input 
                                            type="text" 
                                            v-model="form.Celular" 
                                            class="w-full pl-10 rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 text-sm transition duration-150"
                                            :class="{ 'border-red-500 focus:border-red-500': form.errors.Celular }"
                                            placeholder="Ej: 9XXXXXXXX"
                                        >
                                    </div>
                                    <p v-if="form.errors.Celular" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.Celular }}
                                    </p>
                                </div>

                                <!-- Número Sucursal -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Número de Sucursal
                                    </label>
                                    <input 
                                        type="number" 
                                        v-model.number="form.NumeroSucursal" 
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 text-sm transition duration-150"
                                        :class="{ 'border-red-500 focus:border-red-500': form.errors.NumeroSucursal }"
                                        placeholder="Ej: 1, 2, 3..."
                                    >
                                    <p v-if="form.errors.NumeroSucursal" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.NumeroSucursal }}
                                    </p>
                                </div>

                                <!-- Orden -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Orden de Prioridad
                                    </label>
                                    <input 
                                        type="number" 
                                        v-model.number="form.Orden" 
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 text-sm transition duration-150"
                                        :class="{ 'border-red-500 focus:border-red-500': form.errors.Orden }"
                                        placeholder="0, 1, 2..."
                                    >
                                    <p v-if="form.errors.Orden" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.Orden }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Estado Activo/Inactivo (Rediseñado) -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Estado de la Sucursal</label>
                                    <p class="text-sm text-gray-500">0=Activo / 1=Inactivo</p>
                                </div>
                                <button 
                                    type="button"
                                    @click="toggleEstado"
                                    class="relative inline-flex items-center h-10 rounded-full w-24 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                                    :class="form.ActivoInactivo === 0 ? 'bg-emerald-600' : 'bg-gray-300'"
                                >
                                    <span class="sr-only">Toggle estado</span>
                                    <span 
                                        class="inline-block h-8 w-8 transform rounded-full bg-white shadow-lg transition duration-200 flex items-center justify-center"
                                        :class="form.ActivoInactivo === 0 ? 'translate-x-12' : 'translate-x-1'"
                                    >
                                        <svg v-if="form.ActivoInactivo === 0" class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </span>
                                    <span class="absolute inset-0 flex items-center justify-center px-4 text-white text-sm font-medium">
                                        {{ form.ActivoInactivo === 0 ? 'ACTIVO' : 'INACTIVO' }}
                                    </span>
                                </button>
                            </div>
                            <p class="mt-2 text-sm text-gray-500">
                                <span class="inline-flex items-center">
                                    <svg class="h-4 w-4 text-gray-400 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Las sucursales inactivas no aparecerán en los listados de ventas
                                </span>
                            </p>
                        </div>

                        <!-- Botones de acción -->
                        <div class="mt-6 pt-6 border-t border-gray-200 flex flex-col sm:flex-row justify-end gap-3">
                            <button 
                                type="button" 
                                @click="router.get('/gestion/sucursales')" 
                                class="w-full sm:w-auto px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition duration-150 flex items-center justify-center"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Cancelar
                            </button>
                            <button 
                                type="submit" 
                                :disabled="form.processing" 
                                class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-emerald-700 text-white rounded-lg text-sm font-medium hover:from-emerald-700 hover:to-emerald-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition duration-150 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg v-if="form.processing" class="animate-spin h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                                </svg>
                                {{ form.processing ? 'Guardando...' : (editando ? 'Actualizar Sucursal' : 'Crear Sucursal') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
input:focus, select:focus {
    outline: none;
}

.relative {
    transition: background-color 0.2s;
}

.translate-x-1, .translate-x-12 {
    transition-property: transform;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 200ms;
}

input::placeholder {
    color: #9CA3AF;
    font-size: 0.875rem;
}

select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
</style>