@if ($requestAttributes->requestedProjectRole->canEditProject())
    <div class="list-group">
        <a href="#" class="list-group-item disabled">
            Dashboard
        </a>
        <a class="list-group-item" href="{{ url('/myprojects/'.$requestAttributes->requestedProject->slug)}}">
            <i class="material-icons">&#xE8B8;</i>
            {{ trans('site.details')}}
        </a>

        <a class="list-group-item"
           href="{{ url('/myprojects/'.$requestAttributes->requestedProject->slug .'/formbuilder')}}">
            <i class="material-icons">&#xE8EA;</i>
            {{ trans('site.formbuilder')}}
        </a>

        <a class="list-group-item"
           href="{{ url('/myprojects/'.$requestAttributes->requestedProject->slug .'/manage-users')}}">
            <i class="material-icons">&#xE8D3;</i>
            {{ trans('site.manage_users')}}
        </a>

        <a class="list-group-item"
           href="{{ url('/myprojects/'.$requestAttributes->requestedProject->slug .'/mapping-data')}}">
            <i class="material-icons">&#xE8EF;</i>
            {{ trans('site.mapping_data')}}
        </a>

        <a class="list-group-item" href="{{ url('/myprojects/'.$requestAttributes->requestedProject->slug .'/clone')}}">
            <i class="material-icons">&#xE14D;</i>
            {{ trans('site.clone')}}
        </a>

        <a class="list-group-item"
           href="{{ url('/myprojects/'.$requestAttributes->requestedProject->slug .'/manage-entries')}}">
            <i class="material-icons">&#xE0DE;</i>
            {{ trans('site.manage_entries')}}
        </a>

        <a href="#" class="list-group-item disabled">
            Developers
        </a>

        <a class="list-group-item" href="{{ url('/myprojects/'.$requestAttributes->requestedProject->slug .'/api')}}">
            <i class="material-icons">&#xE2C2;</i>
            {{ trans('site.api')}}
        </a>

        <a class="list-group-item" href="{{ url('/myprojects/'.$requestAttributes->requestedProject->slug .'/apps')}}">
            <i class="material-icons">&#xE5C3;</i>
            {{ trans('site.apps')}}
        </a>
    </div>
@endif
