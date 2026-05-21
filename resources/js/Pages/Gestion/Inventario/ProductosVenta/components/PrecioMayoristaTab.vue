<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    productoId: Number,
    sucursales: Array,
    identificadores: Array,
    preciosIniciales: Array,
})

const emit = defineEmits(['update'])

const precios = ref(props.preciosIniciales || [])
const loading = ref(false)
const nuevaFila = ref({ IdSucursal: '', IdIdentificador: '', Precio: 0, editando: true })
const errors = ref({})
const editandoId = ref(null)
const editPrecioValue = ref(0)
const busquedaIdentificador = ref('')

const identificadoresFiltrados = computed(() => {
    if (!busquedaIdentificador.value) return props.identificadores
    const termino = busquedaIdentificador.value.toLowerCase()
    return props.identificadores.filter(i => 
        i.ci?.toString().includes(termino) || 
        i.nombre?.toLowerCase().includes(termino)
    )
})

const agregarFila = () => {
    nuevaFila.value = { IdSucursal: '', IdIdentificador: '', Precio: 0, editando: true }
    busquedaIdentificador.value = ''
}

const guardarNuevaFila = async () => {
    if (!nuevaFila.value.IdSucursal) {
        errors.value = { IdSucursal: 'Seleccione una sucursal' }
        return
    }
    if (!nuevaFila.value.IdIdentificador) {
        errors.value = { IdIdentificador: 'Seleccione un comisionista' }
        return
    }
    if (nuevaFila.value.Precio <= 0) {
        errors.value = { Precio: 'El precio debe ser mayor a 0' }
        return
    }
    
    loading.value = true
    errors.value = {}
    
    try {
        const response = await axios.post('/gestion/productos-venta/precio-mayorista', {
            IdProducto: props.productoId,
            IdSucursal: nuevaFila.value.IdSucursal,
            IdIdentificador: nuevaFila.value.IdIdentificador,
            Precio: nuevaFila.value.Precio,
        })
        
        if (response.data.success) {
            precios.value.push(response.data.precio)
            emit('update', [...precios.value])
            nuevaFila.value = { IdSucursal: '', IdIdentificador: '', Precio: 0, editando: false }
            busquedaIdentificador.value = ''
        }
    } catch (error) {
        errors.value = { general: error.response?.data?.message || 'Error al guardar' }
    } finally {
        loading.value = false
    }
}

const cancelarNuevaFila = () => {
    nuevaFila.value = { IdSucursal: '', IdIdentificador: '', Precio: 0, editando: false }
    busquedaIdentificador.value = ''
    errors.value = {}
}

const editarFila = (precio) => {
    editandoId.value = precio.IdPrecioMayorista
    editPrecioValue.value = precio.Precio
}

const guardarEdicion = async (precio) => {
    if (editPrecioValue.value <= 0) {
        alert('El precio debe ser mayor a 0')
        return
    }
    
    loading.value = true
    try {
        const response = await axios.put(`/gestion/productos-venta/precio-mayorista/${precio.IdPrecioMayorista}`, {
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
    if (!confirm(`¿Eliminar precio mayorista para ${precio.identificador?.Nombre}?`)) return
    
    try {
        const response = await axios.delete(`/gestion/productos-venta/precio-mayorista/${precio.IdPrecioMayorista}`)
        if (response.data.success) {
            precios.value = precios.value.filter(p => p.IdPrecioMayorista !== precio.IdPrecioMayorista)
            emit('update', precios.value)
        }
    } catch (error) {
        alert('Error al eliminar')
    }
}

const seleccionarIdentificador = (id, ci, nombre) => {
    nuevaFila.value.IdIdentificador = id
    busquedaIdentificador.value = `${ci} - ${nombre}`
}
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-chart-line text-guindo-500 text-sm"></i>
                <span class="text-xs font-medium text-gray-600">Precios especiales por comisionista</span>
            </div>
            <button v-if="!nuevaFila.editando" @click="agregarFila" class="bg-guindo-600 hover:bg-guindo-700 text-white px-3 py-1 rounded-md text-xs flex items-center gap-1 transition shadow-sm">
                <i class="fas fa-plus text-[9px]"></i> Nuevo precio
            </button>
        </div>

        <!-- Tabla sin altura máxima -->
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full">
                <thead class="bg-guindo-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-[11px] font-semibold text-guindo-700 uppercase tracking-wider">Sucursal</th>
                        <th class="px-4 py-2 text-left text-[11px] font-semibold text-guindo-700 uppercase tracking-wider">Comisionista</th>
                        <th class="px-4 py-2 text-right text-[11px] font-semibold text-guindo-700 uppercase tracking-wider">Precio (Bs)</th>
                        <th class="px-4 py-2 text-center text-[11px] font-semibold text-guindo-700 uppercase tracking-wider w-24">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <!-- Nueva fila -->
                    <tr v-if="nuevaFila.editando" class="bg-amber-50">
                        <td class="px-4 py-2">
                            <select v-model="nuevaFila.IdSucursal" class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-guindo-500 focus:border-guindo-500">
                                <option value="">-- Seleccione sucursal --</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="busquedaIdentificador" 
                                    class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-guindo-500 focus:border-guindo-500"
                                    placeholder="Buscar por CI/NIT o nombre..."
                                    @focus="busquedaIdentificador = ''"
                                >
                                <div v-if="busquedaIdentificador && identificadoresFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-48 overflow-y-auto">
                                    <div 
                                        v-for="item in identificadoresFiltrados" 
                                        :key="item.id" 
                                        @click="seleccionarIdentificador(item.id, item.ci, item.nombre)"
                                        class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-xs border-b last:border-b-0"
                                    >
                                        <span class="font-mono text-[10px]">{{ item.ci }}</span> - {{ item.nombre }}
                                    </div>
                                </div>
                            </div>
                            <p v-if="errors.IdIdentificador" class="text-red-500 text-[9px] mt-1">{{ errors.IdIdentificador }}</p>
                        </td>
                        <td class="px-4 py-2">
                            <div class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[9px]">Bs</span>
                                <input type="number" v-model.number="nuevaFila.Precio" step="0.01" min="0" class="w-full pl-7 pr-2 py-1 border border-gray-300 rounded-md text-right text-xs focus:ring-guindo-500 focus:border-guindo-500">
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
                    <tr v-for="precio in precios" :key="precio.IdPrecioMayorista" class="hover:bg-gray-50 transition">
                        <td class="px-4 py-2 text-sm text-gray-700">
                            <i class="fas fa-store text-guindo-400 text-[10px] mr-2"></i>
                            {{ precio.sucursal?.Nombre || '-' }}
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-700">
                            <i class="fas fa-user text-guindo-400 text-[10px] mr-2"></i>
                            {{ precio.identificador?.CI_NIT }} - {{ precio.identificador?.Nombre }}
                        </td>
                        <td class="px-4 py-2">
                            <div v-if="editandoId !== precio.IdPrecioMayorista" class="text-right font-semibold text-guindo-600 text-sm">
                                {{ Number(precio.Precio).toFixed(2) }} Bs
                            </div>
                            <div v-else class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[9px]">Bs</span>
                                <input type="number" v-model.number="editPrecioValue" step="0.01" min="0" class="w-full pl-7 pr-2 py-1 border border-gray-300 rounded-md text-right text-sm focus:ring-guindo-500 focus:border-guindo-500">
                            </div>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <div v-if="editandoId !== precio.IdPrecioMayorista">
                                <button @click="editarFila(precio)" class="text-guindo-600 hover:text-guindo-800 mr-2 transition" title="Editar">
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
                        <td colspan="4" class="px-4 py-8 text-center">
                            <i class="fas fa-chart-line text-gray-300 text-2xl mb-2 block"></i>
                            <p class="text-gray-400 text-xs">No hay precios mayoristas configurados</p>
                            <button @click="agregarFila" class="mt-2 text-guindo-600 hover:text-guindo-700 text-xs font-medium">+ Agregar precio</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>