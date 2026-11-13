<x-app-layout>
    @php
        $authUser = auth()->user();
        $canManageStaffSalary = $authUser && $authUser->hasAnyPermission('teams.staff.salary.manage', 'teams.staff.self.manage');
        $canViewStaffSalary = $authUser && $authUser->hasAnyPermission('teams.staff.salary.view', 'teams.staff.salary.manage', 'teams.staff.self.manage');
        $canViewStaffBank = $authUser && $authUser->hasAnyPermission('teams.staff.bank.view', 'teams.staff.bank.manage', 'teams.staff.self.manage');
        $canManageStaffDocuments = $authUser && $authUser->hasAnyPermission('teams.staff.documents.manage', 'teams.staff.self.manage');
        $canManageStaffInvoiceReceipts = $authUser && $authUser->isCompanyAdmin();
        $canViewTicketDocuments = $authUser && ($authUser->isCompanyAdmin() || $authUser->hasAnyPermission('tickets.view', 'tickets.reply', 'tickets.manage'));
        $canUseAttendanceApi = filled($staffApiAccessToken ?? null);
        $invoicePaymentStatusLabels = [0 => 'Unpaid', 1 => 'Paid'];
        $invoicePaymentMethodLabels = [0 => 'Cash', 1 => 'Online', 2 => 'Cash'];
    @endphp
    <div class="py-8" x-data="{ activeTab: 'profile' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- @if(session('success'))
                <div class="mb-4 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ $errors->first() }}
                </div>
            @endif --}}
            @if(session('success'))
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Success!',
                            text: "{{ session('success') }}",
                            icon: 'success',
                            confirmButtonColor: '#4f46e5', // Matches your indigo-600 color
                        });
                    });
                </script>
            @endif

            @if($errors->any())
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Error!',
                            text: "{{ $errors->first() }}",
                            icon: 'error',
                            confirmButtonColor: '#e11d48', // Matches your rose-600 color
                        });
                    });
                </script>
            @endif

            <div class="bg-white border border-gray-200 p-6 flex justify-between items-center">
                <div class="flex items-center gap-4">
                    {{-- <div class="h-16 w-16 bg-indigo-100  flex items-center justify-center text-indigo-700 font-bold text-xl">
                        {{ substr($staff->name, 0, 1) }}
                    </div> --}}
                    <div class="relative mb-4">
                                    <div class="h-24 w-24 overflow-hidden shadow-lg shadow-indigo-200">
                                        @if($staff->profile_photo)
                                            <img
                                                src="{{ $staff->profile_photo_url }}"
                                                alt="{{ $staff->name }}"
                                                class="h-full w-full object-cover"
                                            >
                                        @else
                                            <div class="h-full w-full bg-indigo-600 flex items-center justify-center text-white text-3xl font-bold">
                                                {{ substr($staff->name, 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <span class="absolute -bottom-2 -right-2 bg-green-500 border-4 border-white w-6 h-6 " title="Active Account"></span>
                                </div>

                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">{{ $staff->name }}</h2>
                        <p class="text-sm text-gray-500">{{ $staff->user?->email ?? '-' }} | ID: #{{ $staff->id }}</p>
                    </div>
                </div>
                <a href="{{ route('teams.staff.index') }}" class="text-sm text-gray-600 hover:text-indigo-600 font-medium flex items-center gap-1">
                    &larr; Back to List
                </a>
            </div>

            <div class="bg-white border-x border-b border-gray-200">
                <nav class="flex px-6 border-b border-gray-100 overflow-x-auto no-scrollbar">
                    <button @click="activeTab = 'profile'" :class="activeTab === 'profile' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all">
                        Staff Details
                    </button>

                    @if($canViewStaffSalary)
                        <button @click="activeTab = 'salary'" :class="activeTab === 'salary' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all">
                            Salary Configuration
                        </button>
                    @endif
                    @if($canViewStaffBank)
                        <button @click="activeTab = 'bank'" :class="activeTab === 'bank' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all">
                            Bank Details
                        </button>
                    @endif
                    @if($canUseAttendanceApi)
                        <button @click="activeTab = 'attendance'" :class="activeTab === 'attendance' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all">
                            Attendance
                        </button>
                    @endif
                    @if($canManageStaffDocuments)
                        <button @click="activeTab = 'documents'" :class="activeTab === 'documents' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all">
                            Document Storage
                        </button>
                    @endif
                    <button @click="activeTab = 'activity'" :class="activeTab === 'activity' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all">
                        Activity
                    </button>
                    <button @click="activeTab = 'tickets'" :class="activeTab === 'tickets' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all">
                        Assigned Tickets
                    </button>
                    <button @click="activeTab = 'performance'" :class="activeTab === 'performance' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all">
                        Performance
                    </button>
                    <button @click="activeTab = 'invoices'" :class="activeTab === 'invoices' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-all">
                        Invoices
                    </button>
                </nav>

                <div class="p-8">

                    <div x-show="activeTab === 'profile'" x-cloak class="bg-white border border-gray-200 shadow-sm overflow-hidden">
                        <div class="flex flex-col md:flex-row">

                            <div class="w-full md:w-1/3 bg-gray-50/50 p-8 border-b md:border-b-0 md:border-r border-gray-100 flex flex-col items-center text-center">
                                <div class="relative mb-4">
                                    <div class="h-24 w-24 overflow-hidden shadow-lg shadow-indigo-200">
                                        @if($staff->profile_photo)
                                            <img
                                                src="{{ $staff->profile_photo_url }}"
                                                alt="{{ $staff->name }}"
                                                class="h-full w-full object-cover"
                                            >
                                        @else
                                            <div class="h-full w-full bg-indigo-600 flex items-center justify-center text-white text-3xl font-bold">
                                                {{ substr($staff->name, 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <span class="absolute -bottom-2 -right-2 bg-green-500 border-4 border-white w-6 h-6 " title="Active Account"></span>
                                </div>

                                <h2 class="text-xl font-bold text-gray-900 leading-tight">{{ $staff->name }}</h2>
                                <p class="text-sm text-gray-500 font-medium mb-4">{{ $staff->user?->role ?? 'Team Member' }}</p>
                            </div>

                            <div class="w-full md:w-2/3 p-8">
                                <div class="flex items-center justify-between mb-8">
                                    <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest">General Information</h3>
                                    <span class="h-px flex-1 bg-gray-100 ml-4"></span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-8 gap-x-12">
                                    <div class="group">
                                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1 group-hover:text-indigo-500 transition-colors">Employee ID</label>
                                        <p class="text-sm font-mono font-bold text-gray-800 bg-gray-50 px-2 py-1 inline-block">
                                            #{{ $staff->employee_id }}
                                        </p>
                                    </div>

                                    <div class="group">
                                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1 group-hover:text-indigo-500 transition-colors">Official Email</label>
                                        <p class="text-sm font-semibold text-gray-900 decoration-indigo-200 underline underline-offset-4 decoration-2">
                                            {{ $staff->user?->email ?? '-' }}
                                        </p>
                                    </div>

                                    <div class="group">
                                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1 group-hover:text-indigo-500 transition-colors">Phone Number</label>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $staff->contact_number ?? '--' }}
                                        </p>
                                    </div>

                                    <div class="group">
                                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1 group-hover:text-indigo-500 transition-colors">Joining Date</label>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $staff->join_date }}
                                        </p>
                                    </div>

                                    <div class="group">
                                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1 group-hover:text-indigo-500 transition-colors">Department</label>
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2  bg-indigo-400"></span>
                                            <p class="text-sm font-semibold text-gray-900">{{ $staff->department ?? 'N/A' }}</p>
                                        </div>
                                    </div>

                                    <div class="group">
                                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1 group-hover:text-indigo-500 transition-colors">Last Updated</label>
                                        <p class="text-xs text-gray-500 italic">
                                            {{ $staff->updated_at->format('d-m-Y H:i') }}
                                        </p>
                                    </div>
                                </div>

                                {{-- <div class="mt-12 pt-6 border-t border-gray-50 flex gap-3">
                                    <button class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100">
                                        Edit Information
                                    </button>
                                    <button class="px-4 py-2 bg-white text-gray-600 border border-gray-200 text-xs font-bold hover:bg-gray-50 transition-all">
                                        Generate Report
                                    </button>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                    <div x-show="activeTab === 'activity'" x-cloak>
                        <div class="flow-root">
                            <ul role="list" class="-mb-8">
                                @forelse($staff->activities as $activity)
                                    <li>
                                        <div class="relative pb-8">
                                            @if(!$loop->last)
                                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                            @endif

                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="h-9 w-9  flex items-center justify-center ring-8 ring-white
                                                        {{ $activity->type === 'Staff Details' ? 'bg-blue-500' : 'bg-gray-400' }}">

                                                        @if($activity->type === 'Staff Details')
                                                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                        @elseif($activity->type === 'Salary Update')
                                                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        @else
                                                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        @endif
                                                    </span>
                                                </div>

                                                <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                                    <div>
                                                        <p class="text-sm text-gray-600">
                                                            {{ $activity->description }}
                                                            <span class="font-bold text-gray-900">by {{ auth()->user()->name }}</span>
                                                        </p>
                                                        @if($activity->ip_address)
                                                            <p class="text-xs text-gray-400 mt-0.5">IP: {{ $activity->ip_address }}</p>
                                                        @endif
                                                    </div>
                                                    <div class="whitespace-nowrap text-right text-sm text-gray-500">
                                                        <time datetime="{{ $activity->occurred_at?->toDateTimeString() }}">
                                                            {{ $activity->occurred_at?->format('d-m-Y H:i') }}
                                                        </time>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <div class="text-center py-8">
                                        <p class="text-gray-500 text-sm">No activity recorded yet.</p>
                                    </div>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    <div x-show="activeTab === 'tickets'" x-cloak class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Assigned Tickets</p>
                                <p class="mt-2 text-2xl font-bold text-gray-900">{{ $staffAssignmentSummary['assigned_tickets'] ?? 0 }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Current Open</p>
                                <p class="mt-2 text-2xl font-bold text-amber-600">{{ $staffAssignmentSummary['current_open_tickets'] ?? 0 }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Completed</p>
                                <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $staffAssignmentSummary['completed_tickets'] ?? 0 }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Historical</p>
                                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $staffAssignmentSummary['historical_tickets'] ?? 0 }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Repeat Handled</p>
                                <p class="mt-2 text-2xl font-bold text-rose-600">{{ $staffAssignmentSummary['repeat_complaints_handled'] ?? 0 }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Reassigned</p>
                                <p class="mt-2 text-2xl font-bold text-cyan-600">{{ $staffAssignmentSummary['reassigned_tickets'] ?? 0 }}</p>
                            </div>
                        </div>

                        <div class="border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400">Assigned Tickets</h3>
                                    <p class="mt-1 text-sm text-gray-500">Latest support work linked to this staff member.</p>
                                </div>
                                <span class=" bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $assignedTickets->count() }} tickets</span>
                            </div>

                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-whitetext-gray-500">Ticket</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Client</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Module</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Issue</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Assigned</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Visit</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Repeat</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @forelse($assignedTickets as $ticket)
                                            <tr>
                                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $ticket->ticket_number }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->customer_name ?: '-' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->generatedFromAmc ? 'AMC' : 'Support' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->subject }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->created_at?->format('d M Y') }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->visit_date?->format('d M Y') ?? '-' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $ticket->status_label }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    @if($ticket->repeat_count)
                                                        <span class=" bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Repeat {{ $ticket->repeat_count }}</span>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">No assigned tickets found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400">Ticket History</h3>
                                    <p class="mt-1 text-sm text-gray-500">All tickets this staff member has handled, including transferred work.</p>
                                </div>
                                <span class=" bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">{{ $historicalTicketRows->count() }} records</span>
                            </div>

                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Ticket</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Assignment Type</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Assigned At</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Released At</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Repeat Handled</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @forelse($historicalTicketRows as $historyRow)
                                            <tr>
                                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                                                    {{ $historyRow->ticket?->ticket_number ?? '-' }}
                                                    <div class="text-xs font-normal text-gray-500">{{ $historyRow->ticket?->subject ?? '' }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ \Illuminate\Support\Str::headline($historyRow->assignment_type ?? 'Manual') }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $historyRow->assigned_at?->format('d M Y, g:i A') ?? '-' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $historyRow->released_at?->format('d M Y, g:i A') ?? 'Present' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $historyRow->repeat_count_handled ?? 0 }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    <span class=" px-2.5 py-1 text-xs font-semibold {{ $historyRow->is_current ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                                        {{ $historyRow->is_current ? 'Current Owner' : 'Transferred' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No ticket history found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div x-show="activeTab === 'performance'" x-cloak class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Total Assigned</p>
                                <p class="mt-2 text-2xl font-bold text-gray-900">{{ $staffPerformance['total_assigned'] ?? 0 }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Completed</p>
                                <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $staffPerformance['completed'] ?? 0 }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Pending</p>
                                <p class="mt-2 text-2xl font-bold text-amber-600">{{ $staffPerformance['pending'] ?? 0 }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Repeat Complaints</p>
                                <p class="mt-2 text-2xl font-bold text-rose-600">{{ $staffAssignmentSummary['repeat_complaints_handled'] ?? 0 }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Completion %</p>
                                <p class="mt-2 text-2xl font-bold text-indigo-600">{{ number_format((float) ($staffPerformance['completion_percentage'] ?? 0), 2) }}%</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Repeat %</p>
                                <p class="mt-2 text-2xl font-bold text-cyan-600">{{ number_format((float) (($staffAssignmentSummary['assigned_tickets'] ?? 0) > 0 ? (($staffAssignmentSummary['repeat_complaints_handled'] ?? 0) / max(1, $staffAssignmentSummary['assigned_tickets'] ?? 0)) * 100 : 0), 2) }}%</p>
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-2">
                            <div class="border border-gray-200 bg-white p-6 shadow-sm">
                                <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400">Client Statistics</h3>
                                @if($topClient)
                                    <div class="mt-4 space-y-3 text-sm">
                                        <div class="flex justify-between"><span class="text-gray-500">Client</span><span class="font-semibold text-gray-900">{{ $topClient['customer_name'] ?: '-' }}</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Total Tickets</span><span class="font-semibold text-gray-900">{{ $topClient['total'] }}</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Open</span><span class="font-semibold text-gray-900">{{ $topClient['open'] }}</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Closed</span><span class="font-semibold text-gray-900">{{ $topClient['closed'] }}</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Repeat Count</span><span class="font-semibold text-gray-900">{{ $topClient['repeat_count'] }}</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Avg Resolution</span><span class="font-semibold text-gray-900">{{ number_format((float) $topClient['average_resolution_days'], 2) }} days</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Last Ticket</span><span class="font-semibold text-gray-900">{{ $topClient['last_ticket']?->ticket_number ?? '-' }}</span></div>
                                    </div>
                                @else
                                    <p class="mt-4 text-sm text-gray-500">No repeatable client history yet.</p>
                                @endif
                            </div>

                            <div class="border border-gray-200 bg-white p-6 shadow-sm">
                                <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400">Performance Notes</h3>
                                <div class="mt-4 space-y-3 text-sm">
                                    <div class="flex justify-between"><span class="text-gray-500">Average Resolution Time</span><span class="font-semibold text-gray-900">{{ number_format((float) ($staffPerformance['average_resolution_days'] ?? 0), 2) }} days</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Repeat Rate</span><span class="font-semibold text-gray-900">{{ number_format((float) (($staffAssignmentSummary['assigned_tickets'] ?? 0) > 0 ? (($staffAssignmentSummary['repeat_complaints_handled'] ?? 0) / max(1, $staffAssignmentSummary['assigned_tickets'] ?? 0)) * 100 : 0), 2) }}%</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Recent Assigned Tickets</span><span class="font-semibold text-gray-900">{{ $assignedTickets->count() }}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Transferred Tickets</span><span class="font-semibold text-gray-900">{{ $staffAssignmentSummary['reassigned_tickets'] ?? 0 }}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Received Tickets</span><span class="font-semibold text-gray-900">{{ $staffAssignmentSummary['received_tickets'] ?? 0 }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="activeTab === 'invoices'" x-cloak class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Total Invoices</p>
                                <p class="mt-2 text-2xl font-bold text-gray-900">{{ $staffInvoiceSummary['total_invoices'] ?? 0 }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Total Invoice Amount</p>
                                <p class="mt-2 text-2xl font-bold text-indigo-600">&#8377;{{ number_format((float) ($staffInvoiceSummary['total_invoice_amount'] ?? 0), 2) }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Paid Amount</p>
                                <p class="mt-2 text-2xl font-bold text-emerald-600">&#8377;{{ number_format((float) ($staffInvoiceSummary['paid_amount'] ?? 0), 2) }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Pending Amount</p>
                                <p class="mt-2 text-2xl font-bold text-amber-600">&#8377;{{ number_format((float) ($staffInvoiceSummary['pending_amount'] ?? 0), 2) }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Amount Received By Admin</p>
                                <p class="mt-2 text-2xl font-bold text-sky-600">&#8377;{{ number_format((float) ($staffInvoiceSummary['received_amount'] ?? 0), 2) }}</p>
                            </div>
                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Remaining Collection</p>
                                <p class="mt-2 text-2xl font-bold text-rose-600">&#8377;{{ number_format((float) ($staffInvoiceSummary['remaining_amount'] ?? 0), 2) }}</p>
                            </div>
                        </div>

                        <div class="border border-gray-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400">Invoice List</h3>
                                    <p class="mt-1 text-sm text-gray-500">All invoices generated for this staff member.</p>
                                </div>
                                <span class=" bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $staffInvoices->count() }} invoices</span>
                            </div>

                            <div class="mt-4 w-full overflow-x-auto">
                                <table class="min-w-full w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Invoice #</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Ticket</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Client</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Invoice Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Invoice Amount</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Payment Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Payment Method</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Amount Received By Admin</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Collected Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-gray-500">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @forelse($staffInvoices as $ticketInvoice)
                                            @php
                                                $invoiceAmount = (float) ($ticketInvoice->total_amount ?? $ticketInvoice->invoice_total ?? $ticketInvoice->invoice?->total ?? 0);
                                                $isPaid = (int) ($ticketInvoice->payment_status ?? 0) === 1;
                                                $paymentStatusLabel = $invoicePaymentStatusLabels[(int) ($ticketInvoice->payment_status ?? 0)] ?? 'Unpaid';
                                                $paymentMethodLabel = $isPaid ? ($invoicePaymentMethodLabels[(int) ($ticketInvoice->payment_method ?? -1)] ?? '-') : '-';
                                            @endphp
                                            <tr>
                                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                                                    {{ $ticketInvoice->invoice_number ?: ($ticketInvoice->invoice?->document_number ?? 'INV-' . $ticketInvoice->id) }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    {{ $ticketInvoice->ticket?->ticket_number ?? ('TKT-' . $ticketInvoice->ticket_id) }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $ticketInvoice->ticket?->customer_name ?: '-' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    {{ $ticketInvoice->invoice?->created_at?->format('d M Y') ?? $ticketInvoice->created_at?->format('d M Y') ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">&#8377;{{ number_format($invoiceAmount, 2) }}</td>
                                                <td class="px-4 py-3 text-sm">
                                                    <span class=" px-2.5 py-1 text-xs font-semibold {{ $isPaid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                                        {{ $paymentStatusLabel }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $paymentMethodLabel }}</td>
                                                <td class="px-4 py-3 text-sm">
                                                    @if(! $isPaid)
                                                        <span class=" bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Invoice not paid yet</span>
                                                    @elseif($canManageStaffInvoiceReceipts)
                                                        <form method="POST" action="{{ route('teams.staff.invoices.received-by-admin.update', [$staff, $ticketInvoice]) }}" class="inline-flex items-center gap-3">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="received_by_admin" value="0">
                                                            <label class="inline-flex items-center cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    name="received_by_admin"
                                                                    value="1"
                                                                    class="sr-only peer"
                                                                    @checked((bool) $ticketInvoice->received_by_admin)
                                                                    onchange="this.form.submit()"
                                                                >
                                                                <span class="relative h-6 w-11  bg-gray-200 transition peer-checked:bg-emerald-500 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after: after:bg-white after:transition after:content-[''] peer-checked:after:translate-x-5"></span>
                                                            </label>
                                                            <span class="text-xs font-semibold {{ $ticketInvoice->received_by_admin ? 'text-emerald-700' : 'text-gray-500' }}">
                                                                {{ $ticketInvoice->received_by_admin ? 'Received' : 'Pending with Staff' }}
                                                            </span>
                                                        </form>
                                                    @else
                                                        <span class=" {{ $ticketInvoice->received_by_admin ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-semibold">
                                                            {{ $ticketInvoice->received_by_admin ? 'Received' : 'Pending with Staff' }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    {{ $ticketInvoice->received_by_admin_at?->format('d M Y') ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    @if($canViewTicketDocuments)
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <a
                                                                href="{{ route('tickets.invoices.view', [$ticketInvoice->ticket, $ticketInvoice]) }}"
                                                                class="inline-flex items-center border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                                                            >
                                                                View
                                                            </a>
                                                            <a
                                                                href="{{ route('tickets.invoices.print', [$ticketInvoice->ticket, $ticketInvoice]) }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="inline-flex items-center border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                            >
                                                                Print
                                                            </a>
                                                        </div>
                                                    @else
                                                        <span class="text-xs text-gray-400">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="10" class="px-4 py-8 text-center text-sm text-gray-500">No invoices found for this staff member.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @if($canViewStaffSalary)
                    <div x-show="activeTab === 'salary'" x-cloak x-data="{ editMode: false }">
                        <div class="flex justify-end mb-2">
                            @if($canManageStaffSalary)
                            <button type="button" @click="editMode = !editMode" class="text-gray-500 hover:text-indigo-600 flex items-center gap-1 text-sm font-medium transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                {{-- <span x-text="editMode ? 'Cancel' : 'Edit Salary'"></span> --}}
                            </button>
                            @endif
                        </div>

                        <form action="{{ route('teams.salary.store') }}" id="salaryForm" method="POST">
                            @csrf
                            <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                            <div class="flex flex-wrap lg:flex-nowrap items-start gap-5 bg-white p-6 border border-gray-200 shadow-sm">

                                <!-- Basic Salary -->
                                <div class="flex-1 min-w-[180px]">
                                    <x-input-label value="Basic Salary" class="text-gray-600 font-semibold mb-1.5 ml-1" />
                                    <x-text-input name="basic_salary"
                                        placeholder="e.g. 50000"
                                        ::disabled="!editMode"
                                        value="{{ $staff->salaryConfiguration?->basic_salary }}"
                                        class="w-full placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500/20" />
                                    <div class="mt-1 min-h-[20px]">
                                        <x-input-error :messages="$errors->get('basic_salary')" class="text-xs" />
                                    </div>
                                </div>

                                <!-- HRA -->
                                <div class="flex-1 min-w-[180px]">
                                    <x-input-label value="HRA" class="text-gray-600 font-semibold mb-1.5 ml-1" />
                                    <x-text-input name="hra"
                                        placeholder="e.g. 5000"
                                        ::disabled="!editMode"
                                        value="{{ $staff->salaryConfiguration?->hra }}"
                                        class="w-full placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500/20" />
                                    <div class="mt-1 min-h-[20px]">
                                        <x-input-error :messages="$errors->get('hra')" class="text-xs" />
                                    </div>
                                </div>

                                <!-- Allowances -->
                                <div class="flex-1 min-w-[180px]">
                                    <x-input-label value="Allowances" class="text-gray-600 font-semibold mb-1.5 ml-1" />
                                    <x-text-input name="allowances"
                                        placeholder="e.g. 2000"
                                        ::disabled="!editMode"
                                        value="{{ is_array($staff->salaryConfiguration?->allowances) ? array_sum($staff->salaryConfiguration->allowances) : $staff->salaryConfiguration?->allowances }}"
                                        class="w-full placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500/20" />
                                    <div class="mt-1 min-h-[20px]">
                                        <x-input-error :messages="$errors->get('allowances')" class="text-xs" />
                                    </div>
                                </div>

                                <!-- Total Display - Aligned with Inputs -->
                                @if($staff->salaryConfiguration)
                                <div class="flex-none pt-[30px]">
                                    <div class="h-[42px] px-4 flex flex-col justify-center bg-indigo-50 border border-indigo-100">
                                        <span class="text-[10px] uppercase font-bold text-indigo-500 leading-none mb-1">Total Salary</span>
                                        <span class="text-sm font-extrabold text-indigo-900 leading-none">
                                            ₹{{ number_format($staff->salaryConfiguration->total_salary, 2) }}
                                        </span>
                                    </div>
                                </div>
                                @endif

                                <!-- Button - Aligned with Inputs -->
                                <div class="flex-none pt-[30px]" x-show="editMode">
                                    <x-primary-button class="h-[42px] px-6 bg-gray-900 hover:bg-black transition-all active:scale-95 shadow-md shadow-gray-200">
                                        {{ __('ADD/UPDATE SALARY') }}
                                    </x-primary-button>
                                </div>

                            </div>


                        </form>
                    </div>
                    @endif

                    <div x-show="activeTab === 'bank'" x-cloak x-data="{ editMode: false }">
                        <div class="flex justify-end mb-2">
                            <button type="button" @click="editMode = !editMode" class="text-gray-500 hover:text-indigo-600 flex items-center gap-1 text-sm font-medium transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                {{-- <span x-text="editMode ? 'Cancel' : 'Edit Bank Details'"></span> --}}
                            </button>
                        </div>

                      <form action="{{ route('teams.bank.store') }}" id="bankForm" method="POST">
                            @csrf
                            <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                            <div class="bg-white p-6 border border-gray-200 shadow-sm">
                                <div class="flex flex-wrap md:flex-nowrap gap-5 items-start">

                                    <!-- Bank Name -->
                                    <div class="flex-1 min-w-[150px]">
                                        <x-input-label value="Bank Name" class="mb-1.5 text-gray-600 font-semibold ml-1" />
                                        <x-text-input name="bank_name"
                                            placeholder="e.g. HDFC Bank"
                                            ::disabled="!editMode"
                                            value="{{ $staff->bankDetails?->bank_name }}"
                                            minlength="2"
                                            maxlength="191"
                                            class="w-full placeholder:text-gray-400 disabled:bg-gray-50 focus:ring-2 focus:ring-indigo-500/20" />
                                        <div class="mt-1 min-h-[20px]">
                                            <x-input-error :messages="$errors->get('bank_name')" class="text-xs" />
                                        </div>
                                    </div>

                                    <!-- Account Number -->
                                    <div class="flex-1 min-w-[150px]">
                                        <x-input-label value="Account Number" class="mb-1.5 text-gray-600 font-semibold ml-1" />
                                        <x-text-input name="account_number"
                                            type="text"
                                            placeholder="e.g. 50100012345678"
                                            ::disabled="!editMode"
                                            value="{{ $staff->bankDetails?->account_number }}"
                                            minlength="9"
                                            maxlength="18"
                                            inputmode="numeric"
                                            class="w-full placeholder:text-gray-400 disabled:bg-gray-50 focus:ring-2 focus:ring-indigo-500/20" />
                                        <div class="mt-1 min-h-[20px]">
                                            <x-input-error :messages="$errors->get('account_number')" class="text-xs" />
                                        </div>
                                    </div>

                                    <!-- IFSC Code -->
                                    <div class="flex-1 min-w-[120px]">
                                        <x-input-label value="IFSC Code" class="mb-1.5 text-gray-600 font-semibold ml-1" />
                                        <x-text-input name="ifsc"
                                            placeholder="e.g. HDFC0001234"
                                            ::disabled="!editMode"
                                            value="{{ $staff->bankDetails?->ifsc }}"
                                            minlength="11"
                                            maxlength="11"
                                            class="w-full placeholder:text-gray-400 disabled:bg-gray-50 focus:ring-2 focus:ring-indigo-500/20 uppercase" />
                                        <div class="mt-1 min-h-[20px]">
                                            <x-input-error :messages="$errors->get('ifsc')" class="text-xs" />
                                        </div>
                                    </div>

                                    <!-- Branch -->
                                    <div class="flex-1 min-w-[120px]">
                                        <x-input-label value="Branch" class="mb-1.5 text-gray-600 font-semibold ml-1" />
                                        <x-text-input name="branch"
                                            placeholder="e.g. Downtown"
                                            ::disabled="!editMode"
                                            value="{{ $staff->bankDetails?->branch }}"
                                            minlength="2"
                                            maxlength="191"
                                            class="w-full placeholder:text-gray-400 disabled:bg-gray-50 focus:ring-2 focus:ring-indigo-500/20" />
                                        <div class="mt-1 min-h-[20px]">
                                            <x-input-error :messages="$errors->get('branch')" class="text-xs" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Button -->
                                <div class="mt-4 flex justify-end" x-show="editMode">
                                    <x-primary-button class="px-8 py-2.5 bg-indigo-600 hover:bg-indigo-700 transition-all active:scale-95 shadow-md shadow-indigo-100 uppercase tracking-widest text-xs">
                                        ADD/UPDATE DETAILS
                                    </x-primary-button>
                                </div>
                            </div>

                        </form>



                    </div>

                    {{-- <div x-show="activeTab === 'attendance'" x-cloak class="space-y-8" x-data="{ attendanceMode: 'individual' }">


                        <div x-show="attendanceMode === 'individual'" class="space-y-8">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div class="border border-emerald-100 bg-emerald-50 p-4">
                                <p class="text-xs text-emerald-700 font-semibold uppercase">Present</p>
                                <p class="text-2xl font-bold text-emerald-900">{{ $attendanceSummary['present'] }}</p>
                            </div>
                            <div class="border border-rose-100 bg-rose-50 p-4">
                                <p class="text-xs text-rose-700 font-semibold uppercase">Absent</p>
                                <p class="text-2xl font-bold text-rose-900">{{ $attendanceSummary['absent'] }}</p>
                            </div>
                            <div class="border border-blue-100 bg-blue-50 p-4">
                                <p class="text-xs text-blue-700 font-semibold uppercase">Overtime Hours</p>
                                <p class="text-2xl font-bold text-blue-900">{{ number_format($attendanceSummary['overtime_hours'], 2) }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3 items-center">
                            <form method="POST" action="{{ route('attendance.check', $staff) }}">
                                @csrf
                                <input type="hidden" name="type" value="check_in">
                                <x-primary-button type="submit">Check In</x-primary-button>
                            </form>
                            <form method="POST" action="{{ route('attendance.check', $staff) }}">
                                @csrf
                                <input type="hidden" name="type" value="check_out">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-slate-700 text-white text-xs font-semibold uppercase tracking-widest hover:bg-slate-800">Check Out</button>
                            </form>
                            <a href="{{ route('attendance.export', [$staff, $attendanceMonth, $attendanceYear]) }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-800 border text-xs font-semibold uppercase tracking-widest hover:bg-gray-200">
                                Export CSV
                            </a>
                        </div>

                        @if(auth()->user()->isCompanyAdmin())
                            <form action="{{ route('attendance.individual') }}" method="POST" x-data="{ selectedDate: '{{ now()->toDateString() }}', todayDate: '{{ now()->toDateString() }}', selectedStatus: 'Present' }" class="border border-gray-200 p-5 bg-gray-50 space-y-4">
                                @csrf
                                <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Mark Individual Attendance</h4>
                                <input type="hidden" name="staff_id" value="{{ $staff->id }}">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <x-input-label value="Date" />
                                    <x-text-input type="date" name="date" value="{{ now()->toDateString() }}" x-model="selectedDate" class="w-full mt-1 rounded-none" required />
                                    </div>
                                    <div>
                                        <x-input-label value="Status" />
                                        <select name="status" x-model="selectedStatus" class="w-full mt-1 border-gray-300 shadow-sm" required>
                                            <option value="Present">Present</option>
                                            <option value="Absent">Absent</option>
                                            <option value="Half Day">Half Day</option>
                                            <option value="Leave">Leave</option>
                                            <option value="Holiday">Holiday</option>
                                        </select>
                                    </div>
                                    <div x-show="selectedStatus === 'Present' || selectedStatus === 'Half Day'" x-cloak>
                                        <x-input-label value="Overtime Hours" />
                                        <x-text-input type="number" step="0.25" min="0" name="overtime_hours" class="w-full mt-1 rounded-none" />
                                    </div>
                                    <div>
                                        <x-input-label value="Check In (HH:MM)" />
                                        <x-text-input type="time" name="check_in_time" class="w-full mt-1 rounded-none" />
                                    </div>
                                    <div>
                                        <x-input-label value="Check Out (HH:MM)" />
                                        <x-text-input type="time" name="check_out_time" class="w-full mt-1 rounded-none" />
                                    </div>
                                    <div class="md:col-span-3" x-show="selectedStatus === 'Leave'" x-cloak>
                                        <x-input-label value="Leave Reason" />
                                        <x-text-input type="text" name="leave_reason" class="w-full mt-1 rounded-none" />
                                    </div>
                                    <div class="md:col-span-3" x-show="selectedDate < todayDate" x-cloak>
                                        <x-input-label value="Correction Reason (Past Date Update)" />
                                        <x-text-input type="text" name="correction_reason" class="w-full mt-1 rounded-none" />
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <x-primary-button>Save Attendance</x-primary-button>
                                </div>
                            </form>
                        @endif

                        <div class="overflow-x-auto border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Check In</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Check Out</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Overtime</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($monthlyAttendance as $entry)
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ \Carbon\Carbon::parse($entry->date)->format('d M Y') }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $entry->status }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $entry->check_in_time ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $entry->check_out_time ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $entry->overtime_hours }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-6 text-sm text-center text-gray-500">No attendance found for this month.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        </div>

                    </div> --}}

                    @if($canUseAttendanceApi)
                    <div
                        x-show="activeTab === 'attendance'"
                        x-cloak
                        x-data="staffSelfAttendancePanel({
                            token: @js($staffApiAccessToken),
                            todayUrl: @js(route('api.staff.attendance.today')),
                            clockInUrl: @js(route('api.staff.attendance.clock-in')),
                            clockOutUrl: @js(route('api.staff.attendance.clock-out')),
                            monthlyUrl: @js(route('api.staff.attendance.monthly', ['month' => $attendanceMonth, 'year' => $attendanceYear]))
                        })"
                        x-init="init()"
                        class="space-y-6"
                    >
                        <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                            <div class="border border-slate-200 bg-white p-6 shadow-sm">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Today Attendance</p>
                                        <h3 class="mt-2 text-2xl font-bold text-slate-900" x-text="todayLabel()"></h3>
                                        {{-- <p class="mt-1 text-sm text-slate-500">Use the same staff API from the web panel for your daily clock events.</p> --}}
                                    </div>
                                    <span class="inline-flex items-center  px-3 py-1 text-xs font-semibold" :class="statusClass(today?.status)" x-text="today?.status || 'Not Marked'"></span>
                                </div>

                                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                    <div class="bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Check In</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-900" x-text="formatTime(today?.check_in_time)"></p>
                                    </div>
                                    <div class="bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Check Out</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-900" x-text="formatTime(today?.check_out_time)"></p>
                                    </div>
                                </div>

                                <div class="mt-6 flex flex-wrap gap-3">
                                    <button type="button" class="inline-flex items-center bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="actionLoading || !canClockIn()" @click="clockIn()">
                                        <span x-text="actionLoading === 'in' ? 'Clocking In...' : 'Clock In'"></span>
                                    </button>
                                    <button type="button" class="inline-flex items-center bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="actionLoading || !canClockOut()" @click="clockOut()">
                                        <span x-text="actionLoading === 'out' ? 'Clocking Out...' : 'Clock Out'"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="border border-slate-200 bg-white p-6 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">This Month</p>
                                <div class="mt-5 grid grid-cols-2 gap-4">
                                    <div class="bg-emerald-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Present</p>
                                        <p class="mt-2 text-2xl font-bold text-emerald-900" x-text="summary.present ?? 0"></p>
                                    </div>
                                    <div class="bg-rose-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Absent</p>
                                        <p class="mt-2 text-2xl font-bold text-rose-900" x-text="summary.absent ?? 0"></p>
                                    </div>
                                    <div class="bg-amber-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Half Day</p>
                                        <p class="mt-2 text-2xl font-bold text-amber-900" x-text="summary.half_day ?? 0"></p>
                                    </div>
                                    <div class="bg-sky-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Overtime</p>
                                        <p class="mt-2 text-2xl font-bold text-sky-900" x-text="summary.overtime_hours ?? 0"></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-200 px-6 py-4">
                                <h3 class="text-sm font-bold uppercase tracking-[0.2em] text-slate-600">Attendance History</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Check In</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Check Out</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Overtime</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-if="loading">
                                            <tr>
                                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">Loading attendance...</td>
                                            </tr>
                                        </template>
                                        <template x-if="!loading && records.length === 0">
                                            <tr>
                                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">No attendance found for this month.</td>
                                            </tr>
                                        </template>
                                        <template x-for="record in records" :key="record.date">
                                            <tr>
                                                <td class="px-6 py-4 text-sm font-medium text-slate-800" x-text="formatDate(record.date)"></td>
                                                <td class="px-6 py-4 text-sm text-slate-700">
                                                    <span class="inline-flex items-center  px-3 py-1 text-xs font-semibold" :class="statusClass(record.status)" x-text="record.status || 'Not Marked'"></span>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-slate-700" x-text="formatTime(record.check_in_time)"></td>
                                                <td class="px-6 py-4 text-sm text-slate-700" x-text="formatTime(record.check_out_time)"></td>
                                                <td class="px-6 py-4 text-sm text-slate-700" x-text="record.overtime_hours ?? 0"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($canManageStaffDocuments)
                    <div x-show="activeTab === 'documents'" x-cloak>
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-gray-800">Uploaded Documents</h3>
                            <button @click="$dispatch('open-modal', 'upload-doc')" class="text-xs bg-indigo-600 text-white px-3 py-1.5 hover:bg-indigo-700">
                                + Add Document
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            @foreach($staff->documents as $doc)
                                <div class="border border-slate-200 p-4 flex items-center gap-3 bg-white hover:shadow-sm transition">
                                    <!-- <div class="flex h-12 w-12 items-center justify-center border border-slate-200 bg-slate-50 text-slate-500">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </div> -->
                                    <div class="truncate">
                                        <p class="text-sm font-semibold truncate">{{ $doc->document_type }}</p>
                                        <a href="{{ route('teams.staff.documents.download', [$staff, $doc]) }}" class="text-xs text-indigo-600 hover:underline">Download</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <x-modal name="upload-doc" focusable>
                            <form action="{{ route('teams.staff.documents.upload', $staff) }}" method="POST" enctype="multipart/form-data" class="p-6" id="documentUploadForm">
                                @csrf
                                <h2 class="text-lg font-medium text-gray-900 mb-4">
                                    Upload New Document
                                </h2>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Document Type (e.g., ID Proof, Contract)</label>
                                        <input type="text" name="document_type" id="document_type" required minlength="2" maxlength="191"
                                            class="mt-1 block w-full border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Select File</label>
                                        <input type="file" name="document" id="document" required data-max-size="10485760" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                        <p id="document-upload-error" class="mt-1 text-xs text-red-600 hidden"></p>
                                    </div>
                                </div>

                                <div class="mt-6 flex justify-end gap-3">
                                    <button type="button" x-on:click="$dispatch('close')" class="px-4 py-2 bg-gray-200 text-gray-800 hover:bg-gray-300">
                                        Cancel
                                    </button>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white hover:bg-indigo-700">
                                        Upload Document
                                    </button>
                                </div>
                            </form>
                        </x-modal>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
<script>
function staffSelfAttendancePanel(config) {
    return {
        loading: true,
        actionLoading: null,
        today: null,
        summary: {
            present: 0,
            absent: 0,
            half_day: 0,
            overtime_hours: 0,
        },
        records: [],
        async init() {
            try {
                await Promise.all([this.loadToday(), this.loadMonthly()]);
            } catch (error) {
                if (window.appAlert) {
                    window.appAlert(error.message || 'Unable to load attendance right now.', 'Attendance', 'error');
                }
            } finally {
                this.loading = false;
            }
        },
        headers() {
            return {
                'Accept': 'application/json',
                'Authorization': `Bearer ${config.token}`,
            };
        },
        async loadToday() {
            const response = await fetch(config.todayUrl, {
                method: 'GET',
                headers: this.headers(),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Unable to load attendance.');
            }

            this.today = data.attendance;
        },
        async loadMonthly() {
            const response = await fetch(config.monthlyUrl, {
                method: 'GET',
                headers: this.headers(),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Unable to load attendance history.');
            }

            this.records = Array.isArray(data.records) ? data.records : [];
            this.summary = data.summary || this.summary;
        },
        async clockIn() {
            await this.submitClock(config.clockInUrl, 'in');
        },
        async clockOut() {
            await this.submitClock(config.clockOutUrl, 'out');
        },
        async submitClock(url, type) {
            this.actionLoading = type;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        ...this.headers(),
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({}),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Unable to save attendance.');
                }

                this.today = data.attendance;
                await this.loadMonthly();

                if (window.appAlert) {
                    window.appAlert(data.message || 'Attendance updated.', 'Success', 'success');
                }
            } catch (error) {
                if (window.appAlert) {
                    window.appAlert(error.message || 'Unable to save attendance right now.', 'Attendance', 'error');
                }
            } finally {
                this.actionLoading = null;
            }
        },
        todayLabel() {
            return this.today?.date ? this.formatDate(this.today.date) : 'Today';
        },
        canClockIn() {
            return !this.today?.check_in_time;
        },
        canClockOut() {
            return Boolean(this.today?.check_in_time) && !this.today?.check_out_time;
        },
        formatDate(value) {
            if (!value) {
                return '-';
            }

            const date = new Date(`${value}T00:00:00`);
            return Number.isNaN(date.getTime())
                ? value
                : date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        formatTime(value) {
            if (!value) {
                return '-';
            }

            const normalized = value.length === 5 ? `${value}:00` : value;
            const date = new Date(`1970-01-01T${normalized}`);
            return Number.isNaN(date.getTime())
                ? value
                : date.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
        },
        statusClass(status) {
            switch (status) {
                case 'Present':
                    return 'bg-emerald-100 text-emerald-800';
                case 'Absent':
                    return 'bg-rose-100 text-rose-800';
                case 'Half Day':
                    return 'bg-amber-100 text-amber-800';
                case 'Leave':
                    return 'bg-sky-100 text-sky-800';
                case 'Holiday':
                    return 'bg-violet-100 text-violet-800';
                default:
                    return 'bg-slate-100 text-slate-700';
            }
        },
    };
}

$(document).ready(function () {
    const documentInput = document.getElementById('document');
    const documentUploadError = document.getElementById('document-upload-error');
    const documentUploadMaxSize = 10 * 1024 * 1024;

    $.validator.addMethod("filesize", function (value, element, param) {
        if (this.optional(element) || !element.files || !element.files.length) {
            return true;
        }

        return element.files[0].size <= param;
    }, "File is too large.");

    $.validator.addMethod("ifscFormat", function (value, element) {
        if (this.optional(element)) {
            return true;
        }

        return /^[A-Za-z]{4}0[A-Za-z0-9]{6}$/.test(value);
    }, "Enter valid IFSC code");

    function normalizeBankDetailValue(value) {
        return $.trim(value || '');
    }

    $("input[name='account_number']").on("input", function () {
        this.value = this.value.replace(/\D+/g, '').slice(0, 18);
    });

    $("input[name='ifsc']").on("input blur", function () {
        this.value = this.value.toUpperCase().replace(/\s+/g, '').slice(0, 11);
    });

    function setDocumentUploadError(message) {
        if (!documentUploadError) {
            return;
        }

        if (message) {
            documentUploadError.textContent = message;
            documentUploadError.classList.remove('hidden');
        } else {
            documentUploadError.textContent = '';
            documentUploadError.classList.add('hidden');
        }
    }

    function showUploadPopup(message) {
        setDocumentUploadError(message);

        if (window.Swal) {
            Swal.fire({
                title: 'Upload Error',
                text: message,
                icon: 'error',
                confirmButtonColor: '#4f46e5',
            });
        }
    }

    function validateDocumentUpload() {
        if (!documentInput || !documentInput.files || !documentInput.files.length) {
            setDocumentUploadError('');
            return true;
        }

        const file = documentInput.files[0];
        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
        const extension = (file.name.split('.').pop() || '').toLowerCase();

        if (!allowedExtensions.includes(extension)) {
            const message = 'Document must be a PDF, JPG, JPEG, PNG, WEBP, DOC, or DOCX file.';
            documentInput.value = '';
            showUploadPopup(message);
            return false;
        }

        if (file.size > documentUploadMaxSize) {
            const message = 'Document is too large. Maximum allowed size is 10 MB.';
            documentInput.value = '';
            showUploadPopup(message);
            return false;
        }

        setDocumentUploadError('');
        return true;
    }

    if (documentInput) {
        documentInput.addEventListener('change', function () {
            validateDocumentUpload();
        });
    }

    // SALARY FORM VALIDATION
    $("#salaryForm").validate({
        ignore: [],
        errorElement: "p",
        errorClass: "text-red-500 text-xs mt-1",
        rules: {
            basic_salary: {
                required: true,
                number: true,
                min: 0
            },
            hra: {
                required: true,
                number: true,
                min: 0
            },
            allowances: {
                required: true,
                number: true,
                min: 0
            }
        },
        messages: {
            basic_salary: "Please enter basic salary",
            hra: "Please enter HRA amount",
            allowances: "Please enter allowances"
        },
        submitHandler: function (form) {
            form.submit();
        }
    });


    // BANK FORM VALIDATION
    $("#bankForm").validate({
        ignore: [],
        errorElement: "p",
        errorClass: "text-red-500 text-xs mt-1",
        rules: {
            bank_name: {
                required: true,
                minlength: 2,
                maxlength: 191,
                normalizer: normalizeBankDetailValue
            },
            account_number: {
                required: true,
                digits: true,
                minlength: 9,
                maxlength: 18,
                normalizer: normalizeBankDetailValue
            },
            ifsc: {
                required: true,
                ifscFormat: true,
                minlength: 11,
                maxlength: 11,
                normalizer: function (value) {
                    return normalizeBankDetailValue(value).toUpperCase();
                }
            },
            branch: {
                minlength: 2,
                maxlength: 191,
                normalizer: normalizeBankDetailValue
            }
        },
        messages: {
            bank_name: {
                required: "Please enter bank name",
                minlength: "Bank name must be at least 2 characters.",
                maxlength: "Bank name may not be greater than 191 characters."
            },
            account_number: {
                required: "Please enter account number",
                digits: "Account number must contain only digits.",
                minlength: "Account number must be at least 9 digits.",
                maxlength: "Account number may not be greater than 18 digits."
            },
            ifsc: {
                required: "Please enter IFSC code",
                ifscFormat: "Enter valid IFSC code",
                minlength: "IFSC code must be exactly 11 characters.",
                maxlength: "IFSC code must be exactly 11 characters."
            },
            branch: {
                minlength: "Branch name must be at least 2 characters.",
                maxlength: "Branch name may not be greater than 191 characters."
            }
        },
        highlight: function (element) {
            $(element).addClass("border-red-500");
        },
        unhighlight: function (element) {
            $(element).removeClass("border-red-500");
        },
        submitHandler: function (form) {
            const branchField = form.querySelector("input[name='branch']");
            const ifscField = form.querySelector("input[name='ifsc']");
            const bankNameField = form.querySelector("input[name='bank_name']");
            const accountNumberField = form.querySelector("input[name='account_number']");

            if (bankNameField) {
                bankNameField.value = normalizeBankDetailValue(bankNameField.value);
            }

            if (accountNumberField) {
                accountNumberField.value = normalizeBankDetailValue(accountNumberField.value);
            }

            if (ifscField) {
                ifscField.value = normalizeBankDetailValue(ifscField.value).toUpperCase();
            }

            if (branchField) {
                branchField.value = normalizeBankDetailValue(branchField.value);
            }

            form.submit();
        }
    });

    $("#documentUploadForm").validate({
        ignore: [],
        errorElement: "p",
        errorClass: "text-red-500 text-xs mt-1",
        rules: {
            document_type: {
                required: true,
                minlength: 2,
                maxlength: 191,
                normalizer: function (value) {
                    return $.trim(value);
                }
            },
            document: {
                required: true,
                extension: "pdf|jpg|jpeg|png|webp|doc|docx",
                filesize: documentUploadMaxSize
            }
        },
        messages: {
            document_type: {
                required: "Please enter document type.",
                minlength: "Document type must be at least 2 characters.",
                maxlength: "Document type may not be greater than 191 characters."
            },
            document: {
                required: "Please select a document to upload.",
                extension: "Document must be a PDF, JPG, JPEG, PNG, WEBP, DOC, or DOCX file.",
                filesize: "Document is too large. Maximum allowed size is 10 MB."
            }
        },
        errorPlacement: function (error, element) {
            if (element.attr("name") === "document") {
                setDocumentUploadError(error.text());
                return;
            }

            error.insertAfter(element);
        },
        highlight: function (element) {
            $(element).addClass("border-red-500");
        },
        unhighlight: function (element) {
            $(element).removeClass("border-red-500");
            if (element.name === "document") {
                setDocumentUploadError('');
            }
        },
        submitHandler: function (form) {
            if (!validateDocumentUpload()) {
                return false;
            }

            form.submit();
        }
    });

});
</script>
