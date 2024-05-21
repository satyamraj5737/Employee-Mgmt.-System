<?php

namespace Tests\Unit\Models\Company;

use Tests\TestCase;
use App\Models\Company\Answer;
use App\Models\Company\Company;
use App\Models\Company\Question;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QuestionTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_belongs_to_a_company(): void
    {
        $question = Question::factory()->create([]);
        $this->assertTrue($question->company()->exists());
    }

    /** @test */
    public function it_has_many_answers(): void
    {
        $question = Question::factory()->create();
        Answer::factory()->count(2)->create([
            'question_id' => $question->id,
        ]);

        $this->assertTrue($question->answers()->exists());
    }

    /** @test */
    public function it_returns_an_object(): void
    {
        $dunder = Company::factory()->create([]);
        $question = Question::factory()->create([
            'company_id' => $dunder->id,
            'title' => 'dunder',
            'created_at' => '2020-01-12 00:00:00',
        ]);

        $this->assertEquals(
            [
                'id' => $question->id,
                'company' => [
                    'id' => $dunder->id,
                ],
                'title' => 'dunder',
                'active' => false,
                'url' => env('APP_URL').'/'.$dunder->id.'/company/questions/'.$question->id,
                'number_of_answers' => 0,
                'created_at' => '2020-01-12 00:00:00',
            ],
            $question->toObject()
        );
    }
}
