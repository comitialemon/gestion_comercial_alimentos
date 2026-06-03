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

// Cambiamos variables del modal por estructuras indexadas por 'id_producto'
const busquedas = ref({})
const resultadosBusqueda = ref({})
const buscando = ref({})
const opcionesSeleccionadas = ref({})

// Cargar composición fija del combo
const cargarComposicion = async () => {
    if (!props.productoId) return
    
    loading.value = true
    try {
        const response = await axios.get(`/combo-opciones/${props.productoId}/composicion`)
        if (response.data.success) {
            composicion.value = response.data.composicion || []
            // Inicializar estados para cada item de la composición
            composicion.value.forEach(item => {
                if (!busquedas.value[item.id_producto]) busquedas.value[item.id_producto] = ''
                if (!resultadosBusqueda.value[item.id_producto]) resultadosBusqueda.value[item.id_producto] = []
                if (!buscando.value[item.id_producto]) buscando.value[item.id_producto] = false
                if (!opcionesSeleccionadas.value[item.id_producto]) opcionesSeleccionadas.value[item.id_producto] = null
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

// Cargar opciones existentes
const cargarOpciones = async () => {
    if (!props.productoId) return
    
    try {
        const response = await axios.get(`/combo-opciones/${props.productoId}`)
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
                        orden: op.orden
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

// Cargar todos los datos
const cargarDatos = async () => {
    await cargarComposicion()
    await cargarOpciones()
}

// Buscar productos disponibles por cada fila
const buscarProductos = async (idProductoOriginal) => {
    const query = busquedas.value[idProductoOriginal]
    
    if (!query || query.length < 2) {
        resultadosBusqueda.value[idProductoOriginal] = []
        return
    }
    
    buscando.value[idProductoOriginal] = true
    try {
        const response = await axios.get('/combo-opciones/productos/disponibles', {
            params: {
                id_producto_original: idProductoOriginal,
                search: query
            }
        })
        resultadosBusqueda.value[idProductoOriginal] = response.data || []
    } catch (error) {
        console.error('Error buscando:', error)
        resultadosBusqueda.value[idProductoOriginal] = []
    } finally {
        buscando.value[idProductoOriginal] = false
    }
}

// Agregar nueva opción desde la fila
const agregarOpcion = async (idProductoOriginal) => {
    const sustituto = opcionesSeleccionadas.value[idProductoOriginal]
    if (!sustituto) return
    
    try {
        await axios.post('/combo-opciones', {
            id_producto_combo: props.productoId,
            id_producto_original: idProductoOriginal,
            id_producto_sustituto: sustituto.id,
            orden: (opciones.value[idProductoOriginal]?.length || 0) + 1,
            es_default: 0
        })
        
        await cargarDatos()
        
        // Limpiar formulario específico de la fila
        busquedas.value[idProductoOriginal] = ''
        opcionesSeleccionadas.value[idProductoOriginal] = null
        resultadosBusqueda.value[idProductoOriginal] = []
        
    } catch (error) {
        alert('Error al agregar opción: ' + (error.response?.data?.message || error.message))
    }
}

// Eliminar opción
const eliminarOpcion = async (opcion) => {
    if (!confirm(`¿Eliminar la opción "${opcion.nombre_sustituto}"?`)) return
    
    try {
        await axios.delete(`/combo-opciones/${opcion.id_combo_opcion}`)
        await cargarDatos()
    } catch (error) {
        alert('Error al eliminar: ' + (error.response?.data?.message || error.message))
    }
}

// Mover opción (cambiar orden)
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
        await axios.put(`/combo-opciones/${opcion.id_combo_opcion}`, { orden: opcion.orden })
        
        if (direccion === 'up' && index > 0) {
            await axios.put(`/combo-opciones/${opcionesActuales[index - 1].id_combo_opcion}`, {
                orden: opcionesActuales[index - 1].orden
            })
        } else if (direccion === 'down' && index < opcionesActuales.length - 1) {
            await axios.put(`/combo-opciones/${opcionesActuales[index + 1].id_combo_opcion}`, {
                orden: opcionesActuales[index + 1].orden
            })
        }
        await cargarOpciones()
    } catch (error) {
        alert('Error al cambiar orden: ' + (error.response?.data?.message || error.message))
        await cargarOpciones()
    }
}

// Debounce para búsquedas reactivas por producto
let timeouts = {}
watch(busquedas, (nuevasBusquedas) => {
    // Detectar cuál input cambió rastreando los valores
    Object.keys(nuevasBusquedas).forEach(idProducto => {
        clearTimeout(timeouts[idProducto])
        timeouts[idProducto] = setTimeout(() => {
            buscarProductos(Number(idProducto))
        }, 500)
    })
}, { deep: true })

// Cargar datos al montar el componente
onMounted(() => {
    cargarDatos()
})
</script>

<template>
    <div class="space-y-4">
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-random text-primary-500"></i>
                <span class="text-sm font-medium text-gray-700">Opciones de cambio para cada producto del combo</span>
            </div>
        </div>

        <div v-if="!loading && composicion.length === 0" class="text-center py-8 bg-yellow-50 rounded-lg border border-yellow-200">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-2"></i>
            <p class="text-yellow-700 text-sm font-medium">No hay productos en la composición</p>
            <p class="text-gray-500 text-xs mt-1">Primero debe agregar productos en la pestaña <strong>"Inventario Detalle"</strong></p>
        </div>

        <div v-else-if="loading" class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-primary-500 text-2xl"></i>
            <p class="text-gray-400 text-sm mt-2">Cargando...</p>
        </div>

        <div v-else class="space-y-6">
            <div v-for="item in composicion" :key="item.id_producto" class="border rounded-lg overflow-hidden shadow-sm bg-white">
                
                <div class="bg-gray-50 px-4 py-2.5 border-b flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-box text-primary-500"></i>
                        <span class="font-semibold text-gray-800">{{ item.nombre }}</span>
                        <span class="text-xs text-gray-400 italic">(original / por defecto)</span>
                    </div>
                </div>
                
                <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Opciones Alternativas</label>
                        
                        <div v-if="opciones[item.id_producto] && opciones[item.id_producto].length > 0" class="space-y-1.5 max-h-60 overflow-y-auto pr-1">
                            <div v-for="opcion in opciones[item.id_producto]" :key="opcion.id_combo_opcion" 
                                 class="flex items-center justify-between bg-gray-50 p-2 border rounded hover:bg-gray-100 transition-colors">
                                <div class="flex items-center gap-2 min-w-0">
                                    <i class="fas fa-exchange-alt text-primary-400 text-xs flex-shrink-0"></i>
                                    <span class="text-sm text-gray-700 truncate" :title="opcion.nombre_sustituto">
                                        {{ opcion.nombre_sustituto }}
                                    </span>
                                </div>
                                <div class="flex gap-1 flex-shrink-0 ml-2">
                                    <button @click="moverOpcion(opcion, 'up')" class="w-6 h-6 rounded bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 text-xs flex items-center justify-center shadow-sm" title="Mover arriba">
                                        ↑
                                    </button>
                                    <button @click="moverOpcion(opcion, 'down')" class="w-6 h-6 rounded bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 text-xs flex items-center justify-center shadow-sm" title="Mover abajo">
                                        ↓
                                    </button>
                                    <button @click="eliminarOpcion(opcion)" class="w-6 h-6 rounded bg-red-50 hover:bg-red-100 border border-red-200 text-red-600 text-xs flex items-center justify-center shadow-sm" title="Eliminar">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-6 text-gray-400 text-sm bg-gray-50 rounded border border-dashed">
                            No hay opciones adicionales asignadas
                        </div>
                    </div>

                    <div class="border-t md:border-t-0 md:border-l pt-4 md:pt-0 md:pl-4 flex flex-col justify-between">
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Añadir opción a esta fila</label>
                            
                            <div class="relative">
                                <input type="text" 
                                       v-model="busquedas[item.id_producto]" 
                                       class="w-full border rounded-md pl-3 pr-8 py-1.5 text-sm focus:border-primary-500 focus:ring-primary-500 text-gray-800"
                                       placeholder="Buscar sustituto por código o nombre...">
                                <span class="absolute right-2 top-2.5 text-gray-400 text-xs" v-if="buscando[item.id_producto]">
                                    <i class="fas fa-spinner fa-spin text-primary-500"></i>
                                </span>
                            </div>

                            <div class="mt-2 border rounded-md max-h-36 overflow-y-auto bg-white shadow-inner" v-if="busquedas[item.id_producto] && busquedas[item.id_producto].length >= 2">
                                
                                <div v-if="resultadosBusqueda[item.id_producto]?.length > 0">
                                    <div v-for="prod in resultadosBusqueda[item.id_producto]" :key="prod.id" 
                                         @click="opcionesSeleccionadas[item.id_producto] = prod"
                                         class="p-2 border-b last:border-b-0 cursor-pointer text-xs flex justify-between items-center transition-colors"
                                         :class="opcionesSeleccionadas[item.id_producto]?.id === prod.id ? 'bg-primary-50 text-primary-800 font-medium' : 'hover:bg-gray-50 text-gray-700'">
                                        <div class="truncate mr-2">
                                            <span class="font-mono bg-gray-100 px-1 rounded text-gray-600 text-[10px] mr-1">{{ prod.Codigo }}</span>
                                            {{ prod.nombre }}
                                        </div>
                                        <i v-if="opcionesSeleccionadas[item.id_producto]?.id === prod.id" class="fas fa-check text-primary-600 text-xs"></i>
                                    </div>
                                </div>
                                
                                <div v-else-if="!buscando[item.id_producto]" class="p-3 text-center text-gray-400 text-xs">
                                    No se encontraron productos
                                </div>
                            </div>
                            
                            <div v-else-if="busquedas[item.id_producto]?.length > 0" class="mt-1 text-gray-400 text-[11px] italic">
                                Escriba al menos 2 caracteres...
                            </div>
                        </div>

                        <div class="mt-3 pt-3 border-t border-dashed flex justify-end">
                            <button @click="agregarOpcion(item.id_producto)" 
                                    :disabled="!opcionesSeleccionadas[item.id_producto]" 
                                    class="w-full md:w-auto px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded text-xs font-medium shadow-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-1.5">
                                <i class="fas fa-plus"></i> Vincular Opción
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>