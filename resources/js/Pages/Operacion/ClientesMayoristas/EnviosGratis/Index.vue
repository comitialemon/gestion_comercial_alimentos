<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, watch, computed, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    grupos:      { type: Array, default: () => [] },
    grupoFiltro: { type: [String, Number], default: null },
    diasSemana:  { type: Object, default: () => ({
        1: 'Lunes', 2: 'Martes', 3: 'Miércoles', 4: 'Jueves',
        5: 'Viernes', 6: 'Sábado', 7: 'Domingo',
    })},
})

const toast = inject('toast')

// ==================== ESTADO ====================
const loading          = ref(false)
const guardando        = ref(false)
const clientes         = ref([])
const buscar           = ref('')
const grupoSeleccionado = ref(props.grupoFiltro || '')

// Debounce
let timerBuscar = null

// ==================== CARGAR CLIENTES ====================
const cargarClientes = async () => {
    loading.value = true
    try {
        const { data } = await axios.get('/operacion/pedidos/clientes-mayoristas/envios-gratis/clientes', {
            params: {
                q: buscar.value.trim() || undefined,
                grupo: grupoSeleccionado.value || undefined,
            },
        })
        if (data.success) {
            clientes.value = (data.data || []).map(c => ({
                ...c,
                DiasGratis: Array.isArray(c.DiasGratis) ? c.DiasGratis.map(Number) : [],
            }))
        } else {
            clientes.value = []
            toast?.error('Error', data.message || 'No se pudo cargar la lista')
        }
    } catch (e) {
        console.error(e)
        clientes.value = []
        toast?.error('Error', 'No se pudo cargar la lista')
    } finally {
        loading.value = false
    }
}

// ==================== TOGGLE DÍA ====================
const toggleDia = (cliente, dia) => {
    const idx = cliente.DiasGratis.indexOf(dia)
    if (idx >= 0) {
        cliente.DiasGratis.splice(idx, 1)
    } else {
        cliente.DiasGratis.push(dia)
        cliente.DiasGratis.sort((a, b) => a - b)
    }
}

// ==================== GUARDAR ====================
const guardar = async () => {
    if (guardando.value) return
    guardando.value = true

    try {
        const payload = {
            clientes: clientes.value.map(c => ({
                IdIdentificador: c.IdIdentificador,
                DiasGratis: c.DiasGratis,
            })),
        }
        const { data } = await axios.post('/operacion/pedidos/clientes-mayoristas/envios-gratis/guardar', payload)
        if (data.success) {
            toast?.success('Éxito', data.message || 'Días gratis guardados')
        } else {
            toast?.error('Error', data.message || 'No se pudo guardar')
        }
    } catch (e) {
        console.error(e)
        toast?.error('Error', e.response?.data?.message || 'No se pudo guardar')
    } finally {
        guardando.value = false
    }
}

// ==================== DEBOUNCE BÚSQUEDA ====================
watch(buscar, () => {
    clearTimeout(timerBuscar)
    timerBuscar = setTimeout(() => cargarClientes(), 300)
})

watch(grupoSeleccionado, () => cargarClientes())

// ==================== CONTADOR ====================
const totalConDias = computed(() => clientes.value.filter(c => c.DiasGratis.length > 0).length)
const totalSinDias = computed(() => clientes.value.filter(c => c.DiasGratis.length === 0).length)

// ==================== LIFECYCLE ====================
onMounted(() => {
    cargarClientes()
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">

                <!-- HEADER -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-calendar-check text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Envíos Gratis por Cliente</h1>
                            <p class="text-xs text-gray-500">Configura qué días de la semana el envío es gratis</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="cargarClientes" :disabled="loading"
                            class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition shadow-sm disabled:opacity-60">
                            <i class="fas fa-sync-alt text-[10px]" :class="{ 'fa-spin': loading }"></i>
                            {{ loading ? 'Cargando...' : 'Actualizar' }}
                        </button>
                        <button @click="guardar" :disabled="guardando || clientes.length === 0"
                            class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition shadow-sm disabled:opacity-60 disabled:cursor-not-allowed">
                            <i :class="guardando ? 'fas fa-spinner fa-spin' : 'fas fa-save'" class="text-[10px]"></i>
                            {{ guardando ? 'Guardando...' : 'Guardar cambios' }}
                        </button>
                    </div>
                </div>

                <!-- INFO -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4 flex items-start gap-2">
                    <i class="fas fa-info-circle text-blue-500 text-sm flex-shrink-0 mt-0.5"></i>
                    <div class="text-xs text-blue-700">
                        <p class="font-medium mb-0.5">¿Cómo funciona?</p>
                        <p>Marca los días en que el envío es <strong>GRATIS</strong> para cada cliente. Los días <strong>no marcados</strong> se cobrará el envío al momento del despacho.</p>
                    </div>
                </div>

                <!-- FILTROS -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-3 p-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative flex-1 min-w-[180px]">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[11px]"></i>
                            <input v-model="buscar" type="text" placeholder="Buscar cliente por nombre o CI/NIT..."
                                class="w-full border border-gray-300 rounded-md pl-7 pr-2.5 py-1.5 text-sm sm:text-xs outline-none focus:border-primary-500" />
                        </div>
                        <div class="flex items-center gap-1.5">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">
                                <i class="fas fa-layer-group text-primary-500 mr-0.5"></i> Grupo:
                            </label>
                            <select v-model="grupoSeleccionado"
                                class="border border-gray-300 rounded-md px-2 py-1.5 text-sm sm:text-xs outline-none focus:border-primary-500">
                                <option value="">Todos</option>
                                <option v-for="g in grupos" :key="g.IdGrupoCliente" :value="g.IdGrupoCliente">
                                    {{ g.Nombre }}
                                </option>
                            </select>
                        </div>
                        <span class="text-[11px] text-gray-500 px-2">
                            <b>{{ clientes.length }}</b> cliente(s) ·
                            <span class="text-emerald-600 font-bold">{{ totalConDias }}</span> con días ·
                            <span class="text-gray-400">{{ totalSinDias }}</span> sin días
                        </span>
                    </div>
                </div>

                <!-- TABLA -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 75vh; overflow-y: auto;">

                        <!-- Loading -->
                        <div v-if="loading" class="flex justify-center py-12">
                            <i class="fas fa-spinner fa-spin text-primary-500 text-2xl"></i>
                        </div>

                        <!-- VACÍO -->
                        <div v-else-if="clientes.length === 0" class="text-center py-12 text-gray-400">
                            <i class="fas fa-users-slash text-3xl mb-2 block"></i>
                            <p class="text-sm font-semibold">No hay clientes</p>
                            <p class="text-xs mt-1">Ajusta los filtros o asigna clientes a un grupo primero.</p>
                        </div>

                        <!-- TABLA -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-3 text-left text-[10px] font-bold text-gray-500 uppercase sticky left-0 bg-gray-50 min-w-[200px]">
                                        Cliente
                                    </th>
                                    <th v-for="(nombre, num) in diasSemana" :key="num"
                                        class="px-2 py-3 text-center text-[10px] font-bold text-gray-500 uppercase w-16">
                                        <span class="hidden sm:inline">{{ nombre }}</span>
                                        <span class="sm:hidden">{{ nombre.slice(0, 3) }}</span>
                                    </th>
                                    <th class="px-2 py-3 text-center text-[10px] font-bold text-gray-500 uppercase w-20">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr v-for="c in clientes" :key="c.IdIdentificador" class="hover:bg-gray-50 transition">
                                    <!-- Cliente -->
                                    <td class="px-3 py-2.5 sticky left-0 bg-white">
                                        <div class="text-xs font-bold text-gray-800">{{ c.Nombre }}</div>
                                        <div class="text-[10px] text-gray-400">CI/NIT: {{ c.CI_NIT || '-' }}</div>
                                    </td>

                                    <!-- Checkboxes días -->
                                    <td v-for="(nombre, num) in diasSemana" :key="num" class="px-2 py-2.5 text-center">
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="c.DiasGratis.includes(Number(num))"
                                                @change="toggleDia(c, Number(num))"
                                                class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500 focus:ring-2 cursor-pointer"
                                            />
                                        </label>
                                    </td>

                                    <!-- Total -->
                                    <td class="px-2 py-2.5 text-center">
                                        <span v-if="c.DiasGratis.length > 0"
                                            class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full font-bold">
                                            {{ c.DiasGratis.length }} día(s)
                                        </span>
                                        <span v-else
                                            class="text-[10px] bg-gray-100 text-gray-500 px-2 py-1 rounded-full">
                                            Sin gratis
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Botón guardar flotante (mobile) -->
                <div class="sm:hidden fixed bottom-4 right-4 z-40">
                    <button @click="guardar" :disabled="guardando || clientes.length === 0"
                        class="bg-primary-600 hover:bg-primary-700 text-white w-14 h-14 rounded-full shadow-lg flex items-center justify-center disabled:opacity-60">
                        <i :class="guardando ? 'fas fa-spinner fa-spin' : 'fas fa-save'" class="text-lg"></i>
                    </button>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) { input, select, textarea { font-size: 16px !important; } }
@media (min-width: 1024px) { input, select, button { font-size: 13px !important; } }
.overflow-x-auto { -webkit-overflow-scrolling: touch; scrollbar-width: thin; }
.overflow-x-auto::-webkit-scrollbar { height: 6px; }
.overflow-x-auto::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.overflow-x-auto::-webkit-scrollbar-track { background: #f1f5f9; }
.overflow-y-auto::-webkit-scrollbar { width: 6px; }
.overflow-y-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
.overflow-y-auto::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

/* Checkbox custom un poco más visible */
input[type="checkbox"] {
    width: 18px;
    height: 18px;
}
</style>