<script setup>
import { ref, computed, watch } from 'vue'  // ← Agregar 'computed' aquí
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    bases: Array,
    empresas: Array,
})

const dbSel = ref('')
const clientes = ref([])
const clienteSel = ref('')
const cargandoClientes = ref(false)
const enviando = ref(false)

const formFact = ref({
    modalidad: 1,
    ambiente: 2,
    token: '',
    codigo_sistema: '',
})

// computed ahora funciona porque lo importaste
const puedeImportar = computed(() => {
    return (
        !!dbSel.value &&
        !!clienteSel.value &&
        String(formFact.value.token).trim().length > 0 &&
        String(formFact.value.codigo_sistema).trim().length > 0 &&
        !enviando.value
    )
})

const cargarClientes = async () => {
    clientes.value = []
    clienteSel.value = ''
    if (!dbSel.value) return

    cargandoClientes.value = true
    try {
        const res = await axios.get('/facturacion/empresas/clientes', { params: { db: dbSel.value } })
        clientes.value = res.data.clientes || []
    } catch (e) {
        console.error('Error cargando clientes:', e)
    } finally {
        cargandoClientes.value = false
    }
}

const importar = () => {
    enviando.value = true

    router.post('/facturacion/empresas/importar', {
        db: dbSel.value,
        idCliente: Number(clienteSel.value),
        modalidad: Number(formFact.value.modalidad),
        ambiente: Number(formFact.value.ambiente),
        token: String(formFact.value.token).trim(),
        codigo_sistema: String(formFact.value.codigo_sistema).trim(),
    }, {
        preserveScroll: true,
        onFinish: () => {
            enviando.value = false
        }
    })
}

watch(() => props.bases, (b) => {
    if (!dbSel.value && Array.isArray(b) && b.length) {
        dbSel.value = b[0]
        cargarClientes()
    }
}, { immediate: true })
</script>

<template>
    <div class="p-6 max-w-6xl mx-auto">
        <h1 class="text-2xl font-semibold mb-6">Importar empresas desde Gestión</h1>

        <div v-if="$page.props.flash?.success" class="mb-4 p-3 rounded bg-green-50 border border-green-200 text-green-800">
            {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error" class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-800">
            {{ $page.props.flash.error }}
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl p-5 shadow space-y-4">
                <div>
                    <label class="text-sm font-medium">Base de datos (gestión)</label>
                    <select v-model="dbSel" @change="cargarClientes" class="w-full border rounded-lg p-2">
                        <option value="">-- Elegir base --</option>
                        <option v-for="b in bases" :key="b" :value="b">{{ b }}</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium">Cliente</label>
                    <select v-model="clienteSel" class="w-full border rounded-lg p-2" :disabled="!dbSel || cargandoClientes">
                        <option value="">-- Elegir cliente --</option>
                        <option v-for="c in clientes" :key="c.id" :value="c.id">
                            {{ c.nombre }} — NIT: {{ c.nit }}
                        </option>
                    </select>
                    <p v-if="cargandoClientes" class="text-xs text-gray-500 mt-1">Cargando clientes…</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Modalidad</label>
                        <select v-model.number="formFact.modalidad" class="w-full border rounded-lg p-2">
                            <option value="1">Electrónica</option>
                            <option value="2">Computarizada</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Ambiente</label>
                        <select v-model.number="formFact.ambiente" class="w-full border rounded-lg p-2">
                            <option value="1">Producción</option>
                            <option value="2">Piloto</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium">Token *</label>
                    <input v-model="formFact.token" class="w-full border rounded-lg p-2 font-mono" placeholder="Token JWT" />
                </div>

                <div>
                    <label class="text-sm font-medium">Código de Sistema *</label>
                    <input v-model="formFact.codigo_sistema" class="w-full border rounded-lg p-2" placeholder="Código SIAT" />
                </div>

                <button
                    :disabled="!puedeImportar"
                    @click="importar"
                    class="w-full px-4 py-2 rounded-lg text-white disabled:opacity-50"
                    :class="puedeImportar ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-indigo-400'"
                >
                    {{ enviando ? 'Importando…' : 'Importar y mapear' }}
                </button>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow">
                <h2 class="font-medium mb-3">Empresas actuales (Facturación)</h2>
                <div class="max-h-80 overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-50 text-left">
                            <tr>
                                <th class="p-2">ID</th>
                                <th class="p-2">Nombre</th>
                                <th class="p-2">NIT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in empresas" :key="e.idEmpresa" class="border-b">
                                <td class="p-2">{{ e.idEmpresa }}</td>
                                <td class="p-2">{{ e.nombre }}</td>
                                <td class="p-2">{{ e.nit }}</td>
                            </tr>
                            <tr v-if="!empresas?.length">
                                <td colspan="3" class="p-3 text-gray-500">No hay empresas aún.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>