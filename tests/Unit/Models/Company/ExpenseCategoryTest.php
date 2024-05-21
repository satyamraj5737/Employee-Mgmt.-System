<?php

namespace Tests\Unit\Models\Company;

use Tests\TestCase;
use App\Models\Company\Expense;
use App\Models\Company\ExpenseCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ExpenseCategoryTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_belongs_to_a_company(): void
    {
        $category = ExpenseCategory::factory()->create([]);
        $this->assertTrue($category->company()->exists());
    }

    /** @test */
    public function it_has_many_expenses(): void
    {
        $category = ExpenseCategory::factory()->create([]);
        Expense::factory()->count(2)->create([
            'expense_category_id' => $category->id,
        ]);

        $this->assertTrue($category->expenses()->exists());
    }
}
