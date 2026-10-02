<template>
  <div class="rounded-2xl border p-5 text-center shadow-sm" :class="bgGradient">
    <p class="text-xs font-semibold uppercase tracking-wider" :class="labelColor">{{ label }}</p>
    <div class="mt-3 flex items-center justify-center gap-2">
      <span class="text-2xl">{{ icon }}</span>
      <p class="text-3xl font-black" :class="valueColor">{{ value }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({
  label: String,
  value: [String, Number],
  color: { type: String, default: 'blue' },
  icon:  String,
})

const bgGradient = computed(() => ({
  blue:   'bg-gradient-to-br from-blue-500 to-indigo-600 border-blue-600',
  purple: 'bg-gradient-to-br from-purple-500 to-violet-700 border-purple-600',
  red:    'bg-gradient-to-br from-red-500 to-rose-600 border-red-600',
  green:  'bg-gradient-to-br from-emerald-500 to-teal-600 border-emerald-600',
}[props.color] ?? 'bg-gradient-to-br from-gray-500 to-gray-700 border-gray-600'))

const valueColor = computed(() => 'text-white')

const labelColor = computed(() => 'text-white/80')
</script>
