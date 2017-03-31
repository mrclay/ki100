<?php
/**
 * Called by $core->call('example/template.html', ['name' => $name])
 */
/* @var ki100\Core $core */
/* @var $name */
?>

<?= $core->call('header.html') ?>

<h1>Hello, <?= $core->h($name) ?></h1>

<p>There are <?= count($core->getPlugins()) ?> plugins!</p>

<?= $core->call('footer.html') ?>