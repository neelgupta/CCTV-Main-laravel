<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Models\Income;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user && $user->hasPermission('accounting.incomes.create');
    }

    public function rules(): array
    {
        $companyId = (int) ($this->user()?->company_id ?? 0);

        return [
            'category_id' => ['nullable', Rule::exists('income_categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'invoice_id' => [
                'nullable',
                Rule::exists('ticket_invoices', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'client_id' => ['required', Rule::exists('clients', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'staff_id' => ['nullable', Rule::exists('staff', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(array_keys(Income::paymentMethods()))],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'received_date' => ['required', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,xls,xlsx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Amount is required.',
            'client_id.required' => 'Client is required.',
            'received_date.required' => 'Received date is required.',
        ];
    }
}
