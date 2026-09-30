<script setup>
import { ref, computed, inject, watch } from 'vue'
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
    idIdentificador: { type: [Number, String], default: null },
    progresoGrupos: { type: Array, default: () => [] },
    cumpleMinimos: { type: Boolean, default: true },
    productosSinMinimo: { type: Array, default: () => [] },
    tipoPrecio: { type: String, default: 'sin_factura' },
    subclientes: { type: Array, default: () => [] },
    horaLimite: { type: [Number, String], default: null },
    horaLimiteFormateada: { type: String, default: null },
})

// ==================== ESTADO ====================
const loading = ref(false)
const observaciones = ref(props.pedido?.Observaciones || '')
const fechaEntrega = ref('')
const modalConfirmacionVisible = ref(false)
const errorFechaEntrega = ref('')

const tipoPrecioLocal = ref(props.tipoPrecio || 'sin_factura')
const cambiandoTipoPrecio = ref(false)

const horaActualCliente = ref(new Date())
const validandoHoraLimite = ref(false)

// ✅ Progreso como REF (para poder actualizarlo)
const progresoLocal = ref([...(props.progresoGrupos || [])])

// ✅ Productos sin mínimo como REF
const productosSinMinimoLocal = ref([...(props.productosSinMinimo || [])])

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

// ==================== HELPERS DE FECHAS ====================
const normalizarFecha = (fecha) => {
    if (!fecha) return null
    if (typeof fecha === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
        return fecha
    }
    const d = new Date(fecha)
    if (isNaN(d.getTime())) return null
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

const compararFechas = (a, b) => {
    const tsA = new Date(a + 'T00:00:00').getTime()
    const tsB = new Date(b + 'T00:00:00').getTime()
    return tsA - tsB
}

const formatearFechaLocal = (fecha) => {
    if (!fecha) return ''
    const partes = fecha.split('-')
    if (partes.length !== 3) return fecha
    return `${partes[2]}/${partes[1]}/${partes[0]}`
}

// ==================== FECHAS BASE ====================
const fechaHoy = computed(() => normalizarFecha(new Date()))

const fechaManana = computed(() => {
    const m = new Date()
    m.setDate(m.getDate() + 1)
    return normalizarFecha(m)
})

const fechaMinima = computed(() => fechaManana.value)

// ==================== HORA ====================
const horaActualTexto = computed(() => {
    const h = horaActualCliente.value.getHours()
    const m = String(horaActualCliente.value.getMinutes()).padStart(2, '0')
    return `${String(h).padStart(2, '0')}:${m}`
})

const horaFormateada = computed(() => {
    if (props.horaLimiteFormateada) return props.horaLimiteFormateada
    if (props.horaLimite) return `${String(props.horaLimite).padStart(2, '0')}:00`
    return ''
})

// ==================== ESTADOS DE FECHA ====================
const fechaVacia = computed(() => !fechaEntrega.value)

const fechaEsInvalida = computed(() => {
    if (!fechaEntrega.value) return false
    const fSel = normalizarFecha(fechaEntrega.value)
    if (!fSel) return false
    return compararFechas(fSel, fechaHoy.value) <= 0
})

const fechaEsManana = computed(() => {
    if (!fechaEntrega.value) return false
    const fSel = normalizarFecha(fechaEntrega.value)
    if (!fSel) return false
    return compararFechas(fSel, fechaManana.value) === 0
})

const fechaEsLejana = computed(() => {
    if (!fechaEntrega.value) return false
    const fSel = normalizarFecha(fechaEntrega.value)
    if (!fSel) return false
    return compararFechas(fSel, fechaManana.value) > 0
})

// ==================== ESTADOS DE HORA ====================
const fueraDeHoraLimite = computed(() => {
    if (!props.horaLimite) return false
    const hora = parseInt(props.horaLimite)
    if (isNaN(hora)) return false
    return horaActualCliente.value.getHours() >= hora
})

const cercaDeHoraLimite = computed(() => {
    if (!props.horaLimite) return false
    const hora = parseInt(props.horaLimite)
    if (isNaN(hora)) return false

    const minutosActuales = horaActualCliente.value.getHours() * 60 + horaActualCliente.value.getMinutes()
    const minutosLimite = hora * 60
    const diferencia = minutosLimite - minutosActuales

    return diferencia > 0 && diferencia <= 60
})

// ==================== ESTADO UNIFICADO DEL BANNER ====================
const estadoBanner = computed(() => {
    if (fechaVacia.value) return 'sinFecha'
    if (fechaEsInvalida.value) return 'fechaInvalida'
    if (fechaEsLejana.value) return 'lejana'
    if (fueraDeHoraLimite.value) return 'bloqueado'
    if (cercaDeHoraLimite.value) return 'cerca'
    return 'ok'
})

const bloqueaFinalizar = computed(() => {
    return estadoBanner.value === 'fechaInvalida' || estadoBanner.value === 'bloqueado'
})

// ==================== PROPS DEL BANNER ====================
const iconoBanner = computed(() => ({
    sinFecha: '⏰',
    fechaInvalida: '⚠️',
    lejana: '📅',
    ok: '✅',
    cerca: '⚠️',
    bloqueado: '⛔'
}[estadoBanner.value] || '⏰'))

const tituloBanner = computed(() => ({
    sinFecha: 'Hora límite de pedidos',
    fechaInvalida: 'Fecha de entrega inválida',
    lejana: 'Sin restricción de hora',
    ok: 'Aún puedes pedir para mañana',
    cerca: 'Última hora para pedir hoy',
    bloqueado: 'Ya no puedes pedir para mañana'
}[estadoBanner.value] || ''))

const chipBanner = computed(() => ({
    sinFecha: 'Hasta ' + horaFormateada.value,
    fechaInvalida: 'Fecha inválida',
    lejana: 'Fecha lejana',
    ok: 'Hasta ' + horaFormateada.value,
    cerca: 'Hasta ' + horaFormateada.value,
    bloqueado: 'Pasó ' + horaFormateada.value
}[estadoBanner.value] || ''))

const fraseBanner = computed(() => {
    const h = horaActualTexto.value
    const hl = horaFormateada.value
    const fechaSel = formatearFechaLocal(fechaEntrega.value)
    const fechaHoyFmt = formatearFechaLocal(fechaHoy.value)
    const fechaManFmt = formatearFechaLocal(fechaManana.value)

    switch (estadoBanner.value) {
        case 'sinFecha':
            return `Si necesitas entrega <strong>mañana</strong> (${fechaManFmt}), tu pedido debe hacerse antes de las <strong>${hl}</strong>. Después de esa hora, la entrega más próxima será <strong>pasado mañana</strong>.`
        case 'fechaInvalida':
            return `La fecha <strong>${fechaSel}</strong> no es válida. El mínimo es <strong>mañana</strong> (${fechaManFmt}). Cambia la fecha para continuar.`
        case 'lejana':
            return `Elegiste entrega para el <strong>${fechaSel}</strong>. Como es una fecha <strong>posterior a mañana</strong> (${fechaManFmt}), <strong>no aplica la hora límite</strong>. Puedes finalizar sin problema.`
        case 'ok':
            return `Son las <strong>${h}</strong>. Tienes hasta las <strong>${hl}</strong> para hacer tu pedido con entrega <strong>mañana</strong> (${fechaManFmt}). Después de esa hora, la entrega más próxima será <strong>pasado mañana</strong>.`
        case 'cerca':
            return `Son las <strong>${h}</strong>. Te queda <strong>menos de 1 hora</strong> para pedir con entrega <strong>mañana</strong> (${fechaManFmt}). Después de las <strong>${hl}</strong>, la entrega más próxima será <strong>pasado mañana</strong>.`
        case 'bloqueado':
            return `Son las <strong>${h}</strong> y ya pasó la hora límite (<strong>${hl}</strong>). Ya no se aceptan pedidos con entrega <strong>mañana</strong> (${fechaManFmt}). La fecha más próxima disponible es <strong>pasado mañana</strong>.`
        default:
            return ''
    }
})

// ==================== TOTALES ====================
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

// ==================== MÍNIMOS ====================
const faltantesGrupo = computed(() =>
    progresoLocal.value.filter(g => !g.Cumple && g.Tipo === 'grupo')
)

const faltantesProducto = computed(() =>
    progresoLocal.value.filter(g => !g.Cumple && g.Tipo === 'producto')
)

const gruposQueNoCumplen = computed(() => progresoLocal.value.filter(g => !g.Cumple))
const cumpleTodos = computed(() =>
    progresoLocal.value.length === 0 || gruposQueNoCumplen.value.length === 0
)

const tieneProductosSinMinimo = computed(() => {
    return productosSinMinimoLocal.value.length > 0
})

/**
 * ✅ Obtener el progreso de un producto por IdProducto
 */
const getProgresoProducto = (idProducto) => {
    return progresoLocal.value.find(
        p => p.Tipo === 'producto' && Number(p.IdProducto) === Number(idProducto)
    ) || null
}

/**
 * ✅ Obtener el progreso de un grupo por IdGrupoAnalisis
 */
const getProgresoGrupo = (idGrupo) => {
    return progresoLocal.value.find(
        g => g.Tipo === 'grupo' && Number(g.IdGrupoAnalisis) === Number(idGrupo)
    ) || null
}

// ✅ BOTÓN
const puedeFinalizar = computed(() => {
    if (detallesLocal.value.length === 0) return false
    if (tieneProductosSinMinimo.value) return false
    if (!cumpleTodos.value) return false
    if (fechaVacia.value) return false
    if (fechaEsInvalida.value) return false
    if (fechaEsManana.value && fueraDeHoraLimite.value) return false
    return true
})

const textoBoton = computed(() => {
    if (loading.value) return 'Procesando...'
    if (validandoHoraLimite.value) return 'Validando hora...'
    if (tieneProductosSinMinimo.value) return 'Productos sin mínimo'
    if (fechaVacia.value) return 'Selecciona fecha'
    if (fechaEsInvalida.value) return 'Fecha inválida'
    if (fechaEsManana.value && fueraDeHoraLimite.value) return 'Hora límite excedida'
    if (!cumpleTodos.value) return 'Cumplir mínimos'
    return 'Finalizar Pedido'
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

// ==================== ✅ RECARGAR PROGRESO DESDE EL BACKEND ====================
const recargarProgreso = async () => {
    try {
        const response = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/${props.pedido.IdPedidoCliente}/progreso`
        )

        if (response.data.success) {
            progresoLocal.value = response.data.data.progresoGrupos || []
            productosSinMinimoLocal.value = response.data.data.productosSinMinimo || []
        }
    } catch (error) {
        console.error('❌ Error recargando progreso:', error)
    }
}

// ==================== WATCH: Validar al cambiar fecha ====================
watch(fechaEntrega, (nueva) => {
    if (!nueva) {
        errorFechaEntrega.value = ''
        return
    }

    const fSel = normalizarFecha(nueva)
    if (!fSel) {
        errorFechaEntrega.value = 'Formato de fecha inválido'
        return
    }

    if (compararFechas(fSel, fechaHoy.value) <= 0) {
        errorFechaEntrega.value = `La fecha debe ser mínimo 1 día después de hoy (${formatearFechaLocal(fechaHoy.value)})`
        return
    }

    errorFechaEntrega.value = ''
})

// ==================== FUNCIONES ====================
const irAtras = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create')
}

const validarFechaEntrega = () => {
    if (!fechaEntrega.value) {
        errorFechaEntrega.value = 'La fecha de entrega es obligatoria'
        return false
    }

    const fSel = normalizarFecha(fechaEntrega.value)
    if (!fSel || compararFechas(fSel, fechaHoy.value) <= 0) {
        errorFechaEntrega.value = `La fecha debe ser mínimo 1 día después de hoy (${formatearFechaLocal(fechaHoy.value)})`
        return false
    }

    errorFechaEntrega.value = ''
    return true
}

const validarHoraLimite = async () => {
    if (!props.horaLimite) return true
    if (!fechaEsManana.value) return true

    validandoHoraLimite.value = true
    try {
        const response = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/pedidos-clientes/api/validar-hora-limite',
            { FechaEntrega: fechaEntrega.value }
        )

        if (response.data.success && !response.data.valido) {
            toast?.error('Hora límite excedida', response.data.mensaje)
            return false
        }

        return true
    } catch (error) {
        console.error('Error validando hora límite:', error)
        return true
    } finally {
        validandoHoraLimite.value = false
    }
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

            await recargarProgreso()

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

const abrirModalConfirmacion = async () => {
    if (detallesLocal.value.length === 0) {
        toast?.warning('Carrito vacío', 'Agregue productos antes de finalizar')
        return
    }

    if (tieneProductosSinMinimo.value) {
        const nombres = productosSinMinimoLocal.value.map(p => `${p.Codigo} - ${p.Descripcion}`).join('\n')
        toast?.error('Productos sin mínimo', `Los siguientes productos ya no tienen mínimo configurado:\n${nombres}`)
        return
    }

    if (!cumpleTodos.value) {
        const items = gruposQueNoCumplen.value.map(g => {
            const tipo = g.Tipo === 'producto' ? 'Producto' : 'Grupo'
            return `[${tipo}] ${g.NombreGrupo}: faltan ${g.Falta} und`
        }).join('\n')
        toast?.error('Mínimos incompletos', `No se puede finalizar:\n${items}`)
        return
    }

    if (!validarFechaEntrega()) {
        toast?.error('Error', errorFechaEntrega.value)
        return
    }

    const horaOk = await validarHoraLimite()
    if (!horaOk) return

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
            IdSubClienteOperador: item.IdSubClienteOperador,
            productos: item.productos.map(p => ({
                IdProducto: p.IdProducto,
                Cantidad: p.Cantidad,
                Precio: p.Precio,
                IdGrupoAnalisis: p.IdGrupoAnalisis || null,
            }))
        }
    }
    modalEdicionVisible.value = true
}

const actualizarContenedor = async (data) => {
    loading.value = true
    try {
        const payload = {
            ...data,
            IdSubClienteOperador: data.IdSubClienteOperador
                ? Number(data.IdSubClienteOperador)
                : null
        }

        const response = await axios.put(
            '/operacion/pedidos/clientes-mayoristas/pedidos-clientes/carrito/contenedor',
            payload
        )

        if (response.data.success) {
            actualizarDetalleLocal(data, payload)
            await recargarProgreso()

            toast?.success('Éxito', 'Contenedor actualizado correctamente')
        } else {
            toast?.error('Error', response.data.message || 'Error al actualizar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al actualizar el contenedor')
    } finally {
        loading.value = false
        modalEdicionVisible.value = false
    }
}

const actualizarDetalleLocal = (data, payload) => {
    const orden = Number(data.OrdenContenedor)
    const index = detallesLocal.value.findIndex(item => Number(item.Orden) === orden)

    if (index === -1) return

    const contenedorActual = detallesLocal.value[index]

    const subCliente = props.subclientes.find(
        s => Number(s.IdSubClienteOperador) === Number(payload.IdSubClienteOperador)
    )

    const nuevosProductos = data.productos
        .filter(p => Number(p.Cantidad) > 0)
        .map(p => {
            const productoOriginal = contenedorActual.productos.find(
                op => op.IdProducto === p.IdProducto
            ) || {}

            return {
                ...productoOriginal,
                IdProducto: p.IdProducto,
                Cantidad: Number(p.Cantidad),
                Precio: Number(p.Precio),
                IdGrupoAnalisis: p.IdGrupoAnalisis || productoOriginal.IdGrupoAnalisis || null,
                Subtotal: Number(p.Cantidad) * Number(p.Precio)
            }
        })

    const totalUnidadesCalc = nuevosProductos.reduce((sum, p) => sum + (Number(p.Cantidad) || 0), 0)
    const subtotal = nuevosProductos.reduce((sum, p) => sum + (Number(p.Cantidad) || 0) * (Number(p.Precio) || 0), 0)

    if (nuevosProductos.length === 0) {
        detallesLocal.value = detallesLocal.value.filter(
            d => Number(d.Orden) !== orden
        )
    } else {
        detallesLocal.value[index] = {
            ...contenedorActual,
            IdSubClienteOperador: payload.IdSubClienteOperador,
            SubClienteNombre: subCliente ? subCliente.Nombre : null,
            productos: nuevosProductos,
            total_unidades: totalUnidadesCalc,
            subtotal: subtotal
        }
    }

    detallesLocal.value = [...detallesLocal.value]
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
        const response = await axios.delete(
            `/operacion/pedidos/clientes-mayoristas/pedidos-clientes/carrito/detalle/${detalleId}`
        )

        if (response.data.success) {
            detallesLocal.value = detallesLocal.value.filter(
                d => Number(d.Orden) !== Number(item.Orden)
            )

            await recargarProgreso()

            toast?.success('Éxito', 'Contenedor eliminado')

            if (detallesLocal.value.length === 0) {
                router.get('/operacion/pedidos/clientes-mayoristas/pedidos-clientes/create')
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

                <!-- HEADER -->
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

                <!-- BANNER HORA LÍMITE -->
                <div
                    v-if="horaLimite"
                    class="mb-3 rounded-xl border overflow-hidden transition-all"
                    :class="{
                        'bg-emerald-50 border-emerald-300': estadoBanner === 'ok',
                        'bg-amber-50 border-amber-300': estadoBanner === 'cerca',
                        'bg-red-50 border-red-300': estadoBanner === 'bloqueado' || estadoBanner === 'fechaInvalida',
                        'bg-blue-50 border-blue-300': estadoBanner === 'lejana',
                        'bg-slate-50 border-slate-300': estadoBanner === 'sinFecha'
                    }"
                >
                    <div
                        class="px-3 py-2 flex items-center justify-between gap-2 border-b"
                        :class="{
                            'bg-emerald-100 border-emerald-200': estadoBanner === 'ok',
                            'bg-amber-100 border-amber-200': estadoBanner === 'cerca',
                            'bg-red-100 border-red-200': estadoBanner === 'bloqueado' || estadoBanner === 'fechaInvalida',
                            'bg-blue-100 border-blue-200': estadoBanner === 'lejana',
                            'bg-slate-100 border-slate-200': estadoBanner === 'sinFecha'
                        }"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-base flex-shrink-0">{{ iconoBanner }}</span>
                            <span
                                class="text-xs font-bold uppercase tracking-wide truncate"
                                :class="{
                                    'text-emerald-800': estadoBanner === 'ok',
                                    'text-amber-800': estadoBanner === 'cerca',
                                    'text-red-800': estadoBanner === 'bloqueado' || estadoBanner === 'fechaInvalida',
                                    'text-blue-800': estadoBanner === 'lejana',
                                    'text-slate-700': estadoBanner === 'sinFecha'
                                }"
                            >
                                {{ tituloBanner }}
                            </span>
                        </div>
                        <span
                            class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full flex-shrink-0 whitespace-nowrap"
                            :class="{
                                'bg-emerald-200 text-emerald-800': estadoBanner === 'ok',
                                'bg-amber-200 text-amber-800': estadoBanner === 'cerca',
                                'bg-red-200 text-red-800': estadoBanner === 'bloqueado' || estadoBanner === 'fechaInvalida',
                                'bg-blue-200 text-blue-800': estadoBanner === 'lejana',
                                'bg-slate-200 text-slate-700': estadoBanner === 'sinFecha'
                            }"
                        >
                            {{ chipBanner }}
                        </span>
                    </div>

                    <div class="px-3 py-2.5">
                        <p
                            class="text-[12px] leading-relaxed"
                            :class="{
                                'text-emerald-900': estadoBanner === 'ok',
                                'text-amber-900': estadoBanner === 'cerca',
                                'text-red-900': estadoBanner === 'bloqueado' || estadoBanner === 'fechaInvalida',
                                'text-blue-900': estadoBanner === 'lejana',
                                'text-slate-700': estadoBanner === 'sinFecha'
                            }"
                            v-html="fraseBanner"
                        ></p>

                        <details class="mt-2 group">
                            <summary
                                class="text-[10px] cursor-pointer font-semibold flex items-center gap-1 opacity-90 hover:opacity-100 select-none"
                                :class="{
                                    'text-emerald-700': estadoBanner === 'ok',
                                    'text-amber-700': estadoBanner === 'cerca',
                                    'text-red-700': estadoBanner === 'bloqueado' || estadoBanner === 'fechaInvalida',
                                    'text-blue-700': estadoBanner === 'lejana',
                                    'text-slate-600': estadoBanner === 'sinFecha'
                                }"
                            >
                                <i class="fas fa-chevron-right text-[8px] transition-transform group-open:rotate-90"></i>
                                Ver cómo funciona la hora límite
                            </summary>

                            <div
                                class="mt-2 p-2.5 bg-white/70 rounded-lg border space-y-2"
                                :class="{
                                    'border-emerald-200': estadoBanner === 'ok',
                                    'border-amber-200': estadoBanner === 'cerca',
                                    'border-red-200': estadoBanner === 'bloqueado' || estadoBanner === 'fechaInvalida',
                                    'border-blue-200': estadoBanner === 'lejana',
                                    'border-slate-200': estadoBanner === 'sinFecha'
                                }"
                            >
                                <div class="flex items-start gap-2">
                                    <span class="text-sm flex-shrink-0">🕐</span>
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-700">Regla principal</p>
                                        <p class="text-[10px] text-gray-600">
                                            Si quieres entrega <strong>mañana</strong>, tu pedido debe hacerse
                                            <strong>antes de las {{ horaFormateada }}</strong>.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-2">
                                    <span class="text-sm flex-shrink-0">📅</span>
                                    <div class="flex-1">
                                        <p class="text-[10px] font-bold text-gray-700 mb-1">Ejemplos según la hora</p>
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-1.5 text-[10px]">
                                                <span class="text-emerald-600 font-bold">✅</span>
                                                <span class="text-gray-600">
                                                    Antes de las <strong>{{ horaFormateada }}</strong>
                                                    → Puedes pedir para <strong>mañana</strong>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-[10px]">
                                                <span class="text-red-600 font-bold">❌</span>
                                                <span class="text-gray-600">
                                                    Después de las <strong>{{ horaFormateada }}</strong>
                                                    → Solo para <strong>pasado mañana</strong> en adelante
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- SELECTOR TIPO DE PRECIO -->
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

                <!-- ALERTA: PRODUCTOS SIN MÍNIMO -->
                <div v-if="tieneProductosSinMinimo" class="bg-red-50 border-l-4 border-red-600 rounded-xl p-3 mb-3">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-ban text-red-600 text-base flex-shrink-0 mt-0.5"></i>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-red-800 text-xs">Productos sin mínimo configurado</h3>
                            <p class="text-[10px] text-red-700 mt-0.5">
                                Los siguientes productos ya no tienen mínimo configurado. Contacta al administrador:
                            </p>
                            <ul class="mt-1.5 space-y-0.5">
                                <li v-for="prod in productosSinMinimoLocal" :key="prod.IdProducto"
                                    class="text-[10px] text-red-700 flex items-start gap-1.5">
                                    <i class="fas fa-circle text-[5px] mt-1.5 flex-shrink-0"></i>
                                    <span><strong>{{ prod.Codigo }}</strong> - {{ prod.Descripcion }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- ALERTA DE MÍNIMOS FALTANTES -->
                <div v-else-if="!cumpleTodos" class="bg-red-50 border-l-4 border-red-500 rounded-xl p-3 mb-3">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-exclamation-triangle text-red-500 text-base flex-shrink-0 mt-0.5"></i>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-red-800 text-xs">No se puede finalizar el pedido</h3>
                            <p class="text-[10px] text-red-700 mt-0.5">Faltan cumplir los siguientes mínimos:</p>

                            <div v-if="faltantesGrupo.length > 0" class="mt-2">
                                <p class="text-[10px] font-bold text-indigo-700 mb-1">
                                    <i class="fas fa-layer-group mr-1"></i>Mínimos de Grupo:
                                </p>
                                <ul class="space-y-0.5">
                                    <li v-for="grupo in faltantesGrupo" :key="'g-' + grupo.IdGrupoAnalisis"
                                        class="text-[10px] text-red-700 flex items-start gap-1.5">
                                        <i class="fas fa-circle text-[5px] mt-1.5 flex-shrink-0"></i>
                                        <span>
                                            <strong>{{ grupo.NombreGrupo }}:</strong>
                                            faltan {{ grupo.Falta }} und
                                            <span class="opacity-70">(tienes {{ grupo.CantidadPedida }}, mínimo {{ grupo.CantidadMinima }})</span>
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="faltantesProducto.length > 0" class="mt-2">
                                <p class="text-[10px] font-bold text-purple-700 mb-1">
                                    <i class="fas fa-box mr-1"></i>Mínimos de Producto:
                                </p>
                                <ul class="space-y-0.5">
                                    <li v-for="prod in faltantesProducto" :key="'p-' + prod.IdProducto"
                                        class="text-[10px] text-red-700 flex items-start gap-1.5">
                                        <i class="fas fa-circle text-[5px] mt-1.5 flex-shrink-0"></i>
                                        <span>
                                            <strong>{{ prod.NombreGrupo }}:</strong>
                                            faltan {{ prod.Falta }} und
                                            <span class="opacity-70">(tienes {{ prod.CantidadPedida }}, mínimo {{ prod.CantidadMinima }})</span>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PROGRESO OK -->
                <div v-else-if="progresoLocal.length > 0" class="bg-emerald-50 border-l-4 border-emerald-500 rounded-xl p-3 mb-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-emerald-500 text-base flex-shrink-0"></i>
                        <div class="flex-1">
                            <h3 class="font-bold text-emerald-800 text-xs">¡Todo listo!</h3>
                            <p class="text-[10px] text-emerald-700">Todos los mínimos están cumplidos. Puede finalizar el pedido.</p>
                        </div>
                    </div>
                </div>

                <!-- CARD PRINCIPAL -->
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
                                <!-- Header del contenedor -->
                                <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-1.5 bg-gray-50 border-b border-gray-200">
                                    <div class="flex items-center gap-1.5 flex-wrap min-w-0 flex-1">
                                        <span class="text-[9px] font-mono bg-primary-600 text-white px-1.5 py-0.5 rounded font-bold flex-shrink-0">
                                            #{{ idx + 1 }}
                                        </span>
                                        <span class="font-semibold text-gray-800 text-xs truncate">{{ item.Codigo }}</span>
                                        <span class="text-[9px] text-gray-500 bg-white px-1.5 py-0.5 rounded border border-gray-200 flex-shrink-0">
                                            Cap: {{ formatearNumero(item.CapacidadTotal) }}
                                        </span>
                                        <span
                                            v-if="item.SubClienteNombre"
                                            class="text-[9px] text-blue-700 bg-blue-100 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0"
                                        >
                                            <i class="fas fa-user-tag mr-0.5"></i>
                                            {{ item.SubClienteNombre }}
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

                                <!-- ✅ Mínimo del grupo (si aplica) -->
                                <div
                                    v-if="item.productos.length > 0 && item.productos[0].IdGrupoAnalisis && getProgresoGrupo(item.productos[0].IdGrupoAnalisis)"
                                    class="px-3 py-1.5 border-b flex items-center gap-2 flex-wrap"
                                    :class="getProgresoGrupo(item.productos[0].IdGrupoAnalisis).Cumple
                                        ? 'bg-green-50/40 border-green-100'
                                        : 'bg-indigo-50/40 border-indigo-100'"
                                >
                                    <i class="fas fa-layer-group text-[10px]"
                                       :class="getProgresoGrupo(item.productos[0].IdGrupoAnalisis).Cumple ? 'text-green-500' : 'text-indigo-500'"></i>
                                    <span class="text-[10px] font-bold"
                                          :class="getProgresoGrupo(item.productos[0].IdGrupoAnalisis).Cumple ? 'text-green-700' : 'text-indigo-700'">
                                        Mínimo del grupo:
                                    </span>
                                    <span class="text-[10px] font-bold tabular-nums"
                                          :class="getProgresoGrupo(item.productos[0].IdGrupoAnalisis).Cumple ? 'text-green-600' : 'text-indigo-600'">
                                        {{ formatearNumero(getProgresoGrupo(item.productos[0].IdGrupoAnalisis).CantidadPedida) }}
                                        /
                                        {{ formatearNumero(getProgresoGrupo(item.productos[0].IdGrupoAnalisis).CantidadMinima) }}
                                    </span>
                                    <span v-if="!getProgresoGrupo(item.productos[0].IdGrupoAnalisis).Cumple"
                                          class="text-[9px] text-orange-600 font-medium bg-orange-100 px-1.5 py-0.5 rounded-full">
                                        <i class="fas fa-exclamation-circle mr-0.5"></i>
                                        faltan {{ formatearNumero(getProgresoGrupo(item.productos[0].IdGrupoAnalisis).Falta) }}
                                    </span>
                                    <span v-else class="text-[9px] text-green-600 font-medium bg-green-100 px-1.5 py-0.5 rounded-full">
                                        <i class="fas fa-check-circle mr-0.5"></i>
                                        Cumple
                                    </span>
                                </div>

                                <!-- Tabla de productos -->
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="border-b border-gray-200 bg-gray-50/50 text-[9px] font-semibold text-gray-400 uppercase tracking-wider">
                                                <th class="py-1.5 px-3">Producto</th>
                                                <th class="py-1.5 px-3 text-right w-14">Cant.</th>
                                                <th class="py-1.5 px-3 text-right w-14">Mín.</th>
                                                <th class="py-1.5 px-3 text-center w-24">Estado</th>
                                                <th class="py-1.5 px-3 text-right w-20">Precio</th>
                                                <th class="py-1.5 px-3 text-right w-24 text-primary-600">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr
                                                v-for="producto in item.productos"
                                                :key="producto.IdProducto"
                                                class="hover:bg-gray-50/80 transition"
                                                :class="{
                                                    'bg-orange-50/40': getProgresoProducto(producto.IdProducto) && !getProgresoProducto(producto.IdProducto).Cumple,
                                                    'bg-green-50/20': getProgresoProducto(producto.IdProducto) && getProgresoProducto(producto.IdProducto).Cumple
                                                }"
                                            >
                                                <td class="py-1.5 px-3 text-gray-700 truncate max-w-[260px]" :title="producto.Descripcion">
                                                    {{ producto.Descripcion }}
                                                </td>

                                                <!-- Cantidad -->
                                                <td class="py-1.5 px-3 text-right font-medium text-gray-800 tabular-nums">
                                                    {{ formatearNumero(producto.Cantidad) }}
                                                </td>

                                                <!-- Mínimo -->
                                                <td class="py-1.5 px-3 text-right tabular-nums text-[11px]"
                                                    :class="getProgresoProducto(producto.IdProducto) ? 'font-semibold text-indigo-700' : 'text-gray-300'">
                                                    <template v-if="getProgresoProducto(producto.IdProducto)">
                                                        {{ formatearNumero(getProgresoProducto(producto.IdProducto).CantidadMinima) }}
                                                    </template>
                                                    <template v-else>—</template>
                                                </td>

                                                <!-- Estado -->
                                                <td class="py-1.5 px-3 text-center">
                                                    <template v-if="getProgresoProducto(producto.IdProducto)">
                                                        <span
                                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold whitespace-nowrap"
                                                            :class="getProgresoProducto(producto.IdProducto).Cumple
                                                                ? 'bg-green-100 text-green-700'
                                                                : 'bg-orange-100 text-orange-700'"
                                                        >
                                                            <i :class="getProgresoProducto(producto.IdProducto).Cumple ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
                                                            {{ getProgresoProducto(producto.IdProducto).Cumple
                                                                ? 'OK'
                                                                : `Falta ${formatearNumero(getProgresoProducto(producto.IdProducto).Falta)}` }}
                                                        </span>
                                                    </template>
                                                    <template v-else>
                                                        <span class="text-[9px] text-gray-300">—</span>
                                                    </template>
                                                </td>

                                                <!-- Precio -->
                                                <td class="py-1.5 px-3 text-right text-gray-600 tabular-nums">
                                                    Bs. {{ formatearPrecio(producto.Precio || 0) }}
                                                </td>

                                                <!-- Subtotal -->
                                                <td class="py-1.5 px-3 text-right font-bold text-primary-600 tabular-nums">
                                                    Bs. {{ formatearPrecio((Number(producto.Cantidad) || 0) * (Number(producto.Precio) || 0)) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

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
                                    :class="{
                                        'border-red-500': errorFechaEntrega || fechaEsInvalida,
                                        'border-amber-400': fechaEsManana && !fueraDeHoraLimite && !errorFechaEntrega
                                    }"
                                />
                                <p v-if="errorFechaEntrega" class="text-[9px] text-red-500 mt-0.5 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle text-[8px]"></i>
                                    {{ errorFechaEntrega }}
                                </p>
                                <p v-else-if="fechaEsManana && fueraDeHoraLimite" class="text-[9px] text-red-500 mt-0.5 flex items-center gap-1">
                                    <i class="fas fa-clock text-[8px]"></i>
                                    Ya pasó la hora límite ({{ horaFormateada }}) para entrega mañana.
                                </p>
                                <p v-else class="text-[9px] text-gray-500 mt-0.5">
                                    <i class="fas fa-info-circle text-[8px] mr-0.5"></i>
                                    Mínimo 1 día después de hoy.
                                    <span v-if="horaLimite" class="text-amber-600 font-medium">
                                        Para entrega <strong>mañana</strong>, el pedido debe realizarse antes de las
                                        <strong>{{ horaFormateada }}</strong>.
                                    </span>
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
                            :disabled="loading || !puedeFinalizar || validandoHoraLimite"
                            class="px-4 py-1.5 rounded-md text-xs font-medium transition flex items-center justify-center gap-1.5 disabled:opacity-50 shadow-sm"
                            :class="puedeFinalizar
                                ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                        >
                            <i v-if="loading || validandoHoraLimite" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else-if="tieneProductosSinMinimo" class="fas fa-ban text-[10px]"></i>
                            <i v-else-if="fechaEsInvalida" class="fas fa-exclamation-triangle text-[10px]"></i>
                            <i v-else-if="fechaEsManana && fueraDeHoraLimite" class="fas fa-clock text-[10px]"></i>
                            <i v-else-if="!puedeFinalizar" class="fas fa-ban text-[10px]"></i>
                            <i v-else class="fas fa-check-circle text-[10px]"></i>
                            {{ textoBoton }}
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODALES -->
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
            :subclientes="subclientes"
            :nombreOperador="operadorNombre"
            :idSubClienteOperadorDefault="contenedorSeleccionado?._datosEdicion?.IdSubClienteOperador || null"
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