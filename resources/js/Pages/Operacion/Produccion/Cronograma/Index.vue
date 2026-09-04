<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

// Estado para modal de confirmación personalizado
const modalConfirmacion = ref({
    visible: false,
    titulo: '',
    mensaje: '',
    accion: null,
    id: null,
    index: null
})

const props = defineProps({
    cronogramas: {
        type: Array,
        default: () => []
    },
    productos: {
        type: Array,
        default: () => []
    },
    dias: {
        type: Array,
        default: () => ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo']
    }
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
const filas = ref([])
const guardando = ref(false)
const eliminando = ref(false)

// Estado para selectores
const busqueda = ref({})
const mostrandoLista = ref({})
const productosFiltrados = ref({})
const dropdownPositions = ref({})

// Día actual
const diaActual = computed(() => {
    const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado']
    const hoy = new Date().getDay()
    return diasSemana[hoy]
})

const dropdownRefs = ref({})
const inputRefs = ref({})

// ==================== FUNCIONES ====================
const initBusquedaPorDiaFila = (dia, filaIndex) => {
    if (!busqueda.value[dia]) busqueda.value[dia] = {}
    if (busqueda.value[dia][filaIndex] === undefined) busqueda.value[dia][filaIndex] = ''
    
    if (!mostrandoLista.value[dia]) mostrandoLista.value[dia] = {}
    mostrandoLista.value[dia][filaIndex] = false
    
    if (!productosFiltrados.value[dia]) productosFiltrados.value[dia] = {}
    productosFiltrados.value[dia][filaIndex] = [...props.productos]
}

const inicializarTodasLasBusquedas = () => {
    props.dias.forEach(dia => {
        filas.value.forEach((_, idx) => {
            initBusquedaPorDiaFila(dia, idx)
            const productoId = filas.value[idx][dia]
            if (productoId) {
                const producto = props.productos.find(p => p.id === productoId)
                if (producto) busqueda.value[dia][idx] = producto.texto
            }
        })
    })
}

const filtrarProductos = (dia, filaIndex, termino) => {
    if (!termino || termino.trim() === '') {
        productosFiltrados.value[dia][filaIndex] = [...props.productos]
        return
    }
    
    const terminoLower = termino.toLowerCase().trim()
    const filtrados = props.productos.filter(p => 
        (p.codigo || '').toLowerCase().includes(terminoLower) ||
        (p.descripcion || '').toLowerCase().includes(terminoLower) ||
        (p.texto || '').toLowerCase().includes(terminoLower)
    )
    productosFiltrados.value[dia][filaIndex] = filtrados
}

const onBuscar = (dia, filaIndex, event) => {
    const termino = event.target.value
    busqueda.value[dia][filaIndex] = termino
    filtrarProductos(dia, filaIndex, termino)
    const tieneResultados = productosFiltrados.value[dia][filaIndex]?.length > 0
    mostrandoLista.value[dia][filaIndex] = termino.length >= 1 && tieneResultados
    
    if (mostrandoLista.value[dia][filaIndex]) {
        actualizarPosicionDropdown(dia, filaIndex)
    }
}

const onFocus = (dia, filaIndex) => {
    const termino = busqueda.value[dia][filaIndex] || ''
    if (termino.length >= 1) {
        filtrarProductos(dia, filaIndex, termino)
        const tieneResultados = productosFiltrados.value[dia][filaIndex]?.length > 0
        mostrandoLista.value[dia][filaIndex] = tieneResultados
    } else {
        productosFiltrados.value[dia][filaIndex] = [...props.productos]
        mostrandoLista.value[dia][filaIndex] = true
    }
    
    if (mostrandoLista.value[dia][filaIndex]) {
        actualizarPosicionDropdown(dia, filaIndex)
    }
}

const actualizarPosicionDropdown = (dia, filaIndex) => {
    const key = `${dia}-${filaIndex}`
    const input = inputRefs.value[key]
    if (input) {
        const rect = input.getBoundingClientRect()
        dropdownPositions.value[key] = {
            top: rect.bottom + window.scrollY + 4,
            left: rect.left + window.scrollX,
            width: Math.max(rect.width, 250)
        }
    }
}

const actualizarTodosLosDropdowns = () => {
    props.dias.forEach(dia => {
        if (mostrandoLista.value[dia]) {
            Object.keys(mostrandoLista.value[dia]).forEach(filaIndex => {
                if (mostrandoLista.value[dia][filaIndex]) {
                    actualizarPosicionDropdown(dia, parseInt(filaIndex))
                }
            })
        }
    })
}

const seleccionarProducto = (dia, filaIndex, producto) => {
    filas.value[filaIndex][dia] = producto.id
    busqueda.value[dia][filaIndex] = producto.texto
    mostrandoLista.value[dia][filaIndex] = false
    productosFiltrados.value[dia][filaIndex] = [...props.productos]
}

const getProductoTexto = (dia, filaIndex) => {
    const id = filas.value[filaIndex][dia]
    if (!id) return '—'
    const producto = props.productos.find(p => p.id === id)
    return producto ? producto.texto : '—'
}

const limpiarSeleccion = (dia, filaIndex) => {
    filas.value[filaIndex][dia] = null
    busqueda.value[dia][filaIndex] = ''
    productosFiltrados.value[dia][filaIndex] = [...props.productos]
    mostrandoLista.value[dia][filaIndex] = false
}

const isFilaVacia = (fila) => {
    return !fila.Lunes && !fila.Martes && !fila.Miercoles && !fila.Jueves && !fila.Viernes && !fila.Sabado && !fila.Domingo
}

const handleClickOutside = (event) => {
    const dropdownKeys = Object.keys(dropdownRefs.value)
    let clickedOutside = true
    
    for (const key of dropdownKeys) {
        const el = dropdownRefs.value[key]
        if (el && el.contains(event.target)) {
            clickedOutside = false
            break
        }
    }
    
    for (const key of Object.keys(inputRefs.value)) {
        const el = inputRefs.value[key]
        if (el && el.contains(event.target)) {
            clickedOutside = false
            break
        }
    }
    
    if (clickedOutside) {
        props.dias.forEach(dia => {
            if (mostrandoLista.value[dia]) {
                Object.keys(mostrandoLista.value[dia]).forEach(filaIndex => {
                    mostrandoLista.value[dia][filaIndex] = false
                })
            }
        })
    }
}

const scrollAlFinal = () => {
    nextTick(() => {
        const tablaContainer = document.querySelector('.overflow-x-auto')
        if (tablaContainer) {
            tablaContainer.scrollLeft = 0
        }
        
        let contenedorScroll = null
        
        const selectores = [
            '.layout-content',
            '.app-content', 
            '.page-wrapper',
            'main',
            '#app > div:first-child',
            '.min-h-screen'
        ]
        
        for (const selector of selectores) {
            const el = document.querySelector(selector)
            if (el && (el.scrollHeight > el.clientHeight || el.scrollTop !== undefined)) {
                contenedorScroll = el
                break
            }
        }
        
        if (!contenedorScroll) {
            const todosDivs = document.querySelectorAll('div')
            for (const div of todosDivs) {
                const estilo = window.getComputedStyle(div)
                if ((estilo.overflowY === 'auto' || estilo.overflowY === 'scroll') && 
                    div.scrollHeight > div.clientHeight) {
                    contenedorScroll = div
                    break
                }
            }
        }
        
        if (contenedorScroll) {
            contenedorScroll.scrollTo({
                top: contenedorScroll.scrollHeight,
                behavior: 'smooth'
            })
        }
        
        window.scrollTo({
            top: document.body.scrollHeight,
            behavior: 'smooth'
        })
        
        setTimeout(() => {
            const ultimaFila = document.querySelector('tbody tr:last-child')
            if (ultimaFila) {
                ultimaFila.scrollIntoView({ behavior: 'smooth', block: 'center' })
            }
        }, 200)
    })
}

const agregarFila = () => {
    const nuevaFila = {
        id: null,
        Lunes: null,
        Martes: null,
        Miercoles: null,
        Jueves: null,
        Viernes: null,
        Sabado: null,
        Domingo: null,
    }
    const nuevaFilaIndex = filas.value.length
    filas.value.push(nuevaFila)
    
    props.dias.forEach(dia => {
        initBusquedaPorDiaFila(dia, nuevaFilaIndex)
    })
    
    filaEditando.value[nuevaFilaIndex] = true
    
    scrollAlFinal()
    
    setTimeout(() => {
        const primerDia = props.dias[0]
        const key = `${primerDia}-${nuevaFilaIndex}`
        const input = inputRefs.value[key]
        if (input) {
            input.focus()
        }
    }, 500)
}

const verificarYEliminarFilaVacia = (index) => {
    const fila = filas.value[index]
    if (isFilaVacia(fila) && !fila.id) {
        filas.value.splice(index, 1)
        inicializarTodasLasBusquedas()
    }
}

const mostrarConfirmacion = (titulo, mensaje, accion, id = null, index = null) => {
    modalConfirmacion.value = {
        visible: true,
        titulo: titulo,
        mensaje: mensaje,
        accion: accion,
        id: id,
        index: index
    }
}

const ejecutarConfirmacion = async () => {
    const { accion, id, index } = modalConfirmacion.value
    
    modalConfirmacion.value.visible = false
    
    if (accion === 'eliminarFila') {
        await eliminarFilaConfirmado(index, id)
    }
}

const eliminarFilaConfirmado = async (index, id) => {
    const fila = filas.value[index]
    
    if (!fila.id || fila.id === null) {
        filas.value.splice(index, 1)
        inicializarTodasLasBusquedas()
        if (toast) {
            toast.info('Fila eliminada', 'La fila fue removida')
        }
        return
    }
    
    eliminando.value = true
    try {
        const response = await axios.delete(`/operacion/produccion/cronograma/${fila.id}`)
        
        if (response.data.success) {
            filas.value.splice(index, 1)
            inicializarTodasLasBusquedas()
            
            if (toast) {
                toast.success('Fila eliminada', 'La fila fue eliminada correctamente')
            }
        } else {
            throw new Error(response.data.message || 'Error al eliminar')
        }
    } catch (error) {
        console.error('Error al eliminar:', error)
        
        if (error.response?.status === 404) {
            filas.value.splice(index, 1)
            inicializarTodasLasBusquedas()
            if (toast) {
                toast.info('Fila eliminada', 'El registro ya no existía en la base de datos')
            }
        } else {
            if (toast) {
                toast.error('Error', error.response?.data?.message || 'No se pudo eliminar la fila')
            }
        }
    } finally {
        eliminando.value = false
    }
}

const confirmarEliminar = (index) => {
    const fila = filas.value[index]
    if (!fila.id && isFilaVacia(fila)) {
        eliminarFilaConfirmado(index, null)
    } else {
        mostrarConfirmacion(
            'Eliminar fila',
            '¿Estás seguro de que deseas eliminar esta fila del cronograma?',
            'eliminarFila',
            fila.id,
            index
        )
    }
}

const filaEditando = ref({})

const iniciarEdicionFila = (index) => {
    filaEditando.value[index] = true
    props.dias.forEach(dia => {
        const productoId = filas.value[index][dia]
        if (productoId) {
            const producto = props.productos.find(p => p.id === productoId)
            if (producto && busqueda.value[dia]) {
                busqueda.value[dia][index] = producto.texto
            }
        }
    })
}

const cancelarEdicionFila = (index) => {
    if (props.cronogramas && props.cronogramas[index]) {
        const original = props.cronogramas[index]
        filas.value[index] = {
            id: original.IdCronograma,
            Lunes: original.Lunes || null,
            Martes: original.Martes || null,
            Miercoles: original.Miercoles || null,
            Jueves: original.Jueves || null,
            Viernes: original.Viernes || null,
            Sabado: original.Sabado || null,
            Domingo: original.Domingo || null,
        }
    }
    filaEditando.value[index] = false
    verificarYEliminarFilaVacia(index)
    
    props.dias.forEach(dia => {
        const productoId = filas.value[index]?.[dia]
        if (productoId) {
            const producto = props.productos.find(p => p.id === productoId)
            if (producto && busqueda.value[dia]) {
                busqueda.value[dia][index] = producto.texto
            }
        } else if (busqueda.value[dia]) {
            busqueda.value[dia][index] = ''
        }
        if (productosFiltrados.value[dia]) {
            productosFiltrados.value[dia][index] = [...props.productos]
        }
        if (mostrandoLista.value[dia]) {
            mostrandoLista.value[dia][index] = false
        }
    })
}

const guardarFila = async (index) => {
    guardando.value = true
    
    try {
        const fila = filas.value[index]
        const response = await axios.post('/operacion/produccion/cronograma', {
            cronogramas: [fila]
        })
        
        filaEditando.value[index] = false
        
        if (toast) {
            toast.success('Fila guardada', 'Los cambios fueron guardados correctamente')
        }
        
        setTimeout(() => {
            router.reload()
        }, 1000)
        
    } catch (error) {
        console.error('Error:', error)
        if (toast) {
            toast.error('Error', error.response?.data?.message || 'Error al guardar')
        }
    } finally {
        guardando.value = false
    }
}

const inicializarFilas = () => {
    if (props.cronogramas && props.cronogramas.length > 0) {
        filas.value = props.cronogramas.map(c => ({
            id: c.IdCronograma,
            Lunes: c.Lunes || null,
            Martes: c.Martes || null,
            Miercoles: c.Miercoles || null,
            Jueves: c.Jueves || null,
            Viernes: c.Viernes || null,
            Sabado: c.Sabado || null,
            Domingo: c.Domingo || null,
        }))
    }
    inicializarTodasLasBusquedas()
}

const diasAbreviados = {
    Lunes: 'LUN',
    Martes: 'MAR',
    Miercoles: 'MIÉ',
    Jueves: 'JUE',
    Viernes: 'VIE',
    Sabado: 'SÁB',
    Domingo: 'DOM'
}

const getDiaClass = (dia) => {
    if (diaActual.value === dia) {
        return 'bg-purple-100 text-purple-800'
    }
    return 'bg-gray-50 text-gray-500'
}

const setInputRef = (el, dia, filaIndex) => {
    const key = `${dia}-${filaIndex}`
    if (el) {
        inputRefs.value[key] = el
    }
}

const setDropdownRef = (el, dia, filaIndex) => {
    const key = `${dia}-${filaIndex}`
    if (el) {
        dropdownRefs.value[key] = el
    }
}

// ==================== LIFECYCLE ====================
onMounted(() => {
    inicializarFilas()
    document.addEventListener('click', handleClickOutside)
    handleResize()
    window.addEventListener('resize', handleResize)
    
    window.addEventListener('scroll', actualizarTodosLosDropdowns)
    window.addEventListener('resize', actualizarTodosLosDropdowns)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    window.removeEventListener('resize', handleResize)
    window.removeEventListener('scroll', actualizarTodosLosDropdowns)
    window.removeEventListener('resize', actualizarTodosLosDropdowns)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-purple-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-purple-600 text-base"></i>
                        </div>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Cronograma de Producción</h1>
                            <p class="text-xs text-gray-500">
                                Hoy es <strong class="text-purple-600">{{ diaActual }}</strong> - Configura qué productos se producen cada día
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="agregarFila"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-md text-xs font-medium flex items-center gap-1.5 transition"
                    >
                        <i class="fas fa-plus text-[10px]"></i> Agregar Fila
                    </button>
                </div>

                <!-- ==================== TABLA ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto" style="overflow-y: visible; -webkit-overflow-scrolling: touch;">
                        <table class="min-w-[700px] sm:min-w-[800px] md:min-w-[900px] lg:min-w-[1000px] xl:min-w-[1100px] divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th 
                                        v-for="dia in dias" 
                                        :key="dia"
                                        class="px-1 sm:px-2 py-2 text-center text-[10px] font-medium uppercase"
                                        :class="getDiaClass(dia)"
                                        style="width: 120px; min-width: 100px; max-width: 160px;"
                                    >
                                        <span class="block sm:hidden">{{ diasAbreviados[dia] }}</span>
                                        <span class="hidden sm:block md:hidden">{{ dia.substring(0, 3) }}</span>
                                        <span class="hidden md:block">{{ dia }}</span>
                                    </th>
                                    <th class="px-1 sm:px-2 py-2 text-center text-[10px] font-medium text-gray-500 uppercase w-20 sm:w-28 md:w-32">Acciones</th>
                                </tr>
                            </thead>
                            
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(fila, idx) in filas" :key="idx" class="hover:bg-gray-50">
                                    <td 
                                        v-for="dia in dias" 
                                        :key="dia"
                                        class="px-1 sm:px-2 py-1.5 align-top"
                                        style="width: 120px; min-width: 100px; max-width: 160px;"
                                    >
                                        <div 
                                            v-if="filaEditando[idx]" 
                                            class="relative"
                                            style="position: relative;"
                                        >
                                            <div class="relative">
                                                <i class="fas fa-search absolute left-1.5 top-1.5 text-gray-400 text-[8px]"></i>
                                                <input 
                                                    :id="`producto-input-${dia}-${idx}`"
                                                    :class="`producto-input-${dia}-${idx}`"
                                                    type="text"
                                                    :value="busqueda[dia]?.[idx] || ''"
                                                    @input="(e) => onBuscar(dia, idx, e)"
                                                    @focus="() => onFocus(dia, idx)"
                                                    :ref="(el) => setInputRef(el, dia, idx)"
                                                    placeholder="Buscar..."
                                                    class="w-full border border-gray-200 rounded-md pl-5 pr-5 py-1 text-xs focus:border-purple-400 focus:ring-1 focus:ring-purple-200 outline-none"
                                                />
                                                <button 
                                                    v-if="fila[dia]"
                                                    @click="limpiarSeleccion(dia, idx)"
                                                    class="absolute right-1 top-1 text-gray-400 hover:text-gray-600"
                                                    type="button"
                                                >
                                                    <i class="fas fa-times text-[8px]"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div v-else class="w-full border border-gray-100 bg-gray-50 rounded-md px-2 py-1 text-xs text-gray-700 truncate">
                                            {{ getProductoTexto(dia, idx) }}
                                        </div>
                                    </td>
                                    
                                    <td class="px-1 sm:px-2 py-2 text-center align-top">
                                        <div class="flex justify-center gap-0.5 sm:gap-1 md:gap-2">
                                            <button 
                                                v-if="!filaEditando[idx]"
                                                @click="iniciarEdicionFila(idx)"
                                                class="text-amber-600 hover:text-amber-700 transition p-1" title="Editar fila"
                                            >
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button 
                                                v-else
                                                @click="cancelarEdicionFila(idx)"
                                                class="text-gray-500 hover:text-gray-700 transition p-1" title="Cancelar edición"
                                            >
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                            
                                            <button 
                                                v-if="filaEditando[idx]"
                                                @click="guardarFila(idx)"
                                                :disabled="guardando"
                                                class="text-emerald-600 hover:text-emerald-700 transition disabled:opacity-50 p-1" title="Guardar cambios"
                                            >
                                                <i v-if="guardando" class="fas fa-spinner fa-spin text-xs"></i>
                                                <i v-else class="fas fa-save text-xs"></i>
                                            </button>
                                            
                                            <button 
                                                @click="confirmarEliminar(idx)"
                                                :disabled="eliminando"
                                                class="text-red-500 hover:text-red-700 transition disabled:opacity-50 p-1" title="Eliminar fila"
                                            >
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr v-if="filas.length === 0">
                                    <td :colspan="dias.length + 1" class="px-4 py-8 text-center text-gray-400">
                                        <i class="fas fa-calendar-day text-2xl mb-2 block"></i>
                                        <span class="text-sm">No hay filas configuradas. Haz clic en "Agregar Fila" para comenzar.</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== INSTRUCCIONES ==================== -->
                <div class="mt-4 p-3 bg-blue-50 rounded-xl border border-blue-100 text-xs text-blue-700 flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5 text-blue-500"></i>
                    <div>
                        <span class="font-medium">Instrucciones:</span>
                        <ul class="list-disc list-inside mt-1 space-y-0.5 text-[11px]">
                            <li>La columna del <span class="font-medium text-purple-600">día actual</span> se resalta en morado</li>
                            <li>Al agregar una fila, se abre automáticamente en modo edición</li>
                            <li>Si cancelas una fila nueva sin guardar, se elimina automáticamente</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== TELEPORT PARA DROPDOWNS ==================== -->
        <Teleport to="body">
            <div 
                v-for="(diaGroup, dia) in mostrandoLista" 
                :key="dia"
            >
                <div 
                    v-for="(mostrando, filaIndex) in diaGroup"
                    :key="`${dia}-${filaIndex}`"
                >
                    <div 
                        v-if="mostrando && productosFiltrados[dia]?.[filaIndex]?.length > 0"
                        ref="dropdownRef"
                        class="fixed z-[9999] bg-white border border-gray-200 rounded-md shadow-lg"
                        :style="{
                            top: (dropdownPositions[`${dia}-${filaIndex}`]?.top || 0) + 'px',
                            left: (dropdownPositions[`${dia}-${filaIndex}`]?.left || 0) + 'px',
                            minWidth: (dropdownPositions[`${dia}-${filaIndex}`]?.width || 250) + 'px',
                            maxWidth: (dropdownPositions[`${dia}-${filaIndex}`]?.width || 320) + 'px',
                            maxHeight: isMobile ? '200px' : '300px',
                            overflowY: 'auto'
                        }"
                    >
                        <div 
                            v-for="producto in productosFiltrados[dia][filaIndex]" 
                            :key="producto.id"
                            @click="seleccionarProducto(dia, filaIndex, producto)"
                            class="px-2 py-1.5 hover:bg-purple-50 cursor-pointer border-b last:border-b-0"
                        >
                            <div class="flex flex-col">
                                <span class="font-mono text-[8px] text-gray-500">{{ producto.codigo }}</span>
                                <span class="text-gray-800 break-words text-xs">{{ producto.descripcion }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div 
                        v-if="mostrando && busqueda[dia]?.[filaIndex] && busqueda[dia][filaIndex].length >= 1 && (!productosFiltrados[dia]?.[filaIndex] || productosFiltrados[dia][filaIndex].length === 0)"
                        class="fixed z-[9999] bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-xs"
                        :style="{
                            top: (dropdownPositions[`${dia}-${filaIndex}`]?.top || 0) + 'px',
                            left: (dropdownPositions[`${dia}-${filaIndex}`]?.left || 0) + 'px',
                            minWidth: (dropdownPositions[`${dia}-${filaIndex}`]?.width || 220) + 'px',
                            maxWidth: (dropdownPositions[`${dia}-${filaIndex}`]?.width || 280) + 'px'
                        }"
                    >
                        No se encontraron productos
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ==================== MODAL DE CONFIRMACIÓN ==================== -->
        <div v-if="modalConfirmacion.visible" class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-xl shadow-xl w-[90%] max-w-md p-5 transform transition-all">
                <div class="text-center">
                    <div class="mx-auto w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-3">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-base font-semibold text-gray-800 mb-2">{{ modalConfirmacion.titulo }}</h3>
                    <p class="text-sm text-gray-600 mb-5">{{ modalConfirmacion.mensaje }}</p>
                    <div class="flex gap-3 justify-center">
                        <button 
                            @click="modalConfirmacion.visible = false"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300 transition"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="ejecutarConfirmacion"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition"
                        >
                            Eliminar
                        </button>
                    </div>
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

@media (max-width: 640px) {
    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
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
</style>