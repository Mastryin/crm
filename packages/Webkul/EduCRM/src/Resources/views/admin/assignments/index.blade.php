<x-admin::layouts>
    <x-slot:title>
        @lang('Lead Assignments')
    </x-slot:title>

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            @lang('Lead Assignments')
        </p>
    </div>

    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
        @lang('Manage round-robin lead assignment settings and view team workload distribution.')
    </p>

    <div class="mt-6">
        <v-assignment-stats></v-assignment-stats>
    </div>

    <div class="mt-6">
        <v-assignment-users ref="usersList"></v-assignment-users>
    </div>

    @pushOnce('scripts')
        <script type="text-x-template" id="v-assignment-stats-template">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <p class="text-sm text-gray-500 dark:text-gray-400">@lang('Total Users')</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white">@{{ stats.total_users }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <p class="text-sm text-gray-500 dark:text-gray-400">@lang('Available')</p>
                    <p class="text-2xl font-bold text-green-600">@{{ stats.available_users }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <p class="text-sm text-gray-500 dark:text-gray-400">@lang('Current Load')</p>
                    <p class="text-2xl font-bold text-blue-600">@{{ stats.total_current_load }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <p class="text-sm text-gray-500 dark:text-gray-400">@lang('Total Capacity')</p>
                    <p class="text-2xl font-bold text-gray-600">@{{ stats.total_capacity }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                    <p class="text-sm text-gray-500 dark:text-gray-400">@lang('Avg Utilization')</p>
                    <p class="text-2xl font-bold" :class="getUtilizationClass(stats.average_utilization)">@{{ stats.average_utilization }}%</p>
                </div>
            </div>
        </script>

        <script type="text/x-template" id="v-assignment-users-template">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-4 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">@lang('Team Workload')</h3>
                </div>

                <div v-if="isLoading" class="p-8 text-center">
                    <x-admin::spinner />
                </div>

                <div v-else-if="users.length === 0" class="p-8 text-center text-gray-500">
                    @lang('No users found with assignment settings.')
                </div>

                <table v-else class="w-full text-sm text-left">
                    <thead class="text-gray-600 bg-gray-50 dark:bg-gray-900 dark:text-gray-300">
                        <tr>
                            <th class="px-6 py-4 font-semibold">@lang('User')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Current Load')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Max Load')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Today')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Daily Limit')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Utilization')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Status')</th>
                            <th class="px-6 py-4 font-semibold text-right">@lang('Actions')</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id" class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                        <span class="text-sm font-medium">@{{ getInitials(user.name) }}</span>
                                    </div>
                                    <div>
                                        <div class="font-medium">@{{ user.name }}</div>
                                        <div class="text-xs text-gray-500">@{{ user.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium">@{{ user.current_load }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <input
                                    type="number"
                                    v-model.number="user.max_load"
                                    @change="updateUserSettings(user)"
                                    class="w-20 px-2 py-1 border rounded dark:bg-gray-700 dark:border-gray-600"
                                    min="1"
                                />
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-medium">@{{ user.today_assignments }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <input
                                    type="number"
                                    v-model.number="user.daily_limit"
                                    @change="updateUserSettings(user)"
                                    class="w-20 px-2 py-1 border rounded dark:bg-gray-700 dark:border-gray-600"
                                    min="1"
                                    placeholder="No limit"
                                />
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div
                                            class="h-full transition-all"
                                            :class="getUtilizationBarClass(user.utilization)"
                                            :style="{ width: user.utilization + '%' }"
                                        ></div>
                                    </div>
                                    <span class="text-sm" :class="getUtilizationClass(user.utilization)">@{{ user.utilization }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <button @click="toggleAvailability(user)" :class="user.is_available ? 'bg-green-500' : 'bg-gray-300'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                    <span :class="user.is_available ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="viewUserLeads(user)" class="text-blue-600 hover:text-blue-900">
                                    @lang('View Leads')
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </script>

        <script type="module">
            app.component('v-assignment-stats', {
                template: '#v-assignment-stats-template',

                data() {
                    return {
                        stats: {
                            total_users: 0,
                            available_users: 0,
                            total_current_load: 0,
                            total_capacity: 0,
                            average_utilization: 0,
                        },
                    };
                },

                mounted() {
                    this.loadStats();
                },

                methods: {
                    async loadStats() {
                        try {
                            const response = await axios.get('{{ route("admin.educrm.assignments.stats") }}', {
                                headers: { 'Accept': 'application/json' }
                            });
                            this.stats = response.data.summary || this.stats;
                        } catch (error) {
                            console.error('Failed to load stats:', error);
                        }
                    },

                    getUtilizationClass(utilization) {
                        if (utilization >= 90) return 'text-red-600';
                        if (utilization >= 70) return 'text-yellow-600';
                        return 'text-green-600';
                    }
                }
            });

            app.component('v-assignment-users', {
                template: '#v-assignment-users-template',

                data() {
                    return {
                        isLoading: true,
                        users: [],
                    };
                },

                mounted() {
                    this.loadUsers();
                },

                methods: {
                    async loadUsers() {
                        this.isLoading = true;
                        try {
                            const response = await axios.get('{{ route("admin.educrm.assignments.stats") }}', {
                                headers: { 'Accept': 'application/json' }
                            });
                            this.users = response.data.data || [];
                        } catch (error) {
                            console.error('Failed to load users:', error);
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    getInitials(name) {
                        return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                    },

                    getUtilizationClass(utilization) {
                        if (utilization >= 90) return 'text-red-600';
                        if (utilization >= 70) return 'text-yellow-600';
                        return 'text-green-600';
                    },

                    getUtilizationBarClass(utilization) {
                        if (utilization >= 90) return 'bg-red-500';
                        if (utilization >= 70) return 'bg-yellow-500';
                        return 'bg-green-500';
                    },

                    async toggleAvailability(user) {
                        try {
                            await axios.patch(`/admin/educrm/assignments/users/${user.id}`, {
                                is_available: !user.is_available
                            });
                            user.is_available = !user.is_available;
                            this.$emitter.emit('add-flash', { type: 'success', message: 'User availability updated' });
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: 'Failed to update availability' });
                        }
                    },

                    async updateUserSettings(user) {
                        try {
                            await axios.patch(`/admin/educrm/assignments/users/${user.id}`, {
                                max_load: user.max_load,
                                daily_limit: user.daily_limit,
                            });
                            this.$emitter.emit('add-flash', { type: 'success', message: 'User settings updated' });
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: 'Failed to update settings' });
                        }
                    },

                    viewUserLeads(user) {
                        window.location.href = `/admin/leads?user_id=${user.id}`;
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
