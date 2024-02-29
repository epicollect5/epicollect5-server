<style>
    .navbar-nav > li, .navbar-nav {
        float: left !important;
    }

    .navbar-nav.navbar-right:last-child {
        margin-right: -15px !important;
    }

    .navbar-right {
        float: right !important;
    }

    /* Hide the buttons by default */
    .android-button, .ios-button {
        display: none !important;
    }
</style>
<script>
    $(document).ready(function () {
        console.log(navigator.userAgent);
        var isAndroid = /Android/.test(navigator.userAgent);
        var isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);
        if (isAndroid) {
            $('.android-button').removeClass('android-button');
        } else if (isIOS) {
            $('.ios-button').removeClass('ios-button');
        }
    });
</script>
<nav class="navbar navbar-default navbar-fixed-top site-navbar project-open-navbar">

    <a class="navbar-brand visible-xs-block" href="{{ url('/') }}">
        <img src="{{ asset('/images/epicollect-icon-256x256@2x.png') }}" width="36"
             alt="Epicollect5: Mobile & Web Application for free and easy data collection.">
    </a>
    <a class="navbar-brand visible-sm-block visible-md-block visible-lg-block visible-xl-block" href="{{ url('/') }}">
        <img src="{{ asset('/images/brand.png') }}" width="180" height="40"
             alt="Epicollect5: Mobile & Web Application for free and easy data collection.">
    </a>
    <ul class="nav navbar-nav navbar-right app-links">
        <li class="android-button">
            <a href="https://play.google.com/store/apps/details?id=uk.ac.imperial.epicollect.five&hl=en_GB">
                <i class="material-icons">
                    android
                </i>
                <span class="">Get the App</span>
            </a>
        </li>
        <li class="ios-button">
            <a href="https://apps.apple.com/us/app/epicollect5/id1183858199">
                <i class="material-icons">
                    apple
                </i>
                <span class="hidden-380"> Get the App</span>
            </a>
        </li>
        <li>
            <a href="{{ url('project/' . $requestAttributes->requestedProject->slug . '/data') }}">
                <i class="material-icons">
                    open_in_browser
                </i>
                <span class="hidden-380"> Open in Browser</span>
            </a>
        </li>
    </ul>
</nav>
