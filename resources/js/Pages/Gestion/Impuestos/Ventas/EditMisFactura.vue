<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, inject, watch } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    venta: Object,
    detalles: Array,
    modo: String
})

// =============================================
// ESTADO
// =============================================
const loading = ref(false)
const guardando = ref(false)
const detallesEdit = ref([])
const modalPersonalizarVisible = ref(false)
const productoActual = ref(null)
const opcionesAgrupadas = ref([])
const cantidadProductos = ref(1)

// =============================================
// INICIALIZAR DETALLES
// =============================================
const inicializarDetalles = () => {
    if (props.detalles && props.detalles.length) {
        detallesEdit.value = props.detalles.map(d => ({
            ...d,
            _editando: false,
            _original_personalizacion: d.personalizacion || null
        }))
    } else {
        detallesEdit.value = []
    }
}

// =============================================
// AGRUPAR OPCIONES POR PRODUCTO - CORREGIDO
// =============================================
const agruparOpcionesPorProducto = (detalle, totalCombos) => {
    const grupos = {}
    const composicion = detalle.composicion || []
    const opciones = detalle.opciones_disponibles || []
    
    // 🔥 OBTENER PERSONALIZACIÓN ACTUAL
    let personalizacionActual = detalle.personalizacion
    
    if (!personalizacionActual) {
        personalizacionActual = []
    }
    
    if (typeof personalizacionActual === 'string') {
        try {
            personalizacionActual = JSON.parse(personalizacionActual)
        } catch (e) {
            personalizacionActual = []
        }
    }
    
    if (personalizacionActual && !Array.isArray(personalizacionActual) && personalizacionActual.sustitutos) {
        personalizacionActual = [{
            sustitutos: personalizacionActual.sustitutos
        }]
    }
    
    if (!Array.isArray(personalizacionActual)) {
        personalizacionActual = []
    }
    
    // Si la personalización tiene menos combos, replicar
    if (personalizacionActual.length === 1 && personalizacionActual.length < totalCombos && totalCombos > 0) {
        const sustitutosBase = personalizacionActual[0].sustitutos || []
        const nuevaPersonalizacion = []
        for (let i = 0; i < totalCombos; i++) {
            nuevaPersonalizacion.push({
                sustitutos: sustitutosBase.map(s => ({ ...s }))
            })
        }
        personalizacionActual = nuevaPersonalizacion
    }
    
    console.log('📝 Personalización procesada:', JSON.stringify(personalizacionActual, null, 2))
    
    // 🔥 PROCESAR CADA PRODUCTO DE LA COMPOSICIÓN
    composicion.forEach(item => {
        const idOriginal = item.id_producto
        const nombreOriginal = item.nombre || 'Producto'
        const cantidadPorCombo = item.porcion || 1
        const cantidadTotal = cantidadPorCombo * totalCombos
        
        let cantidadSustitutoTotal = 0
        let sustitutoSeleccionado = null
        let idSustitutoSeleccionado = null
        
        // 🔥 BUSCAR EN LA PERSONALIZACIÓN
        if (personalizacionActual && personalizacionActual.length > 0) {
            personalizacionActual.forEach(p => {
                if (p.sustitutos && p.sustitutos.length > 0) {
                    p.sustitutos.forEach(sust => {
                        // 🔥 CLAVE: Buscar por id_producto_original
                        if (sust.id_producto_original === idOriginal) {
                            const cantidad = Number(sust.cantidad) || 0
                            cantidadSustitutoTotal += cantidad
                            
                            // Si el sustituto es diferente al original, es un cambio
                            if (sust.id_producto_sustituto !== idOriginal) {
                                idSustitutoSeleccionado = sust.id_producto_sustituto
                                const opcionEncontrada = opciones.find(o => o.id_producto_sustituto === sust.id_producto_sustituto)
                                if (opcionEncontrada) {
                                    sustitutoSeleccionado = opcionEncontrada
                                }
                            }
                        }
                    })
                }
            })
        }
        
        // 🔥 CALCULAR ORIGINALES = TOTAL - SUSTITUTOS
        const cantidadOriginal = cantidadTotal - cantidadSustitutoTotal
        
        console.log(`📊 ${nombreOriginal} | Total: ${cantidadTotal} | Sustituto: ${cantidadSustitutoTotal} | Original: ${cantidadOriginal}`)
        
        // 🔥 CREAR GRUPO
        grupos[idOriginal] = {
            id_producto_original: idOriginal,
            nombre_original: nombreOriginal,
            cantidad_total: cantidadTotal,
            cantidad_original: cantidadOriginal > 0 ? cantidadOriginal : 0,
            cantidad_original_input: cantidadOriginal > 0 ? cantidadOriginal : 0,
            cantidad_sustituto: cantidadSustitutoTotal,
            tiene_sustituto: cantidadSustitutoTotal > 0,
            sustituto_nombre: sustitutoSeleccionado ? sustitutoSeleccionado.nombre_sustituto : null,
            id_sustituto_seleccionado: idSustitutoSeleccionado,
            opciones: opciones.filter(o => o.id_producto_original === idOriginal).map(op => ({
                id_sustituto: op.id_producto_sustituto,
                nombre: op.nombre_sustituto,
                codigo: op.codigo_sustituto || '',
                cantidad_maxima: op.cantidad_maxima || item.porcion || 1,
                cantidad_actual: (cantidadSustitutoTotal > 0 && idSustitutoSeleccionado === op.id_producto_sustituto) ? cantidadSustitutoTotal : 0,
                es_seleccionado: idSustitutoSeleccionado === op.id_producto_sustituto
            }))
        }
    })
    
    return Object.values(grupos)
}

// =============================================
// ABRIR MODAL
// =============================================
const abrirCambioOpciones = (detalle, index) => {
    if (!detalle.tiene_opciones && !detalle.es_agrupado) {
        toast?.warning('Sin opciones', 'Este producto no tiene opciones de cambio disponibles')
        return
    }
    
    const totalCombos = Number(detalle.unidades) || 1
    
    console.log('🔍 Abriendo modal:', {
        producto: detalle.producto_nombre,
        totalCombos: totalCombos,
        personalizacion: detalle.personalizacion
    })
    
    productoActual.value = {
        id: detalle.idrelacionventainventario,
        nombre: detalle.producto_nombre,
        composicion: detalle.composicion || [],
        opciones: detalle.opciones_disponibles || [],
        tipo_producto: detalle.es_agrupado ? 'combo' : 'con_opciones',
        precio_real: detalle.preciounidades || 0,
        total_unidades: totalCombos,
        detalleIndex: index
    }
    
    const grupos = agruparOpcionesPorProducto(detalle, totalCombos)
    opcionesAgrupadas.value = grupos
    
    console.log('🔍 Grupos generados:', grupos)
    
    modalPersonalizarVisible.value = true
}

// =============================================
// VALIDAR CANTIDADES
// =============================================
const validarCantidades = (grupo) => {
    if (grupo.cantidad_original_input > grupo.cantidad_total) {
        grupo.cantidad_original_input = grupo.cantidad_total
    }
    if (grupo.cantidad_original_input < 0) {
        grupo.cantidad_original_input = 0
    }
    
    grupo.opciones.forEach(op => {
        if (op.cantidad_actual > grupo.cantidad_total) {
            op.cantidad_actual = grupo.cantidad_total
        }
        if (op.cantidad_actual < 0) {
            op.cantidad_actual = 0
        }
    })
}

const actualizarSeleccion = (grupo, opcion) => {
    if (opcion.cantidad_actual > 0) {
        if (opcion.cantidad_actual > grupo.cantidad_total) {
            opcion.cantidad_actual = grupo.cantidad_total
        }
        
        opcion.es_seleccionado = true
        grupo.opciones.forEach(o => {
            if (o.id_sustituto !== opcion.id_sustituto) {
                o.es_seleccionado = false
                o.cantidad_actual = 0
            }
        })
        grupo.cantidad_original_input = grupo.cantidad_total - opcion.cantidad_actual
    } else {
        opcion.es_seleccionado = false
        grupo.cantidad_original_input = grupo.cantidad_total
    }
}

const calcularTotalAsignado = (grupo) => {
    let total = Number(grupo.cantidad_original_input) || 0
    grupo.opciones.forEach(op => {
        if (op.es_seleccionado && op.cantidad_actual > 0) {
            total += Number(op.cantidad_actual)
        }
    })
    return total
}

const validarTodosLosGrupos = () => {
    for (const grupo of opcionesAgrupadas.value) {
        if (calcularTotalAsignado(grupo) !== Number(grupo.cantidad_total)) {
            return false
        }
    }
    return true
}

// =============================================
// CONFIRMAR CAMBIO DE OPCIONES
// =============================================
const confirmarCambioOpciones = () => {
    const index = productoActual.value?.detalleIndex
    
    if (index === undefined || index === null) {
        toast?.error('Error', 'No se encontró el producto a actualizar')
        return
    }
    
    const detalleActual = detallesEdit.value[index]
    
    if (!detalleActual) {
        toast?.error('Error', 'No se encontró el producto a actualizar')
        return
    }
    
    const totalCombos = Number(productoActual.value.total_unidades) || 1
    
    if (!validarTodosLosGrupos()) {
        toast?.warning('Cantidades incompletas', 'Debes asignar todas las unidades de cada producto')
        return
    }
    
    // 🔥 CONSTRUIR PERSONALIZACIÓN
    const nuevaPersonalizacion = []
    
    for (let i = 0; i < totalCombos; i++) {
        const sustitutos = []
        
        opcionesAgrupadas.value.forEach(grupo => {
            // Buscar el sustituto seleccionado
            const sustitutoSeleccionado = grupo.opciones.find(op => op.es_seleccionado && Number(op.cantidad_actual) > 0)
            
            // Si hay sustituto seleccionado
            if (sustitutoSeleccionado) {
                const cantidadPorCombo = Math.round(Number(sustitutoSeleccionado.cantidad_actual) / totalCombos)
                if (cantidadPorCombo > 0) {
                    sustitutos.push({
                        id_producto_original: Number(grupo.id_producto_original),
                        id_producto_sustituto: Number(sustitutoSeleccionado.id_sustituto),
                        cantidad: cantidadPorCombo
                    })
                }
            }
            
            // 🔥 SI NO HAY SUSTITUTO, NO AGREGAR NADA (el original se asume)
            // Los originales NO se guardan en la personalización
            // Solo se guardan los cambios (sustitutos)
        })
        
        // Si hay sustitutos, agregarlos
        if (sustitutos.length > 0) {
            nuevaPersonalizacion.push({ sustitutos })
        }
    }
    
    // Si no hay personalización (todos son originales), crear un array vacío
    // o con un solo elemento para que no se pierda
    if (nuevaPersonalizacion.length === 0) {
        // Si no hay cambios, no guardar personalización (se usará la composición original)
        detalleActual.personalizacion = null
        detalleActual.tiene_personalizacion = false
    } else {
        detalleActual.personalizacion = nuevaPersonalizacion
        detalleActual.tiene_personalizacion = true
    }
    
    console.log('📝 PERSONALIZACION FINAL:', JSON.stringify(nuevaPersonalizacion, null, 2))
    console.log('📝 CANTIDAD DE COMBOS:', nuevaPersonalizacion.length)
    
    modalPersonalizarVisible.value = false
    productoActual.value = null
    opcionesAgrupadas.value = []
    
    toast?.success('Opciones actualizadas', 'La personalización se ha actualizado correctamente')
}

// =============================================
// GUARDAR CAMBIOS
// =============================================
const guardarCambios = async () => {
    if (!detallesEdit.value.length) {
        toast?.warning('Sin productos', 'La factura debe tener al menos un producto')
        return
    }
    
    guardando.value = true
    
    try {
        const payload = {
            detalles: detallesEdit.value.map(d => ({
                idrelacionventainventario: d.idrelacionventainventario,
                unidades: d.unidades,
                preciounidades: d.preciounidades,
                personalizacion: d.personalizacion || null
            }))
        }
        
        console.log('📤 Enviando al servidor:', JSON.stringify(payload, null, 2))
        
        const response = await axios.put(`/gestion/reportes/control-interno/ventas/mis-facturas/${props.venta.IdVentas}`, payload)
        
        if (response.data.success) {
            toast?.success('Factura actualizada', 'Los cambios se guardaron correctamente')
            setTimeout(() => {
                router.reload()
            }, 500)
        } else {
            toast?.error('Error', response.data.message || 'Error al guardar')
        }
    } catch (error) {
        console.error('Error guardando:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardando.value = false
    }
}

// =============================================
// FINALIZAR EDICIÓN
// =============================================
const finalizarEdicion = async () => {
    if (!confirm('¿Estás seguro de finalizar la edición?\n\nEsto cerrará la factura y no podrás modificarla nuevamente.')) {
        return
    }
    
    guardando.value = true
    
    try {
        const payload = {
            detalles: detallesEdit.value.map(d => ({
                idrelacionventainventario: d.idrelacionventainventario,
                unidades: d.unidades,
                preciounidades: d.preciounidades,
                personalizacion: d.personalizacion || null
            })),
            finalizar: true
        }
        
        const response = await axios.put(`/gestion/reportes/control-interno/ventas/mis-facturas/${props.venta.IdVentas}`, payload)
        
        if (response.data.success) {
            toast?.success('Factura finalizada', 'La factura se ha cerrado correctamente')
            setTimeout(() => {
                router.get('/gestion/reportes/control-interno/ventas/mis-facturas')
            }, 1500)
        } else {
            toast?.error('Error', response.data.message || 'Error al finalizar')
        }
    } catch (error) {
        console.error('Error finalizando:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al finalizar')
    } finally {
        guardando.value = false
    }
}

// =============================================
// VOLVER AL LISTADO
// =============================================
const volver = () => {
    if (confirm('¿Seguro que quieres salir? Los cambios no guardados se perderán.')) {
        router.get('/gestion/reportes/control-interno/ventas/mis-facturas')
    }
}

// =============================================
// UTILIDADES
// =============================================
const formatearMonto = (monto) => {
    return Number(monto || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    const d = new Date(fecha)
    return d.toLocaleDateString('es-BO', { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const totalFactura = computed(() => {
    return detallesEdit.value.reduce((sum, d) => sum + (d.totalbolivianos || 0), 0)
})

const tieneOpciones = (detalle) => {
    return detalle.tiene_opciones === true || detalle.es_agrupado === true
}

// =============================================
// CICLO DE VIDA
// =============================================
onMounted(() => {
    inicializarDetalles()
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-5xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-edit text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Editar Opciones de Factura</h1>
                            <p class="text-[10px] text-gray-500">
                                N° {{ venta.NumeroFactura }} - {{ formatearFecha(venta.FechaVenta) }}
                                <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-[9px]">
                                    <i class="fas fa-exchange-alt mr-1"></i> Cambiar opciones
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button 
                            @click="volver"
                            class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-medium transition"
                        >
                            <i class="fas fa-arrow-left mr-1"></i> Volver
                        </button>
                        <button 
                            @click="finalizarEdicion"
                            :disabled="guardando"
                            class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-medium transition disabled:opacity-50"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin mr-1"></i>
                            <i v-else class="fas fa-check-circle mr-1"></i>
                            {{ guardando ? 'Cerrando...' : 'Finalizar Edición' }}
                        </button>
                    </div>
                </div>

                <!-- Info de la venta -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                    <div>
                        <span class="text-gray-500">Sucursal:</span>
                        <span class="font-medium block">{{ venta.sucursal_nombre || '-' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Vendedor:</span>
                        <span class="font-medium block">{{ venta.vendedor_nombre || '-' }}</span>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Precio Unit.</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Opciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(detalle, index) in detallesEdit" :key="index" class="hover:bg-gray-50">
                                    <td class="px-3 py-2">
                                        <div class="text-xs font-medium text-gray-800">{{ detalle.producto_nombre }}</div>
                                        <div class="text-[10px] text-gray-400">{{ detalle.producto_codigo || 'Sin código' }}</div>
                                        <div v-if="detalle.tiene_personalizacion" class="text-[10px] text-amber-600">
                                            <i class="fas fa-check-circle mr-1"></i> Personalizado
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-center text-xs text-gray-700">
                                        {{ detalle.unidades }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs font-medium text-gray-700">
                                        {{ formatearMonto(detalle.preciounidades) }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs font-bold text-primary-600">
                                        {{ formatearMonto(detalle.totalbolivianos) }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button 
                                            v-if="tieneOpciones(detalle)"
                                            @click="abrirCambioOpciones(detalle, index)"
                                            :disabled="guardando"
                                            class="px-2 py-1 bg-amber-100 hover:bg-amber-200 text-amber-700 rounded-lg text-xs font-medium transition flex items-center gap-1 mx-auto disabled:opacity-50"
                                            title="Cambiar opciones del producto"
                                        >
                                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                            <i v-else class="fas fa-exchange-alt text-[10px]"></i>
                                            {{ guardando ? 'Guardando...' : 'Cambiar' }}
                                        </button>
                                        <span v-else class="text-[10px] text-gray-400" title="Sin opciones de cambio">
                                            <i class="fas fa-minus text-xs"></i>
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!detallesEdit.length">
                                    <td colspan="5" class="px-3 py-8 text-center text-gray-400 text-sm">
                                        <i class="fas fa-box-open text-3xl block mb-2 text-gray-300"></i>
                                        No hay productos en esta factura
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="detallesEdit.length" class="bg-gray-50 border-t-2 border-gray-200">
                                <tr>
                                    <td colspan="3" class="px-3 py-2 text-right text-xs font-bold text-gray-700">TOTAL:</td>
                                    <td class="px-3 py-2 text-right text-sm font-bold text-primary-700">
                                        {{ formatearMonto(totalFactura) }} Bs
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-end gap-2 mt-4">
                    <button 
                        @click="volver"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm font-medium transition"
                    >
                        Cancelar
                    </button>
                    <button 
                        @click="finalizarEdicion"
                        :disabled="guardando"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50"
                    >
                        <i v-if="guardando" class="fas fa-spinner fa-spin mr-1"></i>
                        <i v-else class="fas fa-check-circle mr-1"></i>
                        {{ guardando ? 'Cerrando...' : 'Finalizar Edición' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal de Cambio de Opciones -->
        <div v-if="modalPersonalizarVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="modalPersonalizarVisible = false">
            <div class="bg-white rounded-xl max-w-lg w-full max-h-[90vh] overflow-hidden shadow-xl flex flex-col">
                
                <div class="bg-primary-700 px-4 py-2.5 flex justify-between items-center flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-white rounded-lg flex items-center justify-center">
                            <i class="fas fa-layer-group text-primary-600 text-xs"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm">Cambiar Opciones</h3>
                            <p class="text-white/70 text-[10px]">{{ productoActual?.nombre }}</p>
                            <p class="text-white/50 text-[9px]">Total: {{ productoActual?.total_unidades || 0 }} unidades</p>
                            <p class="text-white/40 text-[9px]">⚠️ La suma debe ser {{ productoActual?.total_unidades || 0 }}</p>
                        </div>
                    </div>
                    <button @click="modalPersonalizarVisible = false" class="text-white/80 hover:text-white transition text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-4">
                    <div v-if="!opcionesAgrupadas.length" class="text-center text-gray-400 py-6 text-sm">
                        <i class="fas fa-spinner fa-spin text-xl mb-1 block"></i>
                        Cargando opciones...
                    </div>
                    
                    <div v-else>
                        <div v-for="grupo in opcionesAgrupadas" :key="grupo.id_producto_original" class="border-b pb-4 mb-4 last:border-b-0">
                            
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <span class="text-sm font-semibold text-gray-800">{{ grupo.nombre_original }}</span>
                                    <span class="text-[10px] text-gray-400 ml-2">(Total: {{ grupo.cantidad_total }} unid)</span>
                                </div>
                                <span class="text-xs font-medium text-gray-500">
                                    {{ grupo.cantidad_original > 0 ? grupo.cantidad_original : 0 }} originales
                                </span>
                            </div>
                            
                            <div class="space-y-2">
                                <div class="flex items-center gap-3 p-2 rounded-lg border border-blue-200 bg-blue-50">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-medium text-gray-800">📦 {{ grupo.nombre_original }} (Original)</div>
                                        <div class="text-[10px] text-gray-400">Producto por defecto</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input 
                                            type="number" 
                                            v-model.number="grupo.cantidad_original_input" 
                                            :max="grupo.cantidad_total"
                                            :min="0"
                                            class="w-16 text-center text-sm border border-gray-300 rounded px-1 py-1 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                            @input="validarCantidades(grupo)"
                                        />
                                        <span class="text-xs text-gray-500">unid</span>
                                    </div>
                                </div>
                                
                                <div v-for="opcion in grupo.opciones" :key="opcion.id_sustituto"
                                     class="flex items-center gap-3 p-2 rounded-lg border transition"
                                     :class="opcion.es_seleccionado ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white hover:bg-gray-50'">
                                    
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-medium text-gray-800 truncate">
                                            🔄 {{ opcion.nombre }}
                                            <span v-if="opcion.es_seleccionado" class="text-amber-600 text-[9px] ml-1">(seleccionado)</span>
                                        </div>
                                        <div class="text-[10px] text-gray-400">máx {{ opcion.cantidad_maxima || grupo.cantidad_total }} unid</div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        <input 
                                            type="number" 
                                            v-model.number="opcion.cantidad_actual" 
                                            :max="grupo.cantidad_total"
                                            :min="0"
                                            class="w-16 text-center text-sm border border-gray-300 rounded px-1 py-1 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                            :class="opcion.es_seleccionado ? 'border-amber-400 bg-amber-50' : ''"
                                            @input="actualizarSeleccion(grupo, opcion)"
                                        />
                                        <span class="text-xs text-gray-500">unid</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-2 text-[10px] text-gray-500 bg-gray-50 rounded p-1.5 flex justify-between items-center">
                                <span>
                                    <span class="font-medium">Total asignado:</span>
                                    {{ calcularTotalAsignado(grupo) }} / {{ grupo.cantidad_total }} unidades
                                </span>
                                <span v-if="calcularTotalAsignado(grupo) !== grupo.cantidad_total" class="text-amber-600">
                                    ⚠️ Faltan {{ grupo.cantidad_total - calcularTotalAsignado(grupo) }} unidades
                                </span>
                                <span v-else class="text-green-600">
                                    ✅ Completo
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-4 py-2.5 border-t flex justify-end gap-2 flex-shrink-0">
                    <button @click="modalPersonalizarVisible = false" class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-xs hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button 
                        @click="confirmarCambioOpciones"
                        :disabled="!validarTodosLosGrupos()"
                        class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-xs font-medium transition disabled:opacity-50"
                    >
                        <i class="fas fa-check mr-1"></i> Aplicar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input[type="number"] {
    -webkit-appearance: textfield;
    -moz-appearance: textfield;
    appearance: textfield;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>