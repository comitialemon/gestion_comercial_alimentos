<script setup>
import { ref } from 'vue'
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

const agregarFila = () => {
    nuevaFila.value = { IdSucursal: '', Precio: 0, editando: true }
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
        const response = await axios.post('/gestion/productos-venta/precio-sucursal', {
            IdProducto: props.productoId,
            IdSucursal: nuevaFila.value.IdSucursal,
            Precio: nuevaFila.value.Precio,
        })
        
        if (response.data.success) {
            precios.value.push(response.data.precio)
            emit('update', [...precios.value])
            nuevaFila.value = { IdSucursal: '', Precio: 0, editando: false }
        }
    } catch (error) {
        errors.value = { general: error.response?.data?.message || 'Error al guardar' }
    } finally {
        loading.value = false
    }
}

const cancelarNuevaFila = () => {
    nuevaFila.value = { IdSucursal: '', Precio: 0, editando: false }
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
        const response = await axios.put(`/gestion/productos-venta/precio-sucursal/${precio.IdPrecio}`, {
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

const eliminarFila = async (precio) => {
    if (!confirm(`¿Eliminar precio para la sucursal "${precio.sucursal?.Nombre}"?`)) return
    
    try {
        const response = await axios.delete(`/gestion/productos-venta/precio-sucursal/${precio.IdPrecio}`)
        if (response.data.success) {
            precios.value = precios.value.filter(p => p.IdPrecio !== precio.IdPrecio)
            emit('update', precios.value)
        }
    } catch (error) {
        alert('Error al eliminar')
    }
}

const nombreSucursal = (sucursal) => {
    if (!sucursal) return '-'
    return `${sucursal.Nombre} ${sucursal.NumeroSucursal ? `(N° ${sucursal.NumeroSucursal})` : ''}`
}
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-store text-primary-500 text-sm"></i>
                <span class="text-xs font-medium text-gray-600">Precios diferenciados por sucursal</span>
            </div>
            <button v-if="!nuevaFila.editando" @click="agregarFila" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1 rounded-md text-xs flex items-center gap-1 transition shadow-sm">
                <i class="fas fa-plus text-[9px]"></i> Nuevo precio
            </button>
        </div>

        <!-- Tabla sin altura máxima -->
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full">
                <thead class="bg-primary-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider">Sucursal</th>
                        <th class="px-4 py-2 text-right text-[11px] font-semibold text-primary-700 uppercase tracking-wider">Precio (Bs)</th>
                        <th class="px-4 py-2 text-center text-[11px] font-semibold text-primary-700 uppercase tracking-wider w-24">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <!-- Nueva fila -->
                    <tr v-if="nuevaFila.editando" class="bg-amber-50">
                        <td class="px-4 py-2">
                            <select v-model="nuevaFila.IdSucursal" class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-primary-500 focus:border-primary-500">
                                <option value="">-- Seleccione una sucursal --</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">
                                    {{ s.nombre }} {{ s.NumeroSucursal ? `(N° ${s.NumeroSucursal})` : '' }}
                                </option>
                            </select>
                            <p v-if="errors.IdSucursal" class="text-red-500 text-[9px] mt-1">{{ errors.IdSucursal }}</p>
                        </td>
                        <td class="px-4 py-2">
                            <div class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[9px]">Bs</span>
                                <input 
                                    type="number" 
                                    v-model.number="nuevaFila.Precio" 
                                    step="0.01" 
                                    min="0" 
                                    class="w-full pl-7 pr-2 py-1 border border-gray-300 rounded-md text-right text-xs focus:ring-primary-500 focus:border-primary-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                >
                            </div>
                            <p v-if="errors.Precio" class="text-red-500 text-[9px] mt-1">{{ errors.Precio }}</p>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <button @click="guardarNuevaFila" :disabled="loading" class="text-green-600 hover:text-green-800 mr-2 transition" title="Guardar">
                                <i class="fas fa-save text-sm"></i>
                            </button>
                            <button @click="cancelarNuevaFila" class="text-gray-400 hover:text-gray-600 transition" title="Cancelar">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </td>
                    </tr>

                    <!-- Filas existentes -->
                    <tr v-for="precio in precios" :key="precio.IdPrecio" class="hover:bg-gray-50 transition">
                        <td class="px-4 py-2 text-sm text-gray-700">
                            <i class="fas fa-store text-primary-400 text-[10px] mr-2"></i>
                            {{ nombreSucursal(precio.sucursal) }}
                        </td>
                        <td class="px-4 py-2">
                            <div v-if="editandoId !== precio.IdPrecio" class="text-right font-semibold text-primary-600 text-sm">
                                {{ Number(precio.Precio).toFixed(2) }} Bs
                            </div>
                            <div v-else class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[9px]">Bs</span>
                                <input 
                                    type="number" 
                                    v-model.number="editPrecioValue" 
                                    step="0.01" 
                                    min="0" 
                                    class="w-full pl-7 pr-2 py-1 border border-gray-300 rounded-md text-right text-sm focus:ring-primary-500 focus:border-primary-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                >
                            </div>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <div v-if="editandoId !== precio.IdPrecio">
                                <button @click="editarFila(precio)" class="text-primary-600 hover:text-primary-800 mr-2 transition" title="Editar">
                                    <i class="fas fa-edit text-sm"></i>
                                </button>
                                <button @click="eliminarFila(precio)" class="text-red-500 hover:text-red-700 transition" title="Eliminar">
                                    <i class="fas fa-trash-alt text-sm"></i>
                                </button>
                            </div>
                            <div v-else>
                                <button @click="guardarEdicion(precio)" :disabled="loading" class="text-green-600 hover:text-green-800 mr-2 transition" title="Guardar">
                                    <i class="fas fa-save text-sm"></i>
                                </button>
                                <button @click="cancelarEdicion" class="text-gray-400 hover:text-gray-600 transition" title="Cancelar">
                                    <i class="fas fa-times text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="precios.length === 0 && !nuevaFila.editando">
                        <td colspan="3" class="px-4 py-8 text-center">
                            <i class="fas fa-store text-gray-300 text-2xl mb-2 block"></i>
                            <p class="text-gray-400 text-xs">No hay precios configurados</p>
                            <button @click="agregarFila" class="mt-2 text-primary-600 hover:text-primary-700 text-xs font-medium">+ Agregar precio</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>