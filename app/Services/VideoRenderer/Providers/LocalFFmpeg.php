<?php

namespace App\Services\VideoRenderer\Providers;


use App\Services\VideoRenderer\VideoRendererInterface;
use Illuminate\Support\Facades\Http;


class LocalFFmpeg implements VideoRendererInterface
{


public function render($template,$data)
{


// 1. Download video

$videoPath =
storage_path(
'app/videos/template.mp4'
);


// 2. Download image

$imagePath =
storage_path(
'app/images/photo.jpg'
);


// 3. Output file

$output =
storage_path(
'app/generated/output.mp4'
);


// 4. Font

$font =
storage_path(
'app/fonts/arial.ttf'
);



// 5. Create FFmpeg command


$command = "ffmpeg 

-i $videoPath

-i $imagePath

-filter_complex \"


[1:v]scale=250:250[img];


[0:v][img]overlay=600:400,


drawtext=

fontfile=$font:

text='Dr Amit':

fontsize=50:

fontcolor=white:

x=200:

y=1200,


drawtext=

fontfile=$font:

text='ABC Hospital':

fontsize=40:

fontcolor=yellow:

x=200:

y=1300


\"

-c:v libx264

-c:a copy

$output";



// Execute

exec($command);



return [

"status"=>"success",

"video_url"=>$output

];


}


}