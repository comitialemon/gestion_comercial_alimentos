<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, inject, reactive } from 'vue'
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
const guardando = ref(false)
const detallesEdit = ref([])
const modalVisible = ref(false)
const productoActual = ref(null)
const opcionesAgrupadas = ref([])
const cantidadesTemp = ref({})

// =============================================
// INICIALIZAR
// =============================================
const inicializarDetalles = () => {
    if (props.detalles && props.detalles.length) {
        detallesEdit.value = props.detalles.map(d => ({
            ...d,
            _editando: false
        }))
    }
}

// =============================================
// AGRUPAR OPCIONES
// =============================================
const agruparOpciones = (detalle, totalCombos) => {
    const grupos = {}
    const composicion = detalle.composicion || []
    const opciones = detalle.opciones_disponibles || []
    
    let personalizacionActual = detalle.personalizacion || []
    
    if (typeof personalizacionActual === 'string') {
        try {
            personalizacionActual = JSON.parse(personalizacionActual)
        } catch (e) {
            personalizacionActual = []
        }
    }
    
    if (!Array.isArray(personalizacionActual)) {
        personalizacionActual = []
    }
    
    // Crear mapa de sustitutos
    const sustitutosMap = {}
    if (personalizacionActual.length > 0) {
        personalizacionActual.forEach(combo => {
            if (combo.sustitutos) {
                combo.sustitutos.forEach(sust => {
                    const key = sust.id_producto_original
                    if (!sustitutosMap[key]) {
                        sustitutosMap[key] = {}
                    }
                    const subKey = sust.id_producto_sustituto
                    sustitutosMap[key][subKey] = (sustitutosMap[key][subKey] || 0) + (sust.cantidad || 0)
                })
            }
        })
    }
    
    composicion.forEach(item => {
        const idOriginal = item.id_producto
        const nombreOriginal = item.nombre || 'Producto'
        const cantidadPorCombo = item.porcion || 1
        const cantidadTotal = cantidadPorCombo * totalCombos
        
        const opcionesDelProducto = opciones.filter(o => o.id_producto_original === idOriginal)
        const tieneOpciones = opcionesDelProducto.length > 0 && opcionesDelProducto.some(o => o.id_producto_sustituto !== idOriginal)
        
        const opcionesConCantidad = []
        
        // Opción original (siempre presente)
        const cantidadOriginal = sustitutosMap[idOriginal]?.[idOriginal] || 0
        opcionesConCantidad.push({
            id_sustituto: idOriginal,
            nombre: nombreOriginal,
            codigo: '',
            cantidad_maxima: cantidadPorCombo || 1,
            cantidad_actual: cantidadOriginal > 0 ? cantidadOriginal : (tieneOpciones ? 0 : cantidadTotal),
            es_original: true,
            es_fijo: !tieneOpciones,
            cantidad_fija: !tieneOpciones ? cantidadTotal : 0
        })
        
        // Opciones sustitutas (solo si tiene opciones)
        if (tieneOpciones) {
            opcionesDelProducto.forEach(op => {
                const idSustituto = op.id_producto_sustituto
                if (idSustituto !== idOriginal) {
                    const cantidadSustituto = sustitutosMap[idOriginal]?.[idSustituto] || 0
                    opcionesConCantidad.push({
                        id_sustituto: idSustituto,
                        nombre: op.nombre_sustituto || 'Producto',
                        codigo: op.codigo_sustituto || '',
                        cantidad_maxima: op.cantidad_maxima || cantidadPorCombo || 1,
                        cantidad_actual: cantidadSustituto,
                        es_original: false,
                        es_fijo: false,
                        cantidad_fija: 0
                    })
                }
            })
        }
        
        // Si no tiene opciones, es fijo
        if (!tieneOpciones) {
            grupos[idOriginal] = {
                id_producto_original: idOriginal,
                nombre_original: nombreOriginal,
                cantidad_total: cantidadTotal,
                cantidad_original: cantidadTotal,
                tiene_opciones: false,
                es_fijo: true,
                opciones: [{
                    id_sustituto: idOriginal,
                    nombre: nombreOriginal,
                    cantidad_maxima: cantidadTotal,
                    cantidad_actual: cantidadTotal,
                    es_original: true,
                    es_fijo: true,
                    cantidad_fija: cantidadTotal
                }]
            }
            return
        }
        
        // Calcular total de sustitutos
        let totalSustitutos = 0
        opcionesConCantidad.forEach(o => {
            if (!o.es_original) {
                totalSustitutos += Number(o.cantidad_actual) || 0
            }
        })
        
        // Calcular cantidad original restante
        const cantidadOriginalRestante = cantidadTotal - totalSustitutos
        const opcionOriginal = opcionesConCantidad.find(o => o.es_original)
        if (opcionOriginal) {
            opcionOriginal.cantidad_actual = cantidadOriginalRestante > 0 ? cantidadOriginalRestante : 0
        }
        
        grupos[idOriginal] = {
            id_producto_original: idOriginal,
            nombre_original: nombreOriginal,
            cantidad_total: cantidadTotal,
            cantidad_original: cantidadOriginalRestante > 0 ? cantidadOriginalRestante : 0,
            tiene_opciones: true,
            es_fijo: false,
            opciones: opcionesConCantidad
        }
    })
    
    return Object.values(grupos)
}

// =============================================
// ABRIR MODAL
// =============================================
const abrirModal = (detalle, index) => {
    const totalCombos = Number(detalle.unidades) || 1
    
    productoActual.value = {
        id: detalle.idrelacionventainventario,
        nombre: detalle.producto_nombre,
        composicion: detalle.composicion || [],
        opciones: detalle.opciones_disponibles || [],
        total_unidades: totalCombos,
        detalleIndex: index
    }
    
    const grupos = agruparOpciones(detalle, totalCombos)
    opcionesAgrupadas.value = grupos
    
    // Inicializar cantidades temporales
    cantidadesTemp.value = {}
    grupos.forEach((grupo) => {
        grupo.opciones.forEach((opcion) => {
            const key = `${grupo.id_producto_original}_${opcion.id_sustituto}`
            cantidadesTemp.value[key] = Number(opcion.cantidad_actual) || 0
        })
    })
    
    modalVisible.value = true
}

// =============================================
// ACTUALIZAR CANTIDAD TEMPORAL
// =============================================
const actualizarCantidadTemp = (grupo, opcion, nuevoValor) => {
    const total = grupo.cantidad_total
    
    // No permitir negativos
    if (nuevoValor < 0) nuevoValor = 0
    
    // Si es fijo, no se puede modificar
    if (opcion.es_fijo) return
    
    // Actualizar el valor
    const key = `${grupo.id_producto_original}_${opcion.id_sustituto}`
    cantidadesTemp.value[key] = nuevoValor
    opcion.cantidad_actual = nuevoValor
    
    // Recalcular total de sustitutos
    let totalSustitutos = 0
    grupo.opciones.forEach(o => {
        if (!o.es_original && !o.es_fijo) {
            totalSustitutos += Number(o.cantidad_actual) || 0
        }
    })
    
    // Calcular cantidad original restante
    const cantidadOriginalRestante = total - totalSustitutos
    const opcionOriginal = grupo.opciones.find(o => o.es_original)
    if (opcionOriginal && !opcionOriginal.es_fijo) {
        opcionOriginal.cantidad_actual = cantidadOriginalRestante >= 0 ? cantidadOriginalRestante : 0
        const keyOrig = `${grupo.id_producto_original}_${opcionOriginal.id_sustituto}`
        cantidadesTemp.value[keyOrig] = opcionOriginal.cantidad_actual
    }
    
    // Actualizar el estado del grupo
    grupo.cantidad_original = cantidadOriginalRestante >= 0 ? cantidadOriginalRestante : 0
    
    // Forzar actualización de la vista
    opcionesAgrupadas.value = [...opcionesAgrupadas.value]
}

// =============================================
// GUARDAR PERSONALIZACIÓN
// =============================================
const guardarPersonalizacion = async () => {
    // Validar que todos los grupos estén completos
    let hayError = false
    let mensajeError = ''
    
    for (const grupo of opcionesAgrupadas.value) {
        if (grupo.es_fijo) continue
        
        let total = 0
        grupo.opciones.forEach(o => {
            if (!o.es_fijo) {
                total += Number(o.cantidad_actual) || 0
            }
        })
        
        if (total !== grupo.cantidad_total) {
            hayError = true
            mensajeError = `"${grupo.nombre_original}" debe sumar ${grupo.cantidad_total} unidades (actual: ${total})`
            break
        }
    }
    
    if (hayError) {
        toast?.warning('Cantidades incorrectas', mensajeError)
        return
    }
    
    const index = productoActual.value.detalleIndex
    const totalCombos = productoActual.value.total_unidades
    
    // Construir personalización
    const nuevaPersonalizacion = []
    
    for (let i = 0; i < totalCombos; i++) {
        const sustitutos = []
        
        opcionesAgrupadas.value.forEach(grupo => {
            if (grupo.es_fijo) {
                // Producto fijo
                const opcionFija = grupo.opciones.find(o => o.es_fijo)
                if (opcionFija && opcionFija.cantidad_actual > 0) {
                    const cantidadPorCombo = Math.floor(opcionFija.cantidad_actual / totalCombos)
                    if (cantidadPorCombo > 0) {
                        sustitutos.push({
                            id_producto_original: grupo.id_producto_original,
                            id_producto_sustituto: opcionFija.id_sustituto,
                            cantidad: cantidadPorCombo
                        })
                    }
                }
                return
            }
            
            // Producto con opciones
            grupo.opciones.forEach(op => {
                if (op.es_fijo) return
                
                const cantidadTotal = Number(op.cantidad_actual) || 0
                
                if (cantidadTotal > 0) {
                    const cantidadPorCombo = Math.floor(cantidadTotal / totalCombos)
                    const residuo = cantidadTotal % totalCombos
                    
                    const cantidadAUsar = (i === totalCombos - 1) 
                        ? (cantidadPorCombo + residuo) 
                        : cantidadPorCombo
                    
                    if (cantidadAUsar > 0) {
                        sustitutos.push({
                            id_producto_original: grupo.id_producto_original,
                            id_producto_sustituto: op.id_sustituto,
                            cantidad: cantidadAUsar
                        })
                    }
                }
            })
        })
        
        if (sustitutos.length > 0) {
            nuevaPersonalizacion.push({ sustitutos })
        }
    }
    
    const personalizacionFinal = nuevaPersonalizacion.length > 0 ? nuevaPersonalizacion : null
    
    // Actualizar el detalle local
    const nuevoDetalle = {
        ...detallesEdit.value[index],
        personalizacion: personalizacionFinal,
        tiene_personalizacion: personalizacionFinal !== null && personalizacionFinal.length > 0
    }
    
    detallesEdit.value = [
        ...detallesEdit.value.slice(0, index),
        nuevoDetalle,
        ...detallesEdit.value.slice(index + 1)
    ]
    
    // Cerrar modal
    modalVisible.value = false
    productoActual.value = null
    opcionesAgrupadas.value = []
    cantidadesTemp.value = {}
    
    // Guardar en la BD
    await guardarCambios()
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
        
        const response = await axios.put(`/gestion/reportes/control-interno/ventas/mis-facturas/${props.venta.IdVentas}`, payload)
        
        if (response.data.success) {
            toast?.success('Cambios guardados', 'La personalización se actualizó correctamente')
        } else {
            toast?.error('Error', response.data.message || 'Error al guardar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardando.value = false
    }
}

// =============================================
// FINALIZAR EDICIÓN
// =============================================
const finalizarEdicion = async () => {
    if (!confirm('¿Estás seguro de finalizar la edición? La factura quedará cerrada y no podrá editarse.')) return
    
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
            setTimeout(() => router.get('/gestion/reportes/control-interno/ventas/mis-facturas'), 1500)
        } else {
            toast?.error('Error', response.data.message || 'Error al finalizar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al finalizar')
    } finally {
        guardando.value = false
    }
}

// =============================================
// UTILIDADES
// =============================================
const formatearMonto = (monto) => Number(monto || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    const d = new Date(fecha)
    return d.toLocaleDateString('es-BO', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const totalFactura = computed(() => detallesEdit.value.reduce((sum, d) => sum + (d.totalbolivianos || 0), 0))
const tieneOpciones = (detalle) => detalle.tiene_opciones === true || detalle.es_agrupado === true

const volver = () => {
    if (confirm('¿Seguro que quieres salir? Los cambios no guardados se perderán.')) {
        router.get('/gestion/reportes/control-interno/ventas/mis-facturas')
    }
}

onMounted(() => inicializarDetalles())
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
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Editar Opciones</h1>
                            <p class="text-[10px] text-gray-500">N° {{ venta.NumeroFactura }} - {{ formatearFecha(venta.FechaVenta) }}</p>
                            <p class="text-[10px] text-green-600">✅ Los cambios se guardan automáticamente al aplicar</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button @click="volver" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 rounded-lg text-xs">Volver</button>
                        <button @click="finalizarEdicion" :disabled="guardando" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs disabled:opacity-50">
                            {{ guardando ? 'Cerrando...' : 'Finalizar' }}
                        </button>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Producto</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Cant.</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Precio</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Total</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Opciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(detalle, index) in detallesEdit" :key="index" class="hover:bg-gray-50">
                                <td class="px-3 py-2">
                                    <div class="text-xs font-medium text-gray-800">{{ detalle.producto_nombre }}</div>
                                    <div class="text-[10px] text-gray-400">{{ detalle.producto_codigo }}</div>
                                    <div v-if="detalle.tiene_personalizacion" class="text-[10px] text-amber-600">✓ Personalizado</div>
                                </td>
                                <td class="px-3 py-2 text-center text-xs text-gray-700">{{ detalle.unidades }}</td>
                                <td class="px-3 py-2 text-right text-xs text-gray-700">{{ formatearMonto(detalle.preciounidades) }}</td>
                                <td class="px-3 py-2 text-right text-xs font-bold text-primary-600">{{ formatearMonto(detalle.totalbolivianos) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <button v-if="tieneOpciones(detalle)" @click="abrirModal(detalle, index)" :disabled="guardando"
                                            class="px-2 py-1 bg-amber-100 hover:bg-amber-200 text-amber-700 rounded-lg text-xs">
                                        <i class="fas fa-exchange-alt"></i> Cambiar
                                    </button>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="!detallesEdit.length">
                                <td colspan="5" class="px-3 py-8 text-center text-gray-400 text-sm">No hay productos</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 border-t">
                            <tr>
                                <td colspan="3" class="px-3 py-2 text-right text-xs font-bold">TOTAL:</td>
                                <td class="px-3 py-2 text-right text-sm font-bold text-primary-700">{{ formatearMonto(totalFactura) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Indicador de guardado automático -->
                <div class="flex justify-between items-center mt-4">
                    <div class="text-xs text-gray-400">
                        <i class="fas fa-sync-alt text-green-500"></i>
                        Los cambios se guardan automáticamente al aplicar
                    </div>
                    <span v-if="guardando" class="text-xs text-primary-600">
                        <i class="fas fa-spinner fa-spin"></i> Guardando...
                    </span>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="modalVisible = false">
            <div class="bg-white rounded-xl max-w-lg w-full max-h-[90vh] overflow-hidden shadow-xl flex flex-col">
                <!-- Header -->
                <div class="bg-primary-700 px-4 py-2.5 flex justify-between items-center flex-shrink-0">
                    <div>
                        <h3 class="text-white font-bold text-sm">Cambiar Opciones</h3>
                        <p class="text-white/70 text-[10px]">{{ productoActual?.nombre }}</p>
                        <p class="text-white/50 text-[9px]">Total: {{ productoActual?.total_unidades || 0 }} unidades</p>
                    </div>
                    <button @click="modalVisible = false" class="text-white/80 hover:text-white">✕</button>
                </div>

                <!-- Contenido -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div v-for="grupo in opcionesAgrupadas" :key="grupo.id_producto_original" class="border-b pb-3 last:border-0">
                        <!-- Encabezado -->
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-semibold text-gray-800">{{ grupo.nombre_original }}</span>
                            <span class="text-xs font-medium text-gray-500">Total: {{ grupo.cantidad_total }} unid</span>
                        </div>

                        <!-- Producto FIJO (sin opciones) -->
                        <div v-if="grupo.es_fijo" class="text-sm text-gray-600 bg-gray-50 rounded-lg p-2">
                            <i class="fas fa-lock text-gray-400 mr-1"></i>
                            Producto fijo: <strong>{{ grupo.cantidad_total }}</strong> unidades
                            <div class="text-[10px] text-gray-400 mt-1">No se puede modificar porque no tiene opciones de sustitución</div>
                        </div>

                        <!-- Producto con opciones -->
                        <div v-else class="space-y-2">
                            <div v-for="opcion in grupo.opciones" :key="opcion.id_sustituto"
                                 class="flex items-center gap-3 p-2 rounded-lg border"
                                 :class="opcion.es_original ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-white'">
                                
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-medium" :class="opcion.es_original ? 'text-blue-700' : 'text-gray-800'">
                                        {{ opcion.nombre }}
                                        <span v-if="opcion.es_original" class="text-[10px] text-blue-500 ml-1">(original)</span>
                                    </div>
                                    <div class="text-[10px] text-gray-400">máx {{ opcion.cantidad_maxima || grupo.cantidad_total }} unid</div>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <input 
                                        type="number" 
                                        v-model.number="opcion.cantidad_actual"
                                        @input="actualizarCantidadTemp(grupo, opcion, Number($event.target.value))"
                                        :min="0"
                                        class="w-16 text-center text-sm border border-gray-300 rounded px-1 py-1 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                        :class="opcion.es_original ? 'bg-blue-50' : ''"
                                    />
                                    <span class="text-xs text-gray-500">unid</span>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen con estado de validación -->
                        <div class="mt-2 text-[10px] text-gray-500 bg-gray-50 rounded p-1.5 flex justify-between">
                            <span>Total asignado: 
                                <strong>{{ grupo.opciones.reduce((sum, o) => sum + Number(o.cantidad_actual), 0) }}</strong>
                                / {{ grupo.cantidad_total }}
                            </span>
                            <span v-if="grupo.es_fijo" class="text-gray-400">🔒 Fijo</span>
                            <span v-else-if="grupo.opciones.reduce((sum, o) => sum + Number(o.cantidad_actual), 0) === grupo.cantidad_total" 
                                  class="text-green-600">✅ Completo</span>
                            <span v-else-if="grupo.opciones.reduce((sum, o) => sum + Number(o.cantidad_actual), 0) > grupo.cantidad_total" 
                                  class="text-red-600">⚠️ Excede por {{ grupo.opciones.reduce((sum, o) => sum + Number(o.cantidad_actual), 0) - grupo.cantidad_total }}</span>
                            <span v-else class="text-amber-600">⚠️ Faltan {{ grupo.cantidad_total - grupo.opciones.reduce((sum, o) => sum + Number(o.cantidad_actual), 0) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-4 py-2.5 border-t flex justify-end gap-2">
                    <button @click="modalVisible = false" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs">Cancelar</button>
                    <button @click="guardarPersonalizacion" :disabled="guardando" class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-xs flex items-center gap-2 disabled:opacity-50">
                        <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check"></i>
                        {{ guardando ? 'Guardando...' : 'Aplicar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>