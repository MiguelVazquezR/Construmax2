<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BudgetPayment extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = ['budget_id', 'amount', 'payment_date', 'reference', 'payment_method'];

    protected $casts = [
        'payment_date' => 'date',
    ];
}