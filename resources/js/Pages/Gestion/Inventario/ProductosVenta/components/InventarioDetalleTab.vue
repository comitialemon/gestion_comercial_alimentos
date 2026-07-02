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
const nuevaFila = ref({ IdProducto: '', Porcion: 1, editando: false })
const errors = ref({})

// Búsqueda de producto
const busquedaProducto = ref('')
const showDropdown = ref(false)
const dropdownRef = ref(null)
const inputRef = ref(null)

// Estado para el Modal de Confirmación
const showDeleteModal = ref(false)
const filaAEliminar = ref(null)

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

const seleccionarProducto = (producto) => {
    nuevaFila.value.IdProducto = producto.id
    busquedaProducto.value = `${producto.Codigo} - ${producto.Descripcion}`
    showDropdown.value = false
    errors.value.IdProducto = ''
}

const limpiarSeleccion = () => {
    nuevaFila.value.IdProducto = ''
    busquedaProducto.value = ''
    showDropdown.value = false
}

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
        const response = await axios.post('/gestion/inventario/productos-venta/detalle', {
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

const guardarEdicion = async (detalle) => {
    if (detalle.Porcion <= 0) {
        alert('La porción debe ser mayor a 0')
        return
    }
    
    loading.value = true
    try {
        const response = await axios.put(`/gestion/inventario/productos-venta/detalle/${detalle.IdDetalleProductoPorcion}`, {
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

const confirmarEliminarFila = (detalle) => {
    filaAEliminar.value = detalle
    showDeleteModal.value = true
}

const ejecutarEliminacion = async () => {
    if (!filaAEliminar.value) return
    
    try {
        const response = await axios.delete(`/gestion/inventario/productos-venta/detalle/${filaAEliminar.value.IdDetalleProductoPorcion}`)
        if (response.data.success) {
            detalles.value = detalles.value.filter(d => d.IdDetalleProductoPorcion !== filaAEliminar.value.IdDetalleProductoPorcion)
            emit('update', detalles.value)
        }
    } catch (error) {
        alert('Error al eliminar')
    } finally {
        showDeleteModal.value = false
        filaAEliminar.value = null
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
            <div class="flex items-center gap-2.5">
                <div class="p-2 bg-primary-50 rounded-lg text-primary-600">
                    <i class="fas fa-cubes text-xs sm:text-sm"></i>
                </div>
                <div>
                    <span class="text-xs sm:text-sm font-semibold text-gray-800 block">Composición del Item</span>
                    <span class="text-[10px] sm:text-xs text-gray-500">Gestione los productos e ingredientes que componen este artículo.</span>
                </div>
            </div>
            <button v-if="!nuevaFila.editando" @click="agregarFila" 
                    class="w-full sm:w-auto bg-primary-600 hover:bg-primary-700 text-white px-3.5 py-2 rounded-lg text-xs font-medium flex items-center justify-center gap-2 transition-all shadow-sm active:scale-[0.98]">
                <i class="fas fa-plus text-[10px]"></i> Agregar producto
            </button>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden min-h-[300px]">
            
            <table class="min-w-full hidden md:table border-collapse">
                <thead class="bg-gray-50 border-b border-gray-100 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider w-7/12">Producto (Código - Descripción)</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider w-3/12">Porción</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider w-2/12">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="nuevaFila.editando" class="bg-primary-50/40">
                        <td class="px-4 py-3 relative align-middle">
                            <div class="relative">
                                <input 
                                    ref="inputRef"
                                    type="text" 
                                    v-model="busquedaProducto" 
                                    @focus="abrirDropdown"
                                    @blur="cerrarDropdown"
                                    @input="showDropdown = true"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all bg-white"
                                    placeholder="Buscar por código o descripción..."
                                >
                                <button v-if="busquedaProducto" @click="limpiarSeleccion" 
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="showDropdown && busquedaProducto && productosFiltrados.length > 0" 
                                     ref="dropdownRef"
                                     class="absolute z-50 mt-1.5 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-[220px] overflow-y-auto divide-y divide-gray-50">
                                    <div v-for="p in productosFiltrados" :key="p.id" @click="seleccionarProducto(p)" @mousedown.prevent
                                         class="px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs flex items-center gap-2.5 transition">
                                        <span class="font-mono text-[10px] font-bold bg-primary-50 px-2 py-0.5 rounded text-primary-700">{{ p.Codigo }}</span>
                                        <span class="text-gray-700 truncate font-medium">{{ p.Descripcion }}</span>
                                    </div>
                                </div>
                                
                                <div v-if="showDropdown && busquedaProducto && productosFiltrados.length === 0"
                                     class="absolute z-50 mt-1.5 w-full bg-white border border-gray-200 rounded-lg shadow-xl p-3 text-center text-gray-400 text-xs">
                                    No se encontraron productos coincidentes
                                </div>
                            </div>
                            <p v-if="errors.IdProducto" class="text-red-500 text-[11px] mt-1 flex items-center gap-1 font-medium"><i class="fas fa-exclamation-circle"></i> {{ errors.IdProducto }}</p>
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <div class="flex flex-col items-end justify-center w-full">
                                <input 
                                    type="number" 
                                    v-model.number="nuevaFila.Porcion" 
                                    step="0.000001" 
                                    min="0" 
                                    class="no-arrows w-full max-w-[140px] border border-gray-300 rounded-lg px-3 py-1.5 text-right text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-semibold"
                                >
                                <p v-if="errors.Porcion" class="text-red-500 text-[11px] mt-1 font-medium text-right">{{ errors.Porcion }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center align-middle">
                            <div class="flex items-center justify-center gap-2">
                                <button @click="guardarNuevaFila" :disabled="loading" class="text-emerald-600 hover:text-emerald-700 p-1.5 hover:bg-emerald-50 rounded-md transition" title="Guardar">
                                    <i class="fas fa-save text-sm"></i>
                                </button>
                                <button @click="cancelarNuevaFila" class="text-gray-400 hover:text-gray-500 p-1.5 hover:bg-gray-50 rounded-md transition" title="Cancelar">
                                    <i class="fas fa-times text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-for="detalle in detalles" :key="detalle.IdDetalleProductoPorcion" class="hover:bg-gray-50/80 transition-colors group">
                        <td class="px-4 py-3 text-xs text-gray-700 align-middle">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-box text-gray-400 group-hover:text-primary-500 transition-colors"></i>
                                <span class="font-mono text-[10px] font-bold bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">{{ detalle.producto?.Codigo || '-' }}</span>
                                <span class="font-medium text-gray-800">{{ detalle.producto?.Descripcion || '-' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <div v-if="!detalle.editando" class="text-right font-bold text-gray-700 text-xs tracking-wide pr-3">
                                {{ Number(detalle.Porcion).toFixed(6) }}
                            </div>
                            <div v-else class="flex justify-end w-full">
                                <input 
                                    type="number" 
                                    v-model.number="detalle.Porcion" 
                                    step="0.000001" 
                                    min="0" 
                                    class="no-arrows w-full max-w-[140px] border border-gray-300 rounded-lg px-3 py-1.5 text-right text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-semibold"
                                >
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center align-middle">
                            <div class="flex items-center justify-center gap-1.5">
                                <button v-if="!detalle.editando" @click="editarFila(detalle)" class="text-blue-600 hover:text-blue-700 p-1.5 hover:bg-blue-50 rounded-md transition" title="Editar">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <button v-if="detalle.editando" @click="guardarEdicion(detalle)" class="text-emerald-600 hover:text-emerald-700 p-1.5 hover:bg-emerald-50 rounded-md transition" title="Guardar">
                                    <i class="fas fa-save text-xs"></i>
                                </button>
                                <button @click="confirmarEliminarFila(detalle)" class="text-red-500 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-md transition" title="Eliminar">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="detalles.length === 0 && !nuevaFila.editando">
                        <td colspan="3" class="px-4 py-12 text-center">
                            <div class="max-w-[240px] mx-auto">
                                <i class="fas fa-cubes text-gray-300 text-3xl mb-3 block"></i>
                                <p class="text-gray-400 text-xs font-medium">No hay productos agregados en el detalle actualmente.</p>
                                <button @click="agregarFila" class="mt-2 text-primary-600 hover:text-primary-700 text-xs font-semibold underline decoration-2 underline-offset-4">+ Vincular Producto</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="md:hidden divide-y divide-gray-100">
                <div v-for="detalle in detalles" :key="detalle.IdDetalleProductoPorcion" class="p-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex justify-between items-start gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                <span class="font-mono text-[9px] font-bold bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">{{ detalle.producto?.Codigo || '-' }}</span>
                            </div>
                            <p class="text-xs font-semibold text-gray-800 line-clamp-2">{{ detalle.producto?.Descripcion || '-' }}</p>
                        </div>
                        <div class="flex-shrink-0 text-right">
                            <span class="text-[10px] text-gray-400 block mb-0.5 uppercase tracking-wider font-bold">Porción</span>
                            <div v-if="!detalle.editando" class="font-bold text-primary-600 text-xs">
                                {{ Number(detalle.Porcion).toFixed(6) }}
                            </div>
                            <input 
                                v-else 
                                type="number" 
                                v-model.number="detalle.Porcion" 
                                step="0.000001" 
                                min="0" 
                                class="no-arrows w-24 border border-gray-300 rounded-md px-2 py-1 text-right text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-semibold"
                            >
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-3 pt-2.5 border-t border-gray-50">
                        <button v-if="!detalle.editando" @click="editarFila(detalle)" class="text-blue-600 hover:text-blue-700 text-xs font-medium flex items-center gap-1">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button v-if="detalle.editando" @click="guardarEdicion(detalle)" class="text-emerald-600 hover:text-emerald-700 text-xs font-medium flex items-center gap-1">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                        <button @click="confirmarEliminarFila(detalle)" class="text-red-500 hover:text-red-600 text-xs font-medium flex items-center gap-1">
                            <i class="fas fa-trash-alt"></i> Eliminar
                        </button>
                    </div>
                </div>

                <div v-if="nuevaFila.editando" class="p-4 bg-primary-50/30 space-y-3">
                    <div>
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Producto</label>
                        <div class="relative">
                            <input 
                                ref="inputRef"
                                type="text" 
                                v-model="busquedaProducto" 
                                @focus="abrirDropdown"
                                @blur="cerrarDropdown"
                                @input="showDropdown = true"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all bg-white"
                                placeholder="Buscar producto..."
                            >
                            <button v-if="busquedaProducto" @click="limpiarSeleccion" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                            
                            <div v-if="showDropdown && busquedaProducto && productosFiltrados.length > 0"
                                 class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-[160px] overflow-y-auto">
                                <div v-for="p in productosFiltrados" :key="p.id" @click="seleccionarProducto(p)" @mousedown.prevent
                                     class="px-3 py-2 hover:bg-gray-50 text-xs flex items-center gap-2 border-b border-gray-50 last:border-0">
                                    <span class="font-mono text-[9px] font-bold bg-primary-50 px-1.5 py-0.5 rounded text-primary-700">{{ p.Codigo }}</span>
                                    <span class="text-gray-700 truncate font-medium">{{ p.Descripcion }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-if="errors.IdProducto" class="text-red-500 text-[11px] mt-1 font-medium"><i class="fas fa-exclamation-circle"></i> {{ errors.IdProducto }}</p>
                    </div>

                    <div>
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Porción</label>
                        <input 
                            type="number" 
                            v-model.number="nuevaFila.Porcion" 
                            step="0.000001" 
                            min="0" 
                            class="no-arrows w-full border border-gray-300 rounded-lg px-3 py-1.5 text-right text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-semibold"
                        >
                        <p v-if="errors.Porcion" class="text-red-500 text-[11px] mt-1 font-medium">{{ errors.Porcion }}</p>
                    </div>

                    <div class="flex gap-2 pt-1.5">
                        <button @click="guardarNuevaFila" :disabled="loading" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-xs font-medium flex items-center justify-center gap-1.5 transition">
                            <i class="fas fa-save text-[11px]"></i> Guardar
                        </button>
                        <button @click="cancelarNuevaFila" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-2 rounded-lg text-xs font-medium flex items-center justify-center gap-1.5 transition">
                            <i class="fas fa-times text-[11px]"></i> Cancelar
                        </button>
                    </div>
                </div>

                <div v-if="detalles.length === 0 && !nuevaFila.editando" class="p-8 text-center">
                    <i class="fas fa-cubes text-gray-300 text-2xl mb-2 block"></i>
                    <p class="text-gray-400 text-xs font-medium">No hay productos agregados</p>
                    <button @click="agregarFila" class="mt-1.5 text-primary-600 text-xs font-semibold underline">+ Vincular Producto</button>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 z-[99999] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" @click="showDeleteModal = false"></div>
            <div class="bg-white rounded-xl shadow-xl overflow-hidden max-w-md w-full border border-gray-100 transform transition-all z-10 p-5 space-y-4">
                <div class="flex items-start gap-3.5">
                    <div class="p-2.5 bg-red-50 rounded-full text-red-600 flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-base"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-gray-900">¿Remover de la composición?</h3>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            ¿Está seguro de que desea eliminar el producto <span class="font-bold text-gray-700">"{{ filaAEliminar?.producto?.Descripcion }}"</span> del desglose de porciones? Esta acción modificará la estructura actual del item.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showDeleteModal = false" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-xs font-semibold transition">
                        Cancelar
                    </button>
                    <button @click="ejecutarEliminacion" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-semibold transition shadow-sm active:scale-95">
                        Sí, eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-arrows::-webkit-outer-spin-button,
.no-arrows::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-arrows {
    -moz-appearance: textfield;
}
</style>