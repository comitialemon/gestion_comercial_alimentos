<script setup>
import { ref, computed, watch, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    categorias: Array,
    categoriasPadre: Array
})

const editando = ref(false)
const editId = ref(null)
const formData = ref({
    nombre: '',
    id_padre: '',
    orden: 0,
    activo: 1,
    // 🔥 CAMBIADO: ya no usamos imagen_base64
    preview_url: null
})

// 🔥 NUEVO: almacenar el archivo de imagen por separado
const imagenFile = ref(null)
const eliminarImagen = ref(false)

const errors = ref({})
const imgInput = ref(null)
const calculandoOrden = ref(false)

// 🔥 Calcular el siguiente orden según el padre seleccionado
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

// Resetear formulario
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

// Editar
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
    imagenFile.value = null  // Resetear archivo nuevo
    eliminarImagen.value = false
}

// 🔥 Cuando cambia el padre, recalcular el orden
watch(() => formData.value.id_padre, () => {
    if (!editando.value) {
        calcularSiguienteOrden()
    }
})

const convertirMayusculas = () => {
    formData.value.nombre = formData.value.nombre.toUpperCase()
}

// 🔥 MODIFICADO: Guardar el archivo, NO convertir a base64
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
    
    imagenFile.value = file
    
    // Previsualización (solo para mostrar, no se envía al servidor)
    const reader = new FileReader()
    reader.onload = (e) => {
        formData.value.preview_url = e.target.result
    }
    reader.readAsDataURL(file)
}

// 🔥 NUEVO: Marcar para eliminar la imagen actual
const marcarEliminarImagen = () => {
    eliminarImagen.value = true
    formData.value.preview_url = null
    if (imgInput.value) imgInput.value.value = ''
    imagenFile.value = null
}

// 🔥 MODIFICADO: Guardar usando FormData
const guardar = () => {
    // Validar nombre
    if (!formData.value.nombre || formData.value.nombre.trim() === '') {
        errors.value = { nombre: 'El nombre es obligatorio' }
        return
    }
    
    const data = new FormData()
    data.append('nombre', formData.value.nombre)
    if (formData.value.id_padre) data.append('id_padre', formData.value.id_padre)
    data.append('orden', formData.value.orden)
    data.append('activo', formData.value.activo ? '1' : '0')
    
    // 🔥 IMPORTANTE: Agregar la imagen si hay una nueva
    if (imagenFile.value) {
        data.append('imagen', imagenFile.value)
    }
    
    // 🔥 Para edición: indicar si se debe eliminar la imagen actual
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

// Construir árbol para el selector de padre (con sangría)
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

// Mostrar árbol en la tabla
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

// Inicializar orden al montar
resetForm()
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-primary-100 rounded-2xl mb-3">
                        <i class="fas fa-tree text-xl text-primary-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Categorías de Productos</h1>
                    <p class="text-xs text-gray-500">Menú táctil con imágenes - Organización jerárquica (compartido por todas las sucursales)</p>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                        <!-- Nombre -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre *</label>
                            <input type="text" v-model="formData.nombre" @input="convertirMayusculas"
                                class="w-full border rounded-lg px-3 py-2 text-sm uppercase"
                                :class="{ 'border-red-500': errors.nombre }"
                                placeholder="Ej: SOLIDOS, SALTEÑAS, BEBIDAS">
                            <p v-if="errors.nombre" class="text-xs text-red-500 mt-1">{{ errors.nombre }}</p>
                        </div>
                        
                        <!-- Categoría Padre (selector con árbol) -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Categoría Padre</label>
                            <select v-model="formData.id_padre" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">[NINGUNA - ES RAÍZ]</option>
                                <option v-for="cat in categoriasParaSelect" :key="cat.id" :value="cat.id">
                                    {{ cat.nombre }}
                                </option>
                            </select>
                        </div>
                        
                        <!-- Orden (solo lectura, calculado automáticamente) -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Orden (automático)</label>
                            <input type="number" v-model.number="formData.orden" 
                                readonly
                                class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-100 text-gray-600">
                            <p class="text-[10px] text-gray-400 mt-0.5">* Se calcula automáticamente según el padre</p>
                        </div>
                        
                        <!-- Activo -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Activo</label>
                            <select v-model.number="formData.activo" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option :value="1">✓ Activo</option>
                                <option :value="0">✗ Inactivo</option>
                            </select>
                        </div>
                        
                        <!-- 🔥 IMAGEN MODIFICADA - Ahora con FormData -->
                        <div class="md:col-span-2 lg:col-span-4">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Imagen</label>
                            <div class="flex flex-wrap gap-3 items-center">
                                <input type="file" ref="imgInput" @change="onImageChange" accept="image/jpeg,image/png,image/jpg,image/webp"
                                    class="flex-1 border rounded-lg px-3 py-2 text-sm file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                                
                                <!-- Previsualización -->
                                <div v-if="formData.preview_url" class="relative w-12 h-12 rounded-lg overflow-hidden bg-gray-100 border">
                                    <img :src="formData.preview_url" class="w-full h-full object-cover">
                                    <button 
                                        @click="marcarEliminarImagen"
                                        class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white rounded-full text-[8px] flex items-center justify-center hover:bg-red-600"
                                        title="Eliminar imagen">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <!-- Indicador de imagen nueva -->
                                <span v-if="imagenFile" class="text-[10px] text-green-600">
                                    <i class="fas fa-check-circle"></i> Nueva imagen seleccionada
                                </span>
                                <span v-if="eliminarImagen" class="text-[10px] text-red-600">
                                    <i class="fas fa-trash"></i> La imagen será eliminada
                                </span>
                            </div>
                            <p class="text-[9px] text-gray-400 mt-1">
                                <i class="fas fa-info-circle"></i> 
                                Formatos permitidos: JPG, PNG, WEBP. Tamaño máximo: 512KB
                            </p>
                        </div>
                        
                        <!-- Botones -->
                        <div class="flex items-end gap-2">
                            <button @click="guardar" 
                                class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700">
                                <i class="fas" :class="editando ? 'fa-pencil-alt' : 'fa-plus'"></i>
                                {{ editando ? 'Actualizar' : 'Guardar' }}
                            </button>
                            <button v-if="editando" @click="resetForm" 
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de categorías -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-700 uppercase">Imagen</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-700 uppercase">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-700 uppercase">Categoría</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-primary-700 uppercase">Padre</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-primary-700 uppercase">Orden</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-primary-700 uppercase">Estado</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="cat in categoriasArbol" :key="cat.id_categoria" class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="w-10 h-10 rounded-lg bg-gray-100 overflow-hidden">
                                            <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover">
                                            <i v-else class="fas fa-image text-gray-300 text-2xl flex items-center justify-center h-full"></i>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ cat.id_categoria }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <span :style="{ marginLeft: (cat.nivel * 20) + 'px' }">{{ cat.nombre }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ cat.padre?.nombre || '-' }}</td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-500">{{ cat.orden }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full"
                                            :class="cat.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                            {{ cat.activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium">
                                        <button @click="editar(cat)" class="text-primary-600 hover:text-primary-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="eliminar(cat.id_categoria, cat.nombre)" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 text-xs text-gray-400 text-center">
                    <i class="fas fa-info-circle"></i> Las categorías se ordenan automáticamente según el padre seleccionado.
                    El orden se calcula como el siguiente número disponible.
                </div>
            </div>
        </div>
    </div>
</template>