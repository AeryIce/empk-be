<?php

namespace Tests\Feature;

use App\Enums\PanelRole;
use App\Filament\Resources\SchoolResource\Pages\EditSchool;
use App\Filament\Resources\SchoolResource\Pages\ListSchools;
use App\Models\Foundation;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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
            ->assertJsonCount(1)->assertJsonPath('0.slug', $approved->slug)
            ->assertJsonPath('0.logo_url', null)->assertJsonPath('0.yayasan_name', null);
        $this->getJson("/api/schools/{$draft->slug}")->assertNotFound();
        $this->getJson("/api/schools/{$legacyFlag->slug}")->assertNotFound();
        $this->getJson("/api/schools/{$approved->slug}")->assertOk();
        $this->getJson('/api/foundations')
            ->assertOk()->assertHeader('X-EMPK-Publication', 'strict-v1')->assertExactJson([]);
        $this->getJson("/api/foundations/{$parent->slug}")->assertNotFound();
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

    private function school(string $slug, array $fields = []): School
    {
        return School::query()->create($fields + ['nama' => ucfirst($slug), 'slug' => $slug, 'jenjang' => 'SD']);
    }
}
