<?php

declare(strict_types=1);

namespace Unicom360\WhatsApp;

/**
 * A contact card for Session::contacts(), in Meta's contact object shape. Plain arrays in the
 * same shape work too.
 *
 *     ContactCard::create('Unicom360 Support', phones: [['phone' => '+260960123456', 'type' => 'WORK']]);
 */
final class ContactCard implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $data Meta's contact object
     */
    public function __construct(public readonly array $data) {}

    /**
     * @param list<array<string, string>> $phones each {phone, type?, wa_id?}
     * @param list<array<string, string>> $emails each {email, type?}
     * @param list<array<string, string>> $urls each {url, type?}
     * @param array<string, string>|null $org {company?, department?, title?}
     * @param array<string, mixed> $extra any other fields of Meta's contact object (addresses,
     *                                    birthday, more name parts)
     */
    public static function create(
        string $formattedName,
        ?string $firstName = null,
        ?string $lastName = null,
        array $phones = [],
        array $emails = [],
        array $urls = [],
        ?array $org = null,
        array $extra = [],
    ): self {
        $name = ['formatted_name' => $formattedName];
        if ($firstName !== null) {
            $name['first_name'] = $firstName;
        }
        if ($lastName !== null) {
            $name['last_name'] = $lastName;
        }
        $data = ['name' => $name];
        if ($phones !== []) {
            $data['phones'] = $phones;
        }
        if ($emails !== []) {
            $data['emails'] = $emails;
        }
        if ($urls !== []) {
            $data['urls'] = $urls;
        }
        if ($org !== null) {
            $data['org'] = $org;
        }

        return new self($data + $extra);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
