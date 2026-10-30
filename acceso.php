<?php
// acceso.php
// Login, logout, saber quién está logueado y los números del panel.
require_once "conexion.php";

$accion = isset($_GET["accion"]) ? $_GET["accion"] : "";

if ($accion == "login") {
    $datos = leer_body();
    $usuario = isset($datos["usuario"]) ? trim($datos["usuario"]) : "";
    $clave = isset($datos["clave"]) ? $datos["clave"] : "";

    if ($usuario == "" || $clave == "") {
        fallar("Complete usuario y contraseña.");
    }

    $filas = consultar("SELECT * FROM usuarios WHERE usuario = ?", "s", array($usuario));
    if (count($filas) == 0 || !password_verify($clave, $filas[0]["clave"])) {
        fallar("Usuario o contraseña incorrectos.");
    }

    $fila = $filas[0];
    session_regenerate_id(true);
    $_SESSION["id_usuario"] = $fila["id_usuario"];
    $_SESSION["nombre"] = $fila["nombre"] . " " . $fila["apellido"];
    $_SESSION["rol"] = $fila["rol"];

    responder(array("nombre" => $_SESSION["nombre"], "rol" => $_SESSION["rol"]));
}

if ($accion == "logout") {
    session_unset();
    session_destroy();
    responder();
}

if ($accion == "sesion") {
    if (isset($_SESSION["id_usuario"])) {
        responder(array("logueado" => true, "nombre" => $_SESSION["nombre"], "rol" => $_SESSION["rol"]));
    }
    responder(array("logueado" => false));
}

if ($accion == "resumen") {
    pedir_rol(array("Administrador", "Recepcionista", "Coordinador"));

    $resumen = array(
        "documentos" => contar("SELECT COUNT(*) AS total FROM documentos"),
        "encuestas_activas" => contar("SELECT COUNT(*) AS total FROM encuestas WHERE estado = 'Activa'"),
        "respuestas" => contar("SELECT COUNT(*) AS total FROM respuestas"),
        "traslados_pendientes" => contar("SELECT COUNT(*) AS total FROM traslados WHERE estado = 'Pendiente'"),
        "traslados_en_curso" => contar("SELECT COUNT(*) AS total FROM traslados WHERE estado IN ('En viaje', 'En destino', 'Regresando')"),
        "ambulancias_disponibles" => contar("SELECT COUNT(*) AS total FROM ambulancias WHERE estado = 'Disponible'")
    );
    responder(array("resumen" => $resumen));
}

// ---------- usuarios (solo el Administrador) ----------

if ($accion == "usuarios_listar") {
    pedir_rol(array("Administrador"));
    $filas = consultar("SELECT id_usuario, nombre, apellido, usuario, rol FROM usuarios ORDER BY apellido, nombre");
    responder(array("usuarios" => $filas));
}

if ($accion == "usuario_crear") {
    pedir_rol(array("Administrador"));
    $datos = leer_body();
    $nombre = isset($datos["nombre"]) ? trim($datos["nombre"]) : "";
    $apellido = isset($datos["apellido"]) ? trim($datos["apellido"]) : "";
    $usuario = isset($datos["usuario"]) ? trim($datos["usuario"]) : "";
    $clave = isset($datos["clave"]) ? $datos["clave"] : "";
    $rol = isset($datos["rol"]) ? $datos["rol"] : "";

    if ($nombre == "" || $apellido == "" || $usuario == "" || $clave == "" || $rol == "") {
        fallar("Complete todos los campos.");
    }
    if (mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
        fallar("El nombre y el apellido pueden tener hasta 100 letras.");
    }
    if (!preg_match("/^[a-zA-Z0-9._-]{3,50}$/", $usuario)) {
        fallar("El usuario debe tener entre 3 y 50 caracteres: letras sin tilde, números, punto, guion o guion bajo.");
    }
    if (strlen($clave) < 8) {
        fallar("La contraseña debe tener al menos 8 caracteres.");
    }
    if (!in_array($rol, array("Administrador", "Recepcionista", "Coordinador"))) {
        fallar("El rol elegido no es válido.");
    }
    if (contar("SELECT COUNT(*) AS total FROM usuarios WHERE usuario = ?", "s", array($usuario)) > 0) {
        fallar("Ya existe un usuario con ese nombre de usuario.");
    }

    // la contraseña nunca se guarda tal cual: se guarda su hash
    $hash = password_hash($clave, PASSWORD_DEFAULT);
    ejecutar("INSERT INTO usuarios (nombre, apellido, usuario, clave, rol) VALUES (?, ?, ?, ?, ?)",
             "sssss", array($nombre, $apellido, $usuario, $hash, $rol));
    responder(array("mensaje" => "Usuario creado correctamente."));
}

fallar("Acción no reconocida.");
