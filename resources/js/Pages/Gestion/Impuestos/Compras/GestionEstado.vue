<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    compras: Object,
    filtroEstado: String,
    buscar: String
})

const estadoFiltro = ref(props.filtroEstado || '')
const buscador = ref(props.buscar || '')
const cambiando = ref({})
const loading = ref(false)
const isMobile = ref(window.innerWidth < 768)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

const modalVisible = ref(false)
const modalData = ref({ id: null, numero: null, accion: '', nuevoEstado: null })

const toggleSwitch = (compra) => {
    if (cambiando.value[compra.IdCompras]) return
    const nuevoEstado = compra.ActivoInactivo === 1 ? 0 : 1
    abrirModalConfirmacion(compra, nuevoEstado)
}

const abrirModalConfirmacion = (compra, nuevoEstado) => {
    modalData.value = {
        id: compra.IdCompras,
        numero: compra.NumeroCorrelativo,
        accion: nuevoEstado === 1 ? 'activar' : 'desactivar',
        nuevoEstado: nuevoEstado
    }
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    modalData.value = { id: null, numero: null, accion: '', nuevoEstado: null }
}

const ejecutarCambioEstado = async () => {
    if (!modalData.value.id) return
    
    cambiando.value[modalData.value.id] = true
    loading.value = true
    
    try {
        const response = await fetch(`/gestion/compras/${modalData.value.id}/cambiar-estado`, {
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
            window.location.href = `/gestion/compras/gestion-estado?${params.toString()}`
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
    setTimeout(() => toast.remove(), 3000)
}

const aplicarFiltros = () => {
    router.get('/gestion/compras/gestion-estado', {
        estado: estadoFiltro.value || undefined,
        buscar: buscador.value || undefined
    }, {
        preserveState: true,
        replace: true
    })
}

let timeoutBuscador
const buscarCompras = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => aplicarFiltros(), 500)
}

const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

watch(estadoFiltro, () => aplicarFiltros())

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-green-100 text-green-800' : 'bg-secondary-100 text-secondary-800'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Contabilizada' : 'Borrador'
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-toggle-on text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Gestión de Estados - Compras</h1>
                            <p class="text-[10px] text-gray-500">Activar o desactivar comprobantes de compra</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/gestion/compras" class="flex-1 sm:flex-initial bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1 transition">
                            <i class="fas fa-list text-[10px]"></i>
                            <span>Listado</span>
                        </Link>
                        <Link href="/gestion/compras/create" class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1 transition">
                            <i class="fas fa-plus text-[10px]"></i>
                            <span>Nueva Compra</span>
                        </Link>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" class="border border-gray-200 rounded-lg px-2 py-1 text-xs w-36 sm:w-40 focus:border-primary-400 focus:ring-1 focus:ring-primary-200">
                                <option value="">Todos</option>
                                <option value="activos">Contabilizadas</option>
                                <option value="inactivos">Borradores</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarCompras"
                                placeholder="N° Correlativo..."
                                class="border border-gray-200 rounded-lg px-2 py-1 text-xs w-28 sm:w-32 focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                            >
                            <button v-if="buscador" @click="limpiarBusqueda" class="text-gray-400 hover:text-gray-600 text-xs">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div v-if="buscador" class="mt-2 text-[10px] text-gray-500">
                        <span class="font-semibold">{{ buscador }}</span>
                        <span class="ml-2">({{ compras.total || 0 }} resultados)</span>
                    </div>
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> Toque el switch para cambiar el estado
                    </div>
                </div>

                <!-- Vista MÓVIL -->
                <div v-if="isMobile" class="space-y-3">
                    <div v-for="compra in compras.data" :key="compra.IdCompras" class="bg-white rounded-xl shadow-sm p-3">
                        <div class="flex justify-between items-start border-b border-gray-100 pb-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded-lg">
                                    N° {{ compra.NumeroCorrelativo }}
                                </span>
                            </div>
                            <a :href="`/gestion/compras/${compra.IdCompras}/pdf`" target="_blank" class="text-red-600 hover:text-red-700" title="PDF">
                                <i class="fas fa-file-pdf text-sm"></i>
                            </a>
                        </div>
                        
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Fecha:</span>
                                <span class="font-medium">{{ formatearFecha(compra.FechaIngreso) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Proveedor:</span>
                                <span class="font-medium truncate max-w-[180px]">{{ compra.proveedor?.Nombre || '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Monto:</span>
                                <span class="font-bold text-primary-600">{{ Number(compra.ImporteFactura).toFixed(2) }} Bs</span>
                            </div>
                            <div class="flex justify-between items-center pt-1 border-t border-gray-100 mt-1">
                                <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(compra.ActivoInactivo)">
                                    <i :class="compra.ActivoInactivo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'" class="mr-0.5 text-[8px]"></i>
                                    {{ getEstadoTexto(compra.ActivoInactivo) }}
                                </span>
                                
                                <div class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(compra)">
                                    <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out"
                                        :class="compra.ActivoInactivo === 1 ? 'bg-primary-600' : 'bg-gray-300'">
                                        <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out"
                                            :class="compra.ActivoInactivo === 1 ? 'translate-x-[18px]' : 'translate-x-[2px]'">
                                        </div>
                                    </div>
                                    <span class="ml-2 text-[10px]" :class="cambiando[compra.IdCompras] ? 'text-gray-400' : (compra.ActivoInactivo === 1 ? 'text-green-600' : 'text-gray-500')">
                                        <i v-if="cambiando[compra.IdCompras]" class="fas fa-spinner fa-spin"></i>
                                        <span v-else>{{ compra.ActivoInactivo === 1 ? 'Activo' : 'Inactivo' }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="compras.data?.length === 0" class="bg-white rounded-xl shadow-sm p-8 text-center">
                        <i class="fas fa-box-open text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-sm text-gray-400">No hay compras registradas</p>
                    </div>
                </div>

                <!-- Vista ESCRITORIO -->
                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">N° Correlativo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Proveedor</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Monto</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Cambiar</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">PDF</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="compra in compras.data" :key="compra.IdCompras" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">{{ compra.NumeroCorrelativo }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ formatearFecha(compra.FechaIngreso) }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-700 max-w-[200px] truncate">{{ compra.proveedor?.Nombre || '-' }}</td>
                                    <td class="px-3 py-2 text-xs text-right font-semibold text-primary-600">{{ Number(compra.ImporteFactura).toFixed(2) }} Bs</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(compra.ActivoInactivo)">
                                            <i :class="compra.ActivoInactivo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'" class="mr-0.5 text-[8px]"></i>
                                            {{ getEstadoTexto(compra.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(compra)">
                                            <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out"
                                                :class="compra.ActivoInactivo === 1 ? 'bg-primary-600' : 'bg-gray-300'">
                                                <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out"
                                                    :class="compra.ActivoInactivo === 1 ? 'translate-x-[18px]' : 'translate-x-[2px]'">
                                                </div>
                                            </div>
                                            <span class="ml-2 text-[10px]" :class="cambiando[compra.IdCompras] ? 'text-gray-400' : (compra.ActivoInactivo === 1 ? 'text-green-600' : 'text-gray-500')">
                                                <i v-if="cambiando[compra.IdCompras]" class="fas fa-spinner fa-spin"></i>
                                                <span v-else>{{ compra.ActivoInactivo === 1 ? 'Activo' : 'Inactivo' }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <a :href="`/gestion/compras/${compra.IdCompras}/pdf`" target="_blank" class="text-red-600 hover:text-red-700 transition" title="PDF">
                                            <i class="fas fa-file-pdf text-sm"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr v-if="compras.data?.length === 0">
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                        No hay compras registradas
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="compras.links && compras.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex justify-between items-center text-xs">
                            <div class="text-gray-500">Mostrando {{ compras.from || 0 }} a {{ compras.to || 0 }} de {{ compras.total || 0 }}</div>
                            <div class="flex gap-0.5">
                                <Link v-for="link in compras.links" :key="link.label" :href="link.url || '#'" class="px-2 py-0.5 rounded border text-xs transition" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="isMobile && compras.links && compras.links.length > 1" class="mt-3 bg-white rounded-xl shadow-sm p-2">
                    <div class="flex justify-center gap-0.5 flex-wrap">
                        <Link v-for="link in compras.links" :key="link.label" :href="link.url || '#'" class="px-2 py-1 rounded border text-xs min-w-[32px] text-center transition" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarModal">
            <div class="bg-white rounded-xl w-full max-w-[90%] sm:max-w-sm overflow-hidden shadow-xl">
                <div class="p-4 border-b" :class="modalData.accion === 'activar' ? 'bg-green-50' : 'bg-secondary-50'">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0" :class="modalData.accion === 'activar' ? 'bg-green-100' : 'bg-secondary-100'">
                            <i :class="modalData.accion === 'activar' ? 'fas fa-check-circle text-green-600' : 'fas fa-ban text-secondary-600'" class="text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 text-sm sm:text-base">
                                {{ modalData.accion === 'activar' ? 'Activar Compra' : 'Desactivar Compra' }}
                            </h3>
                            <p class="text-[10px] sm:text-xs text-gray-500">Compra N° {{ modalData.numero }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-700 text-center">
                        ¿Estás seguro de <span class="font-bold" :class="modalData.accion === 'activar' ? 'text-green-600' : 'text-red-600'">{{ modalData.accion === 'activar' ? 'ACTIVAR' : 'DESACTIVAR' }}</span> 
                        esta compra?
                    </p>
                    <p class="text-[10px] sm:text-xs text-gray-400 text-center mt-2">
                        {{ modalData.accion === 'activar' ? 'Al activarla, la compra se marcará como contabilizada.' : 'Al desactivarla, la compra volverá a estado borrador y podrá editarse.' }}
                    </p>
                </div>
                <div class="p-3 sm:p-4 bg-gray-50 flex justify-end gap-2 sm:gap-3">
                    <button @click="cerrarModal" class="px-3 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition">Cancelar</button>
                    <button @click="ejecutarCambioEstado" :disabled="loading" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition flex items-center gap-2" :class="modalData.accion === 'activar' ? 'bg-green-600 hover:bg-green-700' : 'bg-secondary-600 hover:bg-secondary-700'">
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
    .xs\:inline { display: inline; } 
    .xs\:block { display: block; } 
}
</style>