<?php

namespace Tests\Unit\ViewHelpers\Dashboard;

use Carbon\Carbon;
use Tests\TestCase;
use App\Helpers\ImageHelper;
use App\Models\Company\Ship;
use App\Models\Company\Team;
use App\Models\Company\Worklog;
use App\Models\Company\Employee;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Http\ViewHelpers\Dashboard\DashboardTeamViewHelper;
use App\Services\Company\Employee\WorkFromHome\UpdateWorkFromHomeInformation;

class DashboardTeamViewHelperTest extends TestCase
{
    use DatabaseTransactions;

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

        $array = DashboardTeamViewHelper::birthdays($sales);

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
    public function it_gets_a_collection_of_people_working_from_home(): void
    {
        Carbon::setTestNow(Carbon::create(2018, 1, 1));
        $sales = Team::factory()->create([]);
        $michael = Employee::factory()->create([
            'company_id' => $sales->company_id,
        ]);
        $dwight = Employee::factory()->create([
            'first_name' => 'Dwight',
            'last_name' => 'Schrute',
            'company_id' => $sales->company_id,
        ]);
        $angela = Employee::factory()->create([
            'first_name' => 'Angela',
            'last_name' => 'Bernard',
            'company_id' => $sales->company_id,
        ]);
        $john = Employee::factory()->create([
            'company_id' => $sales->company_id,
        ]);

        $sales->employees()->syncWithoutDetaching([$michael->id]);
        $sales->employees()->syncWithoutDetaching([$dwight->id]);
        $sales->employees()->syncWithoutDetaching([$angela->id]);
        $sales->employees()->syncWithoutDetaching([$john->id]);

        $dwight = (new UpdateWorkFromHomeInformation)->execute([
            'company_id' => $dwight->company_id,
            'author_id' => $dwight->id,
            'employee_id' => $dwight->id,
            'date' => '2018-01-01',
            'work_from_home' => true,
        ]);

        $collection = DashboardTeamViewHelper::workFromHome($sales);

        $this->assertEquals(1, $collection->count());

        $this->assertEquals(
            [
                0 => [
                    'id' => $dwight->id,
                    'name' => 'Dwight Schrute',
                    'avatar' => ImageHelper::getAvatar($dwight, 35),
                    'position' => $dwight->position,
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

        $collection = DashboardTeamViewHelper::ships($team);

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
                    'url' => route('ships.show', [
                        'company' => $featureA->team->company,
                        'team' => $featureA->team,
                        'ship' => $featureA->id,
                    ]),
                ],
                1 => [
                    'id' => $featureB->id,
                    'title' => $featureB->title,
                    'description' => $featureB->description,
                    'employees' => null,
                    'url' => route('ships.show', [
                        'company' => $featureB->team->company,
                        'team' => $featureB->team,
                        'ship' => $featureB->id,
                    ]),
                ],
            ],
            $collection->toArray()
        );
    }

    /** @test */
    public function it_gets_a_collection_of_teams(): void
    {
        $michael = $this->createAdministrator();
        $team = Team::factory()->create([
            'company_id' => $michael->company_id,
        ]);

        $collection = DashboardTeamViewHelper::teams($michael->company->teams);

        $this->assertEquals(1, $collection->count());
        $this->assertEquals(
            [
                0 => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'url' => env('APP_URL').'/'.$michael->company_id.'/teams/'.$team->id,
                ],
            ],
            $collection->toArray()
        );
    }

    /** @test */
    public function it_gets_the_list_of_worklogs_for_a_given_team_and_a_given_day(): void
    {
        $date = Carbon::now();
        $team = Team::factory()->create([]);

        // making employees
        $dwight = Employee::factory()->create([
            'company_id' => $team->company_id,
        ]);
        $michael = Employee::factory()->create([
            'company_id' => $team->company_id,
        ]);

        $team->employees()->syncWithoutDetaching([$dwight->id]);
        $team->employees()->syncWithoutDetaching([$michael->id]);

        // logging worklogs
        Worklog::factory()->create([
            'employee_id' => $dwight->id,
            'created_at' => $date,
        ]);

        $response = DashboardTeamViewHelper::worklogsForDate($team, $date, $dwight);

        $this->assertIsArray($response);

        $this->assertArrayHasKey('day', $response);
        $this->assertArrayHasKey('date', $response);
        $this->assertArrayHasKey('friendlyDate', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('completionRate', $response);
        $this->assertArrayHasKey('numberOfEmployeesInTeam', $response);
        $this->assertArrayHasKey('numberOfEmployeesWhoHaveLoggedWorklogs', $response);

        $this->assertEquals(7, count($response));
    }

    /** @test */
    public function it_gets_the_list_of_all_the_worklogs_in_the_last_7_days(): void
    {
        $date = Carbon::now();
        $team = Team::factory()->create([]);
        $dwight = Employee::factory()->create([
            'company_id' => $team->company_id,
        ]);

        $team->employees()->syncWithoutDetaching([$dwight->id]);

        $collection = DashboardTeamViewHelper::worklogsForTheLast7Days($team, $date, $dwight);
        $this->assertEquals(6, count($collection));
    }

    /** @test */
    public function it_gets_a_list_of_upcoming_new_hires(): void
    {
        Carbon::setTestNow(Carbon::create(2018, 1, 1));
        $team = Team::factory()->create([]);

        // making employees
        $dwight = Employee::factory()->create([
            'company_id' => $team->company_id,
            'hired_at' => '2018-01-02 00:00:00',
        ]);
        $michael = Employee::factory()->create([
            'company_id' => $team->company_id,
            'hired_at' => '1990-01-01 00:00:00',
        ]);
        $jim = Employee::factory()->create([
            'company_id' => $team->company_id,
            'hired_at' => '2018-01-02 00:00:00',
            'locked' => true,
        ]);

        $team->employees()->syncWithoutDetaching([$dwight->id]);
        $team->employees()->syncWithoutDetaching([$michael->id]);
        $team->employees()->syncWithoutDetaching([$jim->id]);

        $collection = DashboardTeamViewHelper::upcomingNewHires($team);

        $this->assertEquals(1, $collection->count());
        $this->assertEquals(
            [
                0 => [
                    'id' => $dwight->id,
                    'name' => $dwight->name,
                    'avatar' => ImageHelper::getAvatar($dwight, 35),
                    'hired_at' => 'Tuesday (Jan 2nd)',
                    'position' => (! $dwight->position) ? null : [
                        'id' => $dwight->position->id,
                        'title' => $dwight->position->title,
                    ],
                    'url' => env('APP_URL').'/'.$michael->company_id.'/employees/'.$dwight->id,
                ],
            ],
            $collection->toArray()
        );
    }

    /** @test */
    public function it_gets_a_list_of_upcoming_hiring_date_anniversaries(): void
    {
        Carbon::setTestNow(Carbon::create(2018, 1, 1));
        $team = Team::factory()->create([]);

        // making employees
        $dwight = Employee::factory()->create([
            'company_id' => $team->company_id,
            'hired_at' => '1990-01-02 00:00:00',
        ]);
        $michael = Employee::factory()->create([
            'company_id' => $team->company_id,
            'hired_at' => '1990-05-01 00:00:00',
        ]);
        $jim = Employee::factory()->create([
            'company_id' => $team->company_id,
            'hired_at' => '2017-04-02 00:00:00',
            'locked' => true,
        ]);

        $team->employees()->syncWithoutDetaching([$dwight->id]);
        $team->employees()->syncWithoutDetaching([$michael->id]);
        $team->employees()->syncWithoutDetaching([$jim->id]);

        $collection = DashboardTeamViewHelper::upcomingHiredDateAnniversaries($team);

        $this->assertEquals(1, $collection->count());
        $this->assertEquals(
            [
                0 => [
                    'id' => $dwight->id,
                    'name' => $dwight->name,
                    'avatar' => ImageHelper::getAvatar($dwight, 35),
                    'anniversary_date' => 'Tuesday (Jan 2nd)',
                    'anniversary_age' => '28',
                    'url' => env('APP_URL').'/'.$michael->company_id.'/employees/'.$dwight->id,
                ],
            ],
            $collection->toArray()
        );
    }
}
