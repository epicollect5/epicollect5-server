@if(!$errors->isEmpty())
    @foreach($errors->all() as $error)
        <div class="var-holder-error" data-message="{{trans('status_codes.'.$error)}}"></div>
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
