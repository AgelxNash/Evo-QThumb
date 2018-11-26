<?php
/**
 * @author Agel_Nash <modx@agel-nash.ru>
 *
 * @properties &queue=Use queue?;list;true,false;true
 *
 * @example [[qThumb#make? &input=`[+tvimagename+]` &options=`w_255,h=200` &queue=`false`]]
 *
 * @var EvolutionCMS\Core $modx
 */

$qThumb = new AgelxNash\Evo\QThumb\Make($modx);
$qThumb->init($modx->event->params);

return $qThumb->makeFile();
