<script setup>
import { ref, computed, onMounted, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    horas: {
        type: Array,
        default: () => []
    },
    horasDisponibles: {
        type: Array,
        default: () => []
    },
    horaActiva: {
        type: Object,
        default: () => null
    }
})

// Estado del formulario
const editando = ref(false)
const editId = ref(null)
const formData = ref({
    Hora: '',
    ActivaControlDia: 0
})
const errors = ref({})
const guardando = ref(false)

// Obtener la hora existente (solo debe haber una)
const horaExistente = computed(() => {
    if (props.horas && props.horas.length > 0) {
        return props.horas[0]
    }
    return null
})

// Saber si ya existe una hora configurada
const existeHora = computed(() => {
    return horaExistente.value !== null
})

// Reset formulario
const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = {
        Hora: '',
        ActivaControlDia: 0
    }
    errors.value = {}
}

// Editar (cargar la hora existente)
const editar = () => {
    if (!horaExistente.value) return
    
    editando.value = true
    editId.value = horaExistente.value.IdHoraLimite
    formData.value = {
        Hora: horaExistente.value.Hora,
        ActivaControlDia: horaExistente.value.ActivaControlDia ? 1 : 0
    }
}

// Guardar
const guardar = async () => {
    guardando.value = true
    errors.value = {}

    try {
        let response
        const dataToSend = {
            Hora: formData.value.Hora,
            ActivaControlDia: formData.value.ActivaControlDia
        }

        if (editando.value && editId.value) {
            response = await axios.put(`/operacion/pedidos/hora-limite/${editId.value}`, dataToSend)
            
            if (response.status === 200) {
                toast?.success('Hora actualizada', `Hora límite actualizada a ${formData.value.Hora}:00`)
                resetForm()
                setTimeout(() => {
                    router.reload()
                }, 1000)
            }
        } else {
            response = await axios.post('/operacion/pedidos/hora-limite', dataToSend)
            
            if (response.status === 200 || response.status === 201) {
                toast?.success('Hora guardada', `Hora límite configurada a ${formData.value.Hora}:00`)
                resetForm()
                setTimeout(() => {
                    router.reload()
                }, 1000)
            }
        }
    } catch (error) {
        console.error('Error:', error)
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            toast?.error('Error de validación', Object.values(errors.value).join(', '))
        } else {
            const errorMsg = error.response?.data?.message || error.message
            toast?.error('Error', errorMsg)
        }
    } finally {
        guardando.value = false
    }
}

// Cancelar edición
const cancelarEdicion = () => {
    resetForm()
}

// Horas disponibles filtradas
const horasDisponiblesFiltradas = computed(() => {
    if (!props.horasDisponibles) return []
    
    if (editando.value && horaExistente.value) {
        return props.horasDisponibles.map(h => ({
            ...h,
            disponible: h.value === horaExistente.value.Hora || h.disponible
        }))
    }
    
    return props.horasDisponibles.filter(h => h.disponible)
})

// Hora activa formateada
const horaActivaTexto = computed(() => {
    if (!props.horaActiva) return 'No hay hora activa'
    return props.horaActiva.HoraFormateada || props.horaActiva.Hora + ':00'
})

// Estado de la hora existente
const estadoHora = computed(() => {
    if (!horaExistente.value) return null
    const isActive = horaExistente.value.ActivaControlDia === 0
    return {
        isActive: isActive,
        texto: isActive ? 'Activo' : 'Inactivo',
        color: isActive ? 'green' : 'red',
        icono: isActive ? 'fa-check-circle' : 'fa-ban'
    }
})

onMounted(() => {})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-100 rounded-2xl mb-3">
                        <i class="fas fa-clock text-xl text-blue-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Hora Límite de Pedidos</h1>
                    <p class="text-xs text-gray-500">Configure la hora hasta la cual se pueden realizar pedidos</p>
                </div>

                <!-- Indicador de hora activa actual -->
                <div class="mb-5 p-3 rounded-lg" :class="props.horaActiva ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'">
                    <div class="flex items-center gap-2">
                        <i class="fas" :class="props.horaActiva ? 'fa-check-circle text-green-600' : 'fa-exclamation-triangle text-yellow-600'"></i>
                        <span class="text-sm font-medium">
                            Hora límite activa: 
                            <strong class="text-lg">{{ horaActivaTexto }}</strong>
                        </span>
                        <span v-if="!props.horaActiva" class="text-xs text-yellow-700 ml-2">
                            (No hay hora activa. Los pedidos no tendrán restricción)
                        </span>
                    </div>
                    <p v-if="props.horaActiva" class="text-xs text-gray-500 mt-1">
                        Solo puede existir UNA hora límite. Para cambiarla, edita la hora existente.
                    </p>
                </div>

                <!-- 🔥 ESTRUCTURA CORREGIDA - UNA SOLA CADENA DE CONDICIONES -->
                <!-- Caso 1: No existe hora - Mostrar formulario de creación -->
                <div v-if="!existeHora" class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hora Límite *</label>
                            <select 
                                v-model="formData.Hora"
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.Hora }"
                            >
                                <option value="">Seleccione una hora</option>
                                <option 
                                    v-for="hora in horasDisponiblesFiltradas" 
                                    :key="hora.value"
                                    :value="hora.value"
                                >
                                    {{ hora.label }}
                                </option>
                            </select>
                            <p v-if="errors.Hora" class="text-xs text-red-500 mt-1">{{ errors.Hora }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <select v-model.number="formData.ActivaControlDia" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option :value="0">✓ Activo</option>
                                <option :value="1">✗ Inactivo</option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Activo = Permite pedidos hasta esta hora</p>
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="guardar" 
                                :disabled="guardando || !formData.Hora"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50"
                            >
                                <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-save"></i>
                                {{ guardando ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Caso 2: Existe hora Y estamos editando - Mostrar formulario de edición -->
                <div v-else-if="existeHora && editando" class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hora Límite *</label>
                            <select 
                                v-model="formData.Hora"
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.Hora }"
                            >
                                <option value="">Seleccione una hora</option>
                                <option 
                                    v-for="hora in horasDisponiblesFiltradas" 
                                    :key="hora.value"
                                    :value="hora.value"
                                    :disabled="!hora.disponible && hora.value !== horaExistente?.Hora"
                                >
                                    {{ hora.label }}
                                    <span v-if="!hora.disponible && hora.value !== horaExistente?.Hora" class="text-xs">(no disponible)</span>
                                </option>
                            </select>
                            <p v-if="errors.Hora" class="text-xs text-red-500 mt-1">{{ errors.Hora }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <select v-model.number="formData.ActivaControlDia" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option :value="0">✓ Activo</option>
                                <option :value="1">✗ Inactivo</option>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Activo = Permite pedidos hasta esta hora</p>
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="guardar" 
                                :disabled="guardando || !formData.Hora"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50"
                            >
                                <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-pencil-alt"></i>
                                {{ guardando ? 'Guardando...' : 'Actualizar' }}
                            </button>
                            <button 
                                @click="cancelarEdicion" 
                                class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300"
                                :disabled="guardando"
                            >
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Caso 3: Existe hora Y NO estamos editando - Mostrar vista de solo lectura -->
                <div v-else class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div class="flex items-center gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-mono font-bold text-gray-800">
                                    {{ horaExistente.HoraFormateada || horaExistente.Hora + ':00' }}
                                </div>
                                <div class="text-xs text-gray-500">Hora configurada</div>
                            </div>
                            <div class="w-px h-10 bg-gray-200"></div>
                        </div>
                        <div>
                            <button 
                                @click="editar" 
                                class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm hover:bg-amber-700 transition flex items-center gap-2"
                            >
                                <i class="fas fa-edit"></i>
                                Editar Hora
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla informativa -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b">
                        <h3 class="text-sm font-semibold text-gray-700">
                            <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                            Configuración actual
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hora</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aplica a</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="horaExistente" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-mono font-bold text-gray-800">
                                        <i class="fas fa-hourglass-half text-blue-400 mr-2"></i>
                                        {{ horaExistente.HoraFormateada || horaExistente.Hora + ':00' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span 
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="horaExistente.ActivaControlDia ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                                        >
                                            <i v-if="!horaExistente.ActivaControlDia" class="fas fa-check-circle mr-1"></i>
                                            <i v-else class="fas fa-ban mr-1"></i>
                                            {{ horaExistente.ActivaControlDia ? 'Inactivo' : 'Activo' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <i class="fas fa-building mr-1"></i>
                                        Todas las sucursales
                                    </td>
                                </tr>
                                <tr v-else>
                                    <td colspan="3" class="px-6 py-8 text-center text-gray-400">
                                        <i class="fas fa-clock text-2xl mb-2 block"></i>
                                        No hay hora límite configurada.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Nota:</strong> 
                    <ul class="list-disc list-inside mt-1 space-y-0.5">
                        <li>Solo puede existir <strong class="text-blue-800">UNA hora límite</strong> configurada</li>
                        <li>Si la hora está <strong class="text-green-700">Activa</strong>, los pedidos después de esa hora no serán permitidos</li>
                        <li>Si la hora está <strong class="text-red-700">Inactiva</strong>, no hay restricción de horario</li>
                        <li>La configuración aplica a <strong>TODAS las sucursales</strong> de la empresa</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>