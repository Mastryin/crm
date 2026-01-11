<x-admin::layouts>
    <x-slot:title>
        @lang('Automations')
    </x-slot:title>

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            @lang('Status Automations')
        </p>

        <div class="flex items-center gap-x-2.5">
            <button
                type="button"
                class="primary-button"
                @click="$refs.automationModal.open()"
            >
                @lang('Create Automation')
            </button>
        </div>
    </div>

    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
        @lang('Configure automated actions that trigger when leads change status.')
    </p>

    <div class="mt-4">
        <v-automations-list ref="automationsList">
            <x-admin::shimmer.datagrid />
        </v-automations-list>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-automations-list-template">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-4 border-b dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <select v-model="filters.trigger_status" @change="loadAutomations" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">@lang('All Triggers')</option>
                            <option value="qualified">@lang('Qualified')</option>
                            <option value="contacted">@lang('Contacted')</option>
                            <option value="enrolled">@lang('Enrolled')</option>
                            <option value="payment_received">@lang('Payment Received')</option>
                            <option value="disqualified">@lang('Disqualified')</option>
                        </select>
                        <select v-model="filters.is_active" @change="loadAutomations" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">@lang('All Status')</option>
                            <option value="1">@lang('Active')</option>
                            <option value="0">@lang('Inactive')</option>
                        </select>
                    </div>
                </div>

                <div v-if="isLoading" class="p-8 text-center">
                    <x-admin::spinner />
                </div>

                <div v-else-if="automations.length === 0" class="p-8 text-center text-gray-500">
                    @lang('No automations found. Create your first automation to get started.')
                </div>

                <div v-else class="divide-y dark:divide-gray-700">
                    <div v-for="automation in automations" :key="automation.id" class="p-4 hover:bg-gray-50 dark:hover:bg-gray-900">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="font-medium text-gray-900 dark:text-white">@{{ automation.name }}</h3>
                                    <span :class="automation.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'" class="px-2 py-0.5 text-xs rounded-full">
                                        @{{ automation.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <p v-if="automation.description" class="mt-1 text-sm text-gray-500">@{{ automation.description }}</p>

                                <div class="mt-3 flex items-center gap-2 text-sm">
                                    <span class="text-gray-500">When status changes to</span>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded font-medium">@{{ formatStatus(automation.trigger_status) }}</span>
                                    <span class="text-gray-500">then</span>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded font-medium">@{{ formatAction(automation.action_type) }}</span>
                                </div>

                                <div v-if="automation.conditions && Object.keys(automation.conditions).length" class="mt-2">
                                    <span class="text-xs text-gray-400">Conditions:</span>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        <span v-for="(value, key) in automation.conditions" :key="key" class="px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 rounded">
                                            @{{ key }}: @{{ value }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button @click="toggleStatus(automation)" :class="automation.is_active ? 'bg-green-500' : 'bg-gray-300'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span :class="automation.is_active ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                </button>
                                <button @click="editAutomation(automation)" class="p-2 text-blue-600 hover:bg-blue-50 rounded">
                                    <span class="icon-edit text-lg"></span>
                                </button>
                                <button @click="deleteAutomation(automation.id)" class="p-2 text-red-600 hover:bg-red-50 rounded">
                                    <span class="icon-delete text-lg"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-automations-list', {
                template: '#v-automations-list-template',

                data() {
                    return {
                        isLoading: true,
                        automations: [],
                        filters: {
                            trigger_status: '',
                            is_active: '',
                        },
                    };
                },

                mounted() {
                    this.loadAutomations();
                },

                methods: {
                    async loadAutomations() {
                        this.isLoading = true;
                        try {
                            const response = await axios.get('{{ route("admin.educrm.automations.index") }}', {
                                params: this.filters,
                                headers: { 'Accept': 'application/json' }
                            });
                            this.automations = response.data.data || [];
                        } catch (error) {
                            console.error('Failed to load automations:', error);
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    formatStatus(status) {
                        const statuses = {
                            new: 'New',
                            qualified: 'Qualified',
                            contacted: 'Contacted',
                            enrolled: 'Enrolled',
                            payment_received: 'Payment Received',
                            disqualified: 'Disqualified',
                            lost: 'Lost',
                        };
                        return statuses[status] || status;
                    },

                    formatAction(action) {
                        const actions = {
                            send_email: 'Send Email',
                            send_whatsapp: 'Send WhatsApp',
                            send_sms: 'Send SMS',
                            trigger_webhook: 'Trigger Webhook',
                            assign_user: 'Assign User',
                            add_tag: 'Add Tag',
                            update_field: 'Update Field',
                            create_activity: 'Create Activity',
                            schedule_call: 'Schedule Call',
                        };
                        return actions[action] || action;
                    },

                    async toggleStatus(automation) {
                        try {
                            await axios.patch(`/admin/educrm/automations/${automation.id}/toggle`);
                            automation.is_active = !automation.is_active;
                            this.$emitter.emit('add-flash', { type: 'success', message: 'Automation status updated' });
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: 'Failed to update automation status' });
                        }
                    },

                    editAutomation(automation) {
                        this.$emitter.emit('edit-automation', automation);
                    },

                    async deleteAutomation(id) {
                        if (!confirm('Are you sure you want to delete this automation?')) return;

                        try {
                            await axios.delete(`/admin/educrm/automations/${id}`);
                            this.loadAutomations();
                            this.$emitter.emit('add-flash', { type: 'success', message: 'Automation deleted successfully' });
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.error || 'Failed to delete automation' });
                        }
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
