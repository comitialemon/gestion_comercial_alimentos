<script setup>
import { ref, onMounted, onUnmounted, computed, inject } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

const page = usePage()
const toast = inject('toast')

const theme = computed(() => page.props?.theme || {
    primary: '#1f2937', primary_rgb: '31, 41, 55', secondary: '#4b5563', hasCustomTheme: false, text_light: '#ffffff'
})

const notificaciones = ref({ total: 0, modulos: [] })
const productosPendientes = ref([])
const cargando = ref(false)
const abierto = ref(false)
const isMobile = ref(window.innerWidth < 768)
const tabActiva = ref('sistema')

const handleResize = () => { isMobile.value = window.innerWidth < 768 }

const tieneNotificacionesSistema = computed(() => notificaciones.value.total > 0 || notificaciones.value.modulos.length > 0)
const tieneNotificacionesProductos = computed(() => productosPendientes.value.length > 0)

const inicializarTab = () => {
    if (tieneNotificacionesSistema.value) tabActiva.value = 'sistema'
    else if (tieneNotificacionesProductos.value) tabActiva.value = 'productos'
}

const hayNotificaciones = computed(() => notificaciones.value.total > 0 || productosPendientes.value.length > 0)
const totalGlobal = computed(() => notificaciones.value.total + productosPendientes.value.length)

const buttonStyle = computed(() => ({ backgroundColor: 'rgba(255,255,255,0.1)', color: 'var(--color-text-light)' }))
const buttonActiveStyle = computed(() => ({ backgroundColor: 'rgba(255,255,255,0.25)', color: 'var(--color-text-light)' }))
const headerStyle = computed(() => ({ backgroundColor: `rgba(var(--color-primary-rgb), 0.1)`, borderBottomColor: `rgba(var(--color-primary-rgb), 0.2)` }))
const headerTextStyle = computed(() => ({ color: `var(--color-primary)` }))
const badgeStyle = computed(() => ({ backgroundColor: `var(--color-primary)`, color: `var(--color-text-light)` }))
const badgePendienteStyle = computed(() => ({ backgroundColor: `var(--color-primary)`, color: `var(--color-text-light)` }))
const spinnerStyle = computed(() => ({ color: `var(--color-primary)` }))

const cargarNotificaciones = async () => {
    cargando.value = true
    try {
        const [resSistema, resProductos] = await Promise.allSettled([
            axios.get('/api/mis-notificaciones'), 
            axios.get('/api/notificaciones/pendientes')
        ])
        
        notificaciones.value = (resSistema.status === 'fulfilled' && resSistema.value.data) ? resSistema.value.data : { total: 0, modulos: [] }
        productosPendientes.value = (resProductos.status === 'fulfilled' && resProductos.value.data) ? resProductos.value.data : []
        inicializarTab()
    } catch (error) {
        console.error('Error cargando notificaciones:', error)
    } finally {
        cargando.value = false
    }
}

const aprobarProducto = async (votoId) => {
    try {
        await axios.post(`/gestion/inventario/productos-aprobacion/votar/${votoId}`, { estado: 'aprobado' })
        await cargarNotificaciones()
        toast?.success('✅ Aprobado', 'Producto aprobado correctamente')
    } catch (error) { toast?.error('❌ Error', 'Error al aprobar el producto') }
}

const rechazarProducto = async (votoId) => {
    const comentario = prompt('Ingrese un motivo de rechazo (opcional):')
    try {
        await axios.post(`/gestion/inventario/productos-aprobacion/votar/${votoId}`, { estado: 'rechazado', comentario: comentario || '' })
        await cargarNotificaciones()
        toast?.success('✅ Rechazado', 'Producto rechazado correctamente')
    } catch (error) { toast?.error('❌ Error', 'Error al rechazar el producto') }
}

const irANotificacion = (url) => {
    abierto.value = false
    router.visit(url)
}

const toggleDropdown = () => {
    if (!abierto.value) cargarNotificaciones()
    abierto.value = !abierto.value
}

const handleClickOutside = (event) => {
    const element = document.querySelector('.notificaciones-dropdown')
    if (element && !element.contains(event.target)) abierto.value = false
}

const formatearFecha = (fecha) => {
    if (!fecha) return ''
    const date = new Date(fecha)
    const hoy = new Date()
    const diff = hoy - date
    const dias = Math.floor(diff / (1000 * 60 * 60 * 24))
    if (dias === 0) return 'Hoy'
    if (dias === 1) return 'Ayer'
    if (dias < 7) return `Hace ${dias} días`
    return date.toLocaleDateString('es-BO')
}

let intervalo
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    window.addEventListener('resize', handleResize)
    cargarNotificaciones()
    intervalo = setInterval(cargarNotificaciones, 30000)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    window.removeEventListener('resize', handleResize)
    if (intervalo) clearInterval(intervalo)
})
</script>

<template>
    <div class="relative notificaciones-dropdown">
        <button 
            @click="toggleDropdown"
            class="relative p-1.5 sm:p-2 rounded-lg transition flex-shrink-0"
            :class="{ 'animate-pulse ring-2 ring-yellow-400 ring-opacity-75': hayNotificaciones, 'hover:bg-white/10': true }"
            :style="hayNotificaciones ? buttonActiveStyle : buttonStyle"
            title="Notificaciones"
        >
            <i class="fas fa-bell text-xs sm:text-sm"></i>
            <span v-if="totalGlobal > 0" class="absolute -top-1 -right-1 text-white text-[9px] sm:text-[10px] font-bold rounded-full min-w-[16px] sm:min-w-[18px] h-[16px] sm:h-[18px] flex items-center justify-center px-0.5 sm:px-1 animate-bounce" :style="badgePendienteStyle">
                {{ totalGlobal > 99 ? '99+' : totalGlobal }}
            </span>
        </button>

        <div 
            v-if="abierto"
            class="fixed sm:absolute top-16 sm:top-auto sm:right-0 sm:mt-2 w-[calc(100vw-2rem)] sm:w-[420px] bg-white rounded-lg shadow-xl z-50 overflow-hidden border border-gray-200"
            :class="isMobile ? 'left-1/2 -translate-x-1/2' : 'absolute right-0 mt-2'"
        >
            <div class="border-b" :style="headerStyle">
                <div class="flex items-center justify-between px-3 pt-2.5 pb-1">
                    <h3 class="text-xs sm:text-sm font-semibold" :style="headerTextStyle"><i class="fas fa-bell mr-1 sm:mr-2 text-xs sm:text-sm"></i> Notificaciones</h3>
                    <span v-if="totalGlobal > 0" class="text-[10px] sm:text-xs text-white px-1.5 sm:px-2 py-0.5 rounded-full" :style="badgeStyle">{{ totalGlobal }}</span>
                </div>
                
                <div class="flex gap-1 px-2 mt-1">
                    <button v-if="tieneNotificacionesSistema || (!tieneNotificacionesProductos && notificaciones.total >= 0)" @click="tabActiva = 'sistema'" class="px-3 py-1.5 text-xs font-medium rounded-t-lg transition-colors" :class="tabActiva === 'sistema' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-600 hover:bg-white/50'">
                        Sistema
                        <span v-if="notificaciones.total > 0" class="ml-1 text-[9px] bg-red-500 text-white px-1.5 py-0.5 rounded-full">{{ notificaciones.total }}</span>
                    </button>
                    <button v-if="tieneNotificacionesProductos || (!tieneNotificacionesSistema && productosPendientes.length >= 0)" @click="tabActiva = 'productos'" class="px-3 py-1.5 text-xs font-medium rounded-t-lg transition-colors" :class="tabActiva === 'productos' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-600 hover:bg-white/50'">
                        Productos
                        <span v-if="productosPendientes.length > 0" class="ml-1 text-[9px] bg-amber-500 text-white px-1.5 py-0.5 rounded-full">{{ productosPendientes.length }}</span>
                    </button>
                </div>
            </div>

            <div class="max-h-[380px] overflow-y-auto bg-white">
                <div v-if="cargando" class="flex justify-center py-6"><i class="fas fa-spinner fa-spin text-base" :style="spinnerStyle"></i></div>

                <!-- SISTEMA -->
                <div v-else-if="tabActiva === 'sistema'">
                    <div v-if="notificaciones.modulos.length === 0" class="text-center py-8 text-gray-400">
                        <i class="fas fa-check-circle text-2xl mb-1 block text-emerald-400"></i>
                        <p class="text-xs">Todo al día en el sistema.</p>
                    </div>
                    <div v-else>
                        <div v-for="mod in notificaciones.modulos" :key="mod.tipo" class="mb-1">
                            <div class="px-3 py-1.5 bg-gray-50 border-y border-gray-100 flex justify-between items-center sticky top-0 z-10">
                                <span class="text-[10px] font-bold text-gray-500 uppercase flex items-center gap-1.5"><i :class="['fas', mod.icono, 'text-[10px]']"></i> {{ mod.titulo }}</span>
                                <span class="text-[9px] bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded-full font-bold">{{ mod.cantidad }} en total</span>
                            </div>

                            <div 
                                v-for="item in mod.items" 
                                :key="item.id"
                                @click="irANotificacion(item.url || mod.url)" 
                                class="flex items-start gap-2.5 px-3 py-2.5 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition"
                            >
                                <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" :class="mod.color"><i :class="['fas', mod.icono, 'text-[10px]']"></i></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[11px] font-bold text-gray-800 truncate">{{ item.titulo }}</p>
                                    <p class="text-[10px] text-gray-600 truncate">{{ item.subtitulo }}</p>
                                    <p class="text-[9px] text-gray-400 mt-0.5"><i class="far fa-clock mr-1"></i>{{ item.tiempo }}</p>
                                </div>
                                <i class="fas fa-chevron-right text-[8px] text-gray-300 mt-2.5"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRODUCTOS -->
                <div v-else-if="tabActiva === 'productos'">
                    <div v-if="productosPendientes.length === 0" class="text-center py-8 text-gray-400">
                        <i class="fas fa-check-circle text-2xl mb-1 block text-emerald-400"></i>
                        <p class="text-xs">No hay productos pendientes.</p>
                    </div>
                    <div v-else>
                        <div v-for="notif in productosPendientes" :key="notif.IdProductoAprobacionSolicitud" class="p-3 border-b border-gray-100 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <p class="text-[11px] font-bold text-gray-800 line-clamp-2">{{ notif.producto?.Detalle || 'Producto' }}</p>
                                    <p class="text-[10px] text-gray-500 mt-0.5"><i class="fas fa-box mr-1"></i> Cód: {{ notif.producto?.Codigo }}</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5"><i class="far fa-clock mr-1"></i> {{ formatearFecha(notif.FechaSolicitud) }}</p>
                                </div>
                            </div>
                            <div class="flex gap-2 mt-1">
                                <button @click="aprobarProducto(notif.IdProductoAprobacionVoto)" class="flex-1 px-2 py-1.5 bg-green-600 text-white text-[10px] rounded hover:bg-green-700 transition"><i class="fas fa-check mr-1"></i> Aprobar</button>
                                <button @click="rechazarProducto(notif.IdProductoAprobacionVoto)" class="flex-1 px-2 py-1.5 bg-red-600 text-white text-[10px] rounded hover:bg-red-700 transition"><i class="fas fa-times mr-1"></i> Rechazar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-3 py-2 border-t bg-gray-50 text-center">
                <Link v-if="tabActiva === 'sistema'" href="/operacion/pedidos/clientes-mayoristas/tesoreria" class="text-[10px] sm:text-xs font-medium transition hover:underline" :style="{ color: 'var(--color-primary)' }">Ir a Tesorería <i class="fas fa-arrow-right ml-1 text-[8px]"></i></Link>
                <Link v-else href="/gestion/inventario/productos-aprobacion/pendientes" class="text-[10px] sm:text-xs font-medium transition hover:underline" :style="{ color: 'var(--color-primary)' }">Ver todos los productos <i class="fas fa-arrow-right ml-1 text-[8px]"></i></Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
@keyframes pulse-ring { 0% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.7); } 70% { box-shadow: 0 0 0 6px rgba(234, 179, 8, 0); } 100% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); } }
.animate-pulse { animation: pulse-ring 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
@media (max-width: 768px) { .notificaciones-dropdown .fixed { top: 56px; max-height: calc(100vh - 70px); overflow-y: auto; } }
</style>