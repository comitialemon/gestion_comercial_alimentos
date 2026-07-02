<!-- resources/js/Pages/Gestion/Impuestos/LugarVenta/Index.vue -->
<script setup>
import { ref, computed, onMounted, inject } from 'vue'
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

// 🔥 EXPANDIR/CONTRAER POR SUCURSAL
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

// 🔥 AGRUPAR LUGARES POR SUCURSAL
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
                // Inicializar expandido (por defecto cerrado si hay más de 1 sucursal)
                expanded: expandedSucursales.value[sucursalId] ?? false
            }
        }
        grupos[sucursalId].items.push(item)
    })
    
    // Ordenar por nombre de sucursal
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

// 🔥 TOGGLE EXPANDIR/CONTRAER
const toggleExpandir = (sucursalId) => {
    if (expandedSucursales.value[sucursalId] === undefined) {
        expandedSucursales.value[sucursalId] = false
    }
    expandedSucursales.value[sucursalId] = !expandedSucursales.value[sucursalId]
    
    // Guardar en localStorage para mantener estado
    localStorage.setItem('lugar_venta_expanded', JSON.stringify(expandedSucursales.value))
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

// 🔥 VERIFICAR SI UNA SUCURSAL ESTÁ EXPANDIDA
const estaExpandida = (sucursalId) => {
    if (expandedSucursales.value[sucursalId] === undefined) {
        // Por defecto: la primera sucursal expandida, las demás contraídas
        const keys = Object.keys(expandedSucursales.value)
        if (keys.length === 0) {
            // Si no hay ninguna, expandir la primera
            return false
        }
        return false
    }
    return expandedSucursales.value[sucursalId]
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
    
    // 🔥 Recuperar estado de expansión desde localStorage
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
                            <p v-if="contexto_actual?.cliente_nombre" class="text-[10px] text-primary-600">
                                <i class="fas fa-building mr-1"></i> {{ contexto_actual.cliente_nombre }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 🔥 UNA SOLA FILA - IGUAL QUE ALMACEN -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6 sticky top-2 z-10 border border-primary-200">
                    <div class="flex flex-wrap gap-2 items-end">
                        <!-- Campo 1: Sucursal con buscador -->
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Sucursal *</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="busquedaSucursal"
                                    @focus="mostrarDropdown = true"
                                    @blur="cerrarDropdown"
                                    placeholder="Buscar sucursal..."
                                    class="w-full border rounded-md px-3 py-2 text-sm pr-8"
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
                                    <div
                                        @click="seleccionarTodas()"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b text-sm font-medium text-primary-600"
                                        :class="{ 'bg-primary-50': sucursalId === '' }"
                                    >
                                        <i class="fas fa-store mr-2"></i> Todas las sucursales
                                    </div>
                                    <div
                                        v-for="s in sucursalesFiltradas"
                                        :key="s.id"
                                        @click="seleccionarSucursal(s)"
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 text-sm"
                                        :class="{ 'bg-primary-50': sucursalId === s.id }"
                                    >
                                        {{ s.nombre }} 
                                        <span v-if="s.numero" class="text-gray-400 text-[10px]">(N° {{ s.numero }})</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Campo 2: Nombre del Lugar -->
                        <div class="flex-[2] min-w-[180px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre del Lugar *</label>
                            <input 
                                type="text" 
                                v-model="formData.Lugar" 
                                placeholder="Ej: Local Central, Terraza" 
                                class="w-full border rounded-md px-3 py-2 text-sm"
                                :class="{ 'border-red-500': errors.Lugar }"
                                @keyup.enter="guardar"
                                maxlength="50"
                            />
                        </div>

                        <!-- Campo 3: Orden (sin flechas) -->
                        <div class="w-20">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Orden</label>
                            <input 
                                type="number" 
                                v-model.number="formData.Orden" 
                                min="0" 
                                placeholder="0"
                                class="w-full border rounded-md px-2 py-2 text-sm text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                        </div>

                        <!-- Campo 4: Botón Guardar -->
                        <div>
                            <button 
                                @click="guardar" 
                                :disabled="processing || !formData.sucursal_id || !formData.Lugar"
                                class="px-4 py-2 bg-primary-600 text-white rounded-md text-sm hover:bg-primary-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                            >
                                <i v-if="processing" class="fas fa-spinner fa-spin text-xs"></i>
                                <i v-else :class="editando ? 'fas fa-pencil-alt' : 'fas fa-plus'" class="text-xs"></i>
                                {{ processing ? 'Procesando...' : (editando ? 'Actualizar' : 'Guardar') }}
                            </button>
                        </div>

                        <!-- Campo 5: Cancelar -->
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

                <!-- 🔥 BOTONES EXPANDIR/CONTRAER -->
                <div v-if="lugaresAgrupados.length > 0" class="flex gap-2 mb-4">
                    <button 
                        @click="expandirTodos" 
                        class="px-3 py-1.5 text-xs bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center gap-1"
                    >
                        <i class="fas fa-expand text-[10px]"></i> Expandir todos
                    </button>
                    <button 
                        @click="contraerTodos" 
                        class="px-3 py-1.5 text-xs bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center gap-1"
                    >
                        <i class="fas fa-compress text-[10px]"></i> Contraer todos
                    </button>
                    <span class="text-xs text-gray-500 ml-auto flex items-center">
                        <i class="fas fa-store mr-1"></i> {{ lugaresAgrupados.length }} sucursal(es)
                    </span>
                </div>

                <!-- ==================== CARDS PARA MÓVIL ==================== -->
                <div class="block sm:hidden space-y-3">
                    <!-- Agrupar por sucursal en móvil -->
                    <div v-for="grupo in lugaresAgrupados" :key="grupo.id" class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <!-- Título de sucursal con toggle -->
                        <div 
                            @click="toggleExpandir(grupo.id)"
                            class="flex justify-between items-center p-3 bg-primary-50 cursor-pointer hover:bg-primary-100 transition"
                        >
                            <div class="flex items-center gap-2">
                                <i class="fas fa-store text-primary-500 text-sm"></i>
                                <span class="font-semibold text-gray-800 text-sm">{{ grupo.nombre }}</span>
                                <span v-if="grupo.numero" class="text-[10px] text-gray-400">(N° {{ grupo.numero }})</span>
                                <span class="text-[10px] text-gray-400 ml-1">({{ grupo.items.length }})</span>
                            </div>
                            <i 
                                class="fas text-primary-500 transition-transform duration-200"
                                :class="estaExpandida(grupo.id) ? 'fa-chevron-up' : 'fa-chevron-down'"
                            ></i>
                        </div>
                        
                        <!-- Items de la sucursal -->
                        <div 
                            v-show="estaExpandida(grupo.id)"
                            class="divide-y divide-gray-100"
                        >
                            <div 
                                v-for="item in grupo.items" 
                                :key="item.IdLugar" 
                                class="p-3 hover:bg-gray-50"
                            >
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">{{ item.Lugar }}</p>
                                        <span class="inline-block mt-1 px-2 py-0.5 text-[10px] rounded-full bg-gray-100 text-gray-600">
                                            Orden: {{ item.Orden }}
                                        </span>
                                    </div>
                                    <button @click="editar(item)" class="text-primary-600 hover:text-primary-800 text-xs p-1.5 hover:bg-primary-50 rounded transition" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="lugaresAgrupados.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-store text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-sm text-gray-400">No hay lugares de venta registrados</p>
                    </div>
                </div>

                <!-- ==================== TABLA PARA DESKTOP ==================== -->
                <div class="hidden sm:block bg-white rounded-lg shadow-sm overflow-hidden">
                    <!-- Recorrer grupos por sucursal -->
                    <div 
                        v-for="grupo in lugaresAgrupados" 
                        :key="grupo.id" 
                        class="border-b border-gray-200 last:border-b-0"
                    >
                        <!-- Cabecera de sucursal (clickeable) -->
                        <div 
                            @click="toggleExpandir(grupo.id)"
                            class="flex items-center justify-between px-4 py-2.5 bg-primary-50 hover:bg-primary-100 cursor-pointer transition select-none"
                        >
                            <div class="flex items-center gap-2">
                                <i class="fas fa-store text-primary-500 text-sm"></i>
                                <span class="font-semibold text-gray-800 text-sm">{{ grupo.nombre }}</span>
                                <span v-if="grupo.numero" class="text-xs text-gray-400">(N° {{ grupo.numero }})</span>
                                <span class="text-xs text-gray-400 ml-1">({{ grupo.items.length }} lugares)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400">
                                    Orden: {{ grupo.items.reduce((min, item) => Math.min(min, item.Orden), grupo.items[0]?.Orden || 0) }} - 
                                    {{ grupo.items.reduce((max, item) => Math.max(max, item.Orden), grupo.items[0]?.Orden || 0) }}
                                </span>
                                <i 
                                    class="fas text-primary-500 transition-transform duration-200"
                                    :class="estaExpandida(grupo.id) ? 'fa-chevron-up' : 'fa-chevron-down'"
                                ></i>
                            </div>
                        </div>
                        
                        <!-- Tabla de items de la sucursal -->
                        <div 
                            v-show="estaExpandida(grupo.id)"
                            class="transition-all duration-200"
                        >
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-1.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Lugar</th>
                                        <th class="px-4 py-1.5 text-center text-[10px] font-semibold text-gray-500 uppercase">Orden</th>
                                        <th class="px-4 py-1.5 text-right text-[10px] font-semibold text-gray-500 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="item in grupo.items" :key="item.IdLugar" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-700">
                                            <i class="fas fa-location-dot text-primary-400 mr-1 text-[10px]"></i>
                                            {{ item.Lugar }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 rounded-full text-xs text-gray-600">
                                                {{ item.Orden }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">
                                            <button @click="editar(item)" class="text-primary-600 hover:text-primary-800 transition p-1 hover:bg-primary-50 rounded" title="Editar">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="grupo.items.length === 0">
                                        <td colspan="3" class="px-4 py-4 text-center text-gray-400 text-xs">
                                            No hay lugares en esta sucursal
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Mensaje cuando no hay datos -->
                    <div v-if="lugaresAgrupados.length === 0" class="py-8 text-center text-gray-400 text-sm">
                        <i class="fas fa-store text-3xl mb-2 block text-gray-300"></i>
                        No hay lugares de venta registrados
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

input[type="number"] {
    appearance: none;
    -moz-appearance: textfield;
}

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.transition-all {
    transition: all 0.2s ease-in-out;
}
</style>