<?php
/**
 * Called by $core->call('example/template.html', ['name' => $name])
 */
/* @var ki100\Core $this */
/* @var $name */
?>

<?= $this->call('header.html') ?>

<h1>Hello, <?= $this->h($name) ?></h1>

<p>There are <?= count($this->getPlugins()) ?> plugins!</p>

<?= $this->call('footer.html') ?>