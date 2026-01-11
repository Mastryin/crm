<x-admin::layouts>
    <x-slot:title>
        @lang('Qualification Rules')
    </x-slot:title>

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            @lang('Qualification Rules')
        </p>

        <div class="flex items-center gap-x-2.5">
            <button
                type="button"
                class="primary-button"
                @click="$refs.ruleModal.open()"
            >
                @lang('Create Rule')
            </button>
        </div>
    </div>

    <div class="mt-4">
        <v-qualification-rules ref="rulesList">
            <x-admin::shimmer.datagrid />
        </v-qualification-rules>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-qualification-rules-template">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-4 border-b dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <select v-model="filters.entity_type" @change="loadRules" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">@lang('All Entity Types')</option>
                            <option value="lead">@lang('Lead')</option>
                            <option value="person">@lang('Person')</option>
                        </select>
                        <select v-model="filters.is_active" @change="loadRules" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">@lang('All Status')</option>
                            <option value="1">@lang('Active')</option>
                            <option value="0">@lang('Inactive')</option>
                        </select>
                    </div>
                </div>

                <table class="w-full text-sm text-left">
                    <thead class="text-gray-600 bg-gray-50 dark:bg-gray-900 dark:text-gray-300">
                        <tr>
                            <th class="px-6 py-4 font-semibold">@lang('Priority')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Name')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Field')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Operator')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Value')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Action')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Status')</th>
                            <th class="px-6 py-4 font-semibold text-right">@lang('Actions')</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td colspan="8" class="px-6 py-4 text-center">
                                <x-admin::spinner />
                            </td>
                        </tr>
                        <tr v-else-if="rules.length === 0">
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                @lang('No qualification rules found')
                            </td>
                        </tr>
                        <tr v-else v-for="rule in rules" :key="rule.id" class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 font-medium">
                                    @{{ rule.priority }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-medium">@{{ rule.name }}</td>
                            <td class="px-6 py-4">
                                <code class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">@{{ rule.field_name }}</code>
                            </td>
                            <td class="px-6 py-4">@{{ formatOperator(rule.operator) }}</td>
                            <td class="px-6 py-4">@{{ rule.field_value || '-' }}</td>
                            <td class="px-6 py-4">
                                <span :class="getActionClass(rule.action)" class="px-2 py-1 text-xs rounded-full">
                                    @{{ formatAction(rule.action) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <button @click="toggleStatus(rule)" :class="rule.is_active ? 'bg-green-500' : 'bg-gray-300'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span :class="rule.is_active ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="editRule(rule)" class="text-blue-600 hover:text-blue-900 mr-3">
                                    @lang('Edit')
                                </button>
                                <button @click="deleteRule(rule.id)" class="text-red-600 hover:text-red-900">
                                    @lang('Delete')
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </script>

        <script type="module">
            app.component('v-qualification-rules', {
                template: '#v-qualification-rules-template',

                data() {
                    return {
                        isLoading: true,
                        rules: [],
                        filters: {
                            entity_type: '',
                            is_active: '',
                        },
                    };
                },

                mounted() {
                    this.loadRules();
                },

                methods: {
                    async loadRules() {
                        this.isLoading = true;
                        try {
                            const response = await axios.get('{{ route("admin.educrm.qualification.rules") }}', {
                                params: this.filters,
                                headers: { 'Accept': 'application/json' }
                            });
                            this.rules = response.data.data || [];
                        } catch (error) {
                            console.error('Failed to load rules:', error);
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    formatOperator(operator) {
                        const operators = {
                            equals: 'Equals',
                            not_equals: 'Not Equals',
                            contains: 'Contains',
                            not_contains: 'Not Contains',
                            greater_than: 'Greater Than',
                            less_than: 'Less Than',
                            in: 'In List',
                            not_in: 'Not In List',
                            is_empty: 'Is Empty',
                            is_not_empty: 'Is Not Empty',
                        };
                        return operators[operator] || operator;
                    },

                    formatAction(action) {
                        const actions = {
                            qualify: 'Qualify',
                            disqualify: 'Disqualify',
                            score_add: 'Add Score',
                            score_subtract: 'Subtract Score',
                            set_status: 'Set Status',
                            assign_tag: 'Assign Tag',
                        };
                        return actions[action] || action;
                    },

                    getActionClass(action) {
                        const classes = {
                            qualify: 'bg-green-100 text-green-800',
                            disqualify: 'bg-red-100 text-red-800',
                            score_add: 'bg-blue-100 text-blue-800',
                            score_subtract: 'bg-yellow-100 text-yellow-800',
                            set_status: 'bg-gray-100 text-gray-800',
                            assign_tag: 'bg-blue-100 text-blue-800',
                        };
                        return classes[action] || 'bg-gray-100 text-gray-800';
                    },

                    async toggleStatus(rule) {
                        try {
                            await axios.patch(`/admin/educrm/qualification/rules/${rule.id}/toggle`);
                            rule.is_active = !rule.is_active;
                            this.$emitter.emit('add-flash', { type: 'success', message: 'Rule status updated' });
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: 'Failed to update rule status' });
                        }
                    },

                    editRule(rule) {
                        this.$emitter.emit('edit-rule', rule);
                    },

                    async deleteRule(id) {
                        if (!confirm('Are you sure you want to delete this rule?')) return;

                        try {
                            await axios.delete(`/admin/educrm/qualification/rules/${id}`);
                            this.loadRules();
                            this.$emitter.emit('add-flash', { type: 'success', message: 'Rule deleted successfully' });
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.error || 'Failed to delete rule' });
                        }
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
