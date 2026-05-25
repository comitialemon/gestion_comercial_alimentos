<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted, inject } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    ajuste: Object,
    detalles: Array,
    fechas: Array,
    tiposOperacion: Array,
    almacenes: Array,
    personas: Array,
    productos: Array,
})

// Formulario de cabecera
const form = ref({
    IdAjustesPrincipal: props.ajuste?.IdAjustesPrincipal || null,
    IdFecha: props.ajuste?.IdFecha || '',
    ConceptoOperacion: props.ajuste?.ConceptoOperacion || '',
    IdTipoOperacion: props.ajuste?.IdTipoOperacion || '',
    IdAlmacen: props.ajuste?.IdAlmacen || '',
    IdRealizadoPor: props.ajuste?.IdRealizadoPor || '',
    IdAutorizadoPor: props.ajuste?.IdAutorizadoPor || '',
    Explicacion: props.ajuste?.Explicacion || '',
})

// Estado
const detallesGrid = ref([])
const guardandoCabecera = ref(false)
const guardandoDetalle = ref(false)
const contabilizando = ref(false)
const errors = ref({})
const mostrarConfirmacion = ref(false)
const cabeceraGuardada = ref(false)

// Datos del nuevo producto temporal
const nuevoProducto = ref({
    IdProducto: '',
    Codigo: '',
    Descripcion: '',
    Unidades: 0,
    Bolivianos: 0,
})

// Buscador de productos
const busquedaProducto = ref('')
const productosFiltrados = computed(() => {
    if (!busquedaProducto.value) return props.productos
    const termino = busquedaProducto.value.toLowerCase()
    return props.productos.filter(p => 
        p.Codigo?.toLowerCase().includes(termino) || 
        p.Descripcion?.toLowerCase().includes(termino)
    )
})

// Tipos de operación filtrados por concepto
const tiposFiltrados = computed(() => {
    if (!form.value.ConceptoOperacion) return []
    return props.tiposOperacion.filter(t => t.Concepto === form.value.ConceptoOperacion)
})

// Buscadores de personas
const busquedaRealizadoPor = ref('')
const busquedaAutorizadoPor = ref('')
const personasFiltradasRealizado = computed(() => {
    if (!busquedaRealizadoPor.value) return props.personas
    const termino = busquedaRealizadoPor.value.toLowerCase()
    return props.personas.filter(p => 
        p.ci?.toString().includes(termino) || 
        p.nombre?.toLowerCase().includes(termino)
    )
})
const personasFiltradasAutorizado = computed(() => {
    if (!busquedaAutorizadoPor.value) return props.personas
    const termino = busquedaAutorizadoPor.value.toLowerCase()
    return props.personas.filter(p => 
        p.ci?.toString().includes(termino) || 
        p.nombre?.toLowerCase().includes(termino)
    )
})

// Precio calculado
const precioCalculado = computed(() => {
    if (nuevoProducto.value.Unidades > 0 && nuevoProducto.value.Bolivianos > 0) {
        return (nuevoProducto.value.Bolivianos / nuevoProducto.value.Unidades).toFixed(2)
    }
    return '0.00'
})

// Seleccionar producto
const seleccionarProducto = (producto) => {
    nuevoProducto.value.IdProducto = producto.id
    nuevoProducto.value.Codigo = producto.Codigo
    nuevoProducto.value.Descripcion = producto.Descripcion
    busquedaProducto.value = `${producto.Codigo} - ${producto.Descripcion}`
}

const limpiarSeleccionProducto = () => {
    nuevoProducto.value = {
        IdProducto: '',
        Codigo: '',
        Descripcion: '',
        Unidades: 0,
        Bolivianos: 0,
    }
    busquedaProducto.value = ''
}

// Validar campos obligatorios de cabecera
const validarCamposCabecera = () => {
    const nuevosErrors = {}
    if (!form.value.IdFecha) nuevosErrors.IdFecha = 'Seleccione fecha'
    if (!form.value.ConceptoOperacion) nuevosErrors.ConceptoOperacion = 'Seleccione concepto'
    if (!form.value.IdTipoOperacion) nuevosErrors.IdTipoOperacion = 'Seleccione tipo operación'
    if (!form.value.IdAlmacen) nuevosErrors.IdAlmacen = 'Seleccione almacén'
    if (!form.value.IdRealizadoPor) nuevosErrors.IdRealizadoPor = 'Seleccione quien realizó'
    if (!form.value.IdAutorizadoPor) nuevosErrors.IdAutorizadoPor = 'Seleccione quien autorizó'
    errors.value = nuevosErrors
    return Object.keys(nuevosErrors).length === 0
}

// Guardar cabecera
const guardarCabecera = async () => {
    if (!validarCamposCabecera()) {
        toast?.warning('Datos incompletos', 'Complete todos los campos obligatorios')
        return
    }
    
    guardandoCabecera.value = true
    try {
        let idAjuste = form.value.IdAjustesPrincipal
        
        if (!idAjuste) {
            const crearResponse = await axios.post('/gestion/inventario/ajustes/crear')
            if (crearResponse.data.success) {
                idAjuste = crearResponse.data.ajuste.IdAjustesPrincipal
                form.value.IdAjustesPrincipal = idAjuste
            } else {
                throw new Error('No se pudo crear el ajuste')
            }
        }
        
        await axios.put(`/gestion/inventario/ajustes/cabecera/${idAjuste}`, {
            IdFecha: form.value.IdFecha,
            ConceptoOperacion: form.value.ConceptoOperacion,
            IdTipoOperacion: form.value.IdTipoOperacion,
            IdAlmacen: form.value.IdAlmacen,
            IdRealizadoPor: form.value.IdRealizadoPor,
            IdAutorizadoPor: form.value.IdAutorizadoPor,
            Explicacion: form.value.Explicacion || '',
        })
        
        cabeceraGuardada.value = true
        toast?.success('Cabecera guardada', 'Datos guardados correctamente. Ahora puede agregar productos.')
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'Error al guardar la cabecera')
    } finally {
        guardandoCabecera.value = false
    }
}

// Cargar detalles existentes
const cargarDetalles = () => {
    if (props.detalles && props.detalles.length) {
        detallesGrid.value = props.detalles.map(d => ({
            IdAjustesPropiamente: d.IdAjustesPropiamente,
            IdProducto: d.IdProducto,
            Codigo: d.producto?.Codigo || '',
            Descripcion: d.producto?.Descripcion || '',
            Unidades: d.Unidades,
            Bolivianos: d.Bolivianos,
        }))
    }
}

// Agregar producto
const agregarProducto = async () => {
    if (!nuevoProducto.value.IdProducto) {
        toast?.warning('Producto requerido', 'Seleccione un producto')
        return
    }
    
    if (nuevoProducto.value.Unidades <= 0) {
        toast?.warning('Unidades inválidas', 'Deben ser > 0')
        return
    }
    if (nuevoProducto.value.Bolivianos <= 0) {
        toast?.warning('Monto inválido', 'Debe ser > 0')
        return
    }
    
    guardandoDetalle.value = true
    try {
        const response = await axios.post('/gestion/inventario/ajustes/detalle', {
            IdAjustesPrincipal: form.value.IdAjustesPrincipal,
            IdProducto: nuevoProducto.value.IdProducto,
            Unidades: nuevoProducto.value.Unidades,
            Bolivianos: nuevoProducto.value.Bolivianos,
        })
        
        if (response.data.success) {
            detallesGrid.value.push({
                IdAjustesPropiamente: response.data.detalle.IdAjustesPropiamente,
                IdProducto: response.data.detalle.IdProducto,
                Codigo: nuevoProducto.value.Codigo,
                Descripcion: nuevoProducto.value.Descripcion,
                Unidades: nuevoProducto.value.Unidades,
                Bolivianos: nuevoProducto.value.Bolivianos,
            })
            
            limpiarSeleccionProducto()
            toast?.success('Producto agregado', `${nuevoProducto.value.Descripcion} - ${nuevoProducto.value.Unidades} unidades`)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', error.response?.data?.message || 'No se pudo agregar')
    } finally {
        guardandoDetalle.value = false
    }
}

// Eliminar producto
const eliminarProducto = async (index) => {
    const item = detallesGrid.value[index]
    if (!item) return
    
    try {
        const response = await axios.delete(`/gestion/inventario/ajustes/detalle/${item.IdAjustesPropiamente}`)
        if (response.data.success) {
            detallesGrid.value.splice(index, 1)
            toast?.success('Producto eliminado', item.Descripcion)
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('Error', 'No se pudo eliminar')
    }
}

// Contabilizar
const contabilizar = () => {
    if (!validarCamposCabecera()) {
        toast?.warning('Datos incompletos', 'Complete la cabecera')
        return
    }
    if (detallesGrid.value.length === 0) {
        toast?.warning('Sin productos', 'Agregue al menos un producto')
        return
    }
    mostrarConfirmacion.value = true
}

const ejecutarContabilizar = async () => {
    contabilizando.value = true
    mostrarConfirmacion.value = false
    try {
        const response = await axios.post(`/gestion/inventario/ajustes/contabilizar/${form.value.IdAjustesPrincipal}`)
        if (response.status === 200) {
            toast?.success('Ajuste contabilizado', 'Redirigiendo...')
            window.open(`/gestion/inventario/ajustes/${form.value.IdAjustesPrincipal}/pdf`, '_blank')
            setTimeout(() => router.get('/gestion/inventario/ajustes'), 1500)
        }
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al contabilizar')
    } finally {
        contabilizando.value = false
    }
}

const cancelarConfirmacion = () => {
    mostrarConfirmacion.value = false
}

// Inicializar
onMounted(() => {
    cargarDetalles()
    
    if (detallesGrid.value.length > 0) {
        cabeceraGuardada.value = true
    }
    
    if (form.value.IdRealizadoPor) {
        const persona = props.personas.find(p => p.id === form.value.IdRealizadoPor)
        if (persona) busquedaRealizadoPor.value = `${persona.ci} - ${persona.nombre}`
    }
    if (form.value.IdAutorizadoPor) {
        const persona = props.personas.find(p => p.id === form.value.IdAutorizadoPor)
        if (persona) busquedaAutorizadoPor.value = `${persona.ci} - ${persona.nombre}`
    }
})
</script>

<style scoped>
/* 🔥 Eliminar flechas del input number */
.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-spinner {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>

<template>
    <div class="min-h-screen bg-gray-100 py-4 px-3">
        <div class="max-w-full mx-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-guindo-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clipboard-list text-guindo-600 text-sm"></i>
                    </div>
                    <h1 class="text-lg font-bold text-gray-800">Nuevo Ajuste de Inventario</h1>
                </div>
            </div>

            <!-- Formulario Cabecera -->
            <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-sm font-semibold text-gray-700">Datos del Ajuste</h2>
                    <button 
                        @click="guardarCabecera"
                        :disabled="guardandoCabecera"
                        class="bg-guindo-600 hover:bg-guindo-700 text-white px-3 py-1 rounded text-xs flex items-center gap-1"
                    >
                        <i v-if="guardandoCabecera" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ guardandoCabecera ? 'Guardando...' : 'Guardar Cabecera' }}
                    </button>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                    <div>
                        <label class="block text-gray-600 mb-0.5">Fecha *</label>
                        <select v-model="form.IdFecha" class="w-full border rounded px-2 py-1 text-xs" :class="{'border-red-500': errors.IdFecha}">
                            <option value="">Seleccione</option>
                            <option v-for="f in fechas" :key="f.id" :value="f.id">{{ f.fecha }}</option>
                        </select>
                        <p v-if="errors.IdFecha" class="text-red-500 text-[10px]">{{ errors.IdFecha }}</p>
                    </div>

                    <div>
                        <label class="block text-gray-600 mb-0.5">Concepto *</label>
                        <select v-model="form.ConceptoOperacion" class="w-full border rounded px-2 py-1 text-xs" :class="{'border-red-500': errors.ConceptoOperacion}">
                            <option value="">Seleccione</option>
                            <option value="Ingreso">Ingreso</option>
                            <option value="Salida">Salida</option>
                        </select>
                        <p v-if="errors.ConceptoOperacion" class="text-red-500 text-[10px]">{{ errors.ConceptoOperacion }}</p>
                    </div>

                    <div>
                        <label class="block text-gray-600 mb-0.5">Tipo Operación *</label>
                        <select v-model="form.IdTipoOperacion" class="w-full border rounded px-2 py-1 text-xs" :class="{'border-red-500': errors.IdTipoOperacion}" :disabled="!form.ConceptoOperacion">
                            <option value="">Seleccione</option>
                            <option v-for="t in tiposFiltrados" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                        </select>
                        <p v-if="errors.IdTipoOperacion" class="text-red-500 text-[10px]">{{ errors.IdTipoOperacion }}</p>
                        <p v-if="form.ConceptoOperacion && tiposFiltrados.length === 0" class="text-amber-500 text-[10px]">No hay tipos para este concepto</p>
                    </div>

                    <div>
                        <label class="block text-gray-600 mb-0.5">Almacén *</label>
                        <select v-model="form.IdAlmacen" class="w-full border rounded px-2 py-1 text-xs" :class="{'border-red-500': errors.IdAlmacen}">
                            <option value="">Seleccione</option>
                            <option v-for="a in almacenes" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                        </select>
                        <p v-if="errors.IdAlmacen" class="text-red-500 text-[10px]">{{ errors.IdAlmacen }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-gray-600 mb-0.5">Realizado Por *</label>
                        <div class="relative">
                            <input type="text" v-model="busquedaRealizadoPor" class="w-full border rounded px-2 py-1 text-xs" :class="{'border-red-500': errors.IdRealizadoPor}" placeholder="Buscar por NIT o nombre..." @focus="busquedaRealizadoPor = ''">
                            <div v-if="busquedaRealizadoPor && personasFiltradasRealizado.length" class="absolute z-10 mt-1 w-full bg-white border rounded shadow-lg max-h-32 overflow-y-auto">
                                <div v-for="p in personasFiltradasRealizado" :key="p.id" @click="form.IdRealizadoPor = p.id; busquedaRealizadoPor = `${p.ci} - ${p.nombre}`" class="px-2 py-1 hover:bg-gray-100 cursor-pointer border-b text-xs">
                                    <span class="font-mono">{{ p.ci }}</span> - {{ p.nombre }}
                                </div>
                            </div>
                        </div>
                        <p v-if="errors.IdRealizadoPor" class="text-red-500 text-[10px]">{{ errors.IdRealizadoPor }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-gray-600 mb-0.5">Autorizado Por *</label>
                        <div class="relative">
                            <input type="text" v-model="busquedaAutorizadoPor" class="w-full border rounded px-2 py-1 text-xs" :class="{'border-red-500': errors.IdAutorizadoPor}" placeholder="Buscar por NIT o nombre..." @focus="busquedaAutorizadoPor = ''">
                            <div v-if="busquedaAutorizadoPor && personasFiltradasAutorizado.length" class="absolute z-10 mt-1 w-full bg-white border rounded shadow-lg max-h-32 overflow-y-auto">
                                <div v-for="p in personasFiltradasAutorizado" :key="p.id" @click="form.IdAutorizadoPor = p.id; busquedaAutorizadoPor = `${p.ci} - ${p.nombre}`" class="px-2 py-1 hover:bg-gray-100 cursor-pointer border-b text-xs">
                                    <span class="font-mono">{{ p.ci }}</span> - {{ p.nombre }}
                                </div>
                            </div>
                        </div>
                        <p v-if="errors.IdAutorizadoPor" class="text-red-500 text-[10px]">{{ errors.IdAutorizadoPor }}</p>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-gray-600 mb-0.5">Explicación / Motivo</label>
                        <textarea v-model="form.Explicacion" rows="1" class="w-full border rounded px-2 py-1 text-xs" placeholder="Motivo del ajuste..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Sección Detalle (solo visible después de guardar cabecera) -->
            <div v-if="cabeceraGuardada" class="bg-white rounded-lg shadow-sm p-3 mb-4">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Productos Afectados</h2>

                <!-- Buscador de Producto -->
                <div class="grid grid-cols-12 gap-2 mb-2 text-xs">
                    <div class="col-span-12 md:col-span-5 relative">
                        <label class="block text-gray-600 mb-0.5">Producto</label>
                        <input type="text" v-model="busquedaProducto" class="w-full border rounded px-2 py-1 text-xs" placeholder="Buscar por código o nombre..." @focus="busquedaProducto = ''">
                        <div v-if="busquedaProducto && productosFiltrados.length" class="absolute z-10 mt-1 w-full bg-white border rounded shadow-lg max-h-32 overflow-y-auto">
                            <div v-for="prod in productosFiltrados" :key="prod.id" @click="seleccionarProducto(prod)" class="px-2 py-1 hover:bg-gray-100 cursor-pointer border-b text-xs">
                                <span class="font-mono">{{ prod.Codigo }}</span> - {{ prod.Descripcion }}
                            </div>
                        </div>
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-gray-600 mb-0.5">Unidades</label>
                        <input 
                            id="unidades_input" 
                            type="number" 
                            step="0.01" 
                            v-model.number="nuevoProducto.Unidades"
                            class="no-spinner w-full border rounded px-2 py-1 text-xs" 
                            placeholder="0.00"
                        >
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-gray-600 mb-0.5">Monto Bs</label>
                        <input 
                            id="bolivianos_input" 
                            type="number" 
                            step="0.01" 
                            v-model.number="nuevoProducto.Bolivianos"
                            class="no-spinner w-full border rounded px-2 py-1 text-xs" 
                            placeholder="0.00"
                        >
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-gray-600 mb-0.5">Precio Unit.</label>
                        <input 
                            id="precio_display" 
                            type="text" 
                            readonly 
                            class="w-full border rounded px-2 py-1 text-xs bg-gray-100" 
                            :value="precioCalculado"
                        >
                    </div>
                </div>

                <div class="flex justify-end mb-3">
                    <button @click="agregarProducto" :disabled="guardandoDetalle || !nuevoProducto.IdProducto" class="bg-guindo-600 hover:bg-guindo-700 text-white px-3 py-1 rounded text-xs flex items-center gap-1">
                        <i v-if="guardandoDetalle" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-plus"></i>
                        {{ guardandoDetalle ? 'Agregando...' : 'Agregar' }}
                    </button>
                </div>

                <!-- Tabla de productos -->
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs border">
                        <thead class="bg-guindo-50">
                            <tr>
                                <th class="px-2 py-1 text-left text-guindo-700">Código</th>
                                <th class="px-2 py-1 text-left text-guindo-700">Producto</th>
                                <th class="px-2 py-1 text-right text-guindo-700">Unidades</th>
                                <th class="px-2 py-1 text-right text-guindo-700">Monto Bs</th>
                                <th class="px-2 py-1 text-center text-guindo-700"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(item, index) in detallesGrid" :key="index" class="hover:bg-gray-50">
                                <td class="px-2 py-1 font-mono">{{ item.Codigo }}</td>
                                <td class="px-2 py-1">{{ item.Descripcion }}</td>
                                <td class="px-2 py-1 text-right">{{ Number(item.Unidades).toFixed(2) }}</td>
                                <td class="px-2 py-1 text-right font-semibold text-guindo-600">{{ Number(item.Bolivianos).toFixed(2) }}</td>
                                <td class="px-2 py-1 text-center">
                                    <button @click="eliminarProducto(index)" class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="detallesGrid.length === 0">
                                <td colspan="5" class="px-2 py-4 text-center text-gray-400 text-xs">No hay productos agregados</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Botón Contabilizar -->
                <div class="mt-3 flex justify-end">
                    <button @click="contabilizar" :disabled="contabilizando || detallesGrid.length === 0" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-1.5 rounded text-sm font-medium flex items-center gap-1">
                        <i v-if="contabilizando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check-circle"></i>
                        {{ contabilizando ? 'Contabilizando...' : 'CONTABILIZAR' }}
                    </button>
                </div>
            </div>

            <!-- Mensaje si cabecera no guardada -->
            <div v-else class="bg-yellow-50 rounded-lg p-3 text-yellow-800 text-xs text-center">
                <i class="fas fa-info-circle mr-1"></i>
                Complete todos los campos de la cabecera y presione "Guardar Cabecera" para comenzar a agregar productos.
            </div>

            <!-- Modal de Confirmación -->
            <div v-if="mostrarConfirmacion" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-lg max-w-sm w-full">
                    <div class="bg-amber-500 p-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                            <h3 class="text-white font-semibold text-sm">Confirmar Contabilización</h3>
                        </div>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-700 text-sm mb-3">¿Está seguro de contabilizar este ajuste?</p>
                        <p class="text-gray-500 text-xs mb-4">Una vez contabilizado, no se podrá modificar.</p>
                        <div class="flex gap-2">
                            <button @click="cancelarConfirmacion" class="flex-1 py-1.5 rounded bg-gray-200 text-gray-700 text-sm hover:bg-gray-300">Cancelar</button>
                            <button @click="ejecutarContabilizar" class="flex-1 py-1.5 rounded bg-emerald-600 text-white text-sm hover:bg-emerald-700">Sí, Contabilizar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>