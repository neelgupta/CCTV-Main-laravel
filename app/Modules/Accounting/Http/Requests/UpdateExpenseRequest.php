<?php

namespace App\Modules\Accounting\Http\Requests;

class UpdateExpenseRequest extends StoreExpenseRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user && $user->hasPermission('accounting.expenses.edit');
    }

    public function rules(): array
    {
        return parent::rules();
    }
}
