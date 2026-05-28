<script setup>
import { ref } from 'vue'

const props = defineProps({
  items: Array,
  asignados: Array
})

const emit = defineEmits(['update:asignados'])

const expanded = ref({})

props.items.forEach(item => {
  if (item.children && item.children.length) {
    expanded.value[item.id] = true
  }
})

const toggle = (id) => {
  expanded.value[id] = !expanded.value[id]
}

const isChecked = (id) => {
  return props.asignados.includes(id)
}

const toggleCheck = (id, checked) => {
  let nuevosAsignados = [...props.asignados]
  
  if (checked) {
    if (!nuevosAsignados.includes(id)) {
      nuevosAsignados.push(id)
    }
  } else {
    nuevosAsignados = nuevosAsignados.filter(itemId => itemId !== id)
  }
  
  emit('update:asignados', nuevosAsignados)
}

const handleChildUpdate = (nuevosAsignados) => {
  emit('update:asignados', nuevosAsignados)
}
</script>

<template>
  <ul class="menu-tree">
    <li v-for="item in items" :key="item.id">
      <div class="flex items-center">
        <span 
          v-if="item.children && item.children.length"
          class="folder-toggle"
          @click="toggle(item.id)"
        >
          {{ expanded[item.id] ? '📂' : '📁' }}
        </span>
        <span v-else style="width:20px; display:inline-block;"></span>
        
        <label class="flex items-center ml-1">
          <input 
            type="checkbox"
            :checked="isChecked(item.id)"
            @change="toggleCheck(item.id, $event.target.checked)"
            class="mr-2"
          >
          {{ item.title }}
        </label>
      </div>

      <div 
        v-if="item.children && item.children.length"
        class="nested"
        :class="{ active: expanded[item.id] }"
      >
        <MenuTree
          :items="item.children"
          :asignados="asignados"
          @update:asignados="handleChildUpdate"
        />
      </div>
    </li>
  </ul>
</template>

<style scoped>
.menu-tree {
  list-style: none;
  padding-left: 0;
}
.menu-tree li {
  margin: 4px 0;
}
.folder-toggle {
  cursor: pointer;
  user-select: none;
  font-size: 14px;
  margin-right: 4px;
}
.nested {
  display: none;
  margin-left: 20px;
}
.nested.active {
  display: block;
}
</style>