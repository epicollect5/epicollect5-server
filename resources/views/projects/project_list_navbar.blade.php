<div class="row ">
    <div class="btn-pref btn-group btn-group-justified btn-group-lg page-projects-list__nav" role="group" aria - label="...">

        @foreach(Config::get('ec5Enums.project_categories') as $category)
            <a href="{{ url('projects/' . $category) }}" class="btn-group text-center
@if($category == $selectedCategory && Route::getCurrentRoute()->uri() !== Config::get('ec5Strings.search')) active
                @endif
                    " role="group"
            >
                <div>
                    <i class="fa fa-2x {{ Config::get('ec5Enums.project_categories_icons.' . $category) }}"
                       aria-hidden="true">

                    </i>
                    <span class="center-block hidden-xs">&nbsp;{{ trans('site.project_categories.' . $category) }}
                    </span>
                </div>
            </a>
        @endforeach
        <a href="{{ url('projects/search') }}" class="btn-group text-center
@if($selectedCategory === 'search') active @endif

" role="group">
            <div>
                <i class="fa fa-2x fa-search"
                   aria-hidden="true"></i>
                <span class="center-block hidden-xs">&nbsp; Search</span>
            </div>
        </a>
    </div>
</div>
