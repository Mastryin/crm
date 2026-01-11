<x-admin::layouts>
    <x-slot:title>
        @lang('Payments')
    </x-slot:title>

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            @lang('Payments')
        </p>

        <div class="flex items-center gap-x-2.5">
            <x-admin::dropdown>
                <x-slot:toggle>
                    <button type="button" class="transparent-button">
                        @lang('Export')
                        <span class="icon-arrow-down text-xl"></span>
                    </button>
                </x-slot:toggle>

                <x-slot:content class="!p-0">
                    <div class="pb-2.5">
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                            @lang('Export CSV')
                        </a>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                            @lang('Export Excel')
                        </a>
                    </div>
                </x-slot:content>
            </x-admin::dropdown>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
        <v-payment-stats></v-payment-stats>
    </div>

    <div class="mt-6">
        <v-payments-list ref="paymentsList">
            <x-admin::shimmer.datagrid />
        </v-payments-list>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-payment-stats-template">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <p class="text-sm text-gray-500 dark:text-gray-400">@lang('Total Revenue')</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white">@{{ formatCurrency(stats.total_revenue) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <p class="text-sm text-gray-500 dark:text-gray-400">@lang('Pending Payments')</p>
                <p class="text-2xl font-bold text-yellow-600">@{{ formatCurrency(stats.pending_amount) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <p class="text-sm text-gray-500 dark:text-gray-400">@lang('Overdue Amount')</p>
                <p class="text-2xl font-bold text-red-600">@{{ formatCurrency(stats.overdue_amount) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <p class="text-sm text-gray-500 dark:text-gray-400">@lang('This Month')</p>
                <p class="text-2xl font-bold text-green-600">@{{ formatCurrency(stats.this_month) }}</p>
            </div>
        </script>

        <script type="text/x-template" id="v-payments-list-template">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <select v-model="filters.status" @change="loadPayments" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">@lang('All Status')</option>
                            <option value="pending">@lang('Pending')</option>
                            <option value="partial">@lang('Partial')</option>
                            <option value="completed">@lang('Completed')</option>
                            <option value="overdue">@lang('Overdue')</option>
                        </select>
                        <input
                            type="text"
                            v-model="filters.search"
                            @input="debounceSearch"
                            placeholder="Search by student name..."
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2"
                        />
                    </div>
                </div>

                <table class="w-full text-sm text-left">
                    <thead class="text-gray-600 bg-gray-50 dark:bg-gray-900 dark:text-gray-300">
                        <tr>
                            <th class="px-6 py-4 font-semibold">@lang('Student')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Program')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Total Amount')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Paid')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Balance')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Status')</th>
                            <th class="px-6 py-4 font-semibold">@lang('Next Due')</th>
                            <th class="px-6 py-4 font-semibold text-right">@lang('Actions')</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td colspan="8" class="px-6 py-4 text-center">
                                <x-admin::spinner />
                            </td>
                        </tr>
                        <tr v-else-if="payments.length === 0">
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                @lang('No payments found')
                            </td>
                        </tr>
                        <tr v-else v-for="payment in payments" :key="payment.id" class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4">
                                <div class="font-medium">@{{ payment.lead?.title || 'N/A' }}</div>
                                <div class="text-xs text-gray-500">@{{ payment.lead?.person?.name }}</div>
                            </td>
                            <td class="px-6 py-4">@{{ payment.cohort?.program?.name || '-' }}</td>
                            <td class="px-6 py-4 font-medium">@{{ formatCurrency(payment.total_amount) }}</td>
                            <td class="px-6 py-4 text-green-600">@{{ formatCurrency(payment.paid_amount) }}</td>
                            <td class="px-6 py-4 text-red-600">@{{ formatCurrency(payment.total_amount - payment.paid_amount) }}</td>
                            <td class="px-6 py-4">
                                <span :class="getStatusClass(payment.status)" class="px-2 py-1 text-xs rounded-full">
                                    @{{ payment.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="payment.next_due_date" :class="isOverdue(payment.next_due_date) ? 'text-red-600' : ''">
                                    @{{ formatDate(payment.next_due_date) }}
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="viewPayment(payment)" class="text-blue-600 hover:text-blue-900 mr-3">
                                    @lang('View')
                                </button>
                                <button @click="recordPayment(payment)" class="text-green-600 hover:text-green-900">
                                    @lang('Record Payment')
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="pagination.total > pagination.per_page" class="p-4 border-t dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            Showing @{{ pagination.from }} to @{{ pagination.to }} of @{{ pagination.total }} entries
                        </span>
                        <div class="flex gap-2">
                            <button
                                @click="changePage(pagination.current_page - 1)"
                                :disabled="pagination.current_page === 1"
                                class="px-3 py-1 rounded border disabled:opacity-50"
                            >
                                @lang('Previous')
                            </button>
                            <button
                                @click="changePage(pagination.current_page + 1)"
                                :disabled="pagination.current_page === pagination.last_page"
                                class="px-3 py-1 rounded border disabled:opacity-50"
                            >
                                @lang('Next')
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-payment-stats', {
                template: '#v-payment-stats-template',

                data() {
                    return {
                        stats: {
                            total_revenue: 0,
                            pending_amount: 0,
                            overdue_amount: 0,
                            this_month: 0,
                        },
                    };
                },

                mounted() {
                    this.loadStats();
                },

                methods: {
                    async loadStats() {
                        try {
                            const response = await axios.get('{{ route("admin.educrm.payments.stats") }}');
                            this.stats = response.data.data;
                        } catch (error) {
                            console.error('Failed to load stats:', error);
                        }
                    },

                    formatCurrency(amount) {
                        return new Intl.NumberFormat('en-IN', {
                            style: 'currency',
                            currency: 'INR'
                        }).format(amount || 0);
                    }
                }
            });

            app.component('v-payments-list', {
                template: '#v-payments-list-template',

                data() {
                    return {
                        isLoading: true,
                        payments: [],
                        filters: {
                            status: '',
                            search: '',
                        },
                        pagination: {
                            current_page: 1,
                            per_page: 15,
                            total: 0,
                            from: 0,
                            to: 0,
                            last_page: 1,
                        },
                        searchTimeout: null,
                    };
                },

                mounted() {
                    this.loadPayments();
                },

                methods: {
                    async loadPayments() {
                        this.isLoading = true;
                        try {
                            const params = {
                                page: this.pagination.current_page,
                                per_page: this.pagination.per_page,
                                ...this.filters,
                            };
                            const response = await axios.get('{{ route("admin.educrm.payments.index") }}', {
                                params,
                                headers: { 'Accept': 'application/json' }
                            });
                            this.payments = response.data.data;
                            if (response.data.meta) {
                                this.pagination = response.data.meta;
                            }
                        } catch (error) {
                            console.error('Failed to load payments:', error);
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    debounceSearch() {
                        clearTimeout(this.searchTimeout);
                        this.searchTimeout = setTimeout(() => {
                            this.pagination.current_page = 1;
                            this.loadPayments();
                        }, 300);
                    },

                    changePage(page) {
                        this.pagination.current_page = page;
                        this.loadPayments();
                    },

                    formatCurrency(amount) {
                        return new Intl.NumberFormat('en-IN', {
                            style: 'currency',
                            currency: 'INR'
                        }).format(amount || 0);
                    },

                    formatDate(date) {
                        if (!date) return '-';
                        return new Date(date).toLocaleDateString('en-IN', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric'
                        });
                    },

                    isOverdue(date) {
                        return new Date(date) < new Date();
                    },

                    getStatusClass(status) {
                        const classes = {
                            pending: 'bg-yellow-100 text-yellow-800',
                            partial: 'bg-blue-100 text-blue-800',
                            completed: 'bg-green-100 text-green-800',
                            overdue: 'bg-red-100 text-red-800',
                            refunded: 'bg-gray-100 text-gray-800',
                        };
                        return classes[status] || 'bg-gray-100 text-gray-800';
                    },

                    viewPayment(payment) {
                        window.location.href = `/admin/educrm/payments/${payment.id}`;
                    },

                    recordPayment(payment) {
                        this.$emitter.emit('record-payment', payment);
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
