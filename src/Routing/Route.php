<?php

namespace App\Routing;

/**
 * Outcome of RouteResolver::resolve(): which tier handles the request and what it should load.
 */
final class Route
{
    /** Tier 1: an App\Handler\*Handler class (`target` = fully-qualified class name). */
    const TIER_HANDLER = 'handler';
    /** Tier 2: a procedural script under request_handler/ (`target` = relative path to require). */
    const TIER_SCRIPT = 'script';
    /** Tier 3: a legacy page under legacy_pages/request_handler/ (`target` = relative path used as the view). */
    const TIER_LEGACY = 'legacy';
    /** Nothing matched (`target` = the request_handler path that was looked for, for the 404 message). */
    const TIER_NOT_FOUND = 'not_found';

    /** @var string one of the TIER_* constants */
    public $tier;

    /** @var string */
    public $target;

    private function __construct(string $tier, string $target)
    {
        $this->tier = $tier;
        $this->target = $target;
    }

    public static function handler(string $class): self
    {
        return new self(self::TIER_HANDLER, $class);
    }

    public static function script(string $relativePath): self
    {
        return new self(self::TIER_SCRIPT, $relativePath);
    }

    public static function legacy(string $relativePath): self
    {
        return new self(self::TIER_LEGACY, $relativePath);
    }

    public static function notFound(string $lookedFor): self
    {
        return new self(self::TIER_NOT_FOUND, $lookedFor);
    }

    public function isHandler(): bool
    {
        return $this->tier === self::TIER_HANDLER;
    }
}
