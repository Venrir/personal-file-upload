<?php

namespace App\Http\Controllers\WebAPI;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoThumbnail;
use App\Jobs\RemoteDownloadJob;
use App\Uploads;
use App\User;
use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Filesystem\Cache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class UserController extends Controller
{
    public function getFiles(Request $r)
    {
        $val = Validator::make([
            'q' => $r->query('q')
        ], [
            'q' => 'sometimes|nullable|min:2'
        ]);
        if ($val->fails()) {
            return response()->json(['errors' => $val->errors()]);
        }
        /** @var User */
        $user = auth()->user();
        $files = $user->files()->latest()->when($r->query('q', false), function (EloquentBuilder $q, $searchQuery) {
            return $q->where(function(EloquentBuilder $qw) use($searchQuery) {
                $qw->where('filename', 'LIKE', "%$searchQuery%")
                ->orWhere('filetype', 'LIKE', "%$searchQuery%")
                ->orWhere('share_token', '=', "$searchQuery")
                ->orWhere('id', '=', "$searchQuery");
            });
        })->paginate(25);
        $fileSize = $user->files()->select('filesize')->sum('filesize');
        $settings = $user->roles()->first();
        if ($settings != null) {
            $settings = $settings->settings()->first();
        }
        return response()->json([
            'files' => $files, 'user' => $user, 'sizeUsed' => $fileSize,
            'sizeMax' => $settings->maxStorage ?? -1,
            'maxUploadSize' => $settings->maxFilesize ?? -1
        ]);
    }
    public function getStats()
    {
        $uploadCount = auth()->user()->files()->count();
        $linkCount = auth()->user()->links()->count();
        $fileTypeCount = auth()->user()->files()->groupBy('filemime')->select('filemime', DB::raw('count(filemime) as total'))->get();
        $fileTypeCountArray = [];
        foreach ($fileTypeCount as $stat) {
            $key = explode('/', $stat->filemime)[0];
            if (isset($fileTypeCountArray[$key]) && $fileTypeCountArray[$key] != null) {
                $fileTypeCountArray[$key] += $stat->total;
            } else {
                $fileTypeCountArray[$key] = $stat->total;
            }
        }
        return response()->json([
            'statistics' => [
                'uploads' => $uploadCount,
                'links' => $linkCount,
                'downloads' => 0,
                'uploadedTypes' => $fileTypeCountArray
            ]
        ]);
    }
    public function genSecret()
    {
        $user = auth()->user();
        $user->apikey = str_random(28);
        $user->save();
        return response()->json(['key' => $user->apikey]);
    }
    public function getUser()
    {
        return response()->json(['user' => auth()->user()]);
    }
    public function deleteUserFile(Request $r)
    {
        $v = Validator::make($r->all(), [
            'token' => 'required|exists:uploads,share_token,user_id,' . auth()->id(),
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()]);
        $user = auth()->user();
        if (!$user->can(['delete.file', 'administrator'])) {
            $v->errors()->add('user', 'This file has been locked for further inspection');
            return response()->json(['errors' => $v->errors()], 403);
        }
        $file = $user->files()->where('share_token', $r->input('token'))->first();
        $fileHash = $file->getFilePath(false);
        $store = Storage::disk($file->driver);
        if ((!$store->exists($fileHash) && $file->delete()) || $store->delete($fileHash) && $file->delete()) {
            return response()->json([
                'message' => $file->filename . ' has been deleted'
            ]);
        } else {
            $v->errors()->add('file', 'File not Found');
        }
        return response()->json(['errors' => $v->errors()], 403);
    }
    public function upload(Request $r)
    {

        $v = Validator::make($r->all(), [
            'file' => 'required|file'
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 403);
        $user = auth()->user();
        $file = $r->file('file');
        $roleSettings = $user->roles()->first();
        if ($roleSettings != null) {
            $roleSettings = $roleSettings->settings()->first();
        }
        if ($roleSettings != null) {
            if ($roleSettings->maxFilesize != null && $file->getSize() > $roleSettings->maxFilesize) {
                $v->errors()->add('file', trans('validation.lt.file', ['attribute' => 'Uploaded File', 'value' => $roleSettings->maxFilesize / 1024]));
                return response()->json(['errors' => $v->errors()]);
            }
            if ($roleSettings->maxStorage != null) {
                $currentStorageSize = $user->files()->sum('filesize') + $file->getSize();
                if ($currentStorageSize >= $roleSettings->maxStorage) {
                    $v->errors()->add('file', trans('validation.custom.file.storage_exceed'));
                    return response()->json(['errors' => $v->errors()], 403);
                }
            }
        } else if ($file->getSize() > config('app.maxFilesize')) {
            $v->errors()->add('file', trans('validation.lt.file', ['attribute' => 'Uploaded File', 'value' => config('app.maxFilesize') / 1024]));
            return response()->json(['errors' => $v->errors()], 403);
        }
        $ufile = $user->files()->where('hash', md5_file($file->getRealPath()))->first();
        if ($ufile == null) {
            $sharetoken = str_random(10);
            $deletiontoken = str_random(20);
            while (Uploads::where('share_token', $sharetoken)->count() > 0) {
                $sharetoken = str_random(10);
            }
            while (Uploads::where('deletion_token', $deletiontoken)->count() > 0) {
                $deletiontoken = str_random(20);
            }
            list($storageDriver, $storageDriverKey) = [config('filesystems.disks.' . config('filesystems.defaultUpload')), config('filesystems.defaultUpload')];
            if ($storageDriver == null) {
                return abort(500, 'Invalid Storage Endpoint');
            }
            $ufile = Uploads::create([
                'share_token' => $sharetoken,
                'deletion_token' => $deletiontoken,
                'filename' => $file->getClientOriginalName(),
                'filemime' => $file->getClientMimeType(),
                'filetype' => $file->getClientOriginalExtension() ?? (new \Mimey\MimeTypes())->getExtension($file->getClientMimeType()),
                'filesize' => $file->getSize(),
                'user_id' => $user->id,
                'hash' => md5_file($file->getRealPath()),
                'driver' => $storageDriverKey
            ]);
            try {
                $originFile = $file;
                try {
                    $file = $file->move(storage_path('app/tmp'), str_random(20) . '_' . $file->getFilename());
                    $file = new UploadedFile($file->getRealPath(), $originFile->getClientOriginalName(), $originFile->getClientMimeType());
                } catch (Throwable $jobEx) {
                    Log::error($jobEx);
                    @unlink($file->getRealPath());
                    @$ufile->delete();
                    return abort(500);
                }
                if ($storageDriver['driver'] !== 'local') {
                    $storagePath = $ufile->getFilePath();
                    $result = $file->storePubliclyAs('', $storagePath, [
                        'disk' => $storageDriverKey ?? config('filesystems.defaultUpload'),
                        'mime-type' => $ufile->filemime,
                        'ContentType' => $ufile->filemime,
                        'content-type' => $ufile->filemime,
                        'response-content-type' => $ufile->filemime,
                        'type' => $ufile->filemime,
                        'response-content-disposition' => 'inline; filename=' . $ufile->filename,
                        'ContentDisposition' => 'inline; filename=' . $ufile->filename,
                        'content-disposition' => 'inline; filename=' . $ufile->filename,
                        'disposition' => 'inline; filename=' . $ufile->filename
                    ]);
                } else {
                    $result = $file->storeAs('', $ufile->share_token, $storageDriverKey ?? config('filesystems.defaultUpload'));
                }
                if ($file != null) {
                    if (file_exists($file->getRealPath())) {
                        if (preg_match('/^video/', $ufile->filemime)) {
                            dispatch(new ProcessVideoThumbnail($ufile, $file->getRealPath()));
                        } else {
                            @unlink($file->getRealPath());
                        }
                    }
                }
            } catch (Exception $ex) {
                $ufile->delete();
                Log::error($ex);
                return abort(500);
            }
        }
        return response()->json([
            'url' => route('api:upload:get', [$ufile->share_token, str_slug($ufile->filename, "-")]) . ".$ufile->filetype",
            'deletion_url' => route('api:upload:delete', $ufile->deletion_token),
            'info_url' => route('api:upload:info', [$ufile->share_token, str_slug($ufile->filename, "-")])
        ]);
    }
    public function updateAccount(Request $r)
    {
        $v = Validator::make($r->all(), [
            'password' => 'required|confirmed|min:5'
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 403);
        $user = auth()->user();
        if (Hash::check($r->input('password'), $user->password)) {
          $v->errors()->add('account', 'Password must differ.');
          return response()->json(['errors' => $v->errors()], 403);
        }
        $user->password = Hash::make($r->input('password'));
        $user->save();
        return response()->json(['message' => 'Password has been updated'], 200);
    }
    public function addRemoteDownload(Request $r)
    {
        $v = Validator::make($r->all(), [
            'url' => ['required']
        ]);
        $user = auth()->user();
        if (!$user->can('administrator')) {
            $v->errors()->add('user', 'User is not authenticated to use this feature yet.');
        }
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 403);
        $job = new RemoteDownloadJob($user->id, $r->input('url'), preg_match('/magnet:\?xt=urn:[a-z0-9]{20,50}/i', $r->input('url')) != false);
        $pendingJob = $this->dispatch($job);
        return response()->json([
            'jobId' => $pendingJob
        ]);
    }
    public function remoteDownloadStatus(Request $r)
    {
        $v = Validator::make($r->all(), [
            'jobId' => ['required', 'integer']
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 403);
        $job = DB::table('jobs')->where('id', $r->input('jobId'))->first();
        if ($job != null) {
            $jobData = json_decode($job->payload);
            $jobData = unserialize($jobData->data->command);
        }
        return response()->json(['job_done' => $job == null]);
    }
}
