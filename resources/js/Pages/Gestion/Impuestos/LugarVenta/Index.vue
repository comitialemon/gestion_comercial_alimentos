<script setup>
import { ref, computed, onMounted, watch, inject } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    lugares: {
        type: Object,
        default: () => ({ data: [], links: [], from: null, to: null, total: 0 })
    },
    empresas: {
        type: Array,
        default: () => []
    },
    sucursales: {
        type: Array,
        default: () => []
    },
    filtros: {
        type: Object,
        default: () => ({})
    }
})

// ==================== ESTADO DEL FORMULARIO ====================
const editando = ref(false)
const editId = ref(null)
const formData = ref({ 
    IdCliente: '',
    IdSucursal: '',
    Lugar: '',
    Orden: 0
})
const errors = ref({})
const processing = ref(false)

// ==================== BUSCADORES PARA EL FORMULARIO ====================
// Buscador de Empresa (formulario)
const busquedaEmpresa = ref('')
const mostrarDropdownEmpresa = ref(false)

// Buscador de Sucursal (formulario)
const busquedaSucursal = ref('')
const mostrarDropdownSucursal = ref(false)
const sucursalesDisponibles = ref([])

// ==================== FILTROS (con buscadores) ====================
// Filtro de Empresa (con buscador)
const busquedaEmpresaFiltro = ref('')
const mostrarDropdownEmpresaFiltro = ref(false)
const clienteIdFiltro = ref(props.filtros?.cliente_id || '')

// Filtro de Sucursal (con buscador)
const busquedaSucursalFiltro = ref('')
const mostrarDropdownSucursalFiltro = ref(false)
const sucursalIdFiltro = ref(props.filtros?.sucursal_id || '')
const sucursalesFiltro = ref([])

// ==================== MODAL DE ELIMINACIÓN ====================
const modalEliminarOpen = ref(false)
const eliminarId = ref(null)
const eliminarNombre = ref('')
const eliminando = ref(false)

// ==================== COMPUTED PARA FILTRAR EN TIEMPO REAL ====================
// Empresas filtradas para el FORMULARIO
const empresasFiltradas = computed(() => {
    if (!busquedaEmpresa.value) return props.empresas || []
    const termino = busquedaEmpresa.value.toLowerCase()
    return (props.empresas || []).filter(e => 
        e.nombre?.toLowerCase().includes(termino)
    )
})

// Empresas filtradas para el FILTRO
const empresasFiltradasFiltro = computed(() => {
    if (!busquedaEmpresaFiltro.value) return props.empresas || []
    const termino = busquedaEmpresaFiltro.value.toLowerCase()
    return (props.empresas || []).filter(e => 
        e.nombre?.toLowerCase().includes(termino)
    )
})

// Sucursales filtradas para el FORMULARIO
const sucursalesFormFiltradas = computed(() => {
    if (!busquedaSucursal.value) return sucursalesDisponibles.value || []
    const termino = busquedaSucursal.value.toLowerCase()
    return (sucursalesDisponibles.value || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.numero?.toString().includes(termino)
    )
})

// Sucursales filtradas para el FILTRO
const sucursalesFiltroFiltradas = computed(() => {
    if (!busquedaSucursalFiltro.value) return sucursalesFiltro.value || []
    const termino = busquedaSucursalFiltro.value.toLowerCase()
    return (sucursalesFiltro.value || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.numero?.toString().includes(termino)
    )
})

// Texto de la empresa seleccionada en el formulario
const empresaSeleccionadaTexto = computed(() => {
    const emp = (props.empresas || []).find(e => e.id === formData.value.IdCliente)
    return emp ? emp.nombre : ''
})

// Texto de la empresa seleccionada en el filtro
const empresaSeleccionadaFiltroTexto = computed(() => {
    const emp = (props.empresas || []).find(e => e.id === clienteIdFiltro.value)
    return emp ? emp.nombre : ''
})

// Texto de la sucursal seleccionada en el filtro
const sucursalSeleccionadaFiltroTexto = computed(() => {
    const suc = sucursalesFiltro.value.find(s => s.id === sucursalIdFiltro.value)
    return suc ? `${suc.nombre} ${suc.numero ? `(N° ${suc.numero})` : ''}` : ''
})

// ==================== FUNCIONES DEL FORMULARIO ====================
// Cargar sucursales cuando cambia la empresa en el formulario
const cargarSucursalesForm = async (clienteId) => {
    if (!clienteId) {
        sucursalesDisponibles.value = []
        formData.value.IdSucursal = ''
        busquedaSucursal.value = ''
        return
    }
    
    try {
        const response = await axios.get(`/gestion/lugar-venta/sucursales/${clienteId}`)
        sucursalesDisponibles.value = response.data
        formData.value.IdSucursal = ''
        busquedaSucursal.value = ''
    } catch (error) {
        console.error('Error cargando sucursales:', error)
        sucursalesDisponibles.value = []
    }
}

// Seleccionar empresa en el formulario
const seleccionarEmpresa = (empresa) => {
    formData.value.IdCliente = empresa.id
    busquedaEmpresa.value = empresa.nombre
    mostrarDropdownEmpresa.value = false
    cargarSucursalesForm(empresa.id)
}

// Seleccionar sucursal en el formulario
const seleccionarSucursal = (sucursal) => {
    formData.value.IdSucursal = sucursal.id
    busquedaSucursal.value = `${sucursal.nombre} ${sucursal.numero ? `(N° ${sucursal.numero})` : ''}`
    mostrarDropdownSucursal.value = false
}

// Limpiar empresa en formulario
const limpiarEmpresa = () => {
    if (editando.value) return
    busquedaEmpresa.value = ''
    formData.value.IdCliente = ''
    sucursalesDisponibles.value = []
    formData.value.IdSucursal = ''
    busquedaSucursal.value = ''
}

// Limpiar sucursal en formulario
const limpiarSucursal = () => {
    formData.value.IdSucursal = ''
    busquedaSucursal.value = ''
}

// Cerrar dropdowns del formulario
const cerrarDropdownEmpresa = () => {
    setTimeout(() => {
        mostrarDropdownEmpresa.value = false
    }, 200)
}

const cerrarDropdownSucursal = () => {
    setTimeout(() => {
        mostrarDropdownSucursal.value = false
    }, 200)
}

// ==================== FUNCIONES DE FILTROS ====================
// Cargar sucursales para el FILTRO
const cargarSucursalesFiltro = async (id) => {
    if (!id) {
        sucursalesFiltro.value = []
        sucursalIdFiltro.value = ''
        busquedaSucursalFiltro.value = ''
        return
    }
    
    try {
        const response = await axios.get(`/gestion/lugar-venta/sucursales/${id}`)
        sucursalesFiltro.value = response.data
        sucursalIdFiltro.value = ''
        busquedaSucursalFiltro.value = ''
    } catch (error) {
        console.error('Error cargando sucursales:', error)
        sucursalesFiltro.value = []
    }
}

// Seleccionar empresa en el filtro
const seleccionarEmpresaFiltro = (empresa) => {
    clienteIdFiltro.value = empresa.id
    busquedaEmpresaFiltro.value = empresa.nombre
    mostrarDropdownEmpresaFiltro.value = false
    cargarSucursalesFiltro(empresa.id)
}

// Seleccionar sucursal en el filtro
const seleccionarSucursalFiltro = (sucursal) => {
    sucursalIdFiltro.value = sucursal.id
    busquedaSucursalFiltro.value = `${sucursal.nombre} ${sucursal.numero ? `(N° ${sucursal.numero})` : ''}`
    mostrarDropdownSucursalFiltro.value = false
}

// Limpiar empresa en filtro
const limpiarEmpresaFiltro = () => {
    clienteIdFiltro.value = ''
    busquedaEmpresaFiltro.value = ''
    sucursalesFiltro.value = []
    sucursalIdFiltro.value = ''
    busquedaSucursalFiltro.value = ''
    aplicarFiltros()
}

// Limpiar sucursal en filtro
const limpiarSucursalFiltro = () => {
    sucursalIdFiltro.value = ''
    busquedaSucursalFiltro.value = ''
    aplicarFiltros()
}

// Cerrar dropdowns del filtro
const cerrarDropdownEmpresaFiltro = () => {
    setTimeout(() => {
        mostrarDropdownEmpresaFiltro.value = false
    }, 200)
}

const cerrarDropdownSucursalFiltro = () => {
    setTimeout(() => {
        mostrarDropdownSucursalFiltro.value = false
    }, 200)
}

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/gestion/lugar-venta', {
        cliente_id: clienteIdFiltro.value || undefined,
        sucursal_id: sucursalIdFiltro.value || undefined,
    }, { preserveState: true, replace: true })
}

// Limpiar todos los filtros
const limpiarFiltros = () => {
    clienteIdFiltro.value = ''
    sucursalIdFiltro.value = ''
    busquedaEmpresaFiltro.value = ''
    busquedaSucursalFiltro.value = ''
    sucursalesFiltro.value = []
    aplicarFiltros()
}

// ==================== CRUD ====================
// Resetear formulario
const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = { 
        IdCliente: '',
        IdSucursal: '',
        Lugar: '',
        Orden: 0
    }
    busquedaEmpresa.value = ''
    busquedaSucursal.value = ''
    sucursalesDisponibles.value = []
    errors.value = {}
}

// Editar
const editar = async (lugar) => {
    editando.value = true
    editId.value = lugar.IdLugar
    formData.value = {
        IdCliente: lugar.IdCliente,
        IdSucursal: lugar.IdSucursal,
        Lugar: lugar.Lugar,
        Orden: lugar.Orden
    }
    
    const empresa = (props.empresas || []).find(e => e.id === lugar.IdCliente)
    if (empresa) {
        busquedaEmpresa.value = empresa.nombre
    }
    
    await cargarSucursalesForm(lugar.IdCliente)
    const sucursal = sucursalesDisponibles.value.find(s => s.id === lugar.IdSucursal)
    if (sucursal) {
        busquedaSucursal.value = `${sucursal.nombre} ${sucursal.numero ? `(N° ${sucursal.numero})` : ''}`
    }
    
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

// Calcular siguiente orden
const calcularSiguienteOrden = async () => {
    if (!formData.value.IdCliente || !formData.value.IdSucursal) return 0
    
    try {
        const response = await axios.get(`/gestion/lugar-venta/max-orden`, {
            params: {
                cliente_id: formData.value.IdCliente,
                sucursal_id: formData.value.IdSucursal
            }
        })
        return (response.data.max_orden || 0) + 1
    } catch (error) {
        console.error('Error calculando orden:', error)
        return 0
    }
}

// Guardar
const guardar = async () => {
    if (!formData.value.IdCliente) {
        toast?.error('Validación', 'Seleccione una empresa')
        return
    }
    
    if (!formData.value.IdSucursal) {
        toast?.error('Validación', 'Seleccione una sucursal')
        return
    }
    
    if (!formData.value.Lugar || formData.value.Lugar.trim() === '') {
        toast?.error('Validación', 'Ingrese el nombre del lugar')
        return
    }
    
    processing.value = true
    
    let orden = formData.value.Orden
    if (!editando.value && (orden === 0 || orden === null)) {
        orden = await calcularSiguienteOrden()
    }
    
    const datos = {
        IdCliente: formData.value.IdCliente,
        IdSucursal: formData.value.IdSucursal,
        Lugar: formData.value.Lugar,
        Orden: orden
    }
    
    try {
        if (editando.value) {
            await router.put(`/gestion/lugar-venta/${editId.value}`, datos, {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.success('Éxito', 'Lugar actualizado correctamente')
                    resetForm()
                },
                onError: (err) => {
                    toast?.error('Error', Object.values(err)[0]?.[0] || 'Error al actualizar')
                }
            })
        } else {
            await router.post('/gestion/lugar-venta', datos, {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.success('Éxito', 'Lugar creado correctamente')
                    resetForm()
                },
                onError: (err) => {
                    toast?.error('Error', Object.values(err)[0]?.[0] || 'Error al guardar')
                }
            })
        }
    } finally {
        processing.value = false
    }
}

// Abrir modal de eliminación
const abrirModalEliminar = (id, nombre) => {
    eliminarId.value = id
    eliminarNombre.value = nombre
    modalEliminarOpen.value = true
}

const cerrarModalEliminar = () => {
    modalEliminarOpen.value = false
    eliminarId.value = null
    eliminarNombre.value = ''
}

const confirmarEliminar = async () => {
    if (!eliminarId.value) return
    
    eliminando.value = true
    
    router.delete(`/gestion/lugar-venta/${eliminarId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast?.success('Éxito', `Lugar "${eliminarNombre.value}" eliminado correctamente`)
            cerrarModalEliminar()
        },
        onError: () => {
            toast?.error('Error', 'No se pudo eliminar el lugar')
            cerrarModalEliminar()
        },
        onFinish: () => {
            eliminando.value = false
        }
    })
}

// ==================== INICIALIZACIÓN ====================
onMounted(() => {
    const flashSuccess = page.props.flash?.success
    const flashError = page.props.flash?.error
    
    if (flashSuccess && !sessionStorage.getItem('last_flash_success')) {
        toast?.success('Éxito', flashSuccess)
        sessionStorage.setItem('last_flash_success', flashSuccess)
        setTimeout(() => sessionStorage.removeItem('last_flash_success'), 500)
    }
    if (flashError && !sessionStorage.getItem('last_flash_error')) {
        toast?.error('Error', flashError)
        sessionStorage.setItem('last_flash_error', flashError)
        setTimeout(() => sessionStorage.removeItem('last_flash_error'), 500)
    }
    
    if (clienteIdFiltro.value) {
        const empresa = props.empresas.find(e => e.id === clienteIdFiltro.value)
        if (empresa) {
            busquedaEmpresaFiltro.value = empresa.nombre
        }
        cargarSucursalesFiltro(clienteIdFiltro.value)
        
        if (sucursalIdFiltro.value) {
            setTimeout(() => {
                const sucursal = sucursalesFiltro.value.find(s => s.id === sucursalIdFiltro.value)
                if (sucursal) {
                    busquedaSucursalFiltro.value = `${sucursal.nombre} ${sucursal.numero ? `(N° ${sucursal.numero})` : ''}`
                }
            }, 500)
        }
    }
    
    resetForm()
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-store text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">Lugares de Venta</h1>
                            <p class="text-[10px] text-gray-500">Administra los puntos de venta físicos o virtuales</p>
                        </div>
                    </div>
                </div>

                <!-- ==================== FILTROS (con buscadores) ==================== -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <!-- Filtro Empresa con buscador -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Empresa</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaEmpresaFiltro"
                                    @focus="mostrarDropdownEmpresaFiltro = true"
                                    @blur="cerrarDropdownEmpresaFiltro"
                                    placeholder="Buscar empresa..."
                                    class="w-full border rounded-md px-3 py-2 text-sm pr-8"
                                >
                                <button 
                                    v-if="busquedaEmpresaFiltro"
                                    @click="limpiarEmpresaFiltro"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div 
                                    v-if="mostrarDropdownEmpresaFiltro && empresasFiltradasFiltro.length > 0"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div
                                        v-for="e in empresasFiltradasFiltro"
                                        :key="e.id"
                                        @click="seleccionarEmpresaFiltro(e)"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-sm"
                                        :class="{ 'bg-primary-50': clienteIdFiltro === e.id }"
                                    >
                                        {{ e.nombre }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filtro Sucursal con buscador -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Sucursal</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaSucursalFiltro"
                                    @focus="mostrarDropdownSucursalFiltro = true"
                                    @blur="cerrarDropdownSucursalFiltro"
                                    placeholder="Buscar sucursal..."
                                    class="w-full border rounded-md px-3 py-2 text-sm pr-8"
                                    :disabled="!clienteIdFiltro"
                                >
                                <button 
                                    v-if="busquedaSucursalFiltro && clienteIdFiltro"
                                    @click="limpiarSucursalFiltro"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div 
                                    v-if="mostrarDropdownSucursalFiltro && sucursalesFiltroFiltradas.length > 0 && clienteIdFiltro"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div
                                        v-for="s in sucursalesFiltroFiltradas"
                                        :key="s.id"
                                        @click="seleccionarSucursalFiltro(s)"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-sm"
                                        :class="{ 'bg-primary-50': sucursalIdFiltro === s.id }"
                                    >
                                        {{ s.nombre }} 
                                        <span v-if="s.numero" class="text-gray-400 text-[10px]">(N° {{ s.numero }})</span>
                                    </div>
                                </div>
                            </div>
                            <p v-if="clienteIdFiltro && sucursalesFiltro.length === 0" class="text-[10px] text-gray-400 mt-0.5">
                                Esta empresa no tiene sucursales
                            </p>
                        </div>

                        <!-- Botones de filtro (ocupan 2 columnas en móvil, 1 en desktop) -->
                        <div class="flex gap-2 items-end col-span-1 sm:col-span-2 lg:col-span-1">
                            <button @click="aplicarFiltros" class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-md text-sm hover:bg-primary-700 transition">
                                <i class="fas fa-search mr-1 text-xs"></i> Filtrar
                            </button>
                            <button @click="limpiarFiltros" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300 transition">
                                <i class="fas fa-eraser mr-1 text-xs"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== FORMULARIO (TODO EN UNA SOLA LÍNEA) ==================== -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6 sticky top-2 z-10">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Campo 1: Empresa -->
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Empresa *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaEmpresa"
                                    @focus="mostrarDropdownEmpresa = true"
                                    @blur="cerrarDropdownEmpresa"
                                    placeholder="Buscar empresa..."
                                    class="w-full border rounded-md px-3 py-2 text-sm pr-7"
                                    :class="{ 'border-red-500': errors.IdCliente, 'bg-gray-100': editando }"
                                    :disabled="editando"
                                >
                                <button 
                                    v-if="busquedaEmpresa && !editando"
                                    @click="limpiarEmpresa"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                
                                <div 
                                    v-if="mostrarDropdownEmpresa && empresasFiltradas.length > 0 && !editando"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div
                                        v-for="e in empresasFiltradas"
                                        :key="e.id"
                                        @click="seleccionarEmpresa(e)"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-sm"
                                        :class="{ 'bg-primary-50': formData.IdCliente === e.id }"
                                    >
                                        {{ e.nombre }}
                                    </div>
                                </div>
                            </div>
                            <p v-if="editando" class="text-[10px] text-gray-400 mt-0.5">No se puede cambiar en edición</p>
                        </div>

                        <!-- Campo 2: Sucursal -->
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Sucursal *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaSucursal"
                                    @focus="mostrarDropdownSucursal = true"
                                    @blur="cerrarDropdownSucursal"
                                    placeholder="Buscar sucursal..."
                                    class="w-full border rounded-md px-3 py-2 text-sm pr-7"
                                    :class="{ 'border-red-500': errors.IdSucursal, 'bg-gray-100': !formData.IdCliente }"
                                    :disabled="!formData.IdCliente"
                                >
                                <button 
                                    v-if="busquedaSucursal && formData.IdCliente"
                                    @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                
                                <div 
                                    v-if="mostrarDropdownSucursal && sucursalesFormFiltradas.length > 0 && formData.IdCliente"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div
                                        v-for="s in sucursalesFormFiltradas"
                                        :key="s.id"
                                        @click="seleccionarSucursal(s)"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-sm"
                                        :class="{ 'bg-primary-50': formData.IdSucursal === s.id }"
                                    >
                                        {{ s.nombre }} 
                                        <span v-if="s.numero" class="text-gray-400 text-[10px]">(N° {{ s.numero }})</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Campo 3: Nombre del Lugar (más ancho) -->
                        <div class="flex-[2] min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre del Lugar *</label>
                            <input 
                                type="text" 
                                v-model="formData.Lugar" 
                                placeholder="Ej: Local Central, Terraza, Salón VIP" 
                                class="w-full border rounded-md px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.Lugar }"
                                maxlength="50"
                            />
                        </div>

                        <!-- Campo 4: Orden (más pequeño, sin flechas) -->
                        <div class="w-20">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Orden</label>
                            <input 
                                type="number" 
                                v-model.number="formData.Orden" 
                                min="0" 
                                class="w-full border rounded-md px-2 py-2 text-sm text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                placeholder="0"
                            />
                        </div>

                        <!-- Campo 5: Botón Guardar -->
                        <div>
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.IdCliente || !formData.IdSucursal || !formData.Lugar"
                                class="px-4 py-2 bg-primary-600 text-white rounded-md text-sm hover:bg-primary-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-save'" class="text-xs"></i>
                                {{ processing ? '' : (editando ? 'Actualizar' : 'Guardar') }}
                            </button>
                        </div>

                        <!-- Botón Cancelar (solo cuando se edita) -->
                        <div v-if="editando">
                            <button 
                                @click="resetForm" 
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300 transition flex items-center gap-1"
                            >
                                <i class="fas fa-times text-xs"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== CARDS PARA MÓVIL ==================== -->
                <div class="block sm:hidden space-y-3">
                    <div 
                        v-for="item in lugares.data" 
                        :key="item.IdLugar" 
                        class="bg-white rounded-lg shadow-sm p-4 border border-gray-100"
                    >
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-store text-primary-600 text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800 text-sm">{{ item.Lugar }}</h3>
                                    <p class="text-[10px] text-gray-500">{{ item.cliente?.Nombre || '-' }}</p>
                                    <p class="text-[10px] text-gray-400">{{ item.sucursal?.Nombre || '-' }}</p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 text-[9px] rounded-full bg-gray-100 text-gray-600">
                                Orden: {{ item.Orden }}
                            </span>
                        </div>
                        <div class="flex justify-end gap-3 pt-3 border-t border-gray-100 mt-2">
                            <button @click="editar(item)" class="text-primary-600 hover:text-primary-800 text-xs flex items-center gap-1">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button @click="abrirModalEliminar(item.IdLugar, item.Lugar)" class="text-red-600 hover:text-red-800 text-xs flex items-center gap-1">
                                <i class="fas fa-trash-alt"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    <div v-if="!lugares.data || lugares.data.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-store text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-sm text-gray-400">No hay lugares de venta registrados</p>
                    </div>
                </div>

                <!-- ==================== TABLA PARA DESKTOP ==================== -->
                <div class="hidden sm:block bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Orden</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Lugar</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Empresa</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-semibold text-primary-700 uppercase">Sucursal</th>
                                    <th class="px-3 py-2 text-right text-[10px] font-semibold text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in lugares.data" :key="item.IdLugar" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-900">
                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 rounded-full text-xs">
                                            {{ item.Orden }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-700">
                                        <i class="fas fa-location-dot text-primary-400 mr-1 text-[10px]"></i>
                                        {{ item.Lugar }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-600">
                                        {{ item.cliente?.Nombre || '-' }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-600">
                                        <i class="fas fa-store text-primary-400 mr-1 text-[10px]"></i>
                                        {{ item.sucursal?.Nombre || '-' }}
                                        <span v-if="item.sucursal?.NumeroSucursal" class="text-gray-400 text-[10px]">(N° {{ item.sucursal.NumeroSucursal }})</span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">
                                        <button @click="editar(item)" class="text-primary-600 hover:text-primary-800 mr-2 transition" title="Editar">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button @click="abrirModalEliminar(item.IdLugar, item.Lugar)" class="text-red-600 hover:text-red-800 transition" title="Eliminar">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!lugares.data || lugares.data.length === 0">
                                    <td colspan="5" class="px-3 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-store text-2xl mb-1 block text-gray-300"></i>
                                        No hay lugares de venta registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="lugares.links && lugares.links.length > 1" class="mt-4 px-3 py-2 bg-white rounded-lg shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                        <div class="text-[10px] text-gray-500">
                            Mostrando {{ lugares.from || 0 }} a {{ lugares.to || 0 }} de {{ lugares.total || 0 }}
                        </div>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in lugares.links" 
                                :key="link.label" 
                                :href="link.url || '#'" 
                                class="px-2 py-0.5 rounded border text-[10px] transition"
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
        </div>

        <!-- MODAL DE ELIMINACIÓN -->
        <div v-if="modalEliminarOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="cerrarModalEliminar">
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden animate-fade-in-up">
                <div class="bg-red-50 p-4 text-center">
                    <div class="w-12 h-12 mx-auto bg-red-100 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-trash-alt text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">¿Eliminar lugar?</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        ¿Estás seguro de eliminar 
                        <span class="font-semibold text-gray-700">"{{ eliminarNombre }}"</span>?
                    </p>
                    <p class="text-xs text-red-500 mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="p-4 flex gap-3">
                    <button @click="cerrarModalEliminar" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition">
                        Cancelar
                    </button>
                    <button @click="confirmarEliminar" :disabled="eliminando" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition disabled:opacity-50 flex items-center justify-center gap-2">
                        <i v-if="eliminando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-trash-alt"></i>
                        {{ eliminando ? 'Eliminando...' : 'Eliminar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fade-in-up {
    animation: fade-in-up 0.2s ease-out;
}
</style>