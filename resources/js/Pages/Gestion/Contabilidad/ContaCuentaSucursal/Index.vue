<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    asignaciones: Array,
    cuentas: Array,
    sucursales: Array,
})

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

// Estado para controlar sucursales expandidas/contraídas
const sucursalesExpandidas = ref({})

// Inicializar todas las sucursales como contraídas
onMounted(() => {
    setTimeout(() => {
        asignacionesPorSucursal.value.forEach(grupo => {
            if (sucursalesExpandidas.value[grupo.id] === undefined) {
                sucursalesExpandidas.value[grupo.id] = false
            }
        })
    }, 100)
})

// Alternar expansión/contracción
const toggleSucursal = (sucursalId) => {
    sucursalesExpandidas.value[sucursalId] = !sucursalesExpandidas.value[sucursalId]
}

// Expandir todas
const expandirTodas = () => {
    asignacionesPorSucursal.value.forEach(grupo => {
        sucursalesExpandidas.value[grupo.id] = true
    })
}

// Contraer todas
const contraerTodas = () => {
    asignacionesPorSucursal.value.forEach(grupo => {
        sucursalesExpandidas.value[grupo.id] = false
    })
}

// Filtrar sucursales para el selector rápido
const sucursalesFiltradas = computed(() => {
    if (!busquedaSucursalNueva.value) return props.sucursales || []
    const termino = busquedaSucursalNueva.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) || 
        s.NumeroSucursal?.toString().includes(termino)
    )
})

// Cuentas filtradas para el select
const cuentasFiltradas = computed(() => {
    if (!busquedaCuentaLocal.value) return props.cuentas || []
    const termino = busquedaCuentaLocal.value.toLowerCase()
    return (props.cuentas || []).filter(c => 
        c.Cuenta?.toLowerCase().includes(termino) || 
        c.Descripcion?.toLowerCase().includes(termino)
    )
})

// Agrupar asignaciones por sucursal
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

// Filtrar sucursales por búsqueda
const sucursalesFiltradasGrid = computed(() => {
    if (!busquedaSucursal.value) return asignacionesPorSucursal.value
    const termino = busquedaSucursal.value.toLowerCase()
    return asignacionesPorSucursal.value.filter(grupo => 
        grupo.nombre.toLowerCase().includes(termino) || 
        grupo.numero?.toString().includes(termino)
    )
})

// Obtener el texto de la cuenta relacionada (de la tabla conta_cuenta)
const getCuentaRelacionada = (asignacion) => {
    if (asignacion.cuenta?.Cuenta) {
        return `${asignacion.cuenta.Cuenta} - ${asignacion.cuenta.Descripcion || ''}`
    }
    return 'Sin relación'
}

// Buscar nombre de sucursal por ID
const buscarNombreSucursal = (id) => {
    const sucursal = props.sucursales?.find(s => s.id === id)
    if (!sucursal) return ''
    return `${sucursal.nombre} ${sucursal.NumeroSucursal ? `(N° ${sucursal.NumeroSucursal})` : ''}`
}

// Buscar nombre de cuenta por ID (para el formulario)
const buscarNombreCuenta = (id) => {
    const cuenta = props.cuentas?.find(c => c.id === id)
    if (!cuenta) return ''
    return `${cuenta.Cuenta} - ${cuenta.Descripcion}`
}

// Seleccionar sucursal para nueva asignación
const seleccionarSucursalNueva = (sucursal) => {
    nuevaSucursalId.value = sucursal.id
    busquedaSucursalNueva.value = `${sucursal.nombre} ${sucursal.NumeroSucursal ? `(N° ${sucursal.NumeroSucursal})` : ''}`
}

// Limpiar selección de sucursal
const limpiarSucursalNueva = () => {
    nuevaSucursalId.value = ''
    busquedaSucursalNueva.value = ''
}

// Seleccionar cuenta (guarda IdCuenta)
const seleccionarCuenta = (cuenta) => {
    nuevaCuentaId.value = cuenta.id
    // NO jalamos el nombre automáticamente, el usuario escribe lo que quiere
    busquedaCuentaLocal.value = `${cuenta.Cuenta} - ${cuenta.Descripcion}`
}

// Limpiar selección de cuenta
const limpiarCuenta = () => {
    nuevaCuentaId.value = ''
    busquedaCuentaLocal.value = ''
}

// Limpiar nombre de cuenta manualmente
const limpiarNombreCuenta = () => {
    nuevaCuentaNombre.value = ''
}

// Cerrar dropdowns
const cerrarDropdownSucursal = () => {
    setTimeout(() => {
        if (!nuevaSucursalId.value) {
            busquedaSucursalNueva.value = ''
        }
    }, 200)
}

const cerrarDropdownCuenta = () => {
    setTimeout(() => {
        if (!nuevaCuentaId.value) {
            busquedaCuentaLocal.value = ''
        }
    }, 200)
}

// Agregar nueva asignación
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
        toast?.error('Error', 'Ingrese el nombre de la cuenta')
        return
    }
    if (!nuevaDinamica.value) {
        toast?.error('Error', 'Seleccione la dinámica de la cuenta (D/H)')
        return
    }
    
    agregando.value = true
    try {
        const response = await axios.post('/gestion/conta-cuenta-sucursal', {
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

// Editar dinámica
const editarDinamica = (asignacion) => {
    editandoId.value = asignacion.IdCuentaSucursales
}

const guardarDinamica = async (asignacion) => {
    if (!asignacion.DinamicaCuenta) {
        toast?.error('Error', 'Seleccione la dinámica de la cuenta')
        return
    }
    
    try {
        const response = await axios.put(`/gestion/conta-cuenta-sucursal/${asignacion.IdCuentaSucursales}`, {
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

// Eliminar asignación
const eliminarAsignacion = async (asignacion) => {
    const cuentaTexto = `${asignacion.Cuenta || '-'}`
    if (!confirm(`¿Eliminar la cuenta "${cuentaTexto}" de esta sucursal?`)) return
    
    try {
        const response = await axios.delete(`/gestion/conta-cuenta-sucursal/${asignacion.IdCuentaSucursales}`)
        if (response.data.success) {
            toast?.success('Éxito', 'Cuenta desasignada correctamente')
            window.location.reload()
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al eliminar')
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-3 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-link text-primary-600"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">Cuentas por Sucursal</h1>
                            <p class="text-xs text-gray-500">Asignación de cuentas contables a sucursales</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario para nueva asignación -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
                    <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-plus-circle text-primary-500 text-xs"></i> Nueva Asignación
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <!-- Sucursal -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Sucursal *</label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="busquedaSucursalNueva" 
                                    @focus="busquedaSucursalNueva = ''" 
                                    @blur="cerrarDropdownSucursal" 
                                    placeholder="Buscar sucursal..." 
                                    class="w-full border rounded-md px-2 py-1.5 text-xs"
                                    :class="{'border-primary-500 bg-primary-50': nuevaSucursalId}"
                                >
                                <div v-if="nuevaSucursalId" class="absolute right-2 top-1/2 -translate-y-1/2">
                                    <button @click="limpiarSucursalNueva" class="text-gray-400 hover:text-red-500" title="Limpiar selección">
                                        <i class="fas fa-times-circle text-xs"></i>
                                    </button>
                                </div>
                                <div v-if="busquedaSucursalNueva && sucursalesFiltradas.length" class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-40 overflow-y-auto">
                                    <div v-for="s in sucursalesFiltradas" :key="s.id" @click="seleccionarSucursalNueva(s)" class="px-2 py-1 hover:bg-gray-100 cursor-pointer text-xs border-b flex justify-between">
                                        <span>{{ s.nombre }}</span>
                                        <span class="text-gray-400 text-[10px]">N° {{ s.NumeroSucursal }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="nuevaSucursalId" class="mt-1 text-xs text-primary-600">
                                <i class="fas fa-check-circle"></i> Sucursal seleccionada: {{ buscarNombreSucursal(nuevaSucursalId) }}
                            </div>
                        </div>

                        <!-- Cuenta de Contabilidad (IdCuenta) -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Cuenta (Contabilidad) *</label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="busquedaCuentaLocal" 
                                    @focus="busquedaCuentaLocal = ''" 
                                    @blur="cerrarDropdownCuenta" 
                                    placeholder="Buscar cuenta por número o descripción..." 
                                    class="w-full border rounded-md px-2 py-1.5 text-xs font-mono"
                                    :class="{'border-primary-500 bg-primary-50': nuevaCuentaId}"
                                >
                                <div v-if="nuevaCuentaId" class="absolute right-2 top-1/2 -translate-y-1/2">
                                    <button @click="limpiarCuenta" class="text-gray-400 hover:text-red-500" title="Limpiar selección">
                                        <i class="fas fa-times-circle text-xs"></i>
                                    </button>
                                </div>
                                <div v-if="busquedaCuentaLocal && cuentasFiltradas.length" class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-40 overflow-y-auto">
                                    <div v-for="c in cuentasFiltradas" :key="c.id" @click="seleccionarCuenta(c)" class="px-2 py-1 hover:bg-gray-100 cursor-pointer text-xs border-b">
                                        <span class="font-mono">{{ c.Cuenta }}</span> - {{ c.Descripcion }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="nuevaCuentaId" class="mt-1 text-xs text-primary-600">
                                <i class="fas fa-check-circle"></i> Cuenta seleccionada: {{ buscarNombreCuenta(nuevaCuentaId) }}
                            </div>
                        </div>

                        <!-- Nombre de Cuenta (campo Cuenta de la tabla - escrito manualmente) -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre de Cuenta *</label>
                            <input 
                                type="text" 
                                v-model="nuevaCuentaNombre" 
                                placeholder="Ej: Ingreso, Caja Banco, etc." 
                                class="w-full border rounded-md px-2 py-1.5 text-sm"
                                :class="{'border-primary-500 bg-primary-50': nuevaCuentaNombre}"
                            >
                            <div v-if="nuevaCuentaNombre" class="mt-1 text-xs text-gray-400">
                                <i class="fas fa-pencil-alt"></i> Escríbelo como prefieras (sin mayúsculas obligatorias)
                            </div>
                        </div>

                        <!-- Dinámica -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Dinámica (D/H) *</label>
                            <select v-model="nuevaDinamica" class="w-full border rounded-md px-2 py-1.5 text-xs">
                                <option value="">Seleccionar</option>
                                <option value="D">D - Debe</option>
                                <option value="H">H - Haber</option>
                            </select>
                        </div>
                    </div>

                    <!-- Botón fuera del grid -->
                    <div class="mt-3 flex justify-end">
                        <button @click="agregarAsignacion" :disabled="agregando" class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-xs hover:bg-primary-700 transition flex items-center gap-1">
                            <i v-if="agregando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-plus"></i>
                            Asignar Cuenta
                        </button>
                    </div>
                </div>

                <!-- Barra de herramientas -->
                <div class="flex justify-between items-center mb-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" v-model="busquedaSucursal" placeholder="Buscar sucursal por nombre o número..." class="w-64 border rounded-md pl-8 pr-3 py-1.5 text-sm">
                    </div>
                    
                    <div class="flex gap-2">
                        <button @click="expandirTodas" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs hover:bg-gray-300 transition flex items-center gap-1">
                            <i class="fas fa-expand-alt"></i> Expandir todas
                        </button>
                        <button @click="contraerTodas" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs hover:bg-gray-300 transition flex items-center gap-1">
                            <i class="fas fa-compress-alt"></i> Contraer todas
                        </button>
                    </div>
                </div>

                <!-- Grupos por sucursal (Acordeón) -->
                <div v-if="sucursalesFiltradasGrid.length > 0" class="space-y-3">
                    <div v-for="grupo in sucursalesFiltradasGrid" :key="grupo.id" class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <!-- Header del grupo -->
                        <div 
                            @click="toggleSucursal(grupo.id)"
                            class="px-4 py-3 bg-primary-50 border-b border-primary-100 cursor-pointer hover:bg-primary-100 transition flex items-center justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <i class="fas" :class="sucursalesExpandidas[grupo.id] ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                <i class="fas fa-store text-primary-500 text-sm"></i>
                                <h2 class="text-sm font-semibold text-primary-800">{{ grupo.nombre }}</h2>
                                <span v-if="grupo.numero" class="text-xs text-primary-500 bg-primary-100 px-2 py-0.5 rounded-full">
                                    N° {{ grupo.numero }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-primary-400">
                                    {{ grupo.asignaciones.length }} cuenta(s) asignada(s)
                                </span>
                            </div>
                        </div>

                        <!-- Cuerpo del grupo (expandible) -->
                        <div v-show="sucursalesExpandidas[grupo.id]" class="overflow-x-auto transition-all duration-300">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cuenta (Nombre)</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cuenta Relacionada (Contabilidad)</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Dinámica</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-16">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="asignacion in grupo.asignaciones" :key="asignacion.IdCuentaSucursales" class="hover:bg-gray-50 transition">
                                        <td class="px-3 py-2 text-sm text-gray-700 font-medium">
                                            {{ asignacion.Cuenta || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-500">
                                            {{ getCuentaRelacionada(asignacion) }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <div v-if="editandoId !== asignacion.IdCuentaSucursales" class="inline-flex items-center gap-1">
                                                <span class="px-2 py-0.5 text-xs rounded-full" :class="asignacion.DinamicaCuenta === 'D' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'">
                                                    {{ asignacion.DinamicaCuenta }}
                                                </span>
                                                <button @click.stop="editarDinamica(asignacion)" class="text-primary-400 hover:text-primary-600" title="Editar dinámica">
                                                    <i class="fas fa-edit text-[10px]"></i>
                                                </button>
                                            </div>
                                            <div v-else class="flex items-center justify-center gap-1">
                                                <select v-model="asignacion.DinamicaCuenta" class="w-16 border rounded px-1 py-0.5 text-xs">
                                                    <option value="D">D</option>
                                                    <option value="H">H</option>
                                                </select>
                                                <button @click.stop="guardarDinamica(asignacion)" class="text-green-600 hover:text-green-800" title="Guardar">
                                                    <i class="fas fa-save text-[10px]"></i>
                                                </button>
                                                <button @click.stop="editandoId = null" class="text-gray-400 hover:text-gray-600" title="Cancelar">
                                                    <i class="fas fa-times text-[10px]"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button @click.stop="eliminarAsignacion(asignacion)" class="text-red-400 hover:text-red-600 transition" title="Desasignar cuenta">
                                                <i class="fas fa-trash-alt text-xs"></i>
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

                <!-- Mensaje sin resultados -->
                <div v-else class="bg-white rounded-lg shadow-sm p-8 text-center">
                    <i class="fas fa-store text-4xl text-gray-300 mb-2 block"></i>
                    <p class="text-gray-500 text-sm">No hay sucursales con cuentas asignadas</p>
                    <p class="text-xs text-gray-400 mt-1">Utilice el formulario superior para asignar cuentas a sucursales</p>
                </div>

                <!-- Footer informativo -->
                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Dinámica de cuenta:</strong> "D" (Debe) para cuentas de activo y gasto, "H" (Haber) para cuentas de pasivo, patrimonio e ingreso.
                </div>
            </div>
        </div>
    </div>
</template>