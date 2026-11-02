<!DOCTYPE html>
<html lang="en">

<head>
    <?php require('view/template/head.php') ?>
    <style>
        #http-error-msg {
            height: 50vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 2rem;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <?php include('inc/html/nav.html'); ?>

    <div id="http-error-msg">
        <p>x.x oopisee Error!!</p>
    </div>

    <p style="display:none;" id="exception"><?= $_exception ?></p>
    <p style="display:none;" id="exception-msg"><?= $_exception->getMessage() ?></p>
    <p style="display:none;" id="exception-line"><?= $_exception->getLine() ?></p>
    <script>
        setTimeout(_ => { // log error
            const data = {};
            data.error = document.getElementById('exception').innerText;
            data.error_message = document.getElementById('exception-msg').innerText;
            data.error_line = document.getElementById('exception-line').innerText;
            console.error(data);
            gtag('event', 'exception', data);
        }, 0);
    </script>

</body>

</html>