<?php

namespace Tests\Unit\Services\Company\Adminland\Employee;

use Tests\TestCase;
use App\Jobs\ServiceQueue;
use App\Models\Company\Employee;
use App\Models\Company\ImportJob;
use Illuminate\Support\Facades\Queue;
use App\Models\Company\ImportJobReport;
use Illuminate\Validation\ValidationException;
use App\Exceptions\NotEnoughPermissionException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\Company\Adminland\Employee\AddEmployeeToCompany;
use App\Services\Company\Adminland\Employee\ImportEmployeesFromTemporaryTable;

class ImportEmployeesFromTemporaryTableTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_stores_employees_in_a_temporary_table_as_administrator(): void
    {
        $michael = $this->createAdministrator();
        $importJob = ImportJob::factory()->create([
            'company_id' => $michael->company_id,
        ]);
        $report = ImportJobReport::factory()->create([
            'import_job_id' => $importJob->id,
        ]);
        $this->executeService($michael, $importJob, $report);
    }

    /** @test */
    public function it_stores_employees_in_a_temporary_table_as_hr(): void
    {
        $michael = $this->createHR();
        $importJob = ImportJob::factory()->create([
            'company_id' => $michael->company_id,
        ]);
        $report = ImportJobReport::factory()->create([
            'import_job_id' => $importJob->id,
        ]);
        $this->executeService($michael, $importJob, $report);
    }

    /** @test */
    public function normal_employees_cant_execute_the_service(): void
    {
        $michael = $this->createEmployee();
        $importJob = ImportJob::factory()->create([
            'company_id' => $michael->company_id,
        ]);
        $report = ImportJobReport::factory()->create([
            'import_job_id' => $importJob->id,
        ]);

        $this->expectException(NotEnoughPermissionException::class);
        $this->executeService($michael, $importJob, $report);
    }

    /** @test */
    public function it_fails_if_wrong_parameters_are_given(): void
    {
        $michael = $this->createAdministrator();

        $request = [
            'company_id' => $michael->company_id,
            'author_id' => $michael->id,
        ];

        $this->expectException(ValidationException::class);
        (new ImportEmployeesFromTemporaryTable($request))->handle();
    }

    /** @test */
    public function it_save_state_when_failing(): void
    {
        $michael = $this->createEmployee();
        $importJob = ImportJob::factory()->create([
            'company_id' => $michael->company_id,
        ]);
        $report = ImportJobReport::factory()->create([
            'import_job_id' => $importJob->id,
        ]);

        $request = [
            'company_id' => $michael->company_id,
            'author_id' => $michael->id,
            'import_job_id' => $importJob->id,
        ];

        try {
            $this->expectException(NotEnoughPermissionException::class);
            ImportEmployeesFromTemporaryTable::dispatchSync($request);
        } finally {
            $this->assertDatabaseHas('import_jobs', [
                'id' => $importJob->id,
                'status' => 'failed',
            ]);
        }
    }

    private function executeService(Employee $michael, ImportJob $importJob, ImportJobReport $report): void
    {
        Queue::fake();

        $request = [
            'company_id' => $michael->company_id,
            'author_id' => $michael->id,
            'import_job_id' => $importJob->id,
        ];

        (new ImportEmployeesFromTemporaryTable($request))->handle();

        $this->assertDatabaseHas('import_jobs', [
            'id' => $importJob->id,
            'company_id' => $michael->company_id,
            'status' => ImportJob::IMPORTED,
        ]);

        Queue::assertPushed(ServiceQueue::class, function ($service) {
            return $service instanceof ServiceQueue
                && $service->service instanceof AddEmployeeToCompany;
        });
    }
}
