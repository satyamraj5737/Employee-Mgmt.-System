<?php

namespace Tests\Unit\ViewHelpers\Team;

use Carbon\Carbon;
use Tests\TestCase;
use App\Helpers\ImageHelper;
use App\Models\Company\Ship;
use App\Models\Company\Team;
use App\Models\Company\Employee;
use Illuminate\Support\Facades\Log;
use App\Models\Company\MoraleTeamHistory;
use App\Http\ViewHelpers\Team\TeamShowViewHelper;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TeamShowViewHelperTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_gets_a_collection_of_employees(): void
    {
        $michael = $this->createAdministrator();
        $dwight = Employee::factory()->create([
            'company_id' => $michael->company_id,
        ]);
        // create one final employee with a locked status (shouldn't appear in the results)
        Employee::factory()->create([
            'company_id' => $michael->company_id,
            'locked' => true,
        ]);
        $team = Team::factory()->create([
            'company_id' => $michael->company_id,
        ]);

        $team->employees()->attach([$michael->id]);
        $team->employees()->attach([$dwight->id]);

        $collection = TeamShowViewHelper::employees($team);

        $this->assertEquals(2, $collection->count());

        $this->assertEquals(
            [
                0 => [
                    'id' => $michael->id,
                    'name' => $michael->name,
                    'avatar' => ImageHelper::getAvatar($michael, 35),
                    'position' => [
                        'id' => $michael->position->id,
                        'title' => $michael->position->title,
                    ],
                    'url' => env('APP_URL').'/'.$michael->company_id.'/employees/'.$michael->id,
                ],
                1 => [
                    'id' => $dwight->id,
                    'name' => $dwight->name,
                    'avatar' => ImageHelper::getAvatar($dwight, 35),
                    'position' => [
                        'id' => $dwight->position->id,
                        'title' => $dwight->position->title,
                    ],
                    'url' => env('APP_URL').'/'.$dwight->company_id.'/employees/'.$dwight->id,
                ],
            ],
            $collection->toArray()
        );
    }

    /** @test */
    public function it_gets_a_collection_of_recent_ships(): void
    {
        $michael = $this->createAdministrator();
        $team = Team::factory()->create([
            'company_id' => $michael->company_id,
        ]);
        $featureA = Ship::factory()->create([
            'team_id' => $team->id,
        ]);
        $featureB = Ship::factory()->create([
            'team_id' => $team->id,
        ]);
        $featureA->employees()->attach([$michael->id]);
        $collection = TeamShowViewHelper::recentShips($team);

        $this->assertEquals(2, $collection->count());

        $this->assertEquals(
            [
                0 => [
                    'id' => $featureA->id,
                    'title' => $featureA->title,
                    'description' => $featureA->description,
                    'employees' => [
                        0 => [
                            'id' => $michael->id,
                            'name' => $michael->name,
                            'avatar' => ImageHelper::getAvatar($michael, 17),
                            'url' => env('APP_URL').'/'.$michael->company_id.'/employees/'.$michael->id,
                        ],
                    ],
                    'url' => env('APP_URL').'/'.$michael->company_id.'/teams/'.$team->id.'/ships/'.$featureA->id,
                ],
                1 => [
                    'id' => $featureB->id,
                    'title' => $featureB->title,
                    'description' => $featureB->description,
                    'employees' => null,
                    'url' => env('APP_URL').'/'.$michael->company_id.'/teams/'.$team->id.'/ships/'.$featureB->id,
                ],
            ],
            $collection->toArray()
        );
    }

    /** @test */
    public function it_searches_employees_to_assign_a_team_lead(): void
    {
        $michael = Employee::factory()->create([
            'first_name' => 'ale',
            'last_name' => 'ble',
            'email' => 'ale@ble',
        ]);
        $dwight = Employee::factory()->create([
            'first_name' => 'alb',
            'last_name' => 'bli',
            'email' => 'alb@bli',
            'company_id' => $michael->company_id,
        ]);
        // the following should not be included in the search results
        Employee::factory()->create([
            'first_name' => 'ale',
            'last_name' => 'ble',
            'email' => 'ale@ble',
            'locked' => true,
            'company_id' => $michael->company_id,
        ]);

        $collection = TeamShowViewHelper::searchPotentialLead($michael->company, 'e');
        $this->assertEquals(1, $collection->count());
        $this->assertEquals(
            [
                0 => [
                    'id' => $michael->id,
                    'name' => $michael->name,
                ],
            ],
            $collection->toArray()
        );

        $collection = TeamShowViewHelper::searchPotentialLead($michael->company, 'bli');
        $this->assertEquals(1, $collection->count());
        $this->assertEquals(
            [
                0 => [
                    'id' => $dwight->id,
                    'name' => $dwight->name,
                ],
            ],
            $collection->toArray()
        );
    }

    /** @test */
    public function it_gets_a_collection_of_birthdates(): void
    {
        Carbon::setTestNow(Carbon::create(2018, 1, 1));
        $sales = Team::factory()->create([]);
        $michael = Employee::factory()->create([
            'birthdate' => null,
            'company_id' => $sales->company_id,
        ]);
        $dwight = Employee::factory()->create([
            'birthdate' => '1892-01-29',
            'first_name' => 'Dwight',
            'last_name' => 'Schrute',
            'company_id' => $sales->company_id,
        ]);
        $angela = Employee::factory()->create([
            'birthdate' => '1989-01-05',
            'first_name' => 'Angela',
            'last_name' => 'Bernard',
            'company_id' => $sales->company_id,
        ]);
        $john = Employee::factory()->create([
            'birthdate' => '1989-03-20',
            'company_id' => $sales->company_id,
        ]);

        $sales->employees()->syncWithoutDetaching([$michael->id]);
        $sales->employees()->syncWithoutDetaching([$dwight->id]);
        $sales->employees()->syncWithoutDetaching([$angela->id]);
        $sales->employees()->syncWithoutDetaching([$john->id]);

        $array = TeamShowViewHelper::birthdays($sales, $michael->company);

        $this->assertEquals(2, count($array));

        $this->assertEquals(
            [
                0 => [
                    'id' => $angela->id,
                    'name' => 'Angela Bernard',
                    'avatar' => ImageHelper::getAvatar($angela, 35),
                    'url' => env('APP_URL').'/'.$angela->company_id.'/employees/'.$angela->id,
                    'birthdate' => 'January 5th',
                    'sort_key' => '2018-01-05',
                ],
                1 => [
                    'id' => $dwight->id,
                    'name' => 'Dwight Schrute',
                    'avatar' => ImageHelper::getAvatar($dwight, 35),
                    'url' => env('APP_URL').'/'.$angela->company_id.'/employees/'.$dwight->id,
                    'birthdate' => 'January 29th',
                    'sort_key' => '2018-01-29',
                ],
            ],
            $array
        );
    }

    /** @test */
    public function it_gets_a_collection_of_morale_stats(): void
    {
        Carbon::setTestNow(Carbon::create(2018, 1, 1));

        $michael = $this->createAdministrator();
        $dwight = $this->createAnotherEmployee($michael);
        $sales = Team::factory()->create([
            'company_id' => $michael->company_id,
        ]);

        $sales->employees()->attach(
            $dwight->id,
            [
                'created_at' => Carbon::now('UTC'),
            ]
        );
        $sales->employees()->attach(
            $michael->id,
            [
                'created_at' => Carbon::now('UTC'),
            ]
        );

        // yesterday
        MoraleTeamHistory::factory()->create([
            'team_id' => $sales->id,
            'average' => 3,
            'created_at' => Carbon::now()->yesterday(),
        ]);

        // last week
        $lastWeekMonday = Carbon::now()->startOfWeek()->subDays(7);
        $lastWeekSunday = Carbon::now()->endOfWeek()->subDays(7);

        while ($lastWeekMonday->format('Y-m-d') != $lastWeekSunday->format('Y-m-d')) {
            MoraleTeamHistory::factory()->create([
                'team_id' => $sales->id,
                'average' => 2.4,
                'created_at' => $lastWeekMonday,
            ]);

            //Log::info('date: '.$lastWeekMonday->format('Y-m-d'));
            $lastWeekMonday->addDay();
        }

        // last month
        $lastMonthFirstDay = Carbon::now()->startOfMonth()->subMonth();
        $lastMonthLastDay = Carbon::now()->startOfMonth()->subDay();

        while ($lastMonthFirstDay->format('Y-m-d') != $lastMonthLastDay->format('Y-m-d')) {
            MoraleTeamHistory::factory()->create([
                'team_id' => $sales->id,
                'average' => 2.4,
                'created_at' => $lastMonthFirstDay,
            ]);

            //Log::info('date: '.$lastMonthFirstDay->format('Y-m-d'));
            $lastMonthFirstDay->addDay();
        }

        $array = TeamShowViewHelper::morale($sales, $michael);

        $this->assertEquals(
            [
                'yesterday' => [
                    'average' => 3.0,
                    'percent' => 100,
                    'emotion' => 'happy',
                ],
                'last_week' => [
                    'average' => 2.446,
                    'percent' => 82,
                    'emotion' => 'happy',
                ],
                'last_month' => [
                    'average' => 2.416,
                    'percent' => 81,
                    'emotion' => 'happy',
                ],
            ],
            $array
        );
    }

    /** @test */
    public function it_gets_the_new_hires_in_the_next_week(): void
    {
        Carbon::setTestNow(Carbon::create(2018, 1, 1));
        $sales = Team::factory()->create([]);
        $michael = Employee::factory()->create([
            'hired_at' => null,
            'company_id' => $sales->company_id,
        ]);
        $dwight = Employee::factory()->create([
            'hired_at' => Carbon::now()->addWeek()->format('Y-m-d'),
            'first_name' => 'Dwight',
            'last_name' => 'Schrute',
            'company_id' => $sales->company_id,
        ]);
        $angela = Employee::factory()->create([
            'hired_at' => '2018-01-01',
            'first_name' => 'Angela',
            'last_name' => 'Bernard',
            'company_id' => $sales->company_id,
        ]);
        Employee::factory()->create([
            'hired_at' => '2017-12-31',
            'company_id' => $sales->company_id,
        ]);

        $sales->employees()->attach([$michael->id]);
        $sales->employees()->attach([$dwight->id]);
        $sales->employees()->attach([$angela->id]);

        $collection = TeamShowViewHelper::newHiresNextWeek($sales, $sales->company);

        $this->assertEquals(1, $collection->count());

        $this->assertEquals(
            [
                0 => [
                    'id' => $dwight->id,
                    'name' => 'Dwight Schrute',
                    'avatar' => ImageHelper::getAvatar($dwight, 35),
                    'url' => env('APP_URL').'/'.$angela->company_id.'/employees/'.$dwight->id,
                    'hired_at' => 'Starts on Monday (Jan 8th) as Assistant to the regional manager',
                ],
            ],
            $collection->toArray()
        );
    }
}
