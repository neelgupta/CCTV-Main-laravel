@php($isEdit = $income->exists ?? false)
<div
    class="grid gap-4 md:grid-cols-2"
    x-data="{
        pendingAmount: '',
        pendingAmounts: @js($clientPendingAmounts ?? []),
        async loadPendingAmount(clientId) {
            if (!clientId) {
                this.pendingAmount = '';
                return;
            }

            const amount = this.pendingAmounts[String(clientId)];
            this.pendingAmount = Number(amount ?? 0).toFixed(2);
        }
    }"
    x-init="@js(old('client_id', $income->client_id)) && loadPendingAmount(@js(old('client_id', $income->client_id)))"
    @searchable-select:change.window="if ($event.detail.id === 'income_client_id') loadPendingAmount($event.detail.value)"
>
    <div>
        <x-searchable-select
            name="client_id"
            id="income_client_id"
            label="Client"
            :options="$clients->map(fn ($client) => ['id' => $client->id, 'label' => $client->name])->values()"
            :selected="old('client_id', $income->client_id)"
            placeholder="Search client..."
            empty-label="Select client"
        />
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Client Pending Amount</label>
        <input
            type="text"
            :value="pendingAmount === '' ? '-' : `Rs. ${pendingAmount}`"
            class="block w-full rounded-2xl border-slate-300 bg-slate-50"
            disabled
        >
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Amount</label>
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $income->amount) }}" class="block w-full border-slate-300">
    </div>
    <div>
        <x-searchable-select
            name="payment_method"
            id="income_payment_method"
            label="Payment Method"
            :options="collect($paymentMethods)->map(fn ($method) => ['id' => $method, 'label' => $method])->values()"
            :selected="old('payment_method', $income->payment_method ?: '')"
            placeholder="Search payment method..."
            empty-label="Select payment method"
        />
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Transaction No</label>
        <input type="text" name="transaction_no" value="{{ old('transaction_no', $income->transaction_no) }}" class="block w-full border-slate-300">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Received Date</label>
        <input type="date" name="received_date" value="{{ old('received_date', is_object($income->received_date) ? $income->received_date->format('Y-m-d') : ($income->received_date ?? '')) }}" class="block w-full border-slate-300">
    </div>
    <div class="md:col-span-2">
        <div class="mb-2 flex items-center justify-between">
            <label for="income_description" class="block text-sm font-medium text-slate-700">Description</label>
            <x-speech-to-text target="income_description" />
        </div>
        <textarea id="income_description" name="description" rows="4" class="block w-full border-slate-300">{{ old('description', $income->description) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-sm font-medium text-slate-700">Attachment</label>
        <input type="file" name="attachment" class="block w-full border border-dashed border-slate-300 bg-white px-4 py-3 text-sm" accept=".jpg,.jpeg,.png,.pdf,.xls,.xlsx">
        <p class="mt-1 text-xs text-slate-500">Image, PDF, Excel. Max 10 MB.</p>
        @if($isEdit && filled($income->attachment))
            <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remove_attachment" value="1">
                Remove current attachment
            </label>
        @endif
    </div>
</div>
