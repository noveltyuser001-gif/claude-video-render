<?php

namespace App\Services\VideoRenderer\Providers;


use App\Services\VideoRenderer\VideoRendererInterface;


class Rendi implements VideoRendererInterface
{


public function render($template,$data)
{


// Call Rendi API here


return [

"status"=>"success",

"video_url"=>"rendi/video.mp4"

];


}


}