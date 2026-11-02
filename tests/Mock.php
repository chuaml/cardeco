<?php

declare(strict_types=1);

namespace test;

use Exception;

final class Mock
{
    public static function newFile(
        string $post_name,
        string $path_to_file,
        string $content_type = '',
        string $file_name = '',
        int $error = 0
    ): array {
        if (file_exists($path_to_file) === false) {
            throw new Exception("File not found: $path_to_file");
        }

        if($file_name === '') {
            $file_name = basename($path_to_file);
        }

        if($content_type === '') {
            $content_type = mime_content_type($path_to_file);
        }

        $_FILES = [
            $post_name => [
                'name' => $file_name,
                'type' => $content_type,
                'tmp_name' => $path_to_file,
                'error' => $error,
                'size' => filesize($path_to_file),
            ],
        ];

        return $_FILES;
    }
}
