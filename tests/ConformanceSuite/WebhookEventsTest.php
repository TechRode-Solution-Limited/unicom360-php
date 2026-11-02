<?php

declare(strict_types=1);

namespace Unicom360\Tests\ConformanceSuite;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Unicom360\Tests\Support\Canonical;
use Unicom360\Tests\Support\Fx;
use Unicom360\Webhook;
use Unicom360\Webhook\MessageReceivedEvent;
use Unicom360\Webhook\MessageStatusEvent;
use Unicom360\Webhook\TestEvent;
use Unicom360\Webhook\UnknownEvent;

/**
 * conformance/webhooks/events.json: each event, including an unknown event type.
 */
final class WebhookEventsTest extends TestCase
{
    /**
     * @return array<string, array{0: array<mixed>}>
     */
    public static function vectors(): array
    {
        return Fx::vectors('webhooks/events.json');
    }

    /**
     * @param array<mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testConstructEvent(array $vector): void
    {
        Fx::assertKnownKeys($vector, ['id', 'description', 'secret', 'headers', 'body_text', 'now_unix', 'tolerance', 'expect'], 'events.json vector');
        $id = Fx::str($vector, 'id');
        $expect = Fx::arr($vector, 'expect');
        Fx::assertKnownKeys($expect, ['kind', 'event', 'delivery_id', 'created_at', 'data'], "{$id} expect");

        $event = Webhook::constructEvent(
            Fx::str($vector, 'body_text'),
            Fx::arr($vector, 'headers'),
            Fx::str($vector, 'secret'),
            Fx::int($vector, 'tolerance'),
            Fx::num($vector, 'now_unix'),
        );

        $class = match (Fx::str($expect, 'kind')) {
            'message_status' => MessageStatusEvent::class,
            'message_received' => MessageReceivedEvent::class,
            'test' => TestEvent::class,
            'unknown' => UnknownEvent::class,
            default => throw new \LogicException("{$id}: unknown event kind"),
        };
        self::assertInstanceOf($class, $event, "{$id}: event class");
        self::assertSame(Fx::str($expect, 'event'), $event->event, "{$id}: event");
        self::assertSame(Fx::optStr($expect, 'delivery_id'), $event->deliveryId, "{$id}: delivery_id");
        self::assertSame(Fx::optStr($expect, 'created_at'), $event->createdAt, "{$id}: created_at");

        $data = $event instanceof UnknownEvent ? $event->data : Canonical::result($event->data);
        self::assertTrue(
            Canonical::equal($expect['data'] ?? null, $data),
            "{$id}: data differs\nexpected: " . json_encode($expect['data'] ?? null) . "\nactual:   " . json_encode($data),
        );
    }
}
