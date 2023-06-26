<?php
/**
 * Called by $core->call('template.html', ['content' => $content])
 */
/* @var ki100\Core $core */
/* @var string $content */
/* @var string $title */
?>

<?= $core->call('header.html', ['title' => $title ?? '']) ?>

<?= $content ?>

<?= $core->call('footer.html') ?>
