<div class="row flexbox">
    <div class="col-sm-12 col-md-12 col-lg-7 app-link-view__wrapper equal-height">
        <div id="app-link-view" class="panel panel-default">
            <div class="panel-heading">
                <div class="panel-title">App Link <sup>Beta</sup>
                    <a href="https://docs.epicollect.net/web-application/projects-as-app-links"
                       target="_blank">
                        <i class="material-symbols-outlined">help</i></a>
                </div>

            </div>
            <div class="panel-body deeplink-btn-panel">
                <p><strong>On a mobile device</strong>, tapping the link below will open the Epicollect5 app and
                    load the
                    project automatically. </p>

                <span class="deeplink-copy-btn"
                      data-url="{{ url('/open/project') . 'app_link.blade.php/' . $requestAttributes->requestedProject->slug }}">
                            <button class="btn btn-sm btn-action">
                                <i class="material-icons" data-toggle="tooltip" data-placement="top"
                                   title="Copied!"
                                   data-trigger="manual">
                            content_copy
                        </i>
                                Copy App Link
                            </button>
                    </span>

                <p>
                <pre> {{ url('/open/project/'.$requestAttributes->requestedProject->slug) }}</pre>
                </p>


                <p>Alternatively, you can scan the QR code using your device's camera or a QR code scanner to
                    achieve the same result.</p>
                <div id="qrcode"
                     data-url=" {{ url('/open/project/'.$requestAttributes->requestedProject->slug) }}">
                </div>
                <a class="btn btn-action margin-top-sm" id="qrcode-download" href="#"
                   download={{$requestAttributes->requestedProject->slug.'.qr.png'}}>
                    <i class="material-icons">
                        download
                    </i>
                    Download
                </a>
            </div>
        </div>
    </div>
    <div class="col-sm-12 col-md-12 col-lg-5 equal-height">
        <div id="app-link-settings" class="panel panel-default">
            <div class="panel-heading">
                <div class="panel-title">App Link Settings <sup>Beta</sup>
                    <a href="https://docs.epicollect.net/web-application/projects-as-app-links#app-link-visibility"
                       target="_blank">
                        <i class="material-symbols-outlined">help</i></a>
                </div>
            </div>
            <div class="panel-body">
                <div class="table-responsive table-app-link-settings">
                    <table class="table">
                        <tbody>
                        <tr>
                            <th>Visibility</th>
                            <td>

                                <div class="btn-group">
                                    @foreach (array_keys(config('epicollect.strings.app_link_visibility')) as $value)
                                        <div data-setting-type="app_link_visibility" data-value="{{ $value}}"
                                             class="btn btn-default btn-sm settings-app_link_visibility btn-settings-submit">
                                            {{ $value }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
