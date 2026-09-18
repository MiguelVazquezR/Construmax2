<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cost type categories preloaded so every expense and concept payment can
     * be classified as labor or materials. They cannot be edited, deleted or
     * deactivated (enforced in ExpenseCategoryController).
     */
    private const DEFAULT_NAMES = ['Mano de obra', 'Materiales'];

    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        foreach (self::DEFAULT_NAMES as $name) {
            DB::table('expense_categories')->insert([
                'name' => $name,
                'is_active' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
