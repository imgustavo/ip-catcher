<?php
// =============================================
// SCRIPT DE REGISTRO MEJORADO DE VISITAS (v3.0)
// Funcionalidades:
// 1. Geolocalización (país, ciudad, ISP)
// 2. Detección de VPN/Proxy
// 3. Hora local Argentina + hora visitante
// 4. 50 idiomas más hablados
// 5. Datos técnicos (SO, navegador, dispositivo)
// =============================================

// --- Configuración inicial --- //
date_default_timezone_set('America/Argentina/Buenos_Aires');
$hora_argentina = date('Y-m-d H:i:s') . " (GMT-3)";
$archivo_log = 'visitas.log';

// --- Funciones principales --- //

/**
 * Obtiene la IP real del visitante (soporta proxies)
 */
function obtenerIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

/**
 * Consulta la API de geolocalización
 */
function obtenerGeoData($ip) {
    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,regionName,city,isp,timezone,proxy,org");
    return ($response && ($data = json_decode($response)) && $data->status === 'success' ? $data : null;
}

/**
 * Obtiene la hora local del visitante
 */
function obtenerHoraVisitante($timezone) {
    if (!$timezone) return 'Desconocido';
    try {
        $fecha = new DateTime('now', new DateTimeZone($timezone));
        return $fecha->format('Y-m-d H:i:s') . " (" . $fecha->format('P') . ")";
    } catch (Exception $e) {
        return 'Desconocido';
    }
}

/**
 * Detecta el idioma del navegador (50 idiomas soportados)
 */
function obtenerIdiomaNavegador() {
    $idiomas_aceptados = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (empty($idiomas_aceptados)) return 'Desconocido';
    
    preg_match('/^([a-z]{2})/i', $idiomas_aceptados, $matches);
    $codigo_idioma = strtoupper($matches[1] ?? '');
    
    // Los 50 idiomas más hablados (ISO 639-1) + cobertura digital
    $traducciones = [
        'ZH' => 'Chino Mandarín', 'ES' => 'Español', 'EN' => 'Inglés', 'HI' => 'Hindi', 'AR' => 'Árabe',
        'BN' => 'Bengalí', 'PT' => 'Portugués', 'RU' => 'Ruso', 'JA' => 'Japonés', 'PA' => 'Punjabi',
        'DE' => 'Alemán', 'JV' => 'Javanés', 'FR' => 'Francés', 'TR' => 'Turco', 'VI' => 'Vietnamita',
        'KO' => 'Coreano', 'IT' => 'Italiano', 'TH' => 'Tailandés', 'GU' => 'Gujarati', 'FA' => 'Persa',
        'PL' => 'Polaco', 'UK' => 'Ucraniano', 'ML' => 'Malayalam', 'KN' => 'Canarés', 'MR' => 'Maratí',
        'TE' => 'Télugu', 'OR' => 'Oriya', 'TA' => 'Tamil', 'MY' => 'Birmano', 'UR' => 'Urdu',
        'NL' => 'Neerlandés', 'RO' => 'Rumano', 'HU' => 'Húngaro', 'CS' => 'Checo', 'EL' => 'Griego',
        'SV' => 'Sueco', 'FI' => 'Finés', 'DA' => 'Danés', 'SK' => 'Eslovaco', 'HE' => 'Hebreo',
        'ID' => 'Indonesio', 'MS' => 'Malayo', 'TL' => 'Tagalo', 'NE' => 'Nepalí', 'SI' => 'Cingalés',
        'KM' => 'Jemer', 'LO' => 'Lao', 'KA' => 'Georgiano', 'HY' => 'Armenio', 'ET' => 'Estonio',
        'LV' => 'Letón', 'LT' => 'Lituano', 'CY' => 'Galés', 'EU' => 'Euskera', 'GL' => 'Gallego'
    ];
    
    return $traducciones[$codigo_idioma] ?? $codigo_idioma;
}

/**
 * Detecta el sistema operativo
 */
function obtenerSO() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $sistemas = [
        '/windows nt 11/i' => 'Windows 11',
        '/windows nt 10/i' => 'Windows 10',
        '/windows nt 6.3/i' => 'Windows 8.1',
        '/macintosh|mac os x/i' => 'macOS',
        '/linux/i' => 'Linux',
        '/iphone|ipod|ipad/i' => 'iOS',
        '/android/i' => 'Android',
        '/chromeos/i' => 'Chrome OS'
    ];
    foreach ($sistemas as $regex => $so) {
        if (preg_match($regex, $user_agent)) return $so;
    }
    return 'Desconocido';
}

/**
 * Detecta el navegador web
 */
function obtenerNavegador() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $navegadores = [
        '/chrome|chromium|crios/i' => 'Chrome',
        '/firefox|fxios/i' => 'Firefox',
        '/safari/i' => 'Safari',
        '/edge|edg/i' => 'Edge',
        '/opera|opr/i' => 'Opera',
        '/brave/i' => 'Brave',
        '/vivaldi/i' => 'Vivaldi',
        '/samsungbrowser/i' => 'Samsung Browser'
    ];
    foreach ($navegadores as $regex => $nav) {
        if (preg_match($regex, $user_agent)) return $nav;
    }
    return 'Desconocido';
}

/**
 * Detecta el tipo de dispositivo
 */
function obtenerDispositivo() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone/i', $user_agent)) {
        return 'Móvil';
    } elseif (preg_match('/Tablet|iPad|Kindle|Nexus 7|Xoom/i', $user_agent)) {
        return 'Tablet';
    } elseif (preg_match('/TV|SmartTV|AppleTV|Chromecast|Roku/i', $user_agent)) {
        return 'Smart TV';
    }
    return 'Escritorio';
}

// --- Procesamiento de datos --- //
$ip = obtenerIP();
$geoData = obtenerGeoData($ip);
$hora_visitante = obtenerHoraVisitante($geoData->timezone ?? null);
$usa_vpn = ($geoData->proxy ?? false) ? 'Sí' : 'No';
$idioma = obtenerIdiomaNavegador();
$dispositivo = obtenerDispositivo();

// --- Formato del registro --- //
$log_entry = <<<LOG
[Registro de Visita]
Fecha en Argentina: {$hora_argentina}
Fecha del Visitante: {$hora_visitante}
IP: {$ip} | VPN/Proxy: {$usa_vpn} | Idioma: {$idioma}
Ubicación: {$geoData->country ?? 'Desconocido'} ({$geoData->countryCode ?? '??'}) | {$geoData->regionName ?? 'N/A'}, {$geoData->city ?? 'N/A'}
ISP: {$geoData->isp ?? 'Desconocido'} | Organización: {$geoData->org ?? 'N/A'}
Sistema: {obtenerSO()} | Navegador: {obtenerNavegador()} | Dispositivo: {$dispositivo}
User-Agent: {$_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'}
URL: {$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}
Referencia: {$_SERVER['HTTP_REFERER'] ?? 'Directo/Buscador'}
==============================
LOG;

// --- Almacenamiento --- //
file_put_contents($archivo_log, $log_entry . PHP_EOL, FILE_APPEND);

// --- Opcional: Mostrar datos en pantalla (modo debug) --- //
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== DATOS RECOLECTADOS ===\n{$log_entry}";
    exit;
}
?>