<?php

declare(strict_types=1);

/** 
 * escape html special characters
 */
function text(?string $html): string
{
    if ($html === null) {
        return '';
    }
    return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 
 * dump json data, and stop immediately
 */
function dumpjson($json, int $flag = 0, int $depth = 512): void
{
    header('Cache-Control: max-age=1, public');
    header('Content-Type: application/json;charset=UTF-8');
    exit(json_encode($json, $flag, $depth));
}

/**
 * dump out data, and stop immediately
 */
function dd(...$data): void
{
    header('Cache-Control: no-cache');
    header('Content-Type: text/html;charset=UTF-8');
    exit(var_dump(...$data));
}


/**
 * output data as plain text, and stop immediately
 */
function ddtext(...$data): void
{
    header('Cache-Control: no-cache');
    header('Content-Type: text/plain;charset=UTF-8');
    exit(var_dump(...$data));
}
