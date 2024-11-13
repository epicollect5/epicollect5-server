@if (Auth::user()->isSuperAdmin())
    <div class="row settings-dashboard">
        <div class="col-lg-12 col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-4">Current Version
                            <strong class="text-primary">
                                {{$currentVersion}}
                            </strong>
                        </div>
                        <div class="col-xs-4 text-center">Latest Version
                            <strong class="text-primary">
                                {{$CGPSVersion}}
                            </strong>
                        </div>

                        @if($update)
                            <div class="col-xs-4 text-danger text-right">
                                <strong>
                                 <span class="material-symbols-outlined">
                                    error
                                 </span>
                                    Old version, please upgrade!
                                </strong>
                            </div>

                        @else
                            <div class="col-xs-4 text-success text-right">
                                <strong>
                        <span class="material-symbols-outlined">
                                check_circle
                        </span>
                                    Your system is up to date
                                </strong>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            Send email notification when new version is available
                        </div>
                        <div class="col-md-6 text-right">
                            <div class="btn-group">
                                <div data-setting-type="email-notification-version" data-value="on"
                                     class="btn btn-default btn-sm btn-action">
                                    On
                                </div>
                                <div data-setting-type="email-notification-version" data-value="off"
                                     class="btn btn-default btn-sm">
                                    Off
                                </div>
                            </div>
                        </div>
                    </div>
                    <small><em>Checks are performed every 24 hours.</em></small>
                </div>
                <div class="panel-footer"><code>PRODUCTION_SERVER_VERSION</code></div>
            </div>
        </div>
        <div class="col-lg-12 col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">System Email</div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <input type="text" class="form-control" placeholder="Email..." value="{{$systemEmail}}">
                        </div>
                        <div class="col-md-6 text-right">
                            <div class="btn-group">
                                <div data-setting-type="system-email" data-value="Save"
                                     class="btn btn-default btn-sm btn-action">
                                    Save
                                </div>
                            </div>
                        </div>
                    </div>
                    <small><em>Used to send system events and notifications.</em></small>
                </div>
                <div class="panel-footer"><code>SYSTEM_EMAIL</code></div>
            </div>
        </div>

        <div class="col-lg-12 col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">Authentication Allowed Domains</div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <span>Comma separated list of domains to whitelist</span>
                            <input type="text" class="form-control" placeholder="All domains allowed" value="">
                        </div>
                        <div class="col-md-6 text-right">
                            <div class="btn-group">
                                <div data-setting-type="system-email" data-value="Save"
                                     class="btn btn-default btn-sm btn-action">
                                    Save
                                </div>
                            </div>
                        </div>
                    </div>
                    <small><em>Leave empty to allow all domains.</em></small>
                </div>

            </div>
        </div>


    </div>
@else
    <p class="warning-well">
        This section is restricted and requires additional permissions.
    </p>
@endif

@section('scripts')
    <script src="{{ asset('/js/admin/admin.js') }}"></script>
@stop
