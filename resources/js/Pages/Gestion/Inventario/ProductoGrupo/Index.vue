<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    items: Object,
    lineas: Array
})

const editando = ref(false)
const editId = ref(null)
const formData = ref({ IdLinea: '', Grupo: '' })
const errors = ref({})

const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = { IdLinea: '', Grupo: '' }
}

const editar = (item) => {
    editando.value = true
    editId.value = item.IdProductoGrupo
    formData.value = {
        IdLinea: item.IdLinea,
        Grupo: item.Grupo
    }
}

const guardar = () => {
    if (editando.value) {
        router.put(`/gestion/inventario/producto-grupo/${editId.value}`, formData.value, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
            onError: (err) => { errors.value = err }
        })
    } else {
        router.post('/gestion/inventario/producto-grupo', formData.value, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
            onError: (err) => { errors.value = err }
        })
    }
}

const eliminar = (id, nombre) => {
    if (confirm(`¿Eliminar el grupo "${nombre}"?`)) {
        router.delete(`/gestion/inventario/producto-grupo/${id}`)
    }
}

const getLineaNombre = (id) => {
    const linea = props.lineas?.find(l => l.id === id)
    return linea?.nombre || '-'
}

resetForm()
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-100 rounded-2xl mb-3">
                        <i class="fas fa-layer-group text-xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Grupos de Producto</h1>
                    <p class="text-xs text-gray-500">Administra los grupos de productos por línea</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <select v-model="formData.IdLinea" class="w-full border rounded-lg px-3 py-2 text-sm" :class="{ 'border-red-500': errors.IdLinea }">
                                <option value="">Seleccione una línea</option>
                                <option v-for="linea in lineas" :key="linea.id" :value="linea.id">{{ linea.nombre }}</option>
                            </select>
                            <p v-if="errors.IdLinea" class="text-xs text-red-500 mt-1">{{ errors.IdLinea }}</p>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" v-model="formData.Grupo" placeholder="Nombre del grupo" class="flex-1 border rounded-lg px-3 py-2 text-sm" :class="{ 'border-red-500': errors.Grupo }" />
                            <button @click="guardar" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">
                                <i class="fas" :class="editando ? 'fa-pencil-alt' : 'fa-plus'"></i>
                                {{ editando ? 'Actualizar' : 'Guardar' }}
                            </button>
                            <button v-if="editando" @click="resetForm" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Línea</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grupo</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in items.data" :key="item.IdProductoGrupo" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.IdProductoGrupo }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ getLineaNombre(item.IdLinea) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ item.Grupo }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="editar(item)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fas fa-edit"></i></button>
                                    <button @click="eliminar(item.IdProductoGrupo, item.Grupo)" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr v-if="items.data.length === 0"><td colspan="4" class="px-6 py-12 text-center text-gray-500">No hay grupos registrados</td></tr>
                        </tbody>
                    </table>

                    <div v-if="items.links && items.links.length > 1" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">Mostrando {{ items.from || 0 }} a {{ items.to || 0 }} de {{ items.total || 0 }}</div>
                            <div class="flex gap-1">
                                <Link v-for="link in items.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border text-sm" :class="{ 'bg-indigo-600 text-white border-indigo-600': link.active, 'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 'opacity-50 cursor-not-allowed': !link.url }" v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>