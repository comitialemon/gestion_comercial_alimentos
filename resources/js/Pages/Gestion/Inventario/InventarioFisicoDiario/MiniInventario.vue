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

// =============================================
// RESPONSIVE
// =============================================
const isMobile = ref(window.innerWidth < 768)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

// =============================================
// ESTADO
// =============================================
const loading = ref(false)
const guardandoProgreso = ref(false)
const productosContados = ref([])
let debounceTimer = null

// Colores del tema
const primaryColor = inject('primaryColor', 'var(--color-primary)')
const primaryLight = inject('primaryLight', 'var(--color-primary-50)')

// =============================================
// COMPUTADOS
// =============================================
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

// =============================================
// MÉTODOS
// =============================================
const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toFixed(2)
}

const getDiferenciaColor = (cantidad, saldo) => {
    const diff = parseFloat(cantidad || 0) - parseFloat(saldo || 0)
    if (diff > 0) return 'text-green-600'
    if (diff < 0) return 'text-red-600'
    return 'text-gray-400'
}

const getDiferenciaIcono = (cantidad, saldo) => {
    const diff = parseFloat(cantidad || 0) - parseFloat(saldo || 0)
    if (diff > 0) return 'fas fa-arrow-up'
    if (diff < 0) return 'fas fa-arrow-down'
    return 'fas fa-minus'
}

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

// =============================================
// CICLO DE VIDA
// =============================================
onMounted(() => {
    window.addEventListener('resize', handleResize)
    inicializarProductos()
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    clearTimeout(debounceTimer)
})

watch(() => props.productos, () => {
    inicializarProductos()
}, { deep: true, immediate: true })
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm p-2 sm:p-3 md:p-4">
        <!-- Header -->
        <div class="flex flex-col xs:flex-row xs:items-center justify-between gap-2 mb-3 pb-2 border-b border-gray-200">
            <div class="min-w-0 flex-1">
                <h2 class="text-sm sm:text-base font-bold text-gray-800 flex items-center gap-1.5">
                    <i class="fas fa-clipboard-list text-xs sm:text-sm" :style="{ color: primaryColor }"></i>
                    <span class="truncate">Inventario Físico Rápido</span>
                </h2>
                <p class="text-[9px] sm:text-xs text-gray-500 mt-0.5 truncate">
                    📅 <strong>Fecha:</strong> {{ fechaStr }}
                    <span v-if="esBorrador" class="text-yellow-600 ml-1 sm:ml-2">
                        <i class="fas fa-pen mr-0.5"></i> <span class="hidden xs:inline">Progreso guardado</span>
                        <span class="xs:hidden">Borrador</span>
                    </span>
                </p>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                <span class="text-[9px] sm:text-xs text-gray-500 whitespace-nowrap">
                    <span class="hidden xs:inline">Progreso: </span>
                    <strong>{{ contados }}</strong> / {{ cantidadRequerida }}
                </span>
                <div class="w-12 sm:w-16 md:w-24 h-1.5 bg-gray-200 rounded-full overflow-hidden flex-shrink-0">
                    <div 
                        class="h-full transition-all duration-300"
                        :style="{ 
                            width: `${Math.min((contados / cantidadRequerida) * 100, 100)}%`,
                            backgroundColor: primaryColor
                        }"
                    ></div>
                </div>
                <span v-if="guardandoProgreso" class="text-[9px] sm:text-xs text-gray-400 flex-shrink-0">
                    <i class="fas fa-spinner fa-spin"></i>
                </span>
            </div>
        </div>

        <!-- Instrucción -->
        <div class="rounded-lg p-1.5 sm:p-2 mb-2 sm:mb-3 border" :style="{ backgroundColor: primaryLight, borderColor: primaryColor }">
            <div class="flex items-start gap-1.5">
                <i class="fas fa-info-circle mt-0.5 text-[9px] sm:text-[10px] flex-shrink-0" :style="{ color: primaryColor }"></i>
                <p class="text-[9px] sm:text-xs" :style="{ color: primaryColor }">
                    <span class="hidden xs:inline">Cuenta físicamente cada producto y registra la cantidad exacta.</span>
                    <span class="xs:hidden">Cuenta y registra cada producto.</span>
                    <span class="block mt-0.5 text-[8px] sm:text-[9px] opacity-75">💡 Las cantidades se guardan automáticamente.</span>
                </p>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- VISTA MÓVIL: TARJETAS (menor a 768px)        -->
        <!-- ============================================ -->
        <div v-if="productosContados && productosContados.length > 0" class="md:hidden space-y-2">
            <div 
                v-for="(producto, index) in productosContados" 
                :key="producto.IdProducto"
                class="border rounded-lg p-3 transition"
                :class="{
                    'border-green-200 bg-green-50': producto.cantidad !== null && producto.cantidad !== '' && parseFloat(producto.cantidad) > 0,
                    'border-blue-200 bg-blue-50': producto.cantidad !== null && producto.cantidad !== '' && parseFloat(producto.cantidad) === 0,
                    'border-red-200 bg-red-50': producto.cantidad === null || producto.cantidad === ''
                }"
            >
                <!-- Producto -->
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <span class="text-xs font-bold text-gray-400 flex-shrink-0">#{{ index + 1 }}</span>
                        <span class="text-sm font-semibold text-gray-800 truncate" :title="producto.Descripcion">
                            {{ producto.Descripcion || 'Sin nombre' }}
                        </span>
                    </div>
                    <span v-if="producto.Codigo && producto.Codigo !== '-'" class="text-[9px] text-gray-400 flex-shrink-0 ml-1">
                        {{ producto.Codigo }}
                    </span>
                </div>

                <!-- Grid: Saldo | Contado | Diferencia -->
                <div class="grid grid-cols-3 gap-2">
                    <!-- Saldo Sistema -->
                    <div class="bg-white rounded px-2 py-1.5 border border-gray-200 text-center">
                        <div class="text-[8px] text-gray-400 uppercase font-medium">Saldo</div>
                        <div class="text-sm font-bold text-gray-700">{{ formatearNumero(producto.saldo_sistema) }}</div>
                    </div>

                    <!-- Contado (input) -->
                    <div class="bg-white rounded px-2 py-1.5 border border-gray-200 text-center" :class="{ 'border-green-400': producto.cantidad !== null && producto.cantidad !== '' }">
                        <div class="text-[8px] text-gray-400 uppercase font-medium">Contado</div>
                        <input 
                            type="text"
                            inputmode="decimal"
                            :value="producto.cantidad === null || producto.cantidad === undefined || producto.cantidad === '' ? '' : String(producto.cantidad)"
                            @input="onCantidadChangeInput($event, producto)"
                            @blur="onCantidadBlur(producto)"
                            class="w-full text-sm font-bold text-gray-800 bg-transparent border-0 p-0 focus:ring-0 outline-none text-center"
                            placeholder="0"
                            :disabled="loading"
                        />
                    </div>

                    <!-- Diferencia -->
                    <div class="bg-white rounded px-2 py-1.5 border border-gray-200 text-center">
                        <div class="text-[8px] text-gray-400 uppercase font-medium">Dif.</div>
                        <div class="text-sm font-bold" :class="getDiferenciaColor(producto.cantidad, producto.saldo_sistema)">
                            <i :class="getDiferenciaIcono(producto.cantidad, producto.saldo_sistema)" class="mr-0.5 text-[9px]"></i>
                            {{ formatearNumero(parseFloat(producto.cantidad || 0) - parseFloat(producto.saldo_sistema || 0)) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- VISTA DESKTOP: TABLA (mayor o igual a 768px) -->
        <!-- ============================================ -->
        <div v-if="productosContados && productosContados.length > 0" class="hidden md:block">
            <div class="overflow-x-auto -mx-2 sm:mx-0">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 py-1.5 text-left font-medium text-gray-500 w-8">#</th>
                            <th class="px-2 py-1.5 text-left font-medium text-gray-500">Producto</th>
                            <th class="px-2 py-1.5 text-center font-medium text-gray-500">Saldo</th>
                            <th class="px-2 py-1.5 text-center font-medium text-gray-500">Contado</th>
                            <th class="px-2 py-1.5 text-center font-medium text-gray-500">Dif.</th>
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
                            <td class="px-2 py-1.5 text-gray-500 text-center">{{ index + 1 }}</td>
                            <td class="px-2 py-1.5 text-gray-700 max-w-[200px] truncate" :title="producto.Descripcion">
                                {{ producto.Descripcion || 'Sin nombre' }}
                            </td>
                            <td class="px-2 py-1.5 text-center text-gray-600 font-medium">
                                {{ formatearNumero(producto.saldo_sistema) }}
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                <input 
                                    type="text"
                                    inputmode="decimal"
                                    :value="producto.cantidad === null || producto.cantidad === undefined || producto.cantidad === '' ? '' : String(producto.cantidad)"
                                    @input="onCantidadChangeInput($event, producto)"
                                    @blur="onCantidadBlur(producto)"
                                    class="w-16 text-center border rounded px-1 py-0.5 text-sm focus:ring-1 focus:outline-none transition"
                                    :style="{ 
                                        borderColor: producto.cantidad !== null && producto.cantidad !== '' ? '#22c55e' : '#d1d5db',
                                        backgroundColor: producto.cantidad !== null && producto.cantidad !== '' ? '#f0fdf4' : 'white'
                                    }"
                                    placeholder="0"
                                    :disabled="loading"
                                />
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                <span 
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="getDiferenciaColor(producto.cantidad, producto.saldo_sistema)"
                                >
                                    <i :class="getDiferenciaIcono(producto.cantidad, producto.saldo_sistema)" class="mr-1 text-[8px]"></i>
                                    {{ formatearNumero(parseFloat(producto.cantidad || 0) - parseFloat(producto.saldo_sistema || 0)) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Resumen -->
        <div v-if="productosContados && productosContados.length > 0" class="mt-2 sm:mt-3 pt-2 border-t border-gray-200">
            <div class="flex flex-col xs:flex-row justify-between items-start xs:items-center gap-2">
                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 text-[9px] sm:text-xs">
                    <span class="text-gray-500 whitespace-nowrap">
                        Contados: <strong class="text-gray-800">{{ contados }}</strong> / {{ cantidadRequerida }}
                    </span>
                    <span class="text-gray-500 whitespace-nowrap">
                        Total: <strong class="text-gray-800">{{ formatearNumero(totalContado) }}</strong>
                    </span>
                    <span v-if="productosConDiferencia.length > 0" class="text-yellow-600 whitespace-nowrap">
                        <i class="fas fa-exclamation-triangle mr-0.5"></i>
                        <span class="hidden xs:inline">{{ productosConDiferencia.length }} con dif.</span>
                        <span class="xs:hidden">{{ productosConDiferencia.length }} dif.</span>
                    </span>
                </div>
                <div class="flex gap-1.5 sm:gap-2 w-full xs:w-auto">
                    <button
                        @click="volver"
                        class="px-2 sm:px-3 py-1 text-[9px] sm:text-xs text-gray-600 hover:text-gray-800 border border-gray-300 rounded hover:bg-gray-50 transition flex-1 xs:flex-none"
                        :disabled="loading"
                    >
                        <i class="fas fa-arrow-left mr-0.5 sm:mr-1"></i>
                        <span class="hidden xs:inline">Volver</span>
                        <span class="xs:hidden">←</span>
                    </button>
                    <button
                        @click="guardarYContinuar"
                        class="px-2 sm:px-3 py-1 text-[9px] sm:text-xs text-white rounded transition flex items-center justify-center gap-1 flex-1 xs:flex-none disabled:opacity-50 disabled:cursor-not-allowed"
                        :style="{ backgroundColor: primaryColor }"
                        :disabled="loading || !todosContados"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check"></i>
                        <span class="hidden xs:inline">{{ loading ? 'Guardando...' : 'Guardar' }}</span>
                        <span class="xs:hidden">{{ loading ? '...' : '✓' }}</span>
                    </button>
                </div>
            </div>

            <!-- Mensajes de estado -->
            <div v-if="!todosContados" class="mt-1.5 sm:mt-2 text-center">
                <p class="text-[8px] sm:text-[10px] text-red-500">
                    <i class="fas fa-exclamation-circle mr-0.5"></i>
                    Faltan <strong>{{ cantidadRequerida - contados }}</strong> productos
                    <span class="hidden xs:inline">por contar</span>
                </p>
            </div>

            <div v-if="todosContados" class="mt-1.5 sm:mt-2 text-center">
                <p class="text-[8px] sm:text-[10px] text-green-600">
                    <i class="fas fa-check-circle mr-0.5"></i>
                    ✅ Todos listos. Puedes guardar.
                </p>
            </div>
        </div>

        <!-- Sin productos -->
        <div v-else class="text-center py-4 sm:py-6">
            <i class="fas fa-box-open text-2xl sm:text-3xl text-gray-300 mb-1 sm:mb-2 block"></i>
            <p class="text-xs sm:text-sm text-gray-500">No hay productos disponibles</p>
            <p class="text-[9px] sm:text-xs text-gray-400 mt-0.5 sm:mt-1">Contacta al administrador</p>
            <button
                @click="volver"
                class="mt-2 sm:mt-3 px-3 sm:px-4 py-1 sm:py-1.5 text-xs sm:text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50 transition"
            >
                <i class="fas fa-arrow-left mr-1"></i>
                Volver
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Breakpoint extra small para móviles muy pequeños */
@media (min-width: 480px) {
    .xs\:inline { display: inline !important; }
    .xs\:hidden { display: none !important; }
    .xs\:flex-row { flex-direction: row !important; }
    .xs\:flex-none { flex: none !important; }
}

.xs\:inline { display: none; }
.xs\:hidden { display: inline; }
.xs\:flex-row { flex-direction: column; }
.xs\:flex-none { flex: 1; }

/* Inputs sin flechas numéricas */
input[type="text"]::-webkit-inner-spin-button,
input[type="text"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="text"] {
    -moz-appearance: textfield;
}

/* Scroll personalizado para tabla */
.overflow-x-auto::-webkit-scrollbar {
    height: 4px;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
}
</style>