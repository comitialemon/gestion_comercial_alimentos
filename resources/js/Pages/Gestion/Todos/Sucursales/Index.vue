<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import ModalSucursal from './ModalSucursal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: Array,
    plazas: Array
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
const modalOpen = ref(false)
const editando = ref(false)
const sucursalSeleccionada = ref(null)
const plazasExpandidas = ref({})
const searchTerm = ref('')

// ==================== COMPUTED ====================
const sucursalesFiltradas = computed(() => {
    if (!props.sucursales) return []
    if (!searchTerm.value) return props.sucursales
    
    const termino = searchTerm.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.Nombre?.toLowerCase().includes(termino) ||
        s.NumeroSucursal?.toString().includes(termino) ||
        s.Direccion?.toLowerCase().includes(termino) ||
        s.Celular?.includes(termino) ||
        s.plaza?.Plaza?.toLowerCase().includes(termino)
    )
})

const sucursalesPorPlaza = computed(() => {
    const grupos = {}
    
    sucursalesFiltradas.value.forEach(sucursal => {
        const plazaNombre = sucursal.plaza?.Plaza || 'Sin Plaza'
        const plazaId = sucursal.IdPlaza || 'sin-plaza'
        
        if (!grupos[plazaId]) {
            grupos[plazaId] = {
                id: plazaId,
                nombre: plazaNombre,
                sucursales: []
            }
        }
        grupos[plazaId].sucursales.push(sucursal)
    })
    
    return Object.values(grupos).sort((a, b) => a.nombre.localeCompare(b.nombre))
})

const totalEncontradas = computed(() => sucursalesFiltradas.value.length)

// ==================== FUNCIONES ====================
const togglePlaza = (plazaId) => {
    plazasExpandidas.value[plazaId] = !plazasExpandidas.value[plazaId]
}

const isExpanded = (plazaId) => {
    return plazasExpandidas.value[plazaId] !== false
}

const nuevaSucursal = () => {
    sucursalSeleccionada.value = null
    editando.value = false
    modalOpen.value = true
}

const editarSucursal = (sucursal) => {
    sucursalSeleccionada.value = sucursal
    editando.value = true
    modalOpen.value = true
}

const recargarDatos = async () => {
    try {
        await axios.get('/gestion/sucursales/data')
        window.location.reload()
    } catch (error) {
        console.error('Error recargando:', error)
        window.location.reload()
    }
}

const limpiarBusqueda = () => {
    searchTerm.value = ''
}

// ✅ Estados (0 = Activo, 1 = Inactivo)
const estadoTexto = (activo) => {
    return activo === 0 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
}

const activaInactivaRTexto = (valor) => {
    return valor === 0 ? 'Activa' : 'Inactiva'
}

const activaInactivaRClase = (valor) => {
    return valor === 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    
    // Inicializar expansión
    sucursalesPorPlaza.value.forEach(plaza => {
        if (plazasExpandidas.value[plaza.id] === undefined) {
            plazasExpandidas.value[plaza.id] = true
        }
    })
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-store text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Sucursales</h1>
                            <p class="text-[10px] text-gray-500">Administración de sucursales agrupadas por plaza</p>
                        </div>
                    </div>
                    <button @click="nuevaSucursal" 
                        class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition">
                        <i class="fas fa-plus text-[10px]"></i> Nueva Sucursal
                    </button>
                </div>

                <!-- ==================== BUSCADOR ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                        <input 
                            type="text" 
                            v-model="searchTerm" 
                            placeholder="Buscar sucursal por nombre, dirección, celular..." 
                            class="w-full border border-gray-300 rounded-md pl-8 pr-8 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                        />
                        <button 
                            v-if="searchTerm" 
                            @click="limpiarBusqueda"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </div>
                    <div v-if="searchTerm" class="mt-1.5 text-[10px] text-gray-500">
                        <i class="fas fa-filter mr-1"></i>
                        Mostrando {{ totalEncontradas }} de {{ props.sucursales?.length || 0 }} sucursales
                    </div>
                </div>

                <!-- ==================== GRUPOS POR PLAZA ==================== -->
                <div v-if="sucursalesPorPlaza.length > 0" class="space-y-3">
                    <div v-for="plaza in sucursalesPorPlaza" :key="plaza.id" class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                        <!-- Header del grupo -->
                        <div 
                            @click="togglePlaza(plaza.id)" 
                            class="flex items-center justify-between px-3 py-2 bg-primary-50 cursor-pointer hover:bg-primary-100 transition-all duration-200"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <i class="fas fa-chevron-right text-primary-600 text-[10px] transition-transform duration-300 flex-shrink-0" :class="{ 'rotate-90': isExpanded(plaza.id) }"></i>
                                <i class="fas fa-map-marker-alt text-primary-600 text-[10px] flex-shrink-0"></i>
                                <span class="text-xs font-semibold text-primary-800 truncate">{{ plaza.nombre }}</span>
                                <span class="text-[9px] text-primary-500 bg-primary-100 px-1.5 py-0.5 rounded-full flex-shrink-0">
                                    {{ plaza.sucursales.length }}
                                </span>
                            </div>
                        </div>

                        <!-- Contenido del grupo -->
                        <Transition name="expand">
                            <div v-show="isExpanded(plaza.id)">
                                
                                <!-- VISTA MÓVIL (tarjetas) -->
                                <div v-if="isMobile" class="p-2 space-y-2">
                                    <div v-for="sucursal in plaza.sucursales" :key="sucursal.IdClienteSucursal" 
                                        class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-gray-800 truncate">{{ sucursal.Nombre }}</p>
                                                <p class="text-[10px] font-mono text-gray-500">#{{ sucursal.NumeroSucursal }}</p>
                                            </div>
                                            <button @click="editarSucursal(sucursal)" 
                                                class="text-primary-600 hover:text-primary-800 text-[10px] p-1 rounded hover:bg-primary-50 flex-shrink-0">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-2 gap-1 mt-1 text-[9px] text-gray-500">
                                            <div class="truncate" :title="sucursal.Direccion">
                                                <i class="fas fa-map-pin mr-1 w-3 text-center"></i> {{ sucursal.Direccion }}
                                            </div>
                                            <div>
                                                <i class="fas fa-phone mr-1 w-3 text-center"></i> {{ sucursal.Celular }}
                                            </div>
                                            <div>
                                                <i class="fas fa-sort-numeric-up mr-1 w-3 text-center"></i> Orden: {{ sucursal.Orden }}
                                            </div>
                                            <div class="flex gap-1">
                                                <span class="px-1.5 py-0.5 text-[7px] rounded-full border" :class="estadoClase(sucursal.ActivoInactivo)">
                                                    {{ estadoTexto(sucursal.ActivoInactivo) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- VISTA TABLET Y ESCRITORIO (tabla) -->
                                <div v-else class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50 sticky top-0 z-10">
                                            <tr>
                                                <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">N°</th>
                                                <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Nombre</th>
                                                <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase hidden lg:table-cell">Dirección</th>
                                                <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase hidden xl:table-cell">Celular</th>
                                                <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-16">Orden</th>
                                                <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-24">Estado</th>
                                                <th class="px-3 py-1.5 text-right text-[9px] font-medium text-gray-500 uppercase w-12">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="sucursal in plaza.sucursales" :key="sucursal.IdClienteSucursal" class="hover:bg-gray-50 transition">
                                                <td class="px-3 py-1.5 text-[10px] font-mono text-gray-600">{{ sucursal.NumeroSucursal }}</td>
                                                <td class="px-3 py-1.5 text-[10px] text-gray-700 max-w-[150px] truncate" :title="sucursal.Nombre">{{ sucursal.Nombre }}</td>
                                                <td class="px-3 py-1.5 text-[10px] text-gray-500 max-w-[150px] truncate hidden lg:table-cell" :title="sucursal.Direccion">{{ sucursal.Direccion }}</td>
                                                <td class="px-3 py-1.5 text-[10px] text-gray-500 hidden xl:table-cell">{{ sucursal.Celular }}</td>
                                                <td class="px-3 py-1.5 text-[10px] text-center text-gray-500">{{ sucursal.Orden }}</td>
                                                <td class="px-3 py-1.5 text-center">
                                                    <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="estadoClase(sucursal.ActivoInactivo)">
                                                        {{ estadoTexto(sucursal.ActivoInactivo) }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-1.5 text-right">
                                                    <button @click="editarSucursal(sucursal)" class="text-primary-600 hover:text-primary-800 text-[10px] p-1 rounded hover:bg-primary-50" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- ==================== MENSAJES ==================== -->
                <div v-else-if="searchTerm && sucursalesPorPlaza.length === 0" class="bg-white rounded-xl shadow-sm p-8 text-center">
                    <i class="fas fa-search text-3xl text-gray-300 mb-2 block"></i>
                    <p class="text-sm text-gray-500">No se encontraron sucursales con "{{ searchTerm }}"</p>
                    <button @click="limpiarBusqueda" class="inline-block mt-2 text-xs text-primary-600 hover:text-primary-700">
                        <i class="fas fa-eraser"></i> Limpiar búsqueda
                    </button>
                </div>

                <div v-else-if="sucursalesPorPlaza.length === 0" class="bg-white rounded-xl shadow-sm p-8 text-center">
                    <i class="fas fa-store text-3xl text-gray-300 mb-2 block"></i>
                    <p class="text-sm text-gray-500">No hay sucursales registradas</p>
                    <button @click="nuevaSucursal" class="inline-block mt-2 bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs transition">
                        <i class="fas fa-plus text-[10px]"></i> Crear primera sucursal
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL ==================== -->
        <ModalSucursal
            v-model="modalOpen"
            :sucursal="sucursalSeleccionada"
            :plazas="plazas"
            :editando="editando"
            @saved="recargarDatos"
        />
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

.expand-enter-active,
.expand-leave-active {
    transition: all 0.25s ease-out;
    overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
    opacity: 0;
    max-height: 0;
    transform: translateY(-10px);
}

.expand-enter-to,
.expand-leave-from {
    opacity: 1;
    max-height: 2000px;
    transform: translateY(0);
}

.rotate-90 {
    transform: rotate(90deg);
}
</style>