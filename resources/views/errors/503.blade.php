<!DOCTYPE html>
<html>
    <head>
        <title>{{ Config::get('app.name') }} - {{ trans('site.be_right_back') }}</title>

        <link href="https://fonts.googleapis.com/css?family=Lato:100" rel="stylesheet" type="text/css">

        <style>
            html, body {
                height: 100%;
            }

            body {
                margin: 0;
                padding: 0;
                width: 100%;
                color: #673C90;
                display: table;
                font-weight: bold;
                font-family: "Roboto", "Helvetica Neue", Helvetica, Arial, sans-serif;
            }

            .container {
                text-align: center;
                display: table-cell;
                vertical-align: middle;
            }

            .content {
                text-align: center;
                display: inline-block;
            }

            .title {
                font-size: 72px;
                margin-bottom: 40px;
            }
            .lead {
                font-size: 24px;;
            }
        </style>
    </head>
    <body>

        <div class="container">
            <div class="content">
                <img src="{{ asset('/images/brand.png') }}"
                     alt="Epicollect5"
                     width="180"
                     height="40">
                <div class="title">Be right back.</div>
                <p class="lead"> We are performing essential maintenance on our servers. </p>
                <p class="lead" >Sorry for the inconvenience.</p>
            </div>
        </div>
    </body>
</html>
