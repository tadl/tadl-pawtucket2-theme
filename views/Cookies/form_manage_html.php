<?php
/* ----------------------------------------------------------------------
 * themes/default/views/Cookies/form_manage_html.php
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2021 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */
 
$cookies_by_category = (array)$this->getVar('cookiesByCategory');
$config = $this->getVar('config');
$intro = $config->get("cookiesIntroGlobalValue") ? $this->getVar($config->get("cookiesIntroGlobalValue")) : '';
$intro = $intro ?: $config->get("cookiesFormIntro");
$escape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
?>
<div class="row"><div class="col-sm-12 col-md-offset-2 col-md-8">
    <h1><?= _t('Manage Cookies'); ?></h1>
    <div class="cookieIntro"><?= $intro; ?></div>
    <form id="CookieForm" action="<?= caNavUrl($this->request, '*', '*', 'save'); ?>" method="post">
    <?php foreach ($cookies_by_category as $category_code => $category_info):
        $title = caGetOption('title', $category_info, '');
    ?>
        <section class="tadl-cookie-category">
            <h2><?= $escape($title); ?></h2>
            <p><?= caGetOption('description', $category_info, ''); ?></p>
            <details class="cookieByCategory">
                <summary class="cookieCount"><?= _t('View cookies'); ?> (<?= (int)caGetOption('cookieCount', $category_info, 0); ?>)</summary>
                <dl class="tadl-cookie-list">
                    <?php foreach ((array)$category_info['cookies'] as $cookie_info): ?>
                    <dt><?= $escape(caGetOption('name', $cookie_info, '')); ?></dt>
                    <dd><?= caGetOption('description', $cookie_info, ''); ?></dd>
                    <?php endforeach; ?>
                </dl>
            </details>
            <?php if (!caGetOption('required', $category_info, false)):
                $allow = (bool)CookieOptionsManager::allow($category_code);
                $name = 'cookie_options_'.$category_code;
            ?>
            <fieldset class="tadl-cookie-options">
                <legend><?= _t('Allow %1', $escape($title)); ?></legend>
                <label><input type="radio" name="<?= $escape($name); ?>" value="1"<?= $allow ? ' checked' : ''; ?>> <?= _t('On'); ?></label>
                <label><input type="radio" name="<?= $escape($name); ?>" value="0"<?= !$allow ? ' checked' : ''; ?>> <?= _t('Off'); ?></label>
            </fieldset>
            <?php else: ?>
            <p><?= _t('Required cookies are always enabled.'); ?></p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
        <button type="submit" class="btn btn-default"><?= _t('Update'); ?></button>
        <button type="submit" class="btn btn-default" name="accept_all" value="1"><?= _t('Accept All'); ?></button>
    </form>
</div></div>
