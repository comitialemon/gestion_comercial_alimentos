import '../css/app.css'
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { ZiggyVue } from 'ziggy-js'
import { Ziggy } from './ziggy'
import { createPinia } from 'pinia'

// Layout global (navbar + sidebar)
import AppLayout from '@/Layouts/AppLayout.vue'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    const page = pages[`./Pages/${name}.vue`]

    if (!page) {
      console.error('Página no encontrada:', name)
      return
    }

    // ✅ Páginas que NO deben mostrar navbar ni sidebar
    const sinLayout = [
      'Gestion/Todos/Operador/Login',
      'Contexto/Index',
    ]

    // ✅ Aplica AppLayout a TODO menos las páginas excluidas
    if (!sinLayout.includes(name)) {
      page.default.layout ??= AppLayout
    }

    return page
  },

  setup({ el, App, props, plugin }) {
    const pinia = createPinia()

    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue, Ziggy)
      .use(pinia)
      .mount(el)
  },
})
