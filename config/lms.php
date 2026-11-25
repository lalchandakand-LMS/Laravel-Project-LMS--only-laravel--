<?php

return [
    // Soft-deleted records are automatically purged after this many days.
    // Records with linked accounts, enrolments, financial history or learning data are always retained.
    'recycle_bin_retention_days' => (int) env('RECYCLE_BIN_RETENTION_DAYS', 30),
];
