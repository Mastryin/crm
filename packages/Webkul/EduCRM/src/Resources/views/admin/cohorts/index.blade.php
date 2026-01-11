<x-admin::layouts>
    <x-slot:title>
        @lang('Cohorts')
    </x-slot:title>

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            @lang('Cohorts')
        </p>

        <div class="flex items-center gap-x-2.5">
            <button
                type="button"
                class="primary-button"
                @click="$refs.cohortModal.open()"
            >
                @lang('Create Cohort')
            </button>
        </div>
    </div>

    <div class="mt-4">
        <v-cohorts-list ref="cohortsList">
            <x-admin::shimmer.datagrid />
        </v-cohorts-list>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-cohorts-list-template">
            <div class="table-responsive box-shadow">
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-600 bg-gray-50 dark:bg-gray-900 dark:text-gray-300">
                        <tr>
                            <th class="px-6 py-4 font-semibold">@lang('Name')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Program')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Start Date')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Deadline')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Capacity')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Status')</th>
                            <th class="px-6 py-4 font-semibold text-right">@lang('Actions')</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td colspan="7" class="px-6 py-4 text-center">
                                <x-admin::spinner />
                            </td>
                        </tr>
                        <tr v-else-if="cohorts.length === 0">
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                @lang('No cohorts found')
                            </td>
                        </tr>
                        <tr v-else v-for="cohort in cohorts" :key="cohort.id" class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4 font-medium">@{{ cohort.name }}</td>
                            <td class="px-6 py-4">@{{ cohort.program?.name || '-' }}</td>
                            <td class="px-6 py-4">@{{ formatDate(cohort.start_date) }}</td>
                            <td class="px-6 py-4">@{{ formatDate(cohort.application_deadline) }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span>@{{ cohort.enrolled_count }}/@{{ cohort.capacity }}</span>
                                    <div class="w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-blue-500" :style="{ width: getCapacityPercent(cohort) + '%' }"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="getStatusClass(cohort.status)" class="px-2 py-1 text-xs rounded-full">
                                    @{{ cohort.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="viewCohort(cohort)" class="text-blue-600 hover:text-blue-900 mr-3">
                                    @lang('View')
                                </button>
                                <button @click="editCohort(cohort)" class="text-green-600 hover:text-green-900 mr-3">
                                    @lang('Edit')
                                </button>
                                <button @click="deleteCohort(cohort.id)" class="text-red-600 hover:text-red-900">
                                    @lang('Delete')
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </script>

        <script type="module">
            app.component('v-cohorts-list', {
                template: '#v-cohorts-list-template',

                data() {
                    return {
                        isLoading: true,
                        cohorts: [],
                    };
                },

                mounted() {
                    this.loadCohorts();
                },

                methods: {
                    async loadCohorts() {
                        this.isLoading = true;
                        try {
                            const response = await axios.get('{{ route("admin.educrm.cohorts.index") }}', {
                                headers: { 'Accept': 'application/json' }
                            });
                            this.cohorts = response.data.data || [];
                        } catch (error) {
                            console.error('Failed to load cohorts:', error);
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    formatDate(date) {
                        if (!date) return '-';
                        return new Date(date).toLocaleDateString('en-IN', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric'
                        });
                    },

                    getCapacityPercent(cohort) {
                        if (!cohort.capacity) return 0;
                        return Math.min(100, (cohort.enrolled_count / cohort.capacity) * 100);
                    },

                    getStatusClass(status) {
                        const classes = {
                            upcoming: 'bg-blue-100 text-blue-800',
                            active: 'bg-green-100 text-green-800',
                            completed: 'bg-gray-100 text-gray-800',
                            cancelled: 'bg-red-100 text-red-800',
                        };
                        return classes[status] || 'bg-gray-100 text-gray-800';
                    },

                    viewCohort(cohort) {
                        window.location.href = `/admin/educrm/cohorts/${cohort.id}`;
                    },

                    editCohort(cohort) {
                        this.$emitter.emit('edit-cohort', cohort);
                    },

                    async deleteCohort(id) {
                        if (!confirm('Are you sure you want to delete this cohort?')) return;

                        try {
                            await axios.delete(`/admin/educrm/cohorts/${id}`);
                            this.loadCohorts();
                            this.$emitter.emit('add-flash', { type: 'success', message: 'Cohort deleted successfully' });
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.error || 'Failed to delete cohort' });
                        }
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
