<x-admin::layouts>
    <x-slot:title>
        @lang('Negative Keywords')
    </x-slot:title>

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            @lang('Negative Keywords')
        </p>

        <div class="flex items-center gap-x-2.5">
            <button
                type="button"
                class="primary-button"
                @click="$refs.keywordModal.open()"
            >
                @lang('Add Keyword')
            </button>
        </div>
    </div>

    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
        @lang('Leads containing these keywords in specified fields will be automatically disqualified.')
    </p>

    <div class="mt-4">
        <v-negative-keywords ref="keywordsList">
            <x-admin::shimmer.datagrid />
        </v-negative-keywords>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-negative-keywords-template">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-4 border-b dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <select v-model="filters.field_name" @change="loadKeywords" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">@lang('All Fields')</option>
                            <option value="job_role">@lang('Job Role')</option>
                            <option value="company">@lang('Company')</option>
                            <option value="email">@lang('Email')</option>
                            <option value="description">@lang('Description')</option>
                        </select>
                        <select v-model="filters.match_type" @change="loadKeywords" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">@lang('All Match Types')</option>
                            <option value="exact">@lang('Exact Match')</option>
                            <option value="contains">@lang('Contains')</option>
                            <option value="starts_with">@lang('Starts With')</option>
                            <option value="ends_with">@lang('Ends With')</option>
                            <option value="regex">@lang('Regex')</option>
                        </select>
                    </div>
                </div>

                <table class="w-full text-sm text-left">
                    <thead class="text-gray-600 bg-gray-50 dark:bg-gray-900 dark:text-gray-300">
                        <tr>
                            <th class="px-6 py-4 font-semibold">@lang('Keyword')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Field')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Match Type')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Reason')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Status')</th>
                            <th class="px-6 py-4 font-semibold text-right">@lang('Actions')</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td colspan="6" class="px-6 py-4 text-center">
                                <x-admin::spinner />
                            </td>
                        </tr>
                        <tr v-else-if="keywords.length === 0">
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                @lang('No negative keywords found')
                            </td>
                        </tr>
                        <tr v-else v-for="keyword in keywords" :key="keyword.id" class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4">
                                <code class="text-sm bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-300 px-2 py-1 rounded">
                                    @{{ keyword.keyword }}
                                </code>
                            </td>
                            <td class="px-6 py-4">@{{ formatFieldName(keyword.field_name) }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                    @{{ formatMatchType(keyword.match_type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 max-w-xs truncate">@{{ keyword.reason || '-' }}</td>
                            <td class="px-6 py-4">
                                <button @click="toggleStatus(keyword)" :class="keyword.is_active ? 'bg-green-500' : 'bg-gray-300'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span :class="keyword.is_active ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="editKeyword(keyword)" class="text-blue-600 hover:text-blue-900 mr-3">
                                    @lang('Edit')
                                </button>
                                <button @click="deleteKeyword(keyword.id)" class="text-red-600 hover:text-red-900">
                                    @lang('Delete')
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </script>

        <script type="module">
            app.component('v-negative-keywords', {
                template: '#v-negative-keywords-template',

                data() {
                    return {
                        isLoading: true,
                        keywords: [],
                        filters: {
                            field_name: '',
                            match_type: '',
                        },
                    };
                },

                mounted() {
                    this.loadKeywords();
                },

                methods: {
                    async loadKeywords() {
                        this.isLoading = true;
                        try {
                            const response = await axios.get('{{ route("admin.educrm.qualification.negative-keywords") }}', {
                                params: this.filters,
                                headers: { 'Accept': 'application/json' }
                            });
                            this.keywords = response.data.data || [];
                        } catch (error) {
                            console.error('Failed to load keywords:', error);
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    formatFieldName(field) {
                        const fields = {
                            job_role: 'Job Role',
                            company: 'Company',
                            email: 'Email',
                            description: 'Description',
                        };
                        return fields[field] || field;
                    },

                    formatMatchType(type) {
                        const types = {
                            exact: 'Exact Match',
                            contains: 'Contains',
                            starts_with: 'Starts With',
                            ends_with: 'Ends With',
                            regex: 'Regex',
                        };
                        return types[type] || type;
                    },

                    async toggleStatus(keyword) {
                        try {
                            await axios.patch(`/admin/educrm/qualification/negative-keywords/${keyword.id}/toggle`);
                            keyword.is_active = !keyword.is_active;
                            this.$emitter.emit('add-flash', { type: 'success', message: 'Keyword status updated' });
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: 'Failed to update keyword status' });
                        }
                    },

                    editKeyword(keyword) {
                        this.$emitter.emit('edit-keyword', keyword);
                    },

                    async deleteKeyword(id) {
                        if (!confirm('Are you sure you want to delete this keyword?')) return;

                        try {
                            await axios.delete(`/admin/educrm/qualification/negative-keywords/${id}`);
                            this.loadKeywords();
                            this.$emitter.emit('add-flash', { type: 'success', message: 'Keyword deleted successfully' });
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.error || 'Failed to delete keyword' });
                        }
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
