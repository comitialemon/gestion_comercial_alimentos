<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, inject, watch } from 'vue'
import axios from 'axios'
import CreateTipoContenedor from './CreateTipoContenedor.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    contenedor: {
        type: Object,
        default: null
    },
    sucursales: {
        type: Array,
        default: () => []
    },
    tiposContenedor: {
        type: Array,
        default: () => []
    },
    sucursalActual: {
        type: Number,
        default: null
    }
})

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

// ==================== ESTADO DEL CONTENEDOR ====================
const contenedorId = ref(props.contenedor?.IdContenedor || null)
const estaActivo = ref(props.contenedor?.ActivoInactivo === 1)

// ==================== FORMULARIO CABECERA ====================
const form = ref({
    IdSucursal: props.contenedor?.IdSucursal || props.sucursalActual || '',
    IdTipoContenedor: props.contenedor?.IdTipoContenedor || '',
    CapacidadTotal: props.contenedor?.CapacidadTotal || '',
    EsProductoUnico: props.contenedor?.EsProductoUnico === 1 || props.contenedor?.EsProductoUnico === true,
    Codigo: props.contenedor?.EsProductoUnico ? (props.contenedor?.Codigo || '') : '',
})

// ==================== TIPOS DE CONTENEDOR (LOCAL) ====================
const tiposContenedorLocal = ref([...props.tiposContenedor])

// ==================== MODAL TIPO CONTENEDOR ====================
const modalTipoVisible = ref(false)

const abrirModalTipo = () => {
    modalTipoVisible.value = true
}

const cerrarModalTipo = () => {
    modalTipoVisible.value = false
}

const onTipoCreado = (nuevoTipo) => {
    tiposContenedorLocal.value.push(nuevoTipo)
    form.value.IdTipoContenedor = nuevoTipo.id
    toast?.success('Éxito', `Tipo "${nuevoTipo.nombre}" creado y seleccionado`)
}

// ==================== ESTADOS ====================
const errors = ref({})
const processing = ref(false)

// ==================== COMPUTADOS ====================
const codigoGenerado = computed(() => {
    // Si es producto único, el código lo define el usuario
    if (form.value.EsProductoUnico) {
        return form.value.Codigo || ''
    }

    if (!form.value.IdTipoContenedor || !form.value.CapacidadTotal) return ''

    const tipo = tiposContenedorLocal.value.find(t => t.id === form.value.IdTipoContenedor)
    if (!tipo) return ''

    const nombreTipo = tipo.nombre.toUpperCase()
    return nombreTipo + '-' + parseInt(form.value.CapacidadTotal)
})

const capacidadTotalNumero = computed(() => {
    return parseFloat(form.value.CapacidadTotal) || 0
})

// ✅ Sucursal actual (etiqueta fija del login)
const sucursalActualNombre = computed(() => {
    const idBuscar = props.contenedor?.IdSucursal || props.sucursalActual
    if (!idBuscar) return 'Sin sucursal'
    const suc = (props.sucursales || []).find(s => s.id == idBuscar)
    return suc?.nombre || 'Sin sucursal'
})

const sucursalActualNumero = computed(() => {
    const idBuscar = props.contenedor?.IdSucursal || props.sucursalActual
    if (!idBuscar) return null
    const suc = (props.sucursales || []).find(s => s.id == idBuscar)
    return suc?.numero || null
})

// ✅ ¿Puede guardar?
const puedeGuardar = computed(() => {
    if (!form.value.IdSucursal) return false
    if (!form.value.IdTipoContenedor) return false

    if (form.value.EsProductoUnico) {
        // Producto único: solo necesita el código
        if (!form.value.Codigo || form.value.Codigo.trim() === '') return false
        return true
    }

    // Normal: necesita capacidad
    if (!form.value.CapacidadTotal || parseFloat(form.value.CapacidadTotal) <= 0) return false
    return true
})

// ==================== GUARDAR (crear o actualizar) ====================
const guardar = async () => {
    errors.value = {}

    if (!form.value.IdSucursal) {
        toast?.error('Validación', 'No hay sucursal asignada')
        return
    }
    if (!form.value.IdTipoContenedor) {
        toast?.error('Validación', 'Seleccione un tipo de contenedor')
        return
    }

    if (form.value.EsProductoUnico) {
        if (!form.value.Codigo || form.value.Codigo.trim() === '') {
            toast?.error('Validación', 'Ingrese un nombre/código para el contenedor')
            return
        }
    } else {
        if (!form.value.CapacidadTotal || parseFloat(form.value.CapacidadTotal) <= 0) {
            toast?.error('Validación', 'Ingrese la capacidad máxima')
            return
        }
    }

    processing.value = true

    try {
        const payload = {
            IdSucursal: form.value.IdSucursal,
            IdTipoContenedor: form.value.IdTipoContenedor,
            EsProductoUnico: form.value.EsProductoUnico ? 1 : 0,
        }

        if (form.value.EsProductoUnico) {
            payload.Codigo = form.value.Codigo.trim().toUpperCase()
            payload.CapacidadTotal = 0
        } else {
            payload.CapacidadTotal = form.value.CapacidadTotal
        }

        let response

        if (contenedorId.value) {
            // Actualizar
            response = await axios.put(`/operacion/pedidos/clientes-mayoristas/contenedores/${contenedorId.value}`, payload)
        } else {
            // Crear
            response = await axios.post('/operacion/pedidos/clientes-mayoristas/contenedores', payload)
        }

        if (response.data.success) {
            const nuevoContenedorId = response.data.contenedor?.IdContenedor || contenedorId.value

            // ✅ Si es nuevo, finalizar automáticamente
            if (!contenedorId.value && nuevoContenedorId) {
                try {
                    await axios.post(`/operacion/pedidos/clientes-mayoristas/contenedores/${nuevoContenedorId}/finalizar`)
                } catch (e) {
                    console.warn('No se pudo activar automáticamente:', e)
                }
            }

            toast?.success('Éxito', contenedorId.value ? 'Contenedor actualizado' : 'Contenedor creado y activado')

            // Redirigir al listado
            router.get('/operacion/pedidos/clientes-mayoristas/contenedores')
        }
    } catch (error) {
        console.error('Error:', error)
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            toast?.error('Error de validación', Object.values(errors.value).flat().join(', '))
        } else {
            toast?.error('Error', error.response?.data?.message || 'Error al guardar')
        }
    } finally {
        processing.value = false
    }
}

// ==================== CANCELAR ====================
const cancelar = () => {
    if (form.value.IdTipoContenedor || form.value.CapacidadTotal || form.value.Codigo) {
        if (!confirm('¿Estás seguro de salir? Los cambios no guardados se perderán.')) {
            return
        }
    }
    router.get('/operacion/pedidos/clientes-mayoristas/contenedores')
}

// ==================== WATCH ====================
watch(
    () => props.contenedor,
    (nuevoContenedor) => {
        if (nuevoContenedor) {
            contenedorId.value = nuevoContenedor.IdContenedor
            estaActivo.value = nuevoContenedor.ActivoInactivo === 1

            form.value = {
                IdSucursal: nuevoContenedor.IdSucursal || props.sucursalActual || '',
                IdTipoContenedor: nuevoContenedor.IdTipoContenedor || '',
                CapacidadTotal: nuevoContenedor.CapacidadTotal || '',
                EsProductoUnico: nuevoContenedor.EsProductoUnico === 1 || nuevoContenedor.EsProductoUnico === true,
                Codigo: nuevoContenedor.EsProductoUnico ? (nuevoContenedor.Codigo || '') : '',
            }
        }
    },
    { immediate: true, deep: true }
)

// ✅ Cuando cambia EsProductoUnico, resetear el campo correspondiente
watch(() => form.value.EsProductoUnico, (esUnico) => {
    if (esUnico) {
        // Cambió a producto único: limpiar capacidad
        form.value.CapacidadTotal = ''
        // Si estamos editando y ya era producto único, mantener código
        if (contenedorId.value && props.contenedor?.EsProductoUnico) {
            form.value.Codigo = props.contenedor.Codigo || ''
        } else {
            form.value.Codigo = ''
        }
    } else {
        // Cambió a normal: limpiar código
        form.value.Codigo = ''
    }
})

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)

    // ✅ Asegurar que la sucursal venga del login
    if (!form.value.IdSucursal && props.sucursalActual) {
        form.value.IdSucursal = props.sucursalActual
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-boxes text-indigo-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">
                                {{ contenedorId ? 'Editar Contenedor' : 'Nuevo Contenedor' }}
                            </h1>
                            <p class="text-[10px] text-gray-500">
                                {{ contenedorId ? 'Modifica los datos del contenedor' : 'Completa los datos para crear el contenedor' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button
                            @click="guardar"
                            :disabled="processing || !puedeGuardar"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded text-xs flex items-center gap-1 flex-1 sm:flex-initial justify-center transition disabled:opacity-50"
                        >
                            <i v-if="processing" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ processing ? 'Guardando...' : 'Guardar' }}
                        </button>

                        <button
                            @click="cancelar"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded text-xs transition flex-1 sm:flex-initial"
                        >
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>

                <!-- FORMULARIO CABECERA -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">
                        <i class="fas fa-info-circle text-indigo-500 mr-1"></i>
                        Datos del Contenedor
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                        <!-- ✅ Sucursal como ETIQUETA FIJA -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Sucursal</label>
                            <div class="w-full border border-gray-200 bg-gray-50 rounded-md px-2 py-1.5 text-xs flex items-center gap-2">
                                <i class="fas fa-store text-indigo-500 text-[10px]"></i>
                                <span class="font-medium text-gray-800">
                                    {{ sucursalActualNombre }}
                                </span>
                                <span v-if="sucursalActualNumero" class="text-gray-400 text-[10px]">
                                    (N° {{ sucursalActualNumero }})
                                </span>
                                <i class="fas fa-lock text-gray-300 text-[9px] ml-auto" title="Definida desde el login"></i>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-0.5">Sucursal del login (no editable)</p>
                        </div>

                        <!-- Tipo de Contenedor -->
                        <div>
                            <label class="block text-gray-600 mb-0.5">Tipo de Contenedor *</label>
                            <div class="flex gap-2">
                                <select
                                    v-model="form.IdTipoContenedor"
                                    class="flex-1 border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                    :class="{'border-red-500': errors.IdTipoContenedor}"
                                >
                                    <option value="">Seleccione</option>
                                    <option v-for="tipo in tiposContenedorLocal" :key="tipo.id" :value="tipo.id">
                                        {{ tipo.nombre }}
                                    </option>
                                </select>

                                <button
                                    @click="abrirModalTipo"
                                    type="button"
                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded text-xs flex items-center gap-1 transition flex-shrink-0"
                                    title="Crear nuevo tipo de contenedor"
                                >
                                    <i class="fas fa-plus text-[10px]"></i>
                                    <span class="hidden sm:inline">Nuevo</span>
                                </button>
                            </div>
                            <p v-if="errors.IdTipoContenedor" class="text-red-500 text-[10px] mt-0.5">{{ errors.IdTipoContenedor }}</p>
                        </div>

                        <!-- ✅ Toggle: Normal / Producto Único -->
                        <div class="flex flex-col justify-center">
                            <label class="block text-gray-600 mb-0.5">Modalidad</label>
                            <button
                                type="button"
                                @click="form.EsProductoUnico = !form.EsProductoUnico"
                                class="w-full flex items-center justify-between gap-2 border rounded-md px-3 py-1.5 text-xs transition"
                                :class="form.EsProductoUnico
                                    ? 'bg-purple-50 border-purple-300 text-purple-800'
                                    : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100'"
                            >
                                <span class="flex items-center gap-2">
                                    <i class="fas" :class="form.EsProductoUnico ? 'fa-cube text-purple-500' : 'fa-boxes text-gray-400'"></i>
                                    <span class="font-medium">
                                        {{ form.EsProductoUnico ? 'Producto Único' : 'Contenedor Normal' }}
                                    </span>
                                </span>
                                <span
                                    class="relative inline-flex items-center h-5 w-9 rounded-full transition-colors"
                                    :class="form.EsProductoUnico ? 'bg-purple-500' : 'bg-gray-300'"
                                >
                                    <span
                                        class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition"
                                        :class="form.EsProductoUnico ? 'translate-x-5' : 'translate-x-0.5'"
                                    ></span>
                                </span>
                            </button>
                            <p class="text-[10px] text-gray-400 mt-0.5">
                                {{ form.EsProductoUnico
                                    ? 'Se elige 1 producto al hacer el pedido (cantidad = mínimo)'
                                    : 'Se llenan varios productos hasta la capacidad' }}
                            </p>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- MODO PRODUCTO ÚNICO: solo pide nombre -->
                    <!-- ============================================================ -->
                    <div v-if="form.EsProductoUnico" class="mt-4 border-t border-purple-200 pt-4">
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 mb-3">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-info-circle text-purple-500 text-sm flex-shrink-0 mt-0.5"></i>
                                <div class="text-[11px] text-purple-800">
                                    <p class="font-bold">Contenedor de producto único</p>
                                    <p>Al hacer el pedido, el operador elegirá <strong>1 producto</strong> y la cantidad será automáticamente <strong>el mínimo configurado</strong> en Inventario. Podrá agregar varias "rejillas" del mismo producto.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <!-- Nombre / Código -->
                            <div>
                                <label class="block text-gray-600 mb-0.5">Nombre / Código *</label>
                                <input
                                    type="text"
                                    v-model="form.Codigo"
                                    placeholder="Ej: REJILLA"
                                    class="w-full border rounded-md px-2 py-1.5 text-xs uppercase focus:ring-1 focus:ring-purple-400 focus:outline-none"
                                />
                                <p class="text-[10px] text-gray-400 mt-0.5">Nombre libre (ej: REJILLA, TABLA, BANDEJA)</p>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- MODO NORMAL: pide capacidad -->
                    <!-- ============================================================ -->
                    <div v-else class="mt-4 border-t border-gray-200 pt-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <!-- Capacidad Máxima -->
                            <div>
                                <label class="block text-gray-600 mb-0.5">Capacidad Máxima *</label>
                                <input
                                    type="number"
                                    v-model="form.CapacidadTotal"
                                    step="0.01"
                                    placeholder="0.00"
                                    class="w-full border rounded-md px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none"
                                    :class="{'border-red-500': errors.CapacidadTotal}"
                                />
                                <p v-if="errors.CapacidadTotal" class="text-red-500 text-[10px] mt-0.5">{{ errors.CapacidadTotal }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">Límite máximo de unidades</p>
                            </div>
                        </div>
                    </div>

                    <!-- Código generado / Vista previa -->
                    <div v-if="codigoGenerado" class="mt-3 bg-gray-50 rounded-lg p-2 border border-gray-200">
                        <p class="text-[10px] text-gray-500">
                            {{ form.EsProductoUnico ? 'Código del contenedor' : 'Código generado automáticamente' }}
                        </p>
                        <p class="text-sm font-mono font-bold" :class="form.EsProductoUnico ? 'text-purple-600' : 'text-indigo-600'">
                            {{ codigoGenerado }}
                        </p>
                    </div>

                    <!-- Estado -->
                    <div v-if="contenedorId" class="mt-3 flex items-center gap-2 flex-wrap">
                        <span class="text-xs text-gray-500">Estado:</span>
                        <span v-if="estaActivo" class="px-2 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-0.5"></i> ACTIVO
                        </span>
                        <span v-else class="px-2 py-0.5 text-[10px] rounded-full bg-yellow-100 text-yellow-800">
                            <i class="fas fa-pencil-alt mr-0.5"></i> BORRADOR
                        </span>
                        <span v-if="codigoGenerado" class="text-[10px] text-gray-500 ml-2 font-mono">
                            Código: {{ codigoGenerado }}
                        </span>
                    </div>
                </div>

                <!-- INFO -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-info-circle text-blue-500 text-sm flex-shrink-0 mt-0.5"></i>
                        <div class="text-xs text-blue-700">
                            <p class="font-medium mb-0.5">Siguiente paso:</p>
                            <p>Al guardar, el contenedor quedará <strong>activo</strong> y listo para asignar clientes.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL TIPO CONTENEDOR -->
        <CreateTipoContenedor
            :visible="modalTipoVisible"
            :sucursales="sucursales"
            @close="cerrarModalTipo"
            @created="onTipoCreado"
        />
    </div>
</template>

<style scoped>
.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-spinner {
    -moz-appearance: textfield;
    appearance: textfield;
}

[v-show] {
    transition: all 0.2s ease;
}
</style>