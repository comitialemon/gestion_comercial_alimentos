<script setup>
import { ref, computed, onMounted, inject, onUnmounted, watch } from 'vue'
import axios from 'axios'

const toast = inject('toast')

const props = defineProps({
    productos: {
        type: Array,
        default: () => []
    },
    fechaId: Number,
    fechaStr: String,
    cantidadRequerida: {
        type: Number,
        default: 0
    },
    idCabecera: Number,
    esBorrador: Boolean,
})

const emit = defineEmits(['continuar', 'volver'])

const loading = ref(false)
const guardandoProgreso = ref(false)
const productosContados = ref([])
let debounceTimer = null

// 🔥 Obtener colores del tema del cliente (usando variables CSS)
const primaryColor = inject('primaryColor', 'var(--color-primary)')
const primaryLight = inject('primaryLight', 'var(--color-primary-50)')

const inicializarProductos = () => {
    if (props.productos && Array.isArray(props.productos) && props.productos.length > 0) {
        productosContados.value = props.productos.map(p => {
            let cantidadGuardada = null
            if (p.cantidad_contada !== undefined && p.cantidad_contada !== null && p.cantidad_contada !== '') {
                const parsed = parseFloat(p.cantidad_contada)
                cantidadGuardada = !isNaN(parsed) ? parsed : null
            }
            
            return {
                IdProducto: p.IdProducto,
                Codigo: p.Codigo || '-',
                Descripcion: p.Descripcion || 'Sin nombre',
                cantidad: cantidadGuardada,
                saldo_sistema: p.saldo_sistema || 0,
            }
        })
    } else {
        productosContados.value = []
    }
}

const contados = computed(() => {
    if (!productosContados.value || productosContados.value.length === 0) return 0
    return productosContados.value.filter(p => {
        if (p.cantidad === null || p.cantidad === undefined || p.cantidad === '') return false
        return !isNaN(parseFloat(p.cantidad))
    }).length
})

const todosContados = computed(() => {
    if (!productosContados.value || productosContados.value.length === 0) return false
    return productosContados.value.every(p => {
        if (p.cantidad === null || p.cantidad === undefined || p.cantidad === '') return false
        return !isNaN(parseFloat(p.cantidad))
    })
})

const totalContado = computed(() => {
    if (!productosContados.value || productosContados.value.length === 0) return 0
    return productosContados.value.reduce((sum, p) => {
        const val = parseFloat(p.cantidad)
        return sum + (isNaN(val) ? 0 : val)
    }, 0)
})

const productosConDiferencia = computed(() => {
    if (!productosContados.value || productosContados.value.length === 0) return []
    return productosContados.value.filter(p => {
        if (p.cantidad === null || p.cantidad === undefined || p.cantidad === '') return false
        const diff = parseFloat(p.cantidad || 0) - parseFloat(p.saldo_sistema || 0)
        return diff !== 0
    })
})

const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toFixed(2)
}

const guardarProgreso = async () => {
    if (!props.idCabecera) return
    if (guardandoProgreso.value) return
    if (!productosContados.value || productosContados.value.length === 0) return
    
    guardandoProgreso.value = true
    try {
        const data = {
            productos: productosContados.value.map(p => ({
                IdProducto: p.IdProducto,
                CantidadContada: p.cantidad !== null && p.cantidad !== '' ? parseFloat(p.cantidad) : null,
                CantidadSistema: p.saldo_sistema || 0,
            })),
            IdFecha: props.fechaId,
            CantidadTotal: props.cantidadRequerida,
            id_cabecera: props.idCabecera,
        }

        await axios.post('/gestion/inventario/inventario-fisico-diario/guardar-progreso', data)
    } catch (error) {
        console.error('Error guardando progreso:', error)
    } finally {
        guardandoProgreso.value = false
    }
}

const onCantidadChangeInput = (event, producto) => {
    const value = event.target.value
    const cleaned = value.replace(/[^0-9.]/g, '')
    
    if (cleaned === '' || cleaned === '.') {
        producto.cantidad = null
    } else {
        const num = parseFloat(cleaned)
        if (!isNaN(num) && num >= 0) {
            producto.cantidad = num
        } else {
            producto.cantidad = null
        }
    }
    
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        guardarProgreso()
    }, 1000)
}

const onCantidadBlur = (producto) => {
    if (producto.cantidad === null || producto.cantidad === undefined || producto.cantidad === '') {
        producto.cantidad = null
    } else {
        const num = parseFloat(producto.cantidad)
        if (isNaN(num) || num < 0) {
            producto.cantidad = null
        }
    }
}

const guardarYContinuar = async () => {
    if (!todosContados.value) {
        const faltantes = productosContados.value.filter(p => 
            p.cantidad === null || p.cantidad === undefined || p.cantidad === ''
        ).length
        toast.warning(`⚠️ Faltan ${faltantes} productos por contar`)
        return
    }

    loading.value = true

    try {
        const data = {
            productos: productosContados.value.map(p => ({
                IdProducto: p.IdProducto,
                CantidadContada: p.cantidad !== null && p.cantidad !== '' ? parseFloat(p.cantidad) : null,
                CantidadSistema: p.saldo_sistema || 0,
            })),
            IdFecha: props.fechaId,
            CantidadTotal: props.cantidadRequerida,
        }

        const response = await axios.post('/gestion/inventario/inventario-fisico-diario/guardar-mini-inventario', data)
        
        if (response.data.success) {
            toast.success('✅ Inventario físico completado correctamente')
            loading.value = false
            emit('continuar')
        } else {
            toast.error(response.data.message || 'Error al guardar')
            loading.value = false
        }
    } catch (error) {
        console.error('Error:', error)
        toast.error(error.response?.data?.message || 'Error al guardar el inventario')
        loading.value = false
    }
}

const volver = () => {
    if (contados.value > 0 && !todosContados.value) {
        if (!confirm('⚠️ Tienes un progreso sin guardar. ¿Estás seguro de salir?')) {
            return
        }
    }
    emit('volver')
}

onMounted(() => {
    inicializarProductos()
})

watch(() => props.productos, () => {
    inicializarProductos()
}, { deep: true, immediate: true })

onUnmounted(() => {
    clearTimeout(debounceTimer)
})
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3 pb-2 border-b border-gray-200">
            <div>
                <h2 class="text-sm sm:text-base font-bold text-gray-800 flex items-center gap-1.5">
                    <i class="fas fa-clipboard-list" :style="{ color: primaryColor }"></i>
                    Inventario Físico Rápido
                </h2>
                <p class="text-[10px] sm:text-xs text-gray-500 mt-0.5">
                    📅 <strong>Fecha Liquidación:</strong> {{ fechaStr }}
                    <span v-if="esBorrador" class="text-yellow-600 ml-2">
                        <i class="fas fa-pen mr-1"></i> (Progreso guardado)
                    </span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[10px] sm:text-xs text-gray-500">
                    <span class="hidden xs:inline">Progreso: </span>
                    <strong>{{ contados }}</strong> / {{ cantidadRequerida }}
                </span>
                <div class="w-16 sm:w-24 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                        class="h-full transition-all duration-300"
                        :style="{ 
                            width: `${Math.min((contados / cantidadRequerida) * 100, 100)}%`,
                            backgroundColor: primaryColor
                        }"
                    ></div>
                </div>
                <span v-if="guardandoProgreso" class="text-[10px] text-gray-400">
                    <i class="fas fa-spinner fa-spin"></i>
                </span>
            </div>
        </div>

        <!-- Instrucción -->
        <div class="rounded-lg p-2 mb-3 border" :style="{ backgroundColor: primaryLight, borderColor: primaryColor }">
            <div class="flex items-start gap-1.5">
                <i class="fas fa-info-circle mt-0.5 text-[10px]" :style="{ color: primaryColor }"></i>
                <p class="text-[10px] sm:text-xs" :style="{ color: primaryColor }">
                    Cuenta físicamente cada producto y registra la cantidad exacta.
                    <span class="block mt-0.5 text-[9px] opacity-75">💡 Las cantidades se guardan automáticamente.</span>
                </p>
            </div>
        </div>

        <!-- Tabla -->
        <div v-if="productosContados && productosContados.length > 0">
            <div class="overflow-x-auto -mx-3 sm:mx-0">
                <table class="min-w-full divide-y divide-gray-200 text-[10px] sm:text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-1.5 sm:px-2 py-1 text-left font-medium text-gray-500 w-6">#</th>
                            <th class="px-1.5 sm:px-2 py-1 text-left font-medium text-gray-500">Producto</th>
                            <th class="px-1.5 sm:px-2 py-1 text-center font-medium text-gray-500 hidden sm:table-cell">Saldo</th>
                            <th class="px-1.5 sm:px-2 py-1 text-center font-medium text-gray-500">Contado</th>
                            <th class="px-1.5 sm:px-2 py-1 text-center font-medium text-gray-500 hidden sm:table-cell">Dif.</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr 
                            v-for="(producto, index) in productosContados" 
                            :key="producto.IdProducto"
                            class="hover:bg-gray-50 transition"
                            :class="{ 
                                'bg-green-50': producto.cantidad !== null && producto.cantidad !== '' && parseFloat(producto.cantidad) > 0,
                                'bg-blue-50': producto.cantidad !== null && producto.cantidad !== '' && parseFloat(producto.cantidad) === 0,
                                'bg-red-50': producto.cantidad === null || producto.cantidad === ''
                            }"
                        >
                            <td class="px-1.5 sm:px-2 py-1 text-gray-500 text-center">{{ index + 1 }}</td>
                            <td class="px-1.5 sm:px-2 py-1 text-gray-700 truncate max-w-[80px] sm:max-w-[150px] lg:max-w-none text-[10px] sm:text-xs" :title="producto.Descripcion">{{ producto.Descripcion || 'Sin nombre' }}</td>
                            <td class="px-1.5 sm:px-2 py-1 text-center text-gray-500 hidden sm:table-cell">
                                {{ formatearNumero(producto.saldo_sistema) }}
                            </td>
                            <td class="px-1.5 sm:px-2 py-1 text-center">
                                <input 
                                    type="text"
                                    inputmode="decimal"
                                    :value="producto.cantidad === null || producto.cantidad === undefined || producto.cantidad === '' ? '' : String(producto.cantidad)"
                                    @input="onCantidadChangeInput($event, producto)"
                                    @blur="onCantidadBlur(producto)"
                                    class="w-10 sm:w-14 text-center border rounded px-1 py-0.5 text-[10px] sm:text-xs focus:ring-1 focus:outline-none transition"
                                    :style="{ 
                                        borderColor: producto.cantidad !== null && producto.cantidad !== '' ? '#22c55e' : '#d1d5db',
                                        backgroundColor: producto.cantidad !== null && producto.cantidad !== '' ? '#f0fdf4' : 'white'
                                    }"
                                    placeholder="0"
                                    :disabled="loading"
                                />
                            </td>
                            <td class="px-1.5 sm:px-2 py-1 text-center hidden sm:table-cell">
                                <span 
                                    class="text-[10px] sm:text-xs font-medium"
                                    :class="{
                                        'text-green-600': (parseFloat(producto.cantidad || 0) - parseFloat(producto.saldo_sistema || 0)) > 0,
                                        'text-red-600': (parseFloat(producto.cantidad || 0) - parseFloat(producto.saldo_sistema || 0)) < 0,
                                        'text-gray-400': (parseFloat(producto.cantidad || 0) - parseFloat(producto.saldo_sistema || 0)) === 0
                                    }"
                                >
                                    {{ formatearNumero(parseFloat(producto.cantidad || 0) - parseFloat(producto.saldo_sistema || 0)) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Resumen -->
            <div class="mt-3 pt-2 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                <div class="flex flex-wrap items-center gap-2 text-[10px] sm:text-xs">
                    <span class="text-gray-500">
                        Contados: <strong class="text-gray-800">{{ contados }}</strong> / {{ cantidadRequerida }}
                    </span>
                    <span class="text-gray-500">
                        Total: <strong class="text-gray-800">{{ formatearNumero(totalContado) }}</strong>
                    </span>
                    <span v-if="productosConDiferencia.length > 0" class="text-yellow-600">
                        <i class="fas fa-exclamation-triangle mr-0.5"></i>
                        {{ productosConDiferencia.length }} con dif.
                    </span>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button
                        @click="volver"
                        class="px-3 py-1 text-[10px] sm:text-xs text-gray-600 hover:text-gray-800 border border-gray-300 rounded hover:bg-gray-50 transition flex-1 sm:flex-none"
                        :disabled="loading"
                    >
                        <i class="fas fa-arrow-left mr-1"></i>
                        Volver
                    </button>
                    <button
                        @click="guardarYContinuar"
                        class="px-3 py-1 text-[10px] sm:text-xs text-white rounded transition flex items-center justify-center gap-1 flex-1 sm:flex-none disabled:opacity-50 disabled:cursor-not-allowed"
                        :style="{ backgroundColor: primaryColor }"
                        :disabled="loading || !todosContados"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check"></i>
                        {{ loading ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
            </div>

            <div v-if="!todosContados" class="mt-2 text-center">
                <p class="text-[10px] text-red-500">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    Faltan <strong>{{ cantidadRequerida - contados }}</strong> productos
                </p>
            </div>

            <div v-if="todosContados" class="mt-2 text-center">
                <p class="text-[10px] text-green-600">
                    <i class="fas fa-check-circle mr-1"></i>
                    ✅ Todos listos. Puedes guardar.
                </p>
            </div>
        </div>

        <!-- Sin productos -->
        <div v-else class="text-center py-6">
            <i class="fas fa-box-open text-3xl text-gray-300 mb-2 block"></i>
            <p class="text-sm text-gray-500">No hay productos disponibles</p>
            <p class="text-xs text-gray-400 mt-1">Contacta al administrador</p>
            <button
                @click="volver"
                class="mt-3 px-4 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50 transition"
            >
                <i class="fas fa-arrow-left mr-1"></i>
                Volver
            </button>
        </div>
    </div>
</template>

<style scoped>
/* 🔥 BREAKPOINT PARA "xs" (móviles pequeños) */
@media (min-width: 480px) {
    .xs\:inline {
        display: inline !important;
    }
}
.xs\:inline {
    display: none;
}

input[type="text"]::-webkit-inner-spin-button,
input[type="text"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="text"] {
    -moz-appearance: textfield;
}
</style>