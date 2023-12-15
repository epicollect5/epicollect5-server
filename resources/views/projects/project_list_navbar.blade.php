<div class="row ">
    <div class="btn-pref btn-group btn-group-justified btn-group-lg page-projects-list__nav" role="group" aria -
         label="...">

        @foreach(array_keys(config('epicollect.strings.project_categories')) as $category)
            <a href="{{ url('projects/' . $category) }}" class="btn-group text-center
@if($category == $selectedCategory && Route::getCurrentRoute()->uri() !== config('epicollect.strings.search')) active
                @endif
                    " role="group"
            >
                <div>
                    <i class="fa fa-2x {{ config('epicollect.mappings.categories_icons.' . $category) }}"
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
