<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{config('app.name')}}</title>
    @yield('head')
    <link rel="stylesheet" href="{{asset('css/app.css')}}">
    <meta name="theme-color" content="@yield('accent', '#202225')">
    @yield('css')
</head>
<body>
    <div id="app">
        @yield('body')
    </div>
    <script>
        window.appName = "{{config('app.name')}}";
    </script>
    <script src="{{asset('js/app.js')}}"></script>
@yield('js')
</body>
</html>