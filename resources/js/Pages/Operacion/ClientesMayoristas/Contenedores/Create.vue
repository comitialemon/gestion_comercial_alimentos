<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, inject, watch, nextTick } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    contenedor: {
        type: Object,
        default: null
    },
    productos: {
        type: Array,
        default: () => []
    },
    sucursales: {
        type: Array,
        default: () => []
    }
})

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

// ==================== ESTADO DEL CONTENEDOR ====================
const contenedorId = ref(props.contenedor?.IdContenedor || null)
const cabeceraGuardada = ref(!!props.contenedor?.IdContenedor)
const esBorrador = ref(props.contenedor?.ActivoInactivo === 0)
const estaActivo = ref(props.contenedor?.ActivoInactivo === 1)

// ==================== FORMULARIO CABECERA ====================
const form = ref({
    IdSucursal: props.contenedor?.IdSucursal || '',
    Nombre: props.contenedor?.Nombre || '',
    CapacidadTotal: props.contenedor?.CapacidadTotal || '',
})

// ==================== DETALLES (PRODUCTOS) ====================
const detallesGrid = ref([])

// ==================== NUEVO PRODUCTO ====================
const nuevoProducto = ref({
    IdProducto: '',
    Codigo: '',
    Descripcion: '',
    Cantidad: '',
})

// ==================== BUSCADOR DE PRODUCTOS ====================
const busquedaProducto = ref('')
const mostrandoListaProducto = ref(false)
const productosFiltrados = ref([])

// ==================== SUCURSAL - Autocomplete ====================
const busquedaSucursal = ref('')
const mostrarDropdownSucursal = ref(false)

const sucursalesFiltradas = computed(() => {
    if (!busquedaSucursal.value) return props.sucursales || []
    const termino = busquedaSucursal.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.numero?.toString().includes(termino)
    )
})

// ==================== ESTADOS ====================
const errors = ref({})
const processing = ref(false)
const guardandoDetalle = ref(false)
const finalizando = ref(false)

// ==================== COMPUTADOS ====================
const codigoGenerado = computed(() => {
    if (!form.value.Nombre || !form.value.CapacidadTotal) return ''
    const base = form.value.Nombre.substring(0, 3).toUpperCase()
    return base + '-' + parseInt(form.value.CapacidadTotal)
})

const capacidadTotalNumero = computed(() => {
    return parseFloat(form.value.CapacidadTotal) || 0
})

const cantidadExcedeCapacidad = computed(() => {
    const cantidad = parseFloat(nuevoProducto.value.Cantidad) || 0
    return cantidad > capacidadTotalNumero.value && capacidadTotalNumero.value > 0
})

const todosLosProductosValidos = computed(() => {
    if (detallesGrid.value.length === 0) return false
    return detallesGrid.value.every(item => {
        return Number(item.Cantidad) <= capacidadTotalNumero.value
    })
})

const puedeFinalizar = computed(() => {
    return cabeceraGuardada.value && detallesGrid.value.length > 0 && todosLosProductosValidos.value
})

// ==================== FILTRAR PRODUCTOS ====================
const filtrarProductos = (termino) => {
    if (!termino || termino.trim() === '') {
        productosFiltrados.value = [...props.productos]
        return
    }
    const terminoLower = termino.toLowerCase().trim()
    productosFiltrados.value = props.productos.filter(p => 
        (p.codigo || '').toLowerCase().includes(terminoLower) ||
        (p.descripcion || '').toLowerCase().includes(terminoLower) ||
        (p.texto || '').toLowerCase().includes(terminoLower)
    )
}

const onBuscarProducto = (event) => {
    const termino = event.target.value
    busquedaProducto.value = termino
    filtrarProductos(termino)
    mostrandoListaProducto.value = termino.length >= 1 && productosFiltrados.value.length > 0
}

const onFocusProducto = () => {
    const termino = busquedaProducto.value || ''
    if (termino.length >= 1) {
        filtrarProductos(termino)
        mostrandoListaProducto.value = productosFiltrados.value.length > 0
    } else {
        productosFiltrados.value = [...props.productos]
        mostrandoListaProducto.value = true
    }
}

const seleccionarProducto = (producto) => {
    nuevoProducto.value.IdProducto = producto.id
    nuevoProducto.value.Codigo = producto.codigo
    nuevoProducto.value.Descripcion = producto.descripcion
    busquedaProducto.value = `${producto.codigo} - ${producto.descripcion}`
    mostrandoListaProducto.value = false
}

const limpiarSeleccionProducto = () => {
    nuevoProducto.value = {
        IdProducto: '',
        Codigo: '',
        Descripcion: '',
        Cantidad: '',
    }
    busquedaProducto.value = ''
}

// ==================== SUCURSAL - Funciones ====================
const seleccionarSucursal = (sucursal) => {
    form.value.IdSucursal = sucursal.id
    busquedaSucursal.value = `${sucursal.nombre} ${sucursal.numero ? `(N° ${sucursal.numero})` : ''}`
    mostrarDropdownSucursal.value = false
}

const limpiarSucursal = () => {
    form.value.IdSucursal = ''
    busquedaSucursal.value = ''
}

const cerrarDropdownSucursal = () => {
    setTimeout(() => {
        mostrarDropdownSucursal.value = false
    }, 200)
}

// ==================== CARGAR DETALLES DESDE PROPS ====================
const cargarDetalles = () => {
    console.log('========== CARGANDO DETALLES ==========')
    console.log('props.contenedor:', props.contenedor)
    
    if (props.contenedor?.detalles && props.contenedor.detalles.length > 0) {
        console.log('✅ Detalles encontrados en props:', props.contenedor.detalles)
        
        detallesGrid.value = props.contenedor.detalles.map(d => {
            console.log('Procesando detalle:', d)
            return {
                IdContenedorDetalle: d.IdContenedorDetalle,
                IdProducto: d.IdProducto,
                Codigo: d.Codigo || d.producto?.Codigo || '',
                Descripcion: d.Producto || d.producto?.Descripcion || d.Descripcion || '',
                Cantidad: Number(d.Cantidad) || 0,
            }
        })
        
        console.log('✅ detallesGrid actualizado:', detallesGrid.value)
    } else {
        console.log('❌ No hay detalles en props')
        detallesGrid.value = []
    }
    
    console.log('=========================================')
}

// ==================== GUARDAR CABECERA (PASO 1) ====================
const guardarCabecera = async () => {
    errors.value = {}
    
    if (!form.value.IdSucursal) {
        toast?.error('Validación', 'Seleccione una sucursal')
        return
    }
    if (!form.value.Nombre || form.value.Nombre.trim() === '') {
        toast?.error('Validación', 'Ingrese el nombre del contenedor')
        return
    }
    if (!form.value.CapacidadTotal || parseFloat(form.value.CapacidadTotal) <= 0) {
        toast?.error('Validación', 'Ingrese la capacidad máxima por producto')
        return
    }
    
    processing.value = true
    
    try {
        let response
        
        if (contenedorId.value) {
            response = await axios.put(`/operacion/pedidos/clientes-mayoristas/contenedores/${contenedorId.value}`, {
                IdSucursal: form.value.IdSucursal,
                Nombre: form.value.Nombre,
                CapacidadTotal: form.value.CapacidadTotal,
            })
        } else {
            response = await axios.post('/operacion/pedidos/clientes-mayoristas/contenedores', {
                IdSucursal: form.value.IdSucursal,
                Nombre: form.value.Nombre,
                CapacidadTotal: form.value.CapacidadTotal,
            })
        }
        
        if (response.data.success) {
            if (!contenedorId.value && response.data.contenedor) {
                contenedorId.value = response.data.contenedor.IdContenedor
            }
            
            cabeceraGuardada.value = true
            esBorrador.value = true
            estaActivo.value = false
            
            toast?.success('Éxito', 'Cabecera guardada correctamente. Ahora puede agregar productos.')
            
            if (!contenedorId.value) {
                router.get(`/operacion/pedidos/clientes-mayoristas/contenedores/${response.data.contenedor.IdContenedor}/edit`)
            } else {
                router.reload()
            }
        }
    } catch (error) {
        console.error('Error:', error)
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            toast?.error('Error de validación', Object.values(errors.value).join(', '))
        } else {
            toast?.error('Error', error.response?.data?.message || 'Error al guardar la cabecera')
        }
    } finally {
        processing.value = false
    }
}

// ==================== AGREGAR PRODUCTO (PASO 2) ====================
const agregarProducto = async () => {
    if (!nuevoProducto.value.IdProducto) {
        toast?.warning('Producto requerido', 'Seleccione un producto')
        return
    }
    
    const cantidad = parseFloat(nuevoProducto.value.Cantidad) || 0
    const capacidad = capacidadTotalNumero.value
    
    if (cantidad <= 0) {
        toast?.warning('Cantidad inválida', 'Debe ser mayor a cero')
        return
    }
    
    if (cantidad > capacidad) {
        toast?.warning('Cantidad excede el límite', `El límite máximo por producto es ${capacidad} unidades`)
        return
    }
    
    guardandoDetalle.value = true
    
    try {
        const response = await axios.post('/operacion/pedidos/clientes-mayoristas/contenedores/detalle', {
            IdContenedor: contenedorId.value,
            IdProducto: nuevoProducto.value.IdProducto,
            Cantidad: cantidad,
        })
        
        if (response.data.success) {
            detallesGrid.value.push({
                IdContenedorDetalle: response.data.detalle.IdContenedorDetalle,
                IdProducto: response.data.detalle.IdProducto,
                Codigo: nuevoProducto.value.Codigo,
                Descripcion: nuevoProducto.value.Descripcion,
                Cantidad: cantidad,
            })
            
            limpiarSeleccionProducto()
            toast?.success('Producto agregado', `${nuevoProducto.value.Descripcion} - ${cantidad} unidades`)
        } else {
            toast?.error('Error', response.data.message || 'No se pudo agregar el producto')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'No se pudo agregar el producto')
    } finally {
        guardandoDetalle.value = false
    }
}

// ==================== ELIMINAR PRODUCTO ====================
const eliminarProducto = async (index) => {
    const item = detallesGrid.value[index]
    if (!item) return
    
    try {
        const response = await axios.delete(`/operacion/pedidos/clientes-mayoristas/contenedores/detalle/${item.IdContenedorDetalle}`)
        if (response.data.success) {
            detallesGrid.value.splice(index, 1)
            toast?.success('Producto eliminado', item.Descripcion)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'No se pudo eliminar')
    }
}

// ==================== FINALIZAR CONTENEDOR (PASO 3) ====================
const finalizarContenedor = async () => {
    if (!puedeFinalizar.value) {
        toast?.warning('Validación', 'Todos los productos deben cumplir con el límite máximo')
        return
    }
    
    if (!confirm('¿Estás seguro de finalizar el contenedor? Una vez activado no se podrá modificar.')) {
        return
    }
    
    finalizando.value = true
    
    try {
        const response = await axios.post(`/operacion/pedidos/clientes-mayoristas/contenedores/${contenedorId.value}/finalizar`)
        
        if (response.data.success) {
            toast?.success('Éxito', 'Contenedor activado correctamente')
            router.get('/operacion/pedidos/clientes-mayoristas/contenedores')
        } else {
            toast?.error('Error', response.data.message || 'Error al finalizar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al finalizar')
    } finally {
        finalizando.value = false
    }
}

// ==================== CANCELAR ====================
const cancelar = () => {
    if (detallesGrid.value.length > 0 && !confirm('¿Estás seguro de salir? Los cambios no guardados se perderán.')) {
        return
    }
    router.get('/operacion/pedidos/clientes-mayoristas/contenedores')
}

// ==================== WATCH PARA CUANDO CAMBIAN LAS PROPS ====================
watch(
    () => props.contenedor,
    (nuevoContenedor, viejoContenedor) => {
        console.log('========== WATCH DISPARADO ==========')
        console.log('nuevoContenedor:', nuevoContenedor)
        console.log('viejoContenedor:', viejoContenedor)
        
        if (nuevoContenedor) {
            contenedorId.value = nuevoContenedor.IdContenedor
            cabeceraGuardada.value = true
            esBorrador.value = nuevoContenedor.ActivoInactivo === 0
            estaActivo.value = nuevoContenedor.ActivoInactivo === 1
            
            form.value = {
                IdSucursal: nuevoContenedor.IdSucursal || '',
                Nombre: nuevoContenedor.Nombre || '',
                CapacidadTotal: nuevoContenedor.CapacidadTotal || '',
            }
            
            const suc = props.sucursales.find(s => s.id === nuevoContenedor.IdSucursal)
            if (suc) {
                busquedaSucursal.value = `${suc.nombre} ${suc.numero ? `(N° ${suc.numero})` : ''}`
            }
            
            // ✅ CARGAR DETALLES
            cargarDetalles()
        } else {
            contenedorId.value = null
            cabeceraGuardada.value = false
            esBorrador.value = true
            estaActivo.value = false
            detallesGrid.value = []
        }
        console.log('======================================')
    },
    { immediate: true, deep: true }
)

// ==================== LIFECYCLE ====================
onMounted(() => {
    console.log('========== CREATE.VUE MOUNTED ==========')
    console.log('Props recibidas:', props)
    console.log('props.contenedor:', props.contenedor)
    
    handleResize()
    window.addEventListener('resize', handleResize)
    
    productosFiltrados.value = [...props.productos]
    
    // ✅ Cargar detalles al montar
    if (props.contenedor) {
        cargarDetalles()
    }
    
    console.log('=========================================')
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-boxes text-indigo-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">
                                {{ contenedorId ? 'Editar Contenedor' : 'Nuevo Contenedor' }}
                            </h1>
                            <p class="text-[10px] text-gray-500">
                                {{ cabeceraGuardada ? 'Paso 2: Agregar productos' : 'Paso 1: Datos básicos' }}
                                <span v-if="cabeceraGuardada && esBorrador" class="text-yellow-600"> (BORRADOR)</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button 
                            v-if="!cabeceraGuardada"
                            @click="guardarCabecera"
                            :disabled="processing"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 flex-1 sm:flex-initial justify-center transition disabled:opacity-50"
                        >
                            <i v-if="processing" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ processing ? 'Guardando...' : 'Guardar Borrador' }}
                        </button>
                        
                        <button 
                            v-if="cabeceraGuardada && esBorrador"
                            @click="finalizarContenedor"
                            :disabled="finalizando || !puedeFinalizar"
                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 flex-1 sm:flex-initial justify-center transition disabled:opacity-50"
                        >
                            <i v-if="finalizando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-check-circle"></i>
                            {{ finalizando ? 'Finalizando...' : 'Finalizar Contenedor' }}
                        </button>
                        
                        <button 
                            @click="cancelar"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded text-xs transition flex-1 sm:flex-initial"
                        >
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- FORMULARIO CABECERA (siempre visible) -->
                <!-- ============================================ -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Datos del Contenedor</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                        <!-- Sucursal -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Sucursal *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaSucursal"
                                    @focus="mostrarDropdownSucursal = true"
                                    @blur="cerrarDropdownSucursal"
                                    placeholder="Buscar sucursal..."
                                    class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                    :class="{'border-red-500': errors.IdSucursal}"
                                    :disabled="cabeceraGuardada && !esBorrador"
                                />
                                <button 
                                    v-if="busquedaSucursal && !cabeceraGuardada"
                                    @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div 
                                    v-if="mostrarDropdownSucursal && sucursalesFiltradas.length > 0 && !cabeceraGuardada"
                                    class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-32 overflow-y-auto"
                                >
                                    <div
                                        v-for="s in sucursalesFiltradas"
                                        :key="s.id"
                                        @click="seleccionarSucursal(s)"
                                        class="px-2 py-1.5 hover:bg-indigo-50 cursor-pointer border-b text-xs"
                                        :class="{ 'bg-indigo-50': form.IdSucursal === s.id }"
                                    >
                                        {{ s.nombre }}
                                        <span v-if="s.numero" class="text-gray-400">(N° {{ s.numero }})</span>
                                    </div>
                                </div>
                            </div>
                            <p v-if="errors.IdSucursal" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdSucursal }}</p>
                        </div>

                        <!-- Nombre -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Nombre *</label>
                            <input 
                                type="text" 
                                v-model="form.Nombre" 
                                placeholder="Ej: Rejilla, Jaba..." 
                                class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                :class="{'border-red-500': errors.Nombre}"
                                :disabled="cabeceraGuardada && !esBorrador"
                            />
                            <p v-if="errors.Nombre" class="text-red-500 text-[10px] mt-0.5">{{ errors.Nombre }}</p>
                        </div>

                        <!-- Capacidad Máxima por Producto -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Capacidad Máxima por Producto *</label>
                            <input 
                                type="number" 
                                v-model="form.CapacidadTotal" 
                                step="0.01"
                                placeholder="0.00" 
                                class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                :class="{'border-red-500': errors.CapacidadTotal}"
                                :disabled="cabeceraGuardada && !esBorrador"
                            />
                            <p v-if="errors.CapacidadTotal" class="text-red-500 text-[10px] mt-0.5">{{ errors.CapacidadTotal }}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Límite máximo de unidades por cada producto</p>
                        </div>
                    </div>

                    <!-- Código generado -->
                    <div v-if="codigoGenerado && !cabeceraGuardada" class="mt-3 bg-gray-50 rounded-lg p-2 border border-gray-200">
                        <p class="text-[10px] text-gray-500">Código generado automáticamente</p>
                        <p class="text-sm font-mono font-bold text-indigo-600">{{ codigoGenerado }}</p>
                    </div>

                    <!-- Estado del contenedor -->
                    <div v-if="cabeceraGuardada" class="mt-3 flex items-center gap-2 flex-wrap">
                        <span class="text-xs text-gray-500">Estado:</span>
                        <span v-if="esBorrador" class="px-2 py-0.5 text-[10px] rounded-full bg-yellow-100 text-yellow-800">
                            <i class="fas fa-pencil-alt mr-0.5"></i> BORRADOR
                        </span>
                        <span v-else-if="estaActivo" class="px-2 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-0.5"></i> ACTIVO
                        </span>
                        <span v-if="esBorrador" class="text-[10px] text-gray-400 ml-2">
                            (Puede editar y agregar productos)
                        </span>
                        <span v-if="detallesGrid.length > 0" class="text-[10px] text-blue-500 ml-2">
                            {{ detallesGrid.length }} producto(s) agregados
                        </span>
                        <span class="text-[10px] text-indigo-600 ml-2 bg-indigo-50 px-2 py-0.5 rounded-full">
                            <i class="fas fa-limit"></i> Máx: {{ capacidadTotalNumero }} por producto
                        </span>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECCIÓN DE PRODUCTOS (solo si cabecera guardada Y es borrador) -->
                <!-- ============================================ -->
                <div v-if="cabeceraGuardada && esBorrador" class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Productos del Contenedor</h2>

                    <!-- FORMULARIO EN UNA SOLA FILA -->
                    <div class="flex flex-col sm:flex-row gap-2 mb-4">
                        <!-- Producto -->
                        <div class="relative flex-1 min-w-0">
                            <label class="block text-gray-600 text-[10px] mb-0.5">Producto *</label>
                            <input 
                                type="text" 
                                v-model="busquedaProducto"
                                @input="onBuscarProducto"
                                @focus="onFocusProducto"
                                placeholder="Buscar por código o nombre..." 
                                class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                            />
                            <div 
                                v-if="mostrandoListaProducto && productosFiltrados.length > 0"
                                class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-32 overflow-y-auto"
                            >
                                <div 
                                    v-for="p in productosFiltrados" 
                                    :key="p.id" 
                                    @click="seleccionarProducto(p)" 
                                    class="px-2 py-1.5 hover:bg-indigo-50 cursor-pointer border-b text-xs"
                                >
                                    <span class="font-mono text-gray-500">{{ p.codigo }}</span>
                                    <span class="ml-2">{{ p.descripcion }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Cantidad -->
                        <div class="sm:w-32">
                            <label class="block text-gray-600 text-[10px] mb-0.5">Cantidad *</label>
                            <input 
                                type="number" 
                                step="0.01" 
                                v-model.number="nuevoProducto.Cantidad" 
                                placeholder="0.00" 
                                class="no-spinner w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                :class="{'border-red-500': cantidadExcedeCapacidad}"
                            />
                        </div>

                        <!-- Límite (info) -->
                        <div class="sm:w-24 flex items-end">
                            <div class="w-full text-[10px] text-gray-400 bg-gray-50 rounded-md px-2 py-1.5 border text-center">
                                <i class="fas fa-info-circle"></i> Máx: {{ capacidadTotalNumero }}
                            </div>
                        </div>

                        <!-- Botón Agregar -->
                        <div class="sm:w-28 flex items-end">
                            <button 
                                @click="agregarProducto" 
                                :disabled="guardandoDetalle || !nuevoProducto.IdProducto || cantidadExcedeCapacidad"
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 w-full justify-center transition disabled:opacity-50"
                            >
                                <i v-if="guardandoDetalle" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-plus"></i>
                                {{ guardandoDetalle ? 'Agregando...' : 'Agregar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Mensaje de error de cantidad -->
                    <div v-if="cantidadExcedeCapacidad" class="mb-3 p-2 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        La cantidad ({{ nuevoProducto.Cantidad }}) excede el límite máximo de {{ capacidadTotalNumero }} unidades por producto.
                    </div>

                    <!-- TABLA DE PRODUCTOS -->
                    <div class="overflow-x-auto">
                        <!-- Vista MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="space-y-2">
                            <!-- 🔥 DEBUG: Mostrar si hay datos -->
                            <div v-if="detallesGrid.length === 0" class="text-center text-gray-400 text-sm py-8">
                                <i class="fas fa-box-open text-2xl mb-2 block"></i>
                                No hay productos agregados
                            </div>
                            <div v-for="(item, index) in detallesGrid" :key="index" class="bg-gray-50 rounded-lg p-3 border" :class="{'border-red-300': Number(item.Cantidad) > capacidadTotalNumero}">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <p class="text-xs font-mono text-gray-500">{{ item.Codigo }}</p>
                                        <p class="text-sm font-medium text-gray-800">{{ item.Descripcion }}</p>
                                    </div>
                                    <button @click="eliminarProducto(index)" class="text-red-500 hover:text-red-700 transition" title="Eliminar">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </div>
                                <div class="mt-2 pt-2 border-t border-gray-200">
                                    <p class="text-[10px] text-gray-400">Cantidad</p>
                                    <p class="text-sm font-semibold" :class="Number(item.Cantidad) > capacidadTotalNumero ? 'text-red-600' : 'text-gray-800'">
                                        {{ Number(item.Cantidad).toFixed(2) }}
                                        <span v-if="Number(item.Cantidad) > capacidadTotalNumero" class="text-red-500 text-[10px] ml-1">
                                            <i class="fas fa-exclamation-circle"></i> Excede
                                        </span>
                                        <span v-else class="text-green-500 text-[10px] ml-1">
                                            <i class="fas fa-check-circle"></i> OK
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Vista ESCRITORIO (tabla) -->
                        <div v-else>
                            <!-- 🔥 DEBUG: Mostrar si hay datos -->
                            <div v-if="detallesGrid.length === 0" class="text-center text-gray-400 text-sm py-8">
                                <i class="fas fa-box-open text-2xl mb-2 block"></i>
                                No hay productos agregados
                            </div>
                            <table v-else class="min-w-full text-xs border">
                                <thead class="bg-indigo-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-indigo-700">Código</th>
                                        <th class="px-3 py-2 text-left text-indigo-700">Producto</th>
                                        <th class="px-3 py-2 text-right text-indigo-700">Cantidad</th>
                                        <th class="px-3 py-2 text-center text-indigo-700">Estado</th>
                                        <th class="px-3 py-2 text-center text-indigo-700">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(item, index) in detallesGrid" :key="index" class="hover:bg-gray-50" :class="{'bg-red-50': Number(item.Cantidad) > capacidadTotalNumero}">
                                        <td class="px-3 py-2 font-mono">{{ item.Codigo }}</td>
                                        <td class="px-3 py-2">{{ item.Descripcion }}</td>
                                        <td class="px-3 py-2 text-right font-semibold" :class="Number(item.Cantidad) > capacidadTotalNumero ? 'text-red-600' : 'text-gray-800'">
                                            {{ Number(item.Cantidad).toFixed(2) }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span v-if="Number(item.Cantidad) > capacidadTotalNumero" class="text-red-500 text-[10px]">
                                                <i class="fas fa-exclamation-circle"></i> Excede
                                            </span>
                                            <span v-else class="text-green-500 text-[10px]">
                                                <i class="fas fa-check-circle"></i> OK
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button @click="eliminarProducto(index)" class="text-red-500 hover:text-red-700 transition" title="Eliminar">
                                                <i class="fas fa-trash-alt text-sm"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 font-semibold">
                                    <tr>
                                        <td colspan="2" class="px-3 py-2 text-right">TOTAL PRODUCTOS:</td>
                                        <td class="px-3 py-2 text-right text-indigo-600">
                                            {{ detallesGrid.length }} productos
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span v-if="todosLosProductosValidos" class="text-green-600 text-xs">
                                                <i class="fas fa-check-circle"></i> Todos OK
                                            </span>
                                            <span v-else class="text-red-600 text-xs">
                                                <i class="fas fa-exclamation-circle"></i> Revisar
                                            </span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Mensajes de validación -->
                    <div v-if="detallesGrid.length > 0 && !todosLosProductosValidos" class="mt-3 p-2 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Uno o más productos exceden el límite máximo de {{ capacidadTotalNumero }} unidades.
                    </div>
                    <div v-else-if="detallesGrid.length > 0 && todosLosProductosValidos" class="mt-3 p-2 bg-green-50 border border-green-200 rounded-lg text-xs text-green-700">
                        <i class="fas fa-check-circle mr-1"></i>
                        Todos los productos cumplen con el límite máximo. ¡Puedes finalizar!
                    </div>
                </div>

                <!-- Mensaje si está ACTIVO -->
                <div v-if="cabeceraGuardada && estaActivo" class="bg-blue-50 rounded-lg p-4 text-blue-800 text-sm text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    Este contenedor ya está <strong>ACTIVO</strong> y no se puede modificar.
                </div>

                <!-- Mensaje si cabecera no guardada -->
                <div v-if="!cabeceraGuardada" class="bg-amber-50 rounded-lg p-3 text-amber-800 text-xs text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    Complete todos los campos y presione "Guardar Borrador" para comenzar a agregar productos.
                </div>

                <!-- Información de pasos -->
                <div v-if="cabeceraGuardada && esBorrador" class="mt-3 text-center text-[10px] text-gray-400">
                    <i class="fas fa-arrow-right mr-1"></i>
                    Paso 2 de 2: Agregue los productos y presione "Finalizar Contenedor" para activarlo.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-spinner {
    -moz-appearance: textfield;
    appearance: textfield;
}

[v-show] {
    transition: all 0.2s ease;
}
</style>