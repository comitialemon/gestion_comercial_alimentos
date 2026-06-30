<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, inject, onMounted, watch } from 'vue'
import NavBarTactil from '../Components/NavBarTactil.vue'
import axios from 'axios'
import ModalCambioProducto from './ModalCambioProducto.vue' // 🔥 IMPORTAR MODAL

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    categorias: Array,
    ruta: Array,
    titulo: String,
    comisionista: String
})

const loading = ref(false)

// 🔥 NUEVO: Variables para el modal
const mostrarModalCambio = ref(false)
const comboSeleccionado = ref(null)
const opcionesParaModal = ref([])

const irACategoria = (id) => {
    loading.value = true
    router.get(`/venta-tactil/categoria/${id}`, {}, {
        onFinish: () => { loading.value = false }
    })
}

// 🔥 NUEVO: Función para abrir el modal de cambio desde un producto
const abrirModalCambio = async (producto) => {
    try {
        console.log('🔍 Abriendo modal para producto:', producto)
        
        // Cargar los detalles completos del combo
        const response = await axios.get(`/venta-tactil/combo/${producto.id}`)
        
        console.log('📦 Respuesta del servidor:', response.data)
        
        if (response.data.success) {
            const combo = response.data.combo
            
            // Preparar las opciones para el modal
            const opcionesModal = prepararOpcionesParaModal(combo)
            
            console.log('✅ Opciones preparadas:', opcionesModal)
            console.log('📊 Cada grupo debe tener cantidad_total:', opcionesModal.map(g => ({ 
                nombre: g.nombre_original, 
                cantidad_total: g.cantidad_total 
            })))
            
            // Abrir el modal
            mostrarModalCambio.value = true
            comboSeleccionado.value = combo
            opcionesParaModal.value = opcionesModal
            
        } else {
            toast?.error('Error al cargar el combo: ' + response.data.message)
        }
        
    } catch (error) {
        console.error('❌ Error:', error)
        toast?.error('Error al cargar los detalles del combo')
    }
}

// 🔥 NUEVO: Función para transformar las opciones al formato del modal
const prepararOpcionesParaModal = (combo) => {
    if (!combo) return []
    
    const grupos = {}
    
    // Usar la composición para obtener las cantidades
    combo.composicion?.forEach(item => {
        const idOriginal = item.id_producto
        
        // Buscar opciones para este producto
        const opcionesDelProducto = combo.opciones?.filter(
            op => op.id_producto_original === idOriginal
        ) || []
        
        grupos[idOriginal] = {
            id_producto_original: idOriginal,
            nombre_original: item.nombre || 'Producto',
            cantidad_total: item.porcion || 1,
            opciones: opcionesDelProducto.map(op => ({
                id_sustituto: op.id_producto_sustituto,
                nombre: op.nombre_sustituto,
                codigo: op.codigo_sustituto || '',
                cantidad_maxima: op.cantidad_maxima || item.porcion || 1
            }))
        }
    })
    
    // Si no hay opciones, pero hay composición, crear grupos sin opciones
    if (Object.keys(grupos).length === 0 && combo.composicion) {
        combo.composicion.forEach(item => {
            grupos[item.id_producto] = {
                id_producto_original: item.id_producto,
                nombre_original: item.nombre,
                cantidad_total: item.porcion || 1,
                opciones: []
            }
        })
    }
    
    return Object.values(grupos)
}

// 🔥 NUEVO: Confirmar personalización
const confirmarPersonalizacion = (personalizaciones) => {
    console.log('✅ Personalizaciones confirmadas:', personalizaciones)
    console.log('📦 Combo seleccionado:', comboSeleccionado.value)
    
    // Aquí agregas la lógica para agregar al carrito con las personalizaciones
    // Por ahora solo mostramos un mensaje
    toast?.success('Combo personalizado agregado al carrito')
    
    // Cerrar el modal
    mostrarModalCambio.value = false
    comboSeleccionado.value = null
    opcionesParaModal.value = []
}

// 🔥 NUEVO: Cerrar modal
const cerrarModalCambio = () => {
    mostrarModalCambio.value = false
    comboSeleccionado.value = null
    opcionesParaModal.value = []
}

// 🔥 NUEVO: Escuchar evento de clic en producto (desde el componente Productos)
// Si usas el componente Productos, escucha el evento
const handleProductoClick = (producto) => {
    // Verificar si el producto es un combo (tiene composición)
    // O puedes verificar si tiene opciones de cambio
    if (producto.es_combo || producto.tiene_opciones) {
        abrirModalCambio(producto)
    } else {
        // Agregar directamente al carrito
        agregarAlCarritoDirecto(producto)
    }
}

// Función para agregar directamente al carrito (producto simple)
const agregarAlCarritoDirecto = (producto) => {
    console.log('🛒 Agregando producto simple:', producto)
    // Aquí va tu lógica de agregar al carrito
    toast?.success(`${producto.nombre} agregado al carrito`)
}

// Exponer funciones para que los componentes hijos las usen
defineExpose({
    abrirModalCambio,
    agregarAlCarritoDirecto
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="max-w-7xl mx-auto p-3 sm:p-4">
            
            <NavBarTactil 
                :comisionista="comisionista || 'Sin comisionista'"
                :ruta="ruta"
                :mostrar-ruta="true"
                :mostrar-cancelar="true"
            />

            <h1 class="text-xl sm:text-2xl font-bold text-primary-800 mb-4 sm:mb-6 text-center">{{ titulo }}</h1>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4">
                <div 
                    v-for="cat in categorias" 
                    :key="cat.id_categoria"
                    @click="irACategoria(cat.id_categoria)"
                    class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all hover:scale-105 cursor-pointer overflow-hidden border border-gray-100"
                >
                    <div class="h-28 sm:h-32 bg-gradient-to-br from-primary-50 to-secondary-50 flex items-center justify-center p-2 sm:p-3">
                        <img v-if="cat.imagen_url" :src="cat.imagen_url" class="w-full h-full object-cover">
                        <i v-else class="fas fa-folder-open text-4xl sm:text-5xl text-primary-300"></i>
                    </div>
                    <div class="p-2 sm:p-3 text-center">
                        <h3 class="font-bold text-sm sm:text-md text-primary-800">{{ cat.nombre }}</h3>
                        <p class="text-[10px] sm:text-xs text-gray-400 mt-0.5 sm:mt-1">Toca para ver</p>
                    </div>
                </div>
            </div>

            <div v-if="!categorias.length" class="text-center text-gray-500 py-12">
                <i class="fas fa-folder-open text-5xl mb-3 block text-gray-300"></i>
                <p class="text-lg">No hay categorías disponibles</p>
            </div>

            <!-- 🔥 MODAL DE CAMBIO DE PRODUCTO -->
            <ModalCambioProducto
                v-model:visible="mostrarModalCambio"
                :combo="comboSeleccionado"
                :opciones="opcionesParaModal"
                :cantidad="1"
                @confirm="confirmarPersonalizacion"
                @close="cerrarModalCambio"
            />

            <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl p-6 flex items-center gap-3 shadow-xl">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary-600"></i>
                    <span class="text-gray-700">Cargando...</span>
                </div>
            </div>
        </div>
    </div>
</template>