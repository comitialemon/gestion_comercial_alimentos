import '../css/app.css'
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { ZiggyVue } from 'ziggy-js'
import { Ziggy } from './ziggy'
import { createPinia } from 'pinia'
import axios from 'axios'

// 🔥 CONFIGURAR AXIOS (colocar ANTES de createInertiaApp)
const actualizarTokenCSRF = () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    if (token) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token
        console.log('Token CSRF actualizado')
    } else {
        console.warn('Token CSRF no encontrado')
    }
}

// Configurar axios inicial
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
actualizarTokenCSRF()

// 🔥 INTERCEPTOR DE AXIOS (colocar ANTES de createInertiaApp)
axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401 || error.response?.status === 419) {
            // Sesión inválida, redirigir a login
            window.location.href = '/login'
        }
        return Promise.reject(error)
    }
)

// Layout global
import AppLayout from '@/Layouts/AppLayout.vue'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    const page = pages[`./Pages/${name}.vue`]

    if (!page) {
      console.error('Página no encontrada:', name)
      return
    }

    const sinLayout = [
      'Gestion/Todos/Operador/Login',
      'Contexto/Index',
      'Contexto/PuntoVenta',
    ]

    if (!sinLayout.includes(name)) {
      page.default.layout ??= AppLayout
    }

    return page
  },

  setup({ el, App, props, plugin }) {
    const pinia = createPinia()

    const app = createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue, Ziggy)
      .use(pinia)

    // 🔥 Actualizar token CSRF después de cada navegación de Inertia
    app.config.globalProperties.$inertia?.on('navigate', () => {
        actualizarTokenCSRF()
    })

    app.mount(el)
  },
})