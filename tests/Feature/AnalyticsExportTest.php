<?php

namespace Tests\Feature;

use App\Exports\AnalyticsMultiSheetExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/** The Analytics Excel download must build a real workbook with the installed Excel package. */
class AnalyticsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_analytics_export_builds_a_workbook(): void
    {
        $categoryId = DB::table('categories')->insertGetId(['name' => 'drinks', 'created_at' => now(), 'updated_at' => now()]);
        $productId = DB::table('products')->insertGetId(['name' => 'Club Beer', 'category_id' => $categoryId, 'selling_price' => 30, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('daily_sales_summaries')->insert([
            'sales_date' => '2026-10-28', 'drinks_total' => 300, 'food_total' => 100, 'on_the_house' => 0, 'items_sold' => 10,
            'total_cash' => 400, 'total_momo' => 0, 'total_hubtel' => 0, 'snooker' => 0, 'product_id' => $productId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $xlsx = Excel::raw(new AnalyticsMultiSheetExport('2026-10-01', '2026-10-31'), ExcelWriter::XLSX);

        $this->assertStringStartsWith('PK', $xlsx, 'an .xlsx file is a zip archive');
        $this->assertGreaterThan(1000, strlen($xlsx));
    }

    public function test_the_download_button_returns_the_workbook(): void
    {
        DB::connection()->getPdo()->sqliteCreateFunction('TO_CHAR', fn ($value) => substr((string) $value, 0, 7));

        $roleId = DB::table('roles')->insertGetId(['name' => 'Viewer '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $user = \App\Models\User::forceCreate(['name' => 'Viewer', 'username' => 'viewer'.uniqid(), 'phone' => '+233249999999', 'role_id' => $roleId, 'password' => '482913', 'must_change_password' => false]);
        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Livewire\Pages\Analytics::class)
            ->set('timeframe', 'custom')->set('startDate', '2026-10-01')->set('endDate', '2026-10-31')
            ->call('exportCsv')
            ->assertFileDownloaded();
    }
}

