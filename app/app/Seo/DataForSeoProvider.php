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
        if (! in_array($mode, ['mock', 'live'], true)) {
            throw new SeoException('provider_disabled', false, 0);
        }
        if ($mode === 'mock') {
            $this->http->preventStrayRequests();
        }
        if ($mode === 'live' && (! config('seo.live_enabled') || config('seo.live_approval') !== 'approved-after-safety-review'
            || ! config('seo.login') || ! config('seo.password'))) {
            throw new SeoException('live_not_approved', false, 0);
        }
        try {
            $pending = $this->http->acceptJson()->asJson()->timeout(config('seo.timeout'))->connectTimeout(config('seo.connect_timeout'))
                ->withOptions(['allow_redirects' => false, 'verify' => true]);
            if ($mode === 'live') {
                $pending->withBasicAuth(config('seo.login'), config('seo.password'));
            }
            $response = $pending->post($request->operation->url(), [$request->task()]);
        } catch (ConnectionException) {
            throw new SeoException('timeout_unknown_cost');
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

        return ProviderResponse::fromArray($body, $request);
    }
}
