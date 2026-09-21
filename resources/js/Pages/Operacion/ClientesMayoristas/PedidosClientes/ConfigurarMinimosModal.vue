<script setup>
import { ref, computed, watch, inject } from 'vue'
import axios from 'axios'

const toast = inject('toast')

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    identificador: {
        type: Object,
        default: null
    },
    contenedor: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['close', 'saved'])

// =============================================
// ESTADO
// =============================================
const loading = ref(false)
const guardando = ref(false)
const grupos = ref([])

// =============================================
// COMPUTADOS
// =============================================
const totalConfigurados = computed(() => {
    return grupos.value.filter(g => g.CantidadMinimaGrupo !== '' && parseFloat(g.CantidadMinimaGrupo) > 0).length
})

const totalGrupos = computed(() => {
    return grupos.value.length
})

const puedeGuardar = computed(() => {
    return grupos.value.length > 0 && !guardando.value
})

const todosConfigurados = computed(() => {
    return totalGrupos.value > 0 && totalConfigurados.value === totalGrupos.value
})

// =============================================
// MÉTODOS
// =============================================
const cargarGrupos = async () => {
    if (!props.identificador?.IdIdentificador) return

    loading.value = true

    try {
        // ✅ ENVIAR id_contenedor para filtrar solo los grupos de ese contenedor
        const params = {}
        if (props.contenedor?.IdContenedor) {
            params.id_contenedor = props.contenedor.IdContenedor
        }

        const response = await axios.get(
            `/operacion/pedidos/clientes-mayoristas/clientes/${props.identificador.IdIdentificador}/minimos`,
            { params }
        )

        if (response.data.success) {
            grupos.value = response.data.data.map(g => ({
                ...g,
                CantidadMinimaGrupo: g.CantidadMinimaGrupo !== null ? g.CantidadMinimaGrupo : ''
            }))
        }
    } catch (error) {
        console.error('Error al cargar grupos:', error)
        toast?.error('Error', 'No se pudieron cargar los grupos')
    } finally {
        loading.value = false
    }
}

const guardarMinimos = async () => {
    const gruposConMinimo = grupos.value.filter(
        g => g.CantidadMinimaGrupo !== '' && parseFloat(g.CantidadMinimaGrupo) > 0
    )

    if (gruposConMinimo.length === 0) {
        toast?.warning('Validación', 'Ingrese al menos un mínimo')
        return
    }

    guardando.value = true

    try {
        const payload = {
            grupos: grupos.value
                .filter(g => g.CantidadMinimaGrupo !== '' && parseFloat(g.CantidadMinimaGrupo) > 0)
                .map(g => ({
                    IdGrupoAnalisis: g.IdGrupoAnalisis,
                    CantidadMinimaGrupo: parseFloat(g.CantidadMinimaGrupo)
                }))
        }

        const response = await axios.post(
            `/operacion/pedidos/clientes-mayoristas/clientes/${props.identificador.IdIdentificador}/minimos`,
            payload
        )

        if (response.data.success) {
            toast?.success('Éxito', response.data.message || 'Mínimos guardados correctamente')
            emit('saved')
            cerrar()
        } else {
            toast?.error('Error', response.data.message || 'Error al guardar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar')
    } finally {
        guardando.value = false
    }
}

const cerrar = () => {
    emit('close')
}

// =============================================
// WATCHERS
// =============================================
watch(() => props.visible, (newVal) => {
    if (newVal && props.identificador) {
        cargarGrupos()
    }
})
</script>

<template>
    <Teleport to="body">
        <div 
            v-if="visible"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[60] p-3 sm:p-4"
            @click.self="cerrar"
        >
            <div class="bg-white rounded-xl w-full max-w-3xl max-h-[95vh] overflow-hidden shadow-2xl animate-fade-in-up flex flex-col">
                
                <!-- ==================== HEADER ==================== -->
                <div class="px-3 py-2.5 bg-primary-600 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-cog text-white text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-white text-sm truncate flex items-center gap-2">
                                Configurar Mínimos
                            </h3>
                            <p class="text-[10px] text-white/80 truncate mt-0.5">
                                <i class="fas fa-user text-[8px] mr-1"></i>
                                {{ identificador?.Nombre || 'Cliente' }}
                                <span class="mx-1 opacity-60">•</span>
                                <i class="fas fa-box text-[8px] mr-1"></i>
                                {{ contenedor?.Codigo || 'Contenedor' }}
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="cerrar"
                        class="text-white/80 hover:text-white hover:bg-white/10 rounded-lg p-1.5 transition flex-shrink-0"
                    >
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <!-- ==================== INFO ==================== -->
                <div class="px-3 py-2 bg-primary-50 border-b border-primary-100 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-1.5 text-[10px] text-primary-700">
                        <i class="fas fa-info-circle text-primary-500 text-[10px]"></i>
                        <span>
                            Solo grupos de este contenedor. Los mínimos se comparten entre contenedores del mismo cliente.
                        </span>
                    </div>
                    <span class="text-[10px] font-medium text-primary-700 bg-white px-2 py-0.5 rounded-full whitespace-nowrap border border-primary-200">
                        {{ totalConfigurados }} / {{ totalGrupos }}
                    </span>
                </div>

                <!-- ==================== CUERPO ==================== -->
                <div class="p-3 overflow-y-auto flex-1">
                    
                    <!-- Loading -->
                    <div v-if="loading" class="flex flex-col items-center justify-center py-8">
                        <div class="w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
                        <p class="text-xs text-gray-400 mt-2">Cargando grupos...</p>
                    </div>

                    <!-- Sin grupos -->
                    <div v-else-if="grupos.length === 0" class="text-center py-10 text-gray-400">
                        <i class="fas fa-layer-group text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-sm">Este contenedor no tiene grupos asignados</p>
                        <p class="text-xs mt-1">Agregue grupos al contenedor primero</p>
                    </div>

                    <!-- Lista de grupos -->
                    <div v-else class="space-y-2">
                        <div 
                            v-for="grupo in grupos" 
                            :key="grupo.IdGrupoAnalisis"
                            class="rounded-lg border transition-all"
                            :class="{
                                'border-emerald-300 bg-emerald-50/50': grupo.CantidadMinimaGrupo !== '' && parseFloat(grupo.CantidadMinimaGrupo) > 0,
                                'border-gray-200 bg-gray-50': grupo.CantidadMinimaGrupo === '' || parseFloat(grupo.CantidadMinimaGrupo) <= 0
                            }"
                        >
                            <div class="p-2.5">
                                <!-- Header del grupo -->
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <div 
                                            class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                                            :class="grupo.Configurado 
                                                ? 'bg-emerald-100 text-emerald-600' 
                                                : 'bg-gray-200 text-gray-500'"
                                        >
                                            <i class="fas fa-layer-group text-xs"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="font-semibold text-gray-800 text-xs truncate">
                                                {{ grupo.NombreGrupo }}
                                            </h4>
                                            <p class="text-[9px] text-gray-500">
                                                <i class="fas fa-boxes text-[8px] mr-1"></i>
                                                {{ grupo.Contenedores?.length || 0 }} contenedor(es)
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Badge de estado -->
                                    <span 
                                        class="text-[9px] px-1.5 py-0.5 rounded-full font-medium whitespace-nowrap"
                                        :class="grupo.Configurado 
                                            ? 'bg-emerald-100 text-emerald-700' 
                                            : 'bg-yellow-100 text-yellow-700'"
                                    >
                                        <i :class="grupo.Configurado ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'" class="mr-1 text-[8px]"></i>
                                        {{ grupo.Configurado ? 'Configurado' : 'Sin configurar' }}
                                    </span>
                                </div>

                                <!-- Contenedores -->
                                <div v-if="grupo.Contenedores?.length > 0" class="mb-2 flex flex-wrap gap-1">
                                    <span 
                                        v-for="cont in grupo.Contenedores" 
                                        :key="cont"
                                        class="text-[9px] bg-white border border-gray-200 text-gray-600 px-1.5 py-0.5 rounded font-mono"
                                    >
                                        {{ cont }}
                                    </span>
                                </div>

                                <!-- Input del mínimo -->
                                <div class="flex items-center gap-2">
                                    <label class="text-[10px] font-medium text-gray-600 whitespace-nowrap flex items-center gap-1">
                                        <i class="fas fa-arrow-down text-primary-500 text-[9px]"></i>
                                        Mínimo:
                                    </label>
                                    <input
                                        type="number"
                                        step="1"
                                        min="0"
                                        v-model="grupo.CantidadMinimaGrupo"
                                        class="flex-1 border rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none transition"
                                        :class="grupo.CantidadMinimaGrupo !== '' && parseFloat(grupo.CantidadMinimaGrupo) > 0
                                            ? 'border-emerald-400 bg-white focus:border-emerald-500'
                                            : 'border-gray-300 bg-white focus:border-primary-500'"
                                        placeholder="0"
                                    />
                                    <span class="text-[10px] text-gray-500 whitespace-nowrap">und</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="px-3 py-2 border-t border-gray-200 bg-gray-50 flex items-center justify-between gap-2 flex-shrink-0">
                    <div class="text-[10px] text-gray-500">
                        <span v-if="todosConfigurados" class="text-emerald-600">
                            <i class="fas fa-check-circle mr-1 text-[9px]"></i>
                            Todos los grupos configurados
                        </span>
                        <span v-else>
                            <i class="fas fa-info-circle mr-1 text-[9px]"></i>
                            Puede configurar los grupos que necesite
                        </span>
                    </div>
                    <div class="flex gap-1.5">
                        <button 
                            @click="cerrar"
                            :disabled="guardando"
                            class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-xs font-medium transition"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="guardarMinimos"
                            :disabled="!puedeGuardar"
                            class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            {{ guardando ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-fade-in-up {
    animation: fadeInUp 0.2s ease-out;
}
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
}
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
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
@media (min-width: 1024px) {
    input, button {
        font-size: 13px !important;
    }
}
</style>