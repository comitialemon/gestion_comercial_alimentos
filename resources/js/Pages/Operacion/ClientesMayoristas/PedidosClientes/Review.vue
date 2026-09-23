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

const tipoPrecioLocal = ref(props.tipoPrecio || 'sin_factura')
const cambiandoTipoPrecio = ref(false)

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

const modalEdicionVisible = ref(false)
const contenedorSeleccionado = ref(null)

// ==================== COMPUTED ====================
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

const cambiarTipoPrecio = async (nuevoTipo) => {
    if (nuevoTipo === tipoPrecioLocal.value) return
    
    cambiandoTipoPrecio.value = true
    
    try {
        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${props.pedido.IdPedidoCliente}/recalcular-tipo-precio`,
            { TipoPrecio: nuevoTipo }
        )
        
        if (response.data.success) {
            tipoPrecioLocal.value = response.data.tipo_precio || nuevoTipo
            
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
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-5xl mx-auto">

                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <button 
                            @click="irAtras" 
                            class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary-600 hover:border-primary-300 transition flex-shrink-0"
                        >
                            <i class="fas fa-arrow-left text-xs"></i>
                        </button>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800 flex items-center gap-2">
                                Revisión del Pedido
                                <span class="text-[10px] bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full font-medium">
                                    #{{ pedido?.NumeroPedido && pedido.NumeroPedido !== '0' ? pedido.NumeroPedido : 'Nuevo' }}
                                </span>
                            </h1>
                            <p class="text-xs text-gray-500">Confirma los productos y finaliza el pedido</p>
                        </div>
                    </div>
                </div>

                <!-- ==================== SELECTOR TIPO DE PRECIO ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-3 border border-gray-200">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-tag text-primary-500 text-sm"></i>
                            <span class="text-xs font-medium text-gray-700">Tipo de Precio:</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button
                                @click="cambiarTipoPrecio('sin_factura')"
                                :disabled="cambiandoTipoPrecio || tipoPrecioLocal === 'sin_factura'"
                                class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5"
                                :class="tipoPrecioLocal === 'sin_factura' 
                                    ? 'bg-primary-600 text-white shadow-sm' 
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            >
                                <i class="fas fa-receipt text-[10px]"></i>
                                Sin Factura
                            </button>
                            <button
                                @click="cambiarTipoPrecio('con_factura')"
                                :disabled="cambiandoTipoPrecio || tipoPrecioLocal === 'con_factura'"
                                class="px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5"
                                :class="tipoPrecioLocal === 'con_factura' 
                                    ? 'bg-primary-600 text-white shadow-sm' 
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            >
                                <i class="fas fa-file-invoice-dollar text-[10px]"></i>
                                Con Factura
                            </button>
                        </div>
                    </div>
                    <p v-if="cambiandoTipoPrecio" class="text-[10px] text-primary-600 mt-2 flex items-center gap-1">
                        <i class="fas fa-sync fa-spin"></i>
                        Recalculando precios...
                    </p>
                </div>

                <!-- ==================== ALERTA DE MÍNIMOS ==================== -->
                <div v-if="!cumpleTodos" class="bg-red-50 border-l-4 border-red-500 rounded-xl p-3 mb-3">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-exclamation-triangle text-red-500 text-base flex-shrink-0 mt-0.5"></i>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-red-800 text-xs">No se puede finalizar el pedido</h3>
                            <p class="text-[10px] text-red-700 mt-0.5">Faltan cumplir los siguientes mínimos por grupo:</p>
                            <ul class="mt-1.5 space-y-0.5">
                                <li v-for="grupo in gruposQueNoCumplen" :key="grupo.IdGrupoAnalisis" class="text-[10px] text-red-700 flex items-start gap-1.5">
                                    <i class="fas fa-circle text-[5px] mt-1.5 flex-shrink-0"></i>
                                    <span>
                                        <strong>{{ grupo.NombreGrupo }}:</strong>
                                        faltan {{ grupo.Falta }} und
                                        <span class="opacity-70">(tienes {{ grupo.CantidadPedida }}, mínimo {{ grupo.CantidadMinima }})</span>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- ==================== PROGRESO OK ==================== -->
                <div v-else-if="progresoLocal.length > 0" class="bg-emerald-50 border-l-4 border-emerald-500 rounded-xl p-3 mb-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-emerald-500 text-base flex-shrink-0"></i>
                        <div class="flex-1">
                            <h3 class="font-bold text-emerald-800 text-xs">¡Todo listo!</h3>
                            <p class="text-[10px] text-emerald-700">Todos los mínimos están cumplidos. Puede finalizar el pedido.</p>
                        </div>
                    </div>
                </div>

                <!-- ==================== CARD PRINCIPAL ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">

                    <!-- CABECERA -->
                    <div class="p-3 border-b border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-9 h-9 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-user text-primary-600 text-sm"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-gray-800 truncate">
                                        {{ clienteNombre || 'Sin cliente' }}
                                    </div>
                                    <div class="text-[10px] text-gray-500 truncate mt-0.5">
                                        <i class="fas fa-store text-[8px] mr-1"></i>
                                        {{ sucursalNombre || 'Sin sucursal' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-[10px] text-gray-500 sm:text-right flex-shrink-0">
                                <p><span class="font-medium">Fecha:</span> {{ fechaPedido }}</p>
                                <p class="mt-0.5">
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold"
                                        :class="tipoPrecioLocal === 'con_factura' 
                                            ? 'bg-primary-100 text-primary-700' 
                                            : 'bg-gray-200 text-gray-700'">
                                        {{ tipoPrecioTexto }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- PRODUCTOS -->
                    <div class="p-3 border-b border-gray-200">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <h2 class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                                <i class="fas fa-boxes text-primary-500 text-[10px]"></i>
                                Productos
                                <span class="text-[10px] text-gray-400 font-normal">
                                    ({{ totalContenedores }} contenedor{{ totalContenedores !== 1 ? 'es' : '' }} · {{ formatearNumero(totalUnidades) }} und)
                                </span>
                            </h2>
                            <span class="text-[10px] font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded-full">
                                Total: Bs. {{ formatearPrecio(totalGeneral) }}
                            </span>
                        </div>

                        <div v-if="detallesLocal.length === 0" class="text-center text-gray-400 py-8">
                            <i class="fas fa-inbox text-2xl mb-1 block"></i>
                            <p class="text-xs">No hay productos en este pedido</p>
                        </div>

                        <div v-else class="space-y-2">
                            <div 
                                v-for="(item, idx) in detallesLocal" 
                                :key="idx"
                                class="border border-gray-200 rounded-lg overflow-hidden bg-white"
                            >
                                <!-- Header contenedor -->
                                <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-1.5 bg-gray-50 border-b border-gray-200">
                                    <div class="flex items-center gap-1.5 flex-wrap min-w-0 flex-1">
                                        <span class="text-[9px] font-mono bg-primary-600 text-white px-1.5 py-0.5 rounded font-bold flex-shrink-0">
                                            #{{ idx + 1 }}
                                        </span>
                                        <span class="font-semibold text-gray-800 text-xs truncate">{{ item.Codigo }}</span>
                                        <span class="text-[9px] text-gray-500 bg-white px-1.5 py-0.5 rounded border border-gray-200 flex-shrink-0">
                                            Cap: {{ formatearNumero(item.CapacidadTotal) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <span class="text-[10px] text-gray-500">
                                            {{ formatearNumero(item.total_unidades) }} und
                                        </span>
                                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full">
                                            Bs. {{ formatearPrecio(item.subtotal || 0) }}
                                        </span>
                                        <button @click="abrirModalEdicion(item)" class="px-1.5 py-0.5 bg-primary-500 hover:bg-primary-600 text-white rounded text-[9px] transition" title="Editar">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button @click="eliminarContenedor(item)" class="px-1.5 py-0.5 bg-red-500 hover:bg-red-600 text-white rounded text-[9px] transition" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Tabla de productos -->
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="border-b border-gray-200 bg-gray-50/50 text-[9px] font-semibold text-gray-400 uppercase tracking-wider">
                                                <th class="py-1.5 px-3">Producto</th>
                                                <th class="py-1.5 px-3 text-right w-16">Cant.</th>
                                                <th class="py-1.5 px-3 text-right w-20">Precio</th>
                                                <th class="py-1.5 px-3 text-right w-24 text-primary-600">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="producto in item.productos" :key="producto.IdProducto" class="hover:bg-gray-50/80 transition">
                                                <td class="py-1.5 px-3 text-gray-700 truncate max-w-[300px]" :title="producto.Descripcion">
                                                    {{ producto.Descripcion }}
                                                </td>
                                                <td class="py-1.5 px-3 text-right font-medium text-gray-800 tabular-nums">{{ formatearNumero(producto.Cantidad) }}</td>
                                                <td class="py-1.5 px-3 text-right text-gray-600 tabular-nums">Bs. {{ formatearPrecio(producto.Precio || 0) }}</td>
                                                <td class="py-1.5 px-3 text-right font-bold text-primary-600 tabular-nums">
                                                    Bs. {{ formatearPrecio((Number(producto.Cantidad) || 0) * (Number(producto.Precio) || 0)) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Totales -->
                        <div v-if="detallesLocal.length > 0" class="mt-3 pt-3 border-t-2 border-primary-200 flex justify-end">
                            <div class="flex items-center gap-4 sm:gap-6 flex-wrap justify-end">
                                <div class="text-right">
                                    <p class="text-[9px] text-gray-400 font-medium uppercase">Unidades</p>
                                    <p class="text-sm font-bold text-gray-700">{{ formatearNumero(totalUnidades) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[9px] text-gray-400 font-medium uppercase">Contenedores</p>
                                    <p class="text-sm font-bold text-gray-700">{{ totalContenedores }}</p>
                                </div>
                                <div class="pl-3 sm:pl-4 border-l-2 border-primary-200 text-right">
                                    <p class="text-[9px] text-primary-600 font-semibold uppercase">TOTAL GENERAL</p>
                                    <p class="text-xl font-extrabold text-primary-700 tabular-nums">Bs. {{ formatearPrecio(totalGeneral) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FECHA + OBSERVACIONES -->
                    <div class="p-3 space-y-3">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                            <div class="w-32 flex-shrink-0">
                                <label class="text-[10px] font-medium text-gray-500">
                                    Fecha Entrega <span class="text-red-500">*</span>
                                </label>
                            </div>
                            <div class="flex-1 w-full">
                                <input 
                                    type="date"
                                    v-model="fechaEntrega"
                                    :min="fechaMinima"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none bg-white"
                                    :class="{'border-red-500': errorFechaEntrega}"
                                />
                                <p v-if="errorFechaEntrega" class="text-[9px] text-red-500 mt-0.5 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle text-[8px]"></i>
                                    {{ errorFechaEntrega }}
                                </p>
                                <p v-else class="text-[9px] text-gray-400 mt-0.5">
                                    <i class="fas fa-info-circle text-[8px] mr-0.5"></i>
                                    Mínimo 1 día después de hoy
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row items-start gap-2">
                            <div class="w-32 flex-shrink-0 pt-1">
                                <label class="text-[10px] font-medium text-gray-500">Observaciones</label>
                            </div>
                            <div class="flex-1 w-full">
                                <textarea 
                                    v-model="observaciones"
                                    rows="2"
                                    placeholder="Notas adicionales (opcional)..."
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none bg-white resize-none"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- FOOTER -->
                    <div class="p-3 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row justify-end gap-2">
                        <button 
                            @click="irAtras" 
                            class="px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-md text-xs font-medium transition flex items-center justify-center gap-1.5"
                        >
                            <i class="fas fa-arrow-left text-[10px]"></i>
                            Seguir agregando
                        </button>
                        <button 
                            @click="abrirModalConfirmacion"
                            :disabled="loading || !puedeFinalizar"
                            class="px-4 py-1.5 rounded-md text-xs font-medium transition flex items-center justify-center gap-1.5 disabled:opacity-50 shadow-sm"
                            :class="puedeFinalizar 
                                ? 'bg-emerald-600 hover:bg-emerald-700 text-white' 
                                : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                        >
                            <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else-if="!puedeFinalizar" class="fas fa-ban text-[10px]"></i>
                            <i v-else class="fas fa-check-circle text-[10px]"></i>
                            {{ loading ? 'Procesando...' : (puedeFinalizar ? 'Finalizar Pedido' : 'Cumplir mínimos') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== MODALES ==================== -->
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
@media (min-width: 1024px) {
    input, select, button, textarea {
        font-size: 13px !important;
    }
}

.tabular-nums {
    font-variant-numeric: tabular-nums;
}
</style>