<?php /* Shared full-page recovery content; $url, $escape and $translate are supplied by the helper. */ ?>
<section class="tadl-error-content" aria-labelledby="tadl-error-heading">
	<p class="tadl-error-status"><?= $escape($translate($status === 404 ? 'Error 404' : 'Page unavailable')) ?></p>
	<h1 id="tadl-error-heading"><?= $escape($translate($status === 404 ? "We couldn't find that page" : 'We could not load this page')) ?></h1>
	<p class="tadl-error-description"><?= $escape($translate($status === 404
		? 'The link may have changed, or the page may no longer be available. Try a search or explore our collections to find what you need.'
		: 'Please try again later. You can also return to the Local History Collection.')) ?></p>
	<?php if ($status === 404) { ?>
	<form class="tadl-error-search" role="search" aria-label="<?= $escape($translate('Search the Local History Collection')) ?>" method="get" action="<?= $escape($url('MultiSearch', 'Index')) ?>">
		<label for="tadl-error-search-input"><?= $escape($translate('Search local history')) ?></label>
		<div class="tadl-error-search-row">
			<input type="search" id="tadl-error-search-input" name="search" placeholder="<?= $escape($translate('Names, places, dates, or keywords')) ?>" required>
			<button type="submit"><?= $escape($translate('Search')) ?></button>
		</div>
	</form>
	<nav class="tadl-error-links" aria-label="<?= $escape($translate('Explore the collection')) ?>">
		<a class="tadl-error-browse" href="<?= $escape($url('Collections', 'Index')) ?>"><?= $escape($translate('Browse collections')) ?></a>
		<a href="<?= $escape($url('Search', 'advanced/objects')) ?>" rel="nofollow"><?= $escape($translate('Advanced search')) ?></a>
		<a href="<?= $escape($url('Front', 'Index')) ?>"><?= $escape($translate('Return to home')) ?></a>
	</nav>
	<?php } else { ?>
	<p><a href="<?= $escape($url('Front', 'Index')) ?>"><?= $escape($translate('Return to the Local History Collection')) ?></a></p>
	<?php } ?>
</section>
