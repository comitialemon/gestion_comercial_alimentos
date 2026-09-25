<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

// ==================== ESTADO ====================
const loading = ref(false)
const form = ref({
    Nombre: '',
    Descripcion: '',
})
const errors = ref({})

// ==================== GUARDAR ====================
const guardar = async () => {
    errors.value = {}

    if (!form.value.Nombre || form.value.Nombre.trim() === '') {
        errors.value.Nombre = 'El nombre es obligatorio'
        toast?.error('Validación', 'Ingrese el nombre del grupo')
        return
    }

    loading.value = true

    try {
        const response = await axios.post('/operacion/pedidos/clientes-mayoristas/grupos-clientes', {
            Nombre: form.value.Nombre.trim(),
            Descripcion: form.value.Descripcion?.trim() || null,
        })

        if (response.data.success) {
            toast?.success('Éxito', 'Grupo creado correctamente. Ahora configure los mínimos.')
            
            // Redirigir a edición (pestaña mínimos)
            router.get(`/operacion/pedidos/clientes-mayoristas/grupos-clientes/${response.data.grupo.IdGrupoCliente}/edit`)
        } else {
            toast?.error('Error', response.data.message || 'Error al crear el grupo')
        }
    } catch (error) {
        console.error('Error:', error)
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {}
            const firstError = Object.values(errors.value)[0]
            if (firstError) {
                toast?.error('Error de validación', Array.isArray(firstError) ? firstError[0] : firstError)
            }
        } else {
            toast?.error('Error', error.response?.data?.message || 'Error al crear el grupo')
        }
    } finally {
        loading.value = false
    }
}

// ==================== CANCELAR ====================
const cancelar = () => {
    if (form.value.Nombre || form.value.Descripcion) {
        if (!confirm('¿Estás seguro de salir? Los cambios no guardados se perderán.')) {
            return
        }
    }
    router.get('/operacion/pedidos/clientes-mayoristas/grupos-clientes')
}

// ==================== ENTER ====================
const onEnter = (e) => {
    if (e.key === 'Enter' && form.value.Nombre.trim()) {
        guardar()
    }
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-2xl mx-auto">

                <!-- HEADER -->
                <div class="flex items-center gap-3 mb-5">
                    <button 
                        @click="cancelar"
                        class="w-9 h-9 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary-600 hover:border-primary-300 transition flex-shrink-0"
                    >
                        <i class="fas fa-arrow-left text-sm"></i>
                    </button>
                    <div>
                        <h1 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-layer-group text-primary-500"></i>
                            Nuevo Grupo de Clientes
                        </h1>
                        <p class="text-xs text-gray-500">
                            Paso 1: Datos básicos del grupo
                        </p>
                    </div>
                </div>

                <!-- INFO -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4 flex items-start gap-2">
                    <i class="fas fa-info-circle text-blue-500 text-sm flex-shrink-0 mt-0.5"></i>
                    <div class="text-xs text-blue-700">
                        <p class="font-medium mb-0.5">¿Qué es un Grupo de Clientes?</p>
                        <p>Es una agrupación de clientes (operadores PedidoClientes) que comparten <strong>los mismos productos, precios y mínimos</strong>. Útil para configurar 500 clientes en pocos grupos.</p>
                    </div>
                </div>

                <!-- FORMULARIO -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5">
                    <div class="space-y-4">

                        <!-- Nombre -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                Nombre del grupo <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text"
                                v-model="form.Nombre"
                                @keydown="onEnter"
                                placeholder="Ej: MINORISTAS PROVINCIA, MAYORISTAS 50/100 L..."
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none transition"
                                :class="errors.Nombre ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                                maxlength="150"
                                autofocus
                            />
                            <div class="flex justify-between mt-1">
                                <p v-if="errors.Nombre" class="text-[10px] text-red-500">
                                    <i class="fas fa-exclamation-circle mr-0.5"></i>
                                    {{ errors.Nombre }}
                                </p>
                                <p v-else class="text-[10px] text-gray-400">
                                    Nombre descriptivo del grupo
                                </p>
                                <span class="text-[10px] text-gray-400">
                                    {{ form.Nombre.length }}/150
                                </span>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                Descripción <span class="text-gray-400 font-normal">(opcional)</span>
                            </label>
                            <textarea 
                                v-model="form.Descripcion"
                                rows="3"
                                placeholder="Describe qué clientes pertenecen a este grupo..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none transition resize-none"
                                maxlength="500"
                            ></textarea>
                            <div class="flex justify-end mt-1">
                                <span class="text-[10px] text-gray-400">
                                    {{ form.Descripcion.length }}/500
                                </span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- INFO SIGUIENTE PASO -->
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mt-4 flex items-start gap-2">
                    <i class="fas fa-arrow-right text-amber-500 text-sm flex-shrink-0 mt-0.5"></i>
                    <div class="text-xs text-amber-700">
                        <p class="font-medium mb-0.5">Siguiente paso:</p>
                        <p>Al guardar, podrás configurar los <strong>mínimos por grupo de análisis</strong>. Solo los grupos con mínimo tendrán productos disponibles.</p>
                    </div>
                </div>

                <!-- BOTONES -->
                <div class="flex flex-col sm:flex-row justify-end gap-2 mt-5">
                    <button 
                        @click="cancelar"
                        :disabled="loading"
                        class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium transition disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button 
                        @click="guardar"
                        :disabled="loading || !form.Nombre.trim()"
                        class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 shadow-sm"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ loading ? 'Guardando...' : 'Guardar y continuar' }}
                    </button>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, textarea, button {
        font-size: 13px !important;
    }
}
</style>