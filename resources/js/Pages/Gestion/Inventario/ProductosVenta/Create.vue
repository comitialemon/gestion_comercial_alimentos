<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, inject, computed, watch, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import PrecioSucursalTab from './components/PrecioSucursalTab.vue'
import PrecioMayoristaTab from './components/PrecioMayoristaTab.vue'
import InventarioDetalleTab from './components/InventarioDetalleTab.vue'
import ComboOpcionTab from './components/ComboOpcionTab.vue'
import ModalCategorias from './components/ModalCategorias.vue'
import ModalDuplicado from './components/ModalDuplicado.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    producto: Object,
    preciosSucursal: Array,
    preciosMayorista: Array,
    detalles: Array,
    categorias: Array,
    sucursales: Array,
    identificadores: Array,
    productosInventario: Array,
    editando: Boolean,
    errors: Object,
    flash: Object,
})

const activeTab = ref(0)
const productoGuardado = ref(props.editando || false)
const productoId = ref(props.producto?.IdDetalleProducto || null)
const enviandoAprobacion = ref(false)
const eliminando = ref(false)

// 🔥 ESTADOS PARA IMAGEN (FormData)
const archivoImagen = ref(null)           // Archivo nuevo seleccionado
const eliminarImagenFlag = ref(false)     // Flag para eliminar imagen existente
const imgInput = ref(null)

// Estado del modal de categorías
const modalCategoriasOpen = ref(false)
const categoriaNombre = ref('')

// Estado para modal de duplicado
const modalDuplicadoOpen = ref(false)
const productoDuplicado = ref(null)

const ESTADO_ACTIVO = 0
const ESTADO_INACTIVO = 1
const ESTADO_PENDIENTE = 2
const ESTADO_RECHAZADO = 3

// FORM
const form = useForm({
    id_categoria: props.producto?.id_categoria || '',
    Codigo: props.producto?.Codigo || '',
    Detalle: props.producto?.Detalle || '',
    PrecioVenta: props.producto?.PrecioVenta || 0,
    preview_url: props.producto?.ImagenProducto || null,
})

const puedeEnviarAprobacion = computed(() => {
    if (!props.editando) return false
    const estado = props.producto?.ActivoInactivo
    return estado === ESTADO_INACTIVO || estado === ESTADO_RECHAZADO
})

const mostrarToggleEstado = computed(() => {
    if (!props.editando) return false
    const estado = props.producto?.ActivoInactivo
    return estado === ESTADO_ACTIVO || estado === ESTADO_INACTIVO
})

const mostrarDescartarBorrador = computed(() => {
    if (!props.editando) return false
    const estado = props.producto?.ActivoInactivo
    return estado === ESTADO_INACTIVO
})

const estadoTexto = computed(() => {
    if (!props.editando || !props.producto) return ''
    switch(props.producto.ActivoInactivo) {
        case ESTADO_ACTIVO: return 'Activo'
        case ESTADO_INACTIVO: return 'Borrador'
        case ESTADO_PENDIENTE: return 'Pendiente de Aprobación'
        case ESTADO_RECHAZADO: return 'Rechazado'
        default: return 'Desconocido'
    }
})

const estadoClase = computed(() => {
    if (!props.editando || !props.producto) return ''
    switch(props.producto.ActivoInactivo) {
        case ESTADO_ACTIVO: return 'bg-green-100 text-green-800'
        case ESTADO_INACTIVO: return 'bg-gray-100 text-gray-600'
        case ESTADO_PENDIENTE: return 'bg-yellow-100 text-yellow-800'
        case ESTADO_RECHAZADO: return 'bg-red-100 text-red-800'
        default: return 'bg-gray-100 text-gray-500'
    }
})

const puedeEditar = computed(() => {
    if (!props.editando) return true
    const estado = props.producto?.ActivoInactivo
    return estado === ESTADO_ACTIVO || estado === ESTADO_INACTIVO || estado === ESTADO_RECHAZADO
})

const textoBotonEstado = computed(() => {
    if (!props.editando) return ''
    const estado = props.producto?.ActivoInactivo
    if (estado === ESTADO_ACTIVO) return 'Desactivar'
    if (estado === ESTADO_INACTIVO) return 'Activar'
    return ''
})

// Categorías
const actualizarNombreCategoria = () => {
    if (form.id_categoria && props.categorias) {
        const categoriaEncontrada = props.categorias.find(c => c.id === form.id_categoria || c.id_categoria === form.id_categoria)
        if (categoriaEncontrada) {
            categoriaNombre.value = categoriaEncontrada.nombre
        } else {
            categoriaNombre.value = ''
        }
    } else {
        categoriaNombre.value = ''
    }
}

const inicializarCategoria = () => {
    if (props.producto?.id_categoria && props.categorias) {
        const categoriaEncontrada = props.categorias.find(c => 
            c.id === props.producto.id_categoria || 
            c.id_categoria === props.producto.id_categoria
        )
        if (categoriaEncontrada) {
            form.id_categoria = categoriaEncontrada.id || categoriaEncontrada.id_categoria
            categoriaNombre.value = categoriaEncontrada.nombre
        }
    }
}

const seleccionarCategoriaModal = (categoria) => {
    form.id_categoria = categoria.id_categoria || categoria.id
    categoriaNombre.value = categoria.nombre
    modalCategoriasOpen.value = false
}

const abrirModalCategorias = () => {
    if (props.editando && props.producto?.ActivoInactivo === ESTADO_PENDIENTE) {
        toast?.warning('Atención', 'No se puede editar un producto pendiente de aprobación')
        return
    }
    modalCategoriasOpen.value = true
}

// 🔥 MÉTODOS PARA IMAGEN (FormData)
const onImageChange = (event) => {
    const file = event.target.files[0]
    if (!file) return
    
    // Validar tipo de archivo
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp']
    if (!allowedTypes.includes(file.type)) {
        toast?.error('Error', 'Solo se permiten imágenes JPG, PNG o WEBP')
        event.target.value = ''
        return
    }
    
    // Validar tamaño (max 512KB)
    if (file.size > 512 * 1024) {
        toast?.error('Error', 'La imagen no puede superar los 512KB')
        event.target.value = ''
        return
    }
    
    archivoImagen.value = file
    eliminarImagenFlag.value = false  // Si selecciona nueva, cancela eliminación
    
    // Previsualización
    const reader = new FileReader()
    reader.onload = (e) => {
        form.preview_url = e.target.result
    }
    reader.readAsDataURL(file)
}

const eliminarImagenExistente = () => {
    if (props.editando && props.producto?.ImagenProducto) {
        eliminarImagenFlag.value = true
        form.preview_url = null
        archivoImagen.value = null
        if (imgInput.value) imgInput.value.value = ''
        toast?.info('Info', 'La imagen será eliminada al guardar')
    }
}

const cancelarNuevaImagen = () => {
    archivoImagen.value = null
    form.preview_url = props.producto?.ImagenProducto || null
    eliminarImagenFlag.value = false
    if (imgInput.value) imgInput.value.value = ''
}

const preciosSucursalList = ref(props.preciosSucursal || [])
const preciosMayoristaList = ref(props.preciosMayorista || [])
const detallesList = ref(props.detalles || [])

// 🔥 GUARDAR PRODUCTO (con FormData)
const guardarProducto = () => {
    const formData = new FormData()
    
    formData.append('id_categoria', form.id_categoria || '')
    formData.append('Codigo', form.Codigo)
    formData.append('Detalle', form.Detalle)
    formData.append('PrecioVenta', form.PrecioVenta)
    
    // Flag para eliminar imagen existente
    if (eliminarImagenFlag.value) {
        formData.append('eliminar_imagen', '1')
    }
    
    // Nueva imagen (si hay)
    if (archivoImagen.value) {
        formData.append('imagen', archivoImagen.value)
    }
    
    if (props.editando) {
        // UPDATE
        axios.post(`/gestion/productos-venta/${props.producto.IdDetalleProducto}`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'X-HTTP-Method-Override': 'PUT'
            }
        })
        .then(response => {
            if (response.data.success) {
                toast?.success('Éxito', response.data.message || 'Producto actualizado correctamente')
                if (response.data.producto) {
                    // Actualizar los datos del producto
                    form.id_categoria = response.data.producto.id_categoria
                    form.Codigo = response.data.producto.Codigo
                    form.Detalle = response.data.producto.Detalle
                    form.PrecioVenta = response.data.producto.PrecioVenta
                    form.preview_url = response.data.producto.ImagenProducto
                    
                    // Resetear flags
                    archivoImagen.value = null
                    eliminarImagenFlag.value = false
                    
                    if (props.producto) {
                        props.producto = { ...props.producto, ...response.data.producto }
                    }
                }
            } else {
                toast?.error('Error', response.data.message || 'Error al actualizar')
            }
        })
        .catch(error => {
            console.error('Errores:', error)
            const message = error.response?.data?.message || 'Verifique los datos ingresados'
            toast?.error('Error', message)
        })
    } else {
        // STORE (crear nuevo)
        axios.post('/gestion/productos-venta', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })
        .then(response => {
            if (response.data.success) {
                toast?.success('Éxito', response.data.message || 'Producto creado correctamente')
                productoGuardado.value = true
                setTimeout(() => {
                    router.get(`/gestion/productos-venta/${response.data.producto_id}/edit`)
                }, 1000)
            } else {
                toast?.error('Error', response.data.message || 'Error al crear')
            }
        })
        .catch(error => {
            console.error('Errores:', error)
            const message = error.response?.data?.message || 'Verifique los datos ingresados'
            toast?.error('Error', message)
        })
    }
}

const descartarBorrador = async () => {
    if (!props.producto?.IdDetalleProducto) return
    eliminando.value = true
    try {
        await axios.delete(`/gestion/productos-venta/${props.producto.IdDetalleProducto}`)
        toast?.success('🗑️ Borrador eliminado', 'El producto ha sido descartado')
        setTimeout(() => {
            router.get('/gestion/productos-venta')
        }, 1000)
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'No se pudo eliminar el borrador')
    } finally {
        eliminando.value = false
    }
}

const verificarComposicionAntesDeEnviar = async () => {
     if (detallesList.value.length === 0) {
        // 🔥 CAMBIAR warning POR error O info
        toast?.error('Atención', 'Agregue productos al detalle antes de enviar a aprobación')
        return false
    }
    
    const productosIds = detallesList.value.map(d => d.IdProducto)
    const porciones = detallesList.value.map(d => d.Porcion)
    
    try {
        const response = await axios.post('/api/productos-venta/verificar-composicion', {
            productos_ids: productosIds,
            porciones: porciones,
            excluir_id: props.producto?.IdDetalleProducto || null
        })
        
        if (response.data.existe) {
            productoDuplicado.value = response.data.producto
            modalDuplicadoOpen.value = true
            return false
        }
        return true
    } catch (error) {
        console.error('Error verificando composición:', error)
        return true
    }
}

const cancelarCreacion = async () => {
    if (props.producto?.IdDetalleProducto && !props.editando) {
        try {
            await axios.delete(`/gestion/productos-venta/${props.producto.IdDetalleProducto}`)
        } catch (error) {
            console.error('Error eliminando borrador:', error)
        }
    }
    toast?.info('Información', 'Creación cancelada')
    setTimeout(() => {
        router.get('/gestion/productos-venta')
    }, 500)
}

const enviarAprobacion = async () => {
    const composicionValida = await verificarComposicionAntesDeEnviar()
    if (!composicionValida) return
    enviandoAprobacion.value = true
    try {
        await axios.post(`/gestion/productos-venta/${props.producto.IdDetalleProducto}/enviar-aprobacion`)
        toast?.success('Éxito', 'Producto enviado a aprobación correctamente')
        setTimeout(() => {
            router.get('/gestion/productos-venta')
        }, 1500)
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'Error al enviar')
    } finally {
        enviandoAprobacion.value = false
    }
}

const toggleEstado = async () => {
    if (!props.editando) return
    const estadoActual = props.producto?.ActivoInactivo
    const accion = estadoActual === ESTADO_ACTIVO ? 'desactivar' : 'activar'
    if (accion === 'activar') {
        if (detallesList.value.length === 0) {
            toast?.error('Error', 'No se puede activar. El producto no tiene relación con inventario.')
            return
        }
    }
    try {
        const response = await axios.post(`/gestion/productos-venta/${props.producto.IdDetalleProducto}/${accion}`)
        if (response.data.success) {
            toast?.success('Éxito', response.data.message || `Producto ${accion === 'activar' ? 'activado' : 'desactivado'} correctamente`)
            setTimeout(() => {
                window.location.reload()
            }, 500)
        } else {
            toast?.error('Error', response.data.message || 'Error al cambiar estado')
        }
    } catch (error) {
        console.error('Error:', error)
        const message = error.response?.data?.message || error.message || 'Error de conexión'
        toast?.error('Error', message)
    }
}

watch(() => props.producto, (nuevoProducto) => {
    if (nuevoProducto && nuevoProducto.id_categoria && props.categorias) {
        const categoriaEncontrada = props.categorias.find(c => 
            c.id === nuevoProducto.id_categoria || 
            c.id_categoria === nuevoProducto.id_categoria
        )
        if (categoriaEncontrada) {
            form.id_categoria = categoriaEncontrada.id || categoriaEncontrada.id_categoria
            categoriaNombre.value = categoriaEncontrada.nombre
        }
    }
}, { immediate: true, deep: true })

watch(() => form.id_categoria, () => {
    actualizarNombreCategoria()
}, { immediate: true })

onMounted(() => {
    inicializarCategoria()
})

if (props.errors) {
    Object.keys(props.errors).forEach(key => {
        if (form.errors) form.errors[key] = props.errors[key]
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-3 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-box text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-gray-800">{{ editando ? 'Editar Producto' : 'Nuevo Producto' }}</h1>
                            <p class="text-[10px] text-gray-500">{{ editando ? 'Modifique los datos del producto' : 'Complete los datos del nuevo producto' }}</p>
                        </div>
                    </div>
                    
                    <div v-if="editando" class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-600">Estado:</span>
                        <span class="px-2 py-0.5 text-xs rounded-full" :class="estadoClase">
                            {{ estadoTexto }}
                        </span>
                    </div>
                    
                    <div class="flex gap-2">
                        <button type="button" @click="router.get('/gestion/productos-venta')" class="px-3 py-1 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                            Cancelar
                        </button>
                        
                        <button v-if="mostrarDescartarBorrador" @click="descartarBorrador" :disabled="eliminando" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-md text-xs transition disabled:opacity-50 flex items-center gap-1">
                            <i v-if="eliminando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-trash-alt text-[10px]"></i>
                            {{ eliminando ? 'Eliminando...' : 'Descartar Borrador' }}
                        </button>
                        
                        <button v-if="editando && mostrarToggleEstado" @click="toggleEstado" class="px-3 py-1 rounded-md text-xs transition" :class="props.producto?.ActivoInactivo === 0 ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white'">
                            <i :class="props.producto?.ActivoInactivo === 0 ? 'fas fa-ban text-[10px]' : 'fas fa-check-circle text-[10px]'"></i>
                            {{ textoBotonEstado }}
                        </button>
                        
                        <button v-if="editando && puedeEnviarAprobacion" @click="enviarAprobacion" :disabled="enviandoAprobacion" class="px-3 py-1 bg-blue-600 text-white rounded-md text-xs hover:bg-blue-700 transition disabled:opacity-50 flex items-center gap-1">
                            <i v-if="enviandoAprobacion" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-paper-plane text-[10px]"></i>
                            {{ enviandoAprobacion ? 'Enviando...' : 'Enviar a Aprobación' }}
                        </button>
                        
                        <button @click="guardarProducto" :disabled="form.processing || (editando && !puedeEditar)" class="px-3 py-1 bg-emerald-600 text-white rounded-md text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-1">
                            <i v-if="form.processing" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            {{ form.processing ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </div>

                <!-- Mensajes informativos -->
                <div v-if="editando && props.producto?.ActivoInactivo === 2" class="mb-4 p-3 rounded-lg bg-yellow-50 border border-yellow-200">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-clock text-yellow-600"></i>
                        <span class="text-xs text-yellow-700">
                            Este producto está pendiente de aprobación y no puede ser editado.
                        </span>
                    </div>
                </div>

                <div v-if="editando && props.producto?.ActivoInactivo === 3" class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-times-circle text-red-600"></i>
                        <span class="text-xs text-red-700">
                            Este producto fue rechazado. Puede corregirlo y enviarlo nuevamente a aprobación.
                        </span>
                    </div>
                </div>

                <!-- Formulario Principal -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4" :class="{ 'opacity-70': editando && props.producto?.ActivoInactivo === 2 }">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-3">
                        <!-- Campo Categoría -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Categoría *</label>
                            <div class="flex gap-2">
                                <div 
                                    @click="abrirModalCategorias"
                                    class="flex-1 border rounded-md px-2 py-1.5 text-xs cursor-pointer hover:border-primary-400 transition flex items-center justify-between"
                                    :class="{ 'border-red-500': form.errors.id_categoria, 'bg-gray-100 cursor-not-allowed': editando && props.producto?.ActivoInactivo === 2 }"
                                >
                                    <span :class="{ 'text-gray-400': !categoriaNombre }">
                                        {{ categoriaNombre || 'Seleccione una categoría' }}
                                    </span>
                                    <i class="fas fa-chevron-down text-gray-400 text-[10px]"></i>
                                </div>
                                <button 
                                    v-if="categoriaNombre && !(editando && props.producto?.ActivoInactivo === 2)"
                                    @click="form.id_categoria = ''; categoriaNombre = ''" 
                                    type="button" 
                                    class="px-2 py-1 bg-red-100 text-red-600 rounded-md text-xs hover:bg-red-200"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <p v-if="form.errors.id_categoria" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.id_categoria }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Código *</label>
                            <input type="text" v-model="form.Codigo" class="w-full border rounded-md px-2 py-1.5 text-xs uppercase" :class="{ 'border-red-500': form.errors.Codigo }" placeholder="CÓDIGO ÚNICO" :disabled="editando && props.producto?.ActivoInactivo === 2">
                            <p v-if="form.errors.Codigo" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Codigo }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Detalle *</label>
                            <input type="text" v-model="form.Detalle" class="w-full border rounded-md px-2 py-1.5 text-xs" :class="{ 'border-red-500': form.errors.Detalle }" placeholder="Nombre del producto" :disabled="editando && props.producto?.ActivoInactivo === 2">
                            <p v-if="form.errors.Detalle" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.Detalle }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Precio Venta (Bs) *</label>
                            <div class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-500 text-[10px]">Bs</span>
                                <input 
                                    type="number" 
                                    v-model.number="form.PrecioVenta" 
                                    step="0.01" 
                                    min="0" 
                                    class="w-full border rounded-md pl-7 pr-2 py-1.5 text-xs [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                    :class="{ 'border-red-500': form.errors.PrecioVenta }" 
                                    placeholder="0.00" 
                                    :disabled="editando && props.producto?.ActivoInactivo === 2"
                                >
                            </div>
                            <p v-if="form.errors.PrecioVenta" class="text-[10px] text-red-500 mt-0.5">{{ form.errors.PrecioVenta }}</p>
                        </div>
                    </div>

                    <!-- 🔥 IMAGEN CON FORMDATA -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Imagen</label>
                        <div class="flex flex-wrap items-center gap-3">
                            <input 
                                type="file" 
                                ref="imgInput" 
                                @change="onImageChange" 
                                accept="image/jpeg,image/png,image/jpg,image/webp" 
                                class="flex-1 border rounded-md px-2 py-1.5 text-xs file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                                :disabled="editando && props.producto?.ActivoInactivo === 2"
                            >
                            
                            <!-- Botón para eliminar imagen existente (solo en edición y si hay imagen) -->
                            <button 
                                v-if="editando && form.preview_url && !archivoImagen && !eliminarImagenFlag" 
                                @click="eliminarImagenExistente" 
                                type="button" 
                                class="px-3 py-1 bg-red-100 text-red-600 rounded-md text-xs hover:bg-red-200"
                            >
                                <i class="fas fa-trash-alt mr-1"></i> Eliminar imagen
                            </button>
                            
                            <!-- Botón para cancelar nueva imagen seleccionada -->
                            <button 
                                v-if="archivoImagen" 
                                @click="cancelarNuevaImagen" 
                                type="button" 
                                class="px-3 py-1 bg-gray-200 text-gray-700 rounded-md text-xs hover:bg-gray-300"
                            >
                                <i class="fas fa-times mr-1"></i> Cancelar
                            </button>
                        </div>
                        
                        <!-- Previsualización -->
                        <div v-if="form.preview_url" class="mt-2">
                            <img :src="form.preview_url" class="w-14 h-14 object-cover rounded-md border">
                            <p class="text-[9px] text-gray-400 mt-0.5">
                                <i class="fas fa-info-circle"></i> 
                                {{ archivoImagen ? 'Nueva imagen seleccionada' : (eliminarImagenFlag ? 'La imagen será eliminada al guardar' : 'Imagen actual') }}
                            </p>
                        </div>
                        
                        <!-- Indicador de que se va a eliminar -->
                        <div v-if="eliminarImagenFlag && !archivoImagen" class="mt-1">
                            <span class="text-[10px] text-red-500">
                                <i class="fas fa-trash-alt mr-1"></i> La imagen actual será eliminada al guardar
                            </span>
                        </div>
                        
                        <p class="text-[9px] text-gray-400 mt-1">
                            <i class="fas fa-info-circle"></i> 
                            Formatos permitidos: JPG, PNG, WEBP. Tamaño máximo: 512KB
                        </p>
                    </div>
                </div>

                <!-- Pestañas -->
                <div v-if="editando || productoGuardado" class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="border-b border-gray-200">
                        <nav class="flex justify-center -mb-px flex-wrap">
                            <button @click="activeTab = 0" class="px-4 py-2 text-xs font-medium transition" :class="activeTab === 0 ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'">
                                <i class="fas fa-store mr-1 text-[10px]"></i> Precio Sucursal
                            </button>
                            <button @click="activeTab = 1" class="px-4 py-2 text-xs font-medium transition" :class="activeTab === 1 ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'">
                                <i class="fas fa-chart-line mr-1 text-[10px]"></i> Precio Mayorista
                            </button>
                            <button @click="activeTab = 2" class="px-4 py-2 text-xs font-medium transition" :class="activeTab === 2 ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'">
                                <i class="fas fa-cubes mr-1 text-[10px]"></i> Inventario Detalle
                            </button>
                            <button @click="activeTab = 3" class="px-4 py-2 text-xs font-medium transition" :class="activeTab === 3 ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'">
                                <i class="fas fa-random mr-1 text-[10px]"></i> Opciones de Combo
                            </button>
                        </nav>
                    </div>
                    <div class="p-4">
                        <div v-show="activeTab === 0">
                            <PrecioSucursalTab 
                                :producto-id="editando ? props.producto?.IdDetalleProducto : productoId"
                                :sucursales="sucursales"
                                :precios-iniciales="preciosSucursalList"
                                @update="preciosSucursalList = $event"
                            />
                        </div>
                        <div v-show="activeTab === 1">
                            <PrecioMayoristaTab 
                                :producto-id="editando ? props.producto?.IdDetalleProducto : productoId"
                                :sucursales="sucursales"
                                :identificadores="identificadores"
                                :precios-iniciales="preciosMayoristaList"
                                @update="preciosMayoristaList = $event"
                            />
                        </div>
                        <div v-show="activeTab === 2">
                            <InventarioDetalleTab 
                                :producto-id="editando ? props.producto?.IdDetalleProducto : productoId"
                                :productos-inventario="productosInventario"
                                :detalles-iniciales="detallesList"
                                @update="detallesList = $event"
                            />
                        </div>
                        <div v-show="activeTab === 3">
                            <ComboOpcionTab 
                                :producto-id="editando ? props.producto?.IdDetalleProducto : productoId"
                                @update="cargarOpciones"
                            />
                        </div>
                    </div>
                </div>

                <div v-else class="bg-secondary-50 rounded-lg border border-secondary-200 p-4 text-center">
                    <i class="fas fa-info-circle text-secondary-500 text-sm mb-2 block"></i>
                    <p class="text-xs text-secondary-700">Complete los datos del producto y presione "Guardar" para poder configurar precios por sucursal, precios mayorista y detalle de inventario.</p>
                </div>
            </div>
        </div>

        <!-- Modal de Categorías -->
        <ModalCategorias 
            v-model="modalCategoriasOpen"
            :categorias="categorias"
            :categoria-seleccionada="categorias?.find(c => c.id === form.id_categoria || c.id_categoria === form.id_categoria)"
            @select="seleccionarCategoriaModal"
        />

        <!-- Modal de Duplicado -->
        <ModalDuplicado
            v-model:visible="modalDuplicadoOpen"
            :producto-existente="productoDuplicado"
            @continuar="modalDuplicadoOpen = false"
            @cancelar="cancelarCreacion"
        />
    </div>
</template>