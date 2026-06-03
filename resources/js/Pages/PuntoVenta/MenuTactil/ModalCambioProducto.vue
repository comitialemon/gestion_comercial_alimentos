<script setup>
import { ref, watch, computed } from 'vue'

const props = defineProps({
    visible: Boolean,
    combo: Object,
    opciones: Array,
    cantidad: {
        type: Number,
        default: 1
    },
    personalizacionesIniciales: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['update:visible', 'confirm', 'close'])

const pestañaActiva = ref(0)
const personalizaciones = ref([])

// Inicializar personalizaciones cuando se abre el modal
watch(() => props.visible, (visible) => {
    if (visible) {
        pestañaActiva.value = 0
        
        // Si ya vienen personalizaciones iniciales, usarlas
        if (props.personalizacionesIniciales && props.personalizacionesIniciales.length > 0) {
            personalizaciones.value = JSON.parse(JSON.stringify(props.personalizacionesIniciales))
        } else {
            // Inicializar personalizaciones vacías para cada combo
            personalizaciones.value = []
            for (let i = 0; i < props.cantidad; i++) {
                personalizaciones.value.push({ personalizacion: {} })
            }
        }
    }
})

// Seleccionar una opción para un combo específico
const seleccionarOpcion = (indexCombo, idProductoOriginal, idProductoSustituto) => {
    if (!personalizaciones.value[indexCombo]) return
    if (!personalizaciones.value[indexCombo].personalizacion) {
        personalizaciones.value[indexCombo].personalizacion = {}
    }
    personalizaciones.value[indexCombo].personalizacion[idProductoOriginal] = idProductoSustituto
}

// Verificar si una opción está seleccionada para un combo específico
const estaSeleccionada = (indexCombo, idProductoOriginal, idProductoSustituto) => {
    if (!personalizaciones.value[indexCombo]) return false
    if (!personalizaciones.value[indexCombo].personalizacion) return false
    return personalizaciones.value[indexCombo].personalizacion[idProductoOriginal] === idProductoSustituto
}

// Obtener el nombre de la opción seleccionada para mostrar
const getOpcionNombre = (indexCombo, idProductoOriginal) => {
    if (!personalizaciones.value[indexCombo]) return null
    const idSustituto = personalizaciones.value[indexCombo].personalizacion?.[idProductoOriginal]
    if (!idSustituto) return null
    
    const grupo = props.opciones?.find(g => g.id_producto_original === idProductoOriginal)
    const opcion = grupo?.opciones?.find(o => o.id_sustituto === idSustituto)
    return opcion?.nombre || null
}

// Siguiente pestaña
const siguiente = () => {
    if (pestañaActiva.value < personalizaciones.value.length - 1) {
        pestañaActiva.value++
    } else {
        confirmar()
    }
}

// Pestaña anterior
const anterior = () => {
    if (pestañaActiva.value > 0) {
        pestañaActiva.value--
    }
}

// Confirmar y enviar todas las personalizaciones
const confirmar = () => {
    emit('confirm', personalizaciones.value)
    emit('update:visible', false)
}

const cerrar = () => {
    emit('update:visible', false)
    emit('close')
}

// Calcular progreso
const progreso = computed(() => {
    if (personalizaciones.value.length === 0) return 0
    return ((pestañaActiva.value + 1) / personalizaciones.value.length) * 100
})

// Verificar si hay más de un combo para mostrar pestañas
const mostrarPestanas = computed(() => personalizaciones.value.length > 1)
</script>

<template>
    <Teleport to="body">
        <div v-if="visible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4" @click.self="cerrar">
            <div class="bg-white rounded-xl max-w-lg w-full max-h-[90vh] overflow-hidden shadow-xl flex flex-col">
                
                <!-- Header -->
                <div class="bg-primary-700 px-5 py-3 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center overflow-hidden">
                            <img v-if="combo?.imagen" :src="combo.imagen" class="w-full h-full object-cover">
                            <i v-else class="fas fa-box-open text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-base">Personalizar combo</h3>
                            <p class="text-white/70 text-xs">{{ combo?.nombre }}</p>
                        </div>
                    </div>
                    <button @click="cerrar" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Barra de progreso (solo si hay más de un combo) -->
                <div v-if="mostrarPestanas" class="h-1 bg-gray-200">
                    <div class="h-1 bg-primary-500 transition-all duration-300" :style="{ width: progreso + '%' }"></div>
                </div>

                <!-- Pestañas (solo si hay más de un combo) -->
                <div v-if="mostrarPestanas" class="flex border-b overflow-x-auto bg-gray-50">
                    <button 
                        v-for="(_, index) in personalizaciones" 
                        :key="index"
                        @click="pestañaActiva = index"
                        class="px-4 py-2 text-sm font-medium transition whitespace-nowrap"
                        :class="pestañaActiva === index 
                            ? 'border-b-2 border-primary-600 text-primary-600' 
                            : 'text-gray-500 hover:text-gray-700'"
                    >
                        Combo #{{ index + 1 }}
                        <span v-if="personalizaciones[index]?.personalizacion && Object.keys(personalizaciones[index].personalizacion).length > 0" 
                              class="ml-1 text-xs text-green-500">✓</span>
                    </button>
                </div>

                <!-- Contenido de la pestaña activa -->
                <div class="flex-1 overflow-y-auto p-5">
                    <div v-if="personalizaciones.length === 0" class="text-center text-gray-400 py-8">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>
                        <p>Cargando...</p>
                    </div>
                    
                    <div v-else>
                        <!-- Mostrar el número de combo si hay más de uno -->
                        <div v-if="mostrarPestanas" class="text-center mb-4">
                            <span class="inline-block px-3 py-1 bg-gray-100 rounded-full text-xs text-gray-600">
                                Combo #{{ pestañaActiva + 1 }} de {{ personalizaciones.length }}
                            </span>
                        </div>
                        
                        <!-- Opciones para el combo actual -->
                        <div v-for="grupo in opciones" :key="grupo.id_producto_original" class="mb-5 border-b pb-4 last:border-b-0">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                {{ grupo.nombre_original }}
                            </label>
                            <div class="space-y-2">
                                <div 
                                    v-for="op in grupo.opciones" 
                                    :key="op.id_sustituto"
                                    @click="seleccionarOpcion(pestañaActiva, grupo.id_producto_original, op.id_sustituto)"
                                    class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition"
                                    :class="estaSeleccionada(pestañaActiva, grupo.id_producto_original, op.id_sustituto) 
                                        ? 'bg-primary-50 border border-primary-300' 
                                        : 'hover:bg-gray-50 border border-transparent'"
                                >
                                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                                        :class="estaSeleccionada(pestañaActiva, grupo.id_producto_original, op.id_sustituto) 
                                            ? 'border-primary-600 bg-primary-600' 
                                            : 'border-gray-300'">
                                        <i v-if="estaSeleccionada(pestañaActiva, grupo.id_producto_original, op.id_sustituto)" 
                                           class="fas fa-check text-white text-[10px]"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-800">{{ op.nombre }}</span>
                                            <span class="text-[10px] text-gray-400 font-mono">{{ op.codigo }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-gray-500">Bs {{ Number(combo?.precio_real || 0).toFixed(2) }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mostrar opción actual seleccionada -->
                            <div class="mt-1 text-xs text-gray-400">
                                Opción actual: 
                                <span class="font-medium">
                                    {{ getOpcionNombre(pestañaActiva, grupo.id_producto_original) || grupo.opciones.find(o => o.es_default)?.nombre || 'Sin selección' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-5 py-3 border-t">
                    <!-- Resumen de totales -->
                    <div class="flex justify-between items-center mb-3">
                        <div>
                            <p class="text-xs text-gray-500">Precio unitario</p>
                            <p class="text-sm font-bold text-primary-700">{{ Number(combo?.precio_real || 0).toFixed(2) }} Bs</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-gray-400">Total combos</p>
                            <p class="text-sm font-bold text-gray-800">{{ (Number(combo?.precio_real || 0) * personalizaciones.length).toFixed(2) }} Bs</p>
                        </div>
                    </div>
                    
                    <!-- Botones de navegación -->
                    <div class="flex gap-2">
                        <button 
                            v-if="mostrarPestanas && pestañaActiva > 0"
                            @click="anterior" 
                            class="flex-1 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm hover:bg-gray-100 transition"
                        >
                            Anterior
                        </button>
                        <button 
                            @click="siguiente" 
                            class="flex-1 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 transition"
                        >
                            {{ mostrarPestanas && pestañaActiva < personalizaciones.length - 1 ? 'Siguiente' : 'Agregar al carrito' }}
                        </button>
                    </div>
                    
                    <button @click="cerrar" class="w-full mt-2 py-1.5 text-xs text-gray-400 hover:text-gray-600 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>