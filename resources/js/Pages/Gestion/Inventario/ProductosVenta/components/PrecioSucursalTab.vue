<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import axios from 'axios'

const props = defineProps({
    productoId: Number,
    sucursales: Array,
    preciosIniciales: Array,
})

const emit = defineEmits(['update'])

const precios = ref(props.preciosIniciales || [])
const loading = ref(false)
const nuevaFila = ref({ IdSucursal: '', Precio: 0, editando: true })
const errors = ref({})
const editandoId = ref(null)
const editPrecioValue = ref(0)

// BÚSQUEDA DE SUCURSAL
const busquedaSucursal = ref('')
const showSucursalDropdown = ref(false)
const sucursalDropdownRef = ref(null)
const sucursalInputRef = ref(null)

// Sucursales filtradas
const sucursalesFiltradas = computed(() => {
    if (!props.sucursales || !Array.isArray(props.sucursales)) {
        return []
    }
    
    if (!busquedaSucursal.value || busquedaSucursal.value.trim() === '') {
        return props.sucursales
    }
    
    const termino = busquedaSucursal.value.toLowerCase().trim()
    return props.sucursales.filter(s => {
        const nombre = String(s.nombre || s.Nombre || '').toLowerCase()
        return nombre.includes(termino)
    })
})

// Abrir/cerrar dropdown de sucursal
const abrirSucursalDropdown = () => {
    if (busquedaSucursal.value) {
        showSucursalDropdown.value = true
    }
}

const cerrarSucursalDropdown = () => {
    setTimeout(() => {
        showSucursalDropdown.value = false
    }, 200)
}

const handleSucursalClickOutside = (event) => {
    if (sucursalDropdownRef.value && !sucursalDropdownRef.value.contains(event.target) && 
        sucursalInputRef.value && !sucursalInputRef.value.contains(event.target)) {
        showSucursalDropdown.value = false
    }
}

// Seleccionar sucursal
const seleccionarSucursal = (sucursal) => {
    nuevaFila.value.IdSucursal = sucursal.id
    busquedaSucursal.value = sucursal.nombre || sucursal.Nombre
    showSucursalDropdown.value = false
    errors.value.IdSucursal = ''
}

// Limpiar selección de sucursal
const limpiarSeleccionSucursal = () => {
    nuevaFila.value.IdSucursal = ''
    busquedaSucursal.value = ''
    showSucursalDropdown.value = false
}

// Agregar evento global
if (typeof window !== 'undefined') {
    window.addEventListener('click', handleSucursalClickOutside)
}

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('click', handleSucursalClickOutside)
    }
})

const agregarFila = () => {
    nuevaFila.value = { IdSucursal: '', Precio: 0, editando: true }
    busquedaSucursal.value = ''
    showSucursalDropdown.value = false
    errors.value = {}
}

const guardarNuevaFila = async () => {
    if (!nuevaFila.value.IdSucursal) {
        errors.value = { IdSucursal: 'Seleccione una sucursal' }
        return
    }
    if (nuevaFila.value.Precio <= 0) {
        errors.value = { Precio: 'El precio debe ser mayor a 0' }
        return
    }
    
    loading.value = true
    errors.value = {}
    
    try {
        const response = await axios.post('/gestion/inventario/productos-venta/precio-sucursal', {
            IdProducto: props.productoId,
            IdSucursal: nuevaFila.value.IdSucursal,
            Precio: nuevaFila.value.Precio,
        })
        
        if (response.data.success) {
            precios.value.push(response.data.precio)
            emit('update', [...precios.value])
            nuevaFila.value = { IdSucursal: '', Precio: 0, editando: false }
            busquedaSucursal.value = ''
            showSucursalDropdown.value = false
        }
    } catch (error) {
        errors.value = { general: error.response?.data?.message || 'Error al guardar' }
    } finally {
        loading.value = false
    }
}

const cancelarNuevaFila = () => {
    nuevaFila.value = { IdSucursal: '', Precio: 0, editando: false }
    busquedaSucursal.value = ''
    showSucursalDropdown.value = false
    errors.value = {}
}

const editarFila = (precio) => {
    editandoId.value = precio.IdPrecio
    editPrecioValue.value = precio.Precio
}

const guardarEdicion = async (precio) => {
    if (editPrecioValue.value <= 0) {
        alert('El precio debe ser mayor a 0')
        return
    }
    
    loading.value = true
    try {
        const response = await axios.put(`/gestion/inventario/productos-venta/precio-sucursal/${precio.IdPrecio}`, {
            Precio: editPrecioValue.value
        })
        
        if (response.data.success) {
            precio.Precio = editPrecioValue.value
            editandoId.value = null
            emit('update', [...precios.value])
        }
    } catch (error) {
        alert('Error al guardar')
    } finally {
        loading.value = false
    }
}

const cancelarEdicion = () => {
    editandoId.value = null
    editPrecioValue.value = 0
}

// Modal de eliminación
const showDeleteModal = ref(false)
const precioAEliminar = ref(null)

const confirmarEliminarFila = (precio) => {
    precioAEliminar.value = precio
    showDeleteModal.value = true
}

const ejecutarEliminacion = async () => {
    if (!precioAEliminar.value) return
    
    try {
        const response = await axios.delete(`/gestion/inventario/productos-venta/precio-sucursal/${precioAEliminar.value.IdPrecio}`)
        if (response.data.success) {
            precios.value = precios.value.filter(p => p.IdPrecio !== precioAEliminar.value.IdPrecio)
            emit('update', precios.value)
        }
    } catch (error) {
        alert('Error al eliminar')
    } finally {
        showDeleteModal.value = false
        precioAEliminar.value = null
    }
}

const nombreSucursal = (sucursal) => {
    if (!sucursal) return '-'
    return `${sucursal.Nombre} ${sucursal.NumeroSucursal ? `(N° ${sucursal.NumeroSucursal})` : ''}`
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
            <div class="flex items-center gap-2.5">
                <div class="p-2 bg-primary-50 rounded-lg text-primary-600">
                    <i class="fas fa-store text-xs sm:text-sm"></i>
                </div>
                <div>
                    <span class="text-xs sm:text-sm font-semibold text-gray-800 block">Precios por Sucursal</span>
                    <span class="text-[10px] sm:text-xs text-gray-500">Configure precios diferenciados para cada sucursal.</span>
                </div>
            </div>
            <button v-if="!nuevaFila.editando" @click="agregarFila" 
                    class="w-full sm:w-auto bg-primary-600 hover:bg-primary-700 text-white px-3.5 py-2 rounded-lg text-xs font-medium flex items-center justify-center gap-2 transition-all shadow-sm active:scale-[0.98]">
                <i class="fas fa-plus text-[10px]"></i> Nuevo precio
            </button>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden min-h-[300px]">
            
            <table class="min-w-full hidden md:table border-collapse">
                <thead class="bg-gray-50 border-b border-gray-100 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider w-7/12">Sucursal</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider w-3/12">Precio (Bs)</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider w-2/12">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="nuevaFila.editando" class="bg-primary-50/40">
                        <td class="px-4 py-3 relative align-middle">
                            <div class="relative">
                                <input 
                                    ref="sucursalInputRef"
                                    type="text" 
                                    v-model="busquedaSucursal" 
                                    @focus="abrirSucursalDropdown"
                                    @blur="cerrarSucursalDropdown"
                                    @input="showSucursalDropdown = true"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all bg-white"
                                    placeholder="Buscar sucursal..."
                                >
                                <button v-if="busquedaSucursal" @click="limpiarSeleccionSucursal" 
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div v-if="showSucursalDropdown && busquedaSucursal && sucursalesFiltradas.length > 0" 
                                     ref="sucursalDropdownRef"
                                     class="absolute z-50 mt-1.5 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-[220px] overflow-y-auto divide-y divide-gray-50">
                                    <div v-for="s in sucursalesFiltradas" :key="s.id" @click="seleccionarSucursal(s)" @mousedown.prevent
                                         class="px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs flex items-center gap-2.5 transition">
                                        <i class="fas fa-store text-primary-400 text-[10px]"></i>
                                        <span class="text-gray-700 truncate font-medium">{{ s.nombre || s.Nombre }}</span>
                                        <span v-if="s.NumeroSucursal" class="text-[9px] text-gray-400 ml-auto">N° {{ s.NumeroSucursal }}</span>
                                    </div>
                                </div>
                                
                                <div v-if="showSucursalDropdown && busquedaSucursal && sucursalesFiltradas.length === 0"
                                     class="absolute z-50 mt-1.5 w-full bg-white border border-gray-200 rounded-lg shadow-xl p-3 text-center text-gray-400 text-xs">
                                    No se encontraron sucursales coincidentes
                                </div>
                            </div>
                            <p v-if="errors.IdSucursal" class="text-red-500 text-[11px] mt-1 flex items-center gap-1 font-medium"><i class="fas fa-exclamation-circle"></i> {{ errors.IdSucursal }}</p>
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <div class="flex flex-col items-end justify-center w-full">
                                <div class="relative w-full max-w-[140px]">
                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[9px] font-medium">Bs</span>
                                    <input 
                                        type="number" 
                                        v-model.number="nuevaFila.Precio" 
                                        step="0.01" 
                                        min="0" 
                                        class="no-arrows w-full pl-7 pr-3 py-1.5 border border-gray-300 rounded-lg text-right text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-semibold"
                                    >
                                </div>
                                <p v-if="errors.Precio" class="text-red-500 text-[11px] mt-1 font-medium text-right">{{ errors.Precio }}</p>
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

                    <tr v-for="precio in precios" :key="precio.IdPrecio" class="hover:bg-gray-50/80 transition-colors group">
                        <td class="px-4 py-3 text-xs text-gray-700 align-middle">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-store text-gray-400 group-hover:text-primary-500 transition-colors"></i>
                                <span class="font-medium text-gray-800">{{ nombreSucursal(precio.sucursal) }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <div v-if="editandoId !== precio.IdPrecio" class="text-right font-bold text-primary-600 text-xs pr-3">
                                {{ Number(precio.Precio).toFixed(2) }} Bs
                            </div>
                            <div v-else class="flex justify-end w-full">
                                <div class="relative w-full max-w-[140px]">
                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[9px] font-medium">Bs</span>
                                    <input 
                                        type="number" 
                                        v-model.number="editPrecioValue" 
                                        step="0.01" 
                                        min="0" 
                                        class="no-arrows w-full pl-7 pr-3 py-1.5 border border-gray-300 rounded-lg text-right text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-semibold"
                                    >
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center align-middle">
                            <div class="flex items-center justify-center gap-1.5">
                                <button v-if="editandoId !== precio.IdPrecio" @click="editarFila(precio)" class="text-blue-600 hover:text-blue-700 p-1.5 hover:bg-blue-50 rounded-md transition" title="Editar">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <button v-if="editandoId === precio.IdPrecio" @click="guardarEdicion(precio)" :disabled="loading" class="text-emerald-600 hover:text-emerald-700 p-1.5 hover:bg-emerald-50 rounded-md transition" title="Guardar">
                                    <i class="fas fa-save text-xs"></i>
                                </button>
                                <button v-if="editandoId === precio.IdPrecio" @click="cancelarEdicion" class="text-gray-400 hover:text-gray-500 p-1.5 hover:bg-gray-50 rounded-md transition" title="Cancelar">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                <button v-if="editandoId !== precio.IdPrecio" @click="confirmarEliminarFila(precio)" class="text-red-500 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-md transition" title="Eliminar">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="precios.length === 0 && !nuevaFila.editando">
                        <td colspan="3" class="px-4 py-12 text-center">
                            <div class="max-w-[240px] mx-auto">
                                <i class="fas fa-store text-gray-300 text-3xl mb-3 block"></i>
                                <p class="text-gray-400 text-xs font-medium">No hay precios configurados para este producto.</p>
                                <button @click="agregarFila" class="mt-2 text-primary-600 hover:text-primary-700 text-xs font-semibold underline decoration-2 underline-offset-4">+ Agregar precio por sucursal</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="md:hidden divide-y divide-gray-100">
                <div v-for="precio in precios" :key="precio.IdPrecio" class="p-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex justify-between items-start gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                <i class="fas fa-store text-primary-400 text-[10px]"></i>
                            </div>
                            <p class="text-xs font-semibold text-gray-800 line-clamp-2">{{ nombreSucursal(precio.sucursal) }}</p>
                        </div>
                        <div class="flex-shrink-0 text-right">
                            <span class="text-[10px] text-gray-400 block mb-0.5 uppercase tracking-wider font-bold">Precio</span>
                            <div v-if="editandoId !== precio.IdPrecio" class="font-bold text-primary-600 text-xs">
                                {{ Number(precio.Precio).toFixed(2) }} Bs
                            </div>
                            <div v-else class="relative">
                                <span class="absolute left-1.5 top-1/2 -translate-y-1/2 text-gray-400 text-[8px]">Bs</span>
                                <input 
                                    type="number" 
                                    v-model.number="editPrecioValue" 
                                    step="0.01" 
                                    min="0" 
                                    class="no-arrows w-24 pl-5 pr-1.5 py-1 border border-gray-300 rounded text-right text-[10px] focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-semibold"
                                >
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-3 pt-2.5 border-t border-gray-50">
                        <div v-if="editandoId !== precio.IdPrecio">
                            <button @click="editarFila(precio)" class="text-blue-600 hover:text-blue-700 text-xs font-medium flex items-center gap-1">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button @click="confirmarEliminarFila(precio)" class="text-red-500 hover:text-red-600 text-xs font-medium flex items-center gap-1 ml-2">
                                <i class="fas fa-trash-alt"></i> Eliminar
                            </button>
                        </div>
                        <div v-else>
                            <button @click="guardarEdicion(precio)" :disabled="loading" class="text-emerald-600 hover:text-emerald-700 text-xs font-medium flex items-center gap-1">
                                <i class="fas fa-save"></i> Guardar
                            </button>
                            <button @click="cancelarEdicion" class="text-gray-400 hover:text-gray-500 text-xs font-medium flex items-center gap-1 ml-2">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="nuevaFila.editando" class="p-4 bg-primary-50/30 space-y-3">
                    <div>
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Sucursal</label>
                        <div class="relative">
                            <input 
                                ref="sucursalInputRef"
                                type="text" 
                                v-model="busquedaSucursal" 
                                @focus="abrirSucursalDropdown"
                                @blur="cerrarSucursalDropdown"
                                @input="showSucursalDropdown = true"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all bg-white"
                                placeholder="Buscar sucursal..."
                            >
                            <button v-if="busquedaSucursal" @click="limpiarSeleccionSucursal" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                            
                            <div v-if="showSucursalDropdown && busquedaSucursal && sucursalesFiltradas.length > 0"
                                 ref="sucursalDropdownRef"
                                 class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-[160px] overflow-y-auto">
                                <div v-for="s in sucursalesFiltradas" :key="s.id" @click="seleccionarSucursal(s)" @mousedown.prevent
                                     class="px-3 py-2 hover:bg-gray-50 text-xs flex items-center gap-2 border-b border-gray-50 last:border-0">
                                    <i class="fas fa-store text-primary-400 text-[10px]"></i>
                                    <span class="text-gray-700 truncate font-medium">{{ s.nombre || s.Nombre }}</span>
                                    <span v-if="s.NumeroSucursal" class="text-[9px] text-gray-400 ml-auto">N° {{ s.NumeroSucursal }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-if="errors.IdSucursal" class="text-red-500 text-[11px] mt-1 font-medium"><i class="fas fa-exclamation-circle"></i> {{ errors.IdSucursal }}</p>
                    </div>

                    <div>
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Precio (Bs)</label>
                        <div class="relative">
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[9px] font-medium">Bs</span>
                            <input 
                                type="number" 
                                v-model.number="nuevaFila.Precio" 
                                step="0.01" 
                                min="0" 
                                class="no-arrows w-full pl-7 pr-3 py-1.5 border border-gray-300 rounded-lg text-right text-xs focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-semibold"
                            >
                        </div>
                        <p v-if="errors.Precio" class="text-red-500 text-[11px] mt-1 font-medium">{{ errors.Precio }}</p>
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

                <div v-if="precios.length === 0 && !nuevaFila.editando" class="p-8 text-center">
                    <i class="fas fa-store text-gray-300 text-2xl mb-2 block"></i>
                    <p class="text-gray-400 text-xs font-medium">No hay precios configurados</p>
                    <button @click="agregarFila" class="mt-1.5 text-primary-600 text-xs font-semibold underline">+ Agregar precio por sucursal</button>
                </div>
            </div>
        </div>

        <!-- Modal de Confirmación para Eliminar -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-[99999] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" @click="showDeleteModal = false"></div>
            <div class="bg-white rounded-xl shadow-xl overflow-hidden max-w-md w-full border border-gray-100 transform transition-all z-10 p-5 space-y-4">
                <div class="flex items-start gap-3.5">
                    <div class="p-2.5 bg-red-50 rounded-full text-red-600 flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-base"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-gray-900">¿Eliminar precio?</h3>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            ¿Está seguro de que desea eliminar el precio para la sucursal 
                            <span class="font-bold text-gray-700">"{{ precioAEliminar?.sucursal?.Nombre }}"</span>?
                            Esta acción eliminará el precio diferenciado y se usará el precio por defecto.
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