<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    compra: Object,        // Puede ser null si no hay compra pendiente
    detalles: Array,
    almacenes: Array,
    tiposFactura: Array,
    proveedores: Array,
    fechas: Array,
    productos: Array,
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
const cabeceraGuardada = ref(false)  // 🔥 Controla si se muestra la sección de productos

// Buscador de productos
const busquedaProducto = ref('')
const productosFiltrados = computed(() => {
    if (!busquedaProducto.value) return props.productos
    const termino = busquedaProducto.value.toLowerCase()
    return props.productos.filter(p => 
        p.Codigo?.toLowerCase().includes(termino) || 
        p.Descripcion?.toLowerCase().includes(termino)
    )
})

const productoSeleccionado = ref(null)

// Buscador de proveedores
const busquedaProveedor = ref('')
const proveedoresFiltrados = computed(() => {
    if (!busquedaProveedor.value) return props.proveedores
    const termino = busquedaProveedor.value.toLowerCase()
    return props.proveedores.filter(p => 
        p.ci?.toString().includes(termino) || 
        p.nombre?.toLowerCase().includes(termino)
    )
})

// Total de la compra (calculado desde detalles)
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

// Guardar cabecera (crea la compra si no existe, o actualiza si existe)
const guardarCabecera = async () => {
    if (!validarCamposCabecera()) {
        toast?.warning('Datos incompletos', 'Complete todos los campos obligatorios')
        return
    }
    
    guardandoCabecera.value = true
    try {
        let idCompra = form.value.IdCompras
        
        // 🔥 Si no hay ID, primero crear la compra
        if (!idCompra) {
            const crearResponse = await axios.post('/gestion/compras/crear')
            if (crearResponse.data.success) {
                idCompra = crearResponse.data.compra.IdCompras
                form.value.IdCompras = idCompra
            } else {
                throw new Error('No se pudo crear la compra')
            }
        }
        
        // Actualizar cabecera
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
        
        // ✅ SOLO AQUÍ se activa la sección de productos
        cabeceraGuardada.value = true
        toast?.success('Cabecera guardada', 'Datos guardados correctamente. Ahora puede agregar productos.')
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
    
    const productoData = {
        id: productoSeleccionado.value.id,
        Codigo: productoSeleccionado.value.Codigo,
        Descripcion: productoSeleccionado.value.Descripcion
    }
    
    const unidades = parseFloat(document.getElementById('unidades_input')?.value) || 0
    const totalBolivianos = parseFloat(document.getElementById('total_input')?.value) || 0
    
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
            IdProducto: productoData.id,
            Unidades: unidades,
            TotalBolivianos: totalBolivianos,
        })
        
        if (response.data.success) {
            detallesGrid.value.push({
                IdComprasDetalle: response.data.detalle.IdComprasDetalle,
                IdProducto: response.data.detalle.IdProducto,
                Codigo: productoData.Codigo,
                Descripcion: productoData.Descripcion,
                Unidades: unidades,
                TotalBolivianos: totalBolivianos,
                Precio: precio
            })
            
            limpiarSeleccionProducto()
            document.getElementById('unidades_input').value = ''
            document.getElementById('total_input').value = ''
            
            toast?.success('Producto agregado', `${productoData.Descripcion} - ${unidades} x ${totalBolivianos.toFixed(2)} Bs`)
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
            toast?.success('Producto eliminado', item.Descripcion)
        }
    } catch (error) {
        console.error('Error al eliminar:', error)
        toast?.error('Error', error.response?.data?.message || 'No se pudo eliminar')
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
            setTimeout(() => router.get('/gestion/compras'), 1500)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al contabilizar')
    } finally {
        contabilizando.value = false
    }
}

const cancelarConfirmacion = () => mostrarConfirmacion.value = false

// Inicializar
onMounted(() => {
    cargarDetalles()
    
    // 🔥 Si ya existe una compra con ID y tiene detalles, mostrar productos
    if (form.value.IdCompras && detallesGrid.value.length > 0) {
        cabeceraGuardada.value = true
    }
    
    if (props.compra && props.compra.IdNIT && props.compra.IdNIT !== 0) {
        const proveedor = props.proveedores.find(p => p.id === props.compra.IdNIT)
        if (proveedor) busquedaProveedor.value = `${proveedor.ci} - ${proveedor.nombre}`
    }
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 py-4 px-3">
        <div class="max-w-full mx-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-guindo-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus-circle text-guindo-600 text-sm"></i>
                    </div>
                    <h1 class="text-lg font-bold text-gray-800">Nueva Compra</h1>
                </div>
            </div>

            <!-- Formulario Cabecera -->
            <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-sm font-semibold text-gray-700">Datos de la Compra</h2>
                    <button 
                        @click="guardarCabecera" 
                        :disabled="guardandoCabecera" 
                        class="bg-guindo-600 hover:bg-guindo-700 text-white px-3 py-1 rounded text-xs flex items-center gap-1"
                    >
                        <i v-if="guardandoCabecera" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ guardandoCabecera ? 'Guardando...' : 'Guardar Cabecera' }}
                    </button>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                    <div>
                        <label class="block text-gray-600 mb-0.5">Almacén *</label>
                        <select v-model="form.IdAlmacen" class="w-full border rounded px-2 py-1 text-xs">
                            <option value="">Seleccione</option>
                            <option v-for="a in almacenes" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                        </select>
                        <p v-if="errors.IdAlmacen" class="text-red-500 text-[10px]">{{ errors.IdAlmacen }}</p>
                    </div>

                    <div>
                        <label class="block text-gray-600 mb-0.5">Tipo Factura *</label>
                        <select v-model="form.IdTipoFactura" class="w-full border rounded px-2 py-1 text-xs">
                            <option value="">Seleccione</option>
                            <option v-for="t in tiposFactura" :key="t.IdTipoFactura" :value="t.IdTipoFactura">{{ t.IdTipoFactura }} - {{ t.FacturaRecibo }}</option>
                        </select>
                        <p v-if="errors.IdTipoFactura" class="text-red-500 text-[10px]">{{ errors.IdTipoFactura }}</p>
                    </div>

                    <div>
                        <label class="block text-gray-600 mb-0.5">Fecha *</label>
                        <select v-model="form.IdFecha" class="w-full border rounded px-2 py-1 text-xs">
                            <option value="">Seleccione</option>
                            <option v-for="f in fechas" :key="f.id" :value="f.id">{{ f.fecha }}</option>
                        </select>
                        <p v-if="errors.IdFecha" class="text-red-500 text-[10px]">{{ errors.IdFecha }}</p>
                    </div>

                    <div>
                        <label class="block text-gray-600 mb-0.5">N° Documento *</label>
                        <input type="number" v-model.number="form.NumeroFactura" class="w-full border rounded px-2 py-1 text-xs" placeholder="Número">
                        <p v-if="errors.NumeroFactura" class="text-red-500 text-[10px]">{{ errors.NumeroFactura }}</p>
                    </div>

                    <div v-if="mostrarAutorizacion">
                        <label class="block text-gray-600 mb-0.5">N° Autorización</label>
                        <input type="number" v-model.number="form.NumeroAutorizacion" class="w-full border rounded px-2 py-1 text-xs" placeholder="Autorización">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-gray-600 mb-0.5">Proveedor (NIT) *</label>
                        <div class="relative">
                            <input type="text" v-model="busquedaProveedor" class="w-full border rounded px-2 py-1 text-xs" placeholder="Buscar por NIT o nombre..." @focus="busquedaProveedor = ''">
                            <div v-if="busquedaProveedor && proveedoresFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded shadow-lg max-h-32 overflow-y-auto">
                                <div v-for="prov in proveedoresFiltrados" :key="prov.id" @click="form.IdNIT = prov.id; busquedaProveedor = `${prov.ci} - ${prov.nombre}`" class="px-2 py-1 hover:bg-gray-100 cursor-pointer border-b text-xs">
                                    <span class="font-mono">{{ prov.ci }}</span> - {{ prov.nombre }}
                                </div>
                            </div>
                        </div>
                        <p v-if="errors.IdNIT" class="text-red-500 text-[10px]">{{ errors.IdNIT }}</p>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-gray-600 mb-0.5">Observación</label>
                        <textarea v-model="form.Observacion" rows="1" class="w-full border rounded px-2 py-1 text-xs" placeholder="Notas..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Sección Detalle (solo visible después de guardar cabecera) -->
            <div v-if="cabeceraGuardada" class="bg-white rounded-lg shadow-sm p-3 mb-4">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Productos</h2>

                <!-- Buscador de Producto -->
                <div class="grid grid-cols-12 gap-2 mb-2 text-xs">
                    <div class="col-span-12 md:col-span-5 relative">
                        <label class="block text-gray-600 mb-0.5">Producto</label>
                        <input type="text" v-model="busquedaProducto" class="w-full border rounded px-2 py-1 text-xs" placeholder="Buscar por código o nombre..." @focus="busquedaProducto = ''">
                        <div v-if="busquedaProducto && productosFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded shadow-lg max-h-32 overflow-y-auto">
                            <div v-for="prod in productosFiltrados" :key="prod.id" @click="seleccionarProducto(prod)" class="px-2 py-1 hover:bg-gray-100 cursor-pointer border-b text-xs">
                                <span class="font-mono">{{ prod.Codigo }}</span> - {{ prod.Descripcion }}
                            </div>
                        </div>
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-gray-600 mb-0.5">Unidades</label>
                        <input id="unidades_input" type="number" step="0.0001" @input="calcularPrecioDesdeCampos" class="w-full border rounded px-2 py-1 text-xs" placeholder="0.0000">
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-gray-600 mb-0.5">Total Bs</label>
                        <input id="total_input" type="number" step="0.01" @input="calcularPrecioDesdeCampos" class="w-full border rounded px-2 py-1 text-xs" placeholder="0.00">
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-gray-600 mb-0.5">Precio</label>
                        <input id="precio_display" type="text" readonly class="w-full border rounded px-2 py-1 text-xs bg-gray-100" value="0.00">
                    </div>
                </div>

                <div class="flex justify-end mb-3">
                    <button @click="agregarProducto" :disabled="guardandoDetalle || !productoSeleccionado" class="bg-guindo-600 hover:bg-guindo-700 text-white px-3 py-1 rounded text-xs flex items-center gap-1">
                        <i v-if="guardandoDetalle" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-plus"></i>
                        {{ guardandoDetalle ? 'Agregando...' : 'Agregar' }}
                    </button>
                </div>

                <!-- Tabla de productos -->
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border">
                        <thead class="bg-guindo-50">
                            <tr>
                                <th class="px-2 py-1 text-left text-guindo-700">Código</th>
                                <th class="px-2 py-1 text-left text-guindo-700">Producto</th>
                                <th class="px-2 py-1 text-right text-guindo-700">Unidades</th>
                                <th class="px-2 py-1 text-right text-guindo-700">Total Bs</th>
                                <th class="px-2 py-1 text-right text-guindo-700">Precio</th>
                                <th class="px-2 py-1 text-center text-guindo-700"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(item, index) in detallesGrid" :key="index" class="hover:bg-gray-50">
                                <td class="px-2 py-1 font-mono">{{ item.Codigo }}</td>
                                <td class="px-2 py-1">{{ item.Descripcion }}</td>
                                <td class="px-2 py-1 text-right">{{ Number(item.Unidades).toFixed(4) }}</td>
                                <td class="px-2 py-1 text-right font-semibold text-guindo-600">{{ Number(item.TotalBolivianos).toFixed(2) }}</td>
                                <td class="px-2 py-1 text-right">{{ Number(item.Precio).toFixed(2) }}</td>
                                <td class="px-2 py-1 text-center">
                                    <button @click="eliminarProducto(index)" class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="detallesGrid.length === 0">
                                <td colspan="6" class="px-2 py-4 text-center text-gray-400 text-xs">No hay productos agregados</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totales -->
                <div class="mt-3 pt-2 border-t">
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-gray-600 text-xs">Importe Factura</label>
                            <input type="text" :value="totalFactura" readonly class="w-full border rounded px-2 py-1 bg-gray-100 font-bold text-guindo-600 text-sm">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-xs">Total a Pagar</label>
                            <input type="text" :value="totalFactura" readonly class="w-full border rounded px-2 py-1 bg-gray-100 font-bold text-guindo-600 text-sm">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-xs">Crédito Fiscal</label>
                            <input type="text" :value="importeCreditoFiscal" readonly class="w-full border rounded px-2 py-1 bg-gray-100 font-bold text-guindo-600 text-sm">
                        </div>
                    </div>
                </div>

                <!-- Botón Contabilizar -->
                <div class="mt-3 flex justify-end">
                    <button @click="contabilizar" :disabled="contabilizando || detallesGrid.length === 0" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-1.5 rounded text-sm font-medium flex items-center gap-1">
                        <i v-if="contabilizando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check-circle"></i>
                        {{ contabilizando ? 'Contabilizando...' : 'CONTABILIZAR' }}
                    </button>
                </div>
            </div>

            <!-- Mensaje si cabecera no guardada -->
            <div v-else class="bg-yellow-50 rounded-lg p-3 text-yellow-800 text-xs text-center">
                <i class="fas fa-info-circle mr-1"></i>
                Complete todos los campos de la cabecera y presione "Guardar Cabecera" para comenzar a agregar productos.
            </div>

            <!-- Modal de Confirmación -->
            <div v-if="mostrarConfirmacion" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-lg max-w-sm w-full">
                    <div class="bg-amber-500 p-3">
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
</template>