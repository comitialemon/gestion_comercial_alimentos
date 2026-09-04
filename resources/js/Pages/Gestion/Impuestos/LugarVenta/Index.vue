<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')
const page = usePage()

const props = defineProps({
    lugares: {
        type: Object,
        default: () => ({ data: [], links: [], from: null, to: null, total: 0 })
    },
    sucursales: {
        type: Array,
        default: () => []
    },
    sucursalSeleccionada: {
        type: Number,
        default: null
    },
    contexto_actual: {
        type: Object,
        default: () => ({})
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
const sucursalId = ref(props.sucursalSeleccionada || '')
const busquedaSucursal = ref('')
const mostrarDropdown = ref(false)
const editando = ref(false)
const editId = ref(null)
const formData = ref({ 
    sucursal_id: '',
    Lugar: '', 
    Orden: 0
})
const errors = ref({})
const processing = ref(false)

// Expandir/contraer por sucursal
const expandedSucursales = ref({})

// ==================== COMPUTED ====================
const sucursalesFiltradas = computed(() => {
    if (!busquedaSucursal.value) return props.sucursales || []
    const termino = busquedaSucursal.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.numero?.toString().includes(termino)
    )
})

const lugaresAgrupados = computed(() => {
    const grupos = {}
    
    props.lugares.data.forEach(item => {
        const sucursalId = item.IdSucursal
        const sucursalNombre = item.sucursal?.Nombre || 'Sin sucursal'
        const sucursalNumero = item.sucursal?.NumeroSucursal
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                numero: sucursalNumero,
                items: [],
                expanded: expandedSucursales.value[sucursalId] ?? false
            }
        }
        grupos[sucursalId].items.push(item)
    })
    
    return Object.values(grupos).sort((a, b) => a.nombre.localeCompare(b.nombre))
})

// ==================== FUNCIONES ====================
const cerrarDropdown = () => {
    setTimeout(() => {
        mostrarDropdown.value = false
    }, 200)
}

const seleccionarSucursal = (sucursal) => {
    sucursalId.value = sucursal.id
    busquedaSucursal.value = `${sucursal.nombre} ${sucursal.numero ? `(N° ${sucursal.numero})` : ''}`
    mostrarDropdown.value = false
    formData.value.sucursal_id = sucursal.id
    
    router.get('/gestion/impuestos/lugar-venta', { sucursal_id: sucursal.id }, {
        preserveState: true,
        replace: true,
    })
}

const seleccionarTodas = () => {
    sucursalId.value = ''
    busquedaSucursal.value = 'Todas las sucursales'
    mostrarDropdown.value = false
    formData.value.sucursal_id = ''
    
    router.get('/gestion/impuestos/lugar-venta', {}, {
        preserveState: true,
        replace: true,
    })
}

const toggleExpandir = (sucursalId) => {
    if (expandedSucursales.value[sucursalId] === undefined) {
        expandedSucursales.value[sucursalId] = false
    }
    expandedSucursales.value[sucursalId] = !expandedSucursales.value[sucursalId]
    localStorage.setItem('lugar_venta_expanded', JSON.stringify(expandedSucursales.value))
}

const estaExpandida = (sucursalId) => {
    if (expandedSucursales.value[sucursalId] === undefined) {
        return false
    }
    return expandedSucursales.value[sucursalId]
}

const expandirTodos = () => {
    lugaresAgrupados.value.forEach(grupo => {
        expandedSucursales.value[grupo.id] = true
    })
    localStorage.setItem('lugar_venta_expanded', JSON.stringify(expandedSucursales.value))
}

const contraerTodos = () => {
    lugaresAgrupados.value.forEach(grupo => {
        expandedSucursales.value[grupo.id] = false
    })
    localStorage.setItem('lugar_venta_expanded', JSON.stringify(expandedSucursales.value))
}

// ==================== CRUD ====================
const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = { 
        sucursal_id: '',
        Lugar: '', 
        Orden: 0
    }
    errors.value = {}
}

const editar = (item) => {
    editando.value = true
    editId.value = item.IdLugar
    formData.value = {
        sucursal_id: item.IdSucursal,
        Lugar: item.Lugar,
        Orden: item.Orden
    }
    if (item.IdSucursal) {
        const suc = (props.sucursales || []).find(s => s.id === item.IdSucursal)
        if (suc) {
            busquedaSucursal.value = `${suc.nombre} ${suc.numero ? `(N° ${suc.numero})` : ''}`
            formData.value.sucursal_id = suc.id
        }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const guardar = async () => {
    if (!formData.value.sucursal_id) {
        toast?.error('Validación', 'Seleccione una sucursal')
        return
    }
    
    if (!formData.value.Lugar || formData.value.Lugar.trim() === '') {
        toast?.error('Validación', 'Ingrese el nombre del lugar')
        return
    }
    
    processing.value = true
    
    const datos = {
        sucursal_id: formData.value.sucursal_id,
        Lugar: formData.value.Lugar,
        Orden: formData.value.Orden || null
    }
    
    try {
        if (editando.value) {
            await router.put(`/gestion/impuestos/lugar-venta/${editId.value}`, datos, {
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
            await router.post('/gestion/impuestos/lugar-venta', datos, {
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

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    
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
    
    const savedExpanded = localStorage.getItem('lugar_venta_expanded')
    if (savedExpanded) {
        try {
            expandedSucursales.value = JSON.parse(savedExpanded)
        } catch (e) {
            expandedSucursales.value = {}
        }
    }
    
    if (sucursalId.value) {
        const suc = (props.sucursales || []).find(s => s.id === sucursalId.value)
        if (suc) {
            busquedaSucursal.value = `${suc.nombre} ${suc.numero ? `(N° ${suc.numero})` : ''}`
            formData.value.sucursal_id = sucursalId.value
        }
    } else {
        busquedaSucursal.value = 'Todas las sucursales'
    }
    
    resetForm()
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
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-store text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Lugares de Venta</h1>
                            <p class="text-xs text-gray-500">Administra los puntos de venta físicos o virtuales</p>
                            <p v-if="contexto_actual?.cliente_nombre" class="text-xs text-primary-600">
                                <i class="fas fa-building mr-1"></i> {{ contexto_actual.cliente_nombre }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ==================== FORMULARIO ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4 border border-primary-200">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Sucursal -->
                        <div class="flex-1 min-w-[180px] max-w-[240px]">
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Sucursal *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaSucursal"
                                    @focus="mostrarDropdown = true"
                                    @blur="cerrarDropdown"
                                    placeholder="Buscar sucursal..."
                                    class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-sm pr-7 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    :class="{ 'border-red-500': errors.sucursal_id }"
                                >
                                <button 
                                    v-if="busquedaSucursal && busquedaSucursal !== 'Todas las sucursales'"
                                    @click="busquedaSucursal = ''; sucursalId = ''; formData.sucursal_id = ''"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                                
                                <div 
                                    v-if="mostrarDropdown && (sucursalesFiltradas.length > 0 || busquedaSucursal)"
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div @click="seleccionarTodas()"
                                        class="px-3 py-2 hover:bg-primary-50 cursor-pointer border-b text-sm font-medium text-primary-600"
                                        :class="{ 'bg-primary-50': sucursalId === '' }">
                                        <i class="fas fa-store mr-2"></i> Todas las sucursales
                                    </div>
                                    <div v-for="s in sucursalesFiltradas" :key="s.id"
                                        @click="seleccionarSucursal(s)"
                                        class="px-3 py-2 hover:bg-primary-50 cursor-pointer border-b last:border-b-0 text-sm flex items-center justify-between"
                                        :class="{ 'bg-primary-50': sucursalId === s.id }">
                                        <span>{{ s.nombre }}</span>
                                        <span v-if="s.numero" class="text-xs text-gray-400">N° {{ s.numero }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Nombre Lugar -->
                        <div class="flex-[2] min-w-[180px] max-w-[280px]">
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Nombre del Lugar *</label>
                            <input 
                                type="text" 
                                v-model="formData.Lugar" 
                                placeholder="Ej: Local Central, Terraza" 
                                class="w-full border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                :class="{ 'border-red-500': errors.Lugar }"
                                @keyup.enter="guardar"
                                maxlength="50"
                            />
                        </div>

                        <!-- Orden -->
                        <div class="w-20">
                            <label class="block text-xs font-medium text-gray-700 mb-0.5">Orden</label>
                            <input 
                                type="number" 
                                v-model.number="formData.Orden" 
                                min="0" 
                                placeholder="0"
                                class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-2 ml-auto">
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.sucursal_id || !formData.Lugar"
                                class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-sm font-medium hover:bg-primary-700 transition disabled:opacity-50 flex items-center gap-1.5"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-plus'" class="text-xs"></i>
                                {{ processing ? 'Guardando...' : (editando ? 'Actualizar' : 'Guardar') }}
                            </button>
                            <button v-if="editando" @click="resetForm" 
                                class="px-4 py-1.5 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300 transition flex items-center gap-1.5">
                                <i class="fas fa-times text-xs"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== BOTONES EXPANDIR/CONTRAER ==================== -->
                <div v-if="lugaresAgrupados.length > 0" class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div class="flex gap-2">
                        <button @click="expandirTodos" 
                            class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center gap-1.5">
                            <i class="fas fa-expand text-xs"></i> Expandir todos
                        </button>
                        <button @click="contraerTodos" 
                            class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center gap-1.5">
                            <i class="fas fa-compress text-xs"></i> Contraer todos
                        </button>
                    </div>
                    <span class="text-sm text-gray-400 flex items-center">
                        <i class="fas fa-store mr-1.5"></i> {{ lugaresAgrupados.length }} sucursal(es)
                    </span>
                </div>

                <!-- ==================== LISTA DE LUGARES ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 65vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL (tarjetas agrupadas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="grupo in lugaresAgrupados" :key="grupo.id" 
                                class="bg-gray-50 rounded-lg border border-gray-100 overflow-hidden">
                                <div @click="toggleExpandir(grupo.id)"
                                    class="flex justify-between items-center px-3 py-2 bg-primary-50 cursor-pointer hover:bg-primary-100 transition">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <i class="fas fa-store text-primary-500 text-sm flex-shrink-0"></i>
                                        <span class="text-sm font-semibold text-gray-800 truncate">{{ grupo.nombre }}</span>
                                        <span v-if="grupo.numero" class="text-xs text-gray-400 flex-shrink-0">N° {{ grupo.numero }}</span>
                                        <span class="text-xs text-gray-400 flex-shrink-0">({{ grupo.items.length }})</span>
                                    </div>
                                    <i class="fas text-primary-500 text-sm transition-transform duration-200 flex-shrink-0"
                                        :class="estaExpandida(grupo.id) ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                </div>
                                
                                <div v-show="estaExpandida(grupo.id)" class="divide-y divide-gray-100">
                                    <div v-for="item in grupo.items" :key="item.IdLugar" class="p-3 hover:bg-white transition">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-800 truncate">{{ item.Lugar }}</p>
                                                <span class="inline-block mt-0.5 px-2 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600">
                                                    Orden: {{ item.Orden }}
                                                </span>
                                            </div>
                                            <button @click="editar(item)" 
                                                class="text-primary-600 hover:text-primary-800 text-sm p-1.5 rounded hover:bg-primary-50 flex-shrink-0 ml-2">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="lugaresAgrupados.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-store text-2xl mb-1 block"></i>
                                <span class="text-sm">No hay lugares de venta registrados</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO (tabla con agrupación) -->
                        <div v-else>
                            <div v-for="grupo in lugaresAgrupados" :key="grupo.id" class="border-b border-gray-200 last:border-b-0">
                                <!-- Cabecera de sucursal -->
                                <div @click="toggleExpandir(grupo.id)"
                                    class="flex items-center justify-between px-4 py-2 bg-primary-50 hover:bg-primary-100 cursor-pointer transition select-none">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-store text-primary-500 text-sm"></i>
                                        <span class="text-sm font-semibold text-gray-800">{{ grupo.nombre }}</span>
                                        <span v-if="grupo.numero" class="text-xs text-gray-400">N° {{ grupo.numero }}</span>
                                        <span class="text-xs text-gray-400">({{ grupo.items.length }})</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-gray-400">
                                            Orden: {{ grupo.items.reduce((min, item) => Math.min(min, item.Orden), grupo.items[0]?.Orden || 0) }}
                                        </span>
                                        <i class="fas text-primary-500 text-sm transition-transform duration-200"
                                            :class="estaExpandida(grupo.id) ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </div>
                                </div>
                                
                                <!-- Tabla de items -->
                                <div v-show="estaExpandida(grupo.id)" class="transition-all duration-200">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Lugar</th>
                                                <th class="px-4 py-1.5 text-center text-xs font-medium text-gray-500 uppercase w-20">Orden</th>
                                                <th class="px-4 py-1.5 text-right text-xs font-medium text-gray-500 uppercase w-16">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            <tr v-for="item in grupo.items" :key="item.IdLugar" class="hover:bg-gray-50 transition">
                                                <td class="px-4 py-2 text-sm text-gray-700">
                                                    <i class="fas fa-location-dot text-primary-400 mr-1.5 text-xs"></i>
                                                    {{ item.Lugar }}
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    <span class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 rounded-full text-sm text-gray-600">
                                                        {{ item.Orden }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 text-right">
                                                    <button @click="editar(item)" 
                                                        class="text-primary-600 hover:text-primary-800 transition text-sm p-1 rounded hover:bg-primary-50" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div v-if="lugaresAgrupados.length === 0" class="py-8 text-center text-gray-400 text-sm">
                                <i class="fas fa-store text-2xl mb-1 block"></i>
                                No hay lugares de venta registrados
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== PAGINACIÓN ==================== -->
                <div v-if="lugares.links && lugares.links.length > 1" class="mt-3 px-4 py-2 bg-white rounded-xl shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                        <div class="text-sm text-gray-500">
                            Mostrando {{ lugares.from || 0 }} a {{ lugares.to || 0 }} de {{ lugares.total || 0 }}
                        </div>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link v-for="link in lugares.links" :key="link.label" :href="link.url || '#'" 
                                class="px-3 py-1 rounded-lg border text-sm transition"
                                :class="{ 
                                    'bg-primary-600 text-white border-primary-600': link.active, 
                                    'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url, 
                                    'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url 
                                }" 
                                v-html="link.label" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 14px !important;
    }
}

/* Quitar flechas de inputs number */
input[type="number"] {
    appearance: none;
    -moz-appearance: textfield;
}

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Scrollbar personalizada */
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