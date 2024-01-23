<!DOCTYPE html>
<html>
<head>
    <title>Código de Validación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            background-color: #ffffff;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333333;
        }

        p {
            color: #666666;
            line-height: 1.4;
        }

        h2 {
            color: #007bff;
            font-size: 24px;
            margin-top: 10px;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            color: #999999;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Código de Validación</h1>

        <p>Estimado usuario,</p>

        <p>Aquí tienes tu código de validación para el retiro correspondiente al monto de ${{ $monto }}:</p>

        <h2>{{ $resultCode }}</h2>

        <p>Utiliza este código para completar tu proceso de validación. Si no has solicitado este código o tienes alguna pregunta, por favor contáctanos.</p>

        <p>¡Gracias por usar nuestro servicio!</p>

        <div class="footer">
            Atentamente,<br>
            EasyEcommer
        </div>
    </div>
</body>
</html>
