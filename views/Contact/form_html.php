<?php
	$o_config = caGetContactConfig();
	$va_errors = $this->getVar("errors") ?: [];
	$use_turnstile = (bool)$this->getVar('turnstile_active');
	$show_math = !$use_turnstile && $o_config->get('check_security');
	$field_columns = $show_math ? 'col-sm-4' : 'col-sm-6';
	$vn_num1 = rand(1,10);
	$vn_num2 = rand(1,10);
	$vn_sum = $vn_num1 + $vn_num2;
	$vs_page_title = ($o_config->get("contact_page_title")) ? $o_config->get("contact_page_title") : _t("Contact");
	
	# --- if a table has been passed this is coming from the Item Inquiry/Ask An Archivist contact form on detail pages
	$pn_id = $this->request->getParameter("id", pInteger);
	$ps_table = $this->request->getParameter("table", pString);
	
	if($pn_id && $ps_table){
		$t_item = Datamodel::getInstanceByTableName($ps_table);
		if($t_item){
			$t_item->load($pn_id);
			$vs_url = $this->request->config->get("site_host").caDetailUrl($this->request, $ps_table, $pn_id);
			$vs_name = $t_item->get($ps_table.".preferred_labels");
			$vs_idno = $t_item->get($ps_table.".idno");
			$vs_page_title = ($o_config->get("item_inquiry_page_title")) ? $o_config->get("item_inquiry_page_title") : _t("Item Inquiry");
		}
	}
?>
<div class="row"><div class="col-sm-12">
	<H1><?= $vs_page_title; ?></H1>
<?php
	if(!empty($va_errors["display_errors"])){
		print "<div class='alert alert-danger'>".implode("<br/>", $va_errors["display_errors"])."</div>";
	}
?>
	<form id="contactForm" action="<?= caNavUrl($this->request, "", "Contact", "send"); ?>" method="post">
	    <input type="hidden" name="csrfToken" value="<?= caGenerateCSRFToken($this->request); ?>"/>
<?php
	if($pn_id && $t_item->getPrimaryKey()){
?>
		<div class="row">
			<div class="col-sm-12">
				<p><b>Title: </b><?= $vs_name; ?>
				<br/><b>Regarding this URL: </b><a href="<?= $vs_url; ?>" class="purpleLink"><?= $vs_url; ?></a>
				</p>
				<input type="hidden" name="itemId" value="<?= $vs_idno; ?>">
				<input type="hidden" name="itemTitle" value="<?= $vs_name; ?>">
				<input type="hidden" name="itemURL" value="<?= $vs_url; ?>">
				<input type="hidden" name="id" value="<?= $pn_id; ?>">
				<input type="hidden" name="table" value="<?= $ps_table; ?>">
				<hr/><br/><br/>
	
			</div>
		</div>
<?php
	}
?>
		<div class="row">
			<div class="col-md-9">
				<div class="row">
					<div class="<?= $field_columns; ?>">
						<div class="form-group<?= (!empty($va_errors["name"]) ? " has-error" : ""); ?>">
							<label for="name"><?= _t("Your name"); ?></label>
							<input type="text" class="form-control input-sm" placeholder="Enter your name" autocomplete="name" name="name" value="{{{name}}}" id="name">
						</div>
					</div><!-- end col -->
					<div class="<?= $field_columns; ?>">
						<div class="form-group<?= (!empty($va_errors["email"]) ? " has-error" : ""); ?>">
							<label for="email"><?= _t("Email address"); ?></label>
							<input type="text" class="form-control input-sm" id="email" placeholder="Enter email" name="email" value="{{{email}}}">
						</div>
					</div><!-- end col -->
<?php if ($show_math) { ?>
					<div class="col-sm-4">
						<div class="form-group<?= (!empty($va_errors["security"]) ? " has-error" : ""); ?>">
							<label for="security"><?= _t("Security Question"); ?></label>
							<div class='row'>
								<div class='col-sm-6'>
									<p class="form-control-static"><?= $vn_num1; ?> + <?= $vn_num2; ?> = </p>
								</div>
								<div class='col-sm-6'>
									<input name="security" value="" id="security" type="text" class="form-control input-sm" />
								</div>
							</div><!--end row-->	
						</div><!-- end form-group -->
					</div><!-- end col -->
<?php } ?>
				</div><!-- end row -->
			</div><!-- end col -->
		</div><!-- end row -->
		<div class="row">
			<div class="col-md-9">
				<div class="form-group<?= (!empty($va_errors["message"]) ? " has-error" : ""); ?>">
					<label for="message"><?= _t("Message"); ?></label>
					<textarea class="form-control input-sm" id="message" name="message" rows="5">{{{message}}}</textarea>
				</div>
			</div><!-- end col -->
		</div><!-- end row -->
<?php
	if ($use_turnstile && !$this->request->isLoggedIn()) {
		$contact_addresses = (array)$o_config->get('contact_email');
		$help_address = reset($contact_addresses);
?>
		<div class="row"><div class="col-md-9">
			<div class="form-group<?= (!empty($va_errors['turnstile']) ? ' has-error' : ''); ?>">
<?php if ($this->getVar('turnstile_ready')) { ?>
				<div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($this->getVar('turnstile_site_key'), ENT_QUOTES, 'UTF-8'); ?>" data-action="contact" data-theme="light" data-size="flexible"></div>
				<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
				<noscript><p><?= _t('Please enable JavaScript to complete verification, or email us directly.'); ?></p></noscript>
<?php } else { ?>
				<p role="alert"><?= _t('Verification is temporarily unavailable. Please email us directly.'); ?></p>
<?php }
		if (is_string($help_address) && caCheckEmailAddress($help_address)) { ?>
				<p><?= _t('Having trouble?'); ?> <a href="mailto:<?= htmlspecialchars($help_address, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($help_address, ENT_QUOTES, 'UTF-8'); ?></a></p>
<?php } ?>
			</div>
		</div></div>
<?php
	} elseif(!$use_turnstile && !$this->request->isLoggedIn() && defined("__CA_GOOGLE_RECAPTCHA_KEY__") && __CA_GOOGLE_RECAPTCHA_KEY__){
?>
		<script type="text/javascript">
			var gCaptchaRender = function(){
                grecaptcha.render('regCaptcha', {'sitekey': '<?= __CA_GOOGLE_RECAPTCHA_KEY__; ?>'});
        	};
		</script>
		<script src='https://www.google.com/recaptcha/api.js?onload=gCaptchaRender&render=explicit' async defer></script>
			<div class="row">
				<div class="col-sm-12 col-md-offset-1 col-md-10">
					<div class='form-group<?= (!empty($va_errors["recaptcha"]) ? " has-error" : ""); ?>'>
						<div id="regCaptcha" class="col-sm-8 col-sm-offset-4"></div>
					</div>
				</div>
			</div><!-- end row -->
<?php
	}
?>
		<div class="form-group">
			<button type="submit" class="btn btn-default"><?= _t("Send"); ?></button>
		</div><!-- end form-group -->
<?php if ($show_math) { ?>
		<input type="hidden" name="sum" value="<?= $vn_sum; ?>">
<?php } ?>
	</form>
	
</div><!-- end col --></div><!-- end row -->
