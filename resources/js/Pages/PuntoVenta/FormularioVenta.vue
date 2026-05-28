<script setup>
import { ref, computed, onMounted, watch } from "vue"
import { usePage } from "@inertiajs/vue3"
import axios from "axios"
import AppLayout from "@/Layouts/AppLayout.vue"

defineOptions({ layout: AppLayout })

const page = usePage()

const props = defineProps({
    lugarVenta: Object,
    comisionista: Object,
    sucursalNombre: String,
    productosGuardados: {
        type: Array,
        default: () => []
    },
    tieneVentaActiva: {
        type: Boolean,
        default: false
    }
})

const datosCabecera = computed(() => ({
    operador: page.props.auth?.operador?.nombre || "Sistema",
    sucursal: props.sucursalNombre || page.props.ctx?.global_sucursal_nombre || "Sin sucursal",
    consignador: props.comisionista?.nombre || "S/D",
    lugarVenta: props.lugarVenta?.nombre || "S/D",
}))

const grupos = ref([])
const productosPorGrupo = ref({})
const filas = ref([])
const loading = ref(false)

function nuevaFila() {
    return {
        id: Date.now() + Math.random(),
        idVentaGrupo: "",
        idRelacionVentaInventario: "",
        descripcion: "",
        unidades: 1,
        precioUnitario: 0,
        total: 0,
        editando: true,
    }
}

const cargarGrupos = async () => {
    try {
        const res = await axios.get("/api/venta/grupos")
        grupos.value = res.data
        if (grupos.value.length > 0 && filas.value.length === 0 && !props.tieneVentaActiva) {
            const nueva = nuevaFila()
            nueva.idVentaGrupo = grupos.value[0].id
            await cargarProductos(nueva)
            filas.value.push(nueva)
        }
    } catch (e) {
        console.error("Error cargando grupos:", e)
    }
}

const cargarProductos = async (fila) => {
    if (!fila.idVentaGrupo) return
    try {
        const res = await axios.get(`/api/venta/productos/${fila.idVentaGrupo}`)
        productosPorGrupo.value[fila.idVentaGrupo] = res.data
        fila.idRelacionVentaInventario = ""
        fila.descripcion = ""
        fila.precioUnitario = 0
        fila.total = 0
    } catch (e) {
        console.error("Error cargando productos:", e)
    }
}

const seleccionarProducto = async (fila) => {
    const producto = productosPorGrupo.value[fila.idVentaGrupo]?.find(p => p.id == fila.idRelacionVentaInventario)
    if (producto) {
        fila.descripcion = producto.detalle
        let precio = 0
        try {
            const res = await axios.get(`/api/venta/precio-producto/${fila.idRelacionVentaInventario}`)
            precio = parseFloat(res.data.precio) || 0
        } catch (e) {
            precio = parseFloat(producto.precioVenta) || 0
        }
        fila.precioUnitario = precio
        calcularTotal(fila)
    }
}

const calcularTotal = (fila) => {
    const unidades = parseFloat(fila.unidades) || 0
    const precio = parseFloat(fila.precioUnitario) || 0
    fila.total = unidades * precio
}

const validarUnidades = (fila) => {
    const unidades = parseFloat(fila.unidades) || 0
    if (unidades <= 0) {
        alert("Las unidades deben ser mayores a 0")
        fila.unidades = 1
        calcularTotal(fila)
    }
}

const confirmarFila = (fila, index) => {
    if (!fila.idVentaGrupo) {
        alert("Selecciona un grupo")
        return
    }
    if (!fila.idRelacionVentaInventario) {
        alert("Selecciona un producto")
        return
    }
    const unidades = parseFloat(fila.unidades) || 0
    if (unidades <= 0) {
        alert("Las unidades deben ser mayores a 0")
        return
    }
    
    fila.editando = false
    if (index === filas.value.length - 1) {
        setTimeout(() => {
            const nueva = nuevaFila()
            if (grupos.value.length > 0) {
                nueva.idVentaGrupo = fila.idVentaGrupo
                cargarProductos(nueva)
            }
            filas.value.push(nueva)
        }, 200)
    }
}

const agregarFila = () => {
    const nueva = nuevaFila()
    if (grupos.value.length > 0) {
        nueva.idVentaGrupo = grupos.value[0].id
        cargarProductos(nueva)
    }
    filas.value.push(nueva)
}

const eliminarFila = (idx) => { 
    if (filas.value.length > 1) {
        filas.value.splice(idx, 1)
    }
}

const editarFila = (fila) => { 
    fila.editando = true 
}

const totalGeneral = computed(() => {
    return filas.value.reduce((a, f) => a + (parseFloat(f.total) || 0), 0)
})

const puedeContinuar = computed(() => {
    return filas.value.some(f => !f.editando && f.descripcion && (parseFloat(f.unidades) || 0) > 0)
})

const continuarPago = async () => {
    if (!puedeContinuar.value) {
        alert("Agrega al menos un producto confirmado")
        return
    }
    
    loading.value = true
    
    const productos = filas.value
        .filter(f => !f.editando && f.descripcion)
        .map(f => ({
            idVentaGrupo: f.idVentaGrupo,
            idRelacionVentaInventario: f.idRelacionVentaInventario,
            unidades: parseFloat(f.unidades) || 0,
            precioUnitario: parseFloat(f.precioUnitario) || 0,
            totalBolivianos: parseFloat(f.total) || 0
        }))
    
    try {
        const response = await axios.post('/venta-factura/guardar', {
            lugar_venta_id: props.lugarVenta?.id,
            comisionista_id: props.comisionista?.id,
            productos: productos
        })
        
        if (response.data.success) {
            window.location.href = '/venta-factura/pago'
        } else {
            alert('Error: ' + (response.data.error || 'Error desconocido'))
        }
    } catch (error) {
        console.error("Error:", error)
        alert('Error al guardar: ' + (error.response?.data?.error || error.message))
    } finally {
        loading.value = false
    }
}

// 🔥 CARGAR PRODUCTOS GUARDADOS AL INICIAR
onMounted(async () => {
    await cargarGrupos()
    
    // Si hay productos guardados, cargarlos en la tabla
    if (props.productosGuardados && props.productosGuardados.length > 0) {
        filas.value = props.productosGuardados.map(p => ({
            ...p,
            id: Date.now() + Math.random(),
            editando: false
        }))
    } else if (filas.value.length === 0) {
        // Si no hay productos guardados, crear una fila vacía
        const nueva = nuevaFila()
        if (grupos.value.length > 0) {
            nueva.idVentaGrupo = grupos.value[0].id
            await cargarProductos(nueva)
        }
        filas.value.push(nueva)
    }
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-4 px-3 sm:py-6 sm:px-4 md:py-8 md:px-6 lg:py-10 lg:px-8">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-4 sm:mb-6 md:mb-8">
                    <div class="inline-flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 lg:w-16 lg:h-16 bg-emerald-100 rounded-xl sm:rounded-2xl mb-2 sm:mb-3 md:mb-4">
                        <i class="fas fa-cash-register text-base sm:text-lg md:text-xl lg:text-2xl text-emerald-600"></i>
                    </div>
                    <h1 class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-bold text-gray-900">Registro de Venta</h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5 sm:mt-1">Complete los datos de los productos a facturar</p>
                </div>

                <!-- Panel de contexto -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 md:gap-4 mb-4 sm:mb-6 md:mb-8">
                    <div class="bg-white rounded-lg sm:rounded-xl shadow-sm p-2 sm:p-3 md:p-4 border border-gray-100">
                        <div class="text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs text-indigo-600 font-medium">👤 Operador</div>
                        <div class="font-semibold text-[10px] sm:text-xs md:text-sm text-gray-800 truncate">{{ datosCabecera.operador }}</div>
                    </div>
                    <div class="bg-white rounded-lg sm:rounded-xl shadow-sm p-2 sm:p-3 md:p-4 border border-gray-100">
                        <div class="text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs text-emerald-600 font-medium">🏢 Sucursal</div>
                        <div class="font-semibold text-[10px] sm:text-xs md:text-sm text-gray-800 truncate">{{ datosCabecera.sucursal }}</div>
                    </div>
                    <div class="bg-white rounded-lg sm:rounded-xl shadow-sm p-2 sm:p-3 md:p-4 border border-gray-100">
                        <div class="text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs text-amber-600 font-medium">🤝 Consignador</div>
                        <div class="font-semibold text-[10px] sm:text-xs md:text-sm text-gray-800 truncate">{{ datosCabecera.consignador }}</div>
                    </div>
                    <div class="bg-white rounded-lg sm:rounded-xl shadow-sm p-2 sm:p-3 md:p-4 border border-gray-100">
                        <div class="text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs text-purple-600 font-medium">📍 Lugar Venta</div>
                        <div class="font-semibold text-[10px] sm:text-xs md:text-sm text-gray-800 truncate">{{ datosCabecera.lugarVenta }}</div>
                    </div>
                </div>

                <!-- Tarjeta principal -->
                <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg overflow-hidden">
                    <div class="px-3 sm:px-4 md:px-5 lg:px-6 py-2 sm:py-3 md:py-4 bg-gray-50 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 sm:w-7 sm:h-7 md:w-8 md:h-8 lg:w-10 lg:h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-box text-emerald-600 text-xs sm:text-sm md:text-base lg:text-xl"></i>
                            </div>
                            <h2 class="font-semibold text-gray-900 text-xs sm:text-sm md:text-base">Registro de Productos</h2>
                        </div>
                        <div class="text-right">
                            <div class="text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs text-gray-500">Total General</div>
                            <div class="text-base sm:text-lg md:text-xl lg:text-2xl font-bold text-emerald-600">{{ totalGeneral.toFixed(2) }} Bs</div>
                        </div>
                    </div>

                    <!-- Botón agregar -->
                    <div class="px-3 sm:px-4 md:px-5 lg:px-6 py-2 sm:py-2.5 md:py-3 border-b border-gray-100 flex justify-end">
                        <button @click="agregarFila" class="inline-flex items-center gap-1 px-2 sm:px-2.5 md:px-3 lg:px-4 py-1 sm:py-1.5 md:py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg text-[10px] sm:text-xs md:text-sm font-medium transition-all shadow-sm">
                            <i class="fas fa-plus text-[9px] sm:text-xs"></i>
                            <span>Agregar producto</span>
                        </button>
                    </div>

                    <!-- Tabla responsive -->
                    <div class="overflow-x-auto w-full">
                        <div class="min-w-[600px]">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs font-medium text-gray-500 uppercase w-10 sm:w-12 md:w-14 lg:w-16">Acción</th>
                                        <th class="text-left px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs font-medium text-gray-500 uppercase w-24 sm:w-28 md:w-36 lg:w-48">GRUPO</th>
                                        <th class="text-left px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs font-medium text-gray-500 uppercase">PRODUCTO</th>
                                        <th class="text-center px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs font-medium text-gray-500 uppercase w-12 sm:w-14 md:w-16 lg:w-20">UNID</th>
                                        <th class="text-center px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs font-medium text-gray-500 uppercase w-20 sm:w-22 md:w-24 lg:w-28">PRECIO</th>
                                        <th class="text-center px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 text-[8px] sm:text-[9px] md:text-[10px] lg:text-xs font-medium text-gray-500 uppercase w-16 sm:w-18 md:w-20 lg:w-24">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(fila, index) in filas" :key="fila.id" class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 align-middle">
                                            <div class="flex gap-0.5 sm:gap-1">
                                                <button v-if="fila.editando" @click="confirmarFila(fila, index)" class="w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7 rounded-full bg-green-100 text-green-600 hover:bg-green-200 flex items-center justify-center" title="Confirmar">
                                                    <i class="fas fa-check text-[8px] sm:text-[9px] md:text-[10px]"></i>
                                                </button>
                                                <button v-else @click="editarFila(fila)" class="w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7 rounded-full bg-blue-100 text-blue-600 hover:bg-blue-200 flex items-center justify-center" title="Editar">
                                                    <i class="fas fa-edit text-[8px] sm:text-[9px] md:text-[10px]"></i>
                                                </button>
                                                <button @click="eliminarFila(index)" class="w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7 rounded-full bg-red-100 text-red-600 hover:bg-red-200 flex items-center justify-center" :disabled="filas.length === 1" title="Eliminar">
                                                    <i class="fas fa-trash text-[8px] sm:text-[9px] md:text-[10px]"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 align-middle">
                                            <select v-model="fila.idVentaGrupo" @change="cargarProductos(fila)" :disabled="!fila.editando" class="w-full px-1 sm:px-1.5 md:px-2 py-0.5 sm:py-1 rounded-md sm:rounded-lg border border-gray-200 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200 text-[9px] sm:text-[10px] md:text-xs bg-white">
                                                <option v-for="g in grupos" :key="g.id" :value="g.id">{{ g.detalle }}</option>
                                            </select>
                                        </td>
                                        <td class="px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 align-middle">
                                            <select v-model="fila.idRelacionVentaInventario" @change="seleccionarProducto(fila)" :disabled="!fila.editando || !fila.idVentaGrupo" class="w-full px-1 sm:px-1.5 md:px-2 py-0.5 sm:py-1 rounded-md sm:rounded-lg border border-gray-200 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200 text-[9px] sm:text-[10px] md:text-xs bg-white">
                                                <option value="">Seleccione</option>
                                                <option v-for="p in (productosPorGrupo[fila.idVentaGrupo] || [])" :key="p.id" :value="p.id">{{ p.detalle }}</option>
                                            </select>
                                        </td>
                                        <td class="px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 align-middle">
                                            <input type="number" v-model.number="fila.unidades" @input="calcularTotal(fila)" @blur="validarUnidades(fila)" min="1" step="1" :readonly="!fila.editando" class="w-11 sm:w-12 md:w-14 lg:w-16 px-0.5 sm:px-1 py-0.5 sm:py-1 rounded-md border border-gray-200 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200 text-center text-[9px] sm:text-[10px] md:text-xs" />
                                        </td>
                                        <td class="px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 align-middle">
                                            <input type="text" :value="(parseFloat(fila.precioUnitario) || 0).toFixed(2)" readonly class="w-14 sm:w-16 md:w-20 lg:w-24 px-1 sm:px-1.5 py-0.5 sm:py-1 rounded-md border border-gray-200 bg-gray-100 text-center text-[9px] sm:text-[10px] md:text-xs" />
                                        </td>
                                        <td class="px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 text-center font-semibold text-emerald-600 text-[9px] sm:text-[10px] md:text-xs align-middle">
                                            {{ (parseFloat(fila.total) || 0).toFixed(2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-3 sm:px-4 md:px-5 lg:px-6 py-2 sm:py-3 md:py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                        <button 
                            type="button"
                            @click="continuarPago" 
                            :disabled="!puedeContinuar || loading" 
                            class="px-2.5 sm:px-3 md:px-4 lg:px-5 py-1 sm:py-1.5 md:py-2 rounded-lg sm:rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-medium text-[10px] sm:text-xs md:text-sm shadow-sm hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                        >
                            <i v-if="loading" class="fas fa-spinner fa-spin text-[9px] sm:text-xs"></i>
                            <i v-else class="fas fa-arrow-right text-[9px] sm:text-xs"></i>
                            <span>{{ loading ? 'Guardando...' : 'Finalizar Venta' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>