<script setup>
import { ref, computed, watch, inject, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    categorias: Array,
    categoriasPadre: Array
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
const editando = ref(false)
const editId = ref(null)
const formData = ref({
    nombre: '',
    id_padre: '',
    orden: 0,
    activo: 1,
    preview_url: null
})

const imagenFile = ref(null)
const eliminarImagen = ref(false)
const errors = ref({})
const imgInput = ref(null)

// ==================== AUTOCOMPLETADO PARA PADRE ====================
const padreBusqueda = ref('')
const mostrarListaPadres = ref(false)

// ==================== FUNCIONES ====================
const calcularSiguienteOrden = () => {
    if (editando.value) return
    
    const padreId = formData.value.id_padre
    
    let hermanos
    
    if (padreId) {
        hermanos = props.categorias.filter(c => c.id_padre === padreId)
    } else {
        hermanos = props.categorias.filter(c => !c.id_padre)
    }
    
    let maxOrden = 0
    hermanos.forEach(h => {
        if (h.orden > maxOrden) maxOrden = h.orden
    })
    
    formData.value.orden = maxOrden + 1
}

const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = {
        nombre: '',
        id_padre: '',
        orden: 0,
        activo: 1,
        preview_url: null
    }
    padreBusqueda.value = ''
    imagenFile.value = null
    eliminarImagen.value = false
    if (imgInput.value) imgInput.value.value = ''
    calcularSiguienteOrden()
}

const editar = (cat) => {
    editando.value = true
    editId.value = cat.id_categoria
    formData.value = {
        nombre: cat.nombre,
        id_padre: cat.id_padre || '',
        orden: cat.orden,
        activo: cat.activo,
        preview_url: cat.imagen_url
    }
    if (cat.id_padre) {
        const padre = categoriasParaSelect.value.find(c => c.id === cat.id_padre)
        if (padre) {
            padreBusqueda.value = padre.nombre
        }
    } else {
        padreBusqueda.value = ''
    }
    imagenFile.value = null
    eliminarImagen.value = false
}

watch(() => formData.value.id_padre, () => {
    if (!editando.value) {
        calcularSiguienteOrden()
    }
})

const convertirMayusculas = () => {
    formData.value.nombre = formData.value.nombre.toUpperCase()
}

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
    
    imagenFile.value = file
    
    const reader = new FileReader()
    reader.onload = (e) => {
        formData.value.preview_url = e.target.result
    }
    reader.readAsDataURL(file)
}

const marcarEliminarImagen = () => {
    eliminarImagen.value = true
    formData.value.preview_url = null
    if (imgInput.value) imgInput.value.value = ''
    imagenFile.value = null
}

const guardar = () => {
    if (!formData.value.nombre || formData.value.nombre.trim() === '') {
        errors.value = { nombre: 'El nombre es obligatorio' }
        return
    }
    
    const data = new FormData()
    data.append('nombre', formData.value.nombre)
    if (formData.value.id_padre) data.append('id_padre', formData.value.id_padre)
    data.append('orden', formData.value.orden)
    data.append('activo', formData.value.activo ? '1' : '0')
    
    if (imagenFile.value) {
        data.append('imagen', imagenFile.value)
    }
    
    if (editando.value && eliminarImagen.value) {
        data.append('eliminar_imagen', '1')
    }
    
    if (editando.value) {
        data.append('_method', 'PUT')
        router.post(`/gestion/inventario/categorias-producto/${editId.value}`, data, {
            preserveScroll: true,
            forceFormData: true,
            headers: {
                'Content-Type': 'multipart/form-data'
            },
            onSuccess: () => {
                toast?.success('Éxito', 'Categoría actualizada correctamente')
                resetForm()
            },
            onError: (err) => { 
                errors.value = err
                toast?.error('Error', Object.values(err).flat()[0] || 'Error al guardar')
            }
        })
    } else {
        router.post('/gestion/inventario/categorias-producto', data, {
            preserveScroll: true,
            forceFormData: true,
            headers: {
                'Content-Type': 'multipart/form-data'
            },
            onSuccess: () => {
                toast?.success('Éxito', 'Categoría creada correctamente')
                resetForm()
            },
            onError: (err) => { 
                errors.value = err
                toast?.error('Error', Object.values(err).flat()[0] || 'Error al guardar')
            }
        })
    }
}

const eliminar = (id, nombre) => {
    if (confirm(`¿Eliminar la categoría "${nombre}"?`)) {
        router.delete(`/gestion/inventario/categorias-producto/${id}`, {
            onSuccess: () => {
                toast?.success('Éxito', 'Categoría eliminada correctamente')
            }
        })
    }
}

const construirArbolParaSelect = (items, nivel = 0, parentId = null) => {
    let resultado = []
    const hijos = props.categorias.filter(c => c.id_padre === parentId)
    
    hijos.sort((a, b) => a.orden - b.orden).forEach(hijo => {
        const prefix = '—'.repeat(nivel) + (nivel > 0 ? ' ' : '')
        resultado.push({
            id: hijo.id_categoria,
            nombre: prefix + hijo.nombre,
            nivel: nivel,
            orden: hijo.orden
        })
        resultado = resultado.concat(construirArbolParaSelect(items, nivel + 1, hijo.id_categoria))
    })
    
    return resultado
}

const categoriasParaSelect = computed(() => {
    const raices = props.categorias.filter(c => !c.id_padre)
    return construirArbolParaSelect(raices)
})

const padresFiltrados = computed(() => {
    if (!padreBusqueda.value) return categoriasParaSelect.value
    
    const termino = padreBusqueda.value.toLowerCase()
    return categoriasParaSelect.value.filter(cat => 
        cat.nombre.toLowerCase().includes(termino)
    )
})

const categoriaPadreNombre = computed(() => {
    if (!formData.value.id_padre) return ''
    const cat = categoriasParaSelect.value.find(c => c.id == formData.value.id_padre)
    return cat?.nombre || ''
})

const seleccionarPadre = (cat) => {
    formData.value.id_padre = cat.id
    padreBusqueda.value = cat.nombre
    mostrarListaPadres.value = false
    if (!editando.value) {
        calcularSiguienteOrden()
    }
}

const limpiarPadre = () => {
    formData.value.id_padre = ''
    padreBusqueda.value = ''
    mostrarListaPadres.value = false
    if (!editando.value) {
        calcularSiguienteOrden()
    }
}

const handleClickOutside = (event) => {
    const container = document.querySelector('.padre-autocomplete')
    if (container && !container.contains(event.target)) {
        mostrarListaPadres.value = false
    }
}

const mostrarArbol = (items, nivel = 0, parentId = null) => {
    let resultado = []
    const hijos = props.categorias.filter(c => c.id_padre === parentId)
    
    hijos.sort((a, b) => a.orden - b.orden).forEach(hijo => {
        const prefix = '—'.repeat(nivel) + (nivel > 0 ? ' ' : '')
        resultado.push({
            ...hijo,
            nombre_con_indent: prefix + hijo.nombre,
            nivel: nivel
        })
        resultado = resultado.concat(mostrarArbol(items, nivel + 1, hijo.id_categoria))
    })
    
    return resultado
}

const categoriasArbol = computed(() => {
    return mostrarArbol(props.categorias, 0, null)
})

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})

resetForm()
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-tree text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Categorías de Productos</h1>
                        <p class="text-[10px] text-gray-500">Organización jerárquica compartida por todas las sucursales</p>
                    </div>
                </div>

                <!-- ==================== FORMULARIO COMPACTO ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        
                        <!-- Nombre -->
                        <div class="flex-1 min-w-[120px] max-w-[180px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Nombre *</label>
                            <input type="text" v-model="formData.nombre" @input="convertirMayusculas"
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm uppercase focus:ring-primary-500 focus:border-primary-500"
                                :class="{ 'border-red-500': errors.nombre }"
                                placeholder="EJ: SOLIDOS">
                            <p v-if="errors.nombre" class="text-[8px] text-red-500 mt-0.5">{{ errors.nombre }}</p>
                        </div>
                        
                        <!-- Padre con autocompletado -->
                        <div class="padre-autocomplete flex items-center gap-1 min-w-[140px] max-w-[200px]">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Padre:</label>
                            <div class="relative w-full">
                                <input 
                                    type="text"
                                    v-model="padreBusqueda"
                                    @focus="mostrarListaPadres = true"
                                    @input="mostrarListaPadres = true"
                                    class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm pr-6 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    placeholder="Buscar..."
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="formData.id_padre"
                                    @click="limpiarPadre"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    type="button"
                                >
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                                
                                <div v-if="mostrarListaPadres && padresFiltrados.length > 0" 
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto min-w-[180px]">
                                    <div 
                                        v-for="cat in padresFiltrados" 
                                        :key="cat.id"
                                        @click="seleccionarPadre(cat)"
                                        class="px-2 py-1.5 cursor-pointer hover:bg-primary-50 text-xs flex justify-between items-center border-b border-gray-100 last:border-0"
                                        :class="formData.id_padre == cat.id ? 'bg-primary-50' : ''"
                                    >
                                        <span class="truncate" :style="{ paddingLeft: (cat.nivel * 12) + 'px' }">
                                            <span v-if="cat.nivel > 0" class="text-gray-400">— </span>
                                            {{ cat.nombre }}
                                        </span>
                                        <span v-if="formData.id_padre == cat.id" class="text-primary-600 flex-shrink-0 ml-2">
                                            <i class="fas fa-check-circle text-[10px]"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div v-else-if="mostrarListaPadres && padresFiltrados.length === 0 && padreBusqueda" 
                                    class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-500 text-[10px]">
                                    <i class="fas fa-search mr-1"></i> No se encontraron categorías
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estado -->
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Estado:</label>
                            <select v-model.number="formData.activo" 
                                class="w-20 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500">
                                <option :value="1">Activo</option>
                                <option :value="0">Inactivo</option>
                            </select>
                        </div>

                        <!-- Orden -->
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Orden:</label>
                            <input type="number" v-model.number="formData.orden" readonly
                                class="w-12 border border-gray-300 rounded-md px-1 py-1 text-sm bg-gray-100 text-gray-600 text-center">
                        </div>

                        <!-- Imagen -->
                        <div class="flex items-center gap-1 flex-1 min-w-[120px] max-w-[180px]">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Imagen:</label>
                            <input type="file" ref="imgInput" @change="onImageChange" 
                                accept="image/jpeg,image/png,image/jpg,image/webp"
                                class="flex-1 border border-gray-300 rounded-md px-2 py-1 text-[10px] file:mr-1 file:py-0.5 file:px-2 file:rounded file:border-0 file:text-[9px] file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                            
                            <div v-if="formData.preview_url" class="relative w-8 h-8 rounded-lg overflow-hidden bg-gray-100 border flex-shrink-0 border-gray-200">
                                <img :src="formData.preview_url" class="w-full h-full object-cover">
                                <button @click="marcarEliminarImagen"
                                    class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-red-500 text-white rounded-full text-[6px] flex items-center justify-center hover:bg-red-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <span v-if="formData.id_padre && categoriaPadreNombre" class="text-[10px] text-primary-600 font-medium ml-1 whitespace-nowrap">
                            <i class="fas fa-check-circle"></i> {{ categoriaPadreNombre }}
                        </span>

                        <!-- Botones -->
                        <div class="flex gap-1.5 ml-auto">
                            <button @click="guardar" 
                                class="px-3 py-1.5 text-white rounded-md text-xs font-medium transition flex items-center gap-1"
                                :style="{ backgroundColor: `var(--color-primary-600)` }">
                                <i class="fas" :class="editando ? 'fa-pencil-alt' : 'fa-plus'"></i>
                                {{ editando ? 'Actualizar' : 'Guardar' }}
                            </button>
                            <button v-if="editando" @click="resetForm" 
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </div>
                    <div class="mt-1 text-[8px] text-gray-400">
                        <i class="fas fa-info-circle"></i> El orden se calcula automáticamente según el padre seleccionado
                    </div>
                </div>

                <!-- ==================== TABLA CON STICKY HEADER ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- 🔥 CONTENEDOR CON SCROLL -->
                    <div class="overflow-x-auto" style="max-height: 65vh; overflow-y: auto;">
                        
                        <!-- 🔥 VISTA TABLET Y ESCRITORIO - con STICKY HEADER -->
                        <table v-if="!isMobile" class="min-w-full divide-y divide-gray-200">
                            <!-- 🔥 THEAD STICKY - SIEMPRE VISIBLE AL HACER SCROLL -->
                            <thead class="bg-primary-50 sticky top-0 z-10 shadow-sm" style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">Imagen</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">ID</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">Categoría</th>
                                    <th class="px-3 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">Padre</th>
                                    <th class="px-3 py-2 text-center text-[10px] font-medium text-primary-700 uppercase w-16">Orden</th>
                                    <th class="px-3 py-2 text-center text-[10px] font-medium text-primary-700 uppercase w-24">Estado</th>
                                    <th class="px-3 py-2 text-center text-[10px] font-medium text-primary-700 uppercase w-16">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="cat in categoriasArbol" :key="cat.id_categoria" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2">
                                        <div class="w-8 h-8 rounded-lg overflow-hidden bg-gray-100">
                                            <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover">
                                            <div v-else class="w-full h-full flex items-center justify-center">
                                                <i class="fas fa-tree text-gray-400 text-sm"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ cat.id_categoria }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-700">
                                        <span :style="{ marginLeft: (cat.nivel * 14) + 'px' }">{{ cat.nombre_con_indent }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ cat.padre?.nombre || '-' }}</td>
                                    <td class="px-3 py-2 text-center text-xs text-gray-500">{{ cat.orden }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[8px] rounded-full"
                                            :class="cat.activo ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                            {{ cat.activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button @click="editar(cat)" class="text-primary-600 hover:text-primary-800 transition text-xs mr-2" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="eliminar(cat.id_categoria, cat.nombre)" class="text-red-600 hover:text-red-800 transition text-xs" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="categoriasArbol.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-tree text-2xl mb-1 block"></i>
                                        No hay categorías registradas
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- VISTA MÓVIL (tarjetas) - sin sticky -->
                        <div v-else class="p-2 space-y-2">
                            <div v-for="cat in categoriasArbol" :key="cat.id_categoria" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex gap-2.5">
                                    <div class="w-10 h-10 flex-shrink-0 rounded-lg overflow-hidden bg-gray-200">
                                        <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover">
                                        <div v-else class="w-full h-full flex items-center justify-center">
                                            <i class="fas fa-tree text-gray-400 text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start gap-1">
                                            <div class="min-w-0">
                                                <p class="text-xs font-medium text-gray-800 truncate" 
                                                   :style="{ marginLeft: (cat.nivel * 10) + 'px' }">
                                                    {{ cat.nombre }}
                                                </p>
                                                <p class="text-[9px] text-gray-400">ID: {{ cat.id_categoria }}</p>
                                            </div>
                                            <span class="px-1.5 py-0.5 text-[8px] rounded-full whitespace-nowrap"
                                                :class="cat.activo ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                                {{ cat.activo ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap items-center justify-between gap-1 mt-1.5 pt-1.5 border-t border-gray-200">
                                            <span class="text-[9px] text-gray-400">
                                                Padre: {{ cat.padre?.nombre || 'RAÍZ' }}
                                            </span>
                                            <span class="text-[9px] text-gray-400">Orden: {{ cat.orden }}</span>
                                            <div class="flex gap-1">
                                                <button @click="editar(cat)" 
                                                    class="px-2 py-0.5 text-[9px] rounded bg-primary-50 text-primary-600 hover:bg-primary-100 transition">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button @click="eliminar(cat.id_categoria, cat.nombre)" 
                                                    class="px-2 py-0.5 text-[9px] rounded bg-red-50 text-red-600 hover:bg-red-100 transition">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="categoriasArbol.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-tree text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay categorías registradas</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="mt-3 text-[8px] text-gray-400 text-center">
                    <i class="fas fa-info-circle"></i> Las categorías se ordenan automáticamente según el padre seleccionado
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

/* Autocomplete */
.padre-autocomplete {
    display: inline-flex;
    align-items: center;
    flex-wrap: nowrap;
    gap: 4px;
}

.padre-autocomplete .relative {
    flex: 1;
    min-width: 100px;
}

/* 🔥 STICKY HEADER - Fijo al hacer scroll */
.sticky {
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
}

/* Sombra para separar visualmente */
.shadow-sm {
    box-shadow: 0 2px 4px rgba(0,0,0,0.08) !important;
}

/* Contenedor con scroll */
.overflow-x-auto {
    overflow-y: auto !important;
}
</style>