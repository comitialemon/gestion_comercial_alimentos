<!-- resources/js/Pages/Gestion/Todos/ParametrosCuenta/Index.vue -->

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    parametros: Object,
    cuentas: Array,
    secciones: Object,
})

// 🔥 Tab activo
const activeTab = ref('ventas')

// 🔥 Formulario
const form = useForm({
    ...props.parametros
})

// 🔥 Estado para búsquedas de cuentas
const busqueda = ref({})
const opciones = ref({})
const cargando = ref({})
const mostrandoOpciones = ref({})

// 🔥 Función para obtener el nombre de la cuenta por ID
const getNombreCuenta = (id) => {
    if (!id) return ''
    const cuenta = props.cuentas.find(c => c.IdCuenta === id)
    return cuenta ? `${cuenta.Cuenta} - ${cuenta.Descripcion}` : ''
}

// 🔥 Inicializar los valores en los campos de búsqueda
onMounted(() => {
    const campos = getAllCampos()
    campos.forEach(campo => {
        if (form[campo]) {
            busqueda.value[campo] = getNombreCuenta(form[campo])
        }
    })
})

// 🔥 Obtener todos los campos de todas las secciones
const getAllCampos = () => {
    const campos = []
    Object.values(props.secciones).forEach(seccion => {
        Object.keys(seccion.campos).forEach(campo => {
            campos.push(campo)
        })
    })
    return campos
}

// 🔥 Buscar cuentas para autocomplete
const buscarCuentas = async (campo, search) => {
    if (!search || search.length < 1) {
        opciones.value[campo] = []
        mostrandoOpciones.value[campo] = false
        return
    }

    cargando.value[campo] = true
    mostrandoOpciones.value[campo] = true

    try {
        const response = await axios.get('/gestion/todos/parametros-cuentas/cuentas', {
            params: { search }
        })
        opciones.value[campo] = response.data.map(c => ({
            ...c,
            label: `${c.Cuenta} - ${c.Descripcion}`
        }))
    } catch (error) {
        console.error('Error buscando cuentas:', error)
        opciones.value[campo] = []
    } finally {
        cargando.value[campo] = false
    }
}

// 🔥 Seleccionar cuenta
const seleccionarCuenta = (campo, cuenta) => {
    form[campo] = cuenta.IdCuenta
    busqueda.value[campo] = cuenta.label
    opciones.value[campo] = []
    mostrandoOpciones.value[campo] = false
}

// 🔥 Limpiar cuenta seleccionada
const limpiarCuenta = (campo) => {
    form[campo] = null
    busqueda.value[campo] = ''
    opciones.value[campo] = []
    mostrandoOpciones.value[campo] = false
}

// 🔥 Guardar
const guardar = () => {
    form.post('/gestion/todos/parametros-cuentas', {
        preserveScroll: true,
        onSuccess: () => {
            // Puedes mostrar un toast o mensaje
        }
    })
}

// 🔥 Campos por sección para mejor renderizado
const getCamposPorSeccion = (seccionKey) => {
    return props.secciones[seccionKey]?.campos || {}
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-16">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:py-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header compacto -->
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cogs text-primary-600 text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Parámetros de Cuentas</h1>
                        <p class="text-[10px] text-gray-500">Configuración de cuentas contables por tipo de operación</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white rounded-lg shadow-sm mb-4 overflow-hidden">
                    <div class="flex overflow-x-auto border-b border-gray-200 bg-gray-50/50">
                        <button
                            v-for="(seccion, key) in secciones"
                            :key="key"
                            @click="activeTab = key"
                            class="px-4 py-2 text-xs font-medium whitespace-nowrap transition-colors border-b-2"
                            :class="{
                                'text-primary-600 border-primary-600 bg-white': activeTab === key,
                                'text-gray-500 border-transparent hover:text-gray-700 hover:bg-white/50': activeTab !== key
                            }"
                        >
                            <i :class="seccion.icono" class="mr-1.5 text-[11px]"></i>
                            {{ seccion.titulo }}
                        </button>
                    </div>

                    <!-- Formulario -->
                    <form @submit.prevent="guardar" class="p-3 sm:p-4">
                        <!-- Secciones dinámicas -->
                        <div v-for="(seccion, key) in secciones" :key="key" v-show="activeTab === key">
                            <div class="space-y-1.5">
                                <!-- Cada campo en una fila con título y selector -->
                                <div 
                                    v-for="(label, campo) in getCamposPorSeccion(key)" 
                                    :key="campo"
                                    class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 px-2 py-1.5 bg-gray-50 rounded-md hover:bg-gray-100 transition-colors"
                                >
                                    <!-- Título del campo -->
                                    <label :for="campo" class="sm:w-48 text-[11px] font-medium text-gray-700 flex-shrink-0">
                                        {{ label }}
                                    </label>

                                    <!-- Selector con autocomplete -->
                                    <div class="flex-1 relative">
                                        <div class="relative">
                                            <input
                                                :id="campo"
                                                type="text"
                                                :value="busqueda[campo] || getNombreCuenta(form[campo])"
                                                @input="(e) => {
                                                    busqueda[campo] = e.target.value
                                                    buscarCuentas(campo, e.target.value)
                                                }"
                                                @focus="() => {
                                                    if (busqueda[campo] && busqueda[campo].length > 0) {
                                                        buscarCuentas(campo, busqueda[campo])
                                                    }
                                                }"
                                                @blur="() => {
                                                    setTimeout(() => { mostrandoOpciones[campo] = false }, 200)
                                                }"
                                                class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-[11px] focus:ring-2 focus:ring-primary-500 focus:border-primary-500 pr-7"
                                                :class="{ 'border-red-300': form.errors[campo] }"
                                                placeholder="Buscar cuenta..."
                                                autocomplete="off"
                                            />
                                            <!-- Botón limpiar -->
                                            <button
                                                v-if="form[campo]"
                                                @click="limpiarCuenta(campo)"
                                                type="button"
                                                class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                                            >
                                                <i class="fas fa-times text-[10px]"></i>
                                            </button>
                                            <!-- Indicador de cargando -->
                                            <div v-if="cargando[campo]" class="absolute right-1.5 top-1/2 -translate-y-1/2">
                                                <i class="fas fa-spinner fa-spin text-primary-500 text-[10px]"></i>
                                            </div>
                                        </div>

                                        <!-- Lista de sugerencias -->
                                        <div
                                            v-if="mostrandoOpciones[campo] && opciones[campo] && opciones[campo].length > 0"
                                            class="absolute z-20 mt-0.5 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-40 overflow-y-auto"
                                        >
                                            <div
                                                v-for="cuenta in opciones[campo]"
                                                :key="cuenta.IdCuenta"
                                                @mousedown.prevent="seleccionarCuenta(campo, cuenta)"
                                                class="px-2.5 py-1.5 hover:bg-primary-50 cursor-pointer text-[11px] border-b border-gray-100 last:border-b-0 transition-colors flex items-center gap-2"
                                            >
                                                <span class="font-mono text-primary-600 font-medium text-[10px]">{{ cuenta.Cuenta }}</span>
                                                <span class="text-gray-400 text-[10px]">-</span>
                                                <span class="text-gray-700 text-[11px]">{{ cuenta.Descripcion }}</span>
                                            </div>
                                        </div>

                                        <!-- Sin resultados -->
                                        <div
                                            v-if="mostrandoOpciones[campo] && busqueda[campo] && opciones[campo] && opciones[campo].length === 0 && !cargando[campo]"
                                            class="absolute z-20 mt-0.5 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-[10px]"
                                        >
                                            <i class="fas fa-search mr-1 text-[10px]"></i>
                                            No se encontraron cuentas
                                        </div>

                                        <!-- Mostrar cuenta seleccionada -->
                                        <div v-if="form[campo] && !busqueda[campo]" class="mt-0.5 text-[10px] text-green-600">
                                            <i class="fas fa-check-circle mr-0.5 text-[9px]"></i>
                                            {{ getNombreCuenta(form[campo]) }}
                                        </div>

                                        <!-- Error -->
                                        <p v-if="form.errors[campo]" class="mt-0.5 text-[10px] text-red-600">
                                            {{ form.errors[campo] }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-gray-200">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-[11px] font-medium hover:bg-primary-700 transition flex items-center gap-1.5 shadow-sm"
                            >
                                <i v-if="form.processing" class="fas fa-spinner fa-spin text-[11px]"></i>
                                <i v-else class="fas fa-save text-[11px]"></i>
                                {{ form.processing ? 'Guardando...' : 'Guardar Parámetros' }}
                            </button>
                        </div>

                        <!-- Mensaje de éxito -->
                        <div v-if="$page.props.flash?.success" class="mt-3 p-2 bg-green-50 border border-green-200 rounded-md text-[11px] text-green-700">
                            <i class="fas fa-check-circle mr-1.5 text-[11px]"></i>
                            {{ $page.props.flash.success }}
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Scrollbar personalizado para sugerencias */
.max-h-40::-webkit-scrollbar {
    width: 4px;
}
.max-h-40::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.max-h-40::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}
.max-h-40::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>