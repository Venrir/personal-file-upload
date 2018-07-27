<footer class="footer">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col text-center">
                <p class="text-muted">Made with <i class="mdi mdi-heart text-danger"></i> by <a href="https://venipa.net">Venipa</a></p>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col text-center small">
                <div class="text-muted">
                    <span data-tooltip="{{$git->path_with_namespace}}"><i class="mdi mdi-git"></i> Latest Commit: {{(new \Carbon\Carbon($git->last_activity_at))->diffForHumans()}}</span>
                </div>
            </div>
        </div>
    </div>
</footer>