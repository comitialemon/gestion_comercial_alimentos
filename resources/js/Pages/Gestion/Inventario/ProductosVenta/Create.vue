<!-- resources/js/Pages/Gestion/Inventario/ProductosVenta/Create.vue -->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, inject, computed, watch, onMounted, onUnmounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import PrecioSucursalTab from './components/PrecioSucursalTab.vue'
import PrecioMayoristaTab from './components/PrecioMayoristaTab.vue'
import InventarioDetalleTab from './components/InventarioDetalleTab.vue'
import ComboOpcionTab from './components/ComboOpcionTab.vue'
import ModalCategorias from './components/ModalCategorias.vue'
import ModalDuplicado from './components/ModalDuplicado.vue'
import DisponibilidadTab from './components/DisponibilidadTab.vue' // ✅ AGREGAR ESTA LÍNEA

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
    clienteId: Number, // 🔥 AGREGADO
})

const activeTab = ref(0)
const productoGuardado = ref(props.editando || false)
const productoId = ref(props.producto?.IdDetalleProducto || null)
const enviandoAprobacion = ref(false)
const eliminando = ref(false)

// 🔥 ESTADOS PARA APROBADOR
const verificandoAprobador = ref(false)
const aprobadorExiste = ref(true)
const nombreAprobador = ref('')
const modalAprobadorOpen = ref(false)

// 🔥 ESTADOS PARA IMAGEN (FormData)
const archivoImagen = ref(null)
const eliminarImagenFlag = ref(false)
const imgInput = ref(null)

// Estado del modal de categorías
const modalCategoriasOpen = ref(false)
const categoriaNombre = ref('')

// Estado para modal de duplicado
const modalDuplicadoOpen = ref(false)
const productoDuplicado = ref(null)

// Estado responsive
const isMobile = ref(false)
const menuAbierto = ref(false)

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

// ==================== DETECTAR RESPONSIVE ====================
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
    if (!isMobile.value) {
        menuAbierto.value = false
    }
}

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    inicializarCategoria()
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// ==================== VERIFICAR APROBADOR ====================
const verificarAprobador = async () => {
    if (!props.clienteId) {
        console.warn('No hay clienteId disponible')
        return true // Permitir continuar si no hay cliente
    }
    
    // Si el producto está en edición y ya está activo, no verificar
    if (props.editando && props.producto?.ActivoInactivo === ESTADO_ACTIVO) {
        return true
    }
    
    verificandoAprobador.value = true
    
    try {
        const response = await axios.get('/gestion/inventario/productos-venta/verificar-aprobador')
        
        if (response.data.existe) {
            aprobadorExiste.value = true
            nombreAprobador.value = response.data.aprobador || 'No definido'
            
            // Mostrar mensaje informativo solo la primera vez
            if (!sessionStorage.getItem('aprobador_mostrado')) {
                toast?.info('Información', response.data.mensaje || 'Productos serán enviados a aprobación')
                sessionStorage.setItem('aprobador_mostrado', 'true')
            }
            return true
        } else {
            aprobadorExiste.value = false
            nombreAprobador.value = ''
            
            // Mostrar modal de error
            modalAprobadorOpen.value = true
            return false
        }
    } catch (error) {
        console.error('Error verificando aprobador:', error)
        toast?.error('Error', 'No se pudo verificar la configuración de aprobación')
        return false
    } finally {
        verificandoAprobador.value = false
    }
}

// ==================== COMPUTED ====================
const puedeEnviarAprobacion = computed(() => {
    if (!props.editando) return false
    const estado = props.producto?.ActivoInactivo
    return (estado === ESTADO_INACTIVO || estado === ESTADO_RECHAZADO) && aprobadorExiste.value
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

// ==================== FUNCIONES ====================
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

// 🔥 MÉTODOS PARA IMAGEN
const onImageChange = (event) => {
    const file = event.target.files[0]
    if (!file) return
    
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp']
    if (!allowedTypes.includes(file.type)) {
        toast?.error('Error', 'Solo se permiten imágenes JPG, PNG o WEBP')
        event.target.value = ''
        return
    }
    
    if (file.size > 512 * 1024) {
        toast?.error('Error', 'La imagen no puede superar los 512KB')
        event.target.value = ''
        return
    }
    
    archivoImagen.value = file
    eliminarImagenFlag.value = false
    
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

// 🔥 GUARDAR PRODUCTO (COMPLETO CON VERIFICACIÓN DE APROBADOR)
const guardarProducto = async () => {
    // 🔥 Verificar aprobador ANTES de guardar (solo para nuevos productos)
    if (!props.editando) {
        const aprobadorValido = await verificarAprobador()
        if (!aprobadorValido) {
            return // No continuar si no hay aprobador
        }
    }
    
    const formData = new FormData()
    
    formData.append('id_categoria', form.id_categoria || '')
    formData.append('Codigo', form.Codigo)
    formData.append('Detalle', form.Detalle)
    formData.append('PrecioVenta', form.PrecioVenta)
    
    if (eliminarImagenFlag.value) {
        formData.append('eliminar_imagen', '1')
    }
    
    if (archivoImagen.value) {
        formData.append('imagen', archivoImagen.value)
    }
    
    // 🔥 Función para actualizar la imagen después de guardar
    const actualizarImagen = (response) => {
        console.log('📦 Respuesta del servidor:', response.data)
        
        if (response.data.imagen_url) {
            form.preview_url = response.data.imagen_url
            console.log('✅ Imagen actualizada desde imagen_url:', response.data.imagen_url)
            return
        }
        
        if (response.data.producto) {
            const producto = response.data.producto
            console.log('📦 Producto recibido:', producto)
            
            if (producto.imagenes && producto.imagenes.length > 0) {
                const principal = producto.imagenes.find(img => img.EsPrincipal === 1) || producto.imagenes[0]
                const imgUrl = principal.RutaThumbnail || principal.url_thumbnail
                if (imgUrl) {
                    form.preview_url = imgUrl
                    console.log('✅ Imagen actualizada desde imagenes:', imgUrl)
                    return
                }
            }
            
            if (producto.ImagenProducto) {
                form.preview_url = producto.ImagenProducto
                console.log('✅ Imagen actualizada desde ImagenProducto:', producto.ImagenProducto)
                return
            }
        }
        
        form.preview_url = null
        console.log('⚠️ No se encontró imagen en la respuesta')
    }
    
    if (props.editando) {
        axios.post(`/gestion/inventario/productos-venta/${props.producto.IdDetalleProducto}`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'X-HTTP-Method-Override': 'PUT'
            }
        })
        .then(response => {
            if (response.data.success) {
                toast?.success('Éxito', response.data.message || 'Producto actualizado correctamente')
                
                actualizarImagen(response)
                
                if (response.data.producto) {
                    const producto = response.data.producto
                    form.id_categoria = producto.id_categoria
                    form.Codigo = producto.Codigo
                    form.Detalle = producto.Detalle
                    form.PrecioVenta = producto.PrecioVenta
                    
                    if (props.producto) {
                        props.producto = { ...props.producto, ...producto }
                    }
                }
                
                archivoImagen.value = null
                eliminarImagenFlag.value = false
                if (imgInput.value) imgInput.value.value = ''
                
            } else {
                toast?.error('Error', response.data.message || 'Error al actualizar')
            }
        })
        .catch(error => {
            console.error('❌ Errores:', error)
            const message = error.response?.data?.message || 'Verifique los datos ingresados'
            toast?.error('Error', message)
        })
    } else {
        axios.post('/gestion/inventario/productos-venta', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })
        .then(response => {
            if (response.data.success) {
                toast?.success('Éxito', response.data.message || 'Producto creado correctamente')
                
                actualizarImagen(response)
                
                productoGuardado.value = true
                setTimeout(() => {
                    router.get(`/gestion/inventario/productos-venta/${response.data.producto_id}/edit`)
                }, 1000)
            } else {
                toast?.error('Error', response.data.message || 'Error al crear')
            }
        })
        .catch(error => {
            console.error('❌ Errores:', error)
            const message = error.response?.data?.message || 'Verifique los datos ingresados'
            toast?.error('Error', message)
        })
    }
}

const descartarBorrador = async () => {
    if (!props.producto?.IdDetalleProducto) return
    eliminando.value = true
    try {
        await axios.delete(`/gestion/inventario/productos-venta/${props.producto.IdDetalleProducto}`)
        toast?.success('🗑️ Borrador eliminado', 'El producto ha sido descartado')
        setTimeout(() => {
            router.get('/gestion/inventario/productos-venta')
        }, 1000)
    } catch (error) {
        toast?.error('Error', error.response?.data?.message || 'No se pudo eliminar el borrador')
    } finally {
        eliminando.value = false
    }
}

const verificarComposicionAntesDeEnviar = async () => {
    if (detallesList.value.length === 0) {
        toast?.error('Atención', 'Agregue productos al detalle antes de enviar a aprobación')
        return false
    }
    
    // 🔥 Obtener arrays de productos y porciones
    const productosIds = detallesList.value.map(d => d.IdProducto)
    const porciones = detallesList.value.map(d => d.Porcion)
    
    try {
        const response = await axios.post('/gestion/inventario/productos-venta/verificar-composicion', {
            productos_ids: productosIds,
            porciones: porciones,  // 🔥 ENVIAR PORCIONES
            excluir_id: props.producto?.IdDetalleProducto || null
        })
        
        console.log('🔍 Respuesta de verificarComposicion:', response.data)
        
        if (response.data.existe) {
            console.log('📦 Producto duplicado encontrado:', response.data.producto)
            productoDuplicado.value = response.data.producto
            modalDuplicadoOpen.value = true
            return false
        }
        return true
    } catch (error) {
        console.error('Error verificando composición:', error)
        // Si hay error, permitir continuar (por seguridad)
        return true
    }
}

const cancelarCreacion = async () => {
    if (props.producto?.IdDetalleProducto && !props.editando) {
        try {
            await axios.delete(`/gestion/inventario/productos-venta/${props.producto.IdDetalleProducto}`)
        } catch (error) {
            console.error('Error eliminando borrador:', error)
        }
    }
    toast?.info('Información', 'Creación cancelada')
    setTimeout(() => {
        router.get('/gestion/inventario/productos-venta')
    }, 500)
}

// 🔥 ENVIAR APROBACIÓN (COMPLETO CON VERIFICACIÓN DE APROBADOR)
const enviarAprobacion = async () => {
    // 🔥 Verificar aprobador ANTES de enviar
    const aprobadorValido = await verificarAprobador()
    if (!aprobadorValido) {
        return
    }
    
    const composicionValida = await verificarComposicionAntesDeEnviar()
    if (!composicionValida) return
    
    enviandoAprobacion.value = true
    try {
        await axios.post(`/gestion/inventario/productos-venta/${props.producto.IdDetalleProducto}/enviar-aprobacion`)
        toast?.success('Éxito', 'Producto enviado a aprobación correctamente')
        setTimeout(() => {
            router.get('/gestion/inventario/productos-venta')
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
        const response = await axios.post(`/gestion/inventario/productos-venta/${props.producto.IdDetalleProducto}/${accion}`)
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

if (props.errors) {
    Object.keys(props.errors).forEach(key => {
        if (form.errors) form.errors[key] = props.errors[key]
    })
}

// ==================== TOGGLE MENÚ MÓVIL ====================
const toggleMenu = () => {
    menuAbierto.value = !menuAbierto.value
}

// 🔥 TABS
const tabs = [
    { id: 0, icon: 'fa-cubes', label: 'Detalle', fullLabel: 'Inventario Detalle' },
    { id: 1, icon: 'fa-store', label: 'Sucursal', fullLabel: 'Precio Sucursal' },
    { id: 2, icon: 'fa-chart-line', label: 'Mayorista', fullLabel: 'Precio Comisionista' },
    { id: 3, icon: 'fa-random', label: 'Combo', fullLabel: 'Opciones Combo' },
    { id: 4, icon: 'fa-calendar-day', label: 'Días', fullLabel: 'Disponibilidad Días' }, // ✅ NUEVO TAB

]

const cargarOpciones = (opciones) => {
    console.log('Opciones de combo actualizadas:', opciones)
}
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header Responsive -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-3 mb-3 sm:mb-4">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                             :style="{ backgroundColor: `var(--color-primary-100)` }">
                            <i class="fas fa-box text-primary-600 text-[11px] sm:text-sm"
                               :style="{ color: `var(--color-primary-600)` }"></i>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-sm sm:text-base font-bold text-gray-800 truncate">
                                {{ editando ? 'Editar Producto' : 'Nuevo Producto' }}
                            </h1>
                            <p class="text-[9px] sm:text-[10px] text-gray-500 truncate">
                                {{ editando ? 'Modifique los datos del producto' : 'Complete los datos del nuevo producto' }}
                            </p>
                        </div>
                    </div>
                    
                    <!-- Estado (Desktop) -->
                    <div v-if="editando" class="hidden sm:flex items-center gap-2 flex-shrink-0">
                        <span class="text-[10px] sm:text-xs font-medium text-gray-600">Estado:</span>
                        <span class="px-1.5 sm:px-2 py-0.5 text-[9px] sm:text-xs rounded-full" :class="estadoClase">
                            {{ estadoTexto }}
                        </span>
                    </div>
                    
                    <!-- 🔥 BOTONES -->
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <button 
                            @click="toggleMenu"
                            class="sm:hidden flex-1 px-3 py-1.5 bg-white border rounded-lg text-xs flex items-center justify-center gap-1.5 transition"
                            :style="{ borderColor: `var(--color-primary-300)` }"
                        >
                            <i class="fas fa-ellipsis-v text-[10px]" :style="{ color: `var(--color-primary-600)` }"></i>
                            <span class="text-gray-700">Acciones</span>
                            <i class="fas text-[10px]" :class="menuAbierto ? 'fa-chevron-up' : 'fa-chevron-down'" :style="{ color: `var(--color-primary-600)` }"></i>
                        </button>
                        
                        <div class="hidden sm:flex flex-wrap items-center gap-2">
                            <button type="button" @click="router.get('/gestion/inventario/productos-venta')" 
                                    class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                                Cancelar
                            </button>
                            
                            <button v-if="mostrarDescartarBorrador" @click="descartarBorrador" :disabled="eliminando" 
                                    class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-md text-xs transition disabled:opacity-50 flex items-center gap-1">
                                <i v-if="eliminando" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-trash-alt text-[10px]"></i>
                                {{ eliminando ? 'Eliminando...' : 'Descartar' }}
                            </button>
                            
                            <button v-if="editando && mostrarToggleEstado" @click="toggleEstado" 
                                    class="px-3 py-1.5 rounded-md text-xs transition flex items-center gap-1" 
                                    :class="props.producto?.ActivoInactivo === 0 ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white'">
                                <i :class="props.producto?.ActivoInactivo === 0 ? 'fas fa-ban text-[10px]' : 'fas fa-check-circle text-[10px]'"></i>
                                {{ textoBotonEstado }}
                            </button>
                            
                            <button v-if="editando && puedeEnviarAprobacion" @click="enviarAprobacion" :disabled="enviandoAprobacion" 
                                    class="px-3 py-1.5 bg-blue-600 text-white rounded-md text-xs hover:bg-blue-700 transition disabled:opacity-50 flex items-center gap-1">
                                <i v-if="enviandoAprobacion" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-paper-plane text-[10px]"></i>
                                {{ enviandoAprobacion ? 'Enviando...' : 'Enviar' }}
                            </button>
                        </div>
                        
                        <button @click="guardarProducto" 
                                :disabled="form.processing || (editando && !puedeEditar) || verificandoAprobador" 
                                class="flex-1 sm:flex-none px-3 sm:px-4 py-1.5 sm:py-2 bg-emerald-600 text-white rounded-md text-[11px] sm:text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center justify-center gap-1.5">
                            <i v-if="form.processing || verificandoAprobador" class="fas fa-spinner fa-spin text-[10px] sm:text-xs"></i>
                            <i v-else class="fas fa-save text-[10px] sm:text-xs"></i>
                            {{ form.processing ? 'Guardando...' : (verificandoAprobador ? 'Verificando...' : 'Guardar') }}
                        </button>
                    </div>
                </div>

                <!-- Menú de acciones móvil -->
                <div v-if="menuAbierto && isMobile" 
                     class="sm:hidden bg-white rounded-lg shadow-lg p-3 mb-3 border animate-slide-down"
                     :style="{ borderColor: `var(--color-primary-200)` }">
                    
                    <div v-if="editando" class="flex items-center gap-2 mb-2 pb-2 border-b" :style="{ borderColor: `var(--color-primary-100)` }">
                        <span class="text-[10px] font-medium text-gray-600">Estado:</span>
                        <span class="px-1.5 py-0.5 text-[9px] rounded-full" :class="estadoClase">
                            {{ estadoTexto }}
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="router.get('/gestion/inventario/productos-venta')" 
                                class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition">
                            Cancelar
                        </button>
                        
                        <button v-if="mostrarDescartarBorrador" @click="descartarBorrador" :disabled="eliminando" 
                                class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-md text-xs transition disabled:opacity-50 flex items-center justify-center gap-1">
                            <i v-if="eliminando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-trash-alt text-[10px]"></i>
                            Descartar
                        </button>
                        
                        <button v-if="editando && mostrarToggleEstado" @click="toggleEstado" 
                                class="col-span-2 px-3 py-1.5 rounded-md text-xs transition" 
                                :class="props.producto?.ActivoInactivo === 0 ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white'">
                            <i :class="props.producto?.ActivoInactivo === 0 ? 'fas fa-ban text-[10px]' : 'fas fa-check-circle text-[10px]'"></i>
                            {{ textoBotonEstado }}
                        </button>
                        
                        <button v-if="editando && puedeEnviarAprobacion" @click="enviarAprobacion" :disabled="enviandoAprobacion" 
                                class="col-span-2 px-3 py-1.5 bg-blue-600 text-white rounded-md text-xs hover:bg-blue-700 transition disabled:opacity-50 flex items-center justify-center gap-1">
                            <i v-if="enviandoAprobacion" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-paper-plane text-[10px]"></i>
                            {{ enviandoAprobacion ? 'Enviando...' : 'Enviar a Aprobación' }}
                        </button>
                    </div>
                </div>

                <!-- Mensajes informativos -->
                <div v-if="editando && props.producto?.ActivoInactivo === 2" class="mb-3 sm:mb-4 p-2 sm:p-3 rounded-lg bg-yellow-50 border border-yellow-200">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-clock text-yellow-600 text-[10px] sm:text-xs"></i>
                        <span class="text-[10px] sm:text-xs text-yellow-700">
                            Este producto está pendiente de aprobación y no puede ser editado.
                        </span>
                    </div>
                </div>

                <div v-if="editando && props.producto?.ActivoInactivo === 3" class="mb-3 sm:mb-4 p-2 sm:p-3 rounded-lg bg-red-50 border border-red-200">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-times-circle text-red-600 text-[10px] sm:text-xs"></i>
                        <span class="text-[10px] sm:text-xs text-red-700">
                            Este producto fue rechazado. Puede corregirlo y enviarlo nuevamente a aprobación.
                        </span>
                    </div>
                </div>

                <!-- Formulario Principal -->
                <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-3 sm:mb-4" 
                     :class="{ 'opacity-70': editando && props.producto?.ActivoInactivo === 2 }">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
                        <div>
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Categoría *</label>
                            <div class="flex gap-2">
                                <div 
                                    @click="abrirModalCategorias"
                                    class="flex-1 border rounded-md px-2 py-1.5 text-[10px] sm:text-xs cursor-pointer hover:border-primary-400 transition flex items-center justify-between"
                                    :class="{ 'border-red-500': form.errors.id_categoria, 'bg-gray-100 cursor-not-allowed': editando && props.producto?.ActivoInactivo === 2 }"
                                    :style="{ borderColor: form.errors.id_categoria ? '#ef4444' : `var(--color-primary-300)` }"
                                >
                                    <span :class="{ 'text-gray-400': !categoriaNombre }" class="truncate">
                                        {{ categoriaNombre || 'Seleccione una categoría' }}
                                    </span>
                                    <i class="fas fa-chevron-down text-gray-400 text-[8px] sm:text-[10px] flex-shrink-0 ml-1"></i>
                                </div>
                                <button 
                                    v-if="categoriaNombre && !(editando && props.producto?.ActivoInactivo === 2)"
                                    @click="form.id_categoria = ''; categoriaNombre = ''" 
                                    type="button" 
                                    class="px-1.5 sm:px-2 py-1 bg-red-100 text-red-600 rounded-md text-[10px] sm:text-xs hover:bg-red-200 flex-shrink-0"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <p v-if="form.errors.id_categoria" class="text-[8px] sm:text-[10px] text-red-500 mt-0.5">{{ form.errors.id_categoria }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Código *</label>
                            <input type="text" v-model="form.Codigo" 
                                   class="w-full border rounded-md px-2 py-1.5 text-[10px] sm:text-xs uppercase" 
                                   :class="{ 'border-red-500': form.errors.Codigo }" 
                                   :style="{ borderColor: form.errors.Codigo ? '#ef4444' : `var(--color-primary-300)` }"
                                   placeholder="CÓDIGO" 
                                   :disabled="editando && props.producto?.ActivoInactivo === 2">
                            <p v-if="form.errors.Codigo" class="text-[8px] sm:text-[10px] text-red-500 mt-0.5">{{ form.errors.Codigo }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Detalle *</label>
                            <input type="text" v-model="form.Detalle" 
                                   class="w-full border rounded-md px-2 py-1.5 text-[10px] sm:text-xs" 
                                   :class="{ 'border-red-500': form.errors.Detalle }" 
                                   :style="{ borderColor: form.errors.Detalle ? '#ef4444' : `var(--color-primary-300)` }"
                                   placeholder="Nombre del producto" 
                                   :disabled="editando && props.producto?.ActivoInactivo === 2">
                            <p v-if="form.errors.Detalle" class="text-[8px] sm:text-[10px] text-red-500 mt-0.5">{{ form.errors.Detalle }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Precio Venta (Bs) *</label>
                            <div class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-500 text-[9px] sm:text-[10px]">Bs</span>
                                <input 
                                    type="number" 
                                    v-model.number="form.PrecioVenta" 
                                    step="0.01" 
                                    min="0" 
                                    class="w-full border rounded-md pl-6 sm:pl-7 pr-2 py-1.5 text-[10px] sm:text-xs [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                    :class="{ 'border-red-500': form.errors.PrecioVenta }" 
                                    :style="{ borderColor: form.errors.PrecioVenta ? '#ef4444' : `var(--color-primary-300)` }"
                                    placeholder="0.00" 
                                    :disabled="editando && props.producto?.ActivoInactivo === 2"
                                >
                            </div>
                            <p v-if="form.errors.PrecioVenta" class="text-[8px] sm:text-[10px] text-red-500 mt-0.5">{{ form.errors.PrecioVenta }}</p>
                        </div>
                    </div>

                    <!-- 🔥 IMAGEN -->
                    <div class="mt-2 sm:mt-3">
                        <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Imagen</label>
                        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                            <input 
                                type="file" 
                                ref="imgInput" 
                                @change="onImageChange" 
                                accept="image/jpeg,image/png,image/jpg,image/webp" 
                                class="flex-1 border rounded-md px-2 py-1 text-[10px] sm:text-xs file:mr-2 file:py-1 file:px-2 sm:file:px-3 file:rounded-md file:border-0 file:text-[10px] sm:file:text-xs file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                                :style="{ borderColor: `var(--color-primary-300)` }"
                                :disabled="editando && props.producto?.ActivoInactivo === 2"
                            >
                            
                            <button 
                                v-if="editando && form.preview_url && !archivoImagen && !eliminarImagenFlag" 
                                @click="eliminarImagenExistente" 
                                type="button" 
                                class="px-2 sm:px-3 py-1 bg-red-100 text-red-600 rounded-md text-[10px] sm:text-xs hover:bg-red-200"
                            >
                                <i class="fas fa-trash-alt mr-1 text-[8px] sm:text-[10px]"></i> Eliminar
                            </button>
                            
                            <button 
                                v-if="archivoImagen" 
                                @click="cancelarNuevaImagen" 
                                type="button" 
                                class="px-2 sm:px-3 py-1 bg-gray-200 text-gray-700 rounded-md text-[10px] sm:text-xs hover:bg-gray-300"
                            >
                                <i class="fas fa-times mr-1 text-[8px] sm:text-[10px]"></i> Cancelar
                            </button>
                        </div>
                        
                        <div v-if="form.preview_url" class="mt-1.5 sm:mt-2 flex items-center gap-2 sm:gap-3 flex-wrap">
                            <img :src="form.preview_url" class="w-10 h-10 sm:w-14 sm:h-14 object-cover rounded-md border"
                                 :style="{ borderColor: `var(--color-primary-200)` }">
                            <p class="text-[8px] sm:text-[9px] text-gray-400">
                                <i class="fas fa-info-circle"></i> 
                                {{ archivoImagen ? 'Nueva imagen seleccionada' : (eliminarImagenFlag ? 'La imagen será eliminada al guardar' : 'Imagen actual') }}
                            </p>
                        </div>
                        
                        <div v-if="eliminarImagenFlag && !archivoImagen" class="mt-1">
                            <span class="text-[8px] sm:text-[10px] text-red-500">
                                <i class="fas fa-trash-alt mr-1"></i> La imagen actual será eliminada al guardar
                            </span>
                        </div>
                        
                        <p class="text-[8px] sm:text-[9px] text-gray-400 mt-0.5 sm:mt-1">
                            <i class="fas fa-info-circle"></i> 
                            Formatos: JPG, PNG, WEBP. Máx: 512KB
                        </p>
                    </div>
                </div>

                <!-- 🔥 TABS -->
                <div v-if="editando || productoGuardado" class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="hidden sm:block border-b border-gray-200 overflow-x-auto">
                        <nav class="flex justify-center -mb-px flex-nowrap min-w-max">
                            <button v-for="tab in tabs" :key="tab.id"
                                    @click="activeTab = tab.id" 
                                    class="px-4 py-2 text-xs font-medium transition whitespace-nowrap" 
                                    :class="activeTab === tab.id ? 'border-b-2 border-primary-600 text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                                    :style="{ borderColor: activeTab === tab.id ? `var(--color-primary-600)` : 'transparent' }">
                                <i :class="`fas ${tab.icon} mr-1 text-[10px]`"></i> 
                                {{ tab.fullLabel }}
                            </button>
                        </nav>
                    </div>

                    <div class="sm:hidden grid grid-cols-2 gap-1.5 p-1.5 bg-gray-50 border-b border-gray-200">
                        <button v-for="tab in tabs" :key="tab.id"
                                @click="activeTab = tab.id" 
                                class="px-2 py-2 rounded-lg text-[10px] font-medium transition flex items-center justify-center gap-1.5"
                                :class="activeTab === tab.id 
                                    ? 'bg-primary-600 text-white shadow-sm' 
                                    : 'bg-white text-gray-600 hover:bg-gray-100'"
                                :style="{
                                    backgroundColor: activeTab === tab.id ? `var(--color-primary-600)` : 'white',
                                    color: activeTab === tab.id ? 'white' : '#4B5563'
                                }">
                            <i :class="`fas ${tab.icon} text-[9px]`"></i> 
                            {{ tab.label }}
                        </button>
                    </div>

                    <div class="p-2 sm:p-4">
                        <div v-show="activeTab === 0">
                            <InventarioDetalleTab 
                                :producto-id="editando ? props.producto?.IdDetalleProducto : productoId"
                                :productos-inventario="productosInventario"
                                :detalles-iniciales="detallesList"
                                @update="detallesList = $event"
                            />
                        </div>
                        <div v-show="activeTab === 1">
                            <PrecioSucursalTab 
                                :producto-id="editando ? props.producto?.IdDetalleProducto : productoId"
                                :sucursales="sucursales"
                                :precios-iniciales="preciosSucursalList"
                                @update="preciosSucursalList = $event"
                            />
                        </div>
                        <div v-show="activeTab === 2">
                            <PrecioMayoristaTab 
                                :producto-id="editando ? props.producto?.IdDetalleProducto : productoId"
                                :sucursales="sucursales"
                                :identificadores="identificadores"
                                :precios-iniciales="preciosMayoristaList"
                                @update="preciosMayoristaList = $event"
                            />
                        </div>
                        <div v-show="activeTab === 3">
                            <ComboOpcionTab 
                                :producto-id="editando ? props.producto?.IdDetalleProducto : productoId"
                                @update="cargarOpciones"
                            />
                        </div>
                        <!-- TAB DIAS DISPONIBLES  -->
                        <div v-show="activeTab === 4">
                            <DisponibilidadTab 
                                :producto-id="editando ? props.producto?.IdDetalleProducto : productoId"
                                @update="(data) => console.log('Disponibilidad actualizada:', data)"
                            />
                        </div>
                    </div>
                </div>

                <div v-else class="bg-secondary-50 rounded-lg border border-secondary-200 p-3 sm:p-4 text-center">
                    <i class="fas fa-info-circle text-secondary-500 text-sm mb-2 block"></i>
                    <p class="text-[10px] sm:text-xs text-secondary-700">
                        Complete los datos del producto y presione "Guardar" para configurar precios y detalles.
                    </p>
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

        <!-- 🔥 MODAL DE APROBADOR NO CONFIGURADO -->
        <div v-if="modalAprobadorOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="modalAprobadorOpen = false">
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden animate-fade-in-up">
                <div class="bg-red-50 p-4 text-center">
                    <div class="w-12 h-12 mx-auto bg-red-100 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-user-shield text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">No hay aprobador configurado</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        No existe un responsable configurado para aprobar productos en este cliente.
                    </p>
                    <p class="text-xs text-red-500 mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Contacte al administrador para configurar un aprobador.
                    </p>
                </div>
                <div class="p-4">
                    <button 
                        @click="modalAprobadorOpen = false"
                        class="w-full px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes slide-down {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slide-down {
    animation: slide-down 0.2s ease-out;
}

.animate-fade-in-up {
    animation: fade-in-up 0.2s ease-out;
}

@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

input:focus {
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

* {
    scroll-behavior: smooth;
}
</style>