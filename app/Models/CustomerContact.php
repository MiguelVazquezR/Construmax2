<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomerContact extends Model
{
    use HasFactory;

    protected $table = 'customer_contacts';

    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'phone',
        'position',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(CustomerBranch::class, 'customer_branch_contact', 'customer_contact_id', 'customer_branch_id');
    }
}