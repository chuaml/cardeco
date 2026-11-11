<?php

namespace App\Handler;

use mysqli;
use Report\ShippedItemReport;

/**
 * Route `/ShippedItemReport`. The view calls `$handler->isToGenerateReport()`, `getShippedItemReport()`
 * and `getMonthlyRecord()` while rendering, so the report object is handed to the view as `$handler`
 * and the database connection must stay open until the layout has finished (router.php owns it).
 */
final class ShippedItemReportHandler
{
    /** @var mysqli */
    private $con;

    public function __construct(mysqli $con)
    {
        $this->con = $con;
    }

    public function handle(array $request, array $files): Response
    {
        return new Response('view/ShippedItemReport.html', [
            'handler' => new ShippedItemReport($this->con, $files),
        ]);
    }
}
