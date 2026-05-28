<!-- resources/js/Pages/Gestion/Impuestos/LugarVenta/Create.vue -->
<script setup>
import { ref, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    lugar: Object,
    empresas: Array,
    sucursales: Array,
    defaults: Object,
    editando: Boolean,
})

const form = ref({
    Orden: props.lugar?.Orden || 0,
    Lugar: props.lugar?.Lugar || '',
    IdCliente: props.lugar?.IdCliente || props.defaults?.IdCliente || '',
    IdSucursal: props.lugar?.IdSucursal || props.defaults?.IdSucursal || '',
})

const sucursalesDisponibles = ref(props.sucursales || [])
const cargandoSucursales = ref(false)

const cargarSucursales = async (clienteId) => {
    if (!clienteId) {
        sucursalesDisponibles.value = []
        form.value.IdSucursal = ''
        return
    }
    
    cargandoSucursales.value = true
    try {
        const response = await axios.get(`/gestion/lugar-venta/sucursales/${clienteId}`)
        sucursalesDisponibles.value = response.data
        form.value.IdSucursal = ''
    } catch (err) {
        console.error('Error:', err)
        sucursalesDisponibles.value = []
    } finally {
        cargandoSucursales.value = false
    }
}

watch(() => form.value.IdCliente, (newVal) => {
    if (!props.editando) {
        cargarSucursales(newVal)
    }
})

onMounted(() => {
    if (form.value.IdCliente && !props.editando) {
        cargarSucursales(form.value.IdCliente)
    }
})

const guardar = () => {
    if (props.editando) {
        router.put(`/gestion/lugar-venta/${props.lugar.IdLugar}`, form.value)
    } else {
        router.post('/gestion/lugar-venta', form.value)
    }
}

const volver = () => {
    router.get('/gestion/lugar-venta')
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-3" :class="editando ? 'bg-secondary-100' : 'bg-emerald-100'">
                        <i class="fas" :class="editando ? 'fa-pencil-alt text-secondary-600' : 'fa-plus text-emerald-600'"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">
                        {{ editando ? 'Editar Lugar de Venta' : 'Nuevo Lugar de Venta' }}
                    </h1>
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Empresa *</label>
                            <select v-model="form.IdCliente" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="editando">
                                <option value="">Seleccione una empresa</option>
                                <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre }}</option>
                            </select>
                            <p v-if="editando" class="text-xs text-gray-400 mt-1">No se puede cambiar la empresa en edición</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal *</label>
                            <select v-model="form.IdSucursal" class="w-full border rounded-lg px-3 py-2 text-sm" :disabled="!form.IdCliente || cargandoSucursales">
                                <option value="">Seleccione una sucursal</option>
                                <option v-for="s in sucursalesDisponibles" :key="s.id" :value="s.id">
                                    {{ s.nombre }} {{ s.numero ? `(${s.numero})` : '' }}
                                </option>
                            </select>
                            <p v-if="cargandoSucursales" class="text-xs text-gray-400 mt-1">Cargando sucursales...</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Orden *</label>
                            <input type="number" v-model.number="form.Orden" min="0" class="w-full border rounded-lg px-3 py-2 text-sm" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Lugar *</label>
                            <input type="text" v-model="form.Lugar" maxlength="50" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Ej: Local Central" />
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">
                        <button @click="volver" class="px-4 py-2 border rounded-lg text-gray-700 text-sm hover:bg-gray-100">
                            Cancelar
                        </button>
                        <button @click="guardar" :disabled="!form.IdCliente || !form.IdSucursal || !form.Lugar" class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                            {{ editando ? 'Actualizar' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>