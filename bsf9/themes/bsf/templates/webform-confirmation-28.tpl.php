<?php

/**
 * @file
 * Customize confirmation screen after successful submission.
 *
 * This file may be renamed "webform-confirmation-[nid].tpl.php" to target a
 * specific webform e-mail on your site. Or you can leave it
 * "webform-confirmation.tpl.php" to affect all webform confirmations on your
 * site.
 *
 * Available variables:
 * - $node: The node object for this webform.
 * - $confirmation_message: The confirmation message input by the webform author.
 * - $sid: The unique submission ID of this submission.
 */
?>
<div class="row">
	<div class="col-xs-12 col-sm-6 col-sm-offset-3">
		<div class="webform-confirmation highlight">
			<?php if ($confirmation_message): ?>
				<div data-event-category="Contacto" data-event-action="Form" data-event-label="Error_Envio_Contacto"></div><?php print $confirmation_message ?>
			<?php else: ?>
			    <div data-event-category="Contacto" data-event-action="Form" data-event-label="Ok_Envio_Contacto"></div>
			   	<p><?php print t('Thank you, your submission has been received.'); ?></p>
			<?php endif; ?>
			<div class="links bottom-offset top-offset back-btn">
				<a href="<?php print url('node/'. $node->nid) ?>"><i class="fa fa-angle-left"></i> <?php print t('Go back to the form') ?></a>
			</div>			
		</div>
	</div>
</div>