<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick, inject } from 'vue'
import axios from 'axios'
import ModalAgregarIdentificador from './ModalAgregarIdentificador.vue'

const props = defineProps({
    modelValue: Boolean,
    operador: Object,
    asignacion: Object,       // ✅ ahora es la asignación específica que se edita
    sucursales: Array,
    identificadores: Array,
    editando: Boolean,
})

const emit = defineEmits(['update:modelValue', 'saved'])

const toast = inject('toast')

// ==================== ESTADO ====================
const loading = ref(false)
const errors = ref({})
const mensajeExito = ref('')
const mensajeError = ref('')

// ✅ Si el identificador ya tiene operador, lo guardamos aquí
const operadorExistente = ref(null)
const buscandoOperador = ref(false)

// ✅ Sucursales ya asignadas a este operador (para no permitir duplicados)
const sucursalesAsignadas = ref([])

// ==================== FORMULARIO PRINCIPAL ====================
const form = ref({
    IdIdentificador: '',
    Iniciales: '',
    Clave: '',
    NombreAcceso: '',
    DireccionDomicilio: '',
    TelefonoDomicilio: '',
    NumeroCelular: '',
    IdSucursal: '',
    IdSucursalDB: null,     // ✅ nueva clave de edición
    Ciudad: 0,
    Provincia: 0,
    Destino: '',
})

// ==================== BUSCADOR IDENTIFICADOR ====================
const textoBusqueda = ref('')
const sugerencias = ref([])
const mostrarSugerencias = ref(false)
const buscando = ref(false)
const searchInput = ref(null)
const identificadorSeleccionado = ref(null)

// ==================== MODAL IDENTIFICADOR ANIDADO ====================
const mostrarModalIdentificador = ref(false)

// ==================== COMPUTED ====================
const alMenosUnoMarcado = computed(() => form.value.Ciudad === 1 || form.value.Provincia === 1)
const soloUnoMarcado = computed(() => !(form.value.Ciudad === 1 && form.value.Provincia === 1))

// ✅ Indica si estamos reutilizando un operador existente
const esReutilizacion = computed(() => !!operadorExistente.value)

// ✅ Sucursales disponibles (excluye las ya asignadas al operador, salvo la que se edita)
const sucursalesDisponibles = computed(() => {
    if (!props.sucursales) return []
    // Si estamos editando, no filtramos (la sucursal actual debe aparecer)
    if (props.editando) return props.sucursales
    // Si estamos creando con operador existente, excluir sucursales ya asignadas
    return props.sucursales.filter(s => !sucursalesAsignadas.value.includes(s.id))
})

// ==================== SELECCIÓN TIPO UBICACIÓN ====================
const seleccionarTipoUbicacion = (tipo) => {
    if (tipo === 'Ciudad') {
        form.value.Ciudad = 1
        form.value.Provincia = 0
    } else if (tipo === 'Provincia') {
        form.value.Ciudad = 0
        form.value.Provincia = 1
    }
    errors.value.Ciudad = null
}

// ==================== BUSCAR IDENTIFICADORES ====================
const buscarIdentificadores = async (q) => {
    if (!q || q.length < 1) {
        sugerencias.value = []
        mostrarSugerencias.value = false
        return
    }
    buscando.value = true
    try {
        const response = await axios.get('/gestion/todos/identificador', { params: { search: q } })
        let resultados = []
        if (response.data) {
            if (Array.isArray(response.data)) resultados = response.data
            else if (response.data.data && Array.isArray(response.data.data)) resultados = response.data.data
            else if (response.data.results && Array.isArray(response.data.results)) resultados = response.data.results
        }
        if (resultados.length === 0 && props.identificadores) {
            const searchTerm = q.toLowerCase().trim()
            resultados = props.identificadores.filter(item =>
                item.ci?.toString().includes(searchTerm) ||
                item.nombre?.toLowerCase().includes(searchTerm)
            )
        }
        sugerencias.value = resultados.map(item => ({
            id: item.IdIdentificador || item.id,
            CI_NIT: item.CI_NIT || item.ci || '',
            Nombre: item.Nombre || item.nombre || '',
            text: `${item.CI_NIT || item.ci || ''} - ${item.Nombre || item.nombre || ''}`
        }))
        mostrarSugerencias.value = sugerencias.value.length > 0
    } catch (error) {
        console.error('Error buscando identificadores:', error)
        if (props.identificadores) {
            const searchTerm = q.toLowerCase().trim()
            const resultados = props.identificadores.filter(item =>
                item.ci?.toString().includes(searchTerm) ||
                item.nombre?.toLowerCase().includes(searchTerm)
            )
            sugerencias.value = resultados.map(item => ({
                id: item.id, CI_NIT: item.ci || '', Nombre: item.nombre || '',
                text: `${item.ci || ''} - ${item.nombre || ''}`
            }))
            mostrarSugerencias.value = sugerencias.value.length > 0
        } else {
            sugerencias.value = []
            mostrarSugerencias.value = false
        }
    } finally {
        buscando.value = false
    }
}

// ==================== SELECCIONAR IDENTIFICADOR ====================
// ✅ Cuando el usuario selecciona una persona, verificamos si ya tiene operador PedidoClientes
const seleccionarIdentificador = async (item) => {
    form.value.IdIdentificador = item.id
    identificadorSeleccionado.value = {
        IdIdentificador: item.id,
        CI_NIT: item.CI_NIT,
        Nombre: item.Nombre
    }
    textoBusqueda.value = item.text || `${item.CI_NIT} - ${item.Nombre}`
    mostrarSugerencias.value = false
    sugerencias.value = []
    errors.value.IdIdentificador = null

    // ✅ Verificar si este identificador ya tiene un Operador PedidoClientes
    await verificarOperadorExistente(item.id)
}

// ✅ Consulta al backend si ya existe un operador PedidoClientes con ese identificador
const verificarOperadorExistente = async (idIdentificador) => {
    operadorExistente.value = null
    sucursalesAsignadas.value = []
    buscandoOperador.value = true

    try {
        const response = await axios.get(`/operacion/pedidos/clientes-mayoristas/operadores-pedidoclientes/buscar-identificador/${idIdentificador}`)
        const data = response.data

        if (data.existe) {
            const op = data.operador
            operadorExistente.value = op
            // Guardar los IDs de sucursal ya asignadas (para excluirlas del select)
            sucursalesAsignadas.value = (op.asignaciones_en_esta_empresa || []).map(a => a.IdSucursal)

            // Autocompletar datos del operador
            form.value.Iniciales = op.Iniciales || ''
            form.value.NombreAcceso = op.NombreAcceso || ''
            form.value.DireccionDomicilio = op.DireccionDomicilio || ''
            form.value.TelefonoDomicilio = op.TelefonoDomicilio ? String(op.TelefonoDomicilio) : ''
            form.value.NumeroCelular = op.NumeroCelular ? String(op.NumeroCelular) : ''
            form.value.Clave = '' // NO pedimos clave, ya existe

            // Si tiene config en la primera asignación de esta empresa, la usamos como sugerencia
            const primeraAsig = (op.asignaciones_en_esta_empresa || [])[0]
            if (primeraAsig) {
                form.value.Ciudad = (primeraAsig.Ciudad === 'Sí' || primeraAsig.Ciudad === 1) ? 1 : 0
                form.value.Provincia = (primeraAsig.Provincia === 'Sí' || primeraAsig.Provincia === 1) ? 1 : 0
                form.value.Destino = primeraAsig.Destino || ''
            }

            toast?.info?.('Operador existente', `Este operador ya existe. Se reutilizará y solo se agregará la nueva sucursal.`)
        }
    } catch (error) {
        console.error('Error verificando operador:', error)
        // Silencioso: si falla, se asume que no existe
    } finally {
        buscandoOperador.value = false
    }
}

// ==================== ABRIR MODAL IDENTIFICADOR ====================
const abrirModalIdentificador = () => { mostrarModalIdentificador.value = true }

// ==================== CUANDO SE GUARDA IDENTIFICADOR ====================
const onIdentificadorGuardado = async (nuevoIdentificador) => {
    mostrarModalIdentificador.value = false
    if (nuevoIdentificador && nuevoIdentificador.id) {
        form.value.IdIdentificador = nuevoIdentificador.id
        identificadorSeleccionado.value = {
            IdIdentificador: nuevoIdentificador.id,
            CI_NIT: nuevoIdentificador.CI_NIT,
            Nombre: nuevoIdentificador.Nombre
        }
        textoBusqueda.value = nuevoIdentificador.text || `${nuevoIdentificador.CI_NIT} - ${nuevoIdentificador.Nombre}`
        if (props.identificadores) {
            const existe = props.identificadores.some(i => i.id === nuevoIdentificador.id)
            if (!existe) {
                props.identificadores.push({
                    id: nuevoIdentificador.id,
                    ci: nuevoIdentificador.CI_NIT,
                    nombre: nuevoIdentificador.Nombre
                })
            }
        }
        mensajeExito.value = `✅ Identificador "${nuevoIdentificador.Nombre}" creado y seleccionado`
        setTimeout(() => { mensajeExito.value = '' }, 3000)
        toast?.success('Éxito', 'Identificador seleccionado automáticamente')

        // Verificar si ya tiene operador (no debería, es nuevo, pero por seguridad)
        await verificarOperadorExistente(nuevoIdentificador.id)
    }
}

// ==================== CONVERTIR A MAYÚSCULAS ====================
const convertirMayusculas = (campo) => {
    if (form.value[campo]) form.value[campo] = form.value[campo].toUpperCase()
}

// ==================== SUBMIT PRINCIPAL ====================
const submitForm = async () => {
    errors.value = {}
    loading.value = true
    mensajeExito.value = ''
    mensajeError.value = ''

    if (!form.value.IdIdentificador) {
        errors.value = { IdIdentificador: 'Debe seleccionar una persona' }
        mensajeError.value = '❌ Debe seleccionar una persona'
        loading.value = false
        return
    }

    if (!form.value.IdSucursal) {
        errors.value = { IdSucursal: 'Debe seleccionar una sucursal' }
        mensajeError.value = '❌ Debe seleccionar una sucursal'
        loading.value = false
        return
    }

    if (!alMenosUnoMarcado.value) {
        errors.value = { Ciudad: 'Debe seleccionar Ciudad o Provincia' }
        mensajeError.value = '❌ Debe seleccionar Ciudad o Provincia'
        loading.value = false
        return
    }

    if (!soloUnoMarcado.value) {
        errors.value = { Ciudad: 'Solo puede seleccionar Ciudad o Provincia, no ambos' }
        mensajeError.value = '❌ Solo puede seleccionar Ciudad o Provincia, no ambos'
        loading.value = false
        return
    }

    try {
        let url, method

        if (props.editando) {
            url = `/operacion/pedidos/clientes-mayoristas/operadores-pedidoclientes/${props.operador.IdOperador}`
            method = 'put'
        } else {
            url = '/operacion/pedidos/clientes-mayoristas/operadores-pedidoclientes'
            method = 'post'
        }

        const dataToSend = {
            IdIdentificador: form.value.IdIdentificador,
            Iniciales: form.value.Iniciales,
            Clave: form.value.Clave || undefined, // opcional si reutilizamos
            NombreAcceso: form.value.NombreAcceso,
            DireccionDomicilio: form.value.DireccionDomicilio,
            TelefonoDomicilio: form.value.TelefonoDomicilio || null,
            NumeroCelular: form.value.NumeroCelular || null,
            IdSucursal: form.value.IdSucursal,
            IdSucursalDB: form.value.IdSucursalDB, // ✅ para edición
            Ciudad: form.value.Ciudad ? 1 : 0,
            Provincia: form.value.Provincia ? 1 : 0,
            Destino: form.value.Destino || null,
        }

        const response = await axios[method](url, dataToSend)

        if (response.data.success) {
            mensajeExito.value = response.data.message || '✅ Operador guardado correctamente'
            toast?.success('Éxito', response.data.message || 'Operador guardado correctamente')

            setTimeout(() => {
                emit('saved')
                cerrarModal()
            }, 1000)
        }
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {}
            const firstError = Object.values(errors.value)[0]
            if (firstError) mensajeError.value = `❌ ${firstError[0]}`
            toast?.error('Error', mensajeError.value)
        } else {
            mensajeError.value = error.response?.data?.message || '❌ Error al guardar el operador'
            toast?.error('Error', mensajeError.value)
        }
    } finally {
        loading.value = false
    }
}

// ==================== CERRAR MODAL ====================
const cerrarModal = () => {
    emit('update:modelValue', false)
    errors.value = {}
    mensajeExito.value = ''
    mensajeError.value = ''
    mostrarModalIdentificador.value = false
    operadorExistente.value = null
    sucursalesAsignadas.value = []
    form.value = {
        IdIdentificador: '',
        Iniciales: '',
        Clave: '',
        NombreAcceso: '',
        DireccionDomicilio: '',
        TelefonoDomicilio: '',
        NumeroCelular: '',
        IdSucursal: '',
        IdSucursalDB: null,
        Ciudad: 0,
        Provincia: 0,
        Destino: '',
    }
    textoBusqueda.value = ''
    sugerencias.value = []
    mostrarSugerencias.value = false
    identificadorSeleccionado.value = null
}

// ==================== CARGAR DATOS PARA EDITAR ====================
// ✅ Ahora editamos UNA asignación específica (no todo el operador)
const cargarDatosOperador = () => {
    if (!props.operador) return

    // Datos personales del operador
    form.value.IdIdentificador = props.operador.IdIdentificador || ''
    form.value.Iniciales = props.operador.Iniciales || ''
    form.value.NombreAcceso = props.operador.NombreAcceso || ''
    form.value.DireccionDomicilio = props.operador.DireccionDomicilio || ''
    form.value.TelefonoDomicilio = props.operador.TelefonoDomicilio ? String(props.operador.TelefonoDomicilio) : ''
    form.value.NumeroCelular = props.operador.NumeroCelular ? String(props.operador.NumeroCelular) : ''
    form.value.Clave = ''

    // ✅ Datos de la asignación que se edita (sucursal + config)
    if (props.asignacion) {
        form.value.IdSucursalDB = props.asignacion.IdSucursalDB || null
        form.value.IdSucursal = props.asignacion.IdSucursal || ''
        const config = props.asignacion.pedido_cliente_config || {}
        form.value.Ciudad = (config.Ciudad === 'Sí' || config.Ciudad === 1) ? 1 : 0
        form.value.Provincia = (config.Provincia === 'Sí' || config.Provincia === 1) ? 1 : 0
        form.value.Destino = config.Destino || ''
    } else {
        // Fallback: por si no llega asignación
        const config = props.operador.pedido_cliente_config || {}
        form.value.Ciudad = (config.Ciudad === 'Sí' || config.Ciudad === 1) ? 1 : 0
        form.value.Provincia = (config.Provincia === 'Sí' || config.Provincia === 1) ? 1 : 0
        form.value.Destino = config.Destino || ''
        if (props.sucursales && props.sucursales.length > 0) {
            form.value.IdSucursal = props.sucursales[0].id
        }
    }

    // Mostrar nombre en el buscador
    if (props.operador.IdIdentificador) {
        const ident = (props.identificadores || []).find(i => i.id === props.operador.IdIdentificador)
        if (ident) {
            textoBusqueda.value = `${ident.ci} - ${ident.nombre}`
            identificadorSeleccionado.value = {
                IdIdentificador: ident.id,
                CI_NIT: ident.ci,
                Nombre: ident.nombre
            }
        }
    }
}

// ==================== WATCHERS ====================
watch(() => props.modelValue, (nuevoValor) => {
    if (!nuevoValor) cerrarModal()
})

watch(() => props.operador, (nuevoOperador) => {
    if (nuevoOperador) cargarDatosOperador()
}, { immediate: true })

watch(() => props.asignacion, () => {
    if (props.asignacion && props.operador) cargarDatosOperador()
}, { deep: true })

// ==================== CERRAR SUGERENCIAS ====================
const handleClickOutside = (e) => {
    if (!e.target.closest('.relative')) mostrarSugerencias.value = false
}

onMounted(() => {
    if (props.sucursales && props.sucursales.length === 1 && !props.operador) {
        form.value.IdSucursal = props.sucursales[0].id
    }
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-3" @click.self="cerrarModal">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl animate-fadeIn">
            <!-- HEADER -->
            <div class="bg-primary-600 p-3 sticky top-0 z-10 rounded-t-xl">
                <div class="flex justify-between items-center">
                    <h3 class="text-white font-semibold text-sm flex items-center gap-1.5">
                        <i :class="editando ? 'fas fa-edit' : 'fas fa-user-plus'" class="text-[10px]"></i>
                        {{ editando ? 'Editar Sucursal del Operador' : 'Nuevo Operador PedidoClientes' }}
                    </h3>
                    <button @click="cerrarModal" class="text-white/80 hover:text-white transition text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- BODY -->
            <div class="p-4">
                <!-- Mensajes -->
                <div v-if="mensajeExito" class="mb-3 p-2 bg-emerald-50 border border-emerald-200 rounded-md text-emerald-700 text-xs flex items-center gap-1.5">
                    <i class="fas fa-check-circle text-[10px]"></i>
                    <span>{{ mensajeExito }}</span>
                </div>
                <div v-if="mensajeError" class="mb-3 p-2 bg-red-50 border border-red-200 rounded-md text-red-700 text-xs flex items-center gap-1.5">
                    <i class="fas fa-exclamation-circle text-[10px]"></i>
                    <span>{{ mensajeError }}</span>
                </div>

                <!-- Aviso reutilización -->
                <div v-if="esReutilizacion" class="mb-3 p-2.5 bg-blue-50 border border-blue-200 rounded-md text-blue-700 text-xs flex items-start gap-1.5">
                    <i class="fas fa-info-circle text-[10px] mt-0.5"></i>
                    <div>
                        <div class="font-semibold">Operador existente detectado</div>
                        <div class="text-[10px] mt-0.5">
                            Esta persona ya es Operador PedidoClientes. Se reutilizará y <strong>solo se agregará la nueva sucursal</strong> con su configuración. No se duplicará.
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitForm" class="space-y-3">
                    <!-- Tipo -->
                    <div class="bg-emerald-50 border border-emerald-200 rounded-md p-2.5 flex items-center gap-1.5 text-emerald-700 text-xs">
                        <i class="fas fa-info-circle text-[10px]"></i>
                        <span class="font-medium">Tipo: <span class="font-bold">PedidoClientes</span></span>
                    </div>

                    <!-- Identificador -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 mb-0.5">
                            Persona (CI/NIT) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <input 
                                        type="text"
                                        ref="searchInput"
                                        v-model="textoBusqueda"
                                        @input="buscarIdentificadores(textoBusqueda)"
                                        @focus="textoBusqueda && buscarIdentificadores(textoBusqueda)"
                                        :disabled="editando || esReutilizacion"
                                        placeholder="Buscar por CI/NIT o nombre..."
                                        class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none pr-20 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                        :class="{ 'border-red-500': errors.IdIdentificador }"
                                        autocomplete="off"
                                    />
                                    <button 
                                        v-if="!editando && !esReutilizacion"
                                        type="button" 
                                        @click="abrirModalIdentificador"
                                        class="absolute right-1 top-1/2 -translate-y-1/2 text-[10px] transition px-1.5 py-0.5 rounded flex items-center gap-0.5 bg-primary-50 text-primary-600 hover:bg-primary-100"
                                        title="Agregar nuevo identificador">
                                        <i class="fas fa-plus-circle text-[9px]"></i>
                                        <span>Nuevo</span>
                                    </button>
                                    <div v-if="buscandoOperador" class="absolute right-1 top-1/2 -translate-y-1/2 text-[10px] text-primary-600">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="mostrarSugerencias && sugerencias.length > 0"
                                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                <div v-for="item in sugerencias" :key="item.id"
                                    @click="seleccionarIdentificador(item)"
                                    class="px-2.5 py-1.5 cursor-pointer hover:bg-primary-50 border-b border-gray-100 last:border-b-0 transition text-sm">
                                    <div class="font-medium text-gray-800 text-xs">{{ item.Nombre }}</div>
                                    <div class="text-[9px] text-gray-500">CI/NIT: {{ item.CI_NIT }}</div>
                                </div>
                            </div>
                            
                            <p v-if="errors.IdIdentificador" class="text-[8px] text-red-500 mt-0.5">{{ errors.IdIdentificador }}</p>
                            
                            <div v-if="identificadorSeleccionado" class="mt-0.5 text-[9px] text-gray-600 flex items-center gap-1">
                                <i class="fas fa-check-circle text-emerald-500 text-[8px]"></i>
                                <span>Seleccionado: <strong>{{ identificadorSeleccionado.CI_NIT }}</strong> - {{ identificadorSeleccionado.Nombre }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Iniciales + Nombre Acceso -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-medium text-gray-500 mb-0.5">
                                Iniciales <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                v-model="form.Iniciales" 
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm uppercase focus:ring-primary-500 focus:border-primary-500 outline-none disabled:bg-gray-100" 
                                :class="{ 'border-red-500': errors.Iniciales }" 
                                placeholder="Ej: JPG" 
                                maxlength="5"
                                :disabled="esReutilizacion"
                            />
                            <p v-if="errors.Iniciales" class="text-[8px] text-red-500 mt-0.5">{{ errors.Iniciales }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-500 mb-0.5">
                                Nombre de Acceso <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                v-model="form.NombreAcceso" 
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none disabled:bg-gray-100" 
                                :class="{ 'border-red-500': errors.NombreAcceso }" 
                                placeholder="Usuario para login"
                                :disabled="esReutilizacion"
                            />
                            <p v-if="errors.NombreAcceso" class="text-[8px] text-red-500 mt-0.5">{{ errors.NombreAcceso }}</p>
                        </div>
                    </div>

                    <!-- Contraseña + Sucursal -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-medium text-gray-500 mb-0.5">
                                Contraseña 
                                <span v-if="!editando && !esReutilizacion" class="text-red-500">*</span>
                                <span v-else class="text-gray-400">(opcional)</span>
                            </label>
                            <input 
                                type="password" 
                                v-model="form.Clave" 
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none disabled:bg-gray-100" 
                                :class="{ 'border-red-500': errors.Clave }" 
                                :placeholder="editando || esReutilizacion ? 'Nueva contraseña (opcional)' : 'Mínimo 4 caracteres'"
                                :disabled="esReutilizacion"
                            />
                            <p v-if="errors.Clave" class="text-[8px] text-red-500 mt-0.5">{{ errors.Clave }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-500 mb-0.5">
                                Sucursal <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.IdSucursal" 
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none disabled:bg-gray-100"
                                :class="{ 'border-red-500': errors.IdSucursal }"
                                :disabled="editando"
                            >
                                <option value="">Seleccione una sucursal</option>
                                <option v-for="suc in sucursalesDisponibles" :key="suc.id" :value="suc.id">
                                    {{ suc.nombre }} {{ suc.NumeroSucursal ? `(N° ${suc.NumeroSucursal})` : '' }}
                                </option>
                            </select>
                            <p v-if="errors.IdSucursal" class="text-[8px] text-red-500 mt-0.5">{{ errors.IdSucursal }}</p>
                            <p v-if="!editando && sucursalesDisponibles.length === 0" class="text-[8px] text-orange-600 mt-0.5">
                                <i class="fas fa-exclamation-triangle mr-0.5"></i>
                                Este operador ya está en todas las sucursales disponibles.
                            </p>
                        </div>
                    </div>

                    <!-- Dirección -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Dirección Domicilio</label>
                        <input 
                            type="text" 
                            v-model="form.DireccionDomicilio" 
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none disabled:bg-gray-100" 
                            :class="{ 'border-red-500': errors.DireccionDomicilio }" 
                            placeholder="Dirección completa"
                            :disabled="esReutilizacion"
                        />
                        <p v-if="errors.DireccionDomicilio" class="text-[8px] text-red-500 mt-0.5">{{ errors.DireccionDomicilio }}</p>
                    </div>

                    <!-- Teléfonos -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Teléfono Domicilio</label>
                            <input 
                                type="text" 
                                v-model="form.TelefonoDomicilio" 
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none disabled:bg-gray-100" 
                                :class="{ 'border-red-500': errors.TelefonoDomicilio }" 
                                placeholder="Teléfono fijo"
                                :disabled="esReutilizacion"
                            />
                            <p v-if="errors.TelefonoDomicilio" class="text-[8px] text-red-500 mt-0.5">{{ errors.TelefonoDomicilio }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Número Celular</label>
                            <input 
                                type="text" 
                                v-model="form.NumeroCelular" 
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none disabled:bg-gray-100" 
                                :class="{ 'border-red-500': errors.NumeroCelular }" 
                                placeholder="Celular / WhatsApp"
                                :disabled="esReutilizacion"
                            />
                            <p v-if="errors.NumeroCelular" class="text-[8px] text-red-500 mt-0.5">{{ errors.NumeroCelular }}</p>
                        </div>
                    </div>

                    <!-- UBICACIÓN -->
                    <div class="border-t border-gray-200 pt-3 mt-1">
                        <div class="flex items-center gap-1.5 mb-2">
                            <i class="fas fa-map-marker-alt text-primary-600 text-[10px]"></i>
                            <h4 class="text-xs font-semibold text-gray-700">
                                Ubicación <span class="text-gray-400 text-[10px]">(por esta sucursal)</span>
                            </h4>
                            <span class="text-[8px] text-gray-400 ml-auto">
                                <i class="fas fa-info-circle mr-0.5"></i>
                                Seleccione <strong>solo uno</strong>
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                            <!-- Ciudad -->
                            <label 
                                class="flex items-center gap-2 p-2 border rounded-md cursor-pointer transition-all duration-150"
                                :class="form.Ciudad === 1 ? 'border-blue-400 bg-blue-50 ring-1 ring-blue-200' : 'border-gray-200 hover:bg-gray-50'">
                                <input 
                                    type="radio" 
                                    name="tipoUbicacion"
                                    :checked="form.Ciudad === 1"
                                    @change="seleccionarTipoUbicacion('Ciudad')"
                                    class="w-3.5 h-3.5 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                />
                                <div class="flex-1">
                                    <span class="text-xs font-medium text-gray-700 flex items-center gap-1">
                                        <i class="fas fa-city text-blue-500 text-[10px]"></i>
                                        Ciudad
                                    </span>
                                </div>
                                <i v-if="form.Ciudad === 1" class="fas fa-check-circle text-blue-500 text-sm"></i>
                            </label>

                            <!-- Provincia -->
                            <label 
                                class="flex items-center gap-2 p-2 border rounded-md cursor-pointer transition-all duration-150"
                                :class="form.Provincia === 1 ? 'border-emerald-400 bg-emerald-50 ring-1 ring-emerald-200' : 'border-gray-200 hover:bg-gray-50'">
                                <input 
                                    type="radio" 
                                    name="tipoUbicacion"
                                    :checked="form.Provincia === 1"
                                    @change="seleccionarTipoUbicacion('Provincia')"
                                    class="w-3.5 h-3.5 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                />
                                <div class="flex-1">
                                    <span class="text-xs font-medium text-gray-700 flex items-center gap-1">
                                        <i class="fas fa-tree text-emerald-500 text-[10px]"></i>
                                        Provincia
                                    </span>
                                </div>
                                <i v-if="form.Provincia === 1" class="fas fa-check-circle text-emerald-500 text-sm"></i>
                            </label>
                        </div>

                        <p v-if="!alMenosUnoMarcado && errors.Ciudad" class="text-[9px] text-red-500 mb-1.5 flex items-center gap-1">
                            <i class="fas fa-exclamation-circle text-[8px]"></i>
                            Debe seleccionar Ciudad o Provincia
                        </p>

                        <div>
                            <label class="block text-[10px] font-medium text-gray-500 mb-0.5">
                                <i class="fas fa-location-dot text-orange-500 mr-0.5 text-[9px]"></i>
                                Destino
                            </label>
                            <input 
                                type="text" 
                                v-model="form.Destino" 
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none uppercase" 
                                :class="{ 'border-red-500': errors.Destino }" 
                                placeholder="Ej: Cochabamba, Cercado, Quillacollo..."
                                maxlength="150"
                            />
                            <p v-if="errors.Destino" class="text-[8px] text-red-500 mt-0.5">{{ errors.Destino }}</p>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex flex-wrap justify-end gap-1.5 pt-3 border-t border-gray-200">
                        <button type="button" @click="cerrarModal"
                            class="px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-100 transition text-xs font-medium"
                            :disabled="loading">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="loading"
                            class="px-4 py-1.5 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1.5 text-xs font-medium">
                            <i v-if="loading" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            {{ loading ? 'Guardando...' : (editando ? 'Actualizar' : 'Guardar Operador') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <ModalAgregarIdentificador
        v-if="mostrarModalIdentificador"
        :visible="mostrarModalIdentificador"
        @close="mostrarModalIdentificador = false"
        @saved="onIdentificadorGuardado"
    />
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button { font-size: 13px !important; }
}
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
.animate-fadeIn { animation: fadeIn 0.2s ease-out; }
.overflow-y-auto::-webkit-scrollbar { width: 4px; }
.overflow-y-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
</style>