<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Models\TemplateElement;
use App\Models\VideoTemplate;
use App\Services\TemplateSync\MediaDownloader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImportLovableTemplates extends Command
{
    protected $signature = 'lovable:import-templates {source=nhs-portal-1}';

    protected $description = 'Import video_templates rows from a Lovable/Supabase NHS Portal project into this app.';

    public function handle(MediaDownloader $downloader): int
    {
        $source = $this->argument('source');
        $sources = config('services.lovable', []);

        if (!isset($sources[$source])) {
            $this->error("Unknown source '{$source}'. Configure it in config/services.php under 'lovable'.");
            return self::FAILURE;
        }

        $cfg = $sources[$source];

        if (empty($cfg['url']) || empty($cfg['key'])) {
            $this->error("Source '{$source}' is missing its URL/key. Set LOVABLE_*_URL / LOVABLE_*_KEY in .env.");
            return self::FAILURE;
        }

        $base = rtrim($cfg['url'], '/');
        $key = $cfg['key'];
        $bucket = $cfg['bucket'] ?? 'video-templates';

        $this->info("Fetching video_templates from '{$source}' ({$base})...");

        $resp = Http::withHeaders([
            'apikey' => $key,
            'Authorization' => "Bearer {$key}",
        ])->get("{$base}/rest/v1/video_templates", ['select' => '*']);

        if (!$resp->successful()) {
            $this->error('Failed to fetch templates: ' . $resp->body());
            return self::FAILURE;
        }

        $rows = $resp->json();

        if (empty($rows)) {
            $this->warn("No templates found in '{$source}'.");
            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            $this->importOne($source, $base, $key, $bucket, $row, $downloader);
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function importOne(string $source, string $base, string $key, string $bucket, array $row, MediaDownloader $downloader): void
    {
        $this->line("Importing '{$row['name']}' ({$row['id']})...");

        DB::beginTransaction();

        try {
            $template = VideoTemplate::where('source', $source)
                ->where('source_template_id', $row['id'])
                ->first();

            $isNew = !$template;
            $version = $isNew ? 1 : $template->version + 1;

            if ($isNew) {
                $template = new VideoTemplate();
            }

            $width = (int) ($row['video_w'] ?? 1080);
            $height = (int) ($row['video_h'] ?? 1920);

            $template->source = $source;
            $template->source_template_id = $row['id'];
            $template->version = $version;
            $template->name = $row['name'];
            $template->category = $row['audiences'][0] ?? null;
            $template->width = $width;
            $template->height = $height;
            $template->output_format = 'mp4';
            $template->video_codec = 'libx264';
            $template->audio_codec = 'aac';
            $template->active = $row['active'] ?? true;
            $template->settings = ['lovable' => $row];

            $videoUrl = $this->resolveStorageUrl($base, $key, $bucket, $row['video_url'] ?? null);

            if ($videoUrl) {
                $filename = 'template_' . $row['id'] . '_' . $version . '.mp4';
                $template->video_path = $downloader->download($videoUrl, 'videos', $filename);
            }

            $template->save();

            TemplateElement::where('template_id', $template->id)->delete();

            // Photo overlay -> one 'image' element.
            if (!empty($row['photo_w']) && !empty($row['photo_h'])) {
                $pw = (int) $row['photo_w'];
                $ph = (int) $row['photo_h'];
                $px = (int) round((float) ($row['photo_nx'] ?? 0.5) * $width - $pw / 2);
                $py = (int) round((float) ($row['photo_ny'] ?? 0.5) * $height - $ph / 2);

                TemplateElement::create([
                    'template_id' => $template->id,
                    'name' => 'Photo overlay',
                    'field' => 'photo',
                    'type' => 'image',
                    'x' => $px,
                    'y' => $py,
                    'width' => $pw,
                    'height' => $ph,
                    'opacity' => (int) round((float) ($row['photo_opacity'] ?? 100)),
                    'z_index' => 1,
                    'visible' => true,
                    'settings' => [
                        'shape' => $row['photo_shape'] ?? 'round',
                    ],
                ]);
            }

            // Text overlay -> one 'text' element carrying the {name}/{speciality} template.
            if (!empty($row['text_template'])) {
                TemplateElement::create([
                    'template_id' => $template->id,
                    'name' => 'Greeting text',
                    'field' => 'greeting_text',
                    'type' => 'text',
                    'x' => 0,
                    'y' => 0,
                    'opacity' => 100,
                    'z_index' => 2,
                    'visible' => true,
                    'settings' => [
                        'template' => $row['text_template'],
                        'font_size' => (int) ($row['text_size'] ?? 40),
                        'font_color' => $row['text_color'] ?? 'white',
                        'nx' => (float) ($row['text_nx'] ?? 0.5),
                        'ny' => (float) ($row['text_ny'] ?? 0.9),
                    ],
                ]);
            }

            SyncLog::create([
                'template_id' => $template->id,
                'version' => $template->version,
                'status' => 'success',
                'message' => "Imported from {$source}.",
            ]);

            DB::commit();

            $this->info("  -> template_id {$template->id} (v{$template->version})");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('  Failed: ' . $e->getMessage());
        }
    }

    /**
     * Turn a Supabase Storage object path into a fetchable URL, signing
     * it if the bucket is private.
     */
    protected function resolveStorageUrl(string $base, string $key, string $bucket, ?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $resp = Http::withHeaders([
            'apikey' => $key,
            'Authorization' => "Bearer {$key}",
            'Content-Type' => 'application/json',
        ])->post("{$base}/storage/v1/object/sign/{$bucket}/{$path}", [
            'expiresIn' => 3600,
        ]);

        if ($resp->successful() && isset($resp['signedURL'])) {
            return $base . '/storage/v1' . $resp['signedURL'];
        }

        // Fall back to a public URL in case the bucket is public.
        return "{$base}/storage/v1/object/public/{$bucket}/{$path}";
    }
}
