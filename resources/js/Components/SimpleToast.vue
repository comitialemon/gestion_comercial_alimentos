<template>
  <div class="fixed bottom-4 right-4 z-50 space-y-2">
    <div
      v-for="toast in toasts"
      :key="toast.id"
      class="min-w-[280px] max-w-sm rounded-lg shadow-lg p-4 flex items-start gap-3 animate-slide-in"
      :class="{
        'bg-emerald-500': toast.type === 'success',
        'bg-red-500': toast.type === 'error',
        'bg-amber-500': toast.type === 'warning',
        'bg-blue-500': toast.type === 'info'
      }"
    >
      <i :class="iconClass(toast.type)" class="text-white text-xl"></i>
      <div class="flex-1 text-white text-sm">
        <p class="font-medium">{{ toast.title }}</p>
        <p v-if="toast.message" class="text-xs opacity-90 mt-1">{{ toast.message }}</p>
      </div>
      <button @click="removeToast(toast.id)" class="text-white opacity-70 hover:opacity-100">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const toasts = ref([])
let nextId = 1

const iconClass = (type) => {
  return {
    success: 'fas fa-check-circle',
    error: 'fas fa-exclamation-circle',
    warning: 'fas fa-exclamation-triangle',
    info: 'fas fa-info-circle'
  }[type]
}

const addToast = (options) => {
  const id = nextId++
  toasts.value.push({
    id,
    type: options.type || 'info',
    title: options.title || '',
    message: options.message || '',
    duration: options.duration || 3000
  })
  
  setTimeout(() => {
    removeToast(id)
  }, options.duration || 3000)
  
  return id
}

const removeToast = (id) => {
  const index = toasts.value.findIndex(t => t.id === id)
  if (index !== -1) toasts.value.splice(index, 1)
}

// Métodos expuestos
const success = (title, message) => addToast({ type: 'success', title, message, duration: 2000 })
const error = (title, message) => addToast({ type: 'error', title, message, duration: 3000 })
const warning = (title, message) => addToast({ type: 'warning', title, message, duration: 2500 })
const info = (title, message) => addToast({ type: 'info', title, message, duration: 2000 })

defineExpose({ success, error, warning, info })
</script>

<style scoped>
.animate-slide-in {
  animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}
</style>