<div class="row list-group">
    @foreach ($projects as $project)
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3 item">
            <div class="panel panel-default">
                <div class="panel-body animated fadeIn">
                    {{--grid view--}}
                    <div class="grid-view">
                        <div class="flexbox col-direction project-summary">
                            <div class="thumbnail animated fadeIn">
                                <img class="projects-list__project-logo img-responsive img-circle" width="128"
                                     height="128"
                                     alt="{{$project->name}}"
                                     src="@if (!empty($project->logo_url)){{ url('/api/internal/media/' . $project->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
                                @else
                                {{ url('/images/ec5-placeholder-256x256.jpg') }}
                                @endif">
                            </div>
                            <span class="project-name">@if ($project->status == 'trashed')
                                    {{ $project->name }}
                                @else
                                    <a href="{{ url('project') . '/' . $project->slug }}">{{ $project->name }}</a>
                                @endif</span>
                            {{--truncate small desc for layout, see if it break and lower from 100 to until it is fixed --}}
                            <div class="project-small-description">{{ $project->small_description }}</div>
                            <div class="text-center">
                                {{ trans('site.role') }}: <span
                                        class="label label-primary">{{ mb_strtoupper($project->role) }}</span>
                            </div>
                            <div class="text-center">
                                <small>{{trans('site.created')}}:
                                    <strong>{{ date('d M Y', strtotime($project->created_at)) }}</strong></small>
                            </div>
                            <div class="flexbox row-direction">

                                {{-- Status --}}
                                <div class="states text-center">
                                    <i class="material-icons">
                                        @if($project->status == 'active')
                                            &#xE8E5;
                                        @elseif ($project->status == 'trashed')
                                            &#xE872;
                                        @else
                                            &#xE899;
                                        @endif
                                    </i>
                                    <strong>
                                        {{ $project->status }}&nbsp;
                                    </strong>
                                </div>

                                {{-- Visibility --}}
                                <div class="states text-center">
                                    <i class="material-icons">
                                        @if($project->visibility == 'hidden')
                                            &#xE8F5;
                                        @elseif ($project->visibility == 'listed')
                                            &#xE8F4;
                                        @endif
                                    </i>
                                    <strong>
                                        {{ $project->visibility }}&nbsp;
                                    </strong>
                                </div>

                                {{-- Access --}}
                                <div class="states text-center">
                                    <i class="material-icons">
                                        @if($project->access == 'public')
                                            &#xE80B;
                                        @elseif ($project->access == 'private')
                                            &#xE7EF;
                                        @endif
                                    </i>
                                    <strong>
                                        {{ $project->access }}&nbsp;
                                    </strong>
                                </div>

                            </div>
                        </div>
                        <div class="clearfix"></div>

                        <div class="btn-group btn-group-justified margin-top-md" role="group">
                            <a
                                    class="btn btn-action btn-sm"

                                    @if ($project->role === 'creator') disabled
                                    @else
                                        href="{{ url('myprojects') . '/' . $project->slug.'/leave' }}"
                                    @endif

                            >
                                {{ trans('site.leave') }}
                            </a>
                            <a
                                    class="btn btn-action btn-sm"
                                    @if ($project->role === 'collector' || $project->role === 'viewer'|| $project->role === 'curator') disabled
                                    @else
                                        href="{{ url('myprojects') . '/' . $project->slug }}"
                                    @endif

                            >
                                {{ trans('site.details') }}
                            </a>
                            <a
                                    class="btn btn-action btn-sm"
                                    @if ($project->status == 'trashed') disabled
                                    @else
                                        href="{{ url('project') . '/' . $project->slug }}"
                                    @endif
                            >
                                {{ trans('site.view') }}
                            </a>
                        </div>
                    </div>


                    <div class="list-view">
                        <div class="row">
                            <div class="col-xs-7 col-sm-4 col-md-4">
                                <div class="list-view__project-details">
                                    <div class="thumbnail animated fadeIn">
                                        <img class="projects-list__project-logo img-responsive img-circle" width="128"
                                             height="128"
                                             alt="{{$project->name}}"
                                             src="@if (!empty($project->logo_url)){{ url('/api/internal/media/' . $project->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
                                    @else
                                    {{ url('/images/ec5-placeholder-256x256.jpg') }}
                                    @endif">
                                    </div>
                                    <span class="project-name">@if ($project->status == 'trashed')
                                            {{ $project->name }}
                                        @else
                                            <a href="{{ url('project') . '/' . $project->slug }}">{{ $project->name }}</a>
                                        @endif</span>
                                    <div>
                                        <span class="project-small-description">{{ $project->small_description }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="hidden-xs col-sm-2 col-md-2">
                                <div class="text-center list-view__created">
                                    <small>{{trans('site.created')}}:
                                        <strong>{{ date('d M Y', strtotime($project->created_at)) }}</strong></small>
                                </div>
                            </div>
                            <div class="hidden-xs col-sm-2 col-md-2">
                                <div class="text-center list-view__status">
                                    {{--Status --}}
                                    {{$project->status}} |

                                    {{--Visibility --}}
                                    {{$project->visibility}} |

                                    {{--Access --}}
                                    {{$project->access}}
                                </div>
                            </div>
                            <div class="hidden-xs col-xs-2 col-sm-2 col-md-2">
                                <div class="text-center list-view__role">
                                    <small>Role:</small>
                                    <strong> {{ mb_strtoupper($project->role) }}</strong>
                                </div>
                            </div>
                            <div class="col-xs-5 col-sm-2 col-md-2">
                                <div class="text-center">
                                    <a class="btn btn-action @if ($project->role === 'collector' || $project->role === 'viewer' || $project->role === 'curator' ) disabled @endif"
                                       href="{{ url('myprojects') . '/' . $project->slug }}">

                                        <i class="fa fa-cog" aria-hidden="true"></i>
                                    </a>
                                    <a class="btn btn-action
                                @if ($project->status == 'trashed') disabled
                                @endif"
                                       href="{{ url('project') . '/' . $project->slug }}">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    @endforeach

    @if (count($projects) == 0)
        <p class="well text-center animated fadeIn">{{ trans('site.no_projects_found') }}</p>
    @endif

</div>
<div class="text-right">
    {{--render the pagination links--}}
    {{ $projects->render() }}
</div>
