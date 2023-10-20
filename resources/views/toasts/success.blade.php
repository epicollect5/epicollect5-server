@if (session('message'))
    <div class="var-holder-success" data-message="{{trans('status_codes.'.session('message'))}}"></div>
    <script>
        EC5.toast.showSuccess($('.var-holder-success').attr('data-message'));
    </script>
@endif