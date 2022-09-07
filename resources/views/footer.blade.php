<footer class="footer">
    <div class="container-fluid">

        <div class="row">
            <div class="col-xs-12 col-sm-12 col-md-6 col-lg-4 footer-links">
                <ul>
                    <li>
                        <small><a href="https://www.pathogensurveillance.net/">&copy; {{ date('Y') }} Centre for
                                Genomic Pathogen Surveillance</a>,&nbsp;v{{ env('PRODUCTION_SERVER_VERSION') }}
                        </small>
                    </li>

                    <li>
                        <small>
                            <a href="https://docs.epicollect.net/about/privacy-policy">Privacy & Cookie Policy</a>
                        </small>
                    </li>
                    <li>
                        <small>
                            <a href="https://community.epicollect.net" target="_blank">Contact Us</a>
                        </small>
                    </li>
                </ul>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-6 col-lg-2 footer-wellcome-trust">
                <a href="https://wellcome.ac.uk/">
                    <img src="{{ asset('images/src_images_footer_wellcome.png') }}" alt="Wellcome Trust" width="75"
                        height="75">
                </a>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-6 col-lg-3 footer-bdi-logo">
                <a href="http://www.ox.ac.uk">
                    <img src="{{ asset('images/src_images_footer_oxford.png') }}" alt="University of Oxford"
                        width="75" height="75">
                </a>
                <a href="https://www.bdi.ox.ac.uk/">
                    <img src="{{ asset('images/src_images_footer_bdi.png') }}" alt="Big Data Institute" width="75"
                        height="75">
                </a>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-6 col-lg-3 footer-links">
                <ul>
                    <li>
                        <small>
                            <a href="https://docs.epicollect.net/">User Guide</a>
                        </small>
                    </li>
                    <li>
                        <small>
                            <a href="https://community.epicollect.net">Community Support</a>
                        </small>
                    </li>
                    <li>
                        <small>
                            <a href="https://developers.epicollect.net/">Developers</a>
                        </small>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!-- Begin Cookie Consent plugin by Silktide - http://silktide.com/cookieconsent -->
    <script type="text/javascript" defer>
        window.cookieconsent_options = {
            message: 'Epicollect5 uses cookies to ensure you get the best experience on our website.  <a href="https://docs.epicollect.net/about/cookie-policy" style="color:#fff;text-decoration: underline"> Learn more<a/>',
            dismiss: 'Got it!',
            link: null,
            theme: '{{ asset('css/site.css') }}'
        };
    </script>
</footer>
