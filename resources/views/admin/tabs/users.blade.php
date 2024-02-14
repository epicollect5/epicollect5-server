<div class="row">
    {{-- All Users --}}
    <div class="col-lg-12 col-md-12 equal-height">
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-6">
                        <input type="text"
                               name="search"
                               class="form-control user-administration__user-search"
                               placeholder="{{trans('site.search_for_user')}}">
                    </div>
                    @if($adminUser->server_role === 'superadmin')
                        <div class="col-xs-2 pull-right">
                            <a class="btn btn-action user-administration__user-add pull-right hidden-xs"
                               data-toggle="modal" data-target="#ec5ModalAddUser"
                               href="#">{{trans('site.add_user')}}</a>
                            <a class="btn btn-action user-administration__user-add pull-right visible-xs-block"
                               data-toggle="modal" data-target="#ec5ModalAddUser" href="#">
                                <i class="material-icons">&#43;</i>
                            </a>
                        </div>
                    @endif
                    <div @if($adminUser->server_role === 'superadmin')
                             class="col-xs-2 pull-right"
                         @else
                             class="col-xs-4"
                            @endif
                    >
                        <a class="btn btn-default user-administration__user-reset pull-right hidden-xs hidden-sm"
                           href="#">{{trans('site.clear_filter')}}</a>
                        <a class="btn btn-default user-administration__user-reset pull-right visible-sm-block"
                           href="#">{{trans('site.clear')}}</a>
                        <a class="btn btn-default user-administration__user-reset pull-right visible-xs-block"
                           href="#">
                            <i class="material-icons">&#xE5D5;</i>
                        </a>
                    </div>

                </div>
            </div>
            <div class="panel-body">
                {{-- Include Users view --}}
                <div class="user-administration__users table-responsive">
                    @include('admin.tables.users')
                </div>

            </div>
        </div>
    </div>

</div>


<!-- Modal -->
<div class="modal fade" id="ec5ModalAddUser" tabindex="-1" role="dialog" aria-labelledby="ec5ModalLabel"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            {{-- Add new User form --}}
            <form method="POST" action="{{ url('admin/add-user') }}"
                  accept-charset="UTF-8" class="manage-users__user-add-form">

                {{ csrf_field() }}

                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="ec5ModalLabel">{{trans('site.add_new_local_user')}}</h4>
                </div>

                <div class="modal-body">

                    <div class="form-group @if ($errors->has('first_name')) has-error @endif">
                        <label for="first_name"> {{trans('site.first_name')}}</label>
                        <input type="text" class="form-control" id="first_name"
                               placeholder="{{trans('site.first_name')}}"
                               name="first_name" value="{{ old('first_name') }}" required>
                        @if ($errors->has('first_name'))
                            <span class="help-block">{{config('epicollect.codes.'.$errors->first('first_name'))}}</span>
                        @endif
                    </div>

                    <div class="form-group @if ($errors->has('last_name')) has-error @endif">
                        <label for="last_name"> {{trans('site.last_name')}}</label>
                        <input type="text" class="form-control" id="last_name"
                               placeholder="{{trans('site.last_name')}}"
                               name="last_name" value="{{ old('last_name') }}" required>
                        @if ($errors->has('last_name'))
                            <span class="help-block">{{config('epicollect.codes.'.$errors->first('last_name'))}}</span>
                        @endif
                    </div>

                    <div class="form-group @if ($errors->has('email')) has-error @endif">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email"
                               placeholder="{{trans('site.email_address')}}"
                               name="email" value="{{ old('email') }}" required>
                        @if ($errors->has('email'))
                            <span class="help-block">{{config('epicollect.codes.'.$errors->first('email'))}}</span>
                        @endif
                    </div>

                    <div class="form-group @if ($errors->has('password')) has-error @endif">
                        <label for="password">{{trans('site.password')}}</label>
                        <input type="password" class="form-control password-input" id="password"
                               placeholder="{{trans('site.password')}}"
                               name="password" value="{{ old('password') }}" required>
                        @if ($errors->has('password'))
                            <span class="help-block">{{config('epicollect.codes.'.$errors->first('password'))}}</span>
                        @endif
                        <div>
                            <small>
                                Use 10 or more characters with a mix of letters, numbers & symbols.
                            </small>
                        </div>
                    </div>

                    <div class="form-group @if ($errors->has('password')) has-error @endif">
                        <label for="password_confirmation">{{trans('site.confirm_password')}}</label>
                        <input type="password" class="form-control password-input" id="password_confirmation"
                               placeholder=" {{trans('site.confirm_password')}}"
                               name="password_confirmation"
                               value="{{ old('password_confirmation') }}" required>
                        @if ($errors->has('password'))
                            <span class="help-block">{{config('epicollect.codes.'.$errors->first('password'))}}</span>
                        @endif
                    </div>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input show-password-control" id="show-password">
                        <label class="form-check-label" for="show-password">
                            <small>Show passwords</small>
                        </label>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-dismiss="modal">{{ trans('site.close') }}</button>
                    <input type="submit" class="btn btn-primary" value="{{trans('site.add_user')}}">
                </div>
            </form>
        </div>
    </div>
</div>

@section('scripts')
    <script src="{{ asset('/js/admin/admin.js') }}"></script>
@stop
