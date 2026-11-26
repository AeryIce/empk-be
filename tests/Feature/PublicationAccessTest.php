<?php

namespace Tests\Feature;

use App\Enums\PanelRole;
use App\Filament\Resources\FoundationResource\Pages\EditFoundation;
use App\Filament\Resources\FoundationResource\Pages\ListFoundations;
use App\Filament\Resources\FoundationResource\Pages\ViewFoundation;
use App\Filament\Resources\FoundationResource\RelationManagers\SchoolsRelationManager;
use App\Filament\Resources\NewsPostResource\Pages\EditNewsPost;
use App\Filament\Resources\SchoolResource\Pages\EditSchool;
use App\Filament\Resources\SchoolResource\Pages\ListSchools;
use App\Models\Foundation;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class PublicationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_directory_only_returns_published_profiles(): void
    {
        $parent = Foundation::query()->create([
            'name' => 'Legacy LPK',
            'slug' => 'legacy-lpk',
            'is_active' => true,
            'logo_url' => 'https://example.test/legacy-logo.png',
        ]);
        $draft = $this->school('draft', ['foundation_id' => $parent->id]);
        $legacyFlag = $this->school('legacy-flag', ['is_published' => true]);
        $reviewer = User::factory()->create(['role' => PanelRole::SuperModerator->value, 'panel_activated_at' => now()]);
        $approved = $this->school('approved', [
            'foundation_id' => $parent->id,
            'is_published' => true,
            'status_verifikasi' => 'verified',
            'verified_at' => now(),
            'verified_by' => $reviewer->id,
        ]);

        $this->getJson('/api/schools')
            ->assertOk()->assertHeader('X-EMPK-Publication', 'strict-v1')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonCount(1)->assertJsonPath('0.slug', $approved->slug)
            ->assertJsonPath('0.logo_url', null)->assertJsonPath('0.yayasan_name', null);
        $this->getJson("/api/schools/{$draft->slug}")->assertNotFound()->assertHeaderContains('Cache-Control', 'no-store');
        $this->getJson("/api/schools/{$legacyFlag->slug}")->assertNotFound()->assertHeaderContains('Cache-Control', 'no-store');
        $this->getJson("/api/schools/{$approved->slug}")->assertOk()->assertHeaderContains('Cache-Control', 'no-store');
        $this->getJson('/api/foundations')
            ->assertOk()->assertHeader('X-EMPK-Publication', 'strict-v1')
            ->assertHeaderContains('Cache-Control', 'no-store')->assertExactJson([]);
        $this->getJson("/api/foundations/{$parent->slug}")->assertNotFound()->assertHeaderContains('Cache-Control', 'no-store');
        $this->assertDatabaseCount('schools', 3);
        $this->assertDatabaseCount('foundations', 1);
        $this->assertSame($parent->id, $draft->refresh()->foundation_id);
    }

    public function test_directory_revalidations_return_fresh_empty_lists_and_uncacheable_errors(): void
    {
        $legacy = $this->school('legacy-flag', ['is_published' => true]);
        Route::get('/api/schools/test/upstream-error', fn () => abort(503));

        $headers = [
            'If-None-Match' => '*',
            'If-Modified-Since' => 'Thu, 01 Jan 2037 00:00:00 GMT',
        ];
        foreach (['/api/schools', '/api/foundations'] as $path) {
            $this->getJson($path, $headers)
                ->assertOk()->assertExactJson([])
                ->assertHeaderContains('Cache-Control', 'no-store');
        }
        $this->get("/api/schools/{$legacy->slug}", $headers)
            ->assertNotFound()->assertHeaderContains('Cache-Control', 'no-store');
        $this->getJson('/api/foundations/missing/path')
            ->assertNotFound()->assertHeaderContains('Cache-Control', 'no-store');
        $this->getJson('/api/schools/test/upstream-error')
            ->assertServiceUnavailable()->assertHeaderContains('Cache-Control', 'no-store');
        $this->postJson("/api/schools/{$legacy->slug}")
            ->assertMethodNotAllowed()->assertHeaderContains('Cache-Control', 'no-store');

        $this->assertTrue($legacy->refresh()->is_published);
        $this->assertSame('draft', $legacy->status_verifikasi);
    }

    public function test_ordinary_operator_cannot_delete_or_sync_or_access_internal_data(): void
    {
        $operator = User::factory()->create(['role' => PanelRole::SuperAdmin->value, 'panel_activated_at' => now()]);
        $moderator = User::factory()->create(['role' => PanelRole::SuperModerator->value, 'panel_activated_at' => now()]);
        $legacy = User::factory()->create();
        $draft = $this->school('draft', ['nama_bank' => 'private marker', 'dapo_snapshot' => ['private' => 'snapshot marker']]);
        $approved = $this->school('approved', ['is_published' => true]);

        foreach ([School::class, Foundation::class, NewsPost::class] as $model) {
            $this->assertFalse(Gate::forUser($operator)->allows('delete', new $model()));
            $this->assertFalse(Gate::forUser($operator)->allows('deleteAny', $model));
            $this->assertTrue(Gate::forUser($moderator)->allows('delete', new $model()));
            $this->assertFalse(Gate::forUser($legacy)->allows('viewAny', $model));
        }
        $this->assertFalse(Gate::forUser($operator)->allows('syncDapo', $draft));
        $this->assertTrue(Gate::forUser($moderator)->allows('syncDapo', $draft));
        $this->assertFalse(Gate::forUser($moderator)->allows('syncDapo', $approved));

        $this->actingAs($operator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get("/admin/schools/{$approved->id}/edit")->assertForbidden();
        Livewire::test(ListSchools::class)
            ->assertTableActionHidden('harvy_sync', $draft)
            ->assertTableBulkActionHidden('delete');
        Livewire::test(EditSchool::class, ['record' => $draft->id])
            ->assertDontSee('private marker')->assertDontSee('snapshot marker')
            ->set('data.nama_bank', 'forged')
            ->set('data.dapo_snapshot', ['forged' => true])
            ->set('data.is_published', true)
            ->call('save')->assertHasNoFormErrors();

        $draft->refresh();
        $this->assertFalse($draft->is_published);
        $this->assertSame('private marker', $draft->nama_bank);
        $this->assertSame(['private' => 'snapshot marker'], $draft->dapo_snapshot);
    }

    public function test_direct_livewire_calls_cannot_run_restricted_actions_or_change_registry_records(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $operator = User::factory()->create(['role' => PanelRole::SuperAdmin->value, 'panel_activated_at' => now()]);
        $foundation = Foundation::query()->create(['name' => 'LPK', 'slug' => 'lpk']);
        $school = $this->school('school', ['foundation_id' => $foundation->id, 'dapo_school_url' => 'https://example.test/school']);
        $news = NewsPost::query()->create(['title' => 'Berita', 'slug' => 'berita']);
        $original = $school->fresh()->getRawOriginal();

        $this->actingAs($operator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListSchools::class)
            ->call('mountTableAction', 'harvy_sync', (string) $school->id)->assertForbidden();
        Livewire::test(SchoolsRelationManager::class, ['ownerRecord' => $foundation, 'pageClass' => ViewFoundation::class])
            ->call('mountTableAction', 'harvy_sync', (string) $school->id)->assertForbidden();
        Livewire::test(ListSchools::class)
            ->call('mountTableBulkAction', 'delete', [(string) $school->id])->assertForbidden();
        Livewire::test(ListFoundations::class)
            ->call('mountTableBulkAction', 'delete', [(string) $foundation->id])->assertForbidden();
        Livewire::test(EditFoundation::class, ['record' => $foundation->id])
            ->call('mountAction', 'delete')->assertForbidden();
        Livewire::test(EditNewsPost::class, ['record' => $news->id])
            ->call('mountAction', 'delete')->assertForbidden();

        $this->assertSame($original, $school->refresh()->getRawOriginal());
        $this->assertModelExists($foundation);
        $this->assertModelExists($news);
        Http::assertNothingSent();
    }

    public function test_previously_mounted_actions_are_rechecked_when_submitted(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $operator = User::factory()->create(['role' => PanelRole::SuperModerator->value, 'panel_activated_at' => now()]);
        $foundation = Foundation::query()->create(['name' => 'LPK', 'slug' => 'lpk']);
        $school = $this->school('school', ['foundation_id' => $foundation->id, 'dapo_school_url' => 'https://example.test/school']);

        $this->actingAs($operator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $sync = Livewire::test(ListSchools::class)->call('mountTableAction', 'harvy_sync', (string) $school->id);
        $bulk = Livewire::test(ListSchools::class)->call('mountTableBulkAction', 'delete', [(string) $school->id]);
        $delete = Livewire::test(EditFoundation::class, ['record' => $foundation->id])->call('mountAction', 'delete');
        $sync->assertSet('mountedTableActions.0', 'harvy_sync');
        $bulk->assertSet('mountedTableBulkAction', 'delete');
        $delete->assertSet('mountedActions.0', 'delete');

        $operator->forceFill(['role' => PanelRole::SuperAdmin->value])->save();
        $this->actingAs($operator);
        $sync->call('callMountedTableAction')->assertForbidden();
        $bulk->call('callMountedTableBulkAction')->assertForbidden();
        $delete->call('callMountedAction')->assertForbidden();

        $this->assertModelExists($school);
        $this->assertModelExists($foundation);
        $this->assertSame($foundation->id, $school->refresh()->foundation_id);
        Http::assertNothingSent();
    }

    private function school(string $slug, array $fields = []): School
    {
        return School::query()->create($fields + ['nama' => ucfirst($slug), 'slug' => $slug, 'jenjang' => 'SD']);
    }
}
