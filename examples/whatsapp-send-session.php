<?php

declare(strict_types=1);

// Sends WhatsApp session messages (allowed within 24 hours of the customer's last message).

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Client;
use Unicom360\Exception\SessionWindowClosedException;
use Unicom360\WhatsApp\ListRow;
use Unicom360\WhatsApp\ListSection;
use Unicom360\WhatsApp\ReplyButton;
use Unicom360\WhatsApp\Session;

$client = new Client();
$to = '260971234567';

try {
    $text = $client->whatsapp->sendSession(Session::text($to, 'Hi Ada, your order ZM-4821 is ready.', userReferenceId: 's-1'));
    echo "Text: {$text->messageId}\n";

    $client->whatsapp->sendSession(Session::image($to, 'https://example.com/receipts/ZM-4821.png', caption: 'Your receipt'));
    $client->whatsapp->sendSession(Session::document($to, 'https://example.com/invoice-4821.pdf', filename: 'invoice-4821.pdf'));

    $client->whatsapp->sendSession(Session::buttons(
        $to,
        'Collect today or deliver?',
        [new ReplyButton('collect', 'Collect'), new ReplyButton('deliver', 'Deliver')],
        header: 'Order ZM-4821',
    ));

    $client->whatsapp->sendSession(Session::list(
        $to,
        'Choose a delivery slot',
        'Pick a slot',
        [new ListSection([new ListRow('today-am', 'Morning', '08:00 to 12:00'), new ListRow('today-pm', 'Afternoon')], title: 'Today')],
    ));

    $client->whatsapp->sendSession(Session::location($to, -15.4167, 28.2833, name: 'Cairo Road branch'));

    // Quote the text sent above, then react to it.
    $client->whatsapp->sendSession(Session::text($to, 'Following up on this.', replyTo: $text->messageId));
    $client->whatsapp->sendSession(Session::reaction($to, (string) $text->messageId, "\u{1F44D}"));
} catch (SessionWindowClosedException) {
    echo "The 24-hour window is closed: send a template instead.\n";
}
