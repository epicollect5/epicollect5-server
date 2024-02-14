@if(!$errors->isEmpty())
    @foreach($errors->all() as $error)
        @if (strpos($error, 'ec5_') === false)
            {{--error was already translated--}}
            <div class="var-holder-error" data-message="{{$error}}"></div>
        @else
            {{--translate error--}}
            <div class="var-holder-error" data-message="{{config('epicollect.codes.' . $error)}}"></div>
        @endif
    @endforeach
    <script>
        //get all errors
        var errors = '';
        $('.var-holder-error').each(function () {
            errors += $(this).attr('data-message') + '</br>';
        });
        EC5.toast.showError(errors);
    </script>
@endif
