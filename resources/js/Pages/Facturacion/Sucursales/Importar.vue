<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    bases: Array,
    municipios: Array,
})

const dbSel = ref('')
const clientes = ref([])
const clienteSel = ref('')
const sucursales = ref([])
const sucSel = ref('')
const idMunicipio = ref('')

const puede = computed(() => !!dbSel.value && !!sucSel.value)

const cargarClientes = async () => {
    clientes.value = []
    clienteSel.value = ''
    sucursales.value = []
    sucSel.value = ''
    if (!dbSel.value) return
    
    const { data } = await axios.get(route('facturacion.importar.sucursales.clientes'), { params: { db: dbSel.value } })
    clientes.value = data.clientes || []
}

const cargarSucursales = async () => {
    sucursales.value = []
    sucSel.value = ''
    if (!dbSel.value || !clienteSel.value) return
    
    const { data } = await axios.get(route('facturacion.importar.sucursales.lista'), {
        params: { db: dbSel.value, idCliente: clienteSel.value }
    })
    sucursales.value = data.sucursales || []
}

watch(dbSel, cargarClientes)
watch(clienteSel, cargarSucursales)

const importar = () => {
    router.post(route('facturacion.importar.sucursales.store'), {
        db: dbSel.value,
        idClienteSucursal: sucSel.value,
        idMunicipio: idMunicipio.value || null,
    }, { preserveScroll: true })
}
</script>

<template>
    <div class="max-w-5xl mx-auto p-6">
        <h1 class="text-2xl font-semibold mb-6">Importar Sucursal</h1>

        <div class="bg-white rounded-2xl p-5 shadow space-y-4">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm">Base de gestión</label>
                    <select v-model="dbSel" class="w-full border rounded-lg p-2">
                        <option value="">-- Elegir base --</option>
                        <option v-for="b in props.bases" :key="b" :value="b">{{ b }}</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm">Cliente</label>
                    <select v-model="clienteSel" class="w-full border rounded-lg p-2">
                        <option value="">-- Elegir cliente --</option>
                        <option v-for="c in clientes" :key="c.id" :value="c.id">
                            {{ c.nombre }} — NIT: {{ c.nit }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="text-sm">Sucursal existente</label>
                    <select v-model="sucSel" class="w-full border rounded-lg p-2">
                        <option value="">-- Elegir sucursal --</option>
                        <option v-for="s in sucursales" :key="s.id" :value="s.id">
                            #{{ s.numero }} — {{ s.nombre }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="text-sm">Municipio</label>
                    <select v-model="idMunicipio" class="w-full border rounded-lg p-2">
                        <option value="">-- Elegir municipio --</option>
                        <option v-for="m in props.municipios" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                    </select>
                </div>
            </div>

            <button
                :disabled="!puede"
                @click="importar"
                class="px-4 py-2 rounded-lg text-white disabled:opacity-50 bg-indigo-600 hover:bg-indigo-700"
            >
                Importar y mapear
            </button>
        </div>
    </div>
</template>