<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Tells administrators that a background job failed (next step 10): AI
 * batches, notifications and mail run on the queue, so a silent failure
 * would go unnoticed. Sent at most once per job type every ten minutes.
 */
class JobFailedAlert extends Notification
{
    public function __construct(public readonly string $job, public readonly string $error) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_failed',
            'title' => __('fakkroun.ops.job_failed', ['job' => class_basename($this->job)]),
            'message' => \Illuminate\Support\Str::limit($this->error, 300),
            'url' => route('admin.health'),
        ];
    }
}
