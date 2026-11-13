<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user && $user->hasPermission('accounting.expenses.create');
    }

    public function rules(): array
    {
        $companyId = (int) ($this->user()?->company_id ?? 0);

        return [
            'category_id' => ['required', Rule::exists('expense_categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'staff_id' => ['nullable', Rule::exists('staff', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'vendor_name' => ['required', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(array_keys(Expense::paymentMethods()))],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'expense_date' => ['required', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,xls,xlsx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Amount is required.',
            'category_id.required' => 'Category is required.',
            'expense_date.required' => 'Expense date is required.',
        ];
    }
}
