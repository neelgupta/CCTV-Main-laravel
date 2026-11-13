@php($isEdit = $expense->exists ?? false)
<div class="grid gap-4 md:grid-cols-2">
    <div>
        <x-searchable-select
            name="category_id"
            id="expense_category_id"
            label="Category"
            :options="$categories->map(fn ($category) => ['id' => $category->id, 'label' => $category->name])->values()"
            :selected="old('category_id', $expense->category_id)"
            placeholder="Search category..."
            empty-label="Select category"
        />
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Vendor</label>
        <input type="text" name="vendor_name" value="{{ old('vendor_name', $expense->vendor_name) }}" class="block w-full border-slate-300">
    </div>
    <div>
        @if($isStaffScoped ?? false)
            <label class="mb-2 block text-sm font-medium text-slate-700">Staff</label>
            <input type="text" value="{{ $staffMembers->first()?->name ?? 'Current staff' }}" class="block w-full rounded-2xl border-slate-300 bg-slate-50" disabled>
        @else
            <x-searchable-select
                name="staff_id"
                id="expense_staff_id"
                label="Staff"
                :options="$staffMembers->map(fn ($staff) => ['id' => $staff->id, 'label' => $staff->name])->values()"
                :selected="old('staff_id', $expense->staff_id)"
                placeholder="Search staff..."
                empty-label="Optional"
            />
        @endif
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Title</label>
        <input type="text" name="title" value="{{ old('title', $expense->title) }}" class="block w-full border-slate-300">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Amount</label>
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $expense->amount) }}" class="block w-full border-slate-300">
    </div>
    <div>
        <x-searchable-select
            name="payment_method"
            id="expense_payment_method"
            label="Payment Method"
            :options="collect($paymentMethods)->map(fn ($method) => ['id' => $method, 'label' => $method])->values()"
            :selected="old('payment_method', $expense->payment_method ?: '')"
            placeholder="Search payment method..."
            empty-label="Select payment method"
        />
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Transaction No</label>
        <input type="text" name="transaction_no" value="{{ old('transaction_no', $expense->transaction_no) }}" class="block w-full border-slate-300">
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Expense Date</label>
        <input type="date" name="expense_date" value="{{ old('expense_date', is_object($expense->expense_date) ? $expense->expense_date->format('Y-m-d') : ($expense->expense_date ?? '')) }}" class="block w-full border-slate-300">
    </div>
    <div class="md:col-span-2">
        <div class="mb-2 flex items-center justify-between">
            <label for="expense_description" class="block text-sm font-medium text-slate-700">Description</label>
            <x-speech-to-text target="expense_description" />
        </div>
        <textarea id="expense_description" name="description" rows="4" class="block w-full border-slate-300">{{ old('description', $expense->description) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-sm font-medium text-slate-700">Attachment</label>
        <input type="file" name="attachment" class="block w-full border border-dashed border-slate-300 bg-white px-4 py-3 text-sm" accept=".jpg,.jpeg,.png,.pdf,.xls,.xlsx">
        <p class="mt-1 text-xs text-slate-500">Image, PDF, Excel. Max 10 MB.</p>
        @if($isEdit && filled($expense->attachment))
            <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remove_attachment" value="1">
                Remove current attachment
            </label>
        @endif
    </div>
</div>
