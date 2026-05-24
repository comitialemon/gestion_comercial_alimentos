<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
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

// 🔥 BÚSQUEDA DE SUCURSAL
const busquedaSucursal = ref('')
const showSucursalDropdown = ref(false)
const sucursalDropdownRef = ref(null)
const sucursalInputRef = ref(null)

// 🔥 BÚSQUEDA DE COMISIONISTAS
const busquedaIdentificador = ref('')
const showDropdown = ref(false)
const dropdownRef = ref(null)
const inputRef = ref(null)

// 🔥 Sucursales filtradas
const sucursalesFiltradas = computed(() => {
    if (!props.sucursales || !Array.isArray(props.sucursales)) {
        return []
    }
    
    if (!busquedaSucursal.value || busquedaSucursal.value.trim() === '') {
        return props.sucursales
    }
    
    const termino = busquedaSucursal.value.toLowerCase().trim()
    return props.sucursales.filter(s => {
        const nombre = String(s.nombre || '').toLowerCase()
        return nombre.includes(termino)
    })
})

// 🔥 Identificadores filtrados
const identificadoresFiltrados = computed(() => {
    if (!props.identificadores || !Array.isArray(props.identificadores)) {
        return []
    }
    
    if (!busquedaIdentificador.value || busquedaIdentificador.value.trim() === '') {
        return []
    }
    
    const termino = busquedaIdentificador.value.toLowerCase().trim()
    return props.identificadores.filter(i => {
        const ci = String(i.ci || i.CI_NIT || '').toLowerCase()
        const nombre = String(i.nombre || i.Nombre || '').toLowerCase()
        return ci.includes(termino) || nombre.includes(termino)
    })
})

// 🔥 Abrir/cerrar dropdown de sucursal
const abrirSucursalDropdown = () => {
    showSucursalDropdown.value = true
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

// 🔥 Seleccionar sucursal
const seleccionarSucursal = (sucursal) => {
    nuevaFila.value.IdSucursal = sucursal.id
    busquedaSucursal.value = sucursal.nombre
    showSucursalDropdown.value = false
    errors.value.IdSucursal = ''
}

// 🔥 Limpiar selección de sucursal
const limpiarSeleccionSucursal = () => {
    nuevaFila.value.IdSucursal = ''
    busquedaSucursal.value = ''
    showSucursalDropdown.value = false
}

// 🔥 Abrir/cerrar dropdown de comisionista
const abrirDropdown = () => {
    if (busquedaIdentificador.value) {
        showDropdown.value = true
    }
}

const cerrarDropdown = () => {
    setTimeout(() => {
        showDropdown.value = false
    }, 200)
}

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target) && 
        inputRef.value && !inputRef.value.contains(event.target)) {
        showDropdown.value = false
    }
}

// 🔥 Seleccionar identificador
const seleccionarIdentificador = (item) => {
    const id = item.id || item.IdIdentificador
    const ci = item.ci || item.CI_NIT
    const nombre = item.nombre || item.Nombre
    
    nuevaFila.value.IdIdentificador = id
    busquedaIdentificador.value = `${ci} - ${nombre}`
    showDropdown.value = false
    errors.value.IdIdentificador = ''
}

// 🔥 Limpiar selección de comisionista
const limpiarSeleccion = () => {
    nuevaFila.value.IdIdentificador = ''
    busquedaIdentificador.value = ''
    showDropdown.value = false
}

// Agregar eventos globales
if (typeof window !== 'undefined') {
    window.addEventListener('click', handleClickOutside)
    window.addEventListener('click', handleSucursalClickOutside)
}

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('click', handleClickOutside)
        window.removeEventListener('click', handleSucursalClickOutside)
    }
})

const agregarFila = () => {
    nuevaFila.value = { IdSucursal: '', IdIdentificador: '', Precio: 0, editando: true }
    busquedaSucursal.value = ''
    busquedaIdentificador.value = ''
    showDropdown.value = false
    showSucursalDropdown.value = false
    errors.value = {}
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
            busquedaSucursal.value = ''
            busquedaIdentificador.value = ''
            showDropdown.value = false
            showSucursalDropdown.value = false
        }
    } catch (error) {
        errors.value = { general: error.response?.data?.message || 'Error al guardar' }
    } finally {
        loading.value = false
    }
}

const cancelarNuevaFila = () => {
    nuevaFila.value = { IdSucursal: '', IdIdentificador: '', Precio: 0, editando: false }
    busquedaSucursal.value = ''
    busquedaIdentificador.value = ''
    showDropdown.value = false
    showSucursalDropdown.value = false
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

        <!-- Contenedor con altura mínima -->
        <div class="overflow-x-auto rounded-lg border border-gray-200" style="min-height: 300px;">
            <table class="min-w-full">
                <thead class="bg-guindo-50 sticky top-0">
                    <tr>
                        <!-- Sucursal: ancho 220px -->
                        <th class="px-3 py-2 text-left text-[11px] font-semibold text-guindo-700 uppercase tracking-wider w-[220px]">Sucursal</th>
                        <!-- Comisionista: ancho flexible -->
                        <th class="px-3 py-2 text-left text-[11px] font-semibold text-guindo-700 uppercase tracking-wider">Comisionista</th>
                        <!-- Precio: ancho 100px -->
                        <th class="px-3 py-2 text-right text-[11px] font-semibold text-guindo-700 uppercase tracking-wider w-[100px]">Precio (Bs)</th>
                        <!-- Acciones: ancho 80px -->
                        <th class="px-3 py-2 text-center text-[11px] font-semibold text-guindo-700 uppercase tracking-wider w-[80px]">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <!-- Nueva fila -->
                    <tr v-if="nuevaFila.editando" class="bg-amber-50">
                        <!-- Sucursal con buscador -->
                        <td class="px-3 py-2">
                            <div class="relative">
                                <input 
                                    ref="sucursalInputRef"
                                    type="text" 
                                    v-model="busquedaSucursal" 
                                    @focus="abrirSucursalDropdown"
                                    @blur="cerrarSucursalDropdown"
                                    @input="abrirSucursalDropdown"
                                    class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-guindo-500 focus:border-guindo-500"
                                    placeholder="Buscar sucursal..."
                                >
                                <button 
                                    v-if="busquedaSucursal"
                                    @click="limpiarSeleccionSucursal"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[9px]"></i>
                                </button>
                                
                                <!-- Dropdown de sucursales -->
                                <div 
                                    v-if="showSucursalDropdown && sucursalesFiltradas.length > 0"
                                    ref="sucursalDropdownRef"
                                    class="absolute z-[9999] mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-[200px] overflow-y-auto"
                                >
                                    <div
                                        v-for="s in sucursalesFiltradas"
                                        :key="s.id"
                                        @click="seleccionarSucursal(s)"
                                        @mousedown.prevent
                                        class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-xs border-b last:border-b-0"
                                    >
                                        {{ s.nombre }}
                                    </div>
                                </div>
                                
                                <!-- Mensaje sin resultados -->
                                <div 
                                    v-if="showSucursalDropdown && busquedaSucursal && sucursalesFiltradas.length === 0"
                                    class="absolute z-[9999] mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-xs"
                                >
                                    No se encontraron sucursales
                                </div>
                            </div>
                            <p v-if="errors.IdSucursal" class="text-red-500 text-[9px] mt-1">{{ errors.IdSucursal }}</p>
                        </td>
                        <!-- Comisionista con buscador -->
                        <td class="px-3 py-2">
                            <div class="relative">
                                <input 
                                    ref="inputRef"
                                    type="text" 
                                    v-model="busquedaIdentificador" 
                                    @focus="abrirDropdown"
                                    @blur="cerrarDropdown"
                                    @input="abrirDropdown"
                                    class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-guindo-500 focus:border-guindo-500"
                                    placeholder="Buscar por CI o nombre..."
                                >
                                <button 
                                    v-if="busquedaIdentificador"
                                    @click="limpiarSeleccion"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[9px]"></i>
                                </button>
                                
                                <!-- Dropdown de resultados -->
                                <div 
                                    v-if="showDropdown && identificadoresFiltrados.length > 0"
                                    ref="dropdownRef"
                                    class="absolute z-[9999] mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-[200px] overflow-y-auto"
                                >
                                    <div
                                        v-for="item in identificadoresFiltrados"
                                        :key="item.id || item.IdIdentificador"
                                        @click="seleccionarIdentificador(item)"
                                        @mousedown.prevent
                                        class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-xs border-b last:border-b-0 flex items-center gap-2"
                                    >
                                        <span class="font-mono text-[10px] bg-gray-100 px-1.5 py-0.5 rounded text-guindo-600">{{ item.ci || item.CI_NIT }}</span>
                                        <span class="text-gray-700 truncate">{{ item.nombre || item.Nombre }}</span>
                                    </div>
                                </div>
                                
                                <!-- Mensaje sin resultados -->
                                <div 
                                    v-if="showDropdown && busquedaIdentificador && identificadoresFiltrados.length === 0"
                                    class="absolute z-[9999] mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-xs"
                                >
                                    No se encontraron comisionistas
                                </div>
                            </div>
                            <p v-if="errors.IdIdentificador" class="text-red-500 text-[9px] mt-1">{{ errors.IdIdentificador }}</p>
                        </td>
                        <td class="px-3 py-2">
                            <div class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[9px]">Bs</span>
                                <input 
                                    type="number" 
                                    v-model.number="nuevaFila.Precio" 
                                    step="0.01" 
                                    min="0" 
                                    class="w-full pl-7 pr-2 py-1 border border-gray-300 rounded-md text-right text-xs focus:ring-guindo-500 focus:border-guindo-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                >
                            </div>
                            <p v-if="errors.Precio" class="text-red-500 text-[9px] mt-1">{{ errors.Precio }}</p>
                        </td>
                        <td class="px-3 py-2 text-center">
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
                        <td class="px-3 py-2 text-xs text-gray-700">
                            <i class="fas fa-store text-guindo-400 text-[10px] mr-1"></i>
                            {{ precio.sucursal?.Nombre || '-' }}
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-700">
                            <i class="fas fa-user text-guindo-400 text-[10px] mr-1"></i>
                            <span class="font-mono text-[10px] bg-gray-100 px-1.5 py-0.5 rounded mr-1">{{ precio.identificador?.CI_NIT }}</span>
                            <span class="truncate">{{ precio.identificador?.Nombre }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <div v-if="editandoId !== precio.IdPrecioMayorista" class="text-right font-semibold text-guindo-600 text-xs">
                                {{ Number(precio.Precio).toFixed(2) }}
                            </div>
                            <div v-else class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[9px]">Bs</span>
                                <input 
                                    type="number" 
                                    v-model.number="editPrecioValue" 
                                    step="0.01" 
                                    min="0" 
                                    class="w-full pl-7 pr-2 py-1 border border-gray-300 rounded-md text-right text-xs focus:ring-guindo-500 focus:border-guindo-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                >
                            </div>
                        </td>
                        <td class="px-3 py-2 text-center">
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
                        <td colspan="4" class="px-3 py-8 text-center">
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