<?php
// conexion.php
// Lo incluyen todos los demás .php. Se conecta a la base y tiene
// las funciones chiquitas que se repiten en todos lados.

session_start();

$servidor = "localhost";
$usuario_db = "root";
$clave_db = "";
$base = "clinical_data";

mysqli_report(MYSQLI_REPORT_OFF); // los errores los manejamos nosotros
$con = @new mysqli($servidor, $usuario_db, $clave_db, $base);
if ($con->connect_error) {
    fallar("No se pudo conectar a la base de datos clinical_data.", 500);
}
$con->set_charset("utf8mb4");


// ---------- respuestas para el JavaScript ----------

// devuelve datos en formato JSON y corta el programa
function responder($datos = array()) {
    header("Content-Type: application/json; charset=utf-8");
    $datos["ok"] = true;
    echo json_encode($datos);
    exit;
}

// devuelve un error en formato JSON y corta el programa
function fallar($mensaje, $codigo = 400) {
    http_response_code($codigo);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(array("ok" => false, "mensaje" => $mensaje));
    exit;
}

// lee los datos que mandó el JavaScript con fetch (vienen como JSON)
function leer_body() {
    $texto = file_get_contents("php://input");
    $datos = json_decode($texto, true);
    if (is_array($datos)) {
        return $datos;
    }
    return array();
}


// ---------- control de usuarios ----------

// corta si no hay sesión o si el rol no está en la lista
function pedir_rol($roles) {
    if (!isset($_SESSION["id_usuario"])) {
        fallar("Debe iniciar sesión.", 401);
    }
    if (!in_array($_SESSION["rol"], $roles)) {
        fallar("No tiene permisos para esta acción.", 403);
    }
}


// ---------- base de datos ----------

// SELECT: devuelve una lista de filas
// ejemplo: consultar("SELECT * FROM rutas WHERE id_ruta = ?", "i", array(3));
function consultar($sql, $tipos = "", $valores = array()) {
    global $con;
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        fallar("Error en la consulta: " . $con->error, 500);
    }
    if ($tipos != "") {
        $stmt->bind_param($tipos, ...$valores);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();
    $filas = array();
    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $filas[] = $fila;
        }
    }
    $stmt->close();
    return $filas;
}

// INSERT / UPDATE / DELETE: devuelve el id que se acaba de insertar
function ejecutar($sql, $tipos = "", $valores = array()) {
    global $con;
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        fallar("Error en la consulta: " . $con->error, 500);
    }
    if ($tipos != "") {
        $stmt->bind_param($tipos, ...$valores);
    }
    if (!$stmt->execute()) {
        fallar("No se pudo guardar: " . $stmt->error, 500);
    }
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

// cuenta filas: contar("SELECT COUNT(*) AS total FROM documentos")
function contar($sql, $tipos = "", $valores = array()) {
    $filas = consultar($sql, $tipos, $valores);
    return (int) $filas[0]["total"];
}
