<?php

declare(strict_types=1);

// Sends an approved WhatsApp template to two recipients, each with its own parameters.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Client;
use Unicom360\Exception\NoValidRecipientsException;
use Unicom360\WhatsApp\TemplateBody;
use Unicom360\WhatsApp\TemplateButton;
use Unicom360\WhatsApp\TemplateHeader;
use Unicom360\WhatsApp\TemplateRecipient;

$client = new Client();

try {
    $result = $client->whatsapp->sendTemplate(
        template: 'order_ready',
        language: 'en_US',
        from: '260960123456',
        recipients: [
            new TemplateRecipient(
                to: '260971234567',
                userReferenceId: 'inv-42',
                header: TemplateHeader::image('https://example.com/receipts/ZM-4821.png'),
                body: TemplateBody::of(['Ada', 'ZM-4821']),
                buttons: [TemplateButton::url(0, 'ZM-4821')],
            ),
            new TemplateRecipient('260961234567', userReferenceId: 'inv-43', body: TemplateBody::of(['Joseph', 'ZM-4822'])),
        ],
    );
    echo "{$result->acceptedCount} accepted, {$result->rejectedCount} rejected\n";
    foreach ($result->rejected() as $rejected) {
        echo "Rejected {$rejected->to}: {$rejected->reason}\n";
    }
} catch (NoValidRecipientsException $e) {
    foreach ($e->getResults() as $rejected) {
        echo "Rejected {$rejected->to}: {$rejected->reason}\n";
    }
}
