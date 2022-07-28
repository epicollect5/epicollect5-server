<!-- Nav tabs -->
<ul class="developers__api-tabs nav nav-tabs" role="tablist">
    <li role="presentation" class="active">
        <a href="#home" aria-controls="home" role="tab" data-toggle="tab">
            {{trans('site.parameters')}}
        </a>
    </li>
    <li role="presentation">
        <a href="#profile" aria-controls="profile" role="tab" data-toggle="tab">
            {{trans('site.endpoints')}}</a>
    </li>
</ul>

<!-- Tab panes -->
@include('project.developers.tab_panel')
