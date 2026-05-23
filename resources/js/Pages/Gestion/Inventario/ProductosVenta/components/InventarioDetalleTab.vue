<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import axios from 'axios'

const props = defineProps({
    productoId: Number,
    productosInventario: Array,
    detallesIniciales: Array,
})

const emit = defineEmits(['update'])

const detalles = ref(props.detallesIniciales || [])
const loading = ref(false)
const nuevaFila = ref({ IdProducto: '', Porcion: 1, editando: true })
const errors = ref({})

// Búsqueda de producto
const busquedaProducto = ref('')
const showDropdown = ref(false)
const dropdownRef = ref(null)
const inputRef = ref(null)

// Productos filtrados
const productosFiltrados = computed(() => {
    let productos = props.productosInventario || []
    
    if (busquedaProducto.value) {
        const termino = busquedaProducto.value.toLowerCase()
        productos = productos.filter(p => {
            const codigo = (p.Codigo || '').toLowerCase()
            const descripcion = (p.Descripcion || '').toLowerCase()
            return codigo.includes(termino) || descripcion.includes(termino)
        })
    }
    
    return productos
})

// Seleccionar producto
const seleccionarProducto = (producto) => {
    nuevaFila.value.IdProducto = producto.id
    busquedaProducto.value = `${producto.Codigo} - ${producto.Descripcion}`
    showDropdown.value = false
    errors.value.IdProducto = ''
}

// Limpiar selección
const limpiarSeleccion = () => {
    nuevaFila.value.IdProducto = ''
    busquedaProducto.value = ''
    showDropdown.value = false
}

// Manejar clic fuera del dropdown
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target) && 
        inputRef.value && !inputRef.value.contains(event.target)) {
        showDropdown.value = false
    }
}

const abrirDropdown = () => {
    if (busquedaProducto.value) {
        showDropdown.value = true
    }
}

const cerrarDropdown = () => {
    setTimeout(() => {
        showDropdown.value = false
    }, 200)
}

if (typeof window !== 'undefined') {
    window.addEventListener('click', handleClickOutside)
}

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('click', handleClickOutside)
    }
})

const agregarFila = () => {
    nuevaFila.value = { IdProducto: '', Porcion: 1, editando: true }
    busquedaProducto.value = ''
    showDropdown.value = false
    errors.value = {}
}

// 🔥 SIN VALIDACIÓN DE DUPLICADOS - Solo guarda
const guardarNuevaFila = async () => {
    if (!nuevaFila.value.IdProducto) {
        errors.value = { IdProducto: 'Seleccione un producto' }
        return
    }
    if (nuevaFila.value.Porcion <= 0) {
        errors.value = { Porcion: 'La porción debe ser mayor a 0' }
        return
    }
    
    loading.value = true
    errors.value = {}
    
    try {
        const response = await axios.post('/gestion/productos-venta/detalle', {
            IdDetalleProducto: props.productoId,
            IdProducto: nuevaFila.value.IdProducto,
            Porcion: nuevaFila.value.Porcion,
        })
        
        if (response.data.success) {
            detalles.value.push(response.data.detalle)
            emit('update', [...detalles.value])
            nuevaFila.value = { IdProducto: '', Porcion: 1, editando: false }
            busquedaProducto.value = ''
            showDropdown.value = false
        }
    } catch (error) {
        errors.value = { general: error.response?.data?.message || 'Error al guardar' }
    } finally {
        loading.value = false
    }
}

const cancelarNuevaFila = () => {
    nuevaFila.value = { IdProducto: '', Porcion: 1, editando: false }
    busquedaProducto.value = ''
    showDropdown.value = false
    errors.value = {}
}

const editarFila = (detalle) => {
    detalle.editando = true
}

// 🔥 SIN VALIDACIÓN DE DUPLICADOS - Solo actualiza
const guardarEdicion = async (detalle) => {
    if (detalle.Porcion <= 0) {
        alert('La porción debe ser mayor a 0')
        return
    }
    
    loading.value = true
    try {
        const response = await axios.put(`/gestion/productos-venta/detalle/${detalle.IdDetalleProductoPorcion}`, {
            Porcion: detalle.Porcion
        })
        
        if (response.data.success) {
            detalle.editando = false
            emit('update', [...detalles.value])
        }
    } catch (error) {
        alert('Error al guardar')
    } finally {
        loading.value = false
    }
}

const eliminarFila = async (detalle) => {
    if (!confirm(`¿Eliminar el producto "${detalle.producto?.Descripcion}" del detalle?`)) return
    
    try {
        const response = await axios.delete(`/gestion/productos-venta/detalle/${detalle.IdDetalleProductoPorcion}`)
        if (response.data.success) {
            detalles.value = detalles.value.filter(d => d.IdDetalleProductoPorcion !== detalle.IdDetalleProductoPorcion)
            emit('update', detalles.value)
        }
    } catch (error) {
        alert('Error al eliminar')
    }
}
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-cubes text-guindo-500 text-sm"></i>
                <span class="text-[11px] font-medium text-gray-600">Productos que componen este item (porciones)</span>
            </div>
            <button v-if="!nuevaFila.editando" @click="agregarFila" class="bg-guindo-600 hover:bg-guindo-700 text-white px-3 py-1 rounded-md text-[11px] flex items-center gap-1 transition shadow-sm">
                <i class="fas fa-plus text-[10px]"></i> Agregar producto
            </button>
        </div>

        <!-- Contenedor con altura mínima -->
        <div class="overflow-x-auto rounded-lg border border-gray-200" style="min-height: 300px;">
            <table class="min-w-full">
                <thead class="bg-guindo-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left text-[11px] font-semibold text-guindo-700 uppercase tracking-wider">Producto (Código - Descripción)</th>
                        <th class="px-4 py-2 text-right text-[11px] font-semibold text-guindo-700 uppercase tracking-wider w-28">Porción</th>
                        <th class="px-4 py-2 text-center text-[11px] font-semibold text-guindo-700 uppercase tracking-wider w-20">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <!-- Nueva fila con buscador -->
                    <tr v-if="nuevaFila.editando" class="bg-amber-50">
                        <td class="px-4 py-2 relative">
                            <div class="relative">
                                <input 
                                    ref="inputRef"
                                    type="text" 
                                    v-model="busquedaProducto" 
                                    @focus="abrirDropdown"
                                    @blur="cerrarDropdown"
                                    @input="showDropdown = true"
                                    class="w-full border border-gray-300 rounded-md px-2 py-1 text-[11px] focus:ring-guindo-500 focus:border-guindo-500"
                                    placeholder="Buscar producto por código o descripción..."
                                >
                                <button 
                                    v-if="busquedaProducto"
                                    @click="limpiarSeleccion"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                
                                <!-- Dropdown de resultados -->
                                <div 
                                    v-if="showDropdown && busquedaProducto && productosFiltrados.length > 0"
                                    ref="dropdownRef"
                                    class="absolute z-[9999] mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-[250px] overflow-y-auto"
                                >
                                    <div
                                        v-for="p in productosFiltrados"
                                        :key="p.id"
                                        @click="seleccionarProducto(p)"
                                        @mousedown.prevent
                                        class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-[11px] border-b last:border-b-0 flex items-center gap-2"
                                    >
                                        <span class="font-mono text-[10px] bg-gray-100 px-1.5 py-0.5 rounded text-guindo-600">{{ p.Codigo }}</span>
                                        <span class="text-gray-700">{{ p.Descripcion }}</span>
                                    </div>
                                </div>
                                
                                <div 
                                    v-if="showDropdown && busquedaProducto && productosFiltrados.length === 0"
                                    class="absolute z-[9999] mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-[11px]"
                                >
                                    No se encontraron productos
                                </div>
                            </div>
                            <p v-if="errors.IdProducto" class="text-red-500 text-[10px] mt-1">{{ errors.IdProducto }}</p>
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" v-model.number="nuevaFila.Porcion" step="0.000001" min="0" class="w-24 ml-auto border border-gray-300 rounded-md px-2 py-1 text-right text-[11px] focus:ring-guindo-500 focus:border-guindo-500">
                            <p v-if="errors.Porcion" class="text-red-500 text-[10px] mt-1">{{ errors.Porcion }}</p>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <button @click="guardarNuevaFila" :disabled="loading" class="text-green-600 hover:text-green-800 mr-2 transition" title="Guardar">
                                <i class="fas fa-save text-[12px]"></i>
                            </button>
                            <button @click="cancelarNuevaFila" class="text-gray-400 hover:text-gray-600 transition" title="Cancelar">
                                <i class="fas fa-times text-[12px]"></i>
                            </button>
                        </td>
                    </tr>

                    <!-- Filas existentes -->
                    <tr v-for="detalle in detalles" :key="detalle.IdDetalleProductoPorcion" class="hover:bg-gray-50 transition">
                        <td class="px-4 py-2 text-[11px] text-gray-700">
                            <i class="fas fa-box text-guindo-400 text-[10px] mr-2"></i>
                            <span class="font-mono text-[10px] bg-gray-100 px-1.5 py-0.5 rounded mr-2">{{ detalle.producto?.Codigo || '-' }}</span>
                            {{ detalle.producto?.Descripcion || '-' }}
                        </td>
                        <td class="px-4 py-2">
                            <div v-if="!detalle.editando" class="text-right font-semibold text-guindo-600 text-[11px]">
                                {{ Number(detalle.Porcion).toFixed(6) }}
                            </div>
                            <input v-else type="number" v-model.number="detalle.Porcion" step="0.000001" min="0" class="w-24 ml-auto border border-gray-300 rounded-md px-2 py-1 text-right text-[11px] focus:ring-guindo-500 focus:border-guindo-500">
                        </td>
                        <td class="px-4 py-2 text-center">
                            <button v-if="!detalle.editando" @click="editarFila(detalle)" class="text-guindo-600 hover:text-guindo-800 mr-2 transition" title="Editar">
                                <i class="fas fa-edit text-[12px]"></i>
                            </button>
                            <button v-if="detalle.editando" @click="guardarEdicion(detalle)" class="text-green-600 hover:text-green-800 mr-2 transition" title="Guardar">
                                <i class="fas fa-save text-[12px]"></i>
                            </button>
                            <button @click="eliminarFila(detalle)" class="text-red-500 hover:text-red-700 transition" title="Eliminar">
                                <i class="fas fa-trash-alt text-[12px]"></i>
                            </button>
                        </td>
                    </tr>

                    <tr v-if="detalles.length === 0 && !nuevaFila.editando">
                        <td colspan="3" class="px-4 py-8 text-center">
                            <i class="fas fa-cubes text-gray-300 text-2xl mb-2 block"></i>
                            <p class="text-gray-400 text-[11px]">No hay productos agregados al detalle</p>
                            <button @click="agregarFila" class="mt-2 text-guindo-600 hover:text-guindo-700 text-[11px] font-medium">+ Agregar producto</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>