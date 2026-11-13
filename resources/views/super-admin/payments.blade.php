<x-app-layout>
    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-950">Subscription Payments</h2>
            <p class="mt-1 text-sm text-slate-500">All company subscription transactions.</p>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1180px] table-fixed text-sm">
                <colgroup>
                    <col class="w-[18%]">
                    <col class="w-[11%]">
                    <col class="w-[10%]">
                    <col class="w-[13%]">
                    <col class="w-[10%]">
                    <col class="w-[15%]">
                    <col class="w-[12%]">
                </colgroup>
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th class="px-6 py-4 text-left">Company</th>
                        <th class="px-6 py-4 text-left">Plan</th>
                        <th class="px-6 py-4 text-left">Amount</th>
                        <th class="px-6 py-4 text-left">Payment</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-left">Dates</th>
                        <th class="px-6 py-4 text-left">Razorpay</th>
                        <th class="px-6 py-4 text-left">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($subscriptions as $subscription)
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <div class="font-semibold leading-6 text-slate-950">{{ $subscription->company?->name ?? '-' }}</div>
                                <div class="text-xs text-slate-400">ID: {{ $subscription->company?->id ? '#'.$subscription->company->id : '-' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top text-slate-700">{{ $subscription->plan?->name ?? '-' }}</td>
                            <td class="px-6 py-4 align-top font-semibold text-slate-950">Rs. {{ number_format((float) $subscription->amount_paid, 2) }}</td>
                            <td class="px-6 py-4 align-top text-slate-700">
                                <div class="font-medium leading-6">{{ $subscription->payment_method ?: 'Manual' }}</div>
                                <div class="text-xs text-slate-400">{{ $subscription->amount_paid > 0 ? 'Paid' : 'Created' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                    {{ $subscription->status === 'active' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                    {{ $subscription->status === 'trialing' ? 'bg-blue-100 text-blue-700' : '' }}
                                    {{ $subscription->status === 'pending' ? 'bg-amber-100 text-amber-700' : '' }}
                                    {{ $subscription->status === 'expired' ? 'bg-slate-200 text-slate-700' : '' }}
                                    {{ $subscription->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : '' }}">
                                    {{ str($subscription->status)->headline() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-top text-slate-700">
                                <div class="space-y-1">
                                    <div class="whitespace-nowrap">
                                        <span class="font-medium text-slate-500">Start:</span>
                                        <span class="font-semibold text-slate-900">{{ $subscription->starts_at?->format('d-m-Y') ?? '-' }}</span>
                                    </div>
                                    <div class="whitespace-nowrap">
                                        <span class="font-medium text-slate-500">End:</span>
                                        <span class="font-semibold text-slate-900">{{ $subscription->ends_at?->format('d-m-Y') ?? '-' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top text-slate-700">
                                <div class="space-y-1 break-words text-xs leading-5">
                                    <div class="min-w-0">
                                        <span class="font-medium text-slate-500">Order:</span>
                                        <span class="font-mono">{{ $subscription->razorpay_order_id ?: '-' }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="font-medium text-slate-500">Payment:</span>
                                        <span class="font-mono">{{ $subscription->razorpay_payment_id ?: '-' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                @if($subscription->company)
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if(strtolower((string) $subscription->status) !== 'trialing')
                                            <a href="{{ route('subscription.invoice', $subscription) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">
                                                View
                                            </a>
                                            <a href="{{ route('subscription.invoice', ['subscription' => $subscription, 'print' => 1]) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                Print
                                            </a>
                                        @endif
                                        <a href="{{ route('super-admin.payments.history', $subscription->company) }}" class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            History
                                        </a>
                                    </div>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-sm text-slate-500">No payment records available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-app-layout>
