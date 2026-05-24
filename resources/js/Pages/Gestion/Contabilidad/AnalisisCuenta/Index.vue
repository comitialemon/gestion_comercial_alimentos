<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    cuentas: Array,
    sucursales: Array,
    sucursalId: Number,
    tieneMultiplesSucursales: Boolean,
    esSupervisor: Boolean,
})

const form = ref({
    Cuenta: '',
    Fecha: new Date().toISOString().split('T')[0],
    FechaFinal: new Date().toISOString().split('T')[0],
    SucursalId: props.sucursalId || '',
})

const loading = ref(false)

// ==========================================
// FILTRO PARA EL BUSCADOR DE CUENTAS
// ==========================================
const busquedaCuenta = ref('')
const cuentasFiltradas = computed(() => {
    if (!busquedaCuenta.value) return props.cuentas || []
    const termino = busquedaCuenta.value.toLowerCase()
    return props.cuentas.filter(c => c.nombre?.toLowerCase().includes(termino))
})

const generarReporte = () => {
    if (!form.value.Cuenta) {
        alert('Seleccione una cuenta')
        return
    }
    if (!form.value.Fecha) {
        alert('Seleccione una fecha inicial')
        return
    }
    if (!form.value.FechaFinal) {
        alert('Seleccione una fecha final')
        return
    }
    if (form.value.FechaFinal < form.value.Fecha) {
        alert('La fecha final debe ser mayor o igual a la fecha inicial')
        return
    }
    
    loading.value = true
    
    // Crear un formulario temporal y enviarlo
    const formData = new FormData()
    formData.append('Cuenta', form.value.Cuenta)
    formData.append('Fecha', form.value.Fecha)
    formData.append('FechaFinal', form.value.FechaFinal)
    if (props.tieneMultiplesSucursales && form.value.SucursalId) {
        formData.append('SucursalId', form.value.SucursalId)
    }
    
    fetch('/gestion/analisis-cuenta/excel', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    }).then(response => {
        if (response.ok) {
            // Descargar archivo
            return response.blob()
        }
        throw new Error('Error al generar el reporte')
    }).then(blob => {
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = 'AnalisisDeCuenta.xls'
        document.body.appendChild(a)
        a.click()
        window.URL.revokeObjectURL(url)
        a.remove()
    }).catch(error => {
        console.error('Error:', error)
        alert('Error al generar el reporte')
    }).finally(() => {
        loading.value = false
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-chart-line text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Análisis de Cuenta</h1>
                            <p class="text-xs text-gray-500">Reporte de movimientos por cuenta e identificador</p>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <form @submit.prevent="generarReporte" class="space-y-5">
                        <!-- Sucursal (solo para supervisores) -->
                        <div v-if="tieneMultiplesSucursales && esSupervisor">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-store mr-1 text-guindo-600"></i> Sucursal
                            </label>
                            <select v-model="form.SucursalId" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">Todas las sucursales</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                        </div>

                        <!-- Cuenta con Buscador Predictivo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-book mr-1 text-guindo-600"></i> Cuenta *
                            </label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="busquedaCuenta" 
                                    class="w-full border rounded-lg px-3 py-2 text-sm" 
                                    placeholder="Escribe para buscar una cuenta..." 
                                    @focus="busquedaCuenta = ''"
                                >
                                <!-- Menú Desplegable Predictivo -->
                                <div v-if="busquedaCuenta && cuentasFiltradas.length" class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto text-sm">
                                    <div 
                                        v-for="c in cuentasFiltradas" 
                                        :key="c.id" 
                                        @click="form.Cuenta = c.id; busquedaCuenta = c.nombre" 
                                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0"
                                    >
                                        {{ c.nombre }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fechas -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-calendar-alt mr-1 text-guindo-600"></i> Fecha Inicial *
                                </label>
                                <input type="date" v-model="form.Fecha" class="w-full border rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-calendar-alt mr-1 text-guindo-600"></i> Fecha Final *
                                </label>
                                <input type="date" v-model="form.FechaFinal" class="w-full border rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex justify-end gap-3 pt-3 border-t">
                            <button 
                                type="submit" 
                                :disabled="loading"
                                class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-2 text-sm"
                            >
                                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-file-excel"></i>
                                {{ loading ? 'Generando...' : 'Generar Excel' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    El reporte muestra el saldo inicial, movimientos (Debe/Haber) y saldo final por cada identificador que haya tenido movimiento en la cuenta seleccionada.
                </div>
            </div>
        </div>
    </div>
</template>