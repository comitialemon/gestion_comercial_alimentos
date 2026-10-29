<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    operadores: { type: Array, default: () => [] },
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== TABS ====================
const tipoActivo = ref('DEFECTUOSO')
const estadoActivo = ref('Pendiente de Revisión')

const estadosDisponibles = computed(() => [
    { key: 'Pendiente de Revisión', label: 'Pendientes', icon: 'fa-clock' },
    { key: 'En Revisión', label: 'En Revisión', icon: 'fa-search' },
    { key: 'Aprobado por Calidad', label: 'Aprobados', icon: 'fa-check-circle' },
    { key: 'Rechazado', label: 'Rechazados', icon: 'fa-times-circle' },
])

const cambiarTipo = (tipo) => {
    tipoActivo.value = tipo
    estadoActivo.value = 'Pendiente de Revisión'
}

// ==================== BUSCADOR ====================
const buscador = ref('')

// ==================== AGRUPACIÓN Y FILTRADO ====================
const operadoresFiltrados = computed(() => {
    return props.operadores.map(op => {
        const clientesFiltrados = op.Clientes.map(cliente => {
            const ticketsFiltrados = cliente.Reclamos.filter(r =>
                r.TipoReclamo === tipoActivo.value && r.Estado === estadoActivo.value
            )
            return { ...cliente, ticketsFiltrados }
        }).filter(cliente => {
            if (cliente.ticketsFiltrados.length === 0) return false
            if (buscador.value) return cliente.NombreCliente.toLowerCase().includes(buscador.value.toLowerCase())
            return true
        })

        const resumenProductos = {}
        clientesFiltrados.forEach(cliente => {
            cliente.ticketsFiltrados.forEach(ticket => {
                ticket.detalles.forEach(det => {
                    const id = det.IdProducto
                    if (!resumenProductos[id]) {
                        resumenProductos[id] = {
                            IdProducto: id,
                            Descripcion: det.producto?.Descripcion || ('Prod. ' + id),
                            TotalReclamado: 0
                        }
                    }
                    resumenProductos[id].TotalReclamado += Number(det.CantidadReclamada) || 0
                })
            })
        })

        return { 
            ...op, 
            clientesFiltrados,
            resumenProductos: Object.values(resumenProductos)
        }
    }).filter(op => op.clientesFiltrados.length > 0)
})

const conteoTab = (estado) => {
    let count = 0
    props.operadores.forEach(op => {
        op.Clientes.forEach(c => {
            count += c.Reclamos.filter(r => r.TipoReclamo === tipoActivo.value && r.Estado === estado).length
        })
    })
    return count
}

// ==================== ACORDEÓN ====================
const operadoresAbiertos = ref(new Set())

const toggleOperador = (idOperador) => {
    const set = new Set(operadoresAbiertos.value)
    if (set.has(idOperador)) set.delete(idOperador)
    else set.add(idOperador)
    operadoresAbiertos.value = set
}

// ==================== MODALES Y ACCIONES ====================
const modalAprobar = ref({ visible: false, reclamo: null, cantidades: {}, observacion: '' })
const modalRechazar = ref({ visible: false, reclamo: null, motivo: '' })
const modalRecibir = ref({ visible: false, reclamo: null })
const procesando = ref(false)

const abrirAprobar = (reclamo) => {
    const cantidades = {}
    reclamo.detalles.forEach(d => { cantidades[d.IdReclamoDetalle] = d.CantidadAprobada ?? d.CantidadReclamada })
    modalAprobar.value = { visible: true, reclamo, cantidades, observacion: '' }
}

const forzarCantidad = (detalle) => {
    let valor = Number(modalAprobar.value.cantidades[detalle.IdReclamoDetalle])
    if (isNaN(valor) || valor < 0) valor = 0
    else if (valor > detalle.CantidadReclamada) valor = detalle.CantidadReclamada
    valor = Math.floor(valor)
    modalAprobar.value.cantidades[detalle.IdReclamoDetalle] = valor
}

const abrirRechazar = (reclamo) => { modalRechazar.value = { visible: true, reclamo, motivo: '' } }
const abrirRecibir = (reclamo) => { modalRecibir.value = { visible: true, reclamo } }

const cerrarModales = () => {
    if (procesando.value) return
    modalAprobar.value.visible = false
    modalRechazar.value.visible = false
    modalRecibir.value.visible = false
}

const confirmarRecibir = async () => {
    const reclamo = modalRecibir.value.reclamo
    if (!reclamo) return
    procesando.value = true
    try {
        const { data } = await axios.post(`/operacion/pedidos/clientes-mayoristas/calidad/${reclamo.IdReclamo}/recibir`)
        if (data.success) {
            modalRecibir.value.visible = false
            toast?.success('Éxito', data.message || 'Recibido en fábrica')
            router.reload({ only: ['operadores'] })
        }
    } catch (e) {
        toast?.error('Error', e.response?.data?.message || 'Error al recibir')
    } finally {
        procesando.value = false
    }
}

const confirmarAprobar = async () => {
    const r = modalAprobar.value.reclamo
    if (!r) return
    for (const d of r.detalles) {
        const cant = Number(modalAprobar.value.cantidades[d.IdReclamoDetalle]) || 0
        if (cant < 0 || cant > d.CantidadReclamada) {
            toast?.warning('Atención', 'Las cantidades ingresadas no son válidas.')
            return
        }
    }
    const productos_aprobados = Object.entries(modalAprobar.value.cantidades).map(([id, cant]) => ({
        IdReclamoDetalle: Number(id),
        CantidadAprobada: Number(cant) || 0
    }))
    if (productos_aprobados.every(p => p.CantidadAprobada === 0)) {
        toast?.warning('Atención', 'Debes aprobar al menos 1 unidad.')
        return
    }
    procesando.value = true
    try {
        const { data } = await axios.post(`/operacion/pedidos/clientes-mayoristas/calidad/${r.IdReclamo}/aprobar`, {
            observacion: modalAprobar.value.observacion || null,
            productos_aprobados
        })
        if (data.success) {
            modalAprobar.value.visible = false
            toast?.success('Éxito', data.message || 'Aprobado')
            router.reload({ only: ['operadores'] })
        }
    } catch (e) {
        toast?.error('Error', e.response?.data?.message || 'Error al aprobar')
    } finally {
        procesando.value = false
    }
}

const confirmarRechazar = async () => {
    const r = modalRechazar.value.reclamo
    if (!r) return
    if (!modalRechazar.value.motivo.trim()) {
        toast?.warning('Atención', 'Debes indicar el motivo.')
        return
    }
    procesando.value = true
    try {
        const { data } = await axios.post(`/operacion/pedidos/clientes-mayoristas/calidad/${r.IdReclamo}/rechazar`, {
            motivo_rechazo: modalRechazar.value.motivo
        })
        if (data.success) {
            modalRechazar.value.visible = false
            toast?.success('Éxito', data.message || 'Rechazado')
            router.reload({ only: ['operadores'] })
        }
    } catch (e) {
        toast?.error('Error', e.response?.data?.message || 'Error al rechazar')
    } finally {
        procesando.value = false
    }
}

// ==================== HELPERS ====================
const formatFecha = (f) => !f ? '—' : new Date(f).toLocaleString('es-BO', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
})

const puedeRecibir = (r) => r.TipoReclamo === 'DEFECTUOSO' && r.Estado === 'Pendiente de Revisión'
const puedeAprobar = (r) => r.TipoReclamo === 'FALTANTE'
    ? ['Pendiente de Revisión', 'En Revisión'].includes(r.Estado)
    : r.Estado === 'En Revisión'
const puedeRechazar = (r) => puedeAprobar(r)

// ==================== AUTO-ABRIR CARPETA Y SCROLL AL TICKET ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)

    const urlParams = new URLSearchParams(window.location.search)
    const idTicket = urlParams.get('ticket')
    
    if (idTicket) {
        const idReclamoBuscado = parseInt(idTicket)
        
        props.operadores.forEach(op => {
            op.Clientes.forEach(cliente => {
                const ticketEncontrado = cliente.Reclamos.find(r => r.IdReclamo === idReclamoBuscado)
                
                if (ticketEncontrado) {
                    tipoActivo.value = ticketEncontrado.TipoReclamo
                    estadoActivo.value = ticketEncontrado.Estado
                    
                    const set = new Set(operadoresAbiertos.value)
                    set.add(op.IdOperador)
                    operadoresAbiertos.value = set
                    
                    setTimeout(() => {
                        const el = document.getElementById(`ticket-${idReclamoBuscado}`)
                        if (el) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'center' })
                            el.classList.add('ring-4', 'ring-primary-500', 'ring-opacity-50')
                            
                            setTimeout(() => {
                                el.classList.remove('ring-4', 'ring-primary-500', 'ring-opacity-50')
                            }, 3000)
                        }
                    }, 500)
                }
            })
        })
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- HEADER -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-clipboard-check text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Panel de Calidad</h1>
                            <p class="text-xs text-gray-500">Reclamos agrupados por Operador y Cliente</p>
                        </div>
                    </div>
                    <div class="relative w-full sm:w-56">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                        <input type="text" v-model="buscador" placeholder="Buscar cliente..." class="w-full border border-gray-300 rounded-md pl-7 pr-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                    </div>
                </div>

                <!-- TABS TIPO -->
                <div class="flex gap-2 mb-3">
                    <button @click="cambiarTipo('DEFECTUOSO')" class="px-3 py-1.5 text-xs font-semibold rounded-md transition flex items-center gap-1.5" :class="tipoActivo === 'DEFECTUOSO' ? 'bg-rose-100 text-rose-700 shadow-sm' : 'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'">
                        <i class="fas fa-exclamation-triangle text-[10px]"></i> Defectuosos
                        <span v-if="conteoTab(estadoActivo) > 0 && tipoActivo === 'DEFECTUOSO'" class="text-[9px] px-1.5 py-0.5 rounded-full font-bold bg-rose-200 text-rose-700">{{ conteoTab(estadoActivo) }}</span>
                    </button>
                    <button @click="cambiarTipo('FALTANTE')" class="px-3 py-1.5 text-xs font-semibold rounded-md transition flex items-center gap-1.5" :class="tipoActivo === 'FALTANTE' ? 'bg-amber-100 text-amber-700 shadow-sm' : 'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'">
                        <i class="fas fa-box-open text-[10px]"></i> Faltantes
                        <span v-if="conteoTab(estadoActivo) > 0 && tipoActivo === 'FALTANTE'" class="text-[9px] px-1.5 py-0.5 rounded-full font-bold bg-amber-200 text-amber-700">{{ conteoTab(estadoActivo) }}</span>
                    </button>
                </div>

                <!-- TABS ESTADO -->
                <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
                    <div class="flex w-full overflow-x-auto">
                        <button v-for="e in estadosDisponibles" :key="e.key" @click="estadoActivo = e.key" class="flex-1 py-2 text-xs font-semibold border-b-2 transition flex justify-center items-center gap-1.5 whitespace-nowrap px-2" :class="estadoActivo === e.key ? 'border-primary-600 text-primary-700 bg-primary-50/50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'">
                            <i :class="['fas', e.icon, 'text-[10px]']"></i> {{ e.label }}
                            <span v-if="conteoTab(e.key) > 0" class="text-[9px] px-1.5 py-0.5 rounded-full font-bold" :class="estadoActivo === e.key ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-600'">{{ conteoTab(e.key) }}</span>
                        </button>
                    </div>
                </div>

                <!-- LISTA VACÍA -->
                <div v-if="operadoresFiltrados.length === 0" class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fas fa-inbox text-gray-400 text-2xl"></i></div>
                    <h3 class="text-sm font-semibold text-gray-700">No hay reclamos</h3>
                    <p class="text-xs text-gray-400 mt-1">No hay reclamos en esta pestaña.</p>
                </div>

                <!-- LISTA DE OPERADORES -->
                <div v-else class="space-y-3">
                    <div v-for="operador in operadoresFiltrados" :key="operador.IdOperador" class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                        <!-- CABECERA DEL OPERADOR -->
                        <div @click="toggleOperador(operador.IdOperador)" class="px-3 py-2 bg-primary-50 hover:bg-primary-100 transition cursor-pointer select-none border-b border-primary-100 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-primary-200 flex items-center justify-center text-primary-700 flex-shrink-0"><i class="fas fa-user-tie text-[10px]"></i></div>
                                <div>
                                    <p class="text-xs font-bold text-gray-800">Operador: {{ operador.NombreOperador }}</p>
                                    <p class="text-[9px] text-gray-500">{{ operador.clientesFiltrados.length }} cliente(s) con reclamos</p>
                                </div>
                            </div>
                            <i class="fas text-primary-500 text-[10px]" :class="operadoresAbiertos.has(operador.IdOperador) ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                        </div>

                        <!-- CONTENIDO DEL OPERADOR -->
                        <div v-show="operadoresAbiertos.has(operador.IdOperador)" class="p-3 bg-gray-50/50 space-y-4">
                            <div v-if="operador.resumenProductos.length > 0" class="bg-emerald-50 border border-emerald-200 rounded-lg p-2.5">
                                <p class="text-[10px] font-bold text-emerald-700 uppercase mb-1.5 flex items-center gap-1"><i class="fas fa-chart-bar"></i> Resumen de productos (en esta pestaña)</p>
                                <div class="flex flex-wrap gap-2">
                                    <span v-for="p in operador.resumenProductos" :key="p.IdProducto" class="inline-flex items-center gap-1 bg-white border border-emerald-200 px-2 py-1 rounded-md text-[10px] shadow-sm">
                                        <span class="text-gray-700 truncate max-w-[200px]">{{ p.Descripcion }}</span>
                                        <span class="font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">{{ p.TotalReclamado }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Iteramos a los clientes -->
                            <div v-for="cliente in operador.clientesFiltrados" :key="cliente.IdCliente" class="relative">
                                <!-- Cabecera del Cliente -->
                                <div class="flex items-center gap-2 mb-2 px-1">
                                    <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 flex-shrink-0"><i class="fas fa-user text-[9px]"></i></div>
                                    <div>
                                        <h3 class="text-xs font-bold text-gray-800">Cliente: {{ cliente.NombreCliente }}</h3>
                                        <p class="text-[9px] text-gray-500">{{ cliente.NombreSucursal }} · {{ cliente.ticketsFiltrados.length }} ticket(s)</p>
                                    </div>
                                </div>

                                <!-- Tickets del Cliente -->
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                    <div v-for="reclamo in cliente.ticketsFiltrados" :key="reclamo.IdReclamo" :id="`ticket-${reclamo.IdReclamo}`" class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden flex flex-col transition-all duration-300">
                                        <!-- Cabecera del Ticket -->
                                        <div class="px-2.5 py-1.5 border-b border-gray-100 flex justify-between items-start bg-gray-50/50">
                                            <div>
                                                <p class="text-[8px] font-bold text-gray-400 uppercase tracking-wider">Ticket #{{ reclamo.IdReclamo }}</p>
                                                <p class="font-bold text-xs text-gray-800">Pedido #{{ reclamo.pedido?.NumeroPedido }}</p>
                                            </div>
                                            <span class="text-[8px] px-1.5 py-0.5 rounded-full bg-primary-100 text-primary-700 font-bold border border-primary-200 whitespace-nowrap">{{ reclamo.Estado }}</span>
                                        </div>

                                        <!-- Cuerpo del Ticket -->
                                        <div class="p-2.5 space-y-2 flex-1">
                                            <div class="space-y-1 bg-gray-50 p-2 rounded-md border border-gray-100">
                                                <div class="flex items-center gap-1.5 text-[10px] text-gray-700"><i class="fas fa-user text-gray-400 w-3 text-center text-[8px]"></i><span>Cliente: <b>{{ reclamo.pedido?.cliente?.Nombre }}</b></span></div>
                                                <div class="flex items-center gap-1.5 text-[10px] text-gray-700"><i class="fas fa-user-tie text-gray-400 w-3 text-center text-[8px]"></i><span>Operador: <b>{{ reclamo.nombre_operador }}</b></span></div>
                                                <div class="flex items-center gap-1.5 text-[9px] text-gray-500"><i class="fas fa-calendar-alt text-gray-400 w-3 text-center text-[8px]"></i><span>{{ formatFecha(reclamo.FechaCreacion) }}</span></div>
                                            </div>
                                            <div class="bg-amber-50 border border-amber-200 rounded-md p-2">
                                                <p class="text-[8px] font-bold text-amber-700 uppercase mb-0.5 flex items-center gap-1"><i class="fas fa-comment-alt text-[8px]"></i> Motivo</p>
                                                <p class="text-[10px] text-amber-900 italic">"{{ reclamo.Descripcion }}"</p>
                                            </div>
                                            <div v-if="reclamo.RespuestaCalidad" class="bg-purple-50 border border-purple-200 rounded-md p-2">
                                                <p class="text-[8px] font-bold text-purple-700 uppercase mb-0.5 flex items-center gap-1"><i class="fas fa-reply text-[8px]"></i> Respuesta Calidad</p>
                                                <p class="text-[9px] text-purple-900">{{ reclamo.RespuestaCalidad }}</p>
                                            </div>
                                            <div>
                                                <p class="text-[8px] font-bold text-gray-400 uppercase mb-1 flex items-center gap-1"><i class="fas fa-box text-[8px]"></i> Productos</p>
                                                <div class="space-y-1">
                                                    <div v-for="d in reclamo.detalles" :key="d.IdReclamoDetalle" class="flex justify-between items-center bg-gray-50 border border-gray-200 p-1.5 rounded-md">
                                                        <span class="text-[10px] font-medium text-gray-700 truncate mr-2">{{ d.producto?.Descripcion || ('Prod. ' + d.IdProducto) }}</span>
                                                        <span class="text-[9px] font-bold whitespace-nowrap" :class="d.CantidadAprobada > 0 ? 'text-emerald-600' : 'text-rose-600'">
                                                            <template v-if="d.CantidadAprobada > 0">Aprobó: {{ d.CantidadAprobada }}</template>
                                                            <template v-else>Reclamó: {{ d.CantidadReclamada }}</template>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Acciones del Ticket -->
                                        <div class="p-2.5 bg-gray-50 border-t border-gray-100 flex gap-1.5 mt-auto">
                                            <button v-if="puedeRecibir(reclamo)" @click="abrirRecibir(reclamo)" class="w-full py-1 text-[10px] font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-md transition flex items-center justify-center gap-1">
                                                <i class="fas fa-truck-loading text-[9px]"></i> Recibir en Fábrica
                                            </button>
                                            <template v-if="puedeAprobar(reclamo) || puedeRechazar(reclamo)">
                                                <button v-if="puedeRechazar(reclamo)" @click="abrirRechazar(reclamo)" class="flex-1 py-1 text-[10px] font-bold text-rose-700 bg-rose-100 hover:bg-rose-200 border border-rose-200 rounded-md transition flex items-center justify-center gap-1"><i class="fas fa-times text-[9px]"></i> Rechazar</button>
                                                <button v-if="puedeAprobar(reclamo)" @click="abrirAprobar(reclamo)" class="flex-1 py-1 text-[10px] font-bold text-white bg-primary-700 hover:bg-primary-800 rounded-md transition flex items-center justify-center gap-1"><i class="fas fa-check text-[9px]"></i> Aprobar</button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODALES -->
        <Teleport to="body">
            <!-- MODAL APROBAR -->
            <div v-if="modalAprobar.visible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" @click.self="cerrarModales">
                <div class="bg-white rounded-xl w-full max-w-lg shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
                    <div class="px-4 py-2.5 bg-primary-700 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white flex items-center gap-1.5"><i :class="modalAprobar.reclamo?.TipoReclamo === 'FALTANTE' ? 'fas fa-money-bill-wave' : 'fas fa-check-circle'" class="text-xs"></i> {{ modalAprobar.reclamo?.TipoReclamo === 'FALTANTE' ? 'Aprobar Reembolso' : 'Aprobar Devolución' }}</h3>
                            <p class="text-[9px] text-white/80">Indica las cantidades a aprobar</p>
                        </div>
                        <button @click="cerrarModales" class="text-white/80 hover:text-white" :disabled="procesando"><i class="fas fa-times text-sm"></i></button>
                    </div>
                    <div class="p-4 overflow-y-auto space-y-3">
                        <p class="text-xs text-gray-600"><template v-if="modalAprobar.reclamo?.TipoReclamo === 'FALTANTE'">Indica cuántas unidades <b>faltantes</b> se aprueban. El monto lo calculará Tesorería.</template><template v-else>Indica cuántas unidades se aprueban por producto.</template></p>
                        <div class="space-y-2">
                            <div v-for="d in modalAprobar.reclamo.detalles" :key="d.IdReclamoDetalle" class="border border-gray-200 rounded-md p-2">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-gray-800 truncate">{{ d.producto?.Descripcion || ('Producto ' + d.IdProducto) }}</p>
                                        <p class="text-[10px] text-gray-500"><template v-if="modalAprobar.reclamo?.TipoReclamo === 'FALTANTE'">Faltaron: <b>{{ d.CantidadReclamada }}</b></template><template v-else>Reclamado: <b>{{ d.CantidadReclamada }}</b></template></p>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <label class="text-[10px] text-gray-500">{{ modalAprobar.reclamo?.TipoReclamo === 'FALTANTE' ? 'Reembolsar:' : 'Aprobar:' }}</label>
                                        <input type="number" min="0" :max="d.CantidadReclamada" v-model.number="modalAprobar.cantidades[d.IdReclamoDetalle]" @input="forzarCantidad(d)" @blur="forzarCantidad(d)" @keyup="forzarCantidad(d)" class="w-16 border border-gray-300 rounded-md px-1.5 py-1 text-xs text-center font-bold focus:ring-primary-500 focus:border-primary-500 outline-none" :disabled="procesando" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Observación (opcional)</label>
                            <textarea v-model="modalAprobar.observacion" rows="2" maxlength="500" class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none resize-none" placeholder="Ej: Se aprueban 3 de 5 unidades." :disabled="procesando"></textarea>
                        </div>
                    </div>
                    <div class="px-4 py-2.5 border-t border-gray-200 bg-gray-50 flex justify-end gap-2">
                        <button @click="cerrarModales" class="px-3 py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition font-medium" :disabled="procesando">Cancelar</button>
                        <button @click="confirmarAprobar" class="px-3 py-1.5 text-xs text-white bg-primary-700 rounded-md hover:bg-primary-800 transition font-medium flex items-center gap-1.5 disabled:opacity-50" :disabled="procesando"><i :class="procesando ? 'fas fa-spinner fa-spin' : 'fas fa-check'" class="text-[10px]"></i>{{ procesando ? 'Procesando...' : (modalAprobar.reclamo?.TipoReclamo === 'FALTANTE' ? 'Confirmar Reembolso' : 'Confirmar Aprobación') }}</button>
                    </div>
                </div>
            </div>

            <!-- MODAL RECHAZAR -->
            <div v-if="modalRechazar.visible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" @click.self="cerrarModales">
                <div class="bg-white rounded-xl w-full max-w-md shadow-xl overflow-hidden">
                    <div class="px-4 py-2.5 bg-rose-600 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white flex items-center gap-1.5"><i class="fas fa-times-circle text-xs"></i> Rechazar Reclamo</h3>
                            <p class="text-[9px] text-white/80">Indica el motivo del rechazo</p>
                        </div>
                        <button @click="cerrarModales" class="text-white/80 hover:text-white" :disabled="procesando"><i class="fas fa-times text-sm"></i></button>
                    </div>
                    <div class="p-4 space-y-2">
                        <textarea v-model="modalRechazar.motivo" rows="3" maxlength="500" class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-rose-500 focus:border-rose-500 outline-none resize-none" placeholder="Ej: El producto no presenta daño." :disabled="procesando"></textarea>
                    </div>
                    <div class="px-4 py-2.5 border-t border-gray-200 bg-gray-50 flex justify-end gap-2">
                        <button @click="cerrarModales" class="px-3 py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition font-medium" :disabled="procesando">Cancelar</button>
                        <button @click="confirmarRechazar" class="px-3 py-1.5 text-xs text-white bg-rose-600 rounded-md hover:bg-rose-700 transition font-medium flex items-center gap-1.5 disabled:opacity-50" :disabled="procesando"><i :class="procesando ? 'fas fa-spinner fa-spin' : 'fas fa-times'" class="text-[10px]"></i>{{ procesando ? 'Procesando...' : 'Confirmar Rechazo' }}</button>
                    </div>
                </div>
            </div>

            <!-- MODAL RECIBIR -->
            <div v-if="modalRecibir.visible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" @click.self="cerrarModales">
                <div class="bg-white rounded-xl w-full max-w-sm shadow-xl overflow-hidden">
                    <div class="px-4 py-2.5 bg-blue-600 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white flex items-center gap-1.5"><i class="fas fa-truck-loading text-xs"></i> Recibir en Fábrica</h3>
                            <p class="text-[9px] text-white/80">Confirma la recepción</p>
                        </div>
                        <button @click="cerrarModales" class="text-white/80 hover:text-white" :disabled="procesando"><i class="fas fa-times text-sm"></i></button>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex items-start gap-2">
                            <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0"><i class="fas fa-boxes text-blue-600 text-sm"></i></div>
                            <p class="text-xs text-gray-700 leading-tight mt-1">¿Confirmas que has recibido los productos del pedido <b>#{{ modalRecibir.reclamo?.pedido?.NumeroPedido }}</b> en la fábrica?</p>
                        </div>
                    </div>
                    <div class="px-4 py-2.5 border-t border-gray-200 bg-gray-50 flex justify-end gap-2">
                        <button @click="cerrarModales" class="px-3 py-1.5 text-xs text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md transition font-medium" :disabled="procesando">Cancelar</button>
                        <button @click="confirmarRecibir" class="px-3 py-1.5 text-xs text-white bg-blue-600 rounded-md hover:bg-blue-700 transition font-medium flex items-center gap-1.5 disabled:opacity-50" :disabled="procesando"><i :class="procesando ? 'fas fa-spinner fa-spin' : 'fas fa-check'" class="text-[10px]"></i>{{ procesando ? 'Procesando...' : 'Sí, recibido' }}</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

.overflow-x-auto {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

.overflow-x-auto::-webkit-scrollbar {
    height: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>