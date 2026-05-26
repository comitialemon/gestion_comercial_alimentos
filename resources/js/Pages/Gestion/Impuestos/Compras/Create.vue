<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted, inject } from 'vue'
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

// Formulario de cabecera
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

// Estado
const detallesGrid = ref([])
const guardandoCabecera = ref(false)
const guardandoDetalle = ref(false)
const contabilizando = ref(false)
const errors = ref({})
const mostrarConfirmacion = ref(false)
const cabeceraGuardada = ref(false)

// Editar producto
const editandoDetalle = ref(false)
const editandoDetalleIndex = ref(null)
const editandoDetalleId = ref(null)
const unidadesEdit = ref(0)
const totalEdit = ref(0)

// Buscador de productos
const busquedaProducto = ref('')
const productosFiltrados = computed(() => {
    if (!busquedaProducto.value) return props.productos || []
    const termino = busquedaProducto.value.toLowerCase()
    return (props.productos || []).filter(p => 
        p.Codigo?.toLowerCase().includes(termino) || 
        p.Descripcion?.toLowerCase().includes(termino)
    )
})

const productoSeleccionado = ref(null)

// Buscador de proveedores
const busquedaProveedor = ref('')
const proveedoresFiltrados = computed(() => {
    if (!busquedaProveedor.value) return props.proveedores || []
    const termino = busquedaProveedor.value.toLowerCase()
    return (props.proveedores || []).filter(p => 
        p.ci?.toString().includes(termino) || 
        p.nombre?.toLowerCase().includes(termino)
    )
})

// Total de la compra
const importeFacturaCalculado = computed(() => {
    return detallesGrid.value.reduce((sum, item) => sum + (Number(item.TotalBolivianos) || 0), 0)
})

const totalFactura = computed(() => importeFacturaCalculado.value.toFixed(2))
const importeCreditoFiscal = computed(() => importeFacturaCalculado.value.toFixed(2))
const mostrarAutorizacion = computed(() => form.value.IdTipoFactura == 1)

// Validar campos obligatorios
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

// Guardar cabecera
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
            ImporteFactura: importeFacturaCalculado.value,
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

// Cargar detalles existentes
const cargarDetalles = () => {
    if (props.detalles && props.detalles.length) {
        detallesGrid.value = props.detalles.map(d => ({
            IdComprasDetalle: d.IdComprasDetalle,
            IdProducto: d.IdProducto,
            Codigo: d.producto?.Codigo || '',
            Descripcion: d.producto?.Descripcion || '',
            Unidades: d.Unidades,
            TotalBolivianos: d.TotalBolivianos,
            Precio: d.Precio
        }))
    }
}

const seleccionarProducto = (producto) => {
    productoSeleccionado.value = producto
    busquedaProducto.value = `${producto.Codigo} - ${producto.Descripcion}`
}

const limpiarSeleccionProducto = () => {
    productoSeleccionado.value = null
    busquedaProducto.value = ''
}

// Agregar producto
const agregarProducto = async () => {
    if (!productoSeleccionado.value) {
        toast?.warning('Producto requerido', 'Seleccione un producto')
        return
    }
    
    const unidadesInput = document.getElementById('unidades_input')
    const totalInput = document.getElementById('total_input')
    
    const unidades = parseFloat(unidadesInput?.value) || 0
    const totalBolivianos = parseFloat(totalInput?.value) || 0
    
    if (unidades <= 0) {
        toast?.warning('Unidades inválidas', 'Deben ser > 0')
        return
    }
    if (totalBolivianos <= 0) {
        toast?.warning('Total inválido', 'Debe ser > 0')
        return
    }
    
    const precio = totalBolivianos / unidades
    
    guardandoDetalle.value = true
    try {
        const response = await axios.post('/gestion/compras/agregar-detalle', {
            IdCompras: form.value.IdCompras,
            IdProducto: productoSeleccionado.value.id,
            Unidades: unidades,
            TotalBolivianos: totalBolivianos,
        })
        
        if (response.data.success) {
            const nombreProducto = productoSeleccionado.value.Descripcion || 'Producto'
            
            detallesGrid.value.push({
                IdComprasDetalle: response.data.detalle.IdComprasDetalle,
                IdProducto: response.data.detalle.IdProducto,
                Codigo: productoSeleccionado.value.Codigo,
                Descripcion: productoSeleccionado.value.Descripcion,
                Unidades: unidades,
                TotalBolivianos: totalBolivianos,
                Precio: precio
            })
            
            // Actualizar total en cabecera
            const nuevoTotal = importeFacturaCalculado.value
            await axios.put(`/gestion/compras/actualizar-cabecera/${form.value.IdCompras}`, {
                ...form.value,
                ImporteFactura: nuevoTotal
            })
            
            limpiarSeleccionProducto()
            if (unidadesInput) unidadesInput.value = ''
            if (totalInput) totalInput.value = ''
            
            toast?.success('Producto agregado', nombreProducto)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'No se pudo agregar el producto')
    } finally {
        guardandoDetalle.value = false
    }
}

// Eliminar producto
const eliminarProducto = async (index) => {
    const item = detallesGrid.value[index]
    if (!item) return
    
    try {
        const response = await axios.delete(`/gestion/compras/eliminar-detalle/${item.IdComprasDetalle}`)
        if (response.data.success) {
            detallesGrid.value.splice(index, 1)
            
            // Actualizar total en cabecera
            const nuevoTotal = importeFacturaCalculado.value
            await axios.put(`/gestion/compras/actualizar-cabecera/${form.value.IdCompras}`, {
                ...form.value,
                ImporteFactura: nuevoTotal
            })
            
            toast?.success('Producto eliminado', item.Descripcion)
        }
    } catch (error) {
        console.error('Error al eliminar:', error)
        toast?.error('Error', error.response?.data?.message || 'No se pudo eliminar')
    }
}

// 🔥 EDITAR PRODUCTO
const abrirEditarProducto = (item, index) => {
    editandoDetalle.value = true
    editandoDetalleIndex.value = index
    editandoDetalleId.value = item.IdComprasDetalle
    unidadesEdit.value = item.Unidades
    totalEdit.value = item.TotalBolivianos
}

const cerrarEditarProducto = () => {
    editandoDetalle.value = false
    editandoDetalleIndex.value = null
    editandoDetalleId.value = null
    unidadesEdit.value = 0
    totalEdit.value = 0
}

const guardarEditarProducto = async () => {
    if (unidadesEdit.value <= 0) {
        toast?.warning('Unidades inválidas', 'Deben ser mayores a 0')
        return
    }
    if (totalEdit.value <= 0) {
        toast?.warning('Total inválido', 'Debe ser mayor a 0')
        return
    }
    
    const precio = totalEdit.value / unidadesEdit.value
    
    try {
        const response = await axios.put(`/gestion/compras/actualizar-detalle/${editandoDetalleId.value}`, {
            Unidades: unidadesEdit.value,
            TotalBolivianos: totalEdit.value,
        })
        
        if (response.data.success) {
            // Actualizar localmente
            detallesGrid.value[editandoDetalleIndex.value] = {
                ...detallesGrid.value[editandoDetalleIndex.value],
                Unidades: unidadesEdit.value,
                TotalBolivianos: totalEdit.value,
                Precio: precio
            }
            
            // Actualizar total en cabecera
            const nuevoTotal = importeFacturaCalculado.value
            await axios.put(`/gestion/compras/actualizar-cabecera/${form.value.IdCompras}`, {
                ...form.value,
                ImporteFactura: nuevoTotal
            })
            
            toast?.success('Producto actualizado', 'Los cambios se guardaron correctamente')
            cerrarEditarProducto()
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'No se pudo actualizar el producto')
    }
}

const calcularPrecioDesdeCampos = () => {
    const unidades = parseFloat(document.getElementById('unidades_input')?.value) || 0
    const total = parseFloat(document.getElementById('total_input')?.value) || 0
    const precioSpan = document.getElementById('precio_display')
    if (precioSpan && unidades > 0 && total > 0) {
        precioSpan.value = (total / unidades).toFixed(2)
    } else if (precioSpan) {
        precioSpan.value = '0.00'
    }
}

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
            // 🔥 Redirigir a CREATE para seguir ingresando más compras
            window.location.href = '/gestion/compras/create'
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al contabilizar')
    } finally {
        contabilizando.value = false
    }
}

const cancelarConfirmacion = () => mostrarConfirmacion.value = false

onMounted(() => {
    cargarDetalles()
    
    if (form.value.IdCompras && detallesGrid.value.length > 0) {
        cabeceraGuardada.value = true
    }
    
    if (props.compra && props.compra.IdNIT && props.compra.IdNIT !== 0) {
        const proveedor = props.proveedores?.find(p => p.id === props.compra.IdNIT)
        if (proveedor) busquedaProveedor.value = `${proveedor.ci} - ${proveedor.nombre}`
    }
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
    <div class="min-h-screen bg-gray-100 py-3 px-2 sm:py-4 sm:px-3 md:px-4">
        <div class="max-w-full lg:max-w-6xl mx-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-3 sm:mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-guindo-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus-circle text-guindo-600 text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-base sm:text-lg font-bold text-gray-800">
                            {{ editando ? 'Editar Compra' : 'Nueva Compra' }}
                        </h1>
                        <p class="text-[10px] text-gray-500 hidden xs:block">
                            {{ editando ? 'Modifique los datos de la compra' : 'Complete los datos de la compra' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Alerta de edición -->
            <div v-if="editando" class="bg-yellow-50 border border-yellow-200 rounded-lg p-2 sm:p-3 mb-3 sm:mb-4">
                <div class="flex items-center gap-2 text-yellow-700 text-[10px] sm:text-xs">
                    <i class="fas fa-edit"></i>
                    <span>Editando compra N° {{ compra?.NumeroCorrelativo || 'Sin número' }}</span>
                </div>
            </div>

            <!-- Formulario Cabecera -->
            <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-3 sm:mb-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-0 mb-3">
                    <h2 class="text-xs sm:text-sm font-semibold text-gray-700">Datos de la Compra</h2>
                    <button 
                        @click="guardarCabecera" 
                        :disabled="guardandoCabecera" 
                        class="bg-guindo-600 hover:bg-guindo-700 text-white px-2 sm:px-3 py-1 rounded text-[10px] sm:text-xs flex items-center gap-1 w-full sm:w-auto justify-center"
                    >
                        <i v-if="guardandoCabecera" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ guardandoCabecera ? 'Guardando...' : 'Guardar Cabecera' }}
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3 text-xs">
                    <div>
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">Almacén *</label>
                        <select v-model="form.IdAlmacen" class="w-full border rounded px-2 py-1.5 text-xs">
                            <option value="">Seleccione</option>
                            <option v-for="a in almacenes" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                        </select>
                        <p v-if="errors.IdAlmacen" class="text-red-500 text-[9px] sm:text-[10px]">{{ errors.IdAlmacen }}</p>
                    </div>

                    <div>
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">Tipo Factura *</label>
                        <select v-model="form.IdTipoFactura" class="w-full border rounded px-2 py-1.5 text-xs">
                            <option value="">Seleccione</option>
                            <option v-for="t in tiposFactura" :key="t.IdTipoFactura" :value="t.IdTipoFactura">{{ t.IdTipoFactura }} - {{ t.FacturaRecibo }}</option>
                        </select>
                        <p v-if="errors.IdTipoFactura" class="text-red-500 text-[9px] sm:text-[10px]">{{ errors.IdTipoFactura }}</p>
                    </div>

                    <div>
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">Fecha *</label>
                        <select v-model="form.IdFecha" class="w-full border rounded px-2 py-1.5 text-xs">
                            <option value="">Seleccione</option>
                            <option v-for="f in fechas" :key="f.id" :value="f.id">{{ f.fecha_display }}</option>
                        </select>
                        <p v-if="errors.IdFecha" class="text-red-500 text-[9px] sm:text-[10px]">{{ errors.IdFecha }}</p>
                    </div>

                    <div>
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">N° Documento *</label>
                        <input 
                            type="number" 
                            v-model.number="form.NumeroFactura" 
                            class="no-spinner w-full border rounded px-2 py-1.5 text-xs" 
                            placeholder="Número"
                        >
                        <p v-if="errors.NumeroFactura" class="text-red-500 text-[9px] sm:text-[10px]">{{ errors.NumeroFactura }}</p>
                    </div>

                    <div v-if="mostrarAutorizacion">
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">N° Autorización</label>
                        <input 
                            type="number" 
                            v-model.number="form.NumeroAutorizacion" 
                            class="no-spinner w-full border rounded px-2 py-1.5 text-xs" 
                            placeholder="Autorización"
                        >
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">Proveedor (NIT) *</label>
                        <div class="relative">
                            <input type="text" v-model="busquedaProveedor" class="w-full border rounded px-2 py-1.5 text-xs" placeholder="Buscar por NIT o nombre..." @focus="busquedaProveedor = ''">
                            <div v-if="busquedaProveedor && proveedoresFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded shadow-lg max-h-32 overflow-y-auto">
                                <div v-for="prov in proveedoresFiltrados" :key="prov.id" @click="form.IdNIT = prov.id; busquedaProveedor = `${prov.ci} - ${prov.nombre}`" class="px-2 py-1 hover:bg-gray-100 cursor-pointer border-b text-xs">
                                    <span class="font-mono">{{ prov.ci }}</span> - {{ prov.nombre }}
                                </div>
                            </div>
                        </div>
                        <p v-if="errors.IdNIT" class="text-red-500 text-[9px] sm:text-[10px]">{{ errors.IdNIT }}</p>
                    </div>

                    <div class="sm:col-span-2 md:col-span-3 lg:col-span-4">
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">Observación</label>
                        <textarea v-model="form.Observacion" rows="2" class="w-full border rounded px-2 py-1.5 text-xs" placeholder="Notas..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Sección Detalle -->
            <div v-if="cabeceraGuardada" class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-3 sm:mb-4">
                <h2 class="text-xs sm:text-sm font-semibold text-gray-700 mb-2">Productos</h2>

                <!-- Buscador de Producto y campos -->
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 mb-2">
                    <div class="sm:col-span-5 relative">
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">Producto</label>
                        <input type="text" v-model="busquedaProducto" class="w-full border rounded px-2 py-1.5 text-xs" placeholder="Buscar por código o nombre..." @focus="busquedaProducto = ''">
                        <div v-if="busquedaProducto && productosFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded shadow-lg max-h-32 overflow-y-auto">
                            <div v-for="prod in productosFiltrados" :key="prod.id" @click="seleccionarProducto(prod)" class="px-2 py-1 hover:bg-gray-100 cursor-pointer border-b text-xs">
                                <span class="font-mono">{{ prod.Codigo }}</span> - {{ prod.Descripcion }}
                            </div>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">Unidades</label>
                        <input id="unidades_input" type="number" step="0.0001" @input="calcularPrecioDesdeCampos" class="no-spinner w-full border rounded px-2 py-1.5 text-xs" placeholder="0.0000">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">Total Bs</label>
                        <input id="total_input" type="number" step="0.01" @input="calcularPrecioDesdeCampos" class="no-spinner w-full border rounded px-2 py-1.5 text-xs" placeholder="0.00">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-gray-600 mb-0.5 text-[10px] sm:text-xs">Precio</label>
                        <input id="precio_display" type="text" readonly class="w-full border rounded px-2 py-1.5 text-xs bg-gray-100" value="0.00">
                    </div>
                </div>

                <div class="flex justify-end mb-3">
                    <button @click="agregarProducto" :disabled="guardandoDetalle || !productoSeleccionado" class="bg-guindo-600 hover:bg-guindo-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 w-full sm:w-auto justify-center">
                        <i v-if="guardandoDetalle" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-plus"></i>
                        {{ guardandoDetalle ? 'Agregando...' : 'Agregar' }}
                    </button>
                </div>

                <!-- Tabla de productos responsive -->
                <div class="overflow-x-auto -mx-3 sm:mx-0">
                    <div class="min-w-full inline-block align-middle">
                        <div class="overflow-hidden">
                            <!-- Vista para MÓVIL (tarjetas de producto) -->
                            <div class="block sm:hidden space-y-2">
                                <div v-for="(item, index) in detallesGrid" :key="index" class="bg-gray-50 rounded-lg p-2 border">
                                    <div class="grid grid-cols-2 gap-1 text-xs">
                                        <div class="font-semibold text-gray-600">Código:</div>
                                        <div class="font-mono">{{ item.Codigo }}</div>
                                        
                                        <div class="font-semibold text-gray-600">Producto:</div>
                                        <div class="break-words">{{ item.Descripcion }}</div>
                                        
                                        <div class="font-semibold text-gray-600">Unidades:</div>
                                        <div>{{ Number(item.Unidades).toFixed(4) }}</div>
                                        
                                        <div class="font-semibold text-gray-600">Total Bs:</div>
                                        <div class="font-bold text-guindo-600">{{ Number(item.TotalBolivianos).toFixed(2) }}</div>
                                        
                                        <div class="font-semibold text-gray-600">Precio:</div>
                                        <div>{{ Number(item.Precio).toFixed(2) }}</div>
                                    </div>
                                    <div class="flex gap-2 justify-end mt-2 pt-1 border-t">
                                        <button @click="abrirEditarProducto(item, index)" class="text-blue-600 text-xs flex items-center gap-1 px-2 py-1 rounded bg-blue-50">
                                            <i class="fas fa-edit text-[10px]"></i> Editar
                                        </button>
                                        <button @click="eliminarProducto(index)" class="text-red-600 text-xs flex items-center gap-1 px-2 py-1 rounded bg-red-50">
                                            <i class="fas fa-trash-alt text-[10px]"></i> Eliminar
                                        </button>
                                    </div>
                                </div>
                                <div v-if="detallesGrid.length === 0" class="text-center text-gray-400 text-xs py-4">
                                    No hay productos agregados
                                </div>
                            </div>

                            <!-- Vista para TABLET/ESCRITORIO (tabla normal) -->
                            <table class="hidden sm:table min-w-full text-xs border">
                                <thead class="bg-guindo-50">
                                    <tr>
                                        <th class="px-2 py-1 text-left text-guindo-700 text-[10px] sm:text-xs">Código</th>
                                        <th class="px-2 py-1 text-left text-guindo-700 text-[10px] sm:text-xs">Producto</th>
                                        <th class="px-2 py-1 text-right text-guindo-700 text-[10px] sm:text-xs">Unidades</th>
                                        <th class="px-2 py-1 text-right text-guindo-700 text-[10px] sm:text-xs">Total Bs</th>
                                        <th class="px-2 py-1 text-right text-guindo-700 text-[10px] sm:text-xs">Precio</th>
                                        <th class="px-2 py-1 text-center text-guindo-700 text-[10px] sm:text-xs">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(item, index) in detallesGrid" :key="index" class="hover:bg-gray-50">
                                        <td class="px-2 py-1 font-mono text-[10px] sm:text-xs">{{ item.Codigo }}</td>
                                        <td class="px-2 py-1 text-[10px] sm:text-xs">{{ item.Descripcion }}</td>
                                        <td class="px-2 py-1 text-right text-[10px] sm:text-xs">{{ Number(item.Unidades).toFixed(4) }}</td>
                                        <td class="px-2 py-1 text-right font-semibold text-guindo-600 text-[10px] sm:text-xs">{{ Number(item.TotalBolivianos).toFixed(2) }}</td>
                                        <td class="px-2 py-1 text-right text-[10px] sm:text-xs">{{ Number(item.Precio).toFixed(2) }}</td>
                                        <td class="px-2 py-1 text-center whitespace-nowrap">
                                            <div class="flex gap-1 justify-center">
                                                <button @click="abrirEditarProducto(item, index)" class="text-blue-500 hover:text-blue-700 p-1" title="Editar">
                                                    <i class="fas fa-edit text-[10px] sm:text-xs"></i>
                                                </button>
                                                <button @click="eliminarProducto(index)" class="text-red-500 hover:text-red-700 p-1" title="Eliminar">
                                                    <i class="fas fa-trash-alt text-[10px] sm:text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="detallesGrid.length === 0">
                                        <td colspan="6" class="px-2 py-4 text-center text-gray-400 text-[10px] sm:text-xs">
                                            No hay productos agregados
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Totales -->
                <div class="mt-3 pt-2 border-t">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                            <label class="block text-gray-600 text-[10px] sm:text-xs">Importe Factura</label>
                            <input type="text" :value="totalFactura" readonly class="w-full border rounded px-2 py-1.5 bg-gray-100 font-bold text-guindo-600 text-xs sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-[10px] sm:text-xs">Total a Pagar</label>
                            <input type="text" :value="totalFactura" readonly class="w-full border rounded px-2 py-1.5 bg-gray-100 font-bold text-guindo-600 text-xs sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-[10px] sm:text-xs">Crédito Fiscal</label>
                            <input type="text" :value="importeCreditoFiscal" readonly class="w-full border rounded px-2 py-1.5 bg-gray-100 font-bold text-guindo-600 text-xs sm:text-sm">
                        </div>
                    </div>
                </div>

                <!-- Botón Contabilizar -->
                <div class="mt-3 flex justify-end">
                    <button @click="contabilizar" :disabled="contabilizando || detallesGrid.length === 0" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 sm:px-4 sm:py-1.5 rounded text-xs sm:text-sm font-medium flex items-center gap-1 w-full sm:w-auto justify-center">
                        <i v-if="contabilizando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check-circle"></i>
                        {{ contabilizando ? 'Contabilizando...' : 'CONTABILIZAR' }}
                    </button>
                </div>
            </div>

            <!-- Mensaje si cabecera no guardada -->
            <div v-else class="bg-yellow-50 rounded-lg p-2 sm:p-3 text-yellow-800 text-[10px] sm:text-xs text-center">
                <i class="fas fa-info-circle mr-1"></i>
                Complete todos los campos de la cabecera y presione "Guardar Cabecera" para comenzar a agregar productos.
            </div>

            <!-- Modal de Confirmación Contabilización -->
            <div v-if="mostrarConfirmacion" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-lg max-w-sm w-full">
                    <div class="bg-amber-500 p-3 rounded-t-lg">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle text-white text-sm sm:text-lg"></i>
                            <h3 class="text-white font-semibold text-xs sm:text-sm">Confirmar Contabilización</h3>
                        </div>
                    </div>
                    <div class="p-3 sm:p-4">
                        <p class="text-gray-700 text-xs sm:text-sm mb-3">¿Está seguro de contabilizar esta compra?</p>
                        <p class="text-gray-500 text-[10px] sm:text-xs mb-4">Una vez contabilizada, no se podrá modificar.</p>
                        <div class="flex gap-2">
                            <button @click="cancelarConfirmacion" class="flex-1 py-1.5 rounded bg-gray-200 text-gray-700 text-xs sm:text-sm hover:bg-gray-300">Cancelar</button>
                            <button @click="ejecutarContabilizar" class="flex-1 py-1.5 rounded bg-emerald-600 text-white text-xs sm:text-sm hover:bg-emerald-700">Sí, Contabilizar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Editar Producto -->
            <div v-if="editandoDetalle" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarEditarProducto">
                <div class="bg-white rounded-lg max-w-md w-full">
                    <div class="bg-blue-600 p-3 rounded-t-lg">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-edit text-white"></i>
                            <h3 class="text-white font-semibold text-sm">Editar Producto</h3>
                        </div>
                    </div>
                    <div class="p-4 space-y-3">
                        <div>
                            <label class="block text-gray-600 text-xs mb-1">Unidades</label>
                            <input 
                                type="number" 
                                v-model.number="unidadesEdit" 
                                step="0.0001" 
                                class="no-spinner w-full border rounded px-2 py-1.5 text-sm"
                            >
                        </div>
                        <div>
                            <label class="block text-gray-600 text-xs mb-1">Total Bolivianos</label>
                            <input 
                                type="number" 
                                v-model.number="totalEdit" 
                                step="0.01" 
                                class="no-spinner w-full border rounded px-2 py-1.5 text-sm"
                            >
                        </div>
                        <div class="text-xs text-gray-500">
                            Precio calculado: {{ (totalEdit / unidadesEdit).toFixed(2) || '0.00' }} Bs
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button @click="cerrarEditarProducto" class="flex-1 py-1.5 rounded bg-gray-200 text-gray-700 text-sm hover:bg-gray-300">
                                Cancelar
                            </button>
                            <button @click="guardarEditarProducto" class="flex-1 py-1.5 rounded bg-blue-600 text-white text-sm hover:bg-blue-700">
                                Guardar Cambios
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 640px) {
    .xs\:block {
        display: block;
    }
}
</style>