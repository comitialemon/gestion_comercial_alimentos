<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import MenuNode from '@/Components/Menu/MenuNode.vue'

const page = usePage()
const ui = useUiStore()

const props = defineProps({
    items: { type: Array, default: () => [] },
})

// 🔥 TEMA DINÁMICO
const theme = computed(() => page.props?.theme || {
    primary: '#1f2937',
    hasCustomTheme: false
})

const ctxReady  = computed(() => page.props?.ctx?.ready === true)
const menuItems = computed(() => props.items.length ? props.items : (page.props?.menu ?? []))

// Colores del sidebar basados en el tema
const sidebarBgColor = computed(() => {
    if (theme.value.hasCustomTheme) {
        return 'var(--color-primary-800)'
    }
    return '#1f2937'
})

const footerBgColor = computed(() => {
    if (theme.value.hasCustomTheme) {
        return 'var(--color-primary-900)'
    }
    return '#111827'
})
</script>

<template>
    <template v-if="ctxReady">
        <!-- Overlay móvil -->
        <div
            v-if="ui.sidebarMobileOpen"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden transition-opacity duration-200"
            @click="ui.closeMobile()"
        />
        
        <!-- 🔥 SIDEBAR CON ALTURA DINÁMICA (top-16 = 64px) -->
        <aside
            class="fixed top-16 z-50 h-[calc(100vh-4rem)] w-64 overflow-y-auto shadow-lg transition-transform duration-200 ease-out"
            :class="[
                ui.sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full',
                ui.sidebarOpen ? 'lg:translate-x-0' : 'lg:-translate-x-full',
            ]"
            :style="{ backgroundColor: sidebarBgColor }"
        >
            <!-- Contenido del menú -->
            <nav class="p-2 pb-20">
                <ul class="space-y-0.5">
                    <MenuNode
                        v-for="it in menuItems"
                        :key="it.id ?? it.Id"
                        :node="it"
                        :collapsed="false"
                        :depth="0"
                        :visited="new Set()"
                    />
                </ul>
            </nav>
            
            <!-- Footer -->
            <div 
                class="mt-auto p-3 text-center border-t text-xs"
                :style="{ 
                    backgroundColor: footerBgColor,
                    borderColor: 'rgba(255,255,255,0.1)',
                    color: 'rgba(255,255,255,0.5)'
                }"
            >
                <i class="fas fa-palette mr-1"></i>
                <span v-if="theme.hasCustomTheme">Tema personalizado</span>
                <span v-else>Tema por defecto</span>
            </div>
        </aside>
    </template>
</template>

<style scoped>
/* Scrollbar personalizado para el sidebar */
aside::-webkit-scrollbar {
    width: 4px;
}

aside::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

aside::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}

aside::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* Hacer que el sidebar use flex para empujar el footer hacia abajo */
aside {
    display: flex;
    flex-direction: column;
}

nav {
    flex: 1;
}
</style>