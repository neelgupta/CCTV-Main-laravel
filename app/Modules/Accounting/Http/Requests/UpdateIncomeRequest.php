<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Models\Income;
use Illuminate\Validation\Rule;

class UpdateIncomeRequest extends StoreIncomeRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user && $user->hasPermission('accounting.incomes.edit');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $rules['invoice_id'] = array_merge($rules['invoice_id'], [
            Rule::unique('incomes', 'invoice_id')
                ->where(fn ($query) => $query->where('company_id', (int) ($this->user()?->company_id ?? 0)))
                ->ignore($this->route('income') instanceof Income ? $this->route('income')->getKey() : $this->route('income')),
        ]);

        return $rules;
    }
}
