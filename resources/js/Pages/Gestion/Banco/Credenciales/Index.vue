<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    credenciales: { type: Array, default: () => [] },
    bancosDisponibles: { type: Array, default: () => [] },
    ambientesDisponibles: { type: Array, default: () => [] },
    urlsDisponibles: { type: Object, default: () => ({}) },
})

const toast = inject('toast')

// ============================================================
// ESTADO
// ============================================================
const mostrarModal = ref(false)
const modoEdicion = ref(false)
const credencialEditando = ref(null)
const probandoConexion = ref(null)
const guardando = ref(false)

const mostrarPassword = ref(false)
const mostrarAesKey = ref(false)
const mostrarApiKey = ref(false)
const mostrarCuenta = ref(false)

const errores = ref({})

// ============================================================
// FORMULARIO
// ============================================================
const form = ref({
    CodigoBanco: 'BECO',
    NombreBanco: 'Banco Económico S.A.',
    Alias: '',
    UrlBase: '',
    Usuario: '',
    Password: '',
    AesKey: '',
    ApiKey: '',
    CuentaCredito: '',
    BranchCode: '',
    MonedaDefault: 'BOB',
    Timeout: 20,
    Reintentos: 3,
    TokenCacheTtl: 1500,
    Ambiente: 'CERTIFICACION',
    ActivoInactivo: true,
})

// ============================================================
// COMPUTED
// ============================================================
const tituloModal = computed(() => modoEdicion.value ? 'Editar Credencial' : 'Nueva Credencial')
const iconoModal = computed(() => modoEdicion.value ? 'fas fa-edit' : 'fas fa-plus')
const textoBotonGuardar = computed(() => modoEdicion.value ? 'Actualizar' : 'Crear')

const totalActivas = computed(() => props.credenciales.filter(c => c.ActivoInactivo).length)
const totalInactivas = computed(() => props.credenciales.filter(c => !c.ActivoInactivo).length)

const esBancoEconomico = computed(() => form.value.CodigoBanco === 'BECO')
const esBancoGanadero = computed(() => form.value.CodigoBanco === 'BGAN')

// ============================================================
// HELPERS
// ============================================================
const colorAmbiente = (ambiente) => {
    return ambiente === 'PRODUCCION'
        ? 'bg-red-100 text-red-700'
        : 'bg-amber-100 text-amber-700'
}

const colorBanco = (codigo) => {
    return {
        'BECO': 'bg-blue-100 text-blue-700 border-blue-300',
        'BGAN': 'bg-emerald-100 text-emerald-700 border-emerald-300',
    }[codigo] || 'bg-gray-100 text-gray-600 border-gray-300'
}

const nombreBanco = (codigo) => {
    return {
        'BECO': 'Banco Económico',
        'BGAN': 'Banco Ganadero',
    }[codigo] || codigo
}

const limpiarErrores = () => {
    errores.value = {}
}

const setErrores = (errors) => {
    errores.value = {}
    Object.keys(errors).forEach(key => {
        errores.value[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key]
    })
}

// ============================================================
// ACCIONES
// ============================================================

// Abrir modal para crear
const abrirModalCrear = () => {
    form.value = {
        CodigoBanco: 'BECO',
        NombreBanco: 'Banco Económico S.A.',
        Alias: '',
        UrlBase: props.urlsDisponibles?.BECO?.CERTIFICACION || '',
        Usuario: '',
        Password: '',
        AesKey: '',
        ApiKey: '',
        CuentaCredito: '',
        BranchCode: '',
        MonedaDefault: 'BOB',
        Timeout: 20,
        Reintentos: 3,
        TokenCacheTtl: 1500,
        Ambiente: 'CERTIFICACION',
        ActivoInactivo: true,
    }
    limpiarErrores()
    modoEdicion.value = false
    credencialEditando.value = null
    mostrarPassword.value = false
    mostrarAesKey.value = false
    mostrarApiKey.value = false
    mostrarCuenta.value = false
    mostrarModal.value = true
}

// Abrir modal para editar
const abrirModalEditar = (cred) => {
    form.value = {
        CodigoBanco: cred.CodigoBanco,
        NombreBanco: cred.NombreBanco,
        Alias: cred.Alias || '',
        UrlBase: cred.UrlBase,
        Usuario: cred.Usuario,
        Password: '',
        AesKey: '',
        ApiKey: '',
        CuentaCredito: '',
        BranchCode: cred.BranchCode || '',
        MonedaDefault: cred.MonedaDefault || 'BOB',
        Timeout: cred.Timeout || 20,
        Reintentos: cred.Reintentos || 3,
        TokenCacheTtl: cred.TokenCacheTtl || 1500,
        Ambiente: cred.Ambiente,
        ActivoInactivo: cred.ActivoInactivo,
    }
    limpiarErrores()
    modoEdicion.value = true
    credencialEditando.value = cred
    mostrarPassword.value = false
    mostrarAesKey.value = false
    mostrarApiKey.value = false
    mostrarCuenta.value = false
    mostrarModal.value = true
}

// Cerrar modal
const cerrarModal = () => {
    mostrarModal.value = false
    limpiarErrores()
}

// Cambiar banco
const cambiarBanco = () => {
    const nombreBancoSeleccionado = props.bancosDisponibles.find(b => b.codigo === form.value.CodigoBanco)
    if (nombreBancoSeleccionado) {
        form.value.NombreBanco = nombreBancoSeleccionado.nombre
    }

    // Autocompletar URL según banco y ambiente
    if (props.urlsDisponibles && props.urlsDisponibles[form.value.CodigoBanco]) {
        const url = props.urlsDisponibles[form.value.CodigoBanco][form.value.Ambiente]
        if (url) {
            form.value.UrlBase = url
        }
    }

    // Limpiar campos que no aplican
    if (form.value.CodigoBanco === 'BECO') {
        form.value.ApiKey = ''
    } else if (form.value.CodigoBanco === 'BGAN') {
        form.value.AesKey = ''
    }
}

// Cambiar ambiente
const cambiarAmbiente = () => {
    if (props.urlsDisponibles && props.urlsDisponibles[form.value.CodigoBanco]) {
        const url = props.urlsDisponibles[form.value.CodigoBanco][form.value.Ambiente]
        if (url) {
            form.value.UrlBase = url
        }
    }
}

// Guardar (crear o editar) con axios
const guardar = async () => {
    limpiarErrores()

    // Validaciones básicas
    if (!form.value.CodigoBanco) return toast?.error('Error', 'Código de banco es obligatorio')
    if (!form.value.NombreBanco) return toast?.error('Error', 'Nombre del banco es obligatorio')
    if (!form.value.UrlBase) return toast?.error('Error', 'URL Base es obligatoria')
    if (!form.value.Usuario) return toast?.error('Error', 'Usuario es obligatorio')

    if (!modoEdicion.value) {
        if (!form.value.Password) return toast?.error('Error', 'Password es obligatorio')
        if (esBancoEconomico.value && !form.value.AesKey) return toast?.error('Error', 'AES Key es obligatoria para Banco Económico')
        if (esBancoGanadero.value && !form.value.ApiKey) return toast?.error('Error', 'X-Api-Key es obligatoria para Banco Ganadero')
        if (!form.value.CuentaCredito) return toast?.error('Error', 'Cuenta de crédito es obligatoria')
    }

    guardando.value = true

    try {
        const url = modoEdicion.value
            ? `/banco-credenciales/${credencialEditando.value.IdCredencial}`
            : '/banco-credenciales'

        const method = modoEdicion.value ? 'put' : 'post'

        const response = await axios[method](url, form.value)

        if (response.data.success) {
            toast?.success(response.data.message)
            cerrarModal()
            router.reload()
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (error) {
        const errors = error.response?.data?.errors || {}
        const message = error.response?.data?.message || 'Error al guardar'

        setErrores(errors)
        toast?.error('Error', message)
    } finally {
        guardando.value = false
    }
}

// Toggle activo
const toggleActivo = async (cred) => {
    try {
        const response = await axios.post(`/banco-credenciales/${cred.IdCredencial}/toggle-activo`)
        if (response.data.success) {
            toast?.success(response.data.message)
            router.reload()
        }
    } catch (error) {
        toast?.error('Error', 'No se pudo cambiar el estado')
    }
}

// Probar conexión
const probarConexion = async (cred) => {
    probandoConexion.value = cred.IdCredencial
    try {
        const response = await axios.post(`/banco-credenciales/${cred.IdCredencial}/probar-conexion`)
        if (response.data.success) {
            toast?.success('Conexión exitosa', response.data.message)
        } else {
            toast?.error('Error de conexión', response.data.message)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'No se pudo probar la conexión')
    } finally {
        probandoConexion.value = null
    }
}

// Eliminar
const eliminar = (cred) => {
    if (!confirm(`¿Eliminar la credencial "${cred.Alias || cred.CodigoBanco}"?\n\nEsta acción no se puede deshacer.`)) return

    router.delete(`/banco-credenciales/${cred.IdCredencial}`, {
        onSuccess: () => {
            toast?.success('Credencial eliminada')
            router.reload()
        },
        onError: (errors) => {
            toast?.error('Error', Object.values(errors).join(', '))
        },
    })
}

// Ciclo de vida
onMounted(() => {
    // Nada extra por ahora
})

onUnmounted(() => {
    // Nada extra por ahora
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4">
            <div class="max-w-7xl mx-auto">

                <!-- ============================================ -->
                <!-- HEADER -->
                <!-- ============================================ -->
                <div class="bg-gradient-to-r from-primary-700 to-primary-800 rounded-lg shadow-md p-4 mb-6 text-white">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-university text-2xl"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold">Credenciales del Banco</h1>
                                <p class="text-xs opacity-75">Configuración de acceso para pagos QR</p>
                            </div>
                        </div>
                        <button
                            @click="abrirModalCrear"
                            class="flex items-center gap-2 px-4 py-2 bg-white text-primary-700 rounded-md text-sm font-medium hover:bg-white/90 transition shadow-sm"
                        >
                            <i class="fas fa-plus"></i>
                            Nueva Credencial
                        </button>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-3 mt-4 pt-4 border-t border-white/20">
                        <div class="text-center">
                            <p class="text-2xl font-bold">{{ credenciales.length }}</p>
                            <p class="text-[10px] opacity-75">Total</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-green-300">{{ totalActivas }}</p>
                            <p class="text-[10px] opacity-75">Activas</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-red-300">{{ totalInactivas }}</p>
                            <p class="text-[10px] opacity-75">Inactivas</p>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- ESTADO VACÍO -->
                <!-- ============================================ -->
                <div v-if="credenciales.length === 0" class="bg-white rounded-lg shadow-md p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-university text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">No hay credenciales configuradas</h3>
                    <p class="text-sm text-gray-500 mb-4">Agrega tu primera credencial para empezar a usar pagos QR</p>
                    <button
                        @click="abrirModalCrear"
                        class="px-4 py-2 bg-primary-600 text-white rounded-md text-sm font-medium hover:bg-primary-700 transition"
                    >
                        <i class="fas fa-plus mr-1"></i>
                        Crear primera credencial
                    </button>
                </div>

                <!-- ============================================ -->
                <!-- GRID DE CREDENCIALES -->
                <!-- ============================================ -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="cred in credenciales"
                        :key="cred.IdCredencial"
                        class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition"
                        :class="{ 'opacity-60': !cred.ActivoInactivo }"
                    >
                        <!-- Header -->
                        <div class="px-4 py-3 border-b" :class="cred.ActivoInactivo ? 'bg-green-50' : 'bg-gray-50'">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-800 truncate">
                                        {{ cred.Alias || cred.CodigoBanco }}
                                    </h3>
                                    <p class="text-xs text-gray-500 truncate">{{ cred.NombreBanco }}</p>
                                </div>
                                <span
                                    class="px-2 py-1 rounded-full text-[10px] font-medium whitespace-nowrap ml-2"
                                    :class="cred.ActivoInactivo ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'"
                                >
                                    {{ cred.ActivoInactivo ? 'ACTIVA' : 'INACTIVA' }}
                                </span>
                            </div>
                        </div>

                        <!-- Cuerpo -->
                        <div class="p-4 space-y-2 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Banco:</span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                    :class="colorBanco(cred.CodigoBanco)"
                                >
                                    {{ nombreBanco(cred.CodigoBanco) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Ambiente:</span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-medium"
                                    :class="colorAmbiente(cred.Ambiente)"
                                >
                                    {{ cred.Ambiente }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Usuario:</span>
                                <span class="font-mono truncate ml-2">{{ cred.Usuario }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Moneda:</span>
                                <span class="font-medium">{{ cred.MonedaDefault }}</span>
                            </div>
                            <div v-if="cred.BranchCode" class="flex justify-between items-center">
                                <span class="text-gray-500">Sucursal:</span>
                                <span class="font-mono">{{ cred.BranchCode }}</span>
                            </div>
                            <div v-if="cred.FechaUltimoUso" class="flex justify-between items-center pt-2 border-t">
                                <span class="text-gray-500">Último uso:</span>
                                <span class="text-[10px]">{{ cred.FechaUltimoUso }}</span>
                            </div>
                            <div v-if="cred.UltimoError" class="bg-red-50 rounded p-2 text-red-600 text-[10px] border border-red-200">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                {{ cred.UltimoError }}
                            </div>
                        </div>

                        <!-- Acciones -->
                        <div class="px-4 py-3 bg-gray-50 border-t flex flex-wrap gap-2">
                            <button
                                @click="probarConexion(cred)"
                                :disabled="probandoConexion === cred.IdCredencial"
                                class="flex-1 px-2 py-1.5 bg-blue-50 text-blue-600 rounded text-[10px] font-medium hover:bg-blue-100 disabled:opacity-50 transition"
                                title="Probar conexión con el banco"
                            >
                                <i v-if="probandoConexion === cred.IdCredencial" class="fas fa-spinner fa-spin mr-1"></i>
                                <i v-else class="fas fa-plug mr-1"></i>
                                Probar
                            </button>
                            <button
                                @click="abrirModalEditar(cred)"
                                class="flex-1 px-2 py-1.5 bg-amber-50 text-amber-600 rounded text-[10px] font-medium hover:bg-amber-100 transition"
                                title="Editar credencial"
                            >
                                <i class="fas fa-edit mr-1"></i>
                                Editar
                            </button>
                            <button
                                @click="toggleActivo(cred)"
                                class="flex-1 px-2 py-1.5 rounded text-[10px] font-medium transition"
                                :class="cred.ActivoInactivo
                                    ? 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    : 'bg-green-50 text-green-600 hover:bg-green-100'"
                                :title="cred.ActivoInactivo ? 'Desactivar' : 'Activar'"
                            >
                                <i :class="cred.ActivoInactivo ? 'fas fa-pause' : 'fas fa-play'" class="mr-1"></i>
                                {{ cred.ActivoInactivo ? 'Desactivar' : 'Activar' }}
                            </button>
                            <button
                                @click="eliminar(cred)"
                                class="px-2 py-1.5 bg-red-50 text-red-600 rounded text-[10px] font-medium hover:bg-red-100 transition"
                                title="Eliminar credencial"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ============================================ -->
        <!-- MODAL CREAR/EDITAR -->
        <!-- ============================================ -->
        <div
            v-if="mostrarModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto"
        >
            <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full my-8">
                <!-- Header modal -->
                <div class="bg-gradient-to-r from-primary-700 to-primary-800 px-6 py-4 text-white rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                                <i :class="iconoModal" class="text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg">{{ tituloModal }}</h3>
                                <p class="text-xs opacity-75">
                                    {{ modoEdicion ? 'Modifica los datos de la credencial' : 'Configura el acceso al banco' }}
                                </p>
                            </div>
                        </div>
                        <button @click="cerrarModal" class="text-white/70 hover:text-white transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">

                    <!-- SECCIÓN: Banco -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <i class="fas fa-university text-primary-600"></i>
                            Datos del Banco
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                    Banco <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.CodigoBanco"
                                    @change="cambiarBanco"
                                    class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                >
                                    <option v-for="banco in bancosDisponibles" :key="banco.codigo" :value="banco.codigo">
                                        {{ banco.codigo }} - {{ banco.nombre }}
                                    </option>
                                </select>
                                <p v-if="errores.CodigoBanco" class="text-red-500 text-[10px] mt-1">{{ errores.CodigoBanco }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                    Nombre del Banco <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.NombreBanco"
                                    type="text"
                                    class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                    placeholder="Nombre del banco"
                                />
                                <p v-if="errores.NombreBanco" class="text-red-500 text-[10px] mt-1">{{ errores.NombreBanco }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Alias (opcional)</label>
                                <input
                                    v-model="form.Alias"
                                    type="text"
                                    class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                    placeholder="Ej: Principal, Respaldo"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                    Ambiente <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.Ambiente"
                                    @change="cambiarAmbiente"
                                    class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                >
                                    <option v-for="amb in ambientesDisponibles" :key="amb.valor" :value="amb.valor">
                                        {{ amb.nombre }}
                                    </option>
                                </select>
                                <p v-if="errores.Ambiente" class="text-red-500 text-[10px] mt-1">{{ errores.Ambiente }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN: Conexión -->
                    <div class="border-t pt-4">
                        <h4 class="text-xs font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <i class="fas fa-link text-primary-600"></i>
                            Conexión
                        </h4>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                URL Base <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.UrlBase"
                                type="url"
                                class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm font-mono focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                placeholder="https://..."
                            />
                            <p v-if="errores.UrlBase" class="text-red-500 text-[10px] mt-1">{{ errores.UrlBase }}</p>
                            <p class="text-[10px] text-gray-400 mt-1">
                                <template v-if="esBancoEconomico">
                                    Certificación: <code>https://apimktdesa.baneco.com.bo/ApiGateway</code> |
                                    Producción: <code>https://apimkt.baneco.com.bo/ApiGateway/</code>
                                </template>
                                <template v-else-if="esBancoGanadero">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    URL proporcionada por el Banco Ganadero
                                </template>
                            </p>
                        </div>
                    </div>

                    <!-- SECCIÓN: Credenciales -->
                    <div class="border-t pt-4">
                        <h4 class="text-xs font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <i class="fas fa-key text-primary-600"></i>
                            Credenciales
                            <span v-if="modoEdicion" class="text-[10px] text-gray-400 font-normal">
                                (dejar vacío para no cambiar)
                            </span>
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                    Usuario <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.Usuario"
                                    type="text"
                                    class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm font-mono focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                    placeholder="usuario_banco"
                                />
                                <p v-if="errores.Usuario" class="text-red-500 text-[10px] mt-1">{{ errores.Usuario }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                    Password
                                    <span v-if="!modoEdicion" class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="form.Password"
                                        :type="mostrarPassword ? 'text' : 'password'"
                                        class="w-full border border-gray-200 rounded-md px-3 py-2 pr-10 text-sm font-mono focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                        placeholder="••••••••"
                                    />
                                    <button
                                        type="button"
                                        @click="mostrarPassword = !mostrarPassword"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i :class="mostrarPassword ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-xs"></i>
                                    </button>
                                </div>
                                <p v-if="errores.Password" class="text-red-500 text-[10px] mt-1">{{ errores.Password }}</p>
                            </div>

                            <!-- ✅ AES Key (solo para Banco Económico) -->
                            <div v-if="esBancoEconomico">
                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                    AES Key (256 bits)
                                    <span v-if="!modoEdicion" class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="form.AesKey"
                                        :type="mostrarAesKey ? 'text' : 'password'"
                                        class="w-full border border-gray-200 rounded-md px-3 py-2 pr-10 text-sm font-mono focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                        placeholder="40A318B299F245C2B697176723088629"
                                    />
                                    <button
                                        type="button"
                                        @click="mostrarAesKey = !mostrarAesKey"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i :class="mostrarAesKey ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-xs"></i>
                                    </button>
                                </div>
                                <p v-if="errores.AesKey" class="text-red-500 text-[10px] mt-1">{{ errores.AesKey }}</p>
                            </div>

                            <!-- ✅ ApiKey (solo para Banco Ganadero) -->
                            <div v-if="esBancoGanadero">
                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                    X-Api-Key
                                    <span v-if="!modoEdicion" class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="form.ApiKey"
                                        :type="mostrarApiKey ? 'text' : 'password'"
                                        class="w-full border border-gray-200 rounded-md px-3 py-2 pr-10 text-sm font-mono focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                        placeholder="Clave de seguridad del banco"
                                    />
                                    <button
                                        type="button"
                                        @click="mostrarApiKey = !mostrarApiKey"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i :class="mostrarApiKey ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-xs"></i>
                                    </button>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1">
                                    Proporcionada por el Banco Ganadero
                                </p>
                                <p v-if="errores.ApiKey" class="text-red-500 text-[10px] mt-1">{{ errores.ApiKey }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                    Cuenta de Crédito / Account Reference
                                    <span v-if="!modoEdicion" class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="form.CuentaCredito"
                                        :type="mostrarCuenta ? 'text' : 'password'"
                                        class="w-full border border-gray-200 rounded-md px-3 py-2 pr-10 text-sm font-mono focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                        :placeholder="esBancoEconomico ? 'Número de cuenta' : 'accountReference'"
                                    />
                                    <button
                                        type="button"
                                        @click="mostrarCuenta = !mostrarCuenta"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i :class="mostrarCuenta ? 'fas fa-eye-slash' : 'fas fa-eye'" class="text-xs"></i>
                                    </button>
                                </div>
                                <p v-if="errores.CuentaCredito" class="text-red-500 text-[10px] mt-1">{{ errores.CuentaCredito }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Branch Code (opcional)</label>
                                <input
                                    v-model="form.BranchCode"
                                    type="text"
                                    maxlength="5"
                                    class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm font-mono focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                    placeholder="S0001"
                                />
                                <p class="text-[10px] text-gray-400 mt-1">Máximo 5 caracteres</p>
                                <p v-if="errores.BranchCode" class="text-red-500 text-[10px] mt-1">{{ errores.BranchCode }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Moneda por Defecto</label>
                                <select
                                    v-model="form.MonedaDefault"
                                    class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                >
                                    <option value="BOB">BOB - Bolivianos</option>
                                    <option value="USD">USD - Dólares</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN: Configuración avanzada -->
                    <div class="border-t pt-4">
                        <h4 class="text-xs font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <i class="fas fa-cog text-primary-600"></i>
                            Configuración Avanzada
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Timeout (seg)</label>
                                <input
                                    v-model.number="form.Timeout"
                                    type="number"
                                    min="5"
                                    max="120"
                                    class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                />
                                <p v-if="errores.Timeout" class="text-red-500 text-[10px] mt-1">{{ errores.Timeout }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Reintentos</label>
                                <input
                                    v-model.number="form.Reintentos"
                                    type="number"
                                    min="1"
                                    max="10"
                                    class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                />
                                <p v-if="errores.Reintentos" class="text-red-500 text-[10px] mt-1">{{ errores.Reintentos }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Caché Token (seg)</label>
                                <input
                                    v-model.number="form.TokenCacheTtl"
                                    type="number"
                                    min="60"
                                    max="7200"
                                    class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:border-primary-400 focus:ring-1 focus:ring-primary-200"
                                />
                                <p class="text-[10px] text-gray-400 mt-1">1500 = 25 min</p>
                                <p v-if="errores.TokenCacheTtl" class="text-red-500 text-[10px] mt-1">{{ errores.TokenCacheTtl }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN: Estado (solo en edición) -->
                    <div v-if="modoEdicion" class="border-t pt-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                v-model="form.ActivoInactivo"
                                type="checkbox"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <div>
                                <span class="text-sm font-medium text-gray-700">Credencial activa</span>
                                <p class="text-[10px] text-gray-500">Si se desactiva, no se podrá usar para generar QRs</p>
                            </div>
                        </label>
                    </div>

                </div>

                <!-- Footer modal -->
                <div class="px-6 py-4 bg-gray-50 border-t rounded-b-2xl flex justify-end gap-3">
                    <button
                        @click="cerrarModal"
                        :disabled="guardando"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition font-medium disabled:opacity-50"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="guardar"
                        :disabled="guardando"
                        class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-medium transition shadow-sm disabled:opacity-50 flex items-center gap-2"
                    >
                        <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ guardando ? 'Guardando...' : textoBotonGuardar }}
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
/* Nada extra por ahora */
</style>