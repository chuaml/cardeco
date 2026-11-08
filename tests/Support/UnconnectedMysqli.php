<?php

declare(strict_types=1);

namespace test\Support;

use mysqli;

/**
 * A real `mysqli` instance that is NOT connected to any server.
 *
 * Several production classes type-hint `mysqli` in their constructor but have methods that never touch the
 * database (e.g. `OrderProcess\TikTokOrder::getOrders()` only parses the CSV). Unit tests use this to
 * exercise those methods without a database; any accidental query fails loudly.
 */
final class UnconnectedMysqli
{
    public static function create(): mysqli
    {
        return mysqli_init();
    }
}
