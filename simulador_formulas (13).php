<?php
// Configuración de errores y logs
ini_set('display_errors', 0);  // Evitar mostrar errores en la salida
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/home/u797446403/public_html/error_log');

// Asegurar que la respuesta sea JSON
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error) {
        error_log("Error final detectado: " . json_encode($error));
        if (in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            http_response_code(500);
            echo json_encode([
                "error" => "Error interno del servidor",
                "mensaje" => "Se produjo un error al procesar la solicitud."
            ]);
            exit;
        }
    }
});

try {
    // Manejo de sesión
    session_start();
    if (!isset($_SESSION['initialized'])) {
        session_regenerate_id(true);
        $_SESSION['initialized'] = true;
    }

    // Logs iniciales
    error_log("Inicio del archivo simulador_formulas.php");
    error_log("Backend iniciado correctamente.");

    // Conexión a la base de datos
    require_once 'db.php';
    if (!$pdo) {
        throw new Exception("Conexión a la base de datos no inicializada.");
    }

    error_log("Conexión a la base de datos exitosa.");

} catch (Exception $e) {
    error_log("Error inicial: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Error interno del servidor."]);
    exit();
}

 // Escalar ingredientes y ajustar rangos en función de la cantidad de agua
    $cantidadAgua = isset($data['agua']) ? (float)$data['agua'] : 1.0; // Por defecto, 1 litro si no se especifica
    $rangosBase = [
        "Dawn Ultra" => [25, 35],
        "Glicerina" => [40, 60],
        "Goma guar" => [1.5, 2.1],
        "Polvo para hornear" => [1.8, 2.7],
        "J LUBE" => [0.5, 2],
        "Gel fijador de pelo" => [1, 3],
        "Metilcelulosa" => [0.5, 1.5],
        "Goma xantana" => [0.3, 1],
        "Surgilube" => [0.5, 1.5],
        "K-Y lubricante" => [0.5, 1],
        "Azúcar" => [5, 15],
        "Jarabe de maíz" => [10, 20],
        "Gel de linaza" => [5, 10],
        "Alcohol Polivinílico (PVA)" => [0.5, 2]
    ];


$rangosAjustados = [];
foreach ($rangosBase as $ingrediente => [$min, $max]) {
    if (!isset($min) || !isset($max)) {
        error_log("Error: Rangos no definidos para el ingrediente '$ingrediente'.");
        continue; // Omite este ingrediente
    }
    $rangosAjustados[$ingrediente] = [$min * $cantidadAgua, $max * $cantidadAgua];
}


    error_log("Rangos ajustados calculados: " . json_encode($rangosAjustados));

function calcularRangosIngredientes($formulas, $cantidadAgua) {
    $rangos = [];
    foreach ($formulas as $formula) {
        $rangoIngredientesFormula = json_decode($formula['rango_ingredientes'], true) ?? [];
        foreach ($rangoIngredientesFormula as $ingrediente => $rango) {
            $rangos[$ingrediente] = [
                $rango[0] * $cantidadAgua,
                $rango[1] * $cantidadAgua
            ];
        }
    }
    return $rangos;
}

function decodificarJson($json, $contexto = '') {
    if (!empty($json) && is_string($json)) {
        $resultado = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $resultado;
        } else {
            error_log("Error al decodificar JSON ($contexto): " . json_last_error_msg());
        }
    }
    return []; // Retorna un array vacío en caso de error o valor vacío
}

// Función para responder en formato JSON
function responder($codigo, $mensaje, $data = []) {
    http_response_code($codigo);
    echo json_encode(array_merge(
        [
            'status' => $codigo >= 200 && $codigo < 300 ? 'success' : 'error',
            'mensaje' => $mensaje
        ],
        $data
    ), JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    exit();
}

// Validar sesión activa
if (!isset($_SESSION['id_usuario'])) {
    responder(401, "Sesión no iniciada. Por favor, inicia sesión para continuar.");
}

try {
    // Validar usuario en la base de datos
    $id_usuario = $_SESSION['id_usuario'];
    $stmt = $pdo->prepare("SELECT tipo_usuario FROM Usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        responder(404, "Usuario no encontrado.");
    }

    $tipo_usuario = $usuario['tipo_usuario'];
    error_log("Usuario validado: ID = $id_usuario, Tipo = $tipo_usuario");

    // Procesar entrada
$data_raw = file_get_contents("php://input");
if (!$data_raw) {
    responder(400, "No se recibieron datos.");
}

// Decodificar JSON
$data = json_decode($data_raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    responder(400, "Formato JSON inválido: " . json_last_error_msg());
}

// Validar que $data sea un array
if (!is_array($data)) {
    responder(400, "La entrada no es válida.");
}

// Validar cantidad de agua
$cantidadAgua = $data['agua'] ?? 1; // Valor por defecto
if ($cantidadAgua < 1 || $cantidadAgua > 10) {
    responder(400, "La cantidad de agua debe estar entre 1 y 10 litros.");
}
error_log("Cantidad de agua validada: $cantidadAgua litros");

// Obtener el objetivo
$objetivosValidos = ['gigantes', 'duraderas', 'resistentes'];
$objetivo = $data['objetivo'] ?? 'gigantes';
if (!in_array($objetivo, $objetivosValidos)) {
    $objetivo = 'gigantes';
}
error_log("Objetivo procesado: $objetivo");

// Agregar "Agua" como ingrediente predeterminado
$data['agua'] = $data['agua'] ?? 1; // 1 litro por defecto si no se especifica

// Mapeo de ingredientes
$mapaIngredientes = [
    "lavavajillas" => "Dawn Ultra",
    "glicerina" => "Glicerina",
    "gomaGuar" => "Goma guar",
    "polvoParaHornear" => "Polvo para hornear",
    "jLube" => "J LUBE",
    "gelFijador" => "Gel fijador de pelo",
    "metilcelulosa" => "Metilcelulosa",
    "gomaXantana" => "Goma xantana",
    "surgilube" => "Surgilube",
    "kylubricante" => "K-Y lubricante",
    "azucar" => "Azúcar",
    "jarabeMaiz" => "Jarabe de maíz",
    "gelLinaza" => "Gel de linaza",
    "alcoholPVA" => "Alcohol Polivinílico (PVA)",
    "agua" => "Agua"
];


    // Transformar y filtrar ingredientes
    $dataTransformada = [];
    foreach ($data as $clave => $valor) {
    if ($valor >= 0) { // Cambiar la condición para incluir el agua
        $dataTransformada[$mapaIngredientes[$clave] ?? $clave] = $valor;
    }
}

    foreach ($dataTransformada as $ingrediente => &$cantidad) {
    $cantidad = floatval($cantidad);
    $cantidad *= $cantidadAgua;
}
unset($cantidad);


    error_log("Ingredientes transformados y escalados: " . json_encode($dataTransformada));

   
    
    
    // No escalar los ingredientes aquí. La comparación se realizará directamente contra los rangos ajustados.

    error_log("Ingredientes transformados y escalados: " . json_encode($dataTransformada));

    unset($cantidad); // Evitar referencias residuales


    // Excluir "objetivo" antes de continuar
    unset($dataTransformada['objetivo']); // Asegurar que "objetivo" no interfiera

    // Buscar fórmulas en la base de datos según el objetivo seleccionado
    $stmt = $pdo->prepare("SELECT * FROM Formulas WHERE tipo = :tipo");
    $stmt->bindParam(':tipo', $objetivo, PDO::PARAM_STR);
    $stmt->execute();
    $formulas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$formulas) {
        error_log("No se encontraron fórmulas para el objetivo '$objetivo'.");
        responder(404, "No se encontraron fórmulas para el objetivo seleccionado.");
    }

    error_log("Fórmulas encontradas para el objetivo '$objetivo': " . json_encode($formulas));
    



    // Inicializar variables para la simulación
    $esPerfecta = false;
    $formulaCoincidente = null;
    $mejorResultado = null;

    /**
     * Verifica si una fórmula es perfecta según las métricas calculadas y los ingredientes ideales.
     *
     * @param array $metricas Métricas calculadas (elasticidad, duracion, resistencia).
     * @param array $pesos Pesos asignados a los ingredientes para cada métrica.
     * @param array $ingredientesUsuario Ingredientes proporcionados por el usuario.
     * @param array $ingredientesIdeales Valores ideales de los ingredientes para la fórmula.
     * @return bool Devuelve true si la fórmula es perfecta, de lo contrario false.
     */
    function esFormulaPerfecta($metricas, $pesos, $ingredientesUsuario, $ingredientesIdeales) {
        // Criterio: Todas las métricas deben ser igual o mayores al 90%
        foreach ($metricas as $metrica => $valor) {
            if ($valor < 90) {
                return false; // No es perfecta si alguna métrica es menor a 90
            }
        }

        // Verificar que los ingredientes estén dentro de los rangos ideales
        foreach ($ingredientesIdeales as $ingrediente => $valorIdeal) {
            $valorUsuario = $ingredientesUsuario[$ingrediente] ?? null;
            if ($valorUsuario === null || $valorUsuario != $valorIdeal) {
                return false; // No es perfecta si algún ingrediente no coincide con el ideal
            }
        }

        return true; // Es perfecta si pasa todos los criterios
    }

    /**
     * Normaliza una métrica para que esté dentro del rango de 0 a 100.
     * 
     * @param float $valor Valor de la métrica que se desea normalizar.
     * @return float Valor normalizado entre 0 y 100.
     */
    function normalizarMetrica($valor) {
        // Garantizar que el valor esté dentro del rango esperado
        return max(0, min(100, $valor));
    }

    /**
     * Calcula el impacto de un ingrediente basado en su valor actual, el valor ideal y su rango permitido.
     * 
     * @param float $valorUsuario Valor proporcionado por el usuario para el ingrediente.
     * @param float $valorIdeal Valor ideal del ingrediente.
     * @param float $minRango Valor mínimo permitido para el ingrediente.
     * @param float $maxRango Valor máximo permitido para el ingrediente.
     * @return float Impacto calculado (entre 0 y 1, donde 0 es el ideal).
     */
    function calcularImpacto($valorUsuario, $valorIdeal, $minRango, $maxRango) {
    // Validar que los rangos sean válidos
    if ($minRango > $maxRango) {
        error_log("Error: Rango mínimo ($minRango) mayor que el máximo ($maxRango) para un ingrediente.");
        throw new Exception("Los rangos para este ingrediente no son válidos.");
    }

    // Si el valor está dentro del rango ajustado, no hay penalización
    if ($valorUsuario >= $minRango && $valorUsuario <= $maxRango) {
        return 0; // Sin penalización
    }

    // Penalización proporcional según la desviación
    if ($valorUsuario < $minRango) {
        return ($minRango - $valorUsuario) / max($valorIdeal - $minRango, 0.1);
    } elseif ($valorUsuario > $maxRango) {
        return ($valorUsuario - $maxRango) / max($maxRango - $valorIdeal, 0.1);
    }

    return 1; // Penalización máxima si todo falla
}


    // Función para simular una fórmula
    function simularFormula($ingredientesUsuario, $formulaBase, $pesos, $rangosAjustados) {
        error_log("Llamando a simularFormula con ingredientes: " . json_encode($ingredientesUsuario));
        error_log("Usando fórmula base: " . json_encode($formulaBase));
        error_log("Pesos de la fórmula: " . json_encode($pesos));
        error_log("Rangos ajustados: " . json_encode($rangosAjustados));
        
        $resultados = ['elasticidad' => 50, 'duracion' => 50, 'resistencia' => 50]; // Valor base neutro para iniciar las métricas

        $excelenciaIngredientes = json_decode($formulaBase['excelencia_ingredientes'], true) ?? [];

        foreach ($excelenciaIngredientes as $ingrediente => $valorIdeal) {
    $valorUsuario = $ingredientesUsuario[$ingrediente] ?? null;

    if ($valorUsuario === null) {
        error_log("Ingrediente $ingrediente no proporcionado por el usuario. Aplicando penalización.");
        foreach ($resultados as &$valor) {
            $valor = max(0, $valor - 10); // Asegura que la métrica no baje de 0
        }
        continue;
    }


        $rango = $rangosAjustados[$ingrediente] ?? [0, PHP_INT_MAX];
        $impacto = calcularImpacto($valorUsuario, $valorIdeal, $rango[0], $rango[1]);
        
        // Log del impacto calculado
        error_log("Impacto del ingrediente $ingrediente: $impacto");

        foreach ($resultados as $metrica => &$valor) {
    $peso = $pesos[$ingrediente][$metrica] ?? 0;
    $valor += $peso * (1 - $impacto);
    
    // Log de métricas parciales
    error_log("Métrica $metrica ajustada por $ingrediente: $valor");
}

    // Obtener rangos de ingredientes
// Obtener rangos de ingredientes
    $rangosIngredientes = calcularRangosIngredientes($formulas, $cantidadAgua);

    // Normalizar las métricas al rango 0-100
    $resultadosNormalizados = [];
    foreach ($resultados as $metrica => $valor) {
        $maxValor = array_sum(array_column($pesos, $metrica)); // Calcular el máximo posible para la métrica
        $resultadosNormalizados[$metrica] = normalizarMetrica($valor / $maxValor * 100);
    }

    error_log("Resultados de métricas finales en simularFormula: " . json_encode($resultadosNormalizados));

    return $resultadosNormalizados;
}

    // Iterar sobre cada fórmula encontrada para evaluar
    error_log("Número de fórmulas a procesar: " . count($formulas));

    foreach ($formulas as $formula) {
    error_log("Iniciando evaluación para fórmula: " . $formula['nombre'] . ", con pesos: " . json_encode($formula['pesos']));
        
    $pesos = isset($formula['pesos']) ? decodificarJson($formula['pesos'], 'pesos') : [];
    if (empty($pesos)) {
        error_log("Advertencia: Los pesos no están definidos o están vacíos para la fórmula '{$formula['nombre']}'.");
    }

    $excelenciaIngredientes = decodificarJson($formula['excelencia_ingredientes'], 'excelencia_ingredientes');
    $rangoIngredientes = decodificarJson($formula['rango_ingredientes'], 'rango_ingredientes');

        $resultado = simularFormula($dataTransformada, $formula, $pesos, $rangosAjustados);

        // Registrar métricas calculadas
        error_log("Métricas calculadas para fórmula '" . $formula['nombre'] . "': " . json_encode($resultado));

        // Verificar si la fórmula es perfecta
        if (esFormulaPerfecta($resultado, $pesos, $dataTransformada, $excelenciaIngredientes)) {
            error_log("Fórmula perfecta encontrada: " . $formula['nombre']);
            $esPerfecta = true;
            $formulaCoincidente = $formula;
            break; // Salir del bucle al encontrar la fórmula perfecta
        }

        // Guardar el mejor resultado aproximado si no es perfecto
        if (!isset($mejorResultado) || $resultado['duracion'] > $mejorResultado['resultado']['duracion']) {
            $mejorResultado = [
                'formula' => $formula,
                'resultado' => $resultado
            ];
            error_log("Nuevo mejor resultado aproximado: " . json_encode($mejorResultado));
        }
    }

    // Respuesta en caso de fórmula perfecta
    if ($esPerfecta) {
        responder(200, "Fórmula perfecta encontrada.", [
            "nombre" => $formulaCoincidente['nombre'],
            "metricas" => $resultado,
            "detalles" => $formulaCoincidente
        ]);
    }

    function generarFeedback($ingredientesUsuario, $rangosAjustados) {
        $feedback = [];
        foreach ($ingredientesUsuario as $ingrediente => $valorUsuario) {
            if (isset($rangosAjustados[$ingrediente])) {
                [$minRango, $maxRango] = $rangosAjustados[$ingrediente];
                if ($valorUsuario < $minRango) {
                    $feedback[] = "Aumenta la cantidad de '$ingrediente' a al menos $minRango.";
                } elseif ($valorUsuario > $maxRango) {
                    $feedback[] = "Reduce la cantidad de '$ingrediente' a un máximo de $maxRango.";
                } else {
                    $feedback[] = "La cantidad de '$ingrediente' está dentro del rango ideal.";
                }
            } else {
                $feedback[] = "El ingrediente '$ingrediente' no tiene un rango definido.";
            }
        }
        return $feedback;
    }

    // Respuesta en caso de mejor aproximación
    if ($mejorResultado) {
        if ($tipo_usuario === 'premium') {
            error_log("Rangos ajustados utilizados para feedback: " . json_encode($rangosAjustados));
            error_log("Datos transformados utilizados para feedback: " . json_encode($dataTransformada));
            $feedback = generarFeedback($mejorResultado, $dataTransformada, $mapaIngredientes, $rangosAjustados);
        } else {
            $feedback = generarFeedbackAvanzado(
                $mejorResultado['resultado'], 
                $objetivo, 
                [
                    'gigantes' => ['elasticidad' => 80, 'duracion' => 70, 'resistencia' => 60],
                    'duraderas' => ['elasticidad' => 60, 'duracion' => 90, 'resistencia' => 70],
                    'resistentes' => ['elasticidad' => 70, 'duracion' => 70, 'resistencia' => 90]
                ]
            );
        }

        // Añadir el feedback sobre la cantidad de agua
        $cantidadAgua = $data['agua'] ?? 1; // Por defecto 1 litro
$feedback[] = "Las proporciones de los ingredientes se han ajustado para {$cantidadAgua} litros de agua.";

// Añadir feedback para usuarios gratuitos sobre la regla de la glicerina
if ($tipo_usuario !== 'premium') {
    $glicerinaIdeal = 50 * $cantidadAgua; // Gigantes
    if ($objetivo === 'duraderas') {
        $glicerinaIdeal = 100 * $cantidadAgua;
    } elseif ($objetivo === 'resistentes') {
        $glicerinaIdeal = 150 * $cantidadAgua;
    }
    $feedback[] = "Para burbujas $objetivo, se recomienda usar aproximadamente $glicerinaIdeal g de glicerina con {$cantidadAgua} litro(s) de agua.";
}

responder(200, "Simulación completada.", [
            "evaluacion" => "Buena.",
            "mejor_formula" => $mejorResultado['formula']['nombre'],
            "metricas" => $mejorResultado['resultado'],
            "feedback" => $feedback,
            "rangos_ajustados" => $rangosAjustados,
]);

    } else {
        responder(404, "No se encontró ninguna fórmula coincidente.");
    }

// Definir pesos de ingredientes para métricas (elasticidad, duración, resistencia)
$pesos = [
    "lavavajillas" => ["elasticidad" => 30, "duracion" => 10, "resistencia" => 20],
    "glicerina" => ["elasticidad" => 25, "duracion" => 30, "resistencia" => 10],
    "goma guar" => ["elasticidad" => 20, "duracion" => 10, "resistencia" => 25],
    "j lube" => ["elasticidad" => 20, "duracion" => 5, "resistencia" => 20],
    "gel fijador de pelo" => ["elasticidad" => 10, "duracion" => 10, "resistencia" => 15],
    "metilcelulosa" => ["elasticidad" => 5, "duracion" => 20, "resistencia" => 20],
    "goma xantana" => ["elasticidad" => 15, "duracion" => 10, "resistencia" => 30],
    "surgilube" => ["elasticidad" => 10, "duracion" => 15, "resistencia" => 10],
    "k-y lubricante" => ["elasticidad" => 5, "duracion" => 10, "resistencia" => 10],
    "azúcar" => ["elasticidad" => 10, "duracion" => 20, "resistencia" => 5],
    "jarabe de maíz" => ["elasticidad" => 10, "duracion" => 25, "resistencia" => 5],
    "gel de linaza" => ["elasticidad" => 10, "duracion" => 15, "resistencia" => 10],
    "alcohol polivinílico (pva)" => ["elasticidad" => 20, "duracion" => 20, "resistencia" => 15],
    "polvo para hornear" => ["elasticidad" => 5, "duracion" => 10, "resistencia" => 5]
];

// Optimización de cálculos en función de los pesos
function optimizarPesos($pesos) {
    foreach ($pesos as $ingrediente => $valores) {
        foreach ($valores as $metrica => $peso) {
            if ($peso > 100) {
                $pesos[$ingrediente][$metrica] = 100; // Normalizar si excede 100
            }
        }
    }
    return $pesos;
}

$pesos = optimizarPesos($pesos);

// Log de pesos ajustados
error_log("Pesos optimizados: " . json_encode($pesos));

// Manejo de errores en la base de datos
try {
    $stmt = $pdo->prepare("SELECT nombre, unidad, rango_ideal FROM Ingredientes");
    $stmt->execute();
    $ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$ingredientes) {
        throw new Exception("No se encontraron ingredientes en la base de datos.");
    }
} catch (Exception $e) {
    error_log("Error al obtener ingredientes: " . $e->getMessage());
    responder(500, "Error interno al cargar ingredientes.");
}

// Validaciones adicionales en los datos de entrada
function validarDatos($data, $mapaIngredientes) {
    foreach ($data as $clave => $valor) {
        // Validar que el valor sea numérico
        if (!is_numeric($valor)) {
            error_log("Error: El valor de $clave no es numérico: $valor");
            throw new Exception("El valor de $clave debe ser numérico.");
        }

        // Validar que el ingrediente exista en el mapa o sea 'agua'
        if (!array_key_exists($clave, $mapaIngredientes) && $clave !== 'agua') {
            error_log("Ingrediente no válido detectado: $clave");
            throw new Exception("Ingrediente no válido: $clave.");
        }

        // Validar que el valor no sea negativo
        if ($valor < 0) {
            error_log("Valor negativo detectado para el ingrediente $clave: $valor");
            throw new Exception("Valores negativos no permitidos en ingredientes: $clave.");
        }
    }
}

try {
    validarDatos($data, $mapaIngredientes);
} catch (Exception $e) {
    error_log("Error en los datos: " . $e->getMessage());
    responder(400, "Datos inválidos: " . $e->getMessage());
}

// Log final de datos validados
error_log("Datos validados correctamente: " . json_encode($data));

// Inicializar métricas y análisis
$metricas = ["elasticidad" => 0, "duracion" => 0, "resistencia" => 0];
$max_metricas = ["elasticidad" => 0, "duracion" => 0, "resistencia" => 0];
$sugerencias = [];

// Calcular métricas basadas en ingredientes proporcionados
foreach ($ingredientes as $ingrediente) {
    $nombre = strtolower($ingrediente['nombre']);
    $rango_ideal = json_decode($ingrediente['rango_ideal'], true);
    $nombreMapeado = array_search($nombre, array_map('strtolower', $mapaIngredientes)) ?: $nombre;

    if (isset($dataTransformada[$nombreMapeado])) {
        $cantidad = (float)$dataTransformada[$nombreMapeado];
        $pesos_ingrediente = $pesos[$nombreMapeado] ?? ["elasticidad" => 0, "duracion" => 0, "resistencia" => 0];

        // Ajustar métricas basadas en cantidades y pesos
        foreach ($metricas as $metrica => &$valor) {
            $incremento = $cantidad * ($pesos_ingrediente[$metrica] / 100);
            $valor += $incremento;
            $max_metricas[$metrica] += 100 * ($pesos_ingrediente[$metrica] / 100);
        }

        // Validar si el ingrediente está fuera de rango ideal
        if ($cantidad < $rango_ideal[0] || $cantidad > $rango_ideal[1]) {
            $sugerencias[] = "El ingrediente '$nombreMapeado' está fuera del rango ideal (Ideal: {$rango_ideal[0]} - {$rango_ideal[1]}).";
        }
    }
}
try {
// Normalizar métricas calculadas
foreach ($metricas as $metrica => &$valor) {
    if ($max_metricas[$metrica] > 0) {
        $valor = min(100, ($valor / $max_metricas[$metrica]) * 100);
    } else {
        $valor = 0; // Evitar métricas negativas o indefinidas
    }
}
unset($valor);

// Log de métricas normalizadas
error_log("Métricas calculadas normalizadas: " . json_encode($metricas));
error_log("Sugerencias generadas: " . json_encode($sugerencias));

// Preparar respuesta de métricas y sugerencias
$respuesta['metricas'] = $metricas;
$respuesta['sugerencias'] = $sugerencias;

// Log de validación final
error_log("Respuesta generada tras ajustar métricas: " . json_encode($respuesta));

// Respuesta de éxito: envía los datos procesados
responder(200, "Simulación completada.", [
    "metricas" => [
        "elasticidad" => 0, // O valores iniciales predeterminados
        "duracion" => 0,
        "resistencia" => 0
    ],
    "feedback" => ["Datos enviados correctamente, pero no se encontraron métricas específicas."],
    "detalles" => null
]);

} catch (Exception $e) {
    // Captura de cualquier error y respuesta en consecuencia
    error_log("Error en la ejecución: " . $e->getMessage());
    responder(500, "Error interno del servidor.");
}

// Generar feedback avanzado basado en métricas y objetivos ideales
function generarFeedbackAvanzado($metricas, $objetivo, $valoresIdeales) {
    $feedback = [];
    $mensajes = [
        'elasticidad' => "Incrementa elasticidad ajustando ingredientes específicos.",
        'duracion' => "Incrementa duración revisando las proporciones de Glicerina o Azúcar.",
        'resistencia' => "Mejora resistencia utilizando más Polvo para hornear o Goma xantana."
    ];

    foreach ($mensajes as $metrica => $mensaje) {
        if ($metricas[$metrica] < ($valoresIdeales[$objetivo][$metrica] ?? 0)) {
            $feedback[] = $mensaje;
        }
    }

    return $feedback;
}

// Valores ideales de métricas para cada objetivo
$valoresIdeales = [
    'gigantes' => ['elasticidad' => 80, 'duracion' => 70, 'resistencia' => 60],
    'duraderas' => ['elasticidad' => 60, 'duracion' => 90, 'resistencia' => 70],
    'resistentes' => ['elasticidad' => 70, 'duracion' => 70, 'resistencia' => 90]
];

// Generar feedback general basado en métricas calculadas
$feedbackGeneral = generarFeedbackAvanzado($metricas, $objetivo, $valoresIdeales);
error_log("Feedback general generado: " . json_encode($feedbackGeneral));

// Respuesta diferenciada para usuarios premium y gratuitos
if ($tipo_usuario === 'premium') {
    $respuesta['detalles'] = $mejorResultado['formula'] ?? [];
    $respuesta['sugerencias'] = $mejorResultado['resultado']['sugerencias'] ?? [];
    $respuesta['feedback'] = [
        "general" => implode(' ', $feedbackGeneral),
        "detallado" => $mejorResultado['resultado']['notas_generales'] ?? []
    ];
} else {
    $respuesta['detalles'] = "Actualiza a premium para ver detalles avanzados.";
    $respuesta['feedback'] = "Actualiza a premium para acceder a recomendaciones personalizadas.";
}

// Log de respuesta final
error_log("Respuesta final generada para usuario ($tipo_usuario): " . json_encode($respuesta));

// Enviar respuesta final al cliente
responder(200, "Simulación completada.", $respuesta);


// Validar ingredientes transformados y generar feedback
function validarIngredientes($dataTransformada, $mapaIngredientes, $pesos) {
    $feedback = [];
    foreach ($dataTransformada as $ingrediente => $valor) {
        if (!isset($mapaIngredientes[$ingrediente]) && !isset($pesos[$ingrediente])) {
            $feedback[] = "El ingrediente '$ingrediente' no está reconocido. Considera eliminarlo.";
        } elseif ($valor <= 0) {
            $feedback[] = "El valor del ingrediente '$ingrediente' debe ser mayor a 0.";
        }
    }
    return $feedback;
}

// Generar feedback para el usuario
$feedbackIngredientes = validarIngredientes($dataTransformada, $mapaIngredientes, $pesos);
if (!empty($feedbackIngredientes)) {
    $respuesta['feedback']['ingredientes'] = $feedbackIngredientes;
    error_log("Feedback sobre ingredientes: " . json_encode($feedbackIngredientes));
}

// Ajustar respuesta final con validaciones
$respuesta['validaciones'] = [
    "ingredientes" => $feedbackIngredientes
];

// Manejo de errores generales
function manejarError($codigo, $mensaje) {
    error_log("Error detectado: $mensaje");
    http_response_code($codigo);
    echo json_encode(["error" => $mensaje]);
    exit();
}

// Verificar si el resultado es válido antes de enviarlo
if (empty($respuesta)) {
    error_log("Error: la variable 'respuesta' está vacía. Verifica las funciones previas.");
    manejarError(500, "No se pudo generar una respuesta válida.");
}

// Log final y envío de respuesta
error_log("Respuesta final completa: " . json_encode($respuesta));
echo json_encode($respuesta);
exit();

?>

