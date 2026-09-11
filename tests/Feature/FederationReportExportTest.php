<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\FederationReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FederationReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_export_always_produces_an_artefact(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Organization::factory()->create();
        $this->actingAs($super);

        $url = FederationReportGenerator::generate();

        $this->assertStringContainsString('signature=', $url);

        // (** executed: the wkhtmltopdf binary is not guaranteed on every host
        // (this dev box has none), so the generator must fall back to a
        // print-ready HTML artefact instead of throwing a 500. The assertion is
        // deliberately format-agnostic so it also holds where the binary exists. **)
        $files = collect(Storage::disk('local')->files('exports'))
            ->filter(fn (string $file): bool => str_starts_with(basename($file), 'laporan-federasi-'));

        $this->assertNotEmpty($files);
        $this->assertTrue($files->every(
            fn (string $file): bool => str_ends_with($file, '.pdf') || str_ends_with($file, '.html')
        ));

        $this->assertDatabaseHas('audit_logs', ['action' => 'export.generated']);
    }
}
