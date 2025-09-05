<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Composer autoload (o incluir manualmente si no usas Composer)
// require 'src/PHPMailer-master/src/PHPMailer.php';
// require 'src/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/src/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configurar headers para JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit;
}

// Conexión a la base de datos local
// $host = 'localhost';
// $db = 'sistelco_mx';
// $user = 'root';
// $pass = '';

// Conexión a la base de datos en el servidor remoto
$host = 'localhost';
$db = 'sistelcocom_db';
$user = 'sistelcocom_user';
$pass = '1Nw8R{wlWJ5l';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    http_response_code(500);
    file_put_contents("debug_connect_error.txt", ["success" => false, "message" => "Error de conexión a BD: " . $conn->connect_error], FILE_APPEND);
    echo json_encode(["success" => false, "message" => "Error de conexión a BD: " . $conn->connect_error]);
    exit;
}

// Configurar charset para evitar problemas de codificación
$conn->set_charset("utf8");

try {
    // Leer datos JSON
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    // Debug: guardar datos recibidos
    file_put_contents("debug_data.txt", "Input: " . $input . "\nDecoded: " . print_r($data, true) . "\n", FILE_APPEND);
    
    // Verificar que se recibieron datos
    if ($data === null) {
        throw new Exception("No se pudieron decodificar los datos JSON");
    }
    
    // Extraer y validar datos
    $nombre = trim($data['nombre'] ?? '');
    $empresa = trim($data['empresa'] ?? '');
    $email = trim($data['email'] ?? '');
    $telefono = trim($data['telefono'] ?? '');
    $interes = trim($data['interes'] ?? '');
    $mensaje = trim($data['mensaje'] ?? '');
    $privacy = isset($data['privacyAccepted']) && $data['privacyAccepted'] ? 'SI' : 'No';
    $fecha = trim($data['selectedDateTime'] ?? '');
    $demo = isset($data['requestDemo']) && $data['requestDemo'] ? 'SI' : 'No';
    $timestamp = date("Y-m-d H:i:s");
    
    // Validaciones básicas
    if (empty($nombre)) {
        throw new Exception("El nombre es requerido");
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Email válido es requerido");
    }
    
    // Preparar y ejecutar consulta
    $sql = "INSERT INTO contactos (nombre, empresa, email, telefono, interes, mensaje, privacidad, fecha_seleccionada, solicita_demo, fecha_envio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conn->error);
    }
    
    // CORRECCIÓN IMPORTANTE: Especificar los tipos de datos en bind_param
    // s = string, i = integer, d = double, b = blob
    $stmt->bind_param("ssssssssss", $nombre, $empresa, $email, $telefono, $interes, $mensaje, $privacy, $fecha, $demo, $timestamp);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar consulta: " . $stmt->error);
    }
    
    // Respuesta exitosa
    // $response = [
    //     "success" => true, 
    //     "message" => "Datos guardados correctamente",
    //     "id" => $conn->insert_id
    // ];
    
    // echo json_encode($response);
    
} catch (Exception $e) {
    // Manejar errores
    http_response_code(500);
    $error_response = [
        "success" => false, 
        "message" => $e->getMessage()
    ];
    
    // Log del error
    file_put_contents("debug_data.txt", "Error: " . $e->getMessage() . "\n", FILE_APPEND);
    
    echo json_encode($error_response);
    
} finally {
    // Cerrar conexiones
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}

// Enviar el correo
$mail = new PHPMailer(true);

try {
    // Configuración del servidor SMTP
    $mail->isSMTP();
    $mail->Host = 'mail.sistelco.com.mx';
    $mail->SMTPAuth = true;
    $mail->Username = 'contacto@sistelco.com.mx';
    $mail->Password = '35352asfaas@';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // O PHPMailer::ENCRYPTION_SMTPS para SSL
    $mail->Port = 465;
    // $mail->Port = 587;
    
    // Establecer codificación UTF-8
    $mail->CharSet = 'UTF-8';

    // Configuración del mensaje
    $mail->setFrom($email, $nombre);
    $mail->addAddress('contacto@sistelco.com.mx');
    $mail->addCC('ilan.tesone@sistel.co'); // Copia

    $mail->Subject = "Nuevo mensaje de contacto - $nombre";
    $mail->Body = "
        Nombre: $nombre\n
        Empresa: $empresa\n
        Email: $email\n
        Teléfono: $telefono\n
        Interés: $interes\n
        Mensaje: $mensaje\n
        Solicita demo: $demo\n
        Fecha seleccionada: $fecha\n
        Privacidad aceptada: $privacy\n
        Fecha de envío: $timestamp
            ";

    $mail->send();
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al enviar el correo: {$mail->ErrorInfo}"]);
}
