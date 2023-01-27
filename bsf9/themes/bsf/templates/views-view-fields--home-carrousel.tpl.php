<?php

/**
* @file
* Default simple view template to all the fields as a row.
*
* - $view: The view in use.
* - $fields: an array of $field objects. Each one contains:
*   - $field->content: The output of the field.
*   - $field->raw: The raw data for the field, if it exists. This is NOT output safe.
*   - $field->class: The safe class id to use.
*   - $field->handler: The Views field handler object controlling this field. Do not use
*     var_export to dump this object, as it can't handle the recursion.
*   - $field->inline: Whether or not the field should be inline.
*   - $field->inline_html: either div or span based on the above flag.
*   - $field->wrapper_prefix: A complete wrapper containing the inline_html to use.
*   - $field->wrapper_suffix: The closing tag for the wrapper.
*   - $field->separator: an optional separator that may appear before a field.
*   - $field->label: The wrap label text to use.
*   - $field->label_html: The full HTML of the label to use including
*     configured element type.
* - $row: The raw result object from the query, with all data it fetched.
*
* @ingroup views_templates
*/
//var_dump($row->field_field_link[0]);

$detect = mobile_detect_get_object();
$is_mobile = $detect->isMobile();

if ($is_mobile){
   $imagen = $row->field_field_slider_image[0]['rendered'];
}else{
   $imagen  = $row->field_field_slider_main_img[0]['rendered'];
}


?>

<div class="row" style="background-image: url(<?php print $imagen;?>)">
<div class="col-xs-12 col-sm-6 slider-content-wrapper">
    <div class="slider-content">
        <div class="inner-box">
            <h2><?php print $row->node_title;?></h2>
            <?php print $row->field_body[0]['rendered'];?>
            <a data-event-category="Home" data-event-action="Inbound"
               data-event-label="TRAFFIC_SliderHome_[<?php print $row->node_title;?>]_ES"
               href="<?php print $row->field_field_link[0]['rendered']['#markup'];?>" class="btn btn-bsf1">Ver más</a>
        </div>
    </div>
</div>
</div>