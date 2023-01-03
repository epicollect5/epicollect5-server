<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href='https://fonts.googleapis.com/css?family=Arimo' rel='stylesheet' type='text/css'>

    <title>Epicollect5 - {{ $projectName }} - Formbuilder</title>

    <meta id="formbuilder-version" data-version="{{ ENV('RELEASE') }}">

    @include('favicon')

    <link rel="stylesheet" type="text/css"
        href="{{ asset('formbuilder/css/vendor-formbuilder.css') . '?v=' . ENV('RELEASE') }}">
    <link rel="stylesheet" type="text/css"
        href="{{ asset('formbuilder/css/formbuilder.css') . '?v=' . ENV('RELEASE') }}">

    <script src="{{ asset('/js/vendor-site.js') . '?v=' . ENV('RELEASE') }}"></script>
    <script src="{{ asset('/js/site.js') . '?v=' . ENV('RELEASE') }}"></script>
    <script>
        window.EC5 = window.EC5 || {};
        window.EC5.SITE_URL = '{{ url('') }}';
    </script>
</head>
<!--[if IE 9]>
    <body class="loader-background ie9"> <![endif]-->
<!--[if gt IE 9]>
    <body class="loader-background"><![endif]-->
<div class="loader">Loading...</div>
<div class="wait-overlay"></div>

<!--[if lt IE 9]>
    <p class="browserupgrade">You are using an <strong>outdated</strong> browser. Please <a
            href="http://browsehappy.com/">upgrade
    your browser</a> to use this app.</p>
    <![endif]-->

<nav class="navbar navbar-inverse navbar-fixed-top hidden formbuilder-navbar" role="navigation">
    <!--navbar content goes here-->
</nav>

<div class="warning-well screen-resolution">The formbuilder does not support the current screen resolution</div>

<div class="container-fluid page-formbuilder hidden">
    <div class="row">
        <section class="inputs-tools col-sm-3 col-md-2">
            <!--Iputs tools will show here (draggable)-->
        </section>

        <section class="main col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 ">

            <!-- Nav tabs -->
            <ul class="main__tabs nav nav-tabs" role="tablist">
                <li role="presentation" class="active main__tabs__form-btn">
                    <a href="{#form-ref}-tabpanel" role="tab" data-toggle="tab" data-form-index="0">University
                        <i class="form-state fa fa-check"></i>
                    </a>
                </li>
                <li role="presentation">
                    <!-- trigger add form modal-->
                    <a class="main__tabs_add-form" href="#" aria-controls="profile" role="tab"
                        data-toggle="modal" data-target=".main__modal--edit-form-name">Add child form
                        <i class="fa fa-plus"></i>
                    </a>
                </li>
                <li class='main__tabs-btns pull-right'>
                    <div class="btn-group">
                        <button type="button" class="btn btn-default main__tabs__undo-btn" disabled>
                            <i class="project-state fa fa-undo fa-fw"></i>
                            <span>Undo</span>
                        </button>
                        <button type="button" class="btn btn-default main__tabs__save-project-btn" disabled>
                            <i class="project-state fa fa-warning fa-fw"></i>
                            Save <span>project</span>
                        </button>
                    </div>
                </li>
            </ul>

            <!-- Modal -->
            <div class="main__modal--edit-form-name modal fade" tabindex="-1" role="dialog"
                aria-labelledby="Edit form name">
                <!-- load modal dynamically-->
            </div>

            <!-- form/tab content, each tab holding the content for a form-->
            <div class="main__tabs-content tab-content">

                <div role="tabpanel" class="main__tabs-content-tabpanel tab-pane fade in active"
                    id="{form-ref}-tabpanel">

                    <div id="form-ref-inputs-collection" class="inputs-collection col-md-6">
                        <!-- Inputs collection (sortable) container will load here dinamically-->
                    </div>

                    <!-- Input Properties panel-->
                    <div id="form-ref-input-properties" class="input-properties col-md-6">
                        <!-- Properties panel is loaded here dynamically, one per each input-->
                    </div>

                </div>
            </div>

        </section>
    </div>
</div>

<div class="print-preview-wrapper"></div>

<footer>

    <!-- Modal -->
    <div id="info-title" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel">What do you mean by <strong>title</strong>?</h4>
                </div>
                <div class="modal-body">
                    <p>Each entry you will add on your device will appear on a list.<br />
                        To identify each entry, you can set some of your question answers to be part of the entry title
                        (up to a maximum of 3 for either a form or a branch)</p>
                    <p>For example, let's say you have 3 questions like:</p>
                    <ul class="list-group">
                        <li class="list-group-item">What is your name?</li>
                        <li class="list-group-item">What is your age?</li>
                        <li class="list-group-item">What is your date of birth?</li>
                    </ul>
                    <p>If you set all these questions to be a title, you will end up with a list of entries like:</p>
                    <ul class="list-group">
                        <li class="list-group-item">Mirko, 39, 22/05/1977</li>
                        <li class="list-group-item">John, 18, 24/01/1990</li>
                        <li class="list-group-item">...</li>
                    </ul>
                    <p>Remember, if you do not set any title at all, the entry unique identifier generated by the system
                        will be shown instead: <br />
                        <code>149da8d4-2807-11e6-b67b-9e71128cae77</code> <br />
                        Since it is not really readable, we highly recommend you select some questions as
                        <strong>title</strong>!
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-action" data-dismiss="modal">Close</button>
                </div>
            </div>js
        </div>
    </div>

    <div id="info-regex" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">What is a <strong>regex</strong>?</h4>
                </div>
                <div class="modal-body">
                    <p>A regular expression (regex) is a special text string for describing a search pattern. You can
                        think of regular expressions as wildcards. </p>
                    <p> You are probably familiar with wildcard notations such
                        as <code>*.txt</code> to find all text files in a file manager. The regex equivalent is
                        <code>.*\.txt</code>
                    </p>

                    <p>There are many things you can do applying regex to your question, for example:</p>
                    <table class="table table-hover">
                        <tr>
                            <th>
                                Allow only letters
                            </th>
                            <td>
                                <button class="btn btn-default btn-action-inverse pull-right"
                                    data-apply-regex="only_letters">Apply this</button>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                Allow only digits
                            </th>
                            <td>
                                <button class="btn btn-default btn-action-inverse pull-right"
                                    data-apply-regex="only_digits">Apply this</button>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                Limit the answer length to 20 chars <br />(or any length you like, just replace 20 with
                                the length you want)
                            </th>
                            <td>
                                <button class="btn btn-default btn-action-inverse pull-right"
                                    data-apply-regex="limit_length_20">Apply this</button>
                            </td>
                        </tr>
                    </table>
                    <p class="bg-warning"><strong>Important: </strong>make sure the regex fits the context of the input
                        type, as if you apply a letter only regex to a numeric input (which accepts only digits), it
                        would be impossible for the user to answer!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-action" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="info-import-form" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">What are Epicollect5 forms?</h4>
                </div>
                <div class="modal-body">
                    <p>Each Epicollect5 form you create using the formbuilder can be exported as a <code>.json</code>
                        file and imported within the same project or another project.</p>

                    <p class="text-center text-underline">
                        <strong>
                            <a href="https://docs.epicollect.net/formbuilder/importexport-forms" target="_blank">Show
                                me how to do it!</a>
                        </strong>
                    </p>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-action" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="csv-import-possible_answers" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Possible answers csv import options</h4>
                </div>
                <div class="modal-body">
                    <form>

                        <div class="form-group possible_answers__selected-column">
                            <label>Select which dataset to import</label>
                            <div class="possible-answers-column-picker btn-group pull-right">
                                <button type="button" class="btn btn-default btn-sm dropdown-toggle"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    Pick column <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu">
                                </ul>
                            </div>
                        </div>

                        <hr />

                        <div class="form-group possible_answers__first-row-headers">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" checked> First row contains headers
                                </label>
                            </div>
                        </div>

                        <hr />

                        <div class="form-group possible_answers__append-or-replace">
                            <div class="radio">
                                <label>
                                    <input type="radio" name="possibleAnswersImportOptions" id="replace"
                                        value="option2" checked>
                                    Replace all existing possible answers
                                </label>
                            </div>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="possibleAnswersImportOptions" id="append"
                                        value="option1">
                                    Append to existing possible answers
                                </label>
                            </div>
                        </div>
                        <hr />
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-default pull-left"
                        data-dismiss="modal">Dismiss</button>
                    <button type="button" class="btn btn-default btn-action possible_answers-perform-import"
                        disabled>Import</button>
                </div>
            </div>
        </div>
    </div>


</footer>
<script src="{{ asset('formbuilder/js/vendor-formbuilder.js') . '?v=' . ENV('RELEASE') }}"></script>
<script src="{{ asset('formbuilder/js/formbuilder.js') . '?v=' . ENV('RELEASE') }}"></script>
@if (env('APP_ENV') == 'production')
    <script defer data-domain="five.epicollect.net" src="https://analytics.cgps.dev/js/plausible.js"></script>
@endif


</body>

</html>
