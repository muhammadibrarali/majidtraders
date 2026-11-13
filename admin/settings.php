<?php
/** Site Settings — /admin/settings?tab=status|company|social|hero|home|counters|seo */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/auth.php';
require ROOT_PATH . '/includes/admin_layout.php';
require ROOT_PATH . '/includes/admin_form.php';
require ROOT_PATH . '/includes/settings.php';

$admin = require_admin();

$tabs = [
    'status'   => 'Website status',
    'company'  => 'Company',
    'social'   => 'Social links',
    'hero'     => 'Home page hero',
    'home'     => 'Home page sections',
    'counters' => 'Counters',
    'seo'      => 'SEO defaults',
];
// Editable home page section texts: key => [label, max length, multi-line?]
$homeTexts = [
    'home_clients_title'        => ['Client logos — heading', 120, false],
    'home_about_eyebrow'        => ['About — small orange label', 40, false],
    'home_about_heading'        => ['About — heading', 140, false],
    'home_services_eyebrow'     => ['Services — small orange label', 40, false],
    'home_services_title'       => ['Services — heading', 140, false],
    'home_projects_eyebrow'     => ['Projects — small orange label', 40, false],
    'home_projects_title'       => ['Projects — heading', 140, false],
    'home_process_eyebrow'      => ['How we work — small orange label', 40, false],
    'home_process_title'        => ['How we work — heading', 140, false],
    'home_certs_eyebrow'        => ['Certifications — small orange label', 40, false],
    'home_certs_title'          => ['Certifications — heading', 140, false],
    'home_testimonials_eyebrow' => ['Testimonials — small orange label', 40, false],
    'home_testimonials_title'   => ['Testimonials — heading', 140, false],
    'home_blog_eyebrow'         => ['Blog — small orange label', 40, false],
    'home_blog_title'           => ['Blog — heading', 140, false],
    'home_cta_heading'          => ['Call to action — heading', 140, false],
    'home_cta_text'             => ['Call to action — text', 300, true],
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'company';

$socials = [
    'facebook_url'  => ['Facebook', 'https://www.facebook.com/yourpage'],
    'instagram_url' => ['Instagram', 'https://www.instagram.com/yourname'],
    'linkedin_url'  => ['LinkedIn', 'https://www.linkedin.com/company/yourcompany'],
    'youtube_url'   => ['YouTube', 'https://www.youtube.com/@yourchannel'],
    'tiktok_url'    => ['TikTok', 'https://www.tiktok.com/@yourname'],
    'x_url'         => ['X (Twitter)', 'https://x.com/yourname'],
];

$errors = [];
$values = null; // submitted values when re-showing a form with errors

// ---------------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash('error', 'Your session expired, so nothing was saved. Please try again.');
        redirect('/admin/settings?tab=' . $tab);
    }
    $save = [];

    if ($tab === 'company') {
        $save = [
            'company_name'  => post_str('company_name'),
            'tagline'       => post_str('tagline'),
            'company_intro' => post_str('company_intro'),
            'owner_name'    => post_str('owner_name'),
            'address'       => post_str('address'),
            'phone'         => post_str('phone'),
            'whatsapp'      => preg_replace('/\D+/', '', post_str('whatsapp')) ?? '',
            'email'         => strtolower(post_str('email')),
            'office_hours'  => post_str('office_hours'),
            'map_embed_url' => post_str('map_embed_url'),
            'logo_id'                => (string) (post_media_id('logo_id') ?? ''),
            'favicon_id'             => (string) (post_media_id('favicon_id') ?? ''),
            'company_profile_pdf_id' => (string) (post_media_id('company_profile_pdf_id', 'document') ?? ''),
        ];
        if ($save['company_name'] === '') {
            $errors['company_name'] = 'Please enter the company name.';
        }
        foreach (['company_name' => 120, 'tagline' => 120, 'company_intro' => 400, 'owner_name' => 120, 'address' => 300, 'phone' => 40, 'office_hours' => 120] as $k => $max) {
            if (!isset($errors[$k]) && mb_strlen($save[$k]) > $max) {
                $errors[$k] = 'This is too long (maximum ' . $max . ' characters).';
            }
        }
        if ($save['phone'] !== '' && !preg_match('/^[\d\s()+-]{7,20}$/', $save['phone'])) {
            $errors['phone'] = 'Please enter a phone number using digits, spaces, + or dashes only (e.g. 0300 5741542).';
        }
        // WhatsApp: accept 0300…, +92 300…, 92300…; store as 923001234567
        if ($save['whatsapp'] !== '') {
            if (str_starts_with($save['whatsapp'], '0092')) {
                $save['whatsapp'] = substr($save['whatsapp'], 2);
            } elseif (str_starts_with($save['whatsapp'], '0')) {
                $save['whatsapp'] = '92' . substr($save['whatsapp'], 1);
            }
            if (!preg_match('/^\d{10,15}$/', $save['whatsapp'])) {
                $errors['whatsapp'] = 'Please enter a full mobile number, e.g. 0300 1234567 or +92 300 1234567.';
            }
        }
        if ($save['email'] !== '' && !filter_var($save['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        // Google Maps: accept the whole <iframe …> code or just its link.
        if ($save['map_embed_url'] !== '') {
            if (preg_match('/src=["\']([^"\']+)["\']/i', $save['map_embed_url'], $mm)) {
                $save['map_embed_url'] = html_entity_decode($mm[1]);
            }
            if (!preg_match('#^https://(www\.)?google\.com/maps/embed\?[^\s"<>]+$#', $save['map_embed_url'])) {
                $errors['map_embed_url'] = 'Please paste the “Embed a map” code from Google Maps (it starts with <iframe src="https://www.google.com/maps/embed?…).';
            }
        }
    }

    if ($tab === 'social') {
        foreach ($socials as $key => [$label]) {
            $url = post_str($key);
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || mb_strlen($url) > 255)) {
                $errors[$key] = 'Please enter the full web address of your ' . $label . ' page.';
            }
            $save[$key] = $url;
        }
    }

    if ($tab === 'hero') {
        $save = [
            'hero_headline'     => post_str('hero_headline'),
            'hero_subheadline'  => post_str('hero_subheadline'),
            'hero_button1_text' => post_str('hero_button1_text'),
            'hero_button1_link' => post_str('hero_button1_link'),
            'hero_button2_text' => post_str('hero_button2_text'),
            'hero_button2_link' => post_str('hero_button2_link'),
            'hero_image_id'     => (string) (post_media_id('hero_image_id') ?? ''),
        ];
        if ($save['hero_headline'] === '') {
            $errors['hero_headline'] = 'Please enter a headline.';
        } elseif (mb_strlen($save['hero_headline']) > 120) {
            $errors['hero_headline'] = 'The headline is too long (maximum 120 characters).';
        }
        if (mb_strlen($save['hero_subheadline']) > 300) {
            $errors['hero_subheadline'] = 'The sub-headline is too long (maximum 300 characters).';
        }
        foreach ([1, 2] as $b) {
            $t = "hero_button{$b}_text";
            $l = "hero_button{$b}_link";
            if (mb_strlen($save[$t]) > 40) {
                $errors[$t] = 'Button text is too long (maximum 40 characters).';
            }
            if ($save[$t] !== '' && $save[$l] === '') {
                $errors[$l] = 'Please add where this button should go (e.g. /contact).';
            } elseif (!is_safe_link($save[$l])) {
                $errors[$l] = 'Please use a page on this site (e.g. /contact), a full https:// link, tel: or mailto:.';
            }
        }
    }

    if ($tab === 'seo') {
        $save = [
            'home_meta_title'          => post_str('home_meta_title'),
            'default_meta_description' => post_str('default_meta_description'),
            'default_og_image_id'      => (string) (post_media_id('default_og_image_id') ?? ''),
        ];
        if (mb_strlen($save['home_meta_title']) > 160) {
            $errors['home_meta_title'] = 'Too long (maximum 160 characters; about 60 is best).';
        }
        if (mb_strlen($save['default_meta_description']) > 300) {
            $errors['default_meta_description'] = 'Too long (maximum 300 characters; 120–160 is best).';
        }
    }

    if ($tab === 'status') {
        $save = ['site_live' => isset($_POST['site_live']) ? '1' : '0'];
    }

    if ($tab === 'home') {
        foreach ($homeTexts as $key => [$label, $max]) {
            $save[$key] = post_str($key);
            if (mb_strlen($save[$key]) > $max) {
                $errors[$key] = '“' . $label . '” is too long (maximum ' . $max . ' characters).';
            }
        }
        $save['home_about_image_id'] = (string) (post_media_id('home_about_image_id') ?? '');
        // How we work: ordered steps (title + text), stored as JSON.
        $steps = [];
        $stepRows = [];
        foreach ((array) ($_POST['process'] ?? []) as $key => $st) {
            if (!is_array($st)) {
                continue;
            }
            $row = ['key' => (string) $key, 'title' => trim((string) ($st['title'] ?? '')), 'text' => trim((string) ($st['text'] ?? '')), 'remove' => !empty($st['remove'])];
            $stepRows[] = $row;
            if ($row['remove'] || ($row['title'] === '' && $row['text'] === '')) {
                continue;
            }
            $n = count($steps) + 1;
            if ($row['title'] === '') {
                $errors['process-' . $key . '-title'] = 'Step ' . $n . ': please add a short title.';
            } elseif (mb_strlen($row['title']) > 80) {
                $errors['process-' . $key . '-title'] = 'Step ' . $n . ': the title is too long (maximum 80 characters).';
            }
            if (mb_strlen($row['text']) > 300) {
                $errors['process-' . $key . '-text'] = 'Step ' . $n . ': the text is too long (maximum 300 characters).';
            }
            $steps[] = ['title' => $row['title'], 'text' => $row['text']];
        }
        if (count($steps) > 8) {
            $errors['process'] = 'Please use at most 8 steps.';
        }
        $save['home_process'] = json_encode($steps, JSON_UNESCAPED_UNICODE);
    }

    if ($tab === 'counters') {
        $rows = [];
        $pos = 0;
        foreach ((array) ($_POST['counters'] ?? []) as $key => $c) {
            if (!is_array($c)) {
                continue;
            }
            $row = [
                'key'     => (string) $key,
                'id'      => (int) ($c['id'] ?? 0),
                'label'   => trim((string) ($c['label'] ?? '')),
                'value'   => trim((string) ($c['value'] ?? '')),
                'suffix'  => trim((string) ($c['suffix'] ?? '')),
                'visible' => !empty($c['visible']),
                'remove'  => !empty($c['remove']),
            ];
            if (!$row['remove'] && $row['id'] === 0 && $row['label'] === '' && $row['value'] === '') {
                continue; // empty "new" row
            }
            if (!$row['remove']) {
                if ($row['label'] === '') {
                    $errors['counters-' . $key . '-label'] = 'Counter ' . ($pos + 1) . ': please add a label (e.g. “Projects completed”).';
                } elseif (mb_strlen($row['label']) > 100) {
                    $errors['counters-' . $key . '-label'] = 'Counter ' . ($pos + 1) . ': the label is too long (maximum 100 characters).';
                }
                $num = str_replace([',', ' '], '', $row['value']);
                if (!preg_match('/^\d{1,9}$/', $num)) {
                    $errors['counters-' . $key . '-value'] = 'Counter ' . ($pos + 1) . ': the number must be a whole number like 250.';
                }
                $row['value'] = $num;
                if (mb_strlen($row['suffix']) > 10) {
                    $errors['counters-' . $key . '-suffix'] = 'Counter ' . ($pos + 1) . ': the suffix is too long (maximum 10 characters, e.g. “+”).';
                }
                $pos++;
            }
            $rows[] = $row;
        }
        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            $order = 0;
            foreach ($rows as $row) {
                if ($row['remove']) {
                    if ($row['id'] > 0) {
                        $pdo->prepare('DELETE FROM counters WHERE id = ?')->execute([$row['id']]);
                    }
                    continue;
                }
                $order += 10;
                $params = [$row['label'], (int) $row['value'], $row['suffix'] !== '' ? $row['suffix'] : null, $row['visible'] ? 'published' : 'draft', $order];
                if ($row['id'] > 0) {
                    $pdo->prepare('UPDATE counters SET label = ?, value = ?, suffix = ?, status = ?, sort_order = ? WHERE id = ?')->execute([...$params, $row['id']]);
                } else {
                    $pdo->prepare('INSERT INTO counters (label, value, suffix, status, sort_order) VALUES (?, ?, ?, ?, ?)')->execute($params);
                }
            }
            $pdo->commit();
            flash('success', 'Counters saved.');
            redirect('/admin/settings?tab=counters');
        }
        $values = $rows;
    } elseif (!$errors) {
        save_settings($save, $tab);
        flash('success', $tabs[$tab] . ' settings saved.');
        redirect('/admin/settings?tab=' . $tab);
    } else {
        $values = $save + (isset($stepRows) ? ['__process_rows' => $stepRows] : []);
    }
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------
$s = $values ?? settings_all();
$val = static fn (string $k): string => (string) ($s[$k] ?? '');
$med = static fn (string $k): ?array => (int) ($s[$k] ?? 0) > 0 ? media_find((int) $s[$k]) : null;

admin_page_start('Site Settings', 'settings', $admin);
?>
  <nav class="tabs" aria-label="Settings sections">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="/admin/settings?tab=<?= e($key) ?>"<?= $key === $tab ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?= form_error_summary($errors) ?>

  <form method="post" action="/admin/settings?tab=<?= e($tab) ?>" class="settings-form" data-dirty-check novalidate>
    <?= csrf_field() ?>

<?php if ($tab === 'company'): ?>
    <section class="card form">
      <h2>Company details</h2>
      <div class="form-grid">
        <?= field_text('company_name', 'Company name', $val('company_name'), ['required' => true, 'max' => 120], $errors) ?>
        <?= field_text('tagline', 'Tagline', $val('tagline'), ['max' => 120, 'help' => 'Short slogan shown with the logo.'], $errors) ?>
        <?= field_text('owner_name', 'Owner name', $val('owner_name'), ['max' => 120], $errors) ?>
        <?= field_text('office_hours', 'Office hours', $val('office_hours'), ['max' => 120, 'placeholder' => 'Mon–Sat, 9:00 am – 6:00 pm'], $errors) ?>
      </div>
      <?= field_textarea('company_intro', 'Short introduction', $val('company_intro'), ['max' => 400, 'rows' => 3, 'counter' => true, 'help' => 'One or two sentences about the company, used in the footer and About section.'], $errors) ?>
    </section>
    <section class="card form">
      <h2>Contact</h2>
      <?= field_textarea('address', 'Full address', $val('address'), ['max' => 300, 'rows' => 2], $errors) ?>
      <div class="form-grid">
        <?= field_text('phone', 'Phone', $val('phone'), ['type' => 'tel', 'max' => 40, 'placeholder' => '0300 1234567'], $errors) ?>
        <?= field_text('whatsapp', 'WhatsApp number', $val('whatsapp'), ['type' => 'tel', 'max' => 20, 'placeholder' => '0300 1234567', 'help' => 'Used for the WhatsApp button. Saved in international format (92…).'], $errors) ?>
        <?= field_text('email', 'Email', $val('email'), ['type' => 'email', 'max' => 190, 'autocomplete' => 'off'], $errors) ?>
      </div>
      <?= field_textarea('map_embed_url', 'Google Maps location', $val('map_embed_url'), ['rows' => 3, 'help' => 'In Google Maps: find your office › Share › Embed a map › Copy HTML, then paste it here.'], $errors) ?>
    </section>
    <section class="card form">
      <h2>Logo &amp; files</h2>
      <div class="form-grid">
        <?= field_media('logo_id', 'Logo', $med('logo_id'), ['help' => 'PNG or WebP with a transparent background works best.'], $errors) ?>
        <?= field_media('favicon_id', 'Favicon (browser tab icon)', $med('favicon_id'), ['help' => 'A square image, at least 512 × 512 px.'], $errors) ?>
      </div>
      <?= field_media('company_profile_pdf_id', 'Company profile PDF', $med('company_profile_pdf_id'), ['accept' => 'document', 'help' => 'Visitors can open or download it from the website.'], $errors) ?>
    </section>

<?php elseif ($tab === 'social'): ?>
    <section class="card form">
      <h2>Social media links</h2>
      <p class="help">All optional. Paste the full address of each page. Empty ones are hidden on the website.</p>
      <div class="form-grid">
        <?php foreach ($socials as $key => [$label, $ph]): ?>
          <?= field_text($key, $label, $val($key), ['type' => 'url', 'max' => 255, 'placeholder' => $ph], $errors) ?>
        <?php endforeach; ?>
      </div>
    </section>

<?php elseif ($tab === 'hero'): ?>
    <section class="card form">
      <h2>Home page hero</h2>
      <p class="help">The large first section visitors see on the home page.</p>
      <?= field_text('hero_headline', 'Main headline', $val('hero_headline'), ['required' => true, 'max' => 120, 'counter' => true], $errors) ?>
      <?= field_textarea('hero_subheadline', 'Sub-headline', $val('hero_subheadline'), ['max' => 300, 'rows' => 2, 'counter' => true], $errors) ?>
      <div class="form-grid">
        <?= field_text('hero_button1_text', 'Button 1 text', $val('hero_button1_text'), ['max' => 40, 'placeholder' => 'Our Services'], $errors) ?>
        <?= field_text('hero_button1_link', 'Button 1 link', $val('hero_button1_link'), ['max' => 255, 'placeholder' => '/services', 'help' => 'A page on this site like /services, or a full https:// link.'], $errors) ?>
        <?= field_text('hero_button2_text', 'Button 2 text', $val('hero_button2_text'), ['max' => 40, 'placeholder' => 'Contact Us'], $errors) ?>
        <?= field_text('hero_button2_link', 'Button 2 link', $val('hero_button2_link'), ['max' => 255, 'placeholder' => '/contact'], $errors) ?>
      </div>
      <?= field_media('hero_image_id', 'Background image', $med('hero_image_id'), ['help' => 'A wide, high-quality photo (at least 1920 px wide). Text is placed on top of it.'], $errors) ?>
    </section>

<?php elseif ($tab === 'counters'):
    $rows = $values ?? array_map(static fn ($c) => [
        'key' => 'c' . $c['id'], 'id' => (int) $c['id'], 'label' => $c['label'], 'value' => (string) $c['value'],
        'suffix' => (string) $c['suffix'], 'visible' => $c['status'] === 'published', 'remove' => false,
    ], db()->query('SELECT * FROM counters ORDER BY sort_order, id')->fetchAll());
    $counterRow = static function (array $c) use ($errors): string {
        $k = e($c['key']);
        $err = static fn (string $f) => isset($errors['counters-' . $c['key'] . '-' . $f]) ? ' aria-invalid="true"' : '';
        return '<li class="counter-row" data-repeat-row>'
            . '<input type="hidden" name="counters[' . $k . '][id]" value="' . (int) $c['id'] . '">'
            . '<label class="counter-row__value"><span>Number</span><input id="f-counters-' . $k . '-value" name="counters[' . $k . '][value]" value="' . e($c['value']) . '" inputmode="numeric" placeholder="250"' . $err('value') . '></label>'
            . '<label class="counter-row__suffix"><span>Suffix</span><input id="f-counters-' . $k . '-suffix" name="counters[' . $k . '][suffix]" value="' . e($c['suffix']) . '" maxlength="10" placeholder="+"' . $err('suffix') . '></label>'
            . '<label class="counter-row__label"><span>Label</span><input id="f-counters-' . $k . '-label" name="counters[' . $k . '][label]" value="' . e($c['label']) . '" maxlength="100" placeholder="Projects completed"' . $err('label') . '></label>'
            . '<label class="counter-row__check"><input type="checkbox" name="counters[' . $k . '][visible]" value="1"' . ($c['visible'] ? ' checked' : '') . '> Show</label>'
            . '<span class="counter-row__tools">'
            . '<button type="button" class="icon-btn" data-row-move="-1" title="Move up">' . icon_svg('up', 18) . '<span class="visually-hidden">Move up</span></button>'
            . '<button type="button" class="icon-btn" data-row-move="1" title="Move down">' . icon_svg('down', 18) . '<span class="visually-hidden">Move down</span></button>'
            . '<label class="counter-row__remove" title="Remove"><input type="checkbox" name="counters[' . $k . '][remove]" value="1"' . ($c['remove'] ? ' checked' : '') . ' data-row-remove> Remove</label>'
            . '</span></li>';
    };
    $blank = ['id' => 0, 'label' => '', 'value' => '', 'suffix' => '+', 'visible' => true, 'remove' => false];
    ?>
    <section class="card form">
      <h2>Animated counters</h2>
      <p class="help">The big numbers that count up on the home page (e.g. “250+ Projects completed”). Untick “Show” to hide one without deleting it.</p>
      <div data-repeat>
        <ul class="counter-list" data-repeat-list>
          <?php foreach ($rows as $c) { echo $counterRow($c); } ?>
          <?= $counterRow(['key' => 'new1'] + $blank) ?>
        </ul>
        <template data-repeat-template><?= $counterRow(['key' => '__KEY__'] + $blank) ?></template>
        <button type="button" class="btn btn--ghost btn--small" data-repeat-add><?= icon_svg('plus', 18) ?><span>Add counter</span></button>
      </div>
    </section>

<?php elseif ($tab === 'status'): $isLive = $val('site_live') === '1'; ?>
    <section class="card form">
      <h2>Is the new website live?</h2>
      <div class="notice <?= $isLive ? 'notice--ok' : 'notice--info' ?>" role="status">
        <?php if ($isLive): ?>
          <p><strong>The new website is LIVE.</strong> Everyone sees it.</p>
        <?php else: ?>
          <p><strong>Preview mode.</strong> Visitors still see the “coming soon” page. Only you (while logged in to the admin) see the new website, with a small “Preview mode” bar at the top.</p>
        <?php endif; ?>
      </div>
      <?= field_switch('site_live', 'Website is live (everyone can see the new website)', $isLive, ['help' => 'Turn this on when you are happy with the content. You can turn it off again at any time.']) ?>
      <p><a class="btn btn--ghost" href="/" target="_blank" rel="noopener"><?= icon_svg('eye', 18) ?><span><?= $isLive ? 'View the website' : 'Preview the new website' ?></span></a></p>
    </section>

<?php elseif ($tab === 'home'):
    $stepRows = $values['__process_rows'] ?? array_map(static fn ($st, $i) => ['key' => 's' . $i, 'title' => (string) ($st['title'] ?? ''), 'text' => (string) ($st['text'] ?? ''), 'remove' => false],
        $list = (array) json_decode($val('home_process') ?: '[]', true), array_keys($list));
    $stepRow = static function (array $st) use ($errors): string {
        $k = e($st['key']);
        $err = static fn (string $f) => isset($errors['process-' . $st['key'] . '-' . $f]) ? ' aria-invalid="true"' : '';
        return '<li class="process-row" data-repeat-row>'
            . '<label class="process-row__title"><span>Step title</span><input id="f-process-' . $k . '-title" name="process[' . $k . '][title]" value="' . e($st['title']) . '" maxlength="80" placeholder="Site survey & costing"' . $err('title') . '></label>'
            . '<label class="process-row__text"><span>Short description</span><textarea id="f-process-' . $k . '-text" name="process[' . $k . '][text]" rows="2" maxlength="300"' . $err('text') . '>' . e($st['text']) . '</textarea></label>'
            . '<span class="counter-row__tools">'
            . '<button type="button" class="icon-btn" data-row-move="-1" title="Move up">' . icon_svg('up', 18) . '<span class="visually-hidden">Move up</span></button>'
            . '<button type="button" class="icon-btn" data-row-move="1" title="Move down">' . icon_svg('down', 18) . '<span class="visually-hidden">Move down</span></button>'
            . '<label class="counter-row__remove"><input type="checkbox" name="process[' . $k . '][remove]" value="1"' . ($st['remove'] ? ' checked' : '') . ' data-row-remove> Remove</label>'
            . '</span></li>';
    };
    $blankStep = ['title' => '', 'text' => '', 'remove' => false];
    $field = static fn (string $key) => $homeTexts[$key][2]
        ? field_textarea($key, $homeTexts[$key][0], $val($key), ['max' => $homeTexts[$key][1], 'rows' => 2, 'counter' => true], $errors)
        : field_text($key, $homeTexts[$key][0], $val($key), ['max' => $homeTexts[$key][1]], $errors);
    ?>
    <section class="card form">
      <h2>Home page sections</h2>
      <p class="help">Headings and short texts of each section on the home page. The orange “small label” appears above each heading. Sections without content (e.g. no blog posts yet) are hidden automatically.</p>
      <?= $field('home_clients_title') ?>
    </section>
    <section class="card form">
      <h2>About section</h2>
      <p class="help">The text comes from “Short introduction” in the Company tab.</p>
      <div class="form-grid"><?= $field('home_about_eyebrow') ?><?= $field('home_about_heading') ?></div>
      <?= field_media('home_about_image_id', 'About image', $med('home_about_image_id'), ['help' => 'A photo of your team, office, warehouse or a project site. Portrait or square photos work best.'], $errors) ?>
    </section>
    <section class="card form">
      <h2>Section headings</h2>
      <div class="form-grid">
        <?php foreach (['services', 'projects', 'certs', 'testimonials', 'blog'] as $sec): ?>
          <?= $field('home_' . $sec . '_eyebrow') ?><?= $field('home_' . $sec . '_title') ?>
        <?php endforeach; ?>
      </div>
    </section>
    <section class="card form">
      <h2>How we work</h2>
      <div class="form-grid"><?= $field('home_process_eyebrow') ?><?= $field('home_process_title') ?></div>
      <p class="help">The steps of your work process, shown as a timeline. 4–5 steps work best.</p>
      <?php if (isset($errors['process'])): ?><p class="field-error"><?= e($errors['process']) ?></p><?php endif; ?>
      <div data-repeat>
        <ol class="process-list" data-repeat-list>
          <?php foreach ($stepRows as $st) { echo $stepRow($st); } ?>
        </ol>
        <template data-repeat-template><?= $stepRow(['key' => '__KEY__'] + $blankStep) ?></template>
        <button type="button" class="btn btn--ghost btn--small" data-repeat-add><?= icon_svg('plus', 18) ?><span>Add step</span></button>
        <noscript><p class="help">To add a step, enable JavaScript.</p></noscript>
      </div>
    </section>
    <section class="card form">
      <h2>Call to action</h2>
      <p class="help">The dark band near the bottom of the home page with Contact and WhatsApp buttons.</p>
      <?= $field('home_cta_heading') ?><?= $field('home_cta_text') ?>
    </section>

<?php else: ?>
    <section class="card form">
      <h2>Search engine &amp; sharing defaults</h2>
      <p class="help">Used on pages that don’t have their own SEO text or image.</p>
      <?= field_text('home_meta_title', 'Home page title (Google headline)', $val('home_meta_title'), ['max' => 160, 'counter' => true, 'recommend' => [30, 65], 'help' => 'The blue headline Google shows for your home page.'], $errors) ?>
      <?= field_textarea('default_meta_description', 'Default meta description', $val('default_meta_description'), ['max' => 300, 'rows' => 3, 'counter' => true, 'recommend' => [120, 160], 'help' => 'The short text Google shows under your website’s name. 120–160 characters is best.'], $errors) ?>
      <?= field_media('default_og_image_id', 'Default share image', $med('default_og_image_id'), ['help' => 'Shown when someone shares a link on WhatsApp, Facebook or LinkedIn. Best size: 1200 × 630 px.'], $errors) ?>
    </section>
<?php endif; ?>

    <div class="form-actions">
      <button type="submit" class="btn"><?= icon_svg('check', 18) ?><span>Save <?= e(strtolower($tabs[$tab])) ?></span></button>
    </div>
  </form>
<?php
admin_page_end();
