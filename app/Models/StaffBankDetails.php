<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StaffBankDetails extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'staff_id','bank_name','account_number','ifsc','branch'
    ];

    public function staff() {
        return $this->belongsTo(Staff::class);
    }
}
