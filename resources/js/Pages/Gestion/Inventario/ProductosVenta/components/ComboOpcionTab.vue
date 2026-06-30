<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    productoId: {
        type: Number,
        required: true
    }
})

const emit = defineEmits(['update'])

const loading = ref(false)
const composicion = ref([])
const opciones = ref({})

const busquedas = ref({})
const resultadosBusqueda = ref({})
const buscando = ref({})
const opcionesSeleccionadas = ref({})
const cantidadMaxima = ref({})

const API_BASE = '/combo-opciones'

const cargarComposicion = async () => {
    if (!props.productoId) {
        console.warn('No hay productoId para cargar composición')
        return
    }
    
    loading.value = true
    try {
        const response = await axios.get(`${API_BASE}/${props.productoId}/composicion`)
        console.log('Composición cargada:', response.data)
        
        if (response.data.success) {
            composicion.value = response.data.composicion || []
            // Inicializar estados
            composicion.value.forEach(item => {
                if (!busquedas.value[item.id_producto]) busquedas.value[item.id_producto] = ''
                if (!resultadosBusqueda.value[item.id_producto]) resultadosBusqueda.value[item.id_producto] = []
                if (!buscando.value[item.id_producto]) buscando.value[item.id_producto] = false
                if (!opcionesSeleccionadas.value[item.id_producto]) opcionesSeleccionadas.value[item.id_producto] = null
                if (!cantidadMaxima.value[item.id_producto]) cantidadMaxima.value[item.id_producto] = 1
            })
        } else {
            composicion.value = []
            if (response.data.message) {
                console.warn(response.data.message)
            }
        }
    } catch (error) {
        console.error('Error cargando composición:', error)
        composicion.value = []
    } finally {
        loading.value = false
    }
}

const cargarOpciones = async () => {
    if (!props.productoId) return
    
    try {
        const response = await axios.get(`${API_BASE}/${props.productoId}`)
        console.log('Opciones cargadas:', response.data)
        
        if (response.data.success) {
            const agrupadas = {}
            if (response.data.opciones && Array.isArray(response.data.opciones)) {
                response.data.opciones.forEach(op => {
                    if (!agrupadas[op.id_producto_original]) {
                        agrupadas[op.id_producto_original] = []
                    }
                    agrupadas[op.id_producto_original].push({
                        id_combo_opcion: op.id_combo_opcion,
                        id_producto_original: op.id_producto_original,
                        id_producto_sustituto: op.id_producto_sustituto,
                        nombre_sustituto: op.nombre_sustituto,
                        codigo_sustituto: op.codigo_sustituto,
                        orden: op.orden,
                        cantidad_maxima: op.cantidad_maxima || 1
                    })
                })
            }
            opciones.value = agrupadas
        }
    } catch (error) {
        console.error('Error cargando opciones:', error)
        opciones.value = {}
    }
}

const cargarDatos = async () => {
    await cargarComposicion()
    await cargarOpciones()
}

const buscarProductos = async (idProductoOriginal) => {
    const query = busquedas.value[idProductoOriginal]
    
    console.log('🔍 Buscando:', { idProductoOriginal, query })
    
    if (!query || query.length < 2) {
        resultadosBusqueda.value[idProductoOriginal] = []
        return
    }
    
    buscando.value[idProductoOriginal] = true
    
    try {
        const url = `${API_BASE}/productos/disponibles`
        const params = {
            id_producto_original: idProductoOriginal,
            search: query
        }
        
        console.log('📡 URL:', url, 'Params:', params)
        
        const response = await axios.get(url, { params })
        
        console.log('✅ Productos encontrados:', response.data)
        resultadosBusqueda.value[idProductoOriginal] = response.data || []
        
        if (response.data.length === 0) {
            console.warn('⚠️ No se encontraron productos para:', query)
        }
        
    } catch (error) {
        console.error('❌ Error en búsqueda:', error)
        console.error('Detalle:', error.response?.data)
        resultadosBusqueda.value[idProductoOriginal] = []
        alert('Error al buscar productos: ' + (error.response?.data?.message || error.message))
    } finally {
        buscando.value[idProductoOriginal] = false
    }
}

const agregarOpcion = async (idProductoOriginal) => {
    const sustituto = opcionesSeleccionadas.value[idProductoOriginal]
    if (!sustituto) {
        alert('Primero selecciona un producto de la lista')
        return
    }
    
    const cantidad = cantidadMaxima.value[idProductoOriginal] || 1
    
    try {
        const data = {
            id_producto_combo: props.productoId,
            id_producto_original: idProductoOriginal,
            id_producto_sustituto: sustituto.id,
            cantidad: cantidad,
            orden: (opciones.value[idProductoOriginal]?.length || 0) + 1,
            es_default: 0
        }
        
        console.log('📦 Agregando opción:', data)
        
        await axios.post(API_BASE, data)
        
        await cargarDatos()
        
        busquedas.value[idProductoOriginal] = ''
        opcionesSeleccionadas.value[idProductoOriginal] = null
        resultadosBusqueda.value[idProductoOriginal] = []
        cantidadMaxima.value[idProductoOriginal] = 1
        
    } catch (error) {
        console.error('❌ Error al agregar:', error)
        alert('Error al agregar opción: ' + (error.response?.data?.message || error.message))
    }
}

// 🔥 NUEVA FUNCIÓN: Editar cantidad máxima de una opción existente
const editarCantidad = async (opcion, incremento) => {
    const nuevaCantidad = (opcion.cantidad_maxima || 1) + incremento
    
    // Validar límites
    if (nuevaCantidad < 1) {
        alert('La cantidad mínima es 1')
        return
    }
    
    // Buscar el producto original para saber el máximo permitido
    const item = composicion.value.find(i => i.id_producto === opcion.id_producto_original)
    if (item && nuevaCantidad > item.porcion) {
        alert(`No puede exceder las ${item.porcion} unidades del producto original`)
        return
    }
    
    try {
        await axios.put(`${API_BASE}/${opcion.id_combo_opcion}`, {
            cantidad: nuevaCantidad
        })
        
        // Actualizar localmente
        opcion.cantidad_maxima = nuevaCantidad
        
        // Recargar datos para asegurar consistencia
        await cargarDatos()
        
    } catch (error) {
        console.error('❌ Error al editar cantidad:', error)
        alert('Error al editar cantidad: ' + (error.response?.data?.message || error.message))
    }
}

const eliminarOpcion = async (opcion) => {
    if (!confirm(`¿Eliminar la opción "${opcion.nombre_sustituto}"?`)) return
    
    try {
        await axios.delete(`${API_BASE}/${opcion.id_combo_opcion}`)
        await cargarDatos()
    } catch (error) {
        console.error('Error al eliminar:', error)
        alert('Error al eliminar: ' + (error.response?.data?.message || error.message))
    }
}

const moverOpcion = async (opcion, direccion) => {
    const opcionesActuales = [...(opciones.value[opcion.id_producto_original] || [])]
    const index = opcionesActuales.findIndex(o => o.id_combo_opcion === opcion.id_combo_opcion)
    
    let nuevoOrden = opcion.orden
    if (direccion === 'up' && index > 0) {
        nuevoOrden = opcionesActuales[index - 1].orden
        opcionesActuales[index - 1].orden = opcion.orden
        opcion.orden = nuevoOrden
    } else if (direccion === 'down' && index < opcionesActuales.length - 1) {
        nuevoOrden = opcionesActuales[index + 1].orden
        opcionesActuales[index + 1].orden = opcion.orden
        opcion.orden = nuevoOrden
    } else {
        return
    }
    
    try {
        await axios.put(`${API_BASE}/${opcion.id_combo_opcion}`, { orden: opcion.orden })
        
        if (direccion === 'up' && index > 0) {
            await axios.put(`${API_BASE}/${opcionesActuales[index - 1].id_combo_opcion}`, {
                orden: opcionesActuales[index - 1].orden
            })
        } else if (direccion === 'down' && index < opcionesActuales.length - 1) {
            await axios.put(`${API_BASE}/${opcionesActuales[index + 1].id_combo_opcion}`, {
                orden: opcionesActuales[index + 1].orden
            })
        }
        await cargarOpciones()
    } catch (error) {
        alert('Error al cambiar orden: ' + (error.response?.data?.message || error.message))
        await cargarOpciones()
    }
}

let timeouts = {}

watch(busquedas, (nuevasBusquedas) => {
    Object.keys(nuevasBusquedas).forEach(idProducto => {
        if (timeouts[idProducto]) {
            clearTimeout(timeouts[idProducto])
        }
        
        const query = nuevasBusquedas[idProducto]
        
        if (!query || query.length < 2) {
            resultadosBusqueda.value[Number(idProducto)] = []
            return
        }
        
        timeouts[idProducto] = setTimeout(() => {
            buscarProductos(Number(idProducto))
        }, 500)
    })
}, { deep: true })

onMounted(() => {
    cargarDatos()
})
</script>

<template>
    <div class="space-y-3 sm:space-y-4">
        <!-- Header -->
        <div class="flex justify-between items-center mb-3 sm:mb-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-random text-primary-500 text-[10px] sm:text-sm"></i>
                <span class="text-[10px] sm:text-sm font-medium text-gray-700">Opciones de cambio para cada producto del combo</span>
                <span class="text-[8px] sm:text-[10px] bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">
                    Cantidad máxima
                </span>
            </div>
        </div>

        <!-- Estado vacío -->
        <div v-if="!loading && composicion.length === 0" class="text-center py-6 sm:py-8 bg-yellow-50 rounded-lg border border-yellow-200">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl sm:text-3xl mb-2"></i>
            <p class="text-yellow-700 text-[10px] sm:text-sm font-medium">No hay productos en la composición</p>
            <p class="text-gray-500 text-[8px] sm:text-xs mt-1">Primero debe agregar productos en la pestaña <strong>"Inventario Detalle"</strong></p>
        </div>

        <div v-else-if="loading" class="text-center py-6 sm:py-8">
            <i class="fas fa-spinner fa-spin text-primary-500 text-xl sm:text-2xl"></i>
            <p class="text-gray-400 text-[10px] sm:text-sm mt-2">Cargando...</p>
        </div>

        <!-- Cards de productos -->
        <div v-else class="space-y-3 sm:space-y-4">
            <div v-for="item in composicion" :key="item.id_producto" class="border rounded-lg overflow-hidden shadow-sm bg-white">
                
                <!-- Header del producto -->
                <div class="bg-gray-50 px-3 sm:px-4 py-2 sm:py-2.5 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-1 sm:gap-2">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-box text-primary-500 text-[10px] sm:text-sm"></i>
                        <span class="font-semibold text-gray-800 text-[10px] sm:text-sm">{{ item.nombre }}</span>
                        <span class="text-[8px] sm:text-xs text-gray-400 italic">(original)</span>
                        <span class="text-[8px] sm:text-[10px] bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">
                            {{ item.porcion }} unidades
                        </span>
                    </div>
                    <span class="text-[8px] sm:text-[10px] text-gray-500 bg-white px-2 py-0.5 rounded-full border">
                        {{ opciones[item.id_producto]?.length || 0 }} opciones
                    </span>
                </div>
                
                <!-- Contenido -->
                <div class="p-3 sm:p-4 grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
                    
                    <!-- Opciones existentes con edición -->
                    <div>
                        <label class="block text-[8px] sm:text-[10px] font-bold uppercase text-gray-500 mb-1.5 sm:mb-2">Opciones Alternativas</label>
                        
                        <div v-if="opciones[item.id_producto] && opciones[item.id_producto].length > 0" class="space-y-1 max-h-48 sm:max-h-60 overflow-y-auto pr-1">
                            <div v-for="opcion in opciones[item.id_producto]" :key="opcion.id_combo_opcion" 
                                 class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-gray-50 p-2 border rounded hover:bg-gray-100 transition-colors gap-1 sm:gap-0">
                                
                                <!-- Información de la opción -->
                                <div class="flex items-center gap-2 min-w-0 w-full sm:w-auto flex-1">
                                    <i class="fas fa-exchange-alt text-primary-400 text-[8px] sm:text-xs flex-shrink-0"></i>
                                    <span class="text-[10px] sm:text-sm text-gray-700 truncate" :title="opcion.nombre_sustituto">
                                        {{ opcion.nombre_sustituto }}
                                    </span>
                                    
                                    <!-- 🔥 EDITAR CANTIDAD MÁXIMA DIRECTAMENTE -->
                                    <div class="flex items-center gap-1 flex-shrink-0 ml-1">
                                        <button 
                                            @click="editarCantidad(opcion, -1)" 
                                            class="w-5 h-5 rounded bg-gray-200 hover:bg-gray-300 text-gray-600 text-[10px] flex items-center justify-center"
                                            title="Disminuir cantidad máxima"
                                            :disabled="(opcion.cantidad_maxima || 1) <= 1"
                                        >
                                            −
                                        </button>
                                        <span class="text-[9px] font-bold text-primary-600 w-6 text-center">
                                            {{ opcion.cantidad_maxima || 1 }}
                                        </span>
                                        <button 
                                            @click="editarCantidad(opcion, 1)" 
                                            class="w-5 h-5 rounded bg-gray-200 hover:bg-gray-300 text-gray-600 text-[10px] flex items-center justify-center"
                                            title="Aumentar cantidad máxima"
                                            :disabled="(opcion.cantidad_maxima || 1) >= (item.porcion || 10)"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Acciones -->
                                <div class="flex gap-1 flex-shrink-0 w-full sm:w-auto justify-end">
                                    <button @click="moverOpcion(opcion, 'up')" class="w-6 h-6 rounded bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 text-[10px] sm:text-xs flex items-center justify-center shadow-sm" title="Mover arriba">
                                        ↑
                                    </button>
                                    <button @click="moverOpcion(opcion, 'down')" class="w-6 h-6 rounded bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 text-[10px] sm:text-xs flex items-center justify-center shadow-sm" title="Mover abajo">
                                        ↓
                                    </button>
                                    <button @click="eliminarOpcion(opcion)" class="w-6 h-6 rounded bg-red-50 hover:bg-red-100 border border-red-200 text-red-600 text-[10px] sm:text-xs flex items-center justify-center shadow-sm" title="Eliminar">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-4 sm:py-6 text-gray-400 text-[10px] sm:text-sm bg-gray-50 rounded border border-dashed">
                            No hay opciones adicionales asignadas
                        </div>
                    </div>

                    <!-- Agregar opción -->
                    <div class="border-t md:border-t-0 md:border-l pt-3 sm:pt-4 md:pt-0 md:pl-4 flex flex-col justify-between">
                        <div>
                            <label class="block text-[8px] sm:text-[10px] font-bold uppercase text-gray-500 mb-1.5 sm:mb-2">Añadir opción</label>
                            
                            <div class="relative">
                                <input type="text" 
                                       v-model="busquedas[item.id_producto]" 
                                       class="w-full border rounded-md pl-2 sm:pl-3 pr-6 sm:pr-8 py-1.5 text-[10px] sm:text-sm focus:border-primary-500 focus:ring-primary-500 text-gray-800"
                                       placeholder="Buscar sustituto (mínimo 2 caracteres)..."
                                       @keydown.enter="buscarProductos(item.id_producto)">
                                <span class="absolute right-2 top-2 text-gray-400 text-[8px] sm:text-[10px]" v-if="buscando[item.id_producto]">
                                    <i class="fas fa-spinner fa-spin text-primary-500"></i>
                                </span>
                            </div>

                            <!-- Campo de cantidad máxima -->
                            <div class="mt-2">
                                <label class="text-[8px] sm:text-[10px] text-gray-600 font-medium">Cantidad máxima a reemplazar:</label>
                                <input type="number" 
                                       v-model="cantidadMaxima[item.id_producto]" 
                                       min="1"
                                       :max="item.porcion"
                                       class="w-full border rounded-md px-2 py-1 text-[10px] sm:text-sm focus:border-primary-500 focus:ring-primary-500">
                                <span class="text-[8px] text-gray-400">¿Cuántas unidades puede reemplazar este sustituto?</span>
                            </div>

                            <!-- Resultados de búsqueda -->
                            <div class="mt-1.5 sm:mt-2 border rounded-md max-h-32 sm:max-h-36 overflow-y-auto bg-white shadow-inner" 
                                 v-if="busquedas[item.id_producto] && busquedas[item.id_producto].length >= 2">
                                
                                <div v-if="resultadosBusqueda[item.id_producto] && resultadosBusqueda[item.id_producto].length > 0">
                                    <div v-for="prod in resultadosBusqueda[item.id_producto]" :key="prod.id" 
                                         @click="opcionesSeleccionadas[item.id_producto] = prod"
                                         class="p-1.5 sm:p-2 border-b last:border-b-0 cursor-pointer text-[9px] sm:text-xs flex justify-between items-center transition-colors"
                                         :class="opcionesSeleccionadas[item.id_producto]?.id === prod.id ? 'bg-primary-50 text-primary-800 font-medium' : 'hover:bg-gray-50 text-gray-700'">
                                        <div class="truncate mr-2">
                                            <span class="font-mono bg-gray-100 px-1 rounded text-gray-600 text-[8px] sm:text-[10px] mr-1">{{ prod.Codigo }}</span>
                                            {{ prod.nombre }}
                                        </div>
                                        <i v-if="opcionesSeleccionadas[item.id_producto]?.id === prod.id" class="fas fa-check text-primary-600 text-[8px] sm:text-xs"></i>
                                    </div>
                                </div>
                                
                                <div v-else-if="!buscando[item.id_producto]" class="p-2 sm:p-3 text-center text-gray-400 text-[8px] sm:text-xs">
                                    <i class="fas fa-search mr-1"></i> No se encontraron productos
                                </div>
                                
                                <div v-else class="p-2 sm:p-3 text-center text-gray-400 text-[8px] sm:text-xs">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Buscando...
                                </div>
                            </div>
                            
                            <div v-else-if="busquedas[item.id_producto] && busquedas[item.id_producto].length > 0 && busquedas[item.id_producto].length < 2" 
                                 class="mt-0.5 text-gray-400 text-[8px] sm:text-[10px] italic">
                                Escriba al menos 2 caracteres para buscar...
                            </div>
                            
                            <div v-if="opcionesSeleccionadas[item.id_producto]" class="mt-1 text-[8px] sm:text-[10px] text-primary-600 font-medium">
                                <i class="fas fa-check-circle mr-1"></i> Seleccionado: {{ opcionesSeleccionadas[item.id_producto].nombre }}
                            </div>
                        </div>

                        <div class="mt-2 sm:mt-3 pt-2 sm:pt-3 border-t border-dashed flex justify-end">
                            <button @click="agregarOpcion(item.id_producto)" 
                                    :disabled="!opcionesSeleccionadas[item.id_producto]" 
                                    class="w-full md:w-auto px-3 sm:px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded text-[10px] sm:text-xs font-medium shadow-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-1.5">
                                <i class="fas fa-plus text-[8px] sm:text-[10px]"></i> Vincular
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>