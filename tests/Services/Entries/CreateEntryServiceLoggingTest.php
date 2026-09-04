<?php

namespace Tests\Services\Entries;

use DB;
use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Services\Entries\CreateEntryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Log;
use Mockery;
use PDOException;
use Tests\TestCase;

class CreateEntryServiceLoggingTest extends TestCase
{
    public function test_duplicate_uuid_insert_is_not_logged_as_an_error(): void
    {
        Log::spy();
        $this->mockEntryInsert($this->createQueryException(['23000', '1062', 'Duplicate entry']));

        $service = new CreateEntryService();

        $this->assertFalse($service->create(
            Mockery::mock(ProjectDTO::class),
            $this->createEntryStructureMock()
        ));

        Log::shouldHaveReceived('debug')->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_non_duplicate_insert_error_is_logged_as_an_error(): void
    {
        Log::spy();
        $this->mockEntryInsert($this->createQueryException([
            '23000',
            '1452',
            'Cannot add or update a child row'
        ]));

        $service = new CreateEntryService();

        $this->assertFalse($service->create(
            Mockery::mock(ProjectDTO::class),
            $this->createEntryStructureMock()
        ));

        Log::shouldHaveReceived('error')->atLeast()->once();
        Log::shouldNotHaveReceived('debug');
    }

    private function createEntryStructureMock(): EntryStructureDTO
    {
        $entryStructure = Mockery::mock(EntryStructureDTO::class);
        $entryStructure->shouldReceive('getValidatedEntry')->once()->andReturn(['entry' => []]);
        $entryStructure->shouldReceive('getExisting')->once()->andReturnNull();
        $entryStructure->shouldReceive('hasGeoLocation')->once()->andReturnFalse();
        $entryStructure->shouldReceive('getTitle')->once()->andReturn('Entry title');
        $entryStructure->shouldReceive('isBranch')->andReturnFalse();
        $entryStructure->shouldReceive('getParentUuid')->once()->andReturn('');
        $entryStructure->shouldReceive('getParentFormRef')->once()->andReturn('');
        $entryStructure->shouldReceive('getEntryUuid')->andReturn('entry-uuid');
        $entryStructure->shouldReceive('getFormRef')->once()->andReturn('form-ref');
        $entryStructure->shouldReceive('getEntryCreatedAt')->once()->andReturn('2026-01-01 00:00:00');
        $entryStructure->shouldReceive('getProjectId')->once()->andReturn(1);
        $entryStructure->shouldReceive('getHashedDeviceId')->once()->andReturn('device-id');
        $entryStructure->shouldReceive('getPlatform')->once()->andReturn('Android');
        $entryStructure->shouldReceive('getUserId')->once()->andReturn(1);

        return $entryStructure;
    }

    private function createQueryException(array $errorInfo): QueryException
    {
        $previous = new PDOException('Database insert failed');
        $previous->errorInfo = $errorInfo;

        return new QueryException('mysql', 'insert into entries', [], $previous);
    }

    private function mockEntryInsert(QueryException $exception): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('insertGetId')->once()->andThrow($exception);

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('table')
            ->once()
            ->with(config('epicollect.tables.entries'))
            ->andReturn($query);
        DB::shouldReceive('rollBack')->once();
    }
}
