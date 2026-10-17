<template>
  <div class="relative" ref="container">
    <!-- Input trigger -->
    <div
      class="form-input flex items-center gap-2 cursor-text min-h-[38px] py-1.5"
      @click="open"
    >
      <input
        ref="inputRef"
        v-model="search"
        :placeholder="placeholder"
        v-show="isOpen || !selectedLabel"
        class="flex-1 outline-none bg-transparent text-sm min-w-0 border-0 p-0 m-0"
        @focus="isOpen = true"
        @input="onInput"
        @keydown.escape="close"
        @keydown.enter.prevent="pickFirst"
        @keydown.backspace="onBackspace"
        autocomplete="off"
      />
      <span v-if="selectedLabel && !isOpen" class="flex-1 text-sm text-gray-800 truncate">{{ selectedLabel }}</span>
      <button v-if="modelValue" type="button" @mousedown.prevent="clear"
        class="text-gray-400 hover:text-gray-600 shrink-0 leading-none text-base">✕</button>
      <svg v-else class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </div>

    <!-- Dropdown — teleported to body so overflow:hidden parents can't clip it -->
    <Teleport to="body">
      <ul v-if="isOpen && isPositioned"
        class="fixed z-[9999] bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto text-sm"
        :style="dropdownStyle">
        <li v-if="loading" class="px-3 py-2 text-gray-400 text-xs">Searching…</li>
        <li v-else-if="!filteredOptions.length" class="px-3 py-2 text-gray-400 text-xs">
          {{ asyncSearch && !search ? 'Type to search…' : 'No results found' }}
        </li>
        <li
          v-for="opt in filteredOptions" :key="opt[valueKey]"
          @mousedown.prevent="select(opt)"
          class="px-3 py-2 hover:bg-blue-50 cursor-pointer flex items-center justify-between gap-2"
          :class="opt[valueKey] == modelValue ? 'bg-blue-50 font-medium text-blue-700' : 'text-gray-700'"
        >
          <span class="truncate">{{ opt[labelKey] }}</span>
          <span v-if="opt.sub" class="text-xs text-gray-400 shrink-0 truncate max-w-[45%]">{{ opt.sub }}</span>
        </li>
      </ul>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue:  { default: '' },
  options:     { type: Array, default: () => [] },
  labelKey:    { type: String, default: 'name' },
  valueKey:    { type: String, default: 'id' },
  placeholder: { type: String, default: '— Select —' },
  searchKeys:  { type: Array, default: () => [] },
  asyncSearch: { type: Function, default: null },
})
const emit = defineEmits(['update:modelValue', 'select'])

const isOpen       = ref(false)
const isPositioned = ref(false)
const search       = ref('')
const container    = ref(null)
const inputRef     = ref(null)
const dropdownPos  = ref({ top: 0, left: 0, width: 200 })
const asyncOptions = ref([])
const loading      = ref(false)
let debounceTimer  = null

const dropdownStyle = computed(() => ({
  top:   dropdownPos.value.top  + 'px',
  left:  dropdownPos.value.left + 'px',
  width: dropdownPos.value.width + 'px',
}))

const selectedLabel = computed(() => {
  if (!props.modelValue && props.modelValue !== 0) return ''
  // check async cache first, then static options
  const pool = props.asyncSearch ? asyncOptions.value : props.options
  const opt = pool.find(o => o[props.valueKey] == props.modelValue)
  return opt ? opt[props.labelKey] : ''
})

const filteredOptions = computed(() => {
  if (props.asyncSearch) return asyncOptions.value
  const q = search.value.trim().toLowerCase()
  if (!q) return props.options
  return props.options.filter(o =>
    String(o[props.labelKey]).toLowerCase().includes(q) ||
    (o.sub && String(o.sub).toLowerCase().includes(q)) ||
    props.searchKeys.some(k => o[k] && String(o[k]).toLowerCase().includes(q))
  )
})

function onInput() {
  isOpen.value = true
  if (!props.asyncSearch) return
  const q = search.value.trim()
  clearTimeout(debounceTimer)
  if (!q) {
    asyncOptions.value = []
    loading.value = false
    return
  }
  loading.value = true
  debounceTimer = setTimeout(async () => {
    try {
      asyncOptions.value = await props.asyncSearch(q)
    } finally {
      loading.value = false
    }
  }, 300)
}

function open() {
  search.value      = ''
  isPositioned.value = false
  isOpen.value      = true
  nextTick(() => {
    if (container.value) {
      const rect = container.value.getBoundingClientRect()
      dropdownPos.value = {
        top:   rect.bottom + 4,
        left:  rect.left,
        width: rect.width,
      }
    }
    isPositioned.value = true
    inputRef.value?.focus()
  })
}

function close() {
  isOpen.value       = false
  isPositioned.value = false
  search.value       = ''
  clearTimeout(debounceTimer)
  loading.value = false
}

function select(opt) {
  emit('update:modelValue', opt[props.valueKey])
  emit('select', opt)
  close()
}

function clear() {
  emit('update:modelValue', '')
  emit('select', null)
  search.value = ''
  isOpen.value = false
  if (props.asyncSearch) asyncOptions.value = []
}

function pickFirst() {
  if (filteredOptions.value.length) select(filteredOptions.value[0])
}

function onBackspace() {
  if (!search.value && props.modelValue) {
    emit('update:modelValue', '')
    emit('select', null)
    if (props.asyncSearch) asyncOptions.value = []
  }
}

function onClickOutside(e) {
  if (container.value && !container.value.contains(e.target)) close()
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onClickOutside)
  clearTimeout(debounceTimer)
})
</script>
