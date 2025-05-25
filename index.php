<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TransitNYC</title>
</head>
<body style="margin: 0;">
    <img id="background" src="./img/NYC.png" style="position: fixed; opacity: 0.3; width: 100%; height: 100%; z-index: -5;">

    <p id="welcomeMsg" style="text-align: center; font-size: 25px; color: rgb(255, 80, 0);
            width: fit-content; margin: auto; padding: 5px;">
        Welcome to TransitNYC!<br>Click on the logo of a transit agency to continue.</p>

    <button id="mnr" class="agency" style="position: absolute; border: 0px;
            background-color: rgba(0, 0, 0, 0); width: 10%; top: 20%; left: 62%;">
        <img src="./img/mnr.jpeg" style="width: 100%; border: 2px solid black"></button>
    <button id="lirr" class="agency" style="position: absolute; border: 0px;
            background-color: rgba(0, 0, 0, 0); width: 10%; top: 70%; left: 65%;">
        <img src="./img/lirr.jpeg" style="width: 100%; border: 2px solid black"></button>
    <button id="njt" class="agency" style="position: absolute; border: 0px;
            background-color: rgba(0, 0, 0, 0); width: 10%; top: 65%; left: 21%;">
        <img src="./img/njt.jpeg" style="width: 100%; border: 2px solid black"></button>
    <script>
        document.getElementById('mnr').addEventListener('click',() => { window.location='./agency.php?a=mnr'; });
        document.getElementById('lirr').addEventListener('click',() => { window.location='./agency.php?a=lirr'; });
        document.getElementById('njt').addEventListener('click',() => { window.location='./agency.php?a=njt'; });
    </script>
</body>
</html>