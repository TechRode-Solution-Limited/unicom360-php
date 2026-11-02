<?php

declare(strict_types=1);

namespace Unicom360\Exception;

use Unicom360\Model\TemplateResult;

/**
 * Every recipient of a WhatsApp template send was rejected (code NO_VALID_RECIPIENTS).
 * getResults() says why, per recipient.
 */
class NoValidRecipientsException extends ApiException
{
    /**
     * The per-recipient results from data.results.
     *
     * @return list<TemplateResult>
     */
    public function getResults(): array
    {
        $data = $this->getData();
        if (!\is_array($data) || !isset($data['results']) || !\is_array($data['results'])) {
            return [];
        }
        $results = [];
        foreach ($data['results'] as $item) {
            if (\is_array($item)) {
                $results[] = TemplateResult::fromArray($item);
            }
        }

        return $results;
    }
}
