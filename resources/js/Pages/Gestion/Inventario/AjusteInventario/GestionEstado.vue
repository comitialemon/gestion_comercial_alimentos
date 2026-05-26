<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    ajustes: Object,
    filtroEstado: String,
    buscar: String
})

const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')
const cambiando = ref({})
const loading = ref(false)
const isMobile = ref(window.innerWidth < 768)

// Detectar cambios de tamaño de pantalla
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// Modal de confirmación
const modalVisible = ref(false)
const modalData = ref({
    id: null,
    numero: null,
    accion: '',
    nuevoEstado: null
})

// Controlar el toggle manualmente
const toggleSwitch = (ajuste) => {
    if (cambiando.value[ajuste.IdAjustesPrincipal]) return
    const nuevoEstado = ajuste.ActivoInactivo === 1 ? 0 : 1
    abrirModalConfirmacion(ajuste, nuevoEstado)
}

const abrirModalConfirmacion = (ajuste, nuevoEstado) => {
    modalData.value = {
        id: ajuste.IdAjustesPrincipal,
        numero: ajuste.NumeroCorrelativo,
        accion: nuevoEstado === 1 ? 'activar' : 'desactivar',
        nuevoEstado: nuevoEstado
    }
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    modalData.value = { id: null, numero: null, accion: '', nuevoEstado: null }
}

// 🔥 Cambiar estado (Activar/Inactivar)
const ejecutarCambioEstado = async () => {
    if (!modalData.value.id) return
    
    cambiando.value[modalData.value.id] = true
    loading.value = true
    
    try {
        const response = await fetch(`/gestion/inventario/ajustes/${modalData.value.id}/cambiar-estado`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        
        const data = await response.json()
        
        if (data.success) {
            mostrarToast(data.message, 'success')
            
            const params = new URLSearchParams()
            if (estadoFiltro.value) params.append('estado', estadoFiltro.value)
            if (buscador.value) params.append('buscar', buscador.value)
            
            window.location.href = `/gestion/inventario/ajustes/gestion-estado?${params.toString()}`
        } else {
            mostrarToast(data.message, 'error')
            cerrarModal()
        }
    } catch (error) {
        console.error('Error:', error)
        mostrarToast('Error al cambiar el estado', 'error')
        cerrarModal()
    } finally {
        cambiando.value[modalData.value.id] = false
        loading.value = false
    }
}

const mostrarToast = (mensaje, tipo = 'success') => {
    const toastAnterior = document.querySelector('.custom-toast')
    if (toastAnterior) toastAnterior.remove()
    
    const toast = document.createElement('div')
    toast.className = `custom-toast fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white flex items-center gap-2 ${
        tipo === 'success' ? 'bg-green-500' : 'bg-red-500'
    }`
    toast.innerHTML = `<i class="fas ${tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${mensaje}`
    document.body.appendChild(toast)
    
    setTimeout(() => {
        if (toast && toast.remove) toast.remove()
    }, 3000)
}

// APLICAR FILTROS
const aplicarFiltros = () => {
    router.get('/gestion/inventario/ajustes/gestion-estado', {
        estado: estadoFiltro.value || undefined,
        buscar: buscador.value || undefined
    }, {
        preserveState: true,
        replace: true
    })
}

// BUSCAR (con debounce)
let timeoutBuscador
const buscarAjustes = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

// Limpiar búsqueda
const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

// Watch para filtro de estado
watch(estadoFiltro, () => {
    aplicarFiltros()
})

// Formatear moneda
const formatearMonto = (monto) => {
    return Number(monto).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
}

const getEstadoIcono = (activo) => {
    return activo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Contabilizado' : 'Borrador'
}

const getConceptoColor = (concepto) => {
    return concepto === 'INGRESO' ? 'text-emerald-600' : 'text-red-600'
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header Responsive -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-guindo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-guindo-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Gestión de Estados - Ajustes</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">Activar o desactivar ajustes de inventario</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/gestion/inventario/ajustes" class="flex-1 sm:flex-initial bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-list text-[10px]"></i>
                            <span>Listado</span>
                        </Link>
                        <Link href="/gestion/inventario/ajustes/create" class="flex-1 sm:flex-initial bg-guindo-600 hover:bg-guindo-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-plus text-[10px]"></i>
                            <span>Nuevo Ajuste</span>
                        </Link>
                    </div>
                </div>

                <!-- Filtros Responsive - Compactos -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Selector de estado -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" class="border rounded-md px-2 py-1 text-xs w-36 sm:w-40">
                                <option value="">Todos</option>
                                <option value="activos">Contabilizados</option>
                                <option value="inactivos">Borradores</option>
                            </select>
                        </div>
                        
                        <!-- BUSCADOR pequeño -->
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarAjustes"
                                placeholder="N° Ajuste..."
                                class="border rounded-md px-2 py-1 text-xs w-28 sm:w-32"
                            >
                            <button 
                                v-if="buscador" 
                                @click="limpiarBusqueda" 
                                class="text-gray-400 hover:text-gray-600 text-xs"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Resultados de búsqueda -->
                    <div v-if="buscador" class="mt-2 text-[10px] text-gray-500">
                        <span class="font-semibold">{{ buscador }}</span>
                        <span class="ml-2">({{ ajustes.total || 0 }} resultados)</span>
                    </div>
                    
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> Toque el switch para cambiar el estado
                    </div>
                </div>

                <!-- Vista para MÓVIL (tarjetas) -->
                <div v-if="isMobile" class="space-y-3">
                    <div v-for="ajuste in ajustes.data" :key="ajuste.IdAjustesPrincipal" class="bg-white rounded-lg shadow-sm p-3">
                        <!-- Cabecera de tarjeta -->
                        <div class="flex justify-between items-start border-b pb-2 mb-2">
                            <div class="flex flex-col gap-1">
                                <span class="text-xs font-mono font-bold text-guindo-600 bg-guindo-50 px-2 py-0.5 rounded self-start">
                                    N° {{ ajuste.NumeroCorrelativo }}
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <Link v-if="ajuste.ActivoInactivo === 0" :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}/edit`" class="text-guindo-600" title="Editar">
                                    <i class="fas fa-edit text-xs"></i>
                                </Link>
                                <a :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}/pdf`" target="_blank" class="text-red-600" title="PDF">
                                    <i class="fas fa-file-pdf text-sm"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Datos principales -->
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Fecha:</span>
                                <span class="font-medium">{{ ajuste.fecha_formateada || '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Concepto:</span>
                                <span class="font-bold" :class="getConceptoColor(ajuste.ConceptoOperacion)">
                                    {{ ajuste.ConceptoOperacion || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Tipo:</span>
                                <span class="font-medium">{{ ajuste.tipo_operacion?.Detalle || '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Almacén:</span>
                                <span class="font-medium">{{ ajuste.almacen?.Almacen || '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-1 border-t mt-1">
                                <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(ajuste.ActivoInactivo)">
                                    <i :class="getEstadoIcono(ajuste.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                    {{ getEstadoTexto(ajuste.ActivoInactivo) }}
                                </span>
                                
                                <!-- SWITCH PERSONALIZADO -->
                                <div class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(ajuste)">
                                    <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out"
                                        :class="ajuste.ActivoInactivo === 1 ? 'bg-guindo-600' : 'bg-gray-300'">
                                        <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out"
                                            :class="ajuste.ActivoInactivo === 1 ? 'translate-x-[18px]' : 'translate-x-[2px]'">
                                        </div>
                                    </div>
                                    <span class="ml-2 text-[10px]" :class="cambiando[ajuste.IdAjustesPrincipal] ? 'text-gray-400' : (ajuste.ActivoInactivo === 1 ? 'text-green-600' : 'text-gray-500')">
                                        <i v-if="cambiando[ajuste.IdAjustesPrincipal]" class="fas fa-spinner fa-spin"></i>
                                        <span v-else>{{ ajuste.ActivoInactivo === 1 ? 'Activo' : 'Inactivo' }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="ajustes.data?.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-clipboard-list text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-xs text-gray-400">
                            <span v-if="buscador">No hay ajustes que coincidan con "{{ buscador }}"</span>
                            <span v-else>No hay ajustes registrados</span>
                        </p>
                    </div>
                </div>

                <!-- Vista para TABLET Y ESCRITORIO (tabla) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-guindo-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-guindo-700 uppercase">N° Ajuste</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-guindo-700 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-guindo-700 uppercase">Concepto</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-guindo-700 uppercase">Tipo Operación</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-guindo-700 uppercase">Almacén</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-guindo-700 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-guindo-700 uppercase">Cambiar</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-guindo-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="ajuste in ajustes.data" :key="ajuste.IdAjustesPrincipal" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">{{ ajuste.NumeroCorrelativo }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ ajuste.fecha_formateada || '-' }}</td>
                                    <td class="px-3 py-2 text-xs">
                                        <span class="font-bold" :class="getConceptoColor(ajuste.ConceptoOperacion)">
                                            {{ ajuste.ConceptoOperacion || '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-700">{{ ajuste.tipo_operacion?.Detalle || '-' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-700">{{ ajuste.almacen?.Almacen || '-' }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(ajuste.ActivoInactivo)">
                                            <i :class="getEstadoIcono(ajuste.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                            {{ getEstadoTexto(ajuste.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <!-- SWITCH PERSONALIZADO -->
                                        <div class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(ajuste)">
                                            <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out"
                                                :class="ajuste.ActivoInactivo === 1 ? 'bg-guindo-600' : 'bg-gray-300'">
                                                <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out"
                                                    :class="ajuste.ActivoInactivo === 1 ? 'translate-x-[18px]' : 'translate-x-[2px]'">
                                                </div>
                                            </div>
                                            <span class="ml-2 text-[10px]" :class="cambiando[ajuste.IdAjustesPrincipal] ? 'text-gray-400' : (ajuste.ActivoInactivo === 1 ? 'text-green-600' : 'text-gray-500')">
                                                <i v-if="cambiando[ajuste.IdAjustesPrincipal]" class="fas fa-spinner fa-spin"></i>
                                                <span v-else>{{ ajuste.ActivoInactivo === 1 ? 'Activo' : 'Inactivo' }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right space-x-1 whitespace-nowrap">
                                        <Link v-if="ajuste.ActivoInactivo === 0" :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}/edit`" class="text-guindo-600 hover:text-guindo-800 text-xs inline-block" title="Editar">
                                            <i class="fas fa-edit text-xs"></i>
                                        </Link>
                                        <a :href="`/gestion/inventario/ajustes/${ajuste.IdAjustesPrincipal}/pdf`" target="_blank" class="text-red-600 hover:text-red-800 text-xs inline-block" title="Ver PDF">
                                            <i class="fas fa-file-pdf text-sm"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr v-if="ajustes.data?.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-clipboard-list text-2xl mb-1 block"></i>
                                        <span v-if="buscador">No hay ajustes que coincidan con "{{ buscador }}"</span>
                                        <span v-else>No hay ajustes registrados</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación desktop -->
                    <div v-if="ajustes.links && ajustes.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-xs">
                            <div class="text-gray-500">Mostrando {{ ajustes.from || 0 }} a {{ ajustes.to || 0 }} de {{ ajustes.total || 0 }}</div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link v-for="link in ajustes.links" :key="link.label" :href="link.url || '#'" class="px-2 py-0.5 rounded border text-xs" :class="{ 'bg-guindo-600 text-white border-guindo-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paginación móvil -->
                <div v-if="isMobile && ajustes.links && ajustes.links.length > 1" class="mt-3 bg-white rounded-lg shadow-sm p-2">
                    <div class="flex justify-center gap-0.5 flex-wrap">
                        <Link v-for="link in ajustes.links" :key="link.label" :href="link.url || '#'" class="px-2 py-1 rounded border text-xs min-w-[32px] text-center" :class="{ 'bg-guindo-600 text-white border-guindo-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE CONFIRMACIÓN -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarModal">
            <div class="bg-white rounded-xl w-full max-w-[90%] sm:max-w-sm overflow-hidden shadow-xl">
                <div class="p-4 border-b" :class="modalData.accion === 'activar' ? 'bg-green-50' : 'bg-yellow-50'">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0" :class="modalData.accion === 'activar' ? 'bg-green-100' : 'bg-yellow-100'">
                            <i :class="modalData.accion === 'activar' ? 'fas fa-check-circle text-green-600' : 'fas fa-ban text-yellow-600'" class="text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 text-sm sm:text-base">
                                {{ modalData.accion === 'activar' ? 'Activar Ajuste' : 'Desactivar Ajuste' }}
                            </h3>
                            <p class="text-[10px] sm:text-xs text-gray-500">Ajuste N° {{ modalData.numero }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-700 text-center">
                        ¿Estás seguro de <span class="font-bold" :class="modalData.accion === 'activar' ? 'text-green-600' : 'text-red-600'">{{ modalData.accion === 'activar' ? 'ACTIVAR' : 'DESACTIVAR' }}</span> 
                        este ajuste?
                    </p>
                    <p class="text-[10px] sm:text-xs text-gray-400 text-center mt-2">
                        {{ modalData.accion === 'activar' ? 'Al activarlo, el ajuste se marcará como contabilizado.' : 'Al desactivarlo, el ajuste volverá a estado borrador y podrá editarse.' }}
                    </p>
                </div>
                <div class="p-3 sm:p-4 bg-gray-50 flex justify-end gap-2 sm:gap-3">
                    <button @click="cerrarModal" class="px-3 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button @click="ejecutarCambioEstado" :disabled="loading" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition flex items-center gap-2" :class="modalData.accion === 'activar' ? 'bg-green-600 hover:bg-green-700' : 'bg-yellow-600 hover:bg-yellow-700'">
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else :class="modalData.accion === 'activar' ? 'fas fa-check' : 'fas fa-ban'"></i>
                        {{ modalData.accion === 'activar' ? 'Activar' : 'Desactivar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:inline {
        display: inline;
    }
    .xs\:block {
        display: block;
    }
}
</style>