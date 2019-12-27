<?php

if (count($csvData) === 0) {
    throw new \Exception('No data to export');
}
$out = fopen('php://output', 'wb');
if ($out === false) {
    throw new \Exception('output error');
}

try {
    header('Content-type: text/csv');
    header('Cache-Control: no-store, no-cache');
    header('Content-Disposition: attachment; filename="MonthlyRecord_' .date_format(date_create(), 'Y-m-d') .'_.csv"');

    try {
        if (fputcsv($out, array_keys($csvData[0]->getAll()), ',', '"', '\\') === false) {
            throw new \Exception('output error');
        }
        foreach ($csvData as $r) {
            if (fputcsv($out, $r->getAll(), ',', '"', '\\') === false) {
                throw new \Exception('output error');
            }
        }
    } finally {
        if (fclose($out) === false) {
            throw new \Exception('cannot close output');
        }
    }
    exit();
} catch (\Exception $e) {
    if (($header_list = headers_list()) !== false) {
        foreach ($header_list as $v) {
            header_remove($v);
        }
    }
    header('HTTP/1.1 500');
    throw $e;
}
