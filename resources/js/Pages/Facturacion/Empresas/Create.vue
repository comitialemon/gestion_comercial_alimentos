<!-- resources/js/Pages/Facturacion/Empresas/Create.vue -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const page = usePage()
const props = defineProps({ bases: Array })

const AMBIENTES = [
    { label: 'Producción', value: 1 },
    { label: 'Pruebas y Piloto', value: 2 },
]
const MODALIDADES = [
    { label: 'Electrónica en Línea', value: 1 },
    { label: 'Computarizada en Línea', value: 2 },
]

const form = ref({
    db: '',
    nombre: '',
    nit: '',
    modalidad: 1,
    ambiente: 2,
    direccion: '',
    fono: '',
    celular: '',
    ci_rep: '',
    rep: '',
    token: '',
    codigo_sistema: '',
})

const fechaIngreso = ref('')
const cargandoFecha = ref(false)
const errors = ref({})
const enviando = ref(false)

const cargarUltimoIdFecha = async () => {
    const db = form.value.db
    if (!db) {
        fechaIngreso.value = ''
        return
    }
    
    cargandoFecha.value = true
    try {
        const { data } = await axios.get('/facturacion/empresas/ultimo-id-fecha', { params: { db } })

        if (data.id_fecha && data.id_fecha > 0) {
            fechaIngreso.value = data.fecha || 'Fecha encontrada'
        } else {
            fechaIngreso.value = '⚠️ No se encontró fecha de inicio'
            errors.value.id_fecha = 'La base no tiene registro en todos_fecha'
        }
    } catch (e) {
        console.error('Error:', e)
        fechaIngreso.value = '❌ Error al cargar fecha'
        errors.value.id_fecha = e.response?.data?.message || 'Error de conexión'
    } finally {
        cargandoFecha.value = false
    }
}

watch(() => form.value.db, () => {
    cargarUltimoIdFecha()
    errors.value = {}
})

onMounted(() => {
    if (!form.value.db && props.bases?.length) {
        form.value.db = props.bases[0]
        cargarUltimoIdFecha()
    }
})

const canSubmit = computed(() =>
    !!form.value.db &&
    !!form.value.nit &&
    !!form.value.nombre &&
    String(form.value.token).trim().length > 0 &&
    String(form.value.codigo_sistema).trim().length > 0 &&
    fechaIngreso.value && 
    !fechaIngreso.value.includes('⚠️') && 
    !fechaIngreso.value.includes('❌') &&
    !enviando.value
)

const submit = () => {
    enviando.value = true
    errors.value = {}
    
    router.post('/facturacion/empresas/crear', form.value, {
        preserveScroll: true,
        onSuccess: () => {
            enviando.value = false
        },
        onError: (err) => {
            console.error('Errores:', err)
            errors.value = err
            enviando.value = false
            const errorMsg = err.message || err.error || JSON.stringify(err)
            alert('Error al crear empresa: ' + errorMsg)
        }
    })
}

const volver = () => {
    router.get('/facturacion/empresas')
}

const flashSuccess = computed(() => page.props.flash?.success)
const flashError = computed(() => page.props.flash?.error)
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-100 rounded-2xl mb-3">
                        <i class="fas fa-plus text-emerald-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Crear Empresa</h1>
                    <p class="text-xs text-gray-500">Registra una nueva empresa en gestión y facturación</p>
                </div>

                <!-- Botón Volver -->
                <div class="mb-4">
                    <button @click="volver" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                        <i class="fas fa-arrow-left text-xs"></i> Volver
                    </button>
                </div>

                <!-- Flash Messages -->
                <div v-if="flashSuccess" class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg">
                    ✅ {{ flashSuccess }}
                </div>
                <div v-if="flashError" class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg">
                    ❌ {{ flashError }}
                </div>

                <!-- Errores de validación -->
                <div v-if="Object.keys(errors).length" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm font-semibold text-red-800 mb-2">Errores:</p>
                    <ul class="list-disc list-inside text-sm text-red-700">
                        <li v-for="(error, key) in errors" :key="key">{{ key }}: {{ Array.isArray(error) ? error.join(', ') : error }}</li>
                    </ul>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Base de gestión *</label>
                                <select v-model="form.db" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando">
                                    <option value="">-- Elegir base --</option>
                                    <option v-for="b in bases" :key="b" :value="b">{{ b }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">NIT *</label>
                                <input v-model="form.nit" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando" placeholder="123456789" />
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre / Razón Social *</label>
                                <input v-model="form.nombre" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando" placeholder="Nombre de la empresa" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Modalidad *</label>
                                <select v-model.number="form.modalidad" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando">
                                    <option v-for="m in MODALIDADES" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ambiente *</label>
                                <select v-model.number="form.ambiente" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando">
                                    <option v-for="a in AMBIENTES" :key="a.value" :value="a.value">{{ a.label }}</option>
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                                <input v-model="form.direccion" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando" placeholder="Dirección" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                <input v-model="form.fono" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando" placeholder="Teléfono" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Celular</label>
                                <input v-model="form.celular" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando" placeholder="Celular" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CI Rep. Legal</label>
                                <input v-model="form.ci_rep" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Rep. Legal</label>
                                <input v-model="form.rep" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Token *</label>
                                <input v-model="form.token" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" :disabled="enviando" placeholder="Token JWT" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Código de Sistema *</label>
                                <input v-model="form.codigo_sistema" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="enviando" placeholder="Código SIAT" />
                            </div>

                            <div class="md:col-span-2 bg-gray-50 rounded-lg p-3" :class="{ 'border border-red-200 bg-red-50': errors.id_fecha }">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Fecha inicio operaciones</span>
                                    <i v-if="cargandoFecha" class="fas fa-spinner fa-spin text-gray-400"></i>
                                    <i v-else-if="fechaIngreso && !fechaIngreso.includes('⚠️') && !fechaIngreso.includes('❌')" class="fas fa-check-circle text-green-500"></i>
                                    <i v-else-if="fechaIngreso" class="fas fa-exclamation-triangle text-red-500"></i>
                                </div>
                                <p class="text-sm mt-1" :class="{ 'text-red-600': fechaIngreso?.includes('⚠️') || fechaIngreso?.includes('❌'), 'text-gray-600': !fechaIngreso?.includes('⚠️') }">
                                    {{ fechaIngreso || 'Selecciona una base para cargar la fecha' }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">Se toma del último registro de todos_fecha</p>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">
                        <button @click="volver" class="px-4 py-2 border rounded-lg text-gray-700 text-sm hover:bg-gray-100" :disabled="enviando">
                            Cancelar
                        </button>
                        <button @click="submit" :disabled="!canSubmit" class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-2">
                            <i v-if="enviando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ enviando ? 'Creando...' : 'Crear Empresa' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>