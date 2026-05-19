<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { inject } from 'vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    operador: Object,
    identificadores: Array,
})

// Formulario
const form = useForm({
    Iniciales: props.operador?.Iniciales || '',
    NombreAcceso: props.operador?.NombreAcceso || '',
    Clave: '',
    DireccionDomicilio: props.operador?.DireccionDomicilio || '',
    TelefonoDomicilio: props.operador?.TelefonoDomicilio || '',
    NumeroCelular: props.operador?.NumeroCelular || '',
})

const mostrarClave = ref(false)

const submitForm = () => {
    form.put('/gestion/perfil', {
        preserveScroll: true,
        onSuccess: () => {
            toast?.success('Éxito', 'Perfil actualizado correctamente')
            form.Clave = '' // Limpiar campo de contraseña
            mostrarClave.value = false
        },
        onError: (errors) => {
            toast?.error('Error', 'Error al actualizar el perfil')
        }
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-3xl mx-auto">
                <!-- Header -->
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-circle text-guindo-600 text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Mi Perfil</h1>
                        <p class="text-[11px] text-gray-500">Actualiza tus datos personales y de acceso</p>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <form @submit.prevent="submitForm" class="space-y-4">
                        <!-- Identificador (solo lectura) -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">Persona (CI/NIT)</label>
                            <div class="bg-gray-100 rounded-lg px-3 py-2 text-sm text-gray-600 border">
                                <i class="fas fa-id-card text-guindo-500 mr-2 text-xs"></i>
                                {{ operador?.identificador?.CI_NIT }} - {{ operador?.identificador?.Nombre }}
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">* No se puede modificar, contacta al administrador si necesitas cambiar</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Iniciales -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-1">Iniciales *</label>
                                <input 
                                    type="text" 
                                    v-model="form.Iniciales" 
                                    class="w-full border rounded-md px-2 py-1.5 text-xs uppercase focus:ring-1 focus:ring-guindo-500 focus:border-guindo-500"
                                    :class="{ 'border-red-500': form.errors.Iniciales }"
                                    placeholder="Ej: JPG"
                                    maxlength="5"
                                >
                                <p v-if="form.errors.Iniciales" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Iniciales }}</p>
                            </div>

                            <!-- Nombre de Acceso -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-1">Nombre de Acceso *</label>
                                <input 
                                    type="text" 
                                    v-model="form.NombreAcceso" 
                                    class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-guindo-500 focus:border-guindo-500"
                                    :class="{ 'border-red-500': form.errors.NombreAcceso }"
                                    placeholder="Usuario para login"
                                >
                                <p v-if="form.errors.NombreAcceso" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.NombreAcceso }}</p>
                            </div>
                        </div>

                        <!-- Contraseña -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">
                                Contraseña
                                <span class="text-gray-400 font-normal">(dejar en blanco para no cambiar)</span>
                            </label>
                            <div class="relative">
                                <input 
                                    :type="mostrarClave ? 'text' : 'password'" 
                                    v-model="form.Clave" 
                                    class="w-full border rounded-md px-2 py-1.5 text-xs pr-8 focus:ring-1 focus:ring-guindo-500 focus:border-guindo-500"
                                    :class="{ 'border-red-500': form.errors.Clave }"
                                    placeholder="••••••••"
                                >
                                <button 
                                    type="button"
                                    @click="mostrarClave = !mostrarClave"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i :class="mostrarClave ? 'fas fa-eye-slash text-xs' : 'fas fa-eye text-xs'"></i>
                                </button>
                            </div>
                            <p v-if="form.errors.Clave" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Clave }}</p>
                        </div>

                        <!-- Dirección -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">Dirección Domicilio</label>
                            <input 
                                type="text" 
                                v-model="form.DireccionDomicilio" 
                                class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-guindo-500 focus:border-guindo-500"
                                :class="{ 'border-red-500': form.errors.DireccionDomicilio }"
                                placeholder="Dirección completa"
                            >
                            <p v-if="form.errors.DireccionDomicilio" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.DireccionDomicilio }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Teléfono Domicilio -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-1">Teléfono Domicilio</label>
                                <input 
                                    type="text" 
                                    v-model="form.TelefonoDomicilio" 
                                    class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-guindo-500 focus:border-guindo-500"
                                    :class="{ 'border-red-500': form.errors.TelefonoDomicilio }"
                                    placeholder="Teléfono fijo"
                                >
                                <p v-if="form.errors.TelefonoDomicilio" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.TelefonoDomicilio }}</p>
                            </div>

                            <!-- Número Celular -->
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-1">Número Celular</label>
                                <input 
                                    type="text" 
                                    v-model="form.NumeroCelular" 
                                    class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-guindo-500 focus:border-guindo-500"
                                    :class="{ 'border-red-500': form.errors.NumeroCelular }"
                                    placeholder="Celular / WhatsApp"
                                >
                                <p v-if="form.errors.NumeroCelular" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.NumeroCelular }}</p>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-2 pt-3 border-t">
                            <button 
                                type="button" 
                                @click="router.get('/oficial')" 
                                class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="submit" 
                                :disabled="form.processing" 
                                class="px-4 py-1.5 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1"
                            >
                                <i v-if="form.processing" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-save text-[10px]"></i>
                                {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Información adicional -->
                <div class="mt-4 text-center">
                    <p class="text-[10px] text-gray-400">
                        <i class="fas fa-shield-alt mr-1"></i>
                        Tus datos están seguros. Solo tú puedes modificar esta información.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>