<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

class DBBackUpAPIController extends Controller
{
    //d´s ef
    public function db_backup()
    {
        try {
            $start_time = microtime(true);

            // Ruta al archivo .env
            $envFilePath = base_path('.env');

            // Cargar las variables de entorno desde el archivo .env
            if (file_exists($envFilePath)) {
                $dotenv = \Dotenv\Dotenv::createImmutable(base_path());
                $dotenv->load();
            }

            // Variables para el respaldo
            $DB_HOST = env('DB_HOST');
            $DB_USER = env('DB_USERNAME');
            $DB_PASS = env('DB_PASSWORD');
            $DB_NAME = env('DB_DATABASE');


            // Definir el directorio de respaldo
            $desktopPath = getenv("HOMEDRIVE") . getenv("HOMEPATH") . "/Desktop";
            $BACKUP_DIR = $desktopPath . "/dbbackups";

            // Crear el directorio de respaldo si no existe
            if (!file_exists($BACKUP_DIR)) {
                if (!mkdir($BACKUP_DIR, 0777, true)) {
                    return response()->json([
                        'error' => "¡Error! No se pudo crear el directorio de respaldo $BACKUP_DIR"
                    ], 500);
                }
            }

            $DATE = date("Y-m-d_H-i-s");

            // Comando mysqldump para crear el respaldo
            // $command = "mysqldump -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/$DB_NAME-$DATE.sql";
            $command = "mysqldump --column-statistics=0 -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/$DB_NAME-$DATE.sql";


            // Depuración de variables y comando
            // error_log("DB_HOST: $DB_HOST");
            // error_log("DB_USER: $DB_USER");
            // error_log("DB_PASS: $DB_PASS");
            // error_log("DB_NAME: $DB_NAME");
            // error_log("Command: $command");

            exec($command, $output, $exitCode);

            // Verificar si el respaldo se realizó correctamente
            if ($exitCode === 0) {
                return response()->json([
                    'message' => "Respaldo de la base de datos $DB_NAME realizado exitosamente en $BACKUP_DIR/$DB_NAME-$DATE.sql"
                ]);
            } else {
                $errorMessage = "¡Error! No se pudo realizar el respaldo de la base de datos $DB_NAME. Detalles del error: " . implode("\n", $output);
                return response()->json([
                    'error' => $errorMessage
                ], 500);
            }
            $end_time = microtime(true);

            $execution_time = $end_time - $start_time;

            error_log("execution_time: $execution_time");
        } catch (\Exception $e) {
            error_log("ERROR: $e");
            return response()->json([
                'error' => 'Ocurrió un error al procesar la solicitud: ¡Error! No se pudo realizar el respaldo de la base de datos ' . $e->getMessage()
            ], 500);
        }
    }
}
