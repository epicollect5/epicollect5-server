<div class="panel panel-default">
    <div class="panel-heading">
        <span>{{ trans('site.api') }}</span>
        <a class="pull-right btn btn-action btn-sm" href="https://developers.epicollect.net/" target="_blank">Developer
            Guide</a>
    </div>
    <div class="panel-body project-apps">

        {{-- Error handling --}}
        @if (!$errors->isEmpty())
            @foreach ($errors->all() as $error)
                <div class="var-holder-error" data-message="{{ trans('status_codes.' . $error) }}"></div>
            @endforeach
            <script>
                //get all errors
                var errors = '';
                $('.var-holder-error').each(function() {
                    errors += $(this).attr('data-message') + '</br>';
                });
                EC5.toast.showError(errors);
            </script>
        @endif

        {{-- Success Message --}}
        @if (session('message'))
            <div class="var-holder-success" data-message="{{ trans('status_codes.' . session('message')) }}"></div>
            <script>
                EC5.toast.showSuccess($('.var-holder-success').attr('data-message'));
            </script>
        @endif

        <div class="project-api">
            @include('project.developers.tab_navbar')
        </div>

    </div>
</div>
