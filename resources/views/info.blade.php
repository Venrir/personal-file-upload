@extends('layouts.app')
@section('head')
    <link type="application/json+oembed" href="{{route('api:oembed:upload', $file->share_token)}}" />
    @php
        $sitename =  $file->filename;
        $sitedescription = config('app.name') . " - " . "Who doesnt need a personal Storage for Media and other things, this Storage Service is perfect with use of ShareX. Thats why i made an Storage Service which you can share things fast and easy.";
        if($file->thumb_token != null) {
            $logo =  $file->getLatestThumbnailUrl();
        }
    $author = config('app.author');
    @endphp
    <meta name="description" content="{{$file->filename}}">
    @if($file->thumb_token != null)
        <meta name="image" content="{{$logo}}">
        <meta itemprop="image" content="{{$logo}}">
        <meta name="twitter:image" content="{{$logo}}">
        <meta name="twitter:image:src" content="{{$logo}}">
        <meta property="og:image" content="{{$logo}}">
        @endif
    <!-- Schema.org for Google -->
    <meta itemprop="name" content="{{$sitename}}">
    <meta itemprop="description" content="{{$sitedescription}}">
    <!-- Twitter -->
    <meta name="twitter:title" content="{{$sitename}}">
    <meta name="twitter:description" content="{{$sitedescription}}">
    <meta name="twitter:site" content="{{url()->current()}}">
    <meta name="twitter:creator" content="{{$author}}">
    <!-- Open Graph general (Facebook, Pinterest & Google+) -->
    <meta property="og:title" content="{{$sitename}}">
    <meta property="og:description" content="{{$sitedescription}}">
    <meta property="og:url" content="{{url()->current()}}">
    <meta property="og:site_name" content="{{$sitename}}">
    <meta property="og:type" content="website">
    @if(preg_match('/video\//', $file->filemime))
        <meta name="twitter:card" content="player">
        <meta name="twitter:player" content="{{$file->getEmbedUrl()}}">
        <meta name="twitter:player:height" content="320">
        <meta name="twitter:player:width" content="480">
        <meta property="og:video" content="{{$file->getFileUrl()}}">
        <meta property="og:video:height" content="320">
        <meta property="og:video:width" content="480">
        <meta property="og:video:type" content="{{$file->filemime}}" />

    @elseif(preg_match('/audio\//', $file->filemime))
        <meta name="twitter:card" content="player">
        <meta name="twitter:player" content="{{$file->getEmbedUrl()}}">
        <meta name="twitter:player:height" content="60">
        <meta name="twitter:player:width" content="480">
        <meta property="og:audio" content="{{$file->getFileUrl()}}">
        <meta property="og:audio:type" content="{{$file->filemime}}" />
    @elseif(preg_match('/image\//', $file->filemime))
        <meta name="twitter:card" content="summary">
        <meta property="og:image" content="{{$file->getFileUrl()}}" />
        <meta property="og:image:type" content="{{$file->filemime}}" />
    @endif
    @endsection
@section('body')

    <div class="container">
        <div class="row justify-content-center mb-5 mt-5">
            <div class="col">
                <div class="text-center">
                    <div class="sitelogo md"></div>
                    <h4>{{config('app.name')}}</h4>
                    <p class="text-muted text-small">Uploaded by {{$file->user->name}} on the {{(new \Carbon\Carbon($file->created_at))->toFormattedDateString()}}</p>
                </div>
            </div>
        </div>
        @if (preg_match('/video\//', $file->filemime))
            <div class="row mt-2 mb-2 justify-content-center">
                <div class="col-md-8">
                    <div class="card rounded">
                        <video class="_plyr" @if($file->thumb_token != null) poster="{{$logo}}" @endif >
                            <source src="{{route('api:upload:get', $file->share_token)}}" type="{{$file->filemime}}" />
                        </video>
                    </div>
                </div>
            </div>
        @endif
        @if (preg_match('/audio\//', $file->filemime))
            <div class="row mt-2 mb-2 justify-content-center">
                <div class="col-md-8">
                    <div class="card rounded">
                        <audio class="_plyr">
                            <source src="{{route('api:upload:get', $file->share_token)}}" type="{{$file->filemime}}" />
                        </audio>
                    </div>
                </div>
            </div>
        @endif
        <div class="row mt-2 mb-5 justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless">
                                <thead>
                                <tr>
                                    <th width="100px" class="text-right"></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach([
                                    'Name' => $file->filename,
                                    'Size' => $file->humsize,
                                    'Type' => $file->filetype
                                ] as $k=>$v)
                                <tr>
                                    <td width="100px" class="text-right">{{$k}}</td>
                                    <td>{{$v}}</td>
                                </tr>
                                    @endforeach
                                <tr>
                                    <td class="text-right">Download</td>
                                    <td><a class="btn btn-primary btn-sm" download="{{$file->filename}}" href="{{route('api:upload:get', [$file->share_token, str_slug($file->filename, "-")])}}">
                                            <i class="mdi mdi-cloud-download"></i> Download File
                                        </a></td>
                                </tr>
                                <tr>
                                    <td class="text-right">Open</td>
                                    <td><a class="btn btn-secondary btn-sm" target="_blank" href="{{route('api:upload:get', [$file->share_token, str_slug($file->filename, "-") . ($file->filetype == "gif" ? ".gif" : "")])}}">
                                            <i class="mdi mdi-open-in-new"></i> Open in new Window
                                        </a></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new Plyr(document.querySelector('._plyr'), {
              controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'],
              settings: ['captions', 'quality', 'speed', 'loop']
            });
        });
    </script>
    @endsection
    @include('layouts.footer')
    @endsection