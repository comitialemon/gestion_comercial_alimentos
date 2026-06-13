<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: {
        type: Array,
        default: () => []
    },
    movimientos: {
        type: Object,
        default: null
    },
    productoSeleccionado: {
        type: Object,
        default: null
    },
    filtros: {
        type: Object,
        default: () => ({})
    }
})

// Estado
const productoId = ref(props.filtros?.producto_id || '')
const productoBusqueda = ref('')
const mostrarProductos = ref(false)
const loading = ref(false)

// Computados
const productosDisponibles = computed(() => {
    if (!props.productos) return []
    if (!productoBusqueda.value) return props.productos
    
    const termino = productoBusqueda.value.toLowerCase()
    return props.productos.filter(p => 
        p.Codigo?.toLowerCase().includes(termino) ||
        p.Descripcion?.toLowerCase().includes(termino)
    )
})

const productoNombre = computed(() => {
    if (!productoId.value) return ''
    const prod = props.productos?.find(p => p.id == productoId.value)
    return prod ? `${prod.Codigo} - ${prod.Descripcion}` : ''
})

// Acciones
const seleccionarProducto = (producto) => {
    productoId.value = producto.id
    productoBusqueda.value = `${producto.Codigo} - ${producto.Descripcion}`
    mostrarProductos.value = false
}

const limpiarProducto = () => {
    productoId.value = ''
    productoBusqueda.value = ''
    mostrarProductos.value = false
}

const buscar = () => {
    if (!productoId.value) {
        alert('Seleccione un producto')
        return
    }
    
    loading.value = true
    router.get('/gestion/reportes/control-interno/inventario-detalle', {
        producto_id: productoId.value
    }, {
        preserveState: true,
        onFinish: () => { loading.value = false }
    })
}

const limpiarFiltros = () => {
    productoId.value = ''
    productoBusqueda.value = ''
    router.get('/gestion/reportes/control-interno/inventario-detalle', {}, {
        preserveState: true
    })
}

// Clases para estado
const getTipoClase = (tipo) => {
    if (tipo === 'D') return 'text-emerald-600 bg-emerald-50'
    if (tipo === 'H') return 'text-red-600 bg-red-50'
    return 'text-gray-600 bg-gray-50'
}

const getTipoTexto = (tipo) => {
    if (tipo === 'D') return 'ENTRADA'
    if (tipo === 'H') return 'SALIDA'
    return tipo
}

// Cerrar sugerencias
const handleClickOutside = (event) => {
    const container = document.querySelector('.producto-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarProductos.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    if (props.productoSeleccionado) {
        productoBusqueda.value = `${props.productoSeleccionado.Codigo} - ${props.productoSeleccionado.Descripcion}`
    }
})

// Cleanup
const cleanup = () => {
    document.removeEventListener('click', handleClickOutside)
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

// Usamos onUnmounted en lugar
import { onUnmounted } from 'vue'
onUnmounted(cleanup)
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-4 sm:py-6 px-3 sm:px-4 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center"
                             :style="{ backgroundColor: `var(--color-primary-100)`, color: `var(--color-primary-600)` }">
                            <i class="fas fa-boxes text-base sm:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-gray-800">Inventario Detallado</h1>
                            <p class="text-xs text-gray-500 hidden sm:block">Movimientos de inventario con saldo acumulado por producto</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 sm:hidden">Movimientos de inventario con saldo acumulado por producto</p>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Selector de Producto con autocompletado -->
                        <div class="producto-autocomplete md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-box mr-1" :style="{ color: `var(--color-primary-600)` }"></i>
                                Producto <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="productoBusqueda"
                                    @focus="mostrarProductos = true"
                                    @input="mostrarProductos = true"
                                    class="w-full border rounded-lg px-3 py-2 text-sm pr-8 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)` }"
                                    placeholder="Escriba para buscar por código o nombre..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="productoBusqueda"
                                    @click="limpiarProducto"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <!-- Lista de sugerencias -->
                                <div v-if="mostrarProductos && productosDisponibles.length > 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="prod in productosDisponibles" 
                                        :key="prod.id"
                                        @click="seleccionarProducto(prod)"
                                        class="px-3 py-2 cursor-pointer border-b last:border-b-0 transition flex justify-between items-center"
                                        :class="productoId == prod.id ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                        :style="productoId == prod.id ? { backgroundColor: `var(--color-primary-50)` } : {}"
                                    >
                                        <div>
                                            <span class="font-mono text-sm font-medium">{{ prod.Codigo }}</span>
                                            <span class="text-xs text-gray-500 ml-2">{{ prod.Descripcion }}</span>
                                        </div>
                                        <i v-if="productoId == prod.id" class="fas fa-check-circle text-xs" :style="{ color: `var(--color-primary-600)` }"></i>
                                    </div>
                                </div>
                                
                                <!-- Mensaje sin resultados -->
                                <div v-if="mostrarProductos && productoBusqueda && productosDisponibles.length === 0" 
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg p-3 text-center text-gray-500 text-sm">
                                    No se encontraron productos con "{{ productoBusqueda }}"
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex items-end gap-2">
                            <button 
                                @click="buscar" 
                                :disabled="!productoId || loading"
                                class="px-4 py-2 text-white rounded-lg text-sm flex items-center gap-2 disabled:opacity-50"
                                :style="{ backgroundColor: `var(--color-primary-600)` }"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-search"></i>
                                {{ loading ? 'Buscando...' : 'Buscar' }}
                            </button>
                            <button 
                                @click="limpiarFiltros" 
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm hover:bg-gray-100 flex items-center gap-2"
                            >
                                <i class="fas fa-eraser"></i>
                                Limpiar
                            </button>
                        </div>
                    </div>

                    <!-- Producto seleccionado -->
                    <div v-if="productoSeleccionado" class="mt-4 p-3 rounded-lg"
                         :style="{ backgroundColor: `var(--color-primary-50)` }">
                        <div class="flex flex-wrap gap-4 text-sm">
                            <div><span class="font-medium text-gray-500">Código:</span> {{ productoSeleccionado.Codigo }}</div>
                            <div><span class="font-medium text-gray-500">Producto:</span> {{ productoSeleccionado.Descripcion }}</div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de movimientos -->
                <div v-if="movimientos && movimientos.data && movimientos.data.length > 0" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">Tipo</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">Documento</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">Glosa</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-600 uppercase">Almacén</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-600 uppercase">Unidades</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-600 uppercase">Precio (Bs)</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-600 uppercase">Total (Bs)</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-600 uppercase">Saldo Acumulado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="mov in movimientos.data" :key="mov.IdInventarioPropiamente" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-[11px] text-gray-600 whitespace-nowrap">{{ mov.fecha_formateada || '-' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="px-1.5 py-0.5 text-[9px] rounded-full font-medium" :class="getTipoClase(mov.D_H)">
                                            {{ getTipoTexto(mov.D_H) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-[11px] text-gray-600">{{ mov.IdDocumento || '-' }}</td>
                                    <td class="px-3 py-2 text-[11px] text-gray-600 max-w-xs truncate" :title="mov.Glosa">{{ mov.Glosa || '-' }}</td>
                                    <td class="px-3 py-2 text-[11px] text-gray-600">{{ mov.almacen_nombre || '-' }}</td>
                                    <td class="px-3 py-2 text-[11px] text-right">
                                        <span :class="mov.D_H == 'D' ? 'text-emerald-600' : 'text-red-600'">
                                            {{ mov.UnidadesSigno > 0 ? '+' : '' }}{{ Number(mov.UnidadesSigno).toFixed(2) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-[11px] text-right text-gray-600">{{ Number(mov.Bolivianos / mov.Unidades).toFixed(2) || '0.00' }}</td>
                                    <td class="px-3 py-2 text-[11px] text-right font-medium">{{ Number(mov.Bolivianos).toFixed(2) }}</td>
                                    <td class="px-3 py-2 text-[11px] text-right font-bold" :class="mov.SaldoAcumulado > 0 ? 'text-emerald-600' : 'text-red-600'">
                                        {{ Number(mov.SaldoAcumulado).toFixed(2) }}
                                    </td>
                                </tr>
                                <tr v-if="movimientos.data.length === 0">
                                    <td colspan="9" class="px-3 py-8 text-center text-gray-400 text-[11px]">
                                        <i class="fas fa-box-open text-xl mb-1 block"></i>
                                        No hay movimientos para este producto
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="movimientos.links && movimientos.links.length > 1" class="px-3 py-2 border-t border-gray-200">
                        <div class="flex justify-between items-center flex-wrap gap-2">
                            <div class="text-[9px] text-gray-500">
                                Mostrando {{ movimientos.from || 0 }} a {{ movimientos.to || 0 }} de {{ movimientos.total || 0 }}
                            </div>
                            <div class="flex gap-1 flex-wrap">
                                <Link 
                                    v-for="link in movimientos.links" 
                                    :key="link.label" 
                                    :href="link.url || '#'" 
                                    class="px-2 py-0.5 rounded border text-[9px] transition"
                                    :class="{ 
                                        'bg-primary-600 text-white border-primary-600': link.active, 
                                        'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 
                                        'opacity-50 cursor-not-allowed': !link.url 
                                    }" 
                                    v-html="link.label" 
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mensaje cuando no hay resultados -->
                <div v-else-if="movimientos && movimientos.data && movimientos.data.length === 0" class="bg-white rounded-xl shadow-sm p-8 text-center">
                    <i class="fas fa-box-open text-gray-300 text-4xl mb-3 block"></i>
                    <p class="text-gray-500">No se encontraron movimientos para este producto</p>
                </div>

                <!-- Mensaje cuando no hay producto seleccionado -->
                <div v-else class="bg-white rounded-xl shadow-sm p-8 text-center">
                    <i class="fas fa-search text-gray-300 text-4xl mb-3 block"></i>
                    <p class="text-gray-500">Seleccione un producto para ver sus movimientos</p>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 rounded-lg text-xs"
                     :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-700)` }">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Información:</strong> El reporte muestra todos los movimientos de inventario del producto seleccionado, 
                    incluyendo entradas (D) y salidas (H), con el saldo acumulado calculado en tiempo real.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:focus {
    --tw-ring-color: var(--color-primary-500);
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

.transition {
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.max-w-xs {
    max-width: 200px;
}
</style>