<?php
require_once(__DIR__.'/../../helpers/home_faq.php');
$faq_groups = tadlHomeFAQGroups($this->request);
if (!$faq_groups) { return; }
?>
<section class="tadl-home-section tadl-faq-section" aria-labelledby="tadl-faq-heading">
	<h2 class="tadl-section-title" id="tadl-faq-heading"><?= htmlspecialchars(_t('Frequently asked questions'), ENT_QUOTES, 'UTF-8'); ?></h2>
	<div class="tadl-faq-grid">
		<?php foreach ($faq_groups as $category => $questions): ?>
		<div class="tadl-faq-category">
			<h3><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></h3>
			<?php foreach ($questions as $question): ?>
			<details class="tadl-faq-item">
				<summary><?= htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8'); ?></summary>
				<div class="tadl-faq-answer"><?= $question['answer']; ?></div>
			</details>
			<?php endforeach; ?>
		</div>
		<?php endforeach; ?>
	</div>
</section>
