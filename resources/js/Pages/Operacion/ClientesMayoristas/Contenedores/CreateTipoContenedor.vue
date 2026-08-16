<script setup>
import { ref, computed, watch, inject } from 'vue'
import axios from 'axios'

const toast = inject('toast')

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    sucursales: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['close', 'created'])

// ==================== ESTADO ====================
const loading = ref(false)
const form = ref({
    Nombre: '',
    IdSucursal: '',
})

const errors = ref({})

// ==================== COMPUTADOS ====================
const mostrar = computed({
    get: () => props.visible,
    set: (val) => {
        if (!val) emit('close')
    }
})

// ==================== BUSCADOR SUCURSAL ====================
const busquedaSucursal = ref('')
const mostrarDropdownSucursal = ref(false)

const sucursalesFiltradas = computed(() => {
    if (!busquedaSucursal.value) return props.sucursales || []
    const termino = busquedaSucursal.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre?.toLowerCase().includes(termino) ||
        s.numero?.toString().includes(termino)
    )
})

const seleccionarSucursal = (sucursal) => {
    form.value.IdSucursal = sucursal.id
    busquedaSucursal.value = `${sucursal.nombre} ${sucursal.numero ? `(N° ${sucursal.numero})` : ''}`
    mostrarDropdownSucursal.value = false
}

const limpiarSucursal = () => {
    form.value.IdSucursal = ''
    busquedaSucursal.value = ''
}

const cerrarDropdownSucursal = () => {
    setTimeout(() => {
        mostrarDropdownSucursal.value = false
    }, 200)
}

// ==================== ACCIONES ====================
const guardar = async () => {
    errors.value = {}

    if (!form.value.IdSucursal) {
        errors.value.IdSucursal = 'Seleccione una sucursal'
        toast?.error('Validación', 'Seleccione una sucursal')
        return
    }

    if (!form.value.Nombre || form.value.Nombre.trim() === '') {
        errors.value.Nombre = 'Ingrese el nombre del tipo'
        toast?.error('Validación', 'Ingrese el nombre del tipo de contenedor')
        return
    }

    loading.value = true

    try {
        const response = await axios.post('/operacion/pedidos/clientes-mayoristas/contenedores/tipos', {
            Nombre: form.value.Nombre.trim(),
            IdSucursal: form.value.IdSucursal,
        })

        if (response.data.success) {
            toast?.success('Éxito', 'Tipo de contenedor creado correctamente')
            
            // Emitir el tipo creado para actualizar el select
            emit('created', response.data.tipo)
            
            // Cerrar modal
            cerrar()
        } else {
            toast?.error('Error', response.data.message || 'Error al crear')
        }
    } catch (error) {
        console.error('Error:', error)
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            toast?.error('Error de validación', Object.values(errors.value).join(', '))
        } else {
            toast?.error('Error', error.response?.data?.message || 'Error al crear el tipo')
        }
    } finally {
        loading.value = false
    }
}

const cerrar = () => {
    form.value = {
        Nombre: '',
        IdSucursal: '',
    }
    busquedaSucursal.value = ''
    errors.value = {}
    emit('close')
}

// ==================== WATCH ====================
watch(
    () => props.visible,
    (newVal) => {
        if (!newVal) {
            cerrar()
        }
    }
)

// ==================== KEYBOARD ====================
const handleKeydown = (event) => {
    if (event.key === 'Escape' && props.visible) {
        cerrar()
    }
    if (event.key === 'Enter' && props.visible && !loading.value) {
        guardar()
    }
}

// ==================== LIFECYCLE ====================
import { onMounted, onUnmounted } from 'vue'

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <div 
        v-if="visible"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        @click.self="cerrar"
    >
        <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-xl transition-all duration-300 ease-in-out">
            
            <!-- Header -->
            <div class="p-4 border-b bg-indigo-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-indigo-100">
                            <i class="fas fa-plus-circle text-indigo-600 text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 text-sm sm:text-base">
                                Nuevo Tipo de Contenedor
                            </h3>
                            <p class="text-[10px] text-gray-500">
                                Crea un nuevo tipo para tus contenedores
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="cerrar" 
                        class="text-gray-400 hover:text-gray-600 transition"
                        title="Cerrar"
                    >
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>
            
            <!-- Body -->
            <div class="p-4">
                <!-- Sucursal -->
                <div class="mb-3">
                    <label class="block text-gray-600 text-xs font-medium mb-1">
                        Sucursal *
                    </label>
                    <div class="relative">
                        <input 
                            type="text"
                            v-model="busquedaSucursal"
                            @focus="mostrarDropdownSucursal = true"
                            @blur="cerrarDropdownSucursal"
                            placeholder="Buscar sucursal..."
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                            :class="{'border-red-500': errors.IdSucursal}"
                            :disabled="loading"
                        />
                        <button 
                            v-if="busquedaSucursal && !loading"
                            @click="limpiarSucursal"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            type="button"
                        >
                            <i class="fas fa-times text-xs"></i>
                        </button>
                        
                        <div 
                            v-if="mostrarDropdownSucursal && sucursalesFiltradas.length > 0 && !loading"
                            class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto"
                        >
                            <div
                                v-for="s in sucursalesFiltradas"
                                :key="s.id"
                                @click="seleccionarSucursal(s)"
                                class="px-3 py-2 hover:bg-indigo-50 cursor-pointer border-b text-sm"
                                :class="{ 'bg-indigo-50': form.IdSucursal === s.id }"
                            >
                                {{ s.nombre }}
                                <span v-if="s.numero" class="text-gray-400 text-xs">
                                    (N° {{ s.numero }})
                                </span>
                            </div>
                        </div>
                    </div>
                    <p v-if="errors.IdSucursal" class="text-red-500 text-xs mt-1">
                        {{ errors.IdSucursal }}
                    </p>
                </div>

                <!-- Nombre -->
                <div class="mb-3">
                    <label class="block text-gray-600 text-xs font-medium mb-1">
                        Nombre *
                    </label>
                    <input 
                        type="text" 
                        v-model="form.Nombre" 
                        placeholder="Ej: JABA, REJILLA, TERMO, CAJÓN..." 
                        class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                        :class="{'border-red-500': errors.Nombre}"
                        :disabled="loading"
                        @keydown.enter="guardar"
                    />
                    <p v-if="errors.Nombre" class="text-red-500 text-xs mt-1">
                        {{ errors.Nombre }}
                    </p>
                    <p class="text-[10px] text-gray-400 mt-1">
                        <i class="fas fa-info-circle"></i>
                        Nombre descriptivo del tipo de contenedor (ej: JABA, REJILLA, TERMO)
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="p-4 bg-gray-50 flex justify-end gap-3 border-t">
                <button 
                    @click="cerrar"
                    :disabled="loading"
                    class="px-4 py-2 rounded-lg text-sm border border-gray-300 text-gray-700 hover:bg-gray-100 transition"
                >
                    Cancelar
                </button>
                <button 
                    @click="guardar"
                    :disabled="loading"
                    class="px-4 py-2 rounded-lg text-sm bg-indigo-600 text-white hover:bg-indigo-700 transition flex items-center gap-2 disabled:opacity-50"
                >
                    <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                    <i v-else class="fas fa-save"></i>
                    {{ loading ? 'Guardando...' : 'Guardar Tipo' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Animación de entrada */
.fixed {
    animation: fadeIn 0.2s ease-in-out;
}

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

/* Scroll personalizado */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 8px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 8px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>