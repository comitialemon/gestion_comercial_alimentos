<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, onMounted, inject, watch } from 'vue'  // 🔥 Agregar watch
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import DiarioPropiamenteTab from './components/DiarioPropiamenteTab.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    diario: Object,
    asientos: Array,
    fechas: Array,
    tiposDiario: Array,
    sucursales: Array,
    cuentas: Array,
    identificadores: Array,
    actividadCliente: String,
    editando: Boolean,
})

const diarioGuardado = ref(props.editando || false)
const diarioId = ref(props.diario?.IdDiario || null)
const contabilizando = ref(false)
const puedeContabilizar = ref(false)
const modalErrorVisible = ref(false)

// 🔥 CORREGIDO: Inicializar con los valores de props
const form = useForm({
    IdFecha: props.diario?.IdFecha || '',
    IdTipoDiario: props.diario?.IdTipoDiario || '',
    IdSucursal: props.diario?.IdSucursal || '',
})

const asientosList = ref(props.asientos || [])

// 🔥 NUEVO: Watch para actualizar el form cuando cambien los props
watch(() => props.diario, (nuevoDiario) => {
    if (nuevoDiario) {
        form.IdFecha = nuevoDiario.IdFecha || ''
        form.IdTipoDiario = nuevoDiario.IdTipoDiario || ''
        form.IdSucursal = nuevoDiario.IdSucursal || ''
    }
}, { immediate: true, deep: true })

const guardarCabecera = () => {
    if (props.editando) {
        form.put(`/contabilidad/diario-ingreso/${props.diario.IdDiario}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast?.success('Éxito', 'Cabecera actualizada correctamente')
            },
            onError: (errors) => {
                toast?.error('Error', 'Verifique los datos ingresados')
            }
        })
    } else {
        form.post('/contabilidad/diario-ingreso', {
            preserveScroll: true,
            onSuccess: (response) => {
                diarioGuardado.value = true
                setTimeout(() => {
                    window.location.reload()
                }, 500)
            },
            onError: (errors) => {
                toast?.error('Error', 'Verifique los datos ingresados')
            }
        })
    }
}

const manejarEstadoContabilizacion = (valor) => {
    puedeContabilizar.value = valor
}

const contabilizar = async () => {
    if (!puedeContabilizar.value) {
        modalErrorVisible.value = true
        return
    }
    
    if (!confirm('¿Contabilizar este diario? Una vez contabilizado no se podrá modificar.')) return
    
    contabilizando.value = true
    try {
        const response = await axios.post(`/contabilidad/diario-ingreso/${diarioId.value}/contabilizar`)
        if (response.status === 200) {
            window.open(`/contabilidad/diario-ingreso/${diarioId.value}/pdf`, '_blank')
            router.get('/contabilidad/diario-ingreso')
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al contabilizar')
    } finally {
        contabilizando.value = false
    }
}

const cerrarModalError = () => {
    modalErrorVisible.value = false
}

// 🔥 Ya no es necesario onMounted porque watch lo maneja
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-3 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-book text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">{{ editando ? 'Editar Diario' : 'Nuevo Diario' }}</h1>
                            <p class="text-[10px] text-gray-500">{{ editando ? 'Modifique los datos del diario' : 'Complete los datos del nuevo diario' }}</p>
                        </div>
                    </div>
                    
                    <!-- Información de la actividad del cliente -->
                    <div v-if="editando && actividadCliente" class="flex items-center gap-2 px-2 py-1 bg-primary-50 rounded-md">
                        <i class="fas fa-tasks text-primary-600 text-[10px]"></i>
                        <span class="text-[10px] font-medium text-primary-700">Actividad:</span>
                        <span class="text-[10px] text-primary-800">{{ actividadCliente }}</span>
                    </div>
                    
                    <div class="flex gap-2">
                        <button type="button" @click="router.get('/contabilidad/diario-ingreso')" class="px-3 py-1 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                            Cancelar
                        </button>
                        
                        <button v-if="editando" @click="contabilizar" :disabled="contabilizando" class="px-3 py-1 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1">
                            <i v-if="contabilizando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-check-circle text-[10px]"></i>
                            {{ contabilizando ? 'Contabilizando...' : 'Contabilizar' }}
                        </button>
                        
                        <button @click="guardarCabecera" :disabled="form.processing" class="px-3 py-1 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1">
                            <i v-if="form.processing" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            {{ form.processing ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </div>

                <!-- Formulario Principal -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <!-- FECHA -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fecha del Diario *</label>
                            <select 
                                v-model="form.IdFecha" 
                                class="w-full border rounded-md px-2 py-1.5 text-xs" 
                                :class="{ 'border-red-500': form.errors.IdFecha }" 
                                :disabled="editando && diario?.Contabilizado === 1"
                            >
                                <option value="">Seleccione una fecha</option>
                                <option v-for="f in fechas" :key="f.id" :value="f.id">{{ f.fecha }}</option>
                            </select>
                            <p v-if="form.errors.IdFecha" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.IdFecha }}</p>
                        </div>

                        <!-- TIPO DIARIO -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tipo de Diario *</label>
                            <select 
                                v-model="form.IdTipoDiario" 
                                class="w-full border rounded-md px-2 py-1.5 text-xs" 
                                :class="{ 'border-red-500': form.errors.IdTipoDiario }" 
                                :disabled="editando && diario?.Contabilizado === 1"
                            >
                                <option value="">Seleccione un tipo</option>
                                <option v-for="t in tiposDiario" :key="t.IdTipoDiario" :value="t.IdTipoDiario">{{ t.TipoDiario }}</option>
                            </select>
                            <p v-if="form.errors.IdTipoDiario" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.IdTipoDiario }}</p>
                        </div>

                        <!-- 🔥 SUCURSAL (select corregido) -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Sucursal *</label>
                            <select 
                                v-model="form.IdSucursal" 
                                class="w-full border rounded-md px-2 py-1.5 text-xs" 
                                :class="{ 'border-red-500': form.errors.IdSucursal }" 
                                :disabled="editando && diario?.Contabilizado === 1"
                            >
                                <option value="">Seleccione una sucursal</option>
                                <option 
                                    v-for="s in sucursales" 
                                    :key="s.id" 
                                    :value="s.id"
                                    :selected="form.IdSucursal == s.id"
                                >
                                    {{ s.nombre }}
                                </option>
                            </select>
                            <!-- 🔥 Debug: muestra la sucursal seleccionada -->
                            <p v-if="form.IdSucursal" class="text-[9px] text-green-600 mt-0.5">
                                ✅ Sucursal seleccionada ID: {{ form.IdSucursal }}
                            </p>
                            <p v-if="form.errors.IdSucursal" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.IdSucursal }}</p>
                        </div>
                    </div>
                    
                    <div v-if="editando && diario?.NumeroDiario > 0" class="mt-3 pt-2 border-t border-gray-100">
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <i class="fas fa-hashtag"></i>
                            <span>N° Diario: <span class="font-semibold text-primary-600">{{ diario.NumeroDiario }}</span></span>
                            <span class="mx-1">|</span>
                            <i class="fas fa-check-circle" :class="diario.Contabilizado === 1 ? 'text-green-600' : 'text-yellow-600'"></i>
                            <span>{{ diario.Contabilizado === 1 ? 'Contabilizado' : 'Pendiente' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Pestaña de Asientos -->
                <div v-if="editando || diarioGuardado" class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <DiarioPropiamenteTab 
                        :diario-id="editando ? diario.IdDiario : diarioId"
                        :asientos-iniciales="asientosList"
                        :cuentas="cuentas"
                        :identificadores="identificadores"
                        :diario-fecha-id="form.IdFecha"
                        :actividad-cliente="actividadCliente"
                        @update="asientosList = $event"
                        @puede-contabilizar="manejarEstadoContabilizacion"
                    />
                </div>

                <!-- Mensaje para nuevo diario -->
                <div v-else class="bg-secondary-50 rounded-lg border border-secondary-200 p-4 text-center">
                    <i class="fas fa-info-circle text-secondary-500 text-sm mb-2 block"></i>
                    <p class="text-xs text-secondary-700">Complete los datos del diario y presione "Guardar" para poder agregar asientos contables.</p>
                </div>

                <!-- Mensaje de diario contabilizado -->
                <div v-if="editando && diario?.Contabilizado === 1" class="mt-4 p-3 rounded-lg bg-green-50 border border-green-200">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-green-600"></i>
                        <span class="text-xs text-green-700">
                            Este diario ya fue contabilizado con el número <strong>{{ diario.NumeroDiario }}</strong>. No se pueden modificar los asientos.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Error (cuando no cuadra) -->
        <div v-if="modalErrorVisible" class="fixed inset-0 z-50 overflow-y-auto" @click.self="modalErrorVisible = false">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all">
                    <div class="flex items-center justify-between px-4 py-3 border-b bg-red-600 rounded-t-lg">
                        <h3 class="text-sm font-semibold text-white">Error de Contabilización</h3>
                        <button @click="modalErrorVisible = false" class="text-white/80 hover:text-white">✕</button>
                    </div>
                    <div class="p-5">
                        <div class="flex items-center justify-center mb-4">
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-center text-gray-800 text-sm font-medium mb-2">El total debe y el total Haber del diario no cuadran</p>
                        <p class="text-center text-gray-500 text-xs mb-4">Revise los montos de los asientos antes de contabilizar.</p>
                        <div class="flex justify-center">
                            <button @click="modalErrorVisible = false" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition">
                                Entendido
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>