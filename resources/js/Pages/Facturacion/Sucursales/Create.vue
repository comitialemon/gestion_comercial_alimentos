<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    bases: {
        type: Array,
        default: () => []
    },
    municipios: {
        type: Array,
        default: () => []
    }
})

// ✅ Inicializar form correctamente
const form = ref({
    db: '',
    idCliente: '',
    numero: '',
    nombre: '',
    direccion: '',
    idMunicipio: '',
    idPlaza: '',
    telefono: '',
    celular: '',
    categoria: '',
    activoInactivo: true,
    orden: '',
})

const clientes = ref([])
const plazas = ref([])
const empresaResuelta = ref(null)
const cargandoClientes = ref(false)
const cargandoPlazas = ref(false)

// ✅ Función para cargar clientes
const cargarClientes = async () => {
    if (!form.value.db) {
        clientes.value = []
        return
    }
    
    cargandoClientes.value = true
    try {
        const url = `/facturacion/sucursales/clientes?db=${encodeURIComponent(form.value.db)}`
        const { data } = await axios.get(url)
        clientes.value = data.clientes || []
    } catch (error) {
        console.error('Error cargando clientes:', error)
        clientes.value = []
    } finally {
        cargandoClientes.value = false
    }
}

// ✅ Función para cargar plazas
const cargarPlazas = async () => {
    if (!form.value.db) {
        plazas.value = []
        return
    }
    
    cargandoPlazas.value = true
    try {
        const url = `/facturacion/sucursales/plazas?db=${encodeURIComponent(form.value.db)}`
        const { data } = await axios.get(url)
        plazas.value = data.plazas || []
    } catch (error) {
        console.error('Error cargando plazas:', error)
        plazas.value = []
    } finally {
        cargandoPlazas.value = false
    }
}

// ✅ Función para resolver empresa
const resolverEmpresa = async () => {
    empresaResuelta.value = null
    if (!form.value.db || !form.value.idCliente) return
    
    try {
        const url = `/facturacion/sucursales/empresa-por-cliente?db=${encodeURIComponent(form.value.db)}&idCliente=${form.value.idCliente}`
        const { data } = await axios.get(url)
        empresaResuelta.value = data.empresa || null
    } catch (error) {
        console.error('Error resolviendo empresa:', error)
        empresaResuelta.value = null
    }
}

// ✅ Watcher para cuando cambia la base de datos
watch(() => form.value.db, (newVal) => {
    if (newVal) {
        cargarClientes()
        cargarPlazas()
        form.value.idCliente = ''
        form.value.idPlaza = ''
        empresaResuelta.value = null
    } else {
        clientes.value = []
        plazas.value = []
    }
})

// ✅ Watcher para cuando cambia el cliente
watch(() => form.value.idCliente, (newVal) => {
    if (newVal) {
        resolverEmpresa()
    } else {
        empresaResuelta.value = null
    }
})

const canSubmit = computed(() =>
    !!form.value.db &&
    !!form.value.idCliente &&
    !!form.value.idPlaza &&
    !!form.value.telefono &&
    !!form.value.celular &&
    !!form.value.categoria &&
    !!form.value.nombre &&
    !!form.value.direccion &&
    !!form.value.numero
)

const submit = () => {
    router.post('/facturacion/sucursales', { ...form.value }, { preserveScroll: true })
}

onMounted(() => {
    if (!form.value.db && props.bases?.length > 0) {
        form.value.db = props.bases[0]
    }
})
</script>

<template>
    <div class="max-w-5xl mx-auto p-6">
        <h1 class="text-2xl font-semibold mb-6">Crear Sucursal</h1>

        <div class="bg-white rounded-2xl shadow p-5 grid md:grid-cols-2 gap-4">
            <!-- Base de datos -->
            <div>
                <label class="text-sm font-medium">Base de gestión</label>
                <select v-model="form.db" class="w-full border rounded-lg p-2 mt-1">
                    <option value="">-- Elegir base --</option>
                    <option v-for="b in props.bases" :key="b" :value="b">{{ b }}</option>
                </select>
            </div>

            <!-- Cliente (Empresa) -->
            <div>
                <label class="text-sm font-medium">Cliente (Empresa)</label>
                <select v-model="form.idCliente" class="w-full border rounded-lg p-2 mt-1" :disabled="!form.db || cargandoClientes">
                    <option value="">-- Elegir cliente --</option>
                    <option v-for="c in clientes" :key="c.id" :value="c.id">
                        {{ c.nombre }} — NIT: {{ c.nit }}
                    </option>
                </select>
                <p v-if="cargandoClientes" class="text-xs text-gray-500 mt-1">Cargando clientes...</p>
                <p v-if="empresaResuelta" class="text-xs text-green-600 mt-1">
                    ✅ Empresa encontrada: {{ empresaResuelta.nombre }} (NIT: {{ empresaResuelta.nit }})
                </p>
            </div>

            <!-- Número de Sucursal -->
            <div>
                <label class="text-sm font-medium">Número de Sucursal</label>
                <input v-model="form.numero" type="number" class="w-full border rounded-lg p-2 mt-1" placeholder="Ej. 1, 2, 3..." />
            </div>

            <!-- Municipio -->
            <div>
                <label class="text-sm font-medium">Municipio (opcional)</label>
                <select v-model="form.idMunicipio" class="w-full border rounded-lg p-2 mt-1">
                    <option value="">-- Elegir municipio --</option>
                    <option v-for="m in props.municipios" :key="m.id" :value="m.id">
                        {{ m.nombre }}
                    </option>
                </select>
            </div>

            <!-- Nombre -->
            <div class="md:col-span-2">
                <label class="text-sm font-medium">Nombre</label>
                <input v-model="form.nombre" class="w-full border rounded-lg p-2 mt-1" placeholder="Nombre de la sucursal" />
            </div>

            <!-- Dirección -->
            <div class="md:col-span-2">
                <label class="text-sm font-medium">Dirección</label>
                <input v-model="form.direccion" class="w-full border rounded-lg p-2 mt-1" placeholder="Dirección completa" />
            </div>

            <!-- Plaza -->
            <div>
                <label class="text-sm font-medium">Plaza</label>
                <select v-model="form.idPlaza" class="w-full border rounded-lg p-2 mt-1" :disabled="!form.db || cargandoPlazas">
                    <option value="">-- Elegir plaza --</option>
                    <option v-for="p in plazas" :key="p.id" :value="p.id">
                        {{ p.nombre }} <span v-if="p.abrev">({{ p.abrev }})</span>
                    </option>
                </select>
                <p v-if="cargandoPlazas" class="text-xs text-gray-500 mt-1">Cargando plazas...</p>
            </div>

            <!-- Teléfono -->
            <div>
                <label class="text-sm font-medium">Teléfono</label>
                <input v-model="form.telefono" class="w-full border rounded-lg p-2 mt-1" placeholder="Teléfono" />
            </div>

            <!-- Celular -->
            <div>
                <label class="text-sm font-medium">Celular</label>
                <input v-model="form.celular" class="w-full border rounded-lg p-2 mt-1" placeholder="Celular" />
            </div>

            <!-- Categoría -->
            <div>
                <label class="text-sm font-medium">Categoría</label>
                <input v-model="form.categoria" class="w-full border rounded-lg p-2 mt-1" placeholder="Categoría" />
            </div>

            <!-- Orden -->
            <div>
                <label class="text-sm font-medium">Orden</label>
                <input v-model="form.orden" type="number" class="w-full border rounded-lg p-2 mt-1" placeholder="Orden (opcional)" />
            </div>

            <!-- Activo -->
            <div class="flex items-center gap-2 mt-2">
                <input id="activo" type="checkbox" v-model="form.activoInactivo" class="w-4 h-4" />
                <label for="activo" class="text-sm">Activo</label>
            </div>
        </div>

        <button
            :disabled="!canSubmit"
            @click="submit"
            class="mt-4 px-4 py-2 rounded-lg bg-emerald-600 text-white disabled:opacity-50 hover:bg-emerald-700"
        >
            Crear en ambas y mapear
        </button>
    </div>
</template>