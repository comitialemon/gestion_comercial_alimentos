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
const calculandoOrden = ref(false)

// Responsive
const isMobile = ref(false)
const isTablet = ref(false)
const filtrosAbiertos = ref(false)

// ==================== DETECTAR RESPONSIVE ====================
const handleResize = () => {
    isMobile.value = window.innerWidth < 640
    isTablet.value = window.innerWidth >= 640 && window.innerWidth < 1024
}

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

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

resetForm()
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-6">
            <div class="max-w-6xl mx-auto">
                <!-- Header Responsive -->
                <div class="text-center mb-3 sm:mb-6">
                    <div class="inline-flex items-center justify-center w-10 h-10 sm:w-14 sm:h-14 rounded-2xl mb-2 sm:mb-3"
                         :style="{ backgroundColor: `var(--color-primary-100)` }">
                        <i class="fas fa-tree text-primary-600 text-base sm:text-xl"
                           :style="{ color: `var(--color-primary-600)` }"></i>
                    </div>
                    <h1 class="text-base sm:text-xl font-bold text-gray-900">Categorías de Productos</h1>
                    <p class="text-[10px] sm:text-xs text-gray-500 px-2">
                        Menú táctil con imágenes - Organización jerárquica (compartido por todas las sucursales)
                    </p>
                </div>

                <!-- Formulario Responsive -->
                <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-4 sm:mb-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
                        <!-- Nombre -->
                        <div>
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Nombre *</label>
                            <input type="text" v-model="formData.nombre" @input="convertirMayusculas"
                                class="w-full border rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-[11px] sm:text-sm uppercase focus:ring-2 focus:outline-none"
                                :class="{ 'border-red-500': errors.nombre }"
                                :style="{ borderColor: errors.nombre ? '#ef4444' : `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                                placeholder="Ej: SOLIDOS, SALTEÑAS">
                            <p v-if="errors.nombre" class="text-[10px] sm:text-xs text-red-500 mt-0.5">{{ errors.nombre }}</p>
                        </div>
                        
                        <!-- Categoría Padre -->
                        <div>
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Categoría Padre</label>
                            <select v-model="formData.id_padre" 
                                    class="w-full border rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-[11px] sm:text-sm focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }">
                                <option value="">[NINGUNA - ES RAÍZ]</option>
                                <option v-for="cat in categoriasParaSelect" :key="cat.id" :value="cat.id">
                                    {{ cat.nombre }}
                                </option>
                            </select>
                        </div>
                        
                        <!-- Orden -->
                        <div>
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Orden (automático)</label>
                            <input type="number" v-model.number="formData.orden" 
                                readonly
                                class="w-full border rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-[11px] sm:text-sm bg-gray-100 text-gray-600 focus:ring-2 focus:outline-none"
                                :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }">
                            <p class="text-[8px] sm:text-[10px] text-gray-400 mt-0.5">* Calculado automáticamente</p>
                        </div>
                        
                        <!-- Activo -->
                        <div>
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Activo</label>
                            <select v-model.number="formData.activo" 
                                    class="w-full border rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-[11px] sm:text-sm focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }">
                                <option :value="1">✓ Activo</option>
                                <option :value="0">✗ Inactivo</option>
                            </select>
                        </div>
                        
                        <!-- Imagen -->
                        <div class="sm:col-span-2 lg:col-span-4">
                            <label class="block text-[10px] sm:text-xs font-medium text-gray-700 mb-0.5 sm:mb-1">Imagen</label>
                            <div class="flex flex-wrap gap-2 sm:gap-3 items-center">
                                <input type="file" ref="imgInput" @change="onImageChange" 
                                    accept="image/jpeg,image/png,image/jpg,image/webp"
                                    class="flex-1 border rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-sm file:mr-2 file:py-0.5 sm:file:py-1 file:px-2 sm:file:px-3 file:rounded-md file:border-0 file:text-[10px] sm:file:text-xs file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 focus:ring-2 focus:outline-none"
                                    :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }">
                                
                                <!-- Previsualización -->
                                <div v-if="formData.preview_url" class="relative w-10 h-10 sm:w-12 sm:h-12 rounded-lg overflow-hidden bg-gray-100 border flex-shrink-0"
                                     :style="{ borderColor: `var(--color-primary-200)` }">
                                    <img :src="formData.preview_url" class="w-full h-full object-cover">
                                    <button 
                                        @click="marcarEliminarImagen"
                                        class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white rounded-full text-[8px] flex items-center justify-center hover:bg-red-600"
                                        title="Eliminar imagen">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <!-- Indicadores -->
                                <span v-if="imagenFile" class="text-[8px] sm:text-[10px] text-green-600">
                                    <i class="fas fa-check-circle"></i> Nueva imagen
                                </span>
                                <span v-if="eliminarImagen" class="text-[8px] sm:text-[10px] text-red-600">
                                    <i class="fas fa-trash"></i> Será eliminada
                                </span>
                            </div>
                            <p class="text-[8px] sm:text-[9px] text-gray-400 mt-0.5 sm:mt-1">
                                <i class="fas fa-info-circle"></i> 
                                Formatos: JPG, PNG, WEBP. Máx: 512KB
                            </p>
                        </div>
                        
                        <!-- Botones -->
                        <div class="flex flex-wrap gap-2 items-end sm:col-span-2 lg:col-span-4">
                            <button @click="guardar" 
                                class="flex-1 sm:flex-none px-3 sm:px-4 py-1.5 sm:py-2 text-white rounded-lg text-[11px] sm:text-sm font-medium transition"
                                :style="{ backgroundColor: `var(--color-primary-600)` }">
                                <i class="fas" :class="editando ? 'fa-pencil-alt' : 'fa-plus'"></i>
                                {{ editando ? 'Actualizar' : 'Guardar' }}
                            </button>
                            <button v-if="editando" @click="resetForm" 
                                class="flex-1 sm:flex-none px-3 sm:px-4 py-1.5 sm:py-2 bg-gray-200 text-gray-700 rounded-lg text-[11px] sm:text-sm hover:bg-gray-300 transition">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de categorías Responsive -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <!-- Desktop: Tabla completa -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50" :style="{ backgroundColor: `var(--color-primary-50)` }">
                                <tr>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-[10px] sm:text-xs font-medium" :style="{ color: `var(--color-primary-700)` }">Imagen</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-[10px] sm:text-xs font-medium" :style="{ color: `var(--color-primary-700)` }">ID</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-[10px] sm:text-xs font-medium" :style="{ color: `var(--color-primary-700)` }">Categoría</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-[10px] sm:text-xs font-medium" :style="{ color: `var(--color-primary-700)` }">Padre</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-[10px] sm:text-xs font-medium" :style="{ color: `var(--color-primary-700)` }">Orden</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-[10px] sm:text-xs font-medium" :style="{ color: `var(--color-primary-700)` }">Estado</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-[10px] sm:text-xs font-medium" :style="{ color: `var(--color-primary-700)` }">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="cat in categoriasArbol" :key="cat.id_categoria" class="hover:bg-gray-50 transition">
                                    <td class="px-3 sm:px-4 py-2 sm:py-3">
                                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gray-100 overflow-hidden">
                                            <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover">
                                            <i v-else class="fas fa-image text-gray-300 text-base sm:text-2xl flex items-center justify-center h-full"></i>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 text-[10px] sm:text-sm text-gray-500">{{ cat.id_categoria }}</td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 text-[10px] sm:text-sm text-gray-700">
                                        <span :style="{ marginLeft: (cat.nivel * 16) + 'px' }">{{ cat.nombre_con_indent }}</span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 text-[10px] sm:text-sm text-gray-500">{{ cat.padre?.nombre || '-' }}</td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 text-center text-[10px] sm:text-sm text-gray-500">{{ cat.orden }}</td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 text-center">
                                        <span class="px-1.5 sm:px-2 py-0.5 sm:py-1 text-[8px] sm:text-xs rounded-full"
                                            :class="cat.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                            {{ cat.activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 text-right text-[10px] sm:text-sm font-medium">
                                        <button @click="editar(cat)" class="transition mr-2 sm:mr-3" :style="{ color: `var(--color-primary-600)` }">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="eliminar(cat.id_categoria, cat.nombre)" class="text-red-600 hover:text-red-800 transition">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="categoriasArbol.length === 0">
                                    <td colspan="7" class="px-3 sm:px-4 py-8 text-center text-gray-400 text-[10px] sm:text-sm">
                                        <i class="fas fa-tree text-2xl sm:text-3xl mb-2 block text-gray-300"></i>
                                        No hay categorías registradas
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile: Tarjetas -->
                    <div class="md:hidden divide-y divide-gray-100">
                        <div v-for="cat in categoriasArbol" :key="cat.id_categoria" class="p-3 hover:bg-gray-50 transition">
                            <div class="flex items-start gap-3">
                                <!-- Imagen -->
                                <div class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0">
                                    <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover">
                                    <i v-else class="fas fa-image text-gray-300 text-xl flex items-center justify-center h-full"></i>
                                </div>
                                
                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="text-sm font-medium text-gray-800 truncate" 
                                                 :style="{ marginLeft: (cat.nivel * 12) + 'px' }">
                                                {{ cat.nombre }}
                                            </div>
                                            <div class="text-[10px] text-gray-400 mt-0.5">
                                                ID: {{ cat.id_categoria }} | Padre: {{ cat.padre?.nombre || '-' }}
                                            </div>
                                        </div>
                                        <span class="px-1.5 py-0.5 text-[8px] rounded-full flex-shrink-0"
                                            :class="cat.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                            {{ cat.activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between mt-2 pt-2 border-t" :style="{ borderColor: `var(--color-primary-100)` }">
                                        <span class="text-[10px] text-gray-400">Orden: {{ cat.orden }}</span>
                                        <div class="flex gap-2">
                                            <button @click="editar(cat)" class="px-2 py-1 rounded-lg text-[10px] transition"
                                                    :style="{ backgroundColor: `var(--color-primary-50)`, color: `var(--color-primary-600)` }">
                                                <i class="fas fa-edit mr-1"></i> Editar
                                            </button>
                                            <button @click="eliminar(cat.id_categoria, cat.nombre)" 
                                                    class="px-2 py-1 rounded-lg text-[10px] transition bg-red-50 text-red-600">
                                                <i class="fas fa-trash mr-1"></i> Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="categoriasArbol.length === 0" class="p-6 text-center text-gray-400 text-sm">
                            <i class="fas fa-tree text-2xl mb-2 block text-gray-300"></i>
                            No hay categorías registradas
                        </div>
                    </div>
                </div>

                <div class="mt-3 sm:mt-4 text-[8px] sm:text-xs text-gray-400 text-center">
                    <i class="fas fa-info-circle"></i> Las categorías se ordenan automáticamente según el padre seleccionado.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Transiciones suaves */
input:focus, select:focus {
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}

/* Scroll suave */
* {
    scroll-behavior: smooth;
}
</style>