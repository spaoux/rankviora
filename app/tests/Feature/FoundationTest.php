<?php

namespace Tests\Feature;

use App\Livewire\ProjectManager;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function tenant(string $role = 'owner'): array
    {
        $u = User::factory()->create();
        $w = Workspace::create(['name' => 'Örnek ekip']);
        $w->users()->attach($u, ['role' => $role]);

        return [$u, $w];
    }

    public function test_guest_must_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Giriş yap');
    }

    public function test_login_and_logout(): void
    {
        $u = User::factory()->create(['password' => Hash::make('Test!Password827')]);
        $this->post('/login', ['email' => $u->email, 'password' => 'Test!Password827'])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_invalid_login_and_rate_limit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'missing@example.com', 'password' => 'invalid'])->assertSessionHasErrors('email');
        } $this->assertGuest();
    }

    public function test_workspace_creation_ownership_and_crud(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/workspaces', ['name' => 'Yeni ekip'])->assertRedirect();
        $w = Workspace::firstOrFail();
        $this->assertSame('owner', $u->workspaces()->first()->pivot->role);
        $this->patch('/workspaces/'.$w->id, ['name' => 'Güncel ekip'])->assertRedirect();
        $this->assertDatabaseHas('workspaces', ['name' => 'Güncel ekip']);
        $this->delete('/workspaces/'.$w->id)->assertRedirect('/');
        $this->assertDatabaseMissing('workspaces', ['id' => $w->id]);
    }

    public function test_other_tenant_is_forbidden_on_all_pages_and_mutations(): void
    {
        [$a,$wa] = $this->tenant();
        [$b,$wb] = $this->tenant();
        $wb->update(['name' => 'ForeignUniqueWorkspace']);
        $this->actingAs($a)->get('/')->assertDontSee('ForeignUniqueWorkspace');
        foreach (['seo', 'geo', 'projects', 'history', 'usage', 'team'] as $section) {
            $this->get('/workspaces/'.$wb->id.'/'.$section)->assertForbidden();
        }
        $this->patch('/workspaces/'.$wb->id, ['name' => 'Hacked'])->assertForbidden();
        $this->delete('/workspaces/'.$wb->id)->assertForbidden();
        $this->post('/workspaces/'.$wb->id.'/members', ['email' => $a->email])->assertForbidden();
        Livewire::actingAs($a)->test(ProjectManager::class, ['workspaceId' => $wb->id])->assertForbidden();
    }

    public function test_owner_project_competitor_and_demo_crud(): void
    {
        [$u,$w] = $this->tenant();
        $component = Livewire::actingAs($u)->test(ProjectManager::class, ['workspaceId' => $w->id])->set('name', 'Marka')->set('domain', 'EXAMPLE.COM')->call('save')->assertHasNoErrors();
        $p = $w->projects()->firstOrFail();
        $this->assertSame('example.com', $p->domain);
        $component->call('edit', $p->id)->set('name', 'Güncel proje')->call('save')->assertHasNoErrors();
        $this->assertSame('Güncel proje', $p->fresh()->name);
        $component->set('competitorDomain', 'rival.com')->call('addCompetitor', $p->id)->assertHasNoErrors();
        $c = $p->competitors()->firstOrFail();
        $component->call('demo', $p->id, 'seo')->call('demo', $p->id, 'geo');
        $this->assertSame(2, $p->analysisRuns()->where('is_demo', true)->count());
        $this->assertDatabaseCount('api_usage_logs', 0);
        $component->call('deleteCompetitor', $p->id, $c->id);
        $this->assertDatabaseCount('competitors', 0);
        $component->call('delete', $p->id);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('analysis_runs', 0);
    }

    public function test_member_can_read_but_cannot_write(): void
    {
        [$u,$w] = $this->tenant('member');
        $this->actingAs($u)->get('/workspaces/'.$w->id.'/projects')->assertOk();
        $this->patch('/workspaces/'.$w->id, ['name' => 'Hacked'])->assertForbidden();
        Livewire::actingAs($u)->test(ProjectManager::class, ['workspaceId' => $w->id])->set('name', 'Forbidden')->set('domain', 'example.com')->call('save')->assertForbidden();
    }

    public function test_foreign_project_and_competitor_ids_cannot_be_used(): void
    {
        [$u,$w] = $this->tenant();
        [$other,$foreign] = $this->tenant();
        $p = $foreign->projects()->create(['name' => 'Secret', 'domain' => 'secret.com']);
        foreach (['edit', 'delete'] as $action) {
            Livewire::actingAs($u)->test(ProjectManager::class, ['workspaceId' => $w->id])->call($action, $p->id)->assertNotFound();
        }
        Livewire::actingAs($u)->test(ProjectManager::class, ['workspaceId' => $w->id])->call('demo', $p->id, 'seo')->assertNotFound();
        Livewire::actingAs($u)->test(ProjectManager::class, ['workspaceId' => $w->id])->set('competitorDomain', 'rival.com')->call('addCompetitor', $p->id)->assertNotFound();
        $own = $w->projects()->create(['name' => 'Own', 'domain' => 'own.com']);
        $c = $p->competitors()->create(['domain' => 'foreign.com']);
        Livewire::actingAs($u)->test(ProjectManager::class, ['workspaceId' => $w->id])->call('deleteCompetitor', $own->id, $c->id)->assertNotFound();
        $this->assertDatabaseHas('competitors', ['id' => $c->id]);
    }

    public function test_membership_revocation_is_checked_on_livewire_action(): void
    {
        [$u,$w] = $this->tenant();
        $component = Livewire::actingAs($u)->test(ProjectManager::class, ['workspaceId' => $w->id])->set('name', 'No')->set('domain', 'example.com');
        $w->users()->detach($u);
        $component->call('save')->assertForbidden();
    }

    public function test_domain_validation_duplicate_and_own_competitor(): void
    {
        [$u,$w] = $this->tenant();
        $c = Livewire::actingAs($u)->test(ProjectManager::class, ['workspaceId' => $w->id])->set('name', 'Test')->set('domain', 'https://example.com/path')->call('save')->assertHasErrors('domain');
        $c->set('domain', 'example.com')->call('save')->assertHasNoErrors();
        $p = $w->projects()->firstOrFail();
        $c->set('name', 'Duplicate')->set('domain', 'example.com')->call('save')->assertHasErrors('domain');
        $c->set('competitorDomain', 'example.com')->call('addCompetitor', $p->id)->assertHasErrors('competitorDomain');
    }

    public function test_history_usage_and_dashboard_are_tenant_scoped(): void
    {
        [$u,$w] = $this->tenant();
        [$other,$foreign] = $this->tenant();
        $p = $foreign->projects()->create(['name' => 'ForeignUniqueProject', 'domain' => 'secret.com']);
        $p->analysisRuns()->create(['type' => 'seo', 'results' => ['score' => 99]]);
        $foreign->apiUsageLogs()->create(['provider' => 'ForeignUniqueProvider', 'operation' => 'test', 'units' => 99]);
        $this->actingAs($u)->get('/workspaces/'.$w->id.'/history')->assertOk()->assertDontSee('ForeignUniqueProject');
        $this->get('/workspaces/'.$w->id.'/usage')->assertOk()->assertDontSee('ForeignUniqueProvider');
        foreach (['seo', 'geo'] as $section) {
            $this->get('/workspaces/'.$w->id.'/'.$section)->assertOk()->assertSee('DEMO');
        }
        $this->get('/workspaces/'.$w->id.'/team')->assertOk();
    }

    public function test_owner_manages_members_and_member_cannot_escalate(): void
    {
        [$owner,$w] = $this->tenant();
        $member = User::factory()->create();
        $this->actingAs($owner)->post('/workspaces/'.$w->id.'/members', ['email' => $member->email])->assertRedirect();
        $this->assertSame('member', $member->workspaces()->first()->pivot->role);
        $this->actingAs($member)->post('/workspaces/'.$w->id.'/members', ['email' => $owner->email])->assertForbidden();
        $this->actingAs($owner)->delete('/workspaces/'.$w->id.'/members/'.$owner->id)->assertUnprocessable();
        $this->delete('/workspaces/'.$w->id.'/members/'.$member->id)->assertRedirect();
        $this->actingAs($member)->get('/workspaces/'.$w->id.'/seo')->assertForbidden();
    }
}
