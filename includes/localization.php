<?php

const MYPARK_LANGUAGES = ['en' => 'English', 'zh-Hans' => '中文', 'ms' => 'Bahasa Melayu'];
const MYPARK_LANGUAGE_COOKIE = 'mypark_language';

function remember_selected_language(): void {
    $selected = $_GET['lang'] ?? null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !is_string($selected) || !isset(MYPARK_LANGUAGES[$selected])) {
        return;
    }

    setcookie(MYPARK_LANGUAGE_COOKIE, $selected, [
        'expires' => time() + 365 * 86400,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $query = $_GET;
    unset($query['lang']);
    // A query-only redirect stays on the current page and keeps its filters.
    header('Location: ?' . http_build_query($query), true, 303);
    exit;
}

function current_language(): string {
    $cookie = $_COOKIE[MYPARK_LANGUAGE_COOKIE] ?? 'en';
    return is_string($cookie) && isset(MYPARK_LANGUAGES[$cookie]) ? $cookie : 'en';
}

function translation_catalog(): array {
    static $messages = null;
    if ($messages !== null) return $messages;

    $language = current_language();
    $messages = [];
    if ($language !== 'en') {
        $json = @file_get_contents(__DIR__ . '/language/translations.json');
        $catalog = $json === false ? null : json_decode($json, true);
        if (!is_array($catalog)) return $messages;
        foreach ($catalog as $source => $translations) {
            if (isset($translations[$language])) $messages[strtolower($source)] = $translations[$language];
        }
    }
    return $messages;
}

function t(string $source, array $params = []): string {
    $message = translation_catalog()[strtolower($source)] ?? $source;
    $replacements = [];
    foreach ($params as $key => $value) $replacements['{' . $key . '}'] = (string)$value;
    return strtr($message, $replacements);
}

function te(string $source, array $params = []): string {
    return e(t($source, $params));
}

function language_switcher(): void {
    $selected = current_language();
    ?>
    <form class="language-switcher" method="get">
      <?php foreach ($_GET as $key => $value): ?>
        <?php if ($key !== 'lang' && is_string($value)): ?>
          <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      <details class="language-switcher__menu" data-language-switcher>
        <summary class="language-switcher__trigger" aria-label="<?= te('Language') ?>: <?= e(MYPARK_LANGUAGES[$selected]) ?>">
          <svg class="language-switcher__globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
            <circle cx="12" cy="12" r="9"></circle>
            <ellipse cx="12" cy="12" rx="4" ry="9"></ellipse>
            <path d="M3 12h18"></path>
          </svg>
          <span lang="<?= e($selected) ?>"><?= e(MYPARK_LANGUAGES[$selected]) ?></span>
          <svg class="language-switcher__chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
            <path d="m4 6 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"></path>
          </svg>
        </summary>
        <ul class="language-switcher__options">
          <?php foreach (MYPARK_LANGUAGES as $code => $label): ?>
            <li><button class="language-switcher__option" type="submit" name="lang" value="<?= e($code) ?>" lang="<?= e($code) ?>"<?= $selected === $code ? ' aria-current="true"' : '' ?>><?= e($label) ?></button></li>
          <?php endforeach; ?>
        </ul>
      </details>
    </form>
    <?php
}

function locale_client_scripts(): void {
    $messages = (object)translation_catalog();
    echo '<script type="application/json" id="mypark-translations">'
        . json_encode($messages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        . '</script><script src="assets/js/localization.js"></script>';
}

function localized_flash(string|array $message): void {
    $title = '';
    $type = null;
    if (is_array($message)) {
        $title = $message['title'] ?? '';
        $type = $message['type'] ?? null;
        $message = $message['message'];
    }
    if ($message === '') return;
    if ($type === null) {
        $failure = preg_match('/not found|must be|enter a valid|use jpg|invalid|failed|cannot|can be cancelled|location is full/i', $message);
        $success = preg_match('/updated|created|reserved|cancelled|added|topped up|registered/i', $message);
        $type = $failure ? 'error' : ($success ? 'success' : 'info');
    }
    echo '<div class="flash" data-toast-type="' . e($type) . '"'
        . ($title === '' ? '' : ' data-toast-title="' . te($title) . '"') . '>' . te($message) . '</div>';
}
