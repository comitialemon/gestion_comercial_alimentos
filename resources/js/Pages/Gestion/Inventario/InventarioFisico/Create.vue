<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
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
    editando: Boolean,
    esBorrador: Boolean,
})

const isMobile = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

const form = ref({
    IdFisico: props.inventarioFisico?.IdFisico || null,
    IdFecha: props.inventarioFisico?.IdFecha || '',
    IdSucursal: props.inventarioFisico?.IdSucursal || '',
    IdAlmacen: props.inventarioFisico?.IdAlmacen || '',
    IdRealizadoPor: props.inventarioFisico?.IdRealizadoPor || '',
    IdEncargadoSucursal: props.inventarioFisico?.IdEncargadoSucursal || '',
    Observacion: props.inventarioFisico?.Observacion || '',
})

// ==================== AUTOCOMPLETE SUCURSAL ====================
const busquedaSucursal = ref('')
const mostrarSucursales = ref(false)
const sucursalesFiltradas = ref([])

const filtrarSucursales = () => {
    const busqueda = busquedaSucursal.value.toLowerCase()
    sucursalesFiltradas.value = props.sucursales.filter(s => 
        s.nombre.toLowerCase().includes(busqueda)
    )
}

const seleccionarSucursal = (sucursal) => {
    form.value.IdSucursal = sucursal.id
    busquedaSucursal.value = sucursal.nombre
    mostrarSucursales.value = false
    errors.value.IdSucursal = null
    form.value.IdAlmacen = ''
    almacenesDisponibles.value = []
    cargarAlmacenes(sucursal.id)
}

const ocultarSucursales = () => {
    setTimeout(() => { mostrarSucursales.value = false }, 200)
}

// ==================== ALMACENES ====================
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
        form.value.IdAlmacen = ''
        if (almacenesDisponibles.value.length === 1) {
            form.value.IdAlmacen = almacenesDisponibles.value[0].id
        }
    } catch (error) {
        console.error('Error cargando almacenes:', error)
        almacenesDisponibles.value = []
    } finally {
        cargandoAlmacenes.value = false
    }
}

// ==================== AUTOCOMPLETE REALIZADO POR ====================
const busquedaRealizadoPor = ref('')
const mostrarRealizadoPor = ref(false)
const realizadosPorFiltrados = ref([])

const filtrarRealizadoPor = () => {
    const busqueda = busquedaRealizadoPor.value.toLowerCase()
    realizadosPorFiltrados.value = props.identificadores.filter(i => 
        i.texto.toLowerCase().includes(busqueda)
    )
}

const seleccionarRealizadoPor = (item) => {
    form.value.IdRealizadoPor = item.id
    busquedaRealizadoPor.value = item.texto
    mostrarRealizadoPor.value = false
    errors.value.IdRealizadoPor = null
}

const ocultarRealizadoPor = () => {
    setTimeout(() => { mostrarRealizadoPor.value = false }, 200)
}

// ==================== AUTOCOMPLETE ENCARGADO SUCURSAL ====================
const busquedaEncargado = ref('')
const mostrarEncargado = ref(false)
const encargadosFiltrados = ref([])

const filtrarEncargado = () => {
    const busqueda = busquedaEncargado.value.toLowerCase()
    encargadosFiltrados.value = props.identificadores.filter(i => 
        i.texto.toLowerCase().includes(busqueda)
    )
}

const seleccionarEncargado = (item) => {
    form.value.IdEncargadoSucursal = item.id
    busquedaEncargado.value = item.texto
    mostrarEncargado.value = false
    errors.value.IdEncargadoSucursal = null
}

const ocultarEncargado = () => {
    setTimeout(() => { mostrarEncargado.value = false }, 200)
}

// ==================== ESTADO ====================
const detallesGrid = ref([])
const guardandoCabecera = ref(false)
const sincronizando = ref(false)
const contabilizando = ref(false)
const errors = ref({})
const mostrarConfirmacion = ref(false)
const cabeceraGuardada = ref(false)

// ==================== BUSCADOR DE PRODUCTOS ====================
const busquedaProducto = ref('')
const productosFiltrados = computed(() => {
    if (!busquedaProducto.value.trim()) {
        return detallesGrid.value
    }
    const busqueda = busquedaProducto.value.toLowerCase()
    return detallesGrid.value.filter(item => 
        item.Codigo?.toLowerCase().includes(busqueda) || 
        item.Descripcion?.toLowerCase().includes(busqueda)
    )
})

// ==================== EDICIÓN DE UNIDADES ====================
const editandoUnidades = ref(null)

const iniciarEdicionUnidades = (index) => {
    editandoUnidades.value = index
}

const cancelarEdicionUnidades = () => {
    editandoUnidades.value = null
}

const actualizarUnidades = async (item, index) => {
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
    if (!form.value.IdFisico) return
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
        const url = form.value.IdFisico 
            ? `/gestion/inventario-fisico/cabecera/${form.value.IdFisico}`
            : '/gestion/inventario-fisico/cabecera'
        
        const response = await axios.put(url, form.value)
        if (response.data.success) {
            if (response.data.id) {
                form.value.IdFisico = response.data.id
            }
            cabeceraGuardada.value = true
            await cargarDetalles()
            toast?.success('Cabecera guardada', 'Productos sincronizados automáticamente')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardandoCabecera.value = false
    }
}

// ==================== SINCRONIZAR ====================
const sincronizarProductos = async () => {
    if (!form.value.IdFisico) return
    sincronizando.value = true
    try {
        const response = await axios.post(`/gestion/inventario-fisico/${form.value.IdFisico}/sincronizar`)
        if (response.data.success) {
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

// ==================== CONTABILIZAR ====================
const contabilizar = () => {
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
            toast?.success('Éxito', response.data.message)
            if (response.data.pdf_url) {
                window.open(response.data.pdf_url, '_blank')
            }
            window.location.href = '/gestion/inventario-fisico'
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al contabilizar')
    } finally {
        contabilizando.value = false
    }
}

const cancelarConfirmacion = () => {
    mostrarConfirmacion.value = false
}

// ==================== INICIALIZAR ====================
const inicializar = () => {
    if (form.value.IdSucursal && props.sucursales) {
        const sucursal = props.sucursales.find(s => s.id === form.value.IdSucursal)
        if (sucursal) {
            busquedaSucursal.value = sucursal.nombre
            cargarAlmacenes(sucursal.id)
        }
    }
    
    if (form.value.IdRealizadoPor && props.identificadores) {
        const realizado = props.identificadores.find(i => i.id === form.value.IdRealizadoPor)
        if (realizado) busquedaRealizadoPor.value = realizado.texto
    }
    
    if (form.value.IdEncargadoSucursal && props.identificadores) {
        const encargado = props.identificadores.find(i => i.id === form.value.IdEncargadoSucursal)
        if (encargado) busquedaEncargado.value = encargado.texto
    }
    
    if (props.detalles && props.detalles.length > 0) {
        detallesGrid.value = props.detalles
        cabeceraGuardada.value = true
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    inicializar()
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

.absolute {
    position: absolute;
    z-index: 50;
}
</style>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header con botones -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Inventario Físico</h1>
                            <p class="text-[10px] text-gray-500">Registro de conteo físico de productos</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button 
                            @click="guardarCabecera"
                            :disabled="guardandoCabecera"
                            class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition"
                        >
                            <i v-if="guardandoCabecera" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ guardandoCabecera ? 'Guardando...' : 'Guardar Cabecera' }}
                        </button>
                        
                        <button 
                            v-if="cabeceraGuardada && detallesGrid.length > 0"
                            @click="contabilizar"
                            :disabled="contabilizando"
                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition"
                        >
                            <i v-if="contabilizando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-check-circle"></i>
                            {{ contabilizando ? 'Contabilizando...' : 'CONTABILIZAR' }}
                        </button>
                    </div>
                </div>

                <!-- Formulario Cabecera -->
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4 relative">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Datos del Inventario Físico</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <div>
                            <label class="block text-gray-600 mb-0.5">Fecha *</label>
                            <select v-model="form.IdFecha" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{'border-red-500': errors.IdFecha}">
                                <option value="">Seleccione</option>
                                <option v-for="f in fechas" :key="f.id" :value="f.id">{{ f.fecha_display }}</option>
                            </select>
                            <p v-if="errors.IdFecha" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdFecha }}</p>
                        </div>

                        <div class="relative">
                            <label class="block text-gray-600 mb-0.5">Sucursal *</label>
                            <input 
                                type="text"
                                v-model="busquedaSucursal"
                                @input="filtrarSucursales"
                                @focus="mostrarSucursales = true"
                                @blur="ocultarSucursales"
                                placeholder="Buscar sucursal..."
                                class="w-full border rounded-md px-2 py-1.5 text-xs"
                                :class="{'border-red-500': errors.IdSucursal}"
                            />
                            <div v-if="mostrarSucursales && sucursalesFiltradas.length > 0" 
                                 class="absolute z-50 mt-1 w-full border rounded-md max-h-48 overflow-y-auto bg-white shadow-lg">
                                <div v-for="suc in sucursalesFiltradas" 
                                     :key="suc.id"
                                     @click="seleccionarSucursal(suc)"
                                     class="p-2 hover:bg-gray-100 cursor-pointer text-xs border-b last:border-b-0">
                                    {{ suc.nombre }}
                                </div>
                            </div>
                            <p v-if="errors.IdSucursal" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdSucursal }}</p>
                        </div>

                        <div>
                            <label class="block text-gray-600 mb-0.5">Almacén *</label>
                            <select 
                                v-model="form.IdAlmacen"
                                class="w-full border rounded-md px-2 py-1.5 text-xs"
                                :class="{'border-red-500': errors.IdAlmacen}"
                                :disabled="cargandoAlmacenes || (!form.IdSucursal)"
                            >
                                <option value="">-- Seleccione --</option>
                                <option v-for="alm in almacenesDisponibles" :key="alm.id" :value="alm.id">
                                    {{ alm.nombre }}
                                </option>
                            </select>
                            <p v-if="errors.IdAlmacen" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdAlmacen }}</p>
                        </div>

                        <div class="relative">
                            <label class="block text-gray-600 mb-0.5">Realizado Por *</label>
                            <input 
                                type="text"
                                v-model="busquedaRealizadoPor"
                                @input="filtrarRealizadoPor"
                                @focus="mostrarRealizadoPor = true"
                                @blur="ocultarRealizadoPor"
                                placeholder="Buscar..."
                                class="w-full border rounded-md px-2 py-1.5 text-xs"
                                :class="{'border-red-500': errors.IdRealizadoPor}"
                            />
                            <div v-if="mostrarRealizadoPor && realizadosPorFiltrados.length > 0" 
                                 class="absolute z-50 mt-1 w-full border rounded-md max-h-48 overflow-y-auto bg-white shadow-lg">
                                <div v-for="item in realizadosPorFiltrados" 
                                     :key="item.id"
                                     @click="seleccionarRealizadoPor(item)"
                                     class="p-2 hover:bg-gray-100 cursor-pointer text-xs border-b">
                                    {{ item.texto }}
                                </div>
                            </div>
                            <p v-if="errors.IdRealizadoPor" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdRealizadoPor }}</p>
                        </div>

                        <div class="relative">
                            <label class="block text-gray-600 mb-0.5">Encargado Sucursal *</label>
                            <input 
                                type="text"
                                v-model="busquedaEncargado"
                                @input="filtrarEncargado"
                                @focus="mostrarEncargado = true"
                                @blur="ocultarEncargado"
                                placeholder="Buscar..."
                                class="w-full border rounded-md px-2 py-1.5 text-xs"
                                :class="{'border-red-500': errors.IdEncargadoSucursal}"
                            />
                            <div v-if="mostrarEncargado && encargadosFiltrados.length > 0" 
                                 class="absolute z-50 mt-1 w-full border rounded-md max-h-48 overflow-y-auto bg-white shadow-lg">
                                <div v-for="item in encargadosFiltrados" 
                                     :key="item.id"
                                     @click="seleccionarEncargado(item)"
                                     class="p-2 hover:bg-gray-100 cursor-pointer text-xs border-b">
                                    {{ item.texto }}
                                </div>
                            </div>
                            <p v-if="errors.IdEncargadoSucursal" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdEncargadoSucursal }}</p>
                        </div>

                        <div class="sm:col-span-2 lg:col-span-4">
                            <label class="block text-gray-600 mb-0.5">Observación</label>
                            <textarea v-model="form.Observacion" rows="2" class="w-full border rounded-md px-2 py-1.5 text-xs" placeholder="Notas..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Sección Detalle -->
                <div v-if="cabeceraGuardada" class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4">
                    <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
                        <h2 class="text-sm font-semibold text-gray-700">Productos</h2>
                        <div class="w-64">
                            <input 
                                type="text"
                                v-model="busquedaProducto"
                                placeholder="🔍 Buscar producto..."
                                class="w-full border rounded-md px-3 py-1.5 text-xs"
                            />
                        </div>
                        <button 
                            @click="sincronizarProductos"
                            :disabled="sincronizando"
                            class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition"
                        >
                            <i v-if="sincronizando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-sync-alt"></i>
                            {{ sincronizando ? 'Sincronizando...' : 'Actualizar Lista' }}
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs border">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left">Código</th>
                                    <th class="px-3 py-2 text-left">Producto</th>
                                    <th class="px-3 py-2 text-right">Saldo</th>
                                    <th class="px-3 py-2 text-right">Conteo Físico</th>
                                    <th class="px-3 py-2 text-right">Ajuste</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(item, idx) in productosFiltrados" :key="item.IdFisicoPropiamente" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono">{{ item.Codigo }}</td>
                                    <td class="px-3 py-2">{{ item.Descripcion }}</td>
                                    <td class="px-3 py-2 text-right">{{ Number(item.UnidadesSaldo).toFixed(2) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <div v-if="editandoUnidades === idx" class="flex gap-1 justify-end">
                                            <input type="number" step="0.01" v-model.number="item.Unidades" class="w-24 border rounded px-1 py-0.5 text-xs text-right">
                                            <button @click="actualizarUnidades(item, idx)" class="bg-green-500 text-white px-1 rounded">✓</button>
                                            <button @click="cancelarEdicionUnidades" class="bg-gray-500 text-white px-1 rounded">✗</button>
                                        </div>
                                        <div v-else class="flex items-center justify-end gap-1">
                                            <span>{{ Number(item.Unidades).toFixed(2) }}</span>
                                            <button @click="iniciarEdicionUnidades(idx)" class="text-blue-500">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold" :class="item.UnidadesAjuste > 0 ? 'text-green-600' : (item.UnidadesAjuste < 0 ? 'text-red-600' : 'text-gray-500')">
                                        {{ item.UnidadesAjuste > 0 ? '+' : '' }}{{ Number(item.UnidadesAjuste).toFixed(2) }}
                                    </td>
                                </tr>
                                <tr v-if="productosFiltrados.length === 0">
                                    <td colspan="5" class="px-3 py-8 text-center text-gray-400">
                                        <i class="fas fa-box-open text-2xl mb-2 block"></i>
                                        No hay productos. Presione "Actualizar Lista"
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="productosFiltrados.length > 0" class="bg-gray-50">
                                <tr>
                                    <td colspan="2" class="px-3 py-2 font-semibold">Resumen</td>
                                    <td class="px-3 py-2"></td>
                                    <td class="px-3 py-2 text-right font-semibold">Total: {{ totalProductos }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">Con Ajuste: {{ totalConAjuste }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div v-else class="bg-amber-50 rounded-lg p-4 text-amber-800 text-sm text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    Complete la cabecera y presione "Guardar Cabecera"
                </div>

                <!-- Modal de Confirmación -->
                <div v-if="mostrarConfirmacion" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-lg max-w-sm w-full">
                        <div class="bg-green-600 p-3 rounded-t-lg">
                            <h3 class="text-white font-semibold text-sm">Confirmar Contabilización</h3>
                        </div>
                        <div class="p-4">
                            <p class="text-gray-700 text-sm mb-3">¿Está seguro de contabilizar este inventario físico?</p>
                            <p class="text-gray-500 text-xs mb-4">Se generarán los ajustes en el inventario.</p>
                            <div class="flex gap-2">
                                <button @click="cancelarConfirmacion" class="flex-1 py-1.5 rounded bg-gray-200 text-gray-700 text-sm">Cancelar</button>
                                <button @click="ejecutarContabilizar" class="flex-1 py-1.5 rounded bg-green-600 text-white text-sm">Sí, Contabilizar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>