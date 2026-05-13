<!-- resources/js/Pages/Facturacion/PuntoVenta/Create.vue -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    empresas: Array,
    tiposPuntoVenta: Array,
})

// 🔥 Agregar state para errores
const errorMessage = ref('')
const successMessage = ref('')
const isSubmitting = ref(false)

const form = ref({
    idEmpresa: '',
    idSucursal: '',
    idTipoPuntoVenta: '',
    nombre: '',
    direccion: '',
    es_movil: false,
    puede_firmar: false,
    activo: true,
})

const sucursales = ref([])
const cargandoSucursales = ref(false)

// Cargar sucursales cuando cambia la empresa
const cargarSucursales = async () => {
    if (!form.value.idEmpresa) {
        sucursales.value = []
        form.value.idSucursal = ''
        return
    }
    
    cargandoSucursales.value = true
    try {
        const { data } = await axios.get('/facturacion/puntos-venta/sucursales', {
            params: { idEmpresa: form.value.idEmpresa }
        })
        sucursales.value = data.sucursales || []
        
        // Si hay una sola sucursal, seleccionarla automáticamente
        if (sucursales.value.length === 1) {
            form.value.idSucursal = sucursales.value[0].idSucursal
        }
    } catch (error) {
        console.error('Error cargando sucursales:', error)
        errorMessage.value = 'Error cargando sucursales: ' + (error.response?.data?.message || error.message)
        setTimeout(() => errorMessage.value = '', 5000)
    } finally {
        cargandoSucursales.value = false
    }
}

watch(() => form.value.idEmpresa, () => {
    form.value.idSucursal = ''
    cargarSucursales()
})

const canSubmit = computed(() =>
    form.value.idEmpresa &&
    form.value.idSucursal &&
    form.value.idTipoPuntoVenta &&
    form.value.nombre.trim()
)

// 🔥 Método submit mejorado con manejo de errores
const submit = () => {
    if (!canSubmit.value) {
        errorMessage.value = 'Complete todos los campos obligatorios'
        return
    }
    
    isSubmitting.value = true
    errorMessage.value = ''
    successMessage.value = ''
    
    console.log('Enviando formulario:', form.value)
    
    router.post('/facturacion/puntos-venta', form.value, {
        preserveScroll: true,
        onSuccess: (page) => {
            console.log('Success:', page)
            successMessage.value = 'Punto de venta creado correctamente'
            isSubmitting.value = false
            
            // Limpiar formulario después de éxito
            setTimeout(() => {
                successMessage.value = ''
                // Opcional: redirigir o limpiar
                // router.visit('/facturacion/puntos-venta')
            }, 3000)
        },
        onError: (errors) => {
            console.error('Validation errors:', errors)
            errorMessage.value = Object.values(errors).join(', ')
            isSubmitting.value = false
            setTimeout(() => errorMessage.value = '', 5000)
        },
        onFinish: () => {
            // No hacer nada aquí, ya manejamos en onSuccess/onError
        }
    })
}

onMounted(() => {
    console.log('Empresas recibidas:', props.empresas)
    console.log('Tipos punto venta:', props.tiposPuntoVenta)
    
    if (props.empresas && props.empresas.length > 0 && !form.value.idEmpresa) {
        form.value.idEmpresa = props.empresas[0]?.idEmpresa || props.empresas[0]?.idEmpresa
        cargarSucursales()
    }
})
</script>

<template>
    <div class="max-w-3xl mx-auto p-6">
        <h1 class="text-2xl font-semibold mb-6">Nuevo Punto de Venta</h1>

        <!-- 🔥 Mensajes de error/success -->
        <div v-if="errorMessage" class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg">
            ❌ {{ errorMessage }}
        </div>
        <div v-if="successMessage" class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg">
            ✅ {{ successMessage }}
        </div>

        <!-- 🔥 Mostrar si no hay tipos de punto de venta -->
        <div v-if="!tiposPuntoVenta || tiposPuntoVenta.length === 0" class="mb-4 p-3 bg-yellow-100 text-yellow-800 rounded-lg">
            ⚠️ No hay tipos de punto de venta disponibles. Primero sincroniza los catálogos del SIAT.
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <div class="space-y-4">
                <!-- Empresa -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Empresa *</label>
                    <select v-model="form.idEmpresa" class="w-full border rounded-lg p-2">
                        <option value="">Seleccione una empresa</option>
                        <option v-for="e in empresas" :key="e.idEmpresa" :value="e.idEmpresa">
                            {{ e.nombre }} ({{ e.nit }})
                        </option>
                    </select>
                </div>

                <!-- Sucursal -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal *</label>
                    <select v-model="form.idSucursal" class="w-full border rounded-lg p-2" :disabled="!form.idEmpresa || cargandoSucursales">
                        <option value="">Seleccione una sucursal</option>
                        <option v-for="s in sucursales" :key="s.idSucursal" :value="s.idSucursal">
                            {{ s.nombre }} (Código: {{ s.codigo }})
                        </option>
                    </select>
                    <p v-if="cargandoSucursales" class="text-xs text-gray-500 mt-1">Cargando sucursales...</p>
                    <p v-else-if="sucursales.length === 0 && form.idEmpresa" class="text-xs text-red-500 mt-1">
                        No hay sucursales para esta empresa. Crea una sucursal primero.
                    </p>
                </div>

                <!-- Tipo de Punto de Venta -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Punto de Venta *</label>
                    <select v-model="form.idTipoPuntoVenta" class="w-full border rounded-lg p-2">
                        <option value="">Seleccione un tipo</option>
                        <option v-for="t in tiposPuntoVenta" :key="t.idTipoPuntoVenta" :value="t.idTipoPuntoVenta">
                            {{ t.nombre }} (Código SIAT: {{ t.codigo }})
                        </option>
                    </select>
                </div>

                <!-- Nombre -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                    <input 
                        v-model="form.nombre" 
                        type="text" 
                        class="w-full border rounded-lg p-2" 
                        placeholder="Ej: Punto de Venta Principal" 
                    />
                </div>

                <!-- Dirección -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                    <input 
                        v-model="form.direccion" 
                        type="text" 
                        class="w-full border rounded-lg p-2" 
                        placeholder="Dirección del punto de venta" 
                    />
                </div>

                <!-- Opciones -->
                <div class="flex gap-6">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" v-model="form.es_movil" class="w-4 h-4" />
                        <span class="text-sm">Es móvil</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" v-model="form.puede_firmar" class="w-4 h-4" />
                        <span class="text-sm">Puede firmar</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" v-model="form.activo" class="w-4 h-4" />
                        <span class="text-sm">Activo</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button
                    @click="submit"
                    :disabled="!canSubmit || isSubmitting"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
                >
                    <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ isSubmitting ? 'Registrando...' : 'Registrar en SIAT' }}</span>
                </button>
            </div>
        </div>

        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
            <p class="text-xs text-blue-700">
                ⚠️ Al registrar un punto de venta, este se creará en el SIN (SIAT) y recibirá un código oficial.
                Este código será usado para generar facturas electrónicas.
            </p>
        </div>
    </div>
</template>