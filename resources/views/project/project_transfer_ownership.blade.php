@extends('app')
@section('title', trans('site.transfer_ownership'))
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')

    <h1 class="page-title">{{trans('site.transfer_ownership')}}</h1>

    <div class='container-fluid page-transfer-ownership'>

        @include('toasts/success')
        @include('toasts/error')

        <div class="row">
            <div class="col-sm-12 col-md-6 col-lg-6 col-md-offset-3 col-lg-offset-3">
                <div class="panel panel-default ">

                    <div class="panel-heading text-center">
                        Pick the Manager you would like to promote to Creator
                    </div>

                    <div class="panel-body text-center">

                        <form action="{{ url('myprojects') . '/' . $requestAttributes->requestedProject->slug . '/transfer-ownership' }}"
                              class="transfer-ownership"
                              method="POST">

                            {{ csrf_field() }}

                            <div class="form-group">
                                @if(count($projectManagers) > 0)
                                    @foreach($projectManagers as $manager )
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="manager" value="{{$manager->id}}">
                                                <span class="transfer-ownership__manager-fullname">
                                               <strong>
                                                   @if(!empty($manager->name))
                                                       {{ $manager->name . ' ' . $manager->last_name }}
                                                   @else
                                                       <i>(n/a)</i>
                                                   @endif
                                               </strong>
                                            </span>
                                                -
                                                <span class="transfer-ownership__manager-email">
                                                <i>
                                                    {{ $manager->email }}
                                                </i>
                                            </span>

                                            </label>
                                        </div>
                                        <hr/>
                                    @endforeach
                                @else
                                    <p class="well">No Manager(s) found.
                                        <br/>
                                        Please add a Manager to assign this project.
                                    </p>

                                @endif
                            </div>


                            <p>You,
                                @if(!empty($projectCreator->name))
                                    <strong>{{ $projectCreator->name . ' ' . $projectCreator->last_name }}</strong>
                                @else
                                    <strong><i>{{$projectCreator->email}}</i></strong>
                                @endif
                                , will become a Manager of this project.
                            </p>
                            <p>
                                The new assigned Creator will be able to remove you at any time.
                            </p>
                            <p class="warning-well">This action cannot be undone! Please proceed with caution</p>

                            <a class="btn btn-sm btn-default pull-left"
                               href="{{ url('myprojects') . '/' . $requestAttributes->requestedProject->slug.'/manage-users' }}">{{ trans('site.cancel') }}</a>
                            <div class="form-group">
                                <input required type="submit"
                                       class="btn btn-action btn-sm pull-right submit-transfer-ownership" disabled
                                       name="submit"
                                       value="{{ trans('site.confirm') }}"
                                >
                            </div>
                        </form>

                    </div>

                </div>
            </div>
        </div><!-- end col -->
    </div><!-- end row -->

@stop

@section('scripts')
    <script>
        $(document).ready(function () {
            $('input:radio[name=manager]').click(function (e) {
                $('.submit-transfer-ownership').prop('disabled', false);
            });
        });
    </script>
@stop
