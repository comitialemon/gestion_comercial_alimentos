<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, inject, watch } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    inventarioFisico: Object,
    detalles: Array,
    fechas: Array,
    sucursales: Array,
    almacenes: Array,
    identificadores: Array,
    esBorrador: Boolean,
    esContabilizado: Boolean,  // 🔥 NumeroCorrelativo > 0
})

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)
const handleResize = () => { isMobile.value = window.innerWidth < 768 }

// ==================== FORMULARIO ====================
const form = ref({
    IdFisico: props.inventarioFisico?.IdFisico || null,
    IdFecha: props.inventarioFisico?.IdFecha || '',
    IdSucursal: props.inventarioFisico?.IdSucursal || '',
    IdAlmacen: props.inventarioFisico?.IdAlmacen || '',
    IdRealizadoPor: props.inventarioFisico?.IdRealizadoPor || '',
    IdEncargadoSucursal: props.inventarioFisico?.IdEncargadoSucursal || '',
    Observacion: props.inventarioFisico?.Observacion || '',
})

// ==================== ALMACENES DINÁMICOS ====================
const almacenesDisponibles = ref([])
const cargandoAlmacenes = ref(false)

const cargarAlmacenes = async (sucursalId) => {
    if (!sucursalId) {
        almacenesDisponibles.value = []
        form.value.IdAlmacen = ''
        return
    }
    cargandoAlmacenes.value = true
    try {
        const response = await axios.get(`/api/almacenes-por-sucursal/${sucursalId}`)
        almacenesDisponibles.value = response.data
    } catch (error) {
        console.error('Error:', error)
        almacenesDisponibles.value = []
    } finally {
        cargandoAlmacenes.value = false
    }
}

watch(() => form.value.IdSucursal, (newVal) => {
    if (newVal) cargarAlmacenes(newVal)
})

// ==================== ESTADO ====================
const detallesGrid = ref([])
const guardandoCabecera = ref(false)
const contabilizando = ref(false)
const reprocesando = ref(false)
const errors = ref({})
const mostrarConfirmacion = ref(false)
const confirmacionAccion = ref('') // 'contabilizar' o 'reprocesar'

// ==================== EDICIÓN DE UNIDADES ====================
const editandoUnidades = ref(null)

const iniciarEdicionUnidades = (index) => { editandoUnidades.value = index }
const cancelarEdicionUnidades = () => { editandoUnidades.value = null }

const actualizarUnidades = async (index) => {
    const item = detallesGrid.value[index]
    if (!item) return
    if (item.Unidades < 0) {
        toast?.warning('Unidades inválidas', 'No pueden ser negativas')
        item.Unidades = 0
        return
    }
    try {
        const response = await axios.put(
            `/gestion/inventario-fisico/${form.value.IdFisico}/detalle/${item.IdFisicoPropiamente}/unidades`,
            { Unidades: item.Unidades }
        )
        if (response.data.success) {
            item.UnidadesAjuste = response.data.unidades_ajuste
            toast?.success('Actualizado', `Unidades: ${item.Unidades}`)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', 'No se pudo actualizar')
    } finally {
        editandoUnidades.value = null
    }
}

// ==================== Cargar Detalles ====================
const cargarDetalles = async () => {
    try {
        const response = await axios.get(`/gestion/inventario-fisico/${form.value.IdFisico}/detalles`)
        if (response.data.success) {
            detallesGrid.value = response.data.detalles
        }
    } catch (error) {
        console.error('Error cargando detalles:', error)
    }
}

// ==================== TOTALES ====================
const totalProductos = computed(() => detallesGrid.value.length)
const totalConAjuste = computed(() => detallesGrid.value.filter(d => d.UnidadesAjuste !== 0).length)

// ==================== VALIDAR CABECERA ====================
const validarCamposCabecera = () => {
    const nuevosErrors = {}
    if (!form.value.IdFecha) nuevosErrors.IdFecha = 'Seleccione una fecha'
    if (!form.value.IdSucursal) nuevosErrors.IdSucursal = 'Seleccione una sucursal'
    if (!form.value.IdAlmacen) nuevosErrors.IdAlmacen = 'Seleccione un almacén'
    if (!form.value.IdRealizadoPor) nuevosErrors.IdRealizadoPor = 'Seleccione quien realizó'
    if (!form.value.IdEncargadoSucursal) nuevosErrors.IdEncargadoSucursal = 'Seleccione el encargado'
    errors.value = nuevosErrors
    return Object.keys(nuevosErrors).length === 0
}

// ==================== GUARDAR CABECERA ====================
const guardarCabecera = async () => {
    if (!validarCamposCabecera()) {
        toast?.warning('Datos incompletos', 'Complete todos los campos obligatorios')
        return
    }
    
    guardandoCabecera.value = true
    try {
        const response = await axios.put(`/gestion/inventario-fisico/${form.value.IdFisico}/cabecera`, form.value)
        if (response.data.success) {
            // 🔥 Sincronizar productos automáticamente (como ONAFTERINSERT en Scriptcase)
            const syncResponse = await axios.post(`/gestion/inventario-fisico/${form.value.IdFisico}/sincronizar`)
            if (syncResponse.data.success) {
                await cargarDetalles()
                toast?.success('Cabecera guardada', 'Productos sincronizados automáticamente')
            }
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardandoCabecera.value = false
    }
}

// ==================== CONTABILIZA ====================
const abrirConfirmacionContabilizar = () => {
    if (!validarCamposCabecera()) {
        toast?.warning('Datos incompletos', 'Complete la cabecera')
        return
    }
    if (detallesGrid.value.length === 0) {
        toast?.warning('Sin productos', 'Guarde la cabecera primero')
        return
    }
    confirmacionAccion.value = 'contabilizar'
    mostrarConfirmacion.value = true
}

// ==================== REPROCESA ====================
const abrirConfirmacionReprocesar = () => {
    if (!validarCamposCabecera()) {
        toast?.warning('Datos incompletos', 'Complete la cabecera')
        return
    }
    if (detallesGrid.value.length === 0) {
        toast?.warning('Sin productos', 'Guarde la cabecera primero')
        return
    }
    confirmacionAccion.value = 'reprocesar'
    mostrarConfirmacion.value = true
}

const ejecutarAccion = async () => {
    const accion = confirmacionAccion.value
    if (accion === 'contabilizar') contabilizando.value = true
    else reprocesando.value = true
    
    mostrarConfirmacion.value = false
    try {
        const url = accion === 'contabilizar' 
            ? `/gestion/inventario-fisico/${form.value.IdFisico}/contabilizar`
            : `/gestion/inventario-fisico/${form.value.IdFisico}/reprocesar`
        const response = await axios.post(url)
        if (response.data.success) {
            toast?.success(accion === 'contabilizar' ? 'Contabilizado' : 'Reprocesado', response.data.message)
            window.location.reload()
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || `Error al ${accion === 'contabilizar' ? 'contabilizar' : 'reprocesar'}`)
    } finally {
        contabilizando.value = false
        reprocesando.value = false
    }
}

const cancelarConfirmacion = () => {
    mostrarConfirmacion.value = false
}

// ==================== ELIMINAR BORRADOR ====================
const eliminarBorrador = async () => {
    if (!confirm('¿Eliminar este borrador? Se perderán todos los datos.')) return
    try {
        await axios.delete(`/gestion/inventario-fisico/${form.value.IdFisico}`)
        window.location.href = '/gestion/inventario-fisico'
    } catch (error) {
        toast?.error('Error', 'No se pudo eliminar')
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    
    if (props.detalles && props.detalles.length > 0) {
        detallesGrid.value = props.detalles
    }
    
    if (form.value.IdSucursal) {
        cargarAlmacenes(form.value.IdSucursal)
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<style scoped>
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
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- HEADER CON BOTONES (como Scriptcase) -->
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4">
                    <div class="flex flex-wrap justify-between items-center gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clipboard-list text-primary-600 text-sm"></i>
                            </div>
                            <div>
                                <h1 class="text-base sm:text-lg font-bold text-gray-800">
                                    {{ esBorrador ? 'Nuevo Inventario Físico' : 'Editar Inventario Físico' }}
                                </h1>
                                <p v-if="!esBorrador && inventarioFisico?.NumeroCorrelativo" class="text-[10px] text-gray-500">
                                    N° {{ inventarioFisico.NumeroCorrelativo }}
                                </p>
                            </div>
                        </div>
                        
                        <!-- BOTONERA -->
                        <div class="flex flex-wrap gap-2">
                            <!-- Eliminar Borrador (solo si es borrador) -->
                            <button 
                                v-if="esBorrador"
                                @click="eliminarBorrador"
                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition">
                                <i class="fas fa-trash-alt"></i> Eliminar
                            </button>
                            
                            <!-- Guardar (guarda y sincroniza automáticamente) -->
                            <button 
                                @click="guardarCabecera"
                                :disabled="guardandoCabecera"
                                class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition">
                                <i v-if="guardandoCabecera" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-save"></i>
                                {{ guardandoCabecera ? 'Guardando...' : 'Guardar' }}
                            </button>
                            
                            <!-- 🔥 REPROCESA (solo si ya está contabilizado - NumeroCorrelativo > 0) -->
                            <button 
                                v-if="esContabilizado"
                                @click="abrirConfirmacionReprocesar"
                                :disabled="reprocesando"
                                class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition">
                                <i v-if="reprocesando" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-sync-alt"></i>
                                {{ reprocesando ? 'Reprocesando...' : 'ACTUALIZA' }}
                            </button>
                            
                            <!-- CONTABILIZA -->
                            <button 
                                @click="abrirConfirmacionContabilizar"
                                :disabled="contabilizando"
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition">
                                <i v-if="contabilizando" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-check-circle"></i>
                                {{ contabilizando ? 'Contabilizando...' : 'CONTABILIZA' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Alerta de borrador -->
                <div v-if="esBorrador" class="bg-amber-50 border border-amber-200 rounded-lg p-2 mb-4">
                    <div class="flex items-center gap-2 text-amber-700 text-xs">
                        <i class="fas fa-info-circle"></i>
                        <span>Borrador en progreso. Complete los datos y presione "Guardar".</span>
                    </div>
                </div>

                <!-- Alerta de contabilizado -->
                <div v-if="esContabilizado" class="bg-green-50 border border-green-200 rounded-lg p-2 mb-4">
                    <div class="flex items-center gap-2 text-green-700 text-xs">
                        <i class="fas fa-check-circle"></i>
                        <span>Documento contabilizado. Use ACTUALIZA si necesita actualizar productos.</span>
                    </div>
                </div>

                <!-- Formulario Cabecera -->
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Datos del Inventario Físico</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <div>
                            <label class="block text-gray-600 mb-0.5">Fecha *</label>
                            <select v-model="form.IdFecha" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{'border-red-500': errors.IdFecha}">
                                <option value="">Seleccione</option>
                                <option v-for="f in fechas" :key="f.id" :value="f.id">{{ f.fecha_display }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-600 mb-0.5">Sucursal *</label>
                            <select v-model="form.IdSucursal" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{'border-red-500': errors.IdSucursal}">
                                <option value="">Seleccione</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-600 mb-0.5">Almacén *</label>
                            <select v-model="form.IdAlmacen" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{'border-red-500': errors.IdAlmacen}" :disabled="cargandoAlmacenes">
                                <option value="">Seleccione</option>
                                <option v-for="a in almacenesDisponibles" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-600 mb-0.5">Realizado Por *</label>
                            <select v-model="form.IdRealizadoPor" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{'border-red-500': errors.IdRealizadoPor}">
                                <option value="">Seleccione</option>
                                <option v-for="i in identificadores" :key="i.id" :value="i.id">{{ i.texto }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-600 mb-0.5">Encargado Sucursal *</label>
                            <select v-model="form.IdEncargadoSucursal" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{'border-red-500': errors.IdEncargadoSucursal}">
                                <option value="">Seleccione</option>
                                <option v-for="i in identificadores" :key="i.id" :value="i.id">{{ i.texto }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-4">
                            <label class="block text-gray-600 mb-0.5">Observación</label>
                            <textarea v-model="form.Observacion" rows="2" class="w-full border rounded-md px-2 py-1.5 text-xs" placeholder="Notas..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div v-if="detallesGrid.length > 0" class="bg-white rounded-xl shadow-sm p-3 sm:p-4">
                    <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
                        <h2 class="text-sm font-semibold text-gray-700">Productos</h2>
                        <div class="text-xs text-gray-500">Total: {{ totalProductos }} | Con ajuste: {{ totalConAjuste }}</div>
                    </div>

                    <!-- Vista MÓVIL -->
                    <div v-if="isMobile" class="space-y-2">
                        <div v-for="(item, index) in detallesGrid" :key="item.IdFisicoPropiamente" class="bg-gray-50 rounded-lg p-3 border">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <p class="text-xs font-mono text-gray-500">{{ item.Codigo }}</p>
                                    <p class="text-sm font-medium text-gray-800">{{ item.Descripcion }}</p>
                                </div>
                                <span class="text-[10px] text-gray-400">Saldo: {{ Number(item.UnidadesSaldo).toFixed(2) }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-gray-200">
                                <div>
                                    <p class="text-[10px] text-gray-400">Conteo Físico</p>
                                    <div v-if="editandoUnidades === index" class="flex gap-1">
                                        <input type="number" step="0.01" v-model.number="item.Unidades" class="w-24 border rounded px-1 py-0.5 text-xs">
                                        <button @click="actualizarUnidades(index)" class="bg-green-500 text-white px-1 rounded text-[10px]">✓</button>
                                        <button @click="cancelarEdicionUnidades" class="bg-gray-500 text-white px-1 rounded text-[10px]">✗</button>
                                    </div>
                                    <div v-else class="flex items-center gap-1">
                                        <span class="text-sm font-semibold">{{ Number(item.Unidades).toFixed(2) }}</span>
                                        <button @click="iniciarEdicionUnidades(index)" class="text-blue-500">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400">Ajuste</p>
                                    <p class="text-sm font-semibold" :class="item.UnidadesAjuste > 0 ? 'text-green-600' : (item.UnidadesAjuste < 0 ? 'text-red-600' : 'text-gray-500')">
                                        {{ item.UnidadesAjuste > 0 ? '+' : '' }}{{ Number(item.UnidadesAjuste).toFixed(2) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vista ESCRITORIO -->
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-xs border">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left">Código</th>
                                    <th class="px-3 py-2 text-left">Producto</th>
                                    <th class="px-3 py-2 text-right">Saldo en Libros</th>
                                    <th class="px-3 py-2 text-right">Conteo Físico</th>
                                    <th class="px-3 py-2 text-right">Ajuste</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(item, index) in detallesGrid" :key="item.IdFisicoPropiamente" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono">{{ item.Codigo }}</td>
                                    <td class="px-3 py-2">{{ item.Descripcion }}</td>
                                    <td class="px-3 py-2 text-right">{{ Number(item.UnidadesSaldo).toFixed(2) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <div v-if="editandoUnidades === index" class="flex gap-1 justify-end">
                                            <input type="number" step="0.01" v-model.number="item.Unidades" class="w-24 border rounded px-1 py-0.5 text-xs text-right">
                                            <button @click="actualizarUnidades(index)" class="bg-green-500 text-white px-1 rounded text-[10px]">✓</button>
                                            <button @click="cancelarEdicionUnidades" class="bg-gray-500 text-white px-1 rounded text-[10px]">✗</button>
                                        </div>
                                        <div v-else class="flex items-center justify-end gap-1">
                                            <span>{{ Number(item.Unidades).toFixed(2) }}</span>
                                            <button @click="iniciarEdicionUnidades(index)" class="text-blue-500">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold" :class="item.UnidadesAjuste > 0 ? 'text-green-600' : (item.UnidadesAjuste < 0 ? 'text-red-600' : 'text-gray-500')">
                                        {{ item.UnidadesAjuste > 0 ? '+' : '' }}{{ Number(item.UnidadesAjuste).toFixed(2) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="detallesGrid.length > 0" class="bg-gray-50">
                                <tr>
                                    <td colspan="2" class="px-3 py-2 font-semibold">Resumen</td>
                                    <td class="px-3 py-2"></td>
                                    <td class="px-3 py-2 text-right font-semibold">Total: {{ totalProductos }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">Ajuste: {{ totalConAjuste }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Mensaje si no hay productos -->
                <div v-else class="bg-white rounded-xl shadow-sm p-8 text-center">
                    <i class="fas fa-box-open text-gray-300 text-4xl mb-3 block"></i>
                    <p class="text-gray-500">Complete los datos y presione "Guardar" para sincronizar productos.</p>
                </div>

                <!-- Modal de Confirmación -->
                <div v-if="mostrarConfirmacion" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-lg max-w-sm w-full overflow-hidden shadow-xl">
                        <div class="p-4">
                            <p class="text-gray-700 text-sm mb-3">
                                {{ confirmacionAccion === 'contabilizar' 
                                    ? '¿Está seguro de CONTABILIZAR este inventario físico?' 
                                    : '¿Está seguro de REPROCESAR este inventario físico?' }}
                            </p>
                            <p class="text-gray-500 text-xs mb-4">
                                {{ confirmacionAccion === 'contabilizar' 
                                    ? 'Se generará el número correlativo y los ajustes en el inventario.'
                                    : 'Se actualizarán los saldos y ajustes manteniendo el mismo número correlativo.' }}
                            </p>
                            <div class="flex gap-2">
                                <button @click="cancelarConfirmacion" class="flex-1 py-1.5 rounded bg-gray-200 text-gray-700 text-sm hover:bg-gray-300">Cancelar</button>
                                <button @click="ejecutarAccion" class="flex-1 py-1.5 rounded text-white text-sm" :class="confirmacionAccion === 'contabilizar' ? 'bg-green-600 hover:bg-green-700' : 'bg-amber-600 hover:bg-amber-700'">
                                    Sí, {{ confirmacionAccion === 'contabilizar' ? 'Contabilizar' : 'Reprocesar' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>