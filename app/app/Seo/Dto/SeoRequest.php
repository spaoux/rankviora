<?php

namespace App\Seo\Dto;

use App\Seo\DomainName;
use App\Seo\Operation;
use App\Seo\SeoException;

final readonly class SeoRequest
{
    public string $target;

    public ?string $other;

    public function __construct(
        public Operation $operation,
        string $target,
        ?string $other = null,
        public bool $intersections = true,
        public int $limit = 1000,
        public int $offset = 0,
        public int $location = 2792,
        public string $language = 'tr',
    ) {
        $this->target = DomainName::normalize($target);
        $this->other = $other === null ? null : DomainName::normalize($other);
        if ($limit < 1 || $limit > 1000 || $offset < 0 || $offset > 100000 || $location !== 2792 || $language !== 'tr'
            || ($operation === Operation::Intersection && ($this->other === null || $this->target === $this->other))) {
            throw new SeoException('invalid_request');
        }
    }

    public function task(): array
    {
        $task = ['location_code' => $this->location, 'language_code' => $this->language];
        if ($this->operation === Operation::Intersection) {
            $task += ['target1' => $this->target, 'target2' => $this->other, 'intersections' => $this->intersections, 'item_types' => ['organic']];
        } else {
            $task['target'] = $this->target;
        }
        if ($this->operation !== Operation::Overview) {
            $task += ['limit' => $this->limit, 'offset' => $this->offset];
        }
        if ($this->operation === Operation::Keywords) {
            $task['filters'] = ['ranked_serp_element.serp_item.type', '=', 'organic'];
            $task['order_by'] = ['ranked_serp_element.serp_item.rank_group,asc'];
        }
        if ($this->operation === Operation::Pages) {
            $task['order_by'] = ['metrics.organic.etv,desc'];
        }

        return $task;
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->operation->value.json_encode($this->task(), JSON_THROW_ON_ERROR));
    }
}
