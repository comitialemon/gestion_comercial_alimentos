import { defineStore } from 'pinia'
import { router } from '@inertiajs/vue3' // solo para acceso a route() de Ziggy

export const useMenuStore = defineStore('menu', {
  state: () => ({
    items: [],     // árbol de menús
    loading: false,
    loaded: false, // para no volver a pedir si ya está
  }),
  actions: {
    async fetch() {
      if (this.loaded) return
      this.loading = true
      try {
        const res = await window.axios.get(route('menu.index'))
        // Si tu controller devuelve Inertia, cambia a res.props.menu
        this.items = res.data.menu ?? res.data // admite {menu:[...]} o [...]
        this.loaded = true
      } catch (e) {
        console.error('Error cargando menú:', e)
        this.items = []
      } finally {
        this.loading = false
      }
    },
    clear() {
      this.items = []
      this.loaded = false
    }
  },
})
