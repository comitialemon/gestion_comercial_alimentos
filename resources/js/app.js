import '../css/app.css'
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { ZiggyVue } from 'ziggy'
import { Ziggy } from './ziggy'
import { createPinia } from 'pinia'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    const page = pages[`./Pages/${name}.vue`]
    if (!page) console.error('Página no encontrada:', name)
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
