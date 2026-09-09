<!-- resources/js/Pages/Operacion/ClientesMayoristas/OperadoresClientes/ModalAgregarIdentificador.vue -->
<template>
    <Teleport to="body">
        <div v-if="visible" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
             @click.self="cerrar">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 transform transition-all animate-modal">
                <!-- Header -->
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900" :style="{ color: `var(--color-primary-700)` }">
                            <i class="fas fa-id-card mr-2"></i> Nuevo Identificador
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">Ingresa los datos de la persona</p>
                    </div>
                    <button @click="cerrar" class="text-gray-400 hover:text-gray-600 transition" type="button">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Formulario -->
                <form @submit.prevent="guardar" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            CI / NIT <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            v-model="formData.CI_NIT" 
                            @input="formatearCI"
                            @keydown="soloNumeros"
                            placeholder="Solo números" 
                            class="w-full border rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:outline-none"
                            :class="{ 'border-red-500': errors.CI_NIT }"
                            :style="{ borderColor: errors.CI_NIT ? '#ef4444' : `var(--color-primary-300)` }"
                            inputmode="numeric"
                            ref="ciInput"
                            autofocus
                        />
                        <p v-if="errors.CI_NIT" class="text-xs text-red-500 mt-1">{{ errors.CI_NIT }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            v-model="formData.Nombre" 
                            @input="formatearNombre"
                            @blur="formatearNombre"
                            placeholder="Nombre completo" 
                            class="w-full border rounded-lg px-3 py-2 text-sm uppercase focus:ring-2 focus:outline-none"
                            :class="{ 'border-red-500': errors.Nombre }"
                            :style="{ borderColor: errors.Nombre ? '#ef4444' : `var(--color-primary-300)` }"
                        />
                        <p v-if="errors.Nombre" class="text-xs text-red-500 mt-1">{{ errors.Nombre }}</p>
                    </div>

                    <!-- Botones -->
                    <div class="flex gap-3 mt-4">
                        <button type="button" @click="cerrar"
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="cargando"
                            class="flex-1 px-4 py-2 text-white rounded-lg text-sm flex items-center justify-center gap-2 transition"
                            :class="cargando ? 'opacity-50 cursor-not-allowed' : ''"
                            :style="{ backgroundColor: cargando ? '#9ca3af' : `var(--color-primary-600)` }">
                            <i v-if="cargando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ cargando ? 'Guardando...' : 'Guardar Identificador' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, nextTick, inject } from 'vue'
import axios from 'axios'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['close', 'saved'])

const toast = inject('toast')

// Datos del formulario
const formData = ref({
    CI_NIT: '',
    Nombre: ''
})

const errors = ref({})
const cargando = ref(false)
const ciInput = ref(null)
let guardando = false

// Solo permitir números
const soloNumeros = (e) => {
    if (!/^\d$/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && 
        e.key !== 'Tab' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' &&
        e.key !== 'ArrowUp' && e.key !== 'ArrowDown' && e.key !== 'Home' && e.key !== 'End') {
        e.preventDefault()
    }
}

// Formatear CI (solo números)
const formatearCI = () => {
    let valor = formData.value.CI_NIT.replace(/\D/g, '')
    formData.value.CI_NIT = valor
}

// Formatear Nombre (mayúsculas)
const formatearNombre = () => {
    formData.value.Nombre = formData.value.Nombre.toUpperCase()
}

// Resetear formulario
const resetForm = () => {
    formData.value = { CI_NIT: '', Nombre: '' }
    errors.value = {}
    cargando.value = false
    guardando = false
}

// Cerrar modal
const cerrar = () => {
    resetForm()
    emit('close')
}

// 🔥 GUARDAR IDENTIFICADOR - VERSIÓN FINAL
const guardar = async () => {
    if (guardando) {
        console.log('Ya se está guardando, ignorando...')
        return
    }

    // Validar campos
    if (!formData.value.CI_NIT) {
        errors.value = { CI_NIT: 'El CI/NIT es obligatorio' }
        toast?.error('Error', 'El CI/NIT es obligatorio')
        return
    }

    if (!formData.value.Nombre) {
        errors.value = { Nombre: 'El nombre es obligatorio' }
        toast?.error('Error', 'El nombre es obligatorio')
        return
    }

    guardando = true
    cargando.value = true
    errors.value = {}

    try {
        const response = await axios.post('/gestion/todos/identificador', {
            CI_NIT: formData.value.CI_NIT,
            Nombre: formData.value.Nombre
        })

        console.log('📦 RESPUESTA DEL SERVIDOR:', response.data)

        // 🔥 VERIFICAR QUE LA RESPUESTA SEA EXITOSA
        if (response.data && response.data.success === true) {
            const nuevoIdentificador = response.data.identificador
            
            if (nuevoIdentificador && nuevoIdentificador.IdIdentificador) {
                toast?.success('Éxito', 'Identificador creado correctamente')
                
                const identificadorFormateado = {
                    id: nuevoIdentificador.IdIdentificador,
                    CI_NIT: nuevoIdentificador.CI_NIT,
                    Nombre: nuevoIdentificador.Nombre,
                    text: `${nuevoIdentificador.CI_NIT} - ${nuevoIdentificador.Nombre}`
                }
                
                console.log('📤 EMITIENDO SAVED:', identificadorFormateado)
                
                // EMITIR EL EVENTO SAVED
                emit('saved', identificadorFormateado)
                
                // Resetear y cerrar después de un momento
                setTimeout(() => {
                    resetForm()
                    emit('close')
                }, 500)
            } else {
                console.error('❌ Identificador no encontrado en la respuesta:', response.data)
                toast?.error('Error', 'El identificador se creó pero no se pudo obtener la información')
                guardando = false
            }
        } else {
            // Si hay errores de validación
            if (response.data && response.data.errors) {
                errors.value = response.data.errors
                const mensaje = Object.values(errors.value).flat()[0] || 'Error de validación'
                toast?.error('Error', mensaje)
            } else {
                const mensaje = response.data?.message || 'Error al guardar el identificador'
                toast?.error('Error', mensaje)
            }
            guardando = false
        }

    } catch (error) {
        console.error('❌ Error al guardar identificador:', error)
        
        if (error.response?.status === 422 && error.response?.data?.errors) {
            errors.value = error.response.data.errors
            const mensaje = Object.values(errors.value).flat()[0] || 'Error de validación'
            toast?.error('Error', mensaje)
        } else if (error.response?.data?.message) {
            toast?.error('Error', error.response.data.message)
        } else {
            toast?.error('Error', 'Error al guardar el identificador. Intente nuevamente.')
        }
        guardando = false
    } finally {
        cargando.value = false
        setTimeout(() => {
            guardando = false
        }, 1000)
    }
}

// Enfocar el input cuando se abre el modal
watch(() => props.visible, (nuevo) => {
    if (nuevo) {
        guardando = false
        nextTick(() => {
            setTimeout(() => {
                ciInput.value?.focus()
            }, 100)
        })
    }
})
</script>

<style scoped>
@keyframes modal {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.animate-modal {
    animation: modal 0.2s ease-out;
}

input:focus {
    --tw-ring-color: var(--color-primary-500);
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}
</style>