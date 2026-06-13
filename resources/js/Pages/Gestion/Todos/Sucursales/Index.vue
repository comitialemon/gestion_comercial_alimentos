<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import ModalSucursal from './ModalSucursal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: Array,
    plazas: Array,
    categorias: Array
})

// Estado para el modal
const modalOpen = ref(false)
const editando = ref(false)
const sucursalSeleccionada = ref(null)

// Estado para acordeón
const plazasExpandidas = ref({})

// 🔥 BUSCADOR
const searchTerm = ref('')

// Filtrar sucursales por búsqueda
const sucursalesFiltradas = computed(() => {
    if (!props.sucursales) return []
    if (!searchTerm.value) return props.sucursales
    
    const termino = searchTerm.value.toLowerCase()
    return props.sucursales.filter(s => 
        s.Nombre?.toLowerCase().includes(termino) ||
        s.NumeroSucursal?.toString().includes(termino) ||
        s.Direccion?.toLowerCase().includes(termino) ||
        s.Telefono?.includes(termino) ||
        s.Celular?.includes(termino) ||
        s.Categoria?.toLowerCase().includes(termino) ||
        s.plaza?.Plaza?.toLowerCase().includes(termino)
    )
})

// Agrupar sucursales filtradas por plaza
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

// Total de sucursales encontradas
const totalEncontradas = computed(() => {
    return sucursalesFiltradas.value.length
})

// Alternar expansión
const togglePlaza = (plazaId) => {
    plazasExpandidas.value[plazaId] = !plazasExpandidas.value[plazaId]
}

const isExpanded = (plazaId) => {
    return plazasExpandidas.value[plazaId] !== false
}

// Inicializar expansión
sucursalesPorPlaza.value.forEach(plaza => {
    if (plazasExpandidas.value[plaza.id] === undefined) {
        plazasExpandidas.value[plaza.id] = true
    }
})

// Abrir modal para nueva sucursal
const nuevaSucursal = () => {
    sucursalSeleccionada.value = null
    editando.value = false
    modalOpen.value = true
}

// Abrir modal para editar
const editarSucursal = (sucursal) => {
    sucursalSeleccionada.value = sucursal
    editando.value = true
    modalOpen.value = true
}

// Recargar datos después de guardar
const recargarDatos = async () => {
    try {
        const response = await axios.get('/gestion/sucursales/data')
        window.location.reload()
    } catch (error) {
        console.error('Error recargando:', error)
        window.location.reload()
    }
}

// Limpiar búsqueda
const limpiarBusqueda = () => {
    searchTerm.value = ''
}

const estadoTexto = (activo) => {
    return activo === 0 ? 'Activo' : 'Inactivo'
}

const estadoClase = (activo) => {
    return activo === 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
}

const activaInactivaRTexto = (valor) => {
    return valor === 0 ? 'Activa' : 'Inactiva'
}

const activaInactivaRClase = (valor) => {
    return valor === 0 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-store text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">Sucursales</h1>
                            <p class="text-[10px] text-gray-500">Administración de sucursales agrupadas por plaza</p>
                        </div>
                    </div>
                    <button @click="nuevaSucursal" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center gap-1 transition">
                        <i class="fas fa-plus text-[10px]"></i> Nueva Sucursal
                    </button>
                </div>

                <!-- 🔥 BUSCADOR -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input 
                            type="text" 
                            v-model="searchTerm" 
                            placeholder="Buscar por nombre, número, dirección, teléfono, categoría o plaza..." 
                            class="w-full border rounded-lg pl-9 pr-10 py-2 text-sm focus:ring-2 focus:ring-primary-400 focus:outline-none"
                            :style="{ borderColor: `var(--color-primary-300)` }"
                        />
                        <button 
                            v-if="searchTerm" 
                            @click="limpiarBusqueda"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                    <div v-if="searchTerm" class="mt-2 text-xs text-gray-500">
                        <i class="fas fa-filter mr-1"></i>
                        Mostrando {{ totalEncontradas }} de {{ props.sucursales?.length || 0 }} sucursales
                    </div>
                </div>

                <!-- Grupos por Plaza -->
                <div v-if="sucursalesPorPlaza.length > 0" class="space-y-3">
                    <div v-for="plaza in sucursalesPorPlaza" :key="plaza.id" class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <!-- Header del grupo -->
                        <div 
                            @click="togglePlaza(plaza.id)" 
                            class="flex items-center justify-between px-4 py-2.5 bg-primary-50 cursor-pointer hover:bg-primary-100 transition-all duration-200 group"
                        >
                            <div class="flex items-center gap-2">
                                <i class="fas fa-chevron-right text-primary-600 text-[10px] transition-transform duration-300" :class="{ 'rotate-90': isExpanded(plaza.id) }"></i>
                                <i class="fas fa-map-marker-alt text-primary-600 text-xs"></i>
                                <h2 class="text-sm font-semibold text-primary-800">{{ plaza.nombre }}</h2>
                                <span class="text-[10px] text-primary-500 bg-primary-100 px-1.5 py-0.5 rounded-full">
                                    {{ plaza.sucursales.length }} sucursal(es)
                                </span>
                            </div>
                            <i class="fas fa-store text-primary-400 text-xs opacity-50 group-hover:opacity-100 transition-opacity"></i>
                        </div>

                        <!-- Tabla de sucursales -->
                        <Transition name="expand">
                            <div v-show="isExpanded(plaza.id)" class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N°</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Dirección</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Teléfono</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Orden</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Categoría</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Activa/InactivaR</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="sucursal in plaza.sucursales" :key="sucursal.IdClienteSucursal" class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-4 py-2 text-xs font-mono text-gray-900">{{ sucursal.NumeroSucursal }}</td>
                                            <td class="px-4 py-2 text-xs text-gray-700 max-w-[200px] truncate" :title="sucursal.Nombre">{{ sucursal.Nombre }}</td>
                                            <td class="px-4 py-2 text-xs text-gray-500 max-w-[200px] truncate" :title="sucursal.Direccion">{{ sucursal.Direccion }}</td>
                                            <td class="px-4 py-2 text-xs text-gray-500">{{ sucursal.Telefono }}</td>
                                            <td class="px-4 py-2 text-xs text-center text-gray-500">{{ sucursal.Orden }}</td>
                                            <td class="px-4 py-2 text-xs text-center">
                                                <span class="px-1.5 py-0.5 text-[10px] rounded-full bg-blue-100 text-blue-800">
                                                    {{ sucursal.Categoria }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="estadoClase(sucursal.ActivoInactivo)">
                                                    {{ estadoTexto(sucursal.ActivoInactivo) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="activaInactivaRClase(sucursal.ActivaInactivaR)">
                                                    {{ activaInactivaRTexto(sucursal.ActivaInactivaR) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-right">
                                                <button @click="editarSucursal(sucursal)" class="text-primary-600 hover:text-primary-800 text-xs transition" title="Editar">
                                                    <i class="fas fa-edit text-xs"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Mensaje si no hay sucursales con la búsqueda -->
                <div v-else-if="searchTerm && sucursalesPorPlaza.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                    <i class="fas fa-search text-4xl text-gray-300 mb-2 block"></i>
                    <p class="text-gray-500 text-sm">No se encontraron sucursales con "{{ searchTerm }}"</p>
                    <button @click="limpiarBusqueda" class="inline-block mt-3 text-primary-600 hover:text-primary-700 text-sm">
                        <i class="fas fa-eraser"></i> Limpiar búsqueda
                    </button>
                </div>

                <!-- Mensaje si no hay sucursales -->
                <div v-else-if="sucursalesPorPlaza.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                    <i class="fas fa-store text-4xl text-gray-300 mb-2 block"></i>
                    <p class="text-gray-500 text-sm">No hay sucursales registradas</p>
                    <button @click="nuevaSucursal" class="inline-block mt-3 bg-primary-600 hover:bg-primary-700 text-white px-4 py-1.5 rounded-lg text-xs transition">
                        <i class="fas fa-plus text-[10px]"></i> Crear primera sucursal
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <ModalSucursal
            v-model="modalOpen"
            :sucursal="sucursalSeleccionada"
            :plazas="plazas"
            :categorias="categorias"
            :editando="editando"
            @saved="recargarDatos"
        />
    </div>
</template>

<style scoped>
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