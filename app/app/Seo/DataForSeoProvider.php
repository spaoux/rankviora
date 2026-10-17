<?php

namespace App\Seo;

use App\Seo\Dto\ProviderResponse;
use App\Seo\Dto\SeoRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

final class DataForSeoProvider implements Provider
{
    public function __construct(private Factory $http) {}

    public function fetch(SeoRequest $request): ProviderResponse
    {
        $mode = config('seo.mode');
        if (! in_array($mode, ['mock', 'sandbox', 'live'], true)) {
            throw new SeoException('provider_disabled', false, 0);
        }
        if ($mode === 'mock') {
            $this->http->preventStrayRequests();
        }
        if ($mode === 'sandbox') {
            if (! config('seo.sandbox_enabled') || ! in_array(app()->environment(), ['local', 'testing'], true)) {
                throw new SeoException('sandbox_disabled', false, 0);
            }
            if ($request->operation !== Operation::Overview) {
                throw new SeoException('sandbox_overview_only', false, 0);
            }
            if (! config('seo.login') || ! config('seo.password')) {
                throw new SeoException('sandbox_credentials_missing', false, 0);
            }
        }
        if ($mode === 'live' && (! config('seo.live_enabled') || config('seo.live_approval') !== 'approved-after-safety-review'
            || ! config('seo.login') || ! config('seo.password'))) {
            throw new SeoException('live_not_approved', false, 0);
        }
        try {
            $pending = $this->http->acceptJson()->asJson()->timeout(config('seo.timeout'))->connectTimeout(config('seo.connect_timeout'))
                ->withOptions(['allow_redirects' => false, 'verify' => true]);
            if ($mode === 'live' || $mode === 'sandbox') {
                $pending->withBasicAuth(config('seo.login'), config('seo.password'));
            }
            $response = $pending->post($request->operation->url($mode), [$request->task()]);
        } catch (ConnectionException) {
            throw new SeoException('timeout_unknown_cost');
        } catch (\Throwable) {
            // Never retain a transport exception that might contain Basic Auth or request options.
            throw new SeoException('transport_unknown');
        }
        if ($response->status() === 401 || $response->status() === 403) {
            throw new SeoException('provider_auth', false, 0, $response->status());
        }
        if ($response->status() !== 200) {
            throw new SeoException('http_'.$response->status());
        }
        $body = $response->json();
        if (! is_array($body)) {
            throw new SeoException('invalid_json');
        }

        return ProviderResponse::fromArray($body, $request, $mode === 'sandbox');
    }
}
