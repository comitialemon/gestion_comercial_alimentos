import { defineStore } from 'pinia'

export const useUiStore = defineStore('ui', {
  state: () => ({
    sidebarOpen: true,        // desktop
    sidebarMobileOpen: false, // mobile
  }),
  actions: {
    toggleSidebar(){ this.sidebarOpen = !this.sidebarOpen },
    openMobile(){ this.sidebarMobileOpen = true },
    closeMobile(){ this.sidebarMobileOpen = false },
  },
})
