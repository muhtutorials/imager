<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AlbumResource;
use App\Models\Album;
use App\Http\Requests\StoreAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    public function index(Request $request)
    {
        return AlbumResource::collection(Album::where('user_id', $request->user()->id)->paginate());
    }

    public function store(StoreAlbumRequest $request)
    {
        $data = $request->all();
        $data['user_id'] = $request->user()->id;
        $album = Album::create($data);

        return new AlbumResource($album);
    }

    public function show(Request $request, Album $album)
    {
        if ($request->user()->id != $album->user_id) {
            return abort(403, 'Unauthorized');
        }

        return new AlbumResource($album);
    }

    public function update(UpdateAlbumRequest $request, Album $album)
    {
        if ($request->user()->id != $album->user_id) {
            return abort(403, 'Unauthorized');
        }

        $album->update($request->all());

        return new AlbumResource($album);
    }

    public function destroy(Request $request, Album $album)
    {
        if ($request->user()->id != $album->user_id) {
            return abort(403, 'Unauthorized');
        }

        $album->delete();

        return response('', 204);
    }
}
