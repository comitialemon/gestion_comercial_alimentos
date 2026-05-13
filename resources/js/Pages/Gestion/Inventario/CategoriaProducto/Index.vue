<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

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
    imagen_base64: null,
    preview_url: null
})
const errors = ref({})
const imgInput = ref(null)

const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = {
        nombre: '',
        id_padre: '',
        orden: 0,
        activo: 1,
        imagen_base64: null,
        preview_url: null
    }
    if (imgInput.value) imgInput.value.value = ''
}

const editar = (cat) => {
    editando.value = true
    editId.value = cat.id_categoria
    formData.value = {
        nombre: cat.nombre,
        id_padre: cat.id_padre || '',
        orden: cat.orden,
        activo: cat.activo,
        imagen_base64: null,
        preview_url: cat.imagen_url
    }
}

const convertirMayusculas = () => {
    formData.value.nombre = formData.value.nombre.toUpperCase()
}

const onImageChange = (event) => {
    const file = event.target.files[0]
    if (!file) return
    
    const reader = new FileReader()
    reader.onload = (e) => {
        formData.value.imagen_base64 = e.target.result
        formData.value.preview_url = e.target.result
    }
    reader.readAsDataURL(file)
}

const guardar = () => {
    if (editando.value) {
        router.put(`/gestion/inventario/categorias-producto/${editId.value}`, formData.value, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
            onError: (err) => { errors.value = err }
        })
    } else {
        router.post('/gestion/inventario/categorias-producto', formData.value, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
            onError: (err) => { errors.value = err }
        })
    }
}

const eliminar = (id, nombre) => {
    if (confirm(`¿Eliminar la categoría "${nombre}"?`)) {
        router.delete(`/gestion/inventario/categorias-producto/${id}`)
    }
}

const mostrarArbol = (items, nivel = 0) => {
    return items.flatMap(item => {
        const hijos = props.categorias.filter(c => c.id_padre === item.id_categoria)
        const prefix = '—'.repeat(nivel) + (nivel > 0 ? ' ' : '')
        const resultado = [{
            ...item,
            nombre_con_indent: prefix + item.nombre
        }]
        return resultado.concat(mostrarArbol(hijos, nivel + 1))
    })
}

const categoriasArbol = computed(() => {
    const raices = props.categorias.filter(c => !c.id_padre)
    return mostrarArbol(raices)
})

resetForm()
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-100 rounded-2xl mb-3">
                        <i class="fas fa-tree text-xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Categorías de Productos</h1>
                    <p class="text-xs text-gray-500">Menú táctil con imágenes - Organización jerárquica</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nombre *</label>
                            <input type="text" v-model="formData.nombre" @input="convertirMayusculas"
                                class="w-full border rounded-lg px-3 py-2 text-sm uppercase"
                                :class="{ 'border-red-500': errors.nombre }"
                                placeholder="Ej: SOLIDOS, SALTEÑAS, BEBIDAS">
                            <p v-if="errors.nombre" class="text-xs text-red-500 mt-1">{{ errors.nombre }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Categoría Padre</label>
                            <select v-model="formData.id_padre" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">[NINGUNA - ES RAÍZ]</option>
                                <option v-for="cat in categoriasArbol" :key="cat.id_categoria" :value="cat.id_categoria">
                                    {{ cat.nombre_con_indent }}
                                </option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Orden</label>
                            <input type="number" v-model.number="formData.orden" min="0"
                                class="w-full border rounded-lg px-3 py-2 text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Activo</label>
                            <select v-model.number="formData.activo" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option :value="1">✓ Activo</option>
                                <option :value="0">✗ Inactivo</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2 lg:col-span-3">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Imagen</label>
                            <div class="flex gap-3 items-center">
                                <input type="file" ref="imgInput" @change="onImageChange" accept="image/*"
                                    class="flex-1 border rounded-lg px-3 py-2 text-sm">
                                <div v-if="formData.preview_url" class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100">
                                    <img :src="formData.preview_url" class="w-full h-full object-cover">
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-end gap-2">
                            <button @click="guardar" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">
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

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Imagen</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Padre</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Orden</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
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
                                        <span class="font-mono text-xs text-gray-400 mr-2">{{ cat.nivel ? '├─' : '' }}</span>
                                        {{ cat.nombre }}
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
                                        <button @click="editar(cat)" class="text-indigo-600 hover:text-indigo-900 mr-3">
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
                    <i class="fas fa-info-circle"></i> Las categorías raíz (sin padre) serán los botones principales del menú táctil.
                </div>
            </div>
        </div>
    </div>
</template>