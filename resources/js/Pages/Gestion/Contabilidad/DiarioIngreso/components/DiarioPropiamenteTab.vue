<script setup>
import { ref, computed, inject, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    diarioId: Number,
    asientosIniciales: Array,
    cuentas: Array,
    identificadores: Array,
    diarioFechaId: Number,
    actividadCliente: String,
})

const emit = defineEmits(['update', 'puede-contabilizar', 'mostrar-error'])

const toast = inject('toast')

const asientos = ref(props.asientosIniciales || [])
const loading = ref(false)

// Totales
const totalDebe = computed(() => {
    return asientos.value.filter(a => a.D_H === 'D').reduce((sum, a) => sum + (Number(a.MontoBolivianos) || 0), 0)
})

const totalHaber = computed(() => {
    return asientos.value.filter(a => a.D_H === 'H').reduce((sum, a) => sum + (Number(a.MontoBolivianos) || 0), 0)
})

const diferencia = computed(() => totalDebe.value - totalHaber.value)

// Paginación
const currentPage = ref(1)
const itemsPerPage = ref(5)

const totalPages = computed(() => {
    return Math.ceil(asientos.value.length / itemsPerPage.value)
})

const asientosPaginados = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value
    const end = start + itemsPerPage.value
    return asientos.value.slice(start, end)
})

// Verificar si se puede contabilizar
const puedeContabilizar = computed(() => {
    return asientos.value.length > 0 && diferencia.value === 0
})

// Emitir estado para contabilización
const emitirEstado = () => {
    emit('puede-contabilizar', puedeContabilizar.value)
}

watch(diferencia, () => {
    emitirEstado()
})

watch(() => asientos.value.length, () => {
    emitirEstado()
})

// Nueva fila (tarjeta para agregar)
const nuevaFila = ref({
    IdCuenta: '',
    D_H: '',
    MontoBolivianos: 0,
    Glosa: '',
    IdIdentificador: '',
    Deducible: 'D',
    editando: false,
    tipoCambio: 1,
    MontoOtraMoneda: 0,
    idMoneda: 1
})

// Mostrar formulario de nuevo asiento
const mostrarFormulario = ref(false)

const mostrarNuevoAsiento = () => {
    mostrarFormulario.value = true
    nuevaFila.value = {
        IdCuenta: '',
        D_H: '',
        MontoBolivianos: 0,
        MontoOtraMoneda: 0,
        tipoCambio: 1,
        idMoneda: 1,
        Glosa: '',
        IdIdentificador: '',
        Deducible: 'D',
        editando: true
    }
    busquedaCuenta.value = ''
    busquedaIdentificador.value = ''
}

const cancelarNuevoAsiento = () => {
    mostrarFormulario.value = false
    nuevaFila.value.editando = false
}

// Estado de modales
const modalIdentificadorVisible = ref(false)
const nuevoIdentificador = ref({ CI_NIT: '', Nombre: '' })
const guardandoIdentificador = ref(false)

// Búsquedas
const busquedaCuenta = ref('')
const busquedaIdentificador = ref('')

// Cuentas filtradas
const cuentasFiltradas = computed(() => {
    if (!busquedaCuenta.value) return props.cuentas || []
    const termino = busquedaCuenta.value.toLowerCase()
    return (props.cuentas || []).filter(c => 
        c.Cuenta?.toLowerCase().includes(termino) || 
        c.Descripcion?.toLowerCase().includes(termino)
    )
})

// Identificadores filtrados
const identificadoresFiltrados = computed(() => {
    if (!busquedaIdentificador.value) return props.identificadores || []
    const termino = busquedaIdentificador.value.toLowerCase()
    return (props.identificadores || []).filter(i => 
        i.ci?.toString().includes(termino) || 
        i.nombre?.toLowerCase().includes(termino)
    )
})

// Seleccionar cuenta
const seleccionarCuenta = (cuenta) => {
    nuevaFila.value.IdCuenta = cuenta.id
    busquedaCuenta.value = `${cuenta.Cuenta} - ${cuenta.Descripcion}`
    obtenerTipoCambio()
}

// Obtener tipo de cambio
const obtenerTipoCambio = async () => {
    if (!nuevaFila.value.IdCuenta || !props.diarioFechaId) return
    
    try {
        const response = await axios.get(`/api/factor-cambio/${props.diarioFechaId}/${nuevaFila.value.IdCuenta}`)
        if (response.data.success) {
            nuevaFila.value.tipoCambio = response.data.tipoCambio
            nuevaFila.value.idMoneda = response.data.idMoneda
            recalcularMontos()
        }
    } catch (error) {
        console.error('Error obteniendo tipo de cambio:', error)
    }
}

// Seleccionar identificador
const seleccionarIdentificador = (ident) => {
    nuevaFila.value.IdIdentificador = ident.id
    busquedaIdentificador.value = `${ident.ci} - ${ident.nombre}`
}

// Recalcular montos
const recalcularMontos = () => {
    if (nuevaFila.value.idMoneda === 2 || nuevaFila.value.idMoneda === 3) {
        nuevaFila.value.MontoOtraMoneda = nuevaFila.value.MontoBolivianos / nuevaFila.value.tipoCambio
    } else {
        nuevaFila.value.MontoOtraMoneda = nuevaFila.value.MontoBolivianos
    }
}

// Guardar nuevo asiento
const guardarNuevoAsiento = async () => {
    if (!nuevaFila.value.IdCuenta) {
        toast?.error('Error', 'Seleccione una cuenta')
        return
    }
    if (!nuevaFila.value.D_H) {
        toast?.error('Error', 'Seleccione Debe o Haber')
        return
    }
    if (nuevaFila.value.MontoBolivianos <= 0) {
        toast?.error('Error', 'El monto debe ser mayor a 0')
        return
    }
    if (!nuevaFila.value.Glosa) {
        toast?.error('Error', 'La glosa es obligatoria')
        return
    }
    
    loading.value = true
    try {
        const response = await axios.post('/contabilidad/diario-ingreso/asiento', {
            IdDiario: props.diarioId,
            IdCuenta: nuevaFila.value.IdCuenta,
            D_H: nuevaFila.value.D_H,
            MontoBolivianos: nuevaFila.value.MontoBolivianos,
            Glosa: nuevaFila.value.Glosa,
            IdIdentificador: nuevaFila.value.IdIdentificador || null,
            Deducible: nuevaFila.value.Deducible,
        })
        
        if (response.data.success) {
            asientos.value.push(response.data.asiento)
            emit('update', [...asientos.value])
            
            mostrarFormulario.value = false
            nuevaFila.value = {
                IdCuenta: '',
                D_H: '',
                MontoBolivianos: 0,
                MontoOtraMoneda: 0,
                tipoCambio: 1,
                idMoneda: 1,
                Glosa: '',
                IdIdentificador: '',
                Deducible: 'D',
                editando: false
            }
            busquedaCuenta.value = ''
            busquedaIdentificador.value = ''
            
            currentPage.value = totalPages.value
            toast?.success('Éxito', 'Asiento agregado correctamente')
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al agregar')
    } finally {
        loading.value = false
    }
}

// Editar asiento existente
const editarAsiento = (asiento) => {
    asiento.editando = true
    asiento.tipoCambio = asiento.TipoCambio
    asiento.MontoOtraMoneda = asiento.MontoOtraMoneda
    asiento.idMoneda = asiento.cuenta?.IdMoneda || 1
}

const guardarEdicion = async (asiento) => {
    if (asiento.MontoBolivianos <= 0) {
        toast?.error('Error', 'El monto debe ser mayor a 0')
        return
    }
    if (!asiento.Glosa) {
        toast?.error('Error', 'La glosa es obligatoria')
        return
    }
    
    loading.value = true
    try {
        const response = await axios.put(`/contabilidad/diario-ingreso/asiento/${asiento.IdContaPropiamente}`, {
            MontoBolivianos: asiento.MontoBolivianos,
            Glosa: asiento.Glosa,
            IdIdentificador: asiento.IdIdentificador || null,
            Deducible: asiento.Deducible,
        })
        
        if (response.data.success) {
            asiento.editando = false
            emit('update', [...asientos.value])
            toast?.success('Éxito', 'Asiento actualizado correctamente')
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al actualizar')
    } finally {
        loading.value = false
    }
}

// Eliminar asiento
const eliminarAsiento = async (asiento) => {
    if (!confirm(`¿Eliminar este asiento?`)) return
    
    try {
        const response = await axios.delete(`/contabilidad/diario-ingreso/asiento/${asiento.IdContaPropiamente}`)
        if (response.data.success) {
            asientos.value = asientos.value.filter(a => a.IdContaPropiamente !== asiento.IdContaPropiamente)
            emit('update', asientos.value)
            toast?.success('Éxito', 'Asiento eliminado correctamente')
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al eliminar')
    }
}

// Modal de nuevo identificador
const abrirModalIdentificador = () => {
    nuevoIdentificador.value = { CI_NIT: '', Nombre: '' }
    modalIdentificadorVisible.value = true
}

const guardarNuevoIdentificador = async () => {
    if (!nuevoIdentificador.value.CI_NIT) {
        alert('Ingrese el CI/NIT')
        return
    }
    if (!nuevoIdentificador.value.Nombre) {
        alert('Ingrese el nombre')
        return
    }
    
    guardandoIdentificador.value = true
    try {
        const response = await axios.post('/gestion/todos/identificador', {
            CI_NIT: nuevoIdentificador.value.CI_NIT,
            Nombre: nuevoIdentificador.value.Nombre.toUpperCase(),
        })
        
        if (response.data.success) {
            toast?.success('Éxito', 'Identificador creado correctamente')
            modalIdentificadorVisible.value = false
            window.location.reload()
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardandoIdentificador.value = false
    }
}

// Cambiar página
const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page
    }
}

// Formatear números
const formatearNumero = (value, decimals = 2) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    })
}

// Emitir estado inicial
emitirEstado()
</script>

<style scoped>
/* 🔥 Eliminar flechas del input number */
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
    <div class="text-xs">
        <!-- Totales principales (Debe, Haber, Diferencia) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3">
            <div class="bg-emerald-50 rounded-md p-2 text-center border border-emerald-200">
                <p class="text-[10px] text-emerald-600 font-medium uppercase">Debe</p>
                <p class="text-sm font-bold text-emerald-700">{{ formatearNumero(totalDebe) }} Bs</p>
            </div>
            <div class="bg-blue-50 rounded-md p-2 text-center border border-blue-200">
                <p class="text-[10px] text-blue-600 font-medium uppercase">Haber</p>
                <p class="text-sm font-bold text-blue-700">{{ formatearNumero(totalHaber) }} Bs</p>
            </div>
            <div class="rounded-md p-2 text-center border" :class="diferencia === 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                <p class="text-[10px] font-medium uppercase" :class="diferencia === 0 ? 'text-green-600' : 'text-red-600'">Diferencia</p>
                <p class="text-sm font-bold" :class="diferencia === 0 ? 'text-green-700' : 'text-red-700'">
                    {{ formatearNumero(Math.abs(diferencia)) }} Bs
                    <span v-if="diferencia !== 0" class="text-[10px] ml-0.5">{{ diferencia > 0 ? '(Debe)' : '(Haber)' }}</span>
                </p>
            </div>
        </div>

        <!-- Información de la actividad del cliente -->
        <div v-if="actividadCliente" class="mb-3 p-2 bg-primary-50 rounded-md border border-primary-200">
            <div class="flex items-center gap-2">
                <i class="fas fa-tasks text-primary-600 text-[10px]"></i>
                <span class="text-[10px] font-medium text-primary-700">Actividad del Cliente:</span>
                <span class="text-[11px] font-semibold text-primary-800">{{ actividadCliente }}</span>
            </div>
        </div>

        <!-- Botón Nuevo Asiento -->
        <div class="flex justify-end mb-3">
            <button v-if="!mostrarFormulario" @click="mostrarNuevoAsiento" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1 rounded-md text-[11px] flex items-center gap-1">
                <i class="fas fa-plus text-[10px]"></i> Nuevo Asiento
            </button>
        </div>

        <!-- Formulario para nuevo asiento -->
        <div v-if="mostrarFormulario" class="bg-white rounded-md border border-primary-200 shadow-sm overflow-hidden mb-4">
            <div class="bg-primary-50 px-3 py-1.5 border-b border-primary-100">
                <span class="text-[11px] font-semibold text-primary-700">Nuevo Asiento</span>
            </div>
            <div class="p-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Número Cuenta *</label>
                            <div class="relative">
                                <input type="text" v-model="busquedaCuenta" placeholder="Buscar cuenta..." class="w-full border rounded px-2 py-1 text-[11px]" @focus="busquedaCuenta = ''">
                                <div v-if="busquedaCuenta && cuentasFiltradas.length" class="absolute z-10 mt-1 w-full bg-white border rounded shadow-lg max-h-32 overflow-y-auto">
                                    <div v-for="c in cuentasFiltradas" :key="c.id" @click="seleccionarCuenta(c)" class="px-2 py-1 hover:bg-gray-100 cursor-pointer text-[11px] border-b">
                                        <span class="font-mono">{{ c.Cuenta }}</span> - {{ c.Descripcion }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Monto Bolivianos *</label>
                            <input 
                                type="number" 
                                v-model.number="nuevaFila.MontoBolivianos" 
                                @input="recalcularMontos" 
                                step="0.01" 
                                min="0" 
                                class="no-spinner w-full border rounded px-2 py-1 text-[11px] text-right"
                            >
                        </div>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Tipo Cambio</label>
                            <div class="text-[11px] text-gray-700 bg-gray-100 rounded px-2 py-1">
                                {{ formatearNumero(nuevaFila.tipoCambio || 1, 4) }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Monto Otra Moneda</label>
                            <div class="text-[11px] text-gray-700 bg-gray-100 rounded px-2 py-1">
                                {{ formatearNumero(nuevaFila.MontoOtraMoneda || 0, 2) }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Glosa *</label>
                            <input type="text" v-model="nuevaFila.Glosa" class="w-full border rounded px-2 py-1 text-[11px]" placeholder="Descripción">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Identificador</label>
                            <div class="flex gap-1">
                                <div class="relative flex-1">
                                    <input type="text" v-model="busquedaIdentificador" placeholder="Buscar identificador..." class="w-full border rounded px-2 py-1 text-[11px]">
                                    <div v-if="busquedaIdentificador && identificadoresFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded shadow-lg max-h-32 overflow-y-auto">
                                        <div v-for="i in identificadoresFiltrados" :key="i.id" @click="seleccionarIdentificador(i)" class="px-2 py-1 hover:bg-gray-100 cursor-pointer text-[11px] border-b">
                                            <span class="font-mono">{{ i.ci }}</span> - {{ i.nombre }}
                                        </div>
                                    </div>
                                </div>
                                <button @click="abrirModalIdentificador" class="px-2 py-1 bg-primary-600 text-white rounded text-[11px] hover:bg-primary-700">+</button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Debe / Haber *</label>
                            <select v-model="nuevaFila.D_H" class="w-full border rounded px-2 py-1 text-[11px]">
                                <option value="">Seleccione</option>
                                <option value="D">DEBE</option>
                                <option value="H">HABER</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Deducible</label>
                            <select v-model="nuevaFila.Deducible" class="w-full border rounded px-2 py-1 text-[11px]">
                                <option value="D">DEDUCIBLE</option>
                                <option value="N">NO DEDUCIBLE</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Actividad</label>
                            <div class="text-[11px] text-gray-700 bg-gray-100 rounded px-2 py-1">
                                {{ actividadCliente || 'SIN ACTIVIDAD' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-3 pt-2 border-t">
                    <button @click="cancelarNuevoAsiento" class="px-3 py-1 border border-gray-300 rounded text-[11px] text-gray-700 hover:bg-gray-100">Cancelar</button>
                    <button @click="guardarNuevoAsiento" :disabled="loading" class="px-3 py-1 bg-emerald-600 text-white rounded text-[11px] hover:bg-emerald-700 flex items-center gap-1">
                        <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                        Guardar Asiento
                    </button>
                </div>
            </div>
        </div>

        <!-- Tarjetas de asientos existentes -->
        <div class="space-y-3">
            <div v-for="asiento in asientosPaginados" :key="asiento.IdContaPropiamente" class="bg-white rounded-md border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-3 py-1.5 border-b border-gray-200 flex justify-between items-center">
                    <span class="text-[11px] font-semibold text-gray-600">Asiento #{{ asiento.IdContaPropiamente }}</span>
                    <div class="flex gap-2">
                        <button v-if="!asiento.editando" @click="editarAsiento(asiento)" class="text-primary-600 hover:text-primary-800" title="Editar">
                            <i class="fas fa-edit text-[11px]"></i>
                        </button>
                        <button @click="eliminarAsiento(asiento)" class="text-red-500 hover:text-red-700" title="Eliminar">
                            <i class="fas fa-trash-alt text-[11px]"></i>
                        </button>
                    </div>
                </div>
                <div class="p-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <div>
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Número Cuenta</label>
                                <div class="text-[11px] font-semibold text-gray-800">
                                    {{ asiento.cuenta?.Cuenta }} - {{ asiento.cuenta?.Descripcion }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Monto Bolivianos</label>
                                <div v-if="!asiento.editando" class="text-[11px] text-gray-700">
                                    {{ formatearNumero(asiento.MontoBolivianos) }}
                                </div>
                                <input v-else type="number" v-model.number="asiento.MontoBolivianos" step="0.01" class="no-spinner w-full border rounded px-2 py-1 text-[11px] text-right">
                            </div>

                            <div>
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Tipo Cambio</label>
                                <div class="text-[11px] text-gray-700">
                                    {{ formatearNumero(asiento.TipoCambio, 4) }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Monto Otra Moneda</label>
                                <div class="text-[11px] text-gray-700">
                                    {{ formatearNumero(asiento.MontoOtraMoneda, 2) }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Glosa</label>
                                <div v-if="!asiento.editando" class="text-[11px] text-gray-700">
                                    {{ asiento.Glosa }}
                                </div>
                                <input v-else type="text" v-model="asiento.Glosa" class="w-full border rounded px-2 py-1 text-[11px]">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div>
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Identificador</label>
                                <div class="text-[11px] text-gray-700">
                                    {{ asiento.identificador?.CI_NIT || '' }} - {{ asiento.identificador?.Nombre || '' }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Debe / Haber</label>
                                <div v-if="!asiento.editando" class="text-[11px] font-semibold" :class="asiento.D_H === 'D' ? 'text-emerald-600' : 'text-blue-600'">
                                    {{ asiento.D_H === 'D' ? 'DEBE' : 'HABER' }}
                                </div>
                                <select v-else v-model="asiento.D_H" class="w-full border rounded px-2 py-1 text-[11px]">
                                    <option value="D">DEBE</option>
                                    <option value="H">HABER</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Deducible</label>
                                <div v-if="!asiento.editando" class="text-[11px]">
                                    <span :class="asiento.Deducible === 'D' ? 'text-green-600' : 'text-gray-500'">
                                        {{ asiento.Deducible === 'D' ? 'Deducible' : (asiento.Deducible === 'N' ? 'No Deducible' : '-') }}
                                    </span>
                                </div>
                                <select v-else v-model="asiento.Deducible" class="w-full border rounded px-2 py-1 text-[11px]">
                                    <option value="D">DEDUCIBLE</option>
                                    <option value="N">NO DEDUCIBLE</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Actividad</label>
                                <div class="text-[11px] text-gray-700 bg-gray-100 rounded px-2 py-1">
                                    {{ asiento.actividad?.Actividad || actividadCliente || 'SIN ACTIVIDAD' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="asiento.editando" class="flex justify-end gap-2 mt-3 pt-2 border-t">
                        <button @click="asiento.editando = false" class="px-3 py-1 border border-gray-300 rounded text-[11px] text-gray-700 hover:bg-gray-100">Cancelar</button>
                        <button @click="guardarEdicion(asiento)" :disabled="loading" class="px-3 py-1 bg-emerald-600 text-white rounded text-[11px] hover:bg-emerald-700">Guardar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paginación -->
        <div v-if="totalPages > 1" class="flex justify-center items-center gap-2 mt-4 pt-2 border-t border-gray-200">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" class="px-2 py-0.5 border rounded text-[11px] disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100">
                <i class="fas fa-chevron-left text-[10px]"></i>
            </button>
            <span class="text-[11px] text-gray-600">
                Página {{ currentPage }} de {{ totalPages }}
            </span>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" class="px-2 py-0.5 border rounded text-[11px] disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100">
                <i class="fas fa-chevron-right text-[10px]"></i>
            </button>
        </div>

        <!-- Modal Nuevo Identificador -->
        <div v-if="modalIdentificadorVisible" class="fixed inset-0 z-50 overflow-y-auto" @click.self="modalIdentificadorVisible = false">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="modalIdentificadorVisible = false"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
                    <div class="flex items-center justify-between px-4 py-2 border-b bg-primary-600 rounded-t-lg">
                        <h3 class="text-sm font-semibold text-white">Nuevo Identificador</h3>
                        <button @click="modalIdentificadorVisible = false" class="text-white/80 hover:text-white">✕</button>
                    </div>
                    <div class="p-4 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">CI / NIT *</label>
                            <input type="number" v-model.number="nuevoIdentificador.CI_NIT" class="w-full border rounded-md px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Nombre *</label>
                            <input type="text" v-model="nuevoIdentificador.Nombre" @input="nuevoIdentificador.Nombre = nuevoIdentificador.Nombre.toUpperCase()" class="w-full border rounded-md px-2 py-1.5 text-sm uppercase">
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button @click="modalIdentificadorVisible = false" class="px-3 py-1 border rounded-md text-xs text-gray-700 hover:bg-gray-100">Cancelar</button>
                            <button @click="guardarNuevoIdentificador" :disabled="guardandoIdentificador" class="px-3 py-1 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 flex items-center gap-1">
                                <i v-if="guardandoIdentificador" class="fas fa-spinner fa-spin"></i>
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>