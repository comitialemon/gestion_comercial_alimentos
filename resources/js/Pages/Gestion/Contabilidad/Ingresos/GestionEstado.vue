<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    ingresos: Object,
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

// 🔥 SOLO PERMITIR DESACTIVAR (Activo → Borrador)
const toggleSwitch = (ingreso) => {
    // Si ya está inactivo (borrador), NO se puede activar manualmente
    if (ingreso.ActivoInactivo === 0) {
        mostrarToast('Este ingreso está en estado BORRADOR. Solo se activa al editarlo y guardarlo.', 'warning')
        return
    }
    
    // Solo permite DESACTIVAR (Activo → Borrador)
    if (cambiando.value[ingreso.IdIngreso]) return
    const nuevoEstado = 0 // Siempre desactivar (borrador)
    abrirModalConfirmacion(ingreso, nuevoEstado)
}

const abrirModalConfirmacion = (ingreso, nuevoEstado) => {
    modalData.value = {
        id: ingreso.IdIngreso,
        numero: ingreso.NumeroIngreso,
        accion: 'desactivar', // Siempre desactivar
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
        const response = await fetch(`/gestion/ingresos/${modalData.value.id}/cambiar-estado`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                estado: 0 // Siempre desactivar (borrador)
            })
        })
        
        const data = await response.json()
        
        if (data.success) {
            mostrarToast(data.message, 'success')
            const params = new URLSearchParams()
            if (estadoFiltro.value) params.append('estado', estadoFiltro.value)
            if (buscador.value) params.append('buscar', buscador.value)
            window.location.href = `/gestion/ingresos/gestion-estado?${params.toString()}`
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
    const colores = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500'
    }
    const iconos = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle'
    }
    toast.className = `custom-toast fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white flex items-center gap-2 ${colores[tipo] || 'bg-blue-500'}`
    toast.innerHTML = `<i class="fas ${iconos[tipo] || 'fa-info-circle'}"></i> ${mensaje}`
    document.body.appendChild(toast)
    setTimeout(() => toast.remove(), 4000)
}

const aplicarFiltros = () => {
    router.get('/gestion/ingresos/gestion-estado', {
        estado: estadoFiltro.value || undefined,
        buscar: buscador.value || undefined
    }, {
        preserveState: true,
        replace: true
    })
}

let timeoutBuscador
const buscarIngresos = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => aplicarFiltros(), 500)
}

const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

watch(estadoFiltro, () => aplicarFiltros())

const formatearMonto = (monto) => Number(monto).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
const getEstadoColor = (activo) => activo === 1 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
const getEstadoIcono = (activo) => activo === 1 ? 'fas fa-check-circle' : 'fas fa-pencil-alt'
const getEstadoTexto = (activo) => activo === 1 ? 'Contabilizado' : 'Borrador'

// 🔥 Saber si un ingreso puede ser desactivado (solo si está activo)
const puedeDesactivar = (ingreso) => {
    return ingreso.ActivoInactivo === 1
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-toggle-on text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Gestión de Estados - Ingresos</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">Desactivar comprobantes de ingreso (pasar a Borrador)</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/gestion/ingresos" class="flex-1 sm:flex-initial bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-list text-[10px]"></i>
                            <span>Listado</span>
                        </Link>
                        <Link href="/gestion/ingresos/create" class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-plus text-[10px]"></i>
                            <span>Nuevo Ingreso</span>
                        </Link>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" class="border rounded-md px-2 py-1 text-xs w-36 sm:w-40">
                                <option value="">Todos</option>
                                <option value="activos">Contabilizados</option>
                                <option value="inactivos">Borradores</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarIngresos"
                                placeholder="N° Ingreso..."
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
                    <div v-if="buscador" class="mt-2 text-[10px] text-gray-500">
                        <span class="font-semibold">{{ buscador }}</span>
                        <span class="ml-2">({{ ingresos.total || 0 }} resultados)</span>
                    </div>
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> Solo se pueden desactivar ingresos contabilizados (pasar a Borrador)
                    </div>
                </div>

                <!-- Vista MÓVIL (tarjetas) -->
                <div v-if="isMobile" class="space-y-3">
                    <div v-for="ingreso in ingresos.data" :key="ingreso.IdIngreso" class="bg-white rounded-lg shadow-sm p-3">
                        <div class="flex justify-between items-start border-b pb-2 mb-2">
                            <div class="flex flex-col gap-1">
                                <span class="text-xs font-mono font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded self-start">
                                    N° {{ ingreso.NumeroIngreso }}
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <a :href="`/gestion/ingresos/${ingreso.IdIngreso}/pdf`" target="_blank" class="text-red-600" title="PDF">
                                    <i class="fas fa-file-pdf text-sm"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Fecha:</span>
                                <span class="font-medium">{{ ingreso.fecha_formateada || '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Recibido de:</span>
                                <span class="font-medium truncate max-w-[180px]" :title="ingreso.identificador?.Nombre">
                                    {{ ingreso.identificador?.Nombre || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Glosa:</span>
                                <span class="font-medium truncate max-w-[180px]" :title="ingreso.Glosa">
                                    {{ ingreso.Glosa || '-' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Monto:</span>
                                <span class="font-bold text-primary-600">{{ formatearMonto(ingreso.TotalBolivianos) }} Bs</span>
                            </div>
                            <div class="flex justify-between items-center pt-1 border-t mt-1">
                                <span class="px-1.5 py-0.5 text-[10px] rounded-full" :class="getEstadoColor(ingreso.ActivoInactivo)">
                                    <i :class="getEstadoIcono(ingreso.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                    {{ getEstadoTexto(ingreso.ActivoInactivo) }}
                                </span>
                                
                                <!-- 🔥 SWITCH - Solo permite DESACTIVAR -->
                                <div v-if="puedeDesactivar(ingreso)" class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(ingreso)">
                                    <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out bg-primary-600">
                                        <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out translate-x-[18px]">
                                        </div>
                                    </div>
                                    <span class="ml-2 text-[10px]" :class="cambiando[ingreso.IdIngreso] ? 'text-gray-400' : 'text-green-600'">
                                        <i v-if="cambiando[ingreso.IdIngreso]" class="fas fa-spinner fa-spin"></i>
                                        <span v-else>Activo</span>
                                    </span>
                                </div>
                                <div v-else class="flex items-center gap-1">
                                    <span class="text-[10px] text-gray-400">
                                        <i class="fas fa-lock"></i> Borrador
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="ingresos.data?.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-receipt text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-xs text-gray-400">
                            <span v-if="buscador">No hay ingresos que coincidan con "{{ buscador }}"</span>
                            <span v-else>No hay comprobantes de ingreso</span>
                        </p>
                    </div>
                </div>

                <!-- Vista ESCRITORIO (tabla) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">N° Ingreso</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Recibido de</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Glosa</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Monto</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase">Acción</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">PDF</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="ingreso in ingresos.data" :key="ingreso.IdIngreso" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">{{ ingreso.NumeroIngreso }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ ingreso.fecha_formateada || '-' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-700 max-w-[150px] truncate" :title="ingreso.identificador?.Nombre">
                                        {{ ingreso.identificador?.Nombre || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600 max-w-[200px] truncate" :title="ingreso.Glosa">
                                        {{ ingreso.Glosa || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-right font-semibold text-primary-600">{{ formatearMonto(ingreso.TotalBolivianos) }} Bs</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(ingreso.ActivoInactivo)">
                                            <i :class="getEstadoIcono(ingreso.ActivoInactivo)" class="mr-0.5 text-[8px]"></i>
                                            {{ getEstadoTexto(ingreso.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <!-- 🔥 Solo mostrar switch si está ACTIVO -->
                                        <div v-if="puedeDesactivar(ingreso)" class="relative inline-flex items-center cursor-pointer" @click="toggleSwitch(ingreso)">
                                            <div class="w-9 h-5 rounded-full transition-colors duration-200 ease-in-out bg-primary-600">
                                                <div class="absolute w-4 h-4 bg-white rounded-full top-[2px] transition-transform duration-200 ease-in-out translate-x-[18px]">
                                                </div>
                                            </div>
                                            <span class="ml-2 text-[10px]" :class="cambiando[ingreso.IdIngreso] ? 'text-gray-400' : 'text-green-600'">
                                                <i v-if="cambiando[ingreso.IdIngreso]" class="fas fa-spinner fa-spin"></i>
                                                <span v-else>Activo</span>
                                            </span>
                                        </div>
                                        <span v-else class="text-[10px] text-gray-400">
                                            <i class="fas fa-lock mr-1"></i> Borrador
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <a :href="`/gestion/ingresos/${ingreso.IdIngreso}/pdf`" target="_blank" class="text-red-600 hover:text-red-800" title="PDF">
                                            <i class="fas fa-file-pdf text-sm"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr v-if="ingresos.data?.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-xs">
                                        <i class="fas fa-receipt text-2xl mb-1 block"></i>
                                        <span v-if="buscador">No hay ingresos que coincidan con "{{ buscador }}"</span>
                                        <span v-else>No hay comprobantes de ingreso</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación desktop -->
                    <div v-if="ingresos.links && ingresos.links.length > 1" class="px-3 py-2 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-xs">
                            <div class="text-gray-500">Mostrando {{ ingresos.from || 0 }} a {{ ingresos.to || 0 }} de {{ ingresos.total || 0 }}</div>
                            <div class="flex gap-0.5 flex-wrap justify-center">
                                <Link v-for="link in ingresos.links" :key="link.label" :href="link.url || '#'" class="px-2 py-0.5 rounded border text-xs" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paginación móvil -->
                <div v-if="isMobile && ingresos.links && ingresos.links.length > 1" class="mt-3 bg-white rounded-lg shadow-sm p-2">
                    <div class="flex justify-center gap-0.5 flex-wrap">
                        <Link v-for="link in ingresos.links" :key="link.label" :href="link.url || '#'" class="px-2 py-1 rounded border text-xs min-w-[32px] text-center" :class="{ 'bg-primary-600 text-white border-primary-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación (SOLO DESACTIVAR) -->
        <div v-if="modalVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrarModal">
            <div class="bg-white rounded-xl w-full max-w-[90%] sm:max-w-sm overflow-hidden shadow-xl">
                <div class="p-4 border-b bg-yellow-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-yellow-100">
                            <i class="fas fa-ban text-yellow-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 text-sm sm:text-base">Desactivar Ingreso</h3>
                            <p class="text-[10px] sm:text-xs text-gray-500">Ingreso N° {{ modalData.numero }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    <p class="text-xs sm:text-sm text-gray-700 text-center">
                        ¿Estás seguro de <span class="font-bold text-red-600">DESACTIVAR</span> este ingreso?
                    </p>
                    <p class="text-[10px] sm:text-xs text-gray-400 text-center mt-2">
                        Al desactivarlo, el ingreso volverá a estado BORRADOR y podrá editarse.
                    </p>
                </div>
                <div class="p-3 sm:p-4 bg-gray-50 flex justify-end gap-2 sm:gap-3">
                    <button @click="cerrarModal" class="px-3 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button @click="ejecutarCambioEstado" :disabled="loading" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs text-white transition flex items-center gap-2 bg-yellow-600 hover:bg-yellow-700">
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-ban"></i>
                        Desactivar
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