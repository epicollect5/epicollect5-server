<div class="panel panel-default">
    <div class="panel-heading">
        <span>{{ trans('site.api') }}</span>
        <a class="pull-right btn btn-action btn-sm" href="https://developers.epicollect.net/" target="_blank">Developer
            Guide</a>
    </div>
    <div class="panel-body project-apps">

        @include('toasts/success')
        @include('toasts/error')

        <div class="project-api">
            @include('project.developers.tab_navbar')
        </div>

    </div>
</div>
