<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Jobs\RenderVideoJob;
use App\Models\RenderJob;

class VideoRenderController extends Controller
{
    /**
     * Accept a render request and queue it — the caller gets a job id
     * back immediately and polls `status()` for the result, instead of
     * holding one long blocking connection open for the whole encode.
     */
    public function render(Request $request)
    {
        // Pass every request field through as-is so any template's
        // elements (doctor_name, hospital_name, speciality, greeting_text,
        // etc.) can find their data by field name, without the controller
        // needing to know a template's specific fields in advance.
        $data = $request->except(['template_id', 'photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('uploads', 'public');
        }

        $job = RenderJob::create([
            'template_id' => $request->input('template_id'),
            'doctor_name' => $data['doctor_name'] ?? null,
            'hospital_name' => $data['hospital_name'] ?? null,
            'photo_path' => $data['photo'] ?? null,
            'data' => $data,
            'status' => 'pending',
        ]);

        RenderVideoJob::dispatch(
            $job->id,
            $request->input('template_id'),
            $data
        );

        return response()->json([
            'status' => 'queued',
            'job_id' => $job->id,
            'status_url' => url("/api/render-jobs/{$job->id}"),
        ], 202);
    }

    /**
     * Poll this with the job id from render() to find out when the
     * video is ready (or whether it failed).
     */
    public function status(int $id)
    {
        $job = RenderJob::find($id);

        if (!$job) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status' => $job->status,
            'job_id' => $job->id,
            'video_url' => $job->output_video,
            'error_message' => $job->error_message,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
        ]);
    }
}
