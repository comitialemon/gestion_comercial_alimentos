<!-- resources/js/Pages/Operacion/ClientesMayoristas/OperadoresClientes/ModalOperadorPedidoClientes.vue -->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick, inject } from 'vue'
import axios from 'axios'
import ModalAgregarIdentificador from './ModalAgregarIdentificador.vue'

const props = defineProps({
    modelValue: Boolean,
    operador: Object,
    asignacion: Object,
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

// ==================== BUSCAR IDENTIFICADORES ====================
const buscarIdentificadores = async (q) => {
    if (!q || q.length < 1) {
        sugerencias.value = []
        mostrarSugerencias.value = false
        return
    }

    buscando.value = true
    try {
        const response = await axios.get('/gestion/todos/identificador', {
            params: { search: q }
        })
        
        let resultados = []
        
        if (response.data) {
            if (Array.isArray(response.data)) {
                resultados = response.data
            } else if (response.data.data && Array.isArray(response.data.data)) {
                resultados = response.data.data
            } else if (response.data.results && Array.isArray(response.data.results)) {
                resultados = response.data.results
            }
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
                id: item.id,
                CI_NIT: item.ci || '',
                Nombre: item.nombre || '',
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
const seleccionarIdentificador = (item) => {
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
}

// ==================== ABRIR MODAL IDENTIFICADOR ====================
const abrirModalIdentificador = () => {
    console.log('Abriendo modal de identificador')
    mostrarModalIdentificador.value = true
}

// ==================== CUANDO SE GUARDA IDENTIFICADOR ====================
const onIdentificadorGuardado = (nuevoIdentificador) => {
    console.log('📥 IDENTIFICADOR RECIBIDO EN PADRE:', nuevoIdentificador)
    
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
        
        setTimeout(() => {
            mensajeExito.value = ''
        }, 3000)
        
        toast?.success('Éxito', 'Identificador seleccionado automáticamente')
    } else {
        console.error('❌ Identificador inválido:', nuevoIdentificador)
        toast?.error('Error', 'No se pudo seleccionar el identificador creado')
    }
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
            ...form.value,
            TelefonoDomicilio: parseInt(form.value.TelefonoDomicilio) || 0,
            NumeroCelular: parseInt(form.value.NumeroCelular) || 0,
        }

        const response = await axios[method](url, dataToSend)
        
        if (response.data.success) {
            mensajeExito.value = response.data.message || '✅ Operador guardado correctamente'
            toast?.success('Éxito', 'Operador guardado correctamente')
            
            setTimeout(() => {
                emit('saved')
                cerrarModal()
            }, 1000)
        }
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {}
            const firstError = Object.values(errors.value)[0]
            if (firstError) {
                mensajeError.value = `❌ ${firstError[0]}`
            }
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
    form.value = {
        IdIdentificador: '',
        Iniciales: '',
        Clave: '',
        NombreAcceso: '',
        DireccionDomicilio: '',
        TelefonoDomicilio: '',
        NumeroCelular: '',
        IdSucursal: '',
    }
    textoBusqueda.value = ''
    sugerencias.value = []
    mostrarSugerencias.value = false
    identificadorSeleccionado.value = null
}

// ==================== FUNCIÓN PARA CARGAR DATOS DEL OPERADOR ====================
const cargarDatosOperador = () => {
    if (!props.operador) return
    
    console.log('📝 Cargando operador:', props.operador)
    console.log('📝 Asignación recibida:', props.asignacion)
    
    // ✅ Cargar datos del operador
    form.value.IdIdentificador = props.operador.IdIdentificador || ''
    form.value.Iniciales = props.operador.Iniciales || ''
    form.value.NombreAcceso = props.operador.NombreAcceso || ''
    form.value.DireccionDomicilio = props.operador.DireccionDomicilio || ''
    form.value.TelefonoDomicilio = props.operador.TelefonoDomicilio ? String(props.operador.TelefonoDomicilio) : ''
    form.value.NumeroCelular = props.operador.NumeroCelular ? String(props.operador.NumeroCelular) : ''
    form.value.Clave = ''
    
    // ✅ Cargar la sucursal de la asignación
    if (props.asignacion && props.asignacion.IdSucursal) {
        form.value.IdSucursal = props.asignacion.IdSucursal
        console.log('✅ Sucursal cargada:', props.asignacion.IdSucursal)
    } else if (props.sucursales && props.sucursales.length === 1) {
        form.value.IdSucursal = props.sucursales[0].id
        console.log('✅ Sucursal única seleccionada:', props.sucursales[0].id)
    } else {
        console.log('❌ No se encontró asignación para el operador')
        // ✅ Si no hay asignación, seleccionar la primera sucursal por defecto
        if (props.sucursales && props.sucursales.length > 0) {
            form.value.IdSucursal = props.sucursales[0].id
            console.log('⚠️ Seleccionando primera sucursal por defecto:', props.sucursales[0].id)
        }
    }
    
    // ✅ Cargar el identificador
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
    if (!nuevoValor) {
        cerrarModal()
    }
})

// ✅ WATCH PARA CUANDO CAMBIA EL OPERADOR
watch(() => props.operador, (nuevoOperador) => {
    if (nuevoOperador) {
        cargarDatosOperador()
    }
}, { immediate: true })

// ✅ WATCH PARA CUANDO CAMBIA LA ASIGNACIÓN
watch(() => props.asignacion, (nuevaAsignacion) => {
    if (nuevaAsignacion && props.operador) {
        console.log('🔄 Asignación actualizada:', nuevaAsignacion)
        cargarDatosOperador()
    }
}, { deep: true })

// ==================== CERRAR SUGERENCIAS ====================
const handleClickOutside = (e) => {
    if (!e.target.closest('.relative')) {
        mostrarSugerencias.value = false
    }
}

// ==================== LIFECYCLE ====================
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

<!-- EL TEMPLATE ES EL MISMO -->
<template>
    <!-- Modal Principal -->
    <div v-if="modelValue" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="cerrarModal">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl animate-fadeIn">
            <!-- Header -->
            <div class="bg-gradient-to-r from-primary-600 to-primary-700 p-4 sticky top-0 z-10 rounded-t-xl">
                <div class="flex justify-between items-center">
                    <h3 class="text-white font-bold text-lg flex items-center gap-2">
                        <i :class="editando ? 'fas fa-edit' : 'fas fa-user-plus'"></i>
                        {{ editando ? 'Editar Operador PedidoClientes' : 'Nuevo Operador PedidoClientes' }}
                    </h3>
                    <button @click="cerrarModal" class="text-white/80 hover:text-white transition text-xl leading-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="p-6">
                <!-- Mensajes -->
                <div v-if="mensajeExito" class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-700 text-sm flex items-center gap-2 animate-slideDown">
                    <i class="fas fa-check-circle"></i>
                    <span>{{ mensajeExito }}</span>
                </div>
                <div v-if="mensajeError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-center gap-2 animate-slideDown">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>{{ mensajeError }}</span>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- Info de tipo -->
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 flex items-center gap-2 text-emerald-700 text-sm">
                        <i class="fas fa-info-circle"></i>
                        <span class="font-medium">Tipo de operador predefinido: <span class="font-bold">PedidoClientes</span></span>
                    </div>

                    <!-- Identificador con buscador -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
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
                                        placeholder="Buscar por CI/NIT o nombre..."
                                        class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:outline-none pr-28"
                                        :class="{ 'border-red-500': errors.IdIdentificador }"
                                        :style="{ borderColor: errors.IdIdentificador ? '#ef4444' : `var(--color-primary-300)` }"
                                        autocomplete="off"
                                    />
                                    <button type="button" @click="abrirModalIdentificador"
                                        class="absolute right-1.5 top-1/2 -translate-y-1/2 text-xs transition px-2 py-1 rounded flex items-center gap-1"
                                        :style="{ color: `var(--color-primary-600)`, backgroundColor: `var(--color-primary-50)` }"
                                        title="Agregar nuevo identificador">
                                        <i class="fas fa-plus-circle"></i>
                                        <span class="hidden xs:inline">Nuevo</span>
                                    </button>
                                </div>
                            </div>
                            
                            <div v-if="mostrarSugerencias && sugerencias.length > 0"
                                class="absolute z-20 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto"
                                :style="{ borderColor: `var(--color-primary-300)` }">
                                <div v-for="item in sugerencias" :key="item.id"
                                    @click="seleccionarIdentificador(item)"
                                    class="px-3 py-2 cursor-pointer hover:bg-gray-50 border-b last:border-b-0 transition text-sm">
                                    <div class="font-medium text-gray-800">{{ item.Nombre }}</div>
                                    <div class="text-xs text-gray-500">CI/NIT: {{ item.CI_NIT }}</div>
                                </div>
                            </div>
                            
                            <p v-if="errors.IdIdentificador" class="text-xs text-red-500 mt-1">{{ errors.IdIdentificador }}</p>
                            
                            <div v-if="identificadorSeleccionado" class="mt-1 text-xs text-gray-600 flex items-center gap-2">
                                <i class="fas fa-check-circle text-green-500"></i>
                                <span>Seleccionado: <strong>{{ identificadorSeleccionado.CI_NIT }}</strong> - {{ identificadorSeleccionado.Nombre }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Iniciales y Nombre de Acceso -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Iniciales <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                v-model="form.Iniciales" 
                                class="w-full border rounded-lg px-3 py-2 text-sm uppercase focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition" 
                                :class="{ 'border-red-500': errors.Iniciales }" 
                                placeholder="Ej: JPG" 
                                maxlength="5"
                            />
                            <p v-if="errors.Iniciales" class="text-xs text-red-500 mt-1">{{ errors.Iniciales }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nombre de Acceso <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                v-model="form.NombreAcceso" 
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition" 
                                :class="{ 'border-red-500': errors.NombreAcceso }" 
                                placeholder="Usuario para login"
                            />
                            <p v-if="errors.NombreAcceso" class="text-xs text-red-500 mt-1">{{ errors.NombreAcceso }}</p>
                        </div>
                    </div>

                    <!-- Contraseña y Sucursal -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Contraseña 
                                <span v-if="!editando" class="text-red-500">*</span>
                                <span v-else class="text-gray-400 text-xs">(dejar en blanco para no cambiar)</span>
                            </label>
                            <input 
                                type="password" 
                                v-model="form.Clave" 
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition" 
                                :class="{ 'border-red-500': errors.Clave }" 
                                :placeholder="editando ? 'Nueva contraseña (opcional)' : 'Mínimo 4 caracteres'"
                            />
                            <p v-if="errors.Clave" class="text-xs text-red-500 mt-1">{{ errors.Clave }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Sucursal de Asignación <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="form.IdSucursal" 
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition"
                                :class="{ 'border-red-500': errors.IdSucursal }"
                            >
                                <option value="">Seleccione una sucursal</option>
                                <option v-for="suc in sucursales" :key="suc.id" :value="suc.id">
                                    {{ suc.nombre }} {{ suc.NumeroSucursal ? `(N° ${suc.NumeroSucursal})` : '' }}
                                </option>
                            </select>
                            <p v-if="errors.IdSucursal" class="text-xs text-red-500 mt-1">{{ errors.IdSucursal }}</p>
                        </div>
                    </div>

                    <!-- Dirección -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dirección Domicilio</label>
                        <input 
                            type="text" 
                            v-model="form.DireccionDomicilio" 
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition" 
                            :class="{ 'border-red-500': errors.DireccionDomicilio }" 
                            placeholder="Dirección completa"
                        />
                        <p v-if="errors.DireccionDomicilio" class="text-xs text-red-500 mt-1">{{ errors.DireccionDomicilio }}</p>
                    </div>

                    <!-- Teléfonos -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono Domicilio</label>
                            <input 
                                type="text" 
                                v-model="form.TelefonoDomicilio" 
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition" 
                                :class="{ 'border-red-500': errors.TelefonoDomicilio }" 
                                placeholder="Teléfono fijo"
                            />
                            <p v-if="errors.TelefonoDomicilio" class="text-xs text-red-500 mt-1">{{ errors.TelefonoDomicilio }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Número Celular</label>
                            <input 
                                type="text" 
                                v-model="form.NumeroCelular" 
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition" 
                                :class="{ 'border-red-500': errors.NumeroCelular }" 
                                placeholder="Celular / WhatsApp"
                            />
                            <p v-if="errors.NumeroCelular" class="text-xs text-red-500 mt-1">{{ errors.NumeroCelular }}</p>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button 
                            type="button" 
                            @click="cerrarModal" 
                            class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition text-sm font-medium"
                            :disabled="loading"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit" 
                            :disabled="loading" 
                            class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-2 text-sm font-medium"
                        >
                            <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ loading ? 'Guardando...' : 'Guardar Operador' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Agregar Identificador -->
    <ModalAgregarIdentificador
        v-if="mostrarModalIdentificador"
        :visible="mostrarModalIdentificador"
        @close="mostrarModalIdentificador = false"
        @saved="onIdentificadorGuardado"
    />
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fadeIn {
    animation: fadeIn 0.2s ease-out;
}

.animate-slideDown {
    animation: slideDown 0.3s ease-out;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
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

input:focus, select:focus {
    --tw-ring-color: var(--color-primary-500);
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

@media (max-width: 480px) {
    .xs\:inline {
        display: inline !important;
    }
}
</style>