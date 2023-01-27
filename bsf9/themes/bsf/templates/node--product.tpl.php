<?php
/**
 * @file
 * Default theme implementation to display a node.
 *
 * Available variables:
 * - $title: the (sanitized) title of the node.
 * - $content: An array of node items. Use render($content) to print them all,
 *   or print a subset such as render($content['field_example']). Use
 *   hide($content['field_example']) to temporarily suppress the printing of a
 *   given element.
 * - $user_picture: The node author's picture from user-picture.tpl.php.
 * - $date: Formatted creation date. Preprocess functions can reformat it by
 *   calling format_date() with the desired parameters on the $created variable.
 * - $name: Themed username of node author output from theme_username().
 * - $node_url: Direct URL of the current node.
 * - $display_submitted: Whether submission information should be displayed.
 * - $submitted: Submission information created from $name and $date during
 *   template_preprocess_node().
 * - $classes: String of classes that can be used to style contextually through
 *   CSS. It can be manipulated through the variable $classes_array from
 *   preprocess functions. The default values can be one or more of the
 *   following:
 *   - node: The current template type; for example, "theming hook".
 *   - node-[type]: The current node type. For example, if the node is a
 *     "Blog entry" it would result in "node-blog". Note that the machine
 *     name will often be in a short form of the human readable label.
 *   - node-teaser: Nodes in teaser form.
 *   - node-preview: Nodes in preview mode.
 *   The following are controlled through the node publishing options.
 *   - node-promoted: Nodes promoted to the front page.
 *   - node-sticky: Nodes ordered above other non-sticky nodes in teaser
 *     listings.
 *   - node-unpublished: Unpublished nodes visible only to administrators.
 * - $title_prefix (array): An array containing additional output populated by
 *   modules, intended to be displayed in front of the main title tag that
 *   appears in the template.
 * - $title_suffix (array): An array containing additional output populated by
 *   modules, intended to be displayed after the main title tag that appears in
 *   the template.
 *
 * Other variables:
 * - $node: Full node object. Contains data that may not be safe.
 * - $type: Node type; for example, story, page, blog, etc.
 * - $comment_count: Number of comments attached to the node.
 * - $uid: User ID of the node author.
 * - $created: Time the node was published formatted in Unix timestamp.
 * - $classes_array: Array of html class attribute values. It is flattened
 *   into a string within the variable $classes.
 * - $zebra: Outputs either "even" or "odd". Useful for zebra striping in
 *   teaser listings.
 * - $id: Position of the node. Increments each time it's output.
 *
 * Node status variables:
 * - $view_mode: View mode; for example, "full", "teaser".
 * - $teaser: Flag for the teaser state (shortcut for $view_mode == 'teaser').
 * - $page: Flag for the full page state.
 * - $promote: Flag for front page promotion state.
 * - $sticky: Flags for sticky post setting.
 * - $status: Flag for published status.
 * - $comment: State of comment settings for the node.
 * - $readmore: Flags true if the teaser content of the node cannot hold the
 *   main body content.
 * - $is_front: Flags true when presented in the front page.
 * - $logged_in: Flags true when the current user is a logged-in member.
 * - $is_admin: Flags true when the current user is an administrator.
 *
 * Field variables: for each field instance attached to the node a corresponding
 * variable is defined; for example, $node->body becomes $body. When needing to
 * access a field's raw values, developers/themers are strongly encouraged to
 * use these variables. Otherwise they will have to explicitly specify the
 * desired field language; for example, $node->body['en'], thus overriding any
 * language negotiation rule that was previously applied.
 *
 * @see template_preprocess()
 * @see template_preprocess_node()
 * @see template_process()
 *
 * @ingroup templates
 */


//cambiar a gramos
$peso = $node->field_product_weight[LANGUAGE_NONE][0]['value'] * 1000;


?>
<article id="node-<?php print $node->nid; ?>" class="<?php print $classes; ?> clearfix"<?php print $attributes; ?>>
	<div class="row">
		<div class="col-xs-12 col-sm-6">
			<div itemscope itemtype="http://schema.org/Product">
				<span itemprop="name" class="hidden"><?php print $node->title; ?></span>
				<span itemprop="url" class="hidden"><?php echo url('node/' . $nid, array('absolute' => TRUE)); ?></span>
				<?php	echo '<span itemprop="description" class="hidden">'.render($content['body']).'</span>';	?>
				<?php	echo theme('image_style', array(
							'style_name' => 'product',
							'path' => $node->field_product_image['und'][0]['uri'],
							'alt' => check_plain($node->title),
							'width' => $node->field_product_image['und'][0]['width'],
							'height' => $node->field_product_image['und'][0]['height'],
							'attributes' => array('class' => 'img-responsive', 'itemprop' => 'image'),
							));						
				?>
			</div>
		</div>
		<div class="col-xs-12 col-sm-6 mt-100">
			<div class="product-info">
				<h1<?php print $title_attributes; ?>><?php print $node->title; ?></h1>					
				<?php	echo render($content['body']); ?>
				<span class="product-data"><?php print $content['field_product_weight']['#title']; ?>: <?php //echo render($content['field_product_weight']); ?><?php print $peso . t('g');?></span>
				<span class="product-data"><?php print $content['field_product_expiration']['#title']; ?>: <?php echo render($content['field_product_expiration']); ?></span>
				<span class="product-data"><?php print $content['field_product_ean']['#title']; ?>: <?php echo render($content['field_product_ean']); ?></span>								
				<span class="product-header">Ingredientes</span>
				<?php echo '<p>'.render($content['field_product_ingredients']).'</p>';	?>
				<?php if ($node->field_product_nutritional[LANGUAGE_NONE][0]['value']): ?>
				<!--<span class="product-header">Valor nutricional <small>por 100 gramos</small></span>
				<div itemprop="nutrition" itemscope itemtype="http://schema.org/NutritionInformation">
					<dl>
						<dt><?php print $content['field_product_kcal']['#title']; ?>:</dt>
						<dd><span itemprop="calories"><?php echo render($content['field_product_kcal']); ?></span></dd>
						<dt><?php print $content['field_product_fat']['#title']; ?>:</dt>
						<dd><span itemprop="fatContent"><?php echo render($content['field_product_fat']); ?></span></dd>
						<dt><?php print $content['field_product_saturated_fat']['#title']; ?>:</dt>
						<dd><span itemprop="saturatedFatContent"><?php echo render($content['field_product_saturated_fat']); ?></span></dd>
						<dt><?php print $content['field_product_carbohydrates']['#title']; ?>:</dt>
						<dd><span itemprop="carbohydrateContent"><?php echo render($content['field_product_carbohydrates']); ?></span></dd>
						<dt><?php print $content['field_product_sugars']['#title']; ?>:</dt>
						<dd><span itemprop="sugarContent"><?php echo render($content['field_product_sugars']); ?></span></dd>
						<dt><?php print $content['field_product_proteins']['#title']; ?>:</dt>
						<dd><span itemprop="proteinContent"><?php echo render($content['field_product_proteins']); ?></span></dd>
						<dt><?php print $content['field_product_salt']['#title']; ?>:</dt>
						<dd><span itemprop="sodiumContent"><?php echo render($content['field_product_salt']); ?></span></dd>
					</dl>			
				</div>-->
				<?php elseif ($node->nid == 55): ?>
				<!--<span class="product-header">Valor nutricional <small>por 100 gramos</small></span>
				<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
					<div class="panel panel-default">
						<div class="panel-heading" role="tab" id="sandwich_group_heading_1">
							<h3 class="panel-title"><a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#sandwich_group_1" aria-expanded="true" aria-controls="collapseOne">Mini s&aacute;ndwich de at&uacute;n, huevo y pimientos</a></h3>
						</div>
						<div id="sandwich_group_1" class="panel-collapse collapse" role="tabpanel" aria-labelledby="sandwich_group_heading_1">
							<div class="panel-body">
								<div itemprop="nutrition" itemscope itemtype="http://schema.org/NutritionInformation">
									<dl>
										<dt><?php print $content['field_product_kcal']['#title']; ?>:</dt>
										<dd><span itemprop="calories">214cal</span></dd>
										<dt><?php print $content['field_product_fat']['#title']; ?>:</dt>
										<dd><span itemprop="fatContent">11g</span></dd>
										<dt><?php print $content['field_product_saturated_fat']['#title']; ?>:</dt>
										<dd><span itemprop="saturatedFatContent">2g</span></dd>
										<dt><?php print $content['field_product_carbohydrates']['#title']; ?>:</dt>
										<dd><span itemprop="carbohydrateContent">18g</span></dd>
										<dt><?php print $content['field_product_sugars']['#title']; ?>:</dt>
										<dd><span itemprop="sugarContent"></span>2g</dd>
										<dt><?php print $content['field_product_proteins']['#title']; ?>:</dt>
										<dd><span itemprop="proteinContent">11g</span></dd>
										<dt><?php print $content['field_product_salt']['#title']; ?>:</dt>
										<dd><span itemprop="sodiumContent">1,1g</span></dd>
									</dl>			
								</div>
							</div>
						</div>
					</div>-->
					<div class="panel panel-default">
						<div class="panel-heading" role="tab" id="sandwich_group_heading_2">
							<h3 class="panel-title"><a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#sandwich_group_2" aria-expanded="true" aria-controls="collapseOne">Mini s&aacute;ndwich de mozzarella y tomate</a></h3>
						</div>
						<!--<div id="sandwich_group_2" class="panel-collapse collapse" role="tabpanel" aria-labelledby="sandwich_group_heading_2">
							<div class="panel-body">
								<div itemprop="nutrition" itemscope itemtype="http://schema.org/NutritionInformation">
									<dl>
										<dt><?php print $content['field_product_kcal']['#title']; ?>:</dt>
										<dd><span itemprop="calories">200cal</span></dd>
										<dt><?php print $content['field_product_fat']['#title']; ?>:</dt>
										<dd><span itemprop="fatContent">8g</span></dd>
										<dt><?php print $content['field_product_saturated_fat']['#title']; ?>:</dt>
										<dd><span itemprop="saturatedFatContent">5g</span></dd>
										<dt><?php print $content['field_product_carbohydrates']['#title']; ?>:</dt>
										<dd><span itemprop="carbohydrateContent">20g</span></dd>
										<dt><?php print $content['field_product_sugars']['#title']; ?>:</dt>
										<dd><span itemprop="sugarContent"></span>3g</dd>
										<dt><?php print $content['field_product_proteins']['#title']; ?>:</dt>
										<dd><span itemprop="proteinContent">11g</span></dd>
										<dt><?php print $content['field_product_salt']['#title']; ?>:</dt>
										<dd><span itemprop="sodiumContent">1,0g</span></dd>
									</dl>			
								</div>
							</div>
						</div>-->
					</div>
					<div class="panel panel-default">
						<div class="panel-heading" role="tab" id="sandwich_group_heading_3">
							<h3 class="panel-title"><a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#sandwich_group_3" aria-expanded="true" aria-controls="collapseOne">Mini s&aacute;ndwich de york y huevo</a></h3>
						</div>
						<!--<div id="sandwich_group_3" class="panel-collapse collapse" role="tabpanel" aria-labelledby="sandwich_group_heading_3">
							<div class="panel-body">
								<div itemprop="nutrition" itemscope itemtype="http://schema.org/NutritionInformation">
									<dl>
										<dt><?php print $content['field_product_kcal']['#title']; ?>:</dt>
										<dd><span itemprop="calories">223cal</span></dd>
										<dt><?php print $content['field_product_fat']['#title']; ?>:</dt>
										<dd><span itemprop="fatContent">13g</span></dd>
										<dt><?php print $content['field_product_saturated_fat']['#title']; ?>:</dt>
										<dd><span itemprop="saturatedFatContent">2g</span></dd>
										<dt><?php print $content['field_product_carbohydrates']['#title']; ?>:</dt>
										<dd><span itemprop="carbohydrateContent">16g</span></dd>
										<dt><?php print $content['field_product_sugars']['#title']; ?>:</dt>
										<dd><span itemprop="sugarContent">2g</span></dd>
										<dt><?php print $content['field_product_proteins']['#title']; ?>:</dt>
										<dd><span itemprop="proteinContent">11g</span></dd>
										<dt><?php print $content['field_product_salt']['#title']; ?>:</dt>
										<dd><span itemprop="sodiumContent">1,1g</span></dd>
									</dl>		
								</div>
							</div>
						</div>-->
					</div>
					<div class="panel panel-default">
						<div class="panel-heading" role="tab" id="sandwich_group_heading_4">
							<h3 class="panel-title"><a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#sandwich_group_4" aria-expanded="true" aria-controls="collapseOne">Mini s&aacute;ndwich de pavo y camembert</a></h3>
						</div>
						<!--<div id="sandwich_group_4" class="panel-collapse collapse" role="tabpanel" aria-labelledby="sandwich_group_heading_4">
							<div class="panel-body">
								<div itemprop="nutrition" itemscope itemtype="http://schema.org/NutritionInformation">
									<dl>
										<dt><?php print $content['field_product_kcal']['#title']; ?>:</dt>
										<dd><span itemprop="calories">179cal</span></dd>
										<dt><?php print $content['field_product_fat']['#title']; ?>:</dt>
										<dd><span itemprop="fatContent">5g</span></dd>
										<dt><?php print $content['field_product_saturated_fat']['#title']; ?>:</dt>
										<dd><span itemprop="saturatedFatContent">3g</span></dd>
										<dt><?php print $content['field_product_carbohydrates']['#title']; ?>:</dt>
										<dd><span itemprop="carbohydrateContent">21g</span></dd>
										<dt><?php print $content['field_product_sugars']['#title']; ?>:</dt>
										<dd><span itemprop="sugarContent">2g</span></dd>
										<dt><?php print $content['field_product_proteins']['#title']; ?>:</dt>
										<dd><span itemprop="proteinContent">11g</span></dd>
										<dt><?php print $content['field_product_salt']['#title']; ?>:</dt>
										<dd><span itemprop="sodiumContent">1,6</span></dd>
									</dl>		
								</div>
							</div>
						</div>-->
					</div>	
				</div>
				<?php endif; ?>
				<?php
					// Hide comments, tags, and links now so that we can render them later.
					hide($content['comments']);
					hide($content['links']);
					hide($content['field_tags']);
					//print render($content);
				?>
			</div>
		</div>
	</div>
</article>
