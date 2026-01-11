<x-admin::layouts>
    <x-slot:title>
        @lang('Programs')
    </x-slot:title>

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            @lang('Programs')
        </p>

        <div class="flex items-center gap-x-2.5">
            <button
                type="button"
                class="primary-button"
                @click="$refs.programModal.open()"
            >
                @lang('Create Program')
            </button>
        </div>
    </div>

    <div class="mt-4">
        <v-programs-list ref="programsList">
            <x-admin::shimmer.datagrid />
        </v-programs-list>
    </div>

    <x-admin::form
        v-slot="{ meta, errors, handleSubmit }"
        as="div"
        ref="programModal"
    >
        <form @submit="handleSubmit($event, saveProgram)">
            <x-admin::modal ref="programModal">
                <x-slot:header>
                    <p class="text-lg font-bold text-gray-800 dark:text-white">
                        @lang('Program Details')
                    </p>
                </x-slot:header>

                <x-slot:content>
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('Name')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            name="name"
                            rules="required"
                            :label="trans('Name')"
                            :placeholder="trans('Enter program name')"
                        />

                        <x-admin::form.control-group.error control-name="name" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('Description')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="textarea"
                            name="description"
                            :placeholder="trans('Enter program description')"
                        />
                    </x-admin::form.control-group>

                    <div class="flex gap-4">
                        <x-admin::form.control-group class="flex-1">
                            <x-admin::form.control-group.label class="required">
                                @lang('Price')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="number"
                                name="price"
                                rules="required|min:0"
                                :label="trans('Price')"
                                :placeholder="trans('0.00')"
                            />

                            <x-admin::form.control-group.error control-name="price" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group class="flex-1">
                            <x-admin::form.control-group.label>
                                @lang('Duration (weeks)')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="number"
                                name="duration_weeks"
                                :placeholder="trans('12')"
                            />
                        </x-admin::form.control-group>
                    </div>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.control
                            type="checkbox"
                            name="is_active"
                            value="1"
                            :label="trans('Active')"
                        />
                        <label class="ml-2">@lang('Active')</label>
                    </x-admin::form.control-group>
                </x-slot:content>

                <x-slot:footer>
                    <div class="flex items-center gap-x-2.5">
                        <button
                            type="button"
                            class="transparent-button"
                            @click="$refs.programModal.close()"
                        >
                            @lang('Cancel')
                        </button>

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('Save')
                        </button>
                    </div>
                </x-slot:footer>
            </x-admin::modal>
        </form>
    </x-admin::form>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-programs-list-template">
            <div class="table-responsive box-shadow">
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-600 bg-gray-50 dark:bg-gray-900 dark:text-gray-300">
                        <tr>
                            <th class="px-6 py-4 font-semibold">@lang('Name')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Price')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Duration')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Cohorts')</th>
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
                        <tr v-else-if="programs.length === 0">
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                @lang('No programs found')
                            </td>
                        </tr>
                        <tr v-else v-for="program in programs" :key="program.id" class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4 font-medium">@{{ program.name }}</td>
                            <td class="px-6 py-4">@{{ formatPrice(program.price, program.currency) }}</td>
                            <td class="px-6 py-4">@{{ program.duration_weeks }} weeks</td>
                            <td class="px-6 py-4">@{{ program.cohorts?.length || 0 }}</td>
                            <td class="px-6 py-4">
                                <span :class="program.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-1 text-xs rounded-full">
                                    @{{ program.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="editProgram(program)" class="text-blue-600 hover:text-blue-900 mr-3">
                                    @lang('Edit')
                                </button>
                                <button @click="deleteProgram(program.id)" class="text-red-600 hover:text-red-900">
                                    @lang('Delete')
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </script>

        <script type="module">
            app.component('v-programs-list', {
                template: '#v-programs-list-template',

                data() {
                    return {
                        isLoading: true,
                        programs: [],
                    };
                },

                mounted() {
                    this.loadPrograms();
                },

                methods: {
                    async loadPrograms() {
                        this.isLoading = true;
                        try {
                            const response = await axios.get('{{ route("admin.educrm.programs.index") }}', {
                                headers: { 'Accept': 'application/json' }
                            });
                            this.programs = response.data.data || [];
                        } catch (error) {
                            console.error('Failed to load programs:', error);
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    formatPrice(price, currency = 'INR') {
                        return new Intl.NumberFormat('en-IN', {
                            style: 'currency',
                            currency: currency
                        }).format(price);
                    },

                    editProgram(program) {
                        this.$emitter.emit('edit-program', program);
                    },

                    async deleteProgram(id) {
                        if (!confirm('Are you sure you want to delete this program?')) return;

                        try {
                            await axios.delete(`/admin/educrm/programs/${id}`);
                            this.loadPrograms();
                            this.$emitter.emit('add-flash', { type: 'success', message: 'Program deleted successfully' });
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.error || 'Failed to delete program' });
                        }
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
