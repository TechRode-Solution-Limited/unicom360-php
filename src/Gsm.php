<?php

declare(strict_types=1);

namespace Unicom360;

/**
 * Opt-in GSM-7 helpers: check SMS text before sending and count billing units.
 *
 * The gateway accepts only GSM-7 text (the basic set plus ^{}\[~]|€, no form feed) of up to
 * 1,200 characters, and bills max(1, ceil(length / 160)) units per recipient, where a CR LF
 * pair counts as one character. The SDK never applies these checks on its own.
 *
 *     if (Gsm::validateSms($text) === []) { ... the gateway will accept it ... }
 */
final class Gsm
{
    /** The gateway's character set, verbatim (sms_part_counter 0.1.7). */
    public const CHARSET = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà^{}\\[~]|€";

    /** The longest text the gateway accepts, in characters. */
    public const MAX_LENGTH = 1200;

    /** Characters per billing unit. */
    public const UNIT_LENGTH = 160;

    /** validateSms(): the text is empty or only spaces, tabs, CR and LF. */
    public const ISSUE_EMPTY = 'empty';

    /** validateSms(): the text is longer than MAX_LENGTH. */
    public const ISSUE_TOO_LONG = 'too_long';

    /** validateSms(): the text has characters outside the GSM-7 set. */
    public const ISSUE_NON_GSM = 'non_gsm';

    /** @var array<string, true>|null */
    private static ?array $set = null;

    private function __construct() {}

    /** Whether every character is in the GSM-7 set ("" is). */
    public static function isGsm7(string $text): bool
    {
        $set = self::set();
        foreach (self::codePoints($text) as $char) {
            if (!isset($set[$char])) {
                return false;
            }
        }

        return true;
    }

    /** The length the gateway counts: code points, with each CR LF pair counted once. */
    public static function length(string $text): int
    {
        return \count(self::codePoints($text)) - substr_count($text, "\r\n");
    }

    /** Billing units per recipient: max(1, ceil(length / 160)). Not GSM segments. */
    public static function units(string $text): int
    {
        return max(1, intdiv(self::length($text) + self::UNIT_LENGTH - 1, self::UNIT_LENGTH));
    }

    /**
     * Every character outside the set, in order, with its 0-based code point index (here a CR
     * LF pair counts as two).
     *
     * @return list<array{char: string, index: int}>
     */
    public static function nonGsmCharacters(string $text): array
    {
        $set = self::set();
        $out = [];
        foreach (self::codePoints($text) as $index => $char) {
            if (!isset($set[$char])) {
                $out[] = ['char' => $char, 'index' => $index];
            }
        }

        return $out;
    }

    /**
     * The reasons the gateway would refuse the text, in this order: empty, too_long, non_gsm.
     * An empty list means it would accept it.
     *
     * @return list<string>
     */
    public static function validateSms(string $text): array
    {
        $issues = [];
        if (preg_match('/\A[ \t\r\n]*\z/', $text) === 1) {
            $issues[] = self::ISSUE_EMPTY;
        }
        if (self::length($text) > self::MAX_LENGTH) {
            $issues[] = self::ISSUE_TOO_LONG;
        }
        if (!self::isGsm7($text)) {
            $issues[] = self::ISSUE_NON_GSM;
        }

        return $issues;
    }

    /**
     * Splits UTF-8 text into code points without ext-mbstring: a code point starts at every
     * byte that is not a continuation byte. Invalid bytes become characters of their own,
     * which are never in the set.
     *
     * @return list<string>
     */
    private static function codePoints(string $text): array
    {
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/(?=[^\x80-\xBF])/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? str_split($text) : $parts;
    }

    /**
     * @return array<string, true>
     */
    private static function set(): array
    {
        if (self::$set === null) {
            self::$set = [];
            foreach (self::codePoints(self::CHARSET) as $char) {
                self::$set[$char] = true;
            }
        }

        return self::$set;
    }
}
