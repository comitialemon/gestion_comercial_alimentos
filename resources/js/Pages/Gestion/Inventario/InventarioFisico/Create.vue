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
    productos: Array,
    editando: Boolean,
    esBorrador: Boolean,
})

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

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
const almacenesDisponibles = ref(props.almacenes || [])
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
        if (almacenesDisponibles.value.length === 1) {
            form.value.IdAlmacen = almacenesDisponibles.value[0].id
        }
    } catch (error) {
        console.error('Error:', error)
        almacenesDisponibles.value = []
    } finally {
        cargandoAlmacenes.value = false
    }
}

// Watch para cargar almacenes cuando cambia sucursal
watch(() => form.value.IdSucursal, (newVal) => {
    if (newVal) {
        cargarAlmacenes(newVal)
    }
})

// ==================== ESTADO ====================
const detallesGrid = ref([])
const guardandoCabecera = ref(false)
const sincronizando = ref(false)
const contabilizando = ref(false)
const errors = ref({})
const mostrarConfirmacion = ref(false)
const cabeceraGuardada = ref(false)

// ==================== EDICIÓN DE UNIDADES ====================
const editandoUnidades = ref(null)

const iniciarEdicionUnidades = (index) => {
    editandoUnidades.value = index
}

const cancelarEdicionUnidades = () => {
    editandoUnidades.value = null
}

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

// ==================== Sincronizar Productos ====================
const sincronizarProductos = async () => {
    if (!validarCamposCabecera()) {
        toast?.warning('Datos incompletos', 'Complete la cabecera primero')
        return
    }
    
    sincronizando.value = true
    try {
        const response = await axios.post(`/gestion/inventario-fisico/${form.value.IdFisico}/sincronizar`)
        if (response.data.success) {
            // Recargar detalles después de sincronizar
            await cargarDetalles()
            toast?.success('Sincronizado', 'Lista de productos actualizada')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al sincronizar')
    } finally {
        sincronizando.value = false
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
            cabeceraGuardada.value = true
            toast?.success('Cabecera guardada', 'Ahora puede sincronizar productos')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardandoCabecera.value = false
    }
}

// ==================== CONTABILIZAR ====================
const contabilizar = () => {
    if (!validarCamposCabecera()) {
        toast?.warning('Datos incomplejos', 'Complete la cabecera')
        return
    }
    if (detallesGrid.value.length === 0) {
        toast?.warning('Sin productos', 'Sincronice productos primero')
        return
    }
    mostrarConfirmacion.value = true
}

const ejecutarContabilizar = async () => {
    contabilizando.value = true
    mostrarConfirmacion.value = false
    try {
        const response = await axios.post(`/gestion/inventario-fisico/${form.value.IdFisico}/contabilizar`)
        if (response.data.success) {
            toast?.success('Contabilizado', response.data.message)
            window.location.href = `/gestion/inventario-fisico/${form.value.IdFisico}`
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al contabilizar')
    } finally {
        contabilizando.value = false
    }
}

const cancelarConfirmacion = () => {
    mostrarConfirmacion.value = false
}

// ==================== ELIMINAR BORRADOR ====================
const eliminarBorrador = async () => {
    if (!confirm('¿Eliminar este borrador? Se perderán todos los datos no guardados.')) return
    
    try {
        // 🔥 Usar axios.delete con la ruta correcta
        const response = await axios.delete(`/gestion/inventario-fisico/${form.value.IdFisico}`)
        if (response.data.success) {
            toast?.success('Borrador eliminado', 'Redirigiendo...')
            // Redirigir a create (GET)
            window.location.href = '/gestion/inventario-fisico'
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'No se pudo eliminar')
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    
    // Cargar detalles existentes
    if (props.detalles && props.detalles.length > 0) {
        detallesGrid.value = props.detalles
        cabeceraGuardada.value = true
    }
    
    // Cargar almacenes si hay sucursal seleccionada
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
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">
                                {{ props.esBorrador ? 'Nuevo Inventario Físico' : 'Editar Inventario Físico' }}
                            </h1>
                            <p v-if="!props.esBorrador && props.inventarioFisico?.NumeroCorrelativo" class="text-[10px] text-gray-500">
                                N° {{ props.inventarioFisico.NumeroCorrelativo }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button 
                            v-if="props.esBorrador"
                            @click="eliminarBorrador"
                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition"
                        >
                            <i class="fas fa-trash-alt"></i>
                            Eliminar Borrador
                        </button>
                        <button 
                            @click="guardarCabecera"
                            :disabled="guardandoCabecera"
                            class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 flex-1 sm:flex-initial justify-center transition"
                        >
                            <i v-if="guardandoCabecera" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ guardandoCabecera ? 'Guardando...' : 'Guardar Cabecera' }}
                        </button>
                    </div>
                </div>

                <!-- Alerta de borrador -->
                <div v-if="props.esBorrador" class="bg-amber-50 border border-amber-200 rounded-lg p-2 mb-4">
                    <div class="flex items-center gap-2 text-amber-700 text-xs">
                        <i class="fas fa-info-circle"></i>
                        <span>Borrador en progreso. Complete los datos y sincronice productos.</span>
                    </div>
                </div>

                <!-- Formulario Cabecera -->
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Datos del Inventario Físico</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <!-- Fecha -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Fecha *</label>
                            <select v-model="form.IdFecha" class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:outline-none" :class="{'border-red-500': errors.IdFecha}">
                                <option value="">Seleccione</option>
                                <option v-for="f in fechas" :key="f.id" :value="f.id">{{ f.fecha_display }}</option>
                            </select>
                            <p v-if="errors.IdFecha" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdFecha }}</p>
                        </div>

                        <!-- Sucursal -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Sucursal *</label>
                            <select v-model="form.IdSucursal" class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:outline-none" :class="{'border-red-500': errors.IdSucursal}">
                                <option value="">Seleccione</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                            <p v-if="errors.IdSucursal" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdSucursal }}</p>
                        </div>

                        <!-- Almacén -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Almacén *</label>
                            <select v-model="form.IdAlmacen" class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:outline-none" :class="{'border-red-500': errors.IdAlmacen}" :disabled="cargandoAlmacenes">
                                <option value="">Seleccione</option>
                                <option v-for="a in almacenesDisponibles" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                            </select>
                            <p v-if="cargandoAlmacenes" class="text-gray-400 text-[10px] mt-0.5">Cargando almacenes...</p>
                            <p v-if="errors.IdAlmacen" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdAlmacen }}</p>
                        </div>

                        <!-- Realizado Por -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Realizado Por *</label>
                            <select v-model="form.IdRealizadoPor" class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:outline-none" :class="{'border-red-500': errors.IdRealizadoPor}">
                                <option value="">Seleccione</option>
                                <option v-for="i in identificadores" :key="i.id" :value="i.id">{{ i.texto }}</option>
                            </select>
                            <p v-if="errors.IdRealizadoPor" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdRealizadoPor }}</p>
                        </div>

                        <!-- Encargado Sucursal -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Encargado Sucursal *</label>
                            <select v-model="form.IdEncargadoSucursal" class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:outline-none" :class="{'border-red-500': errors.IdEncargadoSucursal}">
                                <option value="">Seleccione</option>
                                <option v-for="i in identificadores" :key="i.id" :value="i.id">{{ i.texto }}</option>
                            </select>
                            <p v-if="errors.IdEncargadoSucursal" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdEncargadoSucursal }}</p>
                        </div>

                        <!-- Observación -->
                        <div class="sm:col-span-2 lg:col-span-4">
                            <label class="block text-gray-600 mb-0.5">Observación</label>
                            <textarea v-model="form.Observacion" rows="2" class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary-400 focus:outline-none" placeholder="Notas..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Sección Detalle -->
                <div v-if="cabeceraGuardada" class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4">
                    <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
                        <h2 class="text-sm font-semibold text-gray-700">Productos</h2>
                        <button 
                            @click="sincronizarProductos"
                            :disabled="sincronizando"
                            class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition"
                        >
                            <i v-if="sincronizando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-sync-alt"></i>
                            {{ sincronizando ? 'Sincronizando...' : 'Sincronizar Productos' }}
                        </button>
                    </div>

                    <!-- Tabla de productos -->
                    <div class="overflow-x-auto">
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
                                            <input type="number" step="0.01" v-model.number="item.Unidades" class="no-spinner w-24 border rounded px-1 py-0.5 text-xs">
                                            <button @click="actualizarUnidades(index)" class="bg-green-500 text-white px-1 rounded text-[10px]">✓</button>
                                            <button @click="cancelarEdicionUnidades" class="bg-gray-500 text-white px-1 rounded text-[10px]">✗</button>
                                        </div>
                                        <div v-else class="flex items-center gap-1">
                                            <span class="text-sm font-semibold">{{ Number(item.Unidades).toFixed(2) }}</span>
                                            <button @click="iniciarEdicionUnidades(index)" class="text-blue-500 hover:text-blue-700">
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
                            <div v-if="detallesGrid.length === 0" class="text-center text-gray-400 text-sm py-8">
                                <i class="fas fa-box-open text-2xl mb-2 block"></i>
                                No hay productos. Presione "Sincronizar Productos"
                            </div>
                        </div>

                        <!-- Vista ESCRITORIO -->
                        <div v-else>
                            <table class="min-w-full text-xs border">
                                <thead class="bg-primary-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-primary-700">Código</th>
                                        <th class="px-3 py-2 text-left text-primary-700">Producto</th>
                                        <th class="px-3 py-2 text-right text-primary-700">Saldo en Libros</th>
                                        <th class="px-3 py-2 text-right text-primary-700">Conteo Físico</th>
                                        <th class="px-3 py-2 text-right text-primary-700">Ajuste</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(item, index) in detallesGrid" :key="item.IdFisicoPropiamente" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 font-mono">{{ item.Codigo }}</td>
                                        <td class="px-3 py-2">{{ item.Descripcion }}</td>
                                        <td class="px-3 py-2 text-right">{{ Number(item.UnidadesSaldo).toFixed(2) }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <div v-if="editandoUnidades === index" class="flex gap-1 justify-end">
                                                <input type="number" step="0.01" v-model.number="item.Unidades" class="no-spinner w-24 border rounded px-1 py-0.5 text-xs text-right">
                                                <button @click="actualizarUnidades(index)" class="bg-green-500 text-white px-1 rounded text-[10px]">✓</button>
                                                <button @click="cancelarEdicionUnidades" class="bg-gray-500 text-white px-1 rounded text-[10px]">✗</button>
                                            </div>
                                            <div v-else class="flex items-center justify-end gap-1">
                                                <span>{{ Number(item.Unidades).toFixed(2) }}</span>
                                                <button @click="iniciarEdicionUnidades(index)" class="text-blue-500 hover:text-blue-700">
                                                    <i class="fas fa-edit text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold" :class="item.UnidadesAjuste > 0 ? 'text-green-600' : (item.UnidadesAjuste < 0 ? 'text-red-600' : 'text-gray-500')">
                                            {{ item.UnidadesAjuste > 0 ? '+' : '' }}{{ Number(item.UnidadesAjuste).toFixed(2) }}
                                        </td>
                                    </tr>
                                    <tr v-if="detallesGrid.length === 0">
                                        <td colspan="5" class="px-3 py-8 text-center text-gray-400">
                                            <i class="fas fa-box-open text-2xl mb-2 block"></i>
                                            No hay productos. Presione "Sincronizar Productos"
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="detallesGrid.length > 0" class="bg-gray-50">
                                    <tr>
                                        <td colspan="2" class="px-3 py-2 font-semibold">Resumen</td>
                                        <td class="px-3 py-2"></td>
                                        <td class="px-3 py-2 text-right font-semibold">Total Productos: {{ totalProductos }}</td>
                                        <td class="px-3 py-2 text-right font-semibold">Con Ajuste: {{ totalConAjuste }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="mt-3 flex justify-end gap-2">
                        <button 
                            @click="sincronizarProductos"
                            :disabled="sincronizando"
                            class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-1.5 rounded text-sm font-medium flex items-center gap-1 transition"
                        >
                            <i v-if="sincronizando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-sync-alt"></i>
                            {{ sincronizando ? 'Sincronizando...' : 'Sincronizar Productos' }}
                        </button>
                        <button 
                            @click="contabilizar"
                            :disabled="contabilizando || detallesGrid.length === 0"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-1.5 rounded text-sm font-medium flex items-center gap-1 transition"
                        >
                            <i v-if="contabilizando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-check-circle"></i>
                            {{ contabilizando ? 'Contabilizando...' : 'CONTABILIZAR' }}
                        </button>
                    </div>
                </div>

                <!-- Mensaje si cabecera no guardada -->
                <div v-else class="bg-amber-50 rounded-lg p-4 text-amber-800 text-sm text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    Complete todos los campos de la cabecera y presione "Guardar Cabecera" para comenzar.
                </div>

                <!-- Modal de Confirmación -->
                <div v-if="mostrarConfirmacion" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-lg max-w-sm w-full overflow-hidden shadow-xl">
                        <div class="bg-green-600 p-3">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clipboard-check text-white text-lg"></i>
                                <h3 class="text-white font-semibold text-sm">Confirmar Contabilización</h3>
                            </div>
                        </div>
                        <div class="p-4">
                            <p class="text-gray-700 text-sm mb-3">¿Está seguro de contabilizar este inventario físico?</p>
                            <p class="text-gray-500 text-xs mb-4">Se generarán los ajustes en el inventario. Una vez contabilizado, no se podrá modificar.</p>
                            <div class="flex gap-2">
                                <button @click="cancelarConfirmacion" class="flex-1 py-1.5 rounded bg-gray-200 text-gray-700 text-sm hover:bg-gray-300 transition">Cancelar</button>
                                <button @click="ejecutarContabilizar" class="flex-1 py-1.5 rounded bg-green-600 text-white text-sm hover:bg-green-700 transition">Sí, Contabilizar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>