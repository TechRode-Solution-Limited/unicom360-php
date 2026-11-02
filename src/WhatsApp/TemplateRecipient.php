<?php

declare(strict_types=1);

namespace Unicom360\WhatsApp;

/**
 * One recipient of a template send, with its own parameters.
 *
 *     new TemplateRecipient(
 *         to: '260971234567',
 *         userReferenceId: 'inv-42',
 *         header: TemplateHeader::image('https://example.com/receipt.png'),
 *         body: TemplateBody::of(['Ada', 'ZM-4821']),
 *         buttons: [TemplateButton::url(0, 'ZM-4821')],
 *     );
 *
 * Plain values work too: a string header is a text header, a list body is positional and an
 * associative array body is named.
 */
final class TemplateRecipient implements \JsonSerializable
{
    /**
     * @param string $to the number in international form
     * @param string|null $userReferenceId your reference; de-duplicated per recipient and sender,
     *                                     and what makes a retry safe
     * @param TemplateHeader|string|array<string, string>|null $header
     * @param TemplateBody|array<array-key, string>|null $body a list is positional, an
     *                                                   associative array named
     * @param list<TemplateButton|array<string, mixed>>|null $buttons
     * @param string|null $otp the code, for AUTHENTICATION templates
     */
    public function __construct(
        public readonly string $to,
        public readonly ?string $userReferenceId = null,
        public readonly TemplateHeader|string|array|null $header = null,
        public readonly TemplateBody|array|null $body = null,
        public readonly ?array $buttons = null,
        public readonly ?string $otp = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $out = ['to' => $this->to];
        if ($this->userReferenceId !== null) {
            $out['user_reference_id'] = $this->userReferenceId;
        }
        if ($this->header !== null) {
            $out['header'] = $this->header;
        }
        if ($this->body !== null) {
            $out['body'] = $this->body;
        }
        if ($this->buttons !== null) {
            $out['buttons'] = $this->buttons;
        }
        if ($this->otp !== null) {
            $out['otp'] = $this->otp;
        }

        return $out;
    }
}
