@extends('app')
@section('title', 'Bulk CSV Export')

@section('content')

    @include('toasts/success')
    @include('toasts/error')

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

            {{-- ── Export queue panel (only when projects are resolved) ─--}}
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

    // ── Helpers ──────────────────────────────────────────────────────────

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
                'Exporting ' + done + ' / ' + total + ' — ' + currentName + '…';
        } else {
            progressEl.textContent = done + ' / ' + total + ' exported.';
        }
    }

    /**
     * Fetch one project archive and save it to disk via a temporary <a download>.
     * Awaiting this function blocks until the *entire* ZIP has been received by
     * the browser, so the loop naturally waits before moving to the next project.
     *
     * Endpoint: GET {siteUrl}/admin/tools/bulk-export/download/{slug}
     *           → BulkExportController@download (auth.admin protected)
     */
    async function downloadProject(slug) {
        // Use the injected site URL so this works on any deployment path
        var url = window.EC5.SITE_URL + '/admin/tools/bulk-export/download/' + encodeURIComponent(slug);

        var response = await fetch(url, { credentials: 'include' });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ' — ' + (await response.text()).substring(0, 120));
        }

        // Buffer the full ZIP in memory then hand it to the browser as a save
        var blob = await response.blob();

        // Derive filename from Content-Disposition if the server supplied it
        var filename = slug + '-csv.zip';
        var disposition = response.headers.get('Content-Disposition') || '';
        var match = disposition.match(/filename[^;=\n]*=(['"]?)([^'"\n]+)\1/);
        if (match && match[2]) { filename = match[2].trim(); }

        var objectUrl = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href     = objectUrl;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        // Revoke after a short delay to let the browser start the save
        setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 2000);
    }

    // ── Main export loop ─────────────────────────────────────────────────

    btnStart.addEventListener('click', async function () {
        stopped = false;

        // Collect every found row that still shows "Pending"
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

        for (var i = 0; i < rows.length; i++) {
            if (stopped) {
                progressEl.textContent =
                    'Stopped after ' + done + ' of ' + total + ' exports.';
                break;
            }

            var tr   = rows[i];
            var slug = tr.getAttribute('data-slug');
            var name = tr.getAttribute('data-name');

            setRowStatus(tr, 'info', 'Downloading…');
            setProgress(done + 1, total, name);

            // Keep the current row visible as the table scrolls
            tr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            try {
                await downloadProject(slug);
                setRowStatus(tr, 'success', '&#10003; Done');
                done++;
            } catch (err) {
                setRowStatus(tr, 'danger', '&#10007; ' + err.message);
                // Continue to the next project even after an error
            }
        }

        if (!stopped) {
            setProgress(done, total, null);
        }

        btnStop.style.display  = 'none';
        btnStart.style.display = 'inline-block';
        btnStart.textContent   = 'Resume Export';
    });

    btnStop.addEventListener('click', function () {
        stopped       = true;
        btnStop.disabled = true;
    });

}());
</script>
@stop

