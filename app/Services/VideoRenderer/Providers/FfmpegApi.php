<?php

namespace App\Services\VideoRenderer\Providers;


use App\Services\VideoRenderer\VideoRendererInterface;


class FfmpegApi implements VideoRendererInterface
{


public function render($template,$data)
{


// Call ffmpegapi.net here


return [

"status"=>"success"

];


}


}