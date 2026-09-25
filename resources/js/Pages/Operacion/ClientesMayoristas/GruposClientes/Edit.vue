<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, inject, watch } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    grupo: {
        type: Object,
        required: true
    }
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const tabActiva = ref('minimos')
const loading = ref(false)
const guardando = ref(false)

const minimos = ref([])
const productos = ref([])
const clientes = ref([])
const datosGrupo = ref({
    Nombre: props.grupo?.Nombre || '',
    Descripcion: props.grupo?.Descripcion || '',
    ActivoInactivo: props.grupo?.ActivoInactivo ?? 1,
})

// ==================== COMPUTED ====================
const grupoId = computed(() => props.grupo?.IdGrupoCliente)

const gruposActivosIds = computed(() => {
    return minimos.value
        .filter(m => m.CantidadMinimaGrupo && parseFloat(m.CantidadMinimaGrupo) > 0)
        .map(m => m.IdGrupoAnalisis)
})

const totalMinimos = computed(() => gruposActivosIds.value.length)

const totalProductosConPrecio = computed(() => {
    let total = 0
    productos.value.forEach(g => {
        total += g.Productos.filter(p => p.TienePrecio).length
    })
    return total
})

const totalClientesAsignados = computed(() => {
    return clientes.value.filter(c => c.EnEsteGrupo).length
})

const productosDeGruposActivos = computed(() => {
    return productos.value.filter(g => gruposActivosIds.value.includes(g.IdGrupoAnalisis))
})

// ==================== BÚSQUEDA DE CLIENTES ====================
const busquedaCliente = ref('')
const filtroCliente = ref('todos')

const clientesFiltrados = computed(() => {
    let lista = clientes.value

    if (filtroCliente.value === 'asignados') {
        lista = lista.filter(c => c.EnEsteGrupo)
    } else if (filtroCliente.value === 'disponibles') {
        lista = lista.filter(c => !c.EnEsteGrupo && !c.EnOtroGrupo)
    }

    if (busquedaCliente.value.trim()) {
        const termino = busquedaCliente.value.toLowerCase().trim()
        lista = lista.filter(c =>
            c.Nombre?.toLowerCase().includes(termino) ||
            c.CI_NIT?.toString().includes(termino)
        )
    }

    return [...lista].sort((a, b) =>
        (a.Nombre || '').localeCompare(b.Nombre || '', 'es', { sensitivity: 'base' })
    )
})

const contadoresClientes = computed(() => {
    return {
        todos: clientes.value.length,
        asignados: clientes.value.filter(c => c.EnEsteGrupo).length,
        disponibles: clientes.value.filter(c => !c.EnEsteGrupo && !c.EnOtroGrupo).length,
        enOtros: clientes.value.filter(c => c.EnOtroGrupo).length,
    }
})

const limpiarBusquedaCliente = () => {
    busquedaCliente.value = ''
    filtroCliente.value = 'todos'
}

// ==================== BÚSQUEDA DE PRODUCTOS ====================
const busquedaProducto = ref('')
const filtroProducto = ref('todos') // 'todos' | 'con_precio' | 'sin_precio'

// ✅ Filtrar productos dentro de cada grupo activo
const productosFiltradosPorGrupo = computed(() => {
    const termino = busquedaProducto.value.toLowerCase().trim()

    return productosDeGruposActivos.value.map(grupo => {
        let prodsFiltrados = grupo.Productos

        // Filtro rápido
        if (filtroProducto.value === 'con_precio') {
            prodsFiltrados = prodsFiltrados.filter(p => p.TienePrecio)
        } else if (filtroProducto.value === 'sin_precio') {
            prodsFiltrados = prodsFiltrados.filter(p => !p.TienePrecio)
        }

        // Búsqueda por texto
        if (termino) {
            prodsFiltrados = prodsFiltrados.filter(p =>
                p.Descripcion?.toLowerCase().includes(termino) ||
                p.Codigo?.toLowerCase().includes(termino)
            )
        }

        return {
            ...grupo,
            Productos: prodsFiltrados,
            _totalOriginal: grupo.Productos.length,
        }
    }).filter(g => g.Productos.length > 0) // Ocultar grupos sin productos filtrados
})

// ✅ Contadores globales de productos
const contadoresProductos = computed(() => {
    let todos = 0
    let conPrecio = 0
    let sinPrecio = 0

    productosDeGruposActivos.value.forEach(g => {
        g.Productos.forEach(p => {
            todos++
            if (p.TienePrecio) conPrecio++
            else sinPrecio++
        })
    })

    return { todos, conPrecio, sinPrecio }
})

// ✅ Contador de resultados filtrados
const contadorProductosFiltrados = computed(() => {
    let total = 0
    productosFiltradosPorGrupo.value.forEach(g => {
        total += g.Productos.length
    })
    return total
})

const limpiarBusquedaProducto = () => {
    busquedaProducto.value = ''
    filtroProducto.value = 'todos'
}

// ==================== PESTAÑA 1: MÍNIMOS ====================
const cargarMinimos = async () => {
    try {
        const response = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/grupos-clientes/${grupoId.value}/minimos`
        )
        if (response.data.success) {
            minimos.value = response.data.data.map(m => ({
                ...m,
                CantidadMinimaGrupo: m.CantidadMinimaGrupo !== null ? m.CantidadMinimaGrupo : ''
            }))
        }
    } catch (error) {
        console.error('Error al cargar mínimos:', error)
        toast?.error('Error', 'No se pudieron cargar los mínimos')
    }
}

const guardarMinimos = async () => {
    guardando.value = true
    try {
        const payload = {
            minimos: minimos.value
                .filter(m => m.CantidadMinimaGrupo !== '' && parseFloat(m.CantidadMinimaGrupo) > 0)
                .map(m => ({
                    IdGrupoAnalisis: m.IdGrupoAnalisis,
                    CantidadMinimaGrupo: parseFloat(m.CantidadMinimaGrupo)
                }))
        }

        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/grupos-clientes/${grupoId.value}/asignar-minimos`,
            payload
        )

        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            await cargarMinimos()
        } else {
            toast?.error('Error', response.data.message || 'Error al guardar mínimos')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar mínimos')
    } finally {
        guardando.value = false
    }
}

// ==================== PESTAÑA 2: PRODUCTOS ====================
const cargarProductos = async () => {
    try {
        const response = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/grupos-clientes/${grupoId.value}/productos`
        )
        if (response.data.success) {
            productos.value = response.data.data
        }
    } catch (error) {
        console.error('Error al cargar productos:', error)
        toast?.error('Error', 'No se pudieron cargar los productos')
    }
}

const guardarProductos = async () => {
    guardando.value = true
    try {
        // ✅ CAMBIO: Enviar TODOS los productos (con o sin precio)
        // El backend decide si guarda o elimina
        const productosAGuardar = []
        
        productos.value.forEach(grupo => {
            grupo.Productos.forEach(prod => {
                const sin = prod.PrecioSinFactura
                const con = prod.PrecioConFactura
                
                // ✅ Enviar SIEMPRE (aunque no tenga precio)
                productosAGuardar.push({
                    IdProducto: prod.IdProducto,
                    PrecioSinFactura: (sin !== '' && sin !== null && parseFloat(sin) > 0) 
                        ? parseFloat(sin) 
                        : null,
                    PrecioConFactura: (con !== '' && con !== null && parseFloat(con) > 0) 
                        ? parseFloat(con) 
                        : null,
                    PedidoMinimo: prod.PedidoMinimo && parseInt(prod.PedidoMinimo) > 0 
                        ? parseInt(prod.PedidoMinimo) 
                        : 0,
                })
            })
        })

        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/grupos-clientes/${grupoId.value}/asignar-productos`,
            { productos: productosAGuardar }
        )

        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            await cargarProductos()
        } else {
            toast?.error('Error', response.data.message || 'Error al guardar productos')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar productos')
    } finally {
        guardando.value = false
    }
}

// ==================== PESTAÑA 3: CLIENTES ====================
const cargarClientes = async () => {
    try {
        const response = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/grupos-clientes/${grupoId.value}/clientes-disponibles`
        )
        if (response.data.success) {
            clientes.value = response.data.data
        }
    } catch (error) {
        console.error('Error al cargar clientes:', error)
        toast?.error('Error', 'No se pudieron cargar los clientes')
    }
}

const toggleCliente = (cliente) => {
    if (cliente.EnOtroGrupo) {
        toast?.warning('Aviso', 'Este cliente ya está en otro grupo. Quítalo primero.')
        return
    }
    cliente.EnEsteGrupo = !cliente.EnEsteGrupo
}

const guardarClientes = async () => {
    guardando.value = true
    try {
        const idsSeleccionados = clientes.value
            .filter(c => c.EnEsteGrupo)
            .map(c => c.IdIdentificador)

        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/grupos-clientes/${grupoId.value}/asignar-clientes`,
            { identificadores: idsSeleccionados }
        )

        if (response.data.success) {
            toast?.success('Éxito', response.data.message)
            await cargarClientes()
        } else {
            toast?.error('Error', response.data.message || 'Error al guardar clientes')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar clientes')
    } finally {
        guardando.value = false
    }
}

// ==================== PESTAÑA 4: DATOS GENERALES ====================
const guardarDatos = async () => {
    if (!datosGrupo.value.Nombre.trim()) {
        toast?.error('Validación', 'El nombre es obligatorio')
        return
    }

    guardando.value = true
    try {
        const response = await axios.put(
            `/operacion/pedidos/clientes-mayoristas/grupos-clientes/${grupoId.value}`,
            {
                Nombre: datosGrupo.value.Nombre.trim(),
                Descripcion: datosGrupo.value.Descripcion?.trim() || null,
                ActivoInactivo: datosGrupo.value.ActivoInactivo,
            }
        )

        if (response.data.success) {
            toast?.success('Éxito', 'Datos guardados correctamente')
            
            // ✅ Volver al Index para verificar
            setTimeout(() => {
                router.get('/operacion/pedidos/clientes-mayoristas/grupos-clientes')
            }, 800)
        } else {
            toast?.error('Error', response.data.message || 'Error al actualizar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al actualizar')
    } finally {
        guardando.value = false
    }
}

// ==================== RESUMEN (Datos Generales) ====================
const resumenGrupo = computed(() => {
    const activos = minimos.value.filter(m => 
        m.CantidadMinimaGrupo && parseFloat(m.CantidadMinimaGrupo) > 0
    )

    return {
        minimosActivos: activos.map(m => ({
            nombre: m.NombreGrupo,
            cantidad: parseFloat(m.CantidadMinimaGrupo)
        })),
        totalMinimos: activos.length,
        totalProductosConPrecio: totalProductosConPrecio.value,
        totalClientes: totalClientesAsignados.value,
        tieneConfiguracion: activos.length > 0 || totalProductosConPrecio.value > 0 || totalClientesAsignados.value > 0,
        configuracionCompleta: activos.length > 0 && totalProductosConPrecio.value > 0 && totalClientesAsignados.value > 0,
    }
})

// ==================== NAVEGACIÓN ====================
const volverAlListado = () => {
    router.get('/operacion/pedidos/clientes-mayoristas/grupos-clientes')
}

// ==================== CARGA INICIAL ====================
const cargarTodo = async () => {
    loading.value = true
    try {
        await Promise.all([
            cargarMinimos(),
            cargarProductos(),
            cargarClientes(),
        ])
    } finally {
        loading.value = false
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    cargarTodo()
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <button 
                            @click="volverAlListado"
                            class="w-9 h-9 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary-600 hover:border-primary-300 transition flex-shrink-0"
                        >
                            <i class="fas fa-arrow-left text-xs"></i>
                        </button>
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-layer-group text-primary-600 text-base"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-base lg:text-lg font-bold text-gray-800 truncate">
                                {{ datosGrupo.Nombre || 'Grupo' }}
                            </h1>
                            <p class="text-xs text-gray-500 truncate">
                                {{ datosGrupo.Descripcion || 'Configura mínimos, productos y clientes' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-1.5 flex-wrap">
                        <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded-lg text-[10px] font-medium flex items-center gap-1">
                            <i class="fas fa-chart-line text-[9px]"></i>
                            {{ totalMinimos }} mínimos
                        </span>
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-lg text-[10px] font-medium flex items-center gap-1">
                            <i class="fas fa-box text-[9px]"></i>
                            {{ totalProductosConPrecio }} precios
                        </span>
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-lg text-[10px] font-medium flex items-center gap-1">
                            <i class="fas fa-users text-[9px]"></i>
                            {{ totalClientesAsignados }} clientes
                        </span>
                    </div>
                </div>

                <!-- ==================== TABS ==================== -->
                <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden">
                    <div class="flex border-b border-gray-200 overflow-x-auto">
                        <button 
                            @click="tabActiva = 'minimos'"
                            class="flex-1 min-w-[140px] px-3 py-2.5 text-xs font-medium transition flex items-center justify-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'minimos' 
                                ? 'text-primary-600 border-b-2 border-primary-600 bg-primary-50/50' 
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        >
                            <i class="fas fa-chart-line text-sm"></i>
                            <span>1. Mínimos</span>
                            <span v-if="totalMinimos > 0" class="bg-primary-100 text-primary-700 rounded-full px-1.5 text-[9px] font-bold">
                                {{ totalMinimos }}
                            </span>
                        </button>
                        <button 
                            @click="tabActiva = 'productos'"
                            class="flex-1 min-w-[140px] px-3 py-2.5 text-xs font-medium transition flex items-center justify-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'productos' 
                                ? 'text-emerald-600 border-b-2 border-emerald-600 bg-emerald-50/50' 
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        >
                            <i class="fas fa-box text-sm"></i>
                            <span>2. Productos</span>
                            <span v-if="totalProductosConPrecio > 0" class="bg-emerald-100 text-emerald-700 rounded-full px-1.5 text-[9px] font-bold">
                                {{ totalProductosConPrecio }}
                            </span>
                        </button>
                        <button 
                            @click="tabActiva = 'clientes'"
                            class="flex-1 min-w-[140px] px-3 py-2.5 text-xs font-medium transition flex items-center justify-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'clientes' 
                                ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50' 
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        >
                            <i class="fas fa-users text-sm"></i>
                            <span>3. Clientes</span>
                            <span v-if="totalClientesAsignados > 0" class="bg-blue-100 text-blue-700 rounded-full px-1.5 text-[9px] font-bold">
                                {{ totalClientesAsignados }}
                            </span>
                        </button>
                        <button 
                            @click="tabActiva = 'datos'"
                            class="flex-1 min-w-[140px] px-3 py-2.5 text-xs font-medium transition flex items-center justify-center gap-1.5 whitespace-nowrap"
                            :class="tabActiva === 'datos' 
                                ? 'text-gray-700 border-b-2 border-gray-700 bg-gray-50' 
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        >
                            <i class="fas fa-cog text-sm"></i>
                            <span>4. Datos</span>
                        </button>
                    </div>
                </div>

                <!-- ==================== LOADING ==================== -->
                <div v-if="loading" class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary-500"></i>
                    <p class="text-sm text-gray-500 mt-2">Cargando datos...</p>
                </div>

                <!-- ==================== PESTAÑA 1: MÍNIMOS ==================== -->
                <div v-else-if="tabActiva === 'minimos'" class="bg-white rounded-xl shadow-sm p-3 sm:p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                        <div>
                            <h2 class="text-sm font-bold text-gray-800 flex items-center gap-1.5">
                                <i class="fas fa-chart-line text-primary-500 text-[10px]"></i>
                                Mínimos por Grupo de Análisis
                            </h2>
                            <p class="text-[10px] text-gray-500 mt-0.5">
                                Define la cantidad mínima que debe pedir un cliente por categoría.
                            </p>
                        </div>
                        <button 
                            @click="guardarMinimos"
                            :disabled="guardando"
                            class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 flex items-center gap-1.5 flex-shrink-0"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            Guardar Mínimos
                        </button>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-2 mb-3 text-[11px] text-blue-700 flex items-start gap-1.5">
                        <i class="fas fa-info-circle text-blue-500 flex-shrink-0 mt-0.5 text-[10px]"></i>
                        <p>Ingresa el mínimo en las categorías que quieras vender. Las que dejes en 0 no aparecerán en el paso 2.</p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                        <div 
                            v-for="item in minimos" 
                            :key="item.IdGrupoAnalisis"
                            class="rounded-md border px-2 py-1.5 transition"
                            :class="item.CantidadMinimaGrupo && parseFloat(item.CantidadMinimaGrupo) > 0 
                                ? 'bg-emerald-50 border-emerald-300' 
                                : 'bg-gray-50 border-gray-200'"
                        >
                            <div class="flex items-center justify-between mb-1 gap-1">
                                <label class="text-[10px] font-semibold text-gray-700 truncate flex-1" :title="item.NombreGrupo">
                                    {{ item.NombreGrupo }}
                                </label>
                                <span 
                                    v-if="item.CantidadMinimaGrupo && parseFloat(item.CantidadMinimaGrupo) > 0"
                                    class="text-[7px] bg-emerald-600 text-white px-1 py-0.5 rounded-full font-medium flex-shrink-0"
                                >
                                    ✓
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <input 
                                    type="number"
                                    v-model="item.CantidadMinimaGrupo"
                                    min="0"
                                    step="1"
                                    placeholder="0"
                                    class="flex-1 border rounded px-1.5 py-0.5 text-xs font-medium text-center focus:ring-1 focus:ring-primary-400 focus:border-primary-400 outline-none"
                                    :class="item.CantidadMinimaGrupo && parseFloat(item.CantidadMinimaGrupo) > 0 
                                        ? 'border-emerald-400 bg-white' 
                                        : 'border-gray-300 bg-white'"
                                />
                                <span class="text-[9px] text-gray-400 font-medium">und</span>
                            </div>
                        </div>
                    </div>

                    <p v-if="minimos.length === 0" class="text-center text-gray-400 py-6 text-xs">
                        No hay grupos de análisis configurados
                    </p>
                </div>

                <!-- ==================== PESTAÑA 2: PRODUCTOS ==================== -->
                <div v-else-if="tabActiva === 'productos'" class="bg-white rounded-xl shadow-sm p-3 sm:p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                        <div>
                            <h2 class="text-sm font-bold text-gray-800 flex items-center gap-1.5">
                                <i class="fas fa-box text-emerald-500 text-[10px]"></i>
                                Productos y Precios
                            </h2>
                            <p class="text-[10px] text-gray-500 mt-0.5">
                                Solo se muestran los grupos activos (mínimo &gt; 0).
                            </p>
                        </div>
                        <button 
                            @click="guardarProductos"
                            :disabled="guardando"
                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 flex items-center gap-1.5 flex-shrink-0"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            Guardar Precios
                        </button>
                    </div>

                    <div v-if="productosDeGruposActivos.length === 0" class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-700 flex items-start gap-2">
                        <i class="fas fa-exclamation-triangle text-amber-500 flex-shrink-0 mt-0.5 text-[10px]"></i>
                        <div>
                            <p class="font-medium mb-0.5">No hay grupos de análisis activos</p>
                            <p class="text-[10px]">Ve a la pestaña <strong>1. Mínimos</strong> y asigna un mínimo.</p>
                        </div>
                    </div>

                    <div v-else>
                        <!-- ✅ BUSCADOR DE PRODUCTOS -->
                        <div class="mb-3 space-y-2">
                            <div class="relative">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                <input 
                                    type="text"
                                    v-model="busquedaProducto"
                                    placeholder="Buscar producto por nombre o código..."
                                    class="w-full border border-gray-300 rounded-md pl-7 pr-7 py-1 text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                                />
                                <button 
                                    v-if="busquedaProducto"
                                    @click="busquedaProducto = ''"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5">
                                <button 
                                    @click="filtroProducto = 'todos'"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-medium transition border"
                                    :class="filtroProducto === 'todos' 
                                        ? 'bg-emerald-600 text-white border-emerald-600' 
                                        : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                >
                                    Todos ({{ contadoresProductos.todos }})
                                </button>
                                <button 
                                    @click="filtroProducto = 'con_precio'"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-medium transition border"
                                    :class="filtroProducto === 'con_precio' 
                                        ? 'bg-emerald-600 text-white border-emerald-600' 
                                        : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                >
                                    <i class="fas fa-check-circle text-[9px] mr-0.5"></i>
                                    Con precio ({{ contadoresProductos.conPrecio }})
                                </button>
                                <button 
                                    @click="filtroProducto = 'sin_precio'"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-medium transition border"
                                    :class="filtroProducto === 'sin_precio' 
                                        ? 'bg-emerald-600 text-white border-emerald-600' 
                                        : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                >
                                    <i class="fas fa-minus-circle text-[9px] mr-0.5"></i>
                                    Sin precio ({{ contadoresProductos.sinPrecio }})
                                </button>

                                <span class="ml-auto text-[10px] text-gray-500">
                                    Mostrando <strong class="text-gray-700">{{ contadorProductosFiltrados }}</strong> de {{ contadoresProductos.todos }}
                                </span>

                                <button 
                                    v-if="busquedaProducto || filtroProducto !== 'todos'"
                                    @click="limpiarBusquedaProducto"
                                    class="text-[10px] text-emerald-600 hover:text-emerald-800 font-medium px-2 py-0.5 rounded hover:bg-emerald-50 transition"
                                >
                                    Limpiar
                                </button>
                            </div>
                        </div>

                        <!-- MENSAJE SIN RESULTADOS -->
                        <div v-if="productosFiltradosPorGrupo.length === 0" class="text-center py-6 text-gray-400">
                            <i class="fas fa-search text-2xl text-gray-300 block mb-1"></i>
                            <p class="text-xs">No se encontraron productos</p>
                            <button 
                                @click="limpiarBusquedaProducto"
                                class="mt-2 text-xs text-emerald-600 hover:text-emerald-800 font-medium underline"
                            >
                                Limpiar filtros
                            </button>
                        </div>

                        <!-- LISTA DE PRODUCTOS FILTRADOS -->
                        <div v-else class="space-y-3">
                            <div 
                                v-for="grupoProd in productosFiltradosPorGrupo" 
                                :key="grupoProd.IdGrupoAnalisis"
                                class="border border-gray-200 rounded-lg overflow-hidden"
                            >
                                <!-- Header grupo -->
                                <div class="bg-gradient-to-r from-emerald-50 to-emerald-100/50 px-3 py-1.5 border-b border-emerald-200 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <i class="fas fa-tag text-emerald-600 text-[10px]"></i>
                                        <span class="font-bold text-emerald-800 text-xs">
                                            {{ grupoProd.NombreGrupo }}
                                        </span>
                                        <span class="text-[8px] bg-white text-emerald-700 px-1.5 py-0.5 rounded-full font-medium border border-emerald-200">
                                            Activo
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-emerald-600 font-medium">
                                        {{ grupoProd.Productos.filter(p => p.TienePrecio).length }} / {{ grupoProd.Productos.length }}
                                    </span>
                                </div>

                                <!-- Tabla productos -->
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50 text-[9px] text-gray-500 uppercase">
                                            <tr>
                                                <th class="px-2 py-1 text-left font-medium">Código</th>
                                                <th class="px-2 py-1 text-left font-medium">Producto</th>
                                                <th class="px-2 py-1 text-center font-medium w-20">S/F (Bs.)</th>
                                                <th class="px-2 py-1 text-center font-medium w-20">C/F (Bs.)</th>
                                                <th class="px-2 py-1 text-center font-medium w-16">Mín.</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr 
                                                v-for="prod in grupoProd.Productos" 
                                                :key="prod.IdProducto"
                                                class="hover:bg-gray-50 transition"
                                                :class="prod.TienePrecio ? 'bg-emerald-50/30' : ''"
                                            >
                                                <td class="px-2 py-1 font-mono text-[10px] text-gray-500">
                                                    {{ prod.Codigo }}
                                                </td>
                                                <td class="px-2 py-1 text-gray-700 text-[11px]">
                                                    {{ prod.Descripcion }}
                                                </td>
                                                <td class="px-2 py-1">
                                                    <input 
                                                        type="number"
                                                        v-model="prod.PrecioSinFactura"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="0.00"
                                                        class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs text-center focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 outline-none"
                                                    />
                                                </td>
                                                <td class="px-2 py-1">
                                                    <input 
                                                        type="number"
                                                        v-model="prod.PrecioConFactura"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="0.00"
                                                        class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs text-center focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 outline-none"
                                                    />
                                                </td>
                                                <td class="px-2 py-1">
                                                    <input 
                                                        type="number"
                                                        v-model="prod.PedidoMinimo"
                                                        min="0"
                                                        step="1"
                                                        placeholder="0"
                                                        class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs text-center focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 outline-none"
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== PESTAÑA 3: CLIENTES ==================== -->
                <div v-else-if="tabActiva === 'clientes'" class="bg-white rounded-xl shadow-sm p-3 sm:p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                        <div>
                            <h2 class="text-sm font-bold text-gray-800 flex items-center gap-1.5">
                                <i class="fas fa-users text-blue-500 text-[10px]"></i>
                                Clientes del Grupo
                            </h2>
                            <p class="text-[10px] text-gray-500 mt-0.5">
                                Selecciona los clientes que pertenecerán a este grupo.
                            </p>
                        </div>
                        <button 
                            @click="guardarClientes"
                            :disabled="guardando"
                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 flex items-center gap-1.5 flex-shrink-0"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            Guardar Clientes
                        </button>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-2 mb-3 text-[11px] text-blue-700 flex items-start gap-1.5">
                        <i class="fas fa-info-circle text-blue-500 flex-shrink-0 mt-0.5 text-[10px]"></i>
                        <p>Haz clic en un cliente para agregarlo o quitarlo. Los que ya están en otro grupo aparecen con candado.</p>
                    </div>

                    <div class="mb-3 space-y-2">
                        <div class="relative">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                            <input 
                                type="text"
                                v-model="busquedaCliente"
                                placeholder="Buscar por nombre o CI/NIT..."
                                class="w-full border border-gray-300 rounded-md pl-7 pr-7 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                            <button 
                                v-if="busquedaCliente"
                                @click="busquedaCliente = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <i class="fas fa-times text-[10px]"></i>
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <button 
                                @click="filtroCliente = 'todos'"
                                class="px-2 py-0.5 rounded-md text-[10px] font-medium transition border"
                                :class="filtroCliente === 'todos' 
                                    ? 'bg-blue-600 text-white border-blue-600' 
                                    : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                            >
                                Todos ({{ contadoresClientes.todos }})
                            </button>
                            <button 
                                @click="filtroCliente = 'asignados'"
                                class="px-2 py-0.5 rounded-md text-[10px] font-medium transition border"
                                :class="filtroCliente === 'asignados' 
                                    ? 'bg-blue-600 text-white border-blue-600' 
                                    : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                            >
                                Asignados ({{ contadoresClientes.asignados }})
                            </button>
                            <button 
                                @click="filtroCliente = 'disponibles'"
                                class="px-2 py-0.5 rounded-md text-[10px] font-medium transition border"
                                :class="filtroCliente === 'disponibles' 
                                    ? 'bg-blue-600 text-white border-blue-600' 
                                    : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                            >
                                Disponibles ({{ contadoresClientes.disponibles }})
                            </button>

                            <span class="ml-auto text-[10px] text-gray-500">
                                Mostrando <strong class="text-gray-700">{{ clientesFiltrados.length }}</strong> de {{ contadoresClientes.todos }}
                            </span>

                            <button 
                                v-if="busquedaCliente || filtroCliente !== 'todos'"
                                @click="limpiarBusquedaCliente"
                                class="text-[10px] text-blue-600 hover:text-blue-800 font-medium px-2 py-0.5 rounded hover:bg-blue-50 transition"
                            >
                                Limpiar
                            </button>
                        </div>
                    </div>

                    <div v-if="clientes.length === 0" class="text-center py-6 text-gray-400">
                        <i class="fas fa-users-slash text-2xl text-gray-300 block mb-1"></i>
                        <p class="text-xs">No hay clientes disponibles</p>
                    </div>

                    <div v-else-if="clientesFiltrados.length === 0" class="text-center py-6 text-gray-400">
                        <i class="fas fa-search text-2xl text-gray-300 block mb-1"></i>
                        <p class="text-xs">No se encontraron clientes</p>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
                        <div 
                            v-for="cliente in clientesFiltrados" 
                            :key="cliente.IdIdentificador"
                            @click="toggleCliente(cliente)"
                            class="rounded-md border px-2 py-1.5 transition cursor-pointer flex items-center justify-between gap-2"
                            :class="{
                                'bg-blue-50 border-blue-300': cliente.EnEsteGrupo,
                                'bg-gray-100 border-gray-200 opacity-60 cursor-not-allowed': cliente.EnOtroGrupo,
                                'bg-white border-gray-200 hover:border-blue-300 hover:bg-blue-50/50': !cliente.EnEsteGrupo && !cliente.EnOtroGrupo
                            }"
                        >
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <div 
                                    class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0"
                                    :class="cliente.EnEsteGrupo ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'"
                                >
                                    <i class="fas fa-user text-[9px]"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium text-gray-800 truncate">
                                        {{ cliente.Nombre }}
                                    </p>
                                    <p class="text-[9px] text-gray-500 font-mono">
                                        CI: {{ cliente.CI_NIT }}
                                    </p>
                                </div>
                            </div>
                            <i 
                                v-if="cliente.EnEsteGrupo" 
                                class="fas fa-check-circle text-blue-600 text-xs flex-shrink-0"
                            ></i>
                            <i 
                                v-else-if="cliente.EnOtroGrupo" 
                                class="fas fa-lock text-gray-400 text-xs flex-shrink-0"
                            ></i>
                            <i 
                                v-else 
                                class="far fa-circle text-gray-300 text-xs flex-shrink-0"
                            ></i>
                        </div>
                    </div>
                </div>

                <!-- ==================== PESTAÑA 4: DATOS ==================== -->
                <div v-else-if="tabActiva === 'datos'" class="space-y-4 max-w-3xl">
                    
                    <!-- RESUMEN DEL GRUPO -->
                    <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4">
                        <div class="mb-3">
                            <h2 class="text-sm font-bold text-gray-800 flex items-center gap-1.5">
                                <i class="fas fa-clipboard-check text-indigo-500 text-[10px]"></i>
                                Resumen de Configuración
                            </h2>
                            <p class="text-[10px] text-gray-500 mt-0.5">
                                Vista previa de lo que has configurado para este grupo.
                            </p>
                        </div>

                        <!-- Estado general -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-3">
                            <div class="rounded-lg p-2.5 border" :class="totalMinimos > 0 ? 'bg-purple-50 border-purple-200' : 'bg-gray-50 border-gray-200'">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <i class="fas fa-chart-line text-[10px]" :class="totalMinimos > 0 ? 'text-purple-600' : 'text-gray-400'"></i>
                                    <span class="text-[10px] font-semibold" :class="totalMinimos > 0 ? 'text-purple-800' : 'text-gray-500'">Mínimos</span>
                                </div>
                                <p class="text-lg font-bold" :class="totalMinimos > 0 ? 'text-purple-700' : 'text-gray-400'">
                                    {{ totalMinimos }}
                                </p>
                                <p class="text-[9px]" :class="totalMinimos > 0 ? 'text-purple-600' : 'text-gray-400'">
                                    {{ totalMinimos > 0 ? 'grupos activos' : 'sin configurar' }}
                                </p>
                            </div>

                            <div class="rounded-lg p-2.5 border" :class="totalProductosConPrecio > 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-gray-50 border-gray-200'">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <i class="fas fa-box text-[10px]" :class="totalProductosConPrecio > 0 ? 'text-emerald-600' : 'text-gray-400'"></i>
                                    <span class="text-[10px] font-semibold" :class="totalProductosConPrecio > 0 ? 'text-emerald-800' : 'text-gray-500'">Productos</span>
                                </div>
                                <p class="text-lg font-bold" :class="totalProductosConPrecio > 0 ? 'text-emerald-700' : 'text-gray-400'">
                                    {{ totalProductosConPrecio }}
                                </p>
                                <p class="text-[9px]" :class="totalProductosConPrecio > 0 ? 'text-emerald-600' : 'text-gray-400'">
                                    {{ totalProductosConPrecio > 0 ? 'con precio' : 'sin precios' }}
                                </p>
                            </div>

                            <div class="rounded-lg p-2.5 border" :class="totalClientesAsignados > 0 ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <i class="fas fa-users text-[10px]" :class="totalClientesAsignados > 0 ? 'text-blue-600' : 'text-gray-400'"></i>
                                    <span class="text-[10px] font-semibold" :class="totalClientesAsignados > 0 ? 'text-blue-800' : 'text-gray-500'">Clientes</span>
                                </div>
                                <p class="text-lg font-bold" :class="totalClientesAsignados > 0 ? 'text-blue-700' : 'text-gray-400'">
                                    {{ totalClientesAsignados }}
                                </p>
                                <p class="text-[9px]" :class="totalClientesAsignados > 0 ? 'text-blue-600' : 'text-gray-400'">
                                    {{ totalClientesAsignados > 0 ? 'asignados' : 'sin asignar' }}
                                </p>
                            </div>
                        </div>

                        <!-- Estado de configuración -->
                        <div v-if="resumenGrupo.configuracionCompleta" class="bg-emerald-50 border border-emerald-200 rounded-lg p-2.5 flex items-start gap-2 mb-3">
                            <i class="fas fa-check-circle text-emerald-600 text-sm flex-shrink-0 mt-0.5"></i>
                            <div class="text-xs text-emerald-700">
                                <p class="font-semibold">¡Configuración completa!</p>
                                <p class="text-[10px]">Este grupo está listo para usarse en pedidos.</p>
                            </div>
                        </div>

                        <div v-else-if="resumenGrupo.tieneConfiguracion" class="bg-amber-50 border border-amber-200 rounded-lg p-2.5 flex items-start gap-2 mb-3">
                            <i class="fas fa-exclamation-triangle text-amber-600 text-sm flex-shrink-0 mt-0.5"></i>
                            <div class="text-xs text-amber-700">
                                <p class="font-semibold">Configuración incompleta</p>
                                <p class="text-[10px]">
                                    Falta:
                                    <span v-if="totalMinimos === 0" class="font-medium">mínimos, </span>
                                    <span v-if="totalProductosConPrecio === 0" class="font-medium">productos con precio, </span>
                                    <span v-if="totalClientesAsignados === 0" class="font-medium">clientes</span>
                                </p>
                            </div>
                        </div>

                        <div v-else class="bg-gray-50 border border-gray-200 rounded-lg p-2.5 flex items-start gap-2 mb-3">
                            <i class="fas fa-info-circle text-gray-500 text-sm flex-shrink-0 mt-0.5"></i>
                            <div class="text-xs text-gray-600">
                                <p class="font-semibold">Sin configuración</p>
                                <p class="text-[10px]">Aún no has configurado este grupo. Empieza por la pestaña <strong>1. Mínimos</strong>.</p>
                            </div>
                        </div>

                        <!-- Mínimos activos -->
                        <div v-if="resumenGrupo.minimosActivos.length > 0">
                            <p class="text-[10px] font-semibold text-gray-600 mb-1.5">
                                <i class="fas fa-chart-line text-purple-500 mr-0.5"></i>
                                Mínimos configurados:
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <span 
                                    v-for="min in resumenGrupo.minimosActivos" 
                                    :key="min.nombre"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-50 border border-purple-200 text-purple-700 rounded-full text-[10px] font-medium"
                                >
                                    <i class="fas fa-tag text-[8px]"></i>
                                    {{ min.nombre }}: <strong>{{ min.cantidad }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- FORMULARIO DE DATOS -->
                    <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4">
                        <div class="mb-3">
                            <h2 class="text-sm font-bold text-gray-800 flex items-center gap-1.5">
                                <i class="fas fa-cog text-gray-600 text-[10px]"></i>
                                Datos Generales
                            </h2>
                            <p class="text-[10px] text-gray-500 mt-0.5">
                                Nombre, descripción y estado del grupo.
                            </p>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                                    Nombre del grupo <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text"
                                    v-model="datosGrupo.Nombre"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    maxlength="150"
                                />
                            </div>

                            <div>
                                <label class="text-[10px] text-gray-500 font-medium block mb-0.5">
                                    Descripción
                                </label>
                                <textarea 
                                    v-model="datosGrupo.Descripcion"
                                    rows="3"
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none resize-none"
                                    maxlength="500"
                                ></textarea>
                            </div>

                            <div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input 
                                        type="checkbox"
                                        :checked="datosGrupo.ActivoInactivo === 1"
                                        @change="datosGrupo.ActivoInactivo = $event.target.checked ? 1 : 0"
                                        class="w-3.5 h-3.5 text-primary-600 rounded focus:ring-primary-500"
                                    />
                                    <span class="text-xs font-medium text-gray-700">Grupo activo</span>
                                </label>
                                <p class="text-[9px] text-gray-400 mt-0.5 ml-5">
                                    Los grupos inactivos no se pueden asignar a contenedores.
                                </p>
                            </div>

                            <div class="pt-2 border-t border-gray-100 flex justify-end gap-2">
                                <button 
                                    @click="volverAlListado"
                                    :disabled="guardando"
                                    class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md text-xs font-medium transition disabled:opacity-50"
                                >
                                    Cancelar
                                </button>
                                <button 
                                    @click="guardarDatos"
                                    :disabled="guardando"
                                    class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 flex items-center gap-1.5"
                                >
                                    <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                    <i v-else class="fas fa-save text-[10px]"></i>
                                    {{ guardando ? 'Guardando...' : 'Guardar y volver' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, textarea, button {
        font-size: 13px !important;
    }
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>