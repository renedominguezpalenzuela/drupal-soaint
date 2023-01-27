<?php
	drupal_add_js(path_to_theme().'/js/webform.js');
	if (!empty($form['actions']) && $form['actions']['submit']) {
    $form['actions']['submit']['#attributes'] = array('class' => array('btn', 'btn-bsf'), 'data-event' => 'event', 'data-event-category' => 'Contacto', 'data-event-action' => 'Form', 'data-event-label' => 'LEAD_FORM_Contacto_ES');
  }
  
  $form['submitted']['name']['#attributes']['onblur'] = "javascript:ga('send', 'event', 'Contacto', 'Form', 'DATA_Name_ES'); ga('gvips.send', 'event', 'Contacto', 'Form', 'DATA_Name_ES');" ;
  $form['submitted']['surname']['#attributes']['onblur'] = "javascript:ga('send', 'event', 'Contacto', 'Form', 'DATA_Surname_ES'); ga('gvips.send', 'event', 'Contacto', 'Form', 'DATA_Surname_ES');" ;
  $form['submitted']['company']['#attributes']['onblur'] = "javascript:ga('send', 'event', 'Contacto', 'Form', 'DATA_Company_ES'); ga('gvips.send', 'event', 'Contacto', 'Form', 'DATA_Company_ES');" ;
  $form['submitted']['address']['#attributes']['onblur'] = "javascript:ga('send', 'event', 'Contacto', 'Form', 'DATA_Address_ES'); ga('gvips.send', 'event', 'Contacto', 'Form', 'DATA_Address_ES');" ;
	
  $form['submitted']['postal_code']['#attributes']['onblur'] = "javascript:ga('send', 'event', 'Contacto', 'Form', 'DATA_Postal_ES'); ga('gvips.send', 'event', 'Contacto', 'Form', 'DATA_Postal_ES');" ;
  $form['submitted']['email']['#attributes']['onblur'] = "javascript:ga('send', 'event', 'Contacto', 'Form', 'DATA_Email_ES'); ga('gvips.send', 'event', 'Contacto', 'Form', 'DATA_Email_ES');" ;
  $form['submitted']['phone']['#attributes']['onblur'] = "javascript:ga('send', 'event', 'Contacto', 'Form', 'DATA_Telephone_ES'); ga('gvips.send', 'event', 'Contacto', 'Form', 'DATA_Telephone_ES');" ;
  $form['submitted']['comments']['#attributes']['onblur'] = "javascript:ga('send', 'event', 'Contacto', 'Form', 'DATA_Comments_ES'); ga('gvips.send', 'event', 'Contacto', 'Form', 'DATA_Comments_ES');" ;
	
  $form['submitted']['legal']['#attributes'] = array('data-event' => 'event', 'data-event-category' => 'Contacto', 'data-event-action' => 'Form', 'data-event-label' => 'DATA_Privacidad_ES');
    
?>
<div class="row">
	<div class="col-xs-12 col-sm-6">
	<?php
		$block = module_invoke('block', 'block_view', '3');
		print render($block['content']);
	?>
	</div>
	<div class="col-xs-12 col-sm-6">
		<div class="row">
			<div class="col-xs-12 col-sm-5">
				<?php print drupal_render($form['submitted']['name']); ?>
			</div>
			<div class="col-xs-12 col-sm-7">
				<?php print drupal_render($form['submitted']['surname']); ?>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-12">		
				<?php print drupal_render($form['submitted']['company']); ?>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-12 col-sm-8">
				<?php print drupal_render($form['submitted']['address']); ?>
			</div>
			<div class="col-xs-12 col-sm-4">
				<?php print drupal_render($form['submitted']['postal_code']); ?>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-12 col-sm-8">
				<?php print drupal_render($form['submitted']['email']); ?>
			</div>
			<div class="col-xs-12 col-sm-4">
				<?php print drupal_render($form['submitted']['phone']); ?>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-12">		
				<?php print drupal_render($form['submitted']['comments']); ?>
			</div>
		</div>		
		<div class="row">
			<div class="col-xs-12">	
            <div class="first-layer">
			 <?php 
			  $node=node_load(78);
			  print $node->body[LANGUAGE_NONE][0]['value'];
			 ?>
            </div>			
				<?php print drupal_render($form['submitted']['legal']); ?>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-12">		
				<small>* Campos obligatorios</small>
			</div>
		</div>		
<?php
  print drupal_render($form['submitted']);
  print drupal_render_children($form);
?>		
	</div>
</div>