<?php
// send_mail.php - Versión sin use statements
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit;
}

try {
    // Cargar PHPMailer sin use statements
    require_once __DIR__ . '/src/PHPMailer-master/src/Exception.php';
    require_once __DIR__ . '/src/PHPMailer-master/src/SMTP.php';
    require_once __DIR__ . '/src/PHPMailer-master/src/PHPMailer.php';

    error_log("=== INICIO send_mail.php ===");

    // Leer datos
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if ($data === null) {
        throw new Exception("Datos JSON inválidos");
    }

    // Extraer datos
    $nombre = trim($data['nombre'] ?? '');
    $empresa = trim($data['empresa'] ?? '');
    $email = trim($data['email'] ?? '');
    $telefono = trim($data['telefono'] ?? '');
    $mensaje = trim($data['mensaje'] ?? '');

    // Validaciones
    if (empty($nombre) || empty($email) || empty($mensaje)) {
        throw new Exception("Campos requeridos: nombre, email, mensaje");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Email inválido");
    }

    error_log("Procesando contacto de: $nombre ($email)");

    $db_success = false;
    $email_success = false;
    $errors = [];

    // BASE DE DATOS
    try {
        $conn = new mysqli('localhost', 'senseistudio_user', 'F3+Hg?]JWeB+', 'senseistudio_db');
        
        if ($conn->connect_error) {
            throw new Exception("Error conexión BD: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8");
        
        // Verificar/crear tabla
        $result = $conn->query("SHOW TABLES LIKE 'contactos'");
        if ($result->num_rows == 0) {
            $create_table = "
            CREATE TABLE contactos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(255) NOT NULL,
                empresa VARCHAR(255),
                email VARCHAR(255) NOT NULL,
                telefono VARCHAR(100),
                mensaje TEXT NOT NULL,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ";
            $conn->query($create_table);
            error_log("Tabla contactos creada");
        }
        
        // Insertar
        $sql = "INSERT INTO contactos (nombre, empresa, email, telefono, mensaje) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Error prepare: " . $conn->error);
        }
        
        $stmt->bind_param("sssss", $nombre, $empresa, $email, $telefono, $mensaje);
        
        if ($stmt->execute()) {
            $db_success = true;
            error_log("BD: Contacto guardado ID: " . $conn->insert_id);
        } else {
            throw new Exception("Error execute: " . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $errors[] = "BD: " . $e->getMessage();
        error_log("Error BD: " . $e->getMessage());
    }

    // EMAIL - Usar nombres completos de clase
    try {
        // Crear instancia usando nombre completo de clase
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.senseistudio.co';
        $mail->SMTPAuth = true;
        $mail->Username = 'contacto@senseistudio.co';
        $mail->Password = '7Rg=s)UkiRGm';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0;
        
        // Configurar email
        $mail->setFrom('contacto@senseistudio.co', 'SENSEI iA Studio');
        $mail->addReplyTo($email, $nombre);
        $mail->addAddress('contacto@senseistudio.co');
        //$mail->addCC('desarrollo006@sistel.co');
        $mail->addCC('info@sistel.co');
        
        $mail->isHTML(true);
        $mail->Subject = "Contacto SENSEI - $nombre ($empresa)";
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 25px; border-radius: 0 0 8px 8px; }
                .field { margin-bottom: 20px; }
                .label { font-weight: bold; color: #007bff; display: block; margin-bottom: 5px; }
                .value { background: white; padding: 12px; border-radius: 5px; border-left: 4px solid #007bff; }
                .mensaje-box { background: white; padding: 20px; border-radius: 5px; border-left: 6px solid #28a745; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Nuevo contacto desde SENSEI iA Studio</h2>
                </div>
                <div class='content'>
                    <div class='field'>
                        <span class='label'>Nombre:</span>
                        <div class='value'>$nombre</div>
                    </div>
                    <div class='field'>
                        <span class='label'>Empresa:</span>
                        <div class='value'>$empresa</div>
                    </div>
                    <div class='field'>
                        <span class='label'>Email:</span>
                        <div class='value'>$email</div>
                    </div>
                    <div class='field'>
                        <span class='label'>Teléfono:</span>
                        <div class='value'>$telefono</div>
                    </div>
                    <div class='field'>
                        <span class='label'>Mensaje:</span>
                        <div class='mensaje-box'>" . nl2br(htmlspecialchars($mensaje)) . "</div>
                    </div>
                    
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "
        Contacto SENSEI iA Studio
        
        Nombre: $nombre
        Empresa: $empresa
        Email: $email
        Teléfono: $telefono
        
        Mensaje: $mensaje
        
        Fecha: " . date('d/m/Y H:i:s');
        
        if ($mail->send()) {
            $email_success = true;
            error_log("Email enviado correctamente");
        }
        
    } catch (Exception $e) {
        $errors[] = "Email: " . $e->getMessage();
        error_log("Error email: " . $e->getMessage());
    }

    // RESPUESTA FINAL
    ob_clean();
    
    if ($db_success && $email_success) {
        echo json_encode([
            "success" => true,
            "message" => "¡Gracias por contactarnos! Tu mensaje ha sido enviado correctamente."
        ]);
    } elseif ($db_success && !$email_success) {
        echo json_encode([
            "success" => true,
            "message" => "Tu mensaje ha sido guardado. Nos pondremos en contacto contigo.",
            "note" => "Problema menor con email"
        ]);
    } elseif (!$db_success && $email_success) {
        echo json_encode([
            "success" => true,
            "message" => "Tu email ha sido enviado correctamente."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error procesando solicitud. Intenta nuevamente.",
            "errors" => $errors
        ]);
    }

} catch (Exception $e) {
    error_log("Error crítico: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error del servidor: " . $e->getMessage()
    ]);
}

ob_end_flush();
?>