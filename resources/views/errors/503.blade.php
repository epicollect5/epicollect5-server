<!DOCTYPE html>
<html>
<head>
    <title>{{ config('app.name') }} - {{ trans('site.be_right_back') }}</title>

    <link href="https://fonts.googleapis.com/css?family=Lato:100" rel="stylesheet" type="text/css">

    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            width: 100%;
            color: #673C90;
            display: flex;
            flex-direction: column;
            font-weight: bold;
            font-family: "Roboto", "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        .container {
            text-align: center;
            flex: 1; /* Allow the container to take up available space */
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .content {
            text-align: center;
        }

        .title {
            font-size: 72px;
            margin-bottom: 40px;
        }

        .lead {
            font-size: 24px;
        }

        .footer {
            text-align: center;
            background-color: #f5f5f5;
            color: black;
            padding: 20px 0;
            width: 100%;
            position: relative;
            bottom: 0;

            a {
                color: #673C90;
            }
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
        <p class="lead">Sorry for the inconvenience.</p>
    </div>
</div>

<footer class="footer">
    <a href="https://www.pathogensurveillance.net/">&copy; {{ date('Y') }} Centre for
        Genomic Pathogen
        Surveillance</a>,&nbsp;v{{ config('app.production_server_version') }}
</footer>

</body>
</html>
