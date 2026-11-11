<template>
  <div class="space-y-4">
    <div class="flex justify-end">
      <button @click="openCreate" class="btn-primary flex items-center gap-2">
        <PlusIcon class="w-4 h-4" /> Add Category
      </button>
    </div>

    <div class="card p-0 overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="table-th">Name</th>
            <th class="table-th">Slug</th>
            <th class="table-th">Products</th>
            <th class="table-th">Status</th>
            <th class="table-th">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-100">
          <tr v-for="c in categories.data" :key="c.id" class="hover:bg-gray-50">
            <td class="table-td font-medium">{{ c.name }}</td>
            <td class="table-td text-gray-400 font-mono text-xs">{{ c.slug }}</td>
            <td class="table-td">{{ c.products_count }}</td>
            <td class="table-td">
              <span :class="c.is_active ? 'badge bg-green-100 text-green-700' : 'badge bg-gray-100 text-gray-500'">
                {{ c.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="table-td">
              <div class="flex gap-2">
            <button @click="openEdit(c)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-700 hover:bg-blue-200">
              <PencilSquareIcon class="w-3.5 h-3.5" /> Edit
            </button>
            <button @click="del(c)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-red-100 text-red-700 hover:bg-red-200">
              <TrashIcon class="w-3.5 h-3.5" /> Delete
            </button>
              </div>
            </td>
          </tr>
          <tr v-if="!categories.data?.length">
            <td colspan="5" class="table-td text-center text-gray-400 py-8">No categories</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Inline modal new-->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold mb-4">{{ editing ? 'Edit' : 'Add' }} Category</h3>
        <div class="space-y-3">
          <div>
            <label class="form-label">Name *</label>
            <input v-model="form.name" required class="form-input" />
          </div>
          <div>
            <label class="form-label">Description</label>
            <textarea v-model="form.description" rows="2" class="form-input"></textarea>
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" v-model="form.is_active" class="rounded text-blue-600" /> Active
          </label>
          <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        </div>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="showModal = false" class="btn-secondary">Cancel</button>
          <button @click="save" :disabled="saving" class="btn-primary">{{ saving ? 'Saving…' : 'Save' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'

const categories = ref({ data: [] })
const showModal  = ref(false)
const editing    = ref(null)
const saving     = ref(false)
const error      = ref('')
const form       = reactive({ name: '', description: '', is_active: true })

async function fetch() {
  const { data } = await axios.get('/api/categories', { params: { per_page: 100 } })
  categories.value = data
}

function openCreate() { editing.value = null; Object.assign(form, { name: '', description: '', is_active: true }); showModal.value = true }
function openEdit(c)  { editing.value = c; Object.assign(form, c); showModal.value = true }

async function save() {
  saving.value = true; error.value = ''
  try {
    if (editing.value) await axios.put(`/api/categories/${editing.value.id}`, form)
    else               await axios.post('/api/categories', form)
    showModal.value = false; fetch()
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors ?? {}).flat().join(', ') || 'Error'
  } finally { saving.value = false }
}

async function del(c) {
  if (!confirm(`Delete "${c.name}"?`)) return
  await axios.delete(`/api/categories/${c.id}`)
  fetch()
}

onMounted(fetch)
</script>
