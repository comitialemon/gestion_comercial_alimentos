<!-- resources/js/Pages/PuntoVenta/MenuTactil/ModalCambioProducto.vue -->

<script setup>
import { ref, watch, computed } from 'vue'

const props = defineProps({
    visible: Boolean,
    combo: Object,
    opciones: Array,
    cantidad: { type: Number, default: 1 },
    personalizacionesIniciales: { type: Array, default: () => [] },
    tipoProducto: { type: String, default: 'normal' }
})

const emit = defineEmits(['update:visible', 'confirm', 'close', 'update:cantidad'])

const pestañaActiva = ref(0)
const personalizaciones = ref([])

// Utilidades
const getTotalUnidades = (id) => {
    if (props.combo?.composicion) {
        const p = props.combo.composicion.find(p => p.id_producto === id)
        if (p?.porcion) return p.porcion
    }
    const g = props.opciones?.find(g => g.id_producto_original === id)
    return g?.cantidad_total || props.combo?.cantidad || 1
}

const getTotalReemplazado = (idx, id) => {
    const c = personalizaciones.value[idx]
    if (!c?.sustitutos) return 0
    return c.sustitutos.filter(s => s.id_producto_original === id)
        .reduce((sum, s) => sum + (s.cantidad || 0), 0)
}

const getUnidadesOriginales = (idx, id) => getTotalUnidades(id) - getTotalReemplazado(idx, id)

const getCantidadSeleccionada = (idx, idOriginal, idSustituto) => {
    const c = personalizaciones.value[idx]
    if (!c?.sustitutos) return 0
    const found = c.sustitutos.find(s => 
        s.id_producto_original === idOriginal && 
        s.id_producto_sustituto === idSustituto
    )
    return found?.cantidad || 0
}

// 🔥 OBTENER MÁXIMO PERMITIDO PARA UN SUSTITUTO
const getMaximoPermitido = (idOriginal, idSustituto) => {
    const grupo = props.opciones?.find(g => g.id_producto_original === idOriginal)
    const opcion = grupo?.opciones?.find(o => o.id_sustituto === idSustituto)
    return opcion?.cantidad_maxima || getTotalUnidades(idOriginal)
}

// 🔥 FUNCIÓN PRINCIPAL: Actualizar cantidad desde input CON VALIDACIÓN ESTRICTA
const actualizarCantidadSustituto = (idx, idOriginal, idSustituto, nuevoValor) => {
    // 1. Limpiar el valor (solo números)
    let valorLimpio = nuevoValor.replace(/[^0-9]/g, '')
    let cantidad = parseInt(valorLimpio) || 0
    
    // 2. Obtener límites
    const maximo = getMaximoPermitido(idOriginal, idSustituto)
    const totalUnidades = getTotalUnidades(idOriginal)
    const yaSeleccionado = getCantidadSeleccionada(idx, idOriginal, idSustituto)
    const disponible = getUnidadesOriginales(idx, idOriginal) + yaSeleccionado
    
    // 3. 🔥 VALIDACIÓN ESTRICTA: No puede exceder el disponible
    if (cantidad > disponible) {
        cantidad = disponible
    }
    
    // 4. No puede ser negativo
    if (cantidad < 0) {
        cantidad = 0
    }
    
    // 5. No puede exceder el máximo por opción
    if (cantidad > maximo) {
        cantidad = maximo
    }
    
    // 6. Actualizar la personalización
    if (!personalizaciones.value[idx]) {
        personalizaciones.value[idx] = { sustitutos: [] }
    }
    const combo = personalizaciones.value[idx]
    if (!combo.sustitutos) combo.sustitutos = []
    
    const existente = combo.sustitutos.find(s => 
        s.id_producto_original === idOriginal && 
        s.id_producto_sustituto === idSustituto
    )
    
    if (cantidad === 0) {
        // Eliminar si es 0
        const index = combo.sustitutos.findIndex(s => 
            s.id_producto_original === idOriginal && 
            s.id_producto_sustituto === idSustituto
        )
        if (index !== -1) {
            combo.sustitutos.splice(index, 1)
        }
    } else if (existente) {
        existente.cantidad = cantidad
    } else {
        combo.sustitutos.push({ 
            id_producto_original: idOriginal, 
            id_producto_sustituto: idSustituto, 
            cantidad: cantidad 
        })
    }
}

// 🔥 FUNCIÓN PARA INPUT DE CANTIDAD DE COMBOS CON VALIDACIÓN
const actualizarCantidadTotal = (nuevoValor) => {
    let cantidad = parseInt(nuevoValor) || 1
    
    // Validar límites
    if (cantidad < 1) cantidad = 1
    if (cantidad > 99) cantidad = 99
    
    const diff = cantidad - personalizaciones.value.length
    
    if (diff > 0) {
        // Agregar más combos
        for (let i = 0; i < diff; i++) {
            personalizaciones.value.push({ sustitutos: [] })
        }
    } else if (diff < 0) {
        // Quitar combos
        personalizaciones.value.splice(cantidad)
        if (pestañaActiva.value >= personalizaciones.value.length) {
            pestañaActiva.value = personalizaciones.value.length - 1
        }
    }
    
    emit('update:cantidad', cantidad)
}

// 🔥 MANEJADOR PARA INPUT (convierte el evento)
const handleInputCantidadTotal = (e) => {
    const val = e.target.value
    // Si está vacío, no hacer nada
    if (val === '') return
    actualizarCantidadTotal(val)
}

// 🔥 MANEJADOR PARA BLUR (cuando pierde el foco, corregir)
const handleBlurCantidadTotal = (e) => {
    let val = parseInt(e.target.value) || 1
    if (val < 1) val = 1
    if (val > 99) val = 99
    e.target.value = val
    actualizarCantidadTotal(val)
}

// Botones + y - para cantidad de combos
const incrementarCombos = () => {
    const nueva = personalizaciones.value.length + 1
    if (nueva <= 99) actualizarCantidadTotal(nueva)
}

const decrementarCombos = () => {
    const nueva = personalizaciones.value.length - 1
    if (nueva >= 1) actualizarCantidadTotal(nueva)
}

// Botones + y - para sustitutos (con validación)
const seleccionarSustituto = (idx, idOriginal, idSustituto) => {
    if (!personalizaciones.value[idx]) {
        personalizaciones.value[idx] = { sustitutos: [] }
    }
    const combo = personalizaciones.value[idx]
    if (!combo.sustitutos) combo.sustitutos = []
    
    const existente = combo.sustitutos.find(s => 
        s.id_producto_original === idOriginal && 
        s.id_producto_sustituto === idSustituto
    )
    
    const disponible = getUnidadesOriginales(idx, idOriginal)
    const maximo = getMaximoPermitido(idOriginal, idSustituto)
    
    if (existente) {
        // Solo incrementar si no excede el máximo y hay disponibilidad
        if (existente.cantidad < maximo && disponible > 0) {
            existente.cantidad++
        }
    } else {
        if (disponible > 0) {
            combo.sustitutos.push({ 
                id_producto_original: idOriginal, 
                id_producto_sustituto: idSustituto, 
                cantidad: 1 
            })
        }
    }
}

const removerSustituto = (idx, idOriginal, idSustituto) => {
    const combo = personalizaciones.value[idx]
    if (!combo?.sustitutos) return
    const index = combo.sustitutos.findIndex(s => 
        s.id_producto_original === idOriginal && 
        s.id_producto_sustituto === idSustituto
    )
    if (index !== -1) {
        if (combo.sustitutos[index].cantidad > 1) {
            combo.sustitutos[index].cantidad--
        } else {
            combo.sustitutos.splice(index, 1)
        }
    }
}

const estaCompleto = (idx) => {
    if (!props.opciones) return true
    for (const grupo of props.opciones) {
        if (!grupo.opciones?.length) continue
        const reemplazado = getTotalReemplazado(idx, grupo.id_producto_original)
        // 🔥 No puede exceder el total de unidades
        if (reemplazado > getTotalUnidades(grupo.id_producto_original)) return false
    }
    return true
}

const getResumen = (idx) => {
    const c = personalizaciones.value[idx]
    if (!c?.sustitutos) return { originales: [], sustitutos: [] }
    
    const originales = []
    const sustitutos = []
    
    props.opciones?.forEach(grupo => {
        const total = getTotalUnidades(grupo.id_producto_original)
        const reemplazado = getTotalReemplazado(idx, grupo.id_producto_original)
        const quedan = total - reemplazado
        if (quedan > 0) {
            originales.push({ nombre: grupo.nombre_original, cantidad: quedan })
        }
    })
    
    c.sustitutos.forEach(sust => {
        const grupo = props.opciones?.find(g => g.id_producto_original === sust.id_producto_original)
        const opcion = grupo?.opciones?.find(o => o.id_sustituto === sust.id_producto_sustituto)
        if (opcion) {
            sustitutos.push({
                original: grupo.nombre_original,
                sustituto: opcion.nombre,
                cantidad: sust.cantidad
            })
        }
    })
    
    return { originales, sustitutos }
}

// Inicializar
watch(() => props.visible, (visible) => {
    if (visible) {
        pestañaActiva.value = 0
        if (props.personalizacionesIniciales?.length) {
            personalizaciones.value = JSON.parse(JSON.stringify(props.personalizacionesIniciales))
        } else {
            const cantidadInicial = props.cantidad || 1
            personalizaciones.value = Array.from({ length: cantidadInicial }, () => ({ sustitutos: [] }))
        }
    }
})

const siguiente = () => {
    if (pestañaActiva.value < personalizaciones.value.length - 1) {
        pestañaActiva.value++
    } else {
        confirmar()
    }
}

const confirmar = () => {
    for (let i = 0; i < personalizaciones.value.length; i++) {
        if (!estaCompleto(i)) {
            alert(`Combo #${i + 1}: Excede el total disponible`)
            pestañaActiva.value = i
            return
        }
    }
    emit('confirm', personalizaciones.value)
    emit('update:visible', false)
}

const cerrar = () => {
    emit('update:visible', false)
    emit('close')
}

const progreso = computed(() => {
    if (!personalizaciones.value.length) return 0
    return ((pestañaActiva.value + 1) / personalizaciones.value.length) * 100
})

const mostrarPestanas = computed(() => personalizaciones.value.length > 1)

const etiquetaPersonalizacion = computed(() => {
    if (props.tipoProducto === 'pack') return 'Personalizar pack'
    if (props.tipoProducto === 'combo') return 'Personalizar combo'
    if (props.tipoProducto === 'con_opciones') return 'Personalizar producto'
    return 'Personalizar'
})
</script>

<template>
    <Teleport to="body">
        <div v-if="visible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-3" @click.self="cerrar">
            <div class="bg-white rounded-xl max-w-lg w-full max-h-[90vh] overflow-hidden shadow-xl flex flex-col">
                
                <!-- Header -->
                <div class="bg-primary-700 px-4 py-2.5 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-white rounded-lg flex items-center justify-center overflow-hidden">
                            <img v-if="combo?.imagen" :src="combo.imagen" class="w-full h-full object-cover">
                            <i v-else class="fas fa-box-open text-primary-600 text-xs"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm">{{ etiquetaPersonalizacion }}</h3>
                            <p class="text-white/70 text-[10px]">{{ combo?.nombre }}</p>
                        </div>
                    </div>
                    <button @click="cerrar" class="text-white/80 hover:text-white transition text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Barra de progreso -->
                <div v-if="mostrarPestanas" class="h-0.5 bg-gray-200">
                    <div class="h-0.5 bg-primary-500 transition-all duration-300" :style="{ width: progreso + '%' }"></div>
                </div>

                <!-- Pestañas con INPUT numérico -->
                <div v-if="mostrarPestanas" class="flex items-center border-b bg-gray-50 p-2 gap-2 overflow-x-auto">
                    <button 
                        v-for="(_, index) in personalizaciones" 
                        :key="index"
                        @click="pestañaActiva = index"
                        class="px-3 py-1 text-xs font-medium rounded-lg transition whitespace-nowrap"
                        :class="pestañaActiva === index 
                            ? 'bg-primary-100 text-primary-700 border border-primary-300' 
                            : 'bg-white text-gray-500 hover:bg-gray-100 border border-gray-200'"
                    >
                        #{{ index + 1 }}
                        <span v-if="estaCompleto(index)" class="ml-0.5 text-green-500">✓</span>
                    </button>
                    
                    <!-- 🔥 INPUT PARA CANTIDAD TOTAL DE COMBOS CON VALIDACIÓN -->
                    <div class="flex items-center gap-1 ml-auto bg-white rounded-lg border border-gray-200 px-1.5 py-0.5">
                        <button 
                            @click="decrementarCombos"
                            class="w-5 h-5 rounded bg-red-100 hover:bg-red-200 text-red-600 text-xs flex items-center justify-center"
                            :disabled="personalizaciones.length <= 1"
                        >−</button>
                        
                        <input 
                            type="number"
                            :value="personalizaciones.length"
                            @input="handleInputCantidadTotal"
                            @blur="handleBlurCantidadTotal"
                            min="1"
                            max="99"
                            class="w-8 text-center text-xs font-bold border-0 focus:ring-0 p-0"
                            style="appearance: textfield; -moz-appearance: textfield;"
                        />
                        
                        <button 
                            @click="incrementarCombos"
                            class="w-5 h-5 rounded bg-primary-100 hover:bg-primary-200 text-primary-600 text-xs flex items-center justify-center"
                            :disabled="personalizaciones.length >= 99"
                        >+</button>
                    </div>
                </div>

                <!-- Contenido -->
                <div class="flex-1 overflow-y-auto p-3 space-y-3">
                    <div v-if="!personalizaciones.length" class="text-center text-gray-400 py-6 text-sm">
                        <i class="fas fa-spinner fa-spin text-xl mb-1 block"></i>
                        Cargando...
                    </div>
                    
                    <div v-else>
                        <!-- Número de combo -->
                        <div v-if="mostrarPestanas" class="text-center text-[10px] text-gray-500 -mt-1">
                            Combo {{ pestañaActiva + 1 }} de {{ personalizaciones.length }}
                        </div>
                        
                        <!-- Opciones -->
                        <div v-for="grupo in opciones" :key="grupo.id_producto_original" class="border-b pb-3 last:border-b-0">
                            <!-- Header compacto -->
                            <div class="flex justify-between items-center mb-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-semibold text-gray-700">{{ grupo.nombre_original }}</span>
                                    <span class="text-[10px] text-gray-400">({{ getTotalUnidades(grupo.id_producto_original) }} unid)</span>
                                </div>
                                <span class="text-[10px] font-medium" 
                                      :class="getUnidadesOriginales(pestañaActiva, grupo.id_producto_original) > 0 ? 'text-green-600' : 'text-blue-600'">
                                    {{ getUnidadesOriginales(pestañaActiva, grupo.id_producto_original) }} orig
                                </span>
                            </div>
                            
                            <!-- Estadísticas compactas -->
                            <div class="flex gap-3 text-[10px] text-gray-500 mb-1.5">
                                <span>🔹 Total: <strong>{{ getTotalUnidades(grupo.id_producto_original) }}</strong></span>
                                <span>✅ Orig: <strong class="text-green-600">{{ getUnidadesOriginales(pestañaActiva, grupo.id_producto_original) }}</strong></span>
                                <span>🔄 Reemp: <strong class="text-blue-600">{{ getTotalReemplazado(pestañaActiva, grupo.id_producto_original) }}</strong></span>
                            </div>
                            
                            <!-- 🔥 OPCIONES CON INPUT NUMÉRICO Y VALIDACIÓN -->
                            <div class="space-y-1.5">
                                <div v-for="op in grupo.opciones" :key="op.id_sustituto"
                                     class="flex items-center gap-2 p-2 rounded-lg border text-sm transition"
                                     :class="getCantidadSeleccionada(pestañaActiva, grupo.id_producto_original, op.id_sustituto) > 0 
                                         ? 'bg-primary-50 border-primary-300' 
                                         : 'bg-white border-gray-200 hover:bg-gray-50'">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-medium text-gray-800 truncate">{{ op.nombre }}</div>
                                        <div class="text-[10px] text-gray-400">
                                            máx {{ op.cantidad_maxima || getTotalUnidades(grupo.id_producto_original) }}
                                        </div>
                                    </div>
                                    
                                    <!-- 🔥 CONTROLES CON INPUT Y VALIDACIÓN -->
                                    <div class="flex items-center gap-1">
                                        <button 
                                            @click="removerSustituto(pestañaActiva, grupo.id_producto_original, op.id_sustituto)"
                                            class="w-6 h-6 rounded bg-red-100 hover:bg-red-200 text-red-600 text-sm disabled:opacity-30 flex items-center justify-center"
                                            :disabled="getCantidadSeleccionada(pestañaActiva, grupo.id_producto_original, op.id_sustituto) === 0"
                                        >−</button>
                                        
                                        <input 
                                            type="number"
                                            :value="getCantidadSeleccionada(pestañaActiva, grupo.id_producto_original, op.id_sustituto)"
                                            @input="actualizarCantidadSustituto(
                                                pestañaActiva, 
                                                grupo.id_producto_original, 
                                                op.id_sustituto, 
                                                $event.target.value
                                            )"
                                            min="0"
                                            :max="getUnidadesOriginales(pestañaActiva, grupo.id_producto_original) + getCantidadSeleccionada(pestañaActiva, grupo.id_producto_original, op.id_sustituto)"
                                            class="w-10 text-center font-bold text-sm border rounded-lg p-0.5 focus:border-primary-400 focus:outline-none"
                                            style="appearance: textfield; -moz-appearance: textfield;"
                                        />
                                        
                                        <button 
                                            @click="seleccionarSustituto(pestañaActiva, grupo.id_producto_original, op.id_sustituto)"
                                            class="w-6 h-6 rounded bg-primary-100 hover:bg-primary-200 text-primary-600 text-sm disabled:opacity-30 flex items-center justify-center"
                                            :disabled="getUnidadesOriginales(pestañaActiva, grupo.id_producto_original) <= 0"
                                        >+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resumen compacto -->
                        <div v-if="getResumen(pestañaActiva).sustitutos.length || getResumen(pestañaActiva).originales.length" 
                             class="bg-gray-50 rounded-lg p-2 text-[10px] border">
                            <div class="flex flex-wrap gap-2">
                                <span v-for="item in getResumen(pestañaActiva).originales" :key="item.nombre" 
                                      class="text-green-600">✅ {{ item.nombre }}: {{ item.cantidad }}x</span>
                                <span v-for="item in getResumen(pestañaActiva).sustitutos" :key="item.sustituto" 
                                      class="text-blue-600">🔄 {{ item.original }}→{{ item.sustituto }}: {{ item.cantidad }}x</span>
                            </div>
                        </div>
                        
                        <!-- Estado -->
                        <div class="text-center text-xs font-medium py-1.5 rounded-lg"
                             :class="estaCompleto(pestañaActiva) ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700'">
                            {{ estaCompleto(pestañaActiva) ? '✅ Listo' : 'ℹ️ Configura tu combo' }}
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-4 py-2.5 border-t">
                    <div class="flex justify-between items-center mb-2">
                        <div>
                            <p class="text-[10px] text-gray-500">Precio unitario</p>
                            <p class="text-sm font-bold text-primary-700">{{ Number(combo?.precio_real || 0).toFixed(2) }} Bs</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-gray-400">Total</p>
                            <p class="text-sm font-bold text-gray-800">{{ (Number(combo?.precio_real || 0) * personalizaciones.length).toFixed(2) }} Bs</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <button v-if="mostrarPestanas && pestañaActiva > 0"
                                @click="pestañaActiva--" 
                                class="flex-1 py-1.5 rounded-lg border border-gray-300 text-gray-700 text-xs hover:bg-gray-100">←</button>
                        <button @click="siguiente" 
                                class="flex-1 py-1.5 rounded-lg bg-primary-600 text-white text-xs font-medium hover:bg-primary-700 disabled:opacity-50"
                                :disabled="!estaCompleto(pestañaActiva)">
                            {{ mostrarPestanas && pestañaActiva < personalizaciones.length - 1 ? 'Siguiente →' : 'Agregar al carrito' }}
                        </button>
                    </div>
                    <button @click="cerrar" class="w-full mt-1 text-[10px] text-gray-400 hover:text-gray-600">Cancelar</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
input[type="number"] {
    -webkit-appearance: textfield;
    -moz-appearance: textfield;
    appearance: textfield;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>