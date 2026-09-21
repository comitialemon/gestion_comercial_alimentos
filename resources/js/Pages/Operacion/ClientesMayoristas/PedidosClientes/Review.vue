<script setup>
import { ref, computed, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import ConfirmModal from './ConfirmModal.vue'
import CreateModalProductos from './CreateModalProductos.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    pedido: { type: Object, required: true },
    detallesAgrupados: { type: Array, default: () => [] },
    clienteNombre: { type: String, default: '' },
    sucursalNombre: { type: String, default: '' },
    operadorNombre: { type: String, default: '' },
    idIdentificador: { type: Number, default: null },
    progresoGrupos: { type: Array, default: () => [] },
    cumpleMinimos: { type: Boolean, default: true },
    tipoPrecio: { type: String, default: 'sin_factura' },
})

// ==================== ESTADO ====================
const loading = ref(false)
const observaciones = ref(props.pedido?.Observaciones || '')
const fechaEntrega = ref('')
const modalConfirmacionVisible = ref(false)
const errorFechaEntrega = ref('')

// ✅ Tipo de precio
const tipoPrecioLocal = ref(props.tipoPrecio || 'sin_factura')
const cambiandoTipoPrecio = ref(false)

// ✅ Detalles reactivos (para actualizar precios sin recargar)
const detallesLocal = ref(
    (props.detallesAgrupados || []).map(item => ({
        ...item,
        productos: (item.productos || []).map(p => ({
            ...p,
            Cantidad: Number(p.Cantidad) || 0,
            Precio: Number(p.Precio) || 0,
            Subtotal: (Number(p.Cantidad) || 0) * (Number(p.Precio) || 0),
        })),
    }))
)

// ✅ Estado para edición
const modalEdicionVisible = ref(false)
const contenedorSeleccionado = ref(null)

// ==================== COMPUTADOS ====================
const fechaMinima = computed(() => {
    const hoy = new Date()
    hoy.setDate(hoy.getDate() + 1)
    const year = hoy.getFullYear()
    const month = String(hoy.getMonth() + 1).padStart(2, '0')
    const day = String(hoy.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
})

const totalUnidades = computed(() => {
    let total = 0
    detallesLocal.value.forEach(item => {
        (item.productos || []).forEach(p => {
            total += Number(p.Cantidad) || 0
        })
    })
    return total
})

const totalContenedores = computed(() => detallesLocal.value.length)

const totalGeneral = computed(() => {
    let total = 0
    detallesLocal.value.forEach(item => {
        (item.productos || []).forEach(p => {
            total += (Number(p.Cantidad) || 0) * (Number(p.Precio) || 0)
        })
    })
    return total
})

const fechaPedido = computed(() => {
    if (props.pedido?.FechaPedido) {
        return new Date(props.pedido.FechaPedido).toLocaleString('es-BO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
    }
    return new Date().toLocaleString('es-BO')
})

const progresoLocal = computed(() => props.progresoGrupos || [])

const gruposQueNoCumplen = computed(() => {
    return progresoLocal.value.filter(g => !g.Cumple)
})

const cumpleTodos = computed(() => {
    return progresoLocal.value.length === 0 || gruposQueNoCumplen.value.length === 0
})

const puedeFinalizar = computed(() => {
    return cumpleTodos.value && detallesLocal.value.length > 0
})

const tipoPrecioTexto = computed(() => {
    return tipoPrecioLocal.value === 'con_factura' ? 'Con Factura' : 'Sin Factura'
})

// ==================== FORMATEO ====================
const formatearNumero = (valor) => {
    if (valor === undefined || valor === null || valor === '') return '0'
    const numero = parseFloat(valor)
    return isNaN(numero) ? '0' : numero.toFixed(0)
}

const formatearPrecio = (valor) => {
    if (valor === undefined || valor === null || valor === '') return '0.00'
    const numero = parseFloat(valor)
    return isNaN(numero) ? '0.00' : numero.toFixed(2)
}

// ==================== FUNCIONES ====================
const irAtras = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create')
}

const validarFechaEntrega = () => {
    if (!fechaEntrega.value) {
        errorFechaEntrega.value = 'La fecha de entrega es obligatoria'
        return false
    }
    
    const hoy = new Date()
    const hoyStr = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`
    
    if (fechaEntrega.value <= hoyStr) {
        errorFechaEntrega.value = `La fecha debe ser mínimo 1 día después de hoy (${hoy.toLocaleDateString('es-BO')})`
        return false
    }
    
    errorFechaEntrega.value = ''
    return true
}

// ✅ CAMBIAR TIPO DE PRECIO - Actualiza precios en pantalla sin recargar
const cambiarTipoPrecio = async (nuevoTipo) => {
    if (nuevoTipo === tipoPrecioLocal.value) return
    
    cambiandoTipoPrecio.value = true
    
    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${props.pedido.IdPedidoCliente}/recalcular-tipo-precio`,
            { TipoPrecio: nuevoTipo }
        )
        
        if (response.data.success) {
            // ✅ Actualizar tipo
            tipoPrecioLocal.value = response.data.tipo_precio || nuevoTipo
            
            // ✅ Actualizar detalles con los nuevos precios
            if (Array.isArray(response.data.detalles_agrupados)) {
                detallesLocal.value = response.data.detalles_agrupados.map(item => ({
                    ...item,
                    productos: (item.productos || []).map(p => ({
                        ...p,
                        Cantidad: Number(p.Cantidad) || 0,
                        Precio: Number(p.Precio) || 0,
                        Subtotal: (Number(p.Cantidad) || 0) * (Number(p.Precio) || 0),
                    })),
                }))
            }
            
            toast?.success('Éxito', 'Precios recalculados correctamente')
        } else {
            toast?.error('Error', response.data.message || 'Error al cambiar tipo de precio')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al cambiar tipo de precio')
    } finally {
        cambiandoTipoPrecio.value = false
    }
}

const abrirModalConfirmacion = () => {
    if (detallesLocal.value.length === 0) {
        toast?.warning('Carrito vacío', 'Agregue productos antes de finalizar')
        return
    }

    if (!cumpleTodos.value) {
        const grupos = gruposQueNoCumplen.value.map(g => `${g.NombreGrupo}: faltan ${g.Falta} und`).join('\n')
        toast?.error('Mínimos incompletos', `No se puede finalizar:\n${grupos}`)
        return
    }
    
    if (!validarFechaEntrega()) {
        toast?.error('Error', errorFechaEntrega.value)
        return
    }
    
    modalConfirmacionVisible.value = true
}

const finalizarPedido = async () => {
    modalConfirmacionVisible.value = false
    loading.value = true
    
    try {
        let fechaEntregaFormateada = null
        if (fechaEntrega.value) {
            const partes = fechaEntrega.value.split('-')
            if (partes.length === 3) {
                fechaEntregaFormateada = `${partes[2]}/${partes[1]}/${partes[0]}`
            }
        }
        
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${props.pedido.IdPedidoCliente}/finalizar`,
            {
                IdCliente: props.pedido.IdCliente,
                IdSucursal: props.pedido.IdSucursal,
                FechaEntrega: fechaEntregaFormateada,
                Observaciones: observaciones.value || null,
                TipoPrecio: tipoPrecioLocal.value
            }
        )
        
        if (response.data.success) {
            toast?.success('Pedido finalizado', `Pedido N° ${response.data.numero_pedido} creado correctamente`)
            
            if (response.data.pdf_url) {
                window.open(response.data.pdf_url, '_blank')
            }
            
            setTimeout(() => {
                router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes')
            }, 1500)
        } else {
            if (response.data.errores) {
                const errores = response.data.errores.join('\n')
                toast?.error('No se puede finalizar', errores)
            } else {
                toast?.error('Error', response.data.message || 'Error al finalizar el pedido')
            }
        }
    } catch (error) {
        console.error('❌ Error:', error)
        const mensaje = error.response?.data?.message || 'Error al finalizar el pedido'
        toast?.error('Error', mensaje)
    } finally {
        loading.value = false
    }
}

// ✅ EDICIÓN
const abrirModalEdicion = (item) => {
    contenedorSeleccionado.value = {
        IdContenedor: item.IdContenedor,
        CapacidadTotal: item.CapacidadTotal,
        Codigo: item.Codigo,
        TipoContenedor: item.TipoContenedor || '',
        _datosEdicion: {
            IdPedidoCliente: props.pedido.IdPedidoCliente,
            IdContenedor: item.IdContenedor,
            OrdenContenedor: item.Orden,
            productos: item.productos.map(p => ({
                IdProducto: p.IdProducto,
                Cantidad: p.Cantidad,
                Precio: p.Precio
            }))
        }
    }
    modalEdicionVisible.value = true
}

const actualizarContenedor = async (data) => {
    loading.value = true
    try {
        const response = await axios.put('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/carrito/contenedor', data)
        if (response.data.success) {
            toast?.success('Éxito', 'Contenedor actualizado correctamente')
            router.reload()
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al actualizar el contenedor')
    } finally {
        loading.value = false
        modalEdicionVisible.value = false
    }
}

const eliminarContenedor = async (item) => {
    const detalleId = item.productos[0]?.IdPedidoClienteDetalle
    if (!detalleId) {
        toast?.error('Error', 'No se pudo identificar el contenedor')
        return
    }
    
    if (!confirm('¿Eliminar este contenedor del pedido?')) return
    
    loading.value = true
    try {
        const response = await axios.delete(`/operacion/pedidos/clientes-mayoristas/pedidos-clientes/carrito/detalle/${detalleId}`)
        if (response.data.success) {
            toast?.success('Éxito', 'Contenedor eliminado')
            if (response.data.carrito_vacio) {
                router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create')
            } else {
                router.reload()
            }
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al eliminar el contenedor')
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="max-w-5xl mx-auto px-3 py-4">
            
            <!-- HEADER -->
            <div class="flex items-center justify-between mb-4">
                <button @click="irAtras" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 transition text-sm font-medium">
                    <i class="fas fa-arrow-left"></i>
                    Volver
                </button>
                <span class="text-xs text-gray-400">
                    Pedido #{{ pedido?.NumeroPedido && pedido.NumeroPedido !== '0' ? pedido.NumeroPedido : 'Nuevo' }}
                </span>
            </div>

            <!-- ✅ SELECTOR DE TIPO DE PRECIO -->
            <div class="mb-4 bg-white rounded-xl shadow-sm p-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-tag text-primary-500"></i>
                        <span class="text-sm font-medium text-gray-700">Tipo de Precio:</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="cambiarTipoPrecio('sin_factura')"
                            :disabled="cambiandoTipoPrecio || tipoPrecioLocal === 'sin_factura'"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2"
                            :class="tipoPrecioLocal === 'sin_factura' 
                                ? 'bg-gray-800 text-white shadow-md' 
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        >
                            <i class="fas fa-receipt"></i>
                            Sin Factura
                            <i v-if="cambiandoTipoPrecio && tipoPrecioLocal !== 'sin_factura'" class="fas fa-spinner fa-spin text-xs"></i>
                        </button>
                        <button
                            @click="cambiarTipoPrecio('con_factura')"
                            :disabled="cambiandoTipoPrecio || tipoPrecioLocal === 'con_factura'"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2"
                            :class="tipoPrecioLocal === 'con_factura' 
                                ? 'bg-blue-600 text-white shadow-md' 
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        >
                            <i class="fas fa-file-invoice-dollar"></i>
                            Con Factura
                            <i v-if="cambiandoTipoPrecio && tipoPrecioLocal !== 'con_factura'" class="fas fa-spinner fa-spin text-xs"></i>
                        </button>
                    </div>
                </div>
                <p v-if="cambiandoTipoPrecio" class="text-xs text-blue-600 mt-2">
                    <i class="fas fa-sync fa-spin mr-1"></i>
                    Recalculando precios...
                </p>
            </div>

            <!-- ✅ ALERTA DE MÍNIMOS -->
            <div v-if="!cumpleTodos" class="mb-4 bg-red-50 border-l-4 border-red-500 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                    <div class="flex-1">
                        <h3 class="font-bold text-red-800 text-sm">No se puede finalizar el pedido</h3>
                        <p class="text-xs text-red-700 mt-1">Faltan cumplir los siguientes mínimos por grupo:</p>
                        <ul class="mt-2 space-y-1">
                            <li v-for="grupo in gruposQueNoCumplen" :key="grupo.IdGrupoAnalisis" class="text-xs text-red-700 flex items-center gap-2">
                                <i class="fas fa-circle text-[6px]"></i>
                                <strong>{{ grupo.NombreGrupo }}:</strong>
                                <span>faltan {{ grupo.Falta }} und (tienes {{ grupo.CantidadPedida }}, mínimo {{ grupo.CantidadMinima }})</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ✅ PROGRESO (SI CUMPLE) -->
            <div v-else-if="progresoLocal.length > 0" class="mb-4 bg-green-50 border-l-4 border-green-500 rounded-lg p-3">
                <div class="flex items-center gap-3">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    <div class="flex-1">
                        <h3 class="font-bold text-green-800 text-sm">¡Todo listo!</h3>
                        <p class="text-xs text-green-700">Todos los mínimos están cumplidos. Puede finalizar el pedido.</p>
                    </div>
                </div>
            </div>

            <!-- DOCUMENTO DEL PEDIDO -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                
                <!-- CABECERA -->
                <div class="p-5 border-b bg-gray-50">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">Revisión del Pedido</h1>
                            <p class="text-xs text-gray-500 mt-1">
                                <span class="font-medium">N° Pedido:</span> 
                                {{ pedido?.NumeroPedido && pedido.NumeroPedido !== '0' ? pedido.NumeroPedido : 'Nuevo' }}
                            </p>
                            <p class="text-xs text-gray-500">
                                <span class="font-medium">Cliente:</span> 
                                {{ clienteNombre || 'Sin cliente' }}
                            </p>
                        </div>
                        <div class="text-xs text-gray-500 sm:text-right">
                            <p><span class="font-medium">Fecha:</span> {{ fechaPedido }}</p>
                            <p><span class="font-medium">Operador:</span> {{ operadorNombre || 'Sin operador' }}</p>
                            <p class="mt-1">
                                <span class="font-medium">Tipo:</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                    :class="tipoPrecioLocal === 'con_factura' 
                                        ? 'bg-blue-100 text-blue-800' 
                                        : 'bg-gray-100 text-gray-700'">
                                    {{ tipoPrecioTexto }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- SUCURSAL -->
                <div class="px-5 py-2.5 border-b bg-gray-50/50">
                    <div class="flex items-center gap-2 text-xs text-gray-600">
                        <i class="fas fa-store text-primary-500"></i>
                        <span class="font-medium">Sucursal:</span>
                        <span>{{ sucursalNombre || 'Sin sucursal' }}</span>
                    </div>
                </div>

                <!-- PRODUCTOS POR CONTENEDOR -->
                <div class="p-5 border-b">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-700 flex items-center">
                            <i class="fas fa-boxes mr-2 text-primary-500"></i>
                            Productos
                            <span class="text-xs text-gray-400 font-normal ml-2">
                                ({{ totalContenedores }} contenedor(es) · {{ formatearNumero(totalUnidades) }} und)
                            </span>
                        </h2>
                        <span class="text-xs font-bold text-primary-600 bg-primary-50 px-2.5 py-1 rounded-full">
                            Total: Bs. {{ formatearPrecio(totalGeneral) }}
                        </span>
                    </div>

                    <div v-if="detallesLocal.length === 0" class="text-center text-gray-400 py-6">
                        <i class="fas fa-inbox text-2xl mb-2 block"></i>
                        <p class="text-sm">No hay productos en este pedido</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div 
                            v-for="(item, idx) in detallesLocal" 
                            :key="idx"
                            class="border rounded-lg overflow-hidden bg-white shadow-sm"
                        >
                            <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 border-b">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[10px] font-mono bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full font-semibold">#{{ idx + 1 }}</span>
                                    <span class="font-semibold text-gray-800 text-sm">{{ item.Codigo }}</span>
                                    <span class="text-[10px] text-gray-400">(Cap: {{ formatearNumero(item.CapacidadTotal) }} und)</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-1">
                                        <span class="text-[10px] text-gray-500">
                                            {{ formatearNumero(item.total_unidades) }} und
                                        </span>
                                        <span class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
                                            Bs. {{ formatearPrecio(item.subtotal || 0) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button @click="abrirModalEdicion(item)" class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-[10px] transition" title="Editar contenedor">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button @click="eliminarContenedor(item)" class="px-2 py-1 bg-red-500 hover:bg-red-600 text-white rounded text-[10px] transition" title="Eliminar contenedor">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-sm">
                                    <thead>
                                        <tr class="border-b bg-gray-50/50 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                                            <th class="py-2 px-4">Producto</th>
                                            <th class="py-2 px-4 text-right w-20">Cantidad</th>
                                            <th class="py-2 px-4 text-right w-24">Precio Unit.</th>
                                            <th class="py-2 px-4 text-right w-28 font-bold text-primary-600">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="producto in item.productos" :key="producto.IdProducto" class="hover:bg-gray-50/80 transition-colors">
                                            <td class="py-2.5 px-4 text-gray-700">{{ producto.Descripcion }}</td>
                                            <td class="py-2.5 px-4 text-right font-medium text-gray-800">{{ formatearNumero(producto.Cantidad) }}</td>
                                            <td class="py-2.5 px-4 text-right text-gray-600">Bs. {{ formatearPrecio(producto.Precio || 0) }}</td>
                                            <td class="py-2.5 px-4 text-right font-bold text-primary-600">
                                                Bs. {{ formatearPrecio((Number(producto.Cantidad) || 0) * (Number(producto.Precio) || 0)) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div v-if="detallesLocal.length > 0" class="mt-4 pt-3 border-t-2 border-primary-200 flex justify-end">
                        <div class="text-right">
                            <div class="flex items-center gap-6">
                                <div>
                                    <p class="text-[10px] text-gray-400 font-medium">Total Unidades</p>
                                    <p class="text-lg font-bold text-gray-700">{{ formatearNumero(totalUnidades) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 font-medium">Total Contenedores</p>
                                    <p class="text-lg font-bold text-gray-700">{{ totalContenedores }}</p>
                                </div>
                                <div class="pl-4 border-l-2 border-primary-200">
                                    <p class="text-[10px] text-primary-600 font-medium">TOTAL GENERAL</p>
                                    <p class="text-2xl font-extrabold text-primary-700">Bs. {{ formatearPrecio(totalGeneral) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FECHA DE ENTREGA Y OBSERVACIONES -->
                <div class="p-5 space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                        <div class="w-32 flex-shrink-0">
                            <label class="text-xs font-medium text-gray-500">
                                Fecha de Entrega <span class="text-red-500">*</span>
                            </label>
                        </div>
                        <div class="flex-1 w-full">
                            <input 
                                type="date"
                                v-model="fechaEntrega"
                                :min="fechaMinima"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none transition bg-white"
                                :class="{'border-red-500': errorFechaEntrega}"
                            />
                            <p v-if="errorFechaEntrega" class="text-[10px] text-red-500 mt-1">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ errorFechaEntrega }}
                            </p>
                            <p class="text-[10px] text-gray-400 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Mínimo 1 día después de hoy
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                        <div class="w-32 flex-shrink-0">
                            <label class="text-xs font-medium text-gray-500">Observaciones</label>
                        </div>
                        <div class="flex-1 w-full">
                            <textarea 
                                v-model="observaciones"
                                rows="2"
                                placeholder="Notas adicionales (opcional)..."
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none transition bg-white resize-none"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- PIE / BOTONES -->
                <div class="p-5 bg-gray-50 border-t flex flex-col sm:flex-row justify-end gap-3">
                    <button @click="irAtras" class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2">
                        <i class="fas fa-arrow-left text-xs"></i>
                        Seguir agregando
                    </button>
                    <button 
                        @click="abrirModalConfirmacion"
                        :disabled="loading || !puedeFinalizar"
                        class="px-6 py-2.5 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2 disabled:opacity-50 shadow-sm"
                        :class="puedeFinalizar 
                            ? 'bg-green-600 hover:bg-green-700 text-white' 
                            : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else-if="!puedeFinalizar" class="fas fa-ban"></i>
                        <i v-else class="fas fa-check-circle"></i>
                        {{ loading ? 'Procesando...' : (puedeFinalizar ? 'Finalizar Pedido' : 'Cumplir mínimos') }}
                    </button>
                </div>
            </div>
        </div>

        <ConfirmModal
            v-model:visible="modalConfirmacionVisible"
            title="Confirmar Pedido"
            message="¿Estás seguro de finalizar este pedido? Una vez confirmado no se podrá modificar."
            confirm-text="Sí, finalizar pedido"
            cancel-text="Cancelar"
            type="success"
            @confirm="finalizarPedido"
        />

        <CreateModalProductos
            :visible="modalEdicionVisible"
            :contenedor="contenedorSeleccionado"
            :idIdentificador="idIdentificador"
            :modoEdicion="true"
            :datosEdicion="contenedorSeleccionado?._datosEdicion || null"
            @close="modalEdicionVisible = false"
            @actualizar="actualizarContenedor"
        />
    </div>
</template>

<style scoped>
/* Estilos opcionales */
</style>