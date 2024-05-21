<?php

namespace Tests\Unit\ViewHelpers\Employee;

use Tests\TestCase;
use App\Helpers\ImageHelper;
use App\Models\Company\Employee;
use App\Models\Company\EmployeeLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Http\ViewHelpers\Employee\EmployeeLogViewHelper;

class EmployeeLogViewHelperTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_gets_a_collection_of_employee_logs(): void
    {
        $michael = Employee::factory()->create([
            'first_name' => 'michael',
            'last_name' => 'scott',
        ]);
        $log = EmployeeLog::factory()->create([
            'author_id' => $michael->id,
            'employee_id' => $michael->id,
            'author_name' => 'michael scott',
            'action' => 'account_created',
            'audited_at' => '2020-01-12 00:00:00',
        ]);

        $logs = $michael->employeeLogs()->with('author')->get();

        $this->assertEquals(
            [
                'localized_content' => '',
                'author' => [
                    'id' => $michael->id,
                    'name' => 'michael scott',
                    'avatar' => ImageHelper::getAvatar($michael, 34),
                    'url' => env('APP_URL').'/'.$michael->company_id.'/employees/'.$michael->id,
                ],
                'localized_audited_at' => 'Jan 12, 2020 12:00 AM',
            ],
            EmployeeLogViewHelper::list($logs, $michael->company)->toArray()[0]
        );
    }

    /** @test */
    public function it_gets_the_information_about_the_employee(): void
    {
        $michael = Employee::factory()->create();

        $this->assertEquals(
            [
                'id' => $michael->id,
                'name' => $michael->name,
                'avatar' => ImageHelper::getAvatar($michael, 80),
            ],
            EmployeeLogViewHelper::employee($michael)
        );
    }
}
