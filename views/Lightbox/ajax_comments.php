<?php
/** ---------------------------------------------------------------------
 * themes/default/Lightbox/ajax_comments.php :
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2015 Whirl-i-Gig
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
 * @package CollectiveAccess
 * @subpackage theme/default
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */
    $vb_close           = $this->getVar("close");
    $vs_message         = $this->getVar("message");
    $vs_error           = $this->getVar("error");
    $vn_item_id         = (int)$this->getVar("item_id");
    $vs_tablename       = $this->getVar("tablename");
    $qr_comments        = $this->getVar("comments");
    $vn_num_comments    = $qr_comments ? $qr_comments->numHits() : 0;
    $t_set              = $this->getVar("set");

?>
    <div class="pull-right closecomment"><button type="button" aria-label="<?= _t('Close comments'); ?>" onclick='jQuery("#comment{{{item_id}}}").hide(); <?php print ($vs_tablename=="ca_sets") ? "jQuery(\"#lbSetThumbRow".$vn_item_id."\").show();" : ""; ?> return false;' title='<?php print _t("close"); ?>'><span class="glyphicon glyphicon-remove-circle" aria-hidden="true"></span></button></div>
    <div id="lbSetCommentErrors{{{item_id}}}" style="display: none;" class='alert alert-danger' role='alert'></div>
    <p id="lbSetCommentStatus{{{item_id}}}" class="sr-only" role="status"></p>
    <div class="lbSetCommentHeader" id="lbSetCommentHeader{{{item_id}}}" <?php print ($vn_num_comments == 0 ? 'style="display: none;"' : ''); ?>><span class="lbSetCommentsCount" id="lbSetCommentHeader{{{item_id}}}Count"><?php print $vn_num_comments." ".(($vn_num_comments == 1) ? _t("comment") : _t("comments")); ?></span></div>
    <div class="lbComments" id="lbSetComments{{{item_id}}}">
<?php
        if($qr_comments && $qr_comments->numHits()){
                while ($qr_comments->nextHit()) {
                    $this->setVar('comment_id', $qr_comments->get('ca_item_comments.comment_id'));
                    $this->setVar('comment', $qr_comments->get('ca_item_comments.comment'));
                    $this->setVar('author', $qr_comments->get('ca_users.fname') . ' ' . $qr_comments->get('ca_users.lname') . ' ' . $qr_comments->get('ca_item_comments.created_on'));
                    $this->setVar('is_author', $qr_comments->get('ca_item_comments.user_id') == $this->request->user->get("user_id"));
                    print $this->render("Lightbox/set_comment_html.php");
                }
        }
?>
        </div>
        <div>
        <form action="#" id="addComment<?php print $vn_item_id; ?>">
<?php
        print caHTMLHiddenInput('csrfToken', array('value' => caGenerateCSRFToken($this->request)));
        if($vs_error){
            print "<div>".$vs_error."</div>";
        }
?>
            <div>
                <label for="addComment<?php print $vn_item_id; ?>TextArea"><?= _t('Your comment'); ?></label>
                <textarea name="comment" id="addComment<?php print $vn_item_id; ?>TextArea" placeholder="add your comment" class="form-control"></textarea>
            </div>
            <button type="submit" class="pull-right btn btn-default btn-xs"><?= _t('Save'); ?></button>
            <input type="hidden" name="type" value="<?php print htmlspecialchars((string)$vs_tablename, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php print $vn_item_id; ?>">
        </form>
        <div style="clear:both;"></div>
    </div>
    <script type='text/javascript'>
        jQuery(document).ready(function() {
            jQuery('#addComment{{{item_id}}}').on('submit', function(e){
                jQuery.getJSON(
                    '<?php print caNavUrl($this->request, '', 'Lightbox', 'AjaxAddComment', null); ?>',
                    jQuery('#addComment{{{item_id}}}').serialize(), function(data) {
                        if(data.status == 'ok') {
                            jQuery("#lbSetCommentErrors{{{item_id}}}").hide();
                            jQuery("#addComment{{{item_id}}}TextArea").val('');
                            jQuery('#lbSetComments{{{item_id}}}').append(data.comment).show();
                            jQuery('#lbSetCommentStatus{{{item_id}}}').text(<?= json_encode(_t('Comment saved.')); ?>);
                            jQuery('#lbSetCommentHeader{{{item_id}}}').show();
                            jQuery('#lbSetCommentHeader{{{item_id}}}Count').html(data.displayCount);  // update comment count
                            jQuery('#lbSetCommentCount{{{item_id}}}').text(data.count);
                        } else {
                            jQuery("#lbSetCommentErrors{{{item_id}}}").show().text((data.errors || []).join('; '));
                        }
                    }
                ).fail(function () {
                    jQuery('#lbSetCommentErrors{{{item_id}}}').show().text(<?= json_encode(_t('The comment could not be saved. Please try again.')); ?>);
                });
                e.preventDefault();
                return false;
            });

            jQuery("#lbSetComments{{{item_id}}}").on('click', '.lbCommentRemove', function(e) {
                var comment_id = jQuery(this).data("comment_id");
                if(comment_id) {
                    jQuery.getJSON('<?php print caNavUrl($this->request, '', 'Lightbox', 'AjaxDeleteComment'); ?>', {'comment_id': comment_id, 'csrfToken': <?= json_encode(caGenerateCSRFToken($this->request)); ?> }, function(data) {
                        if(data.status == 'ok') {
                            jQuery("#lbSetCommentErrors{{{item_id}}}").hide();
                            jQuery("#lbComments" + data.comment_id).remove();
                            jQuery('#lbSetCommentStatus{{{item_id}}}').text(<?= json_encode(_t('Comment removed.')); ?>);
                            jQuery('#addComment{{{item_id}}}TextArea').trigger('focus');

                            jQuery('#lbSetCommentCount{{{item_id}}}').text(data.count);
                            if (data.count > 0) {
                                jQuery('#lbSetComments{{{item_id}}}, #lbSetCommentHeader{{{item_id}}}').show();
                                jQuery("#lbSetCommentHeader{{{item_id}}}Count").html(data.displayCount);  // update comment count
                            } else {
                                jQuery('#lbSetComments{{{item_id}}}, #lbSetCommentHeader{{{item_id}}}').hide();
                            }
                        } else {
                            jQuery("#lbSetCommentErrors{{{item_id}}}").show().text((data.errors || []).join('; '));
                        }
                    }).fail(function () {
                        jQuery('#lbSetCommentErrors{{{item_id}}}').show().text(<?= json_encode(_t('The comment could not be removed. Please try again.')); ?>);
                    });
                    return false;
                }
            });
        });
    </script>
