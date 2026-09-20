<?php

namespace App\Services\TemplateSync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MediaDownloader
{
    public function download($url, $folder, $filename)
    {
        if (empty($url)) {
            return null;
        }

        $response = Http::withoutVerifying()
            ->timeout(300)
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception("Unable to download {$filename}");
        }

        $path = $folder.'/'.$filename;

        Storage::disk('local')->put(
            $path,
            $response->body()
        );

        return $path;
    }
}