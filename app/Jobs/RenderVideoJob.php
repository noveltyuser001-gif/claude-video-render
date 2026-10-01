<?php

namespace App\Jobs;

use App\Models\RenderJob;
use App\Services\VideoRenderer\RendererManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RenderVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // A stuck/hung FFmpeg process shouldn't retry into a pile-up — let
    // it fail once and be visible in render_jobs.status.
    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public int $renderJobId,
        public $templateId,
        public array $data,
        public array $options = []
    ) {
    }

    public function handle(RendererManager $manager): void
    {
        $job = RenderJob::find($this->renderJobId);

        if (!$job) {
            return;
        }

        $job->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            $outputName = 'video_job' . $job->id . '_' . uniqid() . '.mp4';

            $result = $manager->render($this->templateId, $this->data, $outputName, $this->options);

            $job->update([
                'status' => 'completed',
                'output_video' => $result['video_url'],
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
