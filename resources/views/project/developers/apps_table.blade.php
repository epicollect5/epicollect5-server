@if (count($apps) == 0)
    <p class="well">{{ trans('site.no_apps_found')}}</p>
@else
    <table class="table table-bordered table-hover apps__table">
        <tr>
            <th>{{trans('site.name')}}</th>
            <th>{{trans('site.client_id')}}</th>
            <th>{{trans('site.client_secret')}}</th>
            <th>{{trans('site.created_at')}}</th>
            <th></th>
            <th></th>
        </tr>
        @foreach ($apps as $app)
            <tr>
                <td>
                    <span class="project-name">&nbsp;{{ $app->name }}</span>
                </td>
                <td>
                    <span class="project-name">&nbsp;{{ $app->id }}</span>
                </td>
                <td>
                    <span class="project-name">&nbsp;{{ $app->secret }}</span>
                </td>
                <td>
                    {{ \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $app->created_at)->format('d/m/Y H:i') }}
                </td>
                <td>
                    <button class="btn btn-danger btn-sm"
                            data-toggle="modal"
                            id="revoke-app"
                            data-target="#modal-app-delete"
                            data-client-id="{{$app->id}}"
                            href="#">{{trans('site.revoke_token')}}
                    </button>
                </td>
                <td>
                    <button class="btn btn-danger btn-sm"
                            data-toggle="modal"
                            id="delete-app"
                            data-target="#modal-app-delete"
                            data-client-id="{{$app->id}}"
                            href="#">{{trans('site.delete')}}
                    </button>
                </td>
            </tr>
        @endforeach
    </table>
@endif