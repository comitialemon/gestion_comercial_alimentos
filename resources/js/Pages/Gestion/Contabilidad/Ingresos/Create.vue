<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    ingreso: Object,
    fechas: Array,
    identificadores: Array,
    cuentasDebe: Array,
    cuentasHaber: Array,
    editando: Boolean,
})

// Formulario con datos reactivos
const form = ref({
    IdIngreso: null,
    IdFecha: '',
    IdIdentificador: '',
    IdCuentaDebe: '',
    IdCuentaHaber: '',
    Glosa: '',
    TotalBolivianos: '',
})

const guardando = ref(false)
const errors = ref({})

// Lista de identificadores
const listaIdentificadores = ref(props.identificadores || [])
const busquedaIdentificador = ref('')
const identificadoresFiltrados = computed(() => {
    if (!busquedaIdentificador.value) return listaIdentificadores.value
    const termino = busquedaIdentificador.value.toLowerCase()
    return listaIdentificadores.value.filter(i => 
        i.ci?.toString().includes(termino) || 
        i.nombre?.toLowerCase().includes(termino)
    )
})

// Modal de nuevo identificador
const modalIdentificadorVisible = ref(false)
const nuevoIdentificador = ref({ CI_NIT: '', Nombre: '' })
const guardandoIdentificador = ref(false)

// Prevenir teclas de flecha arriba/abajo en el input de monto
const prevenirFlechas = (event) => {
    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        event.preventDefault()
    }
}

// 🔥 FUNCIÓN PARA RESETEAR EL FORMULARIO
const resetFormulario = () => {
    form.value = {
        IdIngreso: null,
        IdFecha: '',
        IdIdentificador: '',
        IdCuentaDebe: '',
        IdCuentaHaber: '',
        Glosa: '',
        TotalBolivianos: '',
    }
    busquedaIdentificador.value = ''
    errors.value = {}
}

const abrirModalIdentificador = () => {
    nuevoIdentificador.value = { CI_NIT: '', Nombre: '' }
    modalIdentificadorVisible.value = true
}

const guardarNuevoIdentificador = async () => {
    if (!nuevoIdentificador.value.CI_NIT) {
        toast?.error('Error', 'Ingrese el CI/NIT')
        return
    }
    if (!nuevoIdentificador.value.Nombre) {
        toast?.error('Error', 'Ingrese el nombre')
        return
    }
    
    guardandoIdentificador.value = true
    try {
        const response = await axios.post('/gestion/todos/identificador', {
            CI_NIT: nuevoIdentificador.value.CI_NIT,
            Nombre: nuevoIdentificador.value.Nombre.toUpperCase(),
        })
        
        if (response.data.success) {
            listaIdentificadores.value.push({
                id: response.data.identificador?.IdIdentificador || Date.now(),
                ci: nuevoIdentificador.value.CI_NIT,
                nombre: nuevoIdentificador.value.Nombre.toUpperCase()
            })
            
            form.value.IdIdentificador = response.data.identificador?.IdIdentificador || Date.now()
            busquedaIdentificador.value = `${nuevoIdentificador.value.CI_NIT} - ${nuevoIdentificador.value.Nombre.toUpperCase()}`
            modalIdentificadorVisible.value = false
            toast?.success('Éxito', 'Identificador creado correctamente')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardandoIdentificador.value = false
    }
}

// Validar campos
const validarCampos = () => {
    const nuevosErrors = {}
    if (!form.value.IdFecha) nuevosErrors.IdFecha = 'Seleccione fecha'
    if (!form.value.IdIdentificador) nuevosErrors.IdIdentificador = 'Seleccione identificador'
    if (!form.value.IdCuentaDebe) nuevosErrors.IdCuentaDebe = 'Seleccione cuenta'
    if (!form.value.IdCuentaHaber) nuevosErrors.IdCuentaHaber = 'Seleccione cuenta'
    if (!form.value.Glosa) nuevosErrors.Glosa = 'Ingrese glosa'
    if (!form.value.TotalBolivianos || form.value.TotalBolivianos <= 0) nuevosErrors.TotalBolivianos = 'Ingrese monto mayor a 0'
    errors.value = nuevosErrors
    return Object.keys(nuevosErrors).length === 0
}

// Enviar formulario
const submitForm = async () => {
    if (!validarCampos()) {
        toast?.warning('Datos incompletos', 'Complete todos los campos obligatorios')
        return
    }
    
    guardando.value = true
    
    try {
        let response
        if (props.editando) {
            response = await axios.put(`/gestion/ingresos/${form.value.IdIngreso}`, form.value)
        } else {
            response = await axios.post('/gestion/ingresos', form.value)
        }
        
        if (response.status === 200 || response.status === 201) {
            // Abrir PDF
            if (response.data?.pdf_url) {
                window.open(response.data.pdf_url, '_blank')
            }
            
            toast?.success('Éxito', props.editando ? 'Ingreso actualizado correctamente' : 'Ingreso guardado correctamente')
            
            // 🔥 LIMPIAR FORMULARIO
            resetFormulario()
            
            // 🔥 SI ES EDICIÓN, REDIRIGIR A CREATE (para nuevo ingreso)
            if (props.editando) {
                window.location.href = '/gestion/ingresos/create'
            }
        }
    } catch (error) {
        console.error('Error:', error)
        const errorMsg = error.response?.data?.message || 'Error al guardar'
        toast?.error('Error', errorMsg)
    } finally {
        guardando.value = false
    }
}

onMounted(() => {
    // Solo cargar datos si estamos editando o hay un borrador
    if (props.ingreso?.IdIngreso && props.editando) {
        form.value = {
            IdIngreso: props.ingreso.IdIngreso,
            IdFecha: props.ingreso.IdFecha || '',
            IdIdentificador: props.ingreso.IdIdentificador || '',
            IdCuentaDebe: props.ingreso.IdCuentaDebe || '',
            IdCuentaHaber: props.ingreso.IdCuentaHaber || '',
            Glosa: props.ingreso.Glosa || '',
            TotalBolivianos: props.ingreso.TotalBolivianos || '',
        }
        
        if (props.ingreso.IdIdentificador) {
            const ident = listaIdentificadores.value.find(i => i.id === props.ingreso.IdIdentificador)
            if (ident) {
                busquedaIdentificador.value = `${ident.ci} - ${ident.nombre}`
            }
        }
    } else {
        // Si no hay ingreso, resetear formulario
        resetFormulario()
    }
})
</script>

<style scoped>
/* Eliminar flechas del input number */
.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-spinner {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-3xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">{{ editando ? 'Editar Ingreso' : 'Nuevo Ingreso' }}</h1>
                            <p class="text-[10px] text-gray-500">Complete los datos del comprobante de ingreso</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <form @submit.prevent="submitForm" class="space-y-3">
                        <!-- Fecha -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Fecha *</label>
                            <select v-model="form.IdFecha" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.IdFecha }">
                                <option value="">Seleccione una fecha</option>
                                <option v-for="f in fechas" :key="f.id" :value="f.id">{{ f.fecha }}</option>
                            </select>
                            <p v-if="errors.IdFecha" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdFecha }}</p>
                        </div>

                        <!-- Identificador -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Efectivo recibido de: *</label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <input type="text" v-model="busquedaIdentificador" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.IdIdentificador }" placeholder="Buscar por CI/NIT o nombre..." @focus="busquedaIdentificador = ''">
                                    <div v-if="busquedaIdentificador && identificadoresFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-36 overflow-y-auto text-xs">
                                        <div v-for="item in identificadoresFiltrados" :key="item.id" @click="form.IdIdentificador = item.id; busquedaIdentificador = `${item.ci} - ${item.nombre}`" class="px-2 py-1.5 hover:bg-gray-100 cursor-pointer border-b last:border-b-0">
                                            <span class="font-mono">{{ item.ci }}</span> - {{ item.nombre }}
                                        </div>
                                    </div>
                                </div>
                                <button type="button" @click="abrirModalIdentificador" class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs hover:bg-primary-700 flex items-center gap-1">
                                    <i class="fas fa-plus text-[10px]"></i> Nuevo
                                </button>
                            </div>
                            <p v-if="errors.IdIdentificador" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdIdentificador }}</p>
                        </div>

                        <!-- Cuentas en grid de 2 columnas -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Efectivo depositado en: *</label>
                                <select v-model="form.IdCuentaDebe" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.IdCuentaDebe }">
                                    <option value="">Seleccione</option>
                                    <option v-for="c in cuentasDebe" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </select>
                                <p v-if="errors.IdCuentaDebe" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdCuentaDebe }}</p>
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Efectivo entregado como: *</label>
                                <select v-model="form.IdCuentaHaber" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.IdCuentaHaber }">
                                    <option value="">Seleccione</option>
                                    <option v-for="c in cuentasHaber" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </select>
                                <p v-if="errors.IdCuentaHaber" class="text-[10px] text-red-500 mt-0.5">{{ errors.IdCuentaHaber }}</p>
                            </div>
                        </div>

                        <!-- Glosa -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Glosa *</label>
                            <textarea v-model="form.Glosa" rows="2" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': errors.Glosa }" placeholder="Descripción del ingreso..."></textarea>
                            <p v-if="errors.Glosa" class="text-[10px] text-red-500 mt-0.5">{{ errors.Glosa }}</p>
                        </div>

                        <!-- Total Bolivianos (sin flechas) -->
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Total Bolivianos *</label>
                            <div class="relative">
                                <span class="absolute left-2 top-1.5 text-gray-500 text-xs">Bs.</span>
                                <input 
                                    type="number" 
                                    v-model.number="form.TotalBolivianos" 
                                    step="0.01" 
                                    min="0" 
                                    @keydown="prevenirFlechas"
                                    class="no-spinner w-full border rounded-md pl-8 pr-2 py-1.5 text-xs" 
                                    :class="{ 'border-red-500': errors.TotalBolivianos }" 
                                    placeholder="0.00"
                                >
                            </div>
                            <p v-if="errors.TotalBolivianos" class="text-[10px] text-red-500 mt-0.5">{{ errors.TotalBolivianos }}</p>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-2 pt-3 border-t">
                            <button type="button" @click="router.get('/gestion/ingresos')" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="guardando" class="px-4 py-1.5 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1">
                                <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-save text-[10px]"></i>
                                {{ guardando ? 'Guardando...' : 'Guardar Ingreso' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Mensaje informativo -->
                <div class="mt-4 text-center text-[10px] text-gray-400">
                    <i class="fas fa-info-circle"></i> Después de guardar, el formulario se limpiará automáticamente para seguir ingresando más ingresos.
                </div>
            </div>
        </div>

        <!-- Modal Nuevo Identificador -->
        <div v-if="modalIdentificadorVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg max-w-sm w-full overflow-hidden shadow-xl">
                <div class="bg-primary-600 p-3">
                    <h3 class="text-white font-bold text-sm">Nuevo Identificador</h3>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700 mb-0.5">CI / NIT *</label>
                        <input type="number" v-model.number="nuevoIdentificador.CI_NIT" class="w-full border rounded-md px-2 py-1.5 text-xs" placeholder="12345678">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700 mb-0.5">Nombre *</label>
                        <input type="text" v-model="nuevoIdentificador.Nombre" @input="nuevoIdentificador.Nombre = nuevoIdentificador.Nombre.toUpperCase()" class="w-full border rounded-md px-2 py-1.5 text-xs uppercase" placeholder="NOMBRE COMPLETO">
                    </div>
                    <div class="flex gap-2 justify-end pt-2">
                        <button @click="modalIdentificadorVisible = false" class="px-3 py-1.5 border rounded-md text-xs text-gray-700 hover:bg-gray-100">Cancelar</button>
                        <button @click="guardarNuevoIdentificador" :disabled="guardandoIdentificador" class="px-3 py-1.5 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 flex items-center gap-1">
                            <i v-if="guardandoIdentificador" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>