<?php

return [
    'title' => 'Backups',
    'description' => 'Database and upload backups on each configured destination.',
    'run_db' => 'Back up database now',
    'run_full' => 'Full backup now',
    'confirm_db' => 'Queue a database backup now?',
    'confirm_full' => 'Queue a full backup (database and uploads) now?',
    'queued' => 'Backup queued. It runs on the queue worker; refresh this page in a moment.',
    'queue_note' => 'Backups run in the background on the queue worker and daily via the scheduler. Archives are never downloadable from the browser; see the README for restoring.',
    'disk' => 'Destination: :disk',
    'healthy' => 'Healthy',
    'unhealthy' => 'Needs attention',
    'unreachable' => 'This destination is not reachable.',
    'count' => 'Backups',
    'newest' => 'Newest',
    'used' => 'Storage used',
    'file' => 'File',
    'date' => 'Date',
    'size' => 'Size',
    'none' => 'No backups yet.',
    'health_label' => 'Backups (:disk)',
];
