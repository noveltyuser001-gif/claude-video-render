<?php

namespace App\Services\VideoRenderer;

use App\Models\VideoTemplate;
use App\Models\TemplateElement;
use Symfony\Component\Process\Process;

class RendererManager
{
    public function render($templateId, $data)
    {
        // Allow FFmpeg to run longer than PHP's default 60 seconds
        set_time_limit(0);

        /*
         * ============================================
         * 1. LOAD TEMPLATE
         * ============================================
         */

        $template = VideoTemplate::find($templateId);

        if (!$template) {
            throw new \Exception(
                "Template {$templateId} not found."
            );
        }

        /*
         * ============================================
         * 2. TEMPLATE VIDEO
         * ============================================
         */

        $input = storage_path(
            'app/private/' . $template->video_path
        );

        if (!file_exists($input)) {
            throw new \Exception(
                "Template video not found: {$input}"
            );
        }

        $videoW = (int) ($template->width ?: 1080);
        $videoH = (int) ($template->height ?: 1920);

        /*
         * ============================================
         * 3. LOAD TEMPLATE ELEMENTS
         * ============================================
         */

        $elements = TemplateElement::where(
            'template_id',
            $template->id
        )
        ->where('visible', true)
        ->orderBy('z_index')
        ->get();

        /*
         * ============================================
         * 4. CREATE OUTPUT DIRECTORY
         * ============================================
         */

        $outputDir = storage_path(
            'app/public/generated'
        );

        if (!is_dir($outputDir)) {
            mkdir(
                $outputDir,
                0755,
                true
            );
        }

        $output = $outputDir . '/video.mp4';

        /*
         * ============================================
         * 5. BUILD FFMPEG INPUTS + FILTER GRAPH
         *
         * Image/photo overlays are handled first (each
         * needs its own -i input), then text is drawn on
         * top of whatever the overlay chain produced.
         * ============================================
         */

        $inputs = ['-i', $input];
        $nextInputIndex = 1;
        $filterParts = [];
        $lastLabel = '0:v';

        foreach ($elements as $element) {
            if ($element->type !== 'image' && $element->type !== 'photo') {
                continue;
            }

            $settings = $this->decodeSettings($element->settings);
            $field = $element->field;
            $photoRel = $data[$field] ?? null;

            if (empty($photoRel)) {
                // No photo supplied for this render — skip the overlay.
                continue;
            }

            $photoPath = $this->resolveDataFilePath($photoRel);

            if (!$photoPath || !file_exists($photoPath)) {
                continue;
            }

            $imgIdx = $nextInputIndex++;
            $inputs[] = '-i';
            $inputs[] = $photoPath;

            $ew = (int) ($element->width ?? 200);
            $eh = (int) ($element->height ?? 200);
            $ex = (int) ($element->x ?? 0);
            $ey = (int) ($element->y ?? 0);
            $shape = $settings['shape'] ?? 'square';

            $scaledLabel = "img{$imgIdx}s";
            $filterParts[] = "[{$imgIdx}:v]scale={$ew}:{$eh}[{$scaledLabel}]";

            $overlaySource = $scaledLabel;

            if ($shape === 'round') {
                $maskedLabel = "img{$imgIdx}m";
                // Alpha-mask the scaled photo into a circle so only the
                // area inside the ellipse is opaque.
                $filterParts[] =
                    "[{$scaledLabel}]format=yuva420p," .
                    "geq=lum='p(X\\,Y)':a='if(gt(pow(X-{$ew}/2\\,2)+pow(Y-{$eh}/2\\,2)\\,pow(min({$ew}\\,{$eh})/2\\,2))\\,0\\,255)'" .
                    "[{$maskedLabel}]";
                $overlaySource = $maskedLabel;
            }

            $overlayLabel = "ov{$imgIdx}";
            $filterParts[] = "[{$lastLabel}][{$overlaySource}]overlay={$ex}:{$ey}[{$overlayLabel}]";
            $lastLabel = $overlayLabel;
        }

        /*
         * ============================================
         * 6. TEXT ELEMENTS
         * ============================================
         */

        $textFilters = [];

        foreach ($elements as $element) {
            if ($element->type !== 'text') {
                continue;
            }

            $settings = $this->decodeSettings($element->settings);
            $field = $element->field;

            $textTemplate = $settings['template'] ?? null;

            if ($textTemplate) {
                // Lovable-style template string with {name}/{speciality}
                // placeholders, substituted from the render data.
                $text = $this->substitutePlaceholders($textTemplate, $data);
            } else {
                $text = $data[$field] ?? '';
            }

            if ($text === '') {
                continue;
            }

            $fontName = $settings['font_name'] ?? 'arial';
            $fontSize = $settings['font_size'] ?? 40;
            $fontColor = $settings['font_color'] ?? 'white';

            /*
             * Escape FFmpeg text
             */
            $text = str_replace(
                ['\\', ':', "'", '%'],
                ['\\\\', '\\:', "\\'", '\\%'],
                $text
            );

            $fontFile = $this->resolveFontFile($fontName);

            /*
             * FFmpeg's filtergraph syntax splits on unescaped ':',
             * which breaks a Windows drive-letter colon even inside
             * quotes — escape it before embedding (a no-op on Linux
             * paths, which don't contain one).
             */
            $fontFileEscaped = str_replace(':', '\\:', $fontFile);

            if (isset($settings['nx'], $settings['ny'])) {
                // Normalized (0-1) anchor, centered like the Lovable
                // canvas preview — evaluated by FFmpeg at render time
                // using the actual rendered text box size.
                $nx = (float) $settings['nx'];
                $ny = (float) $settings['ny'];
                $x = "(w*{$nx})-(text_w/2)";
                $y = "(h*{$ny})-(text_h/2)";
            } else {
                $x = $element->x ?? 0;
                $y = $element->y ?? 0;
            }

            $textFilters[] =
                "drawtext=" .
                "fontfile='{$fontFileEscaped}':" .
                "text='{$text}':" .
                "x={$x}:" .
                "y={$y}:" .
                "fontsize={$fontSize}:" .
                "fontcolor={$fontColor}";
        }

        if (!empty($textFilters)) {
            $outLabel = 'outv';
            $filterParts[] = "[{$lastLabel}]" . implode(',', $textFilters) . "[{$outLabel}]";
            $lastLabel = $outLabel;
        }

        /*
         * ============================================
         * 7. BUILD FFMPEG COMMAND
         * ============================================
         */

        $command = array_merge(['ffmpeg', '-y'], $inputs);

        if (!empty($filterParts)) {
            $command[] = '-filter_complex';
            $command[] = implode(';', $filterParts);
            $command[] = '-map';
            $command[] = "[{$lastLabel}]";
            $command[] = '-map';
            $command[] = '0:a?';
        }

        $command[] = '-c:v';
        $command[] = 'libx264';

        $command[] = '-c:a';
        $command[] = 'aac';

        $command[] = $output;

        /*
         * ============================================
         * 8. RUN FFMPEG
         * ============================================
         */

        $process = new Process($command);

        $process->setTimeout(600);

        $process->run();

        /*
         * ============================================
         * 9. CHECK FFMPEG ERROR
         * ============================================
         */

        if (!$process->isSuccessful()) {

            throw new \Exception(
                $process->getErrorOutput()
            );
        }

        /*
         * ============================================
         * 10. CHECK OUTPUT
         * ============================================
         */

        if (!file_exists($output)) {

            throw new \Exception(
                'FFmpeg completed but output video was not created.'
            );
        }

        /*
         * ============================================
         * 11. RETURN RESULT
         * ============================================
         */

        return [
            'status' => 'success',

            'video_url' =>
                url(
                    'storage/generated/video.mp4'
                ),
        ];
    }

    /**
     * Resolve a font name to an actual .ttf file, preferring the
     * bundled font (works on any OS/container) and only falling back
     * to a platform-specific system font path.
     */
    protected function resolveFontFile($fontName)
    {
        $bundled = storage_path('app/fonts/' . strtoupper($fontName) . '.TTF');
        if (file_exists($bundled)) {
            return $bundled;
        }

        $bundled = storage_path('app/fonts/' . $fontName . '.ttf');
        if (file_exists($bundled)) {
            return $bundled;
        }

        $bundledArial = storage_path('app/fonts/ARIAL.TTF');
        if (file_exists($bundledArial)) {
            return $bundledArial;
        }

        if (stripos(PHP_OS, 'WIN') === 0) {
            $path = 'C:/Windows/Fonts/' . strtolower($fontName) . '.ttf';
            return file_exists($path) ? $path : 'C:/Windows/Fonts/arial.ttf';
        }

        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return storage_path('app/fonts/ARIAL.TTF');
    }

    protected function decodeSettings($settings)
    {
        if (is_string($settings)) {
            return json_decode($settings, true) ?? [];
        }

        return $settings ?? [];
    }

    /**
     * Replace {name} and {speciality} placeholders the same way the
     * Lovable NHS Portal VideoGreetings composer does client-side.
     */
    protected function substitutePlaceholders($template, $data)
    {
        $name = $data['doctor_name'] ?? $data['name'] ?? 'Friend';
        $speciality = $data['speciality'] ?? '';

        $text = str_replace('{name}', $name, $template);
        $text = preg_replace('/\{speciality\}/i', $speciality, $text);

        return $text;
    }

    /**
     * Resolve a value coming from the render request (e.g. the
     * "photo" field) into an absolute filesystem path. Accepts either
     * a path already relative to the public disk (as stored by
     * `$request->file()->store(..., 'public')`) or an absolute path.
     */
    protected function resolveDataFilePath($value)
    {
        if (preg_match('#^([A-Za-z]:[\\\\/]|/)#', $value)) {
            return $value;
        }

        return storage_path('app/public/' . ltrim($value, '/'));
    }
}
