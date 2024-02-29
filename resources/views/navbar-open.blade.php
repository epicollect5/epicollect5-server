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
<nav class="navbar navbar-default navbar-fixed-top site-navbar project-open-navbar">

    <a class="navbar-brand" href="{{ url('/') }}">
        <img src="{{ asset('/images/brand.png') }}" width="180" height="40"
             alt="Epicollect5: Mobile & Web Application for free and easy data collection.">
    </a>

</nav>
