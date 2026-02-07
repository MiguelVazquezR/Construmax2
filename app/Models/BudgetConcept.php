<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetConcept extends Model
{
    use HasFactory;

    protected $fillable = ['budget_id', 'concept', 'amount'];
}