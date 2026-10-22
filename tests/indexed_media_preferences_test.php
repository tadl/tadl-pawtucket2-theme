<?php
/** The complete eligibility/access suite also runs over generated identity columns. */
define('TADL_TEST_INDEXED_MEDIA', true);
require __DIR__.'/media_preferences_test.php';
testAssert((bool)array_filter(Db::$queries, static fn($query) => str_contains($query['sql'], 'r.tadl_media_fingerprint')
    && str_contains($query['sql'], 'r.tadl_has_media = 1') && str_contains($query['sql'], 'FORCE INDEX (i_tadl_media_identity)')),
    'The indexed suite must exercise the actual covering-index SQL.');
