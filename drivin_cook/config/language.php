<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$supported_languages = ['fr', 'en', 'es'];

$default_language = 'fr';

function setLanguage($lang) {
    global $supported_languages, $default_language;
    
    if (in_array($lang, $supported_languages)) {
        $_SESSION['language'] = $lang;
        return true;
    }
    
    $_SESSION['language'] = $default_language;
    return false;
}

function getCurrentLanguage() {
    global $default_language, $supported_languages;
    
    if (isset($_GET['lang']) && in_array($_GET['lang'], $supported_languages)) {
        setLanguage($_GET['lang']);
    } elseif (!isset($_SESSION['language'])) {

        $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2);
        if (in_array($browser_lang, $supported_languages)) {
            setLanguage($browser_lang);
        } else {
            setLanguage($default_language);
        }
    }
    
    return $_SESSION['language'] ?? $default_language;
}

function loadTranslations($lang = null) {
    global $default_language;
    
    if (!$lang) {
        $lang = getCurrentLanguage();
    }
    
    $translation_file = __DIR__ . "/../languages/{$lang}.php";
    
    if (file_exists($translation_file)) {
        return include $translation_file;
    }
    
    return include __DIR__ . "/../languages/{$default_language}.php";
}

$translations = loadTranslations();

function t($key, $default = '') {
    global $translations;
    
    return $translations[$key] ?? $default ?: $key;
}

function tf($key, $params = [], $default = '') {
    $translation = t($key, $default);
    
    foreach ($params as $param => $value) {
        $translation = str_replace("{{$param}}", $value, $translation);
    }
    
    return $translation;
}

function langUrl($url, $lang = null) {
    if (!$lang) {
        $lang = getCurrentLanguage();
    }
    
    $separator = strpos($url, '?') !== false ? '&' : '?';
    
    return $url . $separator . 'lang=' . $lang;
}

function getLanguageName($lang) {
    $names = [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español'
    ];
    
    return $names[$lang] ?? $lang;
}

function getLanguageFlag($lang) {
    $flags = [
        'fr' => '🇫🇷',
        'en' => '🇬🇧',
        'es' => '🇪🇸'
    ];
    
    return $flags[$lang] ?? '🏳️';
}

function renderLanguageSelector($current_url = '') {
    global $supported_languages;
    $current_lang = getCurrentLanguage();
    
    if (!$current_url) {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        $current_url = preg_replace('/[?&]lang=[a-z]{2}/', '', $current_url);
    }
    
    echo '<div class="dropdown">';
    echo '<button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">';
    echo getLanguageFlag($current_lang) . ' ' . getLanguageName($current_lang);
    echo '</button>';
    echo '<ul class="dropdown-menu">';
    
    foreach ($supported_languages as $lang) {
        $active = $lang === $current_lang ? ' active' : '';
        echo '<li><a class="dropdown-item' . $active . '" href="' . langUrl($current_url, $lang) . '">';
        echo getLanguageFlag($lang) . ' ' . getLanguageName($lang);
        echo '</a></li>';
    }
    
    echo '</ul>';
    echo '</div>';
}

function getMenuTranslations($menu_data, $lang = null) {
    if (!$lang) {
        $lang = getCurrentLanguage();
    }
    
    $translations = [];
    
    foreach ($menu_data as $menu) {
        $translations[] = [
            'id' => $menu['id'],
            'name' => $menu["name_{$lang}"] ?? $menu['name_fr'],
            'description' => $menu["description_{$lang}"] ?? $menu['description_fr'],
            'price' => $menu['price'],
            'category' => $menu['category'],
            'image_url' => $menu['image_url'],
            'available' => $menu['available']
        ];
    }
    
    return $translations;
}

getCurrentLanguage();
?>