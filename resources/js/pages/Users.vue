<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-gray-800">User Management</h2>
        <p class="text-sm text-gray-500 mt-0.5">Manage users, roles, and branch assignments</p>
      </div>
      <button @click="openModal(null)" class="btn-primary flex items-center gap-2">
        <PlusIcon class="w-4 h-4" /> Add User
      </button>
    </div>

    <!-- Filters -->
    <div class="card flex gap-3 flex-wrap">
      <input v-model="search" placeholder="Search name or email…" class="form-input flex-1 min-w-48" @input="load" />
      <select v-model="filterRole" class="form-input w-40" @change="load">
        <option value="">All Roles</option>
        <option value="admin">Admin</option>
        <option value="manager">Manager</option>
        <option value="accountant">Accountant</option>
        <option value="hr">HR</option>
        <option value="finance">Finance</option>
        <option value="cashier">Cashier</option>
        <option value="branch">Branch</option>
        <option value="auditor">Tax Auditor</option>
        <option value="gold_buyer">Gold Buyer</option>
      </select>
      <SearchableSelect v-model="filterBranch" :options="branches"
        placeholder="All Branches" class="w-48" @update:modelValue="load" />
    </div>

    <!-- Table -->
    <div class="card p-0 overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="table-th">Name</th>
            <th class="table-th">Email</th>
            <th class="table-th">Role</th>
            <th class="table-th">Branch</th>
            <th class="table-th">Permissions</th>
            <th class="table-th">Status</th>
            <th class="table-th text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50">
            <td class="table-td font-medium">{{ u.name }}</td>
            <td class="table-td text-gray-500 text-sm">{{ u.email }}</td>
            <td class="table-td">
              <span :class="roleBadgeClass(u.role)" class="badge">
                {{ roleLabel(u.role) }}
              </span>
            </td>
            <td class="table-td text-sm text-gray-600">{{ u.branch?.name ?? '—' }}</td>
            <td class="table-td">
              <div class="flex gap-1 flex-wrap">
                <span v-if="u.can_override_gold_rate" class="badge bg-blue-100 text-blue-700 text-xs">Rate Override</span>
                <span v-if="u.can_delete_transactions" class="badge bg-red-100 text-red-700 text-xs">Delete Txn</span>
                <span v-if="!u.can_override_gold_rate && !u.can_delete_transactions && u.role !== 'admin'" class="text-xs text-gray-400">Standard</span>
              </div>
            </td>
            <td class="table-td">
              <span :class="u.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="badge">
                {{ u.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="table-td text-right">
              <div class="flex justify-end gap-1.5">
                <button @click="openModal(u)"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-700 hover:bg-blue-200">
                  <PencilSquareIcon class="w-3.5 h-3.5" /> Edit
                </button>
                <button v-if="u.id !== authUser?.id" @click="deleteUser(u)"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-red-100 text-red-700 hover:bg-red-200">
                  <TrashIcon class="w-3.5 h-3.5" /> Delete
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!users.length">
            <td colspan="7" class="table-td text-center text-gray-400 py-8">No users found</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col">

        <!-- Header -->
        <div class="flex items-center justify-between px-8 py-5 border-b shrink-0">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ editing ? 'Edit User' : 'Add User' }}</h3>
            <p class="text-xs text-gray-400 mt-0.5">{{ editing ? 'Update account details and feature access' : 'Create a new user account and assign feature access' }}</p>
          </div>
          <button @click="showModal = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- 2-column body -->
        <form @submit.prevent="save" class="flex flex-1 min-h-0">

          <!-- LEFT — User details -->
          <div class="w-80 shrink-0 border-r flex flex-col">
            <div class="overflow-y-auto flex-1 px-8 py-6 space-y-5">
              <div>
                <label class="form-label">Full Name <span class="text-red-500">*</span></label>
                <input v-model="form.name" required class="form-input" placeholder="John Silva" />
              </div>
              <div>
                <label class="form-label">Username <span class="text-red-500">*</span></label>
                <input v-model="form.username" required class="form-input" placeholder="john_silva"
                  pattern="[a-zA-Z0-9_\-]+" title="Letters, numbers, underscores and hyphens only" />
                <p class="text-xs text-gray-400 mt-1">Used to log in. Letters, numbers, _ and - only.</p>
              </div>
              <div>
                <label class="form-label">Email <span class="text-red-500">*</span></label>
                <input v-model="form.email" type="email" required class="form-input" placeholder="john@sirilmotors.com" />
              </div>
              <div>
                <label class="form-label">{{ editing ? 'New Password' : 'Password' }} <span class="text-red-500">*</span></label>
                <input v-model="form.password" type="password" :required="!editing" minlength="6"
                  class="form-input" :placeholder="editing ? 'Leave blank to keep current' : 'Min. 6 characters'" />
              </div>
              <div>
                <label class="form-label">Role <span class="text-red-500">*</span></label>
                <select v-model="form.role" required class="form-input">
                  <option value="admin">Admin</option>
                  <option value="manager">Manager</option>
                  <option value="accountant">Accountant</option>
                  <option value="hr">HR</option>
                  <option value="finance">Finance</option>
                  <option value="cashier">Cashier</option>
                  <option value="branch">Branch User</option>
                  <option value="auditor">Tax Auditor</option>
                  <option value="gold_buyer">Gold Buyer</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">Features will reload based on role</p>
              </div>
              <div>
                <label class="form-label">Branch</label>
                <SearchableSelect v-model="form.branch_id" :options="branchOptions" placeholder="— None —" />
              </div>
              <div class="pt-1">
                <label class="flex items-center gap-3 cursor-pointer select-none group">
                  <input type="checkbox" v-model="form.is_active" class="w-4 h-4 rounded text-green-500" />
                  <div>
                    <p class="text-sm font-medium text-gray-700">Active account</p>
                    <p class="text-xs text-gray-400">Inactive users cannot log in</p>
                  </div>
                </label>
              </div>
            </div>

            <!-- Footer buttons -->
            <div class="px-8 py-5 border-t shrink-0 space-y-2">
              <p v-if="formError" class="text-xs text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg">{{ formError }}</p>
              <div class="flex gap-2">
                <button type="button" @click="showModal = false" class="btn-secondary flex-1 text-sm">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1 text-sm">
                  {{ saving ? 'Saving…' : (editing ? 'Update User' : 'Create User') }}
                </button>
              </div>
            </div>
          </div>

          <!-- RIGHT — Feature access -->
          <div class="flex-1 flex flex-col min-w-0">
            <!-- Right header -->
            <div class="px-8 py-4 border-b shrink-0 flex items-center justify-between bg-gray-50 rounded-tr-2xl">
              <div>
                <p class="text-sm font-semibold text-gray-700">Feature Access</p>
                <p class="text-xs text-gray-400 mt-0.5">
                  {{ featureList.filter(f => f.enabled).length }} of {{ featureList.length }} features enabled
                </p>
              </div>
              <div v-if="!featuresLoading" class="flex items-center gap-1">
                <button type="button"
                  class="px-3 py-1 text-xs font-medium rounded-md border border-blue-200 text-blue-600 bg-blue-50 hover:bg-blue-100 transition-colors"
                  @click="featureList.forEach(f => f.enabled = true)">All on</button>
                <button type="button"
                  class="px-3 py-1 text-xs font-medium rounded-md border border-gray-200 text-gray-500 bg-white hover:bg-gray-100 transition-colors"
                  @click="featureList.forEach(f => f.enabled = false)">All off</button>
              </div>
              <span v-else class="text-xs text-gray-400 animate-pulse">Loading features…</span>
            </div>

            <!-- Feature groups -->
            <div class="overflow-y-auto flex-1 px-8 py-5 space-y-5">
              <div v-if="featuresLoading" class="flex items-center justify-center h-32 text-gray-400 text-sm">
                <svg class="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Loading…
              </div>

              <template v-else>
                <div v-for="group in featureGroups" :key="group.key">
                  <div class="flex items-center gap-2 mb-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ group.label }}</p>
                    <div class="flex-1 h-px bg-gray-100"></div>
                    <span class="text-xs text-gray-400">
                      {{ group.items.filter(f => f.enabled).length }}/{{ group.items.length }}
                    </span>
                  </div>
                  <div class="grid grid-cols-2 gap-1.5">
                    <label v-for="feat in group.items" :key="feat.id"
                      class="flex items-center gap-2.5 px-3 py-2 rounded-lg cursor-pointer select-none border transition-all"
                      :class="feat.enabled
                        ? 'bg-blue-50 border-blue-200 text-blue-800'
                        : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300 hover:bg-gray-50'">
                      <input type="checkbox" v-model="feat.enabled"
                        class="w-3.5 h-3.5 rounded text-blue-600 shrink-0" />
                      <span class="text-xs font-medium truncate">{{ feat.label }}</span>
                    </label>
                  </div>
                </div>
              </template>
            </div>
          </div>

        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import axios from 'axios'
import SearchableSelect from '@/components/SearchableSelect.vue'

const auth     = useAuthStore()
const authUser = auth.user

const users       = ref([])
const branches    = ref([])
const branchOptions = computed(() => [{ id: null, name: '— None —' }, ...branches.value])
const showModal   = ref(false)
const editing     = ref(null)
const saving      = ref(false)
const formError   = ref('')
const search      = ref('')
const filterRole   = ref('')
const filterBranch = ref('')

const form = reactive({
  name: '', username: '', email: '', password: '', role: 'branch', branch_id: null,
  can_override_gold_rate: false, can_delete_transactions: false, is_active: true,
})

const featureList    = ref([])   // { id, key, label, group, enabled }
const featuresLoading = ref(false)

const GROUP_LABELS = {
  general: 'Main', purchasing: 'Purchasing', admin: 'Admin',
  hr: 'Human Resources', finance: 'Finance', accounting: 'Accounting',
}
const GROUP_ORDER = ['general', 'purchasing', 'admin', 'hr', 'finance', 'accounting']

const featureGroups = computed(() => GROUP_ORDER.map(key => ({
  key,
  label: GROUP_LABELS[key],
  items: featureList.value.filter(f => f.group === key),
})).filter(g => g.items.length))

async function loadFeaturesForRole(role) {
  featuresLoading.value = true
  try {
    const { data } = await axios.get(`/api/features/by-role/${role}`)
    featureList.value = data.map(f => ({ ...f, enabled: !!f.enabled }))
  } finally {
    featuresLoading.value = false
  }
}

async function loadFeaturesForUser(userId) {
  featuresLoading.value = true
  try {
    const { data } = await axios.get(`/api/users/${userId}/features`)
    featureList.value = data.map(f => ({ ...f, enabled: !!f.enabled }))
  } finally {
    featuresLoading.value = false
  }
}

watch(() => form.role, (role) => {
  if (showModal.value) loadFeaturesForRole(role)
})

async function load() {
  const { data } = await axios.get('/api/users', { params: {
    search: search.value, role: filterRole.value, branch_id: filterBranch.value
  }})
  users.value = data.data
}

function openModal(user) {
  editing.value   = user
  formError.value = ''
  featureList.value = []
  Object.assign(form, {
    name: user?.name ?? '', username: user?.username ?? '', email: user?.email ?? '', password: '',
    role: user?.role ?? 'branch', branch_id: user?.branch_id ?? null,
    can_override_gold_rate: user?.can_override_gold_rate ?? false,
    can_delete_transactions: user?.can_delete_transactions ?? false,
    is_active: user?.is_active ?? true,
  })
  showModal.value = true
  if (user) {
    loadFeaturesForUser(user.id)
  } else {
    loadFeaturesForRole(form.role)
  }
}

async function save() {
  saving.value = true; formError.value = ''
  try {
    let userId
    if (editing.value) {
      await axios.put(`/api/users/${editing.value.id}`, form)
      userId = editing.value.id
    } else {
      const { data } = await axios.post('/api/users', form)
      userId = data.id
    }
    // Save per-user feature overrides
    const enabledIds = featureList.value.filter(f => f.enabled).map(f => f.id)
    await axios.put(`/api/users/${userId}/features`, { feature_ids: enabledIds })

    showModal.value = false
    load()
  } catch (e) {
    formError.value = e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {}).flat().join(', ')
  } finally { saving.value = false }
}

async function deleteUser(user) {
  if (!confirm(`Delete user "${user.name}"? This cannot be undone.`)) return
  try {
    await axios.delete(`/api/users/${user.id}`)
    load()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Error deleting user')
  }
}

onMounted(async () => {
  const [, b] = await Promise.all([load(), axios.get('/api/branches')])
  branches.value = b.data
})

const ROLE_LABELS = {
  admin: 'Admin', manager: 'Manager', accountant: 'Accountant',
  hr: 'HR', finance: 'Finance', cashier: 'Cashier',
  branch: 'Branch', auditor: 'Tax Auditor', gold_buyer: 'Gold Buyer',
}
function roleLabel(role) { return ROLE_LABELS[role] ?? role }

function roleBadgeClass(role) {
  const classes = {
    admin: 'bg-purple-100 text-purple-700',
    manager: 'bg-indigo-100 text-indigo-700',
    accountant: 'bg-cyan-100 text-cyan-700',
    hr: 'bg-pink-100 text-pink-700',
    finance: 'bg-emerald-100 text-emerald-700',
    cashier: 'bg-amber-100 text-amber-700',
    branch: 'bg-blue-100 text-blue-700',
    auditor: 'bg-orange-100 text-orange-700',
    gold_buyer: 'bg-yellow-100 text-yellow-800',
  }
  return classes[role] ?? 'bg-gray-100 text-gray-700'
}
</script>
