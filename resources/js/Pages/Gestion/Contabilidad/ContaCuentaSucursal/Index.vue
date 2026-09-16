<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import axios from 'axios'
import ModalConfirmacion from './components/ModalConfirmacion.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    asignaciones: Array,
    cuentas: Array,
    sucursales: Array,
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
const loading = ref(false)
const busquedaSucursal = ref('')
const nuevaSucursalId = ref('')
const nuevaCuentaId = ref('')
const nuevaCuentaNombre = ref('')
const nuevaDinamica = ref('')
const busquedaCuentaLocal = ref('')
const busquedaSucursalNueva = ref('')
const agregando = ref(false)
const editandoId = ref(null)

// Modal confirmación
const modalConfirmVisible = ref(false)
const elementoAEliminar = ref(null)
const confirmandoEliminacion = ref(false)

// Expansión de sucursales
const sucursalesExpandidas = ref({})

// ==================== COMPUTED ====================
const mensajeEliminar = computed(() => {
    if (!elementoAEliminar.value) return '¿Estás seguro de que deseas eliminar este elemento?'
    return `¿Estás seguro de que deseas desasignar la cuenta "${elementoAEliminar.value.Cuenta || ''}" de esta sucursal?`
})

const sucursalesFiltradas = computed(() => {
    if (!busquedaSucursalNueva.value) return props.sucursales || []
    const termino = busquedaSucursalNueva.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.NumeroSucursal?.toString().includes(termino)
    )
})

const cuentasFiltradas = computed(() => {
    if (!busquedaCuentaLocal.value) return props.cuentas || []
    const termino = busquedaCuentaLocal.value.toLowerCase()
    return (props.cuentas || []).filter(c => 
        c.Cuenta?.toLowerCase().includes(termino) || 
        c.Descripcion?.toLowerCase().includes(termino)
    )
})

const asignacionesPorSucursal = computed(() => {
    const grupos = {}
    
    props.asignaciones.forEach(asignacion => {
        const sucursalId = asignacion.IdSucursal
        const sucursalNombre = asignacion.sucursal?.Nombre || 'Sin sucursal'
        const sucursalNumero = asignacion.sucursal?.NumeroSucursal
        
        if (!grupos[sucursalId]) {
            grupos[sucursalId] = {
                id: sucursalId,
                nombre: sucursalNombre,
                numero: sucursalNumero,
                asignaciones: []
            }
        }
        grupos[sucursalId].asignaciones.push(asignacion)
    })
    
    return Object.values(grupos).sort((a, b) => a.nombre.localeCompare(b.nombre))
})

const sucursalesFiltradasGrid = computed(() => {
    if (!busquedaSucursal.value) return asignacionesPorSucursal.value
    const termino = busquedaSucursal.value.toLowerCase()
    return asignacionesPorSucursal.value.filter(grupo => 
        grupo.nombre.toLowerCase().includes(termino) || 
        grupo.numero?.toString().includes(termino)
    )
})

// ==================== FUNCIONES ====================
const toggleSucursal = (sucursalId) => {
    sucursalesExpandidas.value[sucursalId] = !sucursalesExpandidas.value[sucursalId]
}

const expandirTodas = () => {
    asignacionesPorSucursal.value.forEach(grupo => {
        sucursalesExpandidas.value[grupo.id] = true
    })
}

const contraerTodas = () => {
    asignacionesPorSucursal.value.forEach(grupo => {
        sucursalesExpandidas.value[grupo.id] = false
    })
}

const getCuentaRelacionada = (asignacion) => {
    if (asignacion.cuenta?.Cuenta) {
        return `${asignacion.cuenta.Cuenta} - ${asignacion.cuenta.Descripcion || ''}`
    }
    return 'Sin relación'
}

const buscarNombreSucursal = (id) => {
    const sucursal = props.sucursales?.find(s => s.id === id)
    if (!sucursal) return ''
    return `${sucursal.nombre} ${sucursal.NumeroSucursal ? `(N° ${sucursal.NumeroSucursal})` : ''}`
}

const buscarNombreCuenta = (id) => {
    const cuenta = props.cuentas?.find(c => c.id === id)
    if (!cuenta) return ''
    return `${cuenta.Cuenta} - ${cuenta.Descripcion}`
}

const seleccionarSucursalNueva = (sucursal) => {
    nuevaSucursalId.value = sucursal.id
    busquedaSucursalNueva.value = `${sucursal.nombre} ${sucursal.NumeroSucursal ? `(N° ${sucursal.NumeroSucursal})` : ''}`
}

const limpiarSucursalNueva = () => {
    nuevaSucursalId.value = ''
    busquedaSucursalNueva.value = ''
}

const seleccionarCuenta = (cuenta) => {
    nuevaCuentaId.value = cuenta.id
    busquedaCuentaLocal.value = `${cuenta.Cuenta} - ${cuenta.Descripcion}`
}

const limpiarCuenta = () => {
    nuevaCuentaId.value = ''
    busquedaCuentaLocal.value = ''
}

const cerrarDropdownSucursal = () => {
    setTimeout(() => {
        if (!nuevaSucursalId.value) busquedaSucursalNueva.value = ''
    }, 200)
}

const cerrarDropdownCuenta = () => {
    setTimeout(() => {
        if (!nuevaCuentaId.value) busquedaCuentaLocal.value = ''
    }, 200)
}

const agregarAsignacion = async () => {
    if (!nuevaSucursalId.value) {
        toast?.error('Error', 'Seleccione una sucursal')
        return
    }
    if (!nuevaCuentaId.value) {
        toast?.error('Error', 'Seleccione una cuenta de contabilidad')
        return
    }
    if (!nuevaCuentaNombre.value.trim()) {
        toast?.error('Error', 'Seleccione el tipo de cuenta')
        return
    }
    if (!nuevaDinamica.value) {
        toast?.error('Error', 'Seleccione la dinámica de la cuenta (D/H)')
        return
    }
    
    agregando.value = true
    try {
        const response = await axios.post('/gestion/contabilidad/conta-cuenta-sucursal', {
            IdCuenta: nuevaCuentaId.value,
            Cuenta: nuevaCuentaNombre.value,
            DinamicaCuenta: nuevaDinamica.value.toUpperCase(),
            IdSucursal: nuevaSucursalId.value,
        })
        
        if (response.data.success) {
            toast?.success('Éxito', 'Cuenta asignada correctamente')
            window.location.reload()
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al asignar')
    } finally {
        agregando.value = false
    }
}

const editarDinamica = (asignacion) => {
    editandoId.value = asignacion.IdCuentaSucursales
}

const guardarDinamica = async (asignacion) => {
    if (!asignacion.DinamicaCuenta) {
        toast?.error('Error', 'Seleccione la dinámica de la cuenta')
        return
    }
    
    try {
        const response = await axios.put(`/gestion/contabilidad/conta-cuenta-sucursal/${asignacion.IdCuentaSucursales}`, {
            DinamicaCuenta: asignacion.DinamicaCuenta.toUpperCase(),
        })
        
        if (response.data.success) {
            editandoId.value = null
            toast?.success('Éxito', 'Dinámica actualizada correctamente')
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al actualizar')
    }
}

const mostrarModalEliminar = (asignacion) => {
    elementoAEliminar.value = asignacion
    modalConfirmVisible.value = true
}

const confirmarEliminacion = async () => {
    if (!elementoAEliminar.value) return
    
    confirmandoEliminacion.value = true
    try {
        const response = await axios.delete(`/gestion/contabilidad/conta-cuenta-sucursal/${elementoAEliminar.value.IdCuentaSucursales}`)
        if (response.data.success) {
            modalConfirmVisible.value = false
            toast?.success('Éxito', 'Cuenta desasignada correctamente')
            setTimeout(() => window.location.reload(), 500)
        }
    } catch (error) {
        modalConfirmVisible.value = false
        toast?.error('Error', error.response?.data?.message || 'Error al eliminar')
    } finally {
        confirmandoEliminacion.value = false
        elementoAEliminar.value = null
    }
}

const cancelarEliminacion = () => {
    modalConfirmVisible.value = false
    elementoAEliminar.value = null
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    
    setTimeout(() => {
        asignacionesPorSucursal.value.forEach(grupo => {
            if (sucursalesExpandidas.value[grupo.id] === undefined) {
                sucursalesExpandidas.value[grupo.id] = false
            }
        })
    }, 100)
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
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-link text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Cuentas por Sucursal</h1>
                        <p class="text-xs text-gray-500">Asignación de cuentas contables a sucursales</p>
                    </div>
                </div>

                <!-- ==================== FORMULARIO ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4 border border-primary-200">
                    <h2 class="text-xs font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                        <i class="fas fa-plus-circle text-primary-500 text-[10px]"></i> Nueva Asignación
                    </h2>
                    
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Sucursal -->
                        <div class="flex-1 min-w-[160px] max-w-[220px]">
                            <label class="text-[10px] font-medium text-gray-500 block mb-0.5">Sucursal <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="busquedaSucursalNueva" 
                                    @focus="busquedaSucursalNueva = ''" 
                                    @blur="cerrarDropdownSucursal" 
                                    placeholder="Buscar sucursal..." 
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    :class="{'border-primary-500 bg-primary-50': nuevaSucursalId}"
                                >
                                <div v-if="nuevaSucursalId" class="absolute right-2 top-1/2 -translate-y-1/2">
                                    <button @click="limpiarSucursalNueva" class="text-gray-400 hover:text-red-500" title="Limpiar">
                                        <i class="fas fa-times-circle text-[10px]"></i>
                                    </button>
                                </div>
                                <div v-if="busquedaSucursalNueva && sucursalesFiltradas.length" class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-40 overflow-y-auto">
                                    <div v-for="s in sucursalesFiltradas" :key="s.id" @click="seleccionarSucursalNueva(s)" class="px-2.5 py-1.5 hover:bg-primary-50 cursor-pointer text-xs border-b border-gray-100 last:border-b-0 flex justify-between">
                                        <span>{{ s.nombre }}</span>
                                        <span class="text-gray-400 text-[9px]">N° {{ s.NumeroSucursal }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="nuevaSucursalId" class="mt-0.5 text-[9px] text-primary-600">
                                <i class="fas fa-check-circle"></i> {{ buscarNombreSucursal(nuevaSucursalId) }}
                            </div>
                        </div>

                        <!-- Cuenta -->
                        <div class="flex-1 min-w-[160px] max-w-[220px]">
                            <label class="text-[10px] font-medium text-gray-500 block mb-0.5">Cuenta <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="busquedaCuentaLocal" 
                                    @focus="busquedaCuentaLocal = ''" 
                                    @blur="cerrarDropdownCuenta" 
                                    placeholder="Buscar cuenta..." 
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm font-mono focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    :class="{'border-primary-500 bg-primary-50': nuevaCuentaId}"
                                >
                                <div v-if="nuevaCuentaId" class="absolute right-2 top-1/2 -translate-y-1/2">
                                    <button @click="limpiarCuenta" class="text-gray-400 hover:text-red-500" title="Limpiar">
                                        <i class="fas fa-times-circle text-[10px]"></i>
                                    </button>
                                </div>
                                <div v-if="busquedaCuentaLocal && cuentasFiltradas.length" class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-40 overflow-y-auto">
                                    <div v-for="c in cuentasFiltradas" :key="c.id" @click="seleccionarCuenta(c)" class="px-2.5 py-1.5 hover:bg-primary-50 cursor-pointer text-xs border-b border-gray-100 last:border-b-0">
                                        <span class="font-mono font-semibold">{{ c.Cuenta }}</span> - {{ c.Descripcion }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="nuevaCuentaId" class="mt-0.5 text-[9px] text-primary-600">
                                <i class="fas fa-check-circle"></i> {{ buscarNombreCuenta(nuevaCuentaId) }}
                            </div>
                        </div>

                        <!-- Tipo de Cuenta -->
                        <div class="min-w-[120px]">
                            <label class="text-[10px] font-medium text-gray-500 block mb-0.5">Tipo <span class="text-red-500">*</span></label>
                            <select v-model="nuevaCuentaNombre" class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" :class="{'border-primary-500 bg-primary-50': nuevaCuentaNombre}">
                                <option value="">Seleccione</option>
                                <option value="Ingreso">Ingreso</option>
                                <option value="Egreso">Egreso</option>
                                <option value="CuentaSucursal">CuentaSucursal</option>
                            </select>
                        </div>

                        <!-- Dinámica -->
                        <div class="min-w-[100px]">
                            <label class="text-[10px] font-medium text-gray-500 block mb-0.5">Dinámica <span class="text-red-500">*</span></label>
                            <select v-model="nuevaDinamica" class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="">Seleccionar</option>
                                <option value="D">D - Debe</option>
                                <option value="H">H - Haber</option>
                            </select>
                        </div>

                        <!-- Botón -->
                        <div class="flex gap-1.5 ml-auto">
                            <button @click="agregarAsignacion" :disabled="agregando" class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1.5 disabled:opacity-50">
                                <i v-if="agregando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-plus text-[10px]"></i>
                                Asignar Cuenta
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="relative flex-1 min-w-[200px] max-w-[300px]">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                            <input type="text" v-model="busquedaSucursal" placeholder="Buscar sucursal..." 
                                class="w-full border border-gray-300 rounded-md pl-7 pr-3 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                        </div>
                        
                        <div class="flex gap-1.5">
                            <button @click="expandirTodas" class="px-2.5 py-1 bg-gray-200 text-gray-700 rounded-md text-[10px] hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-expand-alt text-[9px]"></i> Expandir
                            </button>
                            <button @click="contraerTodas" class="px-2.5 py-1 bg-gray-200 text-gray-700 rounded-md text-[10px] hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-compress-alt text-[9px]"></i> Contraer
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== LISTA DE SUCURSALES ==================== -->
                <div v-if="sucursalesFiltradasGrid.length > 0" class="space-y-3">
                    <div v-for="grupo in sucursalesFiltradasGrid" :key="grupo.id" class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                        <!-- Header del grupo -->
                        <div 
                            @click="toggleSucursal(grupo.id)"
                            class="px-3 py-2 bg-primary-50 border-b border-primary-100 cursor-pointer hover:bg-primary-100 transition flex flex-wrap items-center justify-between gap-2"
                        >
                            <div class="flex items-center gap-2">
                                <i class="fas text-primary-600 text-[10px] transition-transform" :class="sucursalesExpandidas[grupo.id] ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                <i class="fas fa-store text-primary-500 text-sm"></i>
                                <h2 class="text-sm font-semibold text-primary-800">{{ grupo.nombre }}</h2>
                                <span v-if="grupo.numero" class="text-[9px] text-primary-500 bg-primary-100 px-1.5 py-0.5 rounded-full">
                                    N° {{ grupo.numero }}
                                </span>
                            </div>
                            <span class="text-[10px] text-primary-400">
                                {{ grupo.asignaciones.length }} cuenta(s)
                            </span>
                        </div>

                        <!-- Tabla de asignaciones -->
                        <div v-show="sucursalesExpandidas[grupo.id]" class="overflow-x-auto transition-all duration-300">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Cuenta</th>
                                        <th class="px-3 py-1.5 text-left text-[9px] font-medium text-gray-500 uppercase">Cuenta Relacionada</th>
                                        <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-24">Dinámica</th>
                                        <th class="px-3 py-1.5 text-center text-[9px] font-medium text-gray-500 uppercase w-16">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="asignacion in grupo.asignaciones" :key="asignacion.IdCuentaSucursales" class="hover:bg-gray-50 transition">
                                        <td class="px-3 py-2 text-xs text-gray-700 font-medium">
                                            {{ asignacion.Cuenta || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-500">
                                            {{ getCuentaRelacionada(asignacion) }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <div v-if="editandoId !== asignacion.IdCuentaSucursales" class="inline-flex items-center gap-1">
                                                <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="asignacion.DinamicaCuenta === 'D' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700'">
                                                    {{ asignacion.DinamicaCuenta }}
                                                </span>
                                                <button @click.stop="editarDinamica(asignacion)" class="text-primary-400 hover:text-primary-600" title="Editar dinámica">
                                                    <i class="fas fa-edit text-[9px]"></i>
                                                </button>
                                            </div>
                                            <div v-else class="flex items-center justify-center gap-1">
                                                <select v-model="asignacion.DinamicaCuenta" class="w-14 border border-gray-300 rounded px-1 py-0.5 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none">
                                                    <option value="D">D</option>
                                                    <option value="H">H</option>
                                                </select>
                                                <button @click.stop="guardarDinamica(asignacion)" class="text-emerald-600 hover:text-emerald-800" title="Guardar">
                                                    <i class="fas fa-save text-[10px]"></i>
                                                </button>
                                                <button @click.stop="editandoId = null" class="text-gray-400 hover:text-gray-600" title="Cancelar">
                                                    <i class="fas fa-times text-[10px]"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button @click.stop="mostrarModalEliminar(asignacion)" class="text-red-400 hover:text-red-600 transition text-xs" title="Desasignar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="grupo.asignaciones.length === 0">
                                        <td colspan="4" class="px-3 py-6 text-center text-gray-400 text-xs">
                                            No hay cuentas asignadas a esta sucursal
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sin resultados -->
                <div v-else class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-store text-3xl mb-2 block"></i>
                    <p class="text-sm font-medium">No hay sucursales con cuentas asignadas</p>
                    <p class="text-xs mt-1">Utilice el formulario superior para asignar cuentas a sucursales</p>
                </div>

                <!-- ==================== FOOTER INFO ==================== -->
                <div class="mt-3 p-2.5 bg-blue-50 rounded-xl border border-blue-100 text-xs text-blue-700 flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5 text-blue-500 text-[10px]"></i>
                    <div>
                        <span class="font-medium">Dinámica de cuenta:</span>
                        <span class="text-[11px] ml-1">"D" (Debe) para cuentas de activo y gasto, "H" (Haber) para cuentas de pasivo, patrimonio e ingreso.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL DE CONFIRMACIÓN ==================== -->
        <ModalConfirmacion
            v-model="modalConfirmVisible"
            titulo="¿Desasignar cuenta?"
            :mensaje="mensajeEliminar"
            @confirm="confirmarEliminacion"
            @cancel="cancelarEliminacion"
        >
            <div class="text-xs text-gray-500 bg-yellow-50 p-2 rounded border border-yellow-200">
                <i class="fas fa-info-circle text-yellow-600"></i>
                Esta acción no elimina la cuenta contable, solo la desasigna de esta sucursal.
            </div>
        </ModalConfirmacion>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
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