<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;
use Spatie\MediaLibrary\Models\Media;
use Spatie\MediaLibrary\PathGenerator\PathGenerator;

class Uploads extends Model implements HasMedia
{

    use HasMediaTrait;
    protected $table = 'uploads';

    protected $guarded = [
        'id'
    ];
    protected $dates = [
        'created_at',
        'updated_at'
    ];
    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function setThumbnail(UploadedFile $file, $token = null) {
        $token = $token ?? str_random();
        $this->thumb_token = $token;
        $this->addMedia($file)->setFileName($token)->toMediaCollection('thumbnails');
        $this->save();
    }
    public function getLatestThumbnail() {
        return $this->getMedia('thumbnails')->last();
    }
    public function getLatestThumbnailUrl() {
        return route('api:thumb:get', $this->thumb_token);
    }
    public function getFileUrl() {
        return route('api:upload:get', [$this->share_token, str_slug($this->filename)]);
    }
    public function getEmbedUrl() {
        return route('api:upload:embed', [$this->share_token, str_slug($this->filename)]);
    }
    public function scopegetFilePath($withFileSuffix = true) {
        $config = config("filesystems.disks.{$this->driver}");
        if ($config != null && $config['driver'] !== 'local') {
            $fileHash = "{$this->user_id}/" . sha1($this->share_token . $this->created_at->getTimestamp()) . "/" . ($withFileSuffix ? $this->filename : '');
        } else {
            $fileHash = $this->share_token;
        }
        return $fileHash;
    }
}
