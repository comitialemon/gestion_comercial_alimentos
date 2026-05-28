<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    tema: Object,
    clienteId: Number,
    clienteNombre: String
})

// Valores por defecto (negro/gris/blanco)
const form = ref({
    color_principal: props.tema?.color_principal || '#1f2937',
    color_secundario: props.tema?.color_secundario || '#4b5563',
    color_fondo: props.tema?.color_fondo || '#ffffff',
    color_texto: props.tema?.color_texto || '#000000',
    color_acento: props.tema?.color_acento || '#6b7280',
    nombre_sistema: props.tema?.nombre_sistema || '',
})

const tieneTemaPropio = computed(() => {
    return props.tema && props.tema.id_tema
})

const previewStyles = computed(() => ({
    primary: { backgroundColor: form.value.color_principal },
    secondary: { backgroundColor: form.value.color_secundario },
    text: { color: form.value.color_texto },
    background: { backgroundColor: form.value.color_fondo },
}))

const guardar = () => {
    router.post(`/gestion/configuracion/tema/${props.clienteId}`, form.value, {
        preserveScroll: true,
        onSuccess: () => {
            alert('Tema guardado correctamente')
        }
    })
}

const resetearDefault = () => {
    if (confirm('¿Restaurar colores por defecto (negro/gris/blanco)?')) {
        form.value = {
            color_principal: '#1f2937',
            color_secundario: '#4b5563',
            color_fondo: '#ffffff',
            color_texto: '#000000',
            color_acento: '#6b7280',
            nombre_sistema: '',
        }
    }
}
</script>

<template>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <!-- Header -->
            <div class="bg-gray-800 text-white px-6 py-4">
                <h1 class="text-xl font-bold">Personalización de Tema</h1>
                <p class="text-sm text-gray-300 mt-1">
                    Configura los colores para <strong>{{ clienteNombre }}</strong>
                </p>
            </div>

            <div class="p-6 space-y-6">
                <!-- Info de estado -->
                <div class="p-3 rounded-lg" :class="tieneTemaPropio ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200'">
                    <div class="flex items-center gap-2">
                        <i :class="tieneTemaPropio ? 'fas fa-palette text-green-600' : 'fas fa-info-circle text-gray-500'"></i>
                        <span class="text-sm" :class="tieneTemaPropio ? 'text-green-700' : 'text-gray-600'">
                            {{ tieneTemaPropio ? 'Este cliente tiene tema personalizado' : 'Este cliente usa el tema por defecto (negro/gris/blanco)' }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Colores -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color Principal</label>
                            <div class="flex items-center gap-3">
                                <input type="color" v-model="form.color_principal" class="w-16 h-10 rounded border cursor-pointer" />
                                <input type="text" v-model="form.color_principal" class="flex-1 border rounded-lg px-3 py-2 text-sm font-mono" />
                            </div>
                            <div class="mt-2 px-3 py-2 rounded text-white text-sm" :style="{ backgroundColor: form.color_principal }">
                                Vista previa: Botones principales, header, barras
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color Secundario</label>
                            <div class="flex items-center gap-3">
                                <input type="color" v-model="form.color_secundario" class="w-16 h-10 rounded border cursor-pointer" />
                                <input type="text" v-model="form.color_secundario" class="flex-1 border rounded-lg px-3 py-2 text-sm font-mono" />
                            </div>
                            <div class="mt-2 px-3 py-2 rounded text-white text-sm" :style="{ backgroundColor: form.color_secundario }">
                                Vista previa: Botones secundarios, acentos
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color de Fondo</label>
                            <div class="flex items-center gap-3">
                                <input type="color" v-model="form.color_fondo" class="w-16 h-10 rounded border cursor-pointer" />
                                <input type="text" v-model="form.color_fondo" class="flex-1 border rounded-lg px-3 py-2 text-sm font-mono" />
                            </div>
                            <div class="mt-2 px-3 py-2 rounded text-sm border" :style="{ backgroundColor: form.color_fondo, color: form.color_texto }">
                                Vista previa: Fondo de pantalla
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color de Texto</label>
                            <div class="flex items-center gap-3">
                                <input type="color" v-model="form.color_texto" class="w-16 h-10 rounded border cursor-pointer" />
                                <input type="text" v-model="form.color_texto" class="flex-1 border rounded-lg px-3 py-2 text-sm font-mono" />
                            </div>
                            <div class="mt-2 px-3 py-2 rounded border" :style="{ backgroundColor: '#f3f4f6', color: form.color_texto }">
                                Vista previa: Texto normal
                            </div>
                        </div>
                    </div>

                    <!-- Preview y opciones -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Sistema</label>
                            <input type="text" v-model="form.nombre_sistema" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Ej: Sistema Gestion - Mi Empresa" />
                        </div>

                        <!-- Preview completo -->
                        <div class="border rounded-lg overflow-hidden mt-4">
                            <div class="p-3" :style="{ backgroundColor: form.color_principal }">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded bg-white bg-opacity-20 flex items-center justify-center">
                                            <i class="fas fa-store text-white text-xs"></i>
                                        </div>
                                        <span class="text-white text-sm font-medium">{{ form.nombre_sistema || 'Sistema Gestion' }}</span>
                                    </div>
                                    <i class="fas fa-user-circle text-white text-xl"></i>
                                </div>
                            </div>
                            <div class="p-3" :style="{ backgroundColor: form.color_secundario }">
                                <div class="flex gap-2">
                                    <button class="px-3 py-1 rounded text-white text-xs" :style="{ backgroundColor: form.color_principal }">Opción 1</button>
                                    <button class="px-3 py-1 rounded text-white text-xs bg-gray-500">Opción 2</button>
                                </div>
                            </div>
                            <div class="p-3" :style="{ backgroundColor: form.color_fondo, color: form.color_texto }">
                                <p class="text-sm">Ejemplo de contenido normal con el texto en el color seleccionado.</p>
                                <p class="text-xs mt-2" :style="{ color: form.color_acento }">Texto de acento o secundario</p>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button 
                                @click="resetearDefault"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition"
                            >
                                Restaurar Default (Negro/Gris/Blanco)
                            </button>
                            <button 
                                @click="guardar"
                                class="flex-1 px-4 py-2 rounded-lg text-white transition"
                                :style="{ backgroundColor: form.color_principal }"
                            >
                                Guardar Configuración
                            </button>
                        </div>
                    </div>
                </div>

                <div class="text-xs text-gray-400 border-t pt-4 mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    Si no se configura ningún color, el cliente usará el tema por defecto (negro/gris/blanco).
                    Esto permite identificar visualmente qué clientes tienen personalización.
                </div>
            </div>
        </div>
    </div>
</template>