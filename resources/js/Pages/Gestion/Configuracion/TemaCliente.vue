<script setup>
import { ref, computed, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

// 🔥 INYECTAR TOAST
const toast = inject('toast')

const props = defineProps({
    tema: Object,
    clienteId: Number,
    clienteNombre: String
})

// Valores por defecto
const form = ref({
    color_principal: props.tema?.color_principal || '#1f2937',
    color_secundario: props.tema?.color_secundario || '#4b5563',
    color_acento: props.tema?.color_acento || '#6b7280',
    color_fondo: props.tema?.color_fondo || '#ffffff',
    color_texto_oscuro: props.tema?.color_texto_oscuro || '#111827',
    color_texto_claro: props.tema?.color_texto_claro || '#ffffff',
    nombre_sistema: props.tema?.nombre_sistema || '',
})

const logoFile = ref(null)
const guardando = ref(false)
const logoPreview = ref(props.tema?.logo_url || null)

const tieneTemaPropio = computed(() => {
    return props.tema && props.tema.id_tema
})

// 🔥 PREVIEW con los dos tipos de texto
const previewHeaderStyle = computed(() => ({
    backgroundColor: form.value.color_principal,
    color: form.value.color_texto_claro
}))

const previewContentStyle = computed(() => ({
    backgroundColor: form.value.color_fondo,
    color: form.value.color_texto_oscuro
}))

const onLogoChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        if (file.size > 512 * 1024) {
            toast?.warning('Logo muy grande', 'El logo no puede superar los 512KB')
            event.target.value = ''
            return
        }
        logoFile.value = file
        logoPreview.value = URL.createObjectURL(file)
    }
}

const guardar = () => {
    // Validar que haya al menos un cambio
    guardando.value = true
    
    const data = new FormData()
    data.append('color_principal', form.value.color_principal)
    data.append('color_secundario', form.value.color_secundario)
    data.append('color_acento', form.value.color_acento)
    data.append('color_fondo', form.value.color_fondo)
    data.append('color_texto_oscuro', form.value.color_texto_oscuro)
    data.append('color_texto_claro', form.value.color_texto_claro)
    data.append('nombre_sistema', form.value.nombre_sistema || '')
    
    if (logoFile.value) data.append('logo', logoFile.value)
    
    router.post(`/gestion/configuracion/tema/${props.clienteId}`, data, {
        preserveScroll: true,
        headers: { 'Content-Type': 'multipart/form-data' },
        onSuccess: (page) => {
            guardando.value = false
            logoFile.value = null
            
            // 🔥 MOSTRAR MENSAJE DE ÉXITO
            if (page.props.flash?.success) {
                toast?.success('✅ Éxito', page.props.flash.success)
            } else {
                toast?.success('✅ Tema guardado', 'La configuración del tema se ha guardado correctamente')
            }
            
            // Recargar la página para aplicar cambios
            setTimeout(() => {
                window.location.reload()
            }, 1500)
        },
        onError: (errors) => {
            guardando.value = false
            
            // 🔥 MOSTRAR ERRORES ESPECÍFICOS
            if (errors && Object.keys(errors).length > 0) {
                const primerError = Object.values(errors)[0]
                toast?.error('❌ Error al guardar', Array.isArray(primerError) ? primerError[0] : primerError)
            } else {
                toast?.error('❌ Error al guardar', 'Ocurrió un error al guardar la configuración')
            }
        }
    })
}

const resetearDefault = () => {
    if (confirm('⚠️ ¿Restaurar colores por defecto? Esta acción eliminará la configuración personalizada.')) {
        router.delete(`/gestion/configuracion/tema/${props.clienteId}/reset`, {
            preserveScroll: true,
            onSuccess: (page) => {
                form.value = {
                    color_principal: '#1f2937',
                    color_secundario: '#4b5563',
                    color_acento: '#6b7280',
                    color_fondo: '#ffffff',
                    color_texto_oscuro: '#111827',
                    color_texto_claro: '#ffffff',
                    nombre_sistema: '',
                }
                logoPreview.value = null
                logoFile.value = null
                
                // 🔥 MOSTRAR MENSAJE DE ÉXITO
                if (page.props.flash?.success) {
                    toast?.success('🔄 Tema restaurado', page.props.flash.success)
                } else {
                    toast?.success('🔄 Tema restaurado', 'Se ha restaurado el tema por defecto')
                }
                
                // Recargar después de restaurar
                setTimeout(() => {
                    window.location.reload()
                }, 1500)
            },
            onError: (errors) => {
                toast?.error('❌ Error', 'No se pudo restaurar el tema por defecto')
            }
        })
    }
}
</script>

<template>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="bg-gray-800 text-white px-6 py-4">
                <h1 class="text-xl font-bold">Personalización de Tema</h1>
                <p class="text-sm text-gray-300 mt-1">
                    Configura los colores para <strong>{{ clienteNombre }}</strong>
                </p>
            </div>

            <div class="p-6 space-y-6">
                <!-- Estado con ícono y mensaje -->
                <div class="p-3 rounded-lg" :class="tieneTemaPropio ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200'">
                    <div class="flex items-center gap-2">
                        <i :class="tieneTemaPropio ? 'fas fa-palette text-green-600' : 'fas fa-info-circle text-gray-500'"></i>
                        <span class="text-sm" :class="tieneTemaPropio ? 'text-green-700' : 'text-gray-600'">
                            {{ tieneTemaPropio ? '✅ Tema personalizado' : '⚪ Tema por defecto' }}
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
                            <div class="mt-2 px-3 py-2 rounded text-sm text-center" :style="previewHeaderStyle">
                                Header · Sidebar · Botones principales
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Fondo de la barra superior y menú lateral</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color Secundario</label>
                            <div class="flex items-center gap-3">
                                <input type="color" v-model="form.color_secundario" class="w-16 h-10 rounded border cursor-pointer" />
                                <input type="text" v-model="form.color_secundario" class="flex-1 border rounded-lg px-3 py-2 text-sm font-mono" />
                            </div>
                            <div class="mt-2 px-3 py-2 rounded text-sm text-center" :style="{ backgroundColor: form.color_secundario, color: form.color_texto_claro }">
                                Botones secundarios · Bordes
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Color de Acento</label>
                            <div class="flex items-center gap-3">
                                <input type="color" v-model="form.color_acento" class="w-16 h-10 rounded border cursor-pointer" />
                                <input type="text" v-model="form.color_acento" class="flex-1 border rounded-lg px-3 py-2 text-sm font-mono" />
                            </div>
                            <div class="mt-2 px-3 py-2 rounded text-sm text-center" :style="{ backgroundColor: '#f3f4f6', color: form.color_acento }">
                                Links · Hovers · Íconos
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fondo de Pantalla</label>
                            <div class="flex items-center gap-3">
                                <input type="color" v-model="form.color_fondo" class="w-16 h-10 rounded border cursor-pointer" />
                                <input type="text" v-model="form.color_fondo" class="flex-1 border rounded-lg px-3 py-2 text-sm font-mono" />
                            </div>
                            <div class="mt-2 px-3 py-2 rounded text-sm border text-center" :style="previewContentStyle">
                                Contenido principal
                            </div>
                        </div>
                    </div>

                    <!-- Textos y Logo -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Texto sobre fondo CLARO</label>
                            <div class="flex items-center gap-3">
                                <input type="color" v-model="form.color_texto_oscuro" class="w-16 h-10 rounded border cursor-pointer" />
                                <input type="text" v-model="form.color_texto_oscuro" class="flex-1 border rounded-lg px-3 py-2 text-sm font-mono" />
                            </div>
                            <div class="mt-2 px-3 py-2 rounded text-sm border" :style="{ backgroundColor: '#f3f4f6', color: form.color_texto_oscuro }">
                                Este texto se ve sobre fondos claros
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Texto sobre fondo OSCURO</label>
                            <div class="flex items-center gap-3">
                                <input type="color" v-model="form.color_texto_claro" class="w-16 h-10 rounded border cursor-pointer" />
                                <input type="text" v-model="form.color_texto_claro" class="flex-1 border rounded-lg px-3 py-2 text-sm font-mono" />
                            </div>
                            <div class="mt-2 px-3 py-2 rounded text-sm text-center" :style="{ backgroundColor: form.color_principal, color: form.color_texto_claro }">
                                Este texto se ve sobre fondos oscuros
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                            <input type="file" @change="onLogoChange" accept="image/png,image/jpg,image/jpeg,image/svg" class="w-full border rounded-lg px-3 py-2 text-sm" />
                            <p class="text-xs text-gray-400 mt-1">Recomendado: PNG transparente, máximo 512KB</p>
                            <div v-if="logoPreview" class="mt-2 p-2 bg-gray-100 rounded-lg inline-block">
                                <img :src="logoPreview" class="h-10 w-auto object-contain" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Sistema</label>
                            <input type="text" v-model="form.nombre_sistema" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Ej: Sistema Gestion" />
                            <p class="text-xs text-gray-400 mt-1">Aparece en la pestaña del navegador</p>
                        </div>

                        <!-- Preview completo -->
                        <div class="border rounded-lg overflow-hidden mt-4">
                            <div class="p-3" :style="{ backgroundColor: form.color_principal, color: form.color_texto_claro }">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <img v-if="logoPreview" :src="logoPreview" class="h-6 w-auto" />
                                        <div v-else class="w-6 h-6 rounded bg-white bg-opacity-20 flex items-center justify-center">
                                            <i class="fas fa-store text-white text-xs"></i>
                                        </div>
                                        <span class="text-sm font-medium">{{ clienteNombre }}</span>
                                    </div>
                                    <i class="fas fa-user-circle text-xl"></i>
                                </div>
                            </div>
                            <div class="p-3" :style="{ backgroundColor: form.color_secundario, color: form.color_texto_claro }">
                                <div class="flex gap-2">
                                    <button class="px-3 py-1 rounded text-xs" :style="{ backgroundColor: form.color_principal, color: form.color_texto_claro }">Principal</button>
                                    <button class="px-3 py-1 rounded text-xs" :style="{ backgroundColor: 'transparent', border: `1px solid ${form.color_texto_claro}`, color: form.color_texto_claro }">Secundario</button>
                                </div>
                            </div>
                            <div class="p-3" :style="{ backgroundColor: form.color_fondo, color: form.color_texto_oscuro }">
                                <p class="text-sm">Ejemplo de contenido normal.</p>
                                <a href="#" class="text-sm" :style="{ color: form.color_acento }">Enlace de ejemplo</a>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button @click="resetearDefault" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                                Restaurar Default
                            </button>
                            <button @click="guardar" :disabled="guardando" class="flex-1 px-4 py-2 rounded-lg text-white transition disabled:opacity-50 flex items-center justify-center gap-2" :style="{ backgroundColor: form.color_principal }">
                                <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                                {{ guardando ? 'Guardando...' : 'Guardar Configuración' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>