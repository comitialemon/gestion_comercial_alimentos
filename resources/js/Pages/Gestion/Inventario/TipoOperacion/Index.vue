<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    items: Object
})

const editando = ref(false)
const editId = ref(null)
const formData = ref({ Detalle: '', Concepto: '', ActivoInactivo: 1 })
const errors = ref({})

const resetForm = () => {
    editando.value = false
    editId.value = null
    formData.value = { Detalle: '', Concepto: '', ActivoInactivo: 1 }
}

const editar = (item) => {
    editando.value = true
    editId.value = item.IdTipoOperacion
    formData.value = {
        Detalle: item.Detalle,
        Concepto: item.Concepto,
        ActivoInactivo: item.ActivoInactivo
    }
}

const guardar = () => {
    if (editando.value) {
        router.put(`/gestion/inventario/tipo-operacion/${editId.value}`, formData.value, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
            onError: (err) => { errors.value = err }
        })
    } else {
        router.post('/gestion/inventario/tipo-operacion', formData.value, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
            onError: (err) => { errors.value = err }
        })
    }
}

const eliminar = (id, nombre) => {
    if (confirm(`¿Eliminar el tipo "${nombre}"?`)) {
        router.delete(`/gestion/inventario/tipo-operacion/${id}`)
    }
}

resetForm()
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-100 rounded-2xl mb-3">
                        <i class="fas fa-exchange-alt text-xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Tipos de Operación</h1>
                    <p class="text-xs text-gray-500">Administra los tipos de operación de inventario</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <input type="text" v-model="formData.Detalle" placeholder="Detalle (Ej: Compra, Venta, Ajuste)" class="w-full border rounded-lg px-3 py-2 text-sm" :class="{ 'border-red-500': errors.Detalle }" />
                            <p v-if="errors.Detalle" class="text-xs text-red-500 mt-1">{{ errors.Detalle }}</p>
                        </div>
                        <div>
                            <input type="text" v-model="formData.Concepto" placeholder="Concepto" class="w-full border rounded-lg px-3 py-2 text-sm" :class="{ 'border-red-500': errors.Concepto }" />
                            <p v-if="errors.Concepto" class="text-xs text-red-500 mt-1">{{ errors.Concepto }}</p>
                        </div>
                        <div class="flex gap-2">
                            <select v-model.number="formData.ActivoInactivo" class="border rounded-lg px-3 py-2 text-sm">
                                <option :value="1">Activo</option>
                                <option :value="0">Inactivo</option>
                            </select>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Detalle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Concepto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in items.data" :key="item.IdTipoOperacion" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.IdTipoOperacion }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ item.Detalle }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.Concepto }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full" :class="item.ActivoInactivo === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        {{ item.ActivoInactivo === 1 ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="editar(item)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fas fa-edit"></i></button>
                                    <button @click="eliminar(item.IdTipoOperacion, item.Detalle)" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr v-if="items.data.length === 0"><td colspan="5" class="px-6 py-12 text-center text-gray-500">No hay tipos de operación registrados</td></tr>
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