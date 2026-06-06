<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomerBranch extends Model
{
    use HasFactory;

    protected $table = 'customer_branches';

    protected $fillable = [
        'customer_id',
        'country',
        'region',
        'city',
        'unit',
        'branch_name',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(CustomerContact::class, 'customer_branch_contact', 'customer_branch_id', 'customer_contact_id');
    }
}