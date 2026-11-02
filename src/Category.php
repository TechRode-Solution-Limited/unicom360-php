<?php

declare(strict_types=1);

namespace Unicom360;

/**
 * WhatsApp conversation categories. Open: any other string passes through.
 */
final class Category
{
    public const UTILITY = 'UTILITY';
    public const MARKETING = 'MARKETING';
    public const AUTHENTICATION = 'AUTHENTICATION';
    public const SERVICE = 'SERVICE';

    private function __construct() {}
}
