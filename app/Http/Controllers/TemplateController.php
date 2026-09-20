<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\VideoTemplate;
use App\Models\TemplateElement;
use App\Models\SyncLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TemplateSync\MediaDownloader;

class TemplateController extends Controller
{
  //  public function sync(Request $request)
  public function sync(
    Request $request,
    MediaDownloader $downloader
)
    {
        $validated = $request->validate([
            'source' => 'required|string',
            'source_template_id' => 'required|string',
            'version' => 'required|integer',

            'name' => 'required|string',
            'category' => 'nullable|string',

            'video_url' => 'nullable|string',
            'thumbnail_url' => 'nullable|string',

            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
            'fps' => 'nullable|integer',
            'bitrate' => 'nullable|string',
            'duration' => 'nullable|numeric',

            'output_format' => 'nullable|string',
            'video_codec' => 'nullable|string',
            'audio_codec' => 'nullable|string',

            'settings' => 'nullable|array',
            'elements' => 'nullable|array',
        ]);

        DB::beginTransaction();

        try {

            $template = VideoTemplate::where(
                'source',
                $validated['source']
            )
            ->where(
                'source_template_id',
                $validated['source_template_id']
            )
            ->first();

            if (!$template) {

                $template = new VideoTemplate();

            }

            $template->source = $validated['source'];

            $template->source_template_id =
                $validated['source_template_id'];

            $template->version =
                $validated['version'];

            $template->name =
                $validated['name'];

            $template->category =
                $validated['category'] ?? null;

            $template->width =
                $validated['width'] ?? 1080;

            $template->height =
                $validated['height'] ?? 1920;

            $template->fps =
                $validated['fps'] ?? 30;

            $template->bitrate =
                $validated['bitrate'] ?? '4M';

            $template->duration =
                $validated['duration'] ?? null;

            $template->output_format =
                $validated['output_format'] ?? 'mp4';

            $template->video_codec =
                $validated['video_codec'] ?? 'libx264';

            $template->audio_codec =
                $validated['audio_codec'] ?? 'aac';

            $template->settings =
                $validated['settings'] ?? null;

            /*
             * Video will be downloaded in the next step.
             * For now we keep the URL temporarily.
             */
          //  $template->video_path =
            //    $validated['video_url'] ?? '';
$videoPath = $template->video_path;

//if (!empty($validated['video_url'])) {
if (!empty($validated['video_url'])) {

    $videoPath = $downloader->download(

        $validated['video_url'],

        'videos',

        'template_'.
        $validated['source_template_id'].
        '_'.
        $validated['version'].
        '.mp4'

    );

}
   


$template->video_path = $videoPath;


            $template->thumbnail =
                $validated['thumbnail_url'] ?? null;

            $template->save();

            /*
             * Remove old elements when updating
             * an existing template.
             */
            TemplateElement::where(
                'template_id',
                $template->id
            )->delete();

            /*
             * Save template elements
             */
            foreach (
                $validated['elements'] ?? []
                as $element
            ) {

                TemplateElement::create([

                    'template_id' =>
                        $template->id,

                    'source_element_id' =>
                        $element['id'] ?? null,

                    'name' =>
                        $element['name'] ?? 'Element',

                    'field' =>
                        $element['field'] ?? '',

                    'type' =>
                        $element['type'] ?? 'text',

                    'x' =>
                        $element['x'] ?? 0,

                    'y' =>
                        $element['y'] ?? 0,

                    'width' =>
                        $element['width'] ?? null,

                    'height' =>
                        $element['height'] ?? null,

                    'font_id' =>
                        $element['font_id'] ?? null,

                    'media_file_id' =>
                        $element['media_file_id'] ?? null,

                    'opacity' =>
                        $element['opacity'] ?? 100,

                    'rotation' =>
                        $element['rotation'] ?? 0,

                    'z_index' =>
                        $element['z_index'] ?? 1,

                    'visible' =>
                        $element['visible'] ?? true,

                    'locked' =>
                        $element['locked'] ?? false,

                    'settings' =>
                        $element['settings'] ?? null,
                ]);
            }

            SyncLog::create([
                'template_id' => $template->id,
                'version' => $template->version,
                'status' => 'success',
                'message' => 'Template synchronized successfully.',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Template synchronized successfully.',
                'template_id' => $template->id,
                'source_template_id' =>
                    $template->source_template_id,
                'version' => $template->version,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}