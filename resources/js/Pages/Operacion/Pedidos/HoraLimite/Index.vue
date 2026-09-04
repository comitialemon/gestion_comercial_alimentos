<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
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

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const editando = ref(false)
const editId = ref(null)
const formData = ref({
    Hora: '',
    ActivaControlDia: 0
})
const errors = ref({})
const guardando = ref(false)

// ==================== COMPUTED ====================
const horaExistente = computed(() => {
    if (props.horas && props.horas.length > 0) {
        return props.horas[0]
    }
    return null
})

const existeHora = computed(() => {
    return horaExistente.value !== null
})

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

const horaActivaTexto = computed(() => {
    if (!props.horaActiva) return 'No hay hora activa'
    return props.horaActiva.HoraFormateada || props.horaActiva.Hora + ':00'
})

// ==================== FUNCIONES ====================
const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = {
        Hora: '',
        ActivaControlDia: 0
    }
    errors.value = {}
}

const editar = () => {
    if (!horaExistente.value) return
    
    editando.value = true
    editId.value = horaExistente.value.IdHoraLimite
    formData.value = {
        Hora: horaExistente.value.Hora,
        ActivaControlDia: horaExistente.value.ActivaControlDia ? 1 : 0
    }
}

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

const cancelarEdicion = () => {
    resetForm()
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-clock text-blue-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Hora Límite de Pedidos</h1>
                        <p class="text-xs text-gray-500">Configure la hora hasta la cual se pueden realizar pedidos</p>
                    </div>
                </div>

                <!-- ==================== INDICADOR DE HORA ACTIVA ==================== -->
                <div class="mb-4 p-3 rounded-xl border" :class="props.horaActiva ? 'bg-emerald-50 border-emerald-200' : 'bg-yellow-50 border-yellow-200'">
                    <div class="flex flex-wrap items-center gap-2">
                        <i class="fas" :class="props.horaActiva ? 'fa-check-circle text-emerald-600' : 'fa-exclamation-triangle text-yellow-600'"></i>
                        <span class="text-xs font-medium">
                            Hora límite activa: 
                            <strong class="text-base">{{ horaActivaTexto }}</strong>
                        </span>
                        <span v-if="!props.horaActiva" class="text-xs text-yellow-700">
                            (No hay hora activa. Los pedidos no tendrán restricción)
                        </span>
                    </div>
                    <p v-if="props.horaActiva" class="text-[10px] text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Solo puede existir UNA hora límite. Para cambiarla, edita la hora existente.
                    </p>
                </div>

                <!-- ==================== FORMULARIO ==================== -->
                <!-- Caso 1: No existe hora - Creación -->
                <div v-if="!existeHora" class="bg-white rounded-xl shadow-sm p-4 mb-4 border border-gray-200">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[140px] max-w-[220px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Hora Límite *</label>
                            <select 
                                v-model="formData.Hora"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
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
                            <p v-if="errors.Hora" class="text-[8px] text-red-500 mt-0.5">{{ errors.Hora }}</p>
                        </div>
                        <div class="w-28">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Estado</label>
                            <select v-model.number="formData.ActivaControlDia" class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option :value="0">Activo</option>
                                <option :value="1">Inactivo</option>
                            </select>
                        </div>
                        <div class="flex gap-1.5">
                            <button 
                                @click="guardar" 
                                :disabled="guardando || !formData.Hora"
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition disabled:opacity-50 flex items-center gap-1.5"
                            >
                                <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-save text-[10px]"></i>
                                {{ guardando ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-[8px] text-gray-400">Activo = Permite pedidos hasta esta hora</p>
                </div>

                <!-- Caso 2: Existe hora Y estamos editando -->
                <div v-else-if="existeHora && editando" class="bg-white rounded-xl shadow-sm p-4 mb-4 border border-amber-200">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[140px] max-w-[220px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Hora Límite *</label>
                            <select 
                                v-model="formData.Hora"
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
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
                                    <span v-if="!hora.disponible && hora.value !== horaExistente?.Hora" class="text-[8px] text-gray-400">(no disponible)</span>
                                </option>
                            </select>
                            <p v-if="errors.Hora" class="text-[8px] text-red-500 mt-0.5">{{ errors.Hora }}</p>
                        </div>
                        <div class="w-28">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Estado</label>
                            <select v-model.number="formData.ActivaControlDia" class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option :value="0">Activo</option>
                                <option :value="1">Inactivo</option>
                            </select>
                        </div>
                        <div class="flex gap-1.5">
                            <button 
                                @click="guardar" 
                                :disabled="guardando || !formData.Hora"
                                class="px-3 py-1.5 bg-amber-600 text-white rounded-md text-xs font-medium hover:bg-amber-700 transition disabled:opacity-50 flex items-center gap-1.5"
                            >
                                <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-pencil-alt text-[10px]"></i>
                                {{ guardando ? 'Guardando...' : 'Actualizar' }}
                            </button>
                            <button 
                                @click="cancelarEdicion" 
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1.5"
                                :disabled="guardando"
                            >
                                <i class="fas fa-times text-[10px]"></i> Cancelar
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-[8px] text-gray-400">Activo = Permite pedidos hasta esta hora</p>
                </div>

                <!-- Caso 3: Existe hora Y NO estamos editando - Vista de solo lectura -->
                <div v-else class="bg-white rounded-xl shadow-sm p-4 mb-4 border border-gray-200">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-4">
                            <div class="text-center">
                                <div class="text-xl font-mono font-bold text-gray-800">
                                    {{ horaExistente.HoraFormateada || horaExistente.Hora + ':00' }}
                                </div>
                                <div class="text-[8px] text-gray-500">Hora configurada</div>
                            </div>
                            <div class="w-px h-8 bg-gray-200"></div>
                            <div>
                                <span 
                                    class="px-2 py-0.5 text-[10px] rounded-full"
                                    :class="horaExistente.ActivaControlDia ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                >
                                    <i v-if="!horaExistente.ActivaControlDia" class="fas fa-check-circle mr-1 text-[8px]"></i>
                                    <i v-else class="fas fa-ban mr-1 text-[8px]"></i>
                                    {{ horaExistente.ActivaControlDia ? 'Inactivo' : 'Activo' }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <button 
                                @click="editar" 
                                class="px-3 py-1.5 bg-amber-600 text-white rounded-md text-xs font-medium hover:bg-amber-700 transition flex items-center gap-1.5"
                            >
                                <i class="fas fa-edit text-[10px]"></i>
                                Editar Hora
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== TABLA INFORMATIVA ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                            <i class="fas fa-info-circle text-blue-500 text-[10px]"></i>
                            Configuración actual
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">Hora</th>
                                    <th class="px-4 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-4 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">Aplica a</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="horaExistente" class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-2 text-sm font-mono font-bold text-gray-800">
                                        <i class="fas fa-hourglass-half text-blue-400 mr-2 text-[10px]"></i>
                                        {{ horaExistente.HoraFormateada || horaExistente.Hora + ':00' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span 
                                            class="px-2 py-0.5 text-[9px] rounded-full"
                                            :class="horaExistente.ActivaControlDia ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                        >
                                            <i v-if="!horaExistente.ActivaControlDia" class="fas fa-check-circle mr-1 text-[7px]"></i>
                                            <i v-else class="fas fa-ban mr-1 text-[7px]"></i>
                                            {{ horaExistente.ActivaControlDia ? 'Inactivo' : 'Activo' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <i class="fas fa-building mr-1 text-[10px]"></i>
                                        Todas las sucursales
                                    </td>
                                </tr>
                                <tr v-else>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        <i class="fas fa-clock text-2xl mb-2 block"></i>
                                        No hay hora límite configurada.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== INFORMACIÓN ADICIONAL ==================== -->
                <div class="mt-3 p-3 bg-blue-50 rounded-xl border border-blue-100 text-xs text-blue-700 flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5 text-blue-500 text-[10px]"></i>
                    <div>
                        <span class="font-medium">Nota:</span>
                        <ul class="list-disc list-inside mt-1 space-y-0.5 text-[11px]">
                            <li>Solo puede existir <strong class="text-blue-800">UNA hora límite</strong> configurada por cliente</li>
                            <li>La configuración aplica a <strong>TODAS las sucursales</strong> de la empresa</li>
                            <li>Si la hora está <strong class="text-emerald-700">Activa</strong>, los pedidos después de esa hora no serán permitidos</li>
                            <li>Si la hora está <strong class="text-red-700">Inactiva</strong>, no hay restricción de horario</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>