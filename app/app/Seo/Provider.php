<?php

namespace App\Seo;

use App\Seo\Dto\ProviderResponse;
use App\Seo\Dto\SeoRequest;

interface Provider
{
    public function fetch(SeoRequest $request): ProviderResponse;
}
