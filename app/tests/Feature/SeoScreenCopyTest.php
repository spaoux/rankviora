<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeoScreenCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function workspace(): Workspace
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Ekran testi']);
        $workspace->users()->attach($user, ['role' => 'owner']);
        $this->actingAs($user);

        return $workspace;
    }

    public static function modes(): array
    {
        return [
            'mock' => ['mock', 'MOCK modunda dış ağ isteği yoktur', 'SANDBOX modunda resmî'],
            'sandbox' => ['sandbox', 'SANDBOX modunda resmî sandbox.dataforseo.com', 'MOCK modunda dış ağ isteği yoktur'],
            'live' => ['live', 'LIVE modunda, onay ve güvenlik kontrolleri izin verdiğinde', 'MOCK modunda dış ağ isteği yoktur'],
        ];
    }

    #[DataProvider('modes')]
    public function test_seo_engine_description_matches_current_mode(string $mode, string $expected, string $absent): void
    {
        config(['seo.mode' => $mode]);
        $response = $this->get(route('seo.index', $this->workspace()))
            ->assertOk()->assertSee($expected)->assertDontSee($absent);

        if ($mode === 'sandbox') {
            $response->assertSee('gerçek SEO verisi değildir ve ücret oluşmaz');
        }

        Http::assertNothingSent();
    }

    public function test_paid_api_request_card_excludes_mock_and_sandbox_units(): void
    {
        $workspace = $this->workspace();
        foreach (['live' => 7, 'mock' => 13, 'sandbox' => 17] as $mode => $units) {
            $workspace->apiUsageLogs()->create([
                'provider' => 'dataforseo', 'operation' => 'domain_rank_overview',
                'mode' => $mode, 'is_demo' => $mode !== 'live', 'units' => $units, 'cost' => 0,
            ]);
        }

        $response = $this->get(route('workspace.show', [$workspace, 'usage']))
            ->assertOk()->assertSee('Ücretli API isteği')->assertDontSee('Gerçek API isteği');
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $count = $xpath->query('//p[normalize-space(.)="Ücretli API isteği"]/following-sibling::p[1]');
        $this->assertSame(1, $count->length);
        $this->assertSame('7', trim($count->item(0)->textContent));
        Http::assertNothingSent();
    }
}
