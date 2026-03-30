@extends('app')
@section('title', 'Bulk CSV Export')

@section('content')

    @include('toasts/success')
    @include('toasts/error')

    {{-- ── Fixed progress toast (hidden until export starts) ──────────── --}}
    <div id="export-toast"
         style="display:none;
                position:fixed;
                top:0; left:0; right:0;
                z-index:9999;
                background:#fff;
                border-bottom:2px solid #ddd;
                box-shadow:0 2px 10px rgba(0,0,0,.18);
                padding:10px 16px;">
        <div class="container-fluid" style="max-width:1200px;">
            <div class="row" style="display:flex;align-items:center;">
                <div class="col-xs-3 col-sm-2">
                    <strong id="toast-counter" style="font-size:15px;white-space:nowrap;">0 / 0</strong>
                    <span id="toast-pct" class="text-muted" style="margin-left:6px;font-size:13px;">0%</span>
                </div>
                <div class="col-xs-9 col-sm-10" style="padding-left:4px;">
                    <div class="progress" style="margin-bottom:4px;height:18px;">
                        <div id="toast-bar"
                             class="progress-bar progress-bar-striped active"
                             role="progressbar"
                             aria-valuemin="0"
                             aria-valuemax="100"
                             aria-valuenow="0"
                             style="width:0%;min-width:0;transition:width .35s ease;">
                        </div>
                    </div>
                    <small id="toast-name" class="text-muted"
                           style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;"></small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-10 col-md-10 col-md-offset-1">

            {{-- ── Upload panel ──────────────────────────────────────── --}}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="material-icons" style="vertical-align:middle">file_upload</i>
                        &nbsp;Upload Infection Classification CSV
                    </h3>
                </div>
                <div class="panel-body">

                    <p class="text-muted">
                        Upload the classification CSV. Every project where
                        <code>is_infection</code> is <code>TRUE</code> will be queued for export.
                        Downloads happen one at a time so your browser stays responsive.
                    </p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ url('admin/tools/bulk-export') }}"
                          enctype="multipart/form-data"
                          accept-charset="UTF-8"
                          id="upload-form">

                        {{ csrf_field() }}

                        <div class="form-group">
                            <label for="csv_file">Classification CSV file</label>
                            <input class="form-control"
                                   type="file"
                                   name="csv_file"
                                   id="csv_file"
                                   accept=".csv,.txt"
                                   required>
                            <p class="help-block">
                                Required columns: <code>project_title</code>,
                                <code>is_infection</code> — all other columns are ignored.
                            </p>
                        </div>

                        <button type="submit" class="btn btn-default btn-action" id="btn-upload">
                            <i class="material-icons" style="vertical-align:middle">search</i>
                            &nbsp;Resolve Projects
                        </button>

                    </form>
                </div>
            </div>

            {{-- ── Export queue panel (only when projects are resolved) ─ --}}
            @if (!empty($projects))

                @php
                    $foundCount    = count(array_filter($projects, fn($p) => $p['found']));
                    $notFoundCount = count($projects) - $foundCount;
                @endphp

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <div class="row">
                            <div class="col-xs-8">
                                <h3 class="panel-title" style="padding-top:3px;">
                                    <i class="material-icons" style="vertical-align:middle">file_download</i>
                                    &nbsp;Export Queue &mdash;
                                    <strong>{{ $foundCount }}</strong> found
                                    @if ($notFoundCount > 0)
                                        , <strong class="text-danger">{{ $notFoundCount }}</strong> not found
                                    @endif
                                </h3>
                            </div>
                            <div class="col-xs-4 text-right">
                                <button class="btn btn-primary btn-sm"
                                        id="btn-start"
                                        {{ $foundCount === 0 ? 'disabled' : '' }}>
                                    <i class="material-icons" style="vertical-align:middle;font-size:16px">play_arrow</i>
                                    &nbsp;Start Export
                                </button>
                                <button class="btn btn-danger btn-sm"
                                        id="btn-stop"
                                        style="display:none;">
                                    <i class="material-icons" style="vertical-align:middle;font-size:16px">stop</i>
                                    &nbsp;Stop
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="panel-body" style="padding-bottom:4px;">
                        <p id="export-progress" class="text-muted" style="margin:0 0 10px;min-height:20px;"></p>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-condensed"
                               id="projects-table"
                               style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th style="width:48px;">#</th>
                                    <th>Project Name</th>
                                    <th style="width:160px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($projects as $i => $project)
                                    <tr data-slug="{{ $project['slug'] }}"
                                        data-found="{{ $project['found'] ? '1' : '0' }}"
                                        data-name="{{ $project['name'] }}">
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $project['name'] }}</td>
                                        <td class="status-cell">
                                            @if ($project['found'])
                                                <span class="label label-default">Pending</span>
                                            @else
                                                <span class="label label-warning">Not found</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>{{-- /panel --}}

            @endif{{-- /if projects --}}

        </div>
    </div>

@stop

@section('scripts')
<script>
(function () {
    'use strict';

    var btnStart   = document.getElementById('btn-start');
    var btnStop    = document.getElementById('btn-stop');
    var progressEl = document.getElementById('export-progress');

    // Nothing to do if the export panel is not on the page
    if (!btnStart) { return; }

    var stopped = false;

    // ── Progress toast elements ───────────────────────────────────────────
    var toast     = document.getElementById('export-toast');
    var toastBar  = document.getElementById('toast-bar');
    var toastCtr  = document.getElementById('toast-counter');
    var toastPct  = document.getElementById('toast-pct');
    var toastName = document.getElementById('toast-name');

    function showToast() {
        toast.style.display = 'block';
        // Push the page content down so the toast doesn't overlap the navbar
        document.body.style.paddingTop = (toast.offsetHeight + 4) + 'px';
    }

    function hideToast() {
        toast.style.display = 'none';
        document.body.style.paddingTop = '';
    }

    /**
     * Update the fixed progress toast.
     * @param {number} done    - projects successfully downloaded so far
     * @param {number} total   - total projects to download
     * @param {string|null} currentName - name of the project currently downloading (null = finished)
     * @param {string} state   - 'running' | 'done' | 'stopped'
     */
    function updateToast(done, total, currentName, state) {
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;

        toastBar.style.width     = pct + '%';
        toastBar.setAttribute('aria-valuenow', pct);
        toastCtr.textContent     = done + ' / ' + total;
        toastPct.textContent     = pct + '%';
        toastName.textContent    = currentName || '';

        // Reset bar classes
        toastBar.className = 'progress-bar';

        if (state === 'done') {
            toastBar.classList.add('progress-bar-success');
            toastName.textContent = 'All done!';
        } else if (state === 'stopped') {
            toastBar.classList.add('progress-bar-warning');
            toastName.textContent = 'Stopped at ' + done + ' / ' + total;
        } else {
            // running — animated stripe
            toastBar.classList.add('progress-bar-striped', 'active');
        }
    }

    // ── Row helpers ───────────────────────────────────────────────────────

    function setRowStatus(tr, type, text) {
        var classes = {
            'default' : 'label-default',
            'info'    : 'label-info',
            'success' : 'label-success',
            'danger'  : 'label-danger',
            'warning' : 'label-warning'
        };
        var cls = classes[type] || 'label-default';
        tr.querySelector('.status-cell').innerHTML =
            '<span class="label ' + cls + '">' + text + '</span>';
    }

    function setProgress(done, total, currentName) {
        if (currentName) {
            progressEl.textContent =
                'Exporting ' + done + ' / ' + total + ' \u2014 ' + currentName + '\u2026';
        } else {
            progressEl.textContent = done + ' / ' + total + ' exported.';
        }
    }

    // ── Download helper ───────────────────────────────────────────────────

    /**
     * Fetch one project archive and save it to disk via a temporary <a download>.
     * Awaiting this function blocks until the *entire* ZIP has been received by
     * the browser, so the loop naturally waits before moving to the next project.
     *
     * Endpoint: GET {siteUrl}/admin/tools/bulk-export/download/{slug}
     *           → BulkExportController@download (auth.admin protected)
     */
    async function downloadProject(slug) {
        var url = window.EC5.SITE_URL + '/admin/tools/bulk-export/download/' + encodeURIComponent(slug);

        var response = await fetch(url, { credentials: 'include' });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ' \u2014 ' + (await response.text()).substring(0, 120));
        }

        var blob = await response.blob();

        var filename = slug + '-csv.zip';
        var disposition = response.headers.get('Content-Disposition') || '';
        var match = disposition.match(/filename[^;=\n]*=(['"]?)([^'"\n]+)\1/);
        if (match && match[2]) { filename = match[2].trim(); }

        var objectUrl = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href          = objectUrl;
        a.download      = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 2000);
    }

    // ── Main export loop ──────────────────────────────────────────────────

    btnStart.addEventListener('click', async function () {
        stopped = false;

        var rows = Array.from(
            document.querySelectorAll('#projects-table tbody tr[data-found="1"]')
        ).filter(function (tr) {
            return tr.querySelector('.status-cell .label-default') !== null;
        });

        if (rows.length === 0) {
            progressEl.textContent = 'No pending projects to export.';
            return;
        }

        var total = rows.length;
        var done  = 0;

        btnStart.style.display = 'none';
        btnStop.style.display  = 'inline-block';
        btnStop.disabled       = false;

        showToast();
        updateToast(0, total, rows[0].getAttribute('data-name'), 'running');

        for (var i = 0; i < rows.length; i++) {
            if (stopped) {
                updateToast(done, total, null, 'stopped');
                progressEl.textContent = 'Stopped after ' + done + ' of ' + total + ' exports.';
                break;
            }

            var tr   = rows[i];
            var slug = tr.getAttribute('data-slug');
            var name = tr.getAttribute('data-name');

            setRowStatus(tr, 'info', 'Downloading\u2026');
            setProgress(done + 1, total, name);
            updateToast(done, total, name, 'running');

            tr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            try {
                await downloadProject(slug);
                setRowStatus(tr, 'success', '&#10003; Done');
                done++;
                updateToast(done, total, null, 'running');
            } catch (err) {
                setRowStatus(tr, 'danger', '&#10007; ' + err.message);
            }
        }

        if (!stopped) {
            setProgress(done, total, null);
            updateToast(done, total, null, 'done');
            // Auto-hide the toast after 6 s once complete
            setTimeout(hideToast, 6000);
        }

        btnStop.style.display  = 'none';
        btnStart.style.display = 'inline-block';
        btnStart.textContent   = 'Resume Export';
    });

    btnStop.addEventListener('click', function () {
        stopped          = true;
        btnStop.disabled = true;
    });

}());
</script>
@stop

