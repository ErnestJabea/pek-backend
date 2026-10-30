<?php

namespace Tests\Feature;

use App\Filament\Filters\DateRangeFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateRangeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_range_filter_builds_correctly(): void
    {
        $filter = DateRangeFilter::make('created_at', 'Période d\'inscription');

        $this->assertEquals('created_at_date_range', $filter->getName());
        $this->assertEquals('Période d\'inscription', $filter->getLabel());
        
        $fields = $filter->getFormSchema();
        $this->assertCount(2, $fields);
        $this->assertEquals('from', $fields[0]->getName());
        $this->assertEquals('until', $fields[1]->getName());
    }

    public function test_date_range_filter_filters_records_by_start_and_end_date(): void
    {
        $user1 = User::factory()->create([
            'first_name' => 'Jean',
            'created_at' => '2026-01-10 10:00:00',
        ]);

        $user2 = User::factory()->create([
            'first_name' => 'Paul',
            'created_at' => '2026-02-15 10:00:00',
        ]);

        $user3 = User::factory()->create([
            'first_name' => 'Marc',
            'created_at' => '2026-03-20 10:00:00',
        ]);

        $filter = DateRangeFilter::make('created_at', 'Période');

        // 1. Both from & until
        $query = User::query();
        $filter->apply($query, [
            'from' => '2026-02-01',
            'until' => '2026-02-28',
        ]);
        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Paul', $results->first()->first_name);

        // 2. Only from
        $queryFrom = User::query();
        $filter->apply($queryFrom, [
            'from' => '2026-02-01',
            'until' => null,
        ]);
        $this->assertEquals(2, $queryFrom->count());

        // 3. Only until
        $queryUntil = User::query();
        $filter->apply($queryUntil, [
            'from' => null,
            'until' => '2026-02-01',
        ]);
        $this->assertEquals(1, $queryUntil->count());
        $this->assertEquals('Jean', $queryUntil->first()->first_name);
    }
}
