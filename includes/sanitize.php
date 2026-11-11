<?php
/**
 * Cleans rich-text HTML with an allow-list (HTML Purifier, in /lib).
 * Only simple formatting survives: no scripts, styles, event handlers,
 * iframes or external images.
 */
declare(strict_types=1);

function clean_html(string $html): string
{
    static $purifier = null;
    if ($purifier === null) {
        require_once ROOT_PATH . '/lib/htmlpurifier/library/HTMLPurifier.auto.php';
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed',
            'p,br,strong,b,em,i,u,s,h2,h3,h4,ul,ol,li,blockquote,hr,'
            . 'a[href|title],img[src|alt|width|height]');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        // Images may only come from this website (the Media Library).
        $config->set('URI.DisableExternalResources', true);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);
        $config->set('Attr.AllowedFrameTargets', []);

        // Cache the purifier's settings in private/cache when possible (faster).
        $cacheDir = PRIVATE_PATH . '/cache/htmlpurifier';
        if (is_dir($cacheDir) || @mkdir($cacheDir, 0700, true)) {
            $config->set('Cache.SerializerPath', $cacheDir);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }
        $purifier = new HTMLPurifier($config);
    }
    $clean = trim($purifier->purify($html));
    // An editor that was emptied leaves "<p><br></p>" etc. — treat as empty.
    return trim(strip_tags($clean, '<img><hr>')) === '' ? '' : $clean;
}
