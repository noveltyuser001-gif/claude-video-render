<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;

use App\Services\VideoRenderer\RendererManager;


class VideoRenderController extends Controller
{


public function render(
    Request $request,
    RendererManager $manager
)
{
    // Pass every request field through as-is so any template's
    // elements (doctor_name, hospital_name, speciality, greeting_text,
    // etc.) can find their data by field name, without the controller
    // needing to know a template's specific fields in advance.
    $data = $request->except(['template_id', 'photo']);

    if ($request->hasFile('photo')) {
        $data['photo'] = $request->file('photo')->store('uploads', 'public');
    }

    $result = $manager->render(
        $request->input('template_id'),
        $data
    );

    return response()->json($result);
}

}