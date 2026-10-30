<?php
// documentos.php
// Módulo 1: documentos para pacientes y encuestas de satisfacción.
// Las acciones "listar", "ver", "encuesta_publica" y "responder"
// son públicas (las usa el paciente desde el QR). Las demás piden login.
require_once "conexion.php";

$accion = isset($_GET["accion"]) ? $_GET["accion"] : "";
$roles = array("Administrador", "Recepcionista");


// revisa que el archivo subido sea un PDF y lo guarda en la carpeta documentos/
// devuelve el nombre con el que quedó guardado
function guardar_pdf() {
    if (!isset($_FILES["archivo"]) || $_FILES["archivo"]["error"] != UPLOAD_ERR_OK) {
        fallar("Elija un archivo PDF (revise que no supere el tamaño máximo del servidor).");
    }

    $nombre_original = $_FILES["archivo"]["name"];
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    $inicio = file_get_contents($_FILES["archivo"]["tmp_name"], false, null, 0, 4);
    if ($extension != "pdf" || $inicio != "%PDF") {
        fallar("Solo se aceptan archivos PDF.");
    }

    $nombre_final = time() . "_" . preg_replace("/[^a-zA-Z0-9_.-]/", "_", $nombre_original);
    if (!is_dir(__DIR__ . "/documentos")) {
        mkdir(__DIR__ . "/documentos", 0777, true);
    }
    if (!move_uploaded_file($_FILES["archivo"]["tmp_name"], __DIR__ . "/documentos/" . $nombre_final)) {
        fallar("No se pudo guardar el archivo en el servidor.", 500);
    }
    return $nombre_final;
}


// ---------------- DOCUMENTOS ----------------

if ($accion == "listar") {
    $filas = consultar("SELECT d.id_documento, d.titulo, d.descripcion, d.categoria, d.fecha_alta,
                               CONCAT(u.nombre, ' ', u.apellido) AS subido_por
                        FROM documentos d
                        JOIN usuarios u ON u.id_usuario = d.id_usuario
                        ORDER BY d.categoria, d.titulo");
    // al paciente no le mostramos quién subió el documento
    if (!isset($_SESSION["id_usuario"])) {
        foreach ($filas as $i => $fila) {
            unset($filas[$i]["subido_por"]);
        }
    }
    responder(array("documentos" => $filas));
}

if ($accion == "ver") {
    $id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
    $filas = consultar("SELECT archivo FROM documentos WHERE id_documento = ?", "i", array($id));
    if (count($filas) == 0) {
        http_response_code(404);
        echo "Documento no encontrado";
        exit;
    }
    $ruta = __DIR__ . "/documentos/" . basename($filas[0]["archivo"]);
    if (!is_file($ruta)) {
        http_response_code(404);
        echo "El archivo no está en el servidor";
        exit;
    }
    header("Content-Type: application/pdf");
    header("Content-Disposition: inline; filename=\"" . basename($ruta) . "\"");
    header("Content-Length: " . filesize($ruta));
    readfile($ruta);
    exit;
}

if ($accion == "subir") {
    pedir_rol($roles);

    $titulo = isset($_POST["titulo"]) ? trim($_POST["titulo"]) : "";
    $descripcion = isset($_POST["descripcion"]) ? trim($_POST["descripcion"]) : "";
    $categoria = isset($_POST["categoria"]) ? trim($_POST["categoria"]) : "";

    if ($titulo == "" || $categoria == "") {
        fallar("Complete el título y la categoría.");
    }
    $nombre_final = guardar_pdf();

    ejecutar("INSERT INTO documentos (titulo, descripcion, categoria, archivo, fecha_alta, id_usuario)
              VALUES (?, ?, ?, ?, NOW(), ?)",
             "ssssi", array($titulo, $descripcion, $categoria, $nombre_final, $_SESSION["id_usuario"]));
    responder(array("mensaje" => "Documento guardado correctamente."));
}

if ($accion == "editar") {
    pedir_rol($roles);

    $id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    $titulo = isset($_POST["titulo"]) ? trim($_POST["titulo"]) : "";
    $descripcion = isset($_POST["descripcion"]) ? trim($_POST["descripcion"]) : "";
    $categoria = isset($_POST["categoria"]) ? trim($_POST["categoria"]) : "";

    $filas = consultar("SELECT archivo FROM documentos WHERE id_documento = ?", "i", array($id));
    if (count($filas) == 0) {
        fallar("El documento no existe.");
    }
    if ($titulo == "" || $categoria == "") {
        fallar("Complete el título y la categoría.");
    }

    // el PDF es opcional: si no se elige uno nuevo, queda el que ya estaba
    $eligio_pdf = isset($_FILES["archivo"]) && $_FILES["archivo"]["error"] != UPLOAD_ERR_NO_FILE;
    if ($eligio_pdf) {
        $nombre_final = guardar_pdf();
        ejecutar("UPDATE documentos SET titulo = ?, descripcion = ?, categoria = ?, archivo = ? WHERE id_documento = ?",
                 "ssssi", array($titulo, $descripcion, $categoria, $nombre_final, $id));
        // recién ahora se borra el PDF anterior
        $ruta_vieja = __DIR__ . "/documentos/" . basename($filas[0]["archivo"]);
        if (is_file($ruta_vieja)) {
            unlink($ruta_vieja);
        }
    } else {
        ejecutar("UPDATE documentos SET titulo = ?, descripcion = ?, categoria = ? WHERE id_documento = ?",
                 "sssi", array($titulo, $descripcion, $categoria, $id));
    }
    responder(array("mensaje" => "Documento actualizado correctamente."));
}

if ($accion == "eliminar_documento") {
    pedir_rol($roles);
    $datos = leer_body();
    $id = isset($datos["id"]) ? (int) $datos["id"] : 0;

    $filas = consultar("SELECT archivo FROM documentos WHERE id_documento = ?", "i", array($id));
    if (count($filas) == 0) {
        fallar("El documento no existe.");
    }
    ejecutar("DELETE FROM documentos WHERE id_documento = ?", "i", array($id));
    $ruta = __DIR__ . "/documentos/" . basename($filas[0]["archivo"]);
    if (is_file($ruta)) {
        unlink($ruta);
    }
    responder(array("mensaje" => "Documento eliminado."));
}


// ---------------- ENCUESTAS (administración) ----------------

if ($accion == "encuestas_listar") {
    pedir_rol($roles);
    $filas = consultar("SELECT e.id_encuesta, e.titulo, e.descripcion, e.estado, e.fecha_creacion,
                               (SELECT COUNT(*) FROM respuestas r WHERE r.id_encuesta = e.id_encuesta) AS total_respuestas
                        FROM encuestas e
                        ORDER BY e.id_encuesta DESC");
    responder(array("encuestas" => $filas));
}

if ($accion == "encuesta_crear") {
    pedir_rol($roles);
    $datos = leer_body();
    $titulo = isset($datos["titulo"]) ? trim($datos["titulo"]) : "";
    $descripcion = isset($datos["descripcion"]) ? trim($datos["descripcion"]) : "";
    $preguntas = isset($datos["preguntas"]) && is_array($datos["preguntas"]) ? $datos["preguntas"] : array();

    if ($titulo == "") {
        fallar("La encuesta necesita un título.");
    }
    if (count($preguntas) == 0) {
        fallar("Agregue al menos una pregunta.");
    }

    // primero se revisa todo, y recién después se guarda
    foreach ($preguntas as $p) {
        $texto = isset($p["texto"]) ? trim($p["texto"]) : "";
        $tipo = isset($p["tipo"]) ? $p["tipo"] : "";
        if ($texto == "" || ($tipo != "cerrada" && $tipo != "abierta")) {
            fallar("Revise que todas las preguntas tengan texto y tipo.");
        }
        if ($tipo == "cerrada") {
            $opciones = isset($p["opciones"]) && is_array($p["opciones"]) ? $p["opciones"] : array();
            $cuantas = 0;
            foreach ($opciones as $o) {
                if (trim($o) != "") {
                    $cuantas++;
                }
            }
            if ($cuantas < 2) {
                fallar("Cada pregunta cerrada necesita al menos dos opciones.");
            }
        }
    }

    $id_encuesta = ejecutar("INSERT INTO encuestas (titulo, descripcion, estado, fecha_creacion) VALUES (?, ?, 'Activa', NOW())",
                            "ss", array($titulo, $descripcion));

    foreach ($preguntas as $p) {
        $id_pregunta = ejecutar("INSERT INTO preguntas (id_encuesta, texto, tipo) VALUES (?, ?, ?)",
                                "iss", array($id_encuesta, trim($p["texto"]), $p["tipo"]));
        if ($p["tipo"] == "cerrada") {
            foreach ($p["opciones"] as $o) {
                if (trim($o) != "") {
                    ejecutar("INSERT INTO opciones (id_pregunta, texto) VALUES (?, ?)", "is", array($id_pregunta, trim($o)));
                }
            }
        }
    }
    responder(array("mensaje" => "Encuesta creada correctamente."));
}

if ($accion == "encuesta_estado") {
    pedir_rol($roles);
    $datos = leer_body();
    $id = isset($datos["id"]) ? (int) $datos["id"] : 0;
    ejecutar("UPDATE encuestas SET estado = IF(estado = 'Activa', 'Inactiva', 'Activa') WHERE id_encuesta = ?", "i", array($id));
    responder(array("mensaje" => "Estado de la encuesta actualizado."));
}

if ($accion == "encuesta_eliminar") {
    pedir_rol($roles);
    $datos = leer_body();
    $id = isset($datos["id"]) ? (int) $datos["id"] : 0;
    // las preguntas, opciones y respuestas se borran solas (ON DELETE CASCADE)
    ejecutar("DELETE FROM encuestas WHERE id_encuesta = ?", "i", array($id));
    responder(array("mensaje" => "Encuesta eliminada."));
}

if ($accion == "encuesta_resultados") {
    pedir_rol($roles);
    $id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

    $encuesta = consultar("SELECT titulo FROM encuestas WHERE id_encuesta = ?", "i", array($id));
    if (count($encuesta) == 0) {
        fallar("La encuesta no existe.");
    }
    $total = contar("SELECT COUNT(*) AS total FROM respuestas WHERE id_encuesta = ?", "i", array($id));
    $preguntas = consultar("SELECT id_pregunta, texto, tipo FROM preguntas WHERE id_encuesta = ? ORDER BY id_pregunta", "i", array($id));

    foreach ($preguntas as $i => $p) {
        if ($p["tipo"] == "cerrada") {
            $preguntas[$i]["opciones"] = consultar(
                "SELECT o.texto, COUNT(rd.id_detalle) AS veces
                 FROM opciones o
                 LEFT JOIN respuestas_detalle rd ON rd.id_opcion = o.id_opcion
                 WHERE o.id_pregunta = ?
                 GROUP BY o.id_opcion, o.texto
                 ORDER BY o.id_opcion", "i", array($p["id_pregunta"]));
        } else {
            $preguntas[$i]["comentarios"] = consultar(
                "SELECT texto_libre FROM respuestas_detalle
                 WHERE id_pregunta = ? AND texto_libre IS NOT NULL AND texto_libre <> ''
                 ORDER BY id_detalle", "i", array($p["id_pregunta"]));
        }
    }
    responder(array("titulo" => $encuesta[0]["titulo"], "total_respuestas" => $total, "preguntas" => $preguntas));
}


// ---------------- ENCUESTA (parte pública, paciente) ----------------

if ($accion == "encuesta_publica") {
    $filas = consultar("SELECT id_encuesta, titulo, descripcion FROM encuestas WHERE estado = 'Activa' ORDER BY id_encuesta DESC LIMIT 1");
    if (count($filas) == 0) {
        responder(array("encuesta" => null));
    }
    $encuesta = $filas[0];
    $preguntas = consultar("SELECT id_pregunta, texto, tipo FROM preguntas WHERE id_encuesta = ? ORDER BY id_pregunta",
                           "i", array($encuesta["id_encuesta"]));
    foreach ($preguntas as $i => $p) {
        $preguntas[$i]["opciones"] = array();
        if ($p["tipo"] == "cerrada") {
            $preguntas[$i]["opciones"] = consultar("SELECT id_opcion, texto FROM opciones WHERE id_pregunta = ? ORDER BY id_opcion",
                                                   "i", array($p["id_pregunta"]));
        }
    }
    $encuesta["preguntas"] = $preguntas;
    responder(array("encuesta" => $encuesta));
}

if ($accion == "responder") {
    $datos = leer_body();
    $id_encuesta = isset($datos["id_encuesta"]) ? (int) $datos["id_encuesta"] : 0;
    $respuestas = isset($datos["respuestas"]) && is_array($datos["respuestas"]) ? $datos["respuestas"] : array();

    if (contar("SELECT COUNT(*) AS total FROM encuestas WHERE id_encuesta = ? AND estado = 'Activa'", "i", array($id_encuesta)) == 0) {
        fallar("Esta encuesta ya no está disponible.");
    }

    // para no guardar cosas que no pertenecen a la encuesta
    $preguntas_validas = array();
    foreach (consultar("SELECT id_pregunta FROM preguntas WHERE id_encuesta = ?", "i", array($id_encuesta)) as $f) {
        $preguntas_validas[] = (int) $f["id_pregunta"];
    }
    $opciones_validas = array(); // id_opcion => id_pregunta
    foreach (consultar("SELECT o.id_opcion, o.id_pregunta FROM opciones o
                        JOIN preguntas p ON p.id_pregunta = o.id_pregunta
                        WHERE p.id_encuesta = ?", "i", array($id_encuesta)) as $f) {
        $opciones_validas[(int) $f["id_opcion"]] = (int) $f["id_pregunta"];
    }

    $para_guardar = array();
    foreach ($respuestas as $r) {
        $id_pregunta = isset($r["id_pregunta"]) ? (int) $r["id_pregunta"] : 0;
        $id_opcion = isset($r["id_opcion"]) && $r["id_opcion"] != "" ? (int) $r["id_opcion"] : 0;
        $texto = isset($r["texto_libre"]) ? trim($r["texto_libre"]) : "";

        if (!in_array($id_pregunta, $preguntas_validas)) {
            continue;
        }
        if ($id_opcion > 0 && (!isset($opciones_validas[$id_opcion]) || $opciones_validas[$id_opcion] != $id_pregunta)) {
            continue;
        }
        if ($id_opcion == 0 && $texto == "") {
            continue; // no contestó esta pregunta
        }
        $para_guardar[] = array($id_pregunta, $id_opcion, mb_substr($texto, 0, 500));
    }

    if (count($para_guardar) == 0) {
        fallar("Responda al menos una pregunta.");
    }

    $id_respuesta = ejecutar("INSERT INTO respuestas (id_encuesta, fecha_hora) VALUES (?, NOW())", "i", array($id_encuesta));
    foreach ($para_guardar as $g) {
        if ($g[1] > 0) {
            ejecutar("INSERT INTO respuestas_detalle (id_respuesta, id_pregunta, id_opcion) VALUES (?, ?, ?)",
                     "iii", array($id_respuesta, $g[0], $g[1]));
        } else {
            ejecutar("INSERT INTO respuestas_detalle (id_respuesta, id_pregunta, texto_libre) VALUES (?, ?, ?)",
                     "iis", array($id_respuesta, $g[0], $g[2]));
        }
    }
    responder(array("mensaje" => "Gracias por responder la encuesta."));
}

fallar("Acción no reconocida.");
