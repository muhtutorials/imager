<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ImageManipulationResource;
use App\Models\Album;
use App\Models\ImageManipulation;
use App\Http\Requests\ResizeImageRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ImageManipulationController extends Controller
{
    public function index(Request $request)
    {
        return ImageManipulationResource::collection(ImageManipulation::where('user_id', $request->user()->id)->paginate());
    }

    public function byAlbum(Request $request, Album $album)
    {
        if ($request->user()->id != $album->user_id) {
            return abort(403, 'Unauthorized');
        }

        $where = ['album_id' => $album->id];

        return ImageManipulationResource::collection(ImageManipulation::where($where)->paginate());
    }

    // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    // to make intervention/image library work
    // remove semicolon before "extension=gd" line in php.ini file
    // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    public function resize(ResizeImageRequest $request)
    {
       $all = $request->all();
       $image = $all['image'];
       unset($all['image']);

       $data = [
           'type' => ImageManipulation::TYPE_RESIZE,
           'data' => json_encode($all),
           'user_id' => $request->user->id
       ];

       if (isset($all['album_id'])) {
           $album = Album::find($all['album_id']);

           if ($request->user()->id != $album->user_id) {
               return abort(403, 'Unauthorized');
           }

           $data['album_id'] = $all['album_id'];
       }

       $dir = 'images/' . Str::random() . '/';
       $absolutePath = public_path($dir);
       File::makeDirectory($absolutePath);

       if ($image instanceof UploadedFile) {
           $data['name'] = $image->getClientOriginalName();
           $filename = pathinfo($data['name'], PATHINFO_FILENAME);
           $extension = $image->getClientOriginalExtension();
           $originalPath = $absolutePath . $data['name'];

           $image->move($absolutePath, $data['name']);
       } else {
           $data['name'] = pathinfo($image, PATHINFO_BASENAME);
           $filename = pathinfo($image, PATHINFO_FILENAME);
           $extension = pathinfo($image, PATHINFO_EXTENSION);
           $originalPath = $absolutePath . $data['name'];

           copy($image, $originalPath);
       }
       $data['path'] = $dir . $data['name'];

       $w= $all['w'];
       $h = $all['h'] ?? false;

       list($image, $width, $height) = $this->getImage($w, $h, $originalPath);

       $resizedFilename = $filename . '-resize.' . $extension;

       $image->resize($width, $height)->save($absolutePath . $resizedFilename);
       $data['output_path'] = $dir . $resizedFilename;

       $imageManipulation = ImageManipulation::create($data);

       return new ImageManipulationResource($imageManipulation);
    }

    public function show(Request $request, ImageManipulation $image)
    {
        if ($request->user()->id != $image->user_id) {
            return abort(403, 'Unauthorized');
        }

        return new ImageManipulationResource($image);
    }

    public function destroy(Request $request, ImageManipulation $image)
    {
        if ($request->user()->id != $image->user_id) {
            return abort(403, 'Unauthorized');
        }

        $image->delete();

        return response('', 204);
    }

    protected function getImage($w, $h, $originalPath)
    {
        $image = Image::make($originalPath);
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        if (str_ends_with($w, '%')) {
            $ratioW = (float) str_replace('%', '', $w);
            $ratioH = $h ? (float) str_replace('%', '', $h) : $ratioW;

            $newWidth = $originalWidth * $ratioW / 100;
            $newHeight = $originalHeight * $ratioH / 100;
        } else {
            $newWidth = (float) $w;
            $newHeight = $h ? (float) $h : ($originalHeight * $newWidth / $originalWidth);
        }

        return [$image, $newWidth, $newHeight];
    }
}
