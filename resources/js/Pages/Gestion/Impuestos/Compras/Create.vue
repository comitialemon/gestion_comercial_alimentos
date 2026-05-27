<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    compra: Object,
    detalles: Array,
    almacenes: Array,
    tiposFactura: Array,
    proveedores: Array,
    fechas: Array,
    productos: Array,
    editando: Boolean,
})

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

// ==================== FORMULARIO ====================
const form = ref({
    IdCompras: props.compra?.IdCompras || null,
    IdAlmacen: props.compra?.IdAlmacen || '',
    IdTipoFactura: props.compra?.IdTipoFactura || '',
    IdFecha: props.compra?.IdFecha || '',
    NumeroFactura: props.compra?.NumeroFactura || '',
    IdNIT: props.compra?.IdNIT || '',
    NumeroAutorizacion: props.compra?.NumeroAutorizacion || '',
    Observacion: props.compra?.Observacion || '',
})

// ==================== ESTADO ====================
const detallesGrid = ref([])
const guardandoCabecera = ref(false)
const guardandoDetalle = ref(false)
const contabilizando = ref(false)
const errors = ref({})
const mostrarConfirmacion = ref(false)
const cabeceraGuardada = ref(false)

// Estado para editar producto (inline como en Ajustes)
const editandoDetalle = ref(false)
const editandoIndex = ref(null)
const editandoId = ref(null)

// ==================== NUEVO PRODUCTO ====================
const nuevoProducto = ref({
    IdProducto: '',
    Codigo: '',
    Descripcion: '',
    Unidades: 0,
    TotalBolivianos: 0,
})

// ==================== BUSCADOR DE PRODUCTOS ====================
const busquedaProducto = ref('')
const productosFiltrados = computed(() => {
    if (!busquedaProducto.value || busquedaProducto.value.length < 2) return []
    const termino = busquedaProducto.value.toLowerCase()
    return (props.productos || []).filter(p => 
        p.Codigo?.toLowerCase().includes(termino) || 
        p.Descripcion?.toLowerCase().includes(termino)
    )
})

// ==================== BUSCADOR DE PROVEEDORES ====================
const busquedaProveedor = ref('')
const proveedoresFiltrados = computed(() => {
    if (!busquedaProveedor.value || busquedaProveedor.value.length < 2) return []
    const termino = busquedaProveedor.value.toLowerCase()
    return (props.proveedores || []).filter(p => 
        p.ci?.toString().includes(termino) || 
        p.nombre?.toLowerCase().includes(termino)
    )
})

// ==================== PRECIO CALCULADO ====================
const precioCalculado = computed(() => {
    if (nuevoProducto.value.Unidades > 0 && nuevoProducto.value.TotalBolivianos > 0) {
        return (nuevoProducto.value.TotalBolivianos / nuevoProducto.value.Unidades).toFixed(2)
    }
    return '0.00'
})

// ==================== TOTALES ====================
const totalFactura = computed(() => {
    return detallesGrid.value.reduce((sum, item) => sum + (Number(item.TotalBolivianos) || 0), 0).toFixed(2)
})

const mostrarAutorizacion = computed(() => form.value.IdTipoFactura == 1)

// ==================== CARGAR DETALLES ====================
const cargarDetalles = () => {
    if (props.detalles && Array.isArray(props.detalles) && props.detalles.length > 0) {
        detallesGrid.value = props.detalles.map(d => ({
            IdComprasDetalle: d.IdComprasDetalle,
            IdProducto: d.IdProducto,
            Codigo: d.producto?.Codigo || '',
            Descripcion: d.producto?.Descripcion || '',
            Unidades: Number(d.Unidades) || 0,
            TotalBolivianos: Number(d.TotalBolivianos) || 0,
            Precio: Number(d.Precio) || 0,
        }))
    }
}

// ==================== SELECCIONAR PRODUCTO ====================
const seleccionarProducto = (producto) => {
    nuevoProducto.value.IdProducto = producto.id
    nuevoProducto.value.Codigo = producto.Codigo
    nuevoProducto.value.Descripcion = producto.Descripcion
    busquedaProducto.value = `${producto.Codigo} - ${producto.Descripcion}`
}

// ==================== SELECCIONAR PROVEEDOR ====================
const seleccionarProveedor = (proveedor) => {
    form.value.IdNIT = proveedor.id
    busquedaProveedor.value = `${proveedor.ci} - ${proveedor.nombre}`
}

// ==================== LIMPIAR SELECCIÓN ====================
const limpiarSeleccionProducto = () => {
    nuevoProducto.value = {
        IdProducto: '',
        Codigo: '',
        Descripcion: '',
        Unidades: 0,
        TotalBolivianos: 0,
    }
    busquedaProducto.value = ''
    editandoDetalle.value = false
    editandoIndex.value = null
    editandoId.value = null
}

// ==================== EDITAR PRODUCTO ====================
const editarProducto = (index) => {
    const item = detallesGrid.value[index]
    if (!item) return
    
    editandoDetalle.value = true
    editandoIndex.value = index
    editandoId.value = item.IdComprasDetalle
    
    nuevoProducto.value = {
        IdProducto: item.IdProducto,
        Codigo: item.Codigo,
        Descripcion: item.Descripcion,
        Unidades: item.Unidades,
        TotalBolivianos: item.TotalBolivianos,
    }
    busquedaProducto.value = `${item.Codigo} - ${item.Descripcion}`
}

// ==================== ACTUALIZAR PRODUCTO ====================
const actualizarProducto = async () => {
    if (!nuevoProducto.value.IdProducto) {
        toast?.warning('Producto requerido', 'Seleccione un producto')
        return
    }
    
    if (nuevoProducto.value.Unidades <= 0) {
        toast?.warning('Unidades inválidas', 'Deben ser > 0')
        return
    }
    if (nuevoProducto.value.TotalBolivianos <= 0) {
        toast?.warning('Total inválido', 'Debe ser > 0')
        return
    }
    
    guardandoDetalle.value = true
    try {
        const response = await axios.put(`/gestion/compras/actualizar-detalle/${editandoId.value}`, {
            Unidades: nuevoProducto.value.Unidades,
            TotalBolivianos: nuevoProducto.value.TotalBolivianos,
        })
        
        if (response.data.success) {
            detallesGrid.value[editandoIndex.value] = {
                ...detallesGrid.value[editandoIndex.value],
                Unidades: nuevoProducto.value.Unidades,
                TotalBolivianos: nuevoProducto.value.TotalBolivianos,
                Precio: nuevoProducto.value.TotalBolivianos / nuevoProducto.value.Unidades,
            }
            
            // Actualizar total en cabecera
            await axios.put(`/gestion/compras/actualizar-cabecera/${form.value.IdCompras}`, {
                IdAlmacen: form.value.IdAlmacen,
                IdTipoFactura: form.value.IdTipoFactura,
                IdFecha: form.value.IdFecha,
                NumeroFactura: form.value.NumeroFactura,
                IdNIT: form.value.IdNIT,
                NumeroAutorizacion: form.value.NumeroAutorizacion || 0,
                ImporteFactura: parseFloat(totalFactura.value),
                Observacion: form.value.Observacion || '',
            })
            
            limpiarSeleccionProducto()
            toast?.success('Producto actualizado', `${nuevoProducto.value.Descripcion}`)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'No se pudo actualizar')
    } finally {
        guardandoDetalle.value = false
    }
}

// ==================== AGREGAR PRODUCTO ====================
const agregarProducto = async () => {
    if (!nuevoProducto.value.IdProducto) {
        toast?.warning('Producto requerido', 'Seleccione un producto')
        return
    }
    
    if (nuevoProducto.value.Unidades <= 0) {
        toast?.warning('Unidades inválidas', 'Deben ser > 0')
        return
    }
    if (nuevoProducto.value.TotalBolivianos <= 0) {
        toast?.warning('Total inválido', 'Debe ser > 0')
        return
    }
    
    guardandoDetalle.value = true
    try {
        const response = await axios.post('/gestion/compras/agregar-detalle', {
            IdCompras: form.value.IdCompras,
            IdProducto: nuevoProducto.value.IdProducto,
            Unidades: nuevoProducto.value.Unidades,
            TotalBolivianos: nuevoProducto.value.TotalBolivianos,
        })
        
        if (response.data.success) {
            detallesGrid.value.push({
                IdComprasDetalle: response.data.detalle.IdComprasDetalle,
                IdProducto: response.data.detalle.IdProducto,
                Codigo: nuevoProducto.value.Codigo,
                Descripcion: nuevoProducto.value.Descripcion,
                Unidades: nuevoProducto.value.Unidades,
                TotalBolivianos: nuevoProducto.value.TotalBolivianos,
                Precio: nuevoProducto.value.TotalBolivianos / nuevoProducto.value.Unidades,
            })
            
            // Actualizar total en cabecera
            await axios.put(`/gestion/compras/actualizar-cabecera/${form.value.IdCompras}`, {
                IdAlmacen: form.value.IdAlmacen,
                IdTipoFactura: form.value.IdTipoFactura,
                IdFecha: form.value.IdFecha,
                NumeroFactura: form.value.NumeroFactura,
                IdNIT: form.value.IdNIT,
                NumeroAutorizacion: form.value.NumeroAutorizacion || 0,
                ImporteFactura: parseFloat(totalFactura.value),
                Observacion: form.value.Observacion || '',
            })
            
            limpiarSeleccionProducto()
            toast?.success('Producto agregado', `${nuevoProducto.value.Descripcion}`)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'No se pudo agregar')
    } finally {
        guardandoDetalle.value = false
    }
}

// ==================== ELIMINAR PRODUCTO ====================
const eliminarProducto = async (index) => {
    const item = detallesGrid.value[index]
    if (!item) return
    
    if (!confirm(`¿Eliminar ${item.Descripcion}?`)) return
    
    try {
        const response = await axios.delete(`/gestion/compras/eliminar-detalle/${item.IdComprasDetalle}`)
        if (response.data.success) {
            detallesGrid.value.splice(index, 1)
            
            // Actualizar total en cabecera
            await axios.put(`/gestion/compras/actualizar-cabecera/${form.value.IdCompras}`, {
                IdAlmacen: form.value.IdAlmacen,
                IdTipoFactura: form.value.IdTipoFactura,
                IdFecha: form.value.IdFecha,
                NumeroFactura: form.value.NumeroFactura,
                IdNIT: form.value.IdNIT,
                NumeroAutorizacion: form.value.NumeroAutorizacion || 0,
                ImporteFactura: parseFloat(totalFactura.value),
                Observacion: form.value.Observacion || '',
            })
            
            toast?.success('Producto eliminado', item.Descripcion)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', 'No se pudo eliminar')
    }
}

// ==================== CANCELAR EDICIÓN ====================
const cancelarEdicion = () => {
    limpiarSeleccionProducto()
}

// ==================== VALIDAR CAMPOS CABECERA ====================
const validarCamposCabecera = () => {
    const nuevosErrors = {}
    if (!form.value.IdAlmacen) nuevosErrors.IdAlmacen = 'Seleccione un almacén'
    if (!form.value.IdTipoFactura) nuevosErrors.IdTipoFactura = 'Seleccione tipo'
    if (!form.value.IdFecha) nuevosErrors.IdFecha = 'Seleccione fecha'
    if (!form.value.NumeroFactura) nuevosErrors.NumeroFactura = 'Ingrese número'
    if (!form.value.IdNIT) nuevosErrors.IdNIT = 'Seleccione proveedor'
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
        let idCompra = form.value.IdCompras
        
        if (!idCompra) {
            const crearResponse = await axios.post('/gestion/compras/crear')
            if (crearResponse.data.success) {
                idCompra = crearResponse.data.compra.IdCompras
                form.value.IdCompras = idCompra
            } else {
                throw new Error('No se pudo crear la compra')
            }
        }
        
        await axios.put(`/gestion/compras/actualizar-cabecera/${idCompra}`, {
            IdAlmacen: form.value.IdAlmacen,
            IdTipoFactura: form.value.IdTipoFactura,
            IdFecha: form.value.IdFecha,
            NumeroFactura: form.value.NumeroFactura,
            IdNIT: form.value.IdNIT,
            NumeroAutorizacion: form.value.NumeroAutorizacion || 0,
            ImporteFactura: parseFloat(totalFactura.value),
            Observacion: form.value.Observacion || '',
        })
        
        cabeceraGuardada.value = true
        toast?.success('Cabecera guardada', 'Ahora puede agregar productos')
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar la cabecera')
    } finally {
        guardandoCabecera.value = false
    }
}

// ==================== CONTABILIZAR ====================
const contabilizar = () => {
    if (!validarCamposCabecera()) {
        toast?.warning('Datos incompletos', 'Complete la cabecera')
        return
    }
    if (detallesGrid.value.length === 0) {
        toast?.warning('Sin productos', 'Agregue al menos un producto')
        return
    }
    mostrarConfirmacion.value = true
}

const ejecutarContabilizar = async () => {
    contabilizando.value = true
    mostrarConfirmacion.value = false
    try {
        const response = await axios.post(`/gestion/compras/contabilizar/${form.value.IdCompras}`)
        if (response.status === 200) {
            toast?.success('Compra contabilizada', 'Redirigiendo...')
            window.open(`/gestion/compras/${form.value.IdCompras}/pdf`, '_blank')
            window.location.href = '/gestion/compras/create'
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

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    
    cargarDetalles()
    
    if (form.value.IdCompras && detallesGrid.value.length > 0) {
        cabeceraGuardada.value = true
    }
    
    if (props.compra && props.compra.IdNIT && props.compra.IdNIT !== 0) {
        const proveedor = props.proveedores?.find(p => p.id === props.compra.IdNIT)
        if (proveedor) busquedaProveedor.value = `${proveedor.ci} - ${proveedor.nombre}`
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
            <div class="max-w-full lg:max-w-6xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-guindo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-guindo-600 text-sm"></i>
                        </div>
                        <h1 class="text-base sm:text-lg font-bold text-gray-800">
                            {{ editando ? 'Editar Compra' : 'Nueva Compra' }}
                        </h1>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button 
                            @click="guardarCabecera"
                            :disabled="guardandoCabecera"
                            class="bg-guindo-600 hover:bg-guindo-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 flex-1 sm:flex-initial justify-center"
                        >
                            <i v-if="guardandoCabecera" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ guardandoCabecera ? 'Guardando...' : 'Guardar Cabecera' }}
                        </button>
                    </div>
                </div>

                <!-- Alerta de edición -->
                <div v-if="editando" class="bg-amber-50 border border-amber-200 rounded-lg p-2 mb-4">
                    <div class="flex items-center gap-2 text-amber-700 text-xs">
                        <i class="fas fa-edit"></i>
                        <span>Editando compra N° {{ compra?.NumeroCorrelativo || 'Sin número' }}</span>
                    </div>
                </div>

                <!-- Formulario Cabecera -->
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Datos de la Compra</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <!-- Almacén -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Almacén *</label>
                            <select v-model="form.IdAlmacen" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{'border-red-500': errors.IdAlmacen}">
                                <option value="">Seleccione</option>
                                <option v-for="a in almacenes" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                            </select>
                            <p v-if="errors.IdAlmacen" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdAlmacen }}</p>
                        </div>

                        <!-- Tipo Factura -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Tipo Factura *</label>
                            <select v-model="form.IdTipoFactura" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{'border-red-500': errors.IdTipoFactura}">
                                <option value="">Seleccione</option>
                                <option v-for="t in tiposFactura" :key="t.IdTipoFactura" :value="t.IdTipoFactura">{{ t.FacturaRecibo }}</option>
                            </select>
                            <p v-if="errors.IdTipoFactura" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdTipoFactura }}</p>
                        </div>

                        <!-- Fecha -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Fecha *</label>
                            <select v-model="form.IdFecha" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{'border-red-500': errors.IdFecha}">
                                <option value="">Seleccione</option>
                                <option v-for="f in fechas" :key="f.id" :value="f.id">{{ f.fecha_display }}</option>
                            </select>
                            <p v-if="errors.IdFecha" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdFecha }}</p>
                        </div>

                        <!-- N° Documento -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">N° Documento *</label>
                            <input type="number" v-model.number="form.NumeroFactura" class="no-spinner w-full border rounded-md px-2 py-1.5 text-xs" placeholder="Número">
                            <p v-if="errors.NumeroFactura" class="text-red-500 text-[10px] mt-0.5">{{ errors.NumeroFactura }}</p>
                        </div>

                        <!-- N° Autorización (solo para factura) -->
                        <div v-if="mostrarAutorizacion">
                            <label class="block text-gray-600 mb-0.5">N° Autorización</label>
                            <input type="number" v-model.number="form.NumeroAutorizacion" class="no-spinner w-full border rounded-md px-2 py-1.5 text-xs" placeholder="Autorización">
                        </div>

                        <!-- Proveedor -->
                        <div class="sm:col-span-2">
                            <label class="block text-gray-600 mb-0.5">Proveedor (NIT) *</label>
                            <div class="relative">
                                <input type="text" v-model="busquedaProveedor" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{'border-red-500': errors.IdNIT}" placeholder="Buscar por NIT o nombre...">
                                <div v-if="busquedaProveedor && proveedoresFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-32 overflow-y-auto">
                                    <div v-for="prov in proveedoresFiltrados" :key="prov.id" @click="seleccionarProveedor(prov)" class="px-2 py-1.5 hover:bg-gray-100 cursor-pointer border-b text-xs">
                                        <span class="font-mono">{{ prov.ci }}</span> - {{ prov.nombre }}
                                    </div>
                                </div>
                            </div>
                            <p v-if="errors.IdNIT" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdNIT }}</p>
                        </div>

                        <!-- Observación -->
                        <div class="sm:col-span-2 lg:col-span-4">
                            <label class="block text-gray-600 mb-0.5">Observación</label>
                            <textarea v-model="form.Observacion" rows="2" class="w-full border rounded-md px-2 py-1.5 text-xs" placeholder="Notas..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Sección Detalle -->
                <div v-if="cabeceraGuardada" class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Productos</h2>

                    <!-- Formulario de nuevo producto -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2 mb-3">
                        <!-- Buscador de Producto -->
                        <div class="relative sm:col-span-2 lg:col-span-5">
                            <label class="block text-gray-600 text-[10px] mb-0.5">Producto *</label>
                            <input type="text" v-model="busquedaProducto" class="w-full border rounded-md px-2 py-1.5 text-xs" placeholder="Buscar por código o nombre...">
                            <div v-if="busquedaProducto && productosFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-32 overflow-y-auto">
                                <div v-for="prod in productosFiltrados" :key="prod.id" @click="seleccionarProducto(prod)" class="px-2 py-1.5 hover:bg-gray-100 cursor-pointer border-b text-xs">
                                    <span class="font-mono">{{ prod.Codigo }}</span> - {{ prod.Descripcion }}
                                </div>
                            </div>
                        </div>
                        <div class="sm:col-span-1 lg:col-span-2">
                            <label class="block text-gray-600 text-[10px] mb-0.5">Unidades *</label>
                            <input type="number" step="0.0001" v-model.number="nuevoProducto.Unidades" class="no-spinner w-full border rounded-md px-2 py-1.5 text-xs" placeholder="0.0000">
                        </div>
                        <div class="sm:col-span-1 lg:col-span-2">
                            <label class="block text-gray-600 text-[10px] mb-0.5">Total Bs *</label>
                            <input type="number" step="0.01" v-model.number="nuevoProducto.TotalBolivianos" class="no-spinner w-full border rounded-md px-2 py-1.5 text-xs" placeholder="0.00">
                        </div>
                        <div class="sm:col-span-1 lg:col-span-2">
                            <label class="block text-gray-600 text-[10px] mb-0.5">Precio Unit.</label>
                            <input type="text" readonly class="w-full border rounded-md px-2 py-1.5 text-xs bg-gray-100" :value="precioCalculado">
                        </div>
                        <div class="sm:col-span-1 lg:col-span-1 flex items-end gap-2">
                            <button v-if="!editandoDetalle" @click="agregarProducto" :disabled="guardandoDetalle || !nuevoProducto.IdProducto" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 w-full justify-center">
                                <i v-if="guardandoDetalle" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-plus"></i>
                                {{ guardandoDetalle ? 'Agregando...' : 'Agregar' }}
                            </button>
                            <div v-else class="flex gap-2 w-full">
                                <button @click="actualizarProducto" :disabled="guardandoDetalle" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 flex-1 justify-center">
                                    <i v-if="guardandoDetalle" class="fas fa-spinner fa-spin"></i>
                                    <i v-else class="fas fa-save"></i>
                                    {{ guardandoDetalle ? 'Actualizando...' : 'Actualizar' }}
                                </button>
                                <button @click="cancelarEdicion" class="bg-gray-400 hover:bg-gray-500 text-white px-3 py-1.5 rounded text-xs">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de productos -->
                    <div class="overflow-x-auto">
                        <!-- Vista MÓVIL -->
                        <div v-if="isMobile" class="space-y-2">
                            <div v-for="(item, index) in detallesGrid" :key="index" class="bg-gray-50 rounded-lg p-3 border">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <p class="text-xs font-mono text-gray-500">{{ item.Codigo }}</p>
                                        <p class="text-sm font-medium text-gray-800">{{ item.Descripcion }}</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button @click="editarProducto(index)" class="text-amber-500 hover:text-amber-700" title="Editar">
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>
                                        <button @click="eliminarProducto(index)" class="text-red-500 hover:text-red-700" title="Eliminar">
                                            <i class="fas fa-trash-alt text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-2 mt-2 pt-2 border-t border-gray-200">
                                    <div>
                                        <p class="text-[10px] text-gray-400">Unidades</p>
                                        <p class="text-sm font-semibold text-gray-800">{{ Number(item.Unidades).toFixed(4) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400">Precio</p>
                                        <p class="text-sm font-semibold text-gray-800">{{ Number(item.Precio).toFixed(2) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400">Total</p>
                                        <p class="text-sm font-semibold text-guindo-600">{{ Number(item.TotalBolivianos).toFixed(2) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="detallesGrid.length === 0" class="text-center text-gray-400 text-sm py-8">
                                <i class="fas fa-box-open text-2xl mb-2 block"></i>
                                No hay productos agregados
                            </div>
                        </div>

                        <!-- Vista ESCRITORIO -->
                        <div v-else>
                            <table class="min-w-full text-xs border">
                                <thead class="bg-guindo-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-guindo-700">Código</th>
                                        <th class="px-3 py-2 text-left text-guindo-700">Producto</th>
                                        <th class="px-3 py-2 text-right text-guindo-700">Unidades</th>
                                        <th class="px-3 py-2 text-right text-guindo-700">Precio</th>
                                        <th class="px-3 py-2 text-right text-guindo-700">Total Bs</th>
                                        <th class="px-3 py-2 text-center text-guindo-700">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(item, index) in detallesGrid" :key="index" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 font-mono">{{ item.Codigo }}</td>
                                        <td class="px-3 py-2">{{ item.Descripcion }}</td>
                                        <td class="px-3 py-2 text-right">{{ Number(item.Unidades).toFixed(4) }}</td>
                                        <td class="px-3 py-2 text-right">{{ Number(item.Precio).toFixed(2) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold text-guindo-600">{{ Number(item.TotalBolivianos).toFixed(2) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <div class="flex gap-2 justify-center">
                                                <button @click="editarProducto(index)" class="text-amber-500 hover:text-amber-700" title="Editar">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </button>
                                                <button @click="eliminarProducto(index)" class="text-red-500 hover:text-red-700" title="Eliminar">
                                                    <i class="fas fa-trash-alt text-sm"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="detallesGrid.length === 0">
                                        <td colspan="6" class="px-3 py-8 text-center text-gray-400">
                                            <i class="fas fa-box-open text-2xl mb-2 block"></i>
                                            No hay productos agregados
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="detallesGrid.length > 0" class="bg-gray-50 font-semibold">
                                    <tr>
                                        <td colspan="4" class="px-3 py-2 text-right">TOTAL:</td>
                                        <td class="px-3 py-2 text-right text-guindo-600">{{ totalFactura }} Bs</td>
                                        <td class="px-3 py-2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="mt-3 flex justify-end">
                        <button @click="contabilizar" :disabled="contabilizando || detallesGrid.length === 0" class="bg-guindo-600 hover:bg-guindo-700 text-white px-4 py-1.5 rounded text-sm font-medium flex items-center gap-1">
                            <i v-if="contabilizando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-check-circle"></i>
                            {{ contabilizando ? 'Contabilizando...' : 'CONTABILIZAR' }}
                        </button>
                    </div>
                </div>

                <!-- Mensaje si cabecera no guardada -->
                <div v-else class="bg-amber-50 rounded-lg p-4 text-amber-800 text-sm text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    Complete todos los campos de la cabecera y presione "Guardar Cabecera" para comenzar a agregar productos.
                </div>

                <!-- Modal de Confirmación -->
                <div v-if="mostrarConfirmacion" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-lg max-w-sm w-full">
                        <div class="bg-amber-500 p-3 rounded-t-lg">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                                <h3 class="text-white font-semibold text-sm">Confirmar Contabilización</h3>
                            </div>
                        </div>
                        <div class="p-4">
                            <p class="text-gray-700 text-sm mb-3">¿Está seguro de contabilizar esta compra?</p>
                            <p class="text-gray-500 text-xs mb-4">Una vez contabilizada, no se podrá modificar.</p>
                            <div class="flex gap-2">
                                <button @click="cancelarConfirmacion" class="flex-1 py-1.5 rounded bg-gray-200 text-gray-700 text-sm hover:bg-gray-300">Cancelar</button>
                                <button @click="ejecutarContabilizar" class="flex-1 py-1.5 rounded bg-emerald-600 text-white text-sm hover:bg-emerald-700">Sí, Contabilizar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>