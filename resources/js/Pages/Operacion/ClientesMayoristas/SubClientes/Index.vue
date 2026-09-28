<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    subclientes: { type: Array, default: () => [] }
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const busqueda = ref('')
const resultados = ref([])
const buscando = ref(false)
const guardando = ref(false)
const mostrarModalEditar = ref(false)
const subclienteEditar = ref(null)
const aliasEditar = ref('')
const guardandoEdicion = ref(false)
const eliminando = ref(null)

// ✅ NUEVO: modal de crear identificador
const mostrarModalCrear = ref(false)
const nuevoNombre = ref('')
const nuevoCI_NIT = ref('')
const guardandoNuevo = ref(false)
const errorCrear = ref('')

let timeoutBusqueda = null

// ==================== COMPUTED ====================
const subclientesFiltrados = computed(() => {
    if (!busqueda.value || busqueda.value.length < 2) {
        return props.subclientes
    }
    const termino = busqueda.value.toLowerCase()
    return props.subclientes.filter(s =>
        s.Nombre?.toLowerCase().includes(termino) ||
        s.CI_NIT?.toLowerCase().includes(termino) ||
        s.Alias?.toLowerCase().includes(termino)
    )
})

const totalSubclientes = computed(() => props.subclientes.length)
const hayResultados = computed(() => resultados.value.length > 0)

const puedeAgregar = computed(() => {
    return resultados.value.some(r => r.seleccionado) && !guardando.value
})

const cantidadSeleccionados = computed(() => {
    return resultados.value.filter(r => r.seleccionado).length
})

// ✅ ¿Hay búsqueda activa sin resultados? → mostrar botón "Crear nuevo"
const mostrarBotonCrear = computed(() => {
    return busqueda.value.length >= 2 && !buscando.value && resultados.value.length === 0
})

// ✅ ¿Puede guardar el nuevo identificador?
const puedeGuardarNuevo = computed(() => {
    return nuevoNombre.value.trim().length >= 2 && !guardandoNuevo.value
})

// ==================== BÚSQUEDA ====================
const buscarIdentificadores = () => {
    if (timeoutBusqueda) clearTimeout(timeoutBusqueda)

    if (busqueda.value.length < 2) {
        resultados.value = []
        return
    }

    timeoutBusqueda = setTimeout(async () => {
        buscando.value = true
        try {
            const response = await axios.get(
                '/operacion/pedidos/clientes-mayoristas/subclientes/buscar-identificadores',
                { params: { q: busqueda.value } }
            )
            if (response.data.success) {
                resultados.value = response.data.identificadores.map(id => ({
                    ...id,
                    seleccionado: false
                }))
            }
        } catch (error) {
            console.error('Error buscando:', error)
            toast?.error('Error', 'No se pudo realizar la búsqueda')
        } finally {
            buscando.value = false
        }
    }, 400)
}

const toggleSeleccion = (item) => {
    item.seleccionado = !item.seleccionado
}

const seleccionarTodos = () => {
    const todosSeleccionados = resultados.value.every(r => r.seleccionado)
    resultados.value.forEach(r => r.seleccionado = !todosSeleccionados)
}

// ==================== AGREGAR ====================
const agregarSeleccionados = async () => {
    const seleccionados = resultados.value.filter(r => r.seleccionado)

    if (seleccionados.length === 0) {
        toast?.warning('Sin selección', 'Selecciona al menos un identificador')
        return
    }

    guardando.value = true
    let exitosos = 0
    let errores = 0

    try {
        for (const item of seleccionados) {
            try {
                const response = await axios.post(
                    '/operacion/pedidos/clientes-mayoristas/subclientes',
                    {
                        IdIdentificador: item.IdIdentificador,
                        Alias: null
                    }
                )
                if (response.data.success) {
                    exitosos++
                } else {
                    errores++
                }
            } catch (error) {
                errores++
                console.error('Error agregando:', item.Nombre, error)
            }
        }

        if (exitosos > 0) {
            toast?.success('Éxito', `${exitosos} subcliente(s) agregado(s)`)
        }
        if (errores > 0) {
            toast?.warning('Atención', `${errores} no se pudieron agregar`)
        }

        busqueda.value = ''
        resultados.value = []
        router.reload({ only: ['subclientes'] })

    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', 'Error al agregar subclientes')
    } finally {
        guardando.value = false
    }
}

// ==================== CREAR NUEVO IDENTIFICADOR ====================
const abrirModalCrear = () => {
    nuevoNombre.value = busqueda.value || '' // Precargar con lo que buscó
    nuevoCI_NIT.value = ''
    errorCrear.value = ''
    mostrarModalCrear.value = true
}

const cerrarModalCrear = () => {
    mostrarModalCrear.value = false
    nuevoNombre.value = ''
    nuevoCI_NIT.value = ''
    errorCrear.value = ''
}

const guardarNuevoIdentificador = async () => {
    if (!puedeGuardarNuevo.value) return

    errorCrear.value = ''
    guardandoNuevo.value = true

    try {
        const response = await axios.post(
            '/operacion/pedidos/clientes-mayoristas/subclientes/crear-identificador',
            {
                Nombre: nuevoNombre.value.trim(),
                CI_NIT: nuevoCI_NIT.value.trim() || null,
            }
        )

        if (response.data.success) {
            toast?.success('Éxito', 'Identificador creado y agregado como subcliente')
            cerrarModalCrear()
            busqueda.value = ''
            resultados.value = []
            router.reload({ only: ['subclientes'] })
        } else {
            errorCrear.value = response.data.message || 'Error al crear'
            toast?.error('Error', errorCrear.value)
        }
    } catch (error) {
        console.error('Error:', error)
        const msg = error.response?.data?.message || 'Error al crear el identificador'
        errorCrear.value = msg
        toast?.error('Error', msg)
    } finally {
        guardandoNuevo.value = false
    }
}

// ==================== EDITAR ALIAS ====================
const abrirEditar = (sub) => {
    subclienteEditar.value = sub
    aliasEditar.value = sub.Alias || ''
    mostrarModalEditar.value = true
}

const cerrarEditar = () => {
    mostrarModalEditar.value = false
    subclienteEditar.value = null
    aliasEditar.value = ''
}

const guardarEdicion = async () => {
    if (!subclienteEditar.value) return

    guardandoEdicion.value = true
    try {
        const response = await axios.put(
            `/operacion/pedidos/clientes-mayoristas/subclientes/${subclienteEditar.value.IdSubClienteOperador}`,
            { Alias: aliasEditar.value || null }
        )

        if (response.data.success) {
            toast?.success('Éxito', 'Alias actualizado correctamente')
            cerrarEditar()
            router.reload({ only: ['subclientes'] })
        } else {
            toast?.error('Error', response.data.message || 'Error al actualizar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al actualizar')
    } finally {
        guardandoEdicion.value = false
    }
}

// ==================== ELIMINAR ====================
const eliminarSubcliente = async (sub) => {
    if (!confirm(`¿Desactivar a "${sub.Nombre}" de tu lista de subclientes?`)) return

    eliminando.value = sub.IdSubClienteOperador
    try {
        const response = await axios.delete(
            `/operacion/pedidos/clientes-mayoristas/subclientes/${sub.IdSubClienteOperador}`
        )

        if (response.data.success) {
            toast?.success('Éxito', 'Subcliente desactivado correctamente')
            router.reload({ only: ['subclientes'] })
        } else {
            toast?.error('Error', response.data.message || 'Error al desactivar')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al desactivar')
    } finally {
        eliminando.value = null
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    if (timeoutBusqueda) clearTimeout(timeoutBusqueda)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-5xl mx-auto">

                <!-- HEADER -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-users text-primary-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Mis Subclientes</h1>
                            <p class="text-xs text-gray-500">Administra tu lista personal de subclientes</p>
                        </div>
                    </div>
                    <div class="bg-white px-3 py-1.5 rounded-xl border border-gray-200 flex items-center gap-2 shadow-sm">
                        <i class="fas fa-user-tag text-primary-500 text-xs"></i>
                        <span class="text-xs font-medium text-gray-700">
                            {{ totalSubclientes }} registrado(s)
                        </span>
                    </div>
                </div>

                <!-- ALERTA INFO -->
                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-xl p-3 mb-4">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5 text-[10px]"></i>
                        <div class="text-xs text-blue-800">
                            <p class="font-semibold">¿Cómo funciona?</p>
                            <p class="mt-0.5 leading-relaxed text-[11px]">
                                Agrega los identificadores que uses como subclientes. Si no existe el que necesitas,
                                puedes <strong>crear uno nuevo</strong> desde esta pantalla.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- AGREGAR SUBCLIENTE -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-4 border border-gray-200">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-primary-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-plus text-primary-600 text-xs"></i>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-gray-800">Agregar Subclientes</h2>
                                <p class="text-[10px] text-gray-500">Busca por nombre o CI/NIT</p>
                            </div>
                        </div>
                        <!-- ✅ BOTÓN CREAR NUEVO -->
                        <button
                            @click="abrirModalCrear"
                            class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-md text-[11px] font-medium transition flex items-center gap-1.5 shadow-sm"
                        >
                            <i class="fas fa-user-plus text-[10px]"></i>
                            Crear Identificador
                        </button>
                    </div>

                    <!-- Buscador -->
                    <div class="relative">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                        <input
                            type="text"
                            v-model="busqueda"
                            @input="buscarIdentificadores"
                            placeholder="Escribe al menos 2 caracteres para buscar..."
                            class="w-full border border-gray-300 rounded-md pl-7 pr-8 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                        />
                        <button
                            v-if="busqueda"
                            @click="busqueda = ''; resultados = []"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </div>

                    <!-- Loading -->
                    <div v-if="buscando" class="flex items-center gap-2 mt-3 text-xs text-gray-500">
                        <i class="fas fa-spinner fa-spin text-primary-500 text-[10px]"></i>
                        Buscando identificadores...
                    </div>

                    <!-- Resultados -->
                    <div v-else-if="hayResultados" class="mt-3 border border-gray-200 rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-3 py-1.5 flex justify-between items-center border-b border-gray-200">
                            <span class="text-[10px] font-medium text-gray-600">
                                {{ resultados.length }} resultado(s)
                            </span>
                            <button
                                @click="seleccionarTodos"
                                class="text-[10px] text-primary-600 hover:text-primary-800 font-medium flex items-center gap-1"
                            >
                                <i class="fas fa-check-square text-[9px]"></i>
                                Seleccionar todos
                            </button>
                        </div>

                        <div class="max-h-72 overflow-y-auto divide-y divide-gray-100">
                            <div
                                v-for="item in resultados"
                                :key="item.IdIdentificador"
                                @click="toggleSeleccion(item)"
                                class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 cursor-pointer transition"
                                :class="item.seleccionado ? 'bg-primary-50' : ''"
                            >
                                <div class="w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0 transition"
                                    :class="item.seleccionado ? 'bg-primary-600 border-primary-600' : 'border-gray-300 bg-white'">
                                    <i v-if="item.seleccionado" class="fas fa-check text-white text-[8px]"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-medium text-gray-800 truncate">{{ item.Nombre }}</div>
                                    <div class="text-[9px] text-gray-500 mt-0.5">
                                        <i class="fas fa-id-card mr-1"></i>
                                        CI/NIT: {{ item.CI_NIT || 'Sin NIT' }}
                                    </div>
                                </div>
                                <i class="fas fa-user-plus text-gray-300 text-xs"></i>
                            </div>
                        </div>

                        <div class="bg-gray-50 px-3 py-2 flex justify-between items-center border-t border-gray-200">
                            <span class="text-[10px] text-gray-600">
                                <strong class="text-primary-600">{{ cantidadSeleccionados }}</strong> seleccionado(s)
                            </span>
                            <button
                                @click="agregarSeleccionados"
                                :disabled="!puedeAgregar"
                                class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-plus text-[10px]"></i>
                                {{ guardando ? 'Guardando...' : `Agregar (${cantidadSeleccionados})` }}
                            </button>
                        </div>
                    </div>

                    <!-- ✅ SIN RESULTADOS → sugerir crear -->
                    <div v-else-if="mostrarBotonCrear" class="mt-3 text-center py-6 text-gray-400">
                        <i class="fas fa-search text-2xl mb-1 block"></i>
                        <p class="text-xs">No se encontró "{{ busqueda }}"</p>
                        <p class="text-[10px] mt-0.5 mb-3">¿Quieres crear un identificador nuevo?</p>
                        <button
                            @click="abrirModalCrear"
                            class="px-4 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5 mx-auto shadow-sm"
                        >
                            <i class="fas fa-user-plus text-[10px]"></i>
                            Crear "{{ busqueda }}"
                        </button>
                    </div>

                    <!-- Ayuda -->
                    <div v-else-if="busqueda.length > 0 && busqueda.length < 2" class="mt-3 text-center py-4 text-gray-400">
                        <p class="text-[11px]">Escribe al menos 2 caracteres...</p>
                    </div>
                </div>

                <!-- LISTA DE SUBCLIENTES -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-list text-primary-500 text-[10px]"></i>
                            <h2 class="text-xs font-semibold text-gray-700">Mi Lista de Subclientes</h2>
                            <span class="text-[9px] bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded-full font-medium">
                                {{ totalSubclientes }}
                            </span>
                        </div>
                    </div>

                    <div v-if="totalSubclientes === 0" class="text-center py-10 text-gray-400">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-user-plus text-gray-300 text-2xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-500">Aún no tienes subclientes</p>
                        <p class="text-[10px] mt-1">Busca o crea uno nuevo para empezar</p>
                    </div>

                    <div v-else class="divide-y divide-gray-100">
                        <div
                            v-for="sub in subclientesFiltrados"
                            :key="sub.IdSubClienteOperador"
                            class="flex items-center justify-between px-3 py-2 hover:bg-gray-50 transition"
                        >
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-500 to-indigo-600 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                    {{ (sub.Nombre || '?').charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-xs font-semibold text-gray-800 truncate">{{ sub.Nombre }}</span>
                                        <span v-if="sub.Alias" class="text-[8px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-medium">
                                            <i class="fas fa-tag mr-0.5 text-[7px]"></i>
                                            {{ sub.Alias }}
                                        </span>
                                        <span v-if="sub.EsPropio" class="text-[8px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-bold">
                                            <i class="fas fa-user-check mr-0.5 text-[7px]"></i>
                                            Mi cuenta
                                        </span>
                                    </div>
                                    <div class="text-[9px] text-gray-500 mt-0.5 flex items-center gap-2">
                                        <span>
                                            <i class="fas fa-id-card mr-0.5"></i>
                                            {{ sub.CI_NIT || 'Sin NIT' }}
                                        </span>
                                        <span v-if="sub.FechaInserta" class="hidden sm:inline">
                                            <i class="fas fa-calendar mr-0.5"></i>
                                            {{ new Date(sub.FechaInserta).toLocaleDateString('es-BO') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-1 flex-shrink-0 ml-2">
                                <button
                                    @click="abrirEditar(sub)"
                                    class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-primary-100 text-gray-500 hover:text-primary-700 flex items-center justify-center transition"
                                    title="Editar alias"
                                >
                                    <i class="fas fa-pencil-alt text-[10px]"></i>
                                </button>
                                <button
                                    v-if="!sub.EsPropio"
                                    @click="eliminarSubcliente(sub)"
                                    :disabled="eliminando === sub.IdSubClienteOperador"
                                    class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-red-100 text-gray-500 hover:text-red-600 flex items-center justify-center transition disabled:opacity-50"
                                    title="Desactivar"
                                >
                                    <i v-if="eliminando === sub.IdSubClienteOperador" class="fas fa-spinner fa-spin text-[10px]"></i>
                                    <i v-else class="fas fa-trash text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-center text-[9px] text-gray-400">
                    <i class="fas fa-lock mr-1"></i>
                    Esta lista es personal y solo tú puedes verla y usarla en tus pedidos
                </div>
            </div>
        </div>

        <!-- ==================== MODAL CREAR IDENTIFICADOR ==================== -->
        <div
            v-if="mostrarModalCrear"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="cerrarModalCrear"
        >
            <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-2xl animate-fade-in-up">
                <!-- Header -->
                <div class="bg-emerald-600 p-3 flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-user-plus text-white text-xs"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-white text-sm">Crear Nuevo Identificador</h3>
                        <p class="text-[10px] text-white/80">Se agregará automáticamente a tu lista</p>
                    </div>
                    <button @click="cerrarModalCrear" class="text-white/80 hover:text-white transition p-1 rounded">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-4 space-y-3">
                    <!-- Nombre -->
                    <div>
                        <label class="text-[10px] font-medium text-gray-600 block mb-0.5">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            v-model="nuevoNombre"
                            placeholder="Ej: Juan Pérez"
                            maxlength="150"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                            @keyup.enter="guardarNuevoIdentificador"
                        />
                    </div>

                    <!-- CI/NIT -->
                    <div>
                        <label class="text-[10px] font-medium text-gray-600 block mb-0.5">
                            CI / NIT <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input
                            type="text"
                            v-model="nuevoCI_NIT"
                            placeholder="Ej: 7654321"
                            maxlength="50"
                            class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                            @keyup.enter="guardarNuevoIdentificador"
                        />
                        <p class="text-[9px] text-gray-400 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Si ya existe un identificador con el mismo CI/NIT, se reutilizará.
                        </p>
                    </div>

                    <!-- Error -->
                    <div v-if="errorCrear" class="p-2 bg-red-50 border-l-4 border-red-400 rounded text-xs text-red-700">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        {{ errorCrear }}
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-4 py-2.5 flex justify-end gap-1.5 border-t border-gray-200">
                    <button
                        @click="cerrarModalCrear"
                        :disabled="guardandoNuevo"
                        class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-xs font-medium transition"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="guardarNuevoIdentificador"
                        :disabled="!puedeGuardarNuevo"
                        class="px-4 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i v-if="guardandoNuevo" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-save text-[10px]"></i>
                        {{ guardandoNuevo ? 'Guardando...' : 'Crear y Agregar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL EDITAR ALIAS ==================== -->
        <div
            v-if="mostrarModalEditar"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="cerrarEditar"
        >
            <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-2xl animate-fade-in-up">
                <div class="bg-primary-600 p-3 flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-tag text-white text-xs"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-white text-sm">Editar Alias</h3>
                        <p class="text-[10px] text-white/80 truncate">{{ subclienteEditar?.Nombre }}</p>
                    </div>
                    <button @click="cerrarEditar" class="text-white/80 hover:text-white transition p-1 rounded">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div class="p-4">
                    <label class="text-[10px] font-medium text-gray-600 block mb-0.5">
                        Alias personalizado
                    </label>
                    <input
                        type="text"
                        v-model="aliasEditar"
                        placeholder="Ej: Cliente Juan, Distribuidora Norte..."
                        maxlength="100"
                        class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                    />
                    <p class="text-[9px] text-gray-400 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Este alias es solo para ti. El nombre real sigue siendo
                        <strong>{{ subclienteEditar?.Nombre }}</strong>
                    </p>
                </div>

                <div class="bg-gray-50 px-4 py-2.5 flex justify-end gap-1.5 border-t border-gray-200">
                    <button
                        @click="cerrarEditar"
                        :disabled="guardandoEdicion"
                        class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-xs font-medium transition"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="guardarEdicion"
                        :disabled="guardandoEdicion"
                        class="px-4 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-md text-xs font-medium transition flex items-center gap-1.5 disabled:opacity-50"
                    >
                        <i v-if="guardandoEdicion" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-save text-[10px]"></i>
                        {{ guardandoEdicion ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.animate-fade-in-up {
    animation: fadeInUp 0.2s ease-out;
}

.max-h-72::-webkit-scrollbar {
    width: 4px;
}
.max-h-72::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 8px;
}
.max-h-72::-webkit-scrollbar-thumb {
    background: #d1d1d1;
    border-radius: 8px;
}
.max-h-72::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>