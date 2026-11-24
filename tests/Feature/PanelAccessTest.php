<?php

namespace Tests\Feature;

use App\Enums\PanelRole;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\FoundationResource\Pages\CreateFoundation;
use App\Filament\Resources\FoundationResource\Pages\EditFoundation;
use App\Filament\Resources\NewsPostResource\Pages\CreateNewsPost;
use App\Filament\Resources\SchoolResource\Pages\CreateSchool;
use App\Filament\Widgets\SystemStatus;
use App\Models\Foundation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function panelRoles(): array
    {
        return [[PanelRole::SuperModerator], [PanelRole::SuperAdmin]];
    }

    #[DataProvider('panelRoles')]
    public function test_active_roles_can_sign_in_and_sign_out(PanelRole $role): void
    {
        $user = $this->panelUser($role);
        Log::spy();
        $this->get('/admin')->assertRedirect('/admin/login');
        $loginPage = $this->get('/admin/login')->assertOk()->assertSeeLivewire(Login::class);
        $cookie = config('session.cookie');
        $this->withUnencryptedCookie($cookie, $loginPage->getCookie($cookie, false)->getValue());
        $oldSessionId = session()->getId();

        $login = $this->withCredentials()->postJson('/livewire/update', $this->livewireCall($loginPage->getContent(), 'authenticate', [
            'data.email' => $user->email,
            'data.password' => 'password',
        ], 'app.filament.pages.auth.login'), ['X-Livewire' => 'true'])->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->withUnencryptedCookie($cookie, $login->getCookie($cookie, false)->getValue());
        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertOk();
        Log::shouldHaveReceived('info')->with('panel.login.succeeded', ['user_id' => $user->id, 'role' => $role->value]);

        $sessionId = session()->getId();
        $token = session()->token();
        $this->post('/admin/logout')->assertRedirect();
        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->get('/admin')->assertRedirect('/admin/login');
        Log::shouldHaveReceived('info')->with('panel.logout', ['user_id' => $user->id]);
    }

    public function test_login_rejects_unknown_and_ineligible_accounts_with_the_same_error_and_throttles(): void
    {
        $ordinary = User::factory()->create();
        $pending = User::factory()->create(['role' => PanelRole::SuperAdmin->value]);
        $unknownRole = User::factory()->create(['role' => 'admin', 'panel_activated_at' => now()]);
        $active = $this->panelUser(PanelRole::SuperAdmin);
        Log::spy();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach ([$ordinary->email, $pending->email, $unknownRole->email, 'unknown@example.invalid', $ordinary->email] as $email) {
            Livewire::test(Login::class)->fillForm(['email' => $email, 'password' => 'password'])
                ->call('authenticate')->assertHasErrors(['data.email']);
            $this->assertGuest();
        }

        Livewire::test(Login::class)->fillForm(['email' => $active->email, 'password' => 'password'])
            ->call('authenticate')->assertNotified();
        $this->assertGuest();
        Log::shouldHaveReceived('notice')->with('panel.login.rejected')->times(5);
        Log::shouldHaveReceived('notice')->with('panel.login.throttled')->once();
    }

    public function test_super_admin_can_manage_operational_content_but_cannot_access_diagnostics_or_accounts(): void
    {
        $admin = $this->panelUser(PanelRole::SuperAdmin);
        $moderator = $this->panelUser(PanelRole::SuperModerator);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (['schools', 'foundations', 'news-posts'] as $resource) {
            $this->get("/admin/{$resource}")->assertOk();
            $this->get("/admin/{$resource}/create")->assertOk();
        }

        $this->get("/admin/users/{$moderator->id}/edit")->assertNotFound();
        $this->assertFalse(Gate::allows('update', $moderator));
        $this->assertFalse(Gate::allows('update', $admin));
        $this->assertFalse(SystemStatus::canView());
        Livewire::test(SystemStatus::class, ['lazy' => false])->assertForbidden();
        $this->assertTrue(Gate::forUser($moderator)->allows('view-panel-diagnostics'));

        Livewire::test(CreateFoundation::class)->fillForm(['name' => 'Yayasan Uji'])->call('create')->assertHasNoFormErrors();
        $foundation = Foundation::where('name', 'Yayasan Uji')->sole();
        $this->get("/admin/foundations/{$foundation->id}/edit")->assertOk();
        Livewire::test(EditFoundation::class, ['record' => $foundation->id])->fillForm(['name' => 'Yayasan Uji Edit'])
            ->call('save')->assertHasNoFormErrors();

        Livewire::test(CreateSchool::class)->fillForm(['nama' => 'Sekolah Uji', 'jenjang' => 'SD'])
            ->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateNewsPost::class)->fillForm(['title' => 'Berita Uji', 'body_html' => '<p>Uji</p>', 'status' => 'draft'])
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('schools', ['nama' => 'Sekolah Uji']);
        $this->assertDatabaseHas('news_posts', ['title' => 'Berita Uji']);

        $this->assertSame('Yayasan Uji Edit', $foundation->refresh()->name);
        Livewire::test(EditFoundation::class, ['record' => $foundation->id])->callAction('delete');
        $this->assertModelMissing($foundation);
    }

    public function test_role_revocation_blocks_direct_urls_and_previously_rendered_livewire_mutations(): void
    {
        $admin = $this->panelUser(PanelRole::SuperAdmin);
        $page = $this->actingAs($admin)->get('/admin/foundations/create')->assertOk();
        $payload = $this->livewireCall($page->getContent(), 'create', ['data.name' => 'Yayasan Unauthorized'], 'create-foundation');

        $admin->forceFill(['role' => null])->save();
        $this->actingAs($admin->fresh());

        $this->get('/admin/foundations/create')->assertForbidden();
        $this->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertForbidden();
        $this->assertDatabaseMissing('foundations', ['name' => 'Yayasan Unauthorized']);
    }

    public function test_csrf_protects_login_logout_and_activation_post_routes(): void
    {
        $this->app->instance('env', 'local');

        $this->post('/livewire/update', [])->assertStatus(419);
        $this->post('/admin/activate', [])->assertStatus(419);
        $this->actingAs($this->panelUser(PanelRole::SuperAdmin))->post('/admin/logout')->assertStatus(419);
        $this->assertAuthenticated();
    }

    private function panelUser(PanelRole $role): User
    {
        return User::factory()->create(['role' => $role->value, 'panel_activated_at' => now()]);
    }

    private function livewireCall(string $html, string $method, array $updates, string $component): array
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_ends_with(json_decode($snapshot, true)['memo']['name'], $component)) {
                return ['components' => [[
                    'snapshot' => $snapshot,
                    'updates' => $updates,
                    'calls' => [['path' => '', 'method' => $method, 'params' => []]],
                ]]];
            }
        }

        $this->fail("Livewire component {$component} was not rendered.");
    }
}
